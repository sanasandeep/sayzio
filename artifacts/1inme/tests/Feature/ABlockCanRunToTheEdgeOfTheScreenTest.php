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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sana, 2026-10-04: "block: i want option to make ith full width... is it
 * possible?".
 *
 * The Block Width row's "Full" is 12 of 12 COLUMNS -- the full width of the
 * content column. The page's max-width still applies, and so does the side
 * margin every block carries, so on a desktop "Full" is a band in the
 * middle of the screen. Edge to edge is a separate thing and now has its
 * own setting.
 *
 * What these tests hold on to:
 *   - the flag survives the sanitizer, which drops any key missing from
 *     STYLE_DEFAULTS (the exact way the block Position dropdown went
 *     nowhere for months while its save still answered "success");
 *   - it clears on the empty value, like every other style key;
 *   - the public page emits the marker class AND the full span, because the
 *     wrap writes grid-column inline and an inline declaration beats the
 *     stylesheet;
 *   - the stylesheet on a biolink page and on both menu pages carries the
 *     rule, so the class means something wherever a block can be placed;
 *   - the editor renders the control for a top-level block and not for one
 *     inside a card container, which cannot honour it.
 */
class ABlockCanRunToTheEdgeOfTheScreenTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $user = User::create([
            'name'     => 'Owner '.Str::random(4),
            'email'    => 'own'.Str::random(8).'@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);

        $ws = app(WorkspaceContext::class)->resolve($user);
        if ($ws !== null) {
            app()->instance('current_workspace', $ws);
        }
        app()->instance('workspace_owner', $user);

        return $user;
    }

    private function makeLink(User $owner, string $type = 'biolink'): Link
    {
        return Link::create([
            'user_id'   => $owner->id,
            'type'      => $type,
            'alias'     => Link::generateAlias(),
            'title'     => 'My Page',
            'is_active' => true,
        ]);
    }

    private function makeBlock(User $owner, Link $link): BiolinkBlock
    {
        $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("/user/links/{$link->id}/blocks", ['type' => 'paragraph'])
            ->assertOk();

        return BiolinkBlock::where('link_id', $link->id)->latest('id')->firstOrFail();
    }

    private function updateStyle(User $owner, Link $link, BiolinkBlock $block, array $style): void
    {
        $resp = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->put("/user/links/{$link->id}/blocks/{$block->id}", [
                'settings' => $block->fresh()->settings,
                'style'    => $style,
            ]);

        $resp->assertOk();
        $this->assertTrue((bool) $resp->json('success'));
    }

    /** The opening wrap tag for one block, out of public HTML. */
    private function extractWrap(string $html, int $blockId): string
    {
        $pos = strpos($html, 'data-block-id="'.$blockId.'"');
        $this->assertNotFalse($pos, 'block wrap not found in public HTML');
        $start = strrpos(substr($html, 0, $pos), '<div');
        $end = strpos($html, '>', $pos);

        return substr($html, $start, $end - $start + 1);
    }

    public function test_the_flag_is_stored_and_not_dropped_by_the_sanitizer(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeLink($owner);
        $block = $this->makeBlock($owner, $link);

        $this->updateStyle($owner, $link, $block, ['_full_bleed' => 1]);

        $style = $block->fresh()->settings['_style'] ?? [];
        $this->assertArrayHasKey(
            '_full_bleed',
            $style,
            'the save answered success but the sanitizer threw the key away'
        );
        $this->assertTrue((bool) $style['_full_bleed']);
    }

    public function test_the_empty_value_clears_it(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeLink($owner);
        $block = $this->makeBlock($owner, $link);

        $this->updateStyle($owner, $link, $block, ['_full_bleed' => 1]);
        $this->assertTrue((bool) ($block->fresh()->settings['_style']['_full_bleed'] ?? false));

        $this->updateStyle($owner, $link, $block, ['_full_bleed' => '']);
        $this->assertArrayNotHasKey('_full_bleed', $block->fresh()->settings['_style'] ?? []);
    }

    public function test_a_junk_value_cannot_reach_a_page(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeLink($owner);
        $block = $this->makeBlock($owner, $link);

        // Bounded 0..1 like stack_mobile, so nothing but a flag is stored.
        $this->updateStyle($owner, $link, $block, ['_full_bleed' => 99]);
        $this->assertSame(1, (int) ($block->fresh()->settings['_style']['_full_bleed'] ?? 0));

        // Something that is not a number at all is dropped outright, which
        // is how every other bounded key behaves here -- the key ends up
        // absent rather than holding a string a stylesheet would choke on.
        $this->updateStyle($owner, $link, $block, ['_full_bleed' => 'yes please']);
        $this->assertArrayNotHasKey('_full_bleed', $block->fresh()->settings['_style'] ?? []);
    }

    public function test_the_public_wrap_carries_the_marker_and_the_whole_row(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeLink($owner);
        $block = $this->makeBlock($owner, $link);

        // Off: no marker, and the block keeps the width it was given.
        $this->updateStyle($owner, $link, $block, ['grid_span' => 6, '_full_bleed' => '']);
        $wrap = $this->extractWrap($this->get('/'.$link->alias)->assertOk()->getContent(), $block->id);
        $this->assertStringNotContainsString('full-bleed', $wrap);
        $this->assertStringContainsString('grid-column: span 6', $wrap);

        // On: marker class, and the span is forced to the full twelve --
        // half a row cannot reach both edges of the screen, and the inline
        // declaration is the one the browser obeys.
        $this->updateStyle($owner, $link, $block, ['grid_span' => 6, '_full_bleed' => 1]);
        $wrap = $this->extractWrap($this->get('/'.$link->alias)->assertOk()->getContent(), $block->id);
        $this->assertStringContainsString('full-bleed', $wrap);
        $this->assertStringContainsString('grid-column: span 12', $wrap);
        $this->assertStringNotContainsString('span 6', $wrap);
    }

    public function test_a_desktop_width_override_is_dropped_while_full_bleed(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeLink($owner);
        $block = $this->makeBlock($owner, $link);

        // A stored desktop override would re-place the block at 768px and
        // pull it back inside the column on exactly the screens where edge
        // to edge is the point.
        $this->updateStyle($owner, $link, $block, [
            'grid_span'    => 6,
            'grid_span_md' => 6,
            '_full_bleed'  => 1,
        ]);

        $wrap = $this->extractWrap($this->get('/'.$link->alias)->assertOk()->getContent(), $block->id);
        $this->assertStringContainsString('full-bleed', $wrap);
        $this->assertStringNotContainsString('md-span', $wrap);
        $this->assertStringNotContainsString('--md-span', $wrap);
    }

    public function test_a_stored_side_margin_does_not_fight_the_negative_margins(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeLink($owner);
        $block = $this->makeBlock($owner, $link);

        // An inline margin-left would beat the stylesheet's negative one and
        // the block would sit in the column looking like nothing happened.
        $this->updateStyle($owner, $link, $block, [
            'margin_left'  => 40,
            'margin_right' => 40,
            '_full_bleed'  => 1,
        ]);

        $wrap = $this->extractWrap($this->get('/'.$link->alias)->assertOk()->getContent(), $block->id);
        $this->assertStringNotContainsString('margin-left:40px', $wrap);
        $this->assertStringNotContainsString('margin-right:40px', $wrap);

        // Still honoured when the block is back inside the column.
        $this->updateStyle($owner, $link, $block, [
            'margin_left'  => 40,
            'margin_right' => 40,
            '_full_bleed'  => '',
        ]);
        $wrap = $this->extractWrap($this->get('/'.$link->alias)->assertOk()->getContent(), $block->id);
        $this->assertStringContainsString('margin-left:40px', $wrap);
    }

    public function test_the_biolink_stylesheet_defines_the_rule(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeLink($owner);
        $block = $this->makeBlock($owner, $link);
        $this->updateStyle($owner, $link, $block, ['_full_bleed' => 1]);

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        // The class has to mean something, not just be present.
        $this->assertMatchesRegularExpression(
            '/\.biolink-block-wrap\.full-bleed\s*\{[^}]*margin-left:\s*calc\(50%\s*-\s*50vw\)/',
            $html
        );
        // 100vw counts the scrollbar, so the overflow has to be cropped --
        // and with `clip`, not `hidden`, which would make the element a
        // scroll container and break the sticky menu bar.
        $this->assertMatchesRegularExpression('/html,\s*body\s*\{\s*overflow-x:\s*clip/', $html);
    }

    /**
     * A restaurant-menu link with no menu row attached falls back to the
     * biolink template (RedirectController: `restaurantMenu()->exists()`),
     * so a test that only sets the link type is testing the biolink page
     * again under a different name. These two build the real thing.
     */
    private function makeRestaurantMenuPage(User $owner): Link
    {
        $link = $this->makeLink($owner, 'restaurant_menu');
        $menu = RestaurantMenu::create([
            'link_id'  => $link->id,
            'user_id'  => $owner->id,
            'mode'     => 'display',
            'currency' => 'INR',
            'settings' => [],
        ]);
        $cat = RestaurantMenuCategory::create([
            'menu_id' => $menu->id, 'name' => 'Tiffins', 'sort_order' => 0, 'is_active' => true,
        ]);
        RestaurantMenuItem::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Masala Dosa',
            'price' => 120, 'sort_order' => 0, 'is_active' => true,
        ]);

        return $link->fresh();
    }

    private function makeStoreMenuPage(User $owner): Link
    {
        $link = $this->makeLink($owner, 'store_menu');
        $menu = StoreMenu::create([
            'link_id'  => $link->id,
            'user_id'  => $owner->id,
            'mode'     => 'display',
            'currency' => 'INR',
            'settings' => [],
        ]);
        $cat = StoreCategory::create([
            'menu_id' => $menu->id, 'name' => 'Mugs', 'sort_order' => 0, 'is_active' => true,
        ]);
        StoreProduct::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Big Mug',
            'price' => 450, 'sort_order' => 0, 'is_active' => true,
        ]);

        return $link->fresh();
    }

    /** The rule, and proof we are looking at the menu template. */
    private function assertMenuPageCarriesTheRule(string $html, string $marker): void
    {
        $this->assertStringContainsString(
            $marker,
            $html,
            'this is not the menu template -- a menu link with no menu row renders the biolink page'
        );
        // A menu page is a plain column, not a twelve-column grid, so it
        // needs its own copy of the rule: the biolink stylesheet is not
        // loaded here at all.
        $this->assertMatchesRegularExpression(
            '/\.biolink-block-wrap\.full-bleed\s*\{[^}]*margin-left:\s*calc\(50%\s*-\s*50vw\)/',
            $html
        );
        $this->assertMatchesRegularExpression('/html,\s*body\s*\{\s*overflow-x:\s*clip/', $html);
    }

    public function test_a_restaurant_menu_page_defines_the_rule_too(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeRestaurantMenuPage($owner);

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();
        $this->assertMenuPageCarriesTheRule($html, 'Masala Dosa');
    }

    public function test_a_store_menu_page_defines_the_rule_too(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeStoreMenuPage($owner);

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();
        $this->assertMenuPageCarriesTheRule($html, 'Big Mug');
    }

    public function test_a_block_on_a_menu_page_gets_the_marker_class(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeRestaurantMenuPage($owner);
        $block = $this->makeBlock($owner, $link);

        $this->updateStyle($owner, $link, $block, ['_full_bleed' => 1]);

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();
        $this->assertStringContainsString('Masala Dosa', $html);
        $this->assertStringContainsString('full-bleed', $this->extractWrap($html, $block->id));
    }

    public function test_the_editor_offers_the_control_on_a_top_level_block(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeLink($owner);
        $block = $this->makeBlock($owner, $link);

        $html = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get("/user/links/{$link->id}/blocks/{$block->id}/edit-form")
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('name="style[_full_bleed]"', $html);
        $this->assertStringContainsString('Edge to Edge', $html);
        // Both choices, and "In column" carries the empty value that clears
        // the key rather than storing a 0 on every block anyone opens.
        $this->assertStringContainsString('In column', $html);
        $this->assertStringContainsString('Full bleed', $html);
        $this->assertMatchesRegularExpression(
            '/name="style\[_full_bleed\]"\s+value=""/',
            $html
        );
    }

    public function test_the_editor_hides_the_control_on_a_card_child(): void
    {
        $owner = $this->makeOwner();
        $link  = $this->makeLink($owner);

        $card = $this->makeBlock($owner, $link);
        $card->update(['type' => 'card']);

        $child = $this->makeBlock($owner, $link);
        $child->update(['parent_id' => $card->id]);

        $this->assertNotNull($child->fresh()->parent_id);

        // The panel comes back as HTML inside a JSON envelope.
        $panel = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get("/user/links/{$link->id}/blocks/{$child->id}/edit-form")
            ->assertOk()
            ->json('html');

        $this->assertStringNotContainsString('name="style[_full_bleed]"', $panel);
        // The ordinary width control is still there -- a child has a width.
        $this->assertStringContainsString('name="style[grid_span]"', $panel);

        // And the card itself, being top level, still gets the choice.
        $cardPanel = $this->actingAs($owner)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get("/user/links/{$link->id}/blocks/{$card->id}/edit-form")
            ->assertOk()
            ->json('html');
        $this->assertStringContainsString('name="style[_full_bleed]"', $cardPanel);
    }
}
