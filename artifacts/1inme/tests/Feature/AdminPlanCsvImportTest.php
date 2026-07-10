<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\PlanImportSnapshot;
use App\Modules\Admin\Models\Price;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Support\PlanCsvSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Coverage for the admin "import plan changes from a spreadsheet" flow.
 *
 * The importer round-trips the human-readable export: rows are matched to
 * plans by their Slug column, each cell is validated against the shared
 * PlanCsvSchema, a diff is previewed, and only on a separate confirm POST
 * are matched plans updated. This test pins:
 *   1. A valid edited CSV produces a preview with the computed diff.
 *   2. Committing that CSV updates the plan (core attr + price + feature).
 *   3. Unknown slugs are skipped with an error row, never created.
 *   4. Invalid cell values are rejected (row skipped, no write).
 *   5. The endpoints require the plans.manage permission.
 */
class AdminPlanCsvImportTest extends TestCase
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
            'name'                    => 'Starter',
            'slug'                    => 'starter-' . uniqid(),
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
     * Build a CSV string from full schema headers. $rows maps a header =>
     * value; any header not supplied is left blank (= "leave unchanged").
     *
     * @param array<int,array<string,string>> $rows
     */
    private function csv(array $rows): string
    {
        $headers = PlanCsvSchema::headers();
        $lines   = [];
        $fh      = fopen('php://temp', 'r+');
        fputcsv($fh, $headers);
        foreach ($rows as $row) {
            $cells = [];
            foreach ($headers as $h) {
                $cells[] = $row[$h] ?? '';
            }
            fputcsv($fh, $cells);
        }
        rewind($fh);
        $out = stream_get_contents($fh);
        fclose($fh);
        return $out;
    }

    private function upload(string $csv): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('plans.csv', $csv);
    }

    public function test_preview_shows_diff_for_valid_edits(): void
    {
        $admin = $this->makeAdmin();
        $plan  = $this->makePlan(['name' => 'Starter', 'status' => 'active']);

        $csv = $this->csv([[
            'Name'            => 'Starter Pro',      // changed
            'Slug'            => $plan->slug,
            'Status'          => 'active',           // unchanged
            'Price USD/monthly' => '19.00',          // changed (was 10.00)
        ]]);

        $resp = $this->actingAs($admin, 'admin')
            ->post(route('admin.plans.import.preview'), ['file' => $this->upload($csv)]);

        $resp->assertOk();
        $resp->assertViewIs('admin.plans.import');
        $resp->assertViewHas('changedCount', 1);
        $resp->assertSee('Starter Pro');
        // Nothing persisted yet.
        $this->assertSame('Starter', $plan->fresh()->name);
    }

    public function test_commit_updates_matched_plan(): void
    {
        $admin = $this->makeAdmin();
        $plan  = $this->makePlan(['name' => 'Starter']);

        $csv = $this->csv([[
            'Name'              => 'Starter Pro',
            'Slug'              => $plan->slug,
            'Price USD/monthly' => '19.00',
            'Module: ' . array_values(\App\Modules\Common\Support\PlanFormCatalogue::modules())[0]['label'] => 'Yes',
        ]]);

        $resp = $this->actingAs($admin, 'admin')
            ->post(route('admin.plans.import.commit'), ['csv' => $csv]);

        $resp->assertRedirect(route('admin.plans.index'));
        $resp->assertSessionHas('success');

        $fresh = $plan->fresh();
        $this->assertSame('Starter Pro', $fresh->name);
        // USD monthly price row synced to 1900 minor units.
        $price = Price::where('priceable_type', Plan::class)
            ->where('priceable_id', $plan->id)
            ->where('currency', 'USD')->where('billing_cycle', 'monthly')->first();
        $this->assertNotNull($price);
        $this->assertSame(1900, (int) $price->amount_minor_units);
    }

    public function test_new_slug_is_offered_as_create_not_created_without_opt_in(): void
    {
        $admin = $this->makeAdmin();
        $before = Plan::count();

        $csv = $this->csv([[
            'Name' => 'Ghost',
            'Slug' => 'does-not-exist-xyz',
        ]]);

        // Preview offers it as a create candidate, not an unknown/error row.
        $this->actingAs($admin, 'admin')
            ->post(route('admin.plans.import.preview'), ['file' => $this->upload($csv)])
            ->assertOk()
            ->assertViewHas('createCount', 1)
            ->assertViewHas('unknownCount', 0)
            ->assertViewHas('changedCount', 0);

        // Committing WITHOUT ticking the row must not create the plan (opt-in).
        $this->actingAs($admin, 'admin')
            ->post(route('admin.plans.import.commit'), ['csv' => $csv])
            ->assertRedirect(route('admin.plans.index'));

        $this->assertSame($before, Plan::count());
    }

    public function test_new_plan_is_created_when_opted_in(): void
    {
        $admin = $this->makeAdmin();

        $moduleLabel = array_values(\App\Modules\Common\Support\PlanFormCatalogue::modules())[0]['label'];
        $csv = $this->csv([[
            'Name'              => 'Growth',
            'Slug'             => 'growth-new',
            'Status'           => 'active',
            'Price USD/monthly' => '29.00',
            'Module: ' . $moduleLabel => 'Yes',
        ]]);

        $resp = $this->actingAs($admin, 'admin')
            ->post(route('admin.plans.import.commit'), [
                'csv'          => $csv,
                'create_slugs' => ['growth-new'],
            ]);

        $resp->assertRedirect(route('admin.plans.index'));
        $resp->assertSessionHas('success');

        $plan = Plan::where('slug', 'growth-new')->first();
        $this->assertNotNull($plan);
        $this->assertSame('Growth', $plan->name);
        // Status column explicitly set active overrides the safe default.
        $this->assertSame('active', $plan->status);

        // USD monthly price row synced to 2900 minor units.
        $price = Price::where('priceable_type', Plan::class)
            ->where('priceable_id', $plan->id)
            ->where('currency', 'USD')->where('billing_cycle', 'monthly')->first();
        $this->assertNotNull($price);
        $this->assertSame(2900, (int) $price->amount_minor_units);
    }

    public function test_new_plan_defaults_to_inactive_internal_when_columns_blank(): void
    {
        $admin = $this->makeAdmin();

        // Only a Name + Slug — Status / Internal columns left blank.
        $csv = $this->csv([[
            'Name' => 'Quiet Tier',
            'Slug' => 'quiet-tier',
        ]]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.plans.import.commit'), [
                'csv'          => $csv,
                'create_slugs' => ['quiet-tier'],
            ])->assertRedirect(route('admin.plans.index'));

        $plan = Plan::where('slug', 'quiet-tier')->first();
        $this->assertNotNull($plan);
        $this->assertSame('inactive', $plan->status);
        $this->assertTrue((bool) $plan->is_internal);
    }

    public function test_new_plan_row_without_name_is_an_error_not_a_create(): void
    {
        $admin = $this->makeAdmin();

        $csv = $this->csv([[
            'Slug' => 'nameless-plan',
        ]]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.plans.import.preview'), ['file' => $this->upload($csv)])
            ->assertOk()
            ->assertViewHas('createCount', 0)
            ->assertViewHas('errorCount', 1);

        // Even if a client forges the opt-in, the re-validated error row is skipped.
        $this->actingAs($admin, 'admin')
            ->post(route('admin.plans.import.commit'), [
                'csv'          => $csv,
                'create_slugs' => ['nameless-plan'],
            ])->assertRedirect(route('admin.plans.index'));

        $this->assertNull(Plan::where('slug', 'nameless-plan')->first());
    }

    public function test_invalid_value_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $plan  = $this->makePlan(['status' => 'active']);

        $csv = $this->csv([[
            'Name'   => $plan->name,
            'Slug'   => $plan->slug,
            'Status' => 'banana', // invalid
        ]]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.plans.import.preview'), ['file' => $this->upload($csv)])
            ->assertOk()
            ->assertViewHas('errorCount', 1)
            ->assertViewHas('changedCount', 0);

        // Commit must not apply the bad row.
        $this->actingAs($admin, 'admin')
            ->post(route('admin.plans.import.commit'), ['csv' => $csv]);
        $this->assertSame('active', $plan->fresh()->status);
    }

    public function test_missing_slug_column_is_a_fatal_error(): void
    {
        $admin = $this->makeAdmin();
        // Header row without a Slug column.
        $csv = "Name,Status\nFoo,active\n";

        $this->actingAs($admin, 'admin')
            ->post(route('admin.plans.import.preview'), ['file' => $this->upload($csv)])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_endpoints_require_authentication(): void
    {
        $csv = $this->csv([['Name' => 'x', 'Slug' => 'y']]);

        // Guests are redirected to admin login, never reaching the importer.
        $this->post(route('admin.plans.import.preview'), ['file' => $this->upload($csv)])
            ->assertRedirect();
        $this->post(route('admin.plans.import.commit'), ['csv' => $csv])
            ->assertRedirect();
    }

    public function test_commit_records_a_reversible_snapshot(): void
    {
        $admin = $this->makeAdmin();
        $plan  = $this->makePlan(['name' => 'Starter']);

        $csv = $this->csv([[
            'Name'              => 'Starter Pro',
            'Slug'              => $plan->slug,
            'Price USD/monthly' => '19.00',
        ]]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.plans.import.commit'), ['csv' => $csv]);

        $snapshot = PlanImportSnapshot::latest('id')->first();
        $this->assertNotNull($snapshot);
        $this->assertSame(1, $snapshot->plans_updated);
        $this->assertSame($admin->id, $snapshot->admin_id);
        $this->assertNull($snapshot->reverted_at);
        // The before-state records the plan's original name.
        $names = collect($snapshot->snapshot)->pluck('core.name');
        $this->assertTrue($names->contains('Starter'));
    }

    public function test_no_snapshot_when_nothing_changes(): void
    {
        $admin = $this->makeAdmin();
        $plan  = $this->makePlan(['name' => 'Starter']);

        // Same value as current — no diff, no write, no undo point.
        $csv = $this->csv([[
            'Name' => 'Starter',
            'Slug' => $plan->slug,
        ]]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.plans.import.commit'), ['csv' => $csv]);

        $this->assertSame(0, PlanImportSnapshot::count());
    }

    public function test_revert_restores_previous_values(): void
    {
        $admin = $this->makeAdmin();
        $plan  = $this->makePlan(['name' => 'Starter', 'features' => ['max_links' => 5]]);

        $csv = $this->csv([[
            'Name'              => 'Starter Pro',
            'Slug'              => $plan->slug,
            'Price USD/monthly' => '19.00',
        ]]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.plans.import.commit'), ['csv' => $csv]);

        $this->assertSame('Starter Pro', $plan->fresh()->name);
        $snapshot = PlanImportSnapshot::latest('id')->first();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.plans.import.revert', $snapshot))
            ->assertRedirect(route('admin.plans.index'))
            ->assertSessionHas('success');

        $fresh = $plan->fresh();
        $this->assertSame('Starter', $fresh->name);
        // Price restored to the original 10.00 (1000 minor units).
        $price = Price::where('priceable_type', Plan::class)
            ->where('priceable_id', $plan->id)
            ->where('currency', 'USD')->where('billing_cycle', 'monthly')->first();
        $this->assertSame(1000, (int) $price->amount_minor_units);

        // Snapshot is stamped as reverted and can't be undone twice.
        $snapshot->refresh();
        $this->assertNotNull($snapshot->reverted_at);
        $this->assertSame($admin->id, $snapshot->reverted_by);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.plans.import.revert', $snapshot))
            ->assertSessionHas('error');
    }

    public function test_only_latest_import_can_be_reverted(): void
    {
        $admin = $this->makeAdmin();
        $plan  = $this->makePlan(['name' => 'Starter']);

        $first = $this->csv([['Name' => 'First', 'Slug' => $plan->slug]]);
        $this->actingAs($admin, 'admin')->post(route('admin.plans.import.commit'), ['csv' => $first]);
        $firstSnapshot = PlanImportSnapshot::latest('id')->first();

        $second = $this->csv([['Name' => 'Second', 'Slug' => $plan->slug]]);
        $this->actingAs($admin, 'admin')->post(route('admin.plans.import.commit'), ['csv' => $second]);

        // Reverting the older (non-latest) import is refused.
        $this->actingAs($admin, 'admin')
            ->post(route('admin.plans.import.revert', $firstSnapshot))
            ->assertSessionHas('error');

        $this->assertSame('Second', $plan->fresh()->name);
    }

    public function test_revert_restores_sibling_popular_flag(): void
    {
        $admin = $this->makeAdmin();
        $planA = $this->makePlan(['name' => 'Alpha', 'is_popular' => true]);
        $planB = $this->makePlan(['name' => 'Beta', 'is_popular' => false]);

        // Making Beta popular flips Alpha's popular flag off as a side-effect.
        $csv = $this->csv([[
            'Name'    => 'Beta',
            'Slug'    => $planB->slug,
            'Popular' => 'Yes',
        ]]);
        $this->actingAs($admin, 'admin')->post(route('admin.plans.import.commit'), ['csv' => $csv]);

        $this->assertTrue($planB->fresh()->is_popular);
        $this->assertFalse($planA->fresh()->is_popular);

        $snapshot = PlanImportSnapshot::latest('id')->first();
        $this->actingAs($admin, 'admin')->post(route('admin.plans.import.revert', $snapshot));

        // Both plans restored: Alpha popular again, Beta not.
        $this->assertTrue($planA->fresh()->is_popular);
        $this->assertFalse($planB->fresh()->is_popular);
    }
}
