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
 * Sana, 2026-09-28: "review order is still bottom.. it needs to be
 * activated and fixed floating button... doesnt need to go end of screen".
 *
 * He was looking at a button that had stopped being fixed. On a menu with a
 * background layer switched on, this rule was applying to it:
 *
 *     body > *:not(.bg-layer):not(.sz-share):not(script):not(style) {
 *         position: relative;
 *     }
 *
 * which outweighs `.cartfab { position: fixed }` on specificity and drops
 * the button into the document flow -- so it scrolls away with the page and
 * comes to rest at the bottom of it, which is exactly what his screenshot
 * shows. The same thing was happening to the order panel and the
 * confirmation panel, both of which are body-level overlays too: on those
 * menus they were not covering the screen, they were sitting in the middle
 * of the page.
 *
 * The comment on that rule already recorded this happening once, to the
 * share button, and the fix at the time was to name that one class in the
 * chain. Naming the next one would just queue up the one after it, so the
 * exclusion is a marker now: anything body-level that pins itself to the
 * viewport carries `sz-pinned` and is excluded once.
 */
class APinnedOverlayStaysPinnedTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /**
     * A background that switches the offending rule on -- the rule only
     * exists on a page that has layers to lift content above, which is why
     * this never showed up on a plain menu.
     */
    private const LAYERED = [
        'biolink' => [
            'background_type'     => 'color',
            'background_color'    => '#0f172a',
            'bg_overlay_opacity'  => 40,
            'bg_overlay_color'    => '#000000',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->user);
    }

    private function restaurant(array $linkSettings = []): Link
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'),
            'title' => 'Priyumm Tiffins', 'is_active' => true,
            'settings' => $linkSettings,
        ]);
        $menu = RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'order', 'currency' => 'INR', 'accent_color' => '#f59e0b',
            'settings' => [],
        ]);
        $cat = RestaurantMenuCategory::create([
            'menu_id' => $menu->id, 'name' => 'Tiffins', 'sort_order' => 0, 'is_active' => true,
        ]);
        RestaurantMenuItem::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => 'Chapathi with Veg Curry', 'price' => 50, 'sort_order' => 0, 'is_active' => true,
        ]);

        return $link->fresh();
    }

    private function store(array $linkSettings = []): Link
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'store_menu',
            'alias' => 'sm'.fake()->unique()->numerify('#####'),
            'title' => 'The Shop', 'is_active' => true,
            'settings' => $linkSettings,
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

    /** @return array<string, string> */
    private function bothPages(array $linkSettings = []): array
    {
        return [
            'restaurant' => $this->get('/'.$this->restaurant($linkSettings)->alias)->assertOk()->getContent(),
            'store' => $this->get('/'.$this->store($linkSettings)->alias)->assertOk()->getContent(),
        ];
    }

    // ===== The rule =======================================================

    public function test_the_lift_rule_leaves_pinned_overlays_alone(): void
    {
        $css = file_get_contents(
            resource_path('views/common/page-background/css.blade.php')
        );

        $this->assertStringContainsString(
            'body > *:not(.bg-layer):not(.sz-pinned)',
            $css,
            'The rule that lifts page content must skip anything pinned to the viewport.'
        );
    }

    public function test_every_body_level_overlay_on_a_menu_says_it_is_pinned(): void
    {
        // Each of these sets `position: fixed` for itself, so each has to be
        // out of the lift rule's way.
        $pinned = ['cartfab', 'cartModal', 'doneModal'];

        foreach ($this->bothPages() as $kind => $html) {
            foreach ($pinned as $id) {
                $this->assertMatchesRegularExpression(
                    '/class="[^"]*\bsz-pinned\b[^"]*"[^>]*id="'.$id.'"/',
                    $html,
                    "On the {$kind} page, #{$id} pins itself but does not say so, so a page "
                    ."with a background layer drops it into the flow."
                );
            }
        }
    }

    // ===== On a page that actually has a background layer ==================

    public function test_the_lift_rule_is_live_on_a_layered_page_and_still_skips_them(): void
    {
        foreach ($this->bothPages(self::LAYERED) as $kind => $html) {
            // The rule is present -- otherwise this test proves nothing.
            $this->assertStringContainsString('body > *:not(.bg-layer)', $html,
                "The {$kind} page was expected to render with a background layer.");
            $this->assertStringContainsString(':not(.sz-pinned)', $html,
                "The {$kind} page's lift rule would drop its cart button into the flow.");
        }
    }

    public function test_the_floating_button_still_asks_to_be_fixed(): void
    {
        $shell = file_get_contents(
            resource_path('views/common/partials/menu-order-shell-css.blade.php')
        );

        // The other half of the bug: the marker only helps if the button
        // still declares itself.
        $this->assertMatchesRegularExpression('/\.cartfab\s*\{[^}]*position:\s*fixed/s', $shell);
        $this->assertMatchesRegularExpression('/\.modal\s*\{[^}]*position:\s*fixed/s', $shell);
    }

    public function test_the_share_button_keeps_its_own_exclusion(): void
    {
        // Other page types have not been given the marker, and their share
        // button must not break while they wait for it.
        $css = file_get_contents(
            resource_path('views/common/page-background/css.blade.php')
        );

        $this->assertStringContainsString(':not(.sz-share)', $css);
    }
}
