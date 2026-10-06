<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\RestaurantTable;

/**
 * What a kitchen screen shows.
 *
 * Sana, 2026-10-05: "Need another dashboard like kitchen.... whowing all
 * table names if exists with current order status... aurto refresh also".
 *
 * ---- This is not the orders board with bigger text --------------------
 *
 * The orders board answers "what came in?", newest first, with money on
 * it. A kitchen answers a different question: "what do I cook next, and
 * who has been waiting?" The two sort in opposite directions -- a kitchen
 * works the OLDEST first -- and a kitchen does not care what anything
 * cost. So this is its own shape rather than a re-skin.
 *
 * ---- Every table, including the empty ones -----------------------------
 *
 * A board that lists only tables with orders cannot tell you table 7 is
 * free, which is half of what the person at the pass is looking at it for.
 * So every table the creator has defined is on the board, and an empty one
 * says so.
 *
 * "if exists" is his own hedge and it is the right one: plenty of these
 * pages take takeaway orders with no tables at all, and the store menu has
 * no concept of a table. Those get the same board grouped by ORDER
 * instead, rather than a screen that is blank because a feature they never
 * set up is missing.
 *
 * ---- A table's status is its oldest open order -------------------------
 *
 * Not its newest. If table 4 has one order waiting twenty minutes and one
 * placed just now, the board must shout about the twenty minutes -- that
 * is the one the kitchen is late on, and showing the newest would hide it
 * behind a fresh green chip.
 *
 * Completed and cancelled orders leave the board entirely. A kitchen
 * screen is a list of work, and work that is done is not on it.
 */
class KitchenBoard
{
    /** How long before a ticket is worth shouting about, in minutes. */
    public const WARN_AFTER  = 10;
    public const LATE_AFTER  = 20;

    /** Tickets on one board. A kitchen screen past this is unreadable anyway. */
    public const MAX_TICKETS = 120;

    /**
     * @param  class-string  $model   RestaurantOrder or StoreOrder
     * @return array{
     *     mode: string, groups: array, open: int, oldest_minutes: int|null,
     *     statuses: array, server_time: string
     * }
     */
    public static function of($menu, string $model, bool $hasTables): array
    {
        $prep = MenuHandoverTiming::resolve((array) $menu->settings)['prep_minutes'];
        $orders = $model::with(['items', 'menu'])
            ->where('menu_id', $menu->id)
            ->whereIn('status', $model::OPEN_STATUSES)
            ->where(fn ($q) => $q->whereNull('wanted_at')->orWhere('wanted_at', '<=', now()->addMinutes($prep))->orWhere('status', 'ready'))
            // Oldest first: the kitchen's own order of work, and the one
            // thing this board must never get backwards.
            ->orderBy('created_at')
            ->limit(self::MAX_TICKETS)
            ->get();

        $tickets = $orders->map(fn ($o) => self::ticket($o, $model))->all();

        $groups = $hasTables
            ? self::byTable($menu, $tickets)
            : self::byOrder($tickets);

        $oldest = $tickets ? max(array_column($tickets, 'minutes')) : null;

        return [
            'mode'           => $hasTables ? 'tables' : 'orders',
            // Split for the screen rather than in it.
            //
            // Sana, 2026-10-05: "it looks ugly and not clear". Four free
            // tables were four full-size cards shouting FREE, and the one
            // table with food on it was a narrow column beside them. A
            // kitchen board puts the work first and says "the rest are free"
            // quietly -- so the two sets come out separately and the screen
            // does not have to decide.
            'groups'         => array_values(array_filter($groups, fn ($g) => ! $g['empty'])),
            'free'           => array_values(array_map(
                fn ($g) => $g['name'],
                array_filter($groups, fn ($g) => $g['empty'])
            )),
            'open'           => count($tickets),
            'oldest_minutes' => $oldest,
            'oldest_wait'    => self::wait($oldest),
            'statuses'       => $model::STATUS_LABELS,
            'server_time'    => now()->toIso8601String(),
        ];
    }

    /** One ticket, with only what somebody cooking needs to read. */
    private static function ticket($order, string $model): array
    {
        $placed  = $order->created_at;
        $waitStart = $placed;
        if ($order->wanted_at) {
            $lead = MenuHandoverTiming::resolve((array) $order->menu->settings)['prep_minutes'];
            $scheduledStart = $order->wanted_at->copy()->subMinutes($lead);
            if (!$waitStart || $scheduledStart->gt($waitStart)) $waitStart = $scheduledStart;
        }
        $minutes = $waitStart ? max(0, (int) floor($waitStart->diffInMinutes(now(), false))) : 0;

        return [
            'id'       => $order->id,
            'ref'      => $order->token_number ?: $order->id,
            'status'   => $order->status,
            'label'    => $model::STATUS_LABELS[$order->status] ?? $order->status,
            'wanted_at' => $order->wanted_at?->toIso8601String(),
            'prepaid' => (bool) data_get($order->meta, 'coupon_reservation', false),
            'customer' => $order->customer_name ?: null,
            'note'     => $order->customer_note ?: null,
            'table'    => $order->table_label ?: null,
            'table_id' => $order->table_id ?? null,
            'minutes'  => $minutes,
            'wait'     => self::wait($minutes),
            'heat'     => self::heat($minutes),
            'placed'   => $placed?->toIso8601String(),
            // Quantity and name, nothing else. A kitchen ticket with a
            // price on it is a receipt, and reading past the price to find
            // the dish is how an order goes out wrong.
            'lines'    => $order->items->map(fn ($i) => [
                'qty'  => (int) $i->quantity,
                'name' => (string) $i->name,
                'note' => $i->note ?? null,
            ])->all(),
            // The moves this ticket can actually make, from the model's own
            // transition map rather than a second copy of it on the screen.
            'next'     => $model::STATUS_TRANSITIONS[$order->status] ?? [],
        ];
    }

    /**
     * A wait, written the way somebody says it out loud.
     *
     * Sana, 2026-10-05: "it looks ugly and not clear".
     *
     * He was looking at a ticket reading "10629m". That is seven and a half
     * days, and there is no reading it as that -- it is a number you have to
     * stop and divide. Minutes are right up to an hour and wrong after it,
     * so past an hour this says hours, and past a day it says days.
     */
    public static function wait(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }
        if ($minutes < 1) {
            return 'just now';
        }
        if ($minutes < 60) {
            return $minutes.'m';
        }

        $hours = intdiv($minutes, 60);
        if ($hours < 24) {
            $rest = $minutes % 60;

            return $rest > 0 ? $hours.'h '.$rest.'m' : $hours.'h';
        }

        $days  = intdiv($hours, 24);
        $restH = $hours % 24;

        return $restH > 0 ? $days.'d '.$restH.'h' : $days.'d';
    }

    /**
     * How loudly a ticket should read.
     *
     * Minutes are on every ticket too, but a number has to be read and a
     * colour does not -- and this screen is looked at from across a kitchen
     * by somebody holding a pan.
     */
    public static function heat(int $minutes): string
    {
        if ($minutes >= self::LATE_AFTER) {
            return 'late';
        }
        if ($minutes >= self::WARN_AFTER) {
            return 'warn';
        }

        return 'fresh';
    }

    /**
     * Every table, with its tickets. Empty ones included and marked.
     *
     * Orders with no table -- takeaway, a counter sale -- are not dropped;
     * they land in one group at the end. A ticket the kitchen cannot see is
     * a meal nobody cooks.
     */
    private static function byTable($menu, array $tickets): array
    {
        $tables = RestaurantTable::where('menu_id', $menu->id)
            ->orderBy('sort_order')->orderBy('id')->get();

        $byId    = [];
        $byLabel = [];
        $loose   = [];

        foreach ($tickets as $t) {
            if ($t['table_id']) {
                $byId[$t['table_id']][] = $t;
            } elseif ($t['table']) {
                // An order placed before a table row existed, or typed in by
                // hand at the counter: matched on the label so it still
                // lands on the right table rather than in the loose pile.
                $byLabel[mb_strtolower(trim($t['table']))][] = $t;
            } else {
                $loose[] = $t;
            }
        }

        $groups = [];

        foreach ($tables as $table) {
            $rows = array_merge(
                $byId[$table->id] ?? [],
                $byLabel[mb_strtolower(trim((string) $table->label))] ?? []
            );
            unset($byLabel[mb_strtolower(trim((string) $table->label))]);

            $groups[] = self::group('table:'.$table->id, $table->label, $rows);
        }

        // Labels that match no table row the creator kept. Named rather
        // than merged into "no table", because "Table 9" on a ticket means
        // somebody sat at one.
        foreach ($byLabel as $label => $rows) {
            $groups[] = self::group('label:'.$label, $rows[0]['table'], $rows);
        }

        if ($loose) {
            $groups[] = self::group('none', 'Takeaway & counter', $loose);
        }

        return $groups;
    }

    private static function byOrder(array $tickets): array
    {
        return array_map(
            fn ($t) => self::group('order:'.$t['id'], $t['customer'] ?: ('#'.$t['ref']), [$t]),
            $tickets
        );
    }

    /**
     * A group's own status is its OLDEST ticket's, and so is its heat.
     *
     * The alternative -- newest, or "most urgent status" -- hides the
     * twenty-minute ticket behind the one placed a moment ago, which is
     * precisely the order the kitchen is late on.
     */
    private static function group(string $key, ?string $name, array $tickets): array
    {
        $minutes = $tickets ? max(array_column($tickets, 'minutes')) : null;

        return [
            'key'      => $key,
            'name'     => $name ?: '—',
            'tickets'  => $tickets,
            'empty'    => $tickets === [],
            'status'   => $tickets[0]['status'] ?? null,
            'label'    => $tickets[0]['label'] ?? 'Free',
            'minutes'  => $minutes,
            'wait'     => self::wait($minutes),
            'heat'     => $minutes === null ? 'idle' : self::heat($minutes),
        ];
    }
}
