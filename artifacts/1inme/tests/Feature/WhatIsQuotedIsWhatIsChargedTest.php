<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\MenuItemOptionGroup;
use App\Modules\User\Models\MenuOption;
use App\Modules\User\Models\MenuOptionGroup;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuCategory;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\RestaurantOrder;
use App\Modules\User\Models\StoreCategory;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreOrder;
use App\Modules\User\Models\StoreProduct;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The number a guest is shown and the number they are charged.
 *
 * Those were computed in four separate places -- the restaurant's quote,
 * the restaurant's order, the store's quote and the store's order -- and
 * they agreed only because nobody had changed one of them yet. Choices are
 * exactly the change that breaks that: an item's price stops being its
 * price and becomes its price plus whatever was picked, and four
 * implementations of "plus whatever was picked" is four chances to charge a
 * number that was never on screen.
 *
 * All four go through MenuCartPricer now. This test is the claim: quote a
 * cart, order the same cart, and get the same number, on both page types,
 * with the rules enforced server-side either way.
 */
class WhatIsQuotedIsWhatIsChargedTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->user);
    }

    /** @return array{0: Link, 1: RestaurantMenu, 2: RestaurantMenuItem} */
    private function restaurant(): array
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'),
            'title' => 'Priyumm Tiffins', 'is_active' => true,
        ]);
        $menu = RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'order', 'currency' => 'INR', 'settings' => [],
        ]);
        $cat = RestaurantMenuCategory::create([
            'menu_id' => $menu->id, 'name' => 'Starters', 'sort_order' => 0, 'is_active' => true,
        ]);
        $item = RestaurantMenuItem::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => 'Chilli Paneer', 'price' => 90, 'sort_order' => 0, 'is_active' => true,
        ]);

        return [$link, $menu, $item];
    }

    /**
     * A group on an item, with its choices.
     *
     * @param  array<int, array{0:string, 1:float}>  $options
     * @return array<string, MenuOption>  by name
     */
    private function choices($menu, $item, string $name, array $rules, array $options): array
    {
        $group = MenuOptionGroup::create(array_merge([
            'menu_type' => MenuOptionGroup::typeFor($menu),
            'menu_id' => $menu->id, 'name' => $name,
            'sort_order' => 0, 'is_active' => true,
        ], $rules));

        $made = [];
        foreach ($options as $i => [$optName, $delta]) {
            $made[$optName] = MenuOption::create([
                'group_id' => $group->id, 'name' => $optName,
                'price_delta' => $delta, 'sort_order' => $i, 'is_active' => true,
            ]);
        }

        MenuItemOptionGroup::create([
            'group_id' => $group->id,
            'owner_type' => MenuItemOptionGroup::typeFor($item),
            'owner_id' => $item->id,
            'sort_order' => 0,
        ]);

        return $made;
    }

    // ===== The restaurant ================================================

    public function test_the_restaurant_quotes_and_charges_the_same_number(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $opts = $this->choices($menu, $item, 'Add-ons',
            ['min_select' => 0, 'max_select' => null, 'max_per_option' => 3],
            [['Extra paneer', 30], ['Extra sauce', 10]]
        );

        // 2 x (90 base + 30 paneer + 2x10 sauce) = 2 x 140 = 280
        $cart = [[
            'item_id' => $item->id, 'quantity' => 2,
            'options' => [
                ['option_id' => $opts['Extra paneer']->id, 'quantity' => 1],
                ['option_id' => $opts['Extra sauce']->id, 'quantity' => 2],
            ],
        ]];

        $quoted = $this->postJson('/rm/'.$link->alias.'/quote', ['items' => $cart])
            ->assertOk()->json('data.bill.total');

        $charged = $this->postJson('/rm/'.$link->alias.'/order', [
            'customer_name' => 'Test Guest',
            'customer_phone' => '9840012345','items' => $cart])
            ->assertCreated()->json('data.order.total');

        $this->assertSame(280.0, (float) $quoted);
        $this->assertSame((float) $quoted, (float) $charged,
            'The guest was quoted one number and charged another.');
    }

    public function test_the_order_line_remembers_what_was_chosen(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $opts = $this->choices($menu, $item, 'Spice level',
            ['is_required' => true, 'min_select' => 1, 'max_select' => 1],
            [['Mild', 0], ['Hot', 0]]
        );

        $this->postJson('/rm/'.$link->alias.'/order', [
            'customer_name' => 'Test Guest',
            'customer_phone' => '9840012345','items' => [[
            'item_id' => $item->id, 'quantity' => 1,
            'options' => [['option_id' => $opts['Hot']->id, 'quantity' => 1]],
        ]]])->assertCreated();

        $line = RestaurantOrder::latest('id')->first()->items()->first();

        $this->assertSame('Spice level', $line->options[0]['group']);
        $this->assertSame('Hot', $line->options[0]['name']);

        // A snapshot: renaming the choice does not rewrite last week's order.
        $opts['Hot']->update(['name' => 'Extra hot', 'price_delta' => 25]);
        $this->assertSame('Hot', $line->fresh()->options[0]['name']);
        $this->assertSame(90.0, (float) $line->fresh()->unit_price);
    }

    public function test_the_same_dish_with_different_choices_is_two_lines(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $opts = $this->choices($menu, $item, 'Spice level',
            ['is_required' => true, 'min_select' => 1, 'max_select' => 1],
            [['Mild', 0], ['Hot', 0]]
        );

        $this->postJson('/rm/'.$link->alias.'/order', [
            'customer_name' => 'Test Guest',
            'customer_phone' => '9840012345','items' => [
            ['item_id' => $item->id, 'quantity' => 1, 'options' => [['option_id' => $opts['Mild']->id]]],
            ['item_id' => $item->id, 'quantity' => 2, 'options' => [['option_id' => $opts['Hot']->id]]],
        ]])->assertCreated();

        $lines = RestaurantOrder::latest('id')->first()->items()->get();

        $this->assertCount(2, $lines, 'One mild and two hot is two lines, not one of three.');
        $this->assertSame('Mild', $lines[0]->options[0]['name']);
        $this->assertSame('Hot', $lines[1]->options[0]['name']);
    }

    // ===== The rules hold where the money is =============================

    public function test_a_required_choice_cannot_be_skipped_by_the_request(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $this->choices($menu, $item, 'Spice level',
            ['is_required' => true, 'min_select' => 1, 'max_select' => 1],
            [['Mild', 0]]
        );

        // The page enforces this too, but the page is not what decides.
        $this->postJson('/rm/'.$link->alias.'/order', [
            'customer_name' => 'Test Guest',
            'customer_phone' => '9840012345','items' => [
            ['item_id' => $item->id, 'quantity' => 1],
        ]])->assertStatus(422)
          ->assertJsonPath('error.message', 'Please choose a spice level for Chilli Paneer.');

        $this->assertSame(0, RestaurantOrder::count());
    }

    public function test_the_price_comes_from_the_database_not_the_request(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $opts = $this->choices($menu, $item, 'Size',
            ['is_required' => true, 'min_select' => 1, 'max_select' => 1],
            [['Family', 60]]
        );

        // Anything the browser says about money is ignored.
        $this->postJson('/rm/'.$link->alias.'/order', [
            'customer_name' => 'Test Guest',
            'customer_phone' => '9840012345','items' => [[
            'item_id' => $item->id, 'quantity' => 1,
            'options' => [['option_id' => $opts['Family']->id, 'quantity' => 1, 'price_delta' => -1000]],
        ]]])->assertCreated();

        $this->assertSame(150.0, (float) RestaurantOrder::latest('id')->first()->total);
    }

    public function test_a_sold_out_choice_stops_the_order(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $opts = $this->choices($menu, $item, 'Toppings',
            ['min_select' => 0, 'max_select' => 2],
            [['Cheese', 20]]
        );
        $opts['Cheese']->update(['is_sold_out' => true]);

        $this->postJson('/rm/'.$link->alias.'/order', [
            'customer_name' => 'Test Guest',
            'customer_phone' => '9840012345','items' => [[
            'item_id' => $item->id, 'quantity' => 1,
            'options' => [['option_id' => $opts['Cheese']->id]],
        ]]])->assertStatus(422);
    }

    public function test_the_quote_refuses_the_same_carts_the_order_does(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $this->choices($menu, $item, 'Spice level',
            ['is_required' => true, 'min_select' => 1, 'max_select' => 1],
            [['Mild', 0]]
        );

        // A cart that cannot be ordered must not be quoted a price either,
        // or the guest gets a total and then a refusal.
        $this->postJson('/rm/'.$link->alias.'/quote', ['items' => [
            ['item_id' => $item->id, 'quantity' => 1],
        ]])->assertStatus(422);
    }

    // ===== An item with no choices is untouched ==========================

    public function test_an_item_with_no_choices_costs_exactly_what_it_did(): void
    {
        [$link, , $item] = $this->restaurant();

        $quoted = $this->postJson('/rm/'.$link->alias.'/quote', ['items' => [
            ['item_id' => $item->id, 'quantity' => 3],
        ]])->assertOk()->json('data.bill.total');

        $this->postJson('/rm/'.$link->alias.'/order', [
            'customer_name' => 'Test Guest',
            'customer_phone' => '9840012345','items' => [
            ['item_id' => $item->id, 'quantity' => 3],
        ]])->assertCreated();

        $this->assertSame(270.0, (float) $quoted);
        $this->assertSame(270.0, (float) RestaurantOrder::latest('id')->first()->total);
        $this->assertNull(RestaurantOrder::latest('id')->first()->items()->first()->options);
    }

    // ===== The store, same rules =========================================

    public function test_the_store_quotes_and_charges_the_same_number_too(): void
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'store_menu',
            'alias' => 'sm'.fake()->unique()->numerify('#####'),
            'title' => 'The Shop', 'is_active' => true,
        ]);
        $menu = StoreMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'order', 'currency' => 'INR', 'settings' => [],
        ]);
        $cat = StoreCategory::create([
            'menu_id' => $menu->id, 'name' => 'Mugs', 'sort_order' => 0, 'is_active' => true,
        ]);
        $product = StoreProduct::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => 'Mug', 'price' => 450, 'sort_order' => 0, 'is_active' => true,
        ]);

        $opts = $this->choices($menu, $product, 'Size',
            ['is_required' => true, 'min_select' => 1, 'max_select' => 1],
            [['Standard', 0], ['Large', 80]]
        );

        $cart = [[
            'product_id' => $product->id, 'quantity' => 2,
            'options' => [['option_id' => $opts['Large']->id, 'quantity' => 1]],
        ]];

        $quoted = $this->postJson('/sm/'.$link->alias.'/quote', ['items' => $cart])
            ->assertOk()->json('data.bill.total');

        $this->postJson('/sm/'.$link->alias.'/order', [
            'customer_name' => 'Test Guest',
            'customer_phone' => '9840012345','items' => $cart])->assertCreated();

        // 2 x (450 + 80) = 1060
        $this->assertSame(1060.0, (float) $quoted);
        $this->assertSame(1060.0, (float) StoreOrder::latest('id')->first()->total);
        $this->assertSame('Large', StoreOrder::latest('id')->first()->items()->first()->options[0]['name']);
    }

    // ===== One pricer, not four ==========================================

    public function test_bulk_price_switches_at_the_coupon_threshold_and_keeps_options(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $item->update(['coupon_from' => 10, 'bulk_price' => 70]);
        $opts = $this->choices($menu, $item, 'Extra',
            ['is_required' => false, 'min_select' => 0, 'max_select' => 1], [['Cheese', 5]]);
        foreach ([9 => 855, 10 => 750, 11 => 825, 2 => 190] as $quantity => $total) {
            $rows = [['item_id' => $item->id, 'quantity' => $quantity,
                'options' => [['option_id' => $opts['Cheese']->id, 'quantity' => 1]]]];
            $this->assertSame((float) $total, (float) $this->postJson('/rm/'.$link->alias.'/quote', ['items' => $rows])
                ->assertOk()->json('data.bill.total'));
        }
        $rows = [['item_id' => $item->id, 'quantity' => 10]];
        $this->postJson('/rm/'.$link->alias.'/order', [
            'customer_name' => 'Bulk customer', 'customer_phone' => '+919840012345', 'items' => $rows,
        ])->assertCreated();
        $order = RestaurantOrder::latest('id')->first();
        $this->assertSame(700.0, (float) $order->total);
        $this->assertSame(70.0, (float) $order->items()->first()->unit_price);
        $this->assertDatabaseCount('menu_order_coupons', 10);
        $item->update(['bulk_price' => 0]);
        $this->assertSame(0.0, app(\App\Modules\Common\Services\MenuCartPricer::class)->price($menu, $rows)['subtotal']);
        $item->update(['coupon_from' => null]);
        $this->assertSame(900.0, app(\App\Modules\Common\Services\MenuCartPricer::class)->price($menu, $rows)['subtotal']);
    }

    public function test_staff_can_order_bulk_coupons_for_a_customer_on_a_display_menu(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $menu->update(['mode' => 'display']);
        $link->update(['visibility' => 'subscribers']);
        $item->update(['coupon_from' => 10, 'bulk_price' => 70]);
        app()->instance('workspace_owner', $this->user);
        $rows = [['item_id' => $item->id, 'quantity' => 10]];
        // A public caller cannot grant itself staff permissions through JSON.
        $this->postJson('/rm/'.$link->alias.'/order', [
            'staff_order_link' => $link->id, 'customer_name' => 'Customer',
            'customer_phone' => '+919840012345', 'items' => $rows,
        ])->assertUnauthorized();
        $this->actingAs($this->user)->get(route('user.links.restaurant.staff-order', $link))
            ->assertOk()->assertSee('Staff order for a customer');
        $this->postJson(route('user.links.restaurant.staff-order.quote', $link), ['items' => $rows])
            ->assertOk();
        $this->postJson(route('user.links.restaurant.staff-order.place', $link), [
            'customer_name' => 'Customer', 'customer_phone' => '+919840012345', 'items' => $rows,
        ])->assertCreated();
        $this->assertSame('Customer', RestaurantOrder::latest('id')->first()->customer_name);
        $this->assertSame('display', $menu->fresh()->mode);
        $this->assertDatabaseCount('menu_order_coupons', 10);
    }

    public function test_store_bulk_pricing_is_quoted_and_saved(): void
    {
        $link = Link::create(['user_id' => $this->user->id, 'type' => 'store_menu',
            'alias' => 'bulk'.fake()->unique()->numerify('#####'), 'title' => 'Bulk store', 'is_active' => true]);
        $menu = StoreMenu::create(['link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'order', 'currency' => 'INR', 'settings' => []]);
        $category = StoreCategory::create(['menu_id' => $menu->id, 'name' => 'Meals',
            'sort_order' => 0, 'is_active' => true]);
        $product = StoreProduct::create(['menu_id' => $menu->id, 'category_id' => $category->id,
            'name' => 'Lunch', 'price' => 100,
            'coupon_from' => 10, 'bulk_price' => 80, 'is_active' => true, 'sort_order' => 0]);
        $rows = [['product_id' => $product->id, 'quantity' => 10]];
        $quote = $this->postJson('/sm/'.$link->alias.'/quote', ['items' => $rows])->assertOk()->json('data.bill.total');
        $this->postJson('/sm/'.$link->alias.'/order', ['items' => $rows,
            'customer_name' => 'Customer', 'customer_phone' => '+919840012345'])->assertCreated();
        $order = StoreOrder::latest('id')->first();
        $this->assertSame(800.0, (float) $quote);
        $this->assertSame((float) $quote, (float) $order->total);
        $this->assertSame(80.0, (float) $order->items()->first()->unit_price);
    }

    public function test_nothing_prices_a_cart_except_the_pricer(): void
    {
        // The arithmetic that used to be written out in four places. If it
        // comes back in any of them, these two numbers start drifting again.
        foreach ([
            'app/Modules/Common/Controllers/PublicRestaurantController.php',
            'app/Modules/Common/Controllers/PublicStoreController.php',
            'app/Modules/Common/Services/RestaurantOrderService.php',
            'app/Modules/Common/Services/StoreOrderService.php',
        ] as $file) {
            $src = file_get_contents(base_path($file));

            $this->assertStringContainsString('MenuCartPricer', $src,
                $file.' does not price through the shared pricer.');
            $this->assertStringNotContainsString('->price) * max(1,', $src,
                $file.' is doing its own cart arithmetic again.');
        }
    }
}
