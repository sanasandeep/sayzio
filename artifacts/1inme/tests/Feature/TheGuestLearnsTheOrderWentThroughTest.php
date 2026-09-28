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
 * Sana, 2026-09-28: "placing struck.. no thank you msg or thank you url".
 *
 * ---- What was actually happening ---------------------------------------
 *
 * On 30 June 2026, commit 17bb70d90 ("Add coupons + estimated GST/tax bill
 * to Restaurant Menu") removed `<div id="doneLines"></div>` from the
 * confirmation panel and left the `lines('doneLines')` call that fills it.
 *
 *     function lines(container) {
 *         const box = document.getElementById(container);
 *         box.innerHTML = '';        // <- TypeError, box is null
 *
 * That call is the first line of `showDone()`, and `showDone()` runs AFTER
 * the order has been created. So for three months every restaurant order
 * did this: the order saved, the kitchen was notified, and the guest was
 * left looking at a button reading "Placing..." that never changed. No
 * reference, no status, no WhatsApp handoff, and no way to know whether to
 * order again -- which is the expensive part, because the guess is to
 * order again.
 *
 * Three fixes, because one of them is not enough:
 *
 *   1. The element is back.
 *   2. `lines()` returns instead of throwing when its box is missing, so a
 *      lost box costs a list rather than the whole confirmation.
 *   3. `place()` catches anything `showDone()` throws and tells the guest
 *      the order DID go through, because by then it has.
 *
 * And a CI guard, scripts/check-menu-dom-ids.php, which fails on exactly
 * the 30 June commit.
 */
class TheGuestLearnsTheOrderWentThroughTest extends TestCase
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
            'mode' => 'order', 'currency' => 'INR', 'settings' => [],
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
            'mode' => 'order', 'currency' => 'INR', 'settings' => [],
        ]);
        $cat = StoreCategory::create([
            'menu_id' => $menu->id, 'name' => 'Mugs', 'sort_order' => 0, 'is_active' => true,
        ]);
        StoreProduct::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => 'Mug', 'price' => 450, 'sort_order' => 0, 'is_active' => true,
        ]);

        return $link->fresh();
    }

    private function source(string $view): string
    {
        return file_get_contents(resource_path('views/common/'.$view.'.blade.php'));
    }

    // ===== 1. The element is back ========================================

    public function test_the_confirmation_panel_has_the_box_it_fills(): void
    {
        $html = $this->get('/'.$this->restaurant()->alias)->assertOk()->getContent();

        $this->assertStringContainsString('id="doneLines"', $html,
            'The panel calls lines("doneLines") and must have somewhere to put them.');
        // In the panel, not somewhere else on the page.
        $this->assertMatchesRegularExpression(
            '/id="doneModal"[\s\S]{0,600}id="doneLines"/',
            $html,
            'The box has to be inside the confirmation panel to be any use.'
        );
    }

    public function test_every_element_an_ordering_page_reaches_for_exists(): void
    {
        // The same check CI runs, so a red build and a red test say the same
        // thing rather than one of them noticing first.
        $script = base_path('scripts/check-menu-dom-ids.php');
        $this->assertFileExists($script);

        exec('php '.escapeshellarg($script).' 2>&1', $output, $status);

        $this->assertSame(0, $status,
            "An ordering page reaches for an element nothing defines:\n".implode("\n", $output));
    }

    // ===== 2. A missing box costs a list, not the confirmation ===========

    public function test_a_missing_container_no_longer_throws(): void
    {
        foreach (['restaurant-menu', 'store-menu'] as $view) {
            $src = $this->source($view);

            $this->assertMatchesRegularExpression(
                '/function lines\(container\) \{\s*const box = document\.getElementById\(container\);[\s\S]{0,600}?if \(!box\) \{ return; \}/',
                $src,
                "common/{$view}: lines() still throws on a missing box, which strands an order."
            );
        }
    }

    // ===== 3. The guest is told, whatever the panel does =================

    public function test_a_broken_panel_still_tells_the_guest_the_order_landed(): void
    {
        foreach (['restaurant-menu', 'store-menu'] as $view) {
            $src = $this->source($view);

            // showDone runs after the order exists, so it must not be the
            // last word on whether the guest hears anything.
            $this->assertMatchesRegularExpression(
                '/try \{\s*this\.showDone\([^)]*\);\s*\} catch \(e\) \{/',
                $src,
                "common/{$view}: nothing catches a throw from showDone, so the button stays on 'Placing...'."
            );
            $this->assertStringContainsString('went through, but this page could not show the confirmation', $src,
                "common/{$view} should say the order landed, because by then it has.");
        }
    }

    public function test_the_button_never_stays_on_placing(): void
    {
        foreach ([
            ['restaurant-menu', 'Place order'],
            ['store-menu', 'Send order request'],
        ] as [$view, $label]) {
            $src = $this->source($view);

            // Every path out of place() past the disable has to put the
            // label back. Two: the failure branch and the catch.
            $this->assertGreaterThanOrEqual(2, substr_count($src, "btn.textContent = '".$label."'"),
                "common/{$view} has a way out of place() that leaves the button disabled.");
        }
    }

    // ===== 4. Both pages still confirm at all ============================

    public function test_both_pages_still_have_a_confirmation_to_show(): void
    {
        $pages = [
            'restaurant' => $this->get('/'.$this->restaurant()->alias)->assertOk()->getContent(),
            'store' => $this->get('/'.$this->store()->alias)->assertOk()->getContent(),
        ];

        foreach ($pages as $kind => $html) {
            foreach (['doneModal', 'ordStatus', 'doneTotal', 'waBtn'] as $id) {
                $this->assertStringContainsString('id="'.$id.'"', $html,
                    "The {$kind} page's confirmation is missing #{$id}.");
            }
        }
    }
}
