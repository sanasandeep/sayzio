<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\StoreCategory;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreOrder;
use App\Modules\User\Models\StoreProduct;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Owner-facing store builder + order dashboard (Task #3072).
 *
 * Mirrors RestaurantMenuController but adapted to store vocabulary
 * (Categories → Products) and WITHOUT coupons, physical tables / per-table
 * QR, or tax: the store has a single shareable QR and an order *request*
 * flow with no online payment.
 */
class StoreMenuController extends Controller
{
    /** Resolve the store config row for a link, creating it on first edit. */
    protected function menuFor(Link $link): StoreMenu
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        abort_unless($link->type === Link::TYPE_STORE_MENU, 404);

        return StoreMenu::firstOrCreate(
            ['link_id' => $link->id],
            ['user_id' => $link->user_id, 'mode' => StoreMenu::MODE_DISPLAY, 'currency' => 'USD']
        );
    }

    /** Guard a category/product belongs to this store. */
    protected function assertOwns(StoreMenu $menu, $model): void
    {
        abort_if((int) $model->menu_id !== (int) $menu->id, 404);
    }

    public function editor(Request $request, Link $link)
    {
        $menu = $this->menuFor($link);
        $menu->load(['categories', 'products']);

        $openOrders = StoreOrder::where('menu_id', $menu->id)
            ->whereIn('status', StoreOrder::OPEN_STATUSES)
            ->count();

        return view('user.links.store.editor', [
            'link'       => $link,
            'menu'       => $menu,
            'openOrders' => $openOrders,
        ]);
    }

    public function saveSettings(Request $request, Link $link)
    {
        $menu = $this->menuFor($link);

        $data = $request->validate([
            'mode'             => 'required|in:display,order',
            'currency'         => 'required|string|size:3',
            'accent_color'     => 'nullable|string|max:16',
            'whatsapp_number'  => 'nullable|string|max:32',
            'accepting_orders' => 'sometimes|boolean',
            'settings'         => 'nullable|array',
            'layout'           => 'nullable|string|max:24',
            'divider'          => 'nullable|string|max:16',
            'heading_style'          => 'nullable|string|max:16',
            'price_style'            => 'nullable|string|max:16',
            'price_display'          => 'nullable|string|max:16',
            'price_position'         => 'nullable|string|max:16',
            'price_decimals'         => 'sometimes|boolean',
            // Menu colours (Sana, 2026-09-23: "i cannot change colors of
            // menu items and all"). Each is optional; an absent one keeps
            // inheriting the page ink, which is what the page did before.
            'heading_color'    => ['nullable', 'string', 'max:16'],
            'item_color'       => ['nullable', 'string', 'max:16'],
            'desc_color'       => ['nullable', 'string', 'max:16'],
            'price_color'      => ['nullable', 'string', 'max:16'],
            'divider_color'    => ['nullable', 'string', 'max:16'],
        ]);

        $settings = $data['settings'] ?? ($menu->settings ?? []);

        // Which of the five layouts draws the products. Validated against
        // the catalog rather than trusted, so an unknown key falls back to
        // the list layout this page has always had.
        if ($request->has('layout')) {
            $settings['layout'] = \App\Modules\User\Support\MenuPresentation::layout($data['layout'] ?? null);
        }

        // Divider shape between items. Same treatment as the layout: the
        // catalog decides what is valid, so an unknown key falls back to
        // the hairline every menu already draws rather than to nothing.
        if ($request->has('divider')) {
            $settings['divider'] = \App\Modules\User\Support\MenuPresentation::divider($data['divider'] ?? null);
        }

        // How the card is SET: where the section titles sit, and where the
        // prices do. Same treatment as the layout -- the catalog decides
        // what is valid, so an unknown key falls back to what the page
        // already draws rather than to nothing.
        if ($request->has('heading_style')) {
            $settings['heading_style'] = \App\Modules\User\Support\MenuPresentation::heading($data['heading_style'] ?? null);
        }
        if ($request->has('price_style')) {
            // Price placement is validated against the catalog alone. Its
            // LAYOUT-dependent default lives in the resolver, because an
            // unset value has to keep meaning "dots" for a Compact menu --
            // storing a resolved value here would freeze that in and change
            // what the menu looks like if the layout is switched later.
            $key = $data['price_style'] ?? null;
            if (isset(\App\Modules\User\Support\MenuPresentation::PRICES[$key])) {
                $settings['price_style'] = $key;
            } else {
                unset($settings['price_style']);
            }
        }

        // How a price is WRITTEN, as opposed to where it sits: the code, a
        // symbol or nothing, before or after, with or without decimals.
        // Sana, 2026-09-23: "prices should have options like INR, [Rs.] USD
        // or $ like that... before or after number..."
        foreach (['price_display' => \App\Modules\User\Support\MenuMoney::DISPLAYS,
                  'price_position' => \App\Modules\User\Support\MenuMoney::POSITIONS] as $mk => $catalog) {
            if (! $request->has($mk)) {
                continue;
            }
            if (isset($catalog[$data[$mk] ?? null])) {
                $settings[$mk] = $data[$mk];
            } else {
                unset($settings[$mk]);
            }
        }
        if ($request->has('price_decimals')) {
            // Stored only when it is OFF. An absent key means "whatever the
            // currency does", which is what every existing menu wants and
            // what it would keep if the currency were later changed.
            if ($data['price_decimals'] ?? true) {
                unset($settings['price_decimals']);
            } else {
                $settings['price_decimals'] = false;
            }
        }

        // Colours are stored only when the form sent them, and an empty
        // value CLEARS the key rather than storing '' -- otherwise a
        // creator could never go back to inheriting the page ink.
        foreach (array_keys(\App\Modules\User\Support\MenuPresentation::COLOURS) as $ck) {
            if (! $request->has($ck)) {
                continue;
            }
            $hex = \App\Modules\User\Support\MenuPresentation::hex($data[$ck] ?? null);
            if ($hex === '') {
                unset($settings[$ck]);
            } else {
                $settings[$ck] = $hex;
            }
        }

        // Optional WhatsApp click-to-chat number for order confirmations. Stored
        // in the store's settings JSON, normalized to the digits-only form
        // wa.me expects. Blank/invalid input clears it (feature off).
        if ($request->has('whatsapp_number')) {
            $normalized = \App\Modules\Common\Services\WhatsappOrderLink::normalizeNumber($data['whatsapp_number'] ?? null);
            if ($normalized) {
                $settings['whatsapp_number'] = $normalized;
            } else {
                unset($settings['whatsapp_number']);
            }
        }

        // Order-accepting toggle lives in the settings JSON (no migration).
        if ($request->has('accepting_orders')) {
            $settings['accepting_orders'] = (bool) $data['accepting_orders'];
        }

        $menu->update([
            'mode'         => $data['mode'],
            'currency'     => strtoupper($data['currency']),
            'accent_color' => $data['accent_color'] ?? $menu->accent_color,
            'settings'     => $settings,
        ]);

        return response()->json(['data' => ['menu' => $menu->fresh()]]);
    }

    // ── Categories ───────────────────────────────────────────────
    public function storeCategory(Request $request, Link $link)
    {
        $menu = $this->menuFor($link);
        $data = $request->validate([
            'name'        => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'parent_id'   => 'nullable|integer',
            'is_active'   => 'sometimes|boolean',
        ]);

        $parentId = $data['parent_id'] ?? null;

        if ($reason = \App\Modules\User\Support\MenuTree::rejectParent(
            StoreCategory::class, (int) $menu->id, null, $parentId ? (int) $parentId : null
        )) {
            return response()->json(['message' => $reason, 'errors' => ['parent_id' => [$reason]]], 422);
        }

        // A sub-section is ordered among its siblings, not among the
        // sections -- otherwise the first one added lands at the end of the
        // whole store and the creator has to drag it back.
        $siblings = StoreCategory::where('menu_id', $menu->id);
        $parentId ? $siblings->where('parent_id', $parentId) : $siblings->whereNull('parent_id');

        $category = StoreCategory::create([
            'menu_id'     => $menu->id,
            'parent_id'   => $parentId,
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active'   => (bool) ($data['is_active'] ?? true),
            'sort_order'  => (int) $siblings->max('sort_order') + 1,
        ]);

        return response()->json(['data' => ['category' => $category]], 201);
    }

    public function updateCategory(Request $request, Link $link, StoreCategory $category)
    {
        $menu = $this->menuFor($link);
        $this->assertOwns($menu, $category);

        $data = $request->validate([
            'name'        => 'sometimes|required|string|max:120',
            'description' => 'nullable|string|max:500',
            'parent_id'   => 'sometimes|nullable|integer',
            'is_active'   => 'sometimes|boolean',
        ]);

        if ($request->has('parent_id')) {
            $parentId = $data['parent_id'] ? (int) $data['parent_id'] : null;

            if ($reason = \App\Modules\User\Support\MenuTree::rejectParent(
                StoreCategory::class, (int) $menu->id, (int) $category->id, $parentId
            )) {
                return response()->json(['message' => $reason, 'errors' => ['parent_id' => [$reason]]], 422);
            }

            $data['parent_id'] = $parentId;
        }

        $category->update($data);

        return response()->json(['data' => ['category' => $category->fresh()]]);
    }

    public function destroyCategory(Request $request, Link $link, StoreCategory $category)
    {
        $menu = $this->menuFor($link);
        $this->assertOwns($menu, $category);

        // Sub-sections go with the section, and their products with them.
        // The alternative is orphan rows that MenuTree has to promote back
        // to top level, which is a creator seeing a sub-section reappear as
        // its own heading after deleting the section above it.
        $childIds = StoreCategory::where('menu_id', $menu->id)
            ->where('parent_id', $category->id)->pluck('id')->all();

        $doomed = array_merge([$category->id], $childIds);

        StoreProduct::whereIn('category_id', $doomed)->delete();
        StoreCategory::whereIn('id', $childIds)->delete();
        $category->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function reorderCategories(Request $request, Link $link)
    {
        $menu = $this->menuFor($link);
        $data = $request->validate(['order' => 'required|array', 'order.*' => 'integer']);

        foreach ($data['order'] as $i => $id) {
            StoreCategory::where('menu_id', $menu->id)->where('id', $id)
                ->update(['sort_order' => $i]);
        }

        return response()->json(['data' => ['reordered' => true]]);
    }

    // ── Products ─────────────────────────────────────────────────
    public function storeProduct(Request $request, Link $link)
    {
        $menu = $this->menuFor($link);
        $data = $request->validate([
            'category_id'     => 'required|integer',
            'name'            => 'required|string|max:160',
            'description'     => 'nullable|string|max:800',
            'price'           => 'nullable|numeric|min:0|max:9999999',
            'currency'        => 'nullable|string|size:3',
            'photo_url'       => 'nullable|string|max:1024',
            'is_out_of_stock' => 'sometimes|boolean',
            'is_active'       => 'sometimes|boolean',
        ]);

        $category = StoreCategory::where('menu_id', $menu->id)->findOrFail($data['category_id']);

        $product = StoreProduct::create([
            'menu_id'         => $menu->id,
            'category_id'     => $category->id,
            'name'            => $data['name'],
            'description'     => $data['description'] ?? null,
            'price'           => $data['price'] ?? 0,
            'currency'        => isset($data['currency']) ? strtoupper($data['currency']) : null,
            'photo_url'       => $data['photo_url'] ?? null,
            'is_out_of_stock' => (bool) ($data['is_out_of_stock'] ?? false),
            'sort_order'      => (int) StoreProduct::where('category_id', $category->id)->max('sort_order') + 1,
        ]);

        return response()->json(['data' => ['product' => $product]], 201);
    }

    public function updateProduct(Request $request, Link $link, StoreProduct $product)
    {
        $menu = $this->menuFor($link);
        $this->assertOwns($menu, $product);

        $data = $request->validate([
            'category_id'     => 'sometimes|integer',
            'name'            => 'sometimes|required|string|max:160',
            'description'     => 'nullable|string|max:800',
            'price'           => 'sometimes|numeric|min:0|max:9999999',
            'currency'        => 'nullable|string|size:3',
            'photo_url'       => 'nullable|string|max:1024',
            'is_out_of_stock' => 'sometimes|boolean',
            'is_active'       => 'sometimes|boolean',
        ]);

        if (isset($data['category_id'])) {
            StoreCategory::where('menu_id', $menu->id)->findOrFail($data['category_id']);
        }
        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $product->update($data);

        return response()->json(['data' => ['product' => $product->fresh()]]);
    }

    public function destroyProduct(Request $request, Link $link, StoreProduct $product)
    {
        $menu = $this->menuFor($link);
        $this->assertOwns($menu, $product);
        $product->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function reorderProducts(Request $request, Link $link)
    {
        $menu = $this->menuFor($link);
        $data = $request->validate(['order' => 'required|array', 'order.*' => 'integer']);

        foreach ($data['order'] as $i => $id) {
            StoreProduct::where('menu_id', $menu->id)->where('id', $id)
                ->update(['sort_order' => $i]);
        }

        return response()->json(['data' => ['reordered' => true]]);
    }

    /** Printable QR page for the whole store (single shareable link). */
    public function storeQr(Request $request, Link $link)
    {
        $menu = $this->menuFor($link);

        $url = url('/' . $link->alias);

        return view('user.links.store.store-qr', [
            'link' => $link,
            'menu' => $menu,
            'url'  => $url,
        ]);
    }

    // ── Orders dashboard ─────────────────────────────────────────
    public function orders(Request $request, Link $link)
    {
        $menu = $this->menuFor($link);

        $orders = StoreOrder::with('items')
            ->where('menu_id', $menu->id)
            ->latest()
            ->limit(100)
            ->get();

        return view('user.links.store.orders', [
            'link'   => $link,
            'menu'   => $menu,
            'orders' => $orders,
        ]);
    }

    /** Near-real-time polling endpoint for the orders dashboard. */
    public function pollOrders(Request $request, Link $link)
    {
        $menu = $this->menuFor($link);

        $query = StoreOrder::with('items')->where('menu_id', $menu->id);

        // Optional incremental fetch: only orders updated after a cursor.
        if ($since = $request->query('since')) {
            try {
                $query->where('updated_at', '>', \Carbon\Carbon::parse($since));
            } catch (\Throwable $e) {
                // ignore bad cursor, return recent set
            }
        }

        $orders = $query->latest('updated_at')->limit(100)->get();

        $openCount = StoreOrder::where('menu_id', $menu->id)
            ->whereIn('status', StoreOrder::OPEN_STATUSES)
            ->count();

        return response()->json(['data' => [
            'orders'      => $orders,
            'open_count'  => $openCount,
            'server_time' => now()->toIso8601String(),
        ]]);
    }

    public function updateOrderStatus(Request $request, Link $link, StoreOrder $order)
    {
        $menu = $this->menuFor($link);
        abort_if((int) $order->menu_id !== (int) $menu->id, 404);

        $data = $request->validate([
            'status' => 'required|in:' . implode(',', StoreOrder::STATUSES),
        ]);

        if (!$order->canTransitionTo($data['status'])) {
            return response()->json(['error' => [
                'message' => "Can't move a request from '{$order->status}' to '{$data['status']}'",
                'code'    => 'invalid_transition',
            ]], 422);
        }

        $order->update(['status' => $data['status']]);

        return response()->json(['data' => ['order' => $order->fresh('items')]]);
    }
}
