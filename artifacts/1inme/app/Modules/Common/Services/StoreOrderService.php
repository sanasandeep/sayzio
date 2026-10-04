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
        // Priced by the same function the quote endpoint uses, so a cart
        // quoted at one number cannot be charged at another. Availability
        // and the choice rules are both enforced in there, from the
        // database rather than from the request.
        $priced = app(\App\Modules\Common\Services\MenuCartPricer::class)->price($menu, $data['items']);
        $lines = $priced['lines'];
        $subtotal = $priced['subtotal'];

        if (empty($lines)) {
            throw new \InvalidArgumentException('Your cart is empty.');
        }

        // The store has no tax and no coupons, so its bill is the subtotal
        // plus whatever charges the chosen handover adds. Same definition
        // as the restaurant's, called directly rather than through a
        // calculator this page type does not otherwise need.
        $modes = \App\Modules\User\Support\MenuFulfilment::modesFor((array) ($menu->settings ?? []), false);
        $chosen = in_array($data['fulfilment'] ?? null, $modes, true)
            ? $data['fulfilment']
            : ($modes[0] ?? null);

        $timezone = $link->user?->effectiveTimezone()
            ?? \App\Support\PlatformTimezone::platformDefault();

        // Null means "as soon as possible". Anything else has to be a slot
        // this menu is offering right now, checked here rather than trusted.
        $wantedAt = \App\Modules\User\Support\MenuHandoverTiming::accept(
            (array) ($menu->settings ?? []), $chosen, $timezone, $data['wanted_at'] ?? null
        );

        $chargeLines   = \App\Modules\User\Support\MenuFulfilment::applicable((array) ($menu->settings ?? []), $chosen, round($subtotal, 2));
        $chargesAmount = \App\Modules\User\Support\MenuFulfilment::total($chargeLines);

        $order = DB::transaction(function () use ($menu, $link, $data, $lines, $subtotal, $chosen, $chargeLines, $chargesAmount, $timezone, $wantedAt) {
        // The number the guest is told to listen for. Reserved inside the
        // same transaction that creates the order, so two people tapping
        // Place order in the same second cannot both be told "14".
        [$tokenNumber, $tokenPeriod] = \App\Modules\User\Support\MenuOrderToken::reserve(
            'store',
            $menu->id,
            (array) ($menu->settings ?? []),
            $timezone
        );

            $phone = $data['customer_phone'] ?? null;

            $order = StoreOrder::create([
                'menu_id'          => $menu->id,
                'link_id'          => $link->id,
                'status'           => StoreOrder::STATUS_NEW,
                'token_number'     => $tokenNumber,
                'wanted_at'        => $wantedAt,
                'token_period'     => $tokenPeriod,
                'customer_name'    => $data['customer_name'] ?? null,
                'customer_phone'   => $phone,
                // `customer_contact` predates the phone column and is what
                // LeadAggregator, the delivery projects and the WhatsApp
                // hand-off all read. It is mirrored rather than migrated so
                // none of those change behaviour in this commit; an email
                // typed into it still wins.
                'customer_contact' => $data['customer_contact'] ?? $phone,
                'customer_note'    => $data['customer_note'] ?? null,
                'subtotal'         => $subtotal,
                'fulfilment'       => $chosen,
                'customer_address' => \App\Modules\User\Support\MenuFulfilment::needsAddress($chosen)
                    ? ($data['customer_address'] ?? null) : null,
                // Snapshot, not a reference: the owner will edit the
                // delivery fee and an order whose total no longer adds up
                // is exactly what a customer writes in about.
                'charges'          => $chargeLines,
                'charges_amount'   => $chargesAmount,
                'total'            => round($subtotal + $chargesAmount, 2),
                'currency'         => $menu->currency,
            ]);

            $saved = [];
            foreach ($lines as $line) {
                $row = StoreOrderItem::create(array_merge($line, ['order_id' => $order->id]));
                $model = \App\Modules\User\Models\StoreProduct::find($line['product_id'] ?? 0);
                if ($model) {
                    $saved[] = ['model' => $model, 'line' => $row];
                }
            }

            // Inside the order's own transaction on purpose: an order
            // either exists with all of its coupons or does not exist.
            // Half of them is a counter turning people away with no way to
            // tell who was unlucky.
            \App\Modules\User\Support\MenuBulkOrder::issue($order, 'store', $menu->id, $saved);

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

        $ordersUrl = \App\Modules\Common\Support\PlatformHosts::outboundUrl(route('user.links.store.orders', $link));
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
