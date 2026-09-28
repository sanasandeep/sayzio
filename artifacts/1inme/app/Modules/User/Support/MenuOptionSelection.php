<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\MenuOption;
use App\Modules\User\Models\MenuOptionGroup;
use Illuminate\Support\Collection;

/**
 * The rules about what a guest may choose, and what those choices cost.
 *
 * ---- Why this is one class rather than page code -----------------------
 *
 * The page has to know the rules -- a guest should be stopped before they
 * tap "Place order", not after. The server has to know them too, because
 * everything the page says arrives over HTTP and can be edited on the way.
 * When those are two implementations they disagree, and the way they
 * disagree is that one of them prices an order differently from the other.
 *
 * So the page enforces the rules for the guest's sake and this class
 * enforces them for the bill's sake, and the numbers the restaurant is
 * told come from HERE, every time, computed from prices read out of the
 * database rather than from anything the browser sent.
 *
 * ---- What the two limits mean ------------------------------------------
 *
 * `max_select` counts DISTINCT choices -- "pick at most 2 toppings" means
 * two different toppings. `max_per_option` caps how many times one choice
 * can be taken -- "extra cheese x3". They are separate questions and
 * collapsing them into one number makes "pick 2 toppings" and "3 of the
 * same add-on" impossible to express at the same time.
 */
class MenuOptionSelection
{
    /** A guest cannot send more lines than this for one item. */
    public const MAX_LINES = 40;

    /**
     * Price and validate a guest's choices for ONE cart line.
     *
     * @param  Collection<int, MenuOptionGroup>  $groups  the item's groups, with `options` loaded
     * @param  array<int, array{option_id:int|string, quantity?:int|string}>  $picks
     * @return array{lines: array<int, array{group:string, name:string, delta:float, quantity:int}>, total: float}
     *
     * @throws \InvalidArgumentException with a message meant for the guest
     */
    public static function resolve(Collection $groups, array $picks, string $itemName): array
    {
        if (count($picks) > self::MAX_LINES) {
            throw new \InvalidArgumentException('Too many choices on '.$itemName.'.');
        }

        // Every option this item actually offers, by id. Anything not in
        // here was not on offer, whatever the request says.
        $offered = [];
        foreach ($groups as $group) {
            if (! $group->is_active) {
                continue;
            }
            foreach ($group->options as $option) {
                if ($option->isAvailable()) {
                    $offered[(int) $option->id] = [$group, $option];
                }
            }
        }

        /** @var array<int, array<int, int>> group id => option id => quantity */
        $chosen = [];
        foreach ($picks as $pick) {
            $id = (int) ($pick['option_id'] ?? 0);
            if (! isset($offered[$id])) {
                throw new \InvalidArgumentException(
                    'One of the choices on '.$itemName.' is no longer available.'
                );
            }
            /** @var MenuOptionGroup $group */
            /** @var MenuOption $option */
            [$group, $option] = $offered[$id];

            $qty = max(1, (int) ($pick['quantity'] ?? 1));
            $cap = $group->perOptionCap();
            if ($qty > $cap) {
                throw new \InvalidArgumentException(
                    $option->name.' can be added at most '.$cap.' '.($cap === 1 ? 'time' : 'times').'.'
                );
            }

            $gid = (int) $group->id;
            // The same option sent twice is one choice at the combined
            // quantity, not two lines that each pass the cap on their own.
            $chosen[$gid][$id] = ($chosen[$gid][$id] ?? 0) + $qty;
            if ($chosen[$gid][$id] > $cap) {
                throw new \InvalidArgumentException(
                    $option->name.' can be added at most '.$cap.' '.($cap === 1 ? 'time' : 'times').'.'
                );
            }
        }

        // Now the per-group counts, including the groups nobody touched --
        // a required group that was simply left out is the case that
        // matters most here.
        $lines = [];
        $total = 0.0;

        foreach ($groups as $group) {
            if (! $group->is_active) {
                continue;
            }

            $picked = $chosen[(int) $group->id] ?? [];
            $distinct = count($picked);
            ['min' => $min, 'max' => $max] = $group->bounds();

            if ($distinct < $min) {
                throw new \InvalidArgumentException(
                    $min === 1
                        ? 'Please choose a '.self::lower($group->name).' for '.$itemName.'.'
                        : 'Please choose at least '.$min.' from '.$group->name.' for '.$itemName.'.'
                );
            }
            if ($max !== null && $distinct > $max) {
                throw new \InvalidArgumentException(
                    'Please choose at most '.$max.' from '.$group->name.' for '.$itemName.'.'
                );
            }

            // Ordered by the group's own order, not by the order the
            // browser happened to send them in, so two identical orders
            // read identically on the kitchen screen.
            foreach ($group->options as $option) {
                $qty = $picked[(int) $option->id] ?? 0;
                if ($qty < 1) {
                    continue;
                }
                $delta = round((float) $option->price_delta, 2);
                $lines[] = [
                    'group'    => $group->name,
                    'name'     => $option->name,
                    'delta'    => $delta,
                    'quantity' => $qty,
                ];
                $total += $delta * $qty;
            }
        }

        return ['lines' => $lines, 'total' => round($total, 2)];
    }

    /**
     * The groups an item offers, in the order that item shows them, with
     * their choices loaded. Inactive groups are left in so `resolve()` can
     * ignore them by the same rule everywhere rather than each caller
     * remembering to filter.
     *
     * @return Collection<int, MenuOptionGroup>
     */
    public static function groupsFor(string $ownerType, int $ownerId): Collection
    {
        return MenuOptionGroup::query()
            ->join('menu_item_option_groups as a', 'a.group_id', '=', 'menu_option_groups.id')
            ->where('a.owner_type', $ownerType)
            ->where('a.owner_id', $ownerId)
            ->orderBy('a.sort_order')
            ->orderBy('menu_option_groups.id')
            ->select('menu_option_groups.*')
            ->with(['options' => fn ($q) => $q->where('is_active', true)])
            ->get();
    }

    /**
     * The same thing for a whole page of items in one go, keyed by item id.
     * A menu with forty items must not mean forty-one queries.
     *
     * @param  array<int, int>  $ownerIds
     * @return array<int, Collection<int, MenuOptionGroup>>
     */
    public static function groupsForMany(string $ownerType, array $ownerIds): array
    {
        if ($ownerIds === []) {
            return [];
        }

        $rows = MenuOptionGroup::query()
            ->join('menu_item_option_groups as a', 'a.group_id', '=', 'menu_option_groups.id')
            ->where('a.owner_type', $ownerType)
            ->whereIn('a.owner_id', $ownerIds)
            ->orderBy('a.sort_order')
            ->orderBy('menu_option_groups.id')
            ->select('menu_option_groups.*', 'a.owner_id as pivot_owner_id', 'a.sort_order as pivot_sort')
            ->with(['options' => fn ($q) => $q->where('is_active', true)])
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->pivot_owner_id][] = $row;
        }

        return array_map(fn ($list) => collect($list), $out);
    }

    /**
     * How the page should describe a group's rule in one short line.
     * Written once here so the restaurant, the store and the editor's
     * preview all say the same thing.
     */
    public static function ruleLabel(MenuOptionGroup $group): string
    {
        ['min' => $min, 'max' => $max] = $group->bounds();

        if ($min > 0 && $max === $min) {
            return $min === 1 ? 'Choose 1' : 'Choose '.$min;
        }

        $parts = [];
        if ($min > 0) {
            $parts[] = 'choose at least '.$min;
        }
        if ($max !== null) {
            $parts[] = ($parts === [] ? 'choose up to ' : 'up to ').$max;
        }
        if ($parts === []) {
            $parts[] = 'optional';
        }

        $label = ucfirst(implode(', ', $parts));

        $cap = $group->perOptionCap();
        if ($cap > 1) {
            $label .= ' · each up to '.$cap.'x';
        }

        return $label;
    }

    /** "Spice level" -> "spice level", for use mid-sentence. */
    private static function lower(string $name): string
    {
        // Leave an acronym alone: "GST" mid-sentence is not "gST".
        return preg_match('/^[A-Z]{2,}$/', $name) ? $name : mb_strtolower(mb_substr($name, 0, 1)).mb_substr($name, 1);
    }
}
