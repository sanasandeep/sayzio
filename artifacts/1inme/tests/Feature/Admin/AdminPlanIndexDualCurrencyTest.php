<?php

namespace Tests\Feature\Admin;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\Price;
use App\Modules\Admin\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Coverage for the dual-currency (USD / INR) price display on the admin
 * Plans index (/admin/plans), so a missing INR price row surfaces as the
 * explicit "—" placeholder instead of silently showing $0.00 at checkout:
 *
 *   1. When authoritative `prices` rows exist, the USD figure comes from
 *      the prices table — NOT the legacy decimal columns.
 *   2. A plan with no INR rows renders the "—" placeholder next to its
 *      USD price for both cycles.
 *   3. A plan with no `prices` rows at all falls back to the legacy
 *      monthly_price / annual_price columns.
 *   4. Free (zero-amount) price rows render as $0.00 / ₹0.00.
 *   5. The page eager-loads prices: the query count does not grow with
 *      the number of plans (no N+1).
 */
class AdminPlanIndexDualCurrencyTest extends TestCase
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

    private function addPrice(Plan $plan, string $currency, string $cycle, int $minor): void
    {
        Price::create([
            'priceable_type'     => Plan::class,
            'priceable_id'       => $plan->id,
            'currency'           => $currency,
            'billing_cycle'      => $cycle,
            'amount_minor_units' => $minor,
            'is_active'          => true,
        ]);
    }

    public function test_usd_comes_from_prices_table_not_legacy_columns(): void
    {
        // Legacy columns deliberately disagree with the prices table so we
        // can prove which source the card renders from.
        $plan = $this->makePlan([
            'monthly_price' => 11.11,
            'annual_price'  => 22.22,
        ]);
        $this->addPrice($plan, 'USD', 'monthly', 4321);    // $43.21
        $this->addPrice($plan, 'USD', 'annual', 43210);    // $432.10
        $this->addPrice($plan, 'INR', 'monthly', 359900);  // ₹3,599.00
        $this->addPrice($plan, 'INR', 'annual', 3599000);  // ₹35,990.00

        $resp = $this->actingAs($this->makeAdmin(), 'admin')->get(route('admin.plans.index'));

        $resp->assertOk();
        $resp->assertSee('$43.21');
        $resp->assertSee('$432.10');
        $resp->assertSee('₹3,599.00');
        $resp->assertSee('₹35,990.00');
        // The legacy decimal columns must NOT leak through when rows exist.
        $resp->assertDontSee('$11.11');
        $resp->assertDontSee('$22.22');
    }

    public function test_missing_inr_rows_render_dash_placeholder(): void
    {
        $plan = $this->makePlan();
        // USD rows only — INR intentionally absent.
        $this->addPrice($plan, 'USD', 'monthly', 8631);   // $86.31
        $this->addPrice($plan, 'USD', 'annual', 86310);   // $863.10

        $resp = $this->actingAs($this->makeAdmin(), 'admin')->get(route('admin.plans.index'));

        $resp->assertOk();
        // The "—" placeholder immediately follows this plan's USD price on
        // each cycle row (seeded plans may legitimately show INR values, so
        // anchor the placeholder to this plan's distinctive amounts).
        $resp->assertSeeInOrder(['$86.31', '/ —', '$863.10', '/ —']);
        // And no INR figure was invented for this plan.
        $this->assertSame(0, Price::where('priceable_id', $plan->id)
            ->where('priceable_type', Plan::class)
            ->where('currency', 'INR')->count());
    }

    public function test_legacy_column_fallback_when_no_price_rows_exist(): void
    {
        $plan = $this->makePlan([
            'monthly_price' => 57.53,
            'annual_price'  => 575.31,
        ]);
        // No prices rows at all.

        $resp = $this->actingAs($this->makeAdmin(), 'admin')->get(route('admin.plans.index'));

        $resp->assertOk();
        // USD falls back to the legacy decimal columns, INR shows the dash.
        $resp->assertSeeInOrder(['$57.53', '/ —', '$575.31', '/ —']);
    }

    public function test_zero_amount_price_rows_render_as_free(): void
    {
        $plan = $this->makePlan([
            'name'          => 'Zero ' . uniqid(),
            'monthly_price' => 0,
            'annual_price'  => 0,
        ]);
        $this->addPrice($plan, 'USD', 'monthly', 0);
        $this->addPrice($plan, 'USD', 'annual', 0);
        $this->addPrice($plan, 'INR', 'monthly', 0);
        $this->addPrice($plan, 'INR', 'annual', 0);

        $resp = $this->actingAs($this->makeAdmin(), 'admin')->get(route('admin.plans.index'));

        $resp->assertOk();
        $resp->assertSee('$0.00');
        $resp->assertSee('₹0.00');
    }

    public function test_index_query_count_does_not_grow_with_plan_count(): void
    {
        $admin = $this->makeAdmin();

        $seed = function (int $n): void {
            for ($i = 0; $i < $n; $i++) {
                $plan = $this->makePlan();
                $this->addPrice($plan, 'USD', 'monthly', 1000 + $i);
                $this->addPrice($plan, 'USD', 'annual', 10000 + $i);
                $this->addPrice($plan, 'INR', 'monthly', 80000 + $i);
                $this->addPrice($plan, 'INR', 'annual', 800000 + $i);
            }
        };

        // Single listener registered once; each measured request reads the
        // delta (DB::listen offers no unlisten, so never stack listeners).
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        $countQueries = function () use ($admin, &$count): int {
            $before = $count;
            $this->actingAs($admin, 'admin')->get(route('admin.plans.index'))->assertOk();
            return $count - $before;
        };

        // Warm-up request pays one-time boot/settings queries.
        $this->actingAs($admin, 'admin')->get(route('admin.plans.index'))->assertOk();

        $seed(2);
        $first = $countQueries();

        $seed(6);
        $second = $countQueries();

        $this->assertSame(
            $first,
            $second,
            "Query count grew with plan count (was {$first}, now {$second}) — likely an N+1 on prices."
        );
    }
}
