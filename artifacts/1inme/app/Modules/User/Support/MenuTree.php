<?php

namespace App\Modules\User\Support;

use Illuminate\Support\Collection;

/**
 * What a menu's structure IS -- one definition, used by both menu types,
 * by the editor and by the public page.
 *
 * Sana, 2026-09-23: "cats and sub cats" and, separately, "i should able to
 * hide/unhide items, sub cat, and cat also".
 *
 * ---- Hiding was already half-built -------------------------------------
 *
 * `is_active` has been on both categories and items since the tables were
 * created. The public pages already filter on it and the API already
 * accepts it. The EDITOR never sent it and offered no control, so the
 * column sat there doing nothing for every creator who has ever used these
 * pages. Same shape as every other bug this week: the save path and the
 * renderer agree, and the screen offers nothing.
 *
 * ---- Why one class ------------------------------------------------------
 *
 * Because "hidden" is not one flag once there are two levels. A visible
 * item inside a hidden sub-category is not visible; a visible sub-category
 * inside a hidden section is not visible. If the public page works that out
 * in Blade and the editor works it out in Alpine, the two WILL drift, and
 * the way a creator finds out is a visitor seeing something they hid.
 *
 * So: the page asks this class what to draw, the editor asks this class
 * what to grey out, and there is exactly one answer.
 */
class MenuTree
{
    /** How deep a menu may nest. A card, not a file system. */
    public const MAX_DEPTH = 2;

    /**
     * The sections of a menu, in order, each with its sub-sections and its
     * own items.
     *
     * A section whose parent is missing (the parent was deleted out from
     * under it, which the controllers try to prevent but a direct DB edit
     * or an older row could still produce) is treated as top-level rather
     * than dropped. An orphan a creator can see and fix beats an item that
     * silently stops appearing.
     *
     * This is the VISITOR's view: hidden sections, hidden sub-sections and
     * hidden items are simply not in it. The editor needs the opposite --
     * everything, with hidden marked -- and it needs to recompute that
     * after every toggle without a round trip, so it owns its own copy of
     * the rule in Alpine. The two are kept honest by
     * TheMenuCardCanHaveSectionsTest, not by sharing a function neither
     * side could actually call.
     *
     * @param  Collection  $categories  every category row of the menu
     * @param  Collection  $items       every item/product row of the menu
     * @return array<int, array{
     *     category: mixed,
     *     items: Collection,
     *     subs: array<int, array{category: mixed, items: Collection}>
     * }>
     */
    public static function build(Collection $categories, Collection $items): array
    {
        $categories = $categories->sortBy('sort_order')->values();
        $byId       = $categories->keyBy('id');
        $itemsByCat = $items->sortBy('sort_order')->groupBy('category_id');

        $roots    = [];
        $children = [];

        foreach ($categories as $cat) {
            $parentId = $cat->parent_id ?? null;

            // An orphan is a root, not a ghost. See the docblock.
            if ($parentId && $byId->has($parentId)) {
                $children[$parentId][] = $cat;
            } else {
                $roots[] = $cat;
            }
        }

        $visible = static fn ($cat) => (bool) ($cat->is_active ?? true);

        $tree = [];

        foreach ($roots as $root) {
            // Hiding a section hides everything inside it -- its own items,
            // its sub-sections, and their items -- whatever their own flags
            // say. Skipping the section here is what enforces that.
            if (! $visible($root)) {
                continue;
            }

            $subs = [];

            foreach ($children[$root->id] ?? [] as $sub) {
                if (! $visible($sub)) {
                    continue;
                }

                $subs[] = [
                    'category' => $sub,
                    'items'    => self::itemsFor($itemsByCat, $sub->id),
                ];
            }

            $tree[] = [
                'category' => $root,
                'items'    => self::itemsFor($itemsByCat, $root->id),
                'subs'     => $subs,
            ];
        }

        return $tree;
    }

    /** A visible category's visible items. */
    private static function itemsFor(Collection $grouped, $catId): Collection
    {
        return ($grouped[$catId] ?? collect())
            ->filter(fn ($i) => (bool) ($i->is_active ?? true))
            ->values();
    }

    /**
     * Whether `$parentId` is a legal parent for `$category` within `$menuId`.
     *
     * Three ways it is not, and each of them is reachable from a normal
     * editor with two menus open in two tabs:
     *   - the parent belongs to a different menu (or does not exist)
     *   - the parent is itself a sub-category (that would be depth 3)
     *   - the category already has sub-categories of its own
     *
     * The last one is the interesting case. Demoting a section that has
     * children would strand them at depth 3, so it is refused with a reason
     * the editor can show rather than silently flattened.
     *
     * @param  class-string  $model  the category Eloquent class
     * @return string|null  null when legal, otherwise the reason
     */
    public static function rejectParent(string $model, int $menuId, ?int $categoryId, ?int $parentId): ?string
    {
        if ($parentId === null) {
            return null;
        }

        if ($categoryId !== null && $parentId === $categoryId) {
            return 'A section cannot be inside itself.';
        }

        $parent = $model::where('menu_id', $menuId)->find($parentId);

        if (! $parent) {
            return 'That section is not part of this menu.';
        }

        if (! empty($parent->parent_id)) {
            return 'Sub-sections cannot hold their own sub-sections.';
        }

        if ($categoryId !== null && $model::where('menu_id', $menuId)->where('parent_id', $categoryId)->exists()) {
            return 'Move its sub-sections out first, then this one can become a sub-section.';
        }

        return null;
    }
}
