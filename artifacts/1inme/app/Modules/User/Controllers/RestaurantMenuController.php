<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuCategory;
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
        ]);

        $settings = $data['settings'] ?? $menu->settings ?? [];

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
        ]);

        $category = RestaurantMenuCategory::create([
            'menu_id'     => $menu->id,
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'sort_order'  => (int) RestaurantMenuCategory::where('menu_id', $menu->id)->max('sort_order') + 1,
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
            'is_active'   => 'sometimes|boolean',
        ]);

        $category->update($data);

        return response()->json(['data' => ['category' => $category->fresh()]]);
    }

    public function destroyCategory(Request $request, Link $link, RestaurantMenuCategory $category)
    {
        $menu = $this->menuFor($link);
        $this->assertOwns($menu, $category);

        // Items in the category go with it.
        RestaurantMenuItem::where('category_id', $category->id)->delete();
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
