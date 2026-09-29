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
    public function __construct(
        protected NotificationService $notifications,
        protected RestaurantBillCalculator $calculator,
    ) {
    }

    /**
     * @param array{table_code?:?string, customer_name?:?string, customer_note?:?string, coupon_code?:?string, items:array<int,array{item_id:int,quantity:int,note?:?string}>} $data
     */
    public function place(Link $link, RestaurantMenu $menu, array $data): RestaurantOrder
    {
        $table = null;
        if (!empty($data['table_code'])) {
            $table = RestaurantTable::where('menu_id', $menu->id)
                ->where('code', $data['table_code'])
                ->first();
        }

        // Priced by the same function the quote endpoint uses, so a cart
        // quoted at one number cannot be charged at another. Availability
        // and the choice rules are both enforced in there, from the
        // database rather than from the request.
        $priced = app(\App\Modules\Common\Services\MenuCartPricer::class)->price($menu, $data['items']);
        $lines = $priced['lines'];
        $subtotal = $priced['subtotal'];

        // Re-compute the estimated bill server-side from the live subtotal so a
        // tampered or stale coupon/total can never be trusted. The same figures
        // feed the staff dashboard and the guest's order-status view.
        // The guest's choice decides which charges apply, so it has to reach
        // the calculator that produces the number they are shown AND the
        // number the restaurant is told. Those being one call is the point.
        $fulfilment = \App\Modules\User\Support\MenuFulfilment::modesFor((array) ($menu->settings ?? []), true);
        $chosen = in_array($data['fulfilment'] ?? null, $fulfilment, true)
            ? $data['fulfilment']
            : ($fulfilment[0] ?? null);

        $bill = $this->calculator->compute($menu, $subtotal, $data['coupon_code'] ?? null, $chosen);

        $order = DB::transaction(function () use ($menu, $link, $table, $data, $lines, $subtotal, $bill, $chosen) {
        // The number the guest is told to listen for. Reserved inside the
        // same transaction that creates the order, so two people tapping
        // Place order in the same second cannot both be told "14".
        [$tokenNumber, $tokenPeriod] = \App\Modules\User\Support\MenuOrderToken::reserve(
            'restaurant',
            $menu->id,
            (array) ($menu->settings ?? []),
            $link->user?->effectiveTimezone() ?? \App\Support\PlatformTimezone::platformDefault()
        );

            $order = RestaurantOrder::create([
                'menu_id'         => $menu->id,
                'link_id'         => $link->id,
                'table_id'        => $table?->id,
                'status'          => RestaurantOrder::STATUS_NEW,
                'table_label'     => $table?->label,
                'token_number'    => $tokenNumber,
                'token_period'    => $tokenPeriod,
                'customer_name'   => $data['customer_name'] ?? null,
                // Its own column rather than a corner of `meta`: it is the
                // number the restaurant rings when an order goes wrong.
                'customer_phone'  => $data['customer_phone'] ?? null,
                'customer_note'   => $data['customer_note'] ?? null,
                'subtotal'        => round($subtotal, 2),
                'coupon_code'     => $bill['coupon_code'],
                'discount_amount' => $bill['discount_amount'],
                'tax_rate'        => $bill['tax_rate'],
                'tax_inclusive'   => $bill['tax_inclusive'],
                'tax_amount'      => $bill['tax_amount'],
                'fulfilment'      => $chosen,
                'customer_address' => \App\Modules\User\Support\MenuFulfilment::needsAddress($chosen)
                    ? ($data['customer_address'] ?? null) : null,
                // The charges are SNAPSHOT, not referenced: the owner will
                // edit or delete one, and an order whose total no longer
                // adds up is the thing a guest queries.
                'charges'         => $bill['charges'],
                'charges_amount'  => $bill['charges_amount'],
                'total'           => $bill['total'],
                'currency'        => $menu->currency,
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
        $estimated = (float) ($order->total ?: $order->subtotal);
        $subject = "New order · {$where}";
        $body = "{$where} · {$count} item(s) · est. {$order->currency} "
            . number_format($estimated, 2)
            . " on \"{$link->title}\".";

        $ordersUrl = \App\Modules\Common\Support\PlatformHosts::outboundUrl(route('user.links.restaurant.orders', $link));
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
