<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuCoupon;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the mobile owner endpoints that write the shared GST/tax + coupon
 * surface (Task #3070) so a future change to the API serializers, validation
 * or bill calculator can't silently produce a wrong estimated bill or let a
 * duplicate coupon code through unnoticed.
 *
 * The menu settings JSON (tax) and the coupons table are the SAME rows the
 * public ordering page and web editor read, and RestaurantBillCalculator is
 * the single source of truth for the bill. These tests cover:
 *   - saveMenuSettings persisting tax (added-on vs inclusive, label, on/off),
 *   - coupon create / update / delete, the duplicate-code 422 guard, and the
 *     menu-ownership checks on each,
 *   - an end-to-end check that a coupon + GST set entirely via the mobile API
 *     yields the exact same itemised bill as the public /quote endpoint.
 *
 * Sanctum note: Sanctum::actingAs breaks the TouchSessionToken middleware, so
 * every owner call uses a real Bearer token (see memory `sanctum-api-tests`).
 */
class RestaurantMobileMenuAdminTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create([
            'name' => 'Owner',
            'role' => 'user',
        ]);
    }

    /** @return array{0:Link,1:RestaurantMenu} */
    private function makeMenu(User $owner, array $settings = []): array
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

    private function token(User $user): string
    {
        return $user->createToken('test', ['*'])->plainTextToken;
    }

    private function auth(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $this->token($user), 'Accept' => 'application/json'];
    }

    // ── saveMenuSettings: tax persistence ────────────────────────────

    public function test_save_settings_persists_added_on_tax(): void
    {
        $owner = $this->makeUser();
        [$link, $menu] = $this->makeMenu($owner);

        $res = $this->postJson("/api/v1/restaurant/links/{$link->id}/menu/settings", [
            'mode'          => 'order',
            'currency'      => 'usd',
            'tax_enabled'   => true,
            'tax_rate'      => 18,
            'tax_inclusive' => false,
            'tax_label'     => 'GST',
        ], $this->auth($owner));

        $res->assertOk();
        $tax = $res->json('data.menu.tax');
        $this->assertTrue($tax['enabled']);
        $this->assertEqualsWithDelta(18.0, $tax['rate'], 0.001);
        $this->assertFalse($tax['inclusive']);
        $this->assertSame('GST', $tax['label']);

        $menu->refresh();
        $this->assertSame([
            'enabled'   => true,
            'rate'      => 18.0,
            'inclusive' => false,
            'label'     => 'GST',
        ], $menu->settings['tax']);
        // Currency is upper-cased on save.
        $this->assertSame('USD', $menu->currency);
    }

    public function test_save_settings_persists_inclusive_tax_and_custom_label(): void
    {
        $owner = $this->makeUser();
        [$link, $menu] = $this->makeMenu($owner);

        $res = $this->postJson("/api/v1/restaurant/links/{$link->id}/menu/settings", [
            'mode'          => 'order',
            'currency'      => 'INR',
            'tax_enabled'   => true,
            'tax_rate'      => 5,
            'tax_inclusive' => true,
            'tax_label'     => 'VAT',
        ], $this->auth($owner));

        $res->assertOk();
        $menu->refresh();
        $this->assertTrue($menu->taxInclusive());
        $this->assertSame('VAT', $menu->taxLabel());
        $this->assertEqualsWithDelta(5.0, $menu->taxRate(), 0.001);
    }

    public function test_save_settings_blank_label_falls_back_to_gst(): void
    {
        $owner = $this->makeUser();
        [$link, $menu] = $this->makeMenu($owner);

        $res = $this->postJson("/api/v1/restaurant/links/{$link->id}/menu/settings", [
            'mode'        => 'order',
            'currency'    => 'USD',
            'tax_enabled' => true,
            'tax_rate'    => 10,
            'tax_label'   => '   ',
        ], $this->auth($owner));

        $res->assertOk();
        $menu->refresh();
        $this->assertSame('GST', $menu->settings['tax']['label']);
    }

    public function test_save_settings_can_disable_tax(): void
    {
        $owner = $this->makeUser();
        [$link, $menu] = $this->makeMenu($owner, ['tax' => [
            'enabled' => true, 'rate' => 18, 'inclusive' => false, 'label' => 'GST',
        ]]);

        $res = $this->postJson("/api/v1/restaurant/links/{$link->id}/menu/settings", [
            'mode'        => 'order',
            'currency'    => 'USD',
            'tax_enabled' => false,
            'tax_rate'    => 18,
        ], $this->auth($owner));

        $res->assertOk();
        $menu->refresh();
        $this->assertFalse($menu->taxEnabled());
        $this->assertFalse($res->json('data.menu.tax.enabled'));
    }

    public function test_save_settings_rejects_out_of_range_tax_rate(): void
    {
        $owner = $this->makeUser();
        [$link] = $this->makeMenu($owner);

        $res = $this->postJson("/api/v1/restaurant/links/{$link->id}/menu/settings", [
            'mode'        => 'order',
            'currency'    => 'USD',
            'tax_enabled' => true,
            'tax_rate'    => 150,
        ], $this->auth($owner));

        $res->assertStatus(422);
    }

    public function test_save_settings_rejects_foreign_menu(): void
    {
        $owner    = $this->makeUser();
        $stranger = $this->makeUser();
        [$link] = $this->makeMenu($owner);

        $res = $this->postJson("/api/v1/restaurant/links/{$link->id}/menu/settings", [
            'mode'     => 'order',
            'currency' => 'USD',
        ], $this->auth($stranger));

        $res->assertNotFound();
    }

    // ── Coupon create ────────────────────────────────────────────────

    public function test_store_coupon_creates_and_normalizes_code(): void
    {
        $owner = $this->makeUser();
        [$link, $menu] = $this->makeMenu($owner);

        $res = $this->postJson("/api/v1/restaurant/links/{$link->id}/menu/coupons", [
            'code'           => '  save20 ',
            'discount_type'  => 'percent',
            'discount_value' => 20,
            'min_subtotal'   => 50,
            'is_active'      => true,
        ], $this->auth($owner));

        $res->assertCreated();
        $coupon = $res->json('data.coupon');
        $this->assertSame('SAVE20', $coupon['code']);
        $this->assertSame('percent', $coupon['discount_type']);
        $this->assertTrue($coupon['is_active']);

        $this->assertDatabaseHas('restaurant_menu_coupons', [
            'id'      => $coupon['id'],
            'menu_id' => $menu->id,
            'code'    => 'SAVE20',
        ]);
    }

    public function test_store_coupon_rejects_duplicate_code_with_422(): void
    {
        $owner = $this->makeUser();
        [$link, $menu] = $this->makeMenu($owner);
        $menu->coupons()->create([
            'code'           => 'SAVE',
            'discount_type'  => 'percent',
            'discount_value' => 10,
            'min_subtotal'   => 0,
            'is_active'      => true,
        ]);

        // Same code, different casing — still a duplicate after normalize.
        $res = $this->postJson("/api/v1/restaurant/links/{$link->id}/menu/coupons", [
            'code'           => 'save',
            'discount_type'  => 'fixed',
            'discount_value' => 5,
        ], $this->auth($owner));

        $res->assertStatus(422);
        $this->assertSame('duplicate_code', $res->json('error.code'));
        $this->assertSame(1, $menu->coupons()->count());
    }

    public function test_store_coupon_validates_discount_type(): void
    {
        $owner = $this->makeUser();
        [$link] = $this->makeMenu($owner);

        $res = $this->postJson("/api/v1/restaurant/links/{$link->id}/menu/coupons", [
            'code'           => 'WEIRD',
            'discount_type'  => 'buy_one_get_one',
            'discount_value' => 1,
        ], $this->auth($owner));

        $res->assertStatus(422);
    }

    public function test_store_coupon_rejects_foreign_menu(): void
    {
        $owner    = $this->makeUser();
        $stranger = $this->makeUser();
        [$link, $menu] = $this->makeMenu($owner);

        $res = $this->postJson("/api/v1/restaurant/links/{$link->id}/menu/coupons", [
            'code'           => 'SNEAK',
            'discount_type'  => 'percent',
            'discount_value' => 50,
        ], $this->auth($stranger));

        $res->assertNotFound();
        $this->assertSame(0, $menu->coupons()->count());
    }

    // ── Coupon update ────────────────────────────────────────────────

    public function test_update_coupon_changes_fields(): void
    {
        $owner = $this->makeUser();
        [$link, $menu] = $this->makeMenu($owner);
        $coupon = $menu->coupons()->create([
            'code'           => 'OLD',
            'discount_type'  => 'percent',
            'discount_value' => 10,
            'min_subtotal'   => 0,
            'is_active'      => true,
        ]);

        $res = $this->putJson("/api/v1/restaurant/links/{$link->id}/menu/coupons/{$coupon->id}", [
            'code'           => 'NEW',
            'discount_type'  => 'fixed',
            'discount_value' => 7.5,
            'min_subtotal'   => 30,
            'is_active'      => false,
        ], $this->auth($owner));

        $res->assertOk();
        $body = $res->json('data.coupon');
        $this->assertSame('NEW', $body['code']);
        $this->assertSame('fixed', $body['discount_type']);
        $this->assertFalse($body['is_active']);

        $this->assertDatabaseHas('restaurant_menu_coupons', [
            'id'            => $coupon->id,
            'code'          => 'NEW',
            'discount_type' => 'fixed',
            'is_active'     => false,
        ]);
    }

    public function test_update_coupon_rejects_duplicate_of_another_coupon(): void
    {
        $owner = $this->makeUser();
        [$link, $menu] = $this->makeMenu($owner);
        $menu->coupons()->create([
            'code' => 'AAA', 'discount_type' => 'percent', 'discount_value' => 5,
            'min_subtotal' => 0, 'is_active' => true,
        ]);
        $editing = $menu->coupons()->create([
            'code' => 'BBB', 'discount_type' => 'percent', 'discount_value' => 5,
            'min_subtotal' => 0, 'is_active' => true,
        ]);

        // Renaming BBB → AAA collides with the existing coupon.
        $res = $this->putJson("/api/v1/restaurant/links/{$link->id}/menu/coupons/{$editing->id}", [
            'code'           => 'aaa',
            'discount_type'  => 'percent',
            'discount_value' => 5,
        ], $this->auth($owner));

        $res->assertStatus(422);
        $this->assertSame('duplicate_code', $res->json('error.code'));
        $this->assertSame('BBB', $editing->fresh()->code);
    }

    public function test_update_coupon_keeping_its_own_code_is_allowed(): void
    {
        $owner = $this->makeUser();
        [$link, $menu] = $this->makeMenu($owner);
        $coupon = $menu->coupons()->create([
            'code' => 'KEEP', 'discount_type' => 'percent', 'discount_value' => 5,
            'min_subtotal' => 0, 'is_active' => true,
        ]);

        $res = $this->putJson("/api/v1/restaurant/links/{$link->id}/menu/coupons/{$coupon->id}", [
            'code'           => 'keep',
            'discount_type'  => 'percent',
            'discount_value' => 15,
        ], $this->auth($owner));

        $res->assertOk();
        $this->assertSame('KEEP', $res->json('data.coupon.code'));
        $this->assertSame('15.00', (string) $coupon->fresh()->discount_value);
    }

    public function test_update_coupon_rejects_foreign_menu(): void
    {
        $owner    = $this->makeUser();
        $stranger = $this->makeUser();
        [$link, $menu] = $this->makeMenu($owner);
        $coupon = $menu->coupons()->create([
            'code' => 'MINE', 'discount_type' => 'percent', 'discount_value' => 5,
            'min_subtotal' => 0, 'is_active' => true,
        ]);

        $res = $this->putJson("/api/v1/restaurant/links/{$link->id}/menu/coupons/{$coupon->id}", [
            'code'           => 'HIJACK',
            'discount_type'  => 'percent',
            'discount_value' => 90,
        ], $this->auth($stranger));

        $res->assertNotFound();
        $this->assertSame('MINE', $coupon->fresh()->code);
    }

    public function test_update_coupon_rejects_coupon_from_a_different_menu(): void
    {
        $owner = $this->makeUser();
        [$linkA, $menuA] = $this->makeMenu($owner);
        [$linkB, $menuB] = $this->makeMenu($owner);
        $couponB = $menuB->coupons()->create([
            'code' => 'BONLY', 'discount_type' => 'percent', 'discount_value' => 5,
            'min_subtotal' => 0, 'is_active' => true,
        ]);

        // Owner owns both menus, but the coupon belongs to menu B, not A.
        $res = $this->putJson("/api/v1/restaurant/links/{$linkA->id}/menu/coupons/{$couponB->id}", [
            'code'           => 'MOVED',
            'discount_type'  => 'percent',
            'discount_value' => 5,
        ], $this->auth($owner));

        $res->assertNotFound();
        $this->assertSame('BONLY', $couponB->fresh()->code);
    }

    // ── Coupon delete ────────────────────────────────────────────────

    public function test_destroy_coupon_removes_it(): void
    {
        $owner = $this->makeUser();
        [$link, $menu] = $this->makeMenu($owner);
        $coupon = $menu->coupons()->create([
            'code' => 'GONE', 'discount_type' => 'percent', 'discount_value' => 5,
            'min_subtotal' => 0, 'is_active' => true,
        ]);

        $res = $this->deleteJson("/api/v1/restaurant/links/{$link->id}/menu/coupons/{$coupon->id}", [], $this->auth($owner));

        $res->assertOk();
        $this->assertTrue($res->json('data.deleted'));
        $this->assertDatabaseMissing('restaurant_menu_coupons', ['id' => $coupon->id]);
    }

    public function test_destroy_coupon_rejects_foreign_menu(): void
    {
        $owner    = $this->makeUser();
        $stranger = $this->makeUser();
        [$link, $menu] = $this->makeMenu($owner);
        $coupon = $menu->coupons()->create([
            'code' => 'SAFE', 'discount_type' => 'percent', 'discount_value' => 5,
            'min_subtotal' => 0, 'is_active' => true,
        ]);

        $res = $this->deleteJson("/api/v1/restaurant/links/{$link->id}/menu/coupons/{$coupon->id}", [], $this->auth($stranger));

        $res->assertNotFound();
        $this->assertDatabaseHas('restaurant_menu_coupons', ['id' => $coupon->id]);
    }

    // ── End-to-end: mobile-configured bill == public /quote ──────────

    public function test_mobile_configured_coupon_and_tax_match_the_public_quote(): void
    {
        $owner = $this->makeUser();
        [$link, $menu] = $this->makeMenu($owner);
        $item = $this->addItem($menu, 50.0, 'Plate');

        // 1) Owner sets GST entirely through the mobile API.
        $this->postJson("/api/v1/restaurant/links/{$link->id}/menu/settings", [
            'mode'          => 'order',
            'currency'      => 'USD',
            'tax_enabled'   => true,
            'tax_rate'      => 18,
            'tax_inclusive' => false,
            'tax_label'     => 'GST',
        ], $this->auth($owner))->assertOk();

        // 2) Owner creates a coupon through the mobile API.
        $this->postJson("/api/v1/restaurant/links/{$link->id}/menu/coupons", [
            'code'           => 'TEN',
            'discount_type'  => 'percent',
            'discount_value' => 10,
        ], $this->auth($owner))->assertCreated();

        // 3) A diner quotes the same cart on the PUBLIC web endpoint.
        $quote = $this->postJson("/rm/{$link->alias}/quote", [
            'coupon_code' => 'ten',
            'items'       => [['item_id' => $item->id, 'quantity' => 2]],
        ]);
        $quote->assertOk();
        $bill = $quote->json('data.bill');

        // subtotal 100 → 10% off (10) → taxable 90 → 18% tax (16.20) → 106.20.
        $this->assertTrue($bill['coupon_applied']);
        $this->assertSame('TEN', $bill['coupon_code']);
        $this->assertEqualsWithDelta(100.0, $bill['subtotal'], 0.001);
        $this->assertEqualsWithDelta(10.0, $bill['discount_amount'], 0.001);
        $this->assertEqualsWithDelta(16.2, $bill['tax_amount'], 0.001);
        $this->assertEqualsWithDelta(106.2, $bill['total'], 0.001);
        $this->assertSame('GST', $bill['tax_label']);

        // 4) The mobile /quote endpoint returns the identical breakdown.
        $apiQuote = $this->postJson("/api/v1/restaurant/{$link->alias}/quote", [
            'coupon_code' => 'TEN',
            'items'       => [['item_id' => $item->id, 'quantity' => 2]],
        ], ['Accept' => 'application/json']);
        $apiQuote->assertOk();
        $apiBill = $apiQuote->json('data.bill');

        $this->assertEqualsWithDelta($bill['discount_amount'], $apiBill['discount_amount'], 0.001);
        $this->assertEqualsWithDelta($bill['tax_amount'], $apiBill['tax_amount'], 0.001);
        $this->assertEqualsWithDelta($bill['total'], $apiBill['total'], 0.001);
        $this->assertSame($bill['tax_label'], $apiBill['tax_label']);
    }
}
