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
            $cached = Cache::remember(self::cacheKey($key), 300, function () use ($key) {
                $row = self::where('key', $key)->first();
                if (!$row) return self::MISSING;
                $v = $row->value;
                return $v === null ? self::MISSING : $v;
            });

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
    }
}
