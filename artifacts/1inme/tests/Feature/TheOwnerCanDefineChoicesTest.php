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
 * The screen side of item choices: defining a group, giving it choices, and
 * saying which items it is on.
 *
 * The data model and the rules have their own test. This one is about the
 * endpoints the editor drives, and mostly about what they REFUSE -- because
 * "a group id" and "an item id" arriving over HTTP are not the same thing
 * as "a group on this menu" and "an item on this menu", and the difference
 * is somebody editing a menu they do not own.
 *
 * It also pins that both menu types get the same endpoints. Seven features
 * this month reached the restaurant and not the store; mounting one
 * controller under both prefixes is only half the fix, and this is the
 * other half.
 */
class TheOwnerCanDefineChoicesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Link $link;

    private RestaurantMenu $menu;

    private RestaurantMenuItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['onboarded_at' => now()]);
        app(WorkspaceContext::class)->resolve($this->owner);

        $this->link = Link::create([
            'user_id' => $this->owner->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'),
            'title' => 'Priyumm Tiffins', 'is_active' => true,
        ]);
        $this->menu = RestaurantMenu::create([
            'link_id' => $this->link->id, 'user_id' => $this->owner->id,
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

    private function base(?Link $link = null, string $kind = 'restaurant'): string
    {
        return '/user/links/'.($link ?? $this->link)->id.'/'.$kind.'/option-groups';
    }

    private function makeGroup(array $over = []): int
    {
        $res = $this->actingAs($this->owner)
            ->postJson($this->base(), array_merge([
                'name' => 'Spice level', 'is_required' => true,
                'min_select' => 1, 'max_select' => 1,
            ], $over))
            ->assertCreated();

        return (int) $res->json('data.group.id');
    }

    // ===== Defining a group ==============================================

    public function test_a_group_can_be_created_read_changed_and_removed(): void
    {
        $id = $this->makeGroup();

        $listed = $this->actingAs($this->owner)->getJson($this->base())->assertOk();
        $this->assertSame('Spice level', $listed->json('data.groups.0.name'));
        $this->assertSame('Choose 1', $listed->json('data.groups.0.rule_label'));

        $this->actingAs($this->owner)
            ->putJson($this->base().'/'.$id, [
                'name' => 'Toppings', 'is_required' => false,
                'min_select' => 0, 'max_select' => 2, 'max_per_option' => 3,
            ])->assertOk();

        $group = MenuOptionGroup::find($id);
        $this->assertSame('Toppings', $group->name);
        $this->assertSame(['min' => 0, 'max' => 2], $group->bounds());
        $this->assertSame(3, $group->perOptionCap());

        $this->actingAs($this->owner)->deleteJson($this->base().'/'.$id)->assertOk();
        $this->assertNull(MenuOptionGroup::find($id));
    }

    public function test_no_ceiling_is_a_real_answer_and_survives_a_round_trip(): void
    {
        // "Add as many as you like" is null, not zero, and the editor has
        // to be able to send it and read it back unchanged.
        $id = $this->makeGroup(['name' => 'Add-ons', 'is_required' => false, 'min_select' => 0, 'max_select' => null]);

        $this->assertNull(MenuOptionGroup::find($id)->max_select);
        $this->assertNull(
            $this->actingAs($this->owner)->getJson($this->base())->json('data.groups.0.max_select')
        );
    }

    public function test_deleting_a_group_takes_its_choices_and_attachments_with_it(): void
    {
        $id = $this->makeGroup();
        $this->actingAs($this->owner)
            ->postJson($this->base().'/'.$id.'/options', ['name' => 'Mild'])->assertCreated();
        $this->actingAs($this->owner)
            ->postJson($this->base().'/'.$id.'/items', ['item_ids' => [$this->item->id]])->assertOk();

        $this->actingAs($this->owner)->deleteJson($this->base().'/'.$id)->assertOk();

        // Nothing left pointing at a group that no longer exists.
        $this->assertSame(0, MenuOption::where('group_id', $id)->count());
        $this->assertSame(0, MenuItemOptionGroup::where('group_id', $id)->count());
    }

    // ===== Choices inside it =============================================

    public function test_a_choice_can_take_money_off_as_well_as_add_it(): void
    {
        $id = $this->makeGroup(['name' => 'Size']);

        $half = $this->actingAs($this->owner)
            ->postJson($this->base().'/'.$id.'/options', ['name' => 'Half', 'price_delta' => -20])
            ->assertCreated();

        $this->assertSame(-20.0, (float) $half->json('data.option.price_delta'));
    }

    public function test_a_choice_can_be_marked_sold_out_without_deleting_it(): void
    {
        $id = $this->makeGroup(['name' => 'Toppings', 'is_required' => false, 'min_select' => 0, 'max_select' => 2]);
        $optionId = (int) $this->actingAs($this->owner)
            ->postJson($this->base().'/'.$id.'/options', ['name' => 'Extra paneer', 'price_delta' => 30])
            ->json('data.option.id');

        $this->actingAs($this->owner)
            ->putJson($this->base().'/'.$id.'/options/'.$optionId, [
                'name' => 'Extra paneer', 'price_delta' => 30, 'is_sold_out' => true,
            ])->assertOk();

        $option = MenuOption::find($optionId);
        $this->assertTrue($option->is_sold_out);
        $this->assertFalse($option->isAvailable());
        // Still there for tomorrow.
        $this->assertTrue($option->is_active);
    }

    // ===== Which items it is on ==========================================

    public function test_attaching_sends_the_whole_list_so_unticking_sticks(): void
    {
        $second = RestaurantMenuItem::create([
            'menu_id' => $this->menu->id, 'category_id' => $this->item->category_id,
            'name' => 'Veg Manchuria', 'price' => 80, 'sort_order' => 1, 'is_active' => true,
        ]);
        $id = $this->makeGroup();

        $this->actingAs($this->owner)
            ->postJson($this->base().'/'.$id.'/items', ['item_ids' => [$this->item->id, $second->id]])
            ->assertOk();
        $this->assertSame(2, MenuItemOptionGroup::where('group_id', $id)->count());

        // Untick one: it goes, and the other is not re-created.
        $this->actingAs($this->owner)
            ->postJson($this->base().'/'.$id.'/items', ['item_ids' => [$second->id]])
            ->assertOk();

        $left = MenuItemOptionGroup::where('group_id', $id)->pluck('owner_id')->map(fn ($i) => (int) $i)->all();
        $this->assertSame([$second->id], $left);

        // And an empty list means off everything, which is a real answer.
        $this->actingAs($this->owner)
            ->postJson($this->base().'/'.$id.'/items', ['item_ids' => []])->assertOk();
        $this->assertSame(0, MenuItemOptionGroup::where('group_id', $id)->count());
    }

    public function test_an_item_from_another_menu_is_quietly_dropped(): void
    {
        $otherLink = Link::create([
            'user_id' => $this->owner->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'), 'title' => 'Other', 'is_active' => true,
        ]);
        $otherMenu = RestaurantMenu::create([
            'link_id' => $otherLink->id, 'user_id' => $this->owner->id,
            'mode' => 'order', 'currency' => 'INR', 'settings' => [],
        ]);
        $otherCat = RestaurantMenuCategory::create([
            'menu_id' => $otherMenu->id, 'name' => 'X', 'sort_order' => 0, 'is_active' => true,
        ]);
        $foreign = RestaurantMenuItem::create([
            'menu_id' => $otherMenu->id, 'category_id' => $otherCat->id,
            'name' => 'Not mine', 'price' => 10, 'sort_order' => 0, 'is_active' => true,
        ]);

        $id = $this->makeGroup();
        $res = $this->actingAs($this->owner)
            ->postJson($this->base().'/'.$id.'/items', ['item_ids' => [$this->item->id, $foreign->id]])
            ->assertOk();

        $this->assertSame([$this->item->id], $res->json('data.item_ids'));
        $this->assertSame(1, MenuItemOptionGroup::where('group_id', $id)->count());
    }

    // ===== What it refuses ===============================================

    public function test_someone_elses_menu_is_not_editable(): void
    {
        $stranger = User::factory()->create(['onboarded_at' => now()]);
        $id = $this->makeGroup();

        app(WorkspaceContext::class)->resolve($stranger);

        // 404 rather than 403 is the better answer and what the workspace
        // scoping gives: a stranger learns nothing about whether the link
        // exists. Either refusal is fine; being served is not.
        foreach ([
            $this->actingAs($stranger)->getJson($this->base()),
            $this->actingAs($stranger)->postJson($this->base(), ['name' => 'Nope']),
            $this->actingAs($stranger)->deleteJson($this->base().'/'.$id),
        ] as $response) {
            $this->assertContains($response->status(), [403, 404],
                'A stranger got served on someone else\'s menu.');
        }

        // And nothing of theirs moved.
        $this->assertSame('Spice level', MenuOptionGroup::find($id)->name);
    }

    public function test_a_group_from_another_menu_is_not_found_on_this_one(): void
    {
        $otherLink = Link::create([
            'user_id' => $this->owner->id, 'type' => 'restaurant_menu',
            'alias' => 'rm'.fake()->unique()->numerify('#####'), 'title' => 'Other', 'is_active' => true,
        ]);
        $otherMenu = RestaurantMenu::create([
            'link_id' => $otherLink->id, 'user_id' => $this->owner->id,
            'mode' => 'order', 'currency' => 'INR', 'settings' => [],
        ]);
        $foreign = MenuOptionGroup::create([
            'menu_type' => MenuOptionGroup::RESTAURANT, 'menu_id' => $otherMenu->id,
            'name' => 'Theirs', 'sort_order' => 0, 'is_active' => true,
        ]);

        // Same owner, different menu: still a 404 on this link's endpoint,
        // because "a group id" is not "a group on this menu".
        $this->actingAs($this->owner)
            ->putJson($this->base().'/'.$foreign->id, ['name' => 'Mine now'])
            ->assertNotFound();

        $this->assertSame('Theirs', $foreign->fresh()->name);
    }

    public function test_a_store_group_is_not_reachable_through_the_restaurant_route(): void
    {
        // The tables are shared, so menu_type is the only thing keeping a
        // store's groups out of a restaurant's editor.
        $storeGroup = MenuOptionGroup::create([
            'menu_type' => MenuOptionGroup::STORE, 'menu_id' => $this->menu->id,
            'name' => 'Store only', 'sort_order' => 0, 'is_active' => true,
        ]);

        $this->actingAs($this->owner)
            ->deleteJson($this->base().'/'.$storeGroup->id)
            ->assertNotFound();

        $this->assertNotNull($storeGroup->fresh());
    }

    public function test_a_menu_cannot_define_unlimited_groups(): void
    {
        for ($i = 0; $i < \App\Modules\User\Controllers\MenuOptionController::MAX_GROUPS; $i++) {
            MenuOptionGroup::create([
                'menu_type' => MenuOptionGroup::RESTAURANT, 'menu_id' => $this->menu->id,
                'name' => 'G'.$i, 'sort_order' => $i, 'is_active' => true,
            ]);
        }

        $this->actingAs($this->owner)
            ->postJson($this->base(), ['name' => 'One too many'])
            ->assertStatus(422);
    }

    // ===== The store gets the same endpoints =============================

    public function test_the_store_has_every_endpoint_the_restaurant_has(): void
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
            'name' => 'Mug', 'price' => 450, 'sort_order' => 0, 'is_active' => true,
        ]);

        $base = $this->base($link, 'store');

        $id = (int) $this->actingAs($this->owner)
            ->postJson($base, ['name' => 'Size', 'is_required' => true, 'min_select' => 1, 'max_select' => 1])
            ->assertCreated()->json('data.group.id');

        $this->assertSame(MenuOptionGroup::STORE, MenuOptionGroup::find($id)->menu_type);

        $this->actingAs($this->owner)
            ->postJson($base.'/'.$id.'/options', ['name' => 'Large', 'price_delta' => 80])
            ->assertCreated();

        $this->actingAs($this->owner)
            ->postJson($base.'/'.$id.'/items', ['item_ids' => [$product->id]])
            ->assertOk();

        $row = MenuItemOptionGroup::where('group_id', $id)->firstOrFail();
        $this->assertSame(MenuItemOptionGroup::STORE_PRODUCT, $row->owner_type);
        $this->assertSame($product->id, (int) $row->owner_id);

        $listed = $this->actingAs($this->owner)->getJson($base)->assertOk();
        $this->assertSame('Large', $listed->json('data.groups.0.options.0.name'));
        $this->assertSame([$product->id], $listed->json('data.groups.0.item_ids'));
    }

    public function test_a_restaurant_item_cannot_be_attached_through_the_store_route(): void
    {
        $link = Link::create([
            'user_id' => $this->owner->id, 'type' => 'store_menu',
            'alias' => 'sm'.fake()->unique()->numerify('#####'),
            'title' => 'The Shop', 'is_active' => true,
        ]);
        StoreMenu::create([
            'link_id' => $link->id, 'user_id' => $this->owner->id,
            'mode' => 'order', 'currency' => 'INR', 'settings' => [],
        ]);
        $base = $this->base($link, 'store');

        $id = (int) $this->actingAs($this->owner)
            ->postJson($base, ['name' => 'Size'])->assertCreated()->json('data.group.id');

        // A restaurant item id, sent to the store's attach endpoint.
        $res = $this->actingAs($this->owner)
            ->postJson($base.'/'.$id.'/items', ['item_ids' => [$this->item->id]])
            ->assertOk();

        $this->assertSame([], $res->json('data.item_ids'));
        $this->assertSame(0, MenuItemOptionGroup::where('group_id', $id)->count());
    }
}
