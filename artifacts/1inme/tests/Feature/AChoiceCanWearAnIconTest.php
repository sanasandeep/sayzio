<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\MenuItemOptionGroup;
use App\Modules\User\Models\MenuOption;
use App\Modules\User\Models\MenuOptionGroup;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuCategory;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\StoreCategory;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreProduct;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\MenuOptionIcon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Icons on choices.
 *
 * Sana, 2026-09-28: "it should represent with icons.. like multiple chilis
 * for spicy.. 1 to 3 / hot and cold / and any other".
 *
 * ---- There is no chilli, and that is the interesting part --------------
 *
 * One was drawn three times and rendered at 15px, which is the size it
 * actually appears at beside a choice name. All three read as a banana: at
 * that size the curve that makes a chilli a chilli is just a fruit
 * outline. A flame carries the same one-two-three meaning and holds its
 * shape, so spice is a flame repeated. The test below that pins the
 * catalogue exists so nobody quietly adds a 'chilli' key back.
 *
 * ---- Why a key and a count ---------------------------------------------
 *
 * The drawing lives in MenuOptionIcon and the row holds a key. An unknown
 * key becomes no icon rather than a gap the owner cannot explain, and the
 * count is clamped, because "draw it forty times" is a row anyone can PUT.
 *
 * ---- And the way in ----------------------------------------------------
 *
 * The last three tests are the ones that matter most. Choices shipped
 * working and reachable only from a card in the settings column; Sana
 * looked on the item and found nothing. That is the eighth time this month
 * a built feature had no screen offering it, so the entry point from the
 * item dialog is asserted on BOTH editors rather than trusted.
 */
class AChoiceCanWearAnIconTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->owner);
    }

    /** @return array{0: Link, 1: RestaurantMenu, 2: RestaurantMenuItem} */
    private function restaurant(): array
    {
        $link = Link::create([
            'user_id' => $this->owner->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'),
            'title' => 'Priyumm Tiffins', 'is_active' => true,
        ]);
        $menu = RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->owner->id,
            'mode' => 'order', 'currency' => 'INR', 'settings' => [],
        ]);
        $cat = RestaurantMenuCategory::create([
            'menu_id' => $menu->id, 'name' => 'Starters', 'sort_order' => 0, 'is_active' => true,
        ]);
        $item = RestaurantMenuItem::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => 'Chilli Paneer', 'price' => 90, 'sort_order' => 0, 'is_active' => true,
        ]);

        return [$link, $menu, $item];
    }

    /** @return array{0: Link, 1: StoreMenu, 2: StoreProduct} */
    private function store(): array
    {
        $link = Link::create([
            'user_id' => $this->owner->id, 'type' => 'store_menu',
            'alias' => 'sm'.fake()->unique()->numerify('#####'),
            'title' => 'The Shop', 'is_active' => true,
        ]);
        $menu = StoreMenu::create([
            'link_id' => $link->id, 'user_id' => $this->owner->id,
            'mode' => 'order', 'currency' => 'INR', 'settings' => [],
        ]);
        $cat = StoreCategory::create([
            'menu_id' => $menu->id, 'name' => 'Mugs', 'sort_order' => 0, 'is_active' => true,
        ]);
        $product = StoreProduct::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => 'Big Mug', 'price' => 450, 'sort_order' => 0, 'is_active' => true,
        ]);

        return [$link, $menu, $product];
    }

    private function spiceOn($menu, $item): MenuOptionGroup
    {
        $group = MenuOptionGroup::create([
            'menu_type' => MenuOptionGroup::typeFor($menu), 'menu_id' => $menu->id,
            'name' => 'Spice level', 'hint' => 'How hot would you like it?',
            'is_required' => true, 'min_select' => 1, 'max_select' => 1,
            'sort_order' => 0, 'is_active' => true,
        ]);
        foreach ([['Mild', 1], ['Medium', 2], ['Hot', 3]] as $i => [$name, $times]) {
            MenuOption::create([
                'group_id' => $group->id, 'name' => $name, 'price_delta' => 0,
                'icon' => 'flame', 'icon_repeat' => $times,
                'sort_order' => $i, 'is_active' => true,
            ]);
        }
        MenuItemOptionGroup::create([
            'group_id' => $group->id,
            'owner_type' => MenuItemOptionGroup::typeFor($item),
            'owner_id' => $item->id, 'sort_order' => 0,
        ]);

        return $group;
    }

    private function panel(): string
    {
        return file_get_contents(resource_path('views/user/links/partials/menu-choices-panel.blade.php'));
    }

    // ===== The catalogue =================================================

    public function test_every_icon_in_the_catalogue_can_actually_be_drawn(): void
    {
        $catalogue = MenuOptionIcon::catalogue();

        $this->assertNotEmpty($catalogue);
        foreach ($catalogue as $key => $icon) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9-]{0,22}$/', $key,
                "'{$key}' is not a key that fits the 24-character column.");
            foreach (['label', 'hint', 'path'] as $field) {
                $this->assertArrayHasKey($field, $icon, "'{$key}' has no {$field}.");
                $this->assertNotSame('', trim((string) $icon[$field]));
            }
            // Path data, not a URL or a filename: these are drawn inline.
            $this->assertMatchesRegularExpression('/^M[\d.\s]/', $icon['path'],
                "'{$key}' does not start with a move, so it is not path data.");
            $this->assertStringNotContainsString('"', $icon['path'],
                "'{$key}' would break out of the attribute it is written into.");
            $this->assertStringNotContainsString('<', $icon['path']);
        }
    }

    public function test_heat_is_a_flame_because_the_chilli_did_not_read(): void
    {
        // Three chillies were drawn and rendered at the size a customer
        // sees them; all three read as a banana. If one comes back, it
        // should come back after somebody has looked at it at 15px.
        $keys = MenuOptionIcon::keys();

        $this->assertContains('flame', $keys, 'There is no way to show heat.');
        $this->assertContains('snowflake', $keys, 'There is no way to show "served cold".');
        $this->assertNotContains('chilli', $keys);
        $this->assertNotContains('chili', $keys);
        $this->assertNotContains('pepper', $keys);
    }

    public function test_an_icon_nobody_can_draw_becomes_no_icon(): void
    {
        $this->assertSame('flame', MenuOptionIcon::sanitize('flame'));
        $this->assertSame('flame', MenuOptionIcon::sanitize('  flame  '));
        $this->assertNull(MenuOptionIcon::sanitize('chilli'));
        $this->assertNull(MenuOptionIcon::sanitize(''));
        $this->assertNull(MenuOptionIcon::sanitize(null));
        $this->assertNull(MenuOptionIcon::sanitize('<svg onload=alert(1)>'));
    }

    public function test_the_repeat_count_is_clamped_and_means_nothing_without_an_icon(): void
    {
        $this->assertSame(1, MenuOptionIcon::repeat('flame', 1));
        $this->assertSame(3, MenuOptionIcon::repeat('flame', 3));
        $this->assertSame(MenuOptionIcon::MAX_REPEAT, MenuOptionIcon::repeat('flame', 40));
        $this->assertSame(1, MenuOptionIcon::repeat('flame', 0));
        $this->assertSame(1, MenuOptionIcon::repeat('flame', -2));
        // No icon: the column never carries a count for nothing.
        $this->assertSame(1, MenuOptionIcon::repeat(null, 3));
        $this->assertSame(1, MenuOptionIcon::repeat('chilli', 3));
    }

    // ===== Saving one ====================================================

    private function endpoint(Link $link, string $kind, MenuOptionGroup $group): string
    {
        return '/user/links/'.$link->id.'/'.$kind.'/option-groups/'.$group->id.'/options';
    }

    public function test_the_owner_can_put_an_icon_on_a_choice(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $group = $this->spiceOn($menu, $item);
        $option = $group->options()->first();

        $this->actingAs($this->owner)
            ->putJson($this->endpoint($link, 'restaurant', $group).'/'.$option->id, [
                'name' => 'Mild', 'price_delta' => 0,
                'icon' => 'snowflake', 'icon_repeat' => 2,
            ])->assertOk();

        $option->refresh();
        $this->assertSame('snowflake', $option->icon);
        $this->assertSame(2, $option->icon_repeat);
    }

    public function test_an_icon_that_does_not_exist_is_dropped_rather_than_refused(): void
    {
        // The picker cannot produce one, so a request carrying one is not a
        // person to explain things to -- and a 422 here would block the
        // name and price edit that came with it.
        [$link, $menu, $item] = $this->restaurant();
        $group = $this->spiceOn($menu, $item);
        $option = $group->options()->first();

        $this->actingAs($this->owner)
            ->putJson($this->endpoint($link, 'restaurant', $group).'/'.$option->id, [
                'name' => 'Renamed', 'price_delta' => 0,
                'icon' => 'chilli', 'icon_repeat' => 3,
            ])->assertOk();

        $option->refresh();
        $this->assertSame('Renamed', $option->name);
        $this->assertNull($option->icon);
        $this->assertSame(1, $option->icon_repeat, 'A count survived its icon.');
    }

    public function test_a_wild_repeat_count_is_refused(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $group = $this->spiceOn($menu, $item);
        $option = $group->options()->first();

        $this->actingAs($this->owner)
            ->putJson($this->endpoint($link, 'restaurant', $group).'/'.$option->id, [
                'name' => 'Mild', 'icon' => 'flame', 'icon_repeat' => 99,
            ])->assertStatus(422);
    }

    public function test_a_new_choice_starts_with_no_icon(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $group = $this->spiceOn($menu, $item);

        $body = $this->actingAs($this->owner)
            ->postJson($this->endpoint($link, 'restaurant', $group), ['name' => 'Extra hot'])
            ->assertCreated()->json('data.option');

        $this->assertNull($body['icon']);
        $this->assertSame(1, (int) $body['icon_repeat']);
    }

    public function test_the_editor_is_told_what_each_choice_wears(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $this->spiceOn($menu, $item);

        $groups = $this->actingAs($this->owner)
            ->getJson('/user/links/'.$link->id.'/restaurant/option-groups')
            ->assertOk()->json('data.groups');

        $options = collect($groups[0]['options'])->keyBy('name');
        $this->assertSame('flame', $options['Mild']['icon']);
        $this->assertSame(1, $options['Mild']['icon_repeat']);
        $this->assertSame(3, $options['Hot']['icon_repeat']);
    }

    // ===== The customer sees it ==========================================

    public function test_both_pages_carry_the_icon_with_the_choice(): void
    {
        foreach ([[$this->restaurant(), 'restaurant'], [$this->store(), 'store']] as [$made, $kind]) {
            [$link, $menu, $item] = $made;
            $this->spiceOn($menu, $item);

            $html = $this->get('/'.$link->alias)->assertOk()->getContent();

            $this->assertStringContainsString('"icon":"flame"', $html,
                "The {$kind} page sends the choices with the icon stripped out.");
            $this->assertStringContainsString('"icon_repeat":3', $html,
                "The {$kind} page does not say how many times to draw it.");
        }
    }

    public function test_the_page_carries_the_drawings_it_needs(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $this->spiceOn($menu, $item);

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        // The catalogue reaches the page from MenuOptionIcon rather than
        // being written out again in the partial, so the shape the owner
        // picked is the shape the customer gets.
        $this->assertStringContainsString('var ICONS =', $html);
        $flame = MenuOptionIcon::paths()['flame'];
        $this->assertStringContainsString(substr($flame, 0, 20), $html,
            'The page knows the icon name but has nothing to draw.');
    }

    public function test_the_chooser_draws_it_beside_the_choice(): void
    {
        $shared = file_get_contents(resource_path('views/common/partials/menu-chooser.blade.php'));

        $this->assertStringContainsString('function iconFor(o)', $shared);
        $this->assertStringContainsString('var ico = iconFor(o);', $shared,
            'The chooser has the drawing but never puts it on the row.');
        // Built as real SVG nodes rather than an HTML string, and clamped
        // here too: the page is handed a JSON payload, not a promise.
        $this->assertStringContainsString("createElementNS('http://www.w3.org/2000/svg', 'svg')", $shared);
        $this->assertStringContainsString('Math.min(MAX_REPEAT, o.icon_repeat || 1)', $shared);
        // Decorative: the name beside it is what the kitchen reads.
        $this->assertStringContainsString("wrap.setAttribute('aria-hidden', 'true')", $shared);
    }

    // ===== The owner can set it ==========================================

    public function test_the_panel_offers_the_picker(): void
    {
        $panel = $this->panel();

        $this->assertStringContainsString('iconMarkup(', $panel, 'Nothing draws the icon in the editor.');
        $this->assertStringContainsString('setIcon(o,', $panel);
        $this->assertStringContainsString('setRepeat(o,', $panel);
        $this->assertStringContainsString('mc-icopick', $panel);
        // Every catalogue icon, not a hand-written subset that drifts.
        $this->assertStringContainsString('x-for="ic in icons"', $panel);
    }

    public function test_the_picker_is_on_both_editors_with_the_whole_catalogue(): void
    {
        [$restaurant, , ] = $this->restaurant();
        [$store, , ] = $this->store();

        foreach ([[$restaurant, 'restaurant'], [$store, 'store']] as [$link, $kind]) {
            $html = $this->actingAs($this->owner)
                ->get('/user/links/'.$link->id.'/'.$kind)->assertOk()->getContent();

            foreach (MenuOptionIcon::keys() as $key) {
                // `@js` reaches the attribute as JSON.parse('...') with the
                // quotes written \u0022, so match any spelling of a quote
                // rather than pinning the one this Laravel version emits.
                $q = '(?:"|&quot;|\\\\u0022)';
                $this->assertMatchesRegularExpression(
                    '/'.$q.'key'.$q.'\s*:\s*'.$q.preg_quote($key, '/').$q.'/',
                    $html,
                    "The {$kind} editor's picker is missing '{$key}'."
                );
            }
        }
    }

    public function test_the_owner_who_clears_an_icon_clears_its_count(): void
    {
        $panel = $this->panel();

        $this->assertStringContainsString("if (!key) { o.icon_repeat = 1; }", $panel);
        // And the save actually carries both, which is the half that was
        // easy to forget: the picker would look right and change nothing.
        $this->assertStringContainsString('icon: o.icon || null,', $panel);
        $this->assertStringContainsString('icon_repeat: Math.max(1, Math.min(this.maxRepeat', $panel);
    }

    // ===== The way in from the item dialog ===============================

    public function test_the_item_dialog_offers_a_way_to_the_choices(): void
    {
        // The eighth built-but-unreachable feature this month, and the one
        // Sana went looking for by hand. Asserted on the rendered page, on
        // both editors, because "it is in the code" is exactly what was
        // true of the other seven.
        [$restaurant, , ] = $this->restaurant();
        [$store, , ] = $this->store();

        foreach ([[$restaurant, 'restaurant', 'itemModal'], [$store, 'store', 'productModal']] as [$link, $kind, $modal]) {
            $html = $this->actingAs($this->owner)
                ->get('/user/links/'.$link->id.'/'.$kind)->assertOk()->getContent();

            $this->assertStringContainsString('menu-choices-open', $html,
                "The {$kind} item dialog has no way through to choices.");
            $this->assertStringContainsString($modal.'.id })', $html,
                "The {$kind} dialog hands over without saying which item.");
            $this->assertStringContainsString('Spice level, size, toppings', $html,
                "The {$kind} dialog's button does not say what it is for.");
        }
    }

    public function test_the_dialog_reads_the_panel_rather_than_reaching_into_it(): void
    {
        $panel = $this->panel();

        // Two separate Alpine components in two separate cards. The panel
        // publishes; the dialog reads. Neither knows the other's shape.
        $this->assertStringContainsString('window.menuChoicesOn = function', $panel);
        $this->assertStringContainsString('publishIndex()', $panel);
        $this->assertStringContainsString('@menu-choices-open.window="openFor(', $panel);

        foreach (['restaurant', 'store'] as $editor) {
            $src = file_get_contents(resource_path('views/user/links/'.$editor.'/editor.blade.php'));
            $this->assertStringNotContainsString('menuChoiceIndex[', $src,
                "The {$editor} dialog is reading the panel's internals.");
        }
    }

    public function test_the_way_in_lands_on_the_group_the_item_already_has(): void
    {
        $panel = $this->panel();

        // Arriving on a blank "New choices" form when the dish already has
        // a spice level is how somebody ends up with two of them.
        $this->assertStringContainsString('const existing = this.groups.find(', $panel);
        $this->assertStringContainsString('this.editGroup(existing); return;', $panel);
        // And otherwise the item is already ticked, so the next step is not
        // a scroll through a list of forty.
        $this->assertStringContainsString('this.draft.item_ids = [id];', $panel);
    }
}
