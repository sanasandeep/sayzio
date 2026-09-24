<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuCategory;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\StoreCategory;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreProduct;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\MenuPresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Three reports from Sana on 2026-09-23, all on the menu pages:
 *
 *   "while managing design for restaurent menu... i cannon change colors
 *    of menu items and all"
 *   "default blocks are shown but in live no blocks.. even i tried to add
 *    but still no bllocks..."
 *   "no option to go back to menu items to update... fix this bug too"
 *
 * Every one of them is the same story: the menu page types were put into
 * Link::BIOLINK_FAMILY so they would inherit the editor, the settings
 * screens and the background picker -- and then only half of what that
 * family offers was actually wired through to them.
 *
 *  - The public template read ONE colour out of the creator's settings
 *    (the accent) and hardcoded the rest.
 *  - The block loop lived inline in common/biolink.blade.php, so a menu
 *    could add blocks, save them, preview them in the editor, and never
 *    show one to a visitor.
 *  - The editor tab row had a branch for Conversational, Slides and AI
 *    Chat, and none for the three types with their own editor screen.
 *
 * The same fix in all three cases is to stop having a second, thinner
 * copy of something that already exists.
 */
class TheMenuPagesAreWiredToTheirOwnEditorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->user);
    }

    /** A restaurant page with one category and one item on it. */
    private function restaurant(array $menuSettings = [], array $linkSettings = []): Link
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'),
            'title' => 'Priyumm Tiffins', 'is_active' => true,
            'settings' => $linkSettings,
        ]);
        $link->biolinkBlocks()->delete();

        $menu = RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'display', 'currency' => 'INR', 'accent_color' => '#e0457b',
            'settings' => $menuSettings,
        ]);
        $cat = RestaurantMenuCategory::create([
            'menu_id' => $menu->id, 'name' => 'Dosas', 'sort_order' => 0, 'is_active' => true,
        ]);
        RestaurantMenuItem::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Masala Dosa',
            'price' => 120, 'sort_order' => 0, 'is_active' => true,
        ]);

        return $link;
    }

    private function store(array $menuSettings = []): Link
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'store_menu',
            'alias' => 'sm'.fake()->unique()->numerify('#####'),
            'title' => 'The Shop', 'is_active' => true,
        ]);
        $link->biolinkBlocks()->delete();

        $menu = StoreMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'display', 'currency' => 'INR', 'accent_color' => '#e0457b',
            'settings' => $menuSettings,
        ]);
        $cat = StoreCategory::create([
            'menu_id' => $menu->id, 'name' => 'Mugs', 'sort_order' => 0, 'is_active' => true,
        ]);
        StoreProduct::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Big Mug',
            'price' => 450, 'sort_order' => 0, 'is_active' => true,
        ]);

        return $link;
    }

    private function page(Link $link): string
    {
        return $this->get('/'.$link->alias)->assertOk()->getContent();
    }

    /** A heading block, the kind a creator would add above a menu. */
    private function block(Link $link, string $text, ?string $slot = null): BiolinkBlock
    {
        $settings = ['text' => $text, 'title' => $text];
        if ($slot !== null) {
            $settings['_style'] = ['_menu_slot' => $slot];
        }

        return $link->biolinkBlocks()->create([
            'user_id'  => $this->user->id,
            'type'     => 'paragraph',
            'settings' => $settings,
            'position' => 0,
            'is_active' => true,
        ]);
    }

    // ===== 1. Blocks reach the live page =====

    /**
     * The headline. A block added through the shared editor saved fine and
     * the public menu never looked at it.
     */
    public function test_a_block_added_to_a_menu_shows_on_the_live_page(): void
    {
        $link = $this->restaurant();
        $this->block($link, 'Free delivery over 500');

        $this->assertStringContainsString('Free delivery over 500', $this->page($link),
            'the block saved, the editor showed it, and the public page never rendered it');
    }

    public function test_a_block_added_to_a_store_shows_on_the_live_page(): void
    {
        $link = $this->store();
        $this->block($link, 'Shipping across India');

        $this->assertStringContainsString('Shipping across India', $this->page($link));
    }

    /** And the menu itself is still there. Blocks sit around it, not over it. */
    public function test_the_menu_still_renders_alongside_its_blocks(): void
    {
        $link = $this->restaurant();
        $this->block($link, 'An advertisement');

        $html = $this->page($link);

        $this->assertStringContainsString('An advertisement', $html);
        $this->assertStringContainsString('Masala Dosa', $html);
    }

    /**
     * Each block picks its side. Sana chose "above and below the menu"
     * when we scoped this, so the side is a per-block flag rather than a
     * page-wide setting.
     */
    public function test_a_block_can_sit_above_or_below_the_menu(): void
    {
        $link = $this->restaurant();
        $this->block($link, 'Sits up top', 'above');
        $this->block($link, 'Sits at the bottom', 'below');

        $html = $this->page($link);

        $top    = strpos($html, 'Sits up top');
        $menu   = strpos($html, 'Masala Dosa');
        $bottom = strpos($html, 'Sits at the bottom');

        $this->assertNotFalse($top);
        $this->assertNotFalse($bottom);
        $this->assertLessThan($menu, $top, 'an "above" block must render before the items');
        $this->assertGreaterThan($menu, $bottom, 'a "below" block must render after them');
    }

    /** A block with no side chosen goes below: the menu is the point. */
    public function test_a_block_with_no_side_goes_below_the_menu(): void
    {
        $link = $this->restaurant();
        $this->block($link, 'No side chosen');

        $html = $this->page($link);

        $this->assertGreaterThan(strpos($html, 'Masala Dosa'), strpos($html, 'No side chosen'));
    }

    /**
     * A menu with no blocks must not show the Link in Bio empty state. It
     * has its own content; an empty block list there is normal.
     */
    public function test_a_menu_without_blocks_shows_no_empty_page_notice(): void
    {
        $html = $this->page($this->restaurant());

        $this->assertStringNotContainsString('being set up', $html);
        $this->assertStringContainsString('Masala Dosa', $html);
    }

    /**
     * The guard that keeps this fixed. The block loop is a partial now
     * precisely so there is one copy; a page type that re-inlines its own
     * is how menus ended up unable to render blocks in the first place.
     */
    public function test_every_page_that_draws_blocks_uses_the_shared_list(): void
    {
        $offenders = [];

        foreach (['biolink', 'restaurant-menu', 'store-menu'] as $view) {
            $src = (string) file_get_contents(resource_path("views/common/{$view}.blade.php"));

            if (! str_contains($src, 'common.partials.biolink-block-list')) {
                $offenders[] = $view.' does not include the shared block list';
            }
            if (preg_match('/@forelse\s*\(\s*\$blocks\s+as\s/', $src)) {
                $offenders[] = $view.' has its own block loop again';
            }
        }

        $this->assertSame([], $offenders, implode("\n  ", $offenders));
    }

    // ===== 2. The colours =====

    /**
     * The page read the accent and hardcoded everything else, so item
     * names were whatever the visitor's colour scheme decided.
     */
    public function test_a_menu_paints_the_colours_the_creator_chose(): void
    {
        $link = $this->restaurant([
            'heading_color' => '#8b0000',
            'item_color'    => '#123456',
            'desc_color'    => '#777777',
            'price_color'   => '#00897b',
            'divider_color' => '#eeddcc',
        ]);

        $html = $this->page($link);

        foreach (['#8b0000', '#123456', '#777777', '#00897b', '#eeddcc'] as $hex) {
            $this->assertStringContainsString($hex, $html, $hex.' was chosen and never reached the page');
        }
    }

    public function test_a_store_paints_them_too(): void
    {
        $html = $this->page($this->store(['item_color' => '#123456']));

        $this->assertStringContainsString('#123456', $html);
    }

    /**
     * A menu nobody has recoloured must look exactly as it did. That is
     * what makes this safe to ship to every existing menu.
     */
    public function test_an_unset_colour_keeps_inheriting_the_page_ink(): void
    {
        $html = $this->page($this->restaurant());

        $this->assertStringContainsString('--ink-item:  inherit', $html);
        $this->assertStringContainsString('--ink-price: var(--accent)', $html);
    }

    public function test_the_colours_save_from_the_editor(): void
    {
        $link = $this->restaurant();

        $this->actingAs($this->user)
            ->postJson('/user/links/'.$link->id.'/restaurant/settings', [
                'mode' => 'display', 'currency' => 'INR',
                'item_color' => '#123456', 'price_color' => '#00897b',
            ])->assertOk();

        $stored = $link->fresh()->restaurantMenu->settings;

        $this->assertSame('#123456', $stored['item_color']);
        $this->assertSame('#00897b', $stored['price_color']);
    }

    /**
     * And clearing one puts that part back to inheriting, rather than
     * storing an empty string that the template would paint with.
     */
    public function test_clearing_a_colour_removes_it_rather_than_storing_blank(): void
    {
        $link = $this->restaurant(['item_color' => '#123456']);

        $this->actingAs($this->user)
            ->postJson('/user/links/'.$link->id.'/restaurant/settings', [
                'mode' => 'display', 'currency' => 'INR', 'item_color' => '',
            ])->assertOk();

        $this->assertArrayNotHasKey('item_color', $link->fresh()->restaurantMenu->settings);
    }

    /** Junk is dropped, not echoed into a stylesheet. */
    public function test_a_junk_colour_is_refused(): void
    {
        $link = $this->restaurant();

        $this->actingAs($this->user)
            ->postJson('/user/links/'.$link->id.'/restaurant/settings', [
                'mode' => 'display', 'currency' => 'INR',
                // Short enough to pass the length rule, so it reaches the
                // hex check rather than being caught by max:16 -- which is
                // the guard that actually matters here.
                'item_color' => 'red',
            ])->assertOk();

        $stored = $link->fresh()->restaurantMenu->settings;

        $this->assertArrayNotHasKey('item_color', $stored);
        $this->assertStringNotContainsString(':  red', $this->page($link));
    }

    /** Every colour the resolver returns is one the editor can set. */
    public function test_the_editor_offers_every_colour_the_page_reads(): void
    {
        $link = $this->restaurant();

        $html = $this->actingAs($this->user)
            ->get('/user/links/'.$link->id.'/restaurant')->assertOk()->getContent();

        foreach (MenuPresentation::COLOURS as $key => $meta) {
            $this->assertStringContainsString($key, $html, $key.' is read by the page but cannot be set');
            $this->assertStringContainsString($meta['label'], $html);
        }
    }

    // ===== 3. The way back =====

    /**
     * Once you opened Settings on a menu there was no link back to the
     * items. Conversational, Slides and AI Chat each had one; the three
     * types with their own editor screen did not.
     */
    public function test_the_settings_screen_links_back_to_the_menu_items(): void
    {
        $link = $this->restaurant();

        $html = $this->actingAs($this->user)
            ->get('/user/links/'.$link->id.'/settings/appearance')->assertOk()->getContent();

        $this->assertStringContainsString('/user/links/'.$link->id.'/restaurant', $html,
            'there was no way back to the items except the browser back button');
    }

    public function test_the_store_settings_screen_links_back_to_the_products(): void
    {
        $link = $this->store();

        $html = $this->actingAs($this->user)
            ->get('/user/links/'.$link->id.'/settings/appearance')->assertOk()->getContent();

        $this->assertStringContainsString('/user/links/'.$link->id.'/store', $html);
    }

    /**
     * The guard: every link type with its own editor screen needs a tab,
     * or it becomes unreachable from Settings the moment it is added.
     */
    public function test_every_type_with_its_own_editor_has_a_tab(): void
    {
        $src = (string) file_get_contents(
            resource_path('views/user/links/partials/editor-header.blade.php')
        );

        $expected = [
            'conversational'  => 'user.links.conversational.editor',
            'slides'          => 'user.links.slides.editor',
            'ai_chat'         => 'user.links.ai-chat.editor',
            'restaurant_menu' => 'user.links.restaurant.editor',
            'store_menu'      => 'user.links.store.editor',
            'service_booking' => 'user.links.service-booking.editor',
        ];

        $missing = [];
        foreach ($expected as $type => $route) {
            if (! str_contains($src, "'{$type}'") || ! str_contains($src, $route)) {
                $missing[] = $type;
            }
        }

        $this->assertSame([], $missing,
            'these types have their own editor screen and no tab back to it: '.implode(', ', $missing));
    }
}
