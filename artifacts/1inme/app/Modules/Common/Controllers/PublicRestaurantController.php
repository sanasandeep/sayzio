<?php

namespace App\Modules\Common\Controllers;

use App\Modules\Common\Services\RestaurantOrderService;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
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
    public function __construct(protected RestaurantOrderService $orders)
    {
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
        if (!$menu->isOrderMode()) {
            return response()->json(['error' => ['message' => 'Ordering is not enabled for this menu', 'code' => 'ordering_disabled']], 422);
        }

        $data = $request->validate([
            'table_code'      => 'nullable|string|max:32',
            'customer_name'   => 'nullable|string|max:120',
            'customer_note'   => 'nullable|string|max:1000',
            'items'           => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.quantity'=> 'required|integer|min:1|max:99',
            'items.*.note'    => 'nullable|string|max:300',
        ]);

        try {
            $order = $this->orders->place($link, $menu, $data);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => ['message' => $e->getMessage(), 'code' => 'invalid_order']], 422);
        }

        $order->loadMissing('items');

        return response()->json(['data' => [
            'order' => [
                'public_token' => $order->public_token,
                'status'       => $order->status,
                'subtotal'     => $order->subtotal,
                'currency'     => $order->currency,
                'table_label'  => $order->table_label,
                'whatsapp'     => \App\Modules\Common\Services\WhatsappOrderLink::build($menu, $order, $link->title),
            ],
        ]], 201);
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
            'order' => [
                'public_token' => $order->public_token,
                'status'       => $order->status,
                'status_label' => $order->status_label,
                'subtotal'     => $order->subtotal,
                'currency'     => $order->currency,
                'table_label'  => $order->table_label,
                'items'        => $order->items->map(fn ($i) => [
                    'name'     => $i->name,
                    'quantity' => $i->quantity,
                    'line_total' => $i->line_total,
                ]),
                'whatsapp'     => $whatsapp,
                'created_at'   => $order->created_at?->toIso8601String(),
            ],
        ]]);
    }
}
