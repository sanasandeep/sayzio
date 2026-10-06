<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuCategory;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\RestaurantOrder;
use App\Modules\User\Models\RestaurantTable;
use App\Modules\User\Models\StoreCategory;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreOrder;
use App\Modules\User\Models\StoreProduct;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\MenuHandoverTiming;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * When a takeaway or delivery order is wanted, and which table it is for.
 *
 * Sana, 2026-09-28: "if dine in table no. with dropdown list / if take away,
 * need to tell slots/time / setting should have min time for prep / if
 * delivery option, then other address also mandatory, need to tell
 * slots/time".
 *
 * ---- Two live bugs came out of building this --------------------------
 *
 * 1. The table box did nothing. It was on the page, a guest could type
 *    "4" into it, and only `table_code` was ever sent -- so somebody who
 *    told us where they were sitting was recorded as a walk-in.
 *
 * 2. A delivery-only menu could not be ordered from. `setFulfilment` is
 *    what reveals the address box, and it only fires from radio buttons
 *    that are not rendered when a menu offers a single mode. The server
 *    requires an address for delivery, so the guest had nothing to type
 *    into and no way through.
 *
 * ---- What the slot list refuses ---------------------------------------
 *
 * A time that is not in the offered list is REFUSED, not snapped to the
 * nearest one. A guest who asked for 7:30 and silently got 8:00 finds out
 * when they arrive.
 */
class WhenDoYouWantItTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private const TZ = 'Asia/Kolkata';

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create([
            'onboarded_at' => now(), 'timezone' => self::TZ,
        ]);
        app(WorkspaceContext::class)->resolve($this->owner);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array{0: Link, 1: RestaurantMenu, 2: RestaurantMenuItem} */
    private function restaurant(array $settings = []): array
    {
        $link = Link::create([
            'user_id' => $this->owner->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'),
            'title' => 'Priyumm Tiffins', 'is_active' => true,
        ]);
        $menu = RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->owner->id,
            'mode' => 'order', 'currency' => 'INR', 'settings' => $settings,
        ]);
        $cat = RestaurantMenuCategory::create([
            'menu_id' => $menu->id, 'name' => 'Mains', 'sort_order' => 0, 'is_active' => true,
        ]);
        $item = RestaurantMenuItem::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => 'Masala Dosa', 'price' => 120, 'sort_order' => 0, 'is_active' => true,
        ]);

        return [$link, $menu, $item];
    }

    /** @return array{0: Link, 1: StoreMenu, 2: StoreProduct} */
    private function store(array $settings = []): array
    {
        $link = Link::create([
            'user_id' => $this->owner->id, 'type' => 'store_menu',
            'alias' => 'sm'.fake()->unique()->numerify('#####'),
            'title' => 'The Shop', 'is_active' => true,
        ]);
        $menu = StoreMenu::create([
            'link_id' => $link->id, 'user_id' => $this->owner->id,
            'mode' => 'order', 'currency' => 'INR', 'settings' => $settings,
        ]);
        $cat = StoreCategory::create([
            'menu_id' => $menu->id, 'name' => 'Jars', 'sort_order' => 0, 'is_active' => true,
        ]);
        $product = StoreProduct::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => 'Pickle', 'price' => 180, 'sort_order' => 0, 'is_active' => true,
        ]);

        return [$link, $menu, $product];
    }

    private function timing(array $over = []): array
    {
        return ['handover_timing' => $over + [
            'enabled' => true, 'interval' => 30,
            'open' => '10:00', 'close' => '22:00', 'prep_minutes' => 30,
        ]];
    }

    private function order(Link $link, RestaurantMenuItem $item, array $over = [])
    {
        return $this->postJson('/rm/'.$link->alias.'/order', array_merge([
            'customer_name'  => 'Sana',
            'customer_phone' => '9840012345',
            'items'          => [['item_id' => $item->id, 'quantity' => 1]],
        ], $over));
    }

    // ===== The slot list ================================================

    public function test_nothing_is_asked_unless_the_owner_turns_it_on(): void
    {
        // Every menu on the platform takes orders today with no timing.
        $this->assertSame([], MenuHandoverTiming::slots([], self::TZ));
        $this->assertFalse(MenuHandoverTiming::resolve([])['enabled']);
    }

    public function test_prep_time_moves_the_first_slot_not_the_list(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 18:52', self::TZ));

        $slots = MenuHandoverTiming::slots($this->timing(['prep_minutes' => 30]), self::TZ);

        // 18:52 + 30 = 19:22, rounded up to the next half hour.
        $this->assertSame('7:30 pm', $slots[0]['label']);
        $this->assertSame('8:00 pm', $slots[1]['label']);
        $this->assertSame('Today', $slots[0]['day']);
    }

    public function test_the_interval_is_the_owners_to_choose(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00', self::TZ));

        $quarter = MenuHandoverTiming::slots($this->timing(['interval' => 15, 'prep_minutes' => 0]), self::TZ);
        $hourly = MenuHandoverTiming::slots($this->timing(['interval' => 60, 'prep_minutes' => 0]), self::TZ);

        $this->assertSame(['12:00 pm', '12:15 pm'], array_column(array_slice($quarter, 0, 2), 'label'));
        $this->assertSame(['12:00 pm', '1:00 pm'], array_column(array_slice($hourly, 0, 2), 'label'));
    }

    public function test_a_window_that_ends_before_it_starts_runs_past_midnight(): void
    {
        // A kitchen open 6pm to 1am is an ordinary thing to be.
        Carbon::setTestNow(Carbon::parse('2026-10-05 19:00', self::TZ));

        $labels = array_column(
            MenuHandoverTiming::slots($this->timing(['open' => '18:00', 'close' => '01:00', 'prep_minutes' => 0]), self::TZ),
            'label'
        );

        $this->assertContains('11:30 pm', $labels);
        $this->assertContains('12:30 am', $labels);
    }

    public function test_the_list_runs_past_today_but_not_forever(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 21:50', self::TZ));

        $slots = MenuHandoverTiming::slots($this->timing(['prep_minutes' => 30]), self::TZ);

        // Past closing today, so the first thing offered is tomorrow.
        $this->assertSame('Tomorrow', $slots[0]['day']);
        $this->assertLessThanOrEqual(MenuHandoverTiming::MAX_SLOTS, count($slots));
        $this->assertNotEmpty($slots);
    }

    public function test_a_prep_time_typo_does_not_empty_the_list(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00', self::TZ));

        // 6000 minutes would push every slot past the horizon.
        $this->assertSame(360, MenuHandoverTiming::resolve($this->timing(['prep_minutes' => 6000]))['prep_minutes']);
        $this->assertNotEmpty(MenuHandoverTiming::slots($this->timing(['prep_minutes' => 6000]), self::TZ));
    }

    public function test_rubbish_settings_fall_back_rather_than_breaking(): void
    {
        foreach ([null, 'later', ['interval' => 7], ['open' => '99:99'], ['close' => 'nope']] as $junk) {
            $resolved = MenuHandoverTiming::sanitize($junk);
            $this->assertContains($resolved['interval'], MenuHandoverTiming::INTERVALS);
            $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $resolved['open']);
            $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $resolved['close']);
        }
    }

    // ===== What the server accepts ======================================

    public function test_as_soon_as_possible_is_always_allowed(): void
    {
        [$link, , $item] = $this->restaurant($this->timing());

        foreach ([null, '', 'asap'] as $value) {
            $this->order($link, $item, ['fulfilment' => 'takeaway', 'wanted_at' => $value])->assertCreated();
        }

        $this->assertTrue(RestaurantOrder::latest('id')->first()->wanted_at->equalTo(Carbon::parse($slot['value'])));
    }

    public function test_a_slot_the_menu_offers_is_kept(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00', self::TZ));
        [$link, $menu, $item] = $this->restaurant(
            $this->timing() + ['fulfilment_modes' => ['dine_in', 'takeaway']]
        );

        $slot = MenuHandoverTiming::slots((array) $menu->settings, self::TZ)[2];
        $this->order($link, $item, ['fulfilment' => 'takeaway', 'wanted_at' => $slot['value']])->assertCreated();

        $saved = RestaurantOrder::latest('id')->first()->wanted_at;
        $this->assertNotNull($saved);
        $this->assertSame($slot['value'], $saved->copy()->utc()->toIso8601String());
    }

    public function test_a_time_the_menu_does_not_offer_is_refused_not_rounded(): void
    {
        // Snapping 7:30 to 8:00 is how somebody turns up half an hour early
        // to a bag that is not ready.
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00', self::TZ));
        [$link, , $item] = $this->restaurant(
            $this->timing() + ['fulfilment_modes' => ['dine_in', 'takeaway']]
        );

        $res = $this->order($link, $item, [
            'fulfilment' => 'takeaway',
            'wanted_at'  => Carbon::parse('2026-10-05 13:07', self::TZ)->utc()->toIso8601String(),
        ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('no longer available', $res->json('error.message'));
    }

    public function test_a_time_in_the_past_is_refused(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00', self::TZ));
        [$link, , $item] = $this->restaurant(
            $this->timing() + ['fulfilment_modes' => ['dine_in', 'takeaway']]
        );

        $this->order($link, $item, [
            'fulfilment' => 'takeaway',
            'wanted_at'  => Carbon::parse('2026-10-05 10:00', self::TZ)->utc()->toIso8601String(),
        ])->assertStatus(422);
    }

    public function test_dine_in_can_book_a_future_time(): void
    {
        // Dine-in reservations use the same validated slots as delivery and pickup.
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00', self::TZ));
        [$link, $menu, $item] = $this->restaurant($this->timing());
        $slot = MenuHandoverTiming::slots((array) $menu->settings, self::TZ)[0];

        $this->order($link, $item, ['fulfilment' => 'dine_in', 'wanted_at' => $slot['value']])->assertCreated();

        $this->assertTrue(RestaurantOrder::latest('id')->first()->wanted_at->equalTo(Carbon::parse($slot['value'])));
    }

    public function test_timing_switched_off_ignores_a_time_rather_than_refusing(): void
    {
        [$link, , $item] = $this->restaurant(['fulfilment_modes' => ['takeaway']]);

        $this->order($link, $item, [
            'fulfilment' => 'takeaway',
            'wanted_at'  => Carbon::now()->addHour()->toIso8601String(),
        ])->assertCreated();

        $this->assertTrue(RestaurantOrder::latest('id')->first()->wanted_at->equalTo(Carbon::parse($slot['value'])));
    }

    public function test_the_store_takes_a_time_the_same_way(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00', self::TZ));
        [$link, $menu, $product] = $this->store(
            $this->timing() + ['fulfilment_modes' => ['takeaway', 'delivery']]
        );
        $slot = MenuHandoverTiming::slots((array) $menu->settings, self::TZ)[1];

        $this->postJson('/sm/'.$link->alias.'/order', [
            'customer_name' => 'Sana', 'customer_phone' => '9840012345',
            'fulfilment' => 'takeaway', 'wanted_at' => $slot['value'],
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->assertNotNull(StoreOrder::latest('id')->first()->wanted_at);
    }

    // ===== The table box that did nothing ===============================

    public function test_a_typed_table_number_is_no_longer_thrown_away(): void
    {
        // It was on the page, a guest could type into it, and only
        // `table_code` was ever sent.
        [$link, $menu, $item] = $this->restaurant();
        RestaurantTable::create(['menu_id' => $menu->id, 'label' => '4', 'sort_order' => 0]);

        $this->order($link, $item, ['table_label' => '4'])->assertCreated();

        $order = RestaurantOrder::latest('id')->first();
        $this->assertSame('4', $order->table_label);
        $this->assertNotNull($order->table_id, 'A real table was not matched to the number typed.');
    }

    public function test_a_table_that_does_not_exist_is_still_recorded_as_written(): void
    {
        // Better to tell the kitchen "the guest said table 9" than to
        // silently call them a walk-in.
        [$link, , $item] = $this->restaurant();

        $this->order($link, $item, ['table_label' => 'Patio 2'])->assertCreated();

        $order = RestaurantOrder::latest('id')->first();
        $this->assertSame('Patio 2', $order->table_label);
        $this->assertNull($order->table_id);
    }

    public function test_the_qr_code_still_wins_over_anything_typed(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $table = RestaurantTable::create(['menu_id' => $menu->id, 'label' => '7', 'sort_order' => 0]);

        $this->order($link, $item, ['table_code' => $table->code, 'table_label' => '2'])->assertCreated();

        $this->assertSame('7', RestaurantOrder::latest('id')->first()->table_label);
    }

    public function test_the_page_offers_the_tables_the_menu_has(): void
    {
        [$link, $menu, ] = $this->restaurant();
        foreach (['1', '2', 'Patio 3'] as $i => $label) {
            RestaurantTable::create(['menu_id' => $menu->id, 'label' => $label, 'sort_order' => $i]);
        }

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        $this->assertStringContainsString('<select class="field" id="fTable">', $html,
            'The table box is still free text on a menu that has tables.');
        $this->assertStringContainsString('Table Patio 3', $html);
    }

    public function test_a_menu_with_no_tables_keeps_a_box_to_type_in(): void
    {
        [$link, , ] = $this->restaurant();

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        $this->assertStringContainsString('<input class="field" id="fTable"', $html);
    }

    // ===== The delivery-only menu that could not be ordered from ========

    public function test_a_single_mode_menu_shows_the_fields_that_mode_needs(): void
    {
        // The radios only render when a menu offers more than one mode, so
        // setFulfilment never fired and the address box stayed hidden --
        // while the server went on requiring an address.
        foreach (['restaurant-menu', 'store-menu'] as $view) {
            $src = file_get_contents(resource_path('views/common/'.$view.'.blade.php'));

            $this->assertStringContainsString('paintFulfilment(){', $src,
                "common/{$view} still only reveals its fields on a change event.");
            $this->assertMatchesRegularExpression('/\n\s+(RM|SM)\.paintFulfilment\(\);/', $src,
                "common/{$view} never paints the handover fields at startup.");
        }
    }

    public function test_a_delivery_only_menu_renders_its_address_box(): void
    {
        [$link, , ] = $this->restaurant(['fulfilment_modes' => ['delivery']]);

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        $this->assertStringContainsString('id="fAddress"', $html);
        // And no radio row, which is the condition that caused the bug.
        $this->assertStringNotContainsString('name="ful"', $html);
    }

    // ===== Both editors, and the orders screen ==========================

    public function test_both_editors_offer_the_card(): void
    {
        foreach ([['restaurant', $this->restaurant()], ['store', $this->store()]] as [$kind, $made]) {
            [$link, , ] = $made;

            $html = $this->actingAs($this->owner)
                ->get('/user/links/'.$link->id.'/'.$kind)->assertOk()->getContent();

            $this->assertStringContainsString('Collection and delivery times', $html,
                "The {$kind} editor has no timing card.");
            $this->assertStringContainsString('timing_prep', $html,
                "The {$kind} editor never sends the prep time.");
            $this->assertStringContainsString('timingApplies()', $html,
                "The {$kind} editor shows the card on a dine-in-only menu.");
        }
    }

    public function test_the_owner_can_save_it(): void
    {
        foreach ([['restaurant', $this->restaurant()], ['store', $this->store()]] as [$kind, $made]) {
            [$link, $menu, ] = $made;

            $this->actingAs($this->owner)
                ->postJson('/user/links/'.$link->id.'/'.$kind.'/settings', [
                    'mode' => 'order', 'currency' => 'INR',
                    'timing_enabled' => true, 'timing_interval' => 15,
                    'timing_open' => '11:30', 'timing_close' => '23:00', 'timing_prep' => 45,
                ])->assertOk();

            $saved = MenuHandoverTiming::resolve((array) $menu->fresh()->settings);
            $this->assertTrue($saved['enabled'], "The {$kind} menu did not save it.");
            $this->assertSame(15, $saved['interval']);
            $this->assertSame('11:30', $saved['open']);
            $this->assertSame(45, $saved['prep_minutes']);
        }
    }

    public function test_the_kitchen_screen_shows_the_time_that_was_asked_for(): void
    {
        foreach (['restaurant', 'store'] as $kind) {
            $src = file_get_contents(resource_path('views/user/links/'.$kind.'/orders.blade.php'));

            $this->assertStringContainsString('wantedLabel(o.wanted_at)', $src,
                "The {$kind} orders screen does not show when an order is wanted.");
            $this->assertStringContainsString("'wanted_at'=>\$o->wanted_at", $src);
            $this->assertStringContainsString('wanted_at:o.wanted_at,', $src,
                "A polled {$kind} order loses its requested time.");
        }
    }

    public function test_the_guest_can_read_their_own_time_back(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00', self::TZ));
        [$link, $menu, $item] = $this->restaurant(
            $this->timing() + ['fulfilment_modes' => ['dine_in', 'takeaway']]
        );
        $slot = MenuHandoverTiming::slots((array) $menu->settings, self::TZ)[0];

        $placed = $this->order($link, $item, ['fulfilment' => 'takeaway', 'wanted_at' => $slot['value']])
            ->assertCreated()->json('data.order');

        $this->assertNotNull($placed['wanted_at']);
        $this->assertSame(
            $placed['wanted_at'],
            $this->getJson('/rm/order/'.$placed['public_token'].'/status')
                ->assertOk()->json('data.order.wanted_at')
        );
    }

    public function test_describe_says_as_soon_as_possible_for_nothing(): void
    {
        $this->assertSame('As soon as possible', MenuHandoverTiming::describe(null, self::TZ));

        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00', self::TZ));
        $at = Carbon::parse('2026-10-05 19:30', self::TZ)->utc();
        $this->assertSame('Today at 7:30 pm', MenuHandoverTiming::describe($at, self::TZ));
    }
    public function test_slots_reach_the_seventh_day_even_with_quarter_hour_intervals(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00', self::TZ));
        $slots = MenuHandoverTiming::slots($this->timing(['interval' => 15]), self::TZ);
        $this->assertTrue(Carbon::parse(end($slots)['value'])->timezone(self::TZ)->isSameDay(Carbon::now(self::TZ)->addDays(7)));
    }

    public function test_coupon_prebooking_is_single_use_and_works_for_both_menu_types(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00', self::TZ));
        foreach (['restaurant', 'store'] as $kind) {
            [$link, $menu, $item] = $kind === 'restaurant' ? $this->restaurant($this->timing()) : $this->store($this->timing());
            $model = $kind === 'restaurant' ? RestaurantOrder::class : StoreOrder::class;
            $purchase = $model::create(['menu_id' => $menu->id, 'link_id' => $link->id, 'status' => 'new', 'subtotal' => 100, 'total' => 100, 'currency' => 'INR']);
            $coupon = \App\Modules\User\Models\MenuOrderCoupon::create([
                'code' => \App\Modules\User\Models\MenuOrderCoupon::mint(), 'order_type' => $kind,
                'order_id' => $purchase->id, 'menu_type' => $kind, 'menu_id' => $menu->id,
                'item_name' => $item->name, 'status' => 'issued',
            ]);
            $slot = MenuHandoverTiming::slots((array) $menu->settings, self::TZ)[0];
            $payload = ['coupon_code' => $coupon->code, 'customer_name' => 'Guest', 'fulfilment' => $kind === 'restaurant' ? 'dine_in' : 'takeaway', 'wanted_at' => $slot['value']];
            $service = app(\App\Modules\Common\Services\MenuCouponPrebooking::class);
            $booking = $service->reserve($link, $menu, $kind, $payload);
            $this->assertSame('0.00', $booking->total);
            $this->assertSame(1, $booking->items()->count());
            $this->assertSame('issued', $coupon->fresh()->status);
            try {
                $service->reserve($link, $menu, $kind, $payload);
                $this->fail('A coupon must not have two active bookings.');
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('already has a booking', $e->getMessage());
            }
            $booking->update(['status' => 'cancelled']);
            $replacement = $service->reserve($link, $menu, $kind, $payload);
            $this->assertNotEquals($booking->id, $replacement->id);
            $coupon->update(['status' => 'redeemed']);
            try {
                $service->reserve($link, $menu, $kind, $payload);
                $this->fail('A redeemed coupon must not be bookable.');
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('Coupon unavailable', $e->getMessage());
            }
        }
    }

}
