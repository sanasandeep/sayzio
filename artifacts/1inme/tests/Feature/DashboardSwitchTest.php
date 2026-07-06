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
 * Security-sensitive coverage for the admin<->user dashboard switch and
 * the admin-promotion (grant/revoke) flows:
 *
 *  - {@see \App\Modules\Common\Controllers\DashboardSwitchController}
 *    bridges the `web` and `admin` guards in one session for a person
 *    who has both a user account and a matching active Admin record.
 *  - {@see \App\Modules\Admin\Controllers\UserRoleController::grantAdminAccess}
 *    / `revokeAdminAccess` create / repoint / delete that Admin record.
 *
 * Both must never collide with an active impersonation session (where
 * the web guard belongs to the impersonated user, not the operator),
 * and the back-office impersonate buttons must stay hidden from
 * operators lacking `users.impersonate`.
 */
class DashboardSwitchTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create($attrs)->fresh();
    }

    /** A fresh admin-guard role that can be assigned to a promoted user. */
    private function makeAdminGuardRole(): Role
    {
        return Role::create([
            'name'  => 'Backoffice ' . Str::random(4),
            'slug'  => 'backoffice-' . Str::random(6),
            'guard' => 'admin',
        ]);
    }

    /**
     * Build a back-office Admin carrying the given permission slugs via a
     * fresh admin-guard role. Passing `$email` lets a test line the admin
     * up with a matching user account (for the switch / self-revoke paths).
     */
    private function makeAdmin(array $permSlugs = [], ?string $email = null): Admin
    {
        $role = Role::create([
            'name'  => 'Staff ' . Str::random(4),
            'slug'  => 'staff-' . Str::random(6),
            'guard' => 'admin',
        ]);

        foreach ($permSlugs as $slug) {
            $perm = Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'group' => explode('.', $slug)[0] ?? 'misc'],
            );
            $role->permissions()->syncWithoutDetaching([$perm->id]);
        }

        return Admin::create([
            'name'     => 'Admin ' . Str::random(4),
            'email'    => $email ?? ('a' . Str::random(8) . '@ex.com'),
            'password' => Hash::make('x'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    // ---------------------------------------------------------------
    // Dashboard switching
    // ---------------------------------------------------------------

    public function test_user_with_matching_active_admin_can_switch_to_admin_and_back(): void
    {
        $user  = $this->makeUser();
        $admin = $this->makeAdmin([], $user->email);

        // User dashboard -> admin dashboard.
        $this->actingAs($user)
            ->post('/user/switch-to-admin')
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success');

        $this->assertAuthenticatedAs($admin, 'admin');

        // Admin dashboard -> back to the user dashboard.
        $this->post('/admin/switch-to-user')
            ->assertRedirect(route('user.dashboard'))
            ->assertSessionHas('success');

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_switch_to_admin_is_blocked_without_a_matching_active_admin_record(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->post('/user/switch-to-admin')
            ->assertRedirect(route('user.dashboard'))
            ->assertSessionHas('error');

        $this->assertGuest('admin');
    }

    public function test_switch_to_admin_is_blocked_when_the_admin_record_is_inactive(): void
    {
        $user  = $this->makeUser();
        $admin = $this->makeAdmin([], $user->email);
        $admin->update(['status' => 'inactive']);

        $this->actingAs($user)
            ->post('/user/switch-to-admin')
            ->assertRedirect(route('user.dashboard'))
            ->assertSessionHas('error');

        $this->assertGuest('admin');
    }

    public function test_switch_to_admin_is_blocked_during_an_impersonation_session(): void
    {
        $user  = $this->makeUser();
        $this->makeAdmin([], $user->email);

        // The web guard belongs to the impersonated user, so the bridge
        // must refuse and never log the admin guard in.
        $this->actingAs($user)
            ->withSession(['impersonate_user_id' => $user->id])
            ->post('/user/switch-to-admin')
            ->assertRedirect(route('user.dashboard'));

        $this->assertGuest('admin');
    }

    public function test_switch_to_user_is_blocked_during_an_impersonation_session(): void
    {
        $user  = $this->makeUser();
        $admin = $this->makeAdmin([], $user->email);
        $other = $this->makeUser();

        // Admin guard is the operator; web guard is an impersonated user.
        // Switching back must not steal the web guard from that session.
        $this->actingAs($admin, 'admin')
            ->actingAs($other)
            ->withSession(['impersonate_user_id' => $other->id])
            ->post('/admin/switch-to-user')
            ->assertRedirect(route('user.dashboard'));

        // The impersonated user must remain on the web guard, untouched.
        $this->assertAuthenticatedAs($other, 'web');
    }

    // ---------------------------------------------------------------
    // Admin promotion: grant
    // ---------------------------------------------------------------

    public function test_grant_admin_access_creates_a_new_admin_record(): void
    {
        $operator = $this->makeAdmin(['staff.create']);
        $target   = $this->makeUser();
        $role     = $this->makeAdminGuardRole();

        $this->actingAs($operator, 'admin')
            ->post('/admin/users/' . $target->id . '/admin-access', [
                'role_id' => $role->id,
            ])
            ->assertRedirect(route('admin.users.roles.edit', $target))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('admins', [
            'email'   => $target->email,
            'role_id' => $role->id,
            'status'  => 'active',
        ]);
    }

    public function test_grant_admin_access_from_staff_page_stays_on_staff(): void
    {
        $operator = $this->makeAdmin(['staff.create']);
        $target   = $this->makeUser();
        $role     = $this->makeAdminGuardRole();

        // The inline "Promote existing user" control on the Staff page
        // submits redirect_to=staff so the operator is kept on Staff
        // instead of being bounced to the user's role page.
        $this->actingAs($operator, 'admin')
            ->post('/admin/users/' . $target->id . '/admin-access', [
                'role_id'     => $role->id,
                'redirect_to' => 'staff',
            ])
            ->assertRedirect(route('admin.staff.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('admins', [
            'email'   => $target->email,
            'role_id' => $role->id,
            'status'  => 'active',
        ]);
    }

    public function test_staff_user_search_requires_staff_create_permission(): void
    {
        $operator = $this->makeAdmin(); // no permissions

        $this->actingAs($operator, 'admin')
            ->getJson('/admin/staff/search-users?q=test')
            ->assertForbidden();
    }

    public function test_staff_user_search_matches_name_and_email(): void
    {
        $operator = $this->makeAdmin(['staff.create']);
        $target   = $this->makeUser(['name' => 'Searchable Person', 'email' => 'searchable@example.test']);

        $this->actingAs($operator, 'admin')
            ->getJson('/admin/staff/search-users?q=searchable')
            ->assertOk()
            ->assertJsonFragment([
                'id'    => $target->id,
                'email' => $target->email,
            ]);
    }

    public function test_grant_admin_access_repoints_an_existing_admin_record(): void
    {
        $target   = $this->makeUser();
        $operator = $this->makeAdmin(['staff.create']);

        // Pre-existing (inactive, old role) admin record on the same email.
        $oldRole = $this->makeAdminGuardRole();
        $existing = Admin::create([
            'name'     => $target->name,
            'email'    => $target->email,
            'password' => Hash::make('x'),
            'role_id'  => $oldRole->id,
            'status'   => 'inactive',
        ]);

        $newRole = $this->makeAdminGuardRole();

        $this->actingAs($operator, 'admin')
            ->post('/admin/users/' . $target->id . '/admin-access', [
                'role_id' => $newRole->id,
            ])
            ->assertRedirect(route('admin.users.roles.edit', $target))
            ->assertSessionHas('success');

        $existing->refresh();
        $this->assertEquals($newRole->id, $existing->role_id);
        $this->assertEquals('active', $existing->status);

        // No duplicate record was created for the same email.
        $this->assertSame(1, Admin::whereRaw('lower(email) = ?', [strtolower($target->email)])->count());
    }

    public function test_grant_admin_access_rejects_a_web_guard_role(): void
    {
        $operator = $this->makeAdmin(['staff.create']);
        $target   = $this->makeUser();
        $webRole  = Role::create(['name' => 'Web Role', 'slug' => 'web-role-' . Str::random(4), 'guard' => 'web']);

        $this->actingAs($operator, 'admin')
            ->post('/admin/users/' . $target->id . '/admin-access', [
                'role_id' => $webRole->id,
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('admins', ['email' => $target->email]);
    }

    public function test_grant_admin_access_is_forbidden_without_staff_create(): void
    {
        $operator = $this->makeAdmin(); // no permissions
        $target   = $this->makeUser();
        $role     = $this->makeAdminGuardRole();

        $this->actingAs($operator, 'admin')
            ->post('/admin/users/' . $target->id . '/admin-access', [
                'role_id' => $role->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('admins', ['email' => $target->email]);
    }

    // ---------------------------------------------------------------
    // Admin promotion: revoke
    // ---------------------------------------------------------------

    public function test_revoke_admin_access_deletes_the_admin_record(): void
    {
        $operator = $this->makeAdmin(['staff.delete']);
        $target   = $this->makeUser();
        $targetAdmin = $this->makeAdmin([], $target->email);

        $this->actingAs($operator, 'admin')
            ->delete('/admin/users/' . $target->id . '/admin-access')
            ->assertRedirect(route('admin.users.roles.edit', $target))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('admins', ['id' => $targetAdmin->id]);
    }

    public function test_revoke_admin_access_blocks_self_revoke(): void
    {
        // Operator's own email matches a user account, so the target
        // user's admin record IS the operator — revoking it would lock
        // them out mid-request and must be refused.
        $operator = $this->makeAdmin(['staff.delete']);
        $self     = $this->makeUser(['email' => $operator->email]);

        $this->actingAs($operator, 'admin')
            ->delete('/admin/users/' . $self->id . '/admin-access')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('admins', ['id' => $operator->id]);
    }

    public function test_revoke_admin_access_is_forbidden_without_staff_delete(): void
    {
        $operator    = $this->makeAdmin(); // no permissions
        $target      = $this->makeUser();
        $targetAdmin = $this->makeAdmin([], $target->email);

        $this->actingAs($operator, 'admin')
            ->delete('/admin/users/' . $target->id . '/admin-access')
            ->assertForbidden();

        $this->assertDatabaseHas('admins', ['id' => $targetAdmin->id]);
    }

    // ---------------------------------------------------------------
    // Impersonate button visibility
    // ---------------------------------------------------------------

    public function test_impersonate_buttons_are_hidden_without_users_impersonate(): void
    {
        $operator = $this->makeAdmin(['users.view']);
        $target   = $this->makeUser();

        $this->actingAs($operator, 'admin')
            ->get('/admin/users')
            ->assertOk()
            ->assertDontSee(route('admin.users.impersonate', $target));

        $this->actingAs($operator, 'admin')
            ->get('/admin/users/' . $target->id)
            ->assertOk()
            ->assertDontSee(route('admin.users.impersonate', $target));
    }

    public function test_impersonate_buttons_are_visible_with_users_impersonate(): void
    {
        $operator = $this->makeAdmin(['users.view', 'users.impersonate']);
        $target   = $this->makeUser();

        $this->actingAs($operator, 'admin')
            ->get('/admin/users')
            ->assertOk()
            ->assertSee(route('admin.users.impersonate', $target));

        $this->actingAs($operator, 'admin')
            ->get('/admin/users/' . $target->id)
            ->assertOk()
            ->assertSee(route('admin.users.impersonate', $target));
    }
}
