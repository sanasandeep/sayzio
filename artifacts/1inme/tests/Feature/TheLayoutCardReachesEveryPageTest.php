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
use App\Modules\User\Support\PageLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sana, 2026-10-04, with Page Padding Top set to 200 on a restaurant menu:
 * "page padding seems to be not working...".
 *
 * ---- It reached one page type out of three --------------------------
 *
 * The Layout card writes settings.biolink.layout and `biolink.blade.php`
 * reads it. The restaurant menu and the store menu are their own templates
 * and read none of it: their container was
 *
 *     .page { max-width: 760px; margin: 0 auto; padding: 0 16px 120px; }
 *
 * as literals. Four controls -- max width per device, top, bottom and side
 * padding -- saved correctly, showed correctly in the editor, and changed
 * nothing on the page.
 *
 * So the tests below are mostly about the MENU pages, and they assert
 * numbers the owner typed rather than the shape of a CSS rule: a test that
 * checks ".page {" exists would have passed throughout the bug.
 */
class TheLayoutCardReachesEveryPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->user);
    }

    /** @param array<string,mixed> $layout */
    private function restaurant(array $layout = []): Link
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'),
            'title' => 'Priyumm Tiffins', 'is_active' => true,
            'settings' => $layout ? ['biolink' => ['layout' => $layout]] : [],
        ]);
        $link->biolinkBlocks()->delete();

        $menu = RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'order', 'currency' => 'INR', 'accent_color' => '#e0457b', 'settings' => [],
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

    /** @param array<string,mixed> $layout */
    private function store(array $layout = []): Link
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'store_menu',
            'alias' => 'sm'.fake()->unique()->numerify('#####'),
            'title' => 'The Shop', 'is_active' => true,
            'settings' => $layout ? ['biolink' => ['layout' => $layout]] : [],
        ]);
        $link->biolinkBlocks()->delete();

        $menu = StoreMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'order', 'currency' => 'INR', 'accent_color' => '#e0457b', 'settings' => [],
        ]);
        $cat = StoreCategory::create(['menu_id' => $menu->id, 'name' => 'Mugs', 'sort_order' => 0, 'is_active' => true]);
        StoreProduct::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Big Mug',
            'price' => 100, 'sort_order' => 0, 'is_active' => true,
        ]);

        return $link;
    }

    // ===== 1. The number he typed =====

    /** The exact report: 200 in the Top box, nothing on the page. */
    public function test_a_menus_top_padding_is_the_number_the_owner_typed(): void
    {
        $link = $this->restaurant(['page_padding_top' => 200]);

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        $this->assertStringContainsString('padding: 200px', $html);
    }

    public function test_all_three_paddings_reach_a_menu_page(): void
    {
        $link = $this->restaurant([
            'page_padding_top' => 120, 'page_padding_bottom' => 90, 'page_padding_x' => 40,
        ]);

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        $this->assertStringContainsString('padding: 120px 40px 90px;', $html);
    }

    public function test_the_three_max_widths_reach_a_menu_page(): void
    {
        $link = $this->restaurant([
            'max_width_phone' => 400, 'max_width_tablet' => 600, 'max_width_desktop' => 900,
        ]);

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        $this->assertStringContainsString('max-width: 400px;', $html);
        $this->assertStringContainsString('max-width: 600px;', $html);
        $this->assertStringContainsString('max-width: 900px;', $html);
        // Not "no 760 anywhere": the cart bar legitimately has its own, and
        // the first version of this assertion matched the CSS COMMENT I had
        // written above the rule explaining what the old literal was. What
        // actually matters is that the .page rule carries the typed width.
        $this->assertMatchesRegularExpression(
            '/\.page\s*\{[^}]*max-width:\s*400px/s',
            $html,
            'The .page container is not using the phone width the owner set.'
        );
    }

    public function test_the_store_menu_honours_the_same_card(): void
    {
        $link = $this->store(['page_padding_top' => 150, 'max_width_phone' => 420]);

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        $this->assertStringContainsString('padding: 150px', $html);
        $this->assertStringContainsString('max-width: 420px;', $html);
    }

    /** And the page it already worked on still works. */
    public function test_a_link_in_bio_still_honours_it(): void
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'biolink',
            'alias' => 'bl'.fake()->unique()->numerify('#####'),
            'title' => 'My Page', 'is_active' => true,
            'settings' => ['biolink' => ['layout' => ['page_padding_top' => 180, 'max_width_phone' => 420]]],
        ]);

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        $this->assertStringContainsString('180px', $html);
        $this->assertStringContainsString('420px', $html);
    }

    // ===== 2. A menu nobody has configured does not move =====

    /**
     * The one that matters for everybody else, and the trap in this fix.
     *
     * Every menu in the product has no layout settings at all. Making the
     * Layout card work is only safe if an unconfigured menu renders
     * EXACTLY as it did when the numbers were literals -- otherwise this
     * reflows every menu Sana has, to make a settings card true. So the
     * menu defaults are those literals, not the Link in Bio set the card
     * used to print.
     */
    public function test_an_unconfigured_menu_renders_exactly_as_it_did(): void
    {
        $link = $this->restaurant();

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        // The old rule, to the pixel: `padding: 0 16px 120px`.
        $this->assertStringContainsString('padding: 0px 16px 120px;', $html);
        // 760 above 640px, as before. The base caps at 600 rather than 760
        // only because 600 is the most the phone box accepts -- and below
        // 640px the viewport is narrower than either, so nothing moves on
        // a real phone.
        $this->assertMatchesRegularExpression('/\.page\s*\{[^}]*max-width:\s*600px/s', $html);
        $this->assertStringContainsString('@media (min-width: 640px) { .page { max-width: 760px; } }', $html);
        $this->assertStringContainsString('@media (min-width: 1024px) { .page { max-width: 760px; } }', $html);
    }

    public function test_an_unconfigured_store_renders_exactly_as_it_did(): void
    {
        $html = $this->get('/'.$this->store()->alias)->assertOk()->getContent();

        $this->assertStringContainsString('padding: 0px 16px 120px;', $html);
        $this->assertStringContainsString('max-width: 760px;', $html);
    }

    /** And a Link in Bio keeps the set IT has always had. */
    public function test_an_unconfigured_link_in_bio_keeps_its_own_defaults(): void
    {
        $this->assertSame(448, PageLayout::resolve([])['max_width_phone']);
        $this->assertSame(32, PageLayout::resolve([])['page_padding_top']);
        $this->assertSame(64, PageLayout::resolve([])['page_padding_bottom']);
    }

    /**
     * The boxes open on the numbers the page is actually using.
     *
     * They were hard-coded to the Link in Bio set, so an owner opening the
     * card on a restaurant menu was told their top padding was 32 when the
     * page was rendering 0 -- which is the other half of "page padding
     * seems to be not working": the card was describing a different page.
     */
    public function test_the_editor_boxes_open_on_this_page_types_own_defaults(): void
    {
        // Written out, NOT taken from PageLayout::resolve(). Deriving the
        // expectation from the thing under test made this pass while a
        // sabotaged resolve() and a sabotaged editor were both wrong in
        // the same direction. These are the numbers a menu has always
        // rendered with, stated independently.
        $menu = [
            'max_width_tablet' => 760,
            'max_width_desktop' => 760,
            'page_padding_top' => 0,
            'page_padding_bottom' => 120,
            'page_padding_x' => 16,
            'block_gap' => 12,
        ];

        $html = $this->actingAs($this->user)
            ->get('/user/links/'.$this->restaurant()->id.'/settings/layout')
            ->assertOk()->getContent();

        foreach (array_keys($menu) as $key) {
            // Matched on THIS input, not on `value="0"` appearing anywhere
            // on the page -- which it does, and which let a sabotaged
            // version of this file pass when the box was hard-coded back
            // to the Link in Bio number.
            $this->assertMatchesRegularExpression(
                '/name="layout\['.$key.'\]"[^>]*value="'.$menu[$key].'"/',
                $html,
                'The editor box for '.$key.' does not open on the number this page renders with.'
            );
        }
    }

    /** A Link in Bio's card still opens on the Link in Bio numbers. */
    public function test_the_editor_boxes_differ_for_a_link_in_bio(): void
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'biolink',
            'alias' => 'bl'.fake()->unique()->numerify('#####'),
            'title' => 'My Page', 'is_active' => true,
        ]);

        $html = $this->actingAs($this->user)
            ->get('/user/links/'.$link->id.'/settings/layout')
            ->assertOk()->getContent();

        $this->assertStringContainsString('value="448"', $html);
        $this->assertStringContainsString('value="32"', $html);
    }

    // ===== 3. Junk in the settings blob does not render =====

    /**
     * These values come out of a JSON column that predates the bounds, so
     * a row written before they existed can hold anything. A max width of
     * 4 would render a menu one character wide.
     */
    public function test_a_value_outside_the_bounds_is_clamped_on_the_way_out(): void
    {
        $link = $this->restaurant(['max_width_phone' => 4, 'page_padding_top' => 9999]);

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        $this->assertStringContainsString('max-width: 280px;', $html);
        $this->assertStringContainsString('padding: 200px', $html);
        // The fields he did not touch still come from the menu set.
        $this->assertStringContainsString('padding: 200px 16px 120px;', $html);
    }

    public function test_nonsense_falls_back_to_the_default(): void
    {
        $this->assertSame(32, PageLayout::resolve(['layout' => ['page_padding_top' => 'tall']])['page_padding_top']);
        $this->assertSame(32, PageLayout::resolve(['layout' => ['page_padding_top' => '']])['page_padding_top']);
        $this->assertSame(32, PageLayout::resolve(['layout' => 'not an array'])['page_padding_top']);
        $this->assertSame(32, PageLayout::resolve([])['page_padding_top']);
    }

    /**
     * Zero is a number somebody chose, not a missing value. An owner who
     * wants their menu flush to the top of the screen has to be able to
     * say so.
     */
    public function test_zero_is_honoured_rather_than_treated_as_unset(): void
    {
        $link = $this->restaurant(['page_padding_top' => 0, 'page_padding_x' => 0]);

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        $this->assertStringContainsString('padding: 0px 0px 120px;', $html);
    }

    /** Block padding keeps meaning "inherit" when it is unset. */
    public function test_block_padding_is_not_given_a_number_it_never_had(): void
    {
        $this->assertArrayNotHasKey('block_padding', PageLayout::resolve([]));
    }
}
