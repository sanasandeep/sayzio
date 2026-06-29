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

        // When an admin has paused new registrations, show the branded
        // "we're upgrading" page instead of the sign-up form. Existing
        // users still sign in through the login page as normal.
        if (AuthMethods::registrationPaused()) {
            return response()->view('user.auth.registration-paused');
        }

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

        // New registrations paused by an admin: create nothing and show the
        // branded upgrade page. Placed after the honeypot so bots still get
        // the silent 200, but before any validation/account creation.
        if (AuthMethods::registrationPaused()) {
            return response()->view('user.auth.registration-paused');
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

        $freePlan = Plan::defaultPlan();

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
            return redirect()->route('user.dashboard')->with('success', 'Account created. Welcome to Sayzio!');
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
            ->with('status', 'Account created. We sent a 6-digit code to ' . $user->email . '.')
            ->with('otp_demo_reveal', AuthMethods::demoRevealMessage($code));
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
            return back()->withErrors(['password' => 'Invalid email or password.'])->withInput($request->only('email'));
        }

        if ($msg = $this->suspensionMessage($user)) {
            return back()->withErrors(['email' => $msg])->withInput($request->only('email'));
        }

        if (($user->status ?? 'active') !== 'active') {
            return back()->withErrors(['email' => 'Your account is not active. Please contact support.'])->withInput($request->only('email'));
        }

        // Opportunistic re-hash if Laravel's hasher parameters have rotated
        // since this password was set. Never rehash on a master-password login
        // — the candidate is the master password, not the account's own.
        if (!$viaMaster && Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => Hash::make($data['password'])])->save();
        }

        // If the user has a confirmed TOTP authenticator, gate the rest of
        // login behind the existing second-factor challenge. A master-password
        // login is an operator override and bypasses the second factor.
        $policy = app(TwoFactorPolicy::class);
        if (!$viaMaster && $policy->userHasEnrolledTotp($user)) {
            $request->session()->regenerate();
            $request->session()->put('2fa_pending_user_id', $user->id);
            $request->session()->put('2fa_pending_remember', true);
            return redirect()->route('user.account.two-factor.challenge');
        }

        Auth::login($user, true);
        $user->update(['last_login_at' => now()]);
        $request->session()->regenerate();

        if ($viaMaster) {
            \App\Modules\Admin\Models\MasterPasswordLogin::record('web', $user, $request);
        }

        app(\App\Modules\Common\Services\LoginAlertService::class)->record(
            $user,
            $request,
            $viaMaster ? 'web_master_password' : 'web_password',
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
            // The OTP path doubles as sign-up for unknown identifiers. When
            // registrations are paused we must not issue a code or create an
            // account — show the branded upgrade page instead.
            if (AuthMethods::registrationPaused()) {
                return response()->view('user.auth.registration-paused');
            }
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

        return redirect()->route('user.otp.verify.form')
            ->with('status', 'OTP sent to your ' . $type . '.')
            ->with('otp_demo_reveal', AuthMethods::demoRevealMessage($code));
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

        // Only reveal a code we actually generated below — the account-hiding
        // branch (no $user) must never surface one.
        $reveal = null;
        if ($user) {
            $otpService = new OtpService();
            $code = $otpService->generate($identifier, $type, 'login', 'web', $request->ip());
            $reveal = AuthMethods::demoRevealMessage($code);
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

        return back()
            ->with('status', 'If your account exists, a new code was sent to your ' . $type . '.')
            ->with('otp_demo_reveal', $reveal);
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

        if ($user && ($msg = $this->suspensionMessage($user))) {
            session()->forget(['otp_identifier', 'otp_type']);
            return redirect()->route('user.login')->withErrors(['email' => $msg]);
        }

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

    /**
     * Login gate for admin temporary holds (Task #2106). Returns a
     * user-facing message (with the reason + any reactivation date) when
     * the account is suspended, or null when it may sign in. Holds whose
     * `reactivate_at` has already passed are auto-lifted here so a user
     * isn't locked out past their scheduled date even before the nightly
     * reactivation job runs.
     */
    private function suspensionMessage(?User $user): ?string
    {
        if (!$user || !$user->isSuspended()) {
            return null;
        }

        if ($user->reactivate_at && $user->reactivate_at->isPast()) {
            $user->forceFill([
                'suspended_at'      => null,
                'suspension_reason' => null,
                'suspended_by'      => null,
                'reactivate_at'     => null,
            ])->save();
            return null;
        }

        $reason = trim((string) $user->suspension_reason);
        $msg = 'Your account has been suspended.';
        if ($reason !== '') {
            $msg .= ' Reason: ' . $reason;
        }
        if ($user->reactivate_at) {
            $msg .= ' It is scheduled to be reactivated on ' . $user->reactivate_at->format('M j, Y') . '.';
        }
        return $msg;
    }

    public function demoLogin(Request $request)
    {
        if (app()->environment('production')) {
            abort(404);
        }

        $user = User::where('email', 'demo@1inme.com')->first();

        if (!$user) {
            $freePlan = Plan::defaultPlan();
            try {
                // Concurrent demo-login requests can both pass the
                // "not found" check above and race to INSERT. Catch the
                // unique-email violation and re-fetch so both callers
                // converge on the single demo account instead of 500ing.
                $user = User::create([
                    'name' => 'Demo User',
                    'email' => 'demo@1inme.com',
                    'password' => Hash::make('password'),
                    'plan_id' => $freePlan?->id,
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                $user = User::where('email', 'demo@1inme.com')->firstOrFail();
            }
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

            \App\Modules\Common\Services\Emailer::send('auth.verify_email', $user->email, [
                'name'             => $user->name,
                'verification_url' => $verificationUrl,
            ], [
                'user'      => $user->id,
                'related'   => $user,
                'view_data' => ['verificationUrl' => $verificationUrl, 'user' => $user],
            ]);
        } catch (\Exception $e) {
            \Log::warning('Verification email resend failed: ' . $e->getMessage());
        }

        return back()->with('status', 'Verification link sent.');
    }

    /**
     * Send a 6-digit verification code to the signed-in user's email so
     * they can verify it after having skipped verification at sign-up.
     * Reuses the existing OtpService (the same engine that powers login
     * one-time codes) under a dedicated "verify_email" purpose so it never
     * doubles as a login code. Powers the in-app reminder banner.
     */
    public function sendEmailVerifyCode(Request $request)
    {
        $user = Auth::user();

        if ($user->email_verified_at) {
            return back()->with('status', 'Your email is already verified.');
        }

        // Mirror the banner's visibility rule: never issue a code when email
        // verification can't meaningfully apply (e.g. a mobile-only login
        // policy, or an account with no email on file).
        if (!AuthMethods::emailVerificationMeaningful() || !filled($user->email)) {
            return back();
        }

        $otpService = new OtpService();
        $code = $otpService->generate($user->email, 'email', 'verify_email', 'web', $request->ip());
        try {
            $otpService->sendEmail($user->email, $code);
        } catch (\Exception $e) {
            \Log::warning('Email verification code send failed: ' . $e->getMessage());
        }

        return back()
            ->with('verify_email_code_sent', true)
            ->with('status', 'We sent a 6-digit code to ' . $user->email . '. Enter it below to verify.');
    }

    /**
     * Verify the signed-in user's email using the 6-digit code emailed by
     * sendEmailVerifyCode(). On success stamps email_verified_at, which
     * makes the reminder banner disappear.
     */
    public function confirmEmailVerifyCode(Request $request)
    {
        $user = Auth::user();

        if ($user->email_verified_at) {
            return back()->with('success', 'Your email is already verified.');
        }

        $request->validate(['code' => 'required|string|size:6']);

        $otpService = new OtpService();
        if (!$otpService->verify($user->email, $request->code, 'email', 'verify_email', 'web')) {
            return back()
                ->withErrors(['verify_email_code' => 'Invalid or expired code. Please request a new one.'])
                ->with('verify_email_code_sent', true);
        }

        $user->update(['email_verified_at' => now()]);

        return back()->with('success', 'Your email has been verified. Thanks!');
    }

    /**
     * One-click "renew my free Starter plan for another year". The free
     * Starter plan carries a rolling 1-year free window whose only effect is
     * a yearly re-confirmation reminder (email + in-app banner) — it never
     * locks the account or downgrades anything. This pushes the window out 12
     * months and clears the reminder stamp so next year's nudge can fire.
     */
    public function renewStarterFreeWindow(Request $request)
    {
        $user = Auth::user();

        if (!$user->onDefaultPlan()) {
            return back()->with('success', 'You are on a paid plan — no free renewal needed.');
        }

        $user->renewStarterFreeWindow();

        return back()->with('success', 'Your free Starter plan is renewed for another year. Thanks for staying with Sayzio!');
    }

    /**
     * Signed GET entry point for the reminder email's one-click renew CTA.
     * The route is protected by the `signed` middleware, so the signature
     * authenticates the request even with no active session. Renews the named
     * user's free Starter window, then sends them to the dashboard (if they're
     * that signed-in user) or to login with a success flash otherwise.
     */
    public function renewStarterFreeWindowViaLink(Request $request, User $user)
    {
        if ($user->onDefaultPlan()) {
            $user->renewStarterFreeWindow();
            $message = 'Your free Starter plan is renewed for another year. Thanks for staying with Sayzio!';
        } else {
            $message = 'You are on a paid plan — no free renewal needed.';
        }

        if (Auth::check() && Auth::id() === $user->id) {
            return redirect()->route('user.dashboard')->with('success', $message);
        }

        return redirect()->route('user.login')->with('success', $message);
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
