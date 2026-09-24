<?php

namespace Tests\Feature;

use App\Modules\Common\Services\WhatsappOrderLink;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuCategory;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\RestaurantOrder;
use App\Modules\User\Models\RestaurantOrderItem;
use App\Modules\User\Models\StoreCategory;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreProduct;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\MenuMoney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sana, 2026-09-23: "prices should have options like INR, [Rs.] USD or $
 * like that... before or after number..."
 *
 * Every price on these pages was the ISO code, a space, and two decimals.
 * Always. So an Indian restaurant could not write "Rs.120", which is how
 * every menu in India prints a price.
 *
 * ---- And the bug that came out of looking ------------------------------
 *
 * That one format was written out SIX times: two Blade closures on the
 * public pages, two JavaScript one-liners for the carts, the WhatsApp
 * message, and the owner's notification. Pulling them into one function
 * is what surfaced the sixth copy being wrong in a way nobody would have
 * gone looking for: the WhatsApp message says "Total" and printed the
 * SUBTOTAL. Tax and coupons shipped after that message format did.
 *
 * So for any order with GST on or a discount applied, the restaurant read
 * a smaller number off their phone than the guest owed -- and charged it.
 * The owner's email notification and the booking version of the same
 * message both already used `total ?: subtotal`; only this one was left
 * behind. That is most of what is tested below.
 */
class AMenuWritesItsPricesTheLocalWayTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->user);
    }

    private function restaurant(array $menuSettings = [], string $currency = 'INR'): array
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'),
            'title' => 'Priyumm Tiffins', 'is_active' => true,
        ]);
        $link->biolinkBlocks()->delete();

        $menu = RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'display', 'currency' => $currency, 'accent_color' => '#e0457b',
            'settings' => $menuSettings,
        ]);
        $cat = RestaurantMenuCategory::create([
            'menu_id' => $menu->id, 'name' => 'Dosas', 'sort_order' => 0, 'is_active' => true,
        ]);
        RestaurantMenuItem::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Masala Dosa',
            'price' => 120, 'sort_order' => 0, 'is_active' => true,
        ]);

        return [$link, $menu];
    }

    private function store(array $menuSettings = [], string $currency = 'INR'): array
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'store_menu',
            'alias' => 'sm'.fake()->unique()->numerify('#####'),
            'title' => 'The Shop', 'is_active' => true,
        ]);
        $link->biolinkBlocks()->delete();

        $menu = StoreMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'display', 'currency' => $currency, 'accent_color' => '#e0457b',
            'settings' => $menuSettings,
        ]);
        $cat = StoreCategory::create(['menu_id' => $menu->id, 'name' => 'Mugs', 'sort_order' => 0, 'is_active' => true]);
        StoreProduct::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Big Mug',
            'price' => 450, 'sort_order' => 0, 'is_active' => true,
        ]);

        return [$link, $menu];
    }

    private function page(Link $link): string
    {
        return $this->get('/'.$link->alias)->assertOk()->getContent();
    }

    /** A restaurant order with whatever bill figures the case needs. */
    private function order(RestaurantMenu $menu, Link $link, array $attrs = []): RestaurantOrder
    {
        $order = RestaurantOrder::create(array_merge([
            'menu_id' => $menu->id, 'link_id' => $link->id,
            'status' => 'new', 'customer_name' => 'Ravi',
            'subtotal' => 240, 'total' => 240, 'currency' => $menu->currency,
        ], $attrs));

        RestaurantOrderItem::create([
            'order_id' => $order->id, 'name' => 'Masala Dosa',
            'unit_price' => 120, 'quantity' => 2, 'line_total' => 240,
        ]);

        return $order->fresh('items');
    }

    // ===== 1. Nothing changes for a menu nobody has touched =====

    public function test_a_menu_that_never_chose_a_format_prints_what_it_always_did(): void
    {
        [$link] = $this->restaurant();

        $this->assertStringContainsString('INR 120.00', $this->page($link));
    }

    // ===== 2. The formats Sana asked for =====

    public function test_a_symbol_can_go_before_the_number(): void
    {
        [$link] = $this->restaurant(['price_display' => 'symbol']);

        $this->assertStringContainsString('Rs.120.00', $this->page($link));
    }

    public function test_decimals_can_be_dropped(): void
    {
        [$link] = $this->restaurant(['price_display' => 'symbol', 'price_decimals' => false]);

        $html = $this->page($link);

        $this->assertStringContainsString('Rs.120', $html);
        $this->assertStringNotContainsString('Rs.120.00', $html);
    }

    public function test_the_code_can_go_after_the_number(): void
    {
        [$link] = $this->restaurant(['price_position' => 'after'], 'EUR');

        $this->assertStringContainsString('120.00 EUR', $this->page($link));
    }

    public function test_the_number_can_stand_alone(): void
    {
        [$link] = $this->restaurant(['price_display' => 'none']);

        $html = $this->page($link);

        $this->assertStringContainsString('>120.00<', $html);
        $this->assertStringNotContainsString('INR 120', $html);
    }

    public function test_the_store_writes_prices_the_same_way(): void
    {
        [$link] = $this->store(['price_display' => 'symbol', 'price_decimals' => false]);

        $this->assertStringContainsString('Rs.450', $this->page($link));
    }

    /**
     * A currency with no minor unit gets no decimals whatever the setting
     * says. "¥1,200.00" is wrong, not a matter of taste.
     */
    public function test_a_currency_with_no_minor_unit_never_shows_decimals(): void
    {
        $fmt = MenuMoney::resolve('JPY', ['price_display' => 'symbol']);

        $this->assertSame(0, $fmt['decimals']);
        $this->assertSame('¥1,200', MenuMoney::format(1200, $fmt));
    }

    /** A currency with no symbol on file falls back to its code, not to junk. */
    public function test_an_unknown_currency_falls_back_to_its_code(): void
    {
        $fmt = MenuMoney::resolve('XYZ', ['price_display' => 'symbol']);

        $this->assertSame('XYZ 120.00', MenuMoney::format(120, $fmt));
    }

    /**
     * The gap is decided by the token's LAST character, not by whether it
     * has letters in it: "Rs." sits tight the way a card sets it, "INR"
     * would otherwise run into the digits.
     */
    public function test_a_word_token_takes_a_gap_and_a_glyph_does_not(): void
    {
        $this->assertSame('Rs.120.00', MenuMoney::format(120, MenuMoney::resolve('INR', ['price_display' => 'symbol'])));
        $this->assertSame('$120.00',   MenuMoney::format(120, MenuMoney::resolve('USD', ['price_display' => 'symbol'])));
        $this->assertSame('KSh 120.00', MenuMoney::format(120, MenuMoney::resolve('KES', ['price_display' => 'symbol'])));
        $this->assertSame('INR 120.00', MenuMoney::format(120, MenuMoney::resolve('INR')));
    }

    // ===== 3. The cart agrees with the menu =====

    /**
     * The cart total used to be its own copy of "code, space, two
     * decimals" in JavaScript. It reads the page's format now, which is
     * the only way the two can be guaranteed to match.
     */
    public function test_the_cart_reads_the_same_format_as_the_menu(): void
    {
        [$link, $menu] = $this->restaurant([
            'price_display' => 'symbol', 'price_decimals' => false,
        ]);
        // The cart JavaScript is only emitted in order mode, which lives on
        // the menu row rather than in its settings.
        $menu->update(['mode' => 'order']);

        $html = $this->page($link);

        $this->assertStringContainsString('const MONEY =', $html);
        $this->assertStringContainsString('"prefix":"Rs."', $html);
        $this->assertStringContainsString('"decimals":0', $html);
        $this->assertStringNotContainsString('const CURRENCY =', $html,
            'the cart must not carry its own idea of the currency format');
    }

    // ===== 4. The WhatsApp total =====

    /**
     * The headline bug. "Total" printed the subtotal, so an order with GST
     * told the restaurant Rs.240 when the guest owed Rs.252.
     */
    public function test_the_whatsapp_message_states_the_amount_actually_owed(): void
    {
        [$link, $menu] = $this->restaurant([
            'whatsapp_number' => '919876543210',
            'tax' => ['enabled' => true, 'rate' => 5, 'inclusive' => false, 'label' => 'GST'],
        ]);

        $order = $this->order($menu, $link, [
            'subtotal' => 240, 'tax_rate' => 5, 'tax_amount' => 12, 'total' => 252,
        ]);

        $message = WhatsappOrderLink::build($menu, $order, $link->title)['message'];

        $this->assertStringContainsString('Total: INR 252.00', $message);
        $this->assertStringNotContainsString('Total: INR 240.00', $message,
            'the restaurant reads this number off their phone and charges it');
    }

    /** With the breakdown, so the number is checkable rather than asserted. */
    public function test_the_whatsapp_message_shows_the_tax_by_its_own_name(): void
    {
        [$link, $menu] = $this->restaurant([
            'whatsapp_number' => '919876543210',
            'tax' => ['enabled' => true, 'rate' => 5, 'inclusive' => false, 'label' => 'GST'],
        ]);

        $order = $this->order($menu, $link, [
            'subtotal' => 240, 'tax_rate' => 5, 'tax_amount' => 12, 'total' => 252,
        ]);

        $message = WhatsappOrderLink::build($menu, $order, $link->title)['message'];

        $this->assertStringContainsString('Subtotal: INR 240.00', $message);
        $this->assertStringContainsString('GST: INR 12.00', $message,
            'an Indian restaurant reading "Tax" where its own menu says "GST" stops trusting the message');
    }

    public function test_a_discount_appears_with_the_code_that_earned_it(): void
    {
        [$link, $menu] = $this->restaurant(['whatsapp_number' => '919876543210']);

        $order = $this->order($menu, $link, [
            'subtotal' => 240, 'coupon_code' => 'DIWALI', 'discount_amount' => 40, 'total' => 200,
        ]);

        $message = WhatsappOrderLink::build($menu, $order, $link->title)['message'];

        $this->assertStringContainsString('Discount (DIWALI): -INR 40.00', $message);
        $this->assertStringContainsString('Total: INR 200.00', $message);
    }

    /**
     * A plain order stays three lines. The breakdown appears only when
     * there is one, or every message turns into a receipt.
     */
    public function test_a_plain_order_gets_no_breakdown(): void
    {
        [$link, $menu] = $this->restaurant(['whatsapp_number' => '919876543210']);

        $order = $this->order($menu, $link);

        $message = WhatsappOrderLink::build($menu, $order, $link->title)['message'];

        $this->assertStringNotContainsString('Subtotal:', $message);
        $this->assertStringContainsString('Total: INR 240.00', $message);
    }

    /** And the line prices are there, so the total can be checked. */
    public function test_the_message_itemises_what_each_line_cost(): void
    {
        [$link, $menu] = $this->restaurant(['whatsapp_number' => '919876543210']);

        $message = WhatsappOrderLink::build($menu, $this->order($menu, $link), $link->title)['message'];

        $this->assertStringContainsString('2× Masala Dosa · INR 240.00', $message);
    }

    // ===== 5. Saving =====

    public function test_the_format_can_be_saved_from_the_editor(): void
    {
        [$link] = $this->restaurant();

        $this->actingAs($this->user)
            ->postJson('/user/links/'.$link->id.'/restaurant/settings', [
                'mode' => 'display', 'currency' => 'INR',
                'price_display' => 'symbol', 'price_position' => 'before', 'price_decimals' => false,
            ])->assertOk();

        $this->assertStringContainsString('Rs.120', $this->page($link));
    }

    public function test_the_store_saves_it_too(): void
    {
        [$link] = $this->store();

        $this->actingAs($this->user)
            ->postJson('/user/links/'.$link->id.'/store/settings', [
                'mode' => 'display', 'currency' => 'INR', 'price_display' => 'symbol',
            ])->assertOk();

        $this->assertStringContainsString('Rs.450.00', $this->page($link));
    }

    /**
     * Decimals ON clears the key rather than storing `true`, because the
     * stored value has to keep meaning "whatever this currency does" if
     * the currency is changed to a zero-decimal one later.
     */
    public function test_turning_decimals_back_on_clears_the_setting(): void
    {
        [$link, $menu] = $this->restaurant(['price_decimals' => false]);

        $this->actingAs($this->user)
            ->postJson('/user/links/'.$link->id.'/restaurant/settings', [
                'mode' => 'display', 'currency' => 'INR', 'price_decimals' => true,
            ])->assertOk();

        $this->assertArrayNotHasKey('price_decimals', $menu->fresh()->settings);
    }

    public function test_an_invented_format_is_refused_rather_than_stored(): void
    {
        [$link, $menu] = $this->restaurant();

        $this->actingAs($this->user)
            ->postJson('/user/links/'.$link->id.'/restaurant/settings', [
                'mode' => 'display', 'currency' => 'INR', 'price_display' => '../../etc/passwd',
            ])->assertOk();

        $this->assertArrayNotHasKey('price_display', $menu->fresh()->settings);
        $this->assertStringNotContainsString('etc/passwd', $this->page($link));
    }

    // ===== 6. The editor offers it =====

    public function test_both_editors_offer_the_format_with_a_live_sample(): void
    {
        foreach ([[$this->restaurant()[0], 'restaurant'], [$this->store()[0], 'store']] as [$link, $kind]) {
            $html = $this->actingAs($this->user)
                ->get('/user/links/'.$link->id.'/'.$kind)->assertOk()->getContent();

            $this->assertStringContainsString('x-model="menu.price_display"', $html);
            $this->assertStringContainsString('x-model="menu.price_position"', $html);
            $this->assertStringContainsString('x-text="priceSample"', $html,
                'three radio groups describing a format are harder to read than one line showing it');
        }
    }

    // ===== 7. Guard =====

    /**
     * The structural one. Six hand-written copies of one money format is
     * what let the WhatsApp total drift from the menu for however many
     * months. This fails if a new one appears.
     */
    public function test_no_menu_surface_formats_money_by_hand(): void
    {
        $files = [
            resource_path('views/common/restaurant-menu.blade.php'),
            resource_path('views/common/store-menu.blade.php'),
            app_path('Modules/Common/Services/WhatsappOrderLink.php'),
        ];

        foreach ($files as $file) {
            $body = file_get_contents($file);

            $this->assertStringNotContainsString("currency . ' ' . number_format", $body,
                basename($file).' has grown its own money format again');
            $this->assertStringNotContainsString("CURRENCY + ' '", $body,
                basename($file).' has grown its own money format in JavaScript again');
        }
    }
}
