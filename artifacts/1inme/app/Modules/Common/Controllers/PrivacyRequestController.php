<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Models\PrivacyRequest;
use App\Modules\Common\Services\PrivacyRequestNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Public-facing privacy data request flow (GDPR right-to-erasure /
 * right-to-access, CCPA). Visitors request permanent account deletion or
 * a full export of their data; ownership is confirmed via an emailed link
 * (anonymous) or the active session (logged-in), then staff review the
 * request in the admin queue.
 */
class PrivacyRequestController extends Controller
{
    public function show(Request $request)
    {
        $type = $request->query('type') === PrivacyRequest::TYPE_DELETION
            ? PrivacyRequest::TYPE_DELETION
            : PrivacyRequest::TYPE_EXPORT;

        return view('public.privacy-request', [
            'type'        => $type,
            'currentUser' => Auth::guard('web')->user(),
        ]);
    }

    public function submit(Request $request, PrivacyRequestNotifier $notifier)
    {
        $data = $request->validate([
            'type'    => 'required|in:deletion,export',
            'email'   => 'required|email|max:190',
            'reason'  => 'nullable|string|max:2000',
            'website' => 'nullable|max:0', // honeypot
        ]);

        // Honeypot tripped — silently pretend success.
        if (!empty($request->input('website'))) {
            return $this->confirmation($data['type']);
        }

        $key = 'privacy-request:' . ($request->ip() ?? 'unknown');
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()
                ->withErrors(['email' => 'Too many requests — please try again in a few minutes.'])
                ->withInput();
        }
        RateLimiter::hit($key, 600);

        $sessionUser = Auth::guard('web')->user();

        // Logged-in users act on their OWN account and skip email
        // verification — the session already proves ownership.
        if ($sessionUser) {
            $email = strtolower(trim($sessionUser->email));
            $pr = PrivacyRequest::create([
                'type'        => $data['type'],
                'user_id'     => $sessionUser->id,
                'email'       => $email,
                'reason'      => $data['reason'] ?? null,
                'status'      => PrivacyRequest::STATUS_VERIFIED,
                'verified_at' => now(),
                'ip'          => $request->ip(),
            ]);
            $pr->recordAudit('submitted', 'user:' . $sessionUser->id, 'Submitted while signed in.');
            $pr->recordAudit('verified', 'session', 'Auto-verified via active session.');

            $notifier->notifyReceived($pr);
            $notifier->notifyVerified($pr);

            return $this->confirmation($data['type'], true);
        }

        // Anonymous submitter — match the email and send a verification
        // link. We always show the same neutral confirmation regardless of
        // whether the email matches an account (no account enumeration).
        $email = strtolower(trim($data['email']));
        $matched = PrivacyRequest::matchUser($email);

        $pr = PrivacyRequest::create([
            'type'               => $data['type'],
            'user_id'            => $matched?->id,
            'email'              => $email,
            'reason'             => $data['reason'] ?? null,
            'status'             => PrivacyRequest::STATUS_PENDING_VERIFICATION,
            'verification_token' => PrivacyRequest::newToken(),
            'token_expires_at'   => now()->addHours(PrivacyRequest::VERIFY_TTL_HOURS),
            'ip'                 => $request->ip(),
        ]);
        $pr->recordAudit('submitted', 'anonymous', $matched ? 'Matched an account.' : 'No matching account.');

        // Only email a verification link when there's actually an account to
        // act on — avoids mailing arbitrary addresses.
        if ($matched) {
            $verifyUrl = route('privacy.verify', ['token' => $pr->verification_token]);
            $notifier->sendVerification($pr, $verifyUrl);
            $notifier->notifyReceived($pr);
        }

        return $this->confirmation($data['type']);
    }

    public function verify(string $token, PrivacyRequestNotifier $notifier)
    {
        $pr = PrivacyRequest::where('verification_token', $token)->first();

        if (!$pr || $pr->status !== PrivacyRequest::STATUS_PENDING_VERIFICATION) {
            return view('public.privacy-request', [
                'type'         => PrivacyRequest::TYPE_EXPORT,
                'currentUser'  => Auth::guard('web')->user(),
                'verifyResult' => 'invalid',
            ]);
        }

        if ($pr->token_expires_at && $pr->token_expires_at->isPast()) {
            return view('public.privacy-request', [
                'type'         => $pr->type,
                'currentUser'  => Auth::guard('web')->user(),
                'verifyResult' => 'expired',
            ]);
        }

        $pr->forceFill([
            'status'             => PrivacyRequest::STATUS_VERIFIED,
            'verified_at'        => now(),
            'verification_token' => null,
            'token_expires_at'   => null,
        ])->save();
        $pr->recordAudit('verified', 'email', 'Confirmed ownership via email link.');

        $notifier->notifyVerified($pr);

        return view('public.privacy-request', [
            'type'         => $pr->type,
            'currentUser'  => Auth::guard('web')->user(),
            'verifyResult' => 'ok',
        ]);
    }

    /** Stream a generated export archive after validating the token + expiry. */
    public function download(string $token): BinaryFileResponse
    {
        $pr = PrivacyRequest::where('download_token', $token)->first();

        abort_if(!$pr || !$pr->downloadIsLive(), 404);

        $disk = Storage::disk('local');
        abort_if(!$pr->archive_path || !$disk->exists($pr->archive_path), 404);

        $pr->recordAudit('downloaded', 'user', 'Export archive downloaded.');

        return response()->download(
            $disk->path($pr->archive_path),
            '1inme-data-export-' . $pr->id . '.zip'
        );
    }

    /**
     * Render the request page in its post-submit confirmation state, with
     * copy that explains what happens next and the legal time window.
     */
    private function confirmation(string $type, bool $autoVerified = false)
    {
        return view('public.privacy-request', [
            'type'         => $type,
            'currentUser'  => Auth::guard('web')->user(),
            'submitted'    => true,
            'autoVerified' => $autoVerified,
        ]);
    }
}
