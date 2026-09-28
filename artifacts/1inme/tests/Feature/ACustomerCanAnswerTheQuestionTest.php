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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The customer's half of item choices.
 *
 * ---- The cart stopped being keyed by item id -------------------------
 *
 * It was `ITEMS[id].qty`: one number per dish. That cannot hold "one mild
 * and two hot", which is the first thing anybody orders once spice levels
 * exist. The cart is a list of LINES now, and what makes two lines the
 * same line is the item plus its sorted choices.
 *
 * That has a consequence on the menu itself: a dish with choices cannot
 * use the +/- stepper, because plus WHICH one? It keeps its Add button,
 * which opens the chooser again.
 *
 * ---- Both halves enforce the rules -----------------------------------
 *
 * The sheet stops a customer before they tap Add; MenuCartPricer stops the
 * request when it arrives. Without the first, a customer gets a refusal
 * after they thought they were done. Without the second, the page is what
 * decides the bill.
 */
class ACustomerCanAnswerTheQuestionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->user);
    }

    /** @return array{0: Link, 1: RestaurantMenu, 2: RestaurantMenuItem} */
    private function restaurant(): array
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'),
            'title' => 'Priyumm Tiffins', 'is_active' => true,
        ]);
        $menu = RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
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

    private function spiceOn($menu, $item): MenuOptionGroup
    {
        $group = MenuOptionGroup::create([
            'menu_type' => MenuOptionGroup::typeFor($menu), 'menu_id' => $menu->id,
            'name' => 'Spice level', 'hint' => 'How hot would you like it?',
            'is_required' => true, 'min_select' => 1, 'max_select' => 1,
            'sort_order' => 0, 'is_active' => true,
        ]);
        foreach ([['Mild', 0], ['Hot', 0]] as $i => [$name, $delta]) {
            MenuOption::create([
                'group_id' => $group->id, 'name' => $name, 'price_delta' => $delta,
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

    private function page(Link $link): string
    {
        return $this->get('/'.$link->alias)->assertOk()->getContent();
    }

    private function shared(): string
    {
        return file_get_contents(resource_path('views/common/partials/menu-chooser.blade.php'));
    }

    // ===== The choices reach the page ====================================

    public function test_the_page_carries_the_choices_for_its_items(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $this->spiceOn($menu, $item);

        $html = $this->page($link);

        $this->assertStringContainsString('const CHOICES =', $html);
        $this->assertStringContainsString('Spice level', $html);
        $this->assertStringContainsString('How hot would you like it?', $html);
        $this->assertStringContainsString('Mild', $html);
        $this->assertStringContainsString('id="chzModal"', $html, 'The chooser sheet is not on the page.');
    }

    public function test_a_menu_with_no_choices_still_renders(): void
    {
        [$link, , ] = $this->restaurant();

        $html = $this->page($link);

        // An empty object, not a missing constant -- the page reads it
        // unconditionally.
        $this->assertStringContainsString('const CHOICES =', $html);
        $this->assertStringContainsString('menuChooser.install(CHOICES', $html);
    }

    public function test_the_choices_cost_one_query_not_one_per_item(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $this->spiceOn($menu, $item);
        for ($i = 0; $i < 20; $i++) {
            RestaurantMenuItem::create([
                'menu_id' => $menu->id, 'category_id' => $item->category_id,
                'name' => 'Dish '.$i, 'price' => 50, 'sort_order' => $i + 1, 'is_active' => true,
            ]);
        }

        \DB::enableQueryLog();
        $this->get('/'.$link->alias)->assertOk();
        $forChoices = collect(\DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'menu_item_option_groups'))
            ->count();
        \DB::disableQueryLog();

        $this->assertLessThanOrEqual(2, $forChoices,
            'Twenty-one items must not mean twenty-one choice queries on a page a guest is waiting for.');
    }

    // ===== The cart is lines now =========================================

    public function test_the_cart_is_a_list_of_lines_not_a_quantity_per_item(): void
    {
        foreach (['restaurant-menu', 'store-menu'] as $view) {
            $src = file_get_contents(resource_path('views/common/'.$view.'.blade.php'));

            $this->assertStringContainsString('let LINES = [];', $src,
                "common/{$view} still has no line-based cart.");
            // The catalog no longer carries a quantity, because the cart does.
            $this->assertStringNotContainsString("parseFloat(el.getAttribute('data-price')), qty: 0 }", $src,
                "common/{$view}'s catalog is still doubling as the cart.");
            $this->assertStringContainsString('menuChooser.key(', $src,
                "common/{$view} has no way to tell one variant from another.");
        }
    }

    public function test_a_dish_with_choices_keeps_its_add_button(): void
    {
        foreach (['restaurant-menu', 'store-menu'] as $view) {
            $src = file_get_contents(resource_path('views/common/'.$view.'.blade.php'));

            // Plus WHICH one? The stepper cannot mean anything on an item
            // that is in the cart twice with different choices.
            $this->assertStringContainsString('if (menuChooser.asks(it.id))', $src,
                "common/{$view} would show a +/- stepper it cannot honour.");
            $this->assertStringContainsString("' in order'", $src,
                "common/{$view} does not say how many are already in the order.");
        }
    }

    public function test_the_order_payload_carries_the_choices(): void
    {
        foreach (['restaurant-menu', 'store-menu'] as $view) {
            $src = file_get_contents(resource_path('views/common/'.$view.'.blade.php'));
            $this->assertStringContainsString('options: menuChooser.payload(l.opts)', $src,
                "common/{$view} sends a cart with the choices stripped out of it.");
        }
    }

    public function test_the_store_no_longer_defines_its_cart_twice(): void
    {
        // It had two cartItems() definitions, the second silently winning.
        $src = file_get_contents(resource_path('views/common/store-menu.blade.php'));

        $this->assertSame(1, substr_count($src, 'function cartItems()'));
    }

    // ===== The sheet enforces what the server enforces ===================

    public function test_the_sheet_refuses_what_the_server_would_refuse(): void
    {
        $shared = $this->shared();

        // A required group left unanswered, and a ceiling exceeded: the two
        // things MenuCartPricer throws on.
        $this->assertStringContainsString("'Please choose a '", $shared);
        $this->assertStringContainsString("'Please choose at least '", $shared);
        $this->assertStringContainsString("'Please choose at most '", $shared);
        $this->assertStringContainsString('function unmet()', $shared);
        // And Add is refused while a rule is unmet.
        $this->assertStringContainsString('var problem = unmet();', $shared);
    }

    public function test_the_sheet_describes_a_rule_the_way_everything_else_does(): void
    {
        $shared = $this->shared();

        // Mirrors MenuOptionSelection::ruleLabel, so the item, the editor
        // preview and the server all say the same sentence.
        foreach (["'Choose '", "'choose at least '", "'optional'", "' · each up to '"] as $piece) {
            $this->assertStringContainsString($piece, $shared);
        }
    }

    public function test_a_sold_out_choice_cannot_be_tapped(): void
    {
        $shared = $this->shared();

        $this->assertStringContainsString('if (o.is_sold_out) { return; }', $shared);
        $this->assertStringContainsString("' — sold out'", $shared);
    }

    public function test_one_copy_of_the_chooser_for_both_pages(): void
    {
        foreach (['restaurant-menu', 'store-menu'] as $view) {
            $src = file_get_contents(resource_path('views/common/'.$view.'.blade.php'));
            $this->assertStringContainsString('common.partials.menu-chooser', $src);
            $this->assertStringNotContainsString('window.menuChooser = {', $src,
                "common/{$view} carries its own copy of the chooser.");
        }

        $this->assertSame(1, substr_count($this->shared(), 'window.menuChooser = {'));
    }

    // ===== The store gets it too =========================================

    public function test_the_store_page_carries_its_own_choices(): void
    {
        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'store_menu',
            'alias' => 'sm'.fake()->unique()->numerify('#####'),
            'title' => 'The Shop', 'is_active' => true,
        ]);
        $menu = StoreMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'order', 'currency' => 'INR', 'settings' => [],
        ]);
        $cat = StoreCategory::create([
            'menu_id' => $menu->id, 'name' => 'Mugs', 'sort_order' => 0, 'is_active' => true,
        ]);
        $product = StoreProduct::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => 'Mug', 'price' => 450, 'sort_order' => 0, 'is_active' => true,
        ]);
        $this->spiceOn($menu, $product);

        $html = $this->page($link);

        $this->assertStringContainsString('const CHOICES =', $html);
        $this->assertStringContainsString('Spice level', $html);
        $this->assertStringContainsString('id="chzModal"', $html);
    }

    // ===== The sheet is a pinned overlay =================================

    public function test_the_sheet_says_it_is_pinned(): void
    {
        // Same trap as #175: a body-level overlay that does not carry the
        // marker gets dropped into the flow on any menu with a background.
        [$link, $menu, $item] = $this->restaurant();
        $this->spiceOn($menu, $item);

        $this->assertMatchesRegularExpression(
            '/class="[^"]*\bsz-pinned\b[^"]*"[^>]*id="chzModal"/',
            $this->page($link),
            'The chooser would scroll away with the page on a menu with a background layer.'
        );
    }
}
