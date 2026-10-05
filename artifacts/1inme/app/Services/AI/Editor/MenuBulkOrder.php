<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\MenuOrderCoupon;
use Illuminate\Support\Facades\DB;

/**
 * How many of a thing one order may ask for, and what a bulk line produces.
 *
 * Sana, 2026-10-04: "need options while order from menu minimum and max
 * order quatity or each order item... this will create like bulk order...
 * when bulk order.... need to generate food coupons ids".
 *
 * ---- The two numbers are per item, not per menu ------------------------
 *
 * A caterer sells biryani in trays of ten and samosas by the piece on the
 * same menu. One pair of numbers for the whole menu would make one of
 * those wrong, and the owner would work around it by splitting the menu.
 *
 * ---- Why a threshold rather than a switch ------------------------------
 *
 * Sana's answer when asked: coupons start at a quantity the owner sets.
 * Order three and nothing changes; order fifty and fifty coupons come out.
 * A plain switch would hand a coupon id to somebody buying one dosa, which
 * is a thing they have to read, carry and not lose for no reason at all.
 */
class MenuBulkOrder
{
    /**
     * The limits on one item, resolved.
     *
     * @return array{min:int, max:?int, coupon_from:?int}
     */
    public static function limits($item): array
    {
        $min = max(1, (int) ($item->min_quantity ?? 1));
        $max = $item->max_quantity ?? null;
        $max = $max === null ? null : max($min, (int) $max);

        $from = $item->coupon_from ?? null;
        $from = $from === null ? null : max(1, (int) $from);

        return ['min' => $min, 'max' => $max, 'coupon_from' => $from];
    }

    /** The highest any of the three may be set to. */
    public const CEILING = 100000;

    /**
     * The most of one item one order line may ask for.
     *
     * This was 99, written into both public order endpoints as a literal,
     * and it was a sensible guard against nonsense right up until the day
     * bulk orders existed: an office ordering 200 lunches was refused by
     * validation before any of the rules below were consulted. The real
     * limit on a line is now the item's own ceiling; this is only the
     * backstop for an item that sets none, and it matches
     * MenuOrderCoupon::MAX_PER_ORDER because past that point a line stops
     * producing coupons anyway and is almost certainly a typo.
     */
    public const LINE_MAX = 2000;

    /**
     * The validation rules both item editors use for the three numbers.
     *
     * One copy, because two copies drift: the restaurant editor would
     * accept a ceiling of 500,000 six months after the store editor
     * stopped, and nobody would find out until a coupon run fell over.
     *
     * @return array<string, string>
     */
    public static function rules(): array
    {
        return [
            'min_quantity' => 'sometimes|nullable|integer|min:1|max:'.self::CEILING,
            'max_quantity' => 'sometimes|nullable|integer|min:1|max:'.self::CEILING,
            'coupon_from'  => 'sometimes|nullable|integer|min:1|max:'.self::CEILING,
        ];
    }

    /**
     * The three columns as they should be stored, from a validated payload.
     *
     * Only keys the request actually sent come back, so a PUT that touches
     * the dish name does not silently reset its quantity rules.
     *
     * `$item` is the row as it stands, and it matters: an editor that
     * sends only a new ceiling has to be checked against the floor already
     * in the database, not against the default. Without that, saving
     * "maximum 5" on a tray-of-ten dish stores a pair no quantity can
     * satisfy, and the item quietly becomes unorderable.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, int|null>
     */
    public static function input(array $data, $item = null): array
    {
        $out = [];

        $floor = array_key_exists('min_quantity', $data)
            ? max(1, (int) ($data['min_quantity'] ?? 1))
            : max(1, (int) ($item->min_quantity ?? 1));

        if (array_key_exists('min_quantity', $data)) {
            $out['min_quantity'] = $floor;
        }

        if (array_key_exists('max_quantity', $data)) {
            $max = $data['max_quantity'];
            // Raised to the floor rather than refused: the owner typing a
            // ceiling below the floor means the ceiling, and telling them
            // off about a number they can't see from here is worse.
            $out['max_quantity'] = $max === null || $max === '' ? null : max($floor, (int) $max);
        } elseif ($item !== null && $item->max_quantity !== null && (int) $item->max_quantity < $floor) {
            // The floor was raised past a ceiling the request never
            // mentioned. Same unorderable pair, arrived at from the other
            // side, so the ceiling comes up with it.
            $out['max_quantity'] = $floor;
        }

        if (array_key_exists('coupon_from', $data)) {
            $from = $data['coupon_from'];
            $out['coupon_from'] = $from === null || $from === '' ? null : max(1, (int) $from);
        }

        return $out;
    }

    /** Whether a line of this size turns into coupons. */
    public static function issuesCoupons($item, int $quantity): bool
    {
        $from = self::limits($item)['coupon_from'];

        return $from !== null && $quantity >= $from;
    }

    /**
     * Check a requested quantity, with a message meant for the guest.
     *
     * The page stops them before they tap Add; this stops the request when
     * it arrives. Both exist, for the same reason the choice rules have
     * both: without the first, somebody is refused after they thought they
     * were done, and without the second, the page decides the order.
     *
     * @throws \InvalidArgumentException
     */
    public static function check($item, int $quantity): void
    {
        ['min' => $min, 'max' => $max] = self::limits($item);

        if ($quantity < $min) {
            throw new \InvalidArgumentException(
                $item->name.' is sold in '.$min.'s. Please order at least '.$min.'.'
            );
        }

        if ($max !== null && $quantity > $max) {
            throw new \InvalidArgumentException(
                'You can order at most '.$max.' of '.$item->name.'.'
            );
        }
    }

    /** How the rule reads under the item on the menu, or ''. */
    public static function label($item): string
    {
        ['min' => $min, 'max' => $max, 'coupon_from' => $from] = self::limits($item);

        $parts = [];
        if ($min > 1) {
            $parts[] = 'Minimum '.$min;
        }
        if ($max !== null) {
            $parts[] = 'maximum '.$max;
        }
        if ($from !== null) {
            $parts[] = $from <= $min
                ? 'coupons issued'
                : 'coupons from '.$from;
        }

        return $parts ? ucfirst(implode(', ', $parts)) : '';
    }

    /**
     * Issue the coupons a saved order's lines have earned.
     *
     * Called inside the order's own transaction, so an order either exists
     * with all of its coupons or does not exist. Half an order's coupons is
     * a counter turning people away with no way to tell who was unlucky.
     *
     * @param  object  $order     the saved order
     * @param  string  $kind      MenuOrderCoupon::RESTAURANT|STORE
     * @param  array<int, array{model: object, line: object}>  $lines
     * @return int  how many were issued
     */
    public static function issue($order, string $kind, int $menuId, array $lines): int
    {
        $rows = [];
        $now = now();

        foreach ($lines as $entry) {
            $item = $entry['model'];
            $line = $entry['line'];
            $quantity = (int) $line->quantity;

            if (! self::issuesCoupons($item, $quantity)) {
                continue;
            }

            for ($i = 0; $i < $quantity; $i++) {
                if (count($rows) >= MenuOrderCoupon::MAX_PER_ORDER) {
                    break 2;
                }
                $rows[] = [
                    'code'          => MenuOrderCoupon::mint(),
                    'order_type'    => $kind,
                    'order_id'      => $order->id,
                    'order_item_id' => $line->id,
                    'menu_type'     => $kind,
                    'menu_id'       => $menuId,
                    // Snapshot: the dish will be renamed, and a coupon has
                    // to keep saying what it is for.
                    'item_name'     => $line->name,
                    'status'        => MenuOrderCoupon::ISSUED,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }
        }

        if ($rows === []) {
            return 0;
        }

        // Chunked: two thousand rows in one insert is a statement some
        // drivers refuse on parameter count alone.
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('menu_order_coupons')->insert($chunk);
        }

        return count($rows);
    }

    /**
     * What is left to collect on an order.
     *
     * @return array{total:int, redeemed:int, outstanding:int}
     */
    public static function tally(string $kind, int $orderId): array
    {
        $counts = MenuOrderCoupon::where('order_type', $kind)
            ->where('order_id', $orderId)
            ->selectRaw('status, count(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status');

        $total = (int) $counts->sum();
        $redeemed = (int) $counts->get(MenuOrderCoupon::REDEEMED, 0);
        $void = (int) $counts->get(MenuOrderCoupon::VOID, 0);

        return [
            'total'       => $total,
            'redeemed'    => $redeemed,
            // A voided coupon is not coming back, so it is not outstanding.
            'outstanding' => max(0, $total - $redeemed - $void),
        ];
    }
}
