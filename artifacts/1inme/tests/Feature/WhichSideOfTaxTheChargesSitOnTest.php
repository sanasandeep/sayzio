<?php

namespace Tests\Feature;

use App\Modules\Common\Services\RestaurantBillCalculator;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\MenuFulfilment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sana, 2026-09-28: "before tax or after tax.. can u make it optional via
 * settings without or with?"
 *
 * The bill has always added charges AFTER tax, with a percentage charge
 * taken on the discounted food bill. That is the ordinary reading of "10%
 * service charge" and it is right in plenty of places. It is also wrong in
 * plenty of others: where GST applies to the delivery charge, a restaurant
 * running this setting off under-collects tax on every delivery it makes.
 *
 * Neither answer can be the only one, and which is right is the owner's tax
 * position rather than a software decision. So it is a setting, defaulting
 * to what it did before -- nobody's totals move unless they move them.
 *
 * One thing does NOT change with the setting: a percentage charge is taken
 * on the discounted food bill either way. A service charge computed on a
 * figure that already contains tax is not a reading anyone asked for.
 */
class WhichSideOfTaxTheChargesSitOnTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->user);
    }

    /** A menu with 5% GST and a 10% service charge on delivery. */
    private function menu(array $extra = []): RestaurantMenu
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'),
            'title' => 'Priyumm Tiffins', 'is_active' => true,
        ]);

        return RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'order', 'currency' => 'INR',
            'settings' => array_merge([
                'fulfilment_modes' => ['dine_in', 'delivery'],
                'charges' => [
                    ['label' => 'Delivery', 'type' => 'fixed', 'amount' => 40, 'modes' => ['delivery']],
                    ['label' => 'Service', 'type' => 'percent', 'amount' => 10, 'modes' => ['delivery']],
                ],
                'tax' => ['enabled' => true, 'rate' => 5, 'inclusive' => false, 'label' => 'GST'],
            ], $extra),
        ]);
    }

    private function bill(RestaurantMenu $menu, float $subtotal = 1000.0): array
    {
        return app(RestaurantBillCalculator::class)->compute($menu, $subtotal, null, 'delivery');
    }

    // ===== The default is exactly what it was =============================

    public function test_by_default_charges_land_after_tax(): void
    {
        $bill = $this->bill($this->menu());

        // 1000 food + 5% GST = 1050, then 40 delivery + 100 service = 1190.
        $this->assertSame(1000.0, $bill['taxable_base'], 'Tax should be worked out on the food alone.');
        $this->assertSame(50.0, $bill['tax_amount']);
        $this->assertSame(140.0, $bill['charges_amount']);
        $this->assertSame(1190.0, $bill['total']);
        $this->assertFalse($bill['charges_taxed']);
    }

    public function test_an_existing_menu_is_untouched_by_this_shipping(): void
    {
        // No setting at all -- every menu on the platform today.
        $menu = $this->menu();
        $this->assertArrayNotHasKey('charges_before_tax', (array) $menu->settings);
        $this->assertFalse(MenuFulfilment::chargesBeforeTax((array) $menu->settings));
        $this->assertSame(1190.0, $this->bill($menu)['total']);
    }

    // ===== Turned on, tax covers the charges =============================

    public function test_turned_on_the_charges_are_inside_the_taxed_base(): void
    {
        $bill = $this->bill($this->menu(['charges_before_tax' => true]));

        // 1000 food + 140 charges = 1140, then 5% GST = 57 -> 1197.
        $this->assertSame(1140.0, $bill['taxable_base']);
        $this->assertSame(57.0, $bill['tax_amount']);
        $this->assertSame(140.0, $bill['charges_amount']);
        $this->assertSame(1197.0, $bill['total']);
        $this->assertTrue($bill['charges_taxed']);
    }

    public function test_the_difference_is_exactly_the_tax_on_the_charges(): void
    {
        $after = $this->bill($this->menu());
        $before = $this->bill($this->menu(['charges_before_tax' => true]));

        // 5% of 140 = 7. Nothing else about the bill moves.
        $this->assertSame(7.0, round($before['total'] - $after['total'], 2));
        $this->assertSame($after['charges_amount'], $before['charges_amount']);
        $this->assertSame($after['subtotal'], $before['subtotal']);
    }

    // ===== What does NOT change with the setting =========================

    public function test_a_percentage_charge_is_taken_on_the_food_bill_either_way(): void
    {
        // 10% service on 1000 is 100, not 10% of a figure that already has
        // tax in it. That reading is not what a service charge means.
        foreach ([false, true] as $beforeTax) {
            $bill = $this->bill($this->menu($beforeTax ? ['charges_before_tax' => true] : []));

            $service = collect($bill['charges'])->firstWhere('label', 'Service');
            $this->assertSame(100.0, (float) $service['amount'],
                'The percentage charge moved when the setting changed, and it should not.');
        }
    }

    public function test_a_discount_still_comes_off_before_anything_else(): void
    {
        foreach ([false, true] as $beforeTax) {
            $menu = $this->menu($beforeTax ? ['charges_before_tax' => true] : []);
            $menu->coupons()->create([
                'code' => 'TEN', 'discount_type' => 'percent', 'discount_value' => 10,
                'min_subtotal' => 0, 'is_active' => true,
            ]);

            $bill = app(RestaurantBillCalculator::class)->compute($menu, 1000.0, 'TEN', 'delivery');

            $this->assertSame(100.0, $bill['discount_amount']);
            // Service is 10% of the DISCOUNTED 900, not of 1000.
            $service = collect($bill['charges'])->firstWhere('label', 'Service');
            $this->assertSame(90.0, (float) $service['amount']);
        }
    }

    public function test_a_menu_with_no_tax_totals_the_same_either_way(): void
    {
        // With no tax line there is no side of it to be on.
        $off = ['tax' => ['enabled' => false, 'rate' => 0, 'inclusive' => false, 'label' => 'GST']];

        $after = $this->bill($this->menu($off));
        $before = $this->bill($this->menu($off + ['charges_before_tax' => true]));

        $this->assertSame(1140.0, $after['total']);
        $this->assertSame($after['total'], $before['total']);
    }

    public function test_inclusive_pricing_shows_a_bigger_tax_portion_not_a_bigger_total(): void
    {
        $incl = ['tax' => ['enabled' => true, 'rate' => 5, 'inclusive' => true, 'label' => 'GST']];

        $after = $this->bill($this->menu($incl));
        $before = $this->bill($this->menu($incl + ['charges_before_tax' => true]));

        // Inclusive means the money is already in the price, so the total is
        // the same; what changes is how much of it is called tax.
        $this->assertSame(1140.0, $after['total']);
        $this->assertSame(1140.0, $before['total']);
        $this->assertGreaterThan($after['tax_amount'], $before['tax_amount'],
            'With charges inside the base, more of the same total is tax.');
    }

    // ===== The owner can actually set it =================================

    public function test_the_owner_can_turn_it_on_and_off(): void
    {
        $menu = $this->menu();
        $link = $menu->link;

        $this->actingAs($this->user)
            ->postJson('/user/links/'.$link->id.'/restaurant/settings', [
                'mode' => 'order', 'currency' => 'INR', 'charges_before_tax' => true,
            ])->assertOk();

        $this->assertTrue(MenuFulfilment::chargesBeforeTax((array) $menu->fresh()->settings));

        $this->actingAs($this->user)
            ->postJson('/user/links/'.$link->id.'/restaurant/settings', [
                'mode' => 'order', 'currency' => 'INR', 'charges_before_tax' => false,
            ])->assertOk();

        $this->assertFalse(MenuFulfilment::chargesBeforeTax((array) $menu->fresh()->settings));
    }

    public function test_the_editor_offers_it_where_the_tax_settings_are(): void
    {
        $src = file_get_contents(resource_path('views/user/links/restaurant/editor.blade.php'));

        $this->assertStringContainsString('charges_before_tax', $src);
        $this->assertStringContainsString('Tax applies to charges too', $src);
        // Only worth showing when there are charges to tax.
        $this->assertStringContainsString('x-show="(menu.charges || []).length > 0"', $src);
        // And it has to be sent when the panel saves.
        $this->assertStringContainsString('charges_before_tax:!!this.menu.charges_before_tax', $src);
    }
}
