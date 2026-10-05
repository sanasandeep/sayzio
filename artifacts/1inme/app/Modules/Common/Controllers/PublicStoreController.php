<?php

namespace App\Modules\Common\Controllers;

use App\Modules\Common\Services\StoreOrderService;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\StoreOrder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Visitor-facing store endpoints (Task #3072). Mounted under the `/sm/`
 * prefix so they never collide with the catch-all `/{alias}` route.
 * No authentication and no online payment — this is an order *request*
 * flow; the owner arranges fulfilment and payment directly.
 *
 * There is a quote endpoint, though a narrow one. The store has no coupons
 * and no tax, so for most of this page's life the total really was the sum
 * of the line prices and the browser could work it out. Charges changed
 * that: which ones apply depends on the handover the customer picked, and
 * having the browser decide that would be a second copy of a rule the
 * server already owns -- the exact shape of bug this whole week has been
 * about. So the shown estimate is quoted, the same way the restaurant's is.
 */
class PublicStoreController extends Controller
{
    public function __construct(
        protected StoreOrderService $orders,
    ) {
    }

    /**
     * The estimated total for a cart, given how the customer wants it
     * handed over. Same figures the order will be stored with, because the
     * same code produces both.
     */
    public function quote(Request $request, string $alias)
    {
        [$link, $menu] = $this->resolveMenu($alias);

        if (!$link || !$menu) {
            return response()->json(['error' => ['message' => 'Store not found', 'code' => 'not_found']], 404);
        }

        $data = $request->validate([
            'fulfilment'          => 'nullable|string|max:16',
            'items'               => 'required|array|min:1',
            'items.*.product_id'  => 'required|integer',
            'items.*.quantity'    => 'required|integer|min:1|max:'.\App\Modules\User\Support\MenuBulkOrder::LINE_MAX,
            'items.*.options'             => 'nullable|array|max:40',
            'items.*.options.*.option_id' => 'required|integer',
            'items.*.options.*.quantity'  => 'nullable|integer|min:1|max:20',
        ]);

        // The quote and the order price through the same function, so a
        // cart quoted at one number cannot be charged at another.
        try {
            $subtotal = app(\App\Modules\Common\Services\MenuCartPricer::class)
                ->price($menu, $data['items'])['subtotal'];
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => ['message' => $e->getMessage(), 'code' => 'invalid_cart'],
            ], 422);
        }

        $modes  = \App\Modules\User\Support\MenuFulfilment::modesFor((array) ($menu->settings ?? []), false);
        $chosen = in_array($data['fulfilment'] ?? null, $modes, true) ? $data['fulfilment'] : ($modes[0] ?? null);

        $charges = \App\Modules\User\Support\MenuFulfilment::applicable((array) ($menu->settings ?? []), $chosen, $subtotal);
        $amount  = \App\Modules\User\Support\MenuFulfilment::total($charges);

        return response()->json(['data' => ['bill' => [
            'subtotal'       => $subtotal,
            'fulfilment'     => $chosen,
            'charges'        => $charges,
            'charges_amount' => $amount,
            'total'          => round($subtotal + $amount, 2),
            'currency'       => $menu->currency,
            'is_estimate'    => true,
        ]]]);
    }

    protected function resolveMenu(string $alias): array
    {
        $link = Link::resolveByAlias($alias, request()->getHost());

        if (!$link || $link->type !== Link::TYPE_STORE_MENU || !$link->is_active) {
            return [null, null];
        }

        $menu = $link->storeMenu()->first();

        return [$link, $menu];
    }

    /**
     * Enforce the link's visibility tier on the order POST, mirroring the
     * public page gate so a private/registered/followers/subscribers store
     * can't be ordered against by an unauthorized visitor. Returns a JSON
     * error response when gated, or null to proceed.
     */
    protected function orderVisibilityGate(Request $request, Link $link)
    {
        if ($request->attributes->get('staff_order_link') === (int) $link->id) return null;

        $vis = $link->visibility ?? 'public';
        if ($vis === 'public') return null;

        $viewer   = $request->user();
        $viewerId = \App\Modules\Common\Services\ViewerSession::id() ?: optional($viewer)->id;
        if ($viewerId && (int) $viewerId === (int) $link->user_id) return null;

        if (!$viewerId) {
            return response()->json(['error' => ['message' => 'Sign in required to order from this store', 'code' => 'auth_required']], 401);
        }
        if ($vis === 'registered') return null;

        $owner = $link->user;
        if ($vis === 'followers') {
            $ok = \App\Modules\User\Models\Follow::where('follower_id', $viewerId)
                ->where('creator_id', $owner->id)->exists();
            return $ok ? null : response()->json(['error' => ['message' => 'Follow this creator to order', 'code' => 'follow_required']], 403);
        }
        if ($vis === 'subscribers') {
            $email = $viewer?->email;
            $ok = $email && \App\Modules\User\Models\Subscriber::where('user_id', $owner->id)
                ->where('email', $email)->where('status', 'active')->exists();
            return $ok ? null : response()->json(['error' => ['message' => 'Subscribe to this creator to order', 'code' => 'subscribe_required']], 403);
        }

        return response()->json(['error' => ['message' => 'Not allowed', 'code' => 'forbidden']], 403);
    }

    /** Place an order request (order mode only). */
    public function placeOrder(Request $request, string $alias)
    {
        [$link, $menu] = $this->resolveMenu($alias);
        if (!$link || !$menu || !$link->isAccessible()) {
            return response()->json(['error' => ['message' => 'Store not found', 'code' => 'not_found']], 404);
        }
        if ($gate = $this->orderVisibilityGate($request, $link)) {
            return $gate;
        }
        if ((!$menu->isOrderMode() && $request->attributes->get('staff_order_link') !== (int) $link->id)) {
            return response()->json(['error' => ['message' => 'Ordering is not enabled for this store', 'code' => 'ordering_disabled']], 422);
        }
        if (!$menu->acceptingOrders() && $request->attributes->get('staff_order_link') !== (int) $link->id) {
            return response()->json(['error' => ['message' => 'This store is not accepting requests right now', 'code' => 'orders_paused']], 422);
        }

        $data = $request->validate([
            // Sana, 2026-09-28: "name and phone mandatory". Same rule on
            // both page types, so a customer never learns one and meets
            // the other.
            'customer_name'       => 'required|string|max:120',
            'customer_phone'      => 'required|string|max:32|min:6',
            'wanted_at'           => 'nullable|string|max:40',
            'customer_contact'    => 'nullable|string|max:160',
            'customer_note'       => 'nullable|string|max:1000',
            'fulfilment'          => 'nullable|string|max:16',
            // Only required when the chosen handover needs somewhere to go.
            'customer_address'    => [
                'nullable', 'string', 'max:500',
                \Illuminate\Validation\Rule::requiredIf(fn () =>
                    \App\Modules\User\Support\MenuFulfilment::needsAddress($request->input('fulfilment'))
                ),
            ],
            'items'               => 'required|array|min:1',
            'items.*.product_id'  => 'required|integer',
            'items.*.quantity'    => 'required|integer|min:1|max:'.\App\Modules\User\Support\MenuBulkOrder::LINE_MAX,
            'items.*.note'        => 'nullable|string|max:300',
            'items.*.options'             => 'nullable|array|max:40',
            'items.*.options.*.option_id' => 'required|integer',
            'items.*.options.*.quantity'  => 'nullable|integer|min:1|max:20',
        ]);

        try {
            $order = $this->orders->place($link, $menu, $data);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => ['message' => $e->getMessage(), 'code' => 'invalid_order']], 422);
        }

        $order->loadMissing('items');

        return response()->json(['data' => [
            'order' => array_merge($this->serializeGuestOrder($order), [
                'whatsapp' => \App\Modules\Common\Services\WhatsappOrderLink::build($menu, $order, $link->title),
            ]),
        ]], 201);
    }

    /** Public guest-order shape. */
    public static function serializeGuestOrder(StoreOrder $order): array
    {
        return [
            'public_token' => $order->public_token,
            // Sana, 2026-10-05: "QR code per order". The identifier was
            // already here; this is the form the QR carries and the form
            // the counter compares, both from MenuOrderCode so the two
            // ends cannot drift apart.
            'order_code'   => \App\Modules\User\Support\MenuOrderCode::of($order->public_token),
            'token_number' => $order->token_number,
            'wanted_at'    => $order->wanted_at?->toIso8601String(),
            'meal_coupons' => \App\Modules\User\Models\MenuOrderCoupon::where('order_type', 'store')
                ->where('order_id', $order->id)
                ->orderBy('id')
                ->get()
                ->map(fn ($c) => ['code' => $c->display(), 'item_name' => $c->item_name])
                ->all(),
            'token_period' => $order->token_period,
            'status'       => $order->status,
            'status_label' => $order->status_label,
            'subtotal'     => $order->subtotal,
            'fulfilment'   => $order->fulfilment,
            'charges'      => $order->charges ?: [],
            'charges_amount' => $order->charges_amount,
            'total'        => $order->total,
            'currency'     => $order->currency,
            'is_estimate'  => true,
            'items'        => $order->relationLoaded('items')
                ? $order->items->map(fn ($i) => [
                    'name'       => $i->name,
                    'quantity'   => $i->quantity,
                    'line_total' => $i->line_total,
                    'options'    => $i->options ?: null,
                    'options_label' => \App\Modules\Common\Services\MenuCartPricer::describe($i->options),
                ])->all()
                : [],
        ];
    }

    /** Guest polls their own order status with the public token. */
    public function orderStatus(Request $request, string $token)
    {
        $order = StoreOrder::with(['items', 'menu', 'link'])->where('public_token', $token)->first();
        if (!$order) {
            return response()->json(['error' => ['message' => 'Order not found', 'code' => 'not_found']], 404);
        }

        $whatsapp = $order->menu
            ? \App\Modules\Common\Services\WhatsappOrderLink::build($order->menu, $order, $order->link?->title)
            : null;

        return response()->json(['data' => [
            'order' => array_merge(self::serializeGuestOrder($order), [
                'whatsapp'   => $whatsapp,
                'created_at' => $order->created_at?->toIso8601String(),
            ]),
        ]]);
    }
}
