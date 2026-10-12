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
 * Drift guard for the admin dual-currency price display.
 *
 * The admin Plans cards (admin/plans/partials/_card.blade.php) and the
 * Addons listing (admin/addons/index.blade.php) share one display
 * contract via PricingResolver::adminDisplayPair():
 *   1. USD + INR come from the authoritative `prices` table.
 *   2. When no USD row exists, the legacy major-unit decimal columns
 *      (monthly_price / annual_price) are the USD fallback.
 *   3. When no INR row exists, the page renders "—" (never ₹0.00).
 *
 * This test renders BOTH admin listings and asserts all three behaviors
 * on each, so a change to one page's display logic that forgets the
 * other fails loudly instead of silently drifting.
 */
class AdminPriceDisplayTest extends TestCase
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
            'name'                    => 'Display Plan ' . uniqid(),
            'slug'                    => 'display-plan-' . uniqid(),
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
            'name'                    => 'Display Addon ' . uniqid(),
            'slug'                    => 'display-addon-' . uniqid(),
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

    private function addPrice($priceable, string $currency, string $cycle, int $minor): void
    {
        Price::create([
            'priceable_type'     => get_class($priceable),
            'priceable_id'       => $priceable->getKey(),
            'currency'           => $currency,
            'billing_cycle'      => $cycle,
            'amount_minor_units' => $minor,
            'is_active'          => true,
        ]);
    }

    // ── Plans listing ────────────────────────────────────────────────

    public function test_plans_index_shows_prices_table_usd_and_inr(): void
    {
        $plan = $this->makePlan();
        $this->addPrice($plan, 'USD', 'monthly', 1999);    // $19.99
        $this->addPrice($plan, 'USD', 'annual', 19990);    // $199.90
        $this->addPrice($plan, 'INR', 'monthly', 149900);  // ₹1,499.00
        $this->addPrice($plan, 'INR', 'annual', 1499000);  // ₹14,990.00

        $resp = $this->actingAs($this->makeAdmin(), 'admin')
            ->get(route('admin.plans.index'));

        $resp->assertOk()
            ->assertSee('$19.99')
            ->assertSee('$199.90')
            ->assertSee('₹1,499.00')
            ->assertSee('₹14,990.00');
    }

    public function test_plans_index_falls_back_to_legacy_usd_and_renders_dash_for_missing_inr(): void
    {
        // No prices-table rows at all: legacy decimal columns drive USD,
        // and INR renders "—". (Assertions are anchored on this plan's
        // card via its name, because the migration-seeded lineup plans
        // also render on this page — the Free plan legitimately shows
        // ₹0.00 from its own seeded INR rows.)
        $plan = $this->makePlan([
            'monthly_price' => 12.5,
            'annual_price'  => 120,
        ]);

        $resp = $this->actingAs($this->makeAdmin(), 'admin')
            ->get(route('admin.plans.index'));

        $resp->assertOk()
            ->assertSeeInOrder([$plan->name, '$12.50', '/ —', '$120.00', '/ —']);
    }

    public function test_plans_index_prices_table_beats_legacy_columns(): void
    {
        // Legacy columns deliberately disagree with the prices table:
        // the prices table must win.
        $plan = $this->makePlan([
            'monthly_price' => 77.77,
            'annual_price'  => 88.88,
        ]);
        $this->addPrice($plan, 'USD', 'monthly', 3100); // $31.00
        $this->addPrice($plan, 'USD', 'annual', 32000); // $320.00

        $resp = $this->actingAs($this->makeAdmin(), 'admin')
            ->get(route('admin.plans.index'));

        $resp->assertOk()
            // Anchored on this plan's card; no INR rows → "—".
            ->assertSeeInOrder([$plan->name, '$31.00', '/ —', '$320.00', '/ —'])
            ->assertDontSee('$77.77')
            ->assertDontSee('$88.88');
    }

    // ── Addons listing ───────────────────────────────────────────────

    public function test_addons_index_shows_prices_table_usd_and_inr(): void
    {
        $addon = $this->makeAddon();
        $this->addPrice($addon, 'USD', 'monthly', 250);    // $2.50
        $this->addPrice($addon, 'USD', 'annual', 2500);    // $25.00
        $this->addPrice($addon, 'INR', 'monthly', 19900);  // ₹199.00
        $this->addPrice($addon, 'INR', 'annual', 199000);  // ₹1,990.00

        $resp = $this->actingAs($this->makeAdmin(), 'admin')
            ->get(route('admin.addons.index'));

        $resp->assertOk()
            ->assertSee('$2.50')
            ->assertSee('$25.00')
            ->assertSee('₹199.00')
            ->assertSee('₹1,990.00');
    }

    public function test_addons_index_falls_back_to_legacy_usd_and_renders_dash_for_missing_inr(): void
    {
        $addon = $this->makeAddon([
            'monthly_price' => 4.25,
            'annual_price'  => 42,
        ]);

        $resp = $this->actingAs($this->makeAdmin(), 'admin')
            ->get(route('admin.addons.index'));

        $resp->assertOk()
            ->assertSeeInOrder([$addon->name, '$4.25', '/ —', '$42.00', '/ —'])
            ->assertDontSee('₹0.00');
    }

    public function test_addons_index_prices_table_beats_legacy_columns(): void
    {
        $addon = $this->makeAddon([
            'monthly_price' => 55.55,
            'annual_price'  => 66.66,
        ]);
        $this->addPrice($addon, 'USD', 'monthly', 910);  // $9.10
        $this->addPrice($addon, 'USD', 'annual', 9200);  // $92.00

        $resp = $this->actingAs($this->makeAdmin(), 'admin')
            ->get(route('admin.addons.index'));

        $resp->assertOk()
            ->assertSeeInOrder([$addon->name, '$9.10', '/ —', '$92.00', '/ —'])
            ->assertDontSee('$55.55')
            ->assertDontSee('$66.66')
            ->assertDontSee('₹0.00');
    }
}
