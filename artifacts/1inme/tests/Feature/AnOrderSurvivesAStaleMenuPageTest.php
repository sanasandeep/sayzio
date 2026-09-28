<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuCategory;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\StoreCategory;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreProduct;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sana, 2026-09-28: "Network error.. when submits".
 *
 * It was not a network error. The endpoint answers 201 with the right bill
 * and the right WhatsApp link -- I reproduced it before changing anything.
 * The page was lying about what came back, because all four guest callers
 * (restaurant quote, restaurant order, store quote, store order) were
 * written the same way:
 *
 *     try {
 *         const r = await fetch(url, ...);
 *         const j = await r.json();          // <- inside the try
 *         if (!r.ok) { alert(j.error.message); return; }
 *     } catch (e) {
 *         alert('Network error, please try again.');
 *     }
 *
 * `r.json()` sits inside the try, so ANY non-JSON body -- a 419 session
 * page, a 500, a proxy error -- throws while parsing and comes out as
 * "Network error". The status never reaches anyone.
 *
 * ---- And 419 is what it was most likely hiding ------------------------
 *
 * These endpoints are in routes/web.php, so Laravel's CSRF check applies.
 * A menu gets opened by scanning a QR code at a table and then READ for
 * several minutes before anyone orders. When the session lapses in between,
 * Place order answers 419 -- and the guest is told the wifi is broken while
 * the restaurant never learns an order was attempted.
 *
 * The page recovers from that one by itself now: mint a fresh token, retry
 * once, report only if it still fails. Everything else is reported as what
 * it actually was.
 */
class AnOrderSurvivesAStaleMenuPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->user);
    }

    private function restaurant(): array
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'),
            'title' => 'Priyumm Tiffins', 'is_active' => true,
        ]);
        $menu = RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'order', 'currency' => 'INR', 'accent_color' => '#f59e0b',
            'settings' => ['tax' => ['enabled' => true, 'label' => 'GST', 'rate' => 5]],
        ]);
        $cat = RestaurantMenuCategory::create([
            'menu_id' => $menu->id, 'name' => 'Starters', 'sort_order' => 0, 'is_active' => true,
        ]);
        $item = RestaurantMenuItem::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => 'Chilli Paneer', 'price' => 90, 'sort_order' => 0, 'is_active' => true,
        ]);

        return [$link->fresh(), $menu, $item];
    }

    private function store(): array
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'store_menu',
            'alias' => 'sm'.fake()->unique()->numerify('#####'),
            'title' => 'The Shop', 'is_active' => true,
        ]);
        $menu = StoreMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'order', 'currency' => 'INR', 'accent_color' => '#f59e0b',
            'settings' => [],
        ]);
        $cat = StoreCategory::create([
            'menu_id' => $menu->id, 'name' => 'Mugs', 'sort_order' => 0, 'is_active' => true,
        ]);
        $product = StoreProduct::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => 'Big Mug', 'price' => 450, 'sort_order' => 0, 'is_active' => true,
        ]);

        return [$link->fresh(), $menu, $product];
    }

    private function page(Link $link): string
    {
        return $this->get('/'.$link->alias)->assertOk()->getContent();
    }

    // ===== 1. A guest can get a fresh token without reloading =============

    public function test_a_stale_page_can_mint_a_fresh_token(): void
    {
        $r = $this->getJson(route('public.csrf-token'))->assertOk();

        $token = $r->json('token');
        $this->assertIsString($token);
        $this->assertNotSame('', $token);
    }

    public function test_the_token_endpoint_returns_only_a_token(): void
    {
        $body = $this->getJson(route('public.csrf-token'))->assertOk()->json();

        // It is unauthenticated, so it says nothing about anyone.
        $this->assertSame(['token'], array_keys($body));
    }

    public function test_no_creator_can_take_an_alias_that_shadows_it(): void
    {
        // The route is registered above the /{alias} catch-all, so routing
        // works either way -- this is the other half: a creator claiming
        // "csrf-token" as their link alias would otherwise own a URL the
        // menus depend on.
        $this->assertContains('csrf-token', \App\Modules\Common\Support\ReservedAlias::WORDS);

        $pattern = \App\Modules\Common\Support\ReservedAlias::pattern('[^/]+$');
        $this->assertSame(0, preg_match('#'.$pattern.'#', 'csrf-token'),
            'The catch-all should refuse to capture the token path.');
        // And still captures an ordinary alias.
        $this->assertSame(1, preg_match('#'.$pattern.'#', 'priyumm'));
    }

    // ===== 2. The page knows how to use it ===============================

    public function test_both_menus_post_through_the_recovering_helper(): void
    {
        [$rLink] = $this->restaurant();
        [$sLink] = $this->store();

        foreach (['restaurant' => $rLink, 'store' => $sLink] as $kind => $link) {
            $html = $this->page($link);

            $this->assertStringContainsString('window.menuPost', $html, "The {$kind} page should carry the shared poster.");
            $this->assertStringContainsString('r.status === 419', $html, "The {$kind} page should recover from a lapsed session.");
            // @json escapes the slashes, so match the path rather than the
            // exact spelling of the URL in the emitted JSON.
            $this->assertStringContainsString('csrf-token', $html, "The {$kind} page should know where to get a token.");
        }
    }

    public function test_no_caller_parses_the_body_inside_the_request_guard(): void
    {
        foreach (['restaurant-menu', 'store-menu'] as $view) {
            $src = file_get_contents(resource_path('views/common/'.$view.'.blade.php'));

            // The exact shape that turned every server response into a
            // network failure.
            $this->assertStringNotContainsString(
                "catch(e) { alert('Network error, please try again.');",
                $src,
                "common/{$view} still reports every failure as a network error."
            );
            $this->assertStringNotContainsString(
                "'X-CSRF-TOKEN':CSRF",
                $src,
                "common/{$view} still posts without the recovery path."
            );
        }
    }

    public function test_every_guest_post_goes_through_one_place(): void
    {
        $helper = file_get_contents(
            resource_path('views/common/partials/menu-guest-post.blade.php')
        );

        // One definition of how a guest page talks to the server. Three
        // copies is how one of them ends up with a message the others do not.
        $this->assertSame(1, substr_count($helper, 'window.menuPost = async'));

        foreach (['restaurant-menu', 'store-menu'] as $view) {
            $src = file_get_contents(resource_path('views/common/'.$view.'.blade.php'));
            $this->assertStringContainsString('common.partials.menu-guest-post', $src);
            // Quote and order, both through the helper.
            $this->assertGreaterThanOrEqual(
                2,
                substr_count($src, 'await menuPost('),
                "common/{$view} should post both its quote and its order through the helper."
            );
        }
    }

    // ===== 3. A failure says what actually happened ======================

    public function test_the_page_has_somewhere_to_put_a_failure_that_is_not_an_alert(): void
    {
        [$rLink] = $this->restaurant();
        [$sLink] = $this->store();

        foreach (['restaurant' => $rLink, 'store' => $sLink] as $kind => $link) {
            $html = $this->page($link);

            $this->assertStringContainsString('id="orderErr"', $html, "The {$kind} page should have an inline error line.");
            $this->assertStringContainsString('role="alert"', $html, "The {$kind} page's error line should announce itself.");
            $this->assertStringContainsString('function orderError(', $html, "The {$kind} page should drive that line.");
        }
    }

    public function test_each_status_gets_its_own_words(): void
    {
        $helper = file_get_contents(
            resource_path('views/common/partials/menu-guest-post.blade.php')
        );

        // Someone standing at a table needs to know whether to refresh, wait,
        // or give up -- which is the thing one catch-all message cannot say.
        $this->assertStringContainsString('419', $helper);
        $this->assertStringContainsString('429', $helper);
        $this->assertStringContainsString('Too many attempts', $helper);
        $this->assertStringContainsString('No connection', $helper);
        // And "no connection" is reserved for the case where there genuinely
        // was none: status 0, the request never reaching the server.
        $this->assertStringContainsString('status: 0', $helper);
    }

    // ===== 4. The thing that was never broken still works ================

    public function test_an_order_still_places_and_comes_back_with_its_bill(): void
    {
        [$link, , $item] = $this->restaurant();

        $r = $this->postJson('/rm/'.$link->alias.'/order', [
            'customer_name' => 'Ravi',
            'items' => [['item_id' => $item->id, 'quantity' => 2]],
        ])->assertStatus(201);

        $order = $r->json('data.order');
        $this->assertSame('180.00', $order['subtotal']);
        $this->assertSame('9.00', $order['tax_amount']);
        $this->assertSame('189.00', $order['total']);
    }

    public function test_a_store_request_still_goes_through(): void
    {
        [$link, , $product] = $this->store();

        $this->postJson('/sm/'.$link->alias.'/order', [
            'customer_name' => 'Ravi',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertStatus(201);
    }
}
