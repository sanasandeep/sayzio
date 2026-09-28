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
 * Sana, 2026-09-28: "Review button should be like floading buttton..",
 * "order form should be like side or popup button..."
 *
 * ---- The button --------------------------------------------------------
 *
 * It was a full-width bar pinned across the bottom of the page. On a phone
 * that is a wall across the menu someone is still reading, and it only ever
 * carried two things: the count and the total. Both now live on the button,
 * so the bar has nothing left to do.
 *
 * ---- The panel ---------------------------------------------------------
 *
 * The sheet was pinned to the bottom on every screen. That is right on a
 * phone, held one-handed with the thumb at the bottom. On a laptop it was a
 * 760px slab stuck to the bottom edge of a 1400px window -- a phone layout
 * that had wandered onto a desktop, which is what looks wrong in his
 * screenshot.
 *
 * Same markup, two shapes: a bottom sheet under 860px, a full-height side
 * panel above it with the menu still readable beside it. Verified by
 * rendering both widths rather than reasoning about them.
 *
 * ---- One copy ----------------------------------------------------------
 *
 * Both pages carried their own identical copy of this CSS. That is how the
 * store spent a week missing what the restaurant got, several times over
 * this month, so it is one partial.
 */
class TheCartIsAButtonNotAWallTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->user);
    }

    private function restaurant(): Link
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'),
            'title' => 'Priyumm Tiffins', 'is_active' => true,
        ]);
        $menu = RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'order', 'currency' => 'INR', 'accent_color' => '#f59e0b',
            'settings' => [],
        ]);
        $cat = RestaurantMenuCategory::create([
            'menu_id' => $menu->id, 'name' => 'Starters', 'sort_order' => 0, 'is_active' => true,
        ]);
        RestaurantMenuItem::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => 'Chilli Paneer', 'price' => 90, 'sort_order' => 0, 'is_active' => true,
        ]);

        return $link->fresh();
    }

    private function store(): Link
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
        StoreProduct::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => 'Big Mug', 'price' => 450, 'sort_order' => 0, 'is_active' => true,
        ]);

        return $link->fresh();
    }

    private function bothPages(): array
    {
        return [
            'restaurant' => $this->get('/'.$this->restaurant()->alias)->assertOk()->getContent(),
            'store' => $this->get('/'.$this->store()->alias)->assertOk()->getContent(),
        ];
    }

    private function shell(): string
    {
        return file_get_contents(
            resource_path('views/common/partials/menu-order-shell-css.blade.php')
        );
    }

    // ===== 1. The bar is gone ============================================

    public function test_neither_page_still_pins_a_bar_across_the_bottom(): void
    {
        foreach ($this->bothPages() as $kind => $html) {
            $this->assertStringNotContainsString('class="cartbar"', $html,
                "The {$kind} page still walls off the menu with a bar.");
            $this->assertStringNotContainsString('id="cartbar"', $html);
        }

        foreach (['restaurant-menu', 'store-menu'] as $view) {
            $src = file_get_contents(resource_path('views/common/'.$view.'.blade.php'));
            $this->assertStringNotContainsString('.cartbar', $src,
                "common/{$view} still carries the bar's styling.");
        }
    }

    public function test_the_cart_is_one_floating_button_carrying_both_numbers(): void
    {
        foreach ($this->bothPages() as $kind => $html) {
            $this->assertStringContainsString('class="cartfab"', $html, "The {$kind} page should float its cart button.");
            // The two things the bar existed to say.
            $this->assertStringContainsString('id="cartCount"', $html, "The {$kind} page's button should carry the count.");
            $this->assertStringContainsString('id="cartTotal"', $html, "The {$kind} page's button should carry the total.");
            // And it is a button, so it says what it does.
            $this->assertStringContainsString('aria-label="Review order"', $html);
        }
    }

    public function test_the_button_appears_only_once_something_is_in_the_cart(): void
    {
        foreach (['restaurant-menu', 'store-menu'] as $view) {
            $src = file_get_contents(resource_path('views/common/'.$view.'.blade.php'));
            $this->assertStringContainsString(
                "document.getElementById('cartfab').classList.toggle('show', count > 0);",
                $src,
                "common/{$view} should show the button only when the cart has something in it."
            );
        }

        $css = $this->shell();
        $this->assertMatchesRegularExpression('/\.cartfab\s*\{[^}]*display:\s*none/s', $css);
        $this->assertStringContainsString('.cartfab.show', $css);
    }

    // ===== 2. The panel has two shapes ===================================

    public function test_the_panel_is_a_bottom_sheet_on_a_phone(): void
    {
        $css = $this->shell();

        // Above the fold in the stylesheet: the phone shape is the default,
        // because that is where most of these pages are opened.
        $this->assertMatchesRegularExpression('/\.modal\s*\{[^}]*align-items:\s*flex-end/s', $css);
        $this->assertMatchesRegularExpression('/\.sheet\s*\{[^}]*border-radius:\s*18px 18px 0 0/s', $css);
        $this->assertMatchesRegularExpression('/\.sheet\s*\{[^}]*max-height:\s*88vh/s', $css);
    }

    public function test_the_panel_is_a_side_panel_on_a_laptop(): void
    {
        $css = $this->shell();

        $this->assertMatchesRegularExpression(
            '/@media \(min-width: 860px\)/',
            $css,
            'The panel should change shape on a wide screen.'
        );
        // Against the right edge, full height -- not a slab stuck to the
        // bottom of a 1400px window.
        $wide = substr($css, strpos($css, '@media (min-width: 860px)'));
        $this->assertStringContainsString('justify-content: flex-end', $wide);
        $this->assertStringContainsString('height: 100%', $wide);
        $this->assertStringContainsString('border-radius: 0', $wide);
    }

    public function test_the_safe_area_is_respected_on_both_shapes(): void
    {
        $css = $this->shell();

        // A phone with a home indicator will otherwise put it through the
        // button and the sheet's last control.
        $this->assertStringContainsString('env(safe-area-inset-bottom)', $css);
        $this->assertSame(2, substr_count($css, 'env(safe-area-inset-bottom)'),
            'Both the floating button and the bottom sheet should clear the home indicator.');
    }

    // ===== 3. One copy ===================================================

    public function test_neither_page_keeps_its_own_copy_of_the_shell(): void
    {
        foreach (['restaurant-menu', 'store-menu'] as $view) {
            $src = file_get_contents(resource_path('views/common/'.$view.'.blade.php'));

            $this->assertStringContainsString('common.partials.menu-order-shell-css', $src,
                "common/{$view} should draw its cart shell from the shared partial.");
            // The copies that were there.
            $this->assertStringNotContainsString('.cartbar .inner', $src);
            $this->assertStringNotContainsString('.modal { position:fixed', $src);
        }
    }

    public function test_the_shell_is_defined_once(): void
    {
        $css = $this->shell();

        // The base rule for each, at the stylesheet's own top level. The
        // further ones are deliberate overrides -- dark mode, reduced
        // motion, the wide-screen shape -- and are nested inside a query.
        $this->assertSame(1, preg_match_all('/^    \.cartfab \{/m', $css),
            'The button should be declared once and then only overridden.');
        $this->assertSame(1, preg_match_all('/^    \.sheet \{/m', $css),
            'The panel should be declared once and then only overridden.');

        // And each page reaches for it once rather than twice.
        foreach (['restaurant-menu', 'store-menu'] as $view) {
            $src = file_get_contents(resource_path('views/common/'.$view.'.blade.php'));
            $this->assertSame(1, substr_count($src, 'common.partials.menu-order-shell-css'),
                "common/{$view} should include the shell exactly once.");
        }
    }

    // ===== 4. Nothing about ordering changed =============================

    public function test_the_order_panel_still_holds_everything_it_did(): void
    {
        // Each page calls the thing by its own name -- a restaurant takes an
        // order, the store takes a request -- and that wording is untouched.
        $headings = ['restaurant' => 'Your order', 'store' => 'Your request'];

        foreach ($this->bothPages() as $kind => $html) {
            $this->assertStringContainsString('id="cartModal"', $html);
            $this->assertStringContainsString('<h3>'.$headings[$kind].'</h3>', $html,
                "The {$kind} page's panel lost its heading.");
            $this->assertStringContainsString('id="placeBtn"', $html, "The {$kind} page lost its place button.");
            $this->assertStringContainsString('id="fName"', $html);
            $this->assertStringContainsString('Keep browsing', $html);
        }
    }
}
