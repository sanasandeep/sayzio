<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Addon;
use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\Price;
use App\Modules\Admin\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regression coverage for the admin plan/addon price editor save path.
 *
 * The editor submits all four (USD/INR × monthly/annual) prices in MINOR
 * units and persists them in a single batched upsert via
 * PricingResolver::upsertManyFromMinor(). This test pins three guarantees
 * for both the Plan and Addon editors:
 *   1. All four rows land in `prices` with the right currency/cycle/amount.
 *   2. Re-saving with new amounts updates the SAME four rows in place
 *      (idempotent — no duplicate rows).
 *   3. Editing one priceable never touches another priceable's rows.
 *
 * Dual-source-of-truth guard: on every save the controllers ALSO write the
 * legacy major-unit decimal columns (`monthly_price`, `annual_price`,
 * `*_secondary`) on the Plan/Addon model. The `prices` table is the
 * authoritative read source for the storefront, but those legacy columns
 * are still read by other paths (e.g. ProrationCalculator, PlanRecommender,
 * AiEngineSettings, admin/user views), so they must NOT be allowed to drift
 * from the `prices` table. The legacy-sync tests below assert that after a
 * save the two stay in lockstep — legacy major units == prices minor / 100
 * for each (currency, cycle) — so a future change to one write path that
 * forgets the other fails loudly instead of silently diverging.
 */
class AdminPriceEditorSaveTest extends TestCase
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
            'name'                    => 'Existing Plan ' . uniqid(),
            'slug'                    => 'existing-plan-' . uniqid(),
            'description'             => 'x',
            'monthly_price'           => 0,
            'annual_price'            => 0,
            'monthly_price_secondary' => 0,
            'annual_price_secondary'  => 0,
            'trial_days'              => 0,
            'grace_days'              => 0,
            'refund_window_days'      => 0,
            'features'                => [],
            'status'                  => 'active',
            'sort_order'              => 0,
        ], $attrs));
    }

    private function makeAddon(array $attrs = []): Addon
    {
        return Addon::create(array_merge([
            'name'                    => 'Existing Addon ' . uniqid(),
            'slug'                    => 'existing-addon-' . uniqid(),
            'description'             => 'x',
            'type'                    => 'recurring',
            'monthly_price'           => 0,
            'annual_price'            => 0,
            'monthly_price_secondary' => 0,
            'annual_price_secondary'  => 0,
            'features'                => [],
            'status'                  => 'active',
            'sort_order'              => 0,
        ], $attrs));
    }

    /** Minimal valid payload for the plan update form (MINOR units). */
    private function planPayload(array $prices): array
    {
        return array_merge([
            'name'                => 'Pro',
            'description'         => 'Pro plan',
            'trial_days'          => 0,
            'grace_days'          => 0,
            'refund_window_days'  => 0,
            'status'              => 'active',
            'sort_order'          => 0,
        ], $prices);
    }

    /** Assert the four expected price rows exist with the given minor amounts. */
    private function assertFourPrices($priceable, array $expected): void
    {
        $type = get_class($priceable);
        $id   = $priceable->getKey();

        // Exactly four rows for this priceable — no duplicates leaked.
        $this->assertSame(4, Price::where('priceable_type', $type)
            ->where('priceable_id', $id)->count());

        foreach ($expected as [$currency, $cycle, $minor]) {
            $this->assertDatabaseHas('prices', [
                'priceable_type'     => $type,
                'priceable_id'       => $id,
                'currency'           => $currency,
                'billing_cycle'      => $cycle,
                'amount_minor_units' => $minor,
                'is_active'          => true,
            ]);
        }
    }

    /**
     * Assert the legacy major-unit decimal columns on a freshly-reloaded
     * priceable equal the authoritative `prices` rows (minor units / 100)
     * for each (currency, cycle). This is the dual-source-of-truth invariant.
     */
    private function assertLegacyColumnsMatchPrices($priceable): void
    {
        $priceable->refresh();
        $type = get_class($priceable);
        $id   = $priceable->getKey();

        // legacy column => [currency, cycle]
        $map = [
            'monthly_price'           => ['USD', 'monthly'],
            'annual_price'            => ['USD', 'annual'],
            'monthly_price_secondary' => ['INR', 'monthly'],
            'annual_price_secondary'  => ['INR', 'annual'],
        ];

        foreach ($map as $column => [$currency, $cycle]) {
            $row = Price::where('priceable_type', $type)
                ->where('priceable_id', $id)
                ->where('currency', $currency)
                ->where('billing_cycle', $cycle)
                ->first();
            $this->assertNotNull(
                $row,
                "Missing prices row for {$currency}/{$cycle} on {$type}#{$id}."
            );

            $legacyMinor = (int) round(((float) $priceable->{$column}) * 100);
            $this->assertSame(
                (int) $row->amount_minor_units,
                $legacyMinor,
                "Legacy column {$column} drifted from prices {$currency}/{$cycle}."
            );
        }
    }

    public function test_plan_editor_keeps_legacy_columns_in_sync_with_prices(): void
    {
        $plan  = $this->makePlan();
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')
            ->put(route('admin.plans.update', $plan), $this->planPayload([
                'monthly_price'           => 1999,
                'annual_price'            => 19990,
                'monthly_price_secondary' => 149900,
                'annual_price_secondary'  => 1499000,
            ]))->assertSessionHasNoErrors();

        $this->assertLegacyColumnsMatchPrices($plan);

        // Re-saving with new amounts keeps them in lockstep too.
        $this->actingAs($admin, 'admin')
            ->put(route('admin.plans.update', $plan), $this->planPayload([
                'monthly_price'           => 5500,
                'annual_price'            => 6600,
                'monthly_price_secondary' => 7700,
                'annual_price_secondary'  => 8800,
            ]))->assertSessionHasNoErrors();

        $this->assertLegacyColumnsMatchPrices($plan);
    }

    public function test_addon_editor_keeps_legacy_columns_in_sync_with_prices(): void
    {
        $addon = $this->makeAddon();
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')
            ->put(route('admin.addons.update', $addon), [
                'name'                    => 'Boost',
                'type'                    => 'one_time',
                'monthly_price'           => 250,
                'annual_price'            => 2500,
                'monthly_price_secondary' => 19900,
                'annual_price_secondary'  => 199000,
                'status'                  => 'active',
                'sort_order'              => 0,
            ])->assertSessionHasNoErrors();

        $this->assertLegacyColumnsMatchPrices($addon);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.addons.update', $addon), [
                'name'                    => 'Boost',
                'type'                    => 'recurring',
                'monthly_price'           => 1500,
                'annual_price'            => 1600,
                'monthly_price_secondary' => 1700,
                'annual_price_secondary'  => 1800,
                'status'                  => 'active',
                'sort_order'              => 0,
            ])->assertSessionHasNoErrors();

        $this->assertLegacyColumnsMatchPrices($addon);
    }

    public function test_plan_editor_saves_all_four_price_rows(): void
    {
        $plan = $this->makePlan();

        $resp = $this->actingAs($this->makeAdmin(), 'admin')
            ->put(route('admin.plans.update', $plan), $this->planPayload([
                'monthly_price'           => 1999,  // $19.99 USD/month
                'annual_price'            => 19990, // $199.90 USD/year
                'monthly_price_secondary' => 149900, // ₹1499 INR/month
                'annual_price_secondary'  => 1499000, // ₹14990 INR/year
            ]));

        $resp->assertRedirect(route('admin.plans.index'));
        $resp->assertSessionHasNoErrors();

        $this->assertFourPrices($plan, [
            ['USD', 'monthly', 1999],
            ['USD', 'annual',  19990],
            ['INR', 'monthly', 149900],
            ['INR', 'annual',  1499000],
        ]);
    }

    public function test_plan_editor_resave_updates_rows_in_place(): void
    {
        $plan  = $this->makePlan();
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')
            ->put(route('admin.plans.update', $plan), $this->planPayload([
                'monthly_price'           => 1000,
                'annual_price'            => 2000,
                'monthly_price_secondary' => 3000,
                'annual_price_secondary'  => 4000,
            ]))->assertSessionHasNoErrors();

        // Second save with new amounts must overwrite the same four rows.
        $this->actingAs($admin, 'admin')
            ->put(route('admin.plans.update', $plan), $this->planPayload([
                'monthly_price'           => 5500,
                'annual_price'            => 6600,
                'monthly_price_secondary' => 7700,
                'annual_price_secondary'  => 8800,
            ]))->assertSessionHasNoErrors();

        $this->assertFourPrices($plan, [
            ['USD', 'monthly', 5500],
            ['USD', 'annual',  6600],
            ['INR', 'monthly', 7700],
            ['INR', 'annual',  8800],
        ]);
    }

    public function test_plan_editor_does_not_touch_another_plans_prices(): void
    {
        $other = $this->makePlan();
        // Seed the other plan with known prices via its own save.
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'admin')
            ->put(route('admin.plans.update', $other), $this->planPayload([
                'monthly_price'           => 111,
                'annual_price'            => 222,
                'monthly_price_secondary' => 333,
                'annual_price_secondary'  => 444,
            ]))->assertSessionHasNoErrors();

        $target = $this->makePlan();
        $this->actingAs($admin, 'admin')
            ->put(route('admin.plans.update', $target), $this->planPayload([
                'monthly_price'           => 9000,
                'annual_price'            => 9100,
                'monthly_price_secondary' => 9200,
                'annual_price_secondary'  => 9300,
            ]))->assertSessionHasNoErrors();

        // The other plan's rows are untouched.
        $this->assertFourPrices($other, [
            ['USD', 'monthly', 111],
            ['USD', 'annual',  222],
            ['INR', 'monthly', 333],
            ['INR', 'annual',  444],
        ]);
        $this->assertFourPrices($target, [
            ['USD', 'monthly', 9000],
            ['USD', 'annual',  9100],
            ['INR', 'monthly', 9200],
            ['INR', 'annual',  9300],
        ]);
    }

    public function test_plan_editor_does_not_touch_addon_prices_of_same_id(): void
    {
        // A Plan and an Addon can share the same integer id; the
        // polymorphic key must keep their price rows separate.
        $admin = $this->makeAdmin();

        $addon = $this->makeAddon();
        $this->actingAs($admin, 'admin')
            ->put(route('admin.addons.update', $addon), [
                'name'                    => 'Addon',
                'type'                    => 'recurring',
                'monthly_price'           => 500,
                'annual_price'            => 600,
                'monthly_price_secondary' => 700,
                'annual_price_secondary'  => 800,
                'status'                  => 'active',
                'sort_order'              => 0,
            ])->assertSessionHasNoErrors();

        $plan = $this->makePlan();
        $this->actingAs($admin, 'admin')
            ->put(route('admin.plans.update', $plan), $this->planPayload([
                'monthly_price'           => 1111,
                'annual_price'            => 2222,
                'monthly_price_secondary' => 3333,
                'annual_price_secondary'  => 4444,
            ]))->assertSessionHasNoErrors();

        // The addon's price rows are independent of the plan's.
        $this->assertFourPrices($addon, [
            ['USD', 'monthly', 500],
            ['USD', 'annual',  600],
            ['INR', 'monthly', 700],
            ['INR', 'annual',  800],
        ]);
    }

    public function test_addon_editor_saves_all_four_price_rows(): void
    {
        $addon = $this->makeAddon();

        $resp = $this->actingAs($this->makeAdmin(), 'admin')
            ->put(route('admin.addons.update', $addon), [
                'name'                    => 'Boost',
                'type'                    => 'one_time',
                'monthly_price'           => 250,
                'annual_price'            => 2500,
                'monthly_price_secondary' => 19900,
                'annual_price_secondary'  => 199000,
                'status'                  => 'active',
                'sort_order'              => 0,
            ]);

        $resp->assertRedirect(route('admin.addons.index'));
        $resp->assertSessionHasNoErrors();

        $this->assertFourPrices($addon, [
            ['USD', 'monthly', 250],
            ['USD', 'annual',  2500],
            ['INR', 'monthly', 19900],
            ['INR', 'annual',  199000],
        ]);
    }

    public function test_addon_editor_resave_updates_rows_in_place(): void
    {
        $addon = $this->makeAddon();
        $admin = $this->makeAdmin();

        $payload = function (array $prices): array {
            return array_merge([
                'name'       => 'Boost',
                'type'       => 'recurring',
                'status'     => 'active',
                'sort_order' => 0,
            ], $prices);
        };

        $this->actingAs($admin, 'admin')
            ->put(route('admin.addons.update', $addon), $payload([
                'monthly_price'           => 100,
                'annual_price'            => 200,
                'monthly_price_secondary' => 300,
                'annual_price_secondary'  => 400,
            ]))->assertSessionHasNoErrors();

        $this->actingAs($admin, 'admin')
            ->put(route('admin.addons.update', $addon), $payload([
                'monthly_price'           => 1500,
                'annual_price'            => 1600,
                'monthly_price_secondary' => 1700,
                'annual_price_secondary'  => 1800,
            ]))->assertSessionHasNoErrors();

        $this->assertFourPrices($addon, [
            ['USD', 'monthly', 1500],
            ['USD', 'annual',  1600],
            ['INR', 'monthly', 1700],
            ['INR', 'annual',  1800],
        ]);
    }

    // -----------------------------------------------------------------
    // Invalid-input coverage: the save path accepts MINOR units directly,
    // so a broken editor posting negatives, decimals/garbage, or dropping
    // one of the four USD/INR × monthly/annual cells must be rejected by
    // validation BEFORE anything is written — no partial writes to either
    // the `prices` table or the legacy decimal columns.
    // -----------------------------------------------------------------

    /** All four price fields for a fresh valid baseline save. */
    private const VALID_PRICES = [
        'monthly_price'           => 1000,
        'annual_price'            => 2000,
        'monthly_price_secondary' => 3000,
        'annual_price_secondary'  => 4000,
    ];

    /** Assert a plan has zero prices rows and untouched legacy columns. */
    private function assertNothingWritten(Plan $plan): void
    {
        $this->assertSame(0, Price::where('priceable_type', Plan::class)
            ->where('priceable_id', $plan->id)->count());

        $plan->refresh();
        foreach (['monthly_price', 'annual_price', 'monthly_price_secondary', 'annual_price_secondary'] as $col) {
            $this->assertSame(0.0, (float) $plan->{$col}, "Legacy column {$col} was written despite validation failure.");
        }
    }

    public function test_plan_editor_rejects_negative_amounts_with_no_writes(): void
    {
        $plan  = $this->makePlan();
        $admin = $this->makeAdmin();

        foreach (array_keys(self::VALID_PRICES) as $field) {
            $prices = self::VALID_PRICES;
            $prices[$field] = -1;

            $this->actingAs($admin, 'admin')
                ->from(route('admin.plans.edit', $plan))
                ->put(route('admin.plans.update', $plan), $this->planPayload($prices))
                ->assertRedirect(route('admin.plans.edit', $plan))
                ->assertSessionHasErrors($field);

            $this->assertNothingWritten($plan);
        }
    }

    public function test_plan_editor_rejects_non_integer_amounts_with_no_writes(): void
    {
        $plan  = $this->makePlan();
        $admin = $this->makeAdmin();

        // Both decimal-major-unit input (a broken editor posting dollars
        // instead of cents) and outright garbage must fail the integer rule.
        foreach (['19.99', 'abc'] as $bad) {
            $prices = self::VALID_PRICES;
            $prices['monthly_price'] = $bad;

            $this->actingAs($admin, 'admin')
                ->put(route('admin.plans.update', $plan), $this->planPayload($prices))
                ->assertSessionHasErrors('monthly_price');

            $this->assertNothingWritten($plan);
        }
    }

    public function test_plan_editor_rejects_missing_price_cells_with_no_writes(): void
    {
        $plan  = $this->makePlan();
        $admin = $this->makeAdmin();

        foreach (array_keys(self::VALID_PRICES) as $missing) {
            $prices = self::VALID_PRICES;
            unset($prices[$missing]);

            $this->actingAs($admin, 'admin')
                ->put(route('admin.plans.update', $plan), $this->planPayload($prices))
                ->assertSessionHasErrors($missing);

            $this->assertNothingWritten($plan);
        }
    }

    public function test_plan_editor_invalid_save_leaves_existing_prices_untouched(): void
    {
        $plan  = $this->makePlan();
        $admin = $this->makeAdmin();

        // Seed known-good prices via a real save.
        $this->actingAs($admin, 'admin')
            ->put(route('admin.plans.update', $plan), $this->planPayload(self::VALID_PRICES))
            ->assertSessionHasNoErrors();

        // A mixed payload (three valid cells + one invalid) must not
        // partially apply the valid cells.
        $this->actingAs($admin, 'admin')
            ->put(route('admin.plans.update', $plan), $this->planPayload([
                'monthly_price'           => 9999,
                'annual_price'            => 8888,
                'monthly_price_secondary' => 7777,
                'annual_price_secondary'  => -5,
            ]))->assertSessionHasErrors('annual_price_secondary');

        $this->assertFourPrices($plan, [
            ['USD', 'monthly', 1000],
            ['USD', 'annual',  2000],
            ['INR', 'monthly', 3000],
            ['INR', 'annual',  4000],
        ]);
        $this->assertLegacyColumnsMatchPrices($plan);
    }

    public function test_addon_editor_rejects_invalid_amounts_with_no_writes(): void
    {
        $addon = $this->makeAddon();
        $admin = $this->makeAdmin();

        $base = [
            'name'       => 'Boost',
            'type'       => 'recurring',
            'status'     => 'active',
            'sort_order' => 0,
        ];

        foreach ([
            ['monthly_price', -10],
            ['annual_price', '12.34'],
            ['monthly_price_secondary', null], // missing cell
        ] as [$field, $bad]) {
            $prices = self::VALID_PRICES;
            if ($bad === null) {
                unset($prices[$field]);
            } else {
                $prices[$field] = $bad;
            }

            $this->actingAs($admin, 'admin')
                ->put(route('admin.addons.update', $addon), array_merge($base, $prices))
                ->assertSessionHasErrors($field);

            $this->assertSame(0, Price::where('priceable_type', Addon::class)
                ->where('priceable_id', $addon->id)->count());

            $addon->refresh();
            foreach (['monthly_price', 'annual_price', 'monthly_price_secondary', 'annual_price_secondary'] as $col) {
                $this->assertSame(0.0, (float) $addon->{$col}, "Addon legacy column {$col} was written despite validation failure.");
            }
        }
    }

    public function test_addon_editor_does_not_touch_another_addons_prices(): void
    {
        $admin = $this->makeAdmin();

        $other = $this->makeAddon();
        $this->actingAs($admin, 'admin')
            ->put(route('admin.addons.update', $other), [
                'name'                    => 'Other',
                'type'                    => 'recurring',
                'monthly_price'           => 11,
                'annual_price'            => 22,
                'monthly_price_secondary' => 33,
                'annual_price_secondary'  => 44,
                'status'                  => 'active',
                'sort_order'              => 0,
            ])->assertSessionHasNoErrors();

        $target = $this->makeAddon();
        $this->actingAs($admin, 'admin')
            ->put(route('admin.addons.update', $target), [
                'name'                    => 'Target',
                'type'                    => 'recurring',
                'monthly_price'           => 8000,
                'annual_price'            => 8100,
                'monthly_price_secondary' => 8200,
                'annual_price_secondary'  => 8300,
                'status'                  => 'active',
                'sort_order'              => 0,
            ])->assertSessionHasNoErrors();

        $this->assertFourPrices($other, [
            ['USD', 'monthly', 11],
            ['USD', 'annual',  22],
            ['INR', 'monthly', 33],
            ['INR', 'annual',  44],
        ]);
        $this->assertFourPrices($target, [
            ['USD', 'monthly', 8000],
            ['USD', 'annual',  8100],
            ['INR', 'monthly', 8200],
            ['INR', 'annual',  8300],
        ]);
    }
}
