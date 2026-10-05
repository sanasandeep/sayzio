<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuCategory;
use App\Modules\User\Models\RestaurantMenuCoupon;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\RestaurantOrder;
use App\Modules\User\Models\RestaurantTable;
use App\Modules\User\Support\MenuItemMarks;
use App\Modules\User\Support\MenuOrderRange;
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
            'price_display'         => 'nullable|string|max:16',
            'price_position'        => 'nullable|string|max:16',
            'price_decimals'        => 'sometimes|boolean',
            'fulfilment_modes'      => 'sometimes|array',
            'fulfilment_modes.*'    => 'string|max:16',
            'charges'        => 'sometimes|array',
            'charges.*.label'       => 'nullable|string|max:60',
            'charges.*.type'        => 'nullable|string|in:fixed,percent',
            'charges.*.amount'      => 'nullable|numeric|min:0|max:999999',
            'charges.*.modes'       => 'nullable|array',
            'charges.*.modes.*'     => 'nullable|string|max:16',
            // Menu colours (Sana, 2026-09-23: "i cannot change colors of
            // menu items and all"). Each is optional; an absent one keeps
            // inheriting the page ink, which is what the page did before.
        ] + \App\Modules\User\Support\MenuPresentation::colourRules() + \App\Modules\User\Support\MenuHero::rules() + \App\Modules\User\Support\MenuSectionNav::rules() + [
            'tax_enabled'     => 'sometimes|boolean',
            'tax_rate'        => 'nullable|numeric|min:0|max:100',
            'tax_inclusive'   => 'sometimes|boolean',
            'tax_label'       => 'nullable|string|max:24',
            'charges_before_tax' => 'sometimes|boolean',
            'tokens_enabled'  => 'sometimes|boolean',
            'timing_enabled'  => 'sometimes|boolean',
            'timing_interval' => 'sometimes|nullable|integer',
            'timing_open'     => 'sometimes|nullable|string|max:5',
            'timing_close'    => 'sometimes|nullable|string|max:5',
            'timing_prep'     => 'sometimes|nullable|integer',
            'tokens_reset'    => 'sometimes|nullable|string|max:16',
            // What the guest sees once the order goes through (Sana,
            // 2026-09-28). The mode is validated against the catalog rather
            // than here, so an unknown value falls back rather than 422s a
            // save that came from an older client.
            'confirm_mode'     => 'nullable|string|max:16',
            'confirm_url'      => 'nullable|string|max:2048',
            'confirm_message'  => 'nullable|string|max:600',
            'confirm_headline' => 'nullable|string|max:80',
        ]);

        $settings = $data['settings'] ?? ($menu->settings ?? []);

        // Which of the five layouts draws the items. Validated against the
        // catalog rather than trusted, so an unknown key falls back to the
        // list layout this page has always had instead of rendering nothing.
        if ($request->has('layout')) {
            $settings['layout'] = \App\Modules\User\Support\MenuPresentation::layout($data['layout'] ?? null);
        }

        // Sana, 2026-10-05: "Section jumping" and "section headings should
        // have numbers default".
        //
        // Same treatment as the layout: the catalogue decides what is
        // valid, so an unknown key falls back to the default rather than
        // rendering nothing. Validated AND written -- the first version of
        // this change validated both and wrote neither, which is a control
        // that exists and does nothing, for the seventeenth time.
        if ($request->has('section_nav')) {
            $settings['section_nav'] = \App\Modules\User\Support\MenuSectionNav::nav($data['section_nav'] ?? null);
        }
        if ($request->has('section_marker')) {
            $settings['section_marker'] = \App\Modules\User\Support\MenuSectionNav::marker($data['section_marker'] ?? null);
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

        // How an order is handed over, and what that adds to it. Both are
        // validated against MenuFulfilment rather than trusted, so an
        // unknown mode or a malformed charge is dropped instead of being
        // stored and then silently ignored by the calculator.
        if ($request->has('fulfilment_modes')) {
            $settings['fulfilment_modes'] = \App\Modules\User\Support\MenuFulfilment::modesFor(
                ['fulfilment_modes' => $data['fulfilment_modes'] ?? []], true
            );
        }
        if ($request->has('charges')) {
            $clean = \App\Modules\User\Support\MenuFulfilment::charges(['charges' => $data['charges'] ?? []]);
            if ($clean === []) {
                unset($settings['charges']);
            } else {
                $settings['charges'] = $clean;
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

        // Whether tax applies to the charges. The owner's tax position,
        // so it is theirs to set; default off keeps every existing menu's
        // totals exactly where they are.
        if ($request->has('charges_before_tax')) {
            if ($data['charges_before_tax'] ?? false) {
                $settings['charges_before_tax'] = true;
            } else {
                unset($settings['charges_before_tax']);
            }
        }

        // When a takeaway or delivery order is wanted. One block, so the
        // window and the prep time that shapes it cannot be saved apart.
        if ($request->hasAny(['timing_enabled', 'timing_interval', 'timing_open', 'timing_close', 'timing_prep'])) {
            $settings['handover_timing'] = \App\Modules\User\Support\MenuHandoverTiming::sanitize([
                'enabled'      => $request->boolean('timing_enabled'),
                'interval'     => $request->input('timing_interval'),
                'open'         => $request->input('timing_open'),
                'close'        => $request->input('timing_close'),
                'prep_minutes' => $request->input('timing_prep'),
            ]);
        }

        // The number a guest is told to listen for, and how often it goes
        // back to 1. Written as one block so "on" and "resets daily" can
        // never be saved apart.
        if ($request->hasAny(['tokens_enabled', 'tokens_reset'])) {
            $settings['tokens'] = \App\Modules\User\Support\MenuOrderToken::sanitize([
                'enabled' => $request->boolean('tokens_enabled', true),
                'reset'   => $request->input('tokens_reset'),
            ]);
        }

        // What the guest sees the moment the order goes through. Written as
        // one block so the mode and the thing that mode needs can never be
        // saved apart from one another.
        if ($request->hasAny(['confirm_mode', 'confirm_url', 'confirm_message', 'confirm_headline'])) {
            $settings['confirmation'] = \App\Modules\User\Support\MenuConfirmation::sanitize([
                'mode'     => $data['confirm_mode'] ?? null,
                'url'      => $data['confirm_url'] ?? null,
                'message'  => $data['confirm_message'] ?? null,
                'headline' => $data['confirm_headline'] ?? null,
            ]);
        }


        // The hero: what the top of the page shows and how. Same shape as
        // the colours above -- only written when the form sent the key, and
        // a blank colour CLEARS rather than storing '', so "back to
        // inheriting" stays reachable.
        foreach (['hero_title_hidden', 'hero_badge_hidden'] as $hk) {
            if ($request->has($hk)) {
                $settings[$hk] = $request->boolean($hk);
            }
        }
        if ($request->has('hero_align')) {
            $settings['hero_align'] = \App\Modules\User\Support\MenuHero::align($data['hero_align'] ?? null);
        }
        if ($request->has('hero_size')) {
            $settings['hero_size'] = \App\Modules\User\Support\MenuHero::size($data['hero_size'] ?? null);
        }
        foreach (['hero_title_color', 'hero_badge_color'] as $hk) {
            if (! $request->has($hk)) {
                continue;
            }
            $hex = \App\Modules\User\Support\MenuPresentation::hex($data[$hk] ?? null);
            if ($hex === '') {
                unset($settings[$hk]);
            } else {
                $settings[$hk] = $hex;
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
            // Sana, 2026-10-05: "option with selecting icons also".
            'icon'        => ['nullable', 'string', 'max:40', \Illuminate\Validation\Rule::in(array_keys(\App\Modules\User\Support\MenuSectionNav::ICONS))],
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
            // Validated AND saved. The first version of this accepted an
            // icon, passed every rule, and then did not write it -- which
            // is this codebase's oldest bug shape: a control that exists
            // and does nothing.
            'icon'        => \App\Modules\User\Support\MenuSectionNav::icon($data['icon'] ?? null),
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
            // Sana, 2026-10-05: "option with selecting icons also".
            'icon'        => ['nullable', 'string', 'max:40', \Illuminate\Validation\Rule::in(array_keys(\App\Modules\User\Support\MenuSectionNav::ICONS))],
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
            'marks'       => 'sometimes|array|max:40',
            'is_active'   => 'sometimes|boolean',
        ] + \App\Modules\User\Support\MenuBulkOrder::rules());

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
            // Never what the browser sent: an unknown key, a grade of
            // forty and a dish wearing thirty marks are each one PUT
            // away, and every one of them draws on a public page.
            'marks'       => MenuItemMarks::sanitize($data['marks'] ?? []),
            'is_active'   => (bool) ($data['is_active'] ?? true),
            'sort_order'  => (int) RestaurantMenuItem::where('category_id', $category->id)->max('sort_order') + 1,
        ] + \App\Modules\User\Support\MenuBulkOrder::input($data));

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
            'marks'       => 'sometimes|array|max:40',
            'is_active'   => 'sometimes|boolean',
        ] + \App\Modules\User\Support\MenuBulkOrder::rules());

        if (isset($data['category_id'])) {
            RestaurantMenuCategory::where('menu_id', $menu->id)->findOrFail($data['category_id']);
        }
        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        if (array_key_exists('marks', $data)) {
            $data['marks'] = MenuItemMarks::sanitize($data['marks']);
        }

        // Resolved against the row as it stands, so a new ceiling is
        // checked against the floor already saved -- not the default.
        $data = array_merge($data, \App\Modules\User\Support\MenuBulkOrder::input($data, $item));

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

        // Sana, 2026-09-28: "wht is too many order? selected with dates?"
        // It used to be latest()->limit(100): on a busy Saturday the
        // hundred-and-first order silently did not exist, and yesterday
        // could not be looked at at all.
        $range = MenuOrderRange::resolve(
            $request->query('range'),
            $request->query('from'),
            $request->query('to'),
            $link->user?->effectiveTimezone() ?? \App\Support\PlatformTimezone::platformDefault()
        );

        $scoped = fn () => MenuOrderRange::apply(
            RestaurantOrder::where('menu_id', $menu->id), $range
        );

        $total = $scoped()->count();
        $page = max(1, (int) $request->query('page', 1));

        $orders = $scoped()->with('items')
            ->orderByDesc('id')
            ->forPage($page, MenuOrderRange::PER_PAGE)
            ->get();

        // The "needs attention" number is deliberately NOT scoped to the
        // range: an open order from yesterday is still open, and hiding it
        // because the screen is showing today is how one gets forgotten.
        $openCount = RestaurantOrder::where('menu_id', $menu->id)
            ->whereIn('status', RestaurantOrder::OPEN_STATUSES)
            ->count();

        if ($request->query('format') === 'json') {
            return response()->json(['data' => [
                'orders' => $orders,
                'range'  => MenuOrderRange::forPage($range, $total, $orders->count(), 'orders'),
                'page'   => $page,
                'more'   => ($page * MenuOrderRange::PER_PAGE) < $total,
            ]]);
        }

        return view('user.links.restaurant.orders', [
            'link'      => $link,
            'menu'      => $menu,
            'orders'    => $orders,
            'range'     => $range,
            'rangeMeta' => MenuOrderRange::forPage($range, $total, $orders->count(), 'orders'),
            'page'      => $page,
            'hasMore'   => ($page * MenuOrderRange::PER_PAGE) < $total,
            'openCount' => $openCount,
            // Sana, 2026-10-05: "orders dashbord summary missing".
            // Aggregated over the scoped QUERY, not the fetched page, so
            // the numbers do not change when somebody taps "load more".
            'summary'   => \App\Modules\User\Support\MenuOrderSummary::of($scoped(), RestaurantOrder::class),
            'labels'    => \App\Modules\User\Support\MenuOrderSummary::labels(RestaurantOrder::class),
            'exportUrl' => route('user.links.restaurant.orders.export', ['link' => $link] + $request->only(['range', 'from', 'to', 'status'])),
            // Sana, 2026-10-05: "i need top items, item sales, reccuring
            // things, highlights or anything related....". The totals say
            // how much came in; these say what to do about it.
            'insights'  => \App\Modules\User\Support\MenuInsights::of(
                $scoped(),
                \App\Modules\User\Models\RestaurantOrder::class,
                \App\Modules\User\Models\RestaurantOrderItem::class,
                $menu,
                \App\Modules\User\Models\RestaurantMenuItem::class,
            ),
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
