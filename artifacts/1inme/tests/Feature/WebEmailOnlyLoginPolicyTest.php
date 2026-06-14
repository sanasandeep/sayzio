<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\AuthMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task #1424 — email is the only login identifier by default; WhatsApp
 * (mobile) OTP login is behind an admin toggle with an allowed-country-code
 * list. EmailOnlyLoginPolicyTest covers the REST/mobile surface; this one
 * guards the session-based *web* login flow (the /user/login screen and the
 * /user/send-otp + /user/resend-otp handlers) so the policy can't silently
 * regress on the web path.
 */
class WebEmailOnlyLoginPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_offers_email_only_by_default(): void
    {
        $response = $this->get('/user/login')->assertOk();

        // The email-only form renders a fixed type=email hidden field and no
        // WhatsApp toggle when mobile login has not been enabled.
        $response->assertSee('value="email"', false);
        $response->assertDontSee('WhatsApp', false);
    }

    public function test_login_screen_shows_mobile_option_when_admin_enables_it(): void
    {
        AppSetting::put(AuthMethods::SETTING_MOBILE_ENABLED, true);
        AppSetting::put(AuthMethods::SETTING_ALLOWED_CODES, ['+91', '+1']);

        $response = $this->get('/user/login')->assertOk();

        // The WhatsApp channel toggle and the supported-codes hint appear.
        $response->assertSee('WhatsApp', false);
        $response->assertSee('+91, +1', false);
    }

    public function test_web_send_otp_rejects_mobile_when_disabled(): void
    {
        $this->post('/user/send-otp', [
            'identifier' => '+919876543210',
            'type' => 'mobile',
        ])
            ->assertSessionHasErrors(['identifier']);
    }

    public function test_web_send_otp_rejects_disallowed_country_code_when_enabled(): void
    {
        AppSetting::put(AuthMethods::SETTING_MOBILE_ENABLED, true);
        AppSetting::put(AuthMethods::SETTING_ALLOWED_CODES, ['+91']);

        $this->post('/user/send-otp', [
            'identifier' => '+15551234567',
            'type' => 'mobile',
        ])
            ->assertSessionHasErrors(['identifier']);
    }

    public function test_web_send_otp_allows_email(): void
    {
        // No account exists for this address, but the email channel is always
        // permitted, so we get the enumeration-safe redirect to the verify
        // form with no identifier error.
        $this->post('/user/send-otp', [
            'identifier' => 'nobody@example.com',
            'type' => 'email',
        ])
            ->assertRedirect(route('user.otp.verify.form'))
            ->assertSessionHasNoErrors();
    }

    public function test_web_send_otp_allows_allowed_mobile_when_enabled(): void
    {
        AppSetting::put(AuthMethods::SETTING_MOBILE_ENABLED, true);
        AppSetting::put(AuthMethods::SETTING_ALLOWED_CODES, ['+91']);

        // No account exists, but the country guard must pass so we land on the
        // generic verify-form redirect rather than an identifier error.
        $this->post('/user/send-otp', [
            'identifier' => '+919876543210',
            'type' => 'mobile',
        ])
            ->assertRedirect(route('user.otp.verify.form'))
            ->assertSessionHasNoErrors();
    }

    public function test_web_resend_otp_rejects_mobile_after_admin_disables_it(): void
    {
        // A visitor mid-flow had a mobile identifier stashed in session, then
        // the admin switched WhatsApp login off. The resend path must refuse
        // to re-issue a mobile code and bounce back to the login screen.
        $this->withSession([
            'otp_identifier' => '+919876543210',
            'otp_type' => 'mobile',
        ])
            ->post('/user/resend-otp')
            ->assertRedirect(route('user.login'))
            ->assertSessionHasErrors(['identifier']);
    }
}
