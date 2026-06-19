<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Models\SchemaRepairAudit;
use App\Modules\Common\Support\ExpectedSchemaHealth;
use App\Modules\Common\Support\SchemaManifest;
use App\Modules\User\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the mobile schema-column repair parity endpoints:
 *
 *   GET  /api/v1/admin/schema-health         (read-only drift report)
 *   POST /api/v1/admin/schema-health/repair  (add + backfill missing columns)
 *
 * Both share the same destructive-adjacent engine as the web dashboard banner
 * ({@see ExpectedSchemaHealth}) and are gated behind the same `settings.manage`
 * permission the web "Fix now" action uses, so a regular sanctum token must be
 * rejected. The repair endpoint must distinguish repairable column drift
 * (reported under `added`) from a whole-missing table it cannot recreate in
 * place (reported under `unrepairable`), and leave an audit row.
 *
 * No local `1inme_testing` DB exists, so (like the other Feature tests) these
 * run against the CI Postgres via RefreshDatabase, which transactionally wraps
 * each test — the deliberate column/table drops below are rolled back
 * automatically.
 */
class MobileSchemaHealthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The manifest caches by migration-file fingerprint and the report by
        // its own key; clear both so each test computes against current state.
        SchemaManifest::flush();
        ExpectedSchemaHealth::flush();
    }

    private function makeUser(): User
    {
        $user = User::create([
            'name'     => 'SH ' . Str::random(4),
            'email'    => 'sh-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
        $user->ensureDefaultWorkspace();
        return $user->fresh();
    }

    /** A user holding the web-guard `settings.manage` permission (super admin). */
    private function makeAdmin(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => 'platform-settings'],
            ['name' => 'Platform Settings', 'guard' => 'web']
        );
        $perm = Permission::firstOrCreate(
            ['slug' => 'settings.manage'],
            ['name' => 'Manage Settings', 'group' => 'settings']
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);

        $user = $this->makeUser();
        $user->roles()->attach($role->id);
        $user->flushPermissionCache();
        return $user->fresh();
    }

    /**
     * Authenticate as the given user using a REAL Sanctum personal access
     * token (Bearer header), exactly like the Expo app. We deliberately avoid
     * Sanctum::actingAs: it injects a Mockery mock as the current access
     * token, which the TouchSessionToken middleware can't forceFill()->save()
     * on, 500ing every authenticated request.
     */
    private function asUser(User $user): self
    {
        $plain = $user->createToken('mobile-test')->plainTextToken;
        $this->withToken($plain);
        return $this;
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/admin/schema-health')->assertStatus(401);
        $this->postJson('/api/v1/admin/schema-health/repair')->assertStatus(401);
    }

    public function test_status_forbidden_for_a_non_admin_token(): void
    {
        $this->asUser($this->makeUser());

        $this->getJson('/api/v1/admin/schema-health')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_repair_forbidden_for_a_non_admin_token(): void
    {
        $this->asUser($this->makeUser());

        $this->postJson('/api/v1/admin/schema-health/repair')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');

        // A blocked caller must not have altered the schema, so no audit row.
        $this->assertSame(0, SchemaRepairAudit::count());
    }

    public function test_status_returns_a_healthy_drift_report_for_an_admin(): void
    {
        $this->asUser($this->makeAdmin());

        $resp = $this->getJson('/api/v1/admin/schema-health');

        $resp->assertOk();
        // A fresh, fully-migrated DB is in sync: available + healthy, nothing missing.
        $resp->assertJsonPath('data.available', true);
        $resp->assertJsonPath('data.healthy', true);
        $resp->assertJsonPath('data.missing_count', 0);
        $resp->assertJsonPath('data.missing', []);
        $this->assertIsInt($resp->json('data.scanned'));
        $this->assertGreaterThan(0, $resp->json('data.scanned'));
    }

    public function test_status_reports_a_dropped_column_under_missing(): void
    {
        $admin = $this->makeAdmin();

        // Drop a column the manifest expects to simulate edited-after-applied
        // drift (rolled back with the test transaction).
        Schema::table('links', function (Blueprint $t) {
            $t->dropColumn('seo_title');
        });
        ExpectedSchemaHealth::flush();

        $this->asUser($admin);
        $resp = $this->getJson('/api/v1/admin/schema-health');

        $resp->assertOk();
        $resp->assertJsonPath('data.available', true);
        $resp->assertJsonPath('data.healthy', false);
        $this->assertGreaterThanOrEqual(1, $resp->json('data.missing_count'));

        $entry = collect($resp->json('data.missing'))->firstWhere('table', 'links');
        $this->assertNotNull($entry, 'links should appear in the drift report');
        $this->assertFalse($entry['table_missing'], 'a column-only drift must not flag the whole table');
        $this->assertContains('seo_title', $entry['columns']);
    }

    public function test_repair_reports_column_drift_under_added_and_whole_missing_table_under_unrepairable(): void
    {
        $admin = $this->makeAdmin();

        // Column drift: a single expected column is missing (repairable in place).
        Schema::table('links', function (Blueprint $t) {
            $t->dropColumn('seo_title');
        });
        // Whole-missing table: a leaf analytics table with no incoming FKs is
        // gone entirely (cannot be re-created in place — needs migrate --force).
        Schema::drop('link_clicks');
        $this->assertFalse(Schema::hasColumn('links', 'seo_title'));
        $this->assertFalse(Schema::hasTable('link_clicks'));

        ExpectedSchemaHealth::flush();

        $this->asUser($admin);
        $resp = $this->postJson('/api/v1/admin/schema-health/repair');

        $resp->assertOk();

        // Column drift was repaired in place and reported under `added`.
        $this->assertContains('seo_title', $resp->json('data.added.links') ?? [], 'repaired column should be under added');
        $this->assertTrue(Schema::hasColumn('links', 'seo_title'), 'repair should physically re-create the column');

        // The whole-missing table is reported under `unrepairable`, NOT added.
        $this->assertContains('link_clicks', $resp->json('data.unrepairable') ?? [], 'whole-missing table should be unrepairable');
        $this->assertArrayNotHasKey('link_clicks', (array) $resp->json('data.added'), 'a whole-missing table must not be reported as added');
        $this->assertFalse(Schema::hasTable('link_clicks'), 'repair must not recreate a whole-missing table');

        // The two failure classes never cross over.
        $this->assertNotContains('links', $resp->json('data.unrepairable') ?? [], 'column drift must not be flagged unrepairable');

        // Outcome flags reflect the still-missing whole table.
        $this->assertGreaterThanOrEqual(1, $resp->json('data.added_columns_count'));
        $this->assertGreaterThanOrEqual(1, $resp->json('data.still_missing'));
        $this->assertFalse($resp->json('data.healthy'), 'still-missing table keeps the schema unhealthy');
    }

    public function test_repair_writes_an_audit_row_attributing_the_admin_actor(): void
    {
        $admin = $this->makeAdmin();

        Schema::table('links', function (Blueprint $t) {
            $t->dropColumn('seo_title');
        });
        ExpectedSchemaHealth::flush();

        $this->asUser($admin);
        $this->postJson('/api/v1/admin/schema-health/repair')->assertOk();

        $audit = SchemaRepairAudit::latest('created_at')->first();
        $this->assertNotNull($audit, 'repair must leave an audit row');
        $this->assertSame($admin->id, $audit->actor_user_id);
        $this->assertSame('web', $audit->actor_guard);
        $this->assertSame($admin->email, $audit->actor_email);
        $this->assertContains('seo_title', $audit->added['links'] ?? []);
        $this->assertGreaterThanOrEqual(1, $audit->added_columns_count);
    }

    public function test_repair_on_a_healthy_schema_is_a_no_op(): void
    {
        $this->asUser($this->makeAdmin());

        $resp = $this->postJson('/api/v1/admin/schema-health/repair');

        $resp->assertOk();
        $resp->assertJsonPath('data.added', []);
        $resp->assertJsonPath('data.unrepairable', []);
        $resp->assertJsonPath('data.added_columns_count', 0);
        $resp->assertJsonPath('data.still_missing', 0);
        $resp->assertJsonPath('data.healthy', true);

        // A no-op run is still audited for the trail (web parity).
        $audit = SchemaRepairAudit::latest('created_at')->first();
        $this->assertNotNull($audit);
        $this->assertSame(0, $audit->added_columns_count);
    }
}
