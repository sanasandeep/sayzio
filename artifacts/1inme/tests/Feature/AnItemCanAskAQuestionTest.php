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
use App\Modules\User\Support\MenuOptionSelection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sana, 2026-09-28: "like spicy levels, served like hot cold.. variations
 * like sizes and toppings options with limitions settings (like select 2)
 * ... options like addons with variable quatity".
 *
 * Four asks, one object: a group of choices with a floor, a ceiling, and a
 * cap on how many times one choice can be taken. This test is mostly proof
 * of that claim -- each of his four examples expressed in the same shape --
 * and then proof that the rules hold on the server, where the bill is
 * decided, rather than only in the page where a guest can edit them away.
 */
class AnItemCanAskAQuestionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private RestaurantMenu $menu;

    private RestaurantMenuItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->user);

        $link = Link::create([
            'user_id' => $this->user->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'),
            'title' => 'Priyumm Tiffins', 'is_active' => true,
        ]);
        $this->menu = RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->user->id,
            'mode' => 'order', 'currency' => 'INR', 'settings' => [],
        ]);
        $cat = RestaurantMenuCategory::create([
            'menu_id' => $this->menu->id, 'name' => 'Starters', 'sort_order' => 0, 'is_active' => true,
        ]);
        $this->item = RestaurantMenuItem::create([
            'menu_id' => $this->menu->id, 'category_id' => $cat->id,
            'name' => 'Chilli Paneer', 'price' => 90, 'sort_order' => 0, 'is_active' => true,
        ]);
    }

    /**
     * @param  array<int, array{0:string, 1:float}>  $options  [name, delta]
     */
    private function group(string $name, array $rules, array $options, ?RestaurantMenuItem $on = null): MenuOptionGroup
    {
        $group = MenuOptionGroup::create(array_merge([
            'menu_type' => MenuOptionGroup::RESTAURANT,
            'menu_id' => $this->menu->id,
            'name' => $name,
            'sort_order' => 0,
            'is_active' => true,
        ], $rules));

        foreach ($options as $i => [$optName, $delta]) {
            MenuOption::create([
                'group_id' => $group->id, 'name' => $optName,
                'price_delta' => $delta, 'sort_order' => $i, 'is_active' => true,
            ]);
        }

        MenuItemOptionGroup::create([
            'group_id' => $group->id,
            'owner_type' => MenuItemOptionGroup::RESTAURANT_ITEM,
            'owner_id' => ($on ?? $this->item)->id,
            'sort_order' => MenuItemOptionGroup::where('owner_id', ($on ?? $this->item)->id)->count(),
        ]);

        return $group->fresh();
    }

    private function itemGroups(): \Illuminate\Support\Collection
    {
        return MenuOptionSelection::groupsFor(
            MenuItemOptionGroup::RESTAURANT_ITEM, $this->item->id
        );
    }

    /** @param array<int, array{0:MenuOption|int, 1?:int}> $picks */
    private function resolve(array $picks): array
    {
        return MenuOptionSelection::resolve(
            $this->itemGroups(),
            array_map(fn ($p) => [
                'option_id' => $p[0] instanceof MenuOption ? $p[0]->id : $p[0],
                'quantity' => $p[1] ?? 1,
            ], $picks),
            $this->item->name
        );
    }

    private function optionNamed(string $name): MenuOption
    {
        return MenuOption::where('name', $name)->firstOrFail();
    }

    // ===== 1. His four examples, in one shape ============================

    public function test_a_spice_level_is_one_required_choice_that_costs_nothing(): void
    {
        $this->group('Spice level', ['is_required' => true, 'min_select' => 1, 'max_select' => 1], [
            ['Mild', 0], ['Medium', 0], ['Hot', 0],
        ]);

        $out = $this->resolve([[$this->optionNamed('Medium')]]);

        $this->assertSame(0.0, $out['total']);
        $this->assertSame([['group' => 'Spice level', 'name' => 'Medium', 'delta' => 0.0, 'quantity' => 1]], $out['lines']);
    }

    public function test_a_size_is_the_same_thing_with_prices_on_it(): void
    {
        $this->group('Size', ['is_required' => true, 'min_select' => 1, 'max_select' => 1], [
            ['Half', -20], ['Full', 0], ['Family', 60],
        ]);

        // A smaller size takes money off, which is why the delta is signed.
        $this->assertSame(-20.0, $this->resolve([[$this->optionNamed('Half')]])['total']);
        $this->assertSame(60.0, $this->resolve([[$this->optionNamed('Family')]])['total']);
    }

    public function test_toppings_are_the_same_thing_with_a_ceiling_of_two(): void
    {
        $this->group('Toppings', ['min_select' => 0, 'max_select' => 2], [
            ['Cheese', 20], ['Extra paneer', 30], ['Olives', 25],
        ]);

        $two = $this->resolve([[$this->optionNamed('Cheese')], [$this->optionNamed('Olives')]]);
        $this->assertSame(45.0, $two['total']);

        // None is fine -- it is not a required group.
        $this->assertSame(0.0, $this->resolve([])['total']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at most 2 from Toppings');
        $this->resolve([
            [$this->optionNamed('Cheese')], [$this->optionNamed('Olives')], [$this->optionNamed('Extra paneer')],
        ]);
    }

    public function test_an_addon_is_the_same_thing_taken_more_than_once(): void
    {
        $this->group('Add-ons', ['min_select' => 0, 'max_select' => null, 'max_per_option' => 3], [
            ['Extra paneer', 30], ['Extra sauce', 10],
        ]);

        $out = $this->resolve([[$this->optionNamed('Extra paneer'), 2], [$this->optionNamed('Extra sauce'), 3]]);

        $this->assertSame(90.0, $out['total']); // 30x2 + 10x3
        $this->assertSame(2, $out['lines'][0]['quantity']);
        $this->assertSame(3, $out['lines'][1]['quantity']);
    }

    // ===== 2. The rules hold where the bill is decided ===================

    public function test_a_required_group_cannot_simply_be_left_out(): void
    {
        $this->group('Spice level', ['is_required' => true], [['Mild', 0], ['Hot', 0]]);

        // The whole point: an empty request is the shape a tampered one
        // takes, and the group nobody touched is the one that must fail.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Please choose a spice level for Chilli Paneer.');
        $this->resolve([]);
    }

    public function test_required_and_a_minimum_of_zero_still_means_at_least_one(): void
    {
        // Ticking "required" and leaving the minimum alone is the obvious
        // way for a creator to say this, and reading the two flags
        // separately is how a page lets the guest skip it anyway.
        $group = $this->group('Spice level', ['is_required' => true, 'min_select' => 0], [['Mild', 0]]);

        $this->assertSame(1, $group->bounds()['min']);
        $this->assertTrue($group->isRequired());
    }

    public function test_a_minimum_without_the_flag_is_still_required(): void
    {
        $group = $this->group('Sides', ['is_required' => false, 'min_select' => 2, 'max_select' => 3], [
            ['Rice', 0], ['Naan', 0], ['Salad', 0],
        ]);

        $this->assertTrue($group->isRequired());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at least 2 from Sides');
        $this->resolve([[$this->optionNamed('Rice')]]);
    }

    public function test_a_ceiling_below_the_floor_is_read_as_a_typo(): void
    {
        // Not as an instruction to make the group impossible to satisfy.
        $group = $this->group('Sides', ['min_select' => 3, 'max_select' => 1], [['Rice', 0]]);

        $this->assertSame(['min' => 3, 'max' => 3], $group->bounds());
    }

    public function test_one_choice_cannot_be_taken_more_often_than_allowed(): void
    {
        $this->group('Add-ons', ['max_per_option' => 2], [['Extra sauce', 10]]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at most 2 times');
        $this->resolve([[$this->optionNamed('Extra sauce'), 5]]);
    }

    public function test_the_same_choice_sent_twice_is_added_up_before_the_cap(): void
    {
        // Two lines of 2 is four of them, and must not pass a cap of 3
        // just because each line is under it on its own.
        $this->group('Add-ons', ['max_per_option' => 3], [['Extra sauce', 10]]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at most 3 times');
        $this->resolve([[$this->optionNamed('Extra sauce'), 2], [$this->optionNamed('Extra sauce'), 2]]);
    }

    public function test_a_choice_from_another_item_is_not_on_offer(): void
    {
        $other = RestaurantMenuItem::create([
            'menu_id' => $this->menu->id, 'category_id' => $this->item->category_id,
            'name' => 'Veg Manchuria', 'price' => 80, 'sort_order' => 1, 'is_active' => true,
        ]);
        $this->group('Gravy', ['min_select' => 0], [['Extra gravy', 15]], $other);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no longer available');
        $this->resolve([[$this->optionNamed('Extra gravy')]]);
    }

    public function test_a_sold_out_choice_cannot_be_ordered(): void
    {
        $this->group('Toppings', ['min_select' => 0], [['Cheese', 20]]);
        $cheese = $this->optionNamed('Cheese');
        $cheese->update(['is_sold_out' => true]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no longer available');
        $this->resolve([[$cheese]]);
    }

    public function test_choices_come_back_in_the_menus_order_not_the_browsers(): void
    {
        $this->group('Toppings', ['min_select' => 0, 'max_select' => 3], [
            ['Cheese', 20], ['Olives', 25], ['Extra paneer', 30],
        ]);

        // Sent last-first; two identical orders must read identically on
        // the kitchen screen regardless of tap order.
        $out = $this->resolve([
            [$this->optionNamed('Extra paneer')], [$this->optionNamed('Cheese')], [$this->optionNamed('Olives')],
        ]);

        $this->assertSame(['Cheese', 'Olives', 'Extra paneer'], array_column($out['lines'], 'name'));
    }

    // ===== 3. One group, many items ======================================

    public function test_a_group_is_defined_once_and_attached_to_many_items(): void
    {
        // The reason these are not JSON on the item: "Spice level" belongs
        // on thirty dishes and must not be thirty copies to edit.
        $second = RestaurantMenuItem::create([
            'menu_id' => $this->menu->id, 'category_id' => $this->item->category_id,
            'name' => 'Chicken Manchuria', 'price' => 120, 'sort_order' => 1, 'is_active' => true,
        ]);

        $group = $this->group('Spice level', ['is_required' => true], [['Mild', 0], ['Hot', 0]]);
        MenuItemOptionGroup::create([
            'group_id' => $group->id,
            'owner_type' => MenuItemOptionGroup::RESTAURANT_ITEM,
            'owner_id' => $second->id,
            'sort_order' => 0,
        ]);

        // Adding a choice once reaches both dishes.
        MenuOption::create([
            'group_id' => $group->id, 'name' => 'Extra hot', 'price_delta' => 0,
            'sort_order' => 2, 'is_active' => true,
        ]);

        foreach ([$this->item, $second] as $item) {
            $groups = MenuOptionSelection::groupsFor(MenuItemOptionGroup::RESTAURANT_ITEM, $item->id);
            $this->assertCount(1, $groups);
            $this->assertSame(
                ['Mild', 'Hot', 'Extra hot'],
                $groups->first()->options->pluck('name')->all(),
                'A choice added to the group should reach every item carrying it.'
            );
        }
    }

    public function test_a_page_of_items_costs_one_query_not_one_each(): void
    {
        $ids = [$this->item->id];
        for ($i = 0; $i < 12; $i++) {
            $ids[] = RestaurantMenuItem::create([
                'menu_id' => $this->menu->id, 'category_id' => $this->item->category_id,
                'name' => 'Dish '.$i, 'price' => 50, 'sort_order' => $i + 1, 'is_active' => true,
            ])->id;
        }
        $group = $this->group('Spice level', ['is_required' => true], [['Mild', 0]]);
        foreach (array_slice($ids, 1) as $id) {
            MenuItemOptionGroup::create([
                'group_id' => $group->id,
                'owner_type' => MenuItemOptionGroup::RESTAURANT_ITEM,
                'owner_id' => $id, 'sort_order' => 0,
            ]);
        }

        \DB::enableQueryLog();
        $all = MenuOptionSelection::groupsForMany(MenuItemOptionGroup::RESTAURANT_ITEM, $ids);
        $queries = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        $this->assertCount(13, $all);
        $this->assertLessThanOrEqual(2, $queries,
            'Thirteen items must not mean fourteen queries.');
    }

    // ===== 4. The store gets the same tables =============================

    public function test_the_store_uses_the_same_groups_the_restaurant_does(): void
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

        $group = MenuOptionGroup::create([
            'menu_type' => MenuOptionGroup::typeFor($menu), 'menu_id' => $menu->id,
            'name' => 'Size', 'is_required' => true, 'min_select' => 1, 'max_select' => 1,
            'sort_order' => 0, 'is_active' => true,
        ]);
        MenuOption::create(['group_id' => $group->id, 'name' => 'Large', 'price_delta' => 80, 'sort_order' => 0, 'is_active' => true]);
        MenuItemOptionGroup::create([
            'group_id' => $group->id,
            'owner_type' => MenuItemOptionGroup::typeFor($product),
            'owner_id' => $product->id, 'sort_order' => 0,
        ]);

        $this->assertSame(MenuOptionGroup::STORE, $group->menu_type);
        $this->assertSame(MenuItemOptionGroup::STORE_PRODUCT, MenuItemOptionGroup::typeFor($product));

        $out = MenuOptionSelection::resolve(
            MenuOptionSelection::groupsFor(MenuItemOptionGroup::STORE_PRODUCT, $product->id),
            [['option_id' => MenuOption::where('name', 'Large')->first()->id, 'quantity' => 1]],
            'Mug'
        );

        $this->assertSame(80.0, $out['total']);
    }

    // ===== 5. What the page is told to say ===============================

    public function test_the_rule_is_described_once_for_every_screen(): void
    {
        $cases = [
            ['Choose 1', ['is_required' => true, 'min_select' => 1, 'max_select' => 1]],
            ['Choose 2', ['min_select' => 2, 'max_select' => 2]],
            ['Choose up to 2', ['min_select' => 0, 'max_select' => 2]],
            ['Optional', ['min_select' => 0, 'max_select' => null]],
            ['Choose at least 1, up to 3', ['min_select' => 1, 'max_select' => 3]],
            ['Optional · each up to 3x', ['min_select' => 0, 'max_select' => null, 'max_per_option' => 3]],
        ];

        foreach ($cases as [$expected, $rules]) {
            $group = new MenuOptionGroup(array_merge(
                ['name' => 'G', 'max_per_option' => 1], $rules
            ));
            $this->assertSame($expected, MenuOptionSelection::ruleLabel($group));
        }
    }
}
