<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Services\TotpService;
use App\Modules\User\Services\TwoFactorPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

/**
 * TOTP enrollment, confirmation, and disable flows for the signed-in user.
 *
 * Secrets and recovery codes are encrypted at rest via Laravel's app key.
 * The `confirm` step requires a valid live TOTP code so we never persist
 * a "half-enrolled" state — either the user has a working authenticator
 * or they don't.
 */
class TwoFactorController extends Controller
{
    public function __construct(protected TotpService $totp, protected TwoFactorPolicy $policy) {}

    /** Setup screen — generates a fresh secret if the user isn't enrolled yet. */
    public function show(Request $request)
    {
        $user = $request->user();

        // Already-enrolled users see a "Disable" panel; pending users see
        // the QR + secret. We re-use the secret across page loads so a
        // refresh doesn't invalidate the QR they just scanned.
        $enrolled = $this->policy->userHasEnrolledTotp($user);

        $secret = null;
        $qrSvg = null;
        if (!$enrolled) {
            $secret = $request->session()->get('pending_2fa_secret');
            if (!$secret) {
                $secret = $this->totp->generateSecret();
                $request->session()->put('pending_2fa_secret', $secret);
            }
            $uri = $this->totp->provisioningUri(
                $secret,
                $user->email ?: ('user-' . $user->id),
                config('app.name', '1INME')
            );
            $qrSvg = $this->totp->qrSvg($uri);
        }

        return view('user.account.two-factor', [
            'enrolled'       => $enrolled,
            'secret'         => $secret,
            'qrSvg'          => $qrSvg,
            'recoveryCodes'  => $request->session()->pull('show_recovery_codes', []),
            'policyCovers'   => $this->policy->userIsCoveredByAnyPolicy($user),
        ]);
    }

    /** Forced-enrollment landing page (used by the policy middleware). */
    public function required(Request $request)
    {
        // Reuse the same view as `show()` but tag it so the template can
        // surface the "you must enroll to continue" copy.
        return view('user.account.two-factor-required', [
            'continueUrl' => route('user.account.two-factor.show'),
        ]);
    }

    /** Verify the code typed from the user's authenticator + persist. */
    public function confirm(Request $request)
    {
        $user = $request->user();
        $request->validate(['code' => 'required|string|min:6|max:8']);

        if ($this->policy->userHasEnrolledTotp($user)) {
            return back()->with('error', '2FA is already enabled for this account.');
        }

        $secret = $request->session()->get('pending_2fa_secret');
        if (!$secret) {
            return redirect()->route('user.account.two-factor.show')
                ->with('error', 'Your setup session expired — please scan the QR again.');
        }

        if ($this->totp->verify($secret, $request->code) === null) {
            return back()->with('error', 'That code didn\'t match. Try the next one your app shows.');
        }

        $codes = $this->totp->generateRecoveryCodes();
        $user->forceFill([
            'two_factor_secret'          => Crypt::encryptString($secret),
            'two_factor_confirmed_at'    => now(),
            'two_factor_recovery_codes'  => Crypt::encryptString(json_encode(
                array_map(fn ($c) => Hash::make($c), $codes)
            )),
        ])->save();

        $request->session()->forget('pending_2fa_secret');
        $request->session()->put('show_recovery_codes', $codes);

        return redirect()->route('user.account.two-factor.show')
            ->with('success', '2FA enabled — save your recovery codes somewhere safe.');
    }

    /** Disable 2FA. Refused if any of the user's workspaces still requires it. */
    public function disable(Request $request)
    {
        $user = $request->user();

        // If a workspace still requires 2FA and the grace period has
        // expired, blocking disable here mirrors the enforcement gate.
        foreach ($user->accessibleWorkspaces() as $ws) {
            if ((int) $ws->owner_user_id === (int) $user->id) continue;
            if ($this->policy->workspaceRequires2FA($ws) && $this->policy->workspaceGraceExpired($ws)) {
                return back()->with('error', "You can't disable 2FA — the '{$ws->name}' workspace requires it.");
            }
        }

        $user->forceFill([
            'two_factor_secret'         => null,
            'two_factor_confirmed_at'   => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        return back()->with('success', '2FA disabled for your account.');
    }

    /** Regenerate recovery codes (rotates and shows them once). */
    public function regenerateRecoveryCodes(Request $request)
    {
        $user = $request->user();
        if (!$this->policy->userHasEnrolledTotp($user)) {
            return back()->with('error', 'Enable 2FA before generating recovery codes.');
        }
        $codes = $this->totp->generateRecoveryCodes();
        $user->forceFill([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode(
                array_map(fn ($c) => Hash::make($c), $codes)
            )),
        ])->save();
        $request->session()->put('show_recovery_codes', $codes);
        return redirect()->route('user.account.two-factor.show')
            ->with('success', 'New recovery codes generated. The old ones no longer work.');
    }

    // ------------------------------------------------------------------
    // 2FA login challenge (called from AuthController after OTP verify).
    // ------------------------------------------------------------------

    /** Render the 6-digit challenge form during the login handshake. */
    public function challenge(Request $request)
    {
        if (!$request->session()->has('2fa_pending_user_id')) {
            return redirect()->route('user.login');
        }
        return view('user.auth.two-factor-challenge');
    }

    /** Verify the 2FA code and complete login. Accepts a recovery code too. */
    public function verifyChallenge(Request $request)
    {
        $userId = $request->session()->get('2fa_pending_user_id');
        if (!$userId) return redirect()->route('user.login');

        $request->validate(['code' => 'required|string']);

        $user = \App\Modules\User\Models\User::find($userId);
        if (!$user || !$this->policy->userHasEnrolledTotp($user)) {
            $request->session()->forget(['2fa_pending_user_id', '2fa_pending_remember']);
            return redirect()->route('user.login')
                ->withErrors(['code' => 'Session expired — please sign in again.']);
        }

        $secret = Crypt::decryptString($user->two_factor_secret);
        $code   = trim($request->input('code'));

        $matched = $this->totp->verify($secret, $code) !== null;

        // If TOTP fails, try recovery codes (single-use).
        if (!$matched) {
            $stored = json_decode(Crypt::decryptString($user->two_factor_recovery_codes ?? '[]') ?: '[]', true) ?: [];
            $remaining = [];
            $consumed = false;
            foreach ($stored as $hashed) {
                if (!$consumed && Hash::check($code, $hashed)) {
                    $consumed = true;
                    continue;
                }
                $remaining[] = $hashed;
            }
            if ($consumed) {
                $user->forceFill([
                    'two_factor_recovery_codes' => Crypt::encryptString(json_encode($remaining)),
                ])->save();
                $matched = true;
            }
        }

        if (!$matched) {
            return back()->withErrors(['code' => 'Invalid 2FA code.']);
        }

        $remember = (bool) $request->session()->pull('2fa_pending_remember', true);
        $request->session()->forget('2fa_pending_user_id');

        Auth::login($user, $remember);
        $user->update(['last_login_at' => now()]);
        $request->session()->regenerate();

        $user->ensureDefaultWorkspace();
        AcceptInviteController::attachPendingInvite($user);

        return redirect()->intended(route('user.dashboard'));
    }
}
