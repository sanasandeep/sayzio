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
 * Unlike the restaurant flow there is NO quote endpoint: with no coupons or
 * tax to validate server-side, the total is simply the sum of line prices.
 */
class PublicStoreController extends Controller
{
    public function __construct(
        protected StoreOrderService $orders,
    ) {
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
        if (!$menu->isOrderMode()) {
            return response()->json(['error' => ['message' => 'Ordering is not enabled for this store', 'code' => 'ordering_disabled']], 422);
        }
        if (!$menu->acceptingOrders()) {
            return response()->json(['error' => ['message' => 'This store is not accepting requests right now', 'code' => 'orders_paused']], 422);
        }

        $data = $request->validate([
            'customer_name'       => 'nullable|string|max:120',
            'customer_contact'    => 'nullable|string|max:160',
            'customer_note'       => 'nullable|string|max:1000',
            'items'               => 'required|array|min:1',
            'items.*.product_id'  => 'required|integer',
            'items.*.quantity'    => 'required|integer|min:1|max:99',
            'items.*.note'        => 'nullable|string|max:300',
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
            'status'       => $order->status,
            'status_label' => $order->status_label,
            'subtotal'     => $order->subtotal,
            'total'        => $order->total,
            'currency'     => $order->currency,
            'is_estimate'  => true,
            'items'        => $order->relationLoaded('items')
                ? $order->items->map(fn ($i) => [
                    'name'       => $i->name,
                    'quantity'   => $i->quantity,
                    'line_total' => $i->line_total,
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
