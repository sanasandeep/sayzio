<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\MenuOrderCoupon;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuCategory;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\StoreCategory;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreProduct;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\MenuBulkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sana, 2026-10-04: "need options while order from menu minimum and max
 * order quatity or each order item... this will create like bulk order...
 * when bulk order.... need to generate food coupons ids.... when coupoon
 * id give, order of that 1 coupon is completed or delivered... also
 * alternatively, search order by phone no also possible".
 *
 * ---- What actually needs guarding ---------------------------------------
 *
 * Three things, in this order of consequence.
 *
 * One: that nothing changes for the menus that have no rule. Every item on
 * every menu today has no floor, no ceiling and no coupons, and the first
 * test here is that such an item still orders exactly as it did.
 *
 * Two: that a coupon cannot be spent twice. The whole feature is a
 * promise that a code is one serving, and two staff scanning the same code
 * in the same second is not a hypothetical at a counter at 1pm.
 *
 * Three: that every one of these has a screen. Eight features this month
 * shipped working and reachable from nowhere -- so the editor fields, the
 * counter panel and the guest's copy of the codes are each asserted to be
 * ON the page, on both the restaurant and the store side.
 */
class OneOrderCanFeedTwoHundredPeopleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->user);
    }

    /** A restaurant in order mode with one Rs.100 dish. */
    private function restaurant(array $itemAttrs = []): array
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'),
            'title' => 'Priyumm Tiffins', 'is_active' => true,
        ]);
        $link->biolinkBlocks()->delete();

        $menu = RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'order', 'currency' => 'INR', 'accent_color' => '#e0457b',
            'settings' => [],
        ]);
        $cat = RestaurantMenuCategory::create([
            'menu_id' => $menu->id, 'name' => 'Meals', 'sort_order' => 0, 'is_active' => true,
        ]);
        $item = RestaurantMenuItem::create(array_merge([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Lunch Meals',
            'price' => 100, 'sort_order' => 0, 'is_active' => true,
        ], $itemAttrs));

        return [$link, $menu, $item];
    }

    private function store(array $productAttrs = []): array
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'store_menu',
            'alias' => 'sm'.fake()->unique()->numerify('#####'),
            'title' => 'The Shop', 'is_active' => true,
        ]);
        $link->biolinkBlocks()->delete();

        $menu = StoreMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'order', 'currency' => 'INR', 'accent_color' => '#e0457b',
            'settings' => [],
        ]);
        $cat = StoreCategory::create(['menu_id' => $menu->id, 'name' => 'Mugs', 'sort_order' => 0, 'is_active' => true]);
        $product = StoreProduct::create(array_merge([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Big Mug',
            'price' => 100, 'sort_order' => 0, 'is_active' => true,
        ], $productAttrs));

        return [$link, $menu, $product];
    }

    private function order(Link $link, int $itemId, int $quantity, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/rm/'.$link->alias.'/order', array_merge([
            'customer_name' => 'Test Guest',
            'customer_phone' => '9840012345',
            'items' => [['item_id' => $itemId, 'quantity' => $quantity]],
        ], $extra));
    }

    // ===== 1. Nothing changes for an item with no rule =====

    /**
     * The one that matters most. Every dish on every live menu is this
     * dish, and if this test goes red the feature has broken ordering for
     * everybody to serve a canteen.
     */
    public function test_an_item_with_no_rule_orders_exactly_as_it_did(): void
    {
        [$link, $menu, $item] = $this->restaurant();

        $res = $this->order($link, $item->id, 2)->assertCreated();

        $this->assertSame('200.00', (string) $res->json('data.order.total'));
        $this->assertSame([], $res->json('data.order.meal_coupons'));
        $this->assertSame(0, MenuOrderCoupon::count());
    }

    /** And its rule prints as nothing at all under the dish. */
    public function test_an_item_with_no_rule_says_nothing_about_quantity(): void
    {
        [$link, $menu, $item] = $this->restaurant();

        $this->assertSame('', MenuBulkOrder::label($item));
    }

    // ===== 2. The two numbers hold =====

    public function test_fewer_than_the_minimum_is_refused_with_a_sentence(): void
    {
        [$link, $menu, $item] = $this->restaurant(['min_quantity' => 10]);

        $res = $this->order($link, $item->id, 3)->assertStatus(422);

        // A number and the dish's own name: "that quantity is invalid" is
        // not something a guest can act on.
        $this->assertStringContainsString('10', (string) $res->json('error.message'));
        $this->assertStringContainsString('Lunch Meals', (string) $res->json('error.message'));
        $this->assertSame(0, MenuOrderCoupon::count());
    }

    public function test_more_than_the_maximum_is_refused(): void
    {
        [$link, $menu, $item] = $this->restaurant(['max_quantity' => 50]);

        $res = $this->order($link, $item->id, 51)->assertStatus(422);

        $this->assertStringContainsString('50', (string) $res->json('error.message'));
    }

    public function test_exactly_the_minimum_and_exactly_the_maximum_both_go_through(): void
    {
        [$link, $menu, $item] = $this->restaurant(['min_quantity' => 10, 'max_quantity' => 50]);

        $this->order($link, $item->id, 10)->assertCreated();
        $this->order($link, $item->id, 50)->assertCreated();
    }

    /**
     * The rule reads as a sentence to a diner, not as three settings.
     *
     * It first printed "Minimum 10, maximum 300, coupons from 20" under the
     * dish -- the owner's three fields, in the owner's words, to somebody
     * deciding what to have for lunch.
     */
    public function test_the_rule_reads_as_english(): void
    {
        [$link, $menu, $item] = $this->restaurant(['min_quantity' => 10, 'max_quantity' => 50, 'coupon_from' => 20]);

        $this->assertSame('10 to 50 per order · meal coupons on 20 or more', MenuBulkOrder::label($item));
    }

    /** Each shape of the rule gets its own sentence rather than a template. */
    public function test_each_shape_of_the_rule_has_its_own_sentence(): void
    {
        $say = function (array $attrs) {
            [$link, $menu, $item] = $this->restaurant($attrs);

            return MenuBulkOrder::label($item);
        };

        $this->assertSame('Minimum 10 per order', $say(['min_quantity' => 10]));
        $this->assertSame('Up to 50 per order', $say(['max_quantity' => 50]));
        $this->assertSame('Sold in 12s', $say(['min_quantity' => 12, 'max_quantity' => 12]));
        $this->assertSame('Comes with meal coupons', $say(['coupon_from' => 1]));
        $this->assertSame(
            'Minimum 20 per order · comes with meal coupons',
            $say(['min_quantity' => 20, 'coupon_from' => 20])
        );
    }

    // ===== 3. Coupons, one per serving =====

    public function test_a_line_above_the_threshold_produces_one_coupon_per_serving(): void
    {
        [$link, $menu, $item] = $this->restaurant(['coupon_from' => 20]);

        $res = $this->order($link, $item->id, 50)->assertCreated();

        $this->assertCount(50, $res->json('data.order.meal_coupons'));
        $this->assertSame(50, MenuOrderCoupon::count());
    }

    public function test_a_line_below_the_threshold_produces_none(): void
    {
        [$link, $menu, $item] = $this->restaurant(['coupon_from' => 20]);

        $res = $this->order($link, $item->id, 19)->assertCreated();

        $this->assertSame([], $res->json('data.order.meal_coupons'));
        $this->assertSame(0, MenuOrderCoupon::count());
    }

    /** Null is never, which is what every item means today. */
    public function test_an_item_with_no_threshold_never_issues_coupons(): void
    {
        [$link, $menu, $item] = $this->restaurant();

        $this->order($link, $item->id, 500)->assertCreated();

        $this->assertSame(0, MenuOrderCoupon::count());
    }

    /**
     * And 200 of something goes through at all.
     *
     * Both order endpoints capped a line at 99 as a literal, which was a
     * fine guard against nonsense until the day an office ordered 200
     * lunches and was refused by validation before any rule was read.
     */
    public function test_a_line_of_two_hundred_is_an_order_and_not_a_refusal(): void
    {
        [$link, $menu, $item] = $this->restaurant(['coupon_from' => 50]);

        $res = $this->order($link, $item->id, 200)->assertCreated();

        $this->assertCount(200, $res->json('data.order.meal_coupons'));
    }

    /** Every code is different, and none of them is guessable from its neighbour. */
    public function test_every_coupon_on_one_order_has_its_own_code(): void
    {
        [$link, $menu, $item] = $this->restaurant(['coupon_from' => 1]);

        $this->order($link, $item->id, 40)->assertCreated();

        $codes = MenuOrderCoupon::pluck('code');
        $this->assertCount(40, $codes->unique());
    }

    /** The alphabet exists to be typed at a counter. */
    public function test_no_code_contains_a_character_people_misread(): void
    {
        [$link, $menu, $item] = $this->restaurant(['coupon_from' => 1]);

        $this->order($link, $item->id, 30)->assertCreated();

        foreach (MenuOrderCoupon::pluck('code') as $code) {
            $this->assertSame(MenuOrderCoupon::LENGTH, strlen($code));
            // 0/O and 1/I/L are what goes wrong when somebody reads a code
            // off a phone screen and types it.
            $this->assertDoesNotMatchRegularExpression('/[01OIL]/', $code);
        }
    }

    /** A renamed dish does not rewrite the coupons it already produced. */
    public function test_a_coupon_keeps_saying_what_it_was_issued_for(): void
    {
        [$link, $menu, $item] = $this->restaurant(['coupon_from' => 1]);
        $this->order($link, $item->id, 5)->assertCreated();

        $item->update(['name' => 'Something Else Entirely']);

        $this->assertSame('Lunch Meals', MenuOrderCoupon::first()->item_name);
    }

    // ===== 4. Taking one at the counter =====

    /** Looking one up does not spend it. */
    public function test_looking_a_coupon_up_leaves_it_unredeemed(): void
    {
        [$link, $menu, $item] = $this->restaurant(['coupon_from' => 1]);
        $this->order($link, $item->id, 3)->assertCreated();
        $coupon = MenuOrderCoupon::first();

        $res = $this->actingAs($this->user)
            ->getJson('/user/links/'.$link->id.'/restaurant/meal-coupons/'.$coupon->code)
            ->assertOk();

        $this->assertSame(MenuOrderCoupon::ISSUED, $res->json('data.coupon.status'));
        $this->assertSame(MenuOrderCoupon::ISSUED, $coupon->fresh()->status);
    }

    public function test_a_coupon_can_be_taken_once(): void
    {
        [$link, $menu, $item] = $this->restaurant(['coupon_from' => 1]);
        $this->order($link, $item->id, 3)->assertCreated();
        $coupon = MenuOrderCoupon::first();

        $first = $this->actingAs($this->user)
            ->postJson('/user/links/'.$link->id.'/restaurant/meal-coupons/'.$coupon->code.'/redeem')
            ->assertOk();

        $this->assertTrue($first->json('data.redeemed'));
        $this->assertSame(MenuOrderCoupon::REDEEMED, $coupon->fresh()->status);
    }

    /**
     * And not twice. Two staff on two phones, same code, same second: the
     * second one has to be told it is already gone rather than serving a
     * second lunch off one pass.
     */
    public function test_a_coupon_cannot_be_taken_twice(): void
    {
        [$link, $menu, $item] = $this->restaurant(['coupon_from' => 1]);
        $this->order($link, $item->id, 3)->assertCreated();
        $coupon = MenuOrderCoupon::first();
        $url = '/user/links/'.$link->id.'/restaurant/meal-coupons/'.$coupon->code.'/redeem';

        $this->actingAs($this->user)->postJson($url)->assertOk();
        $second = $this->actingAs($this->user)->postJson($url)->assertOk();

        $this->assertFalse($second->json('data.redeemed'));
        // Not a bare failure: the counter needs to know WHEN, so it can
        // tell the person in front of it what happened.
        $this->assertStringContainsString('Already collected', (string) $second->json('data.message'));
    }

    /** Typed the way it was shown, or in lower case, or with the dash. */
    public function test_a_code_is_found_however_it_was_typed(): void
    {
        [$link, $menu, $item] = $this->restaurant(['coupon_from' => 1]);
        $this->order($link, $item->id, 3)->assertCreated();
        $coupon = MenuOrderCoupon::first();

        foreach ([$coupon->display(), strtolower($coupon->code), $coupon->code] as $typed) {
            $this->actingAs($this->user)
                ->getJson('/user/links/'.$link->id.'/restaurant/meal-coupons/'.urlencode($typed))
                ->assertOk();
        }
    }

    /** A code from another menu is not redeemable at this counter. */
    public function test_a_coupon_belongs_to_the_menu_that_issued_it(): void
    {
        [$linkA, $menuA, $itemA] = $this->restaurant(['coupon_from' => 1]);
        [$linkB] = $this->restaurant(['coupon_from' => 1]);
        $this->order($linkA, $itemA->id, 3)->assertCreated();
        $coupon = MenuOrderCoupon::first();

        $this->actingAs($this->user)
            ->getJson('/user/links/'.$linkB->id.'/restaurant/meal-coupons/'.$coupon->code)
            ->assertStatus(404);
    }

    public function test_a_code_that_does_not_exist_says_so(): void
    {
        [$link, $menu, $item] = $this->restaurant(['coupon_from' => 1]);

        $this->actingAs($this->user)
            ->getJson('/user/links/'.$link->id.'/restaurant/meal-coupons/ZZZZ9999')
            ->assertStatus(404);
    }

    /** Somebody else's menu is not somebody else's business. */
    public function test_another_owners_coupons_are_not_reachable(): void
    {
        [$link, $menu, $item] = $this->restaurant(['coupon_from' => 1]);
        $this->order($link, $item->id, 3)->assertCreated();
        $coupon = MenuOrderCoupon::first();

        $other = User::factory()->create(['onboarded_at' => now()]);

        $status = $this->actingAs($other)
            ->getJson('/user/links/'.$link->id.'/restaurant/meal-coupons/'.$coupon->code)
            ->getStatusCode();

        // 403 from the ownership check, or 404 if the link is not visible
        // to them at all. Both are refusals; pinning one would be a test
        // about routing rather than about access.
        $this->assertContains($status, [403, 404]);
    }

    // ===== 5. The order finishes when the last coupon is in =====

    public function test_the_order_completes_when_every_coupon_has_been_collected(): void
    {
        [$link, $menu, $item] = $this->restaurant(['coupon_from' => 1]);
        $this->order($link, $item->id, 3)->assertCreated();
        // The guest's copy of an order carries its public token, not its
        // row id, and deliberately so. The test reads the row.
        $orderId = \App\Modules\User\Models\RestaurantOrder::latest('id')->first()->id;

        foreach (MenuOrderCoupon::pluck('code') as $code) {
            $this->actingAs($this->user)
                ->postJson('/user/links/'.$link->id.'/restaurant/meal-coupons/'.$code.'/redeem')
                ->assertOk();
        }

        $this->assertSame('completed', \App\Modules\User\Models\RestaurantOrder::find($orderId)->status);
    }

    /** One of three is not three of three. */
    public function test_one_coupon_does_not_complete_the_order(): void
    {
        [$link, $menu, $item] = $this->restaurant(['coupon_from' => 1]);
        $this->order($link, $item->id, 3)->assertCreated();
        $orderId = \App\Modules\User\Models\RestaurantOrder::latest('id')->first()->id;

        $this->actingAs($this->user)
            ->postJson('/user/links/'.$link->id.'/restaurant/meal-coupons/'.MenuOrderCoupon::first()->code.'/redeem')
            ->assertOk();

        $this->assertNotSame('completed', \App\Modules\User\Models\RestaurantOrder::find($orderId)->status);
    }

    /** A cancelled order does not un-cancel because somebody scanned a pass. */
    public function test_a_cancelled_order_stays_cancelled(): void
    {
        [$link, $menu, $item] = $this->restaurant(['coupon_from' => 1]);
        $this->order($link, $item->id, 2)->assertCreated();
        $order = \App\Modules\User\Models\RestaurantOrder::latest('id')->first();
        $order->update(['status' => 'cancelled']);

        foreach (MenuOrderCoupon::pluck('code') as $code) {
            $this->actingAs($this->user)
                ->postJson('/user/links/'.$link->id.'/restaurant/meal-coupons/'.$code.'/redeem')
                ->assertOk();
        }

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    // ===== 6. Finding an order by phone =====

    public function test_an_order_is_found_by_the_phone_number_that_placed_it(): void
    {
        [$link, $menu, $item] = $this->restaurant(['coupon_from' => 1]);
        $this->order($link, $item->id, 4, ['customer_phone' => '9840012345'])->assertCreated();

        $res = $this->actingAs($this->user)
            ->getJson('/user/links/'.$link->id.'/restaurant/meal-coupons/by-phone?phone=9840012345')
            ->assertOk();

        $this->assertCount(1, $res->json('data.orders'));
        $this->assertSame(4, $res->json('data.orders.0.coupons.total'));
    }

    /**
     * A number saved with a country code still answers to the number
     * somebody reads out at the counter.
     */
    public function test_a_number_is_matched_on_its_last_ten_digits(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $this->order($link, $item->id, 1, ['customer_phone' => '+91 98400 12345'])->assertCreated();

        $res = $this->actingAs($this->user)
            ->getJson('/user/links/'.$link->id.'/restaurant/meal-coupons/by-phone?phone=9840012345')
            ->assertOk();

        $this->assertCount(1, $res->json('data.orders'));
    }

    /** Two digits would return the whole day's orders. */
    public function test_too_few_digits_is_refused_rather_than_searched(): void
    {
        [$link] = $this->restaurant();

        $this->actingAs($this->user)
            ->getJson('/user/links/'.$link->id.'/restaurant/meal-coupons/by-phone?phone=98')
            ->assertStatus(422);
    }

    public function test_a_number_with_no_orders_comes_back_empty_rather_than_failing(): void
    {
        [$link] = $this->restaurant();

        $res = $this->actingAs($this->user)
            ->getJson('/user/links/'.$link->id.'/restaurant/meal-coupons/by-phone?phone=9000000000')
            ->assertOk();

        $this->assertSame([], $res->json('data.orders'));
    }

    // ===== 7. The item editor's numbers =====

    public function test_the_three_numbers_save_from_the_item_editor(): void
    {
        [$link, $menu, $item] = $this->restaurant();

        $this->actingAs($this->user)->putJson('/user/links/'.$link->id.'/restaurant/items/'.$item->id, [
            'min_quantity' => 10,
            'max_quantity' => 200,
            'coupon_from'  => 20,
        ])->assertOk();

        $item->refresh();
        $this->assertSame(10, (int) $item->min_quantity);
        $this->assertSame(200, (int) $item->max_quantity);
        $this->assertSame(20, (int) $item->coupon_from);
    }

    /** Blank is blank: no floor, no ceiling, no coupons. */
    public function test_clearing_the_numbers_puts_the_item_back_how_it_was(): void
    {
        [$link, $menu, $item] = $this->restaurant(['min_quantity' => 10, 'max_quantity' => 200, 'coupon_from' => 20]);

        $this->actingAs($this->user)->putJson('/user/links/'.$link->id.'/restaurant/items/'.$item->id, [
            'min_quantity' => null,
            'max_quantity' => null,
            'coupon_from'  => null,
        ])->assertOk();

        $item->refresh();
        $this->assertSame(1, (int) $item->min_quantity);
        $this->assertNull($item->max_quantity);
        $this->assertNull($item->coupon_from);
        $this->assertSame('', MenuBulkOrder::label($item));
    }

    /**
     * A ceiling below the floor is the one pair that makes an item
     * unorderable, and it is two keystrokes away. It comes up to the
     * floor rather than being stored as typed.
     */
    public function test_a_ceiling_below_the_floor_is_raised_rather_than_stored(): void
    {
        [$link, $menu, $item] = $this->restaurant(['min_quantity' => 10]);

        $this->actingAs($this->user)->putJson('/user/links/'.$link->id.'/restaurant/items/'.$item->id, [
            'max_quantity' => 5,
        ])->assertOk();

        $this->assertSame(10, (int) $item->fresh()->max_quantity);
        $this->order($link, $item->id, 10)->assertCreated();
    }

    /** And the same pair arrived at from the other side. */
    public function test_raising_the_floor_past_a_saved_ceiling_lifts_the_ceiling_too(): void
    {
        [$link, $menu, $item] = $this->restaurant(['min_quantity' => 1, 'max_quantity' => 5]);

        $this->actingAs($this->user)->putJson('/user/links/'.$link->id.'/restaurant/items/'.$item->id, [
            'min_quantity' => 20,
        ])->assertOk();

        $item->refresh();
        $this->assertSame(20, (int) $item->min_quantity);
        $this->assertSame(20, (int) $item->max_quantity);
        $this->order($link, $item->id, 20)->assertCreated();
    }

    /** Editing the name does not quietly wipe the quantity rule. */
    public function test_saving_an_item_without_the_numbers_leaves_them_alone(): void
    {
        [$link, $menu, $item] = $this->restaurant(['min_quantity' => 10, 'coupon_from' => 20]);

        $this->actingAs($this->user)->putJson('/user/links/'.$link->id.'/restaurant/items/'.$item->id, [
            'name' => 'Renamed',
        ])->assertOk();

        $item->refresh();
        $this->assertSame(10, (int) $item->min_quantity);
        $this->assertSame(20, (int) $item->coupon_from);
    }

    public function test_the_numbers_save_on_a_store_product_too(): void
    {
        [$link, $menu, $product] = $this->store();

        $this->actingAs($this->user)->putJson('/user/links/'.$link->id.'/store/products/'.$product->id, [
            'min_quantity' => 12,
            'coupon_from'  => 12,
        ])->assertOk();

        $product->refresh();
        $this->assertSame(12, (int) $product->min_quantity);
        $this->assertSame(12, (int) $product->coupon_from);
    }

    // ===== 8. Every one of these has a screen =====

    /** The editor dialog offers the three numbers, on both editors. */
    public function test_both_item_editors_have_the_quantity_fields(): void
    {
        [$rLink] = $this->restaurant();
        [$sLink] = $this->store();

        $pages = [
            $this->actingAs($this->user)->get('/user/links/'.$rLink->id.'/restaurant'),
            $this->actingAs($this->user)->get('/user/links/'.$sLink->id.'/store'),
        ];

        foreach ($pages as $page) {
            $page->assertOk();
            $html = $page->getContent();
            $this->assertStringContainsString('Quantity', $html);
            $this->assertStringContainsString('min_quantity', $html);
            $this->assertStringContainsString('max_quantity', $html);
            $this->assertStringContainsString('coupon_from', $html);
            // The sentence that reads the three numbers back. Three boxes
            // with no sentence is three numbers to hold in your head.
            $this->assertStringContainsString('menuQuantity.explain', $html);
        }
    }

    /** The counter is on both orders screens, with all three ways in. */
    public function test_both_orders_screens_have_the_counter(): void
    {
        [$rLink] = $this->restaurant();
        [$sLink] = $this->store();

        $pages = [
            $this->actingAs($this->user)->get('/user/links/'.$rLink->id.'/restaurant/orders'),
            $this->actingAs($this->user)->get('/user/links/'.$sLink->id.'/store/orders'),
        ];

        foreach ($pages as $page) {
            $page->assertOk();
            $html = $page->getContent();
            $this->assertStringContainsString('ordersCounter(', $html);
            // Type it, scan it, or find the person by phone.
            $this->assertStringContainsString('Coupon code', $html);
            $this->assertStringContainsString('BarcodeDetector', $html);
            $this->assertStringContainsString('by-phone', $html);
        }
    }

    /** The guest's own copy of the codes is on both menu pages. */
    public function test_both_menu_pages_can_show_the_guest_their_coupons(): void
    {
        [$rLink] = $this->restaurant();
        [$sLink] = $this->store();

        foreach ([$rLink, $sLink] as $link) {
            $page = $this->get('/'.$link->alias)->assertOk();
            $html = $page->getContent();
            $this->assertStringContainsString('id="mealCoupons"', $html);
            $this->assertStringContainsString('menuMealCoupons', $html);
        }
    }

    /** The rule is on the menu, before the guest taps Add. */
    public function test_the_menu_tells_a_guest_the_rule_before_they_order(): void
    {
        [$link, $menu, $item] = $this->restaurant(['min_quantity' => 10, 'max_quantity' => 50]);

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        $this->assertStringContainsString('10 to 50 per order', $html);
        $this->assertStringContainsString('data-min="10"', $html);
        $this->assertStringContainsString('data-max="50"', $html);
    }

    /** And an item with no rule puts no empty row on the page. */
    public function test_an_item_with_no_rule_adds_nothing_to_the_menu(): void
    {
        [$link] = $this->restaurant();

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        $this->assertStringNotContainsString('class="qty-rule"', $html);
        // The rule row needs a flex order in the two layouts that remap
        // them, or it lands before the dish name -- the way the marks row
        // broke the leader dots when it first went inline.
        $this->assertStringContainsString('.price-dots .item .qty-rule', $html);
    }

    // ===== 9. The two words that are not the same word =====

    /**
     * A menu already has "coupons": the DISCOUNT codes a guest types at
     * checkout. These are meal coupons, under their own URL, because one
     * word over two unrelated features is how somebody types their lunch
     * pass into the discount box.
     */
    public function test_meal_coupons_do_not_live_under_the_discount_coupon_url(): void
    {
        [$link] = $this->restaurant();

        $this->assertStringContainsString(
            '/meal-coupons',
            route('user.links.restaurant.meal-coupons.by-phone', $link)
        );
        $this->assertStringNotContainsString(
            '/coupons/',
            route('user.links.restaurant.meal-coupons.by-phone', $link)
        );
    }
}
