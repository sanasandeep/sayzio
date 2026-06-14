<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\AuthMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task #1424 — email is the only login identifier by default; WhatsApp
 * (mobile) OTP login is behind an admin toggle with an allowed-country-code
 * list. These cover the REST API surface (web mirrors the same policy via
 * AuthMethods).
 */
class EmailOnlyLoginPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_endpoint_defaults_to_email_only(): void
    {
        $this->getJson('/api/v1/auth/config')
            ->assertOk()
            ->assertJsonPath('data.mobile_login_enabled', false)
            ->assertJsonPath('data.allowed_country_codes', ['+91', '+1']);
    }

    public function test_config_endpoint_reflects_admin_toggle(): void
    {
        AppSetting::put(AuthMethods::SETTING_MOBILE_ENABLED, true);
        AppSetting::put(AuthMethods::SETTING_ALLOWED_CODES, ['+44']);

        $this->getJson('/api/v1/auth/config')
            ->assertOk()
            ->assertJsonPath('data.mobile_login_enabled', true)
            ->assertJsonPath('data.allowed_country_codes', ['+44']);
    }

    public function test_mobile_otp_send_is_rejected_when_disabled(): void
    {
        $this->postJson('/api/v1/auth/otp/send', [
            'identifier' => '+919876543210',
            'type' => 'mobile',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'mobile_login_disabled');
    }

    public function test_mobile_otp_send_rejects_disallowed_country_code_when_enabled(): void
    {
        AppSetting::put(AuthMethods::SETTING_MOBILE_ENABLED, true);
        AppSetting::put(AuthMethods::SETTING_ALLOWED_CODES, ['+91']);

        $this->postJson('/api/v1/auth/otp/send', [
            'identifier' => '+15551234567',
            'type' => 'mobile',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'country_code_not_allowed');
    }

    public function test_email_otp_send_always_succeeds(): void
    {
        $this->postJson('/api/v1/auth/otp/send', [
            'identifier' => 'nobody@example.com',
            'type' => 'email',
        ])
            ->assertOk()
            ->assertJsonPath('data.sent', true);
    }

    public function test_allowed_mobile_passes_country_guard_when_enabled(): void
    {
        AppSetting::put(AuthMethods::SETTING_MOBILE_ENABLED, true);
        AppSetting::put(AuthMethods::SETTING_ALLOWED_CODES, ['+91']);

        // No account exists for this number, but the country guard must pass
        // so we get the generic enumeration-safe success rather than a 422.
        $this->postJson('/api/v1/auth/otp/send', [
            'identifier' => '+919876543210',
            'type' => 'mobile',
        ])
            ->assertOk()
            ->assertJsonPath('data.sent', true);
    }
}
