<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Services\OtpService;
use App\Modules\Common\Support\AuthMethods;
use App\Modules\User\Services\ReferralService;
use App\Modules\User\Services\TwoFactorPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showRegister(Request $request)
    {
        if (Auth::check()) return redirect()->route('user.dashboard');
        $prefilledRef = $request->query('ref') ?: $request->cookie(ReferralService::COOKIE_NAME);
        return view('user.auth.register', [
            'prefilledRef'         => $prefilledRef,
            'emailPasswordEnabled' => AuthMethods::emailPasswordEnabled(),
        ]);
    }

    public function register(Request $request, ReferralService $referrals)
    {
        // Honeypot: a hidden field that real users never see and never
        // fill, but headless spam bots populate every input on the
        // form. Bail silently with a 200 so the bot can't tell its
        // submission was rejected.
        if (filled($request->input('website'))) {
            \Log::info('Registration honeypot tripped', ['ip' => $request->ip()]);
            return redirect()->route('user.login')
                ->with('status', 'If your account was created, we sent a code to your inbox.');
        }

        $passwordEnabled = AuthMethods::emailPasswordEnabled();

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:190|unique:users,email',
            'mobile' => 'nullable|string|max:20',
            'referral_code' => 'nullable|string|max:32',
            'country' => ['nullable', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
        ];
        // When password login is enabled, the sign-up form captures a
        // password the user chooses (confirmed). When it's off, the form has
        // no password field and accounts keep their random, unused password.
        if ($passwordEnabled) {
            $rules['password'] = ['required', 'string', 'min:8', 'max:72', 'confirmed'];
        }
        $validated = $request->validate($rules);
        $validated['email'] = strtolower($validated['email']);
        if (!empty($validated['country'])) {
            $validated['country'] = strtoupper($validated['country']);
        }

        // If a referral code was submitted, ensure it resolves to a real user;
        // otherwise drop it silently and fall back to the cookie attribution.
        $submittedCode = $validated['referral_code'] ?? null;
        if ($submittedCode && !$referrals->findReferrerByCode($submittedCode)) {
            return back()->withErrors(['referral_code' => 'That referral code is not valid.'])->withInput();
        }

        $freePlan = Plan::where('slug', 'free')->first();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'mobile' => $validated['mobile'] ?? null,
            // When password login is enabled, store the user's chosen
            // password. Otherwise the column is NOT NULL but unused — fill
            // it with an unguessable random hash so the OTP flow is the
            // only way in.
            'password' => Hash::make($passwordEnabled ? $validated['password'] : Str::random(48)),
            'plan_id' => $freePlan?->id,
            'status' => 'active',
            'referral_code' => $referrals->generateUniqueCode(),
            'country' => $validated['country'] ?? null,
        ]);

        $cookieCode = $request->cookie(ReferralService::COOKIE_NAME);
        $referrals->attributeSignup($user, $submittedCode, $cookieCode, $request->ip(), $request->userAgent());

        // Every new user starts with a personal workspace. Team workspaces
        // (if their plan allows) can be created later from the switcher.
        $user->ensureDefaultWorkspace();

        // Decide whether the new user can skip email verification and go
        // straight to their dashboard. This is only possible when a usable
        // password exists (so they have another way to sign in later) AND
        // either email OTP login is off (no code to verify — password-only
        // mode) OR an admin has made verification optional at sign-up. In
        // OTP-only mode the emailed code is the sole way in, so verification
        // can never be skipped regardless of the admin toggle.
        $skipVerification = $passwordEnabled
            && (!AuthMethods::emailOtpEnabled() || !AuthMethods::emailVerificationRequired());

        if ($skipVerification) {
            Auth::login($user, true);
            $user->update(['last_login_at' => now()]);
            $request->session()->regenerate();
            $request->session()->regenerateToken();

            app(\App\Modules\Common\Services\LoginAlertService::class)->record(
                $user,
                $request,
                'web_register_password',
                ['session_id' => $request->session()->getId()]
            );

            \App\Modules\User\Controllers\AcceptInviteController::attachPendingInvite($user);

            if ($redirect = \App\Modules\Admin\Services\HandleRenameEnforcer::maybeRedirect($user)) {
                return $redirect;
            }
            return redirect()->route('user.dashboard')->with('success', 'Account created. Welcome to 1INME!');
        }

        // Send a login OTP and route the new user through verification.
        $otpService = new OtpService();
        $code = $otpService->generate($user->email, 'email', 'login', 'web', $request->ip());
        try {
            $otpService->sendEmail($user->email, $code);
        } catch (\Exception $e) {
            \Log::warning('OTP email failed: ' . $e->getMessage());
        }

        session([
            'otp_identifier' => $user->email,
            'otp_type'       => 'email',
        ]);

        // Rotate both the session ID and the CSRF token so that any
        // pre-auth session fixation handle a bot may have planted on
        // the visitor is invalidated before we hand off to the OTP
        // verification flow.
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('user.otp.verify.form')
            ->with('status', 'Account created. We sent a 6-digit code to ' . $user->email . '.');
    }

    public function showLogin()
    {
        if (Auth::check()) return redirect()->route('user.dashboard');
        return view('user.auth.login', [
            'mobileLoginEnabled'   => AuthMethods::mobileLoginEnabled(),
            'emailPasswordEnabled' => AuthMethods::emailPasswordEnabled(),
            'emailOtpEnabled'      => AuthMethods::emailOtpEnabled(),
            'allowedCountryCodes'  => AuthMethods::allowedCountryCodes(),
        ]);
    }

    /**
     * Email + password sign-in. Only available when an admin has enabled
     * the email-password login method. Validates against the user's stored
     * password hash and routes through the existing 2FA/TOTP challenge when
     * the user has an authenticator enrolled.
     */
    public function loginWithPassword(Request $request)
    {
        if (!AuthMethods::emailPasswordEnabled()) {
            return redirect()->route('user.login')
                ->withErrors(['password' => 'Password login is not available. Please sign in with a one-time code.']);
        }

        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $email = strtolower($data['email']);
        $user  = $this->resolveUserByIdentifier($email, 'email');

        // Always run a Hash::check, even when no user matches, so an attacker
        // can't tell "unknown email" apart from "known email + wrong password"
        // by timing the response.
        $hashedAttempt = $user ? $user->password : '$2y$12$.invalid.dummy.hash.to.equalize.timing.zzzzzzzzzzzzzz';
        $passwordOk    = Hash::check($data['password'], $hashedAttempt);

        if (!$user || !$passwordOk) {
            return back()->withErrors(['password' => 'Invalid email or password.'])->withInput($request->only('email'));
        }

        if (($user->status ?? 'active') !== 'active') {
            return back()->withErrors(['email' => 'Your account is not active. Please contact support.'])->withInput($request->only('email'));
        }

        // Opportunistic re-hash if Laravel's hasher parameters have rotated
        // since this password was set.
        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => Hash::make($data['password'])])->save();
        }

        // If the user has a confirmed TOTP authenticator, gate the rest of
        // login behind the existing second-factor challenge.
        $policy = app(TwoFactorPolicy::class);
        if ($policy->userHasEnrolledTotp($user)) {
            $request->session()->regenerate();
            $request->session()->put('2fa_pending_user_id', $user->id);
            $request->session()->put('2fa_pending_remember', true);
            return redirect()->route('user.account.two-factor.challenge');
        }

        Auth::login($user, true);
        $user->update(['last_login_at' => now()]);
        $request->session()->regenerate();

        app(\App\Modules\Common\Services\LoginAlertService::class)->record(
            $user,
            $request,
            'web_password',
            ['session_id' => $request->session()->getId()]
        );

        $user->ensureDefaultWorkspace();
        \App\Modules\User\Controllers\AcceptInviteController::attachPendingInvite($user);

        if ($redirect = \App\Modules\Admin\Services\HandleRenameEnforcer::maybeRedirect($user)) {
            return $redirect;
        }
        return redirect()->intended(route('user.dashboard'));
    }

    public function sendOtp(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'type' => 'required|in:email,mobile',
        ]);

        $identifier = $request->identifier;
        $type = $request->type;

        // Honor the email-OTP toggle: when an admin has switched it off
        // (password-only mode), reject email one-time-code requests even if
        // someone crafts the POST directly.
        if ($type === 'email' && !AuthMethods::emailOtpEnabled()) {
            return back()->withErrors(['identifier' => 'Email one-time-code login is not available. Please sign in with your password.'])->withInput();
        }

        // Email is the only login identifier unless an admin has switched on
        // WhatsApp (mobile) login. Reject mobile attempts when it's off and
        // enforce the allowed-country-code list when it's on.
        if ($type === 'mobile') {
            if (!AuthMethods::mobileLoginEnabled()) {
                return back()->withErrors(['identifier' => 'Mobile login is not available. Please sign in with your email.'])->withInput();
            }
            if (!AuthMethods::isAllowedMobile($identifier)) {
                return back()->withErrors(['identifier' => 'That country code isn\'t supported. Allowed codes: ' . AuthMethods::allowedCountryCodesLabel() . '.'])->withInput();
            }
        }

        $user = $this->resolveUserByIdentifier($identifier, $type);

        if (!$user) {
            session(['otp_identifier' => $identifier, 'otp_type' => $type]);
            return redirect()->route('user.otp.verify.form')->with('status', 'If an account exists, an OTP has been sent to your ' . $type . '.');
        }

        $otpService = new OtpService();
        $code = $otpService->generate($identifier, $type, 'login', 'web', $request->ip());

        if ($type === 'email') {
            $otpService->sendEmail($identifier, $code);
        } else {
            $otpService->sendWhatsApp($identifier, $code);
        }

        session(['otp_identifier' => $identifier, 'otp_type' => $type]);
        // Regular login flow — clear any stale merge-challenge marker so
        // we don't accidentally hijack the session into a merge.
        session()->forget('merge_challenge_active');

        // Rotate the CSRF token at the start of the auth handshake so
        // the verify-otp POST has to be made from a freshly-issued token.
        $request->session()->regenerateToken();

        return redirect()->route('user.otp.verify.form')->with('status', 'OTP sent to your ' . $type . '.');
    }

    public function resendOtp(Request $request)
    {
        $identifier = session('otp_identifier');
        $type = session('otp_type', 'email');
        if (!$identifier) {
            return redirect()->route('user.login');
        }

        // Mirror the send-time policy: never re-issue a mobile code once
        // WhatsApp login has been switched off (or for a now-disallowed code).
        if ($type === 'mobile' && (!AuthMethods::mobileLoginEnabled() || !AuthMethods::isAllowedMobile($identifier))) {
            return redirect()->route('user.login')
                ->withErrors(['identifier' => 'Mobile login is not available. Please sign in with your email.']);
        }

        // Likewise, don't re-issue an email code once email OTP login has
        // been switched off.
        if ($type === 'email' && !AuthMethods::emailOtpEnabled()) {
            return redirect()->route('user.login')
                ->withErrors(['identifier' => 'Email one-time-code login is not available. Please sign in with your password.']);
        }

        // Only generate/send when a real user matches the session identifier.
        // Always show a generic success so we don't leak account existence.
        $user = $this->resolveUserByIdentifier($identifier, $type);

        if ($user) {
            $otpService = new OtpService();
            $code = $otpService->generate($identifier, $type, 'login', 'web', $request->ip());
            try {
                if ($type === 'email') {
                    $otpService->sendEmail($identifier, $code);
                } else {
                    $otpService->sendWhatsApp($identifier, $code);
                }
            } catch (\Exception $e) {
                \Log::warning('Resend OTP failed: ' . $e->getMessage());
            }
        }

        return back()->with('status', 'If your account exists, a new code was sent to your ' . $type . '.');
    }

    public function showOtpVerify()
    {
        if (!session('otp_identifier')) {
            return redirect()->route('user.login');
        }
        return view('user.auth.otp-verify');
    }

    public function verifyOtp(Request $request)
    {
        $request->validate(['code' => 'required|string|size:6']);

        $identifier = session('otp_identifier');
        $type = session('otp_type', 'email');

        if (!$identifier) {
            return redirect()->route('user.login')->withErrors(['code' => 'Session expired. Please try again.']);
        }

        $otpService = new OtpService();
        if (!$otpService->verify($identifier, $request->code, $type, 'login', 'web')) {
            return back()->withErrors(['code' => 'Invalid or expired OTP.']);
        }

        // If this is a "merge another account" challenge, hand off to the
        // merge flow instead of swapping the active session.
        if (session('merge_challenge_active')) {
            $other = $this->resolveUserByIdentifier($identifier, $type);
            session()->forget(['otp_identifier', 'otp_type', 'merge_challenge_active']);
            if (!$other) {
                return redirect()->route('user.merge.start')
                    ->withErrors(['code' => 'No account matched that identifier.']);
            }
            session([
                'merge_secondary_id' => $other->id,
                'merge_primary_id'   => Auth::id(),
            ]);
            return redirect()->route('user.merge.preview');
        }

        $user = $this->resolveUserByIdentifier($identifier, $type);

        if ($user) {
            // If the user has a confirmed TOTP authenticator, gate the rest
            // of login behind a second-factor challenge instead of logging
            // them in immediately. We stash the user id in the session
            // (rotated) and bounce to the 2FA challenge form.
            $policy = app(TwoFactorPolicy::class);
            if ($policy->userHasEnrolledTotp($user)) {
                session()->forget(['otp_identifier', 'otp_type']);
                $request->session()->regenerate();
                $request->session()->put('2fa_pending_user_id', $user->id);
                $request->session()->put('2fa_pending_remember', true);
                return redirect()->route('user.account.two-factor.challenge');
            }

            Auth::login($user, true);
            $user->update(['last_login_at' => now()]);
            session()->forget(['otp_identifier', 'otp_type']);
            $request->session()->regenerate();

            app(\App\Modules\Common\Services\LoginAlertService::class)->record(
                $user,
                $request,
                'web_otp_' . $type,
                ['session_id' => $request->session()->getId()]
            );

            // Ensure user has a default workspace; auto-attach any pending invite.
            $user->ensureDefaultWorkspace();
            \App\Modules\User\Controllers\AcceptInviteController::attachPendingInvite($user);

            if ($redirect = \App\Modules\Admin\Services\HandleRenameEnforcer::maybeRedirect($user)) {
                return $redirect;
            }
            return redirect()->intended(route('user.dashboard'));
        }

        return redirect()->route('user.login')->withErrors(['code' => 'User not found.']);
    }

    /**
     * Resolve any verified linked identifier (email/phone) to its owning
     * user. Falls back to the legacy users.email / users.mobile columns
     * for accounts predating the linked-identifiers backfill.
     */
    private function resolveUserByIdentifier(string $identifier, string $type): ?User
    {
        $kind = $type === 'mobile' ? 'phone' : 'email';
        $user = LinkedIdentifier::resolveUser($kind, $identifier);
        if ($user) return $user;
        return $type === 'email'
            ? User::where('email', $identifier)->first()
            : User::where('mobile', $identifier)->first();
    }

    public function demoLogin(Request $request)
    {
        if (app()->environment('production')) {
            abort(404);
        }

        $user = User::where('email', 'demo@1inme.com')->first();

        if (!$user) {
            $freePlan = Plan::where('slug', 'free')->first();
            $user = User::create([
                'name' => 'Demo User',
                'email' => 'demo@1inme.com',
                'password' => Hash::make('password'),
                'plan_id' => $freePlan?->id,
                'status' => 'active',
                'email_verified_at' => now(),
            ]);
        }

        // Demo accounts get the user-admin role so the platform-admin
        // sidebar items are reachable end-to-end during demos.
        $userAdminRoleId = \Illuminate\Support\Facades\DB::table('roles')
            ->where('slug', 'user-admin')->where('guard', 'web')
            ->value('id');
        if ($userAdminRoleId) {
            $user->roles()->syncWithoutDetaching([$userAdminRoleId]);
            $user->flushPermissionCache();
        }

        Auth::login($user);
        $user->update(['last_login_at' => now()]);
        $request->session()->regenerate();

        app(\App\Modules\Common\Services\LoginAlertService::class)->record(
            $user,
            $request,
            'web_demo',
            ['session_id' => $request->session()->getId()]
        );

        return redirect()->route('user.dashboard');
    }

    public function showVerifyEmail()
    {
        if (Auth::user()->email_verified_at) {
            return redirect()->route('user.dashboard');
        }
        return view('user.auth.verify-email');
    }

    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        if ((int) $id !== (int) Auth::id()) {
            abort(403, 'You can only verify your own email.');
        }

        if (!hash_equals(sha1($user->email), $hash)) {
            abort(403, 'Invalid verification link.');
        }

        if (!$user->email_verified_at) {
            $user->update(['email_verified_at' => now()]);
        }

        return redirect()->route('user.dashboard')->with('success', 'Email verified successfully.');
    }

    public function resendVerification(Request $request)
    {
        $user = Auth::user();

        if ($user->email_verified_at) {
            return back()->with('status', 'Email already verified.');
        }

        try {
            $verificationUrl = URL::temporarySignedRoute(
                'user.verification.verify',
                now()->addHours(24),
                ['id' => $user->id, 'hash' => sha1($user->email)]
            );

            Mail::send('emails.verify-email', ['verificationUrl' => $verificationUrl, 'user' => $user], function ($message) use ($user) {
                $message->to($user->email);
                $message->subject('Verify Your Email - 1INME');
            });
        } catch (\Exception $e) {
            \Log::warning('Verification email resend failed: ' . $e->getMessage());
        }

        return back()->with('status', 'Verification link sent.');
    }

    public function logout(Request $request)
    {
        if (session()->has('admin_id')) {
            $adminId = session('admin_id');
            session()->forget(['impersonate_user_id', 'admin_id']);
            Auth::logout();
            Auth::guard('admin')->loginUsingId($adminId);
            return redirect()->route('admin.users.index')->with('success', 'Impersonation stopped.');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('user.login');
    }
}
