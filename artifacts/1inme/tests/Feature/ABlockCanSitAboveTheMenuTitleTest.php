<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuCategory;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\BlockStyleSanitizer;
use App\Modules\User\Support\MenuBlockSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sana, 2026-10-04, on a block set to "Very top, above the title":
 * "very top... not saving also not showing live".
 *
 * ---- What was wrong -----------------------------------------------------
 *
 * MenuBlockSlot grew a THIRD slot -- `top`, before the page's title -- when
 * the two it had turned out to mean the same place to a creator. The style
 * sanitizer's allow-list was not grown with it: it accepted `above`,
 * `below` and `section:<id>` and dropped anything else on the floor.
 *
 * So the editor sent `top`, the sanitizer discarded the key, the controller
 * saw no `_menu_slot` to check, the block kept whatever slot it had, and
 * the endpoint answered `success: true`. The dropdown snapped back on the
 * next load and the page never changed -- which is exactly the pair of
 * symptoms reported, and the reason the second half of this file is about
 * the response telling the truth rather than about slots.
 */
class ABlockCanSitAboveTheMenuTitleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->user);
    }

    /** @return array{0: Link, 1: RestaurantMenu, 2: RestaurantMenuCategory} */
    private function menu(): array
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'),
            'title' => 'Priyumm Tiffins', 'is_active' => true,
        ]);
        $link->biolinkBlocks()->delete();

        $menu = RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'order', 'currency' => 'INR', 'accent_color' => '#e0457b',
            'settings' => [],
        ]);
        $cat = RestaurantMenuCategory::create([
            'menu_id' => $menu->id, 'name' => 'Starters', 'sort_order' => 0, 'is_active' => true,
        ]);
        RestaurantMenuItem::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Chilli Paneer',
            'price' => 90, 'sort_order' => 0, 'is_active' => true,
        ]);

        return [$link, $menu, $cat];
    }

    private function block(Link $link): BiolinkBlock
    {
        return BiolinkBlock::create([
            'link_id' => $link->id,
            'type' => 'heading',
            'settings' => ['text' => 'Lunch is served 12 to 3'],
            'is_active' => true,
            'order' => 0,
        ]);
    }

    private function setSlot(Link $link, BiolinkBlock $block, string $slot)
    {
        return $this->actingAs($this->user)->putJson(
            '/user/links/'.$link->id.'/blocks/'.$block->id,
            ['style' => ['_menu_slot' => $slot]]
        );
    }

    // ===== 1. The slot that was being dropped =====

    /** The sanitizer kept two of the three slots and silently ate the third. */
    public function test_the_style_sanitizer_keeps_every_slot_the_picker_offers(): void
    {
        foreach ([MenuBlockSlot::TOP, MenuBlockSlot::ABOVE, MenuBlockSlot::BELOW, 'section:7'] as $slot) {
            $kept = BlockStyleSanitizer::sanitize(['_menu_slot' => $slot]);

            $this->assertSame(
                $slot,
                $kept['_menu_slot'] ?? null,
                'The picker offers '.$slot.' and the sanitizer dropped it.'
            );
        }
    }

    /**
     * Every value the picker can produce survives a save. Written against
     * the picker's own list rather than against three hard-coded strings,
     * so a fourth slot cannot be added to the menu without this failing.
     */
    public function test_every_position_the_picker_offers_actually_saves(): void
    {
        [$link, $menu, $cat] = $this->menu();
        $block = $this->block($link);

        foreach (MenuBlockSlot::options(collect([$cat])) as $option) {
            $this->setSlot($link, $block, $option['value'])->assertOk();

            $this->assertSame(
                $option['value'],
                MenuBlockSlot::of($block->fresh()->settings),
                '"'.$option['label'].'" did not save.'
            );
        }
    }

    /** And the one he reported, on its own. */
    public function test_a_block_can_be_pinned_above_the_page_title(): void
    {
        [$link] = $this->menu();
        $block = $this->block($link);

        $this->setSlot($link, $block, MenuBlockSlot::TOP)->assertOk();

        $this->assertSame(MenuBlockSlot::TOP, MenuBlockSlot::of($block->fresh()->settings));
    }

    /** It is not saving if it does not come out above the title on the page. */
    public function test_the_page_renders_that_block_above_the_title(): void
    {
        [$link] = $this->menu();
        $block = $this->block($link);
        $this->setSlot($link, $block, MenuBlockSlot::TOP)->assertOk();

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        $atBlock = strpos($html, 'Lunch is served 12 to 3');
        // The hero, not the <title> tag -- which is the restaurant's name
        // too, sits in <head>, and made the first version of this test pass
        // for a reason that had nothing to do with where the block was.
        $atTitle = strpos($html, '<div class="hero">');
        $this->assertNotFalse($atBlock, 'The block is not on the page at all.');
        $this->assertNotFalse($atTitle, 'The page has no hero to sit above.');
        $this->assertLessThan($atTitle, $atBlock, 'The block rendered below the title, not above it.');
    }

    // ===== 2. The response says what actually happened =====

    /**
     * The save answered `success: true` while having stored nothing, and
     * the editor believed it. A response that carries the slot the block is
     * ACTUALLY in is the thing that makes that impossible: the picker sets
     * itself from the answer rather than from what it hoped.
     */
    public function test_the_save_reports_the_slot_the_block_ended_up_in(): void
    {
        [$link] = $this->menu();
        $block = $this->block($link);

        $res = $this->setSlot($link, $block, MenuBlockSlot::TOP)->assertOk();

        $this->assertSame(MenuBlockSlot::TOP, $res->json('menu_slot'));
    }

    /**
     * A slot naming another menu's section is refused, and the answer says
     * where the block really is instead of claiming the move worked.
     */
    public function test_a_refused_slot_comes_back_as_the_slot_that_was_kept(): void
    {
        [$link] = $this->menu();
        [$otherLink, $otherMenu, $otherCat] = $this->menu();
        $block = $this->block($link);

        $res = $this->setSlot($link, $block, MenuBlockSlot::forSection($otherCat->id))->assertOk();

        $this->assertSame(MenuBlockSlot::DEFAULT, $res->json('menu_slot'));
        $this->assertSame(MenuBlockSlot::DEFAULT, MenuBlockSlot::of($block->fresh()->settings));
    }

    /** Junk is refused the same way rather than stored and rendered. */
    public function test_a_slot_nobody_offers_is_refused(): void
    {
        [$link] = $this->menu();
        $block = $this->block($link);
        $this->setSlot($link, $block, MenuBlockSlot::TOP)->assertOk();

        $res = $this->setSlot($link, $block, 'somewhere-else')->assertOk();

        // The shape check drops it before the link is ever consulted, so the
        // block keeps the slot it had. Either way the answer is honest about
        // which slot that is.
        $this->assertContains($res->json('menu_slot'), [MenuBlockSlot::TOP, MenuBlockSlot::DEFAULT]);
        $this->assertSame($res->json('menu_slot'), MenuBlockSlot::of($block->fresh()->settings));
    }

    /** Saving something else about a block does not move it. */
    public function test_an_unrelated_save_leaves_the_position_alone(): void
    {
        [$link] = $this->menu();
        $block = $this->block($link);
        $this->setSlot($link, $block, MenuBlockSlot::TOP)->assertOk();

        $this->actingAs($this->user)->putJson('/user/links/'.$link->id.'/blocks/'.$block->id, [
            'style' => ['grid_span' => 6],
        ])->assertOk();

        $this->assertSame(MenuBlockSlot::TOP, MenuBlockSlot::of($block->fresh()->settings));
    }

    // ===== 3. The picker reads as a page, top to bottom =====

    /**
     * The labels are the whole control: a creator picks a position by
     * reading them. They are asserted here because "Very top, above the
     * title" next to "Above the menu" is two descriptions of the mechanism
     * rather than two places on a page.
     */
    public function test_the_positions_are_named_as_places_on_the_page(): void
    {
        [$link, $menu, $cat] = $this->menu();

        $labels = array_column(MenuBlockSlot::options(collect([$cat])), 'label');

        $this->assertSame([
            'Above the title',
            'Below the title, before the menu',
            'After “Starters”',
            'Below the menu',
        ], $labels);
    }

    /** And they are in the order they appear going down the page. */
    public function test_the_positions_are_listed_in_page_order(): void
    {
        [$link, $menu, $cat] = $this->menu();
        $second = RestaurantMenuCategory::create([
            'menu_id' => $menu->id, 'name' => 'Mains', 'sort_order' => 1, 'is_active' => true,
        ]);

        $values = array_column(MenuBlockSlot::options(collect([$cat, $second])), 'value');

        $this->assertSame([
            MenuBlockSlot::TOP,
            MenuBlockSlot::ABOVE,
            MenuBlockSlot::forSection($cat->id),
            MenuBlockSlot::forSection($second->id),
            MenuBlockSlot::BELOW,
        ], $values);
    }

    /** A section whose name would break the label still gets a readable one. */
    public function test_a_sections_own_name_is_what_the_label_says(): void
    {
        [$link, $menu] = $this->menu();
        $odd = RestaurantMenuCategory::create([
            'menu_id' => $menu->id, 'name' => 'Chef’s "Specials" & More', 'sort_order' => 5, 'is_active' => true,
        ]);

        $labels = array_column(MenuBlockSlot::options(collect([$odd])), 'label');

        $this->assertContains('After “Chef’s "Specials" & More”', $labels);
    }

    // ===== 4. The control is on the screen =====

    public function test_the_blocks_screen_shows_the_position_picker(): void
    {
        [$link] = $this->menu();
        $this->block($link);

        $html = $this->actingAs($this->user)
            ->get('/user/links/'.$link->id.'/blocks')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-slot-row=', $html);
        $this->assertStringContainsString('Above the title', $html);
        $this->assertStringContainsString('Below the menu', $html);
    }

    /** A Link in Bio has no menu to sit around, so it offers no positions. */
    public function test_a_page_with_no_menu_does_not_offer_positions(): void
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'biolink',
            'alias' => 'bl'.fake()->unique()->numerify('#####'),
            'title' => 'My Page', 'is_active' => true,
        ]);
        $link->biolinkBlocks()->delete();
        $this->block($link);

        $html = $this->actingAs($this->user)
            ->get('/user/links/'.$link->id.'/blocks')
            ->assertOk()
            ->getContent();

        // The picker's CSS ships on every blocks page; its MARKUP is what
        // says the control was drawn.
        $this->assertStringNotContainsString('data-slot-row=', $html);
    }
}
