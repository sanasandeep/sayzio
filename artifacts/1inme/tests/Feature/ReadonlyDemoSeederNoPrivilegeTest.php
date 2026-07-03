<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use Database\Seeders\ReadonlyDemoAccountSeeder;
use Database\Seeders\ShowcaseAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task #3502 — privilege-boundary regression coverage for the public
 * read-only demo account provisioned by {@see ReadonlyDemoAccountSeeder}.
 *
 * That seeder subclasses {@see ShowcaseAccountSeeder} and overrides two
 * distinct privilege-granting paths so the publicly-shared demo account stays
 * a plain, no-privilege user:
 *
 *   1. {@see ReadonlyDemoAccountSeeder::ensureAdminBridge()} — a no-op that
 *      also cleans up any stale back-office {@see Admin} row, so the account
 *      never gets admin / super-admin access.
 *   2. {@see ReadonlyDemoAccountSeeder::shouldAssignUserAdminRole()} returning
 *      false — so the inherited {@see ShowcaseAccountSeeder::ensureUser()}
 *      never attaches the privileged `user-admin` web role.
 *
 * A past code review caught that stripping only the Admin bridge (step 1) left
 * the account with the `user-admin` role (step 2) — a subtle privilege leak.
 * Nothing guarded that both overrides stay in force, so a future refactor of
 * either seeder could silently re-elevate a publicly-shared account. These
 * tests lock in the boundary.
 *
 * The full showcase content graph (dozens of link types, biolink blocks,
 * forms, QR, backdated analytics) takes minutes to build — far past the test
 * budget. Since the privilege decisions all live in the account-provisioning
 * path ({@see ShowcaseAccountSeeder::ensureUser()} +
 * {@see ShowcaseAccountSeeder::ensureAdminBridge()}, the first two steps of
 * `run()`), these tests exercise exactly that path via reflection and skip the
 * content build entirely.
 */
class ReadonlyDemoSeederNoPrivilegeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Run only the account-provisioning path of a showcase-style seeder — the
     * first two steps of its `run()`: `ensureUser($plan)` then
     * `ensureAdminBridge($user)`. This is the entire surface where the demo
     * account's privileges (or lack thereof) are decided, and it avoids the
     * minutes-long content graph the rest of `run()` builds.
     *
     * `ensureAdminBridge()` is `protected` (so reflection dispatches to the
     * subclass override for the read-only demo seeder); `ensureUser()` is a
     * `private` base method reused unchanged by both seeders.
     */
    private function provisionAccountOnly(ShowcaseAccountSeeder $seeder, Plan $plan): User
    {
        // `ensureUser` is a private base method reused unchanged by both
        // seeders — resolve it from its declaring class explicitly.
        $ensureUser = new \ReflectionMethod(ShowcaseAccountSeeder::class, 'ensureUser');
        $ensureUser->setAccessible(true);
        /** @var User $user */
        $user = $ensureUser->invoke($seeder, $plan);

        $ensureAdminBridge = new \ReflectionMethod($seeder, 'ensureAdminBridge');
        $ensureAdminBridge->setAccessible(true);
        $ensureAdminBridge->invoke($seeder, $user);

        return $user->fresh();
    }

    /**
     * The `unlimited` comp plan the showcase seeders bind their account to.
     * `run()` bails early when it's missing, so provisioning requires it.
     */
    private function unlimitedPlan(): Plan
    {
        return Plan::create([
            'name'        => 'Unlimited',
            'slug'        => 'unlimited',
            'status'      => 'active',
            'is_internal' => true,
        ]);
    }

    /**
     * `ensureAdminBridge()` on the BASE seeder needs the admin-guard
     * `super-admin` role to exist. Unlike the web-guard `user-admin` role
     * (seeded by migration, so already present under RefreshDatabase), this
     * one is only created by DatabaseSeeder, so the test creates it.
     */
    private function ensureSuperAdminRole(): void
    {
        Role::firstOrCreate(
            ['slug' => 'super-admin', 'guard' => 'admin'],
            ['name' => 'Super Admin', 'description' => 'Full access to all admin features']
        );
    }

    private function userAdminRoleId(): ?int
    {
        return Role::where('slug', 'user-admin')->where('guard', 'web')->value('id');
    }

    private function hasUserAdminRole(User $user): bool
    {
        $roleId = $this->userAdminRoleId();
        if (!$roleId) {
            return false;
        }

        return $user->roles()->where('roles.id', $roleId)->exists();
    }

    public function test_readonly_demo_seeder_provisions_a_plain_no_privilege_user(): void
    {
        $this->ensureSuperAdminRole();
        $plan = $this->unlimitedPlan();

        // Sanity: the privileged web role really exists (seeded by migration),
        // so "no user-admin role" below is a meaningful assertion, not a
        // false pass from the role simply being absent everywhere.
        $this->assertNotNull(
            $this->userAdminRoleId(),
            'Pre-condition: the user-admin web role should exist (seeded by migration).'
        );

        $user = $this->provisionAccountOnly(new ReadonlyDemoAccountSeeder(), $plan);

        $this->assertSame(
            ReadonlyDemoAccountSeeder::EMAIL,
            $user->email,
            'The read-only demo seeder should provision demo@sayzio.app.'
        );

        // 1. The write-guard flag that neuters every state-changing request.
        $this->assertTrue(
            (bool) $user->is_readonly_demo,
            'The read-only demo account must have is_readonly_demo = true.'
        );

        // 2. No back-office Admin record → no admin / super-admin access.
        $this->assertNull(
            Admin::where('email', ReadonlyDemoAccountSeeder::EMAIL)->first(),
            'The read-only demo account must have NO Admin (back-office) record.'
        );

        // 3. No privileged user-admin web role either.
        $this->assertFalse(
            $this->hasUserAdminRole($user),
            'The read-only demo account must NOT hold the user-admin web role.'
        );
    }

    public function test_base_showcase_seeder_account_still_gets_role_and_admin_bridge(): void
    {
        $this->ensureSuperAdminRole();
        $plan = $this->unlimitedPlan();

        $user = $this->provisionAccountOnly(new ShowcaseAccountSeeder(), $plan);

        $this->assertSame(
            ShowcaseAccountSeeder::EMAIL,
            $user->email,
            'The base showcase seeder should provision sana@sayzio.app.'
        );

        // The base showcase account is intentionally privileged — this
        // companion assertion proves the read-only demo overrides above are
        // what strips privileges, not a change to the shared base behaviour.
        $this->assertFalse(
            (bool) $user->is_readonly_demo,
            'The base showcase account must NOT be flagged read-only demo.'
        );

        $this->assertNotNull(
            Admin::where('email', ShowcaseAccountSeeder::EMAIL)->first(),
            'The base showcase account must get its back-office Admin bridge.'
        );

        $this->assertTrue(
            $this->hasUserAdminRole($user),
            'The base showcase account must hold the user-admin web role.'
        );
    }

    /**
     * Re-running the read-only demo seeder over an account that was previously
     * left privileged (Admin row + user-admin role — e.g. seeded before this
     * guard existed) must strip both. This is the exact idempotent-cleanup
     * path that the past code-review fix added, so it gets its own guard.
     */
    public function test_readonly_demo_seeder_cleans_up_previously_granted_privileges(): void
    {
        $this->ensureSuperAdminRole();
        $plan = $this->unlimitedPlan();

        // First provision the SAME email via the privileged base seeder so it
        // ends up with the Admin bridge and the user-admin role.
        $privileged = $this->provisionAccountOnly(new ReadonlyDemoAccountSeederAsBaseForTest(), $plan);
        $this->assertNotNull(Admin::where('email', ReadonlyDemoAccountSeeder::EMAIL)->first());
        $this->assertTrue($this->hasUserAdminRole($privileged));

        // Now the real read-only demo seeder must demote it to a plain user.
        $demo = $this->provisionAccountOnly(new ReadonlyDemoAccountSeeder(), $plan);

        $this->assertTrue((bool) $demo->is_readonly_demo);
        $this->assertNull(
            Admin::where('email', ReadonlyDemoAccountSeeder::EMAIL)->first(),
            'Re-seeding must remove a stale Admin record from the demo account.'
        );
        $this->assertFalse(
            $this->hasUserAdminRole($demo),
            'Re-seeding must detach the stale user-admin role from the demo account.'
        );
    }
}

/**
 * Test-only helper: provisions the read-only demo EMAIL but WITH privileges,
 * standing in for "the account as it looked before the privilege guard
 * existed". It keeps the base seeder's privileged behaviour (admin bridge +
 * user-admin role) while inheriting the demo account's fixed
 * email/handle/name constants, so the cleanup test starts from a realistically
 * over-privileged demo row.
 */
class ReadonlyDemoAccountSeederAsBaseForTest extends ShowcaseAccountSeeder
{
    public const EMAIL = ReadonlyDemoAccountSeeder::EMAIL;
    public const PASSWORD = ReadonlyDemoAccountSeeder::PASSWORD;
    public const HANDLE = ReadonlyDemoAccountSeeder::HANDLE;
    public const NAME = ReadonlyDemoAccountSeeder::NAME;
    public const BIO = ReadonlyDemoAccountSeeder::BIO;
}
