<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Services\NotificationService;
use App\Modules\Common\Services\OtpService;
use App\Services\Integrations\IntegrationKeySettings;
use App\Services\Integrations\InternalAlertDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the unified admin "API Keys & Plugins" hub: permission gating,
 * encrypted/blank-leaves-unchanged save UX, the WhatsApp OTP admin-value
 * read-path preference, and real internal-alert webhook delivery.
 */
class ApiKeysPluginsPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminWithPermission(string|array $permSlugs): Admin
    {
        $slugs = is_array($permSlugs) ? $permSlugs : [$permSlugs];
        sort($slugs);

        $role = Role::firstOrCreate(
            ['slug' => 'staff-' . implode('-', $slugs)],
            ['name' => 'Staff (' . implode(',', $slugs) . ')', 'guard' => 'admin']
        );
        foreach ($slugs as $permSlug) {
            $perm = Permission::firstOrCreate(
                ['slug' => $permSlug],
                ['name' => $permSlug, 'group' => explode('.', $permSlug)[0] ?? 'misc']
            );
            $role->permissions()->syncWithoutDetaching([$perm->id]);
        }

        return Admin::create([
            'name'     => 'Admin ' . Str::random(4),
            'email'    => 'a' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    // ── Gating ────────────────────────────────────────────────────

    public function test_page_requires_settings_manage(): void
    {
        $viewerOnly = $this->makeAdminWithPermission('users.view');

        $this->actingAs($viewerOnly, 'admin')
            ->get('/admin/api-keys')
            ->assertForbidden();
    }

    public function test_page_loads_with_settings_manage(): void
    {
        $admin = $this->makeAdminWithPermission('settings.manage');

        $this->actingAs($admin, 'admin')
            ->get('/admin/api-keys')
            ->assertOk()
            ->assertSee('WhatsApp Cloud API')
            ->assertSee('Internal alerts');
    }

    // ── Save UX ───────────────────────────────────────────────────

    public function test_update_stores_encrypted_secrets_and_plain_config(): void
    {
        $admin = $this->makeAdminWithPermission('settings.manage');

        $this->actingAs($admin, 'admin')
            ->put('/admin/api-keys', [
                'wa_phone_number_id'   => '123456789012345',
                'wa_access_token'      => 'EAAB-secret-token',
                'wa_template_name'     => 'otp_code',
                'wa_template_language' => 'en_US',
                'wa_graph_version'     => 'v21.0',
                'alerts_enabled'       => '1',
                'slack_webhook_url'    => 'https://hooks.slack.com/services/T/B/abc',
            ])
            ->assertRedirect(route('admin.api-keys.index'));

        // Plain config persisted verbatim.
        $this->assertSame('123456789012345', IntegrationKeySettings::whatsappPhoneNumberId());

        // Secret decrypts to the original and is stored encrypted (not plaintext).
        $this->assertSame('EAAB-secret-token', IntegrationKeySettings::whatsappAccessToken());
        $rawToken = AppSetting::get(IntegrationKeySettings::KEY_WA_ACCESS_TOKEN_ENC);
        $this->assertNotSame('EAAB-secret-token', $rawToken);
        $this->assertSame('EAAB-secret-token', Crypt::decryptString($rawToken));

        // Alerts.
        $this->assertTrue(IntegrationKeySettings::alertsEnabled());
        $this->assertSame('https://hooks.slack.com/services/T/B/abc', IntegrationKeySettings::slackWebhookUrl());
    }

    public function test_blank_secret_leaves_stored_value_unchanged(): void
    {
        $admin = $this->makeAdminWithPermission('settings.manage');
        IntegrationKeySettings::setWhatsappAccessToken('original-token');

        $this->actingAs($admin, 'admin')
            ->put('/admin/api-keys', [
                'wa_phone_number_id' => '999',
                'wa_access_token'    => '', // blank → keep
            ])
            ->assertRedirect();

        $this->assertSame('original-token', IntegrationKeySettings::whatsappAccessToken());
    }

    public function test_clear_checkbox_removes_stored_secret(): void
    {
        $admin = $this->makeAdminWithPermission('settings.manage');
        IntegrationKeySettings::setWhatsappAccessToken('original-token');

        $this->actingAs($admin, 'admin')
            ->put('/admin/api-keys', [
                'wa_access_token'       => '',
                'clear_wa_access_token' => '1',
            ])
            ->assertRedirect();

        // Falls back to config (env), which is empty in tests → null.
        $this->assertNull(AppSetting::get(IntegrationKeySettings::KEY_WA_ACCESS_TOKEN_ENC));
    }

    // ── Status badges ─────────────────────────────────────────────

    public function test_whatsapp_status_reflects_admin_vs_preview(): void
    {
        $this->assertSame('preview', IntegrationKeySettings::whatsappStatus()['key']);

        IntegrationKeySettings::setWhatsappPhoneNumberId('123');
        IntegrationKeySettings::setWhatsappAccessToken('tok');
        $this->assertSame('configured', IntegrationKeySettings::whatsappStatus()['key']);
    }

    // ── OTP read-path ─────────────────────────────────────────────

    public function test_otp_prefers_admin_whatsapp_values_over_env(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'x']]], 200)]);

        IntegrationKeySettings::setWhatsappPhoneNumberId('ADMIN_PHONE_ID');
        IntegrationKeySettings::setWhatsappAccessToken('ADMIN_TOKEN');
        IntegrationKeySettings::setWhatsappGraphVersion('v21.0');

        app(OtpService::class)->sendWhatsApp('+15551234567', '123456');

        Http::assertSent(fn ($req) =>
            str_contains($req->url(), 'ADMIN_PHONE_ID') &&
            $req->hasHeader('Authorization', 'Bearer ADMIN_TOKEN')
        );
    }

    public function test_otp_preview_mode_sends_nothing_when_unconfigured(): void
    {
        Http::fake();

        app(OtpService::class)->sendWhatsApp('+15551234567', '123456');

        Http::assertNothingSent();
    }

    // ── Internal alert delivery ───────────────────────────────────

    public function test_internal_alert_fans_out_to_slack_when_enabled(): void
    {
        Http::fake(['hooks.slack.com/*' => Http::response('ok', 200)]);

        IntegrationKeySettings::setAlertsEnabled(true);
        IntegrationKeySettings::setSlackWebhookUrl('https://hooks.slack.com/services/T/B/abc');

        $res = InternalAlertDispatcher::send('Title', 'Body message', 'critical');

        $this->assertTrue($res['enabled']);
        $this->assertSame('slack', $res['channels'][0]['channel']);
        $this->assertTrue($res['channels'][0]['ok']);
        Http::assertSent(fn ($req) =>
            str_contains($req->url(), 'hooks.slack.com') &&
            isset($req->data()['text'])
        );
    }

    public function test_internal_alert_is_noop_when_disabled(): void
    {
        Http::fake();

        IntegrationKeySettings::setAlertsEnabled(false);
        IntegrationKeySettings::setSlackWebhookUrl('https://hooks.slack.com/services/T/B/abc');

        $res = app(NotificationService::class)->systemAlert('T', 'B');

        $this->assertFalse($res['enabled']);
        Http::assertNothingSent();
    }

    public function test_test_alert_action_requires_configured_webhook(): void
    {
        $admin = $this->makeAdminWithPermission('settings.manage');

        $this->actingAs($admin, 'admin')
            ->post('/admin/api-keys/test-alert', ['channel' => 'discord'])
            ->assertRedirect();
        // No webhook configured → error flash, nothing sent.
        $this->assertNull(IntegrationKeySettings::discordWebhookUrl());
    }
}
