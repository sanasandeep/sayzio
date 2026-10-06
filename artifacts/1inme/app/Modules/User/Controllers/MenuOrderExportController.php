<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantOrder;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreOrder;
use App\Modules\User\Support\MenuOrderRange;
use App\Modules\User\Support\MenuOrderSummary;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Taking the orders off the screen.
 *
 * Sana, 2026-10-05: "export of orders , pdf, csv, with filter options
 * active".
 *
 * ---- "with filter options active" is the whole requirement -------------
 *
 * An export that quietly ignores the range is worse than no export: the
 * creator filters to last month, downloads, and reconciles a year against
 * their books. So the range and the status filter are resolved here
 * exactly as the board resolves them -- same MenuOrderRange call, same
 * parameters -- and a test asserts the two agree rather than trusting that
 * they will.
 *
 * ---- CSV streams, PDF does not ----------------------------------------
 *
 * The CSV is chunked and streamed, so a year of orders does not have to
 * fit in memory before the download starts. The PDF cannot be: a page
 * layout needs the whole set. It is therefore capped, and the cap is
 * stated ON the document rather than silently truncating -- a report whose
 * last page is missing and does not say so is how somebody under-declares
 * their takings.
 */
class MenuOrderExportController extends Controller
{
    /** Rows in a PDF before it stops being a document and becomes a dump. */
    public const PDF_MAX_ROWS = 2000;

    /** The menu, its order class, and what this page type calls an order. */
    protected function resolve(Link $link, string $kind): array
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);

        if ($kind === 'restaurant') {
            abort_unless($link->type === Link::TYPE_RESTAURANT_MENU, 404);
            $menu = RestaurantMenu::where('link_id', $link->id)->first();
            abort_if(! $menu, 404);

            return [$menu, RestaurantOrder::class, 'order'];
        }

        abort_unless($link->type === Link::TYPE_STORE_MENU, 404);
        $menu = StoreMenu::where('link_id', $link->id)->first();
        abort_if(! $menu, 404);

        return [$menu, StoreOrder::class, 'request'];
    }

    /**
     * The same set of rows the board is showing.
     *
     * Deliberately one method used by both formats and by the summary, so
     * the CSV, the PDF and the numbers on screen cannot disagree about what
     * "the filtered set" is.
     */
    protected function scoped(Request $request, $menu, string $model, Link $link): array
    {
        $range = MenuOrderRange::resolve(
            $request->query('range'),
            $request->query('from'),
            $request->query('to'),
            $link->user?->effectiveTimezone() ?? \App\Support\PlatformTimezone::platformDefault()
        );

        $query = MenuOrderRange::apply($model::where('menu_id', $menu->id), $range);
        if ($request->query('schedule') === 'upcoming') {
            $query = $model::where('menu_id', $menu->id)->where('wanted_at', '>', now())->whereIn('status', $model::OPEN_STATUSES);
            $range['label'] = 'Upcoming preorders';
        }

        // The board's Open/All toggle, as a server-side filter. Anything
        // that is not a real status is ignored rather than returning
        // nothing -- an export that silently comes back empty reads as "no
        // orders" and not as "bad parameter".
        $status = (string) $request->query('status', '');
        if ($status === 'open') {
            $query->whereIn('status', $model::OPEN_STATUSES);
        } elseif (in_array($status, $model::STATUSES, true)) {
            $query->where('status', $status);
        }

        return [$query, $range, $status];
    }

    public function export(Request $request, Link $link, string $kind = 'restaurant')
    {
        [$menu, $model, $noun] = $this->resolve($link, $kind);
        [$query, $range] = $this->scoped($request, $menu, $model, $link);

        $format = $request->query('format') === 'pdf' ? 'pdf' : 'csv';
        $stamp  = now()->format('Ymd-His');
        $base   = ($link->alias ?: $noun).'-orders-'.$stamp;

        return $format === 'pdf'
            ? $this->pdf($query, $model, $menu, $link, $range, $base.'.pdf')
            : $this->csv($query, $base.'.csv');
    }

    /** Streamed, so a year of orders never has to fit in memory at once. */
    protected function csv($query, string $filename)
    {
        $rows = (clone $query)->with('items')->orderByDesc('id');

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Order', 'Placed', 'Status', 'Customer', 'Table',
                'Items', 'Quantity', 'Subtotal', 'Discount', 'Tax', 'Total', 'Currency', 'Note',
            ]);

            $rows->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $order) {
                    // One row per ORDER, with the lines folded into a cell.
                    // A row per line would double-count every total the
                    // moment somebody drops it into a pivot table.
                    $lines = $order->items->map(
                        fn ($i) => $i->quantity.'× '.$i->name
                    )->implode('; ');

                    fputcsv($out, [
                        $order->token_number ?: $order->id,
                        optional($order->created_at)->format('Y-m-d H:i'),
                        $order->status,
                        $order->customer_name ?? '',
                        $order->table_label ?? '',
                        $lines,
                        (int) $order->items->sum('quantity'),
                        $order->subtotal,
                        $order->discount_amount,
                        $order->tax_amount,
                        $order->total,
                        $order->currency,
                        $order->customer_note ?? '',
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** A document, so it is capped and says so. */
    protected function pdf($query, string $model, $menu, Link $link, array $range, string $filename)
    {
        $summary = MenuOrderSummary::of($query, $model);

        $orders = (clone $query)->with('items')
            ->orderByDesc('id')
            ->limit(self::PDF_MAX_ROWS)
            ->get();

        $html = view('user.links.partials.orders-pdf', [
            'link'     => $link,
            'menu'     => $menu,
            'orders'   => $orders,
            'summary'  => $summary,
            'labels'   => MenuOrderSummary::labels($model),
            'range'    => $range,
            // Said on the document rather than silently dropped: a report
            // missing its last page without admitting it is how somebody
            // under-declares their takings.
            'truncated' => $summary['orders'] > $orders->count() ? $summary['orders'] - $orders->count() : 0,
            'cap'       => self::PDF_MAX_ROWS,
        ])->render();

        $dompdf = new \Dompdf\Dompdf(new \Dompdf\Options([
            'isRemoteEnabled' => false,   // nothing on this page needs the network
            'defaultFont'     => 'DejaVu Sans',
        ]));
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
