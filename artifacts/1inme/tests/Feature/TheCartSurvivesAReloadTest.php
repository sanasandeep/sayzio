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
 * The cart is still there after a reload.
 *
 * Sana, 2026-09-28: "when reload page, selected items are lost".
 *
 * ---- What these tests can and cannot reach -----------------------------
 *
 * The behaviour is browser behaviour -- localStorage, a reload, a menu
 * that changed in between -- and PHPUnit cannot run any of it. So this
 * file asserts the CONTRACT that makes the behaviour correct, and the
 * behaviour itself was driven in a real Chromium against three rendered
 * versions of the same menu:
 *
 *   built     2 dosa + 1 paneer (Hot) -> pill "3", INR 340.00
 *   reloaded  same page            -> pill "3", INR 340.00, cart intact
 *   changed   dosa delisted, Hot sold out
 *                                  -> both lines dropped, pill hidden,
 *                                     "2 dishes you had chosen are no
 *                                      longer available"
 *   repriced  paneer 90 -> 110     -> restored at 110, "Some prices have
 *                                     changed since you were last here"
 *
 * The contract below is what makes those four outcomes the ones that
 * happen, and the tests are written so that breaking any of them is
 * visible here even though the browser is not.
 *
 * ---- The thing that must never regress ---------------------------------
 *
 * A restored cart must never carry a STORED price. If it does, the
 * customer is quietly shown yesterday's total, the quote endpoint refuses
 * it at checkout, and they get a rejection after they thought they were
 * done -- which is a worse version of the bug this fixes.
 */
class TheCartSurvivesAReloadTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->owner);
    }

    private function restaurant(): Link
    {
        $link = Link::create([
            'user_id' => $this->owner->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'),
            'title' => 'Priyumm Tiffins', 'is_active' => true,
        ]);
        $menu = RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->owner->id,
            'mode' => 'order', 'currency' => 'INR', 'settings' => [],
        ]);
        $cat = RestaurantMenuCategory::create([
            'menu_id' => $menu->id, 'name' => 'Mains', 'sort_order' => 0, 'is_active' => true,
        ]);
        RestaurantMenuItem::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => 'Masala Dosa', 'price' => 120, 'sort_order' => 0, 'is_active' => true,
        ]);

        return $link->fresh();
    }

    private function store(): Link
    {
        $link = Link::create([
            'user_id' => $this->owner->id, 'type' => 'store_menu',
            'alias' => 'sm'.fake()->unique()->numerify('#####'),
            'title' => 'The Shop', 'is_active' => true,
        ]);
        $menu = StoreMenu::create([
            'link_id' => $link->id, 'user_id' => $this->owner->id,
            'mode' => 'order', 'currency' => 'INR', 'settings' => [],
        ]);
        $cat = StoreCategory::create([
            'menu_id' => $menu->id, 'name' => 'Jars', 'sort_order' => 0, 'is_active' => true,
        ]);
        StoreProduct::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => 'Pickle', 'price' => 180, 'sort_order' => 0, 'is_active' => true,
        ]);

        return $link->fresh();
    }

    private function shared(): string
    {
        return file_get_contents(resource_path('views/common/partials/menu-cart-store.blade.php'));
    }

    private function chooser(): string
    {
        return file_get_contents(resource_path('views/common/partials/menu-chooser.blade.php'));
    }

    // ===== It is on both pages, once ====================================

    public function test_both_menus_keep_the_cart(): void
    {
        foreach ([[$this->restaurant(), 'restaurant'], [$this->store(), 'store']] as [$link, $kind]) {
            $html = $this->get('/'.$link->alias)->assertOk()->getContent();

            $this->assertStringContainsString('window.menuCart', $html, "The {$kind} page cannot keep a cart.");
            $this->assertStringContainsString('menuCart.restore(CART_KEY', $html,
                "The {$kind} page saves a cart it never reads back.");
            $this->assertStringContainsString('function saveCart()', $html,
                "The {$kind} page reads a cart it never writes.");
        }
    }

    public function test_one_copy_of_the_store_for_both_pages(): void
    {
        foreach (['restaurant-menu', 'store-menu'] as $view) {
            $src = file_get_contents(resource_path('views/common/'.$view.'.blade.php'));
            $this->assertStringContainsString('common.partials.menu-cart-store', $src);
            $this->assertStringNotContainsString('window.menuCart = {', $src,
                "common/{$view} carries its own copy of the cart store.");
        }

        $this->assertSame(1, substr_count($this->shared(), 'window.menuCart = {'));
    }

    public function test_each_menu_has_its_own_drawer(): void
    {
        // Two menus open in one browser must not share a cart, and the key
        // is the alias, so they cannot.
        $a = $this->restaurant();
        $b = $this->restaurant();

        $htmlA = $this->get('/'.$a->alias)->assertOk()->getContent();
        $htmlB = $this->get('/'.$b->alias)->assertOk()->getContent();

        $this->assertStringContainsString('const CART_KEY = "'.$a->alias.'"', $htmlA);
        $this->assertStringContainsString('const CART_KEY = "'.$b->alias.'"', $htmlB);
        $this->assertStringNotContainsString($b->alias, $htmlA);
    }

    // ===== What is stored, and what is not ==============================

    public function test_only_what_the_customer_chose_is_stored(): void
    {
        $shared = $this->shared();

        // Ids and counts. Not the name, not the price, not the computed
        // per-unit total: those come back from the live menu.
        $this->assertStringContainsString('option_id: o.option_id', $shared);
        $this->assertStringContainsString('quantity: o.quantity', $shared);
        $this->assertStringNotContainsString('name: l.name', $shared,
            'The cart is storing names, which will go stale.');
    }

    public function test_the_old_price_is_kept_only_to_notice_it_changed(): void
    {
        $shared = $this->shared();

        // It IS stored -- as `was` -- and it is read in exactly one place,
        // to compare. If it ever reaches a total, a customer is being shown
        // yesterday's price.
        $this->assertStringContainsString('was: l.perUnit', $shared);
        $this->assertStringContainsString('Math.abs(sl.was - perUnit) > 0.001', $shared);
        $this->assertStringContainsString('var perUnit = Math.round((it.price + chooser.extraFor(opts)) * 100) / 100;', $shared,
            'The restored price is not being recomputed from the live menu.');
        // And it never reaches the line that is handed back: a stored
        // price inside the restored object is the whole failure mode.
        $pushed = substr($shared, strpos($shared, 'lines.push({'));
        $pushed = substr($pushed, 0, strpos($pushed, '});') + 3);
        $this->assertStringNotContainsString('was', $pushed,
            'A stored price is being handed back as part of a restored line.');
    }

    public function test_a_stale_cart_is_thrown_away_rather_than_served(): void
    {
        $shared = $this->shared();

        // Reopening a QR code the next lunchtime and finding yesterday's
        // half order waiting is confusing at best.
        $this->assertStringContainsString('TTL_MS', $shared);
        $this->assertStringContainsString('(Date.now() - parsed.at) > TTL_MS', $shared);
        // And something unparseable is binned, not left to break the page
        // every single time they come back.
        $this->assertStringContainsString('this.clear(alias);', $shared);
    }

    public function test_storage_being_unavailable_does_not_break_the_menu(): void
    {
        // localStorage THROWS, not returns null, in a Safari private window
        // and wherever site data is blocked -- and a menu opened from a QR
        // code on a hotel captive portal hits both. A menu that will not
        // render is a far worse bug than a cart that does not persist.
        $shared = $this->shared();

        $this->assertStringContainsString('function available()', $shared);
        $this->assertGreaterThanOrEqual(4, substr_count($shared, 'catch (e)'),
            'Not every storage call is guarded.');
    }

    // ===== Rebuilding against the live menu =============================

    public function test_a_line_that_cannot_be_rebuilt_is_dropped(): void
    {
        $chooser = $this->chooser();

        $this->assertStringContainsString('rebuild: function (id, payload)', $chooser);
        // Sold out, or over a ceiling that has since come down.
        $this->assertStringContainsString('if (o.is_sold_out || q > c) { return null; }', $chooser);
        // A required group the item has since gained, or a maximum that has
        // since been lowered.
        $this->assertStringContainsString('if (picked < b.min) { return null; }', $chooser);
        $this->assertStringContainsString('if (b.max !== null && picked > b.max) { return null; }', $chooser);
        // And an option id this item no longer offers at all.
        $this->assertStringContainsString('if (total !== seen) { return null; }', $chooser);
    }

    public function test_the_names_and_prices_come_back_from_the_menu(): void
    {
        $chooser = $this->chooser();

        // A choice renamed or repriced in the editor must come back
        // correct, not as it was when the cart was saved.
        $this->assertStringContainsString(
            "out.push({ option_id: o.id, name: o.name, delta: o.price_delta, quantity: q });",
            $chooser
        );
    }

    public function test_a_dropped_line_is_said_out_loud(): void
    {
        // Silently handing someone a different order from the one they left
        // is worse than losing it.
        foreach ([[$this->restaurant(), 'restaurant'], [$this->store(), 'store']] as [$link, $kind]) {
            $html = $this->get('/'.$link->alias)->assertOk()->getContent();

            $this->assertStringContainsString('id="cartRestored"', $html,
                "The {$kind} page has nowhere to say what changed.");
            $this->assertStringContainsString('menuCart.notice(back,', $html,
                "The {$kind} page drops lines without a word.");
        }

        $shared = $this->shared();
        $this->assertStringContainsString('no longer available', $shared);
        $this->assertStringContainsString('Some prices have changed', $shared);
    }

    public function test_the_copy_pluralises_properly(): void
    {
        // Shipped as "as many dishs as it applies to" on the choices panel
        // a week ago, and this notice said "2 dishs" the first time it was
        // rendered. English plurals are not a string operation. Caught both
        // times by looking at the output, not by a test.
        $shared = $this->shared();

        $this->assertStringNotContainsString("noun + 's'", $shared);
        $this->assertStringContainsString('nouns', $shared);

        $rHtml = $this->get('/'.$this->restaurant()->alias)->assertOk()->getContent();
        $sHtml = $this->get('/'.$this->store()->alias)->assertOk()->getContent();
        $this->assertStringContainsString("'dish', 'dishes'", $rHtml);
        $this->assertStringContainsString("'item', 'items'", $sHtml);
    }

    // ===== The cart is spent once the order exists ======================

    public function test_placing_an_order_empties_the_drawer(): void
    {
        // Otherwise the next person to open the menu on that phone -- or
        // the same person, a minute later -- is handed an order they have
        // already placed.
        foreach ([[$this->restaurant(), 'restaurant'], [$this->store(), 'store']] as [$link, $kind]) {
            $html = $this->get('/'.$link->alias)->assertOk()->getContent();

            $this->assertStringContainsString('menuCart.clear(CART_KEY);', $html,
                "The {$kind} page keeps a cart that has already been ordered.");
        }
    }

    public function test_the_confirmation_is_still_painted_after_the_clear(): void
    {
        // The clear must take the STORAGE only. LINES is what paints the
        // itemised confirmation the guest is about to read.
        foreach (['restaurant-menu', 'store-menu'] as $view) {
            $src = file_get_contents(resource_path('views/common/'.$view.'.blade.php'));

            $clearAt = strpos($src, 'menuCart.clear(CART_KEY);');
            $doneAt = strpos($src, 'this.showDone(order);');
            $this->assertNotFalse($clearAt);
            $this->assertNotFalse($doneAt);
            $this->assertLessThan($doneAt, $clearAt);
            // And nothing empties LINES on the way.
            $between = substr($src, $clearAt, $doneAt - $clearAt);
            $this->assertStringNotContainsString('LINES = []', $between,
                "common/{$view} empties the cart before it has drawn the confirmation.");
        }
    }

    // ===== A failed quote still shows a number ==========================

    public function test_a_failed_quote_falls_back_to_a_real_number(): void
    {
        // Found by rendering the sheet with the quote endpoint
        // unreachable: the total read "INR NaN". The fallback summed
        // ITEMS, which is the CATALOG, and catalog entries have carried no
        // qty since the cart became LINES. Every failed quote -- routine
        // at a table on patchy wifi -- showed a guest NaN as their bill.
        foreach (['restaurant-menu', 'store-menu'] as $view) {
            $src = file_get_contents(resource_path('views/common/'.$view.'.blade.php'));

            $this->assertStringNotContainsString('Object.values(ITEMS).reduce((s, it) => s + it.qty * it.price, 0)', $src,
                "common/{$view} still sums a quantity the catalog does not have.");
            $this->assertStringContainsString('LINES.reduce((s, l) => s + l.qty * l.perUnit, 0)', $src,
                "common/{$view} has no honest fallback total.");
        }
    }

    // ===== It shows up without being asked for ==========================

    public function test_a_restored_cart_reaches_the_pill(): void
    {
        // The cart can be perfectly restored and still look lost: the
        // floating pill and the +/- steppers are painted by render(), and
        // nothing calls it on load unless this does.
        foreach (['restaurant-menu', 'store-menu'] as $view) {
            $src = file_get_contents(resource_path('views/common/'.$view.'.blade.php'));

            $this->assertMatchesRegularExpression(
                '/\n\s*render\(\);\n\}\)\(\);/',
                $src,
                "common/{$view} restores a cart that nothing draws."
            );
        }
    }
}
