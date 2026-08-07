<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;
use App\Services\Integrations\TurnstileSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Invisible Turnstile captcha on the WEB sign-up and OTP-send/resend flows
 * (Task #6704).
 *
 * Contract under test:
 *   - Disabled / unconfigured (the default) ⇒ every flow behaves exactly as
 *     before: no token required, no widget rendered, no script loaded.
 *   - Enabled + missing or invalid token ⇒ friendly rejection on BOTH the
 *     classic redirect path and the AJAX/JSON path; no user is created and
 *     no OTP row is inserted.
 *   - Enabled + valid (mocked) token ⇒ the flow proceeds normally.
 */
class TurnstileAuthGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Plan::firstOrCreate(['slug' => 'free'], [
            'name' => 'Free', 'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'grace_days' => 0, 'refund_window_days' => 0,
            'status' => 'active', 'sort_order' => 0, 'features' => [],
            'is_default' => true,
        ]);
    }

    private function enableTurnstile(): void
    {
        TurnstileSettings::setSiteKey('1x00000000000000000000AA');
        TurnstileSettings::setSecretKey('1x0000000000000000000000000000000AA');
        TurnstileSettings::setEnabled(true);
        $this->assertTrue(TurnstileSettings::enabled());
    }

    private function fakeSiteverify(bool $success): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => $success], 200),
        ]);
    }

    private function registerPayload(string $email): array
    {
        // Password fields included for when email-password login is enabled
        // (the default); harmlessly ignored in OTP-only mode.
        return [
            'name'                  => 'Turnstile Tester',
            'email'                 => $email,
            'password'              => 'secret-password-123',
            'password_confirmation' => 'secret-password-123',
        ];
    }

    private function uniqueEmail(): string
    {
        return 'turnstile-' . Str::lower(Str::random(10)) . '@example.com';
    }

    // ── Disabled = unchanged ─────────────────────────────────────

    public function test_disabled_register_needs_no_token_and_renders_no_widget(): void
    {
        $this->assertFalse(TurnstileSettings::enabled());

        $page = $this->get(route('user.register'));
        $page->assertOk();
        $page->assertDontSee('cf-turnstile', false);
        $page->assertDontSee('challenges.cloudflare.com', false);

        $email = $this->uniqueEmail();
        $resp  = $this->post(route('user.register.submit'), $this->registerPayload($email));
        $resp->assertRedirect();
        $this->assertNotNull(User::where('email', $email)->first());
    }

    public function test_disabled_send_otp_needs_no_token(): void
    {
        $email = $this->uniqueEmail();
        $resp  = $this->post(route('user.otp.send'), ['identifier' => $email, 'type' => 'email']);
        $resp->assertRedirect(route('user.otp.verify.form'));
        $this->assertSame(1, DB::table('otps')->where('identifier', $email)->count());
    }

    // ── Enabled + missing token ──────────────────────────────────

    public function test_enabled_register_missing_token_creates_no_user_classic(): void
    {
        $this->enableTurnstile();
        Http::fake(); // no request should even be attempted for an empty token

        $email = $this->uniqueEmail();
        $resp  = $this->from(route('user.register'))
            ->post(route('user.register.submit'), $this->registerPayload($email));

        $resp->assertRedirect(route('user.register'));
        $resp->assertSessionHasErrors('turnstile');
        $this->assertNull(User::where('email', $email)->first());
        Http::assertNothingSent();
    }

    public function test_enabled_register_missing_token_creates_no_user_ajax(): void
    {
        $this->enableTurnstile();

        $email = $this->uniqueEmail();
        $resp  = $this->post(route('user.register.submit'), $this->registerPayload($email), [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $resp->assertOk();
        $resp->assertJson(['ok' => false]);
        $resp->assertJsonPath('errors._', fn ($m) => is_string($m) && $m !== '');
        $this->assertNull(User::where('email', $email)->first());
    }

    public function test_enabled_send_otp_missing_token_sends_nothing_ajax(): void
    {
        $this->enableTurnstile();

        $email = $this->uniqueEmail();
        $resp  = $this->post(route('user.otp.send'), ['identifier' => $email, 'type' => 'email'], [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $resp->assertOk();
        $resp->assertJson(['ok' => false]);
        $this->assertSame(0, DB::table('otps')->where('identifier', $email)->count());
    }

    public function test_enabled_resend_otp_missing_token_sends_nothing(): void
    {
        $this->enableTurnstile();

        $email = $this->uniqueEmail();
        $resp  = $this->withSession(['otp_identifier' => $email, 'otp_type' => 'email'])
            ->post(route('user.otp.resend'), [], ['X-Requested-With' => 'XMLHttpRequest']);

        $resp->assertOk();
        $resp->assertJson(['ok' => false]);
        $this->assertSame(0, DB::table('otps')->where('identifier', $email)->count());
    }

    // ── Enabled + invalid token ──────────────────────────────────

    public function test_enabled_register_invalid_token_rejected(): void
    {
        $this->enableTurnstile();
        $this->fakeSiteverify(false);

        $email = $this->uniqueEmail();
        $resp  = $this->from(route('user.register'))->post(route('user.register.submit'),
            $this->registerPayload($email) + [TurnstileSettings::TOKEN_FIELD => 'bad-token']);

        $resp->assertRedirect(route('user.register'));
        $resp->assertSessionHasErrors('turnstile');
        $this->assertNull(User::where('email', $email)->first());
        Http::assertSentCount(1);
    }

    public function test_enabled_send_otp_invalid_token_rejected(): void
    {
        $this->enableTurnstile();
        $this->fakeSiteverify(false);

        $email = $this->uniqueEmail();
        $resp  = $this->post(route('user.otp.send'),
            ['identifier' => $email, 'type' => 'email', TurnstileSettings::TOKEN_FIELD => 'bad-token'],
            ['X-Requested-With' => 'XMLHttpRequest']);

        $resp->assertOk();
        $resp->assertJson(['ok' => false]);
        $this->assertSame(0, DB::table('otps')->where('identifier', $email)->count());
    }

    // ── Enabled + valid token ────────────────────────────────────

    public function test_enabled_register_valid_token_succeeds(): void
    {
        $this->enableTurnstile();
        $this->fakeSiteverify(true);

        $email = $this->uniqueEmail();
        $resp  = $this->post(route('user.register.submit'),
            $this->registerPayload($email) + [TurnstileSettings::TOKEN_FIELD => 'good-token']);

        $resp->assertRedirect();
        $this->assertNotNull(User::where('email', $email)->first());
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'challenges.cloudflare.com')
                && $request['response'] === 'good-token';
        });
    }

    public function test_enabled_send_otp_valid_token_succeeds_ajax(): void
    {
        $this->enableTurnstile();
        $this->fakeSiteverify(true);

        $email = $this->uniqueEmail();
        $resp  = $this->post(route('user.otp.send'),
            ['identifier' => $email, 'type' => 'email', TurnstileSettings::TOKEN_FIELD => 'good-token'],
            ['X-Requested-With' => 'XMLHttpRequest']);

        $resp->assertOk();
        $resp->assertJson(['ok' => true]);
        $this->assertSame(1, DB::table('otps')->where('identifier', $email)->count());
    }

    // ── Widget rendering when enabled ────────────────────────────

    public function test_enabled_register_page_renders_widget_and_script(): void
    {
        $this->enableTurnstile();

        $page = $this->get(route('user.register'));
        $page->assertOk();
        $page->assertSee('cf-turnstile', false);
        $page->assertSee('challenges.cloudflare.com/turnstile/v0/api.js', false);

        $login = $this->get(route('user.login'));
        $login->assertOk();
        $login->assertSee('cf-turnstile', false);
    }
}
