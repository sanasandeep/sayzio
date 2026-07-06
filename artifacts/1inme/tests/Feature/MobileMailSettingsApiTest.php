<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use App\Services\Integrations\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Coverage for the mobile mail-settings parity endpoints (Task #1589):
 *
 *   GET  /api/v1/admin/mail-settings       (status badge + effective transport)
 *   POST /api/v1/admin/mail-settings/test  (live "send test email")
 *
 * Both are gated behind the same `settings.manage` permission the web admin
 * "Email / SMTP" page uses, so a regular sanctum token must be rejected. The
 * status payload reports a status badge and the effective transport WITHOUT
 * ever leaking the stored SMTP password, and the test-send endpoint flags the
 * no-delivery "log" driver case (sent=false) instead of a false success.
 */
class MobileMailSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create()->fresh();
    }

    /** A user holding the web-guard `settings.manage` permission (super admin). */
    private function makeAdmin(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'platform-settings'],
            ['name' => 'Platform Settings', 'guard' => 'web']
        );
        $perm = Permission::firstOrCreate(
            ['slug' => 'settings.manage'],
            ['name' => 'Manage Settings', 'group' => 'settings']
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);

        $user = $this->makeUser();
        $user->roles()->attach($role->id);
        $user->flushPermissionCache();
        return $user->fresh();
    }

    /**
     * Authenticate as the given user using a REAL Sanctum personal access
     * token (Bearer header), exactly like the Expo app. We deliberately avoid
     * Sanctum::actingAs: it injects a Mockery mock as the current access
     * token, which the TouchSessionToken middleware can't forceFill()->save()
     * on, 500ing every authenticated request.
     */
    private function asUser(User $user): self
    {
        $plain = $user->createToken('mobile-test')->plainTextToken;
        $this->withToken($plain);
        return $this;
    }

    public function test_status_forbidden_for_a_non_admin_token(): void
    {
        $this->asUser($this->makeUser());

        $this->getJson('/api/v1/admin/mail-settings')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_test_send_forbidden_for_a_non_admin_token(): void
    {
        $this->asUser($this->makeUser());

        $this->postJson('/api/v1/admin/mail-settings/test', [
            'test_email' => 'someone@example.com',
        ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_status_returns_badge_and_effective_transport_without_leaking_password(): void
    {
        $admin = $this->makeAdmin();

        // An admin-saved transport, including a stored SMTP password that must
        // never appear in the API response.
        MailSettings::setMailer('smtp');
        MailSettings::setHost('smtp.example.test');
        MailSettings::setPort(587);
        MailSettings::setEncryption('tls');
        MailSettings::setFromAddress('hello@example.test');
        MailSettings::setFromName('Example');
        MailSettings::setPassword('sup3r-s3cret-pw');

        $this->asUser($admin);
        $resp = $this->getJson('/api/v1/admin/mail-settings');

        $resp->assertOk();
        // Status badge mirrors the web admin badge (configured/env/log).
        $resp->assertJsonPath('data.status.key', 'configured');
        // Effective transport is reported.
        $resp->assertJsonPath('data.mailer', 'smtp');
        $resp->assertJsonPath('data.host', 'smtp.example.test');
        $resp->assertJsonPath('data.port', 587);
        $resp->assertJsonPath('data.encryption', 'tls');
        $resp->assertJsonPath('data.from_address', 'hello@example.test');
        $resp->assertJsonPath('data.from_name', 'Example');
        // The presence of a password is reported as a boolean flag only.
        $resp->assertJsonPath('data.has_password', true);

        // The plaintext password (and any explicit field for it) is never
        // exposed in the payload.
        $this->assertStringNotContainsString('sup3r-s3cret-pw', $resp->getContent());
        $this->assertArrayNotHasKey('password', (array) $resp->json('data'));
    }

    public function test_test_send_reports_the_log_driver_as_not_delivered(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();

        // Force the no-delivery "log" mailer so the endpoint must flag it.
        MailSettings::setMailer('log');

        $this->asUser($admin);
        $resp = $this->postJson('/api/v1/admin/mail-settings/test', [
            'test_email' => 'admin@example.test',
        ]);

        $resp->assertOk();
        $resp->assertJsonPath('data.sent', false);
        $resp->assertJsonPath('data.driver', 'log');
    }

    public function test_test_send_reports_success_on_a_real_mailer(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();

        // A live (non-log) transport — Mail::fake() captures the send so no
        // real SMTP connection is made.
        MailSettings::setMailer('smtp');
        MailSettings::setHost('smtp.example.test');
        MailSettings::setPort(587);

        $this->asUser($admin);
        $resp = $this->postJson('/api/v1/admin/mail-settings/test', [
            'test_email' => 'admin@example.test',
        ]);

        $resp->assertOk();
        $resp->assertJsonPath('data.sent', true);
        $resp->assertJsonPath('data.to', 'admin@example.test');
    }

    public function test_test_send_validates_the_email_address(): void
    {
        $this->asUser($this->makeAdmin());

        $this->postJson('/api/v1/admin/mail-settings/test', [
            'test_email' => 'not-an-email',
        ])->assertStatus(422);
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/admin/mail-settings')->assertStatus(401);
        $this->postJson('/api/v1/admin/mail-settings/test', [
            'test_email' => 'a@b.test',
        ])->assertStatus(401);
    }
}
