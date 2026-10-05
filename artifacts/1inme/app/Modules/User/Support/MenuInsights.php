<?php

namespace App\Modules\User\Support;

use Illuminate\Support\Facades\DB;

/**
 * What the orders are actually telling the owner.
 *
 * Sana, 2026-10-05: "dashborad: i need top items, item sales, reccuring
 * things, highlights or anything related....".
 *
 * ---- The totals are not the answer -------------------------------------
 *
 * The summary tiles say how much came in. They cannot say what to DO. The
 * four things below can:
 *
 *   - what sells, so the top of the menu is the right items;
 *   - what sells for MONEY, which is a different list — a 30-rupee tea
 *     outsells everything and earns less than the thing nobody reorders;
 *   - who comes back, because a regular is worth more than a visitor and
 *     most owners have no idea they have any;
 *   - what has never sold at all, which is the one nobody builds and the
 *     one that actually shortens a menu.
 *
 * ---- Cancelled orders are out of every number here ---------------------
 *
 * A cancelled order is not a sale. It belongs in the status breakdown on
 * the board, where it is counted and named, and nowhere near "your best
 * seller" — a dish that was ordered twice and cancelled twice is not a
 * best seller, and promoting it because of this screen would be the
 * screen's fault.
 */
class MenuInsights
{
    /** How many rows a "top" list is before it stops being a shortlist. */
    public const TOP = 8;

    /** Orders from one person before they count as a regular. */
    public const REGULAR_AT = 2;

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query  scoped to the menu and the range
     * @param  class-string  $model
     * @param  class-string  $itemModel
     * @return array<string, mixed>
     */
    public static function of($query, string $model, string $itemModel, $menu, string $catalogueModel): array
    {
        $billable = (clone $query)->reorder()
            ->where('status', '!=', $model::STATUS_CANCELLED);

        $orderIds = (clone $billable)->select('id');

        // One grouped pass over the lines. Grouped by NAME rather than by
        // item id on purpose: a dish that was renamed, or deleted and added
        // again, is the same dish to the person reading this, and three rows
        // for "Masala Dosa" would be a worse answer than one.
        $lines = $itemModel::query()
            ->whereIn('order_id', $orderIds)
            ->selectRaw('name, SUM(quantity) AS qty, SUM(line_total) AS revenue, COUNT(DISTINCT order_id) AS orders')
            ->groupBy('name')
            ->get();

        $byQty = $lines->sortByDesc(fn ($l) => (int) $l->qty)->take(self::TOP)->values();
        $byRev = $lines->sortByDesc(fn ($l) => (float) $l->revenue)->take(self::TOP)->values();

        return [
            'sold'      => (int) $lines->sum(fn ($l) => (int) $l->qty),
            'distinct'  => $lines->count(),
            'top_qty'   => self::rows($byQty),
            'top_money' => self::rows($byRev),
            'regulars'  => self::regulars($billable),
            'never'     => self::neverSold($lines, $menu, $catalogueModel),
            'busiest'   => self::busiest($billable),
        ];
    }

    /** @return array<int, array{name: string, qty: int, revenue: float, orders: int}> */
    private static function rows($lines): array
    {
        return $lines->map(fn ($l) => [
            'name'    => (string) $l->name,
            'qty'     => (int) $l->qty,
            'revenue' => round((float) $l->revenue, 2),
            'orders'  => (int) $l->orders,
        ])->all();
    }

    /**
     * People who came back.
     *
     * Matched on PHONE first and only falling back to a name, because two
     * customers called "Raj" are two people and one phone number is one
     * person. A name-only match would invent regulars out of common names,
     * and an owner who is told they have forty regulars when they have four
     * will stop believing the whole screen.
     *
     * Orders with neither are skipped rather than lumped together — an
     * anonymous counter sale is not evidence of anybody returning.
     */
    private static function regulars($billable): array
    {
        $rows = (clone $billable)
            ->selectRaw("COALESCE(NULLIF(customer_phone, ''), NULLIF(customer_name, '')) AS who")
            ->selectRaw('MAX(customer_name) AS label')
            ->selectRaw('COUNT(*) AS orders, SUM(total) AS spent, MAX(created_at) AS last_at')
            ->whereRaw("COALESCE(NULLIF(customer_phone, ''), NULLIF(customer_name, '')) IS NOT NULL")
            ->groupByRaw("COALESCE(NULLIF(customer_phone, ''), NULLIF(customer_name, ''))")
            ->havingRaw('COUNT(*) >= ?', [self::REGULAR_AT])
            ->orderByRaw('COUNT(*) DESC')
            ->limit(self::TOP)
            ->get();

        return [
            'count' => $rows->count(),
            'rows'  => $rows->map(fn ($r) => [
                'name'   => (string) ($r->label ?: $r->who),
                'orders' => (int) $r->orders,
                'spent'  => round((float) $r->spent, 2),
                'last'   => $r->last_at ? \Carbon\Carbon::parse($r->last_at)->diffForHumans() : null,
            ])->all(),
        ];
    }

    /**
     * What is on the menu and has never been ordered.
     *
     * The one nobody builds, and the one that actually shortens a menu. An
     * owner can see their best sellers anywhere; the dish that has sat there
     * for six months taking up a line is invisible by definition, because
     * nothing ever happens to it.
     *
     * Hidden and sold-out items are left out: they have an explanation
     * already, and listing them as dead weight would be wrong.
     */
    private static function neverSold($lines, $menu, string $catalogueModel): array
    {
        $sold = $lines->pluck('name')->map(fn ($n) => mb_strtolower(trim((string) $n)))->flip();

        return $catalogueModel::where('menu_id', $menu->id)
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')
            ->pluck('name')
            ->filter(fn ($n) => ! $sold->has(mb_strtolower(trim((string) $n))))
            ->take(self::TOP)
            ->values()
            ->all();
    }

    /**
     * When the orders come in.
     *
     * The hour is the useful unit: it is what staffing and prep are decided
     * by, and "Saturday" without a time is something every owner already
     * knows.
     */
    private static function busiest($billable): array
    {
        $rows = (clone $billable)
            ->selectRaw('EXTRACT(HOUR FROM created_at) AS h, COUNT(*) AS n')
            ->groupByRaw('EXTRACT(HOUR FROM created_at)')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(3)
            ->get();

        return $rows->map(fn ($r) => [
            'hour'  => (int) $r->h,
            'label' => self::hourLabel((int) $r->h),
            'count' => (int) $r->n,
        ])->all();
    }

    /** 14 => "2–3pm", because nobody staffs a kitchen by the 24-hour clock. */
    public static function hourLabel(int $hour): string
    {
        $fmt = function (int $h): string {
            $h = ($h + 24) % 24;
            $suffix = $h < 12 ? 'am' : 'pm';
            $display = $h % 12;
            if ($display === 0) {
                $display = 12;
            }

            return $display.$suffix;
        };

        // "11am–12pm" rather than "11–12pm": the two halves straddle noon and
        // dropping the first suffix reads as the wrong hour.
        $from = $fmt($hour);
        $to   = $fmt($hour + 1);

        $fromBare = rtrim($from, 'apm');
        $sameHalf = substr($from, -2) === substr($to, -2);

        return $sameHalf ? $fromBare.'–'.$to : $from.'–'.$to;
    }
}
