<?php

namespace App\Modules\User\Support;

use App\Modules\Admin\Models\BgCatalogEntry;
use Illuminate\Support\Facades\Cache;

/**
 * The one place the seven background catalogs read the database.
 *
 * This sits on the hot path: every published page that paints a preset,
 * gradient, mesh, pattern, tiles or torn background resolves it through a
 * catalog, and a catalog now has to consider admin rows. So the whole
 * table is read once, cached forever, and memoised per request -- a query
 * per page render for a table that changes a few times a year would be a
 * poor trade for the feature.
 *
 * Two rules keep the merge safe:
 *
 *  - A missing or broken table resolves to "no overrides", never an
 *    exception. This code runs before its own migration during a deploy,
 *    and on every page on the platform afterwards; an empty result there
 *    means the shipped library, which is the right answer.
 *
 *  - `hidden()` is separate from `for()`. Hiding a look removes it from
 *    the picker and NOT from the renderer, so pages that already chose it
 *    keep rendering.
 */
class CatalogOverrides
{
    private const CACHE_KEY = 'bg_catalog_entries.v1';

    /** @var array<string, array<string, array>>|null */
    private static ?array $memo = null;

    /**
     * Every row, grouped by kind and keyed by entry key.
     *
     * @return array<string, array<string, array{label: string, payload: array, is_active: bool, sort_order: int}>>
     */
    public static function all(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        try {
            self::$memo = Cache::rememberForever(self::CACHE_KEY, function () {
                $out = [];
                foreach (BgCatalogEntry::query()->orderBy('sort_order')->orderBy('id')->get() as $row) {
                    $out[$row->kind][$row->entry_key] = [
                        'label'      => (string) $row->label,
                        'payload'    => is_array($row->payload) ? $row->payload : [],
                        'is_active'  => (bool) $row->is_active,
                        'sort_order' => (int) $row->sort_order,
                    ];
                }

                return $out;
            });
        } catch (\Throwable $e) {
            // Before the migration runs, or if the cache store is down:
            // the shipped library is a correct answer, an exception is not.
            self::$memo = [];
        }

        return self::$memo;
    }

    /**
     * Rows for one catalog, keyed by entry key, in admin sort order.
     *
     * Includes hidden rows: an override of a shipped look still overrides
     * it when hidden, because hiding is about the picker, not the paint.
     *
     * @return array<string, array{label: string, payload: array, is_active: bool, sort_order: int}>
     */
    public static function for(string $kind): array
    {
        return self::all()[$kind] ?? [];
    }

    /**
     * Keys the admin has hidden from the picker for this catalog.
     *
     * A shipped key can appear here (a row that exists only to hide it),
     * which is the only way to take a compiled-in look out of the library
     * without a deploy.
     *
     * @return array<string, true>
     */
    public static function hidden(string $kind): array
    {
        $out = [];
        foreach (self::for($kind) as $key => $row) {
            if (! $row['is_active']) {
                $out[$key] = true;
            }
        }

        return $out;
    }

    /** Called whenever a row is written; also resets this request's memo. */
    public static function flush(): void
    {
        self::$memo = null;
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable $e) {
            // A cache store that cannot forget is not a reason to fail the save.
        }
    }
}
