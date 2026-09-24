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
use App\Modules\User\Support\MenuPresentation;
use App\Modules\User\Support\MenuTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two of Sana's notes from 2026-09-23, and they belong together:
 *
 *   "cats and sub cats"
 *   "also i should able to hide/unhide items, sub cat, and cat also"
 *
 * He was holding his own printed Priyumm Tiffins card while he wrote the
 * first one. On it, "Tiffins" is a heading and Idli, Dosa and Vada sit
 * under it -- which is how every menu card in the world is organised, and
 * which this product could not express at all. One nullable self-reference
 * on the categories table is the whole structural change.
 *
 * ---- Hiding was already built, minus the button ------------------------
 *
 * `is_active` has been on both tables since the day they were created. The
 * public pages already filtered on it. The API already accepted it. The
 * EDITOR never sent it and offered no control, so in the entire life of
 * these two page types nobody has ever been able to hide anything --
 * deleting was the only way, and deleting a seasonal dish to bring it back
 * in October is a different operation.
 *
 * ---- What is worth testing ---------------------------------------------
 *
 * Not "a parent column exists". The thing that will actually break is the
 * INHERITANCE: a visible item inside a hidden sub-section is not visible,
 * and the editor has to grey out exactly what the page leaves off. Those
 * two answers come from MenuTree, once, which is most of why it exists.
 */
class TheMenuCardCanHaveSectionsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->user);
    }

    private function restaurant(array $menuSettings = []): array
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
            'settings' => $menuSettings,
        ]);

        return [$link, $menu];
    }

    private function store(array $menuSettings = []): array
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

        return [$link, $menu];
    }

    private function cat(RestaurantMenu $menu, string $name, array $attrs = []): RestaurantMenuCategory
    {
        return RestaurantMenuCategory::create(array_merge([
            'menu_id' => $menu->id, 'name' => $name, 'sort_order' => 0, 'is_active' => true,
        ], $attrs));
    }

    private function item(RestaurantMenu $menu, RestaurantMenuCategory $cat, string $name, array $attrs = []): RestaurantMenuItem
    {
        return RestaurantMenuItem::create(array_merge([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => $name,
            'price' => 60, 'sort_order' => 0, 'is_active' => true,
        ], $attrs));
    }

    private function page(Link $link): string
    {
        return $this->get('/'.$link->alias)->assertOk()->getContent();
    }

    private function editor(Link $link, string $kind = 'restaurant'): string
    {
        return $this->actingAs($this->user)
            ->get('/user/links/'.$link->id.'/'.$kind)
            ->assertOk()->getContent();
    }

    // ===== 1. The card has sections =====

    /**
     * The headline, and the one Sana was holding a printed card to explain:
     * a sub-section prints under its section, with its own heading.
     */
    public function test_a_sub_section_prints_under_its_section(): void
    {
        [$link, $menu] = $this->restaurant();
        $tiffins = $this->cat($menu, 'Tiffins');
        $steamed = $this->cat($menu, 'Steamed', ['parent_id' => $tiffins->id]);
        $this->item($menu, $steamed, 'Idli');

        $html = $this->page($link);

        $this->assertStringContainsString('Tiffins', $html);
        $this->assertStringContainsString('Steamed', $html);
        $this->assertStringContainsString('Idli', $html);
        $this->assertStringContainsString('class="subcat"', $html,
            'the sub-section needs its own container or it reads as a second top-level heading');

        // Order matters on a menu card: the section, then the group inside it.
        $this->assertLessThan(
            strpos($html, 'Steamed'),
            strpos($html, 'Tiffins'),
            'a sub-section printed above its own section is not a sub-section'
        );
    }

    /** A section keeps its own loose items alongside its sub-sections. */
    public function test_a_section_can_hold_both_items_and_sub_sections(): void
    {
        [$link, $menu] = $this->restaurant();
        $tiffins = $this->cat($menu, 'Tiffins');
        $this->item($menu, $tiffins, 'Poori');

        $sub = $this->cat($menu, 'Steamed', ['parent_id' => $tiffins->id]);
        $this->item($menu, $sub, 'Idli');

        $html = $this->page($link);

        $this->assertStringContainsString('Poori', $html);
        $this->assertStringContainsString('Idli', $html);
        $this->assertLessThan(strpos($html, 'Idli'), strpos($html, 'Poori'),
            'the loose items come before the named groups, the way a card is set');
    }

    /** The store menu is the same product; it gets the same structure. */
    public function test_the_store_gets_sub_sections_too(): void
    {
        [$link, $menu] = $this->store();
        $drink = StoreCategory::create(['menu_id' => $menu->id, 'name' => 'Drinkware', 'sort_order' => 0, 'is_active' => true]);
        $mugs  = StoreCategory::create(['menu_id' => $menu->id, 'parent_id' => $drink->id, 'name' => 'Mugs', 'sort_order' => 0, 'is_active' => true]);
        StoreProduct::create(['menu_id' => $menu->id, 'category_id' => $mugs->id, 'name' => 'Big Mug', 'price' => 450, 'sort_order' => 0, 'is_active' => true]);

        $html = $this->page($link);

        $this->assertStringContainsString('Drinkware', $html);
        $this->assertStringContainsString('class="subcat"', $html);
        $this->assertStringContainsString('Big Mug', $html);
    }

    // ===== 2. Hiding, and what it takes with it =====

    /** The plain case: one item, off. */
    public function test_a_hidden_item_is_not_on_the_page(): void
    {
        [$link, $menu] = $this->restaurant();
        $cat = $this->cat($menu, 'Dosas');
        $this->item($menu, $cat, 'Masala Dosa');
        $this->item($menu, $cat, 'Seasonal Special', ['is_active' => false]);

        $html = $this->page($link);

        $this->assertStringContainsString('Masala Dosa', $html);
        $this->assertStringNotContainsString('Seasonal Special', $html);
    }

    /**
     * The case that makes the shared MenuTree worth having. The item is
     * marked visible. Its sub-section is not. It must not appear -- and if
     * the page and the editor each worked this out for themselves, the way
     * a creator would find out is a visitor seeing something they hid.
     */
    public function test_hiding_a_sub_section_hides_what_is_inside_it(): void
    {
        [$link, $menu] = $this->restaurant();
        $tiffins = $this->cat($menu, 'Tiffins');
        $sub = $this->cat($menu, 'Steamed', ['parent_id' => $tiffins->id, 'is_active' => false]);
        $this->item($menu, $sub, 'Idli');

        $html = $this->page($link);

        $this->assertStringContainsString('Tiffins', $html);
        $this->assertStringNotContainsString('Steamed', $html);
        $this->assertStringNotContainsString('Idli', $html,
            'an item marked visible inside a hidden sub-section is not visible');
    }

    /**
     * And the same one level up: a hidden section takes its sub-sections.
     *
     * The section is called Breakfast rather than Tiffins here because the
     * page's own title is "Priyumm Tiffins" -- a "not on the page" assertion
     * has to name something that appears ONLY as the section.
     */
    public function test_hiding_a_section_hides_its_sub_sections(): void
    {
        [$link, $menu] = $this->restaurant();
        $breakfast = $this->cat($menu, 'Breakfast', ['is_active' => false]);
        $sub = $this->cat($menu, 'Steamed', ['parent_id' => $breakfast->id]);
        $this->item($menu, $sub, 'Idli');

        $html = $this->page($link);

        $this->assertStringNotContainsString('Breakfast', $html);
        $this->assertStringNotContainsString('Steamed', $html);
        $this->assertStringNotContainsString('Idli', $html);
    }

    /**
     * The editor has to grey out exactly what the page leaves off, and it
     * cannot call MenuTree to find out: it recomputes after every toggle
     * without a round trip, so it carries the rule in Alpine. That makes
     * this the one place the two copies can drift, so it is asserted
     * directly -- both editors must inherit the section's state into the
     * sub-section rather than reading the sub-section's own flag alone.
     */
    public function test_both_editors_inherit_hiding_the_way_the_page_does(): void
    {
        foreach (['restaurant', 'store'] as $kind) {
            $blade = file_get_contents(resource_path('views/user/links/'.$kind.'/editor.blade.php'));

            $this->assertStringContainsString(
                'hidden: hidden || sub.is_active === false',
                $blade,
                $kind.' editor would show a sub-section as visible inside a hidden section'
            );
            $this->assertStringContainsString(
                'rowHidden(row, g){ return g.hidden || row.is_active === false; }',
                $blade,
                $kind.' editor would show an item as visible inside a hidden section'
            );
        }
    }

    /** And the page's own copy of the rule, on real rows. */
    public function test_the_tree_the_page_draws_leaves_out_everything_hidden(): void
    {
        [, $menu] = $this->restaurant();
        $breakfast = $this->cat($menu, 'Breakfast', ['is_active' => false]);
        $sub = $this->cat($menu, 'Steamed', ['parent_id' => $breakfast->id]);
        $this->item($menu, $sub, 'Idli');
        $meals = $this->cat($menu, 'Meals');
        $this->item($menu, $meals, 'Thali');

        $tree = MenuTree::build($menu->categories()->get(), $menu->items()->get());

        $this->assertCount(1, $tree, 'the hidden section and everything under it is gone');
        $this->assertSame('Meals', $tree[0]['category']->name);
        $this->assertSame('Thali', $tree[0]['items']->first()->name);
    }

    // ===== 3. The editor can actually do it =====

    /**
     * The whole reason this was broken: the control did not exist. Both the
     * toggle and the sub-section button have to be on the screen.
     */
    public function test_the_editor_offers_a_show_hide_control(): void
    {
        [$link] = $this->restaurant();
        $html = $this->editor($link);

        // The BUTTON, not the method. Asserting on `toggleCategory(` alone
        // would pass on a screen whose only mention of it is its own
        // definition -- which is exactly the state this change fixes.
        $this->assertStringContainsString('@click="toggleCategory(g.cat)"', $html);
        $this->assertStringContainsString('@click="toggleRow(row)"', $html);
        $this->assertStringContainsString('fa-eye-slash', $html);
    }

    public function test_the_editor_offers_a_sub_section_button(): void
    {
        [$link] = $this->restaurant();
        $html = $this->editor($link);

        $this->assertStringContainsString('fa-folder-plus', $html);
        $this->assertStringContainsString('parentChoices()', $html,
            'and a way to move an existing section into another one');
    }

    public function test_the_store_editor_offers_the_same_controls(): void
    {
        [$link] = $this->store();
        $html = $this->editor($link, 'store');

        $this->assertStringContainsString('@click="toggleCategory(g.cat)"', $html);
        $this->assertStringContainsString('@click="toggleRow(row)"', $html);
        $this->assertStringContainsString('fa-folder-plus', $html);
    }

    /** And the save path takes what the screen sends. */
    public function test_a_sub_section_can_be_created_through_the_editor(): void
    {
        [$link, $menu] = $this->restaurant();
        $tiffins = $this->cat($menu, 'Tiffins');

        $this->actingAs($this->user)
            ->postJson('/user/links/'.$link->id.'/restaurant/categories', [
                'name' => 'Steamed', 'parent_id' => $tiffins->id,
            ])->assertCreated();

        $this->assertSame(
            $tiffins->id,
            (int) RestaurantMenuCategory::where('name', 'Steamed')->first()->parent_id
        );
    }

    public function test_an_item_can_be_hidden_through_the_editor(): void
    {
        [$link, $menu] = $this->restaurant();
        $cat  = $this->cat($menu, 'Dosas');
        $item = $this->item($menu, $cat, 'Masala Dosa');

        $this->actingAs($this->user)
            ->putJson('/user/links/'.$link->id.'/restaurant/items/'.$item->id, ['is_active' => false])
            ->assertOk();

        $this->assertFalse((bool) $item->fresh()->is_active);
        $this->assertStringNotContainsString('Masala Dosa', $this->page($link));
    }

    public function test_a_product_can_be_hidden_through_the_editor(): void
    {
        [$link, $menu] = $this->store();
        $cat = StoreCategory::create(['menu_id' => $menu->id, 'name' => 'Mugs', 'sort_order' => 0, 'is_active' => true]);
        $p = StoreProduct::create(['menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Big Mug', 'price' => 450, 'sort_order' => 0, 'is_active' => true]);

        $this->actingAs($this->user)
            ->putJson('/user/links/'.$link->id.'/store/products/'.$p->id, ['is_active' => false])
            ->assertOk();

        $this->assertStringNotContainsString('Big Mug', $this->page($link));
    }

    // ===== 4. The nesting cap, and the ways round it =====

    /** Two levels is the cap. A card is not a file system. */
    public function test_a_sub_section_cannot_hold_its_own_sub_section(): void
    {
        [$link, $menu] = $this->restaurant();
        $tiffins = $this->cat($menu, 'Tiffins');
        $steamed = $this->cat($menu, 'Steamed', ['parent_id' => $tiffins->id]);

        $this->actingAs($this->user)
            ->postJson('/user/links/'.$link->id.'/restaurant/categories', [
                'name' => 'Rice based', 'parent_id' => $steamed->id,
            ])->assertStatus(422);
    }

    /**
     * And the move that would strand children: demoting a section that
     * already holds sub-sections would put them at depth three. Refused,
     * with a reason, rather than silently flattened.
     */
    public function test_a_section_with_sub_sections_cannot_be_demoted(): void
    {
        [$link, $menu] = $this->restaurant();
        $tiffins = $this->cat($menu, 'Tiffins');
        $this->cat($menu, 'Steamed', ['parent_id' => $tiffins->id]);
        $meals = $this->cat($menu, 'Meals');

        $this->actingAs($this->user)
            ->putJson('/user/links/'.$link->id.'/restaurant/categories/'.$tiffins->id, [
                'parent_id' => $meals->id,
            ])->assertStatus(422);

        $this->assertNull($tiffins->fresh()->parent_id);
    }

    public function test_a_section_cannot_be_put_inside_itself(): void
    {
        [$link, $menu] = $this->restaurant();
        $tiffins = $this->cat($menu, 'Tiffins');

        $this->actingAs($this->user)
            ->putJson('/user/links/'.$link->id.'/restaurant/categories/'.$tiffins->id, [
                'parent_id' => $tiffins->id,
            ])->assertStatus(422);
    }

    /** A category from somebody else's menu is not a parent. */
    public function test_a_section_from_another_menu_is_not_a_parent(): void
    {
        [$linkA, $menuA] = $this->restaurant();
        [, $menuB] = $this->restaurant();
        $theirs = $this->cat($menuB, 'Someone else');

        $this->actingAs($this->user)
            ->postJson('/user/links/'.$linkA->id.'/restaurant/categories', [
                'name' => 'Mine', 'parent_id' => $theirs->id,
            ])->assertStatus(422);
    }

    /** Deleting a section takes its sub-sections and their items with it. */
    public function test_deleting_a_section_takes_its_sub_sections(): void
    {
        [$link, $menu] = $this->restaurant();
        $tiffins = $this->cat($menu, 'Tiffins');
        $sub = $this->cat($menu, 'Steamed', ['parent_id' => $tiffins->id]);
        $idli = $this->item($menu, $sub, 'Idli');

        $this->actingAs($this->user)
            ->deleteJson('/user/links/'.$link->id.'/restaurant/categories/'.$tiffins->id)
            ->assertOk();

        $this->assertNull(RestaurantMenuCategory::find($sub->id));
        $this->assertNull(RestaurantMenuItem::find($idli->id),
            'an orphan sub-section would reappear on the page as its own heading');
    }

    // ===== 5. Dividers =====

    public function test_the_divider_a_creator_picks_reaches_the_page(): void
    {
        [$link, $menu] = $this->restaurant(['layout' => 'list', 'divider' => 'dotted']);
        $cat = $this->cat($menu, 'Dosas');
        $this->item($menu, $cat, 'Masala Dosa');

        // The WRAPPER, not the word. The CSS block on this page names every
        // divider key, so asserting on 'div-dotted' alone passes even when
        // nothing wears the class -- the same false positive the share
        // button hit last week.
        $this->assertStringContainsString('class="items lay-list div-dotted"', $this->page($link));
    }

    /** An unset divider draws exactly what every menu draws today. */
    public function test_a_menu_that_never_picked_a_divider_is_unchanged(): void
    {
        [$link, $menu] = $this->restaurant();
        $cat = $this->cat($menu, 'Dosas');
        $this->item($menu, $cat, 'Masala Dosa');

        $this->assertStringContainsString('class="items lay-list div-line"', $this->page($link));
        $this->assertSame('line', MenuPresentation::divider(null));
        $this->assertSame('line', MenuPresentation::divider('something-invented'));
    }

    // ===== 6. Guards =====

    /**
     * The structural one. These two pages carried the same twenty-line item
     * loop written out twice, which is how the store menu spent a week
     * missing fixes the restaurant menu got. If either page grows its own
     * copy back, this fails.
     */
    public function test_neither_menu_page_keeps_its_own_item_loop(): void
    {
        foreach (['restaurant-menu', 'store-menu'] as $view) {
            $blade = file_get_contents(resource_path('views/common/'.$view.'.blade.php'));

            $this->assertStringNotContainsString('@forelse($cats', $blade,
                $view.' has re-grown its own category loop -- it must include menu-section-list');
            $this->assertStringContainsString('common.partials.menu-section-list', $blade);
        }
    }

    /** Same for the editors. */
    public function test_neither_menu_editor_keeps_its_own_category_list(): void
    {
        foreach (['restaurant', 'store'] as $kind) {
            $blade = file_get_contents(resource_path('views/user/links/'.$kind.'/editor.blade.php'));

            $this->assertStringContainsString('user.links.partials.menu-structure-editor', $blade);
            $this->assertStringNotContainsString('x-for="(cat, ci) in categories"', $blade,
                $kind.' editor has re-grown its own category list');
        }
    }

    /**
     * Every divider the editor offers has to be one the page can draw. The
     * catalog is the single source; this fails if a key is added to one and
     * not the other.
     */
    public function test_every_offered_divider_has_a_rule_on_the_page(): void
    {
        $css = file_get_contents(resource_path('views/common/partials/menu-layout-css.blade.php'));

        foreach (array_keys(MenuPresentation::DIVIDERS) as $key) {
            $this->assertStringContainsString('div-'.$key, $css,
                "the editor offers the '$key' divider and no CSS draws it");
        }
    }
}
