<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\MenuOrderCoupon;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuCategory;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\RestaurantOrder;
use App\Modules\User\Models\RestaurantOrderItem;
use App\Modules\User\Models\StoreCategory;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreOrder;
use App\Modules\User\Models\StoreProduct;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\MenuOrderCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sana, 2026-10-05: "QR code per order".
 *
 * Every order has carried a `public_token` since ordering shipped. What it
 * never had was a way off the guest's phone and into the counter's hands
 * without somebody reading thirty-two characters out loud. The QR is that
 * way, and this is the counter end of it.
 *
 * The things these hold on to, in rough order of what would hurt most:
 *
 *   - one menu cannot look up another menu's order. A code identifies an
 *     order globally; the query is narrowed by menu first. Without that,
 *     a canteen with a scanner reads the restaurant next door's customer
 *     names off its own screen;
 *   - an order code and a meal coupon can never be confused, because they
 *     can never be the same length. The counter has one box and one
 *     camera and tells them apart on that alone;
 *   - the moves the counter offers are the moves the server would accept,
 *     taken from the order's own transition map rather than listed twice;
 *   - the code survives the round trip through a scanner, which upper-cases
 *     and strips punctuation, and back to the stored UUID.
 */
class AnOrderCanBeScannedAtTheCounterTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $user = User::create([
            'name'     => 'Owner '.Str::random(4),
            'email'    => 'own'.Str::random(8).'@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);

        $ws = app(WorkspaceContext::class)->resolve($user);
        if ($ws !== null) {
            app()->instance('current_workspace', $ws);
        }
        app()->instance('workspace_owner', $user);

        return $user;
    }

    /** A restaurant menu with one item, and an order on it. */
    private function makeRestaurant(User $owner): array
    {
        $link = Link::create([
            'user_id'   => $owner->id,
            'type'      => 'restaurant_menu',
            'alias'     => Link::generateAlias(),
            'title'     => 'Priyumm Tiffins',
            'is_active' => true,
        ]);
        $menu = RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $owner->id,
            'mode' => 'order', 'currency' => 'INR', 'settings' => [],
        ]);
        $cat = RestaurantMenuCategory::create([
            'menu_id' => $menu->id, 'name' => 'Tiffins', 'sort_order' => 0, 'is_active' => true,
        ]);
        $item = RestaurantMenuItem::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Masala Dosa',
            'price' => 120, 'sort_order' => 0, 'is_active' => true,
        ]);
        $order = RestaurantOrder::create([
            'menu_id' => $menu->id, 'link_id' => $link->id,
            'status' => RestaurantOrder::STATUS_NEW,
            'customer_name' => 'Asha', 'customer_note' => 'No chilli',
            'table_label' => 'Table 4',
            'subtotal' => 240, 'total' => 240, 'currency' => 'INR',
        ]);
        RestaurantOrderItem::create([
            'order_id' => $order->id, 'item_id' => $item->id,
            'name' => 'Masala Dosa', 'quantity' => 2,
            'unit_price' => 120, 'line_total' => 240,
        ]);

        return [$link, $menu, $order->fresh('items')];
    }

    private function makeStore(User $owner): array
    {
        $link = Link::create([
            'user_id'   => $owner->id,
            'type'      => 'store_menu',
            'alias'     => Link::generateAlias(),
            'title'     => 'The Shop',
            'is_active' => true,
        ]);
        $menu = StoreMenu::create([
            'link_id' => $link->id, 'user_id' => $owner->id,
            'mode' => 'order', 'currency' => 'INR', 'settings' => [],
        ]);
        StoreCategory::create([
            'menu_id' => $menu->id, 'name' => 'Mugs', 'sort_order' => 0, 'is_active' => true,
        ]);
        $order = StoreOrder::create([
            'menu_id' => $menu->id, 'link_id' => $link->id,
            'status' => StoreOrder::STATUS_NEW,
            'customer_name' => 'Ravi',
            'subtotal' => 450, 'total' => 450, 'currency' => 'INR',
        ]);

        return [$link, $menu, $order->fresh()];
    }

    private function scan(User $owner, Link $link, string $kind, string $code)
    {
        return $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson("/user/links/{$link->id}/{$kind}/orders/by-code/".$code);
    }

    // ── The code itself ────────────────────────────────────────────

    public function test_the_code_survives_a_round_trip_through_a_scanner(): void
    {
        $uuid = (string) Str::uuid();
        $code = MenuOrderCode::of($uuid);

        // What the QR carries: compact, upper case, nothing to mistype.
        $this->assertSame(32, strlen($code));
        $this->assertMatchesRegularExpression('/^[0-9A-F]{32}$/', $code);

        // And back to exactly what the column holds.
        $this->assertSame($uuid, MenuOrderCode::toToken($code));
        // However the decoder hands it over: hyphens, case, stray spaces.
        $this->assertSame($uuid, MenuOrderCode::toToken($uuid));
        $this->assertSame($uuid, MenuOrderCode::toToken(strtoupper($uuid)));
        $this->assertSame($uuid, MenuOrderCode::toToken("  $uuid \n"));
    }

    public function test_an_order_code_can_never_be_mistaken_for_a_meal_coupon(): void
    {
        // The counter has one box and one camera and tells them apart on
        // length alone. The day these two meet, it cannot.
        $this->assertNotSame(
            MenuOrderCoupon::LENGTH,
            MenuOrderCode::LENGTH,
            'a meal coupon and an order code are now the same length, so the counter can no longer tell them apart'
        );

        // A coupon code is not an order code, whatever it spells.
        $this->assertSame('', MenuOrderCode::normalize('ABCDEF23'));
        $this->assertFalse(MenuOrderCode::isOne('ABCDEF23'));
        // Nor is anything else a guest might paste in.
        $this->assertSame('', MenuOrderCode::normalize('have you got my dosa'));
        $this->assertSame('', MenuOrderCode::normalize(null));
        // 32 characters that are not hex is not one either.
        $this->assertSame('', MenuOrderCode::normalize(str_repeat('Z', 32)));
    }

    public function test_a_bad_code_never_reaches_the_database_half_built(): void
    {
        $this->assertSame('', MenuOrderCode::toToken('not a code'));
        $this->assertSame('', MenuOrderCode::toToken(''));
    }

    // ── The lookup ────────────────────────────────────────────────

    public function test_scanning_an_order_returns_what_the_counter_needs(): void
    {
        $owner = $this->makeOwner();
        [$link, , $order] = $this->makeRestaurant($owner);

        $r = $this->scan($owner, $link, 'restaurant', MenuOrderCode::of($order->public_token))->assertOk();
        $o = $r->json('data.order');

        $this->assertSame($order->id, $o['id']);
        $this->assertSame('Asha', $o['customer_name']);
        $this->assertSame('Table 4', $o['table_label']);
        $this->assertSame('No chilli', $o['customer_note']);
        $this->assertSame(RestaurantOrder::STATUS_NEW, $o['status']);
        $this->assertCount(1, $o['items']);
        $this->assertSame('Masala Dosa', $o['items'][0]['name']);
        $this->assertSame(2, (int) $o['items'][0]['quantity']);
        // The URL for the move, handed over rather than rebuilt in JS.
        $this->assertStringContainsString("/user/links/{$link->id}/restaurant/orders/{$order->id}/status", $o['status_url']);
    }

    public function test_the_store_counter_scans_its_own_orders(): void
    {
        $owner = $this->makeOwner();
        [$link, , $order] = $this->makeStore($owner);

        $o = $this->scan($owner, $link, 'store', MenuOrderCode::of($order->public_token))
            ->assertOk()->json('data.order');

        $this->assertSame($order->id, $o['id']);
        $this->assertSame('Ravi', $o['customer_name']);
        $this->assertStringContainsString("/user/links/{$link->id}/store/orders/{$order->id}/status", $o['status_url']);
    }

    public function test_the_offered_moves_are_the_moves_the_server_accepts(): void
    {
        $owner = $this->makeOwner();
        [$link, , $order] = $this->makeRestaurant($owner);
        $code = MenuOrderCode::of($order->public_token);

        $offered = fn () => array_column(
            $this->scan($owner, $link, 'restaurant', $code)->assertOk()->json('data.order.next_statuses'),
            'value'
        );

        // A new order can be accepted or cancelled, and nothing else --
        // a "Ready" button here would be a button that cannot work.
        $this->assertSame(
            RestaurantOrder::STATUS_TRANSITIONS[RestaurantOrder::STATUS_NEW],
            $offered()
        );

        $order->update(['status' => RestaurantOrder::STATUS_READY]);
        $this->assertSame(
            RestaurantOrder::STATUS_TRANSITIONS[RestaurantOrder::STATUS_READY],
            $offered()
        );

        // A finished order offers nothing at all.
        $order->update(['status' => RestaurantOrder::STATUS_COMPLETED]);
        $this->assertSame([], $offered());
    }

    public function test_the_handed_over_url_actually_moves_the_order(): void
    {
        $owner = $this->makeOwner();
        [$link, , $order] = $this->makeRestaurant($owner);

        $o = $this->scan($owner, $link, 'restaurant', MenuOrderCode::of($order->public_token))
            ->assertOk()->json('data.order');

        $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson($o['status_url'], ['status' => RestaurantOrder::STATUS_ACCEPTED])
            ->assertOk();

        $this->assertSame(RestaurantOrder::STATUS_ACCEPTED, $order->fresh()->status);
    }

    // ── What it refuses ───────────────────────────────────────────

    public function test_one_menu_cannot_look_up_another_menus_order(): void
    {
        $owner = $this->makeOwner();
        [, , $theirs] = $this->makeRestaurant($owner);
        // A second restaurant belonging to the SAME owner: even inside one
        // account, a code scanned at one counter must not resolve at the
        // other, or a canteen reads the sister branch's orders.
        [$otherLink] = $this->makeRestaurant($owner);

        $this->scan($owner, $otherLink, 'restaurant', MenuOrderCode::of($theirs->public_token))
            ->assertNotFound();
    }

    public function test_a_stranger_cannot_scan_an_order(): void
    {
        $owner = $this->makeOwner();
        [$link, , $order] = $this->makeRestaurant($owner);
        $code = MenuOrderCode::of($order->public_token);

        // 404 rather than 403, and that is the better answer: the link is
        // outside the intruder's workspace, so it does not resolve at all
        // and they are told nothing about whether it exists. The controller's
        // own 403 guard sits behind that as the second lock.
        $intruder = $this->makeOwner();
        $this->actingAs($intruder)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->getJson("/user/links/{$link->id}/restaurant/orders/by-code/".$code)
            ->assertNotFound();
    }

    public function test_a_coupon_code_scanned_as_an_order_is_not_found(): void
    {
        $owner = $this->makeOwner();
        [$link] = $this->makeRestaurant($owner);

        // Not a crash and not a 500: the two code kinds share a box, and
        // the wrong one arriving here is an ordinary miss.
        $this->scan($owner, $link, 'restaurant', 'ABCDEF23')->assertNotFound();
    }

    public function test_a_restaurant_code_does_not_resolve_on_the_store_route(): void
    {
        $owner = $this->makeOwner();
        [, , $rOrder] = $this->makeRestaurant($owner);
        [$sLink] = $this->makeStore($owner);

        $this->scan($owner, $sLink, 'store', MenuOrderCode::of($rOrder->public_token))
            ->assertNotFound();
    }

    // ── The guest end ─────────────────────────────────────────────

    public function test_the_guest_is_handed_the_code_the_counter_compares(): void
    {
        $owner = $this->makeOwner();
        [, , $order] = $this->makeRestaurant($owner);

        // Both ends come from MenuOrderCode, so the square a guest is
        // shown and the string the counter looks up cannot drift apart.
        $shape = \App\Modules\Common\Controllers\PublicRestaurantController::serializeGuestOrder($order);

        $this->assertArrayHasKey('order_code', $shape);
        $this->assertSame(MenuOrderCode::of($order->public_token), $shape['order_code']);
    }

    public function test_the_store_guest_is_handed_it_too(): void
    {
        $owner = $this->makeOwner();
        [, , $order] = $this->makeStore($owner);

        $shape = \App\Modules\Common\Controllers\PublicStoreController::serializeGuestOrder($order);

        $this->assertArrayHasKey('order_code', $shape);
        $this->assertSame(MenuOrderCode::of($order->public_token), $shape['order_code']);
    }

    public function test_both_menu_pages_draw_the_square_and_the_counter_offers_the_scan(): void
    {
        $owner = $this->makeOwner();
        [$rLink] = $this->makeRestaurant($owner);
        [$sLink] = $this->makeStore($owner);

        foreach ([$rLink, $sLink] as $link) {
            $html = $this->get('/'.$link->alias)->assertOk()->getContent();
            $this->assertStringContainsString('id="ordQr"', $html);
            // The box AND the call that fills it. Asserting only that the
            // partial is on the page passes while the page draws an empty
            // slot forever, which is the failure mode this whole project
            // keeps producing.
            $this->assertMatchesRegularExpression(
                "/menuOrderQr\.show\(\s*document\.getElementById\('ordQr'\)\s*,\s*order\s*\)/",
                $html,
                'the QR box is on the page but nothing fills it'
            );
        }

        // And the counter is wired to a lookup URL, not left with the
        // control present and nothing behind it.
        foreach ([[$rLink, 'restaurant'], [$sLink, 'store']] as [$link, $kind]) {
            $board = $this->actingAs($owner)
                ->get("/user/links/{$link->id}/{$kind}/orders")
                ->assertOk()->getContent();
            // The URL reaches the page through @js, which escapes its
            // slashes, so compare against the unescaped text rather than
            // asserting on a form the page never literally contains.
            $plain = str_replace('\\/', '/', $board);
            $this->assertStringContainsString("/{$kind}/orders/by-code", $plain);
            $this->assertStringContainsString('lookupOrder', $board);
        }
    }
}
