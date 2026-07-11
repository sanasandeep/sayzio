<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Rules\NotRetiredAdminEmail;
use App\Modules\Common\Support\RetiredAdminEmails;
use App\Modules\User\Models\User;
use App\Services\Integrations\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Guards the server-side rejection of the now-retired privileged admin email
 * addresses from admin-editable recipient settings (billing CC list, contact
 * recipient, mail "From" address). A one-off migration scrubbed those
 * addresses out of `app_settings`; these validators stop an admin from typing
 * them straight back in and recreating the same identity confusion.
 *
 * @see \App\Modules\Common\Support\RetiredAdminEmails
 * @see \App\Modules\Admin\Rules\NotRetiredAdminEmail
 */
class RetiredAdminEmailValidationTest extends TestCase
{
    use RefreshDatabase;

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

        $user = User::factory()->create()->fresh();
        $user->roles()->attach($role->id);
        $user->flushPermissionCache();
        return $user->fresh();
    }

    private function asUser(User $user): self
    {
        $this->withToken($user->createToken('test')->plainTextToken);
        return $this;
    }

    public function test_helper_detects_retired_addresses_case_insensitively(): void
    {
        foreach (RetiredAdminEmails::RETIRED as $retired) {
            $this->assertTrue(RetiredAdminEmails::isRetired($retired));
            $this->assertTrue(RetiredAdminEmails::isRetired(strtoupper($retired)));
            $this->assertTrue(RetiredAdminEmails::isRetired('  ' . $retired . '  '));
            $this->assertSame(RetiredAdminEmails::CANONICAL, RetiredAdminEmails::normalize($retired));
        }

        $this->assertFalse(RetiredAdminEmails::isRetired(RetiredAdminEmails::CANONICAL));
        $this->assertFalse(RetiredAdminEmails::isRetired('leads@sayzio.app'));
        $this->assertFalse(RetiredAdminEmails::isRetired(''));
        $this->assertFalse(RetiredAdminEmails::isRetired(null));
        $this->assertSame('leads@sayzio.app', RetiredAdminEmails::normalize('leads@sayzio.app'));
    }

    public function test_rule_fails_retired_and_passes_others(): void
    {
        $rule = new NotRetiredAdminEmail();

        $fail = Validator::make(
            ['e' => 'admin@1inme.com'],
            ['e' => [$rule]],
        );
        $this->assertTrue($fail->fails());

        $ok = Validator::make(
            ['e' => 'no-reply@sayzio.app'],
            ['e' => [$rule]],
        );
        $this->assertFalse($ok->fails());
    }

    public function test_api_mail_settings_rejects_retired_from_address(): void
    {
        $this->asUser($this->makeAdmin());

        $resp = $this->putJson('/api/v1/admin/mail-settings', [
            'mailer'       => 'log',
            'encryption'   => 'tls',
            'from_address' => 'sanasandeep@gmail.com',
            'from_name'    => 'Sayzio',
        ]);

        // API routes reformat validation errors into the unified
        // {error:{code,details}} envelope (see bootstrap/app.php).
        $resp->assertStatus(422);
        $resp->assertJsonPath('error.code', 'validation_failed');
        $this->assertArrayHasKey('from_address', (array) $resp->json('error.details'));
    }

    public function test_api_mail_settings_accepts_canonical_from_address(): void
    {
        $this->asUser($this->makeAdmin());

        $this->putJson('/api/v1/admin/mail-settings', [
            'mailer'       => 'log',
            'encryption'   => 'tls',
            'from_address' => RetiredAdminEmails::CANONICAL,
            'from_name'    => 'Sayzio',
        ])->assertOk();

        $this->assertSame(RetiredAdminEmails::CANONICAL, MailSettings::fromAddress());
    }
}
