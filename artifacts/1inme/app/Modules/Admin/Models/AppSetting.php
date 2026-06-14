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
     * Read a setting by key. Cached for 5 minutes to keep the hot path (the
     * per-link coach render) from hitting the DB on every request.
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
            return Cache::remember(self::cacheKey($key), 300, function () use ($key, $default) {
                $row = self::where('key', $key)->first();
                if (!$row) return $default;
                $v = $row->value;
                return $v === null ? $default : $v;
            });
        } catch (QueryException $e) {
            if (DatabaseErrors::isMissingTable($e)) {
                return $default;
            }
            throw $e;
        }
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
