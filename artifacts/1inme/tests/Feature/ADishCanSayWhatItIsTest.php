<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\MenuItemMark;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuCategory;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\StoreCategory;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreProduct;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\MenuItemMarks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Marks on a dish: veg, non-veg, egg, seafood, spicy, hot, cold, no garlic.
 *
 * ---- This is not a choice group, and that was the whole correction -----
 *
 * Spice shipped first as a question the customer answers -- "how hot would
 * you like it?" -- and Sana said plainly that it is not one:
 *
 *   "for spicy, serving and all.. its fixed value.. only customers will see
 *    in menu if its too spicy or less... hot or cold... gravy or dry or
 *    semi gravy... no garlic.. no onions"
 *
 * A choice asks and changes the bill. A mark states and changes nothing.
 * Toppings and add-ons stay choices; these became marks.
 *
 * ---- What these tests actually guard -----------------------------------
 *
 * Three things, in order of how badly they would hurt:
 *
 *  1. Sanitizing. What arrives is a JSON array from a browser and what it
 *     produces is drawn on a public page. An unknown key, a grade of
 *     forty, the same mark twice, thirty marks on one dish: each is one
 *     PUT away.
 *  2. A retired mark. Admin switches "Jain" off; every dish saved wearing
 *     it must stop drawing it, without anyone editing those dishes.
 *  3. Reachability, again. Seven features this month were built with no
 *     screen offering them, so the picker is asserted on the rendered page
 *     of both editors and the admin screen on the rendered sidebar.
 */
class ADishCanSayWhatItIsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        MenuItemMarks::forget();
        $this->owner = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->owner);
    }

    protected function tearDown(): void
    {
        MenuItemMarks::forget();
        parent::tearDown();
    }

    /** @return array{0: Link, 1: RestaurantMenu, 2: RestaurantMenuItem} */
    private function restaurant(array $marks = []): array
    {
        $link = Link::create([
            'user_id' => $this->owner->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'),
            'title' => 'Priyumm Tiffins', 'is_active' => true,
        ]);
        $menu = RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->owner->id,
            'mode' => 'display', 'currency' => 'INR', 'settings' => [],
        ]);
        $cat = RestaurantMenuCategory::create([
            'menu_id' => $menu->id, 'name' => 'Mains', 'sort_order' => 0, 'is_active' => true,
        ]);
        $item = RestaurantMenuItem::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => 'Paneer Butter Masala', 'price' => 280,
            'marks' => $marks, 'sort_order' => 0, 'is_active' => true,
        ]);

        return [$link, $menu, $item];
    }

    /** @return array{0: Link, 1: StoreMenu, 2: StoreProduct} */
    private function store(array $marks = []): array
    {
        $link = Link::create([
            'user_id' => $this->owner->id, 'type' => 'store_menu',
            'alias' => 'sm'.fake()->unique()->numerify('#####'),
            'title' => 'The Shop', 'is_active' => true,
        ]);
        $menu = StoreMenu::create([
            'link_id' => $link->id, 'user_id' => $this->owner->id,
            'mode' => 'display', 'currency' => 'INR', 'settings' => [],
        ]);
        $cat = StoreCategory::create([
            'menu_id' => $menu->id, 'name' => 'Jars', 'sort_order' => 0, 'is_active' => true,
        ]);
        $product = StoreProduct::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id,
            'name' => 'Pickle', 'price' => 180,
            'marks' => $marks, 'sort_order' => 0, 'is_active' => true,
        ]);

        return [$link, $menu, $product];
    }

    private function admin(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );

        return Admin::create([
            'name'     => 'Test Admin',
            'email'    => 'admin'.uniqid().'@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    // ===== The vocabulary ships with something usable ====================

    public function test_the_platform_starts_with_a_vocabulary_worth_having(): void
    {
        $keys = MenuItemMarks::vocabulary()->keys()->all();

        // The ones Sana named, by name.
        foreach (['veg', 'nonveg', 'egg', 'seafood', 'spicy', 'served-hot', 'served-cold',
            'gravy', 'semi-gravy', 'dry', 'no-garlic', 'no-onion'] as $key) {
            $this->assertContains($key, $keys, "The starting vocabulary has no '{$key}'.");
        }
    }

    public function test_the_veg_and_non_veg_marks_differ_only_in_colour(): void
    {
        // They are the same square-and-dot drawing. The colour is the whole
        // meaning, which is why colour is a column on the mark rather than
        // something the page decides.
        $vocabulary = MenuItemMarks::vocabulary();
        $veg = $vocabulary->get('veg');
        $nonveg = $vocabulary->get('nonveg');

        $this->assertSame($veg->icon, $nonveg->icon);
        $this->assertNotSame($veg->color, $nonveg->color);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $veg->color);
    }

    public function test_spice_is_the_graded_one(): void
    {
        $spicy = MenuItemMarks::vocabulary()->get('spicy');

        $this->assertTrue($spicy->is_graded, 'Spice cannot be shown 1, 2 or 3.');
        $this->assertSame(3, $spicy->grades());
        $this->assertFalse(MenuItemMarks::vocabulary()->get('veg')->is_graded,
            'A dish cannot be three-times vegetarian.');
    }

    public function test_a_mark_with_no_icon_is_meant_to_be_a_chip(): void
    {
        // "No garlic" has no legible 14px glyph and inventing one means a
        // diner guessing. A null icon is a decision, not a gap.
        $this->assertNull(MenuItemMarks::vocabulary()->get('no-garlic')->icon);
        $this->assertNotNull(MenuItemMarks::vocabulary()->get('veg')->icon);
    }

    // ===== Sanitizing ====================================================

    public function test_a_key_nobody_defined_is_dropped(): void
    {
        $out = MenuItemMarks::sanitize([
            ['key' => 'veg'], ['key' => 'unicorn'], ['key' => ''], ['key' => null], 'no-onion',
        ]);

        $this->assertSame(['veg', 'no-onion'], array_column($out, 'key'));
    }

    public function test_a_grade_is_clamped_to_the_marks_own_ceiling(): void
    {
        $this->assertSame(3, MenuItemMarks::sanitize([['key' => 'spicy', 'grade' => 99]])[0]['grade']);
        $this->assertSame(1, MenuItemMarks::sanitize([['key' => 'spicy', 'grade' => 0]])[0]['grade']);
        $this->assertSame(2, MenuItemMarks::sanitize([['key' => 'spicy', 'grade' => 2]])[0]['grade']);

        // An ungraded mark carries no grade at all rather than a 1 nobody
        // reads: a dish is vegetarian, not vegetarian once.
        $this->assertArrayNotHasKey('grade', MenuItemMarks::sanitize([['key' => 'veg', 'grade' => 3]])[0]);
    }

    public function test_the_same_mark_twice_is_one_mark(): void
    {
        $out = MenuItemMarks::sanitize([
            ['key' => 'spicy', 'grade' => 1],
            ['key' => 'spicy', 'grade' => 3],
            ['key' => 'veg'], ['key' => 'veg'],
        ]);

        $this->assertCount(2, $out);
        // The higher one, not whichever happened to arrive last.
        $this->assertSame(3, collect($out)->firstWhere('key', 'spicy')['grade']);
    }

    public function test_a_dish_cannot_wear_more_marks_than_a_row_can_hold(): void
    {
        $everything = MenuItemMarks::vocabulary()->keys()->map(fn ($k) => ['key' => $k])->all();
        $this->assertGreaterThan(MenuItemMark::MAX_PER_ITEM, count($everything));

        $this->assertCount(MenuItemMark::MAX_PER_ITEM, MenuItemMarks::sanitize($everything));
    }

    public function test_marks_come_out_in_one_order_however_they_went_in(): void
    {
        // Two dishes marked the same must read the same, so the order is
        // the vocabulary's, never the order somebody ticked the boxes.
        $a = MenuItemMarks::sanitize([['key' => 'no-onion'], ['key' => 'spicy'], ['key' => 'veg']]);
        $b = MenuItemMarks::sanitize([['key' => 'veg'], ['key' => 'no-onion'], ['key' => 'spicy']]);

        $this->assertSame(array_column($a, 'key'), array_column($b, 'key'));
        $this->assertSame(['veg', 'spicy', 'no-onion'], array_column($a, 'key'));
    }

    public function test_a_group_decides_the_order_not_the_row_it_was_added_on(): void
    {
        // The sort_order column orders marks WITHIN a group; the group
        // itself orders them against each other. Without that, a
        // preference mark added early in admin draws before the veg square
        // on every menu on the platform, and nobody would connect the two.
        MenuItemMark::create([
            'key' => 'late-diet', 'label' => 'Halal', 'group' => MenuItemMark::GROUP_DIET,
            'icon' => 'diet-mark', 'color' => '#0a8f3c', 'sort_order' => 9000, 'is_active' => true,
        ]);
        MenuItemMark::create([
            'key' => 'early-pref', 'label' => 'No ginger', 'group' => MenuItemMark::GROUP_PREFERENCE,
            'sort_order' => 1, 'is_active' => true,
        ]);
        MenuItemMarks::forget();

        $out = array_column(MenuItemMarks::sanitize([['key' => 'early-pref'], ['key' => 'late-diet']]), 'key');

        $this->assertSame(['late-diet', 'early-pref'], $out,
            'A preference mark is drawing before a diet mark because it was added first.');
    }

    public function test_rubbish_does_not_throw(): void
    {
        foreach ([null, '', 'nonsense', 42, ['x'], [['no' => 'key']], [[]]] as $junk) {
            $this->assertSame([], MenuItemMarks::sanitize($junk));
        }
    }

    // ===== A retired mark stops being drawn ==============================

    public function test_switching_a_mark_off_takes_it_off_every_dish_at_once(): void
    {
        [$link, , $item] = $this->restaurant([['key' => 'veg'], ['key' => 'jain']]);

        $this->assertStringContainsString('Jain', $this->get('/'.$link->alias)->getContent());

        MenuItemMark::where('key', 'jain')->update(['is_active' => false]);
        MenuItemMarks::forget();

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();
        $this->assertStringNotContainsString('Jain', $html,
            'A mark retired in admin is still drawn on the dishes that were saved with it.');
        // And the rest of the dish is untouched.
        $this->assertStringContainsString('Vegetarian', $html);
        $this->assertSame(['veg'], array_column($item->fresh()->marksForDisplay(), 'key'));
    }

    // ===== The diner sees them ===========================================

    public function test_both_menus_draw_the_marks_on_the_row(): void
    {
        foreach ([[$this->restaurant([['key' => 'veg'], ['key' => 'spicy', 'grade' => 3]]), 'restaurant'],
            [$this->store([['key' => 'veg'], ['key' => 'spicy', 'grade' => 3]]), 'store']] as [$made, $kind]) {
            [$link, , ] = $made;

            $html = $this->get('/'.$link->alias)->assertOk()->getContent();

            $this->assertStringContainsString('class="marks"', $html, "The {$kind} page draws no marks.");
            $this->assertStringContainsString('#0a8f3c', $html,
                "The {$kind} page lost the colour, which is the whole meaning of that square.");
            // Three flames, one per grade.
            $flame = MenuItemMarks::vocabulary()->get('spicy')->shape()['path'];
            $this->assertSame(3, substr_count($html, $flame),
                "The {$kind} page does not draw spice 3 as three flames.");
        }
    }

    public function test_a_dish_with_no_marks_renders_exactly_as_before(): void
    {
        // "not all item need to have these configs" -- nothing is required
        // and an unmarked dish grows no empty container.
        [$link, , ] = $this->restaurant([]);

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        $this->assertStringContainsString('Paneer Butter Masala', $html);
        $this->assertStringNotContainsString('class="marks"', $html);
    }

    public function test_the_icons_are_decorative_and_the_words_are_not(): void
    {
        [$link, , ] = $this->restaurant([['key' => 'veg'], ['key' => 'spicy', 'grade' => 2]]);

        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        // A diner on a screen reader hears the list; the drawings are
        // hidden so they do not hear "image image image".
        $this->assertStringContainsString('Vegetarian, Spicy 2', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    public function test_a_marked_dish_costs_no_extra_queries(): void
    {
        [$link, $menu, $item] = $this->restaurant([['key' => 'veg'], ['key' => 'spicy', 'grade' => 2]]);
        for ($i = 0; $i < 25; $i++) {
            RestaurantMenuItem::create([
                'menu_id' => $menu->id, 'category_id' => $item->category_id,
                'name' => 'Dish '.$i, 'price' => 100,
                'marks' => [['key' => 'nonveg'], ['key' => 'spicy', 'grade' => 3]],
                'sort_order' => $i + 1, 'is_active' => true,
            ]);
        }

        MenuItemMarks::forget();
        \DB::enableQueryLog();
        $this->get('/'.$link->alias)->assertOk();
        $forMarks = collect(\DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'menu_item_marks'))
            ->count();
        \DB::disableQueryLog();

        $this->assertLessThanOrEqual(1, $forMarks,
            'Twenty-six dishes must not mean twenty-six vocabulary queries.');
    }

    // ===== The owner can set them ========================================

    public function test_the_owner_can_mark_a_dish(): void
    {
        [$link, , $item] = $this->restaurant();

        $this->actingAs($this->owner)
            ->putJson('/user/links/'.$link->id.'/restaurant/items/'.$item->id, [
                'name'  => 'Paneer Butter Masala',
                'marks' => [['key' => 'veg'], ['key' => 'spicy', 'grade' => 2], ['key' => 'unicorn']],
            ])->assertOk();

        $stored = $item->fresh()->marks;
        $this->assertSame(['veg', 'spicy'], array_column($stored, 'key'));
        $this->assertSame(2, $stored[1]['grade']);
    }

    public function test_the_store_can_mark_a_product_too(): void
    {
        [$link, , $product] = $this->store();

        $this->actingAs($this->owner)
            ->putJson('/user/links/'.$link->id.'/store/products/'.$product->id, [
                'name'  => 'Pickle',
                'marks' => [['key' => 'veg'], ['key' => 'spicy', 'grade' => 3]],
            ])->assertOk();

        $this->assertSame(['veg', 'spicy'], array_column($product->fresh()->marks, 'key'));
    }

    public function test_a_grade_of_forty_does_not_reach_the_database(): void
    {
        // The page clamps for the owner's sake; this clamps for the menu's.
        [$link, , $item] = $this->restaurant();

        $this->actingAs($this->owner)
            ->putJson('/user/links/'.$link->id.'/restaurant/items/'.$item->id, [
                'name' => 'x', 'marks' => [['key' => 'spicy', 'grade' => 40]],
            ])->assertOk();

        $this->assertSame(3, $item->fresh()->marks[0]['grade']);
    }

    public function test_not_sending_marks_leaves_the_ones_already_there(): void
    {
        // The photo dialog saves a dish without touching its marks; that
        // must not silently strip them.
        [$link, , $item] = $this->restaurant([['key' => 'veg']]);

        $this->actingAs($this->owner)
            ->putJson('/user/links/'.$link->id.'/restaurant/items/'.$item->id, ['name' => 'Renamed'])
            ->assertOk();

        $this->assertSame('Renamed', $item->fresh()->name);
        $this->assertSame(['veg'], array_column($item->fresh()->marks, 'key'));
    }

    // ===== And there is a screen for it ==================================

    public function test_the_picker_is_in_both_item_dialogs(): void
    {
        [$restaurant, , ] = $this->restaurant();
        [$store, , ] = $this->store();

        foreach ([[$restaurant, 'restaurant', 'itemModal'], [$store, 'store', 'productModal']] as [$link, $kind, $modal]) {
            $html = $this->actingAs($this->owner)
                ->get('/user/links/'.$link->id.'/'.$kind)->assertOk()->getContent();

            $this->assertStringContainsString('MENU_MARK_GROUPS', $html,
                "The {$kind} editor never received the vocabulary.");
            $this->assertStringContainsString('menuMarks.toggle('.$modal.'.marks', $html,
                "The {$kind} item dialog has no way to set a mark.");
            $this->assertStringContainsString('Vegetarian', $html);
            $this->assertStringContainsString('No garlic', $html);
        }
    }

    public function test_the_editor_opens_on_the_marks_a_dish_already_has(): void
    {
        [$link, , ] = $this->restaurant([['key' => 'veg'], ['key' => 'spicy', 'grade' => 2]]);

        $html = $this->actingAs($this->owner)
            ->get('/user/links/'.$link->id.'/restaurant')->assertOk()->getContent();

        // Otherwise editing a dish for any reason quietly clears them.
        $this->assertMatchesRegularExpression('/(&quot;|\\\\u0022|")key\\1?:\\1?(&quot;|\\\\u0022|")?spicy/', $html,
            'The editor was not told which marks the dish carries.');
        $this->assertStringContainsString('marks:(item.marks||[])', $html);
    }

    public function test_admin_has_a_screen_and_it_is_in_the_sidebar(): void
    {
        // Seven features this month were built with no way in. This is the
        // way in.
        $admin = $this->admin();

        $page = $this->actingAs($admin, 'admin')->get('/admin/menu-item-marks');
        $page->assertOk();
        $page->assertSee('Menu marks');
        $page->assertSee('No garlic');
        $page->assertSee('Vegetarian');

        foreach (['admin/layouts/app', 'admin/partials/sidebar'] as $nav) {
            $src = file_get_contents(resource_path('views/'.$nav.'.blade.php'));
            $this->assertStringContainsString("admin.menu-item-marks.index", $src,
                "There is no link to the marks screen in {$nav}.");
        }
    }

    public function test_admin_can_add_and_retire_a_mark(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post('/admin/menu-item-marks', [
            'label' => 'Chef special', 'key' => 'Chef Special!', 'group' => 'other', 'icon' => 'star',
            'color' => '#D18700',
        ])->assertRedirect();

        $mark = MenuItemMark::where('label', 'Chef special')->first();
        $this->assertNotNull($mark);
        $this->assertSame('chef-special', $mark->key, 'The key was not normalised.');
        $this->assertSame('#d18700', $mark->color);

        $this->actingAs($admin, 'admin')->post('/admin/menu-item-marks/'.$mark->id.'/toggle')->assertRedirect();
        $this->assertFalse($mark->fresh()->is_active);
    }

    public function test_admin_cannot_rename_a_key_out_from_under_the_dishes(): void
    {
        // Renaming a key strips the mark off every dish storing it, with no
        // error and nothing to look at. The label is what people mean.
        $admin = $this->admin();
        $veg = MenuItemMark::where('key', 'veg')->first();

        $this->actingAs($admin, 'admin')->put('/admin/menu-item-marks/'.$veg->id, [
            'key' => 'vegetarian', 'label' => 'Pure veg', 'group' => 'diet',
            'icon' => 'diet-mark', 'color' => '#0a8f3c',
        ])->assertRedirect();

        $veg->refresh();
        $this->assertSame('veg', $veg->key);
        $this->assertSame('Pure veg', $veg->label);
    }

    public function test_an_icon_admin_cannot_draw_is_refused(): void
    {
        $this->actingAs($this->admin(), 'admin')->post('/admin/menu-item-marks', [
            'label' => 'Nonsense', 'key' => 'nonsense', 'group' => 'other', 'icon' => 'chilli',
        ])->assertSessionHasErrors('icon');
    }

    public function test_the_marks_screen_is_staff_only(): void
    {
        $this->get('/admin/menu-item-marks')->assertRedirect();
    }
}
