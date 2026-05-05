<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserRoleAudit;
use App\Modules\User\Services\UserRoleAuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the audit ledger behind the "User access" page and the
 * back-office user-roles editor. The two surfaces share one logger
 * service, so we exercise the diff logic plus both controller
 * endpoints to make sure neither path silently skips a write.
 */
class UserRoleAuditTest extends TestCase
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

    public function test_logger_writes_one_row_per_attached_and_detached_role(): void
    {
        $target = $this->makeUser();
        $roleA  = $this->makeRole('alpha');
        $roleB  = $this->makeRole('beta');
        $roleC  = $this->makeRole('gamma');

        // Pretend target started with A & B and now has B & C — so A
        // is detached and C is attached. B is unchanged.
        app(UserRoleAuditLogger::class)->recordDiff(
            $target,
            [$roleA->id, $roleB->id],
            [$roleB->id, $roleC->id],
            UserRoleAudit::SOURCE_USER_ACCESS,
            '203.0.113.7',
        );

        $this->assertSame(2, UserRoleAudit::where('target_user_id', $target->id)->count());

        $this->assertDatabaseHas('user_role_audits', [
            'target_user_id' => $target->id,
            'role_id'        => $roleA->id,
            'action'         => 'detached',
            'source'         => 'user_access',
            'ip'             => '203.0.113.7',
        ]);
        $this->assertDatabaseHas('user_role_audits', [
            'target_user_id' => $target->id,
            'role_id'        => $roleC->id,
            'action'         => 'attached',
            'source'         => 'user_access',
        ]);
    }

    public function test_logger_is_a_no_op_when_role_set_unchanged(): void
    {
        $target = $this->makeUser();
        $role   = $this->makeRole('same');

        app(UserRoleAuditLogger::class)->recordDiff(
            $target,
            [$role->id],
            [$role->id],
            UserRoleAudit::SOURCE_ADMIN,
        );

        $this->assertSame(0, UserRoleAudit::count());
    }

    public function test_user_access_update_records_actor_on_web_guard(): void
    {
        // Operator with `user.roles.manage` (which is bundled into the
        // user-admin role seeded by the migration).
        $userAdminRole = Role::where('slug', 'user-admin')->where('guard', 'web')->firstOrFail();
        $operator = $this->makeUser(['name' => 'Op One']);
        $operator->roles()->attach($userAdminRole->id);

        $target = $this->makeUser(['name' => 'Promoted Pat']);
        $newRole = $this->makeRole('writer');

        $this->actingAs($operator, 'web')
            ->post(route('user.access.users.update', $target), [
                'role_ids' => [$newRole->id],
            ])
            ->assertRedirect();

        $row = UserRoleAudit::where('target_user_id', $target->id)
            ->where('role_id', $newRole->id)
            ->firstOrFail();

        $this->assertSame('attached', $row->action);
        $this->assertSame('user_access', $row->source);
        $this->assertSame('web', $row->actor_guard);
        $this->assertSame((int) $operator->id, (int) $row->actor_user_id);
        $this->assertNull($row->actor_admin_id);
        $this->assertSame('Op One', $row->actor_name);
    }

    public function test_user_detail_page_hides_role_audits_from_view_only_admins(): void
    {
        // A "Support" admin with `users.view` but NOT `users.edit`
        // should see the user-detail page render, but the role-change
        // audit panel and its data must not be present.
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
            'email'    => 'riley@admin.test',
            'password' => Hash::make('x'),
            'role_id'  => $supportRole->id,
            'status'   => 'active',
        ]);

        $target  = $this->makeUser(['name' => 'Subject Sam']);
        $oldRole = $this->makeRole('historical');
        UserRoleAudit::create([
            'actor_user_id'  => null,
            'actor_admin_id' => null,
            'actor_guard'    => null,
            'actor_name'     => 'Some Past Operator',
            'actor_email'    => null,
            'target_user_id' => $target->id,
            'role_id'        => $oldRole->id,
            'role_slug'      => $oldRole->slug,
            'role_name'      => $oldRole->name,
            'action'         => 'attached',
            'source'         => 'admin',
            'ip'             => '203.0.113.9',
            'created_at'     => now(),
        ]);

        $resp = $this->actingAs($admin, 'admin')
            ->get(route('admin.users.show', $target))
            ->assertOk();

        $resp->assertDontSee('Role change history');
        $resp->assertDontSee('Some Past Operator');
    }

    public function test_deleting_user_records_detached_rows_for_each_attached_role(): void
    {
        // The pivot has cascadeOnDelete(), so the DB would silently
        // strip these rows the moment the user is gone. The model's
        // `deleting` hook must capture them first or reviewers see an
        // attach with no matching detach when investigating the
        // removed admin.
        $target = $this->makeUser(['name' => 'Doomed Dan']);
        $roleA  = $this->makeRole('alpha');
        $roleB  = $this->makeRole('beta');
        $target->roles()->attach([$roleA->id, $roleB->id]);

        // Sanity: clear out the 'attached' rows the controllers would
        // normally have written so this test only asserts the new
        // cascade-driven detach behavior.
        UserRoleAudit::query()->where('target_user_id', $target->id)->delete();

        $target->delete();

        $this->assertSame(2, UserRoleAudit::where('target_user_id', $target->id)->count());

        foreach ([$roleA, $roleB] as $role) {
            $this->assertDatabaseHas('user_role_audits', [
                'target_user_id' => $target->id,
                'role_id'        => $role->id,
                'role_slug'      => $role->slug,
                'action'         => 'detached',
                'source'         => 'user_deleted',
            ]);
        }
    }

    public function test_deleting_user_with_no_roles_writes_no_audit_rows(): void
    {
        $target = $this->makeUser(['name' => 'Lonely Lou']);

        $target->delete();

        $this->assertSame(0, UserRoleAudit::where('target_user_id', $target->id)->count());
    }

    public function test_deleting_role_records_detached_rows_for_each_user_that_held_it(): void
    {
        // Same cascade story on the role side: dropping a role nukes
        // every pivot row for it. The Role model's `deleting` hook
        // walks the pivot first so each affected user account gets
        // its 'detached' counterpart written through the same logger.
        $role = $this->makeRole('soon-gone');
        $u1 = $this->makeUser(['name' => 'User One']);
        $u2 = $this->makeUser(['name' => 'User Two']);
        $u1->roles()->attach($role->id);
        $u2->roles()->attach($role->id);

        UserRoleAudit::query()->whereIn('target_user_id', [$u1->id, $u2->id])->delete();

        $slugSnapshot = $role->slug;
        $role->delete();

        foreach ([$u1, $u2] as $user) {
            $this->assertDatabaseHas('user_role_audits', [
                'target_user_id' => $user->id,
                'role_slug'      => $slugSnapshot,
                'action'         => 'detached',
                'source'         => 'role_deleted',
            ]);
        }
        $this->assertSame(2, UserRoleAudit::where('source', 'role_deleted')->count());
    }

    public function test_admin_destroying_a_user_attributes_cascade_detach_to_that_admin(): void
    {
        // The actor on the audit row should be whoever called delete,
        // not 'System' — back-office operators want to see who
        // destroyed the account that took these roles with it.
        $superRole = Role::firstOrCreate(
            ['slug' => 'super-admin', 'guard' => 'admin'],
            ['name' => 'Super Admin']
        );
        $admin = Admin::create([
            'name'     => 'Destroyer Dana',
            'email'    => 'dana@admin.test',
            'password' => Hash::make('x'),
            'role_id'  => $superRole->id,
            'status'   => 'active',
        ]);

        $target = $this->makeUser(['name' => 'Goodbye Greg']);
        $role   = $this->makeRole('keeper');
        $target->roles()->attach($role->id);
        UserRoleAudit::query()->where('target_user_id', $target->id)->delete();

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.users.destroy', $target))
            ->assertRedirect();

        $row = UserRoleAudit::where('source', 'user_deleted')
            ->where('role_id', $role->id)
            ->firstOrFail();

        $this->assertSame('detached', $row->action);
        $this->assertSame('admin', $row->actor_guard);
        $this->assertSame((int) $admin->id, (int) $row->actor_admin_id);
        $this->assertSame('Destroyer Dana', $row->actor_name);
    }

    public function test_admin_role_update_records_actor_on_admin_guard(): void
    {
        // Seed a super-admin Admin (role lookup is permissive — slug
        // 'super-admin' grants every permission).
        $superRole = Role::firstOrCreate(
            ['slug' => 'super-admin', 'guard' => 'admin'],
            ['name' => 'Super Admin']
        );
        $admin = Admin::create([
            'name'     => 'Back Office Bob',
            'email'    => 'bob@admin.test',
            'password' => Hash::make('x'),
            'role_id'  => $superRole->id,
            'status'   => 'active',
        ]);

        $target = $this->makeUser(['name' => 'Demoted Dee']);
        $oldRole = $this->makeRole('previously');
        $target->roles()->attach($oldRole->id);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.users.roles.update', $target), [
                // Empty role_ids => detach everything.
                'role_ids' => [],
            ])
            ->assertRedirect();

        $row = UserRoleAudit::where('target_user_id', $target->id)
            ->where('role_id', $oldRole->id)
            ->firstOrFail();

        $this->assertSame('detached', $row->action);
        $this->assertSame('admin', $row->source);
        $this->assertSame('admin', $row->actor_guard);
        $this->assertNull($row->actor_user_id);
        $this->assertSame((int) $admin->id, (int) $row->actor_admin_id);
        $this->assertSame('Back Office Bob', $row->actor_name);
    }
}
