<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreOrder;
use App\Modules\User\Models\StoreOrderItem;
use App\Modules\User\Models\StoreProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Core order-request placement logic for the store menu page (Task #3072).
 *
 * Validates the requested products against the live catalog, snapshots their
 * name/price, persists the request, then fires the `store.new_order`
 * notification to the owner across in-app + push + email per prefs.
 *
 * Unlike the restaurant flow there is NO tax/coupon calculation and NO
 * physical-table concept: the request total is simply the sum of line totals.
 * No money is ever collected — this is an order *request*, not a checkout.
 */
class StoreOrderService
{
    public function __construct(
        protected NotificationService $notifications,
    ) {
    }

    /**
     * @param array{customer_name?:?string, customer_contact?:?string, customer_note?:?string, items:array<int,array{product_id:int,quantity:int,note?:?string}>} $data
     */
    public function place(Link $link, StoreMenu $menu, array $data): StoreOrder
    {
        // Pull all referenced products in one query and validate availability.
        $productIds = collect($data['items'])->pluck('product_id')->map(fn ($i) => (int) $i)->all();
        $products = StoreProduct::where('menu_id', $menu->id)
            ->whereIn('id', $productIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $lines = [];
        $subtotal = 0;
        foreach ($data['items'] as $row) {
            $product = $products->get((int) $row['product_id']);
            if (!$product) {
                throw new \InvalidArgumentException('One or more products are no longer available.');
            }
            if ($product->is_out_of_stock) {
                throw new \InvalidArgumentException($product->name . ' is out of stock.');
            }
            $qty = max(1, (int) $row['quantity']);
            $lineTotal = round(((float) $product->price) * $qty, 2);
            $subtotal += $lineTotal;
            $lines[] = [
                'product_id' => $product->id,
                'name'       => $product->name,
                'unit_price' => $product->price,
                'quantity'   => $qty,
                'line_total' => $lineTotal,
                'note'       => $row['note'] ?? null,
            ];
        }

        if (empty($lines)) {
            throw new \InvalidArgumentException('Your cart is empty.');
        }

        $subtotal = round($subtotal, 2);

        $order = DB::transaction(function () use ($menu, $link, $data, $lines, $subtotal) {
            $order = StoreOrder::create([
                'menu_id'          => $menu->id,
                'link_id'          => $link->id,
                'status'           => StoreOrder::STATUS_NEW,
                'customer_name'    => $data['customer_name'] ?? null,
                'customer_contact' => $data['customer_contact'] ?? null,
                'customer_note'    => $data['customer_note'] ?? null,
                'subtotal'         => $subtotal,
                'total'            => $subtotal,
                'currency'         => $menu->currency,
            ]);

            foreach ($lines as $line) {
                StoreOrderItem::create(array_merge($line, ['order_id' => $order->id]));
            }

            return $order;
        });

        $this->notifyOwner($link, $menu, $order->fresh('items'));

        return $order;
    }

    /** Fan a new-request alert to the store owner across all channels. */
    protected function notifyOwner(Link $link, StoreMenu $menu, StoreOrder $order): void
    {
        $owner = $link->user;
        if (!$owner) {
            return;
        }

        $count = $order->items->sum('quantity');
        $who = $order->customer_name ? ('From ' . $order->customer_name) : 'New request';
        $subject = "New order request · {$count} item(s)";
        $body = "{$who} · {$count} item(s) · {$order->currency} "
            . number_format((float) $order->total, 2)
            . " on \"{$link->title}\".";

        $ordersUrl = AppModulesCommonSupportPlatformHosts::outboundUrl(route('user.links.store.orders', $link));
        $notification = null;
        try {
            $notification = $this->notifications->notify($owner, 'store.new_order', [
                'subject'    => $subject,
                'message'    => $body,
                'link_id'    => $link->id,
                'link_alias' => $link->alias,
                'order_id'   => $order->id,
                'subtotal'   => $order->subtotal,
                'currency'   => $order->currency,
                'url'        => $ordersUrl,
            ]);
        } catch (\Throwable $e) {
            Log::warning('store new_order in-app notify failed: ' . $e->getMessage());
        }

        if ($owner->email && $this->notifications->prefersChannel($owner->id, 'store.new_order', 'email')) {
            try {
                \App\Modules\Common\Services\Emailer::send('store.new_order', $owner->email, [
                    'customer'   => $order->customer_name ?: 'A customer',
                    'count'      => $count,
                    'currency'   => $order->currency,
                    'total'      => number_format((float) $order->total, 2),
                    'link_title' => $link->title,
                    'orders_url' => $ordersUrl,
                ], ['user' => $owner->id, 'related' => $order]);
            } catch (\Throwable $e) {
                Log::warning('store new_order email failed: ' . $e->getMessage());
            }
        }

        $this->notifications->pushToUser(
            $owner,
            'store.new_order',
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
