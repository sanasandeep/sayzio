<?php

namespace Tests\Feature;

use App\Modules\Common\Support\ExpectedSchemaHealth;
use App\Modules\Common\Support\SchemaManifest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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
