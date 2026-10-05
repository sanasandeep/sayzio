<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\MenuItemOptionGroup;
use App\Modules\User\Models\MenuOption;
use App\Modules\User\Models\MenuOptionGroup;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreProduct;
use App\Modules\User\Support\MenuOptionIcon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Choices on menu items: spice levels, sizes, toppings, add-ons.
 *
 * ---- Why one controller for both menu types ----------------------------
 *
 * Because the tables are shared, and because every single feature built for
 * one of these two page types this month has had to be built again for the
 * other -- late, after somebody noticed the store was missing it. The
 * routes mount this same class under both prefixes and the only thing that
 * differs is which menu table the link resolves into.
 *
 * ---- What it refuses ---------------------------------------------------
 *
 * Everything here is scoped to the menu the link owns. A group id from
 * someone else's menu 404s rather than being edited; an item id from
 * another menu cannot have a group attached to it. That is checked on
 * every call rather than trusted from the request, because the editor is
 * the only thing that SHOULD be calling these and the only thing that
 * definitely is not, is an attacker.
 */
class MenuOptionController extends Controller
{
    /** How many groups one menu may define, and how many choices per group. */
    public const MAX_GROUPS = 40;

    public const MAX_OPTIONS = 40;

    /**
     * The menu behind this link, whichever type it is, with the strings the
     * shared tables key on.
     *
     * @return array{0: RestaurantMenu|StoreMenu, 1: string, 2: string}
     *         [menu, menu_type, owner_type]
     */
    protected function resolve(Link $link, string $kind): array
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);

        if ($kind === 'restaurant') {
            abort_unless($link->type === Link::TYPE_RESTAURANT_MENU, 404);
            $menu = RestaurantMenu::firstOrCreate(
                ['link_id' => $link->id],
                ['user_id' => $link->user_id, 'mode' => RestaurantMenu::MODE_DISPLAY, 'currency' => 'USD']
            );

            return [$menu, MenuOptionGroup::RESTAURANT, MenuItemOptionGroup::RESTAURANT_ITEM];
        }

        abort_unless($link->type === Link::TYPE_STORE_MENU, 404);
        $menu = StoreMenu::firstOrCreate(
            ['link_id' => $link->id],
            ['user_id' => $link->user_id, 'mode' => StoreMenu::MODE_DISPLAY, 'currency' => 'USD']
        );

        return [$menu, MenuOptionGroup::STORE, MenuItemOptionGroup::STORE_PRODUCT];
    }

    /**
     * NOTE ON ARGUMENT ORDER
     *
     * Laravel fills controller arguments POSITIONALLY from the route's
     * parameter list -- the URI segments first, then whatever `->defaults()`
     * added. It does not match them up by name. So `$kind` is last on every
     * method here, after every segment: put it earlier and it receives the
     * group id while the group receives the word "restaurant", and the
     * lookup 404s in a way that looks like a permissions bug.
     */

    /** A group on THIS menu, or a 404. Never "a group with that id". */
    protected function groupOn($menu, string $menuType, string|int $groupId): MenuOptionGroup
    {
        $group = MenuOptionGroup::where('id', (int) $groupId)
            ->where('menu_type', $menuType)
            ->where('menu_id', $menu->id)
            ->first();

        abort_if(! $group, 404);

        return $group;
    }

    /** The validated shape of a group, shared by store and update. */
    protected function groupRules(): array
    {
        return [
            'name'           => 'required|string|max:80',
            'hint'           => 'nullable|string|max:160',
            'is_required'    => 'sometimes|boolean',
            'min_select'     => 'nullable|integer|min:0|max:20',
            // Null is meaningful here: it is "no ceiling", not "zero".
            'max_select'     => 'nullable|integer|min:1|max:20',
            'max_per_option' => 'nullable|integer|min:1|max:20',
            'is_active'      => 'sometimes|boolean',
        ];
    }

    protected function groupAttributes(array $data): array
    {
        return [
            'name'           => trim($data['name']),
            'hint'           => isset($data['hint']) ? (trim((string) $data['hint']) ?: null) : null,
            'is_required'    => (bool) ($data['is_required'] ?? false),
            'min_select'     => max(0, (int) ($data['min_select'] ?? 0)),
            'max_select'     => array_key_exists('max_select', $data) && $data['max_select'] !== null
                ? max(1, (int) $data['max_select'])
                : null,
            'max_per_option' => max(1, (int) ($data['max_per_option'] ?? 1)),
            'is_active'      => (bool) ($data['is_active'] ?? true),
        ];
    }

    /**
     * The validated shape of one choice, shared by store and update so the
     * two cannot drift -- which they already had, before the icon columns
     * made it three places to remember.
     */
    protected function optionRules(): array
    {
        return [
            'name'        => 'required|string|max:80',
            // Signed on purpose: a smaller size may take money off.
            'price_delta' => 'nullable|numeric|min:-999999|max:999999',
            // A key out of the catalogue, or nothing. An unknown key is not
            // a 422: it comes from a picker that cannot produce one, and
            // sanitize() turns it into "no icon" rather than a gap.
            'icon'        => 'nullable|string|max:24',
            'icon_repeat' => 'nullable|integer|min:1|max:'.MenuOptionIcon::MAX_REPEAT,
            'is_sold_out' => 'sometimes|boolean',
            'is_active'   => 'sometimes|boolean',
        ];
    }

    protected function optionAttributes(array $data): array
    {
        $icon = MenuOptionIcon::sanitize($data['icon'] ?? null);

        return [
            'name'        => trim($data['name']),
            'price_delta' => round((float) ($data['price_delta'] ?? 0), 2),
            'icon'        => $icon,
            'icon_repeat' => MenuOptionIcon::repeat($icon, $data['icon_repeat'] ?? 1),
            'is_sold_out' => (bool) ($data['is_sold_out'] ?? false),
            'is_active'   => (bool) ($data['is_active'] ?? true),
        ];
    }

    /** Every group this menu defines, with its choices and what it is on. */
    public function index(Request $request, Link $link, string $kind = 'restaurant')
    {
        [$menu, $menuType, $ownerType] = $this->resolve($link, $kind);

        $groups = MenuOptionGroup::where('menu_type', $menuType)
            ->where('menu_id', $menu->id)
            ->with('options')
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        $attachments = MenuItemOptionGroup::where('owner_type', $ownerType)
            ->whereIn('group_id', $groups->pluck('id'))
            ->get()
            ->groupBy('group_id')
            ->map(fn ($rows) => $rows->pluck('owner_id')->map(fn ($i) => (int) $i)->values());

        return response()->json(['data' => [
            'groups' => $groups->map(fn ($g) => [
                'id'             => $g->id,
                'name'           => $g->name,
                'hint'           => $g->hint,
                'is_required'    => (bool) $g->is_required,
                'min_select'     => (int) $g->min_select,
                'max_select'     => $g->max_select === null ? null : (int) $g->max_select,
                'max_per_option' => $g->perOptionCap(),
                'is_active'      => (bool) $g->is_active,
                'sort_order'     => (int) $g->sort_order,
                'rule_label'     => \App\Modules\User\Support\MenuOptionSelection::ruleLabel($g),
                'options'        => $g->options->map(fn ($o) => [
                    'id'          => $o->id,
                    'name'        => $o->name,
                    'icon'        => $o->iconKey(),
                    'icon_repeat' => $o->iconRepeat(),
                    'price_delta' => (float) $o->price_delta,
                    'is_sold_out' => (bool) $o->is_sold_out,
                    'is_active'   => (bool) $o->is_active,
                    'sort_order'  => (int) $o->sort_order,
                ])->values(),
                'item_ids'       => $attachments->get($g->id, collect())->all(),
            ])->values(),
        ]]);
    }

    public function storeGroup(Request $request, Link $link, string $kind = 'restaurant')
    {
        [$menu, $menuType] = $this->resolve($link, $kind);

        $count = MenuOptionGroup::where('menu_type', $menuType)->where('menu_id', $menu->id)->count();
        if ($count >= self::MAX_GROUPS) {
            return response()->json([
                'error' => ['message' => 'A menu can have at most '.self::MAX_GROUPS.' choice groups.'],
            ], 422);
        }

        $data = $request->validate($this->groupRules());

        $group = MenuOptionGroup::create(array_merge($this->groupAttributes($data), [
            'menu_type'  => $menuType,
            'menu_id'    => $menu->id,
            'sort_order' => $count,
        ]));

        return response()->json(['data' => ['group' => $group->fresh()]], 201);
    }

    public function updateGroup(Request $request, Link $link, string|int $group, string $kind = 'restaurant')
    {
        [$menu, $menuType] = $this->resolve($link, $kind);
        $model = $this->groupOn($menu, $menuType, $group);

        $data = $request->validate($this->groupRules());
        $model->update($this->groupAttributes($data));

        return response()->json(['data' => ['group' => $model->fresh()]]);
    }

    public function destroyGroup(Request $request, Link $link, string|int $group, string $kind = 'restaurant')
    {
        [$menu, $menuType] = $this->resolve($link, $kind);
        $model = $this->groupOn($menu, $menuType, $group);

        DB::transaction(function () use ($model) {
            // The rows that point at it go first, so nothing is left
            // pointing at a group that no longer exists.
            MenuItemOptionGroup::where('group_id', $model->id)->delete();
            MenuOption::where('group_id', $model->id)->delete();
            $model->delete();
        });

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function storeOption(Request $request, Link $link, string|int $group, string $kind = 'restaurant')
    {
        [$menu, $menuType] = $this->resolve($link, $kind);
        $model = $this->groupOn($menu, $menuType, $group);

        $count = MenuOption::where('group_id', $model->id)->count();
        if ($count >= self::MAX_OPTIONS) {
            return response()->json([
                'error' => ['message' => 'A group can have at most '.self::MAX_OPTIONS.' choices.'],
            ], 422);
        }

        $data = $request->validate($this->optionRules());

        $option = MenuOption::create(array_merge($this->optionAttributes($data), [
            'group_id'   => $model->id,
            'sort_order' => $count,
        ]));

        return response()->json(['data' => ['option' => $option]], 201);
    }

    public function updateOption(Request $request, Link $link, string|int $group, string|int $option, string $kind = 'restaurant')
    {
        [$menu, $menuType] = $this->resolve($link, $kind);
        $model = $this->groupOn($menu, $menuType, $group);

        $row = MenuOption::where('id', (int) $option)->where('group_id', $model->id)->first();
        abort_if(! $row, 404);

        $data = $request->validate($this->optionRules());

        $row->update($this->optionAttributes($data));

        return response()->json(['data' => ['option' => $row->fresh()]]);
    }

    public function destroyOption(Request $request, Link $link, string|int $group, string|int $option, string $kind = 'restaurant')
    {
        [$menu, $menuType] = $this->resolve($link, $kind);
        $model = $this->groupOn($menu, $menuType, $group);

        $row = MenuOption::where('id', (int) $option)->where('group_id', $model->id)->first();
        abort_if(! $row, 404);
        $row->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * Set which items a group is on, as the whole list.
     *
     * The editor shows a checkbox per item, so it knows the complete answer
     * every time it saves. Sending the whole list means a box unticked
     * while two tabs were open cannot silently survive, which an
     * add-one/remove-one pair would allow.
     */
    public function attach(Request $request, Link $link, string|int $group, string $kind = 'restaurant')
    {
        [$menu, $menuType, $ownerType] = $this->resolve($link, $kind);
        $model = $this->groupOn($menu, $menuType, $group);

        $data = $request->validate([
            'item_ids'   => 'present|array|max:500',
            'item_ids.*' => 'integer',
        ]);

        // Only items on THIS menu. An id from somewhere else is dropped
        // rather than 422'd: the editor cannot produce one, so a request
        // carrying one is not a user to explain things to.
        $wanted = collect($data['item_ids'])->map(fn ($i) => (int) $i)->unique();

        $mine = $ownerType === MenuItemOptionGroup::RESTAURANT_ITEM
            ? RestaurantMenuItem::where('menu_id', $menu->id)->whereIn('id', $wanted)->pluck('id')
            : StoreProduct::where('menu_id', $menu->id)->whereIn('id', $wanted)->pluck('id');

        $mine = $mine->map(fn ($i) => (int) $i)->values();

        DB::transaction(function () use ($model, $ownerType, $mine) {
            $existing = MenuItemOptionGroup::where('group_id', $model->id)
                ->where('owner_type', $ownerType)
                ->get()
                ->keyBy(fn ($row) => (int) $row->owner_id);

            foreach ($existing as $ownerId => $row) {
                if (! $mine->contains($ownerId)) {
                    $row->delete();
                }
            }

            foreach ($mine as $ownerId) {
                if ($existing->has($ownerId)) {
                    continue;
                }
                MenuItemOptionGroup::create([
                    'group_id'   => $model->id,
                    'owner_type' => $ownerType,
                    'owner_id'   => $ownerId,
                    // Appended: an item that already shows two groups keeps
                    // showing them in the order it showed them.
                    'sort_order' => MenuItemOptionGroup::where('owner_type', $ownerType)
                        ->where('owner_id', $ownerId)->count(),
                ]);
            }
        });

        return response()->json(['data' => ['item_ids' => $mine->all()]]);
    }
}
