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
        if (!$link || !$menu || !$link->isAccessible() || !$menu->isOrderMode()) {
            return response()->json(['error' => ['message' => 'Menu not found', 'code' => 'not_found']], 404);
        }

        $data = $request->validate([
            'coupon_code'      => 'nullable|string|max:64',
            'items'            => 'required|array|min:1',
            'items.*.item_id'  => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1|max:99',
        ]);

        $subtotal = $this->subtotalFor($menu, $data['items']);
        $bill = $this->calculator->compute($menu, $subtotal, $data['coupon_code'] ?? null);

        return response()->json(['data' => ['bill' => $this->serializeBill($bill)]]);
    }

    /** Sum the live price of the requested cart lines for an order menu. */
    protected function subtotalFor(RestaurantMenu $menu, array $items): float
    {
        $ids = collect($items)->pluck('item_id')->map(fn ($i) => (int) $i)->all();
        $rows = RestaurantMenuItem::where('menu_id', $menu->id)
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $subtotal = 0.0;
        foreach ($items as $row) {
            $item = $rows->get((int) $row['item_id']);
            if (!$item || $item->is_sold_out) {
                continue;
            }
            $subtotal += round(((float) $item->price) * max(1, (int) $row['quantity']), 2);
        }

        return round($subtotal, 2);
    }

    /** Shape a calculator breakdown into the public estimate payload. */
    public static function serializeBill(array $bill): array
    {
        return [
            'subtotal'        => round($bill['subtotal'], 2),
            'coupon_code'     => $bill['coupon_code'],
            'coupon_applied'  => $bill['coupon_applied'],
            'coupon_error'    => $bill['coupon_error'],
            'discount_amount' => round($bill['discount_amount'], 2),
            'tax_enabled'     => $bill['tax_enabled'],
            'tax_inclusive'   => $bill['tax_inclusive'],
            'tax_rate'        => $bill['tax_rate'],
            'tax_label'       => $bill['tax_label'],
            'tax_amount'      => round($bill['tax_amount'], 2),
            'total'           => round($bill['total'], 2),
            'currency'        => $bill['currency'],
            'is_estimate'     => true,
        ];
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
            'coupon_code'     => 'nullable|string|max:64',
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
            'status'       => $order->status,
            'status_label' => $order->status_label,
            'subtotal'     => $order->subtotal,
            'coupon_code'  => $order->coupon_code,
            'discount_amount' => $order->discount_amount,
            'tax_inclusive'   => (bool) $order->tax_inclusive,
            'tax_rate'        => $order->tax_rate,
            'tax_amount'      => $order->tax_amount,
            'total'           => $order->total,
            'currency'        => $order->currency,
            'table_label'     => $order->table_label,
            'is_estimate'     => true,
            'items'           => $order->relationLoaded('items')
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
