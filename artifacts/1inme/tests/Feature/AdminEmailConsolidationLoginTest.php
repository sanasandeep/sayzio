<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression guard for the admin-email consolidation migrations, exercised
 * end-to-end against the *real* admin login path.
 *
 * The consolidation migration
 * (2027_07_17_000001_consolidate_admin_email_to_sayzioapp) merges up to three
 * legacy privileged admin rows (sanasandeep@gmail.com, official1inme@gmail.com,
 * admin@1inme.com) down to a single canonical `sayzioapp@gmail.com` super-admin
 * and reconciles `protected_accounts` accordingly; the sibling migration
 * (2027_07_17_000002_rename_demo_login_user_to_sayzioapp) fixes the misspelled
 * `sazioapp@gmail.com` demo-login web user. Both were only checked for ordering
 * and PHP syntax — nothing exercised the actual admin console login *after* they
 * run, so a regression could silently lock out the super-admin.
 *
 * RefreshDatabase already runs both migrations on a fresh DB, but with no legacy
 * rows to merge the interesting rename/prune branches are never touched there.
 * Each test here deliberately rebuilds the pre-consolidation world (the three
 * legacy admins + their locked protected_accounts entries, with NO canonical
 * row) and re-invokes the migrations' up(), then drives the demo-login route the
 * admin console actually uses and asserts:
 *   - the console authenticates as `sayzioapp@gmail.com` with super-admin access,
 *   - protected_accounts holds `sayzioapp@gmail.com` locked=true and NO longer
 *     holds `sanasandeep@gmail.com` or `official1inme@gmail.com`.
 */
class AdminEmailConsolidationLoginTest extends TestCase
{
    use RefreshDatabase;

    private const CANONICAL = 'sayzioapp@gmail.com';
    private const LEGACY = [
        'sanasandeep@gmail.com',
        'official1inme@gmail.com',
        'admin@1inme.com',
    ];
    private const TYPO_USER = 'sazioapp@gmail.com';

    /** Resolve (creating if absent) the admin-guard super-admin role id. */
    private function superAdminRoleId(): int
    {
        $id = DB::table('roles')
            ->where('slug', 'super-admin')
            ->where('guard', 'admin')
            ->value('id');

        if ($id) {
            return (int) $id;
        }

        return (int) DB::table('roles')->insertGetId([
            'name'        => 'Super Admin',
            'slug'        => 'super-admin',
            'description' => 'Full access to all admin features',
            'guard'       => 'admin',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /**
     * Tear the admin/protected state down to the LEGACY, pre-consolidation
     * world: no canonical row, the three legacy admins present (the first a
     * genuine super-admin), and a locked protected_accounts entry per legacy
     * email. This is exactly the state Case B of the consolidation migration is
     * built to collapse.
     */
    private function seedLegacyWorld(): void
    {
        $now = now();
        $superRoleId = $this->superAdminRoleId();

        // Wipe every admin + protected row so no leftover canonical row from
        // the migration's own setup run masks the rename branch.
        DB::table('admins')->delete();
        DB::table('protected_accounts')->delete();

        foreach (self::LEGACY as $i => $email) {
            DB::table('admins')->insert([
                'name'       => 'Legacy ' . $email,
                'email'      => $email,
                'password'   => Hash::make('secret'),
                // First legacy row is the genuine super-admin the migration
                // should prefer and rename; the rest are ordinary rows.
                'role_id'    => $i === 0 ? $superRoleId : null,
                'status'     => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('protected_accounts')->insert([
                'email'      => $email,
                'locked'     => true,
                'label'      => 'Legacy',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** Run both consolidation migrations' up() against the current data. */
    private function runConsolidationMigrations(): void
    {
        foreach ([
            'migrations/2027_07_17_000001_consolidate_admin_email_to_sayzioapp.php',
            'migrations/2027_07_17_000002_rename_demo_login_user_to_sayzioapp.php',
        ] as $rel) {
            $migration = require database_path($rel);
            $migration->up();
        }
    }

    private function protectedExists(string $email): bool
    {
        return DB::table('protected_accounts')
            ->whereRaw('lower(email) = ?', [strtolower($email)])
            ->exists();
    }

    public function test_admin_console_logs_in_as_canonical_super_admin_after_consolidation(): void
    {
        // The RecordAdminLastLoginJob the login path dispatches is irrelevant to
        // this assertion; keep it off the wire so the test is about auth only.
        Queue::fake();

        $this->seedLegacyWorld();
        $this->runConsolidationMigrations();

        // --- The consolidated admin row is the canonical super-admin. --------
        $canonical = Admin::where('email', self::CANONICAL)->first();
        $this->assertNotNull($canonical, 'Consolidation must leave a canonical sayzioapp@gmail.com admin row.');
        $this->assertSame('active', $canonical->status);
        $this->assertTrue(
            $canonical->isSuperAdmin(),
            'The consolidated admin must hold the super-admin role.'
        );

        // Every legacy admin row is gone (merged into the canonical one).
        foreach (self::LEGACY as $legacy) {
            $this->assertDatabaseMissing('admins', ['email' => $legacy]);
        }

        // --- The real admin console login path authenticates as it. ---------
        // (The GET redirects to the login page; the POST is the demo quick-login
        // the console exposes off-production.)
        $response = $this->post(route('admin.demo.login'));
        $response->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($canonical, 'admin');
        $loggedIn = $this->app['auth']->guard('admin')->user();
        $this->assertSame(self::CANONICAL, $loggedIn->email);
        $this->assertTrue($loggedIn->isSuperAdmin(), 'The logged-in admin must have super-admin access.');
    }

    public function test_protected_accounts_are_reconciled_to_the_canonical_email(): void
    {
        $this->seedLegacyWorld();
        $this->runConsolidationMigrations();

        // The canonical email is protected and hard-locked ("Superadmin").
        $canonical = DB::table('protected_accounts')
            ->whereRaw('lower(email) = ?', [strtolower(self::CANONICAL)])
            ->first();
        $this->assertNotNull($canonical, 'sayzioapp@gmail.com must be a protected account.');
        $this->assertTrue((bool) $canonical->locked, 'The canonical protected entry must be locked.');

        // The retired legacy emails are no longer protected.
        $this->assertFalse(
            $this->protectedExists('sanasandeep@gmail.com'),
            'sanasandeep@gmail.com must no longer be a protected account after consolidation.'
        );
        $this->assertFalse(
            $this->protectedExists('official1inme@gmail.com'),
            'official1inme@gmail.com must no longer be a protected account after consolidation.'
        );
    }

    public function test_consolidation_is_idempotent_when_canonical_already_exists(): void
    {
        // Case A: a canonical row already exists — re-running must keep it, drop
        // any stray legacy rows, and never lock out the super-admin.
        Queue::fake();

        $this->seedLegacyWorld();
        $this->runConsolidationMigrations();
        // Re-run to prove the migration is safe on an already-consolidated DB.
        $this->runConsolidationMigrations();

        $this->assertSame(
            1,
            Admin::where('email', self::CANONICAL)->count(),
            'Re-running consolidation must not duplicate the canonical admin.'
        );

        $this->post(route('admin.demo.login'))
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs(
            Admin::where('email', self::CANONICAL)->first(),
            'admin'
        );
    }

    public function test_demo_login_web_user_typo_is_renamed_to_canonical(): void
    {
        // The sibling migration fixes the misspelled demo-login web user. Seed
        // the typo'd row with no canonical present so the rename branch runs.
        $this->seedLegacyWorld();

        DB::table('users')->whereRaw('lower(email) = ?', [strtolower(self::TYPO_USER)])->delete();
        DB::table('users')->whereRaw('lower(email) = ?', [strtolower(self::CANONICAL)])->delete();
        $typoId = DB::table('users')->insertGetId([
            'name'       => 'Demo ' . Str::random(4),
            'email'      => self::TYPO_USER,
            'password'   => Hash::make('secret'),
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->runConsolidationMigrations();

        // The row is renamed in place (same id → FK relations preserved).
        $this->assertDatabaseMissing('users', ['email' => self::TYPO_USER]);
        $this->assertSame(
            self::CANONICAL,
            DB::table('users')->where('id', $typoId)->value('email'),
            'The demo-login web user must be renamed in place to the canonical spelling.'
        );
    }
}
