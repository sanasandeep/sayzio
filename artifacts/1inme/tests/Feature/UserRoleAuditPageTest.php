<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserRoleAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the dedicated, filterable role-change audit page (and its
 * CSV export sibling) on both the user-facing and the back-office
 * surfaces. The two pages share the model-level `Filtered` scope and
 * `streamCsv` helper, so the assertions exercise the filter parser
 * and the CSV output through both controllers.
 */
class UserRoleAuditPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::create(array_merge([
            'name'     => 'U ' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ], $attrs));
    }

    private function userAdminRole(): Role
    {
        $role = Role::where('slug', 'user-admin')->where('guard', 'web')->first();
        $this->assertNotNull($role, 'user-admin role must be seeded');
        return $role;
    }

    /**
     * @param string|array<int, string> $permSlugs Single slug or list of slugs to attach.
     */
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

    private function seedAudit(array $attrs): UserRoleAudit
    {
        return UserRoleAudit::create(array_merge([
            'actor_user_id'  => null,
            'actor_admin_id' => null,
            'actor_guard'    => null,
            'actor_name'     => 'Someone',
            'actor_email'    => null,
            'target_user_id' => 1,
            'role_id'        => null,
            'role_slug'      => 'a-role',
            'role_name'      => 'A Role',
            'action'         => UserRoleAudit::ACTION_ATTACHED,
            'source'         => UserRoleAudit::SOURCE_USER_ACCESS,
            'ip'             => '203.0.113.1',
            'created_at'     => now(),
        ], $attrs));
    }

    // ---------------------------------------------------------------
    // Gating
    // ---------------------------------------------------------------

    public function test_user_audit_page_requires_user_roles_manage(): void
    {
        $bystander = $this->makeUser();

        $this->actingAs($bystander)
            ->get('/user/access/audit')
            ->assertForbidden();
    }

    public function test_user_audit_export_requires_user_roles_manage(): void
    {
        $bystander = $this->makeUser();

        $this->actingAs($bystander)
            ->get('/user/access/audit/export')
            ->assertForbidden();
    }

    public function test_admin_audit_page_requires_users_edit(): void
    {
        // users.view is enough to see the user-detail page but NOT
        // enough to see the role audit log — that requires users.edit
        // because the same gate guards mutating roles.
        $viewerOnly = $this->makeAdminWithPermission('users.view');

        $this->actingAs($viewerOnly, 'admin')
            ->get('/admin/users/role-audits')
            ->assertForbidden();
    }

    public function test_admin_audit_page_loads_with_users_edit(): void
    {
        $admin = $this->makeAdminWithPermission('users.edit');

        $this->actingAs($admin, 'admin')
            ->get('/admin/users/role-audits')
            ->assertOk()
            ->assertSee('Role change audit log');
    }

    // ---------------------------------------------------------------
    // Filters
    // ---------------------------------------------------------------

    public function test_filters_narrow_visible_rows_on_user_audit_page(): void
    {
        $operator = $this->makeUser(['name' => 'Operator Owen']);
        $operator->roles()->attach($this->userAdminRole()->id);

        $alice = $this->makeUser(['name' => 'Alice Target']);
        $bob   = $this->makeUser(['name' => 'Bob Target']);

        $this->seedAudit([
            'actor_name'     => 'Operator Owen',
            'target_user_id' => $alice->id,
            'role_slug'      => 'editor',
            'role_name'      => 'Editor',
            'action'         => UserRoleAudit::ACTION_ATTACHED,
            'source'         => UserRoleAudit::SOURCE_USER_ACCESS,
            'created_at'     => now()->subDays(2),
        ]);
        $this->seedAudit([
            'actor_name'     => 'Other Olga',
            'target_user_id' => $bob->id,
            'role_slug'      => 'viewer',
            'role_name'      => 'Viewer',
            'action'         => UserRoleAudit::ACTION_DETACHED,
            'source'         => UserRoleAudit::SOURCE_ADMIN,
            'created_at'     => now()->subDays(2),
        ]);

        // Filter by role slug.
        $resp = $this->actingAs($operator)
            ->get('/user/access/audit?role=editor')
            ->assertOk();
        $resp->assertSee('Alice Target');
        $resp->assertDontSee('Bob Target');

        // Filter by action.
        $resp = $this->actingAs($operator)
            ->get('/user/access/audit?action=detached')
            ->assertOk();
        $resp->assertSee('Bob Target');
        $resp->assertDontSee('Alice Target');

        // Filter by actor (free-text on the snapshotted name).
        $resp = $this->actingAs($operator)
            ->get('/user/access/audit?actor=Olga')
            ->assertOk();
        $resp->assertSee('Bob Target');
        $resp->assertDontSee('Alice Target');

        // Filter by target user id.
        $resp = $this->actingAs($operator)
            ->get('/user/access/audit?target=' . $alice->id)
            ->assertOk();
        $resp->assertSee('Alice Target');
        $resp->assertDontSee('Bob Target');

        // Filter by source.
        $resp = $this->actingAs($operator)
            ->get('/user/access/audit?source=admin')
            ->assertOk();
        $resp->assertSee('Bob Target');
        $resp->assertDontSee('Alice Target');
    }

    public function test_date_range_excludes_rows_outside_window(): void
    {
        $operator = $this->makeUser();
        $operator->roles()->attach($this->userAdminRole()->id);

        $oldTarget = $this->makeUser(['name' => 'Old Target']);
        $newTarget = $this->makeUser(['name' => 'Recent Target']);

        $this->seedAudit([
            'target_user_id' => $oldTarget->id,
            'created_at'     => now()->subDays(60),
        ]);
        $this->seedAudit([
            'target_user_id' => $newTarget->id,
            'created_at'     => now()->subDays(2),
        ]);

        $from = now()->subDays(10)->toDateString();
        $resp = $this->actingAs($operator)
            ->get('/user/access/audit?from=' . $from)
            ->assertOk();

        $resp->assertSee('Recent Target');
        $resp->assertDontSee('Old Target');
    }

    // ---------------------------------------------------------------
    // CSV export
    // ---------------------------------------------------------------

    public function test_user_audit_export_streams_csv_with_filtered_rows(): void
    {
        $operator = $this->makeUser();
        $operator->roles()->attach($this->userAdminRole()->id);

        $alice = $this->makeUser(['name' => 'Alice For CSV']);
        $bob   = $this->makeUser(['name' => 'Bob For CSV']);

        $this->seedAudit([
            'actor_name'     => 'CSV Owen',
            'target_user_id' => $alice->id,
            'role_slug'      => 'csv-editor',
            'role_name'      => 'CSV Editor',
            'action'         => UserRoleAudit::ACTION_ATTACHED,
            'ip'             => '198.51.100.10',
        ]);
        $this->seedAudit([
            'actor_name'     => 'Unrelated',
            'target_user_id' => $bob->id,
            'role_slug'      => 'csv-viewer',
        ]);

        $resp = $this->actingAs($operator)
            ->get('/user/access/audit/export?role=csv-editor');
        $resp->assertOk();
        $resp->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment', $resp->headers->get('Content-Disposition') ?? '');

        $body = $resp->streamedContent();

        // Header row + the one matching audit row.
        $this->assertStringContainsString('Timestamp (UTC)', $body);
        $this->assertStringContainsString('csv-editor', $body);
        $this->assertStringContainsString('CSV Owen', $body);
        $this->assertStringContainsString('Alice For CSV', $body);
        $this->assertStringContainsString('198.51.100.10', $body);

        // The unrelated row must be filtered out.
        $this->assertStringNotContainsString('csv-viewer', $body);
        $this->assertStringNotContainsString('Bob For CSV', $body);
    }

    public function test_admin_audit_export_streams_csv(): void
    {
        $admin = $this->makeAdminWithPermission('users.edit');
        $alice = $this->makeUser(['name' => 'Admin CSV Alice']);

        $this->seedAudit([
            'target_user_id' => $alice->id,
            'role_slug'      => 'admin-csv-role',
            'role_name'      => 'Admin CSV Role',
        ]);

        $resp = $this->actingAs($admin, 'admin')
            ->get('/admin/users/role-audits/export?target=' . $alice->id);
        $resp->assertOk();
        $resp->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $body = $resp->streamedContent();
        $this->assertStringContainsString('admin-csv-role', $body);
        $this->assertStringContainsString('Admin CSV Alice', $body);
    }

    // ---------------------------------------------------------------
    // Cross-page navigation
    // ---------------------------------------------------------------

    public function test_user_access_page_links_to_full_audit_log(): void
    {
        $operator = $this->makeUser();
        $operator->roles()->attach($this->userAdminRole()->id);

        $this->actingAs($operator)
            ->get('/user/access/users')
            ->assertOk()
            ->assertSee(route('user.access.audit.index'), false);
    }

    public function test_admin_user_detail_page_links_to_full_audit_log(): void
    {
        // The user-detail show route gates on `users.view`, but the
        // role-audit panel (and its full-log link) only renders for
        // operators that also hold `users.edit`.
        $admin  = $this->makeAdminWithPermission(['users.view', 'users.edit']);
        $target = $this->makeUser();

        $this->actingAs($admin, 'admin')
            ->get('/admin/users/' . $target->id)
            ->assertOk()
            ->assertSee(
                route('admin.users.role-audits.index', ['target' => $target->id]),
                false
            );
    }
}
