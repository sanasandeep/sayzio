<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Support\PlanFormCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Guards the admin plan CSV export (GET /admin/plans/export).
 *
 * The export builds its column list dynamically from PlanFormCatalogue, so a
 * new catalogue key added without a matching column would silently ship a
 * broken file. These tests pin the contract:
 *   1. The endpoint downloads as CSV (Content-Type + Content-Disposition).
 *   2. The file has a header row and at least one data row.
 *   3. Every quantity-limit and feature-flag key in the catalogue maps to a
 *      non-empty column header — the test fails the moment a key is added
 *      without its corresponding export column.
 */
class AdminPlanExportTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );
        return Admin::create([
            'name'     => 'Test Admin',
            'email'    => 'admin' . uniqid() . '@example.com',
            'password' => Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    private function makePlan(array $attrs = []): Plan
    {
        return Plan::create(array_merge([
            'name'                    => 'Plan ' . uniqid(),
            'slug'                    => 'plan-' . uniqid(),
            'description'             => 'x',
            'monthly_price'           => 10,
            'annual_price'            => 100,
            'monthly_price_secondary' => 800,
            'annual_price_secondary'  => 8000,
            'trial_days'              => 0,
            'grace_days'              => 0,
            'refund_window_days'      => 0,
            'features'                => ['max_links' => 5],
            'status'                  => 'active',
            'sort_order'              => 0,
        ], $attrs));
    }

    /**
     * Parse the streamed CSV export into [headerRow, dataRows], stripping the
     * leading UTF-8 BOM the controller writes for Excel.
     *
     * @return array{0: array<int,string>, 1: array<int,array<int,string>>}
     */
    private function exportRows(): array
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.plans.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString(
            'attachment',
            (string) $response->headers->get('Content-Disposition')
        );
        $this->assertStringContainsString(
            '.csv',
            (string) $response->headers->get('Content-Disposition')
        );

        $content = $response->streamedContent();
        // Strip the UTF-8 BOM so the first header cell parses cleanly.
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $lines = array_values(array_filter(
            preg_split('/\r\n|\r|\n/', trim($content)),
            fn ($l) => $l !== ''
        ));

        $rows = array_map(fn ($line) => str_getcsv($line), $lines);

        $header = array_shift($rows) ?? [];

        return [$header, $rows];
    }

    public function test_export_downloads_as_csv_with_header_and_data_rows(): void
    {
        $this->makePlan();
        $this->makePlan(['is_internal' => true]);

        [$header, $rows] = $this->exportRows();

        $this->assertNotEmpty($header, 'Export header row should not be empty.');
        $this->assertNotEmpty($rows, 'Export should contain at least one data row.');

        // No header cell may be blank — a missing label would ship an
        // unlabelled column that no importer could read.
        foreach ($header as $i => $cell) {
            $this->assertNotSame(
                '',
                trim((string) $cell),
                "Export header column #{$i} is blank."
            );
        }

        // Every data row must be as wide as the header (no ragged rows).
        foreach ($rows as $r => $row) {
            $this->assertCount(
                count($header),
                $row,
                "Data row #{$r} column count does not match the header."
            );
        }
    }

    public function test_every_quantity_limit_key_has_a_column(): void
    {
        $this->makePlan();

        [$header] = $this->exportRows();

        foreach (PlanFormCatalogue::quantityLimits() as $q) {
            $this->assertContains(
                $q['label'],
                $header,
                "Quantity-limit key '{$q['key']}' is missing its export column '{$q['label']}'."
            );
        }
    }

    public function test_every_feature_flag_key_has_a_column(): void
    {
        $this->makePlan();

        [$header] = $this->exportRows();

        foreach (PlanFormCatalogue::featureFlags() as $flag) {
            $label = PlanFormCatalogue::labelFor($flag['key']);

            $this->assertNotSame(
                '',
                trim($label),
                "Feature-flag key '{$flag['key']}' resolves to a blank label."
            );
            $this->assertContains(
                $label,
                $header,
                "Feature-flag key '{$flag['key']}' is missing its export column '{$label}'."
            );
        }
    }
}
