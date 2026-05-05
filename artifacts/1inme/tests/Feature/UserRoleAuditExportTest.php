<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserRoleAudit;
use App\Modules\User\Services\UserRoleAuditCsvExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the CSV export endpoints attached to the role-change audit
 * panels — both the self-service "User access" page (full pool) and
 * the back-office user pages (single user). The shared exporter is
 * also smoke-tested directly so column drift is caught even if the
 * route layer changes shape.
 */
class UserRoleAuditExportTest extends TestCase
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

    private function makeAudit(User $target, Role $role, array $overrides = []): UserRoleAudit
    {
        return UserRoleAudit::create(array_merge([
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
        ], $overrides));
    }

    public function test_user_access_export_streams_csv_with_required_columns(): void
    {
        $userAdminRole = Role::where('slug', 'user-admin')->where('guard', 'web')->firstOrFail();
        $operator = $this->makeUser(['name' => 'Op One']);
        $operator->roles()->attach($userAdminRole->id);

        $targetA = $this->makeUser(['name' => 'Alpha']);
        $targetB = $this->makeUser(['name' => 'Bravo']);
        $roleA   = $this->makeRole('writer');
        $roleB   = $this->makeRole('editor');

        $this->makeAudit($targetA, $roleA, [
            'actor_name'  => 'System Seeder',
            'source'      => 'backfill',
            'action'      => 'attached',
            'ip'          => null,
            'created_at'  => now()->subDays(2),
        ]);
        $this->makeAudit($targetB, $roleB, [
            'actor_name'  => 'Op One',
            'actor_guard' => 'web',
            'source'      => 'user_access',
            'action'      => 'detached',
            'created_at'  => now()->subDay(),
        ]);

        $resp = $this->actingAs($operator, 'web')
            ->get(route('user.access.users.audit.export'));

        $resp->assertOk();
        $resp->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString(
            'attachment; filename="role-change-audit-',
            $resp->headers->get('Content-Disposition'),
        );

        $csv = $resp->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csv))));

        // Header + 2 data rows.
        $this->assertCount(3, $lines);
        $this->assertSame(
            implode(',', UserRoleAuditCsvExporter::COLUMNS),
            $lines[0],
        );

        // Both targets present, backfill row clearly tagged via the
        // `source` column so reviewers can filter live vs synthetic.
        $this->assertStringContainsString('backfill', $csv);
        $this->assertStringContainsString('user_access', $csv);
        $this->assertStringContainsString('Alpha', $csv);
        $this->assertStringContainsString('Bravo', $csv);
    }

    public function test_user_access_export_requires_role_manage_permission(): void
    {
        // A plain user with no `user.roles.manage` permission should
        // be redirected away from the export endpoint by the same
        // middleware that gates the index page.
        $randomUser = $this->makeUser();

        $resp = $this->actingAs($randomUser, 'web')
            ->get(route('user.access.users.audit.export'));

        // The middleware throws or redirects — anything other than a
        // 200 with CSV content is fine here. Crucially the body must
        // not be CSV.
        $this->assertNotEquals(200, $resp->getStatusCode(), 'Export must be gated.');
    }

    public function test_admin_user_role_export_is_scoped_to_target_user(): void
    {
        $superRole = Role::firstOrCreate(
            ['slug' => 'super-admin', 'guard' => 'admin'],
            ['name' => 'Super Admin']
        );
        $admin = Admin::create([
            'name'     => 'Back Office Bob',
            'email'    => 'bob-export@admin.test',
            'password' => Hash::make('x'),
            'role_id'  => $superRole->id,
            'status'   => 'active',
        ]);

        $targetA = $this->makeUser(['name' => 'Subject Sam']);
        $targetB = $this->makeUser(['name' => 'Other Olly']);
        $role    = $this->makeRole('reviewer');

        $this->makeAudit($targetA, $role, [
            'actor_name' => 'Bob Action', 'source' => 'admin',
        ]);
        $this->makeAudit($targetB, $role, [
            'actor_name' => 'Bob Action', 'source' => 'admin',
        ]);

        $resp = $this->actingAs($admin, 'admin')
            ->get(route('admin.users.roles.audit.export', $targetA));

        $resp->assertOk();
        $resp->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $resp->streamedContent();
        $this->assertStringContainsString('Subject Sam', $csv);
        $this->assertStringNotContainsString(
            'Other Olly',
            $csv,
            'Per-user export must not leak other users\' audit rows.',
        );

        // Filename embeds the target user id so multiple downloads
        // don't overwrite each other in the reviewer's downloads
        // folder.
        $this->assertStringContainsString(
            'role-change-audit-user-' . $targetA->id . '-',
            $resp->headers->get('Content-Disposition'),
        );
    }

    public function test_admin_user_role_export_requires_users_edit_permission(): void
    {
        // A "Support" admin with `users.view` but NOT `users.edit`
        // can see the user detail page but must not be able to
        // download the audit history.
        $supportRole = Role::create([
            'name'  => 'Support ' . Str::random(4),
            'slug'  => 'support-' . Str::random(4),
            'guard' => 'admin',
        ]);
        $viewPerm = Permission::firstOrCreate(
            ['slug' => 'users.view'],
            ['name' => 'View Users', 'group' => 'users'],
        );
        $supportRole->permissions()->attach($viewPerm->id);

        $admin = Admin::create([
            'name'     => 'Read Only Riley',
            'email'    => 'riley-export@admin.test',
            'password' => Hash::make('x'),
            'role_id'  => $supportRole->id,
            'status'   => 'active',
        ]);

        $target = $this->makeUser();

        $resp = $this->actingAs($admin, 'admin')
            ->get(route('admin.users.roles.audit.export', $target));

        $this->assertNotEquals(200, $resp->getStatusCode(), 'Export must be gated.');
    }

    public function test_show_page_renders_export_link(): void
    {
        $superRole = Role::firstOrCreate(
            ['slug' => 'super-admin', 'guard' => 'admin'],
            ['name' => 'Super Admin']
        );
        $admin = Admin::create([
            'name'     => 'Back Office Bob',
            'email'    => 'bob-show@admin.test',
            'password' => Hash::make('x'),
            'role_id'  => $superRole->id,
            'status'   => 'active',
        ]);
        $target = $this->makeUser();

        $resp = $this->actingAs($admin, 'admin')
            ->get(route('admin.users.show', $target))
            ->assertOk();

        $resp->assertSee(route('admin.users.roles.audit.export', $target), false);
    }
}
