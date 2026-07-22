<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\OtpService;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use App\Modules\User\Services\AccountMergeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Drives the user-initiated "merge another account into this one" flow:
 *
 *   start → challenge (OTP/OAuth) → preview → confirm
 *
 * The challenge proves the user controls the *other* account; on success
 * its id is stashed in the session and the preview screen lists what will
 * move and lets the user pick which paid plan to keep.
 */
class AccountMergeController extends Controller
{
    /** Step 1: ask for the other account's identifier. */
    public function start()
    {
        return view('user.merge.start');
    }

    /** Step 2: send an OTP to the supplied identifier. */
    public function challenge(Request $request)
    {
        $request->validate([
            'kind'  => 'required|in:email,phone',
            'value' => 'required|string|max:255',
        ]);
        $kind  = $request->input('kind');
        $value = LinkedIdentifier::normalize($kind, (string) $request->input('value'));
        $otpType = $kind === 'phone' ? 'mobile' : 'email';

        // Refuse if the identifier already belongs to the signed-in user
        // — there's no "other account" to merge in that case.
        $owner = LinkedIdentifier::resolveUser($kind, $value);
        if ($owner && $owner->id === Auth::id()) {
            return back()->with('error', 'That identifier is already on your current account — nothing to merge.');
        }

        $otp = new OtpService();
        $code = $otp->generate($value, $otpType, 'login', 'web');
        try {
            if ($kind === 'email') $otp->sendEmail($value, $code); else $otp->sendWhatsApp($value, $code);
        } catch (\Throwable $e) {
            \Log::warning('merge challenge OTP send failed: ' . $e->getMessage());
        }

        session([
            'otp_identifier'          => $value,
            'otp_type'                => $otpType,
            'merge_challenge_active'  => true,
            // Bind this challenge to the user who started it so a stale
            // session cookie can't be reused after a different account
            // logs in on the same browser.
            'merge_primary_id'        => Auth::id(),
        ]);
        return redirect()->route('user.otp.verify.form')
            ->with('status', 'Code sent to ' . $value . '. Enter it to continue the merge.')
            ->with('otp_demo_reveal', \App\Modules\Common\Support\AuthMethods::demoRevealMessage($code));
    }

    /** Step 3: show what will move and (if needed) let the user choose a plan. */
    public function preview(AccountMergeService $service)
    {
        $secondaryId = (int) session('merge_secondary_id');
        $primaryBound = (int) session('merge_primary_id');
        if (!$secondaryId || !$primaryBound || $primaryBound !== Auth::id()) {
            session()->forget(['merge_secondary_id', 'merge_primary_id', 'merge_challenge_active']);
            return redirect()->route('user.merge.start')->with('error', 'Merge session expired. Please start again.');
        }
        $primary   = Auth::user();
        $secondary = User::find($secondaryId);
        if (!$secondary || $secondary->id === $primary->id) {
            return redirect()->route('user.merge.start')->with('error', 'The other account could not be found.');
        }
        if ($secondary->roles()->exists() || $primary->roles()->exists()) {
            session()->forget('merge_secondary_id');
            return redirect()->route('user.merge.start')->with('error', 'Admin accounts cannot be merged.');
        }

        $preview = $service->preview($primary, $secondary);
        return view('user.merge.preview', $preview);
    }

    /** Step 4: execute the merge inside a transaction. */
    public function confirm(Request $request, AccountMergeService $service)
    {
        $request->validate(['keep_plan_from' => 'nullable|in:primary,secondary']);

        $secondaryId = (int) session('merge_secondary_id');
        $primaryBound = (int) session('merge_primary_id');
        if (!$secondaryId || !$primaryBound || $primaryBound !== Auth::id()) {
            session()->forget(['merge_secondary_id', 'merge_primary_id', 'merge_challenge_active']);
            return redirect()->route('user.merge.start')->with('error', 'Merge session expired. Please start again.');
        }
        $primary   = Auth::user();
        $secondary = User::find($secondaryId);
        if (!$secondary || $secondary->id === $primary->id) {
            return redirect()->route('user.merge.start')->with('error', 'The other account could not be found.');
        }

        $keep = $request->input('keep_plan_from', 'primary');
        try {
            $summary = $service->merge($primary, $secondary, $keep);
        } catch (\Throwable $e) {
            // Log the technical detail for support, but show the user a
            // generic message — exception text can leak schema or
            // implementation internals.
            \Log::error('Account merge failed', [
                'primary_id'   => $primary->id,
                'secondary_id' => $secondary->id,
                'keep'         => $keep,
                'message'      => $e->getMessage(),
            ]);
            $userMsg = ($e instanceof \InvalidArgumentException || $e instanceof \RuntimeException)
                ? $e->getMessage()
                : 'We couldn\'t complete the merge. No changes were made — please try again or contact support.';
            return redirect()->route('user.merge.start')->with('error', $userMsg);
        }

        session()->forget(['merge_secondary_id', 'merge_primary_id', 'merge_challenge_active']);

        $rowTotal = array_sum($summary['reassigned'] ?? []);
        $msg = "Merge complete — {$rowTotal} record" . ($rowTotal === 1 ? '' : 's')
            . " moved from {$summary['secondary_email']} into your account.";
        if ($keep === 'secondary') {
            $msg .= ' Plan from the merged account is now active; the previous plan was cancelled with no refund.';
        }
        return redirect()->route('user.identifiers.index')->with('success', $msg);
    }

    /** Cancel an in-flight merge session. */
    public function cancel()
    {
        session()->forget(['merge_secondary_id', 'merge_primary_id', 'merge_challenge_active']);
        return redirect()->route('user.identifiers.index')->with('status', 'Merge cancelled.');
    }
}
