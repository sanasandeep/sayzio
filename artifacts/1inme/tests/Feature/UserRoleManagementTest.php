<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the user-side role editor (RoleManagementController) — the
 * companion to UserAccessController. The editor lets operators with
 * `user.roles.manage` create/rename/delete user-pool roles and tweak
 * each role's permission checklist without touching seed migrations.
 */
class UserRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create($attrs)->fresh();
    }

    private function adminRoleId(): int
    {
        $id = DB::table('roles')->where('slug', 'user-admin')->where('guard', 'web')->value('id');
        $this->assertNotNull($id, 'user-admin role must be seeded');
        return (int) $id;
    }

    private function permId(string $slug): int
    {
        $id = DB::table('permissions')->where('slug', $slug)->value('id');
        $this->assertNotNull($id, "permission {$slug} must be seeded");
        return (int) $id;
    }

    private function makeAdmin(): User
    {
        $user = $this->makeUser();
        $user->roles()->syncWithoutDetaching([$this->adminRoleId()]);
        $user->flushPermissionCache();
        return $user->fresh();
    }

    public function test_users_without_permission_cannot_open_role_editor(): void
    {
        $u = $this->makeUser();
        $this->actingAs($u)
             ->get('/user/access/roles')
             ->assertStatus(403);
    }

    public function test_admin_can_list_roles(): void
    {
        $admin = $this->makeAdmin();
        $resp = $this->actingAs($admin)->get('/user/access/roles');
        $resp->assertOk();
        $resp->assertSee('User Admin');
    }

    public function test_admin_can_create_a_custom_role_with_one_permission(): void
    {
        $admin = $this->makeAdmin();
        $verifyPerm = $this->permId('user.verifications.review');

        $resp = $this->actingAs($admin)->post('/user/access/roles', [
            'name'           => 'Verifier',
            'slug'           => '',
            'description'    => 'Reviews verification requests only',
            'permission_ids' => [$verifyPerm],
        ]);
        $resp->assertRedirect();

        $this->assertDatabaseHas('roles', [
            'name'  => 'Verifier',
            'slug'  => 'verifier',
            'guard' => 'web',
        ]);

        $role = Role::where('slug', 'verifier')->first();
        $this->assertNotNull($role);
        $this->assertEquals([$verifyPerm], $role->permissions()->pluck('permissions.id')->all());
    }

    public function test_admin_can_edit_a_role_and_change_its_permission_checklist(): void
    {
        $admin = $this->makeAdmin();
        $role = Role::create([
            'name' => 'Verifier', 'slug' => 'verifier', 'guard' => 'web',
        ]);
        $role->permissions()->sync([$this->permId('user.verifications.review')]);

        $resp = $this->actingAs($admin)->put('/user/access/roles/' . $role->id, [
            'name'           => 'Verifier (renamed)',
            'slug'           => 'verifier',
            'description'    => 'updated',
            'permission_ids' => [$this->permId('user.invoices.view_any')],
        ]);
        $resp->assertRedirect();

        $role->refresh();
        $this->assertEquals('Verifier (renamed)', $role->name);
        $this->assertEquals(
            [$this->permId('user.invoices.view_any')],
            $role->permissions()->pluck('permissions.id')->all(),
        );
    }

    public function test_back_office_permissions_are_silently_dropped_on_save(): void
    {
        $admin = $this->makeAdmin();

        // Manually insert an admin-group permission to simulate a
        // back-office permission existing in the same table.
        $bogusId = DB::table('permissions')->insertGetId([
            'name' => 'Back-office only', 'slug' => 'admin.back_office.only',
            'group' => 'admin', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $resp = $this->actingAs($admin)->post('/user/access/roles', [
            'name'           => 'Mixed',
            'slug'           => 'mixed',
            'permission_ids' => [$bogusId, $this->permId('user.verifications.review')],
        ]);
        $resp->assertRedirect();

        $role = Role::where('slug', 'mixed')->first();
        $this->assertEquals(
            [$this->permId('user.verifications.review')],
            $role->permissions()->pluck('permissions.id')->all(),
            'Permissions outside user-app group must be filtered out.',
        );
    }

    public function test_admin_cannot_strip_their_own_role_management_when_only_role(): void
    {
        $admin = $this->makeAdmin();
        $userAdminRoleId = $this->adminRoleId();

        // Try to save user-admin without `user.roles.manage` — that's
        // the actor's only path to this page, so the save must fail.
        $resp = $this->actingAs($admin)->put('/user/access/roles/' . $userAdminRoleId, [
            'name'           => 'User Admin',
            'slug'           => 'user-admin',
            'permission_ids' => [$this->permId('user.verifications.review')],
        ]);
        $resp->assertSessionHasErrors('permission_ids');

        $role = Role::find($userAdminRoleId);
        $this->assertContains(
            $this->permId('user.roles.manage'),
            $role->permissions()->pluck('permissions.id')->all(),
            'user.roles.manage should remain attached after a refused self-lockout save.',
        );
    }

    public function test_admin_can_strip_manage_when_held_via_another_role(): void
    {
        $admin = $this->makeAdmin();

        // Give the actor a second role that also grants user.roles.manage.
        $other = Role::create(['name' => 'Other Mgr', 'slug' => 'other-mgr', 'guard' => 'web']);
        $other->permissions()->sync([$this->permId('user.roles.manage')]);
        $admin->roles()->syncWithoutDetaching([$other->id]);
        $admin->flushPermissionCache();

        $userAdminRoleId = $this->adminRoleId();

        $resp = $this->actingAs($admin)->put('/user/access/roles/' . $userAdminRoleId, [
            'name'           => 'User Admin',
            'slug'           => 'user-admin',
            'permission_ids' => [$this->permId('user.verifications.review')],
        ]);
        $resp->assertSessionDoesntHaveErrors('permission_ids');

        $role = Role::find($userAdminRoleId);
        $this->assertNotContains(
            $this->permId('user.roles.manage'),
            $role->permissions()->pluck('permissions.id')->all(),
        );
    }

    public function test_cannot_delete_a_role_that_still_has_users(): void
    {
        $admin = $this->makeAdmin();
        $resp = $this->actingAs($admin)
            ->delete('/user/access/roles/' . $this->adminRoleId());
        $resp->assertSessionHasErrors('role');
        $this->assertDatabaseHas('roles', ['id' => $this->adminRoleId()]);
    }

    public function test_can_delete_an_unattached_role(): void
    {
        $admin = $this->makeAdmin();
        $role = Role::create(['name' => 'Throwaway', 'slug' => 'throwaway', 'guard' => 'web']);

        $resp = $this->actingAs($admin)->delete('/user/access/roles/' . $role->id);
        $resp->assertRedirect();
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_admin_guard_roles_are_invisible_to_user_side_editor(): void
    {
        $admin = $this->makeAdmin();
        $adminGuardRole = Role::create([
            'name' => 'Backoffice', 'slug' => 'backoffice', 'guard' => 'admin',
        ]);

        $this->actingAs($admin)
             ->get('/user/access/roles/' . $adminGuardRole->id . '/edit')
             ->assertNotFound();

        $this->actingAs($admin)
             ->put('/user/access/roles/' . $adminGuardRole->id, [
                 'name' => 'x', 'slug' => 'x', 'permission_ids' => [],
             ])
             ->assertNotFound();
    }

    public function test_slug_must_be_unique_within_web_guard(): void
    {
        $admin = $this->makeAdmin();
        $resp = $this->actingAs($admin)->post('/user/access/roles', [
            'name'           => 'Dup',
            'slug'           => 'user-admin',
            'permission_ids' => [],
        ]);
        $resp->assertSessionHasErrors('slug');
    }
}
