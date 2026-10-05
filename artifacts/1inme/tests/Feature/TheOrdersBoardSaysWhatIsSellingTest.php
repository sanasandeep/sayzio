<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuCategory;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\RestaurantOrder;
use App\Modules\User\Models\RestaurantOrderItem;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\MenuInsights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sana, 2026-10-05: "dashborad: i need top items, item sales, reccuring
 * things, highlights or anything related....".
 *
 * The summary tiles say how much came in. They cannot say what to do. What
 * this file guards, hardest first:
 *
 *   - CANCELLED ORDERS ARE OUT OF EVERY NUMBER. A dish ordered twice and
 *     cancelled twice is not a best seller, and promoting it because this
 *     screen said so would be this screen's fault;
 *   - "most ordered" and "earns the most" are DIFFERENT LISTS. A 30-rupee
 *     tea outsells everything and earns less than the dish nobody
 *     reorders. One list hides whichever answer the owner needed;
 *   - a regular is matched on PHONE, falling back to a name. Two customers
 *     called "Raj" are two people; an owner told they have forty regulars
 *     when they have four stops believing the screen;
 *   - what has NEVER sold, which is the column nobody builds and the only
 *     one that shortens a menu.
 */
class TheOrdersBoardSaysWhatIsSellingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'name' => 'Owner '.Str::random(4), 'email' => 'own'.Str::random(8).'@ex.com',
            'password' => Hash::make('x'), 'status' => 'active',
        ]);

        $ws = app(WorkspaceContext::class)->resolve($this->owner);
        if ($ws !== null) {
            app()->instance('current_workspace', $ws);
        }
        app()->instance('workspace_owner', $this->owner);
    }

    /** @return array{0: Link, 1: RestaurantMenu, 2: RestaurantMenuCategory} */
    private function menu(): array
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

        return [$link, $menu, $cat];
    }

    private function dish($menu, $cat, string $name, float $price): RestaurantMenuItem
    {
        return RestaurantMenuItem::create([
            'menu_id' => $menu->id, 'category_id' => $cat->id, 'name' => $name,
            'price' => $price, 'currency' => 'INR', 'sort_order' => 0, 'is_active' => true,
        ]);
    }

    /** @param array<int, array{0: RestaurantMenuItem, 1: int}> $lines */
    private function order($link, $menu, string $status, array $lines, ?string $phone = null, ?string $who = null): RestaurantOrder
    {
        $total = 0.0;
        foreach ($lines as [$item, $qty]) {
            $total += $item->price * $qty;
        }

        $order = RestaurantOrder::create([
            'menu_id' => $menu->id, 'link_id' => $link->id, 'status' => $status,
            'customer_name' => $who, 'customer_phone' => $phone,
            'subtotal' => $total, 'total' => $total, 'currency' => 'INR',
        ]);

        foreach ($lines as [$item, $qty]) {
            RestaurantOrderItem::create([
                'order_id' => $order->id, 'item_id' => $item->id, 'name' => $item->name,
                'quantity' => $qty, 'unit_price' => $item->price,
                'line_total' => $item->price * $qty,
            ]);
        }

        return $order;
    }

    private function insights($link, $menu): array
    {
        return MenuInsights::of(
            RestaurantOrder::where('menu_id', $menu->id),
            RestaurantOrder::class,
            RestaurantOrderItem::class,
            $menu,
            RestaurantMenuItem::class,
        );
    }

    // ── The one that would be worst to get wrong ──────────────────

    public function test_a_cancelled_order_is_not_a_sale(): void
    {
        [$link, $menu, $cat] = $this->menu();
        $dosa = $this->dish($menu, $cat, 'Masala Dosa', 120);
        $idli = $this->dish($menu, $cat, 'Idli', 40);

        // Idli: ordered and cancelled, twice. Dosa: sold once.
        $this->order($link, $menu, RestaurantOrder::STATUS_CANCELLED, [[$idli, 10]]);
        $this->order($link, $menu, RestaurantOrder::STATUS_CANCELLED, [[$idli, 10]]);
        $this->order($link, $menu, RestaurantOrder::STATUS_COMPLETED, [[$dosa, 1]]);

        $data = $this->insights($link, $menu);

        $this->assertSame(
            'Masala Dosa',
            $data['top_qty'][0]['name'],
            'a cancelled order made a dish the best seller — this screen would be telling the owner to promote it'
        );
        $this->assertSame(1, $data['sold'], 'cancelled quantities are being counted as sold');
        $this->assertNotContains('Idli', array_column($data['top_qty'], 'name'));

        // And an item that only ever appeared on a cancelled order has still
        // never sold.
        $this->assertContains('Idli', $data['never']);
    }

    // ── Two questions, two lists ──────────────────────────────────

    public function test_most_ordered_and_earns_the_most_are_different_lists(): void
    {
        [$link, $menu, $cat] = $this->menu();
        $tea    = $this->dish($menu, $cat, 'Filter Coffee', 30);
        $thali  = $this->dish($menu, $cat, 'Full Thali', 250);

        // Fifty teas at 30 is 1500; ten thalis at 250 is 2500. Tea wins on
        // count, thali wins on money -- which is the whole point, and the
        // first version of this test got the arithmetic wrong (four thalis
        // is 1000, less than the tea) and so asserted a case that was not
        // real. The test caught it, which is what it is for.
        $this->order($link, $menu, RestaurantOrder::STATUS_COMPLETED, [[$tea, 50]]);
        $this->order($link, $menu, RestaurantOrder::STATUS_COMPLETED, [[$thali, 10]]);

        $data = $this->insights($link, $menu);

        $this->assertSame('Filter Coffee', $data['top_qty'][0]['name']);
        $this->assertSame(
            'Full Thali',
            $data['top_money'][0]['name'],
            'the money list is just the count list again — the owner cannot see what actually earns'
        );
        $this->assertEqualsWithDelta(2500.0, $data['top_money'][0]['revenue'], 0.01);
        // And the tea is still on the money list, just not at the top.
        $this->assertSame('Filter Coffee', $data['top_money'][1]['name']);
    }

    public function test_the_same_dish_across_orders_is_one_row(): void
    {
        [$link, $menu, $cat] = $this->menu();
        $dosa = $this->dish($menu, $cat, 'Masala Dosa', 120);

        $this->order($link, $menu, RestaurantOrder::STATUS_COMPLETED, [[$dosa, 2]]);
        $this->order($link, $menu, RestaurantOrder::STATUS_READY, [[$dosa, 3]]);

        $data = $this->insights($link, $menu);

        $this->assertCount(1, $data['top_qty'], 'one dish is listed twice');
        $this->assertSame(5, $data['top_qty'][0]['qty']);
        $this->assertSame(2, $data['top_qty'][0]['orders']);
    }

    // ── Who comes back ────────────────────────────────────────────

    public function test_a_regular_is_somebody_who_ordered_more_than_once(): void
    {
        [$link, $menu, $cat] = $this->menu();
        $dosa = $this->dish($menu, $cat, 'Masala Dosa', 120);

        $this->order($link, $menu, RestaurantOrder::STATUS_COMPLETED, [[$dosa, 1]], '+919000000001', 'Asha');
        $this->order($link, $menu, RestaurantOrder::STATUS_COMPLETED, [[$dosa, 2]], '+919000000001', 'Asha');
        $this->order($link, $menu, RestaurantOrder::STATUS_COMPLETED, [[$dosa, 1]], '+919000000002', 'Ravi');

        $data = $this->insights($link, $menu);

        $this->assertSame(1, $data['regulars']['count'], 'a one-time customer is being counted as a regular');
        $this->assertSame('Asha', $data['regulars']['rows'][0]['name']);
        $this->assertSame(2, $data['regulars']['rows'][0]['orders']);
        $this->assertEqualsWithDelta(360.0, $data['regulars']['rows'][0]['spent'], 0.01);
    }

    /**
     * Two people with the same name are two people.
     *
     * Matching on name alone invents regulars out of common names, and an
     * owner told they have forty when they have four stops believing the
     * whole screen.
     */
    public function test_two_customers_sharing_a_name_are_not_one_regular(): void
    {
        [$link, $menu, $cat] = $this->menu();
        $dosa = $this->dish($menu, $cat, 'Masala Dosa', 120);

        $this->order($link, $menu, RestaurantOrder::STATUS_COMPLETED, [[$dosa, 1]], '+919000000001', 'Raj');
        $this->order($link, $menu, RestaurantOrder::STATUS_COMPLETED, [[$dosa, 1]], '+919000000002', 'Raj');

        $this->assertSame(
            0,
            $this->insights($link, $menu)['regulars']['count'],
            'two different phone numbers were merged into one regular because the names matched'
        );
    }

    public function test_an_anonymous_counter_sale_is_not_evidence_of_anybody_returning(): void
    {
        [$link, $menu, $cat] = $this->menu();
        $dosa = $this->dish($menu, $cat, 'Masala Dosa', 120);

        $this->order($link, $menu, RestaurantOrder::STATUS_COMPLETED, [[$dosa, 1]]);
        $this->order($link, $menu, RestaurantOrder::STATUS_COMPLETED, [[$dosa, 1]]);
        $this->order($link, $menu, RestaurantOrder::STATUS_COMPLETED, [[$dosa, 1]]);

        $this->assertSame(
            0,
            $this->insights($link, $menu)['regulars']['count'],
            'three walk-ins with no name became one very loyal customer'
        );
    }

    // ── The column nobody builds ──────────────────────────────────

    public function test_what_is_on_the_menu_and_has_never_sold(): void
    {
        [$link, $menu, $cat] = $this->menu();
        $dosa = $this->dish($menu, $cat, 'Masala Dosa', 120);
        $this->dish($menu, $cat, 'Rava Kesari', 60);

        $hidden = $this->dish($menu, $cat, 'Seasonal Special', 200);
        $hidden->update(['is_active' => false]);

        $this->order($link, $menu, RestaurantOrder::STATUS_COMPLETED, [[$dosa, 1]]);

        $never = $this->insights($link, $menu)['never'];

        $this->assertContains('Rava Kesari', $never);
        $this->assertNotContains('Masala Dosa', $never);
        // A hidden dish has an explanation already; calling it dead weight
        // would be wrong.
        $this->assertNotContains('Seasonal Special', $never, 'a hidden item was listed as never ordered');
    }

    // ── Highlights ────────────────────────────────────────────────

    public function test_the_busiest_hour_reads_as_a_time_of_day(): void
    {
        // Nobody staffs a kitchen by the 24-hour clock.
        $this->assertSame('2–3pm', MenuInsights::hourLabel(14));
        $this->assertSame('9–10am', MenuInsights::hourLabel(9));
        // Straddling noon and midnight, where dropping the first suffix
        // would read as the wrong hour entirely.
        $this->assertSame('11am–12pm', MenuInsights::hourLabel(11));
        $this->assertSame('11pm–12am', MenuInsights::hourLabel(23));
        $this->assertSame('12–1am', MenuInsights::hourLabel(0));
    }

    public function test_the_highlights_count_what_sold_not_how_many_orders(): void
    {
        [$link, $menu, $cat] = $this->menu();
        $dosa = $this->dish($menu, $cat, 'Masala Dosa', 120);
        $idli = $this->dish($menu, $cat, 'Idli', 40);

        $this->order($link, $menu, RestaurantOrder::STATUS_COMPLETED, [[$dosa, 3], [$idli, 2]]);

        $data = $this->insights($link, $menu);

        $this->assertSame(5, $data['sold'], 'the highlight is counting orders rather than things sold');
        $this->assertSame(2, $data['distinct']);
    }

    // ── On the screen ─────────────────────────────────────────────

    public function test_the_orders_board_shows_it(): void
    {
        [$link, $menu, $cat] = $this->menu();
        $dosa = $this->dish($menu, $cat, 'Masala Dosa', 120);
        $this->dish($menu, $cat, 'Rava Kesari', 60);
        $this->order($link, $menu, RestaurantOrder::STATUS_COMPLETED, [[$dosa, 3]], '+919000000001', 'Asha');
        $this->order($link, $menu, RestaurantOrder::STATUS_COMPLETED, [[$dosa, 1]], '+919000000001', 'Asha');

        $html = $this->actingAs($this->owner)
            ->get(route('user.links.restaurant.orders', $link).'?range=all')
            ->assertOk()->getContent();

        $this->assertStringContainsString('What is selling', $html);
        $this->assertStringContainsString('Most ordered', $html);
        $this->assertStringContainsString('Earns the most', $html);
        $this->assertStringContainsString('Coming back', $html);
        $this->assertStringContainsString('Never ordered', $html);

        // And real content in them, not just the headings.
        $this->assertStringContainsString('Masala Dosa', $html);
        $this->assertStringContainsString('Rava Kesari', $html);
        $this->assertStringContainsString('Asha', $html);
    }

    public function test_an_empty_range_says_so_rather_than_showing_blank_columns(): void
    {
        [$link, $menu, $cat] = $this->menu();
        $this->dish($menu, $cat, 'Masala Dosa', 120);

        $html = $this->actingAs($this->owner)
            ->get(route('user.links.restaurant.orders', $link))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Nothing has sold in this range yet', $html);
    }
}
