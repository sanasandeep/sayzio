<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature coverage for the two role-management surfaces seeded by the
 * `user.roles.manage` permission system:
 *
 *  - `/user/access/users`           (self-service operator page)
 *  - `/admin/users/{user}/roles`    (back-office page)
 *
 * Both pages are the only UI for granting the `user-admin` role, so a
 * silent regression here would lock real operators out of the app. The
 * tests cover gating (who can see the page, who can submit), the happy
 * path of attaching/detaching `user-admin`, and the guard-isolation
 * rule that admin-guard roles must never be attachable to a user
 * account from either surface.
 */
class UserAccessRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create($attrs);
    }

    /** Resolve the seeded user-admin role (web guard, has user.roles.manage). */
    private function userAdminRole(): Role
    {
        $role = Role::where('slug', 'user-admin')->where('guard', 'web')->first();
        $this->assertNotNull($role, 'user-admin role must be seeded by the user_roles migration');
        return $role;
    }

    /** Build a fresh admin-guard role used to verify guard isolation. */
    private function makeAdminGuardRole(): Role
    {
        return Role::create([
            'name'  => 'Test Admin Guard ' . Str::random(4),
            'slug'  => 'test-admin-guard-' . Str::random(6),
            'guard' => 'admin',
        ]);
    }

    private function attachRole(User $user, Role $role): void
    {
        $user->roles()->syncWithoutDetaching([$role->id]);
        $user->flushPermissionCache();
    }

    /** Make a back-office Admin with the given permission slug. */
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

    private function makeAdminWithoutAnyPermission(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'staff-empty'],
            ['name' => 'Staff (no perms)', 'guard' => 'admin']
        );
        return Admin::create([
            'name'     => 'NoPerm Admin',
            'email'    => 'np' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    // ---------------------------------------------------------------
    // /user/access/users — self-service operator page
    // ---------------------------------------------------------------

    public function test_user_without_roles_manage_is_forbidden_from_access_index(): void
    {
        $bystander = $this->makeUser();

        $this->actingAs($bystander)
            ->get('/user/access/users')
            ->assertForbidden();
    }

    public function test_user_without_roles_manage_is_forbidden_from_access_update(): void
    {
        $bystander = $this->makeUser();
        $target    = $this->makeUser();
        $userAdmin = $this->userAdminRole();

        $this->actingAs($bystander)
            ->post('/user/access/users/' . $target->id . '/roles', [
                'role_ids' => [$userAdmin->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $target->id,
            'role_id' => $userAdmin->id,
        ]);
    }

    public function test_operator_can_attach_and_detach_user_admin_via_access_page(): void
    {
        $operator  = $this->makeUser();
        $target    = $this->makeUser();
        $userAdmin = $this->userAdminRole();

        // Bootstrap: give the operator the user-admin role so they hold
        // user.roles.manage themselves.
        $this->attachRole($operator, $userAdmin);

        // Attach user-admin to the target.
        $this->actingAs($operator)
            ->post('/user/access/users/' . $target->id . '/roles', [
                'role_ids' => [$userAdmin->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $target->id,
            'role_id' => $userAdmin->id,
        ]);

        // Refresh — the index page must now render the freshly-promoted
        // user with their role visible.
        $resp = $this->actingAs($operator)->get('/user/access/users');
        $resp->assertOk()
             ->assertSee($target->email);

        // Detach — submitting an empty role_ids set must clear the role.
        $this->actingAs($operator)
            ->post('/user/access/users/' . $target->id . '/roles', [])
            ->assertRedirect();

        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $target->id,
            'role_id' => $userAdmin->id,
        ]);
    }

    public function test_access_update_ignores_admin_guard_role_ids(): void
    {
        $operator   = $this->makeUser();
        $target     = $this->makeUser();
        $userAdmin  = $this->userAdminRole();
        $adminGuard = $this->makeAdminGuardRole();

        $this->attachRole($operator, $userAdmin);

        // Submit BOTH a legitimate web-guard role and an admin-guard
        // role. Only the web-guard one should land on the user.
        $this->actingAs($operator)
            ->post('/user/access/users/' . $target->id . '/roles', [
                'role_ids' => [$userAdmin->id, $adminGuard->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $target->id,
            'role_id' => $userAdmin->id,
        ]);
        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $target->id,
            'role_id' => $adminGuard->id,
        ]);
    }

    public function test_operator_cannot_remove_their_own_roles_manage_access(): void
    {
        $operator  = $this->makeUser();
        $userAdmin = $this->userAdminRole();
        $this->attachRole($operator, $userAdmin);

        // Submitting an empty role set against their own account must
        // be rejected so they don't lock themselves out of the page.
        $this->actingAs($operator)
            ->from('/user/access/users')
            ->post('/user/access/users/' . $operator->id . '/roles', [])
            ->assertRedirect('/user/access/users')
            ->assertSessionHasErrors('role_ids');

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $operator->id,
            'role_id' => $userAdmin->id,
        ]);
    }

    // ---------------------------------------------------------------
    // /admin/users/{user}/roles — back-office page
    // ---------------------------------------------------------------

    public function test_admin_without_users_edit_is_forbidden_from_admin_roles_edit(): void
    {
        $target  = $this->makeUser();
        $admin   = $this->makeAdminWithoutAnyPermission();

        $this->actingAs($admin, 'admin')
            ->get('/admin/users/' . $target->id . '/roles')
            ->assertForbidden();
    }

    public function test_admin_without_users_edit_is_forbidden_from_admin_roles_update(): void
    {
        $target    = $this->makeUser();
        $admin     = $this->makeAdminWithoutAnyPermission();
        $userAdmin = $this->userAdminRole();

        $this->actingAs($admin, 'admin')
            ->put('/admin/users/' . $target->id . '/roles', [
                'role_ids' => [$userAdmin->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $target->id,
            'role_id' => $userAdmin->id,
        ]);
    }

    public function test_admin_with_users_edit_can_attach_and_detach_user_admin(): void
    {
        $target    = $this->makeUser();
        $admin     = $this->makeAdminWithPermission('users.edit');
        $userAdmin = $this->userAdminRole();

        // GET the edit page renders.
        $this->actingAs($admin, 'admin')
            ->get('/admin/users/' . $target->id . '/roles')
            ->assertOk()
            ->assertSee($target->email);

        // Attach.
        $this->actingAs($admin, 'admin')
            ->put('/admin/users/' . $target->id . '/roles', [
                'role_ids' => [$userAdmin->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $target->id,
            'role_id' => $userAdmin->id,
        ]);

        // Refresh of the edit page must show the role as assigned (the
        // checkbox state is driven by the `assigned` array, so the role
        // row is rendered with the assigned slug visible).
        $this->actingAs($admin, 'admin')
            ->get('/admin/users/' . $target->id . '/roles')
            ->assertOk()
            ->assertSee('user-admin');

        // Detach via empty submission.
        $this->actingAs($admin, 'admin')
            ->put('/admin/users/' . $target->id . '/roles', [])
            ->assertRedirect();

        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $target->id,
            'role_id' => $userAdmin->id,
        ]);
    }

    public function test_admin_roles_update_ignores_admin_guard_role_ids(): void
    {
        $target     = $this->makeUser();
        $admin      = $this->makeAdminWithPermission('users.edit');
        $userAdmin  = $this->userAdminRole();
        $adminGuard = $this->makeAdminGuardRole();

        // Submit an admin-guard role alongside the legitimate one — the
        // controller must drop the admin-guard id so it never leaks
        // onto a user account.
        $this->actingAs($admin, 'admin')
            ->put('/admin/users/' . $target->id . '/roles', [
                'role_ids' => [$userAdmin->id, $adminGuard->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $target->id,
            'role_id' => $userAdmin->id,
        ]);
        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $target->id,
            'role_id' => $adminGuard->id,
        ]);
    }
}
