<?php

namespace App\Modules\Admin\Models;

use App\Modules\Common\Support\DatabaseErrors;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

/**
 * Generic key/value store for workspace-wide admin settings.
 *
 * Values are serialized to JSONB so both scalars and structured arrays (like
 * the Performance Coach defaults) can be stored without schema changes.
 */
class AppSetting extends Model
{
    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    private static function cacheKey(string $key): string
    {
        return 'app_setting:' . $key;
    }

    /**
     * Sentinel cached in place of a missing/null setting value.
     *
     * `Cache::remember()` treats a cached `null` as a cache MISS (it can't tell
     * "stored null" from "absent"), so caching the raw default for an unset key
     * re-runs the DB query on EVERY request. With ~15 mostly-unset integration
     * settings read at boot (MailSettings/PlatformServiceSettings), that meant
     * ~15 queries per request — multiplied by cross-region RDS latency this made
     * even a trivial endpoint take ~16s. Caching a non-null sentinel instead
     * lets the "absent" result actually stick.
     */
    private const MISSING = '__app_setting_missing__';

    /**
     * Whether this process has already bulk-primed the per-key caches from a
     * single `SELECT * FROM app_settings` (see get()). Once primed, the full
     * key set below is authoritative for "row exists?" checks, so unset keys
     * never trigger a per-key query in the same process.
     */
    private static bool $bulkPrimed = false;

    /**
     * monotonic-ish timestamp of the last successful bulk prime. The primed
     * key set is only authoritative for the same 300s window as the per-key
     * cache TTL — after that, the next per-key miss re-runs the bulk prime,
     * so keys created by ANOTHER process become visible within one TTL.
     */
    private static float $bulkPrimedAt = 0.0;

    /**
     * key => true for every row seen by the in-process bulk prime. Only
     * meaningful when $bulkPrimed is true.
     *
     * @var array<string,true>
     */
    private static array $bulkKeys = [];

    /**
     * Read a setting by key. Cached for 5 minutes to keep the hot path (boot-time
     * runtime-config overrides + the per-link coach render) from hitting the DB
     * on every request. Missing/null values are cached via a sentinel (see
     * self::MISSING) so unset keys don't re-query the DB on every request.
     *
     * The caller-supplied $default is applied at read time (not cached), so the
     * same key can be read with different defaults by different callers.
     *
     * If the underlying `app_settings` table does not exist yet (un-migrated
     * environment), we degrade gracefully to the caller-supplied default
     * instead of letting the QueryException bubble up and 500 every route.
     * The fallback is intentionally NOT cached, so the very first request
     * after the table is migrated reads real values again. Any other database
     * error is re-thrown — only "table does not exist" is swallowed.
     */
    public static function get(string $key, $default = null)
    {
        try {
            $cached = Cache::get(self::cacheKey($key));

            // First per-key MISS of this process: bulk-prime EVERY setting
            // from one `SELECT *` instead of paying one cross-region
            // round-trip per key. A cold home render reads ~26 settings; at
            // ~750ms/query against the production RDS the per-key misses
            // alone were ~20s of the cold TTFB. One bulk query (~0.8s)
            // replaces all of them. Warm requests never reach this branch.
            $primeFresh = self::$bulkPrimed
                && (microtime(true) - self::$bulkPrimedAt) < 300;

            if ($cached === null && !$primeFresh) {
                $keys = [];
                foreach (self::all() as $row) {
                    $v = $row->value;
                    Cache::put(self::cacheKey($row->key), $v === null ? self::MISSING : $v, 300);
                    $keys[$row->key] = true;
                }
                // Only mark the process as primed AFTER the full load
                // succeeded — if the query above throws (transient DB error),
                // the next get() retries the bulk prime instead of serving
                // MISSING for every key from a half-initialized state.
                self::$bulkKeys = $keys;
                self::$bulkPrimed = true;
                self::$bulkPrimedAt = microtime(true);
                $primeFresh = true;
                $cached = Cache::get(self::cacheKey($key));
            }

            // Still a miss after (or during the TTL following) a bulk prime:
            // if this process bulk-primed, the full key set is authoritative —
            // an unknown key has no row, so cache the sentinel WITHOUT another
            // per-key query. Otherwise fall back to the classic per-key read.
            if ($cached === null) {
                if ($primeFresh && !isset(self::$bulkKeys[$key])) {
                    $cached = self::MISSING;
                    Cache::put(self::cacheKey($key), $cached, 300);
                } else {
                    $cached = Cache::remember(self::cacheKey($key), 300, function () use ($key) {
                        $row = self::where('key', $key)->first();
                        if (!$row) return self::MISSING;
                        $v = $row->value;
                        return $v === null ? self::MISSING : $v;
                    });
                }
            }

            return $cached === self::MISSING ? $default : $cached;
        } catch (QueryException $e) {
            if (DatabaseErrors::isMissingTable($e)) {
                return $default;
            }
            throw $e;
        }
    }

    /**
     * Proactively warm the per-key cache: one bulk query loads every stored
     * row, and any additional $expectedKeys with no row get the MISSING
     * sentinel cached — so the first request after a deploy/restart doesn't
     * pay one ~750ms cross-region query per unset setting key. Invoked by
     * MarketingPageCache::warm(). Uses the same cache keys and value shapes
     * as get(), so the request path can never drift.
     *
     * Returns the number of keys warmed.
     */
    public static function warmAll(array $expectedKeys = [], int $ttl = 300): int
    {
        $warmed = 0;
        $seen = [];

        foreach (self::all() as $row) {
            $v = $row->value;
            Cache::put(self::cacheKey($row->key), $v === null ? self::MISSING : $v, $ttl);
            $seen[$row->key] = true;
            $warmed++;
        }

        foreach ($expectedKeys as $key) {
            if (!isset($seen[$key])) {
                Cache::put(self::cacheKey($key), self::MISSING, $ttl);
                $warmed++;
            }
        }

        return $warmed;
    }

    /**
     * Persist a setting and invalidate its cache entry.
     */
    public static function put(string $key, $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::cacheKey($key));

        // Keep the in-process bulk-prime key set coherent with writes: the
        // row now exists, so a later get() in this same process must fall
        // through to a real per-key read instead of short-circuiting to the
        // MISSING sentinel because the key was absent at prime time.
        if (self::$bulkPrimed) {
            self::$bulkKeys[$key] = true;
        }
    }
}
