<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Services\RestaurantOrderService;
use App\Modules\Common\Support\SitePagesContent;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuCategory;
use App\Modules\User\Models\RestaurantMenuCoupon;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\RestaurantOrder;
use App\Modules\User\Models\RestaurantTable;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\UserFile;
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
        $link = Link::resolveByAlias($alias, $request->getHost());

        if (!$link || $link->type !== Link::TYPE_RESTAURANT_MENU || !$link->is_active || !$link->isAccessible()) {
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
                'tax'          => $this->taxPayload($menu),
            ],
            'link' => [
                'alias' => $link->alias,
                'title' => $link->title,
            ],
            'table' => $table ? ['code' => $table->code, 'label' => $table->label] : null,
            'pairings' => SitePagesContent::linkTypePairingsFor('restaurant_menu'),
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
        $link = Link::resolveByAlias($alias, $request->getHost());

        if (!$link || $link->type !== Link::TYPE_RESTAURANT_MENU || !$link->is_active || !$link->isAccessible()) {
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
            'coupon_code'      => 'nullable|string|max:64',
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

        return $this->created(['order' => $this->guestOrder($order->fresh('items'), $menu, $link)]);
    }

    /**
     * Live estimated-bill quote for a cart (mobile parity with the web quote
     * endpoint). Validates the coupon server-side and returns the itemised
     * breakdown without creating an order.
     */
    public function quote(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());

        if (!$link || $link->type !== Link::TYPE_RESTAURANT_MENU || !$link->is_active || !$link->isAccessible()) {
            return $this->notFound('Menu not found');
        }

        if ($gate = $this->checkVisibility($link, $request->user())) {
            return $this->fail($gate['message'], $gate['status'], $gate['code']);
        }

        $menu = $link->restaurantMenu()->first();
        if (!$menu || !$menu->isOrderMode()) {
            return $this->fail('Ordering is not enabled for this menu', 422, 'ordering_disabled');
        }

        $data = $request->validate([
            'coupon_code'      => 'nullable|string|max:64',
            'items'            => 'required|array|min:1',
            'items.*.item_id'  => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1|max:99',
        ]);

        $ids = collect($data['items'])->pluck('item_id')->map(fn ($i) => (int) $i)->all();
        $rows = RestaurantMenuItem::where('menu_id', $menu->id)
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $subtotal = 0.0;
        foreach ($data['items'] as $row) {
            $item = $rows->get((int) $row['item_id']);
            if (!$item || $item->is_sold_out) {
                continue;
            }
            $subtotal += round(((float) $item->price) * max(1, (int) $row['quantity']), 2);
        }

        $bill = app(\App\Modules\Common\Services\RestaurantBillCalculator::class)
            ->compute($menu, round($subtotal, 2), $data['coupon_code'] ?? null);

        return $this->ok(['bill' => [
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
        ]]);
    }

    /** Guest polls their own order status with the public token. */
    public function orderStatus(Request $request, string $token)
    {
        $order = RestaurantOrder::with(['items', 'menu', 'link'])->where('public_token', $token)->first();
        if (!$order) {
            return $this->notFound('Order not found');
        }

        return $this->ok(['order' => $this->guestOrder($order, $order->menu, $order->link)]);
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

    // ── Owner builder (Sanctum) ──────────────────────────────────

    /** Owner: full menu config — settings, categories+items, tables. */
    public function ownerMenu(Request $request, Link $link)
    {
        $menu = $this->ownedMenu($request, $link);
        if (!$menu) {
            return $this->notFound('Menu not found');
        }

        return $this->ok(['menu' => $this->ownerMenuPayload($menu, $link)]);
    }

    /** Owner: update menu settings (mode/currency/accent). */
    public function saveMenuSettings(Request $request, Link $link)
    {
        $menu = $this->ownedMenu($request, $link);
        if (!$menu) {
            return $this->notFound('Menu not found');
        }

        $data = $request->validate([
            'mode'            => 'required|in:display,order',
            'currency'        => 'required|string|size:3',
            'accent_color'    => 'nullable|string|max:16',
            'whatsapp_number' => 'nullable|string|max:32',
            'tax_enabled'     => 'sometimes|boolean',
            'tax_rate'        => 'nullable|numeric|min:0|max:100',
            'tax_inclusive'   => 'sometimes|boolean',
            'tax_label'       => 'nullable|string|max:24',
        ]);

        $settings = $menu->settings ?? [];

        // Optional WhatsApp click-to-chat number for order confirmations,
        // normalized to wa.me's digits-only form. Blank/invalid clears it.
        if ($request->has('whatsapp_number')) {
            $normalized = \App\Modules\Common\Services\WhatsappOrderLink::normalizeNumber($data['whatsapp_number'] ?? null);
            if ($normalized) {
                $settings['whatsapp_number'] = $normalized;
            } else {
                unset($settings['whatsapp_number']);
            }
        }

        // Menu-level GST/tax config lives in the `settings` JSON; mirror the
        // web editor's saveSettings so mobile owners can set it too.
        if ($request->has('tax_enabled') || $request->has('tax_rate')
            || $request->has('tax_inclusive') || $request->has('tax_label')) {
            $settings['tax'] = [
                'enabled'   => (bool) ($data['tax_enabled'] ?? false),
                'rate'      => round((float) ($data['tax_rate'] ?? 0), 3),
                'inclusive' => (bool) ($data['tax_inclusive'] ?? false),
                'label'     => trim((string) ($data['tax_label'] ?? 'GST')) ?: 'GST',
            ];
        }

        $menu->update([
            'mode'         => $data['mode'],
            'currency'     => strtoupper($data['currency']),
            'accent_color' => $data['accent_color'] ?? $menu->accent_color,
            'settings'     => $settings,
        ]);

        return $this->ok(['menu' => $this->ownerMenuPayload($menu->fresh(), $link)]);
    }

    // ── Coupons (Task #3070) ─────────────────────────────────────
    // Owner CRUD for menu coupon codes, mirroring the web
    // RestaurantMenuController. Single coupon per order, percentage or
    // fixed amount, optional minimum bill and an active toggle.

    public function storeCoupon(Request $request, Link $link)
    {
        $menu = $this->ownedMenu($request, $link);
        if (!$menu) {
            return $this->notFound('Menu not found');
        }

        $data = $this->validateCoupon($request);
        $code = RestaurantMenuCoupon::normalizeCode($data['code']);

        if ($menu->coupons()->where('code', $code)->exists()) {
            return $this->fail('A coupon with that code already exists on this menu.', 422, 'duplicate_code');
        }

        $coupon = $menu->coupons()->create([
            'code'           => $code,
            'discount_type'  => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'min_subtotal'   => $data['min_subtotal'] ?? 0,
            'is_active'      => (bool) ($data['is_active'] ?? true),
        ]);

        return $this->created(['coupon' => $this->ownerCoupon($coupon)]);
    }

    public function updateCoupon(Request $request, Link $link, RestaurantMenuCoupon $coupon)
    {
        $menu = $this->ownedMenu($request, $link);
        if (!$menu || (int) $coupon->menu_id !== (int) $menu->id) {
            return $this->notFound('Coupon not found');
        }

        $data = $this->validateCoupon($request);
        $code = RestaurantMenuCoupon::normalizeCode($data['code']);

        if ($menu->coupons()->where('code', $code)->where('id', '!=', $coupon->id)->exists()) {
            return $this->fail('A coupon with that code already exists on this menu.', 422, 'duplicate_code');
        }

        $coupon->update([
            'code'           => $code,
            'discount_type'  => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'min_subtotal'   => $data['min_subtotal'] ?? 0,
            'is_active'      => (bool) ($data['is_active'] ?? true),
        ]);

        return $this->ok(['coupon' => $this->ownerCoupon($coupon->fresh())]);
    }

    public function destroyCoupon(Request $request, Link $link, RestaurantMenuCoupon $coupon)
    {
        $menu = $this->ownedMenu($request, $link);
        if (!$menu || (int) $coupon->menu_id !== (int) $menu->id) {
            return $this->notFound('Coupon not found');
        }

        $coupon->delete();

        return $this->ok(['deleted' => true]);
    }

    protected function validateCoupon(Request $request): array
    {
        return $request->validate([
            'code'           => 'required|string|max:64',
            'discount_type'  => 'required|in:percent,fixed',
            'discount_value' => 'required|numeric|min:0|max:9999999',
            'min_subtotal'   => 'nullable|numeric|min:0|max:9999999',
            'is_active'      => 'sometimes|boolean',
        ]);
    }

    // ── Categories ───────────────────────────────────────────────
    public function storeCategory(Request $request, Link $link)
    {
        $menu = $this->ownedMenu($request, $link);
        if (!$menu) {
            return $this->notFound('Menu not found');
        }

        $data = $request->validate([
            'name'        => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
        ]);

        $category = RestaurantMenuCategory::create([
            'menu_id'     => $menu->id,
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'sort_order'  => (int) RestaurantMenuCategory::where('menu_id', $menu->id)->max('sort_order') + 1,
        ]);

        return $this->created(['category' => $this->ownerCategory($category, collect())]);
    }

    public function updateCategory(Request $request, Link $link, RestaurantMenuCategory $category)
    {
        $menu = $this->ownedMenu($request, $link);
        if (!$menu || (int) $category->menu_id !== (int) $menu->id) {
            return $this->notFound('Category not found');
        }

        $data = $request->validate([
            'name'        => 'sometimes|required|string|max:120',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'sometimes|boolean',
        ]);

        $category->update($data);

        $items = $menu->items()->where('category_id', $category->id)->get();

        return $this->ok(['category' => $this->ownerCategory($category->fresh(), $items)]);
    }

    public function destroyCategory(Request $request, Link $link, RestaurantMenuCategory $category)
    {
        $menu = $this->ownedMenu($request, $link);
        if (!$menu || (int) $category->menu_id !== (int) $menu->id) {
            return $this->notFound('Category not found');
        }

        RestaurantMenuItem::where('category_id', $category->id)->delete();
        $category->delete();

        return $this->ok(['deleted' => true]);
    }

    // ── Items ────────────────────────────────────────────────────
    public function storeItem(Request $request, Link $link)
    {
        $menu = $this->ownedMenu($request, $link);
        if (!$menu) {
            return $this->notFound('Menu not found');
        }

        $data = $request->validate([
            'category_id' => 'required|integer',
            'name'        => 'required|string|max:160',
            'description' => 'nullable|string|max:800',
            'price'       => 'nullable|numeric|min:0|max:9999999',
            'photo_url'   => 'nullable|string|max:1024',
            'is_sold_out' => 'sometimes|boolean',
        ]);

        $category = RestaurantMenuCategory::where('menu_id', $menu->id)->find($data['category_id']);
        if (!$category) {
            return $this->fail('Category not found', 422, 'invalid_category');
        }

        $item = RestaurantMenuItem::create([
            'menu_id'     => $menu->id,
            'category_id' => $category->id,
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'price'       => $data['price'] ?? 0,
            'photo_url'   => $data['photo_url'] ?? null,
            'is_sold_out' => (bool) ($data['is_sold_out'] ?? false),
            'sort_order'  => (int) RestaurantMenuItem::where('category_id', $category->id)->max('sort_order') + 1,
        ]);

        return $this->created(['item' => $this->ownerItem($item)]);
    }

    public function updateItem(Request $request, Link $link, RestaurantMenuItem $item)
    {
        $menu = $this->ownedMenu($request, $link);
        if (!$menu || (int) $item->menu_id !== (int) $menu->id) {
            return $this->notFound('Item not found');
        }

        $data = $request->validate([
            'category_id' => 'sometimes|integer',
            'name'        => 'sometimes|required|string|max:160',
            'description' => 'nullable|string|max:800',
            'price'       => 'sometimes|numeric|min:0|max:9999999',
            'photo_url'   => 'nullable|string|max:1024',
            'is_sold_out' => 'sometimes|boolean',
            'is_active'   => 'sometimes|boolean',
        ]);

        if (isset($data['category_id'])) {
            $owned = RestaurantMenuCategory::where('menu_id', $menu->id)->find($data['category_id']);
            if (!$owned) {
                return $this->fail('Category not found', 422, 'invalid_category');
            }
        }

        $item->update($data);

        return $this->ok(['item' => $this->ownerItem($item->fresh())]);
    }

    public function destroyItem(Request $request, Link $link, RestaurantMenuItem $item)
    {
        $menu = $this->ownedMenu($request, $link);
        if (!$menu || (int) $item->menu_id !== (int) $menu->id) {
            return $this->notFound('Item not found');
        }

        $item->delete();

        return $this->ok(['deleted' => true]);
    }

    /** Owner: upload a photo for a menu item; returns the public URL. */
    public function uploadItemPhoto(Request $request, Link $link)
    {
        $menu = $this->ownedMenu($request, $link);
        if (!$menu) {
            return $this->notFound('Menu not found');
        }

        $request->validate([
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $user = $request->user();

        try {
            $userFile = UserFile::createFromUpload($request->file('photo'), $user, [
                'max_size_mb'    => 5,
                'compress_image' => true,
                'max_width'      => 1000,
                'max_height'     => 1000,
                'quality'        => 85,
            ]);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422, 'upload_failed');
        }

        // The sanctum API path doesn't bind the active workspace, so the
        // shared createFromUpload() lands the vault file with workspace_id =
        // null. workspace_id isn't mass-assignable, so set it directly.
        if ($userFile->workspace_id === null) {
            $userFile->workspace_id = $this->activeWorkspaceId($user);
            $userFile->save();
        }

        return $this->ok(['photo_url' => $userFile->url]);
    }

    // ── Tables (order mode) ──────────────────────────────────────
    public function storeTable(Request $request, Link $link)
    {
        $menu = $this->ownedMenu($request, $link);
        if (!$menu) {
            return $this->notFound('Menu not found');
        }

        $data = $request->validate(['label' => 'required|string|max:80']);

        $table = RestaurantTable::create([
            'menu_id'    => $menu->id,
            'label'      => $data['label'],
            'sort_order' => (int) RestaurantTable::where('menu_id', $menu->id)->max('sort_order') + 1,
        ]);

        return $this->created(['table' => $this->ownerTable($table->fresh(), $link)]);
    }

    public function destroyTable(Request $request, Link $link, RestaurantTable $table)
    {
        $menu = $this->ownedMenu($request, $link);
        if (!$menu || (int) $table->menu_id !== (int) $menu->id) {
            return $this->notFound('Table not found');
        }

        $table->delete();

        return $this->ok(['deleted' => true]);
    }

    // ── Serializers ──────────────────────────────────────────────

    /** Full owner-facing menu (includes inactive rows the builder edits). */
    protected function ownerMenuPayload(RestaurantMenu $menu, Link $link): array
    {
        $menu->load(['categories', 'items', 'tables', 'coupons']);
        $itemsByCat = $menu->items->groupBy('category_id');

        return [
            'mode'            => $menu->mode,
            'currency'        => $menu->currency,
            'accent_color'    => $menu->accent_color,
            'whatsapp_number' => $menu->settings['whatsapp_number'] ?? null,
            'order_enabled'   => $menu->isOrderMode(),
            'public_url'      => url('/' . $link->alias),
            'tax'             => $this->taxPayload($menu),
            'categories'   => $menu->categories->map(fn ($c) => $this->ownerCategory(
                $c,
                $itemsByCat->get($c->id) ?? collect(),
            ))->values(),
            'tables'       => $menu->tables->map(fn ($t) => $this->ownerTable($t, $link))->values(),
            'coupons'      => $menu->coupons->map(fn ($c) => $this->ownerCoupon($c))->values(),
        ];
    }

    protected function ownerCoupon(RestaurantMenuCoupon $coupon): array
    {
        return [
            'id'             => $coupon->id,
            'code'           => $coupon->code,
            'discount_type'  => $coupon->discount_type,
            'discount_value' => $coupon->discount_value,
            'min_subtotal'   => $coupon->min_subtotal,
            'is_active'      => (bool) $coupon->is_active,
        ];
    }

    protected function ownerCategory(RestaurantMenuCategory $category, $items): array
    {
        return [
            'id'          => $category->id,
            'name'        => $category->name,
            'description' => $category->description,
            'is_active'   => (bool) ($category->is_active ?? true),
            'items'       => collect($items)->map(fn ($i) => $this->ownerItem($i))->values(),
        ];
    }

    protected function ownerItem(RestaurantMenuItem $item): array
    {
        return [
            'id'          => $item->id,
            'category_id' => $item->category_id,
            'name'        => $item->name,
            'description' => $item->description,
            'price'       => $item->price,
            'photo_url'   => $item->photo_url,
            'is_sold_out' => (bool) $item->is_sold_out,
            'is_active'   => (bool) ($item->is_active ?? true),
        ];
    }

    protected function ownerTable(RestaurantTable $table, Link $link): array
    {
        return [
            'id'        => $table->id,
            'label'     => $table->label,
            'code'      => $table->code,
            'order_url' => url('/' . $link->alias) . '?t=' . $table->code,
        ];
    }

    protected function openCount(int $menuId): int
    {
        return RestaurantOrder::where('menu_id', $menuId)
            ->whereIn('status', RestaurantOrder::OPEN_STATUSES)
            ->count();
    }

    protected function guestOrder(RestaurantOrder $order, ?RestaurantMenu $menu = null, ?Link $link = null): array
    {
        $whatsapp = ($menu && $link)
            ? \App\Modules\Common\Services\WhatsappOrderLink::build($menu, $order, $link->title)
            : null;

        return array_merge([
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
            'whatsapp'     => $whatsapp,
            'created_at'   => $order->created_at?->toIso8601String(),
        ], $this->orderBreakdown($order));
    }

    /** Menu-level estimated-tax config for the public + owner payloads. */
    protected function taxPayload(RestaurantMenu $menu): array
    {
        return [
            'enabled'   => $menu->taxEnabled(),
            'rate'      => $menu->taxRate(),
            'inclusive' => $menu->taxInclusive(),
            'label'     => $menu->taxLabel(),
        ];
    }

    protected function ownerOrder(RestaurantOrder $order): array
    {
        return array_merge([
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
        ], $this->orderBreakdown($order));
    }
}
