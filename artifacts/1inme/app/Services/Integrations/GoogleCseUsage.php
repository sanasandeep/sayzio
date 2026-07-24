<?php

namespace App\Services\Integrations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Daily usage counters for Google Custom Search image queries made by the
 * AI biolink builder. Backed by `google_cse_usage_counters` (one row per
 * day+user; user_id = 0 is the platform-wide daily total).
 *
 * Powers the admin Integrations readout (today + recent days against the
 * 100/day free tier) and the optional per-user daily cap. Every method is
 * failure-tolerant: recording must never sink an image search, and the
 * admin page must render even before the migration has run.
 */
class GoogleCseUsage
{
    /** Google's free tier for the Custom Search JSON API. */
    public const FREE_TIER_DAILY = 100;

    private const TABLE = 'google_cse_usage_counters';

    /** Record one query against today's platform total and the user's row. */
    public static function record(?int $userId): void
    {
        $day = now()->toDateString();
        $ids = array_unique([0, max(0, (int) $userId)]);

        foreach ($ids as $id) {
            try {
                DB::table(self::TABLE)->upsert(
                    [['day' => $day, 'user_id' => $id, 'queries' => 1, 'created_at' => now(), 'updated_at' => now()]],
                    ['day', 'user_id'],
                    ['queries' => DB::raw('"' . self::TABLE . '"."queries" + 1'), 'updated_at' => now()],
                );
            } catch (\Throwable $e) {
                Log::info('Google CSE usage record failed: ' . $e->getMessage());
            }
        }
    }

    /** Platform-wide query count for today. */
    public static function todayTotal(): int
    {
        return self::count(now()->toDateString(), 0);
    }

    /** A specific user's query count for today. */
    public static function todayForUser(int $userId): int
    {
        return $userId > 0 ? self::count(now()->toDateString(), $userId) : 0;
    }

    /**
     * True when the admin-set per-user daily cap exists and the user has
     * hit it. Cap 0/unset = unlimited.
     */
    public static function capReached(?int $userId): bool
    {
        $cap = PlatformServiceSettings::googleCseUserDailyCap();

        return $cap > 0 && $userId !== null && $userId > 0 && self::todayForUser($userId) >= $cap;
    }

    /**
     * Platform totals for the last N days (today first).
     *
     * @return list<array{day:string,queries:int}>
     */
    public static function recentDaily(int $days = 7): array
    {
        try {
            if (!Schema::hasTable(self::TABLE)) {
                return [];
            }

            $since = now()->subDays(max(0, $days - 1))->toDateString();
            $rows = DB::table(self::TABLE)
                ->where('user_id', 0)
                ->where('day', '>=', $since)
                ->orderByDesc('day')
                ->limit($days)
                ->get(['day', 'queries']);

            return $rows->map(fn ($r) => [
                'day'     => (string) $r->day,
                'queries' => (int) $r->queries,
            ])->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Today's heaviest users (excludes the platform-total row).
     *
     * @return list<array{user_id:int,queries:int}>
     */
    public static function topUsersToday(int $limit = 5): array
    {
        try {
            if (!Schema::hasTable(self::TABLE)) {
                return [];
            }

            $rows = DB::table(self::TABLE)
                ->where('day', now()->toDateString())
                ->where('user_id', '>', 0)
                ->orderByDesc('queries')
                ->limit($limit)
                ->get(['user_id', 'queries']);

            return $rows->map(fn ($r) => [
                'user_id' => (int) $r->user_id,
                'queries' => (int) $r->queries,
            ])->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function count(string $day, int $userId): int
    {
        try {
            if (!Schema::hasTable(self::TABLE)) {
                return 0;
            }

            return (int) DB::table(self::TABLE)
                ->where('day', $day)
                ->where('user_id', $userId)
                ->value('queries');
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
