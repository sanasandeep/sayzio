<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AdminActionAudit;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\ProtectedAccount;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Services\AdminActionLogger;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Coverage for the "never delete / never suspend" account protection
 * (see {@see ProtectedAccount}). The guard is defense-in-depth: every
 * destructive admin path must call {@see ProtectedAccount::isProtected()}
 * server-side and bail, logging a DELETE_BLOCKED / SUSPEND_BLOCKED audit
 * row, regardless of what the UI hides.
 *
 * The list itself is superadmin-managed: staff with `users.view` may
 * read it but only a superadmin may add/remove entries, and the two
 * hard-locked seeds (superadmin + demo) can never be removed.
 */
class ProtectedAccountGuardTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------

    private function makeUser(array $attrs = []): User
    {
        return User::create(array_merge([
            'name'     => 'U ' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ], $attrs));
    }

    private function makeSuperAdmin(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );

        return Admin::create([
            'name'     => 'Super ' . Str::random(4),
            'email'    => 'super' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    /**
     * A non-super-admin staff member holding exactly the given permission
     * slug(s). Used to prove list management is superadmin-only while the
     * page itself is viewable with `users.view`.
     *
     * @param string|array<int, string> $permSlugs
     */
    private function makeStaffWithPermission(string|array $permSlugs, array $attrs = []): Admin
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

        return Admin::create(array_merge([
            'name'     => 'Staff ' . Str::random(4),
            'email'    => 'staff' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ], $attrs));
    }

    private function protect(string $email, array $attrs = []): ProtectedAccount
    {
        return ProtectedAccount::create(array_merge([
            'email'  => ProtectedAccount::normalizeEmail($email),
            'locked' => false,
            'label'  => 'Test',
        ], $attrs));
    }

    /**
     * Assert exactly one new audit row with the given action references
     * the target email (matched case-insensitively against either the
     * snapshotted target_email or the details payload, since staff blocks
     * may have no matching web user to snapshot).
     */
    private function assertActionLogged(string $action, string $email): void
    {
        $audit = AdminActionAudit::where('action', $action)->latest('id')->first();
        $this->assertNotNull($audit, "Expected an audit row for action {$action}.");

        $needle = strtolower(trim($email));
        $haystack = [
            strtolower(trim((string) $audit->target_email)),
            strtolower(trim((string) ($audit->details['email'] ?? ''))),
        ];
        $this->assertContains($needle, $haystack, "Audit row for {$action} did not reference {$email}.");
    }

    // ---------------------------------------------------------------
    // User: delete is blocked
    // ---------------------------------------------------------------

    public function test_destroying_a_protected_user_is_blocked_and_logged(): void
    {
        $operator = $this->makeSuperAdmin();
        $user = $this->makeUser(['email' => 'protected-del@ex.com']);
        $this->protect($user->email);

        $this->actingAs($operator, 'admin')
            ->delete('/admin/users/' . $user->id)
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertActionLogged(AdminActionLogger::DELETE_BLOCKED, $user->email);
    }

    public function test_destroying_an_unprotected_user_succeeds(): void
    {
        // Control: the guard must not blanket-block ordinary accounts.
        $operator = $this->makeSuperAdmin();
        $user = $this->makeUser();

        $this->actingAs($operator, 'admin')
            ->delete('/admin/users/' . $user->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    // ---------------------------------------------------------------
    // User: suspend is blocked
    // ---------------------------------------------------------------

    public function test_suspending_a_protected_user_is_blocked_and_logged(): void
    {
        $operator = $this->makeSuperAdmin();
        $user = $this->makeUser(['email' => 'protected-susp@ex.com']);
        $this->protect($user->email);

        $this->actingAs($operator, 'admin')
            ->post('/admin/users/' . $user->id . '/suspend', [
                'reason' => 'spam',
            ])
            ->assertRedirect();

        $this->assertNull($user->fresh()->suspended_at);
        $this->assertActionLogged(AdminActionLogger::SUSPEND_BLOCKED, $user->email);
    }

    // ---------------------------------------------------------------
    // User: update(status -> banned|suspended|inactive) is blocked
    // ---------------------------------------------------------------

    #[DataProvider('blockingStatuses')]
    public function test_updating_protected_user_to_blocking_status_is_blocked_and_logged(string $status): void
    {
        $operator = $this->makeSuperAdmin();
        $user = $this->makeUser(['email' => "protected-{$status}@ex.com", 'status' => 'active']);
        $this->protect($user->email);

        $this->actingAs($operator, 'admin')
            ->put('/admin/users/' . $user->id, [
                'status' => $status,
            ])
            ->assertRedirect();

        $this->assertSame('active', $user->fresh()->status);
        $this->assertActionLogged(AdminActionLogger::SUSPEND_BLOCKED, $user->email);
    }

    public static function blockingStatuses(): array
    {
        return [
            'banned'    => ['banned'],
            'suspended' => ['suspended'],
            'inactive'  => ['inactive'],
        ];
    }

    public function test_updating_protected_user_to_active_is_allowed(): void
    {
        // Control: a non-blocking status change is not a suspend-in-disguise.
        $operator = $this->makeSuperAdmin();
        $user = $this->makeUser(['email' => 'protected-rename@ex.com', 'status' => 'active']);
        $this->protect($user->email);

        $this->actingAs($operator, 'admin')
            ->put('/admin/users/' . $user->id, [
                'name'   => 'Renamed',
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertSame('Renamed', $user->fresh()->name);
    }

    // ---------------------------------------------------------------
    // Case-insensitive email matching
    // ---------------------------------------------------------------

    public function test_protection_matches_email_case_insensitively(): void
    {
        $operator = $this->makeSuperAdmin();
        // Protected entry stored lowercased; the user's email differs only
        // by case, so the guard must still recognise it.
        $this->protect('casetest@example.com');
        $user = $this->makeUser(['email' => 'CaseTest@Example.COM']);

        $this->assertTrue(ProtectedAccount::isProtected($user));

        $this->actingAs($operator, 'admin')
            ->delete('/admin/users/' . $user->id)
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertActionLogged(AdminActionLogger::DELETE_BLOCKED, $user->email);
    }

    // ---------------------------------------------------------------
    // Staff (Admin pool): delete + deactivate are blocked
    // ---------------------------------------------------------------

    public function test_destroying_a_protected_staff_member_is_blocked_and_logged(): void
    {
        $operator = $this->makeSuperAdmin();
        $staff = $this->makeStaffWithPermission('users.view', ['email' => 'protected-staff-del@ex.com']);
        $this->protect($staff->email);

        $this->actingAs($operator, 'admin')
            ->delete('/admin/staff/' . $staff->id)
            ->assertRedirect();

        $this->assertDatabaseHas('admins', ['id' => $staff->id]);
        $this->assertActionLogged(AdminActionLogger::DELETE_BLOCKED, $staff->email);
    }

    public function test_deactivating_a_protected_staff_member_is_blocked_and_logged(): void
    {
        $operator = $this->makeSuperAdmin();
        $staff = $this->makeStaffWithPermission('users.view', [
            'email'  => 'protected-staff-inactive@ex.com',
            'status' => 'active',
        ]);
        $this->protect($staff->email);

        $this->actingAs($operator, 'admin')
            ->put('/admin/staff/' . $staff->id, [
                'name'    => $staff->name,
                'email'   => $staff->email,
                'role_id' => $staff->role_id,
                'status'  => 'inactive',
            ])
            ->assertRedirect();

        $this->assertSame('active', $staff->fresh()->status);
        $this->assertActionLogged(AdminActionLogger::SUSPEND_BLOCKED, $staff->email);
    }

    // ---------------------------------------------------------------
    // Protected list management: superadmin-only add/remove
    // ---------------------------------------------------------------

    public function test_superadmin_can_add_and_remove_a_protected_account(): void
    {
        $operator = $this->makeSuperAdmin();

        // Add.
        $this->actingAs($operator, 'admin')
            ->post('/admin/protected-accounts', [
                'email' => 'New.Entry@Example.com',
                'label' => 'Founder',
            ])
            ->assertRedirect();

        $this->assertTrue(ProtectedAccount::isProtectedEmail('new.entry@example.com'));
        $this->assertActionLogged(AdminActionLogger::PROTECTED_ADDED, 'new.entry@example.com');

        // Remove the freshly added (non-locked) entry.
        $entry = ProtectedAccount::whereRaw('lower(email) = ?', ['new.entry@example.com'])->first();
        $this->assertNotNull($entry);

        $this->actingAs($operator, 'admin')
            ->delete('/admin/protected-accounts/' . $entry->id)
            ->assertRedirect();

        $this->assertFalse(ProtectedAccount::isProtectedEmail('new.entry@example.com'));
        $this->assertActionLogged(AdminActionLogger::PROTECTED_REMOVED, 'new.entry@example.com');
    }

    public function test_staff_with_users_view_can_read_the_list(): void
    {
        $staff = $this->makeStaffWithPermission('users.view');

        $this->actingAs($staff, 'admin')
            ->get('/admin/protected-accounts')
            ->assertOk();
    }

    public function test_non_superadmin_staff_cannot_add_to_the_list(): void
    {
        $staff = $this->makeStaffWithPermission('users.view');

        $this->actingAs($staff, 'admin')
            ->post('/admin/protected-accounts', [
                'email' => 'sneaky@example.com',
            ])
            ->assertForbidden();

        $this->assertFalse(ProtectedAccount::isProtectedEmail('sneaky@example.com'));
    }

    public function test_non_superadmin_staff_cannot_remove_from_the_list(): void
    {
        $staff = $this->makeStaffWithPermission('users.view');
        $entry = $this->protect('removable@example.com');

        $this->actingAs($staff, 'admin')
            ->delete('/admin/protected-accounts/' . $entry->id)
            ->assertForbidden();

        $this->assertTrue(ProtectedAccount::isProtectedEmail('removable@example.com'));
    }

    // ---------------------------------------------------------------
    // Hard-locked seeds can never be removed
    // ---------------------------------------------------------------

    public function test_locked_seed_cannot_be_removed_even_by_superadmin(): void
    {
        $operator = $this->makeSuperAdmin();

        $locked = ProtectedAccount::where('locked', true)->first();
        $this->assertNotNull($locked, 'The migration must seed at least one hard-locked protected account.');

        $this->actingAs($operator, 'admin')
            ->delete('/admin/protected-accounts/' . $locked->id)
            ->assertRedirect();

        $this->assertDatabaseHas('protected_accounts', ['id' => $locked->id]);
    }
}
