<?php

namespace App\Modules\Common\Controllers;

use App\Modules\Common\Services\RestaurantBillCalculator;
use App\Modules\Common\Services\RestaurantOrderService;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\RestaurantOrder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Visitor-facing restaurant menu endpoints. Mounted under the `/rm/`
 * prefix so they never collide with the catch-all `/{alias}` route.
 * No authentication and no online payment — guests pay staff directly.
 */
class PublicRestaurantController extends Controller
{
    public function __construct(
        protected RestaurantOrderService $orders,
        protected RestaurantBillCalculator $calculator,
    ) {
    }

    /**
     * Live estimated-bill quote for the cart the guest is building. Validates
     * the coupon server-side (so codes never leak to the page) and returns the
     * full itemised breakdown. No order is created.
     */
    public function quote(Request $request, string $alias)
    {
        [$link, $menu] = $this->resolveMenu($alias);
        if (!$link || !$menu || !$link->isAccessible() || (!$menu->isOrderMode() && $request->attributes->get('staff_order_link') !== (int) $link->id)) {
            return response()->json(['error' => ['message' => 'Menu not found', 'code' => 'not_found']], 404);
        }

        $data = $request->validate([
            'coupon_code'      => 'nullable|string|max:64',
            'fulfilment'       => 'nullable|string|max:16',
            'items'            => 'required|array|min:1',
            'items.*.item_id'  => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1|max:'.\App\Modules\User\Support\MenuBulkOrder::LINE_MAX,
            'items.*.options'             => 'nullable|array|max:40',
            'items.*.options.*.option_id' => 'required|integer',
            'items.*.options.*.quantity'  => 'nullable|integer|min:1|max:20',
        ]);

        // The quote and the order price through the same function, so a
        // cart quoted at one number cannot be charged at another.
        try {
            $priced = app(\App\Modules\Common\Services\MenuCartPricer::class)->price($menu, $data['items']);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => ['message' => $e->getMessage(), 'code' => 'invalid_cart'],
            ], 422);
        }

        $bill = $this->calculator->compute($menu, $priced['subtotal'], $data['coupon_code'] ?? null, $data['fulfilment'] ?? null);

        return response()->json(['data' => ['bill' => $this->serializeBill($bill)]]);
    }

    /**
     * Sum the live price of the requested cart lines.
     *
     * Kept as a method because other code calls it, but it is one caller of
     * MenuCartPricer now rather than a second implementation of it -- the
     * arithmetic that used to live here is the arithmetic the order used,
     * written twice.
     */
    protected function subtotalFor(RestaurantMenu $menu, array $items): float
    {
        return app(\App\Modules\Common\Services\MenuCartPricer::class)->price($menu, $items)['subtotal'];
    }

    /** Shape a calculator breakdown into the public estimate payload. */
    public static function serializeBill(array $bill): array
    {
        return [
            'billing_company' => $bill['billing_company'] ?? null,
            'subtotal'        => round($bill['subtotal'], 2),
            'fulfilment'      => $bill['fulfilment'] ?? null,
            'charges'         => $bill['charges'] ?? [],
            'charges_amount'  => round($bill['charges_amount'] ?? 0, 2),
            'coupon_code'     => $bill['coupon_code'],
            'coupon_applied'  => $bill['coupon_applied'],
            'coupon_error'    => $bill['coupon_error'],
            'discount_amount' => round($bill['discount_amount'], 2),
            'tax_enabled'     => $bill['tax_enabled'],
            'tax_inclusive'   => $bill['tax_inclusive'],
            'tax_rate'        => $bill['tax_rate'],
            'tax_label'       => $bill['tax_label'],
            'tax_amount'      => round($bill['tax_amount'], 2),
            'tax_breakdown' => $bill['tax_breakdown'] ?? [],
            'total'           => round($bill['total'], 2),
            'currency'        => $bill['currency'],
            'is_estimate'     => true,
        ];
    }

    public function prebookCoupon(Request $request, string $alias)
    {
        [$link, $menu] = $this->resolveMenu($alias);
        if (!$link || !$menu || !$link->isAccessible() || !$menu->isOrderMode()) {
            return response()->json(['error' => ['message' => 'Menu unavailable']], 404);
        }
        if ($gate = $this->orderVisibilityGate($request, $link)) return $gate;
        $data = $request->validate([
            'coupon_code' => 'required|string|max:64', 'wanted_at' => 'required|string|max:40',
            'fulfilment' => 'required|string|max:16', 'customer_name' => 'required|string|max:150',
            'customer_phone' => 'nullable|string|max:40', 'customer_address' => 'nullable|string|max:1000',
        ]);
        try {
            $order = app(\App\Modules\Common\Services\MenuCouponPrebooking::class)->reserve($link, $menu, 'restaurant', $data);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => ['message' => $e->getMessage()]], 422);
        }
        return response()->json(['data' => ['booking_id' => $order->id, 'wanted_at' => $order->wanted_at->toIso8601String(), 'message' => 'Coupon prebooking confirmed. Show your coupon at collection.']], 201);
    }

    protected function resolveMenu(string $alias): array
    {
        $link = Link::resolveByAlias($alias, request()->getHost());

        if (!$link || $link->type !== Link::TYPE_RESTAURANT_MENU || !$link->is_active) {
            return [null, null];
        }

        $menu = $link->restaurantMenu()->first();

        return [$link, $menu];
    }

    /**
     * Enforce the link's visibility tier on the order POST, mirroring the
     * public page gate in RedirectController so a private/registered/
     * followers/subscribers menu can't be ordered against by an unauthorized
     * visitor. Returns a JSON error response when gated, or null to proceed.
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
            return response()->json(['error' => ['message' => 'Sign in required to order from this menu', 'code' => 'auth_required']], 401);
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

    /** Place an order (order mode only). */
    public function placeOrder(Request $request, string $alias)
    {
        [$link, $menu] = $this->resolveMenu($alias);
        if (!$link || !$menu || !$link->isAccessible()) {
            return response()->json(['error' => ['message' => 'Menu not found', 'code' => 'not_found']], 404);
        }
        if ($gate = $this->orderVisibilityGate($request, $link)) {
            return $gate;
        }
        if ((!$menu->isOrderMode() && $request->attributes->get('staff_order_link') !== (int) $link->id)) {
            return response()->json(['error' => ['message' => 'Ordering is not enabled for this menu', 'code' => 'ordering_disabled']], 422);
        }

        $data = $request->validate([
            'table_code'      => 'nullable|string|max:32',
            // A guest who typed a table number instead of scanning its QR.
            // The box was on the page and its value was never sent.
            'table_label'     => 'nullable|string|max:32',
            'wanted_at'       => 'nullable|string|max:40',
            // Sana, 2026-09-28: "name and phone mandatory". Required on
            // every handover type, dine-in included, so the kitchen always
            // has someone to call when an order goes wrong.
            'customer_name'   => 'required|string|max:120',
            'customer_phone'  => 'required|string|max:32|min:6',
            'customer_note'   => 'nullable|string|max:1000',
            'coupon_code'     => 'nullable|string|max:64',
            'fulfilment'      => 'nullable|string|max:16',
            // Only required when the chosen mode needs somewhere to go.
            // Making it always-required would put an address field in front
            // of every dine-in guest at a table.
            'customer_address' => [
                'nullable', 'string', 'max:500',
                \Illuminate\Validation\Rule::requiredIf(fn () =>
                    \App\Modules\User\Support\MenuFulfilment::needsAddress($request->input('fulfilment'))
                ),
            ],
            'items'           => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.quantity'=> 'required|integer|min:1|max:'.\App\Modules\User\Support\MenuBulkOrder::LINE_MAX,
            'items.*.note'    => 'nullable|string|max:300',
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

    /** Public guest-order shape, including the estimated-bill breakdown. */
    public static function serializeGuestOrder(RestaurantOrder $order): array
    {
        return [
            'public_token' => $order->public_token,
            // Sana, 2026-10-05: "QR code per order". The identifier was
            // already here; this is the form the QR carries and the form
            // the counter compares, both from MenuOrderCode so the two
            // ends cannot drift apart.
            'order_code'   => \App\Modules\User\Support\MenuOrderCode::of($order->public_token),
            // The number the guest is told to listen for, and which run of
            // numbers it belongs to.
            'billing_company' => $order->meta['billing_company'] ?? null,
            'token_number' => $order->token_number,
            'wanted_at'    => $order->wanted_at?->toIso8601String(),
            // The codes this order produced, so the person who placed it
            // can hand them out. Empty on every ordinary order.
            'meal_coupons' => \App\Modules\User\Models\MenuOrderCoupon::where('order_type', 'restaurant')
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
            'coupon_code'  => $order->coupon_code,
            'discount_amount' => $order->discount_amount,
            'tax_inclusive'   => (bool) $order->tax_inclusive,
            'tax_rate'        => $order->tax_rate,
            'tax_amount'      => $order->tax_amount,
            'tax_label' => $order->meta['tax_label'] ?? 'Tax',
            'tax_breakdown' => $order->meta['tax_breakdown'] ?? [],
            'total'           => $order->total,
            'currency'        => $order->currency,
            'table_label'     => $order->table_label,
            'is_estimate'     => true,
            'items'           => $order->relationLoaded('items')
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
        $order = RestaurantOrder::with(['items', 'menu', 'link'])->where('public_token', $token)->first();
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
