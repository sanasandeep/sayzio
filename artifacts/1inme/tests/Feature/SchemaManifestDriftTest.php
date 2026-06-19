<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\ExpectedSchemaHealth;
use App\Modules\Common\Support\SchemaManifest;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the auto-derived schema drift detector
 * ({@see SchemaManifest} + {@see ExpectedSchemaHealth}).
 *
 * The detector replaced the hand-maintained expected-columns list with one
 * derived by replaying every migration's net `up()` effect. It runs hourly and
 * gates the `/up/schema` readiness probe, so a regression in the replay logic
 * could silently stop catching real drift — the exact failure class that took
 * the public /creators page down. These tests pin the behaviour that matters:
 *  - the manifest is built and contains known tables/columns,
 *  - `down()` rollback drops are never applied (only `up()` is replayed),
 *  - the report shape is stable and a genuinely-missing live column is caught.
 *
 * No local `1inme_testing` DB exists, so (like the other Feature tests) these
 * run against the CI Postgres via RefreshDatabase, which transactionally wraps
 * each test — the deliberate column drop below is rolled back automatically.
 */
class SchemaManifestDriftTest extends TestCase
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

    public function test_build_is_available_and_includes_known_tables_and_columns(): void
    {
        $manifest = SchemaManifest::build();

        $this->assertTrue($manifest['available'], 'manifest replay should succeed');
        $this->assertArrayHasKey('tables', $manifest);

        $tables = $manifest['tables'];
        $this->assertArrayHasKey('users', $tables);
        $this->assertArrayHasKey('links', $tables);

        // 18+ creator column (the original edited-after-applied drift class).
        $this->assertContains('adult_content_enabled', $tables['users']);

        // Unified link-settings columns read on the public biolink/SEO path.
        $this->assertContains('seo_title', $tables['links']);
        $this->assertContains('visibility', $tables['links']);
    }

    public function test_down_rollback_drops_are_not_applied(): void
    {
        $tables = SchemaManifest::build()['tables'];

        // `links.favicon` is added by its migration's up() and dropped only in
        // its down(); `links.splash_page_id` / `splash_enabled` likewise. Since
        // the manifest replays ONLY up(), the down() dropColumn calls must have
        // no effect and these columns must remain in the expected map.
        $this->assertContains('favicon', $tables['links']);
        $this->assertContains('splash_page_id', $tables['links']);
        $this->assertContains('splash_enabled', $tables['links']);
    }

    public function test_compute_report_shape_is_stable(): void
    {
        $report = ExpectedSchemaHealth::compute();

        $this->assertArrayHasKey('available', $report);
        $this->assertArrayHasKey('scanned', $report);
        $this->assertArrayHasKey('missing', $report);

        $this->assertTrue($report['available']);
        $this->assertIsInt($report['scanned']);
        $this->assertGreaterThan(0, $report['scanned']);
        $this->assertIsArray($report['missing']);

        // Every missing entry (if any) carries the documented shape.
        foreach ($report['missing'] as $entry) {
            $this->assertArrayHasKey('table', $entry);
            $this->assertArrayHasKey('table_missing', $entry);
            $this->assertArrayHasKey('columns', $entry);
            $this->assertIsBool($entry['table_missing']);
            $this->assertIsArray($entry['columns']);
        }
    }

    public function test_deliberately_missing_column_is_reported_under_missing(): void
    {
        // A column the manifest expects and that a healthy live DB has. Drop it
        // (rolled back with the test transaction) to simulate real drift.
        $table  = 'links';
        $column = 'seo_title';

        $before = $this->missingColumnsFor(ExpectedSchemaHealth::compute(), $table);
        $this->assertNotContains($column, $before, "$table.$column should be present before the drop");

        Schema::table($table, function (Blueprint $t) use ($column) {
            $t->dropColumn($column);
        });

        ExpectedSchemaHealth::flush();
        $report = ExpectedSchemaHealth::compute();

        $this->assertTrue($report['available']);
        $after = $this->missingColumnsFor($report, $table);
        $this->assertContains($column, $after, "dropped $table.$column should be reported as missing");

        // The whole-table flag should stay false — only a column went missing.
        foreach ($report['missing'] as $entry) {
            if ($entry['table'] === $table) {
                $this->assertFalse($entry['table_missing']);
            }
        }
    }

    public function test_repair_recreates_a_dropped_column_with_correct_type_nullable_default(): void
    {
        // `links.visibility` has a full repair spec in ExpectedSchemaHealth::EXPECTED
        // (string(20), NOT NULL, default 'public') — the most demanding case since
        // it must land a default on existing rows. Drop it (rolled back with the
        // test transaction) and confirm repair() puts it back faithfully.
        $table  = 'links';
        $column = 'visibility';

        $this->assertTrue(Schema::hasColumn($table, $column), "$table.$column should exist before the drop");

        Schema::table($table, function (Blueprint $t) use ($column) {
            $t->dropColumn($column);
        });
        $this->assertFalse(Schema::hasColumn($table, $column), "$table.$column should be gone after the drop");

        ExpectedSchemaHealth::flush();
        $result = ExpectedSchemaHealth::repair();

        // The column is reported as added under its table and nothing was flagged
        // as an unrepairable whole-missing table.
        $this->assertArrayHasKey($table, $result['added'], 'repair should report the table it touched');
        $this->assertContains($column, $result['added'][$table], "repair should report re-adding $table.$column");
        $this->assertEmpty($result['unrepairable'], 'a column-only drift must not be reported unrepairable');

        // The column is physically back in the live schema.
        $this->assertTrue(Schema::hasColumn($table, $column), "repair should re-create $table.$column");

        // Type / nullability / default match the EXPECTED spec (string(20), NOT
        // NULL, default 'public'). Verify directly against information_schema so a
        // wrong type or missing backfill default is caught, not just presence.
        $meta = collect(\DB::select(
            'select data_type, is_nullable, column_default, character_maximum_length '
            . 'from information_schema.columns where table_name = ? and column_name = ?',
            [$table, $column]
        ))->first();

        $this->assertNotNull($meta, "information_schema should describe the re-created $table.$column");
        $this->assertSame('character varying', $meta->data_type, 'visibility should be a string column');
        $this->assertSame(20, (int) $meta->character_maximum_length, 'visibility length should match the spec (20)');
        $this->assertSame('NO', $meta->is_nullable, 'visibility should be NOT NULL per the spec');
        $this->assertStringContainsString("'public'", (string) $meta->column_default, "visibility should default to 'public'");

        // The default backfilled onto the column means rows are readable again.
        $row = \DB::table($table)->first();
        if ($row !== null) {
            $this->assertSame('public', $row->visibility, 'existing rows should be backfilled with the default');
        }
    }

    public function test_repair_is_idempotent_on_a_second_run(): void
    {
        $table  = 'links';
        $column = 'visibility';

        Schema::table($table, function (Blueprint $t) use ($column) {
            $t->dropColumn($column);
        });

        ExpectedSchemaHealth::flush();
        $first = ExpectedSchemaHealth::repair();
        $this->assertContains($column, $first['added'][$table] ?? [], 'first run should add the column');

        // Second run: the column is already present, so repair must be a no-op —
        // nothing added, nothing unrepairable, and no exception from re-adding.
        ExpectedSchemaHealth::flush();
        $second = ExpectedSchemaHealth::repair();

        $this->assertSame([], $second['added'], 'second run should add nothing (idempotent)');
        $this->assertSame([], $second['unrepairable'], 'second run should report nothing unrepairable');
        $this->assertTrue(Schema::hasColumn($table, $column), 'column should remain present after the second run');
    }

    public function test_repair_reports_whole_missing_table_as_unrepairable(): void
    {
        // A whole-missing table can't be re-created in place (no full schema), so
        // repair() must surface it under `unrepairable` rather than silently
        // dropping it or throwing.
        $missing = [[
            'table'         => 'a_table_that_does_not_exist',
            'table_missing' => true,
            'columns'       => ['some_column'],
        ]];

        $result = ExpectedSchemaHealth::repair($missing);

        $this->assertSame([], $result['added']);
        $this->assertContains('a_table_that_does_not_exist', $result['unrepairable']);
    }

    public function test_build_reports_unavailable_when_migrations_cannot_be_replayed(): void
    {
        // Break the migration set so the replay can't even enumerate the files.
        // build() must degrade to available=false (carrying the error) rather
        // than throwing or returning an empty-but-"successful" manifest.
        $this->breakMigrator('migration files unreadable');

        $manifest = SchemaManifest::build();

        $this->assertFalse($manifest['available'], 'manifest must report unavailable when replay fails');
        $this->assertArrayHasKey('error', $manifest);
        $this->assertStringContainsString('migration files unreadable', $manifest['error']);
        $this->assertSame([], $manifest['tables'], 'no tables should be derived from a failed replay');
    }

    public function test_compute_propagates_unavailable_when_manifest_is_unavailable(): void
    {
        // When the manifest is blind, compute() must NOT treat the empty expected
        // set as "all healthy" — it has to propagate available=false so callers
        // (dashboard, readiness probe, hourly alert) degrade to "unknown".
        $this->breakMigrator('migration files unreadable');
        SchemaManifest::flush();
        ExpectedSchemaHealth::flush();

        $report = ExpectedSchemaHealth::compute();

        $this->assertFalse($report['available'], 'compute must not report healthy when the manifest is blind');
        $this->assertSame(0, $report['scanned']);
        $this->assertSame([], $report['missing']);
        $this->assertArrayHasKey('error', $report);
        $this->assertStringContainsString('migration files unreadable', $report['error']);
    }

    public function test_hourly_command_alerts_ops_admins_when_detector_is_blind(): void
    {
        Mail::fake();
        $admin = $this->makeOpsAdmin();

        $this->breakMigrator('migration files unreadable');
        SchemaManifest::flush();
        ExpectedSchemaHealth::flush();

        $this->artisan('db:check-expected-columns')->assertSuccessful();

        // Ops admin received a DISTINCT detector-blind alert — not a
        // "schema out of date" / missing-columns alert.
        $blind = UserNotification::where('user_id', $admin->id)
            ->where('type', 'expected_columns_detector_blind')
            ->first();
        $this->assertNotNull($blind, 'ops admin should get a detector-blind alert');
        $this->assertSame(
            0,
            UserNotification::where('user_id', $admin->id)->where('type', 'expected_columns_missing')->count(),
            'a blind detector must not masquerade as a missing-columns alert'
        );

        // The blind episode is tracked under its own state keys, separate from
        // the missing-columns episode.
        $state = AppSetting::get('expected_schema_health', []);
        $this->assertIsArray($state);
        $this->assertTrue($state['blind_alerting'] ?? false, 'blind episode should be marked open');
        $this->assertStringContainsString('migration files unreadable', $state['blind_error'] ?? '');
    }

    /**
     * Replace the bound migrator with one that throws while enumerating the
     * migration files, forcing the manifest replay (and thus the detector) to
     * report itself unavailable.
     */
    private function breakMigrator(string $message): void
    {
        $this->app->instance('migrator', \Mockery::mock(Migrator::class, function ($mock) use ($message) {
            $mock->shouldReceive('paths')->andThrow(new \RuntimeException($message));
            $mock->shouldReceive('getMigrationFiles')->andThrow(new \RuntimeException($message));
        }));
    }

    /**
     * Create a verified user holding `user.ops_alerts.receive` (via the seeded
     * user-admin role) so the ops-alert fan-out targets them.
     */
    private function makeOpsAdmin(): User
    {
        $roleId = DB::table('roles')->where('slug', 'user-admin')->where('guard', 'web')->value('id');
        $this->assertNotNull($roleId, 'user-admin role must be seeded');

        $user = User::create([
            'name'              => 'Ops ' . Str::random(4),
            'email'             => 'ops' . Str::random(8) . '@ex.com',
            'password'          => Hash::make('x'),
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);
        $user->roles()->syncWithoutDetaching([(int) $roleId]);
        if (method_exists($user, 'flushPermissionCache')) {
            $user->flushPermissionCache();
        }

        return $user->fresh();
    }

    /**
     * Collect the reported-missing columns for a given table from a compute()
     * report.
     *
     * @param array{missing:array<int,array{table:string,table_missing:bool,columns:array<int,string>}>} $report
     * @return array<int,string>
     */
    private function missingColumnsFor(array $report, string $table): array
    {
        foreach ($report['missing'] as $entry) {
            if ($entry['table'] === $table) {
                return $entry['columns'];
            }
        }

        return [];
    }
}
