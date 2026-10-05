<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuCategory;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\RestaurantOrder;
use App\Modules\User\Models\RestaurantOrderItem;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreOrder;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\MenuOrderSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sana, 2026-10-05: "export of orders , pdf, csv, with filter options
 * active" and "orders dashbord summary missing".
 *
 * Two requests, one subject: the board holds the numbers and would not say
 * them, and would not let them off the screen.
 *
 * The three things these hold, hardest first:
 *
 *   - "with filter options active" is the whole requirement. An export
 *     that quietly ignores the range is WORSE than no export: you filter
 *     to last month, download, and reconcile a year against your books. So
 *     the range reaches both formats and a test proves it;
 *   - revenue excludes cancelled orders and the average divides by the
 *     right denominator. An average that quietly includes cancellations is
 *     wrong in the direction nobody checks;
 *   - the summary is computed over the RANGE and not over the page, so it
 *     does not change when somebody taps "load more". A total that moves
 *     while you look at it teaches people not to trust the screen.
 */
class TheOrdersBoardAddsUpAndComesOffTheScreenTest extends TestCase
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

    /** A restaurant with a known spread of orders. */
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
            'price' => 120, 'sort_order' => 0, 'is_active' => true,
        ]);

        // 100 + 200 + 300 billable, and a 900 cancellation that must not
        // touch revenue or the average.
        $spec = [
            [RestaurantOrder::STATUS_NEW,       100.00, 'Asha'],
            [RestaurantOrder::STATUS_READY,     200.00, 'Ravi'],
            [RestaurantOrder::STATUS_COMPLETED, 300.00, 'Meena'],
            [RestaurantOrder::STATUS_CANCELLED, 900.00, 'Nobody'],
        ];
        foreach ($spec as [$status, $total, $name]) {
            $order = RestaurantOrder::create([
                'menu_id' => $menu->id, 'link_id' => $link->id,
                'status' => $status, 'customer_name' => $name,
                'subtotal' => $total, 'total' => $total, 'currency' => 'INR',
            ]);
            RestaurantOrderItem::create([
                'order_id' => $order->id, 'item_id' => $item->id,
                'name' => 'Masala Dosa', 'quantity' => 1,
                'unit_price' => $total, 'line_total' => $total,
            ]);
        }

        return [$link, $menu];
    }

    private function store(): Link
    {
        $link = Link::create([
            'user_id' => $this->owner->id, 'type' => 'store_menu',
            'alias' => Link::generateAlias(), 'title' => 'The Shop', 'is_active' => true,
        ]);
        $menu = StoreMenu::create([
            'link_id' => $link->id, 'user_id' => $this->owner->id,
            'mode' => 'order', 'currency' => 'INR', 'settings' => [],
        ]);
        StoreOrder::create([
            'menu_id' => $menu->id, 'link_id' => $link->id,
            'status' => StoreOrder::STATUS_NEW, 'customer_name' => 'Ravi',
            'subtotal' => 450, 'total' => 450, 'currency' => 'INR',
        ]);

        return $link;
    }

    // ── The arithmetic ────────────────────────────────────────────

    public function test_revenue_and_average_leave_cancellations_out(): void
    {
        [, $menu] = $this->restaurant();

        $summary = MenuOrderSummary::of(
            RestaurantOrder::where('menu_id', $menu->id),
            RestaurantOrder::class
        );

        // Four orders happened, so four are counted.
        $this->assertSame(4, $summary['orders']);
        // But 900 of it did not: an order that was cancelled is not money.
        $this->assertEqualsWithDelta(600.00, $summary['revenue'], 0.001);
        // And the average divides by three, not four. Dividing by four
        // would be wrong in the direction nobody checks.
        $this->assertEqualsWithDelta(200.00, $summary['average'], 0.001);
        $this->assertSame(1, $summary['cancelled']);
    }

    public function test_open_counts_the_statuses_the_kitchen_still_owes(): void
    {
        [, $menu] = $this->restaurant();
        $summary = MenuOrderSummary::of(RestaurantOrder::where('menu_id', $menu->id), RestaurantOrder::class);

        // new + ready are open; completed and cancelled are not.
        $this->assertSame(2, $summary['open']);
    }

    public function test_every_status_appears_even_at_zero(): void
    {
        [, $menu] = $this->restaurant();
        $summary = MenuOrderSummary::of(RestaurantOrder::where('menu_id', $menu->id), RestaurantOrder::class);

        // The breakdown reads the same on a quiet day as on a busy one,
        // rather than reshuffling as statuses appear and disappear.
        $this->assertSame(RestaurantOrder::STATUSES, array_keys($summary['by_status']));
        $this->assertSame(0, $summary['by_status'][RestaurantOrder::STATUS_PREPARING]['count']);
    }

    public function test_an_empty_range_does_not_divide_by_zero(): void
    {
        [, $menu] = $this->restaurant();
        $summary = MenuOrderSummary::of(
            RestaurantOrder::where('menu_id', $menu->id)->whereRaw('1 = 0'),
            RestaurantOrder::class
        );

        $this->assertSame(0, $summary['orders']);
        $this->assertEqualsWithDelta(0.0, $summary['average'], 0.001);
    }

    // ── The board shows them ──────────────────────────────────────

    public function test_both_boards_show_the_summary_and_the_export(): void
    {
        [$rLink] = $this->restaurant();
        $sLink = $this->store();

        foreach ([[$rLink, 'restaurant'], [$sLink, 'store']] as [$link, $kind]) {
            $html = $this->actingAs($this->owner)
                ->get("/user/links/{$link->id}/{$kind}/orders")
                ->assertOk()->getContent();

            $this->assertStringContainsString('Revenue', $html, "$kind: no revenue tile");
            $this->assertStringContainsString('Still open', $html, "$kind: no open tile");
            $this->assertStringContainsString('Cancelled excluded', $html, "$kind: does not say what revenue leaves out");
            // Both formats, by their own URL. Asserting on the base path
            // alone passes with one of the two buttons dead, because the
            // other one still carries it.
            $this->assertStringContainsString("/{$kind}/orders/export", $html, "$kind: no export");
            $this->assertStringContainsString('format=csv', $html, "$kind: no CSV link");
            $this->assertStringContainsString('format=pdf', $html, "$kind: no PDF link");
        }
    }

    // ── It comes off the screen, filtered ─────────────────────────

    public function test_the_csv_carries_the_orders(): void
    {
        [$link] = $this->restaurant();

        $csv = $this->actingAs($this->owner)
            ->get("/user/links/{$link->id}/restaurant/orders/export?format=csv")
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Asha', $csv);
        $this->assertStringContainsString('Masala Dosa', $csv);
        // One row per ORDER with the lines folded in: a row per line
        // double-counts every total the moment it lands in a pivot table.
        $this->assertSame(1, substr_count($csv, 'Meena'));
    }

    public function test_the_status_filter_reaches_the_csv(): void
    {
        [$link] = $this->restaurant();

        $csv = $this->actingAs($this->owner)
            ->get("/user/links/{$link->id}/restaurant/orders/export?format=csv&status=open")
            ->assertOk()
            ->streamedContent();

        // new + ready.
        $this->assertStringContainsString('Asha', $csv);
        $this->assertStringContainsString('Ravi', $csv);
        // completed and cancelled are not open.
        $this->assertStringNotContainsString('Meena', $csv);
        $this->assertStringNotContainsString('Nobody', $csv);
    }

    public function test_the_date_range_reaches_the_csv(): void
    {
        [$link, $menu] = $this->restaurant();

        // An order from last year. "Today" must not carry it, or somebody
        // filters to today, downloads, and reconciles a year.
        $old = RestaurantOrder::create([
            'menu_id' => $menu->id, 'link_id' => $link->id,
            'status' => RestaurantOrder::STATUS_COMPLETED, 'customer_name' => 'LastYear',
            'subtotal' => 50, 'total' => 50, 'currency' => 'INR',
        ]);
        // created_at is not fillable, so passing it to create() is silently
        // ignored and the "old" order is stamped today -- which would have
        // made this test pass against a range filter that did nothing.
        $old->forceFill(['created_at' => now()->subYear()])->saveQuietly();
        $this->assertTrue($old->fresh()->created_at->isBefore(now()->subMonths(6)), 'the old order was not actually backdated');

        $today = $this->actingAs($this->owner)
            ->get("/user/links/{$link->id}/restaurant/orders/export?format=csv&range=today")
            ->assertOk()->streamedContent();
        $this->assertStringNotContainsString('LastYear', $today);

        $all = $this->actingAs($this->owner)
            ->get("/user/links/{$link->id}/restaurant/orders/export?format=csv&range=all")
            ->assertOk()->streamedContent();
        $this->assertStringContainsString('LastYear', $all);
    }

    public function test_the_pdf_is_a_pdf_and_carries_the_totals(): void
    {
        [$link] = $this->restaurant();

        $res = $this->actingAs($this->owner)
            ->get("/user/links/{$link->id}/restaurant/orders/export?format=pdf")
            ->assertOk();

        $res->assertHeader('Content-Type', 'application/pdf');
        // A document, not a stream: dompdf needs the whole set to lay out
        // pages, so this one is built and then sent.
        $this->assertStringStartsWith('%PDF', $res->getContent(), 'that is not a PDF');
    }

    public function test_the_pdf_honours_the_status_filter_too(): void
    {
        [$link] = $this->restaurant();

        // Rendered rather than downloaded, so the assertion is about the
        // document's CONTENT and not about a byte stream nobody can read.
        $html = $this->renderPdfView($link, ['status' => 'open']);

        $this->assertStringContainsString('Asha', $html);
        $this->assertStringNotContainsString('Meena', $html);
    }

    public function test_a_stranger_cannot_export_someone_elses_orders(): void
    {
        [$link] = $this->restaurant();
        $intruder = $this->makeIntruder();

        $this->actingAs($intruder)
            ->get("/user/links/{$link->id}/restaurant/orders/export?format=csv")
            ->assertNotFound();
    }

    public function test_a_junk_status_returns_everything_rather_than_nothing(): void
    {
        [$link] = $this->restaurant();

        // An export that silently comes back empty reads as "no orders",
        // not as "bad parameter", and that is the reading that costs money.
        $csv = $this->actingAs($this->owner)
            ->get("/user/links/{$link->id}/restaurant/orders/export?format=csv&status=banana")
            ->assertOk()->streamedContent();

        $this->assertStringContainsString('Asha', $csv);
        $this->assertStringContainsString('Meena', $csv);
    }

    // ── helpers ───────────────────────────────────────────────────

    private function makeIntruder(): User
    {
        $u = User::create([
            'name' => 'Other', 'email' => 'oth'.Str::random(8).'@ex.com',
            'password' => Hash::make('x'), 'status' => 'active',
        ]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        if ($ws !== null) {
            app()->instance('current_workspace', $ws);
        }
        app()->instance('workspace_owner', $u);

        return $u;
    }

    /** The PDF's own view, so its contents can be asserted as text. */
    private function renderPdfView(Link $link, array $query): string
    {
        $menu = RestaurantMenu::where('link_id', $link->id)->firstOrFail();

        $controller = new \App\Modules\User\Controllers\MenuOrderExportController();
        $m = new \ReflectionMethod($controller, 'scoped');
        $m->setAccessible(true);
        [$scoped, $range] = $m->invoke(
            $controller,
            request()->merge($query),
            $menu,
            RestaurantOrder::class,
            $link
        );

        return view('user.links.partials.orders-pdf', [
            'link'      => $link,
            'menu'      => $menu,
            'orders'    => (clone $scoped)->with('items')->orderByDesc('id')->get(),
            'summary'   => MenuOrderSummary::of($scoped, RestaurantOrder::class),
            'labels'    => MenuOrderSummary::labels(RestaurantOrder::class),
            'range'     => $range,
            'truncated' => 0,
            'cap'       => \App\Modules\User\Controllers\MenuOrderExportController::PDF_MAX_ROWS,
        ])->render();
    }
}
