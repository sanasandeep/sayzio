<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Services\OtpService;
use App\Modules\Common\Support\AuthMethods;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end coverage for the in-chat passwordless login/signup on the
 * "Zio Bot" site assistant. The flow was previously verified statically
 * only (lint / blade compile / route registration); this suite drives the
 * real HTTP roundtrip through the Laravel kernel so the contract both
 * front-ends depend on is pinned:
 *
 *   - Same-origin blade widget: identifier -> OTP -> session login in
 *     place (no `issue_token`, no full-page redirect).
 *   - Cross-origin marketing React widget: same flow with
 *     `issue_token:true` mints a Sanctum bearer token that authenticates
 *     subsequent /assistant/* calls.
 *   - Login == signup: first verify of an unknown identifier creates the
 *     account; registration-pause blocks unknown identifiers; 2FA-enrolled
 *     accounts bounce to the full login page.
 *   - Bot defences: honeypot (`website`) + time-trap (`elapsed_ms`) drop
 *     submissions silently (send) / reject them (verify).
 *   - The anonymous "Contact us" (quick-contact) path is unaffected.
 *
 * In the testing environment OtpService::generate() always issues the
 * fixed code "123456" (non-production branch), so the tests verify with
 * that constant rather than scraping the demo-reveal line.
 */
class SiteAssistantInChatLoginTest extends TestCase
{
    use RefreshDatabase;

    /** The deterministic OTP issued outside production. */
    private const CODE = '123456';

    protected function setUp(): void
    {
        parent::setUp();

        // Email OTP defaults on; mobile login defaults off. Pin both so a
        // changed default can't silently flip the behaviour under test.
        AppSetting::put(AuthMethods::SETTING_EMAIL_OTP_ENABLED, true);
        AppSetting::put(AuthMethods::SETTING_MOBILE_ENABLED, false);
        AppSetting::put(AuthMethods::SETTING_REGISTRATION_PAUSED, false);
    }

    private function makeUser(string $email): User
    {
        return User::create([
            'name'     => 'Existing Member',
            'email'    => strtolower($email),
            'password' => bcrypt('x'),
            'status'   => 'active',
            'role'     => 'user',
        ]);
    }

    private function uniqueEmail(string $prefix = 'chat'): string
    {
        return $prefix . '-' . Str::lower(Str::random(8)) . '@example.com';
    }

    // ---- send-code: code issuance ----------------------------------

    public function test_send_code_issues_an_otp_for_an_email_identifier(): void
    {
        $email = $this->uniqueEmail();

        $response = $this->postJson(route('site-assistant.auth.send-code'), [
            'identifier' => $email,
            'type'       => 'email',
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true, 'sent' => true]);

        // A real code row was written for this identifier.
        $this->assertSame(1, DB::table('otps')
            ->where('identifier', $email)
            ->where('type', 'email')
            ->where('purpose', 'login')
            ->where('used', false)
            ->count());

        // Demo-reveal is on by default in the testing env, so the issued
        // code is surfaced for reviewers.
        $this->assertStringContainsString(self::CODE, (string) $response->json('demo_reveal'));
    }

    // ---- blade widget: session login (no token) --------------------

    public function test_verify_code_signs_existing_user_in_via_session(): void
    {
        $email = $this->uniqueEmail();
        $user  = $this->makeUser($email);

        $this->postJson(route('site-assistant.auth.send-code'), [
            'identifier' => $email,
            'type'       => 'email',
        ])->assertOk();

        $response = $this->postJson(route('site-assistant.auth.verify-code'), [
            'identifier' => $email,
            'type'       => 'email',
            'code'       => self::CODE,
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);
        // No bearer token is minted on the same-origin path.
        $this->assertNull($response->json('token'));
        // A real web session was established in place.
        $this->assertAuthenticatedAs($user);
    }

    public function test_verify_code_creates_account_on_unknown_identifier(): void
    {
        $email = $this->uniqueEmail('newbie');
        $this->assertSame(0, User::where('email', $email)->count());

        $this->postJson(route('site-assistant.auth.send-code'), [
            'identifier' => $email,
            'type'       => 'email',
        ])->assertOk();

        $response = $this->postJson(route('site-assistant.auth.verify-code'), [
            'identifier' => $email,
            'type'       => 'email',
            'code'       => self::CODE,
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);

        // Login == signup: the account now exists and is signed in.
        $created = User::where('email', $email)->first();
        $this->assertNotNull($created);
        $this->assertAuthenticatedAs($created);
    }

    // ---- marketing widget: bearer-token login ----------------------

    public function test_verify_code_mints_bearer_token_when_requested(): void
    {
        $email = $this->uniqueEmail('mkt');

        $this->postJson(route('site-assistant.auth.send-code'), [
            'identifier' => $email,
            'type'       => 'email',
        ])->assertOk();

        $response = $this->postJson(route('site-assistant.auth.verify-code'), [
            'identifier'  => $email,
            'type'        => 'email',
            'code'        => self::CODE,
            'issue_token' => true,
        ]);

        $response->assertOk();
        $token = (string) $response->json('token');
        $this->assertNotEmpty($token);

        // The minted token authenticates subsequent /assistant/* calls:
        // bootstrap drops auth_required once the bearer is replayed.
        $boot = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson(route('site-assistant.bootstrap'));
        $boot->assertOk();
        $boot->assertJson(['auth_required' => false]);
    }

    // ---- registration pause ----------------------------------------

    public function test_send_code_blocks_unknown_identifier_when_registration_paused(): void
    {
        AppSetting::put(AuthMethods::SETTING_REGISTRATION_PAUSED, true);
        $email = $this->uniqueEmail('paused');

        $response = $this->postJson(route('site-assistant.auth.send-code'), [
            'identifier' => $email,
            'type'       => 'email',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['ok' => false, 'code' => AuthMethods::ERROR_REGISTRATION_PAUSED]);

        // No code issued, nothing to brute-force later.
        $this->assertSame(0, DB::table('otps')->where('identifier', $email)->count());
    }

    public function test_verify_code_blocks_new_account_when_registration_paused(): void
    {
        $email = $this->uniqueEmail('paused2');

        // Issue a real code while registrations are still open...
        app(OtpService::class)->generate($email, 'email', 'login', 'web');
        // ...then pause before the unknown identifier is verified.
        AppSetting::put(AuthMethods::SETTING_REGISTRATION_PAUSED, true);

        $response = $this->postJson(route('site-assistant.auth.verify-code'), [
            'identifier' => $email,
            'type'       => 'email',
            'code'       => self::CODE,
        ]);

        $response->assertStatus(403);
        $response->assertJson(['ok' => false, 'code' => AuthMethods::ERROR_REGISTRATION_PAUSED]);
        $this->assertSame(0, User::where('email', $email)->count());
    }

    // ---- two-factor bounce -----------------------------------------

    public function test_verify_code_bounces_two_factor_accounts_to_login_page(): void
    {
        $email = $this->uniqueEmail('tfa');
        $user  = $this->makeUser($email);
        $user->forceFill([
            'two_factor_secret'       => encrypt('SECRETSECRET'),
            'two_factor_confirmed_at' => now(),
        ])->save();

        app(OtpService::class)->generate($email, 'email', 'login', 'web');

        $response = $this->postJson(route('site-assistant.auth.verify-code'), [
            'identifier' => $email,
            'type'       => 'email',
            'code'       => self::CODE,
        ]);

        $response->assertStatus(409);
        $response->assertJson(['ok' => false, 'twofactor' => true]);
        $this->assertStringContainsString('/login', (string) $response->json('login_url'));
        // Not signed in: the factor was not bypassed.
        $this->assertGuest();
    }

    // ---- bot defences: honeypot + time-trap ------------------------

    public function test_send_code_honeypot_silently_drops_submission(): void
    {
        $email = $this->uniqueEmail('bot');

        $response = $this->postJson(route('site-assistant.auth.send-code'), [
            'identifier' => $email,
            'type'       => 'email',
            'website'    => 'http://spam.example', // decoy filled => bot
        ]);

        // Generic success (no signal to the bot) but nothing was issued.
        $response->assertOk();
        $response->assertJson(['ok' => true, 'sent' => true]);
        $this->assertNull($response->json('demo_reveal'));
        $this->assertSame(0, DB::table('otps')->where('identifier', $email)->count());
    }

    public function test_send_code_time_trap_silently_drops_fast_submission(): void
    {
        $email = $this->uniqueEmail('fast');

        $response = $this->postJson(route('site-assistant.auth.send-code'), [
            'identifier' => $email,
            'type'       => 'email',
            'elapsed_ms' => 100, // below MIN_FILL_MS (2000) => bot
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true, 'sent' => true]);
        $this->assertSame(0, DB::table('otps')->where('identifier', $email)->count());
    }

    public function test_verify_code_rejects_honeypot_and_time_trap(): void
    {
        $email = $this->uniqueEmail('botverify');
        $this->makeUser($email);
        app(OtpService::class)->generate($email, 'email', 'login', 'web');

        $honeypot = $this->postJson(route('site-assistant.auth.verify-code'), [
            'identifier' => $email,
            'type'       => 'email',
            'code'       => self::CODE,
            'website'    => 'http://spam.example',
        ]);
        $honeypot->assertStatus(422);
        $this->assertGuest();

        $fast = $this->postJson(route('site-assistant.auth.verify-code'), [
            'identifier' => $email,
            'type'       => 'email',
            'code'       => self::CODE,
            'elapsed_ms' => 50,
        ]);
        $fast->assertStatus(422);
        $this->assertGuest();
    }

    // ---- bad code ---------------------------------------------------

    public function test_verify_code_rejects_an_invalid_code(): void
    {
        $email = $this->uniqueEmail('wrong');
        $this->makeUser($email);
        app(OtpService::class)->generate($email, 'email', 'login', 'web');

        $response = $this->postJson(route('site-assistant.auth.verify-code'), [
            'identifier' => $email,
            'type'       => 'email',
            'code'       => '000000',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['ok' => false, 'code' => 'invalid_otp']);
        $this->assertGuest();
    }

    // ---- method policy ---------------------------------------------

    public function test_send_code_rejects_mobile_when_mobile_login_disabled(): void
    {
        // Mobile login is off by default (pinned in setUp()).
        $response = $this->postJson(route('site-assistant.auth.send-code'), [
            'identifier' => '+15551234567',
            'type'       => 'mobile',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['ok' => false, 'code' => 'mobile_login_disabled']);
    }

    // ---- regression: anonymous "Contact us" still works ------------

    public function test_quick_contact_still_works_for_anonymous_visitor(): void
    {
        $response = $this->postJson(route('site-assistant.quick-contact'), [
            'channel'    => 'email',
            'email'      => 'lead@example.com',
            'message'    => 'Please reach out.',
            'elapsed_ms' => 5000,
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);
    }
}
