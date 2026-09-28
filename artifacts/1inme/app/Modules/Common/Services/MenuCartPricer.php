<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\MenuItemOptionGroup;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreProduct;
use App\Modules\User\Support\MenuOptionSelection;

/**
 * What a cart costs, once, for every screen that needs to know.
 *
 * ---- Why this is one class -----------------------------------------------
 *
 * The number a guest is quoted and the number the kitchen is told have been
 * computed in four separate places: the restaurant's quote endpoint, the
 * restaurant's order service, the store's quote endpoint and the store's
 * order service. They agreed only because nobody had changed one of them
 * yet. Choices are exactly the change that would break that -- an item's
 * price is no longer its price, it is its price plus whatever was picked --
 * and four implementations of "plus whatever was picked" is four chances to
 * charge a different number from the one on screen.
 *
 * So the quote and the order go through here, and a cart that quotes at
 * Rs.430 is charged at Rs.430 because it is the same function both times.
 *
 * ---- Options are priced per unit -----------------------------------------
 *
 * Two Chilli Paneer with extra paneer is two lots of extra paneer. So the
 * choices are priced for one unit and multiplied by the quantity, and the
 * line's `unit_price` is the base plus that per-unit amount -- which keeps
 * every existing sum (`unit_price` x `quantity` = `line_total`) true for
 * code that has never heard of options.
 */
class MenuCartPricer
{
    /** More lines than this is not a cart. */
    public const MAX_LINES = 60;

    /**
     * Price a cart.
     *
     * @param  array<int, array{item_id?:int|string, product_id?:int|string, quantity:int|string, note?:?string, options?:array}>  $rows
     * @return array{lines: array<int, array<string, mixed>>, subtotal: float}
     *
     * @throws \InvalidArgumentException with a message meant for the guest
     */
    public function price(RestaurantMenu|StoreMenu $menu, array $rows): array
    {
        $isRestaurant = $menu instanceof RestaurantMenu;
        $ownerType = $isRestaurant
            ? MenuItemOptionGroup::RESTAURANT_ITEM
            : MenuItemOptionGroup::STORE_PRODUCT;
        $idKey = $isRestaurant ? 'item_id' : 'product_id';

        if (count($rows) > self::MAX_LINES) {
            throw new \InvalidArgumentException('That is more items than one order can take.');
        }

        $ids = collect($rows)
            ->map(fn ($r) => (int) ($r[$idKey] ?? $r['item_id'] ?? $r['product_id'] ?? 0))
            ->filter()
            ->unique()
            ->values();

        $catalog = $isRestaurant
            ? RestaurantMenuItem::where('menu_id', $menu->id)->whereIn('id', $ids)->where('is_active', true)->get()->keyBy('id')
            : StoreProduct::where('menu_id', $menu->id)->whereIn('id', $ids)->where('is_active', true)->get()->keyBy('id');

        // One query for every item's groups rather than one per item: a
        // forty-item menu must not mean forty-one round trips.
        $groupsByItem = MenuOptionSelection::groupsForMany($ownerType, $ids->all());

        $lines = [];
        $subtotal = 0.0;

        foreach ($rows as $row) {
            $id = (int) ($row[$idKey] ?? $row['item_id'] ?? $row['product_id'] ?? 0);
            $item = $catalog->get($id);

            if (! $item) {
                throw new \InvalidArgumentException('One or more items are no longer available.');
            }
            // The two page types spell "we have run out" differently.
            $soldOut = $isRestaurant ? $item->is_sold_out : $item->is_out_of_stock;
            if ($soldOut) {
                throw new \InvalidArgumentException($item->name.' is sold out.');
            }

            $qty = max(1, (int) $row['quantity']);

            // Rules and prices both come from the database here, never from
            // the request: what the browser sent is a list of choices, not
            // a bill.
            $picked = is_array($row['options'] ?? null) ? $row['options'] : [];
            $chosen = MenuOptionSelection::resolve(
                $groupsByItem[$id] ?? collect(),
                $picked,
                $item->name
            );

            $perUnit = round(((float) $item->price) + $chosen['total'], 2);
            $lineTotal = round($perUnit * $qty, 2);
            $subtotal += $lineTotal;

            $line = [
                $isRestaurant ? 'item_id' : 'product_id' => $item->id,
                'name'          => $item->name,
                'unit_price'    => $perUnit,
                'quantity'      => $qty,
                'line_total'    => $lineTotal,
                'note'          => $row['note'] ?? null,
                // A snapshot, not a reference. The owner will rename and
                // re-price these, and an order from last Tuesday has to keep
                // saying what was actually ordered and actually charged.
                'options'       => $chosen['lines'] ?: null,
                'options_total' => round($chosen['total'] * $qty, 2),
            ];

            $lines[] = $line;
        }

        return ['lines' => $lines, 'subtotal' => round($subtotal, 2)];
    }

    /**
     * How a chosen set of options reads on one line, for a kitchen screen,
     * a WhatsApp message or a receipt. Written once so those three never
     * describe the same order differently.
     *
     * @param  array<int, array{group:string, name:string, quantity:int}>|null  $options
     */
    public static function describe(?array $options): string
    {
        if (! $options) {
            return '';
        }

        return collect($options)
            ->map(fn ($o) => ($o['quantity'] ?? 1) > 1
                ? $o['quantity'].'x '.$o['name']
                : $o['name'])
            ->implode(', ');
    }
}
