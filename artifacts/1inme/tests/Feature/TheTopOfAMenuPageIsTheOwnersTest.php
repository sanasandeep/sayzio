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
use App\Modules\User\Support\MenuHero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sana, 2026-10-04: "Priyumm tiffins and order at table both..... can be
 * optional hidden.. also alignment and color and style changes....".
 *
 * ---- Why hiding your own restaurant's name is reasonable --------------
 *
 * Because it is already in the logo block above it. The menu page renders
 * creator blocks above the title, and the first thing a restaurant puts
 * there is its logo -- with the name in it. His screenshot is exactly that:
 * the Priyumm Tiffins logo, and then "Priyumm Tiffins" again as an h1
 * underneath, with no way to drop the second.
 *
 * ---- What the tests are mostly about ----------------------------------
 *
 * That hidden means hidden on the PAGE and not removed from the document.
 * A title still has to reach <title>, the OG tags and the heading a screen
 * reader announces -- otherwise "hide the name" quietly becomes "publish an
 * untitled page", which is a different and much worse thing to have done to
 * somebody's menu.
 */
class TheTopOfAMenuPageIsTheOwnersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->user);
    }

    private function restaurant(array $settings = [], ?string $desc = null): Link
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'),
            'title' => 'Priyumm Tiffins', 'is_active' => true,
            // Where a page description actually lives. `Link::description`
            // has no column and no accessor -- see the note on the menu
            // templates about why this line used to be unreachable.
            'settings' => $desc ? ['biolink' => ['biolink_description' => $desc]] : [],
        ]);
        $link->biolinkBlocks()->delete();

        $menu = RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'order', 'currency' => 'INR', 'accent_color' => '#e0457b',
            'settings' => $settings,
        ]);
        $cat = RestaurantMenuCategory::create([
            'menu_id' => $menu->id, 'name' => 'Starters', 'sort_order' => 0, 'is_active' => true,
        ]);
        RestaurantMenuItem::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Chilli Paneer',
            'price' => 90, 'sort_order' => 0, 'is_active' => true,
        ]);

        return $link;
    }

    private function store(array $settings = []): Link
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'store_menu',
            'alias' => 'sm'.fake()->unique()->numerify('#####'),
            'title' => 'The Shop', 'is_active' => true,
        ]);
        $link->biolinkBlocks()->delete();

        $menu = StoreMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'order', 'currency' => 'INR', 'accent_color' => '#e0457b',
            'settings' => $settings,
        ]);
        $cat = StoreCategory::create(['menu_id' => $menu->id, 'name' => 'Mugs', 'sort_order' => 0, 'is_active' => true]);
        StoreProduct::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Big Mug',
            'price' => 100, 'sort_order' => 0, 'is_active' => true,
        ]);

        return $link;
    }

    // ===== 1. A menu nobody has touched does not move =====

    /**
     * Every menu in the product has none of these settings. This whole
     * change is only safe if such a menu renders the hero exactly as it
     * did: name shown, badge shown, left, 26px/800.
     */
    public function test_an_untouched_menu_renders_its_hero_as_before(): void
    {
        $html = $this->get('/'.$this->restaurant()->alias)->assertOk()->getContent();

        $this->assertStringContainsString('<h1>Priyumm Tiffins</h1>', $html);
        $this->assertStringContainsString('Order at table', $html);
        $this->assertStringContainsString('.hero { text-align: left; }', $html);
        $this->assertStringContainsString('font-size: 26px; font-weight: 800;', $html);
    }

    public function test_the_defaults_are_the_markup_that_was_there(): void
    {
        $r = MenuHero::resolve([]);

        $this->assertFalse($r['hero_title_hidden']);
        $this->assertFalse($r['hero_badge_hidden']);
        $this->assertSame('left', $r['hero_align']);
        $this->assertSame('medium', $r['hero_size']);
        $this->assertSame('', $r['hero_title_color']);
    }

    // ===== 2. Hiding =====

    public function test_the_name_can_be_hidden_from_the_page(): void
    {
        $html = $this->get('/'.$this->restaurant(['hero_title_hidden' => true])->alias)
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('<h1>Priyumm Tiffins</h1>', $html);
    }

    /**
     * The load-bearing half. Hidden is a visual choice about one block, not
     * a decision to publish an untitled document -- the name still has to
     * reach the browser tab, the link preview and search.
     */
    public function test_a_hidden_name_is_still_the_documents_title(): void
    {
        $html = $this->get('/'.$this->restaurant(['hero_title_hidden' => true])->alias)
            ->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<title>[^<]*Priyumm Tiffins/', $html);
        $this->assertStringContainsString('Priyumm Tiffins', $html);
    }

    public function test_the_badge_can_be_hidden_on_its_own(): void
    {
        $html = $this->get('/'.$this->restaurant(['hero_badge_hidden' => true])->alias)
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('Order at table', $html);
        // The name is untouched by the badge's switch.
        $this->assertStringContainsString('<h1>Priyumm Tiffins</h1>', $html);
    }

    public function test_the_store_badge_hides_too(): void
    {
        $html = $this->get('/'.$this->store(['hero_badge_hidden' => true])->alias)
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('Order requests open', $html);
    }

    /**
     * With both hidden and no description the hero was an empty div with
     * 46px of padding -- a gap at the top of the page nobody asked for.
     */
    public function test_an_empty_hero_is_not_drawn_at_all(): void
    {
        $html = $this->get('/'.$this->restaurant([
            'hero_title_hidden' => true, 'hero_badge_hidden' => true,
        ])->alias)->assertOk()->getContent();

        $this->assertStringNotContainsString('<div class="hero">', $html);
    }

    /** But a description alone is reason enough to keep it. */
    public function test_a_description_keeps_the_hero_alive(): void
    {
        $html = $this->get('/'.$this->restaurant([
            'hero_title_hidden' => true, 'hero_badge_hidden' => true,
        ], 'Home-style tiffins since 1998.')->alias)->assertOk()->getContent();

        $this->assertStringContainsString('<div class="hero">', $html);
        $this->assertStringContainsString('Home-style tiffins since 1998.', $html);
    }

    /**
     * Found while building the hero: both menu templates read
     * `$link->description`, which Link has no column and no accessor for,
     * so it was null on every menu ever rendered. The description a creator
     * types into Page Design has never once appeared on a menu page. Same
     * source and fallback as the Link in Bio page now.
     */
    public function test_a_page_description_finally_shows_on_a_menu(): void
    {
        $html = $this->get('/'.$this->restaurant([], 'Home-style tiffins since 1998.')->alias)
            ->assertOk()->getContent();

        $this->assertStringContainsString('<p>Home-style tiffins since 1998.</p>', $html);
    }

    /** And it falls back to the SEO description, as the bio page does. */
    public function test_the_description_falls_back_to_the_seo_one(): void
    {
        $link = $this->restaurant();
        $link->update(['seo_description' => 'Tiffins, dosas and filter coffee.']);

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        $this->assertStringContainsString('Tiffins, dosas and filter coffee.', $html);
    }

    public function test_isEmpty_is_decided_by_all_three_parts(): void
    {
        $both = MenuHero::resolve(['hero_title_hidden' => true, 'hero_badge_hidden' => true]);

        $this->assertTrue(MenuHero::isEmpty($both, false, true));
        $this->assertFalse(MenuHero::isEmpty($both, true, true), 'A description keeps it.');
        $this->assertFalse(
            MenuHero::isEmpty(MenuHero::resolve(['hero_badge_hidden' => true]), false, true),
            'The name alone keeps it.'
        );
        // A display-only menu has no badge to begin with, so hiding only the
        // name empties it.
        $this->assertTrue(MenuHero::isEmpty(MenuHero::resolve(['hero_title_hidden' => true]), false, false));
    }

    // ===== 3. Alignment, size, colour =====

    public function test_alignment_reaches_the_page(): void
    {
        foreach (array_keys(MenuHero::ALIGNMENTS) as $align) {
            $html = $this->get('/'.$this->restaurant(['hero_align' => $align])->alias)
                ->assertOk()->getContent();

            $this->assertStringContainsString('.hero { text-align: '.$align.'; }', $html);
        }
    }

    public function test_every_size_reaches_the_page(): void
    {
        foreach (MenuHero::SIZES as $key => $meta) {
            $html = $this->get('/'.$this->restaurant(['hero_size' => $key])->alias)
                ->assertOk()->getContent();

            $this->assertStringContainsString(
                'font-size: '.$meta['size'].'; font-weight: '.$meta['weight'].';',
                $html,
                'The "'.$meta['label'].'" size does not reach the page.'
            );
        }
    }

    public function test_the_colours_reach_the_page_and_blank_inherits(): void
    {
        $html = $this->get('/'.$this->restaurant([
            'hero_title_color' => '#ff8800', 'hero_badge_color' => '#00aa55',
        ])->alias)->assertOk()->getContent();

        $this->assertStringContainsString('.hero h1 { color: #ff8800; }', $html);
        $this->assertStringContainsString('.hero .badge { background: #00aa55; }', $html);

        // Unset emits no rule at all, so the page keeps inheriting.
        $plain = $this->get('/'.$this->restaurant()->alias)->assertOk()->getContent();
        $this->assertStringNotContainsString('.hero h1 { color:', $plain);
    }

    /** These land in a style block on a public page. */
    public function test_a_colour_that_is_not_a_colour_does_not_reach_the_css(): void
    {
        $html = $this->get('/'.$this->restaurant([
            'hero_title_color' => 'red; } body { display:none } .x {',
        ])->alias)->assertOk()->getContent();

        $this->assertStringNotContainsString('body { display:none }', $html);
        $this->assertStringNotContainsString('.hero h1 { color: red', $html);
    }

    public function test_junk_settings_read_as_the_defaults(): void
    {
        $this->assertSame('left', MenuHero::align('diagonal'));
        $this->assertSame('medium', MenuHero::size('enormous'));
        $this->assertSame('left', MenuHero::align(null));
    }

    // ===== 4. It saves, and the editor opens on what is saved =====

    public function test_the_settings_save_and_come_back_to_the_editor(): void
    {
        $link = $this->restaurant();

        $this->actingAs($this->user)->postJson('/user/links/'.$link->id.'/restaurant/settings', [
            'mode' => 'order', 'currency' => 'INR',
            'hero_title_hidden' => true,
            'hero_align' => 'center',
            'hero_size' => 'huge',
            'hero_title_color' => '#ff8800',
        ])->assertOk();

        $html = $this->actingAs($this->user)
            ->get('/user/links/'.$link->id.'/restaurant')
            ->assertOk()->getContent();

        // The read half -- the thing that was broken for the item colours.
        $this->assertMatchesRegularExpression('/"hero_title_hidden":true/', $html);
        $this->assertMatchesRegularExpression('/"hero_align":"center"/', $html);
        $this->assertMatchesRegularExpression('/"hero_size":"huge"/', $html);
        $this->assertStringContainsString('#ff8800', $html);
    }

    public function test_the_panel_is_on_both_editors(): void
    {
        $pages = [
            $this->actingAs($this->user)->get('/user/links/'.$this->restaurant()->id.'/restaurant'),
            $this->actingAs($this->user)->get('/user/links/'.$this->store()->id.'/store'),
        ];

        foreach ($pages as $page) {
            $html = $page->assertOk()->getContent();
            $this->assertStringContainsString('Hide the page name', $html);
            $this->assertStringContainsString('hero_align', $html);
            $this->assertStringContainsString('hero_size', $html);
        }
    }

    /** Each page type names its own badge, rather than both saying one. */
    public function test_each_editor_names_the_badge_its_own_page_shows(): void
    {
        $r = $this->actingAs($this->user)
            ->get('/user/links/'.$this->restaurant()->id.'/restaurant')->assertOk()->getContent();
        $s = $this->actingAs($this->user)
            ->get('/user/links/'.$this->store()->id.'/store')->assertOk()->getContent();

        $this->assertStringContainsString('Order at table', $r);
        $this->assertStringContainsString('Order requests open', $s);
    }

    public function test_a_size_nobody_offers_is_refused_on_save(): void
    {
        $link = $this->restaurant();

        $this->actingAs($this->user)->postJson('/user/links/'.$link->id.'/restaurant/settings', [
            'mode' => 'order', 'currency' => 'INR',
            'hero_size' => 'enormous',
        ])->assertStatus(422);
    }
}
