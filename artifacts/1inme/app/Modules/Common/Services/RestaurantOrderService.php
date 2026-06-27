<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\RestaurantOrder;
use App\Modules\User\Models\RestaurantOrderItem;
use App\Modules\User\Models\RestaurantTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Core order-placement logic for the restaurant menu page (Task #1536).
 * Validates the requested items against the live menu, snapshots their
 * name/price, persists the order, then fires the `restaurant.new_order`
 * notification to the owner across in-app + push + email per prefs.
 */
class RestaurantOrderService
{
    public function __construct(protected NotificationService $notifications)
    {
    }

    /**
     * @param array{table_code?:?string, customer_name?:?string, customer_note?:?string, items:array<int,array{item_id:int,quantity:int,note?:?string}>} $data
     */
    public function place(Link $link, RestaurantMenu $menu, array $data): RestaurantOrder
    {
        $table = null;
        if (!empty($data['table_code'])) {
            $table = RestaurantTable::where('menu_id', $menu->id)
                ->where('code', $data['table_code'])
                ->first();
        }

        // Pull all referenced items in one query and validate availability.
        $itemIds = collect($data['items'])->pluck('item_id')->map(fn ($i) => (int) $i)->all();
        $items = RestaurantMenuItem::where('menu_id', $menu->id)
            ->whereIn('id', $itemIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $lines = [];
        $subtotal = 0;
        foreach ($data['items'] as $row) {
            $item = $items->get((int) $row['item_id']);
            if (!$item) {
                throw new \InvalidArgumentException('One or more items are no longer available.');
            }
            if ($item->is_sold_out) {
                throw new \InvalidArgumentException($item->name . ' is sold out.');
            }
            $qty = max(1, (int) $row['quantity']);
            $lineTotal = round(((float) $item->price) * $qty, 2);
            $subtotal += $lineTotal;
            $lines[] = [
                'item_id'    => $item->id,
                'name'       => $item->name,
                'unit_price' => $item->price,
                'quantity'   => $qty,
                'line_total' => $lineTotal,
                'note'       => $row['note'] ?? null,
            ];
        }

        $order = DB::transaction(function () use ($menu, $link, $table, $data, $lines, $subtotal) {
            $order = RestaurantOrder::create([
                'menu_id'       => $menu->id,
                'link_id'       => $link->id,
                'table_id'      => $table?->id,
                'status'        => RestaurantOrder::STATUS_NEW,
                'table_label'   => $table?->label,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_note' => $data['customer_note'] ?? null,
                'subtotal'      => round($subtotal, 2),
                'currency'      => $menu->currency,
            ]);

            foreach ($lines as $line) {
                RestaurantOrderItem::create(array_merge($line, ['order_id' => $order->id]));
            }

            return $order;
        });

        $this->notifyOwner($link, $menu, $order->fresh('items'));

        return $order;
    }

    /** Fan a new-order alert to the menu owner across all channels. */
    protected function notifyOwner(Link $link, RestaurantMenu $menu, RestaurantOrder $order): void
    {
        $owner = $link->user;
        if (!$owner) {
            return;
        }

        $where = $order->table_label ? ('Table ' . $order->table_label) : 'Walk-in';
        $count = $order->items->sum('quantity');
        $subject = "New order · {$where}";
        $body = "{$where} · {$count} item(s) · {$order->currency} "
            . number_format((float) $order->subtotal, 2)
            . " on \"{$link->title}\".";

        $ordersUrl = route('user.links.restaurant.orders', $link);
        $notification = null;
        try {
            $notification = $this->notifications->notify($owner, 'restaurant.new_order', [
                'subject'      => $subject,
                'message'      => $body,
                'link_id'      => $link->id,
                'link_alias'   => $link->alias,
                'order_id'     => $order->id,
                'table_label'  => $order->table_label,
                'subtotal'     => $order->subtotal,
                'currency'     => $order->currency,
                'url'          => $ordersUrl,
            ]);
        } catch (\Throwable $e) {
            Log::warning('restaurant new_order in-app notify failed: ' . $e->getMessage());
        }

        if ($owner->email && $this->notifications->prefersChannel($owner->id, 'restaurant.new_order', 'email')) {
            try {
                \App\Modules\Common\Services\Emailer::send('restaurant.new_order', $owner->email, [
                    'where'      => $where,
                    'count'      => $count,
                    'currency'   => $order->currency,
                    'subtotal'   => number_format((float) $order->subtotal, 2),
                    'link_title' => $link->title,
                    'orders_url' => $ordersUrl,
                ], ['user' => $owner->id, 'related' => $order]);
            } catch (\Throwable $e) {
                Log::warning('restaurant new_order email failed: ' . $e->getMessage());
            }
        }

        // Carry the same target URL the in-app row uses (so a tapped push
        // opens the orders dashboard) and the originating notification id
        // (so the tap can mark that row read).
        $this->notifications->pushToUser(
            $owner,
            'restaurant.new_order',
            $subject,
            $body,
            array_merge(
                [
                    'link_id'  => $link->id,
                    'order_id' => $order->id,
                    'url'      => $ordersUrl,
                ],
                $notification ? ['notification_id' => $notification->id] : [],
            ),
        );
    }
}
