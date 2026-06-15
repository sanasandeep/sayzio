<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Services\RestaurantOrderService;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantOrder;
use App\Modules\User\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * REST API parity for the restaurant menu page (Task #1536).
 *
 * Public (optional auth): fetch a menu by alias, place an order, poll a
 * guest order status by its public token.
 * Authenticated (Sanctum): owner orders list, incremental poll, and
 * status updates — mirrors the web orders dashboard for the mobile app.
 *
 * Unified `{data}` / `{error}` envelope via ApiResponses. No online
 * payment — guests pay staff directly.
 */
class RestaurantController extends Controller
{
    use ApiResponses;

    public function __construct(protected RestaurantOrderService $orders)
    {
    }

    // ── Public ───────────────────────────────────────────────────

    /** Public menu fetch by alias (display + order mode). */
    public function show(Request $request, string $alias)
    {
        $link = Link::where('alias', $alias)
            ->where('type', Link::TYPE_RESTAURANT_MENU)
            ->first();

        if (!$link || !$link->is_active || !$link->isAccessible()) {
            return $this->notFound('Menu not found');
        }

        if ($gate = $this->checkVisibility($link, $request->user())) {
            return $this->fail($gate['message'], $gate['status'], $gate['code']);
        }

        $menu = $link->restaurantMenu()->first();
        if (!$menu) {
            return $this->notFound('Menu not found');
        }

        $menu->load([
            'categories' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
            'items'      => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
        ]);

        $table = null;
        if ($code = $request->query('t')) {
            $table = $menu->tables()->where('code', $code)->first();
        }

        $itemsByCat = $menu->items->groupBy('category_id');

        return $this->ok([
            'menu' => [
                'mode'         => $menu->mode,
                'currency'     => $menu->currency,
                'accent_color' => $menu->accent_color,
                'order_enabled'=> $menu->isOrderMode(),
            ],
            'link' => [
                'alias' => $link->alias,
                'title' => $link->title,
            ],
            'table' => $table ? ['code' => $table->code, 'label' => $table->label] : null,
            'categories' => $menu->categories->map(fn ($c) => [
                'id'          => $c->id,
                'name'        => $c->name,
                'description' => $c->description,
                'items'       => ($itemsByCat->get($c->id) ?? collect())->map(fn ($i) => [
                    'id'          => $i->id,
                    'name'        => $i->name,
                    'description' => $i->description,
                    'price'       => $i->price,
                    'photo_url'   => $i->photo_url,
                    'is_sold_out' => (bool) $i->is_sold_out,
                ])->values(),
            ])->values(),
        ]);
    }

    /** Place an order (order mode only). */
    public function placeOrder(Request $request, string $alias)
    {
        $link = Link::where('alias', $alias)
            ->where('type', Link::TYPE_RESTAURANT_MENU)
            ->first();

        if (!$link || !$link->is_active || !$link->isAccessible()) {
            return $this->notFound('Menu not found');
        }

        if ($gate = $this->checkVisibility($link, $request->user())) {
            return $this->fail($gate['message'], $gate['status'], $gate['code']);
        }

        $menu = $link->restaurantMenu()->first();
        if (!$menu) {
            return $this->notFound('Menu not found');
        }
        if (!$menu->isOrderMode()) {
            return $this->fail('Ordering is not enabled for this menu', 422, 'ordering_disabled');
        }

        $data = $request->validate([
            'table_code'       => 'nullable|string|max:32',
            'customer_name'    => 'nullable|string|max:120',
            'customer_note'    => 'nullable|string|max:1000',
            'items'            => 'required|array|min:1',
            'items.*.item_id'  => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1|max:99',
            'items.*.note'     => 'nullable|string|max:300',
        ]);

        try {
            $order = $this->orders->place($link, $menu, $data);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422, 'invalid_order');
        }

        return $this->created(['order' => $this->guestOrder($order->fresh('items'))]);
    }

    /** Guest polls their own order status with the public token. */
    public function orderStatus(Request $request, string $token)
    {
        $order = RestaurantOrder::with('items')->where('public_token', $token)->first();
        if (!$order) {
            return $this->notFound('Order not found');
        }

        return $this->ok(['order' => $this->guestOrder($order)]);
    }

    /**
     * Visibility/access gating, mirroring BiolinkController so private,
     * registered-only, follower-only, and subscriber-only menus are enforced
     * on the public API exactly as on biolinks. Returns null when allowed.
     */
    protected function checkVisibility(Link $link, $viewer): ?array
    {
        $vis   = $link->visibility ?? 'public';
        $owner = $link->user;

        if ($vis === 'public') return null;
        if ($viewer && $owner && (int) $viewer->id === (int) $owner->id) return null;

        if (!$viewer) {
            return ['status' => 401, 'code' => 'auth_required', 'message' => 'Sign in required to view this menu'];
        }
        if ($vis === 'registered') return null;

        if ($vis === 'followers') {
            $follows = Follow::where('follower_id', $viewer->id)->where('creator_id', $owner->id)->exists();
            if ($follows) return null;
            return ['status' => 403, 'code' => 'follow_required', 'message' => 'Follow this creator to view'];
        }

        if ($vis === 'subscribers') {
            $isSub = Subscriber::where('user_id', $owner->id)
                ->where('email', $viewer->email)
                ->where('status', 'active')
                ->exists();
            if ($isSub) return null;
            return ['status' => 403, 'code' => 'subscribe_required', 'message' => 'Subscribe to this creator to view'];
        }

        return ['status' => 403, 'code' => 'forbidden', 'message' => 'Not allowed'];
    }

    // ── Owner (Sanctum) ──────────────────────────────────────────

    /** Resolve an owned restaurant-menu link or fail. */
    protected function ownedMenu(Request $request, Link $link): ?RestaurantMenu
    {
        if ($link->type !== Link::TYPE_RESTAURANT_MENU) {
            return null;
        }
        if ((int) $link->user_id !== (int) $request->user()->id) {
            return null;
        }

        return $link->restaurantMenu()->first()
            ?? RestaurantMenu::create([
                'link_id'  => $link->id,
                'user_id'  => $link->user_id,
                'mode'     => RestaurantMenu::MODE_DISPLAY,
                'currency' => 'USD',
            ]);
    }

    /** Owner: list recent orders for a menu. */
    public function ownerOrders(Request $request, Link $link)
    {
        $menu = $this->ownedMenu($request, $link);
        if (!$menu) {
            return $this->notFound('Menu not found');
        }

        $orders = RestaurantOrder::with('items')
            ->where('menu_id', $menu->id)
            ->latest()
            ->limit(100)
            ->get();

        return $this->ok([
            'orders'     => $orders->map(fn ($o) => $this->ownerOrder($o))->values(),
            'open_count' => $this->openCount($menu->id),
            'server_time'=> now()->toIso8601String(),
        ]);
    }

    /** Owner: incremental poll for new/updated orders. */
    public function ownerPoll(Request $request, Link $link)
    {
        $menu = $this->ownedMenu($request, $link);
        if (!$menu) {
            return $this->notFound('Menu not found');
        }

        $query = RestaurantOrder::with('items')->where('menu_id', $menu->id);
        if ($since = $request->query('since')) {
            try {
                $query->where('updated_at', '>', \Carbon\Carbon::parse($since));
            } catch (\Throwable $e) {
                // ignore bad cursor
            }
        }

        $orders = $query->latest('updated_at')->limit(100)->get();

        return $this->ok([
            'orders'     => $orders->map(fn ($o) => $this->ownerOrder($o))->values(),
            'open_count' => $this->openCount($menu->id),
            'server_time'=> now()->toIso8601String(),
        ]);
    }

    /** Owner: advance an order's status. */
    public function updateOrderStatus(Request $request, Link $link, RestaurantOrder $order)
    {
        $menu = $this->ownedMenu($request, $link);
        if (!$menu) {
            return $this->notFound('Menu not found');
        }
        if ((int) $order->menu_id !== (int) $menu->id) {
            return $this->notFound('Order not found');
        }

        $data = $request->validate([
            'status' => 'required|in:' . implode(',', RestaurantOrder::STATUSES),
        ]);

        if (!$order->canTransitionTo($data['status'])) {
            return $this->fail(
                "Can't move an order from '{$order->status}' to '{$data['status']}'",
                422,
                'invalid_transition',
            );
        }

        $order->update(['status' => $data['status']]);

        return $this->ok(['order' => $this->ownerOrder($order->fresh('items'))]);
    }

    // ── Serializers ──────────────────────────────────────────────

    protected function openCount(int $menuId): int
    {
        return RestaurantOrder::where('menu_id', $menuId)
            ->whereIn('status', RestaurantOrder::OPEN_STATUSES)
            ->count();
    }

    protected function guestOrder(RestaurantOrder $order): array
    {
        return [
            'public_token' => $order->public_token,
            'status'       => $order->status,
            'status_label' => $order->status_label,
            'subtotal'     => $order->subtotal,
            'currency'     => $order->currency,
            'table_label'  => $order->table_label,
            'items'        => $order->items->map(fn ($i) => [
                'name'       => $i->name,
                'quantity'   => $i->quantity,
                'line_total' => $i->line_total,
            ])->values(),
            'created_at'   => $order->created_at?->toIso8601String(),
        ];
    }

    protected function ownerOrder(RestaurantOrder $order): array
    {
        return [
            'id'            => $order->id,
            'status'        => $order->status,
            'status_label'  => $order->status_label,
            'table_label'   => $order->table_label,
            'customer_name' => $order->customer_name,
            'customer_note' => $order->customer_note,
            'subtotal'      => $order->subtotal,
            'currency'      => $order->currency,
            'created_at'    => $order->created_at?->toIso8601String(),
            'updated_at'    => $order->updated_at?->toIso8601String(),
            'items'         => $order->items->map(fn ($i) => [
                'id'         => $i->id,
                'name'       => $i->name,
                'quantity'   => $i->quantity,
                'unit_price' => $i->unit_price,
                'line_total' => $i->line_total,
                'note'       => $i->note,
            ])->values(),
        ];
    }
}
