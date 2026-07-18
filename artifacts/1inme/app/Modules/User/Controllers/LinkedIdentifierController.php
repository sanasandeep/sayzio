<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\OtpService;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Services\AccountMergeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Manages the "Linked identifiers" section of Account Settings:
 * list, add (with OTP verification), remove non-primary, and
 * promote-to-primary actions for emails and phone numbers.
 *
 * Social-provider linking is handled by SocialOAuthController's
 * regular connect/callback flow, which now also writes a
 * LinkedIdentifier row alongside the SocialAccountConnection.
 */
class LinkedIdentifierController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $identifiers = $user->linkedIdentifiers()->get();
        $pending = session('linked_identifier_pending');
        return view('user.identifiers.index', compact('user', 'identifiers', 'pending'));
    }

    /** Begin adding a new email or phone — sends a verification OTP. */
    public function start(Request $request)
    {
        $request->validate([
            'kind'  => 'required|in:email,phone',
            'value' => 'required|string|max:255',
        ]);
        $kind  = $request->input('kind');
        $value = LinkedIdentifier::normalize($kind, (string) $request->input('value'));

        // Reject if already attached to *any* account (including this one).
        $existing = LinkedIdentifier::where('kind', $kind)->where('value', $value)->first();
        if ($existing) {
            return back()->with('error', $existing->user_id === Auth::id()
                ? 'That ' . $kind . ' is already linked to your account.'
                : 'That ' . $kind . ' is already linked to another account.');
        }

        $otpType = $kind === 'phone' ? 'mobile' : 'email';
        $otp = new OtpService();
        $code = $otp->generate($value, $otpType, 'link', 'web');
        try {
            if ($kind === 'email') $otp->sendEmail($value, $code); else $otp->sendSms($value, $code);
        } catch (\Throwable $e) {
            \Log::warning('linked identifier OTP send failed: ' . $e->getMessage());
        }

        session(['linked_identifier_pending' => ['kind' => $kind, 'value' => $value]]);
        return back()
            ->with('status', 'Verification code sent to ' . $value . '.')
            ->with('otp_demo_reveal', \App\Modules\Common\Support\AuthMethods::demoRevealMessage($code));
    }

    public function confirm(Request $request)
    {
        $request->validate(['code' => 'required|string|size:6']);
        $pending = session('linked_identifier_pending');
        if (!$pending) {
            return redirect()->route('user.identifiers.index')->with('error', 'No pending identifier to verify.');
        }
        $kind  = $pending['kind'];
        $value = $pending['value'];
        $otpType = $kind === 'phone' ? 'mobile' : 'email';

        $otp = new OtpService();
        if (!$otp->verify($value, $request->code, $otpType, 'link', 'web')) {
            return back()->with('error', 'Invalid or expired code.');
        }

        // Re-check for race-condition duplicates under the unique
        // constraint. Idempotent for the same user (repeat submits or
        // a double-click on Verify must not 500), and a clear error if
        // someone else slipped in between Send and Verify.
        $existing = LinkedIdentifier::where('kind', $kind)->where('value', $value)->first();
        if ($existing && $existing->user_id !== Auth::id()) {
            session()->forget('linked_identifier_pending');
            return back()->with('error', 'That ' . $kind . ' was just linked to another account.');
        }
        if ($existing) {
            // Already on this user — just mark verified and finish.
            if (!$existing->verified_at) {
                $existing->verified_at = now();
                $existing->save();
            }
            $adopted = $kind === 'email'
                && app(AccountMergeService::class)->adoptEmailIfMissing(Auth::user(), $value);
            session()->forget('linked_identifier_pending');
            return redirect()->route('user.identifiers.index')->with('success', $adopted
                ? 'Email verified — it is now the email address on your account.'
                : 'Identifier already linked to your account.');
        }

        LinkedIdentifier::create([
            'user_id'     => Auth::id(),
            'kind'        => $kind,
            'value'       => $value,
            'verified_at' => now(),
            'is_primary'  => false,
        ]);
        // Mobile/WhatsApp-only accounts have no users.email — adopt the
        // freshly verified email as the account email so email-dependent
        // flows (e.g. being promoted to admin) work.
        $adopted = $kind === 'email'
            && app(AccountMergeService::class)->adoptEmailIfMissing(Auth::user(), $value);
        session()->forget('linked_identifier_pending');
        return redirect()->route('user.identifiers.index')->with('success', $adopted
            ? 'Email verified — it is now the email address on your account.'
            : 'Identifier verified and linked.');
    }

    public function destroy(LinkedIdentifier $identifier, AccountMergeService $service)
    {
        $user = Auth::user();
        try {
            $service->unlink($user, $identifier);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Identifier removed.');
    }

    public function promote(LinkedIdentifier $identifier, AccountMergeService $service)
    {
        $user = Auth::user();
        try {
            $service->promoteToPrimary($user, $identifier);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Primary identifier updated.');
    }
}
