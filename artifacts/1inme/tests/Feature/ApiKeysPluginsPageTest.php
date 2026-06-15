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

    public function test_test_alert_action_posts_to_discord_and_flashes_success(): void
    {
        Http::fake(['discord.com/*' => Http::response('', 204)]);

        $admin = $this->makeAdminWithPermission('settings.manage');
        IntegrationKeySettings::setDiscordWebhookUrl('https://discord.com/api/webhooks/123/abc');

        $this->actingAs($admin, 'admin')
            ->post('/admin/api-keys/test-alert', ['channel' => 'discord'])
            ->assertRedirect()
            ->assertSessionHas('success');

        Http::assertSent(fn ($req) =>
            str_contains($req->url(), 'discord.com') &&
            isset($req->data()['content'])
        );
    }

    public function test_test_alert_action_flashes_error_when_discord_hook_fails(): void
    {
        Http::fake(['discord.com/*' => Http::response('boom', 500)]);

        $admin = $this->makeAdminWithPermission('settings.manage');
        IntegrationKeySettings::setDiscordWebhookUrl('https://discord.com/api/webhooks/123/abc');

        $this->actingAs($admin, 'admin')
            ->post('/admin/api-keys/test-alert', ['channel' => 'discord'])
            ->assertRedirect()
            ->assertSessionHas('error');

        Http::assertSent(fn ($req) => str_contains($req->url(), 'discord.com'));
    }

    // ── WhatsApp test message ─────────────────────────────────────

    public function test_test_whatsapp_dispatches_via_otp_when_configured(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'x']]], 200)]);

        $admin = $this->makeAdminWithPermission('settings.manage');
        IntegrationKeySettings::setWhatsappPhoneNumberId('123456789012345');
        IntegrationKeySettings::setWhatsappAccessToken('EAAB-token');
        IntegrationKeySettings::setWhatsappGraphVersion('v21.0');

        $this->actingAs($admin, 'admin')
            ->post('/admin/api-keys/test-whatsapp', ['test_number' => '+15551234567'])
            ->assertRedirect()
            ->assertSessionHas('success');

        // The OTP path actually called the WhatsApp Cloud API.
        Http::assertSent(fn ($req) =>
            str_contains($req->url(), 'graph.facebook.com') &&
            str_contains($req->url(), '123456789012345') &&
            $req->hasHeader('Authorization', 'Bearer EAAB-token')
        );
    }

    public function test_test_whatsapp_preview_mode_logs_and_flashes_info(): void
    {
        Http::fake();

        $admin = $this->makeAdminWithPermission('settings.manage');
        // No WhatsApp credentials stored → preview mode.

        $this->actingAs($admin, 'admin')
            ->post('/admin/api-keys/test-whatsapp', ['test_number' => '+15551234567'])
            ->assertRedirect()
            ->assertSessionHas('info');

        // Preview mode means nothing is actually sent to Meta.
        Http::assertNothingSent();
    }

    // ── Per-category alert toggles ────────────────────────────────

    public function test_categories_default_to_enabled(): void
    {
        // Nothing stored yet → every category fans out (backward compatible).
        $this->assertTrue(IntegrationKeySettings::alertCategoryEnabled(IntegrationKeySettings::ALERT_CATEGORY_RENEWAL));
        $this->assertTrue(IntegrationKeySettings::alertCategoryEnabled(IntegrationKeySettings::ALERT_CATEGORY_JOB));
        $this->assertTrue(IntegrationKeySettings::alertCategoryEnabled(IntegrationKeySettings::ALERT_CATEGORY_BROADCAST));
        // Unknown / uncategorised alerts always send.
        $this->assertTrue(IntegrationKeySettings::alertCategoryEnabled('something-else'));
    }

    public function test_payment_category_is_always_on_and_cannot_be_muted(): void
    {
        IntegrationKeySettings::setAlertCategoryEnabled(IntegrationKeySettings::ALERT_CATEGORY_PAYMENT, false);

        // The setter refuses to persist an always-on category as off.
        $this->assertTrue(IntegrationKeySettings::alertCategoryEnabled(IntegrationKeySettings::ALERT_CATEGORY_PAYMENT));
    }

    public function test_muted_category_is_skipped_by_dispatcher(): void
    {
        Http::fake(['hooks.slack.com/*' => Http::response('ok', 200)]);

        IntegrationKeySettings::setAlertsEnabled(true);
        IntegrationKeySettings::setSlackWebhookUrl('https://hooks.slack.com/services/T/B/abc');
        IntegrationKeySettings::setAlertCategoryEnabled(IntegrationKeySettings::ALERT_CATEGORY_JOB, false);

        $res = InternalAlertDispatcher::send('Title', 'Body', 'error', [], IntegrationKeySettings::ALERT_CATEGORY_JOB);

        $this->assertTrue($res['enabled']);
        $this->assertTrue($res['muted'] ?? false);
        $this->assertSame([], $res['channels']);
        Http::assertNothingSent();
    }

    public function test_critical_category_still_sends_even_if_others_muted(): void
    {
        Http::fake(['hooks.slack.com/*' => Http::response('ok', 200)]);

        IntegrationKeySettings::setAlertsEnabled(true);
        IntegrationKeySettings::setSlackWebhookUrl('https://hooks.slack.com/services/T/B/abc');
        IntegrationKeySettings::setAlertCategoryEnabled(IntegrationKeySettings::ALERT_CATEGORY_PAYMENT, false);

        $res = InternalAlertDispatcher::send('Pay', 'Body', 'critical', [], IntegrationKeySettings::ALERT_CATEGORY_PAYMENT);

        $this->assertTrue($res['enabled']);
        $this->assertArrayNotHasKey('muted', $res);
        $this->assertSame('slack', $res['channels'][0]['channel']);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'hooks.slack.com'));
    }

    public function test_update_persists_category_toggles(): void
    {
        $admin = $this->makeAdminWithPermission('settings.manage');

        $this->actingAs($admin, 'admin')
            ->put('/admin/api-keys', [
                'alerts_enabled'      => '1',
                // job left unchecked (hidden 0 only) → muted
                'alert_cat_job'       => '0',
                // renewal checked → enabled
                'alert_cat_renewal'   => '1',
                'alert_cat_broadcast' => '1',
            ])
            ->assertRedirect(route('admin.api-keys.index'));

        $this->assertFalse(IntegrationKeySettings::alertCategoryEnabled(IntegrationKeySettings::ALERT_CATEGORY_JOB));
        $this->assertTrue(IntegrationKeySettings::alertCategoryEnabled(IntegrationKeySettings::ALERT_CATEGORY_RENEWAL));
        // Payment stays on regardless of the form.
        $this->assertTrue(IntegrationKeySettings::alertCategoryEnabled(IntegrationKeySettings::ALERT_CATEGORY_PAYMENT));
    }
}
