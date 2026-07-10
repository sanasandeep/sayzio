<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Api\Resources\UserResource;
use App\Modules\Common\Services\OtpService;
use App\Modules\Common\Support\AuthMethods;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use App\Modules\Admin\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Mobile-friendly OTP flow. Mirrors the web AuthController OTP UX but
 * over JSON: send → verify → token. Generic responses avoid leaking
 * account existence.
 */
class OtpController extends Controller
{
    use ApiResponses;

    /**
     * Public auth config so clients (mobile app, web) can decide whether to
     * offer WhatsApp (mobile) login and which country codes are accepted.
     */
    public function config()
    {
        return $this->ok([
            'mobile_login_enabled' => AuthMethods::mobileLoginEnabled(),
            'allowed_country_codes' => AuthMethods::allowedCountryCodes(),
        ]);
    }

    public function send(Request $request, OtpService $otp)
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:190'],
            'type'       => ['required', Rule::in(['email', 'mobile'])],
        ]);

        if ($denied = $this->guardMobile($data['type'], $data['identifier'])) {
            return $denied;
        }

        $user = $this->resolve($data['identifier'], $data['type']);

        // The OTP path doubles as sign-up for unknown identifiers. When
        // registrations are paused and no account exists, issue no code and
        // create nothing — return the structured upgrade message instead.
        if (!$user && AuthMethods::registrationPaused()) {
            return $this->fail(AuthMethods::registrationPausedMessage(), 403, AuthMethods::ERROR_REGISTRATION_PAUSED);
        }

        // For email: always generate and send a code even for unknown
        // identifiers — the account is created automatically at verify time.
        // For mobile (WhatsApp): only send when a real user exists (enumeration
        // safety; WhatsApp accounts require an explicit sign-up intent via the
        // /register endpoint).
        // Demo reveal is only returned for codes issued to existing users.
        $reveal = null;
        if ($user || $data['type'] === 'email') {
            $code = $otp->generate($data['identifier'], $data['type'], 'login', 'web', $request->ip());
            if ($user) {
                $reveal = AuthMethods::demoRevealMessage($code);
            }
            try {
                $data['type'] === 'email'
                    ? $otp->sendEmail($data['identifier'], $code)
                    : $otp->sendWhatsApp($data['identifier'], $code);
            } catch (\Throwable $e) {
                \Log::warning('OTP send failed: ' . $e->getMessage());
            }
        }

        return $this->ok([
            'sent'        => true,
            'message'     => 'If an account exists, a verification code has been sent.',
            'demo_reveal' => $reveal,
        ]);
    }

    public function verify(Request $request, OtpService $otp)
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:190'],
            'type'       => ['required', Rule::in(['email', 'mobile'])],
            'code'       => ['required', 'string', 'size:6'],
            'device'     => ['nullable', 'string', 'max:60'],
        ]);

        if ($denied = $this->guardMobile($data['type'], $data['identifier'])) {
            return $denied;
        }

        if (!$otp->verify($data['identifier'], $data['code'], $data['type'], 'login', 'web')) {
            return $this->fail('Invalid or expired code', 400, 'invalid_otp');
        }

        $user = $this->resolve($data['identifier'], $data['type']);

        // Email OTP doubles as sign-up: if no account exists for this email,
        // auto-create a free account now so the user lands in the app without
        // a separate registration step. A `needs_name` flag in the response
        // tells the mobile client to show the mandatory name-entry modal.
        $needsName = false;
        if (!$user && $data['type'] === 'email') {
            if (AuthMethods::registrationPaused()) {
                return $this->fail(AuthMethods::registrationPausedMessage(), 403, AuthMethods::ERROR_REGISTRATION_PAUSED);
            }
            $freePlan = Plan::defaultPlan();
            $user = User::create([
                'name'     => Str::before($data['identifier'], '@') ?: 'Creator',
                'email'    => strtolower($data['identifier']),
                'password' => Hash::make(Str::random(48)),
                'plan_id'  => $freePlan?->id,
                'status'   => 'active',
            ]);
            if (method_exists($user, 'ensureDefaultWorkspace')) {
                $user->ensureDefaultWorkspace();
            }
            $needsName = true;
        }

        if (!$user) return $this->fail('No account found', 404, 'user_not_found');

        $newToken = \App\Modules\Api\Support\SessionTokenIssuer::issue(
            $user, $request, $data['device'] ?? null, 'mobile', 'mobile'
        );
        \App\Jobs\RecordLoginEventJob::dispatch(
            $user->id,
            'mobile_otp',
            (string) ($request->ip() ?? ''),
            (string) ($request->userAgent() ?? ''),
            [
                'personal_access_token_id' => $newToken->accessToken->id ?? null,
                'device_label'             => $data['device'] ?? null,
            ],
            true,
            now(),
        );

        $userData = UserResource::toArray($user, self: true);
        if ($needsName) {
            $userData['needs_name'] = true;
        }

        return $this->ok([
            'user'  => $userData,
            'token' => $newToken->plainTextToken,
        ]);
    }

    /**
     * Mobile signup: create account then immediately issue OTP for the
     * provided identifier so the client can complete /verify on the next
     * step.
     */
    public function register(Request $request, OtpService $otp)
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:120'],
            'identifier' => ['required', 'string', 'max:190'],
            'type'       => ['required', Rule::in(['email', 'mobile'])],
            'country'    => ['nullable', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            // A handle the visitor claimed up-front (e.g. the marketing
            // hero's "claim your link" pill, carried through the WhatsApp/OTP
            // sign-up path). Loosely bounded here; the canonical
            // format/uniqueness/banned-name rules run in ClaimedHandle::apply,
            // which silently skips anything invalid so sign-up never dead-ends.
            'desired_handle' => ['nullable', 'string', 'max:60'],
        ]);

        if ($denied = $this->guardMobile($data['type'], $data['identifier'])) {
            return $denied;
        }

        if (AuthMethods::registrationPaused()) {
            return $this->fail(AuthMethods::registrationPausedMessage(), 403, AuthMethods::ERROR_REGISTRATION_PAUSED);
        }

        $existing = $this->resolve($data['identifier'], $data['type']);
        if ($existing) {
            return $this->fail('An account already exists for that identifier', 409, 'account_exists');
        }

        $freePlan = Plan::defaultPlan();
        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['type'] === 'email'  ? strtolower($data['identifier']) : null,
            'mobile'   => $data['type'] === 'mobile' ? $data['identifier'] : null,
            'password' => Hash::make(Str::random(48)),
            'plan_id'  => $freePlan?->id,
            'status'   => 'active',
            'country'  => isset($data['country']) ? strtoupper($data['country']) : null,
        ]);
        if (method_exists($user, 'ensureDefaultWorkspace')) {
            $user->ensureDefaultWorkspace();
        }

        // If the visitor claimed a handle up-front (e.g. the marketing hero's
        // "claim your link" pill routed into the WhatsApp/OTP sign-up path),
        // reserve it now so it's waiting after verification. Validation mirrors
        // the web register form; invalid/taken/banned values are silently
        // skipped so sign-up never dead-ends.
        \App\Modules\Common\Support\ClaimedHandle::apply($user, $data['desired_handle'] ?? null);

        $code = $otp->generate($data['identifier'], $data['type'], 'login', 'web', $request->ip());
        try {
            $data['type'] === 'email'
                ? $otp->sendEmail($data['identifier'], $code)
                : $otp->sendSms($data['identifier'], $code);
        } catch (\Throwable $e) {
            \Log::warning('OTP send (register) failed: ' . $e->getMessage());
        }

        return $this->created([
            'sent'        => true,
            'user_id'     => $user->id,
            'message'     => 'Account created. A verification code has been sent.',
            'demo_reveal' => AuthMethods::demoRevealMessage($code),
        ]);
    }

    public function demo(Request $request)
    {
        if (app()->environment('production')) {
            return $this->notFound('Demo login is disabled in production');
        }

        $user = User::firstOrCreate(
            ['email' => 'sazioapp@gmail.com'],
            [
                'name'              => 'Demo User',
                'password'          => Hash::make('password'),
                'plan_id'           => Plan::defaultPlan()?->id,
                'status'            => 'active',
                'email_verified_at' => now(),
            ]
        );
        $userAdminRoleId = \Illuminate\Support\Facades\DB::table('roles')
            ->where('slug', 'user-admin')->where('guard', 'web')
            ->value('id');
        if ($userAdminRoleId) {
            $user->roles()->syncWithoutDetaching([$userAdminRoleId]);
            $user->flushPermissionCache();
        }
        if (method_exists($user, 'ensureDefaultWorkspace')) {
            $user->ensureDefaultWorkspace();
        }

        $newToken = \App\Modules\Api\Support\SessionTokenIssuer::issue(
            $user, $request, null, 'demo-mobile', 'mobile'
        );
        \App\Jobs\RecordLoginEventJob::dispatch(
            $user->id,
            'mobile_demo',
            (string) ($request->ip() ?? ''),
            (string) ($request->userAgent() ?? ''),
            ['personal_access_token_id' => $newToken->accessToken->id ?? null],
            true,
            now(),
        );

        return $this->ok([
            'user'  => UserResource::toArray($user, self: true),
            'token' => $newToken->plainTextToken,
        ]);
    }

    /**
     * Enforce the email-only-by-default policy. Returns a JSON error
     * response when a mobile request is not permitted (login disabled or
     * the number falls outside the allowed country codes); null otherwise.
     */
    private function guardMobile(string $type, string $identifier)
    {
        if ($type !== 'mobile') {
            return null;
        }
        if (!AuthMethods::mobileLoginEnabled()) {
            return $this->fail('Mobile login is not available. Use your email instead.', 422, 'mobile_login_disabled');
        }
        if (!AuthMethods::isAllowedMobile($identifier)) {
            return $this->fail(
                'That country code isn\'t supported. Allowed codes: ' . AuthMethods::allowedCountryCodesLabel() . '.',
                422,
                'country_code_not_allowed'
            );
        }
        return null;
    }

    private function resolve(string $identifier, string $type): ?User
    {
        $kind = $type === 'mobile' ? 'phone' : 'email';
        $u = LinkedIdentifier::resolveUser($kind, $identifier);
        if ($u) return $u;
        return $type === 'email'
            ? User::where('email', strtolower($identifier))->first()
            : User::where('mobile', $identifier)->first();
    }
}
