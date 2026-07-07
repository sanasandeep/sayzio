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
    /** Plan + coin-package catalogue (attribute arrays + prices relation). */
    public const CATALOG_CACHE_KEY = 'pricing:catalog:v1';

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
            $payload = Cache::remember(self::CATALOG_CACHE_KEY, self::TTL, fn () => self::buildCatalog());
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
     * @return array{plans:int,packages:int}
     */
    public static function warm(int $ttl): array
    {
        $payload = self::buildCatalog();
        Cache::put(self::CATALOG_CACHE_KEY, $payload, $ttl);

        return [
            'plans'    => count($payload['plans']),
            'packages' => count($payload['packages']),
        ];
    }
}
