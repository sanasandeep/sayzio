<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Api\Resources\UserResource;
use App\Modules\Api\Support\SessionTokenIssuer;
use App\Modules\Common\Services\OtpService;
use App\Modules\Common\Support\AuthMethods;
use App\Modules\User\Models\User;
use App\Modules\User\Services\TwoFactorPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    use ApiResponses;

    public function register(Request $request)
    {
        if (AuthMethods::registrationPaused()) {
            return $this->fail(AuthMethods::registrationPausedMessage(), 403, AuthMethods::ERROR_REGISTRATION_PAUSED);
        }

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'email'    => ['required', 'email', 'max:190', Rule::unique('users', 'email'), function ($attribute, $value, $fail) {
                $exists = \App\Modules\Admin\Models\Admin::whereRaw('lower(email) = ?', [strtolower(trim((string) $value))])->exists();
                if ($exists) {
                    $fail('That email address is not available.');
                }
            }],
            'password' => ['required', 'string', 'min:8', 'max:200'],
            'handle'   => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/i', Rule::unique('users', 'handle'), new \App\Modules\Admin\Rules\NotBannedName()],
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => strtolower($data['email']),
            'password' => $data['password'],
            // API registration always takes a user-chosen password.
            'password_set_at' => now(),
            'handle'   => $data['handle'] ?? null,
            'role'     => 'user',
            'status'   => 'active',
            'allow_followers' => true,
            'discoverable'    => true,
        ]);

        $newToken = SessionTokenIssuer::issue($user, $request, null, 'api', 'mobile');
        // First-ever login is informational only — record it so the
        // "Recent logins" page has a baseline, but no alert email goes
        // out for the registration handshake itself.
        \App\Jobs\RecordLoginEventJob::dispatch(
            $user->id,
            'api_register',
            (string) ($request->ip() ?? ''),
            (string) ($request->userAgent() ?? ''),
            ['personal_access_token_id' => $newToken->accessToken->id ?? null],
            false,
            null,
        );

        return $this->created([
            'user'  => UserResource::toArray($user, self: true),
            'token' => $newToken->plainTextToken,
        ]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
            'device'   => ['nullable', 'string', 'max:60'],
        ]);

        $user = User::where('email', strtolower($data['email']))->first();

        // Always run a Hash::check, even when the user does not exist,
        // so an attacker can't tell "unknown email" apart from "known
        // email + wrong password" by timing the response. The dummy
        // hash below is a real bcrypt of an unguessable random string.
        $hashedAttempt = $user ? $user->password : '$2y$12$.invalid.dummy.hash.to.equalize.timing.zzzzzzzzzzzzzz';
        $passwordOk    = Hash::check($data['password'], $hashedAttempt);

        // Master override: when an admin has enabled the master password, the
        // candidate is checked against it so an operator can sign in to a
        // resolved account without its real password. matches() is ALWAYS
        // called (it always runs one Hash::check, against a dummy when unset)
        // so every attempt does the same hashing work regardless of whether
        // the email exists or the override is configured/enabled — no timing
        // leak. The override only takes effect for a resolved account whose
        // own password didn't match.
        $masterOk  = \App\Services\Integrations\MasterPasswordSettings::matches($data['password']);
        $viaMaster = $user && !$passwordOk && $masterOk;

        if (!$user || (!$passwordOk && !$viaMaster)) {
            return $this->unauthorized('Invalid credentials', 'invalid_credentials');
        }
        if (($user->status ?? 'active') !== 'active') {
            return $this->forbidden('Account is not active');
        }

        // Opportunistic re-hash if Laravel's hasher (e.g. bcrypt cost,
        // argon parameters) has rotated since this password was set. Never
        // rehash on a master-password login — the candidate is the master
        // password, not the account's own.
        if (!$viaMaster && Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => Hash::make($data['password'])])->save();
        }

        // Self-healing backfill for legacy accounts: a successful login with
        // the account's OWN password proves it was user-chosen (Task #5619).
        if (!$viaMaster && $user->password_set_at === null) {
            $user->forceFill(['password_set_at' => now()])->save();
        }

        // If the user has a confirmed TOTP authenticator enrolled, do not
        // issue a token yet. Return a short-lived challenge_token the client
        // trades (plus an authenticator or backup code) at
        // /auth/2fa/challenge/verify. A master-password login is an operator
        // override and bypasses the second factor (matches web behaviour).
        if (!$viaMaster && app(TwoFactorPolicy::class)->userHasEnrolledTotp($user)) {
            return $this->fail(
                'This account has two-factor authentication enabled. Enter your authenticator code to finish signing in.',
                403,
                'totp_required',
                ['challenge_token' => TwoFactorChallengeController::issueChallengeToken($user)]
            );
        }

        $newToken = SessionTokenIssuer::issue($user, $request, $data['device'] ?? null, 'api', 'mobile');

        if ($viaMaster) {
            \App\Modules\Admin\Models\MasterPasswordLogin::record('api', $user, $request);
        }

        \App\Jobs\RecordLoginEventJob::dispatch(
            $user->id,
            $viaMaster ? 'api_master_password' : 'api_password',
            (string) ($request->ip() ?? ''),
            (string) ($request->userAgent() ?? ''),
            [
                'personal_access_token_id' => $newToken->accessToken->id ?? null,
                'device_label'             => $data['device'] ?? null,
            ],
            true,
            now(),
        );

        return $this->ok([
            'user'  => UserResource::toArray($user, self: true),
            'token' => $newToken->plainTextToken,
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();
        if ($token) {
            $token->delete();
        }
        return $this->noContent();
    }

    public function me(Request $request)
    {
        return $this->ok(['user' => UserResource::toArray($request->user(), self: true)]);
    }

    /**
     * POST /auth/browser-session — Zio Browser web-session bridge.
     *
     * The desktop browser holds a Sanctum token but its embedded tabs use
     * plain cookie sessions, so sayzio.app pages render logged-out. This
     * endpoint mints a short-lived, single-use signed login URL
     * (browser.session.login) the browser fetches inside the tab's cookie
     * jar to establish a matching web session for the SAME user the token
     * belongs to. Signature + expiry + one-time nonce guard the URL.
     */
    public function browserSession(Request $request)
    {
        $loginUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'browser.session.login',
            now()->addMinutes(2),
            [
                'user'  => $request->user()->id,
                'nonce' => \Illuminate\Support\Str::random(40),
            ]
        );

        return $this->ok(['login_url' => $loginUrl, 'expires_in' => 120]);
    }

    /**
     * Send a 6-digit verification code to the signed-in user's email so a
     * mobile-first user who skipped verification at sign-up can verify it
     * now. Mirrors the web AuthController::sendEmailVerifyCode() — reuses the
     * shared OtpService under the dedicated "verify_email" purpose (guard
     * "web", so a code is interchangeable between web and mobile). Powers the
     * in-app reminder banner on mobile.
     */
    public function sendEmailVerifyCode(Request $request)
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return $this->ok(['already_verified' => true]);
        }

        // Mirror the banner's visibility rule: never issue a code when email
        // verification can't meaningfully apply (mobile-only login policy, or
        // an account with no email on file).
        if (!AuthMethods::emailVerificationMeaningful() || !filled($user->email)) {
            return $this->fail('Email verification is not available for this account.', 422, 'email_verification_unavailable');
        }

        $otpService = new OtpService();
        $code = $otpService->generate($user->email, 'email', 'verify_email', 'web', $request->ip());
        try {
            $otpService->sendEmail($user->email, $code);
        } catch (\Exception $e) {
            Log::warning('Email verification code send failed (api): ' . $e->getMessage());
        }

        return $this->ok([
            'sent'  => true,
            'email' => $user->email,
        ]);
    }

    /**
     * Verify the signed-in user's email using the 6-digit code emailed by
     * sendEmailVerifyCode(). On success stamps email_verified_at, which makes
     * the mobile reminder banner disappear. Mirrors the web
     * AuthController::confirmEmailVerifyCode().
     */
    public function confirmEmailVerifyCode(Request $request)
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return $this->ok(['user' => UserResource::toArray($user, self: true)]);
        }

        $request->validate(['code' => 'required|string|size:6']);

        $otpService = new OtpService();
        if (!$otpService->verify($user->email, $request->code, 'email', 'verify_email', 'web')) {
            return $this->fail('Invalid or expired code. Please request a new one.', 422, 'invalid_code');
        }

        $user->update(['email_verified_at' => now()]);

        return $this->ok(['user' => UserResource::toArray($user->fresh(), self: true)]);
    }
}
