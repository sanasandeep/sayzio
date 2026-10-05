<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuCategory;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\RestaurantOrder;
use App\Modules\User\Models\RestaurantOrderItem;
use App\Modules\User\Models\RestaurantTable;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreOrder;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\KitchenBoard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sana, 2026-10-05: "Need another dashboard like kitchen.... whowing all
 * table names if exists with current order status... aurto refresh also".
 *
 * What this file guards, hardest first:
 *
 *   - OLDEST FIRST, and a table's status is its oldest open ticket's. Get
 *     this backwards and the twenty-minute order hides behind the one
 *     placed a moment ago -- which is exactly the one the kitchen is late
 *     on, and the screen would be lying in the most expensive direction;
 *   - EVERY TABLE IS ON THE BOARD, empty ones included. A board that lists
 *     only tables with orders cannot tell you table 7 is free, which is
 *     half of what the person at the pass is reading it for;
 *   - "if exists" is his own hedge: a page with no tables, and the store,
 *     get the same board grouped by order rather than a blank screen;
 *   - finished work leaves. A completed order still sitting on a kitchen
 *     wall is a meal somebody cooks twice;
 *   - an order with no table is NOT dropped. A ticket the kitchen cannot
 *     see is a meal nobody cooks at all.
 */
class TheKitchenScreenShowsWhatIsWaitingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'name'     => 'Owner '.Str::random(4),
            'email'    => 'own'.Str::random(8).'@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);

        $ws = app(WorkspaceContext::class)->resolve($this->owner);
        if ($ws !== null) {
            app()->instance('current_workspace', $ws);
        }
        app()->instance('workspace_owner', $this->owner);
    }

    // ── Fixtures ──────────────────────────────────────────────────

    private function restaurant(): array
    {
        $link = Link::create([
            'user_id' => $this->owner->id, 'type' => 'restaurant_menu',
            'alias' => Link::generateAlias(), 'title' => 'Priyumm Tiffins', 'is_active' => true,
        ]);
        $menu = RestaurantMenu::create([
            'link_id' => $link->id, 'user_id' => $this->owner->id,
            'mode' => 'order', 'currency' => 'INR', 'settings' => [],
        ]);
        $cat = RestaurantMenuCategory::create([
            'menu_id' => $menu->id, 'name' => 'Tiffins', 'sort_order' => 0, 'is_active' => true,
        ]);
        $item = RestaurantMenuItem::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => 'Masala Dosa',
            'price' => 120, 'currency' => 'INR', 'sort_order' => 0, 'is_active' => true,
        ]);

        return [$link, $menu, $item];
    }

    private function table(RestaurantMenu $menu, string $label, int $sort = 0): RestaurantTable
    {
        return RestaurantTable::create([
            'menu_id' => $menu->id, 'label' => $label, 'sort_order' => $sort,
        ]);
    }

    /**
     * An order placed $agoMinutes ago.
     *
     * created_at is not fillable, so it is forced -- a "twenty minutes ago"
     * order stamped with now() would make the ageing assertions pass
     * against a board that computes nothing.
     */
    private function order(
        $link, $menu, $item, string $status, int $agoMinutes,
        ?RestaurantTable $table = null, ?string $tableLabel = null, ?string $who = null
    ): RestaurantOrder {
        $order = RestaurantOrder::create([
            'menu_id' => $menu->id, 'link_id' => $link->id,
            'status' => $status, 'customer_name' => $who,
            'table_id' => $table?->id,
            'table_label' => $tableLabel ?? $table?->label,
            'subtotal' => 100, 'total' => 100, 'currency' => 'INR',
        ]);

        $at = now()->subMinutes($agoMinutes);
        $order->forceFill(['created_at' => $at])->saveQuietly();

        RestaurantOrderItem::create([
            'order_id' => $order->id, 'item_id' => $item->id,
            'name' => 'Masala Dosa', 'quantity' => 2, 'unit_price' => 120, 'total_price' => 240,
        ]);

        $this->assertSame(
            $at->toDateString(),
            $order->fresh()->created_at->toDateString(),
            'the backdating did not take — every ageing assertion below would be meaningless'
        );

        return $order;
    }

    private function board(Link $link, $menu, bool $hasTables = true): array
    {
        return KitchenBoard::of($menu, RestaurantOrder::class, $hasTables);
    }

    private function group(array $board, string $name): ?array
    {
        foreach ($board['groups'] as $g) {
            if ($g['name'] === $name) {
                return $g;
            }
        }

        return null;
    }

    // ── The sort, which is the thing to get right ─────────────────

    public function test_a_table_shows_its_oldest_open_ticket_not_its_newest(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $t4 = $this->table($menu, 'Table 4');

        // Twenty-five minutes waiting, and one placed a moment ago.
        $this->order($link, $menu, $item, RestaurantOrder::STATUS_PREPARING, 25, $t4);
        $this->order($link, $menu, $item, RestaurantOrder::STATUS_NEW, 1, $t4);

        $g = $this->group($this->board($link, $menu), 'Table 4');

        $this->assertNotNull($g);
        $this->assertSame(
            RestaurantOrder::STATUS_PREPARING,
            $g['status'],
            'the table is reporting its newest ticket — the late one is hidden behind a fresh chip'
        );
        $this->assertGreaterThanOrEqual(25, $g['minutes'], 'the table is reporting the wrong age');
        $this->assertSame('late', $g['heat']);

        // And the tickets themselves are oldest-first, which is the order
        // the kitchen works in.
        $this->assertSame(
            [25, 1],
            array_map(fn ($t) => $t['minutes'] >= 25 ? 25 : 1, $g['tickets']),
            'the tickets are newest-first — a kitchen reads the other way round'
        );
    }

    public function test_heat_turns_amber_then_red_at_the_stated_minutes(): void
    {
        $this->assertSame('fresh', KitchenBoard::heat(0));
        $this->assertSame('fresh', KitchenBoard::heat(KitchenBoard::WARN_AFTER - 1));
        $this->assertSame('warn',  KitchenBoard::heat(KitchenBoard::WARN_AFTER));
        $this->assertSame('warn',  KitchenBoard::heat(KitchenBoard::LATE_AFTER - 1));
        $this->assertSame('late',  KitchenBoard::heat(KitchenBoard::LATE_AFTER));
    }

    // ── Every table, including the free ones ──────────────────────

    public function test_a_table_with_nothing_on_it_is_still_on_the_board(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $this->table($menu, 'Table 1', 0);
        $t2 = $this->table($menu, 'Table 2', 1);
        $this->table($menu, 'Table 3', 2);

        $this->order($link, $menu, $item, RestaurantOrder::STATUS_NEW, 2, $t2);

        $board = $this->board($link, $menu);
        $names = array_column($board['groups'], 'name');

        $this->assertContains('Table 1', $names, 'a free table is missing — the board cannot say which tables are open');
        $this->assertContains('Table 3', $names);

        $free = $this->group($board, 'Table 1');
        $this->assertTrue($free['empty']);
        $this->assertSame('idle', $free['heat']);
        $this->assertNull($free['minutes']);
        $this->assertSame('Free', $free['label']);

        // In the creator's own order, not whatever the orders happened to
        // arrive in.
        $this->assertSame(['Table 1', 'Table 2', 'Table 3'], array_slice($names, 0, 3));
    }

    public function test_finished_work_leaves_the_board(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $t1 = $this->table($menu, 'Table 1');

        $this->order($link, $menu, $item, RestaurantOrder::STATUS_COMPLETED, 5, $t1);
        $this->order($link, $menu, $item, RestaurantOrder::STATUS_CANCELLED, 6, $t1);

        $g = $this->group($this->board($link, $menu), 'Table 1');

        $this->assertTrue(
            $g['empty'],
            'a completed order is still on the kitchen wall — that is a meal somebody cooks twice'
        );
        $this->assertSame(0, $this->board($link, $menu)['open']);
    }

    // ── Nothing is dropped ────────────────────────────────────────

    public function test_an_order_with_no_table_still_appears(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $this->table($menu, 'Table 1');

        $this->order($link, $menu, $item, RestaurantOrder::STATUS_NEW, 3, null, null, 'Asha');

        $board = $this->board($link, $menu);
        $loose = $this->group($board, 'Takeaway & counter');

        $this->assertNotNull($loose, 'a takeaway ticket vanished — a ticket the kitchen cannot see is a meal nobody cooks');
        $this->assertCount(1, $loose['tickets']);
        $this->assertSame('Asha', $loose['tickets'][0]['customer']);
        $this->assertSame(1, $board['open']);
    }

    public function test_a_label_with_no_table_row_is_named_rather_than_lumped_in(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $this->table($menu, 'Table 1');

        // Typed at the counter, or placed before the table row was deleted.
        $this->order($link, $menu, $item, RestaurantOrder::STATUS_NEW, 4, null, 'Table 9');

        $board = $this->board($link, $menu);

        $this->assertNotNull(
            $this->group($board, 'Table 9'),
            '"Table 9" on a ticket means somebody sat at one — it should not read as takeaway'
        );
        $this->assertNull($this->group($board, 'Takeaway & counter'));
    }

    public function test_an_order_matching_a_table_by_label_lands_on_that_table(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $t = $this->table($menu, 'Table 2');

        // table_id is null, the label matches: still the same table.
        $this->order($link, $menu, $item, RestaurantOrder::STATUS_NEW, 5, null, 'table 2');

        $g = $this->group($this->board($link, $menu), 'Table 2');

        $this->assertCount(1, $g['tickets'], 'a ticket named for this table was filed somewhere else');
        $this->assertNull($this->group($this->board($link, $menu), 'table 2'), 'the same table appears twice');
    }

    // ── "if exists" ───────────────────────────────────────────────

    public function test_a_page_with_no_tables_gets_the_board_grouped_by_order(): void
    {
        [$link, $menu, $item] = $this->restaurant();

        $this->order($link, $menu, $item, RestaurantOrder::STATUS_NEW, 2, null, null, 'Ravi');
        $this->order($link, $menu, $item, RestaurantOrder::STATUS_PREPARING, 9, null, null, 'Meena');

        $board = $this->board($link, $menu, hasTables: false);

        $this->assertSame('orders', $board['mode']);
        $this->assertCount(2, $board['groups'], 'a takeaway page got a blank board instead of its orders');
        // Oldest first here too.
        $this->assertSame('Meena', $board['groups'][0]['name']);
        $this->assertSame('Ravi', $board['groups'][1]['name']);
    }

    public function test_the_store_gets_the_same_screen(): void
    {
        $link = Link::create([
            'user_id' => $this->owner->id, 'type' => 'store_menu',
            'alias' => Link::generateAlias(), 'title' => 'Clay & Co', 'is_active' => true,
        ]);
        $menu = StoreMenu::create([
            'link_id' => $link->id, 'user_id' => $this->owner->id,
            'mode' => 'order', 'currency' => 'INR', 'settings' => [],
        ]);
        StoreOrder::create([
            'menu_id' => $menu->id, 'link_id' => $link->id,
            'status' => StoreOrder::STATUS_NEW, 'customer_name' => 'Nadia',
            'subtotal' => 450, 'total' => 450, 'currency' => 'INR',
        ]);

        $board = KitchenBoard::of($menu, StoreOrder::class, false);

        $this->assertSame('orders', $board['mode']);
        $this->assertSame(1, $board['open']);
        $this->assertSame('Nadia', $board['groups'][0]['name']);

        $this->actingAs($this->owner)
            ->get(route('user.links.store.kitchen', $link))
            ->assertOk()
            ->assertSee('Kitchen');
    }

    // ── What a ticket carries ─────────────────────────────────────

    public function test_a_ticket_carries_the_dishes_and_no_prices(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $t = $this->table($menu, 'Table 1');
        $this->order($link, $menu, $item, RestaurantOrder::STATUS_NEW, 1, $t);

        $ticket = $this->group($this->board($link, $menu), 'Table 1')['tickets'][0];

        $this->assertSame(2, $ticket['lines'][0]['qty']);
        $this->assertSame('Masala Dosa', $ticket['lines'][0]['name']);

        // A kitchen ticket with money on it is a receipt, and reading past
        // the price to find the dish is how an order goes out wrong.
        $flat = json_encode($ticket);
        $this->assertStringNotContainsString('price', $flat, 'a price reached the kitchen ticket');
        $this->assertStringNotContainsString('total', $flat);
    }

    /**
     * The moves on a ticket come from the model's own transition map.
     *
     * A hand-written list here would offer a button the server rejects --
     * which on a kitchen screen reads as the board being broken.
     */
    public function test_a_ticket_offers_only_the_moves_the_order_can_make(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $t = $this->table($menu, 'Table 1');
        $this->order($link, $menu, $item, RestaurantOrder::STATUS_PREPARING, 1, $t);

        $ticket = $this->group($this->board($link, $menu), 'Table 1')['tickets'][0];

        $this->assertSame(
            RestaurantOrder::STATUS_TRANSITIONS[RestaurantOrder::STATUS_PREPARING],
            $ticket['next'],
            'the moves on a ticket have drifted from the ones the server will accept'
        );
    }

    public function test_a_runaway_service_is_capped(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $t = $this->table($menu, 'Table 1');

        for ($i = 0; $i < 5; $i++) {
            $this->order($link, $menu, $item, RestaurantOrder::STATUS_NEW, $i, $t);
        }

        $this->assertLessThanOrEqual(KitchenBoard::MAX_TICKETS, $this->board($link, $menu)['open']);
    }

    // ── The screen and the refresh ────────────────────────────────

    public function test_the_screen_renders_with_the_tables_on_it(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $t = $this->table($menu, 'Table 4');
        $this->order($link, $menu, $item, RestaurantOrder::STATUS_NEW, 2, $t);

        $html = $this->actingAs($this->owner)
            ->get(route('user.links.restaurant.kitchen', $link))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Table 4', $html);
        $this->assertStringContainsString('Masala Dosa', $html);
    }

    /**
     * "aurto refresh also" -- and the three things that make it survivable
     * on a screen left running all service.
     */
    public function test_the_board_refreshes_itself_without_reloading_the_page(): void
    {
        [$link, $menu] = $this->restaurant();

        $html = $this->actingAs($this->owner)
            ->get(route('user.links.restaurant.kitchen', $link))
            ->assertOk()->getContent();

        $poll = str_replace('\\/', '/', $html);

        // It polls its own JSON rather than reloading: a full reload on a
        // wall screen loses scroll position and flashes white every ten
        // seconds, which is how a kitchen turns the thing off.
        $this->assertStringContainsString(
            route('user.links.restaurant.kitchen.poll', $link),
            $poll,
            'the board has no poll endpoint — it is not refreshing at all'
        );
        $this->assertMatchesRegularExpression('/setInterval\(/', $html);

        // One request in flight at a time -- asserted as the GUARD, not as
        // the word. The first version of this checked that "inFlight"
        // appeared anywhere in the page, and deleting the guard left the
        // state declaration behind, so the sabotage passed. On a busy
        // Saturday a slow response must not queue a second and then a
        // third: a board that falls behind by stacking requests is worse
        // than one that skips a beat.
        $this->assertMatchesRegularExpression(
            '/if\s*\([^)]*this\.inFlight[^)]*\)\s*return/',
            $html,
            'tick() does not bail when a request is already in flight — requests can stack'
        );
        $this->assertMatchesRegularExpression(
            '/this\.inFlight\s*=\s*true/',
            $html,
            'nothing ever sets the in-flight flag, so the guard can never fire'
        );

        // And it says when it last managed to refresh. A board that quietly
        // stops updating is worse than one that is obviously broken.
        $this->assertStringContainsString('stale', $html);
        $this->assertStringContainsString('Could not refresh', $html);
    }

    public function test_the_poll_endpoint_returns_the_whole_board(): void
    {
        [$link, $menu, $item] = $this->restaurant();
        $t = $this->table($menu, 'Table 1');
        $order = $this->order($link, $menu, $item, RestaurantOrder::STATUS_NEW, 2, $t);

        $body = $this->actingAs($this->owner)
            ->getJson(route('user.links.restaurant.kitchen.poll', $link))
            ->assertOk()->json('data');

        $this->assertSame(1, $body['open']);
        $this->assertSame('Table 1', $body['groups'][0]['name']);

        // The whole board, not a delta: a delta cannot express a REMOVAL,
        // so an order completed on somebody's phone would sit on the wall
        // forever.
        $order->update(['status' => RestaurantOrder::STATUS_COMPLETED]);

        $after = $this->actingAs($this->owner)
            ->getJson(route('user.links.restaurant.kitchen.poll', $link))
            ->assertOk()->json('data');

        $this->assertSame(0, $after['open'], 'a finished order is still being sent to the board');
        $this->assertTrue($after['groups'][0]['empty']);
    }

    // ── Reachable, and only by its owner ──────────────────────────

    public function test_both_order_boards_link_to_it(): void
    {
        [$link] = $this->restaurant();

        $html = $this->actingAs($this->owner)
            ->get(route('user.links.restaurant.orders', $link))
            ->assertOk()->getContent();

        $this->assertStringContainsString(
            route('user.links.restaurant.kitchen', $link),
            str_replace('&amp;', '&', $html),
            'the kitchen board exists and no screen offers it'
        );
    }

    public function test_somebody_elses_menu_is_not_readable(): void
    {
        [$link] = $this->restaurant();

        $other = User::create([
            'name' => 'Other', 'email' => 'oth'.Str::random(8).'@ex.com',
            'password' => Hash::make('x'), 'status' => 'active',
        ]);

        app()->instance('workspace_owner', $other);

        $this->actingAs($other)
            ->get(route('user.links.restaurant.kitchen', $link))
            ->assertForbidden();
    }

    public function test_the_wrong_page_type_is_not_a_kitchen(): void
    {
        $link = Link::create([
            'user_id' => $this->owner->id, 'type' => 'biolink',
            'alias' => Link::generateAlias(), 'title' => 'Just a page', 'is_active' => true,
        ]);

        $this->actingAs($this->owner)
            ->get(route('user.links.restaurant.kitchen', $link))
            ->assertNotFound();
    }
}
