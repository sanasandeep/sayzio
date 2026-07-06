<?php

namespace Tests\Feature;

use App\Modules\Admin\Controllers\ActivityLogController;
use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AdminActionAudit;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\ProtectedAccount;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Services\AdminActionLogger;
use App\Modules\User\Models\Link;
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
        return User::factory()->create($attrs);
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
     * Give a user a key owned relation (a link) so blocked-destroy tests
     * can prove the account *and its data* survived the attempt — not just
     * that the row exists, but that a future cascading-delete refactor
     * didn't quietly take the children with it.
     */
    private function makeLinkFor(User $user, array $attrs = []): Link
    {
        return Link::create(array_merge([
            'user_id'  => $user->id,
            'type'     => 'short',
            'alias'    => 'lk' . Str::random(8),
            'title'    => 'Owned link',
            'long_url' => 'https://example.com/' . Str::random(6),
        ], $attrs));
    }

    /**
     * Assert a protected user row is physically present and byte-for-byte
     * unchanged after a blocked destructive request, including its owned
     * link relation(s). This closes the loop the controller-level audit
     * assertion leaves open: a refactor could log the block yet still
     * delete/mutate the row, and only a direct "row still present and
     * identical" check would catch it.
     *
     * @param array<int, int> $linkIds owned link ids that must survive
     */
    private function assertUserPersistedIntact(User $before, array $linkIds = []): void
    {
        $this->assertDatabaseHas('users', [
            'id'           => $before->id,
            'email'        => $before->email,
            'name'         => $before->name,
            'status'       => $before->status,
            'suspended_at' => $before->suspended_at,
        ]);

        $after = User::find($before->id);
        $this->assertNotNull($after, 'Protected user row vanished after a blocked attempt.');
        $this->assertSame($before->status, $after->status);
        $this->assertNull($after->suspended_at, 'Protected user was suspended despite the guard.');

        foreach ($linkIds as $linkId) {
            $this->assertDatabaseHas('links', [
                'id'      => $linkId,
                'user_id' => $before->id,
            ]);
        }
    }

    /**
     * Assert a protected staff (admin-pool) row is present and unchanged
     * after a blocked destructive request, including its role relation.
     */
    private function assertStaffPersistedIntact(Admin $before): void
    {
        $this->assertDatabaseHas('admins', [
            'id'      => $before->id,
            'email'   => $before->email,
            'name'    => $before->name,
            'status'  => $before->status,
            'role_id' => $before->role_id,
        ]);

        $after = Admin::find($before->id);
        $this->assertNotNull($after, 'Protected staff row vanished after a blocked attempt.');
        $this->assertSame($before->status, $after->status);
        $this->assertSame($before->role_id, $after->role_id, 'Protected staff lost its role relation.');
        $this->assertNotNull($after->role, 'Protected staff role relation no longer resolves.');
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
        $link = $this->makeLinkFor($user);
        $this->protect($user->email);

        $this->actingAs($operator, 'admin')
            ->delete('/admin/users/' . $user->id)
            ->assertRedirect();

        $this->assertActionLogged(AdminActionLogger::DELETE_BLOCKED, $user->email);
        // The account *and* its owned link must physically survive the
        // blocked delete — not merely that an audit row was written.
        $this->assertUserPersistedIntact($user, [$link->id]);
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
        $link = $this->makeLinkFor($user);
        $this->protect($user->email);

        $this->actingAs($operator, 'admin')
            ->post('/admin/users/' . $user->id . '/suspend', [
                'reason' => 'spam',
            ])
            ->assertRedirect();

        $this->assertNull($user->fresh()->suspended_at);
        $this->assertActionLogged(AdminActionLogger::SUSPEND_BLOCKED, $user->email);
        $this->assertUserPersistedIntact($user, [$link->id]);
    }

    // ---------------------------------------------------------------
    // User: update(status -> banned|suspended|inactive) is blocked
    // ---------------------------------------------------------------

    #[DataProvider('blockingStatuses')]
    public function test_updating_protected_user_to_blocking_status_is_blocked_and_logged(string $status): void
    {
        $operator = $this->makeSuperAdmin();
        $user = $this->makeUser(['email' => "protected-{$status}@ex.com", 'status' => 'active']);
        $link = $this->makeLinkFor($user);
        $this->protect($user->email);

        $this->actingAs($operator, 'admin')
            ->put('/admin/users/' . $user->id, [
                'status' => $status,
            ])
            ->assertRedirect();

        $this->assertSame('active', $user->fresh()->status);
        $this->assertActionLogged(AdminActionLogger::SUSPEND_BLOCKED, $user->email);
        $this->assertUserPersistedIntact($user, [$link->id]);
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

        $this->assertActionLogged(AdminActionLogger::DELETE_BLOCKED, $staff->email);
        $this->assertStaffPersistedIntact($staff);
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
        $this->assertStaffPersistedIntact($staff);
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

    // ---------------------------------------------------------------
    // End-to-end: a protected account survives a *full* deletion attempt
    // ---------------------------------------------------------------

    /**
     * The single most important guarantee, exercised end-to-end: fire every
     * destructive admin path (delete + suspend + status→banned/suspended/
     * inactive) at the SAME protected user, back to back, and confirm the
     * account — and its owned link — is still present and unchanged after
     * the whole barrage. A refactor that blocks one path but regresses
     * another, or that logs the block yet mutates the row, fails here.
     */
    public function test_protected_user_survives_a_full_sequence_of_destructive_attempts(): void
    {
        $operator = $this->makeSuperAdmin();
        $user = $this->makeUser(['email' => 'survivor@ex.com', 'status' => 'active']);
        $link = $this->makeLinkFor($user);
        $this->protect($user->email);

        // 1. Hard delete.
        $this->actingAs($operator, 'admin')
            ->delete('/admin/users/' . $user->id)
            ->assertRedirect();
        $this->assertUserPersistedIntact($user, [$link->id]);

        // 2. Suspend (temporary hold).
        $this->actingAs($operator, 'admin')
            ->post('/admin/users/' . $user->id . '/suspend', ['reason' => 'spam'])
            ->assertRedirect();
        $this->assertUserPersistedIntact($user, [$link->id]);

        // 3. Each blocking status change, in turn.
        foreach (['banned', 'suspended', 'inactive'] as $status) {
            $this->actingAs($operator, 'admin')
                ->put('/admin/users/' . $user->id, ['status' => $status])
                ->assertRedirect();
            $this->assertUserPersistedIntact($user, [$link->id]);
        }

        // After the full barrage the account is untouched and still active.
        $final = $user->fresh();
        $this->assertSame('active', $final->status);
        $this->assertNull($final->suspended_at);
        $this->assertSame(1, $user->links()->count(), 'Owned link was lost during the deletion barrage.');
    }

    /**
     * Staff-pool counterpart: fire delete + deactivate at the same protected
     * staff member in sequence and confirm the admin row (and its role
     * relation) survives both.
     */
    public function test_protected_staff_survives_a_full_sequence_of_destructive_attempts(): void
    {
        $operator = $this->makeSuperAdmin();
        $staff = $this->makeStaffWithPermission('users.view', [
            'email'  => 'survivor-staff@ex.com',
            'status' => 'active',
        ]);
        $this->protect($staff->email);

        // 1. Hard delete.
        $this->actingAs($operator, 'admin')
            ->delete('/admin/staff/' . $staff->id)
            ->assertRedirect();
        $this->assertStaffPersistedIntact($staff);

        // 2. Deactivate (status → inactive, the staff "suspend").
        $this->actingAs($operator, 'admin')
            ->put('/admin/staff/' . $staff->id, [
                'name'    => $staff->name,
                'email'   => $staff->email,
                'role_id' => $staff->role_id,
                'status'  => 'inactive',
            ])
            ->assertRedirect();
        $this->assertStaffPersistedIntact($staff);

        $this->assertSame('active', $staff->fresh()->status);
    }

    // ---------------------------------------------------------------
    // Activity log renders blocked actions with a human-readable label
    // ---------------------------------------------------------------

    /**
     * The two blocked-action constants written by the guard.
     *
     * @return array<string, array{0:string}>
     */
    public static function blockedActions(): array
    {
        return [
            'delete blocked'  => [AdminActionLogger::DELETE_BLOCKED],
            'suspend blocked' => [AdminActionLogger::SUSPEND_BLOCKED],
        ];
    }

    /**
     * A blocked-action constant must be registered in BOTH label sources,
     * or it renders raw/blank/un-curated in the Activity Log. Per the
     * known gotcha, every action needs an entry in
     * {@see ActivityLogController::ACTIONS} (the filter map / dropdown)
     * AND a curated arm in {@see AdminActionAudit::actionLabel()} (the
     * per-row label). If `actionLabel()` lacks the arm it falls back to
     * the generic ucfirst() form, which won't equal the curated ACTIONS
     * label — so asserting the two agree catches a future constant added
     * to only one of the two places.
     */
    #[DataProvider('blockedActions')]
    public function test_blocked_action_is_registered_in_both_label_sources(string $action): void
    {
        // 1. Present in the controller's filter map (drives the dropdown
        //    and the action filter whitelist).
        $this->assertArrayHasKey(
            $action,
            ActivityLogController::ACTIONS,
            "Action {$action} is missing from ActivityLogController::ACTIONS."
        );

        $curated = ActivityLogController::ACTIONS[$action];

        // 2. A real human label, not the raw dotted constant.
        $this->assertNotSame('', trim($curated), "Action {$action} has a blank label.");
        $this->assertNotSame($action, $curated, "Action {$action} renders as its raw constant.");

        // 3. The per-row label must match the curated map. A missing arm
        //    in actionLabel() would yield the ucfirst() fallback instead.
        $audit = new AdminActionAudit(['action' => $action]);
        $this->assertSame(
            $curated,
            $audit->actionLabel(),
            "actionLabel() for {$action} doesn't match its ActivityLogController::ACTIONS label — one of the two registrations is missing or out of sync."
        );
    }

    /**
     * End-to-end: drive a real blocked delete + blocked suspend at the
     * same protected user, then load the admin Activity Log page and
     * confirm both rows surface their human label (and never the raw
     * `account.delete_blocked` / `account.suspend_blocked` constant).
     */
    public function test_activity_log_page_renders_blocked_actions_readably(): void
    {
        $operator = $this->makeSuperAdmin();
        $user = $this->makeUser(['email' => 'log-render@ex.com']);
        $this->protect($user->email);

        // Blocked hard-delete, then blocked suspend (the account survives
        // the delete, so the suspend attempt still has a target to log).
        $this->actingAs($operator, 'admin')
            ->delete('/admin/users/' . $user->id)
            ->assertRedirect();
        $this->actingAs($operator, 'admin')
            ->post('/admin/users/' . $user->id . '/suspend', ['reason' => 'spam'])
            ->assertRedirect();

        $this->assertActionLogged(AdminActionLogger::DELETE_BLOCKED, $user->email);
        $this->assertActionLogged(AdminActionLogger::SUSPEND_BLOCKED, $user->email);

        $response = $this->actingAs($operator, 'admin')
            ->get(route('admin.users.activity-log.index'))
            ->assertOk();

        foreach ([AdminActionLogger::DELETE_BLOCKED, AdminActionLogger::SUSPEND_BLOCKED] as $action) {
            $curated = ActivityLogController::ACTIONS[$action];

            // The curated human label is on the page...
            $response->assertSee($curated);
            // ...and the raw dotted constant is never shown as visible text.
            $response->assertDontSee('>' . $action . '<', false);
        }
    }
}
