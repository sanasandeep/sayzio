<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Models\BannedName;
use Illuminate\Support\Facades\Cache;

/**
 * Lookup helper for the admin-managed banned-names list. Used by every
 * profile-handle and link-alias validation path so the rules stay in
 * one place and behave identically.
 *
 * The single-name lookup is cached for 5 minutes — banned-name reads
 * are on the hot path (every handle + alias save) but writes are rare,
 * and the controller flushes the affected entries on create/update/
 * delete so cached false-positives are not a concern.
 */
class BannedNameChecker
{
    private const TTL_SECONDS = 300;

    private static function cacheKey(string $loweredName): string
    {
        return 'banned_name:' . $loweredName;
    }

    /**
     * True when $name is on the banned list (case-insensitive). Empty
     * strings always return false so this rule can sit alongside
     * `nullable` validators without rejecting blank input.
     */
    public static function isBanned(string $name): bool
    {
        $lower = mb_strtolower(trim($name));
        if ($lower === '') return false;

        return Cache::remember(self::cacheKey($lower), self::TTL_SECONDS, function () use ($lower) {
            return BannedName::whereRaw('LOWER(name) = ?', [$lower])->exists();
        });
    }

    /**
     * Forget the cached lookup for a specific name. Called from the
     * banned-names admin controller on every write.
     */
    public static function flush(?string $name = null): void
    {
        if ($name === null || trim($name) === '') return;
        Cache::forget(self::cacheKey(mb_strtolower(trim($name))));
    }
}
