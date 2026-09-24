<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuCategory;
use App\Modules\User\Models\RestaurantMenuCoupon;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\RestaurantOrder;
use App\Modules\User\Models\RestaurantTable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class RestaurantMenuController extends Controller
{
    /** Resolve the menu config row for a link, creating it on first edit. */
    protected function menuFor(Link $link): RestaurantMenu
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        abort_unless($link->type === Link::TYPE_RESTAURANT_MENU, 404);

        return RestaurantMenu::firstOrCreate(
            ['link_id' => $link->id],
            ['user_id' => $link->user_id, 'mode' => RestaurantMenu::MODE_DISPLAY, 'currency' => 'USD']
        );
    }

    /** Guard a category/item/table belongs to this menu. */
    protected function assertOwns(RestaurantMenu $menu, $model): void
    {
        abort_if((int) $model->menu_id !== (int) $menu->id, 404);
    }

    public function editor(Request $request, Link $link)
    {
        $menu = $this->menuFor($link);
        $menu->load(['categories', 'items', 'tables']);

        $openOrders = RestaurantOrder::where('menu_id', $menu->id)
            ->whereIn('status', RestaurantOrder::OPEN_STATUSES)
            ->count();

        return view('user.links.restaurant.editor', [
            'link'       => $link,
            'menu'       => $menu,
            'openOrders' => $openOrders,
        ]);
    }

    public function saveSettings(Request $request, Link $link)
    {
        $menu = $this->menuFor($link);

        $data = $request->validate([
            'mode'            => 'required|in:display,order',
            'currency'        => 'required|string|size:3',
            'accent_color'    => 'nullable|string|max:16',
            'whatsapp_number' => 'nullable|string|max:32',
            'settings'        => 'nullable|array',
            'layout'          => 'nullable|string|max:24',
            'divider'         => 'nullable|string|max:16',
            'heading_style'         => 'nullable|string|max:16',
            'price_style'           => 'nullable|string|max:16',
            // Menu colours (Sana, 2026-09-23: "i cannot change colors of
            // menu items and all"). Each is optional; an absent one keeps
            // inheriting the page ink, which is what the page did before.
            'heading_color'   => ['nullable', 'string', 'max:16'],
            'item_color'      => ['nullable', 'string', 'max:16'],
            'desc_color'      => ['nullable', 'string', 'max:16'],
            'price_color'     => ['nullable', 'string', 'max:16'],
            'divider_color'   => ['nullable', 'string', 'max:16'],
            'tax_enabled'     => 'sometimes|boolean',
            'tax_rate'        => 'nullable|numeric|min:0|max:100',
            'tax_inclusive'   => 'sometimes|boolean',
            'tax_label'       => 'nullable|string|max:24',
        ]);

        $settings = $data['settings'] ?? ($menu->settings ?? []);

        // Which of the five layouts draws the items. Validated against the
        // catalog rather than trusted, so an unknown key falls back to the
        // list layout this page has always had instead of rendering nothing.
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
        // in the menu's settings JSON, normalized to the digits-only form
        // wa.me expects. Blank/invalid input clears it (feature off).
        if ($request->has('whatsapp_number')) {
            $normalized = \App\Modules\Common\Services\WhatsappOrderLink::normalizeNumber($data['whatsapp_number'] ?? null);
            if ($normalized) {
                $settings['whatsapp_number'] = $normalized;
            } else {
                unset($settings['whatsapp_number']);
            }
        }

        // Tax/GST settings live in the menu `settings` JSON. Accept them either
        // as flat fields (web editor, mobile API) or pre-nested in `settings`.
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

        return response()->json(['data' => ['menu' => $menu->fresh()]]);
    }

    // ── Coupons (Task #3067) ─────────────────────────────────────
    public function storeCoupon(Request $request, Link $link)
    {
        $menu = $this->menuFor($link);

        $data = $this->validateCoupon($request);
        $code = RestaurantMenuCoupon::normalizeCode($data['code']);

        if ($menu->coupons()->where('code', $code)->exists()) {
            return response()->json(['error' => [
                'message' => 'A coupon with that code already exists on this menu.',
                'code'    => 'duplicate_code',
            ]], 422);
        }

        $coupon = $menu->coupons()->create([
            'code'           => $code,
            'discount_type'  => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'min_subtotal'   => $data['min_subtotal'] ?? 0,
            'is_active'      => (bool) ($data['is_active'] ?? true),
        ]);

        return response()->json(['data' => ['coupon' => $coupon]], 201);
    }

    public function updateCoupon(Request $request, Link $link, RestaurantMenuCoupon $coupon)
    {
        $menu = $this->menuFor($link);
        $this->assertOwns($menu, $coupon);

        $data = $this->validateCoupon($request);
        $code = RestaurantMenuCoupon::normalizeCode($data['code']);

        if ($menu->coupons()->where('code', $code)->where('id', '!=', $coupon->id)->exists()) {
            return response()->json(['error' => [
                'message' => 'A coupon with that code already exists on this menu.',
                'code'    => 'duplicate_code',
            ]], 422);
        }

        $coupon->update([
            'code'           => $code,
            'discount_type'  => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'min_subtotal'   => $data['min_subtotal'] ?? 0,
            'is_active'      => (bool) ($data['is_active'] ?? true),
        ]);

        return response()->json(['data' => ['coupon' => $coupon->fresh()]]);
    }

    public function destroyCoupon(Request $request, Link $link, RestaurantMenuCoupon $coupon)
    {
        $menu = $this->menuFor($link);
        $this->assertOwns($menu, $coupon);
        $coupon->delete();

        return response()->json(['data' => ['deleted' => true]]);
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
        $menu = $this->menuFor($link);
        $data = $request->validate([
            'name'        => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'parent_id'   => 'nullable|integer',
            'is_active'   => 'sometimes|boolean',
        ]);

        $parentId = $data['parent_id'] ?? null;

        if ($reason = \App\Modules\User\Support\MenuTree::rejectParent(
            RestaurantMenuCategory::class, (int) $menu->id, null, $parentId ? (int) $parentId : null
        )) {
            return response()->json(['message' => $reason, 'errors' => ['parent_id' => [$reason]]], 422);
        }

        // A sub-section is ordered among its siblings, not among the
        // sections -- otherwise the first one added lands at the end of the
        // whole menu and the creator has to drag it back.
        $siblings = RestaurantMenuCategory::where('menu_id', $menu->id);
        $parentId ? $siblings->where('parent_id', $parentId) : $siblings->whereNull('parent_id');

        $category = RestaurantMenuCategory::create([
            'menu_id'     => $menu->id,
            'parent_id'   => $parentId,
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active'   => (bool) ($data['is_active'] ?? true),
            'sort_order'  => (int) $siblings->max('sort_order') + 1,
        ]);

        return response()->json(['data' => ['category' => $category]], 201);
    }

    public function updateCategory(Request $request, Link $link, RestaurantMenuCategory $category)
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
                RestaurantMenuCategory::class, (int) $menu->id, (int) $category->id, $parentId
            )) {
                return response()->json(['message' => $reason, 'errors' => ['parent_id' => [$reason]]], 422);
            }

            $data['parent_id'] = $parentId;
        }

        $category->update($data);

        return response()->json(['data' => ['category' => $category->fresh()]]);
    }

    public function destroyCategory(Request $request, Link $link, RestaurantMenuCategory $category)
    {
        $menu = $this->menuFor($link);
        $this->assertOwns($menu, $category);

        // Sub-sections go with the section, and their items with them. The
        // alternative is orphan rows that MenuTree has to promote back to
        // top level, which is a creator seeing "Idli" reappear as its own
        // heading after deleting "Tiffins".
        $childIds = RestaurantMenuCategory::where('menu_id', $menu->id)
            ->where('parent_id', $category->id)->pluck('id')->all();

        $doomed = array_merge([$category->id], $childIds);

        RestaurantMenuItem::whereIn('category_id', $doomed)->delete();
        RestaurantMenuCategory::whereIn('id', $childIds)->delete();
        $category->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function reorderCategories(Request $request, Link $link)
    {
        $menu = $this->menuFor($link);
        $data = $request->validate(['order' => 'required|array', 'order.*' => 'integer']);

        foreach ($data['order'] as $i => $id) {
            RestaurantMenuCategory::where('menu_id', $menu->id)->where('id', $id)
                ->update(['sort_order' => $i]);
        }

        return response()->json(['data' => ['reordered' => true]]);
    }

    // ── Items ────────────────────────────────────────────────────
    public function storeItem(Request $request, Link $link)
    {
        $menu = $this->menuFor($link);
        $data = $request->validate([
            'category_id' => 'required|integer',
            'name'        => 'required|string|max:160',
            'description' => 'nullable|string|max:800',
            'price'       => 'nullable|numeric|min:0|max:9999999',
            'currency'    => 'nullable|string|size:3',
            'photo_url'   => 'nullable|string|max:1024',
            'is_sold_out' => 'sometimes|boolean',
            'is_active'   => 'sometimes|boolean',
        ]);

        $category = RestaurantMenuCategory::where('menu_id', $menu->id)->findOrFail($data['category_id']);

        $item = RestaurantMenuItem::create([
            'menu_id'     => $menu->id,
            'category_id' => $category->id,
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'price'       => $data['price'] ?? 0,
            'currency'    => isset($data['currency']) ? strtoupper($data['currency']) : null,
            'photo_url'   => $data['photo_url'] ?? null,
            'is_sold_out' => (bool) ($data['is_sold_out'] ?? false),
            'is_active'   => (bool) ($data['is_active'] ?? true),
            'sort_order'  => (int) RestaurantMenuItem::where('category_id', $category->id)->max('sort_order') + 1,
        ]);

        return response()->json(['data' => ['item' => $item]], 201);
    }

    public function updateItem(Request $request, Link $link, RestaurantMenuItem $item)
    {
        $menu = $this->menuFor($link);
        $this->assertOwns($menu, $item);

        $data = $request->validate([
            'category_id' => 'sometimes|integer',
            'name'        => 'sometimes|required|string|max:160',
            'description' => 'nullable|string|max:800',
            'price'       => 'sometimes|numeric|min:0|max:9999999',
            'currency'    => 'nullable|string|size:3',
            'photo_url'   => 'nullable|string|max:1024',
            'is_sold_out' => 'sometimes|boolean',
            'is_active'   => 'sometimes|boolean',
        ]);

        if (isset($data['category_id'])) {
            RestaurantMenuCategory::where('menu_id', $menu->id)->findOrFail($data['category_id']);
        }
        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $item->update($data);

        return response()->json(['data' => ['item' => $item->fresh()]]);
    }

    public function destroyItem(Request $request, Link $link, RestaurantMenuItem $item)
    {
        $menu = $this->menuFor($link);
        $this->assertOwns($menu, $item);
        $item->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function reorderItems(Request $request, Link $link)
    {
        $menu = $this->menuFor($link);
        $data = $request->validate(['order' => 'required|array', 'order.*' => 'integer']);

        foreach ($data['order'] as $i => $id) {
            RestaurantMenuItem::where('menu_id', $menu->id)->where('id', $id)
                ->update(['sort_order' => $i]);
        }

        return response()->json(['data' => ['reordered' => true]]);
    }

    // ── Tables (order mode) ──────────────────────────────────────
    public function storeTable(Request $request, Link $link)
    {
        $menu = $this->menuFor($link);
        $data = $request->validate(['label' => 'required|string|max:80']);

        $table = RestaurantTable::create([
            'menu_id'    => $menu->id,
            'label'      => $data['label'],
            'sort_order' => (int) RestaurantTable::where('menu_id', $menu->id)->max('sort_order') + 1,
        ]);

        return response()->json(['data' => ['table' => $table]], 201);
    }

    public function destroyTable(Request $request, Link $link, RestaurantTable $table)
    {
        $menu = $this->menuFor($link);
        $this->assertOwns($menu, $table);
        $table->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /** Printable QR page for a single table. */
    public function tableQr(Request $request, Link $link, RestaurantTable $table)
    {
        $menu = $this->menuFor($link);
        $this->assertOwns($menu, $table);

        $url = url('/' . $link->alias) . '?t=' . $table->code;

        return view('user.links.restaurant.table-qr', [
            'link'  => $link,
            'menu'  => $menu,
            'table' => $table,
            'url'   => $url,
        ]);
    }

    /** Printable sheet of every table's QR code on one page. */
    public function tablesQrSheet(Request $request, Link $link)
    {
        $menu = $this->menuFor($link);

        $tables = $menu->tables()->orderBy('id')->get()->map(function ($t) use ($link) {
            return [
                'label' => $t->label,
                'url'   => url('/' . $link->alias) . '?t=' . $t->code,
            ];
        });

        return view('user.links.restaurant.tables-qr-sheet', [
            'link'   => $link,
            'menu'   => $menu,
            'tables' => $tables,
        ]);
    }

    // ── Orders dashboard ─────────────────────────────────────────
    public function orders(Request $request, Link $link)
    {
        $menu = $this->menuFor($link);

        $orders = RestaurantOrder::with('items')
            ->where('menu_id', $menu->id)
            ->latest()
            ->limit(100)
            ->get();

        return view('user.links.restaurant.orders', [
            'link'   => $link,
            'menu'   => $menu,
            'orders' => $orders,
        ]);
    }

    /** Near-real-time polling endpoint for the orders dashboard. */
    public function pollOrders(Request $request, Link $link)
    {
        $menu = $this->menuFor($link);

        $query = RestaurantOrder::with('items')->where('menu_id', $menu->id);

        // Optional incremental fetch: only orders updated after a cursor.
        if ($since = $request->query('since')) {
            try {
                $query->where('updated_at', '>', \Carbon\Carbon::parse($since));
            } catch (\Throwable $e) {
                // ignore bad cursor, return recent set
            }
        }

        $orders = $query->latest('updated_at')->limit(100)->get();

        $openCount = RestaurantOrder::where('menu_id', $menu->id)
            ->whereIn('status', RestaurantOrder::OPEN_STATUSES)
            ->count();

        return response()->json(['data' => [
            'orders'     => $orders,
            'open_count' => $openCount,
            'server_time'=> now()->toIso8601String(),
        ]]);
    }

    public function updateOrderStatus(Request $request, Link $link, RestaurantOrder $order)
    {
        $menu = $this->menuFor($link);
        abort_if((int) $order->menu_id !== (int) $menu->id, 404);

        $data = $request->validate([
            'status' => 'required|in:' . implode(',', RestaurantOrder::STATUSES),
        ]);

        if (!$order->canTransitionTo($data['status'])) {
            return response()->json(['error' => [
                'message' => "Can't move an order from '{$order->status}' to '{$data['status']}'",
                'code'    => 'invalid_transition',
            ]], 422);
        }

        $order->update(['status' => $data['status']]);

        return response()->json(['data' => ['order' => $order->fresh('items')]]);
    }
}
