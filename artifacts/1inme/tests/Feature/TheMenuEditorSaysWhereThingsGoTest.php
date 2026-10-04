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
use App\Modules\User\Support\MenuMoney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sana, 2026-09-28, four things at once.
 *
 * 1. Having set a block to "Top of page": "its not going top of table..
 *    still i see more heading and sub heading". He was right and the LABEL
 *    was wrong. There was one slot above the menu and it rendered after the
 *    hero, so a block claiming the top of the page had the restaurant's name
 *    and description above it. Those are two different places and a creator
 *    may want either, so they are two slots that each say which they are.
 *
 * 2. "select drop looks ugly". It was a full-width native select with a
 *    platform chevron sitting in a strip of 9px icon buttons.
 *
 * 3. "same thing works in for store?" Yes -- both menus share the renderer
 *    partials, and this file checks that rather than asserting it.
 *
 * 4. "currency symbol, can u make it dropdown?" It was a three-character
 *    text box, so the only way to learn whether a currency had a symbol on
 *    file was to type it and watch the sample.
 */
class TheMenuEditorSaysWhereThingsGoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->user);
    }

    private function restaurant(string $desc = 'The best tiffins in town'): array
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'),
            'title' => 'Priyumm Tiffins', 'description' => $desc, 'is_active' => true,
        ]);
        $link->biolinkBlocks()->delete();

        $menu = RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'display', 'currency' => 'INR', 'accent_color' => '#f59e0b',
            'settings' => [],
        ]);
        $cat = RestaurantMenuCategory::create([
            'menu_id' => $menu->id, 'name' => 'Starters', 'sort_order' => 0, 'is_active' => true,
        ]);
        RestaurantMenuItem::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => 'Chilli Paneer', 'price' => 90, 'sort_order' => 0, 'is_active' => true,
        ]);

        return [$link->fresh(), $menu, $cat];
    }

    private function store(): array
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'store_menu',
            'alias' => 'sm'.fake()->unique()->numerify('#####'),
            'title' => 'The Shop', 'description' => 'Mugs and shirts', 'is_active' => true,
        ]);
        $link->biolinkBlocks()->delete();

        $menu = StoreMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'display', 'currency' => 'INR', 'accent_color' => '#f59e0b',
            'settings' => [],
        ]);
        $cat = StoreCategory::create([
            'menu_id' => $menu->id, 'name' => 'Mugs', 'sort_order' => 0, 'is_active' => true,
        ]);
        StoreProduct::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => 'Big Mug', 'price' => 450, 'sort_order' => 0, 'is_active' => true,
        ]);

        return [$link->fresh(), $menu, $cat];
    }

    private function heading(Link $link, string $text, string $slot): void
    {
        BiolinkBlock::create([
            'link_id' => $link->id, 'type' => 'heading',
            'settings' => ['text' => $text, '_style' => ['_menu_slot' => $slot]],
            'sort_order' => 0, 'is_active' => true,
        ]);
    }

    private function page(Link $link): string
    {
        return $this->get('/'.$link->alias)->assertOk()->getContent();
    }

    private function editor(Link $link, string $kind): string
    {
        return $this->actingAs($this->user)
            ->get('/user/links/'.$link->id.'/'.$kind)->assertOk()->getContent();
    }

    private function blocksEditor(Link $link): string
    {
        return $this->actingAs($this->user)
            ->get(route('user.links.blocks.editor', $link))->assertOk()->getContent();
    }

    private function assertOrder(string $html, string $first, string $second, string $why): void
    {
        $a = strpos($html, $first);
        $b = strpos($html, $second);
        $this->assertNotFalse($a, "Missing: {$first}");
        $this->assertNotFalse($b, "Missing: {$second}");
        $this->assertLessThan($b, $a, $why);
    }

    // ===== 1. "Top of page" now means the top of the page =================

    public function test_the_very_top_slot_renders_before_the_title(): void
    {
        [$link] = $this->restaurant();
        $this->heading($link, 'Open until eleven', MenuBlockSlot::TOP);

        $html = $this->page($link->fresh());

        // The complaint, exactly: the restaurant's name was above it.
        // The <h1>, not the <title> in the head.
        $this->assertOrder($html, 'Open until eleven', '<h1>Priyumm Tiffins</h1>',
            'A block at the very top should render before the page title.');
    }

    public function test_above_the_menu_still_sits_under_the_title(): void
    {
        [$link] = $this->restaurant();
        $this->heading($link, 'Today we open at four', MenuBlockSlot::ABOVE);

        $html = $this->page($link->fresh());

        // Unchanged, and now labelled for what it is rather than claiming
        // to be the top.
        $this->assertOrder($html, '<h1>Priyumm Tiffins</h1>', 'Today we open at four',
            'An "above the menu" block should still follow the title.');
        $this->assertOrder($html, 'Today we open at four', 'Chilli Paneer',
            'An "above the menu" block should still precede the menu.');
    }

    public function test_the_two_positions_are_offered_by_their_real_names(): void
    {
        [$link] = $this->restaurant();
        $this->heading($link, 'Anything', MenuBlockSlot::BELOW);

        $html = $this->blocksEditor($link->fresh());

        // Reworded on 2026-10-04: the four labels had been measuring from
        // four different things ("very top" and "bottom of page" from the
        // page, the other two from the menu), so each one now names a place
        // relative to the title or the menu. Sana: "the dropdown and words
        // look unpolished".
        $this->assertStringContainsString('Above the title', $html);
        $this->assertStringContainsString('Below the title, before the menu', $html);
        // The label that was not true.
        $this->assertStringNotContainsString('>Top of page<', $html);
    }

    public function test_the_store_puts_them_in_the_same_two_places(): void
    {
        [$link] = $this->store();
        $this->heading($link, 'Free shipping over 500', MenuBlockSlot::TOP);

        $html = $this->page($link->fresh());

        $this->assertOrder($html, 'Free shipping over 500', '<h1>The Shop</h1>',
            'The store should honour the very-top slot too.');
    }

    // ===== 2. The picker looks like the strip it sits in ==================

    public function test_the_position_picker_is_a_chip_not_a_form_field(): void
    {
        [$link] = $this->restaurant();
        $this->heading($link, 'Anything', MenuBlockSlot::BELOW);

        $html = $this->blocksEditor($link->fresh());

        // Scoped to the wrapper, because `[data-app-layout] select` in
        // app.css is more specific than a bare class and owns appearance,
        // padding-right and the chevron for every select in the admin. An
        // unscoped rule lost to it, which is how this control came to wear
        // the app-wide 16px chevron AND a smaller one of its own -- two
        // arrows side by side, found by looking at the rendered row.
        $this->assertMatchesRegularExpression(
            '/\.menu-slot-field \.menu-slot-select\s*\{[^}]*background-color:/s',
            $html,
            'The picker must set background-COLOR; the shorthand resets the chevron geometry and makes it tile.'
        );
        $this->assertMatchesRegularExpression(
            '/\.menu-slot-field \.menu-slot-select\s*\{[^}]*height:\s*22px/s',
            $html,
            'The picker should be the same height as the Width row beneath it.'
        );
        // It used to be `width: auto`, on the reasoning that it should size
        // to its label. A native select sizes to its LONGEST option, not the
        // selected one, so that gave a fixed wide box that changed size
        // whenever a section was renamed. It fills the strip and truncates.
        $this->assertMatchesRegularExpression(
            '/\.menu-slot-field \.menu-slot-select\s*\{[^}]*width:\s*100%/s',
            $html,
            'The position picker should fill the strip rather than size to its longest option.'
        );
        $this->assertStringNotContainsString('menu-slot-select flex-1', $html,
            'The width belongs in the stylesheet, not in a utility class on the control.');
    }

    // ===== 3. Currency is picked, not typed ==============================

    public function test_both_editors_offer_a_currency_list(): void
    {
        [$rLink] = $this->restaurant();
        [$sLink] = $this->store();

        foreach (['restaurant' => [$rLink, 'restaurant'], 'store' => [$sLink, 'store']] as $kind => $pair) {
            $html = $this->editor($pair[0], $pair[1]);

            // The BINDING, not just the method definition: a select with no
            // handler is a picker that saves nothing.
            $this->assertStringContainsString(
                '@change="onCurrencyPicked($event)"',
                $html,
                "The {$kind} editor's currency select should be wired to the handler."
            );
            $this->assertStringContainsString('<option value="__other">', $html,
                "The {$kind} editor should offer an unlisted currency.");
            // Labelled with what a price will look like, which is the
            // question the picker is being asked.
            $this->assertStringContainsString('INR · Rs.', $html, "The {$kind} editor should show the symbol on the option.");
            $this->assertStringContainsString('USD · $', $html);
            // And an escape for a currency we carry no symbol for.
            $this->assertStringContainsString('Other', $html, "The {$kind} editor should allow an unlisted currency.");
        }
    }

    public function test_a_currency_we_do_not_list_is_not_rewritten(): void
    {
        [$link, $menu] = $this->restaurant();
        // Ghana cedi: formats fine, just prints its code. A picker that
        // dropped it would change this menu's prices without being asked.
        $menu->update(['currency' => 'GHS']);

        $html = $this->editor($link->fresh(), 'restaurant');

        $this->assertStringContainsString('currencyIsOther', $html);
        $this->assertStringContainsString('"currency":"GHS"', $html,
            'A currency outside the list should still reach the editor.');
        $this->assertSame('GHS', $menu->fresh()->currency);
    }

    public function test_the_option_labels_come_from_the_symbols_table(): void
    {
        $options = MenuMoney::options();

        $this->assertSame(count(MenuMoney::SYMBOLS), count($options));

        $byCode = collect($options)->keyBy('code');
        // A real symbol is worth showing...
        $this->assertSame('INR · Rs.', $byCode['INR']['label']);
        // ...and one that is just the code again is not.
        $this->assertSame('EUR', $byCode['EUR']['label']);
    }

    // ===== 4. Both menus, from one definition ============================

    public function test_neither_page_decides_the_slots_for_itself(): void
    {
        foreach (['restaurant-menu', 'store-menu'] as $view) {
            $src = file_get_contents(resource_path('views/common/'.$view.'.blade.php'));

            // Every slot the page draws is named from MenuBlockSlot rather
            // than spelled out, so a fifth position cannot reach one page
            // and not the other.
            $this->assertStringContainsString('MenuBlockSlot::TOP', $src, "common/{$view} should draw the very-top slot.");
            $this->assertStringContainsString('MenuBlockSlot::forSection', $src, "common/{$view} should draw the section slots.");
        }
    }
}
