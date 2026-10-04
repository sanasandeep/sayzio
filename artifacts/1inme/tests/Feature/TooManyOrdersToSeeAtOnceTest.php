<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantOrder;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreOrder;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\MenuOrderRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The kitchen screen when there are more orders than fit.
 *
 * Sana, 2026-09-28: "wht is too many order? selected with dates?"
 *
 * ---- What it did before ------------------------------------------------
 *
 * `latest()->limit(100)`, and nothing else. Two failures, both silent:
 * on a busy Saturday the hundred-and-first order did not exist, and
 * yesterday could not be looked at at all. The screen showed a hundred
 * orders and said nothing about the rest.
 *
 * ---- The two that would actually hurt ---------------------------------
 *
 * 1. The open count must NOT be scoped to the window. An order left open
 *    from yesterday is still open, and hiding it because the screen is
 *    showing today is exactly how one gets forgotten.
 * 2. While somebody is looking at last Tuesday, the screen must say it is
 *    not live. A pulsing "Live" dot is a promise that new orders will
 *    appear, and a screen that has quietly stopped receiving them looks
 *    identical to a quiet evening.
 */
class TooManyOrdersToSeeAtOnceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create([
            'onboarded_at' => now(), 'timezone' => 'Asia/Kolkata',
        ]);
        app(WorkspaceContext::class)->resolve($this->owner);
    }

    /** @return array{0: Link, 1: RestaurantMenu} */
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

        return [$link, $menu];
    }

    /** @return array{0: Link, 1: StoreMenu} */
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

        return [$link, $menu];
    }

    private function order($menu, Carbon $at, string $status = 'new'): RestaurantOrder
    {
        $o = RestaurantOrder::create([
            'menu_id' => $menu->id, 'link_id' => $menu->link_id, 'status' => $status,
            'subtotal' => 100, 'total' => 100, 'currency' => 'INR',
        ]);
        // created_at is the window's axis, and the model sets it to now.
        // Converted explicitly: forceFill bypasses the cast, so an IST
        // Carbon would otherwise be stored as that wall clock in UTC.
        $utc = $at->copy()->utc();
        $o->forceFill(['created_at' => $utc, 'updated_at' => $utc])->save();

        return $o->fresh();
    }

    private function board(Link $link, string $kind, array $query = []): array
    {
        $url = '/user/links/'.$link->id.'/'.$kind.'/orders?'.http_build_query($query + ['format' => 'json']);

        return $this->actingAs($this->owner)->getJson($url)->assertOk()->json('data');
    }

    // ===== The window ====================================================

    public function test_todays_orders_are_what_the_screen_opens_on(): void
    {
        [$link, $menu] = $this->restaurant();
        $tz = 'Asia/Kolkata';

        $this->order($menu, Carbon::now($tz)->startOfDay()->addHours(11));
        $this->order($menu, Carbon::now($tz)->subDay()->setHour(13));
        $this->order($menu, Carbon::now($tz)->subDays(9));

        $data = $this->board($link, 'restaurant');

        $this->assertSame('today', $data['range']['key']);
        $this->assertSame(1, $data['range']['total'], 'The screen is not opening on today.');
    }

    public function test_each_preset_asks_for_what_it_says(): void
    {
        [$link, $menu] = $this->restaurant();
        $tz = 'Asia/Kolkata';

        $this->order($menu, Carbon::now($tz)->startOfDay()->addHours(11));   // today
        $this->order($menu, Carbon::now($tz)->subDay()->setHour(13));        // yesterday
        $this->order($menu, Carbon::now($tz)->subDays(4)->setHour(13));      // this week
        $this->order($menu, Carbon::now($tz)->subDays(20)->setHour(13));     // this month
        $this->order($menu, Carbon::now($tz)->subDays(200)->setHour(13));    // long ago

        $this->assertSame(1, $this->board($link, 'restaurant', ['range' => 'today'])['range']['total']);
        $this->assertSame(1, $this->board($link, 'restaurant', ['range' => 'yesterday'])['range']['total']);
        $this->assertSame(3, $this->board($link, 'restaurant', ['range' => '7d'])['range']['total']);
        $this->assertSame(4, $this->board($link, 'restaurant', ['range' => '30d'])['range']['total']);
        $this->assertSame(5, $this->board($link, 'restaurant', ['range' => 'all'])['range']['total']);
    }

    public function test_today_means_the_owners_today(): void
    {
        // 20:00 UTC is 01:30 the next day in Chennai. A kitchen there
        // asking for today at that moment must not be shown a window that
        // closed four and a half hours ago.
        $at = Carbon::parse('2026-10-04 20:00:00', 'UTC');

        $ist = MenuOrderRange::resolve('today', null, null, 'Asia/Kolkata');
        $utc = MenuOrderRange::resolve('today', null, null, 'UTC');

        Carbon::setTestNow($at);
        $ist = MenuOrderRange::resolve('today', null, null, 'Asia/Kolkata');
        $utc = MenuOrderRange::resolve('today', null, null, 'UTC');
        Carbon::setTestNow();

        $this->assertSame('2026-10-05', $ist['from_date']);
        $this->assertSame('2026-10-04', $utc['from_date']);
    }

    public function test_a_custom_range_includes_both_ends(): void
    {
        // Somebody asking for the 3rd to the 5th means all of the 5th, not
        // up to midnight at its start.
        [$link, $menu] = $this->restaurant();
        $tz = 'Asia/Kolkata';

        $this->order($menu, Carbon::parse('2026-09-03 10:00', $tz));
        $this->order($menu, Carbon::parse('2026-09-05 23:30', $tz));
        $this->order($menu, Carbon::parse('2026-09-06 00:30', $tz));

        $data = $this->board($link, 'restaurant', [
            'range' => 'custom', 'from' => '2026-09-03', 'to' => '2026-09-05',
        ]);

        $this->assertSame(2, $data['range']['total']);
    }

    public function test_a_backwards_custom_range_is_a_typo_not_a_request_for_nothing(): void
    {
        [$link, $menu] = $this->restaurant();
        $this->order($menu, Carbon::parse('2026-09-04 10:00', 'Asia/Kolkata'));

        $data = $this->board($link, 'restaurant', [
            'range' => 'custom', 'from' => '2026-09-05', 'to' => '2026-09-03',
        ]);

        $this->assertSame(1, $data['range']['total']);
    }

    public function test_one_end_of_a_custom_range_is_a_fair_question(): void
    {
        [$link, $menu] = $this->restaurant();
        $this->order($menu, Carbon::parse('2026-09-01 10:00', 'Asia/Kolkata'));
        $this->order($menu, Carbon::parse('2026-09-20 10:00', 'Asia/Kolkata'));

        $since = $this->board($link, 'restaurant', ['range' => 'custom', 'from' => '2026-09-10']);
        $until = $this->board($link, 'restaurant', ['range' => 'custom', 'to' => '2026-09-10']);

        $this->assertSame(1, $since['range']['total']);
        $this->assertSame(1, $until['range']['total']);
    }

    public function test_nonsense_falls_back_rather_than_showing_an_empty_screen(): void
    {
        [$link, $menu] = $this->restaurant();
        $this->order($menu, Carbon::now('Asia/Kolkata')->startOfDay()->addHours(11));

        foreach ([
            ['range' => 'fortnight'],
            ['range' => 'custom', 'from' => 'yesterday', 'to' => 'soon'],
            ['range' => 'custom'],
        ] as $query) {
            $data = $this->board($link, 'restaurant', $query);
            $this->assertSame('today', $data['range']['key'], json_encode($query));
            $this->assertSame(1, $data['range']['total']);
        }
    }

    // ===== Paging ========================================================

    public function test_the_hundred_and_first_order_exists_now(): void
    {
        [$link, $menu] = $this->restaurant();
        $today = Carbon::now('Asia/Kolkata')->startOfDay()->addHours(11);
        for ($i = 0; $i < 120; $i++) {
            $this->order($menu, $today->copy()->addMinutes($i));
        }

        $first = $this->board($link, 'restaurant');

        $this->assertSame(120, $first['range']['total'], 'The screen still cannot see past a cap.');
        $this->assertCount(MenuOrderRange::PER_PAGE, $first['orders']);
        $this->assertTrue($first['more']);

        $second = $this->board($link, 'restaurant', ['page' => 2]);
        $this->assertCount(MenuOrderRange::PER_PAGE, $second['orders']);
        $this->assertTrue($second['more']);

        $third = $this->board($link, 'restaurant', ['page' => 3]);
        $this->assertCount(20, $third['orders']);
        $this->assertFalse($third['more'], 'The screen thinks there is more after the last page.');
    }

    public function test_pages_do_not_overlap_or_skip(): void
    {
        [$link, $menu] = $this->restaurant();
        $today = Carbon::now('Asia/Kolkata')->startOfDay()->addHours(11);
        for ($i = 0; $i < 75; $i++) {
            $this->order($menu, $today->copy()->addMinutes($i));
        }

        $ids = array_merge(
            array_column($this->board($link, 'restaurant', ['page' => 1])['orders'], 'id'),
            array_column($this->board($link, 'restaurant', ['page' => 2])['orders'], 'id'),
        );

        $this->assertCount(75, $ids);
        $this->assertCount(75, array_unique($ids), 'An order appears on two pages.');
        // Newest first, and stable: paging by id rather than by a
        // timestamp two orders can share.
        $this->assertSame($ids, array_reverse(array_sort_desc_helper($ids)));
    }

    // ===== The two that would hurt ======================================

    public function test_an_open_order_from_yesterday_is_still_counted(): void
    {
        // Scoping the open count to the window is how an order left open
        // overnight gets forgotten.
        [$link, $menu] = $this->restaurant();
        $tz = 'Asia/Kolkata';
        $this->order($menu, Carbon::now($tz)->subDay()->setHour(22), 'preparing');
        $this->order($menu, Carbon::now($tz)->startOfDay()->addHours(11), 'new');

        $html = $this->actingAs($this->owner)
            ->get('/user/links/'.$link->id.'/restaurant/orders')->assertOk()->getContent();

        $this->assertStringContainsString('openCount: 2', $html,
            'An order still open from yesterday has vanished from the count.');
    }

    public function test_a_past_range_says_it_is_not_live(): void
    {
        [$link, ] = $this->restaurant();

        $today = $this->board($link, 'restaurant', ['range' => 'today']);
        $yesterday = $this->board($link, 'restaurant', ['range' => 'yesterday']);
        $old = $this->board($link, 'restaurant', [
            'range' => 'custom', 'from' => '2026-01-01', 'to' => '2026-01-07',
        ]);

        $this->assertTrue($today['range']['is_live']);
        $this->assertFalse($yesterday['range']['is_live'], 'Yesterday claims new orders will appear in it.');
        $this->assertFalse($old['range']['is_live']);
    }

    public function test_the_screen_says_so_out_loud(): void
    {
        [$link, ] = $this->restaurant();

        $html = $this->actingAs($this->owner)
            ->get('/user/links/'.$link->id.'/restaurant/orders?range=yesterday')->assertOk()->getContent();

        $this->assertStringContainsString('Not live.', $html);
        $this->assertStringContainsString('New orders will not appear', $html);
        // And the pulsing dot is gone, because it is a promise.
        $this->assertStringContainsString('x-if="meta.is_live"', $html);
    }

    public function test_a_new_order_is_not_dragged_into_a_view_of_last_tuesday(): void
    {
        // The poll returns everything updated since a cursor. Merging all
        // of it would put tonight's orders into a screen showing a past
        // week -- but a STATUS CHANGE on an order already on screen still
        // has to land.
        foreach (['restaurant', 'store'] as $kind) {
            $src = file_get_contents(resource_path('views/user/links/'.$kind.'/orders.blade.php'));

            $this->assertStringContainsString('if (this.known(o.id) || this.fits(o)) { this.merge(o); }', $src,
                "The {$kind} board merges anything the poll returns.");
            $this->assertStringContainsString('known(id){', $src);
            $this->assertStringContainsString('fits(o){', $src);
        }
    }

    public function test_the_copy_is_written_not_assembled(): void
    {
        // "No orders in yesterday." is what gluing "in " onto a lowercased
        // label produces, and it is what this said the first time it was
        // rendered. Each window takes a different preposition or none.
        $range = fn (string $key) => MenuOrderRange::resolve($key, null, null, 'Asia/Kolkata');

        $this->assertSame('No orders yesterday.', MenuOrderRange::emptyPhrase($range('yesterday'), 'orders'));
        $this->assertSame('No orders in the last 7 days.', MenuOrderRange::emptyPhrase($range('7d'), 'orders'));
        $this->assertSame('No orders yet.', MenuOrderRange::emptyPhrase($range('all'), 'orders'));
        $this->assertStringStartsWith('No orders today yet.', MenuOrderRange::emptyPhrase($range('today'), 'orders'));

        foreach (MenuOrderRange::keys() as $key) {
            $phrase = MenuOrderRange::emptyPhrase($range($key), 'orders');
            $this->assertStringNotContainsString('in today', $phrase);
            $this->assertStringNotContainsString('in yesterday', $phrase);
            $this->assertStringNotContainsString('in all time', $phrase);
        }

        // And the store says its own noun rather than the restaurant's.
        [$link, ] = $this->store();
        $html = $this->actingAs($this->owner)
            ->get('/user/links/'.$link->id.'/store/orders')->assertOk()->getContent();
        $this->assertStringContainsString('No order requests today yet.', $html);
    }

    // ===== Both screens =================================================

    public function test_the_store_screen_got_all_of_it_too(): void
    {
        // These two drift constantly: the store has been a week behind the
        // restaurant on nearly every feature this month.
        [$link, $menu] = $this->store();
        $tz = 'Asia/Kolkata';

        foreach ([0, 0, 1] as $daysAgo) {
            $o = StoreOrder::create([
                'menu_id' => $menu->id, 'link_id' => $menu->link_id, 'status' => 'new',
                'subtotal' => 50, 'total' => 50, 'currency' => 'INR',
            ]);
            $at = $daysAgo
                ? Carbon::now($tz)->subDay()->setHour(13)
                : Carbon::now($tz)->startOfDay()->addHours(11);
            $utc = $at->copy()->utc();
            $o->forceFill(['created_at' => $utc, 'updated_at' => $utc])->save();
        }

        $today = $this->board($link, 'store', ['range' => 'today']);
        $this->assertSame(2, $today['range']['total']);
        $this->assertSame(1, $this->board($link, 'store', ['range' => 'yesterday'])['range']['total']);

        $html = $this->actingAs($this->owner)
            ->get('/user/links/'.$link->id.'/store/orders')->assertOk()->getContent();
        foreach (MenuOrderRange::LABELS as $label) {
            $this->assertStringContainsString($label, $html, "The store screen has no '{$label}'.");
        }
        $this->assertStringContainsString('loadMore()', $html);
    }

    public function test_someone_elses_orders_are_not_reachable_by_asking_nicely(): void
    {
        $other = User::factory()->create(['onboarded_at' => now()]);
        [$link, $menu] = $this->restaurant();
        $this->order($menu, Carbon::now('Asia/Kolkata'));

        // 403 or 404 -- both refuse, and 404 leaks less, so pinning one
        // would make a safe change to the other look like a regression.
        $status = $this->actingAs($other)
            ->get('/user/links/'.$link->id.'/restaurant/orders?range=all')
            ->getStatusCode();

        $this->assertContains($status, [403, 404],
            "Another workspace's orders came back with a {$status}.");
    }

    // ===== The empty state stopped lying ================================

    public function test_the_empty_state_does_not_promise_orders_that_cannot_arrive(): void
    {
        foreach (['restaurant', 'store'] as $kind) {
            $src = file_get_contents(resource_path('views/user/links/'.$kind.'/orders.blade.php'));

            $this->assertStringContainsString('emptyMessage()', $src);
            // The old copy was unconditional. On a past range it is untrue.
            $this->assertStringNotContainsString('New orders appear here automatically.</div>', $src);
            $this->assertStringNotContainsString('New requests appear here automatically.</div>', $src);
        }
    }
}

/** Sorted descending, as a plain helper so the assertion above reads. */
function array_sort_desc_helper(array $ids): array
{
    sort($ids);

    return $ids;
}
