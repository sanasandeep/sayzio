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
use App\Modules\User\Support\MenuBlockSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sana, 2026-09-28: "how to add blocks on top of menu or in betweel somwhere
 * like end of each sec section?"
 *
 * He could not, and the reason is the fifth instance of one pattern this
 * month: settings._style._menu_slot was READ by a single filter on the
 * public page and WRITTEN by nothing at all. No editor control, no API
 * field, no default anywhere. So every block fell through to 'below', and
 * the 'above' position the renderer already knew how to draw had never been
 * reachable by any creator since the day it was written. "After a section"
 * was not expressible at all.
 *
 * Three positions now -- top of page, after any top-level section, bottom of
 * page -- with a picker on each block card.
 *
 * ---- The case worth most of this file ----------------------------------
 *
 * A block pinned after a section the creator later DELETES. Filtering on the
 * stored string, which is what the old code did, drops that block off the
 * page: no error, no warning, the creator's content simply gone because they
 * reorganised their menu. It resolves back to the bottom of the page
 * instead, and the editor shows it there too, so the control and the page
 * agree about where the block actually is.
 */
class ABlockCanSitBetweenTwoMenuSectionsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->user);
    }

    /** A restaurant menu with the given section names, each holding a dish. */
    private function restaurant(array $sections = ['Starters', 'Mains']): array
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'),
            'title' => 'Priyumm Tiffins', 'is_active' => true,
        ]);
        $link->biolinkBlocks()->delete();

        $menu = RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'display', 'currency' => 'INR', 'accent_color' => '#e0457b',
            'settings' => [],
        ]);

        $cats = [];
        foreach (array_values($sections) as $i => $name) {
            $cat = RestaurantMenuCategory::create([
                'menu_id' => $menu->id, 'name' => $name, 'sort_order' => $i, 'is_active' => true,
            ]);
            RestaurantMenuItem::create([
                'menu_id' => $menu->id, 'category_id' => $cat->id,
                'name' => $name.' dish', 'price' => 100 + $i, 'sort_order' => 0, 'is_active' => true,
            ]);
            $cats[$name] = $cat;
        }

        return [$link->fresh(), $menu, $cats];
    }

    private function store(array $sections = ['Mugs', 'Shirts']): array
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
            'settings' => [],
        ]);

        $cats = [];
        foreach (array_values($sections) as $i => $name) {
            $cat = StoreCategory::create([
                'menu_id' => $menu->id, 'name' => $name, 'sort_order' => $i, 'is_active' => true,
            ]);
            StoreProduct::create([
                'menu_id' => $menu->id, 'category_id' => $cat->id,
                'name' => $name.' item', 'price' => 200 + $i, 'sort_order' => 0, 'is_active' => true,
            ]);
            $cats[$name] = $cat;
        }

        return [$link->fresh(), $menu, $cats];
    }

    private function heading(Link $link, string $text, ?string $slot = null): BiolinkBlock
    {
        $settings = ['text' => $text];
        if ($slot !== null) {
            $settings['_style'] = ['_menu_slot' => $slot];
        }

        return BiolinkBlock::create([
            'link_id' => $link->id, 'type' => 'heading',
            'settings' => $settings, 'sort_order' => 0, 'is_active' => true,
        ]);
    }

    private function page(Link $link): string
    {
        return $this->get('/'.$link->alias)->assertOk()->getContent();
    }

    private function editor(Link $link): string
    {
        return $this->actingAs($this->user)
            ->get(route('user.links.blocks.editor', $link))
            ->assertOk()->getContent();
    }

    /** Assert $first appears before $second in the rendered page. */
    private function assertOrder(string $html, string $first, string $second, string $why): void
    {
        $a = strpos($html, $first);
        $b = strpos($html, $second);
        $this->assertNotFalse($a, "Missing from the page: {$first}");
        $this->assertNotFalse($b, "Missing from the page: {$second}");
        $this->assertLessThan($b, $a, $why);
    }

    // ===== 1. The three positions =========================================

    public function test_a_block_can_sit_between_two_sections(): void
    {
        [$link, , $cats] = $this->restaurant(['Starters', 'Mains']);
        $this->heading($link, 'Chef recommends', MenuBlockSlot::forSection($cats['Starters']->id));

        $html = $this->page($link->fresh());

        $this->assertOrder($html, 'Starters dish', 'Chef recommends', 'A section block should render after that section.');
        $this->assertOrder($html, 'Chef recommends', 'Mains dish', 'A section block should render before the next section.');
    }

    public function test_a_block_can_sit_at_the_top_of_the_page(): void
    {
        [$link] = $this->restaurant();
        $this->heading($link, 'Open until eleven', MenuBlockSlot::ABOVE);

        $html = $this->page($link->fresh());

        // This position existed in the renderer and no creator could reach
        // it, because nothing ever wrote the value that selects it.
        $this->assertOrder($html, 'Open until eleven', 'Starters dish', 'An "above" block should render before the menu.');
    }

    public function test_a_block_with_no_position_still_lands_at_the_bottom(): void
    {
        [$link] = $this->restaurant();
        $this->heading($link, 'Find us on Instagram');

        $html = $this->page($link->fresh());

        // Every block that exists today has no slot and renders after the
        // menu. A different default would rearrange every menu page in the
        // product on deploy.
        $this->assertOrder($html, 'Mains dish', 'Find us on Instagram', 'A block with no position should stay below the menu.');
    }

    public function test_the_store_menu_places_them_the_same_way(): void
    {
        [$link, , $cats] = $this->store(['Mugs', 'Shirts']);
        $this->heading($link, 'Free shipping over 500', MenuBlockSlot::forSection($cats['Mugs']->id));

        $html = $this->page($link->fresh());

        $this->assertOrder($html, 'Mugs item', 'Free shipping over 500', 'The store should place a section block too.');
        $this->assertOrder($html, 'Free shipping over 500', 'Shirts item', 'The store should place it before the next section.');
    }

    // ===== 2. A deleted section must not take the block with it ===========

    public function test_a_block_pinned_after_a_deleted_section_falls_to_the_bottom(): void
    {
        [$link, , $cats] = $this->restaurant(['Starters', 'Mains']);
        $this->heading($link, 'Chef recommends', MenuBlockSlot::forSection($cats['Starters']->id));

        $cats['Starters']->delete();

        $html = $this->page($link->fresh());

        // The whole point: the creator reorganised their menu and did not
        // lose the block.
        $this->assertStringContainsString('Chef recommends', $html);
        $this->assertOrder($html, 'Mains dish', 'Chef recommends', 'An orphaned block should fall back below the menu.');
    }

    public function test_the_editor_shows_an_orphaned_block_where_it_actually_renders(): void
    {
        [$link, , $cats] = $this->restaurant(['Starters', 'Mains']);
        $block = $this->heading($link, 'Chef recommends', MenuBlockSlot::forSection($cats['Starters']->id));
        $cats['Starters']->delete();

        $html = $this->editor($link->fresh());

        // The stored value still names the dead section; what the control
        // shows has to be where the block is, or the editor is lying about
        // the page.
        $this->assertStringContainsString('Chef recommends', $html);
        $this->assertMatchesRegularExpression(
            '/<option value="below"[^>]*\bselected\b/',
            $html,
            'An orphaned block should show as Below the menu in the editor.'
        );
    }

    public function test_the_resolver_decides_this_in_one_place(): void
    {
        $live = [7, 9];

        $this->assertSame('above', MenuBlockSlot::resolve('above', $live));
        $this->assertSame('below', MenuBlockSlot::resolve('below', $live));
        $this->assertSame('section:7', MenuBlockSlot::resolve('section:7', $live));
        // Gone, never existed, and malformed all land on the default.
        $this->assertSame('below', MenuBlockSlot::resolve('section:8', $live));
        $this->assertSame('below', MenuBlockSlot::resolve('section:', $live));
        $this->assertSame('below', MenuBlockSlot::resolve('section:abc', $live));
        $this->assertSame('below', MenuBlockSlot::resolve(null, $live));
        $this->assertSame('below', MenuBlockSlot::resolve('nonsense', $live));
    }

    // ===== 3. Saving a position ===========================================

    public function test_a_creator_can_move_a_block_from_the_editor(): void
    {
        [$link, , $cats] = $this->restaurant(['Starters', 'Mains']);
        $block = $this->heading($link, 'Chef recommends');

        $this->actingAs($this->user)->putJson(
            route('user.links.blocks.update', [$link, $block]),
            ['style' => ['_menu_slot' => MenuBlockSlot::forSection($cats['Mains']->id)]]
        )->assertOk();

        $this->assertSame(
            MenuBlockSlot::forSection($cats['Mains']->id),
            $block->fresh()->settings['_style']['_menu_slot'] ?? null
        );

        $html = $this->page($link->fresh());
        $this->assertOrder($html, 'Mains dish', 'Chef recommends', 'The saved position should reach the page.');
    }

    public function test_a_block_cannot_be_pinned_to_another_menus_section(): void
    {
        [$mine] = $this->restaurant(['Starters']);
        [, , $theirCats] = $this->restaurant(['Somebody elses section']);

        $block = $this->heading($mine, 'Chef recommends');

        $this->actingAs($this->user)->putJson(
            route('user.links.blocks.update', [$mine, $block]),
            ['style' => ['_menu_slot' => MenuBlockSlot::forSection($theirCats['Somebody elses section']->id)]]
        )->assertOk();

        // Refused and defaulted, not stored. The style sanitizer only checks
        // the shape of the string; this is the controller's job because only
        // it knows which menu the link has.
        $this->assertSame(
            MenuBlockSlot::BELOW,
            $block->fresh()->settings['_style']['_menu_slot'] ?? null
        );
    }

    // ===== 4. The control ================================================

    public function test_the_block_card_offers_every_position_on_a_menu_page(): void
    {
        [$link] = $this->restaurant(['Starters', 'Mains']);
        $this->heading($link, 'Chef recommends');

        $html = $this->editor($link->fresh());

        // "Top of page" was renamed once it turned out to render below the
        // hero: it is two positions now, each saying which it is. They were
        // reworded again on 2026-10-04 -- they had been measuring from four
        // different things -- so every label now names a place relative to
        // the title or the menu, and a section's own name is quoted.
        $this->assertStringContainsString('Above the title', $html);
        $this->assertStringContainsString('Below the title, before the menu', $html);
        $this->assertStringContainsString('After “Starters”', $html);
        $this->assertStringContainsString('After “Mains”', $html);
        $this->assertStringContainsString('Below the menu', $html);
        $this->assertStringContainsString('setMenuSlot(', $html);
    }

    public function test_a_page_with_no_menu_is_not_offered_a_position(): void
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'biolink',
            'alias' => 'bl'.fake()->unique()->numerify('#####'),
            'title' => 'My Bio', 'is_active' => true,
        ]);
        $link->biolinkBlocks()->delete();
        $this->heading($link, 'Hello');

        $html = $this->editor($link->fresh());

        // There is no menu for a block to sit around, so an empty picker
        // would be a control that cannot do anything.
        $this->assertStringNotContainsString('menu-slot-row', $html);
        $this->assertStringNotContainsString('Below the menu', $html);
    }

    public function test_only_top_level_sections_are_offered(): void
    {
        [$link, $menu, $cats] = $this->restaurant(['Tiffins']);
        RestaurantMenuCategory::create([
            'menu_id' => $menu->id, 'name' => 'Steamed',
            'parent_id' => $cats['Tiffins']->id, 'sort_order' => 0, 'is_active' => true,
        ]);
        $this->heading($link, 'Chef recommends');

        $html = $this->editor($link->fresh());

        $this->assertStringContainsString('After “Tiffins”', $html);
        // A sub-section is drawn inside its parent, so a block between the
        // two would land in the middle of one card's worth of dishes.
        $this->assertStringNotContainsString('After “Steamed”', $html);
    }
}
