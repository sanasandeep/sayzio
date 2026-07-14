<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\CoinPackage;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\Price;
use Illuminate\Support\Facades\Cache;

/**
 * Single source for the public /pricing page's cacheable catalogue: every
 * active public plan and coin package with their `prices` relation, stored
 * as PLAIN ATTRIBUTE ARRAYS and rehydrated on read — never serialized
 * Eloquent models, which the file cache turns into __PHP_Incomplete_Class.
 *
 * The catalogue is user-independent (both display currencies are embedded
 * per row and tax is layered on top per-user by the controller), so all
 * visitors share one entry. With production DB_PERSISTENT=false each query
 * pays a ~3s cross-region SSL reconnect, so the warm anonymous /pricing
 * path must run zero live plan/price queries.
 *
 * Two consumers share the builder (mirroring HomePageCache):
 *   - PricingPagesController renders from the cache (rebuilding lazily on
 *     a miss as a cold-boot fallback);
 *   - the scheduled `home:warm-caches` job (via HomePageCache::warm())
 *     overwrites the key proactively every few minutes with WARM_TTL, so
 *     admin plan/price edits land within one warm cadence and no visitor
 *     pays the rebuild.
 */
class PricingPageCache
{
    /**
     * Plan + coin-package catalogue (attribute arrays + prices relation).
     * Key PREFIX only — the stored key is suffixed with the monotonic
     * catalogue version (see {@see catalogKey()}).
     */
    public const CATALOG_CACHE_KEY = 'pricing:catalog:v1';

    /**
     * Monotonic plan-catalogue version, bumped by {@see flush()}. Both the
     * /pricing catalogue key and the homepage anonymous plan-teaser keys are
     * suffixed with it, which closes the flush-vs-warm race: the scheduled
     * warmer captures the version BEFORE it starts building, so if an admin
     * plan save lands mid-run the warmer's writes go to the now-retired
     * version's keys (harmless garbage that expires with its TTL) and every
     * reader — already on the bumped version — takes a clean miss and
     * rebuilds fresh instead of being re-shadowed by pre-edit data.
     */
    public const CATALOG_VERSION_KEY = 'pricing:catalog:ver';

    /**
     * Current catalogue version (>= 1). Falls back to 1 when the cache
     * layer is unavailable, matching the read paths' live-query fallbacks.
     */
    public static function version(): int
    {
        try {
            return max(1, (int) Cache::get(self::CATALOG_VERSION_KEY, 1));
        } catch (\Throwable $e) {
            return 1;
        }
    }

    /**
     * The versioned catalogue cache key. Pass an explicit version (as the
     * warmer does, captured before building) to pin writes to that version.
     */
    public static function catalogKey(?int $version = null): string
    {
        return self::CATALOG_CACHE_KEY . ':' . ($version ?? self::version());
    }

    /** TTL for lazily rebuilt cache on the request path (seconds). */
    public const TTL = 300;

    /**
     * Fetch the full catalogue from the DB as plain attribute arrays — the
     * cacheable representation. Rehydrate with {@see hydrateCatalog()}.
     *
     * @return array{plans:array<int,array{attrs:array,prices:array}>,packages:array<int,array{attrs:array,prices:array}>}
     */
    public static function buildCatalog(): array
    {
        $toRows = fn ($models) => $models
            ->map(fn ($p) => [
                'attrs'  => $p->getAttributes(),
                'prices' => $p->prices->map(fn ($pr) => $pr->getAttributes())->all(),
            ])->all();

        return [
            'plans'    => $toRows(Plan::active()->public()->with('prices')->ordered()->get()),
            'packages' => $toRows(CoinPackage::active()->with('prices')->ordered()->get()),
        ];
    }

    /**
     * Returns [plans Collection, packages Collection] with the `prices`
     * relation loaded, from cache when warm. Falls back to live queries if
     * the cache layer is unavailable.
     *
     * @return array{0:\Illuminate\Support\Collection,1:\Illuminate\Support\Collection}
     */
    public static function catalog(): array
    {
        try {
            $payload = Cache::remember(self::catalogKey(), self::TTL, fn () => self::buildCatalog());
        } catch (\Throwable $e) {
            $payload = self::buildCatalog();
        }

        return self::hydrateCatalog($payload);
    }

    /**
     * Rehydrate a cached catalogue payload into Eloquent collections with
     * the `prices` relation set.
     *
     * @param  array{plans?:array,packages?:array}  $payload
     * @return array{0:\Illuminate\Support\Collection,1:\Illuminate\Support\Collection}
     */
    public static function hydrateCatalog(array $payload): array
    {
        $hydrate = function (string $modelClass, array $rows) {
            return collect($rows)->map(function (array $row) use ($modelClass) {
                $model = $modelClass::query()->hydrate([$row['attrs']])->first();
                $model->setRelation('prices', Price::hydrate($row['prices'] ?? []));

                return $model;
            });
        };

        return [
            $hydrate(Plan::class, $payload['plans'] ?? []),
            $hydrate(CoinPackage::class, $payload['packages'] ?? []),
        ];
    }

    /**
     * Rebuild the catalogue from the database and overwrite the stored
     * copy. Called by the scheduled warmer; safe to call any time (it only
     * writes fresher data). Returns row counts for the warm-run summary.
     *
     * The warmer passes $version captured BEFORE any building started: if a
     * plan save flushes (and bumps the version) while this build is in
     * flight, the write below lands on the retired version's key and can
     * never re-shadow the edit for readers on the new version.
     *
     * @return array{plans:int,packages:int}
     */
    public static function warm(int $ttl, ?int $version = null): array
    {
        $version ??= self::version();
        $payload = self::buildCatalog();
        Cache::put(self::catalogKey($version), $payload, $ttl);

        return [
            'plans'    => count($payload['plans']),
            'packages' => count($payload['packages']),
        ];
    }

    /**
     * Drop the cached catalogue so the next /pricing request rebuilds it
     * from the database. Called from admin plan write paths (PlanWriter)
     * so plan edits (prices, features, coin grants) are visible to
     * visitors immediately instead of waiting for the TTL / warm cadence.
     *
     * Also drops the homepage's anonymous plan-teaser payloads: they embed
     * the same plan/price data, so any write that invalidates the pricing
     * catalogue must invalidate the home teaser too. Doing it here keeps
     * every existing flush call site (PlanWriter + PlanController's
     * archive/delete/import/revert paths) covering both surfaces.
     */
    public static function flush(): void
    {
        try {
            $current = self::version();

            // Drop the current-version keys so readers miss immediately...
            Cache::forget(self::catalogKey($current));

            // ...and bump the version so any warm run that started building
            // BEFORE this flush (with pre-edit data) writes to the retired
            // version's keys instead of resurrecting stale prices. Stored
            // forever: it's a tiny int and letting it expire could briefly
            // point readers back at an unexpired retired-version entry.
            Cache::forever(self::CATALOG_VERSION_KEY, $current + 1);
        } catch (\Throwable $e) {
            // Cache layer unavailable — reads already fall back to live queries.
        }

        HomePageCache::flushAnonPayloads();
    }
}
