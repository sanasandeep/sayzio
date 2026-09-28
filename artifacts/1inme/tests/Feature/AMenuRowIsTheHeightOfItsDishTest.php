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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sana, 2026-09-27: "Menu items looks too big... fix the look" -- and, the
 * same day, "Same issue with restaurent and store also".
 *
 * The row's five actions were stacked in a column. So the row could never be
 * shorter than five buttons, whatever was in it: a dish with a name, one line
 * of description and a price -- about 55px of text -- occupied roughly 200px,
 * and a section of eight dishes filled the screen with mostly nothing. The
 * actions are icon buttons on one line now and the text sets the height.
 *
 * ---- And the price that argued with the price format -------------------
 *
 * Reading that row turned up the other half. It printed
 *
 *     currency + ' ' + (+row.price).toFixed(2)
 *
 * on its own, which is the exact format MenuMoney replaced four days ago and
 * ignores all three of the controls sitting a few hundred pixels to its
 * right. Pick "Rs." and "after the number" and the public page reads
 * "120 Rs." while every row on the screen you picked it on still reads
 * "INR 120.00". The sample under those controls was right, because it had its
 * own copy of the formatting. That is now one method both of them call.
 */
class AMenuRowIsTheHeightOfItsDishTest extends TestCase
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
        $menu = RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'display', 'currency' => 'INR', 'accent_color' => '#e0457b',
            'settings' => $menuSettings,
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

    private function store(array $menuSettings = []): Link
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'store_menu',
            'alias' => 'sm'.fake()->unique()->numerify('#####'),
            'title' => 'The Shop', 'is_active' => true,
        ]);
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

        return $link->fresh();
    }

    private function editor(Link $link, string $kind): string
    {
        return $this->actingAs($this->user)
            ->get('/user/links/'.$link->id.'/'.$kind)
            ->assertOk()->getContent();
    }

    /** Both editors, each with a row in it, for the checks that apply to both. */
    private function bothEditors(): array
    {
        return [
            'restaurant' => $this->editor($this->restaurant(), 'restaurant'),
            'store' => $this->editor($this->store(), 'store'),
        ];
    }

    // ===== 1. The actions sit on a line, not in a column ===================

    public function test_the_row_actions_are_not_stacked_in_a_column(): void
    {
        foreach ($this->bothEditors() as $kind => $html) {
            $this->assertStringContainsString(
                'class="rm-acts"',
                $html,
                "The {$kind} editor should lay a row's actions out on one line."
            );
            // The exact thing that made a row five buttons tall.
            $this->assertStringNotContainsString(
                'flex flex-col gap-1',
                $html,
                "The {$kind} editor still stacks the row actions vertically."
            );
        }
    }

    public function test_all_five_row_actions_survive_the_relayout(): void
    {
        foreach ($this->bothEditors() as $kind => $html) {
            foreach ([
                'moveRow(g.cat.id,row,-1)',
                'moveRow(g.cat.id,row,1)',
                'toggleRow(row)',
                'openRow(g.cat.id, row)',
                'deleteRow(row)',
            ] as $call) {
                $this->assertStringContainsString(
                    $call,
                    $html,
                    "The {$kind} editor lost the row action {$call} in the relayout."
                );
            }
        }
    }

    public function test_the_row_no_longer_sets_a_floor_on_its_own_height(): void
    {
        foreach (['restaurant', 'store'] as $kind) {
            $css = file_get_contents(resource_path('views/user/links/'.$kind.'/editor.blade.php'));

            // A 28px icon button, not a pill. Five of these on one line are
            // shorter than one of them was tall in a stack of five.
            $this->assertMatchesRegularExpression(
                '/\.rm-act\s*\{[^}]*height:\s*28px/',
                $css,
                "The {$kind} editor should size a row action as an icon button."
            );
            // Centred, so the buttons sit beside the dish rather than holding
            // the top of a tall row open.
            $this->assertMatchesRegularExpression(
                '/\.rm-item\s*\{[^}]*align-items:\s*center/',
                $css,
                "The {$kind} editor's row should centre its contents."
            );
        }
    }

    // ===== 2. The price in the list is the price on the page ==============

    public function test_the_row_price_goes_through_the_same_formatter_as_the_sample(): void
    {
        foreach ($this->bothEditors() as $kind => $html) {
            $this->assertStringContainsString(
                'money(row.price)',
                $html,
                "The {$kind} editor's row should format its price the way the page does."
            );
            // The hand-rolled copy that ignored the price-format controls.
            $this->assertStringNotContainsString(
                '(+row.price).toFixed(2)',
                $html,
                "The {$kind} editor still prints a row price in its own format."
            );
        }
    }

    public function test_the_sample_and_the_rows_share_one_definition(): void
    {
        foreach (['restaurant', 'store'] as $kind) {
            $blade = file_get_contents(resource_path('views/user/links/'.$kind.'/editor.blade.php'));

            // One formatter...
            $this->assertStringContainsString('money(amount){', $blade);
            // ...that the sample calls rather than reimplements. A second copy
            // here is how the row and the sample disagreed in the first place.
            $this->assertStringContainsString(
                'get priceSample(){ return this.money(1234.5); }',
                $blade,
                "The {$kind} editor's sample should call the shared formatter."
            );
            $this->assertSame(
                1,
                substr_count($blade, 'price_position === \'after\''),
                "The {$kind} editor should decide price position in exactly one place."
            );
        }
    }

    /**
     * The point of routing the row through money(): a menu that chose a
     * symbol and a trailing position sees that choice in its own list.
     */
    public function test_a_menu_that_chose_a_format_sees_it_in_the_editor_list(): void
    {
        $settings = ['price_display' => 'symbol', 'price_position' => 'after'];

        $restaurant = $this->editor($this->restaurant($settings), 'restaurant');
        $store = $this->editor($this->store($settings), 'store');

        foreach (['restaurant' => $restaurant, 'store' => $store] as $kind => $html) {
            // The editor formats client-side from menu.price_display, so what
            // must be true server-side is that the chosen format reached the
            // screen at all and that the row reads it.
            $this->assertStringContainsString('money(row.price)', $html);
            $this->assertStringContainsString('"price_display":"symbol"', $html, "The {$kind} editor never received the chosen format.");
            $this->assertStringContainsString('"price_position":"after"', $html, "The {$kind} editor never received the chosen position.");
        }
    }

    // ===== 3. One copy of the row, still =================================

    public function test_the_row_markup_is_still_written_once(): void
    {
        foreach (['restaurant', 'store'] as $kind) {
            $blade = file_get_contents(resource_path('views/user/links/'.$kind.'/editor.blade.php'));
            $this->assertStringContainsString(
                'user.links.partials.menu-structure-editor',
                $blade,
                "The {$kind} editor should still draw its rows from the shared partial."
            );
        }

        $partial = file_get_contents(
            resource_path('views/user/links/partials/menu-structure-editor.blade.php')
        );
        $this->assertSame(
            1,
            substr_count($partial, 'class="rm-acts"'),
            'The row action bar should exist once, not once per level.'
        );
    }
}
