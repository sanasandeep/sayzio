<?php

namespace Tests\Feature;

use App\Modules\Common\Services\RestaurantBillCalculator;
use App\Modules\Common\Services\RestaurantOrderService;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuCategory;
use App\Modules\User\Models\RestaurantMenuCoupon;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Guards the restaurant estimated-bill math (Task #3071) so a future change
 * can never silently miscalculate the coupon discount or GST/tax a diner and
 * staff rely on. The bill (subtotal → coupon discount → tax → total) is shown
 * across the public web page, staff dashboard, and mobile app, all driven by
 * RestaurantBillCalculator (the single source of truth).
 *
 * Three layers are covered:
 *   - RestaurantBillCalculator directly: added-on / inclusive / no tax,
 *     percentage vs fixed coupons, invalid/inactive/below-minimum coupons
 *     (coupon_error path), discount-applied-before-tax ordering, clamping,
 *     and rounding.
 *   - the live quote endpoints (web PublicRestaurantController + mobile
 *     Api\RestaurantController) returning a correct breakdown without an order.
 *   - placeOrder snapshotting the same figures onto the persisted order.
 */
class RestaurantBillEstimateTest extends TestCase
{
    use RefreshDatabase;

    private function calc(): RestaurantBillCalculator
    {
        return app(RestaurantBillCalculator::class);
    }

    private function makeUser(): User
    {
        return User::create([
            'name'     => 'Owner',
            'email'    => 'owner-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'role'     => 'user',
        ]);
    }

    /**
     * Build an order-mode restaurant menu with the given tax settings.
     *
     * @return array{0:Link,1:RestaurantMenu}
     */
    private function makeMenu(User $owner, array $tax = [], string $currency = 'USD'): array
    {
        $link = Link::create([
            'user_id'   => $owner->id,
            'type'      => Link::TYPE_RESTAURANT_MENU,
            'alias'     => Link::generateAlias(),
            'title'     => 'Cafe Bistro',
            'is_active' => true,
        ]);

        $settings = [];
        if ($tax !== []) {
            $settings['tax'] = $tax;
        }

        $menu = RestaurantMenu::create([
            'link_id'  => $link->id,
            'user_id'  => $owner->id,
            'mode'     => RestaurantMenu::MODE_ORDER,
            'currency' => $currency,
            'settings' => $settings,
        ]);

        return [$link, $menu];
    }

    private function addItem(RestaurantMenu $menu, float $price, string $name = 'Item'): RestaurantMenuItem
    {
        return RestaurantMenuItem::create([
            'menu_id'   => $menu->id,
            'name'      => $name,
            'price'     => $price,
            'is_active' => true,
        ]);
    }

    private function addCoupon(RestaurantMenu $menu, array $attrs): RestaurantMenuCoupon
    {
        return RestaurantMenuCoupon::create(array_merge([
            'menu_id'        => $menu->id,
            'code'           => 'SAVE',
            'discount_type'  => RestaurantMenuCoupon::TYPE_PERCENT,
            'discount_value' => 10,
            'min_subtotal'   => 0,
            'is_active'      => true,
        ], $attrs));
    }

    // ── Calculator: tax modes ────────────────────────────────────────

    public function test_added_on_tax_is_added_on_top_of_subtotal(): void
    {
        $owner = $this->makeUser();
        [, $menu] = $this->makeMenu($owner, ['enabled' => true, 'rate' => 10, 'inclusive' => false]);

        $bill = $this->calc()->compute($menu, 100.0);

        $this->assertSame(100.0, $bill['subtotal']);
        $this->assertTrue($bill['tax_enabled']);
        $this->assertFalse($bill['tax_inclusive']);
        $this->assertSame(10.0, $bill['tax_amount']);
        $this->assertSame(110.0, $bill['total']);
    }

    public function test_inclusive_tax_is_broken_out_but_not_added(): void
    {
        $owner = $this->makeUser();
        [, $menu] = $this->makeMenu($owner, ['enabled' => true, 'rate' => 10, 'inclusive' => true]);

        $bill = $this->calc()->compute($menu, 100.0);

        $this->assertTrue($bill['tax_inclusive']);
        // 100 - (100 / 1.10) = 9.0909… → 9.09, and the total stays 100.
        $this->assertSame(9.09, $bill['tax_amount']);
        $this->assertSame(100.0, $bill['total']);
    }

    public function test_no_tax_when_disabled(): void
    {
        $owner = $this->makeUser();
        [, $menu] = $this->makeMenu($owner); // no tax settings at all

        $bill = $this->calc()->compute($menu, 100.0);

        $this->assertFalse($bill['tax_enabled']);
        $this->assertSame(0.0, $bill['tax_amount']);
        $this->assertSame(100.0, $bill['total']);
    }

    public function test_tax_enabled_with_zero_rate_adds_nothing(): void
    {
        $owner = $this->makeUser();
        [, $menu] = $this->makeMenu($owner, ['enabled' => true, 'rate' => 0, 'inclusive' => false]);

        $bill = $this->calc()->compute($menu, 100.0);

        // A configured-but-zero rate must not flag a tax line nor change total.
        $this->assertFalse($bill['tax_enabled']);
        $this->assertSame(0.0, $bill['tax_amount']);
        $this->assertSame(100.0, $bill['total']);
    }

    public function test_default_tax_label_is_gst_and_custom_label_is_kept(): void
    {
        $owner = $this->makeUser();

        [, $menuDefault] = $this->makeMenu($owner, ['enabled' => true, 'rate' => 5]);
        $this->assertSame('GST', $this->calc()->compute($menuDefault, 10.0)['tax_label']);

        [, $menuCustom] = $this->makeMenu($owner, ['enabled' => true, 'rate' => 5, 'label' => 'VAT']);
        $this->assertSame('VAT', $this->calc()->compute($menuCustom, 10.0)['tax_label']);
    }

    // ── Calculator: coupons ──────────────────────────────────────────

    public function test_percentage_coupon_discounts_the_subtotal(): void
    {
        $owner = $this->makeUser();
        [, $menu] = $this->makeMenu($owner);
        $this->addCoupon($menu, ['code' => 'TWENTY', 'discount_type' => RestaurantMenuCoupon::TYPE_PERCENT, 'discount_value' => 20]);

        $bill = $this->calc()->compute($menu, 100.0, 'twenty'); // lowercase ⇒ normalized

        $this->assertTrue($bill['coupon_applied']);
        $this->assertSame('TWENTY', $bill['coupon_code']);
        $this->assertNull($bill['coupon_error']);
        $this->assertSame(20.0, $bill['discount_amount']);
        $this->assertSame(80.0, $bill['taxable_base']);
        $this->assertSame(80.0, $bill['total']);
    }

    public function test_fixed_coupon_discounts_a_flat_amount(): void
    {
        $owner = $this->makeUser();
        [, $menu] = $this->makeMenu($owner);
        $this->addCoupon($menu, ['code' => 'FIVEOFF', 'discount_type' => RestaurantMenuCoupon::TYPE_FIXED, 'discount_value' => 15]);

        $bill = $this->calc()->compute($menu, 100.0, 'FIVEOFF');

        $this->assertTrue($bill['coupon_applied']);
        $this->assertSame(15.0, $bill['discount_amount']);
        $this->assertSame(85.0, $bill['taxable_base']);
    }

    public function test_fixed_coupon_is_clamped_to_subtotal(): void
    {
        $owner = $this->makeUser();
        [, $menu] = $this->makeMenu($owner);
        $this->addCoupon($menu, ['code' => 'BIG', 'discount_type' => RestaurantMenuCoupon::TYPE_FIXED, 'discount_value' => 999]);

        $bill = $this->calc()->compute($menu, 30.0, 'BIG');

        // Never discount more than the bill — total floors at 0, never negative.
        $this->assertSame(30.0, $bill['discount_amount']);
        $this->assertSame(0.0, $bill['taxable_base']);
        $this->assertSame(0.0, $bill['total']);
    }

    public function test_invalid_coupon_code_sets_error_and_no_discount(): void
    {
        $owner = $this->makeUser();
        [, $menu] = $this->makeMenu($owner);
        // No coupon exists with this code.

        $bill = $this->calc()->compute($menu, 100.0, 'NOPE');

        $this->assertFalse($bill['coupon_applied']);
        $this->assertNull($bill['coupon_code']);
        $this->assertNotNull($bill['coupon_error']);
        $this->assertSame(0.0, $bill['discount_amount']);
        $this->assertSame(100.0, $bill['total']);
    }

    public function test_inactive_coupon_sets_error_and_no_discount(): void
    {
        $owner = $this->makeUser();
        [, $menu] = $this->makeMenu($owner);
        $this->addCoupon($menu, ['code' => 'OFF', 'is_active' => false, 'discount_value' => 50]);

        $bill = $this->calc()->compute($menu, 100.0, 'OFF');

        $this->assertFalse($bill['coupon_applied']);
        $this->assertNotNull($bill['coupon_error']);
        $this->assertSame(0.0, $bill['discount_amount']);
    }

    public function test_coupon_below_minimum_subtotal_sets_error(): void
    {
        $owner = $this->makeUser();
        [, $menu] = $this->makeMenu($owner);
        $this->addCoupon($menu, ['code' => 'MIN50', 'discount_value' => 10, 'min_subtotal' => 50]);

        $bill = $this->calc()->compute($menu, 30.0, 'MIN50');

        $this->assertFalse($bill['coupon_applied']);
        $this->assertNotNull($bill['coupon_error']);
        $this->assertStringContainsString('or more', $bill['coupon_error']);
        $this->assertSame(0.0, $bill['discount_amount']);
    }

    public function test_no_coupon_code_means_no_error_and_no_discount(): void
    {
        $owner = $this->makeUser();
        [, $menu] = $this->makeMenu($owner);

        $bill = $this->calc()->compute($menu, 100.0, null);

        $this->assertFalse($bill['coupon_applied']);
        $this->assertNull($bill['coupon_error']);
        $this->assertSame(0.0, $bill['discount_amount']);
    }

    // ── Calculator: ordering + rounding ──────────────────────────────

    public function test_discount_is_applied_before_tax(): void
    {
        $owner = $this->makeUser();
        [, $menu] = $this->makeMenu($owner, ['enabled' => true, 'rate' => 18, 'inclusive' => false]);
        $this->addCoupon($menu, ['code' => 'TEN', 'discount_type' => RestaurantMenuCoupon::TYPE_PERCENT, 'discount_value' => 10]);

        $bill = $this->calc()->compute($menu, 100.0, 'TEN');

        // Tax must be charged on the DISCOUNTED base (90), not the gross (100):
        //   discount 10 → taxable 90 → tax 90*0.18 = 16.20 → total 106.20.
        // If tax were applied first it would be 18.00 / total 108.00.
        $this->assertSame(10.0, $bill['discount_amount']);
        $this->assertSame(90.0, $bill['taxable_base']);
        $this->assertSame(16.2, $bill['tax_amount']);
        $this->assertSame(106.2, $bill['total']);
    }

    public function test_added_on_tax_amount_is_rounded_to_cents(): void
    {
        $owner = $this->makeUser();
        [, $menu] = $this->makeMenu($owner, ['enabled' => true, 'rate' => 8.25, 'inclusive' => false]);

        $bill = $this->calc()->compute($menu, 19.99);

        // 19.99 * 0.0825 = 1.649175 → rounds to 1.65; total 21.64.
        $this->assertSame(1.65, $bill['tax_amount']);
        $this->assertSame(21.64, $bill['total']);
    }

    public function test_negative_subtotal_is_floored_to_zero(): void
    {
        $owner = $this->makeUser();
        [, $menu] = $this->makeMenu($owner, ['enabled' => true, 'rate' => 10, 'inclusive' => false]);

        $bill = $this->calc()->compute($menu, -50.0);

        $this->assertSame(0.0, $bill['subtotal']);
        $this->assertSame(0.0, $bill['tax_amount']);
        $this->assertSame(0.0, $bill['total']);
    }

    // ── Web quote endpoint (PublicRestaurantController) ───────────────

    public function test_web_quote_returns_correct_breakdown_with_coupon_and_tax(): void
    {
        $owner = $this->makeUser();
        [$link, $menu] = $this->makeMenu($owner, ['enabled' => true, 'rate' => 18, 'inclusive' => false]);
        $item = $this->addItem($menu, 50.0, 'Plate');
        $this->addCoupon($menu, ['code' => 'TEN', 'discount_type' => RestaurantMenuCoupon::TYPE_PERCENT, 'discount_value' => 10]);

        $res = $this->postJson("/rm/{$link->alias}/quote", [
            'coupon_code' => 'TEN',
            'items'       => [['item_id' => $item->id, 'quantity' => 2]],
        ]);

        $res->assertOk();
        $bill = $res->json('data.bill');
        $this->assertEqualsWithDelta(100.0, $bill['subtotal'], 0.001);
        $this->assertTrue($bill['coupon_applied']);
        $this->assertEqualsWithDelta(10.0, $bill['discount_amount'], 0.001);
        $this->assertEqualsWithDelta(16.2, $bill['tax_amount'], 0.001);
        $this->assertEqualsWithDelta(106.2, $bill['total'], 0.001);
        $this->assertTrue($bill['is_estimate']);
    }

    public function test_web_quote_reports_coupon_error_for_invalid_code(): void
    {
        $owner = $this->makeUser();
        [$link, $menu] = $this->makeMenu($owner);
        $item = $this->addItem($menu, 25.0);

        $res = $this->postJson("/rm/{$link->alias}/quote", [
            'coupon_code' => 'BOGUS',
            'items'       => [['item_id' => $item->id, 'quantity' => 1]],
        ]);

        $res->assertOk();
        $bill = $res->json('data.bill');
        $this->assertFalse($bill['coupon_applied']);
        $this->assertNotNull($bill['coupon_error']);
        $this->assertEqualsWithDelta(0.0, $bill['discount_amount'], 0.001);
        $this->assertEqualsWithDelta(25.0, $bill['total'], 0.001);
    }

    // ── Web placeOrder snapshot (PublicRestaurantController) ──────────

    public function test_web_place_order_snapshots_the_estimated_bill(): void
    {
        $owner = $this->makeUser();
        [$link, $menu] = $this->makeMenu($owner, ['enabled' => true, 'rate' => 18, 'inclusive' => false]);
        $item = $this->addItem($menu, 50.0, 'Plate');
        $this->addCoupon($menu, ['code' => 'TEN', 'discount_type' => RestaurantMenuCoupon::TYPE_PERCENT, 'discount_value' => 10]);

        $res = $this->postJson("/rm/{$link->alias}/order", [
            'customer_name' => 'Ada',
            'coupon_code'   => 'TEN',
            'items'         => [['item_id' => $item->id, 'quantity' => 2]],
        ]);

        $res->assertCreated();
        $order = $res->json('data.order');
        $this->assertSame('100.00', (string) $order['subtotal']);
        $this->assertSame('TEN', $order['coupon_code']);
        $this->assertSame('10.00', (string) $order['discount_amount']);
        $this->assertSame('16.20', (string) $order['tax_amount']);
        $this->assertSame('106.20', (string) $order['total']);

        $this->assertDatabaseHas('restaurant_orders', [
            'public_token'    => $order['public_token'],
            'menu_id'         => $menu->id,
            'coupon_code'     => 'TEN',
            'discount_amount' => 10.00,
            'tax_amount'      => 16.20,
            'total'           => 106.20,
        ]);
    }

    public function test_web_place_order_ignores_a_tampered_coupon(): void
    {
        $owner = $this->makeUser();
        [$link, $menu] = $this->makeMenu($owner);
        $item = $this->addItem($menu, 40.0);
        // No coupon defined — a guest sending a fake code must get no discount.

        $res = $this->postJson("/rm/{$link->alias}/order", [
            'coupon_code' => 'HACK90',
            'items'       => [['item_id' => $item->id, 'quantity' => 1]],
        ]);

        $res->assertCreated();
        $order = $res->json('data.order');
        $this->assertNull($order['coupon_code']);
        $this->assertSame('0.00', (string) $order['discount_amount']);
        $this->assertSame('40.00', (string) $order['total']);
    }

    // ── Mobile API quote + placeOrder (Api\RestaurantController) ──────

    public function test_api_quote_returns_correct_breakdown(): void
    {
        $owner = $this->makeUser();
        [$link, $menu] = $this->makeMenu($owner, ['enabled' => true, 'rate' => 10, 'inclusive' => true]);
        $item = $this->addItem($menu, 50.0, 'Plate');

        $res = $this->postJson("/api/v1/restaurant/{$link->alias}/quote", [
            'items' => [['item_id' => $item->id, 'quantity' => 2]],
        ], ['Accept' => 'application/json']);

        $res->assertOk();
        $bill = $res->json('data.bill');
        $this->assertEqualsWithDelta(100.0, $bill['subtotal'], 0.001);
        $this->assertTrue($bill['tax_inclusive']);
        // Inclusive: 100 - 100/1.1 = 9.09 broken out; total stays 100.
        $this->assertEqualsWithDelta(9.09, $bill['tax_amount'], 0.001);
        $this->assertEqualsWithDelta(100.0, $bill['total'], 0.001);
        $this->assertTrue($bill['is_estimate']);
    }

    public function test_api_place_order_snapshots_the_estimated_bill(): void
    {
        $owner = $this->makeUser();
        [$link, $menu] = $this->makeMenu($owner, ['enabled' => true, 'rate' => 18, 'inclusive' => false]);
        $item = $this->addItem($menu, 50.0, 'Plate');
        $this->addCoupon($menu, ['code' => 'FIVE', 'discount_type' => RestaurantMenuCoupon::TYPE_FIXED, 'discount_value' => 20]);

        $res = $this->postJson("/api/v1/restaurant/{$link->alias}/order", [
            'coupon_code' => 'FIVE',
            'items'       => [['item_id' => $item->id, 'quantity' => 2]],
        ], ['Accept' => 'application/json']);

        $res->assertCreated();
        $order = $res->json('data.order');
        // subtotal 100, fixed 20 off → taxable 80, tax 80*0.18 = 14.40, total 94.40.
        $this->assertSame('100.00', (string) $order['subtotal']);
        $this->assertSame('FIVE', $order['coupon_code']);
        $this->assertSame('20.00', (string) $order['discount_amount']);
        $this->assertSame('14.40', (string) $order['tax_amount']);
        $this->assertSame('94.40', (string) $order['total']);

        $this->assertDatabaseHas('restaurant_orders', [
            'public_token'    => $order['public_token'],
            'discount_amount' => 20.00,
            'tax_amount'      => 14.40,
            'total'           => 94.40,
        ]);
    }

    public function test_service_place_matches_calculator_for_inclusive_tax(): void
    {
        $owner = $this->makeUser();
        [$link, $menu] = $this->makeMenu($owner, ['enabled' => true, 'rate' => 10, 'inclusive' => true]);
        $item = $this->addItem($menu, 30.0, 'Plate');

        $order = app(RestaurantOrderService::class)->place($link, $menu, [
            'items' => [['item_id' => $item->id, 'quantity' => 2]],
        ]);

        // subtotal 60 inclusive of 10%: tax broken out = 60 - 60/1.1 = 5.45,
        // total unchanged at 60.
        $this->assertSame('60.00', (string) $order->subtotal);
        $this->assertSame('5.45', (string) $order->tax_amount);
        $this->assertSame('60.00', (string) $order->total);
    }
}
