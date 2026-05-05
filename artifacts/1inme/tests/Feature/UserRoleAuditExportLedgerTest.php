<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserRoleAudit;
use App\Modules\User\Models\UserRoleAuditExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the audit-the-auditor ledger: every CSV download of the
 * role-change history must append one row to
 * `user_role_audit_exports`, capturing actor (id + guard), scope,
 * row count, and IP.
 */
class UserRoleAuditExportLedgerTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::create(array_merge([
            'name'     => 'Test ' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ], $attrs));
    }

    private function makeRole(string $slug, string $guard = 'web'): Role
    {
        return Role::create([
            'name'  => ucfirst($slug),
            'slug'  => $slug . '-' . Str::random(4),
            'guard' => $guard,
        ]);
    }

    private function makeAudit(User $target, Role $role): UserRoleAudit
    {
        return UserRoleAudit::create([
            'actor_user_id'  => null,
            'actor_admin_id' => null,
            'actor_guard'    => null,
            'actor_name'     => 'System',
            'actor_email'    => null,
            'target_user_id' => $target->id,
            'role_id'        => $role->id,
            'role_slug'      => $role->slug,
            'role_name'      => $role->name,
            'action'         => 'attached',
            'source'         => 'admin',
            'ip'             => '203.0.113.1',
            'created_at'     => now(),
        ]);
    }

    public function test_user_access_full_pool_export_appends_ledger_row(): void
    {
        $userAdminRole = Role::where('slug', 'user-admin')->where('guard', 'web')->firstOrFail();
        $operator = $this->makeUser(['name' => 'Pool Puller']);
        $operator->roles()->attach($userAdminRole->id);

        $target = $this->makeUser();
        $role   = $this->makeRole('writer');
        $this->makeAudit($target, $role);
        $this->makeAudit($target, $role);
        $this->makeAudit($target, $role);

        $this->assertSame(0, UserRoleAuditExport::query()->count());

        $resp = $this->actingAs($operator, 'web')
            ->get(route('user.access.users.audit.export'));

        $resp->assertOk();
        // Force the StreamedResponse to flush so any side effects of
        // streaming have settled before the assertions below.
        $resp->streamedContent();

        $this->assertSame(1, UserRoleAuditExport::query()->count());
        $row = UserRoleAuditExport::query()->latest('id')->first();
        $this->assertNotNull($row);
        $this->assertSame(UserRoleAuditExport::SCOPE_FULL_POOL, $row->scope);
        $this->assertNull($row->target_user_id);
        $this->assertSame(3, $row->row_count);
        $this->assertSame((int) $operator->id, (int) $row->actor_user_id);
        $this->assertNull($row->actor_admin_id);
        $this->assertSame('web', $row->actor_guard);
        $this->assertSame($operator->name, $row->actor_name);
        $this->assertSame($operator->email, $row->actor_email);
    }

    public function test_admin_single_user_export_appends_scoped_ledger_row(): void
    {
        $superRole = Role::firstOrCreate(
            ['slug' => 'super-admin', 'guard' => 'admin'],
            ['name' => 'Super Admin']
        );
        $admin = Admin::create([
            'name'     => 'Audit Admin',
            'email'    => 'audit-admin@admin.test',
            'password' => Hash::make('x'),
            'role_id'  => $superRole->id,
            'status'   => 'active',
        ]);

        $targetA = $this->makeUser(['name' => 'Subject Sam']);
        $targetB = $this->makeUser(['name' => 'Other Olly']);
        $role    = $this->makeRole('reviewer');

        $this->makeAudit($targetA, $role);
        $this->makeAudit($targetA, $role);
        $this->makeAudit($targetB, $role);

        $resp = $this->actingAs($admin, 'admin')
            ->get(route('admin.users.roles.audit.export', $targetA));

        $resp->assertOk();
        $resp->streamedContent();

        $row = UserRoleAuditExport::query()->latest('id')->first();
        $this->assertNotNull($row);
        $this->assertSame(UserRoleAuditExport::SCOPE_SINGLE_USER, $row->scope);
        $this->assertSame((int) $targetA->id, (int) $row->target_user_id);
        // Only the two rows belonging to $targetA were in scope.
        $this->assertSame(2, $row->row_count);
        $this->assertSame((int) $admin->id, (int) $row->actor_admin_id);
        $this->assertNull($row->actor_user_id);
        $this->assertSame('admin', $row->actor_guard);
    }

    public function test_admin_panel_renders_recent_exports_for_super_admin(): void
    {
        $superRole = Role::firstOrCreate(
            ['slug' => 'super-admin', 'guard' => 'admin'],
            ['name' => 'Super Admin']
        );
        $admin = Admin::create([
            'name'     => 'Watcher Wendy',
            'email'    => 'wendy@admin.test',
            'password' => Hash::make('x'),
            'role_id'  => $superRole->id,
            'status'   => 'active',
        ]);

        // Pre-seed a couple of export rows so the panel has something
        // to render. Distinct IPs so the assertions below are
        // unambiguous.
        UserRoleAuditExport::create([
            'actor_user_id'  => null,
            'actor_admin_id' => $admin->id,
            'actor_guard'    => 'admin',
            'actor_name'     => 'Watcher Wendy',
            'actor_email'    => 'wendy@admin.test',
            'scope'          => UserRoleAuditExport::SCOPE_FULL_POOL,
            'target_user_id' => null,
            'row_count'      => 42,
            'ip'             => '198.51.100.7',
            'created_at'     => now()->subMinutes(5),
        ]);
        $someUser = $this->makeUser(['name' => 'Pulled Pat']);
        UserRoleAuditExport::create([
            'actor_user_id'  => null,
            'actor_admin_id' => $admin->id,
            'actor_guard'    => 'admin',
            'actor_name'     => 'Watcher Wendy',
            'actor_email'    => 'wendy@admin.test',
            'scope'          => UserRoleAuditExport::SCOPE_SINGLE_USER,
            'target_user_id' => $someUser->id,
            'row_count'      => 7,
            'ip'             => '198.51.100.99',
            'created_at'     => now()->subMinutes(2),
        ]);

        $resp = $this->actingAs($admin, 'admin')
            ->get(route('admin.users.role-audit-exports.index'));

        $resp->assertOk();
        $resp->assertSee('Role audit downloads');
        $resp->assertSee('198.51.100.7');
        $resp->assertSee('198.51.100.99');
        $resp->assertSee('Pulled Pat');
        $resp->assertSee('42');
        $resp->assertSee('Full user pool');
        $resp->assertSee('Single user');
    }

    public function test_admin_panel_is_super_admin_only(): void
    {
        // A "support" admin has no super-admin role and must be 403'd
        // off the audit-of-the-audit panel even though they may have
        // permissions to view users.
        $supportRole = Role::create([
            'name'  => 'Support ' . Str::random(4),
            'slug'  => 'support-' . Str::random(4),
            'guard' => 'admin',
        ]);
        $admin = Admin::create([
            'name'     => 'Read Only Riley',
            'email'    => 'riley-panel@admin.test',
            'password' => Hash::make('x'),
            'role_id'  => $supportRole->id,
            'status'   => 'active',
        ]);

        $resp = $this->actingAs($admin, 'admin')
            ->get(route('admin.users.role-audit-exports.index'));

        $this->assertSame(403, $resp->getStatusCode());
    }
}
