<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\MenuItemMark;
use Illuminate\Support\Collection;

/**
 * What an item's marks are, cleaned up, and how they reach a page.
 *
 * ---- One query per page, not one per dish -------------------------------
 *
 * The vocabulary is a handful of rows shared by every item on every menu,
 * so it is read once and held for the request. A forty-dish menu that
 * queried per dish would be forty-one round trips on a page a hungry
 * person is waiting for -- the same trap `groupsForMany` exists to avoid.
 *
 * ---- Why sanitize is not optional ---------------------------------------
 *
 * What arrives is a JSON array from a browser. A key that is not in the
 * vocabulary, a grade of forty, the same mark twice, a dish wearing thirty
 * marks: all of those are one PUT away, and every one of them ends up
 * drawn on a public page. So the stored value is always the sanitized one,
 * and the rendered value is sanitized again on the way out -- because a
 * mark can be retired in admin long after a dish was saved wearing it.
 */
class MenuItemMarks
{
    /** The whole vocabulary, active and inactive, keyed by key. */
    private static ?Collection $all = null;

    /** @return Collection<string, MenuItemMark> */
    public static function vocabulary(): Collection
    {
        if (self::$all !== null) {
            return self::$all;
        }

        $order = array_keys(MenuItemMark::GROUPS);

        return self::$all = MenuItemMark::query()
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            // Diet before spice before preference, whatever order the rows
            // were created in, so two menus never read differently.
            ->sortBy(fn ($m) => [array_search($m->group, $order, true), $m->sort_order, $m->id])
            ->keyBy('key');
    }

    /** Only what an owner may pick, grouped for the picker. */
    public static function pickable(): Collection
    {
        return self::vocabulary()->filter(fn ($m) => $m->is_active)->values();
    }

    /** Tests and long-running workers change the table under us. */
    public static function forget(): void
    {
        self::$all = null;
    }

    /**
     * The value to STORE: known active keys only, deduped, grades clamped
     * to each mark's own ceiling, in vocabulary order, capped.
     *
     * @param  mixed  $raw  whatever the request sent
     * @return array<int, array{key: string, grade?: int}>
     */
    public static function sanitize(mixed $raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (! is_array($raw)) {
            return [];
        }

        $vocabulary = self::vocabulary();
        $seen = [];

        foreach ($raw as $entry) {
            // Accept a bare key as well as an object: the picker sends
            // objects, but a bare list is the obvious thing for anyone
            // scripting against this and costs nothing to allow.
            $key = is_array($entry) ? ($entry['key'] ?? null) : $entry;
            if (! is_string($key)) {
                continue;
            }

            $mark = $vocabulary->get($key);
            if (! $mark || ! $mark->is_active) {
                continue;
            }

            $grade = is_array($entry) ? (int) ($entry['grade'] ?? 1) : 1;
            $grade = max(1, min($mark->grades(), $grade));

            // The same mark twice keeps the higher grade rather than
            // whichever happened to be last.
            $seen[$key] = max($seen[$key] ?? 1, $grade);
        }

        $out = [];
        foreach ($vocabulary->keys() as $key) {
            if (! array_key_exists($key, $seen)) {
                continue;
            }
            $mark = $vocabulary->get($key);
            $out[] = $mark->is_graded
                ? ['key' => $key, 'grade' => $seen[$key]]
                : ['key' => $key];

            if (count($out) >= MenuItemMark::MAX_PER_ITEM) {
                break;
            }
        }

        return $out;
    }

    /**
     * The value to DRAW: everything a page needs for one item, with the
     * retired marks already gone.
     *
     * @return array<int, array{key:string,label:string,color:?string,grade:int,path:?string,solid:?string}>
     */
    public static function resolve(mixed $stored): array
    {
        $vocabulary = self::vocabulary();
        $out = [];

        foreach (self::sanitize($stored) as $entry) {
            $mark = $vocabulary->get($entry['key']);
            $shape = $mark->shape();

            $out[] = [
                'key'   => $mark->key,
                'label' => $mark->label,
                'color' => $mark->color,
                'grade' => (int) ($entry['grade'] ?? 1),
                'path'  => $shape['path'] ?? null,
                'solid' => ($shape['solid'] ?? '') ?: null,
            ];
        }

        return $out;
    }

    /**
     * How a dish's marks read to a screen reader and in a tooltip: the
     * icons themselves are decorative, so this is the only place the
     * meaning is written out in words.
     */
    public static function describe(array $resolved): string
    {
        return implode(', ', array_map(function ($m) {
            return $m['grade'] > 1 ? $m['label'].' '.$m['grade'] : $m['label'];
        }, $resolved));
    }

    /** The picker's payload, grouped and ready for the editor. */
    public static function forPicker(): array
    {
        $groups = [];

        foreach (self::pickable() as $mark) {
            $shape = $mark->shape();
            $groups[$mark->group]['label'] = $mark->groupLabel();
            $groups[$mark->group]['marks'][] = [
                'key'       => $mark->key,
                'label'     => $mark->label,
                'color'     => $mark->color,
                'is_graded' => (bool) $mark->is_graded,
                'grades'    => $mark->grades(),
                'path'      => $shape['path'] ?? null,
                'solid'     => ($shape['solid'] ?? '') ?: null,
            ];
        }

        return array_values($groups);
    }
}
