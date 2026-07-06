<?php

namespace Tests\Feature;

use App\Modules\Common\Services\RestaurantOrderService;
use App\Modules\Common\Services\WhatsappOrderLink;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\RestaurantOrder;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the optional "Send order via WhatsApp" click-to-chat link (Task
 * #3062) end-to-end so it stays consistent across the web public page and
 * the mobile REST API.
 *
 * The wa.me link is built server-side in WhatsappOrderLink and surfaced in
 * three places that must stay in lockstep:
 *   - the web public order POST/status JSON (PublicRestaurantController),
 *   - the mobile guest order POST/status JSON (Api\RestaurantController),
 *   - the owner menu payload's `whatsapp_number` (Api\RestaurantController).
 *
 * These assert: number normalization bounds (7-15 digits) + null on junk,
 * that a configured number yields a correct wa.me payload while ordering
 * still records the order, that an absent number yields a null payload, and
 * that the owner builder echoes back the stored number.
 */
class RestaurantWhatsappOrderingTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create([
            'name' => 'Owner',
            'role' => 'user',
        ]);
    }

    /**
     * Build an order-mode restaurant menu with a single active item.
     *
     * @return array{0:Link,1:RestaurantMenu,2:RestaurantMenuItem}
     */
    private function makeMenu(User $owner, ?string $whatsapp): array
    {
        $link = Link::create([
            'user_id'   => $owner->id,
            'type'      => Link::TYPE_RESTAURANT_MENU,
            'alias'     => Link::generateAlias(),
            'title'     => 'Cafe Bistro',
            'is_active' => true,
        ]);

        $menu = RestaurantMenu::create([
            'link_id'  => $link->id,
            'user_id'  => $owner->id,
            'mode'     => RestaurantMenu::MODE_ORDER,
            'currency' => 'USD',
            'settings' => $whatsapp ? ['whatsapp_number' => $whatsapp] : [],
        ]);

        $item = RestaurantMenuItem::create([
            'menu_id'   => $menu->id,
            'name'      => 'Espresso',
            'price'     => 3.50,
            'is_active' => true,
        ]);

        return [$link, $menu, $item];
    }

    // ── normalizeNumber bounds + null cases ──────────────────────────

    public function test_normalize_number_strips_punctuation_to_digits(): void
    {
        $this->assertSame('14155552671', WhatsappOrderLink::normalizeNumber('+1 (415) 555-2671'));
        $this->assertSame('919876543210', WhatsappOrderLink::normalizeNumber('+91 98765 43210'));
    }

    public function test_normalize_number_is_null_for_blank_or_junk(): void
    {
        $this->assertNull(WhatsappOrderLink::normalizeNumber(null));
        $this->assertNull(WhatsappOrderLink::normalizeNumber(''));
        $this->assertNull(WhatsappOrderLink::normalizeNumber('   '));
        $this->assertNull(WhatsappOrderLink::normalizeNumber('not-a-number'));
    }

    public function test_normalize_number_enforces_seven_to_fifteen_digit_bounds(): void
    {
        // 6 digits — below the lower bound.
        $this->assertNull(WhatsappOrderLink::normalizeNumber('123456'));
        // 7 digits — the minimum.
        $this->assertSame('1234567', WhatsappOrderLink::normalizeNumber('123-4567'));
        // 15 digits — the E.164 maximum.
        $this->assertSame('123456789012345', WhatsappOrderLink::normalizeNumber('123456789012345'));
        // 16 digits — above the upper bound.
        $this->assertNull(WhatsappOrderLink::normalizeNumber('1234567890123456'));
    }

    // ── Web public order: whatsapp payload present + order recorded ───

    public function test_web_place_order_returns_whatsapp_payload_and_records_order(): void
    {
        $owner = $this->makeUser();
        [$link, $menu, $item] = $this->makeMenu($owner, '+1 (415) 555-2671');

        $res = $this->postJson("/rm/{$link->alias}/order", [
            'customer_name' => 'Ada',
            'items'         => [['item_id' => $item->id, 'quantity' => 2]],
        ]);

        $res->assertCreated();
        $whatsapp = $res->json('data.order.whatsapp');
        $this->assertIsArray($whatsapp);
        $this->assertSame('14155552671', $whatsapp['number']);
        $this->assertStringStartsWith('https://wa.me/14155552671?text=', $whatsapp['url']);
        $this->assertStringContainsString('Cafe Bistro', $whatsapp['message']);
        $this->assertStringContainsString('2× Espresso', $whatsapp['message']);

        // The order still records regardless of WhatsApp being present.
        $token = $res->json('data.order.public_token');
        $this->assertNotEmpty($token);
        $this->assertSame('7.00', (string) $res->json('data.order.subtotal'));
        $this->assertDatabaseHas('restaurant_orders', [
            'public_token' => $token,
            'menu_id'      => $menu->id,
        ]);
    }

    public function test_web_place_order_whatsapp_is_null_when_unconfigured(): void
    {
        $owner = $this->makeUser();
        [$link, , $item] = $this->makeMenu($owner, null);

        $res = $this->postJson("/rm/{$link->alias}/order", [
            'items' => [['item_id' => $item->id, 'quantity' => 1]],
        ]);

        $res->assertCreated();
        $this->assertNull($res->json('data.order.whatsapp'));
        $this->assertNotEmpty($res->json('data.order.public_token'));
    }

    public function test_web_order_status_returns_whatsapp_payload(): void
    {
        $owner = $this->makeUser();
        [$link, $menu, $item] = $this->makeMenu($owner, '+1 (415) 555-2671');

        $order = app(RestaurantOrderService::class)->place($link, $menu, [
            'items' => [['item_id' => $item->id, 'quantity' => 1]],
        ]);

        $res = $this->getJson("/rm/order/{$order->public_token}/status");

        $res->assertOk();
        $this->assertSame('14155552671', $res->json('data.order.whatsapp.number'));
    }

    // ── Mobile API guest order: whatsapp payload + null case ─────────

    public function test_api_place_order_returns_whatsapp_payload(): void
    {
        $owner = $this->makeUser();
        [$link, $menu, $item] = $this->makeMenu($owner, '+91 98765 43210');

        $res = $this->postJson("/api/v1/restaurant/{$link->alias}/order", [
            'items' => [['item_id' => $item->id, 'quantity' => 3]],
        ], ['Accept' => 'application/json']);

        $res->assertCreated();
        $whatsapp = $res->json('data.order.whatsapp');
        $this->assertIsArray($whatsapp);
        $this->assertSame('919876543210', $whatsapp['number']);
        $this->assertStringStartsWith('https://wa.me/919876543210?text=', $whatsapp['url']);
        $this->assertStringContainsString('3× Espresso', $whatsapp['message']);

        $this->assertDatabaseHas('restaurant_orders', [
            'public_token' => $res->json('data.order.public_token'),
            'menu_id'      => $menu->id,
        ]);
    }

    public function test_api_place_order_whatsapp_is_null_when_unconfigured(): void
    {
        $owner = $this->makeUser();
        [$link, , $item] = $this->makeMenu($owner, null);

        $res = $this->postJson("/api/v1/restaurant/{$link->alias}/order", [
            'items' => [['item_id' => $item->id, 'quantity' => 1]],
        ], ['Accept' => 'application/json']);

        $res->assertCreated();
        $this->assertNull($res->json('data.order.whatsapp'));
    }

    public function test_api_order_status_returns_whatsapp_payload(): void
    {
        $owner = $this->makeUser();
        [$link, $menu, $item] = $this->makeMenu($owner, '+1 (415) 555-2671');

        $order = app(RestaurantOrderService::class)->place($link, $menu, [
            'items' => [['item_id' => $item->id, 'quantity' => 1]],
        ]);

        $res = $this->getJson("/api/v1/restaurant/orders/{$order->public_token}/status", [
            'Accept' => 'application/json',
        ]);

        $res->assertOk();
        $this->assertSame('14155552671', $res->json('data.order.whatsapp.number'));
    }

    // ── Owner menu payload exposes whatsapp_number ───────────────────

    public function test_owner_menu_payload_exposes_normalized_whatsapp_number(): void
    {
        $owner = $this->makeUser();
        [$link] = $this->makeMenu($owner, '14155552671');

        // Real Bearer token: Sanctum::actingAs breaks TouchSessionToken.
        $token = $owner->createToken('test', ['*'])->plainTextToken;

        $res = $this->getJson("/api/v1/restaurant/links/{$link->id}/menu", [
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ]);

        $res->assertOk();
        $this->assertSame('14155552671', $res->json('data.menu.whatsapp_number'));
    }

    public function test_owner_menu_payload_whatsapp_number_is_null_when_unconfigured(): void
    {
        $owner = $this->makeUser();
        [$link] = $this->makeMenu($owner, null);

        $token = $owner->createToken('test', ['*'])->plainTextToken;

        $res = $this->getJson("/api/v1/restaurant/links/{$link->id}/menu", [
            'Authorization' => 'Bearer ' . $token,
            'Accept'        => 'application/json',
        ]);

        $res->assertOk();
        $this->assertNull($res->json('data.menu.whatsapp_number'));
    }
}
