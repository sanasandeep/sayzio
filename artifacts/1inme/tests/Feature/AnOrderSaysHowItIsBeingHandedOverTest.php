<?php

namespace Tests\Feature;

use App\Modules\Common\Services\WhatsappOrderLink;
use App\Modules\User\Models\Link;
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
use App\Modules\User\Support\MenuFulfilment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sana, 2026-09-23: "adding of address form, delivery options and other
 * settings configurable" -- and, from the list before it, the charges a
 * real restaurant puts on a bill: parcel, delivery, service.
 *
 * ---- Why charges are a list ---------------------------------------------
 *
 * The obvious build is three fields, and it runs out within a week: a
 * restaurant wants packing AND delivery on the same order, and the next
 * one wants a late-night surcharge. So a charge is a row -- a name, a flat
 * or percentage amount, and the handovers it applies to.
 *
 * ---- What actually needs guarding ---------------------------------------
 *
 * Everything below is one question asked several ways: does the number the
 * customer is shown equal the number the owner is told and the number
 * stored on the order? Three surfaces, one calculation, and they have to
 * agree even though the customer can change the handover after seeing a
 * total.
 */
class AnOrderSaysHowItIsBeingHandedOverTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->user);
    }

    /** A restaurant in order mode with one Rs.100 item. */
    private function restaurant(array $menuSettings = []): array
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
            'settings' => $menuSettings,
        ]);
        $cat = RestaurantMenuCategory::create([
            'menu_id' => $menu->id, 'name' => 'Dosas', 'sort_order' => 0, 'is_active' => true,
        ]);
        $item = RestaurantMenuItem::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Masala Dosa',
            'price' => 100, 'sort_order' => 0, 'is_active' => true,
        ]);

        return [$link, $menu, $item];
    }

    private function store(array $menuSettings = []): array
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
            'settings' => $menuSettings,
        ]);
        $cat = StoreCategory::create(['menu_id' => $menu->id, 'name' => 'Mugs', 'sort_order' => 0, 'is_active' => true]);
        $product = StoreProduct::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Big Mug',
            'price' => 100, 'sort_order' => 0, 'is_active' => true,
        ]);

        return [$link, $menu, $product];
    }

    /** A menu offering all three handovers, with a parcel and a delivery fee. */
    private function withCharges(): array
    {
        return [
            'whatsapp_number'  => '919876543210',
            'fulfilment_modes' => ['dine_in', 'takeaway', 'delivery'],
            'charges' => [
                ['label' => 'Parcel charge',   'type' => 'fixed',   'amount' => 20, 'modes' => ['takeaway', 'delivery']],
                ['label' => 'Delivery charge', 'type' => 'fixed',   'amount' => 50, 'modes' => ['delivery']],
                ['label' => 'Service charge',  'type' => 'percent', 'amount' => 10, 'modes' => ['dine_in']],
            ],
        ];
    }

    // ===== 1. Nothing changes for a menu nobody has configured =====

    /**
     * The one that matters. Every existing order menu has no fulfilment
     * setting and no charges, and its bill must come to exactly what it
     * came to yesterday.
     */
    public function test_a_menu_that_was_never_configured_bills_what_it_always_did(): void
    {
        [$link, $menu, $item] = $this->restaurant();

        $res = $this->postJson('/rm/'.$link->alias.'/order', [
            'items' => [['item_id' => $item->id, 'quantity' => 2]],
        ])->assertCreated();

        $this->assertSame('200.00', (string) $res->json('data.order.total'));
        $this->assertSame(0.0, (float) $res->json('data.order.charges_amount'));
    }

    /** And it still offers exactly one way to order, rather than none. */
    public function test_an_unconfigured_menu_defaults_to_its_obvious_handover(): void
    {
        $this->assertSame(['dine_in'], MenuFulfilment::modesFor([], true));
        $this->assertSame(['takeaway'], MenuFulfilment::modesFor([], false));

        // Turning everything off is not a way to make a menu unorderable.
        $this->assertSame(['dine_in'], MenuFulfilment::modesFor(['fulfilment_modes' => []], true));
    }

    /** A store never gets offered Dine in. */
    public function test_a_store_is_not_offered_dine_in(): void
    {
        $modes = MenuFulfilment::modesFor(['fulfilment_modes' => ['dine_in', 'delivery']], false);

        $this->assertNotContains('dine_in', $modes);
        $this->assertContains('delivery', $modes);
    }

    // ===== 2. Charges reach the bill =====

    public function test_a_flat_charge_is_added_for_the_handover_it_names(): void
    {
        [$link, $menu, $item] = $this->restaurant($this->withCharges());

        $res = $this->postJson('/rm/'.$link->alias.'/order', [
            'items' => [['item_id' => $item->id, 'quantity' => 2]],
            'fulfilment' => 'takeaway',
        ])->assertCreated();

        // 200 + 20 parcel. The delivery charge does not apply to takeaway.
        $this->assertSame('220.00', (string) $res->json('data.order.total'));
    }

    public function test_two_charges_can_apply_to_the_same_order(): void
    {
        [$link, $menu, $item] = $this->restaurant($this->withCharges());

        $res = $this->postJson('/rm/'.$link->alias.'/order', [
            'items' => [['item_id' => $item->id, 'quantity' => 2]],
            'fulfilment' => 'delivery', 'customer_address' => '12 MG Road, Bengaluru',
        ])->assertCreated();

        // 200 + 20 parcel + 50 delivery. Three hardcoded fields could not
        // have expressed this.
        $this->assertSame('270.00', (string) $res->json('data.order.total'));
        $this->assertCount(2, $res->json('data.order.charges'));
    }

    public function test_a_percentage_charge_is_taken_on_the_food_bill(): void
    {
        [$link, $menu, $item] = $this->restaurant($this->withCharges());

        $res = $this->postJson('/rm/'.$link->alias.'/order', [
            'items' => [['item_id' => $item->id, 'quantity' => 2]],
            'fulfilment' => 'dine_in',
        ])->assertCreated();

        $this->assertSame('220.00', (string) $res->json('data.order.total'));
    }

    /**
     * Charges land after tax, and the percentage is taken on the discounted
     * subtotal rather than the taxed total. Written down rather than
     * assumed -- see MenuFulfilment's docblock, it is a tax question.
     */
    public function test_charges_are_added_after_tax_not_taxed_themselves(): void
    {
        [$link, $menu, $item] = $this->restaurant(array_merge($this->withCharges(), [
            'tax' => ['enabled' => true, 'rate' => 5, 'inclusive' => false, 'label' => 'GST'],
        ]));

        $res = $this->postJson('/rm/'.$link->alias.'/order', [
            'items' => [['item_id' => $item->id, 'quantity' => 2]],
            'fulfilment' => 'takeaway',
        ])->assertCreated();

        // 200 food + 10 GST on the food + 20 parcel = 230.
        // If the parcel charge were taxed it would be 231.
        $this->assertSame('10.00', (string) $res->json('data.order.tax_amount'));
        $this->assertSame('230.00', (string) $res->json('data.order.total'));
    }

    /**
     * And the base a PERCENTAGE charge is taken on is the food bill, not
     * the taxed total. "10% service charge" means ten percent of what the
     * food cost; taking it on the tax as well is a quietly larger bill.
     */
    public function test_a_percentage_charge_ignores_the_tax_when_working_out_its_base(): void
    {
        [$link, $menu, $item] = $this->restaurant(array_merge($this->withCharges(), [
            'tax' => ['enabled' => true, 'rate' => 5, 'inclusive' => false, 'label' => 'GST'],
        ]));

        $res = $this->postJson('/rm/'.$link->alias.'/order', [
            'items' => [['item_id' => $item->id, 'quantity' => 2]],
            'fulfilment' => 'dine_in',
        ])->assertCreated();

        // 200 food + 10 GST + 20 service (10% of 200) = 230.
        // 10% of the taxed 210 would be 21, and a total of 231.
        $this->assertSame('20.00', (string) $res->json('data.order.charges_amount'));
        $this->assertSame('230.00', (string) $res->json('data.order.total'));
    }

    /** The store gets the same treatment. */
    public function test_a_store_order_carries_its_charges(): void
    {
        [$link, $menu, $product] = $this->store([
            'fulfilment_modes' => ['takeaway', 'delivery'],
            'charges' => [['label' => 'Delivery', 'type' => 'fixed', 'amount' => 60, 'modes' => ['delivery']]],
        ]);

        $res = $this->postJson('/sm/'.$link->alias.'/order', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'fulfilment' => 'delivery', 'customer_address' => '12 MG Road',
        ])->assertCreated();

        $this->assertSame('160.00', (string) $res->json('data.order.total'));
    }

    // ===== 3. The customer is shown the number they will be charged =====

    /**
     * The quote and the order are the same calculation, and they have to
     * stay that way even though the customer picks the handover AFTER
     * seeing a total.
     */
    public function test_the_quote_matches_what_the_order_is_stored_with(): void
    {
        [$link, $menu, $item] = $this->restaurant($this->withCharges());
        $cart = [['item_id' => $item->id, 'quantity' => 2]];

        $quoted = $this->postJson('/rm/'.$link->alias.'/quote', [
            'items' => $cart, 'fulfilment' => 'delivery',
        ])->assertOk()->json('data.bill.total');

        $placed = $this->postJson('/rm/'.$link->alias.'/order', [
            'items' => $cart, 'fulfilment' => 'delivery', 'customer_address' => '12 MG Road',
        ])->assertCreated()->json('data.order.total');

        $this->assertSame((float) $quoted, (float) $placed);
    }

    /**
     * The store gained a quote endpoint for exactly one reason: which
     * charges apply depends on the handover, and having the browser work
     * that out would be a second copy of a rule the server owns.
     */
    public function test_the_store_quotes_its_charges_rather_than_letting_the_browser_guess(): void
    {
        [$link, $menu, $product] = $this->store([
            'fulfilment_modes' => ['takeaway', 'delivery'],
            'charges' => [['label' => 'Delivery', 'type' => 'fixed', 'amount' => 60, 'modes' => ['delivery']]],
        ]);
        $cart = [['product_id' => $product->id, 'quantity' => 1]];

        $pickup = $this->postJson('/sm/'.$link->alias.'/quote', ['items' => $cart, 'fulfilment' => 'takeaway'])
            ->assertOk()->json('data.bill.total');
        $delivery = $this->postJson('/sm/'.$link->alias.'/quote', ['items' => $cart, 'fulfilment' => 'delivery'])
            ->assertOk()->json('data.bill.total');

        $this->assertSame(100.0, (float) $pickup);
        $this->assertSame(160.0, (float) $delivery);
    }

    // ===== 4. The address =====

    public function test_delivery_needs_an_address(): void
    {
        [$link, $menu, $item] = $this->restaurant($this->withCharges());

        $this->postJson('/rm/'.$link->alias.'/order', [
            'items' => [['item_id' => $item->id, 'quantity' => 1]],
            'fulfilment' => 'delivery',
        ])->assertStatus(422);
    }

    /** And the other handovers do not, or every guest at a table types one. */
    public function test_dine_in_does_not_ask_for_an_address(): void
    {
        [$link, $menu, $item] = $this->restaurant($this->withCharges());

        $this->postJson('/rm/'.$link->alias.'/order', [
            'items' => [['item_id' => $item->id, 'quantity' => 1]],
            'fulfilment' => 'dine_in',
        ])->assertCreated();
    }

    /** An address sent with a handover that does not need one is dropped. */
    public function test_an_address_is_not_kept_for_a_handover_that_has_no_use_for_it(): void
    {
        [$link, $menu, $item] = $this->restaurant($this->withCharges());

        $this->postJson('/rm/'.$link->alias.'/order', [
            'items' => [['item_id' => $item->id, 'quantity' => 1]],
            'fulfilment' => 'takeaway', 'customer_address' => '12 MG Road',
        ])->assertCreated();

        $this->assertNull(RestaurantOrder::latest('id')->first()->customer_address);
    }

    // ===== 5. What the owner is told =====

    public function test_the_whatsapp_message_says_how_and_where(): void
    {
        [$link, $menu, $item] = $this->restaurant($this->withCharges());

        $this->postJson('/rm/'.$link->alias.'/order', [
            'items' => [['item_id' => $item->id, 'quantity' => 2]],
            'fulfilment' => 'delivery', 'customer_address' => '12 MG Road, Bengaluru',
        ])->assertCreated();

        $order = RestaurantOrder::with('items')->latest('id')->first();
        $message = WhatsappOrderLink::build($menu, $order, $link->title)['message'];

        $this->assertStringContainsString('How: Delivery', $message);
        $this->assertStringContainsString('Address: 12 MG Road, Bengaluru', $message,
            'a delivery order without the address is useless to the person reading it');
        $this->assertStringContainsString('Parcel charge: INR 20.00', $message);
        $this->assertStringContainsString('Delivery charge: INR 50.00', $message);
        $this->assertStringContainsString('Total: INR 270.00', $message);
    }

    /** A store says Pickup where a restaurant says Takeaway. */
    public function test_each_page_type_uses_its_own_word_for_the_same_handover(): void
    {
        $this->assertSame('Takeaway', MenuFulfilment::label('takeaway', true));
        $this->assertSame('Pickup', MenuFulfilment::label('takeaway', false));
    }

    // ===== 6. The order keeps its own copy =====

    /**
     * A charge lives in the menu's settings and the owner WILL edit it. An
     * order that stored only a total would be unexplainable a week later,
     * and an unexplainable line is the one a customer rings up about.
     */
    public function test_an_order_still_adds_up_after_the_owner_edits_the_charge(): void
    {
        [$link, $menu, $item] = $this->restaurant($this->withCharges());

        $this->postJson('/rm/'.$link->alias.'/order', [
            'items' => [['item_id' => $item->id, 'quantity' => 2]],
            'fulfilment' => 'delivery', 'customer_address' => '12 MG Road',
        ])->assertCreated();

        // The owner doubles the delivery fee and deletes the parcel charge.
        $menu->update(['settings' => array_merge($menu->settings, [
            'charges' => [['label' => 'Delivery charge', 'type' => 'fixed', 'amount' => 100, 'modes' => ['delivery']]],
        ])]);

        $order = RestaurantOrder::latest('id')->first();

        $this->assertSame('270.00', (string) $order->total);
        $this->assertCount(2, $order->charges);
        $this->assertSame('Parcel charge', $order->charges[0]['label']);
    }

    // ===== 7. Junk =====

    public function test_a_malformed_charge_is_dropped_rather_than_repaired(): void
    {
        $charges = MenuFulfilment::charges(['charges' => [
            ['label' => '',              'type' => 'fixed',   'amount' => 20,  'modes' => ['delivery']],
            ['label' => 'No modes',      'type' => 'fixed',   'amount' => 20,  'modes' => []],
            ['label' => 'Negative',      'type' => 'fixed',   'amount' => -5,  'modes' => ['delivery']],
            ['label' => 'Over a tonne',  'type' => 'percent', 'amount' => 900, 'modes' => ['delivery']],
            ['label' => 'Good one',      'type' => 'fixed',   'amount' => 20,  'modes' => ['delivery']],
        ]]);

        $this->assertCount(1, $charges);
        $this->assertSame('Good one', $charges[0]['label']);
    }

    public function test_an_invented_handover_falls_back_rather_than_being_stored(): void
    {
        [$link, $menu, $item] = $this->restaurant($this->withCharges());

        $this->postJson('/rm/'.$link->alias.'/order', [
            'items' => [['item_id' => $item->id, 'quantity' => 1]],
            'fulfilment' => '../../etc/passwd',
        ])->assertCreated();

        $this->assertSame('dine_in', RestaurantOrder::latest('id')->first()->fulfilment);
    }

    // ===== 8. Saving, and the editor =====

    public function test_the_settings_can_be_saved_from_the_editor(): void
    {
        [$link, $menu] = $this->restaurant();

        $this->actingAs($this->user)
            ->postJson('/user/links/'.$link->id.'/restaurant/settings', [
                'mode' => 'order', 'currency' => 'INR',
                'fulfilment_modes' => ['takeaway', 'delivery'],
                'charges' => [['label' => 'Delivery', 'type' => 'fixed', 'amount' => 50, 'modes' => ['delivery']]],
            ])->assertOk();

        $settings = $menu->fresh()->settings;

        $this->assertSame(['takeaway', 'delivery'], $settings['fulfilment_modes']);
        $this->assertSame('Delivery', $settings['charges'][0]['label']);
    }

    public function test_both_editors_offer_the_handovers_and_the_charges(): void
    {
        foreach ([[$this->restaurant()[0], 'restaurant'], [$this->store()[0], 'store']] as [$link, $kind]) {
            $html = $this->actingAs($this->user)
                ->get('/user/links/'.$link->id.'/'.$kind)->assertOk()->getContent();

            $this->assertStringContainsString('x-model="menu.fulfilment_modes"', $html);
            $this->assertStringContainsString('@click="addCharge()"', $html);
        }
    }

    /** Dine in is a restaurant word; the store editor must not offer it. */
    public function test_the_store_editor_does_not_offer_dine_in(): void
    {
        $html = $this->actingAs($this->user)
            ->get('/user/links/'.$this->store()[0]->id.'/store')->assertOk()->getContent();

        $this->assertStringContainsString('value="delivery"', $html);
        $this->assertStringNotContainsString('value="dine_in"', $html);
    }

    // ===== 9. Guard =====

    /**
     * The charge rule lives on the server. If either public page starts
     * working out which charges apply in JavaScript, the number the
     * customer sees and the number they are billed can differ -- which is
     * the entire class of bug this week has been about.
     */
    public function test_neither_page_decides_charges_in_the_browser(): void
    {
        foreach (['restaurant-menu', 'store-menu'] as $view) {
            $blade = file_get_contents(resource_path('views/common/'.$view.'.blade.php'));

            $this->assertStringNotContainsString('charge.modes', $blade,
                $view.' is deciding which charges apply in the browser');
            $this->assertStringContainsString('QUOTE_URL', $blade,
                $view.' must ask the server what the total is');
        }
    }
}
