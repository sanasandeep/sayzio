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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Three more of Sana's notes from 2026-09-23, all about how the card is
 * SET rather than what is on it:
 *
 *   "design and style of cats and sub cats"
 *   "pricing on same column or new column"
 *   "design looks of menu section"
 *
 * He was comparing the screen to his printed Priyumm Tiffins card, which
 * centres its section titles between rules and runs its prices down the
 * right edge with leader dots.
 *
 * ---- Two axes, not more layouts ----------------------------------------
 *
 * Layout decides how one ITEM is drawn. These decide how the CARD is set,
 * and every combination is valid. Folding them into LAYOUTS would have
 * meant fifteen layouts to express five looks times three price
 * placements, and a creator choosing "Two columns with centred headings
 * and dotted prices" from a list.
 *
 * ---- The one that needed care ------------------------------------------
 *
 * The leader dots already existed -- welded into the Compact layout, so
 * the thing his card does on every line was unreachable from the other
 * four and could not be turned off in the one that had it. Pulling them
 * out into their own axis is the fix, and the whole risk: an unset value
 * has to keep meaning dots for a Compact menu, or every Compact menu on
 * the platform silently changes. That is what most of the tests below are
 * about.
 */
class TheMenuCardIsSetLikeAPrintedOneTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->user);
    }

    private function restaurant(array $menuSettings = []): Link
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
        $cat = StoreCategory::create(['menu_id' => $menu->id, 'name' => 'Mugs', 'sort_order' => 0, 'is_active' => true]);
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

    private function editor(Link $link, string $kind = 'restaurant'): string
    {
        return $this->actingAs($this->user)
            ->get('/user/links/'.$link->id.'/'.$kind)->assertOk()->getContent();
    }

    // ===== 1. Nothing moves for a menu nobody has restyled =====

    /**
     * The one that matters most. Both axes are new, both have defaults, and
     * a live menu that has never seen this screen must render exactly what
     * it rendered yesterday.
     */
    public function test_a_menu_that_never_chose_anything_is_set_as_it_was(): void
    {
        $html = $this->page($this->restaurant());

        $this->assertStringContainsString('head-plain price-inline"', $html);
    }

    /**
     * And the Compact exception, which is the actual trap. Leader dots used
     * to BE part of what Compact meant. Pulling them out into their own
     * axis must not take them away from the menus that have them.
     */
    public function test_a_compact_menu_keeps_its_leader_dots_without_asking(): void
    {
        $html = $this->page($this->restaurant(['layout' => 'compact']));

        $this->assertStringContainsString('price-dots"', $html,
            'every Compact menu on the platform would silently lose its dots');
        $this->assertSame('dots', MenuPresentation::price(null, 'compact'));
        $this->assertSame('inline', MenuPresentation::price(null, 'list'));
    }

    /** And a Compact menu can now say no, which it never could before. */
    public function test_a_compact_menu_can_turn_its_dots_off(): void
    {
        $html = $this->page($this->restaurant(['layout' => 'compact', 'price_style' => 'inline']));

        // The WRAPPER, not the word: the page's own CSS block names every
        // placement key, so 'price-dots' appears whatever is chosen.
        $this->assertStringContainsString('price-inline"', $html);
        $this->assertStringNotContainsString('price-dots"', $html);
    }

    // ===== 2. The choices reach the page =====

    public function test_every_heading_style_reaches_the_page_and_has_css(): void
    {
        foreach (array_keys(MenuPresentation::HEADINGS) as $key) {
            $html = $this->page($this->restaurant(['heading_style' => $key]));

            $this->assertStringContainsString('head-'.$key.' price-', $html,
                $key.' must reach the page wrapper');
            $this->assertStringContainsString('.head-'.$key, $html,
                $key.' is offered but nothing draws it, so it would render as plain');
        }
    }

    public function test_every_price_placement_reaches_the_page_and_has_css(): void
    {
        foreach (array_keys(MenuPresentation::PRICES) as $key) {
            $html = $this->page($this->restaurant(['price_style' => $key]));

            $this->assertStringContainsString('price-'.$key.'"', $html);

            // `inline` is the absence of the other two -- there is nothing
            // for it to override, so it correctly has no rules of its own.
            if ($key !== 'inline') {
                $this->assertStringContainsString('.price-'.$key, $html,
                    $key.' is offered but nothing draws it');
            }
        }
    }

    /** The two axes are independent: any heading with any price placement. */
    public function test_the_two_choices_do_not_exclude_each_other(): void
    {
        $html = $this->page($this->restaurant([
            'layout' => 'cards', 'heading_style' => 'centered', 'price_style' => 'dots',
        ]));

        $this->assertStringContainsString('head-centered price-dots', $html);
        $this->assertStringContainsString('lay-cards', $html,
            'a card layout with dotted prices is a real combination, not a conflict');
    }

    /** The store is the same product and gets the same treatment. */
    public function test_the_store_is_set_the_same_way(): void
    {
        $html = $this->page($this->store(['heading_style' => 'label', 'price_style' => 'right']));

        $this->assertStringContainsString('head-label price-right"', $html);
    }

    // ===== 3. Saving =====

    public function test_a_card_design_can_be_saved_from_the_editor(): void
    {
        $link = $this->restaurant();

        $this->actingAs($this->user)
            ->postJson('/user/links/'.$link->id.'/restaurant/settings', [
                'mode' => 'display', 'currency' => 'INR',
                'heading_style' => 'centered', 'price_style' => 'dots',
            ])->assertOk();

        $settings = $link->fresh()->restaurantMenu->settings;

        $this->assertSame('centered', $settings['heading_style']);
        $this->assertSame('dots', $settings['price_style']);
        $this->assertStringContainsString('head-centered price-dots', $this->page($link));
    }

    public function test_the_store_saves_it_too(): void
    {
        $link = $this->store();

        $this->actingAs($this->user)
            ->postJson('/user/links/'.$link->id.'/store/settings', [
                'mode' => 'display', 'currency' => 'INR',
                'heading_style' => 'banner', 'price_style' => 'right',
            ])->assertOk();

        $this->assertStringContainsString('head-banner price-right', $this->page($link));
    }

    /** Junk is corrected rather than stored. */
    public function test_an_invented_heading_style_falls_back_rather_than_rendering_nothing(): void
    {
        $link = $this->restaurant();

        $this->actingAs($this->user)
            ->postJson('/user/links/'.$link->id.'/restaurant/settings', [
                'mode' => 'display', 'currency' => 'INR',
                'heading_style' => '../../etc/passwd',
            ])->assertOk();

        $this->assertSame('plain', $link->fresh()->restaurantMenu->settings['heading_style']);
        $this->assertStringNotContainsString('etc/passwd', $this->page($link));
    }

    /**
     * Junk in the price field CLEARS the key rather than storing a
     * corrected one, because the correct value depends on the layout. A
     * menu that stored 'inline' would stop being a dotted Compact card the
     * day its owner switched to Compact.
     */
    public function test_a_junk_price_placement_is_cleared_not_frozen(): void
    {
        $link = $this->restaurant(['layout' => 'compact']);

        $this->actingAs($this->user)
            ->postJson('/user/links/'.$link->id.'/restaurant/settings', [
                'mode' => 'display', 'currency' => 'INR', 'layout' => 'compact',
                'price_style' => 'nonsense',
            ])->assertOk();

        $this->assertArrayNotHasKey('price_style', $link->fresh()->restaurantMenu->settings);
        $this->assertStringContainsString('price-dots"', $this->page($link),
            'the layout default has to still apply, not a frozen corrected value');
    }

    // ===== 4. The editor offers them =====

    public function test_both_editors_offer_both_choices(): void
    {
        foreach ([[$this->restaurant(), 'restaurant'], [$this->store(), 'store']] as [$link, $kind]) {
            $html = $this->editor($link, $kind);

            $this->assertStringContainsString('x-model="menu.heading_style"', $html);
            $this->assertStringContainsString('x-model="menu.price_style"', $html);
            $this->assertStringContainsString('Section titles', $html);
        }
    }

    /**
     * Two layouts put the photo above the text, so a right-hand price has
     * nothing to line up against and stays inline. The control is still
     * offered -- it applies again when the layout changes -- and the editor
     * says what is happening rather than appearing to do nothing.
     */
    public function test_the_editor_says_when_the_layout_ignores_the_price_placement(): void
    {
        $html = $this->editor($this->restaurant(), 'restaurant');

        $this->assertStringContainsString("['grid','showcase'].includes(menu.layout)", $html);
        $this->assertStringContainsString('prices stay under the name here', $html);
    }

    // ===== 5. Guards =====

    /**
     * Every style the editor offers has to be one the page can draw, in
     * both directions: the catalog is the single source, and this fails if
     * a key is added to one side and not the other.
     */
    public function test_every_offered_style_is_drawn_somewhere(): void
    {
        $css = file_get_contents(resource_path('views/common/partials/menu-layout-css.blade.php'));

        foreach (array_keys(MenuPresentation::HEADINGS) as $key) {
            $this->assertStringContainsString('.head-'.$key, $css,
                "the editor offers the '$key' heading and no CSS draws it");
        }
        foreach (['right', 'dots'] as $key) {
            $this->assertStringContainsString('.price-'.$key, $css,
                "the editor offers the '$key' price placement and no CSS draws it");
        }
    }

    /**
     * The structural one. The leader dots must not go back to living inside
     * the Compact layout: that is what made them unreachable from the other
     * four and impossible to switch off in the one that had them.
     */
    public function test_the_leader_dots_do_not_belong_to_one_layout(): void
    {
        $css = file_get_contents(resource_path('views/common/partials/menu-layout-css.blade.php'));

        $this->assertStringNotContainsString('.items.lay-compact .item .info::after', $css,
            'the dots have been welded back into the Compact layout');
    }

    /** And the pickers stay single-source across the two editors. */
    public function test_neither_editor_keeps_its_own_copy_of_the_pickers(): void
    {
        foreach (['restaurant', 'store'] as $kind) {
            $blade = file_get_contents(resource_path('views/user/links/'.$kind.'/editor.blade.php'));

            $this->assertStringContainsString('user.links.partials.menu-card-design', $blade);
            $this->assertStringNotContainsString('MenuPresentation::HEADINGS as', $blade,
                $kind.' editor has its own copy of the heading picker');
        }
    }
}
