<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Services\StoreOrderService;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\StoreCategory;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreOrder;
use App\Modules\User\Models\StoreProduct;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\UserFile;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * REST API parity for the store page (Task #3072).
 *
 * Public (optional auth): fetch a store by alias, place an order request,
 * poll a guest order status by its public token.
 * Authenticated (Sanctum): owner orders list, incremental poll, status
 * updates, and the full builder CRUD — mirrors the web StoreMenuController
 * for the mobile app.
 *
 * Unified `{data}` / `{error}` envelope via ApiResponses. Unlike the
 * restaurant API there is NO quote endpoint, NO tables, and NO tax/coupon:
 * the request total is simply the sum of line totals, and no online payment
 * is ever collected — this is an order *request* flow.
 */
class StoreController extends Controller
{
    use ApiResponses;

    public function __construct(protected StoreOrderService $orders)
    {
    }

    // ── Public ───────────────────────────────────────────────────

    /** Public store fetch by alias (display + order mode). */
    public function show(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());

        if (!$link || $link->type !== Link::TYPE_STORE_MENU || !$link->is_active || !$link->isAccessible()) {
            return $this->notFound('Store not found');
        }

        if ($gate = $this->checkVisibility($link, $request->user())) {
            return $this->fail($gate['message'], $gate['status'], $gate['code']);
        }

        $menu = $link->storeMenu()->first();
        if (!$menu) {
            return $this->notFound('Store not found');
        }

        $menu->load([
            'categories' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
            'products'   => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
        ]);

        $productsByCat = $menu->products->groupBy('category_id');

        return $this->ok([
            'menu' => [
                'mode'             => $menu->mode,
                'currency'         => $menu->currency,
                'accent_color'     => $menu->accent_color,
                'order_enabled'    => $menu->isOrderMode(),
                'accepting_orders' => $menu->acceptingOrders(),
            ],
            'link' => [
                'alias' => $link->alias,
                'title' => $link->title,
            ],
            'categories' => $menu->categories->map(fn ($c) => [
                'id'          => $c->id,
                'name'        => $c->name,
                'description' => $c->description,
                'products'    => ($productsByCat->get($c->id) ?? collect())->map(fn ($p) => [
                    'id'              => $p->id,
                    'name'            => $p->name,
                    'description'     => $p->description,
                    'price'           => $p->price,
                    'photo_url'       => $p->photo_url,
                    'is_out_of_stock' => (bool) $p->is_out_of_stock,
                ])->values(),
            ])->values(),
        ]);
    }

    /** Place an order request (order mode only). */
    public function placeOrder(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());

        if (!$link || $link->type !== Link::TYPE_STORE_MENU || !$link->is_active || !$link->isAccessible()) {
            return $this->notFound('Store not found');
        }

        if ($gate = $this->checkVisibility($link, $request->user())) {
            return $this->fail($gate['message'], $gate['status'], $gate['code']);
        }

        $menu = $link->storeMenu()->first();
        if (!$menu) {
            return $this->notFound('Store not found');
        }
        if (!$menu->isOrderMode()) {
            return $this->fail('Ordering is not enabled for this store', 422, 'ordering_disabled');
        }
        if (!$menu->acceptingOrders()) {
            return $this->fail('This store is not accepting requests right now', 422, 'orders_paused');
        }

        $data = $request->validate([
            'customer_name'      => 'nullable|string|max:120',
            'customer_contact'   => 'nullable|string|max:160',
            'customer_note'      => 'nullable|string|max:1000',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity'   => 'required|integer|min:1|max:99',
            'items.*.note'       => 'nullable|string|max:300',
        ]);

        try {
            $order = $this->orders->place($link, $menu, $data);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422, 'invalid_order');
        }

        return $this->created(['order' => $this->guestOrder($order->fresh('items'), $menu, $link)]);
    }

    /** Guest polls their own order status with the public token. */
    public function orderStatus(Request $request, string $token)
    {
        $order = StoreOrder::with(['items', 'menu', 'link'])->where('public_token', $token)->first();
        if (!$order) {
            return $this->notFound('Order not found');
        }

        return $this->ok(['order' => $this->guestOrder($order, $order->menu, $order->link)]);
    }

    /**
     * Visibility/access gating, mirroring BiolinkController so private,
     * registered-only, follower-only, and subscriber-only stores are enforced
     * on the public API exactly as on biolinks. Returns null when allowed.
     */
    protected function checkVisibility(Link $link, $viewer): ?array
    {
        $vis   = $link->visibility ?? 'public';
        $owner = $link->user;

        if ($vis === 'public') return null;
        if ($viewer && $owner && (int) $viewer->id === (int) $owner->id) return null;

        if (!$viewer) {
            return ['status' => 401, 'code' => 'auth_required', 'message' => 'Sign in required to view this store'];
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

    /** Resolve an owned store-menu link or fail. */
    protected function ownedMenu(Request $request, Link $link): ?StoreMenu
    {
        if ($link->type !== Link::TYPE_STORE_MENU) {
            return null;
        }
        if ((int) $link->user_id !== (int) $request->user()->id) {
            return null;
        }

        return $link->storeMenu()->first()
            ?? StoreMenu::create([
                'link_id'  => $link->id,
                'user_id'  => $link->user_id,
                'mode'     => StoreMenu::MODE_DISPLAY,
                'currency' => 'USD',
            ]);
    }

    /** Owner: list recent order requests for a store. */
    public function ownerOrders(Request $request, Link $link)
    {
        $menu = $this->ownedMenu($request, $link);
        if (!$menu) {
            return $this->notFound('Store not found');
        }

        $orders = StoreOrder::with('items')
            ->where('menu_id', $menu->id)
            ->latest()
            ->limit(100)
            ->get();

        return $this->ok([
            'orders'      => $orders->map(fn ($o) => $this->ownerOrder($o))->values(),
            'open_count'  => $this->openCount($menu->id),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /** Owner: incremental poll for new/updated requests. */
    public function ownerPoll(Request $request, Link $link)
    {
        $menu = $this->ownedMenu($request, $link);
        if (!$menu) {
            return $this->notFound('Store not found');
        }

        $query = StoreOrder::with('items')->where('menu_id', $menu->id);
        if ($since = $request->query('since')) {
            try {
                $query->where('updated_at', '>', \Carbon\Carbon::parse($since));
            } catch (\Throwable $e) {
                // ignore bad cursor
            }
        }

        $orders = $query->latest('updated_at')->limit(100)->get();

        return $this->ok([
            'orders'      => $orders->map(fn ($o) => $this->ownerOrder($o))->values(),
            'open_count'  => $this->openCount($menu->id),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /** Owner: advance a request's status. */
    public function updateOrderStatus(Request $request, Link $link, StoreOrder $order)
    {
        $menu = $this->ownedMenu($request, $link);
        if (!$menu) {
            return $this->notFound('Store not found');
        }
        if ((int) $order->menu_id !== (int) $menu->id) {
            return $this->notFound('Order not found');
        }

        $data = $request->validate([
            'status' => 'required|in:' . implode(',', StoreOrder::STATUSES),
        ]);

        if (!$order->canTransitionTo($data['status'])) {
            return $this->fail(
                "Can't move a request from '{$order->status}' to '{$data['status']}'",
                422,
                'invalid_transition',
            );
        }

        $order->update(['status' => $data['status']]);

        return $this->ok(['order' => $this->ownerOrder($order->fresh('items'))]);
    }

    // ── Owner builder (Sanctum) ──────────────────────────────────

    /** Owner: full store config — settings + categories with products. */
    public function ownerMenu(Request $request, Link $link)
    {
        $menu = $this->ownedMenu($request, $link);
        if (!$menu) {
            return $this->notFound('Store not found');
        }

        return $this->ok(['menu' => $this->ownerMenuPayload($menu, $link)]);
    }

    /** Owner: update store settings (mode/currency/accent/whatsapp/accepting). */
    public function saveMenuSettings(Request $request, Link $link)
    {
        $menu = $this->ownedMenu($request, $link);
        if (!$menu) {
            return $this->notFound('Store not found');
        }

        $data = $request->validate([
            'mode'             => 'required|in:display,order',
            'currency'         => 'required|string|size:3',
            'accent_color'     => 'nullable|string|max:16',
            'whatsapp_number'  => 'nullable|string|max:32',
            'accepting_orders' => 'sometimes|boolean',
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

        if ($request->has('accepting_orders')) {
            $settings['accepting_orders'] = (bool) $data['accepting_orders'];
        }

        $menu->update([
            'mode'         => $data['mode'],
            'currency'     => strtoupper($data['currency']),
            'accent_color' => $data['accent_color'] ?? $menu->accent_color,
            'settings'     => $settings,
        ]);

        return $this->ok(['menu' => $this->ownerMenuPayload($menu->fresh(), $link)]);
    }

    // ── Categories ───────────────────────────────────────────────
    public function storeCategory(Request $request, Link $link)
    {
        $menu = $this->ownedMenu($request, $link);
        if (!$menu) {
            return $this->notFound('Store not found');
        }

        $data = $request->validate([
            'name'        => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
        ]);

        $category = StoreCategory::create([
            'menu_id'     => $menu->id,
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'sort_order'  => (int) StoreCategory::where('menu_id', $menu->id)->max('sort_order') + 1,
        ]);

        return $this->created(['category' => $this->ownerCategory($category, collect())]);
    }

    public function updateCategory(Request $request, Link $link, StoreCategory $category)
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

        $products = $menu->products()->where('category_id', $category->id)->get();

        return $this->ok(['category' => $this->ownerCategory($category->fresh(), $products)]);
    }

    public function destroyCategory(Request $request, Link $link, StoreCategory $category)
    {
        $menu = $this->ownedMenu($request, $link);
        if (!$menu || (int) $category->menu_id !== (int) $menu->id) {
            return $this->notFound('Category not found');
        }

        StoreProduct::where('category_id', $category->id)->delete();
        $category->delete();

        return $this->ok(['deleted' => true]);
    }

    // ── Products ─────────────────────────────────────────────────
    public function storeProduct(Request $request, Link $link)
    {
        $menu = $this->ownedMenu($request, $link);
        if (!$menu) {
            return $this->notFound('Store not found');
        }

        $data = $request->validate([
            'category_id'     => 'required|integer',
            'name'            => 'required|string|max:160',
            'description'     => 'nullable|string|max:800',
            'price'           => 'nullable|numeric|min:0|max:9999999',
            'photo_url'       => 'nullable|string|max:1024',
            'is_out_of_stock' => 'sometimes|boolean',
        ]);

        $category = StoreCategory::where('menu_id', $menu->id)->find($data['category_id']);
        if (!$category) {
            return $this->fail('Category not found', 422, 'invalid_category');
        }

        $product = StoreProduct::create([
            'menu_id'         => $menu->id,
            'category_id'     => $category->id,
            'name'            => $data['name'],
            'description'     => $data['description'] ?? null,
            'price'           => $data['price'] ?? 0,
            'photo_url'       => $data['photo_url'] ?? null,
            'is_out_of_stock' => (bool) ($data['is_out_of_stock'] ?? false),
            'sort_order'      => (int) StoreProduct::where('category_id', $category->id)->max('sort_order') + 1,
        ]);

        return $this->created(['product' => $this->ownerProduct($product)]);
    }

    public function updateProduct(Request $request, Link $link, StoreProduct $product)
    {
        $menu = $this->ownedMenu($request, $link);
        if (!$menu || (int) $product->menu_id !== (int) $menu->id) {
            return $this->notFound('Product not found');
        }

        $data = $request->validate([
            'category_id'     => 'sometimes|integer',
            'name'            => 'sometimes|required|string|max:160',
            'description'     => 'nullable|string|max:800',
            'price'           => 'sometimes|numeric|min:0|max:9999999',
            'photo_url'       => 'nullable|string|max:1024',
            'is_out_of_stock' => 'sometimes|boolean',
            'is_active'       => 'sometimes|boolean',
        ]);

        if (isset($data['category_id'])) {
            $owned = StoreCategory::where('menu_id', $menu->id)->find($data['category_id']);
            if (!$owned) {
                return $this->fail('Category not found', 422, 'invalid_category');
            }
        }

        $product->update($data);

        return $this->ok(['product' => $this->ownerProduct($product->fresh())]);
    }

    public function destroyProduct(Request $request, Link $link, StoreProduct $product)
    {
        $menu = $this->ownedMenu($request, $link);
        if (!$menu || (int) $product->menu_id !== (int) $menu->id) {
            return $this->notFound('Product not found');
        }

        $product->delete();

        return $this->ok(['deleted' => true]);
    }

    /** Owner: upload a photo for a product; returns the public URL. */
    public function uploadProductPhoto(Request $request, Link $link)
    {
        $menu = $this->ownedMenu($request, $link);
        if (!$menu) {
            return $this->notFound('Store not found');
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

    // ── Serializers ──────────────────────────────────────────────

    /** Full owner-facing store (includes inactive rows the builder edits). */
    protected function ownerMenuPayload(StoreMenu $menu, Link $link): array
    {
        $menu->load(['categories', 'products']);
        $productsByCat = $menu->products->groupBy('category_id');

        return [
            'mode'             => $menu->mode,
            'currency'         => $menu->currency,
            'accent_color'     => $menu->accent_color,
            'whatsapp_number'  => $menu->settings['whatsapp_number'] ?? null,
            'accepting_orders' => $menu->acceptingOrders(),
            'order_enabled'    => $menu->isOrderMode(),
            'public_url'       => url('/' . $link->alias),
            'categories'       => $menu->categories->map(fn ($c) => $this->ownerCategory(
                $c,
                $productsByCat->get($c->id) ?? collect(),
            ))->values(),
        ];
    }

    protected function ownerCategory(StoreCategory $category, $products): array
    {
        return [
            'id'          => $category->id,
            'name'        => $category->name,
            'description' => $category->description,
            'is_active'   => (bool) ($category->is_active ?? true),
            'products'    => collect($products)->map(fn ($p) => $this->ownerProduct($p))->values(),
        ];
    }

    protected function ownerProduct(StoreProduct $product): array
    {
        return [
            'id'              => $product->id,
            'category_id'     => $product->category_id,
            'name'            => $product->name,
            'description'     => $product->description,
            'price'           => $product->price,
            'photo_url'       => $product->photo_url,
            'is_out_of_stock' => (bool) $product->is_out_of_stock,
            'is_active'       => (bool) ($product->is_active ?? true),
        ];
    }

    protected function openCount(int $menuId): int
    {
        return StoreOrder::where('menu_id', $menuId)
            ->whereIn('status', StoreOrder::OPEN_STATUSES)
            ->count();
    }

    protected function guestOrder(StoreOrder $order, ?StoreMenu $menu = null, ?Link $link = null): array
    {
        $whatsapp = ($menu && $link)
            ? \App\Modules\Common\Services\WhatsappOrderLink::build($menu, $order, $link->title)
            : null;

        return [
            'public_token' => $order->public_token,
            'status'       => $order->status,
            'status_label' => $order->status_label,
            'subtotal'     => $order->subtotal,
            'total'        => $order->total,
            'currency'     => $order->currency,
            'is_estimate'  => true,
            'items'        => $order->items->map(fn ($i) => [
                'name'       => $i->name,
                'quantity'   => $i->quantity,
                'line_total' => $i->line_total,
            ])->values(),
            'whatsapp'     => $whatsapp,
            'created_at'   => $order->created_at?->toIso8601String(),
        ];
    }

    protected function ownerOrder(StoreOrder $order): array
    {
        return [
            'id'               => $order->id,
            'status'           => $order->status,
            'status_label'     => $order->status_label,
            'customer_name'    => $order->customer_name,
            'customer_contact' => $order->customer_contact,
            'customer_note'    => $order->customer_note,
            'subtotal'         => $order->subtotal,
            'total'            => $order->total,
            'currency'         => $order->currency,
            'created_at'       => $order->created_at?->toIso8601String(),
            'updated_at'       => $order->updated_at?->toIso8601String(),
            'items'            => $order->items->map(fn ($i) => [
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
