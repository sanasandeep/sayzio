<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\LoginAlertService;
use App\Modules\User\Models\LoginEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Web-facing security pages — recent-logins history + the signed
 * one-click "This wasn't me" revoke endpoint linked from the
 * suspicious-login email.
 */
class SecurityController extends Controller
{
    public function logins(Request $request)
    {
        $events = LoginEvent::where('user_id', Auth::id())
            ->orderByDesc('id')
            ->limit(50)
            ->get();
        return view('user.security.logins', ['events' => $events]);
    }

    /**
     * Signed URL handler — invalidates the offending session,
     * cascades through every other session/token, and bounces the
     * user into the password-reset flow. Deliberately works without
     * an active web session so the email link works from any device
     * or inbox.
     */
    public function revoke(Request $request, string $token, LoginAlertService $service)
    {
        if (!$request->hasValidSignature()) {
            return view('user.security.revoke-invalid', [
                'reason' => 'This security link is no longer valid. Sign in and use the Recent logins page to revoke a session manually.',
            ], 403);
        }

        $event = LoginEvent::where('revoke_token', $token)->first();
        if (!$event) {
            return view('user.security.revoke-invalid', [
                'reason' => 'We could not find a matching sign-in. It may have already been revoked.',
            ], 404);
        }

        $user = $service->revokeFromEmail($event);

        // Drop the current visitor's session too — they may BE the
        // attacker following the email link, but they may also be
        // the legitimate user; either way they need a fresh password.
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return view('user.security.revoke-done', [
            'user'  => $user,
            'event' => $event,
        ]);
    }

    /**
     * Authenticated revoke from the Recent logins page (no signed URL
     * required, but CSRF-guarded by the standard web middleware).
     */
    public function revokeFromList(Request $request, LoginEvent $loginEvent, LoginAlertService $service)
    {
        if ($loginEvent->user_id !== Auth::id()) {
            abort(404);
        }
        $service->revokeFromEmail($loginEvent);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('user.login')
            ->with('status', "We've signed every device out and cleared your password. Use 'Forgot password' to set a new one.");
    }
}
