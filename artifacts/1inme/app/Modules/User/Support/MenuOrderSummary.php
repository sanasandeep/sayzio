<?php

namespace App\Modules\User\Support;

/**
 * The numbers at the top of an orders board.
 *
 * Sana, 2026-10-05: "orders dashbord summary missing".
 *
 * ---- What a restaurant actually wants from this screen -----------------
 *
 * Not a list. A list is what the kitchen reads. The owner opening this on
 * a Sunday night wants four numbers -- how many, how much, what the
 * average ticket was, and how many are still open -- and every one of them
 * was computable from rows already on the page and shown nowhere.
 *
 * ---- Computed in the database, not from the page ----------------------
 *
 * The board paginates at fifty. Summing the fifty rows it happens to be
 * holding would have given a total that silently changes when somebody
 * clicks "load more", which is worse than no total: a number that moves
 * when you look at it teaches people not to trust the screen.
 *
 * So this takes the SCOPED QUERY, not the fetched collection, and
 * aggregates over the whole range.
 *
 * ---- Cancelled orders are counted and excluded ------------------------
 *
 * They are in the status breakdown, because "six cancellations today" is
 * worth knowing. They are out of revenue and out of the average, because
 * an order that was cancelled is not money and an average that includes it
 * is not the average ticket. Both at once is the only honest shape.
 */
class MenuOrderSummary
{
    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query   already scoped to the menu and the range
     * @param  class-string  $model  RestaurantOrder or StoreOrder
     * @return array<string, mixed>
     */
    public static function of($query, string $model): array
    {
        $cancelled = $model::STATUS_CANCELLED;

        // One grouped query rather than one per status: a busy Saturday is
        // exactly when this screen is opened and exactly when the extra
        // round trips would be felt.
        $rows = (clone $query)->reorder()
            ->selectRaw('status, COUNT(*) AS n, COALESCE(SUM(total),0) AS value')
            ->groupBy('status')
            ->get();

        $byStatus = [];
        $orders = 0;
        $revenue = 0.0;
        $billable = 0;

        foreach ($rows as $row) {
            $byStatus[$row->status] = [
                'count' => (int) $row->n,
                'value' => (float) $row->value,
            ];
            $orders += (int) $row->n;

            if ($row->status !== $cancelled) {
                $revenue  += (float) $row->value;
                $billable += (int) $row->n;
            }
        }

        // Every status, in the order the kitchen moves through them, so the
        // breakdown reads the same on a quiet day as on a busy one instead
        // of reshuffling as statuses appear and disappear.
        $ordered = [];
        foreach ($model::STATUSES as $status) {
            $ordered[$status] = $byStatus[$status] ?? ['count' => 0, 'value' => 0.0];
        }

        $open = 0;
        foreach ($model::OPEN_STATUSES as $status) {
            $open += $ordered[$status]['count'];
        }

        return [
            'orders'    => $orders,
            // Cancelled out: an order that was cancelled is not money.
            'revenue'   => round($revenue, 2),
            'average'   => $billable > 0 ? round($revenue / $billable, 2) : 0.0,
            'open'      => $open,
            'cancelled' => $ordered[$cancelled]['count'],
            'by_status' => $ordered,
        ];
    }

    /** Human labels for the status breakdown, from the model's own map. */
    public static function labels(string $model): array
    {
        return $model::STATUS_LABELS;
    }
}
