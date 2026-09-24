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
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Sana, 2026-09-23: "for restaurant menu, store menu: i dont see much
 * options of backgrounds, fonts and other changes here... need detailed
 * changes just like in link in bio" and "multiple layout options like 2
 * columns, 1 columns... show atleast 5 options".
 *
 * The font was not missing -- it was ignored. Both page types are in
 * Link::BIOLINK_FAMILY, so they reach the same Appearance screen a Link in
 * Bio uses and updatePageSettings SAVES every key it offers: font_family,
 * stickers, custom CSS. The menu templates then read exactly one of them
 * (the background) and hardcoded the system font stack. A creator could
 * pick Playfair Display, watch it save, and get -apple-system forever.
 *
 * Layout genuinely did not exist: one hardcoded column, photo left, text
 * right, no setting a renderer branched on.
 */
class MenuPagesHonourTheirThemeAndLayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-23 12:00:00'));

        $this->user = User::factory()->create(['onboarded_at' => Carbon::parse('2026-01-01')]);
        app(WorkspaceContext::class)->resolve($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** A restaurant page with one category and one item on it. */
    private function restaurant(array $linkSettings = [], array $menuSettings = []): Link
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'),
            'title' => 'Dosa House', 'is_active' => true,
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

    private function store(array $linkSettings = [], array $menuSettings = []): Link
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'store_menu',
            'alias' => 'sm'.fake()->unique()->numerify('#####'),
            'title' => 'The Shop', 'is_active' => true,
            'settings' => $linkSettings,
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

    // ===== 1. The font the creator picked =====

    /**
     * The whole bug in one test: a font saved on the Appearance screen must
     * reach the page.
     */
    public function test_a_restaurant_page_uses_the_font_the_creator_picked(): void
    {
        $link = $this->restaurant(['biolink' => ['font_family' => 'Playfair Display']]);

        $html = $this->page($link);

        $this->assertStringContainsString("'Playfair Display'", $html,
            'the Appearance screen saves this font for this page type; the page must use it');
        $this->assertStringContainsString('fonts.googleapis.com', $html,
            'and must actually load it, or the browser falls back silently');
    }

    /** Same for the store page. */
    public function test_a_store_page_uses_the_font_the_creator_picked(): void
    {
        $link = $this->store(['biolink' => ['font_family' => 'Playfair Display']]);

        $html = $this->page($link);

        $this->assertStringContainsString("'Playfair Display'", $html);
        $this->assertStringContainsString('fonts.googleapis.com', $html);
    }

    /** A page with no font picked asks for no stylesheet at all. */
    public function test_a_page_with_no_font_chosen_loads_no_font(): void
    {
        $html = $this->page($this->restaurant());

        $this->assertStringNotContainsString('fonts.googleapis.com', $html,
            'the default page must not pay for a request it does not need');
        $this->assertStringContainsString('-apple-system', $html,
            'and still has a font stack');
    }

    /** An uploaded font has no @font-face here, so it must not be claimed. */
    public function test_an_uploaded_font_falls_back_rather_than_breaking(): void
    {
        $link = $this->restaurant(['biolink' => ['font_family' => 'custom:My Brand Sans']]);

        $html = $this->page($link);

        $this->assertStringNotContainsString('My Brand Sans', $html,
            'these templates serve no @font-face, so naming a custom family '
            .'would point the browser at a font it can never find');
        $this->assertStringContainsString('-apple-system', $html);
    }

    /** Something that is not in the font catalog is ignored, not echoed. */
    public function test_an_unknown_family_is_ignored(): void
    {
        $link = $this->restaurant(['biolink' => ['font_family' => 'Definitely Not A Font']]);

        $html = $this->page($link);
        $this->assertStringNotContainsString('Definitely Not A Font', $html);
    }

    // ===== 2. Five layouts =====

    /** There are at least the five Sana asked for. */
    public function test_there_are_at_least_five_layouts(): void
    {
        $this->assertGreaterThanOrEqual(5, count(MenuPresentation::LAYOUTS));

        foreach (MenuPresentation::LAYOUTS as $key => $meta) {
            $this->assertNotSame('', trim($meta['label']), $key.' needs a name');
            $this->assertNotSame('', trim($meta['hint']), $key.' needs to say when to use it');
        }
    }

    /** Each one reaches the page, and each one is actually styled. */
    public function test_every_layout_renders_and_has_css_of_its_own(): void
    {
        foreach (array_keys(MenuPresentation::LAYOUTS) as $key) {
            $html = $this->page($this->restaurant([], ['layout' => $key]));

            $this->assertStringContainsString('class="items lay-'.$key.' div-', $html,
                $key.' must reach the item list');
            $this->assertStringContainsString('.items.lay-'.$key, $html,
                $key.' is named but has no CSS, so it would render as the default');
        }
    }

    /** The store page offers the same five. */
    public function test_the_store_page_takes_a_layout_too(): void
    {
        $html = $this->page($this->store([], ['layout' => 'grid']));
        $this->assertStringContainsString('class="items lay-grid div-', $html);
    }

    /** A page that never chose one renders exactly as it always did. */
    public function test_an_unset_layout_stays_on_the_original_list(): void
    {
        $this->assertStringContainsString('class="items lay-list div-', $this->page($this->restaurant()));
    }

    /** Junk falls back rather than rendering an unstyled page. */
    public function test_a_junk_layout_falls_back_to_the_list(): void
    {
        $html = $this->page($this->restaurant([], ['layout' => '../../etc/passwd']));

        $this->assertStringContainsString('class="items lay-list div-', $html);
        $this->assertStringNotContainsString('etc/passwd', $html);
    }

    /** The side-by-side layouts get a wider column; the reading ones do not. */
    public function test_the_column_widens_only_for_the_side_by_side_layouts(): void
    {
        foreach (['cards', 'grid'] as $wide) {
            $this->assertStringContainsString('class="page wide"', $this->page($this->restaurant([], ['layout' => $wide])),
                $wide.' puts items side by side and needs the room');
        }
        foreach (['list', 'compact', 'showcase'] as $narrow) {
            $this->assertStringContainsString('class="page"', $this->page($this->restaurant([], ['layout' => $narrow])),
                $narrow.' is a reading layout and keeps its narrow column');
        }
    }

    // ===== 3. The editor offers it =====

    /** All five are pickable, with their descriptions. */
    public function test_the_editor_offers_every_layout(): void
    {
        $link = $this->restaurant();

        $html = $this->actingAs($this->user)
            ->get('/user/links/'.$link->id.'/restaurant')->assertOk()->getContent();

        foreach (MenuPresentation::LAYOUTS as $key => $meta) {
            $this->assertStringContainsString('value="'.$key.'"', $html);
            $this->assertStringContainsString($meta['label'], $html);
        }
        $this->assertStringContainsString('Background &amp; fonts', $html,
            'the panel should say the font lives on that screen, since it now works');
    }

    /** Saving one sticks. */
    public function test_a_layout_can_be_saved_from_the_editor(): void
    {
        $link = $this->restaurant();

        $this->actingAs($this->user)
            ->postJson('/user/links/'.$link->id.'/restaurant/settings', [
                'mode' => 'display', 'currency' => 'INR', 'layout' => 'showcase',
            ])->assertOk();

        $this->assertSame('showcase', $link->fresh()->restaurantMenu->settings['layout']);
        $this->assertStringContainsString('class="items lay-showcase div-', $this->page($link));
    }

    /** And a junk one saved through the API is corrected, not stored. */
    public function test_a_junk_layout_is_corrected_on_save(): void
    {
        $link = $this->restaurant();

        $this->actingAs($this->user)
            ->postJson('/user/links/'.$link->id.'/restaurant/settings', [
                'mode' => 'display', 'currency' => 'INR', 'layout' => 'nonsense',
            ])->assertOk();

        $this->assertSame('list', $link->fresh()->restaurantMenu->settings['layout']);
    }

    /** The store editor offers them too. */
    public function test_the_store_editor_offers_every_layout(): void
    {
        $link = $this->store();

        $html = $this->actingAs($this->user)
            ->get('/user/links/'.$link->id.'/store')->assertOk()->getContent();

        foreach (array_keys(MenuPresentation::LAYOUTS) as $key) {
            $this->assertStringContainsString('value="'.$key.'"', $html);
        }
    }
}
