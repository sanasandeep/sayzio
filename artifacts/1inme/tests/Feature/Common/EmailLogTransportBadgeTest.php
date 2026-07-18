<?php

namespace Tests\Feature\Common;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Models\EmailLog;
use App\Modules\Common\Services\Emailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the effective-transport stamping on email_logs rows and the admin
 * "log driver (not delivered)" badge that flags black-holed non-production
 * sends so an operator isn't misled by a false "sent".
 */
class EmailLogTransportBadgeTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdminWithPermission(string $permSlug): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'staff-' . $permSlug],
            ['name' => 'Staff (' . $permSlug . ')', 'guard' => 'admin']
        );
        $perm = Permission::firstOrCreate(
            ['slug' => $permSlug],
            ['name' => $permSlug, 'group' => explode('.', $permSlug)[0] ?? 'misc']
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);

        return Admin::create([
            'name'     => 'Admin ' . Str::random(4),
            'email'    => 'a' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    public function test_send_through_log_driver_stamps_transport(): void
    {
        config(['mail.default' => 'log']);

        $log = Emailer::send('uncategorized', 'nobody@example.test', [], [
            'subject' => 'Hello',
            'body'    => 'Body',
        ]);

        $this->assertNotNull($log);
        $this->assertSame('sent', $log->status);
        $this->assertSame('log', $log->transport);
    }

    public function test_send_through_smtp_driver_stamps_transport(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.transport' => 'smtp']);
        // Fake so nothing actually leaves the process while the config still
        // resolves a delivering (non log/array) transport.
        \Illuminate\Support\Facades\Mail::fake();

        $log = Emailer::send('uncategorized', 'nobody@example.test', [], [
            'subject' => 'Hello',
            'body'    => 'Body',
        ]);

        $this->assertNotNull($log);
        $this->assertSame('smtp', $log->transport);
    }

    public function test_admin_index_shows_not_delivered_badge_for_log_rows(): void
    {
        EmailLog::create([
            'email_key' => 'uncategorized',
            'category'  => 'uncategorized',
            'recipient' => 'delivered@example.test',
            'subject'   => 'Delivered subject',
            'body'      => 'x',
            'format'    => 'html',
            'transport' => 'smtp',
            'status'    => 'sent',
        ]);

        EmailLog::create([
            'email_key' => 'uncategorized',
            'category'  => 'uncategorized',
            'recipient' => 'swallowed@example.test',
            'subject'   => 'Swallowed subject',
            'body'      => 'x',
            'format'    => 'html',
            'transport' => 'log',
            'status'    => 'sent',
        ]);

        $admin = $this->makeAdminWithPermission('settings.manage');

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.email-logs.index'));

        $response->assertOk();
        $response->assertSee('Log driver (not delivered)');
        // The delivered (smtp) row still reads as a plain "Sent".
        $response->assertSee('Sent');
    }
}
