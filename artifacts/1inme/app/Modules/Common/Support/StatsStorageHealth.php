<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only view of analytics-history storage health for the admin surfaces
 * (web dashboard panel + mobile parity). Surfaces:
 *   - live per-table estimated row counts (planner stats, never count(*)),
 *   - the effective retention window the next sweep will apply, with the reason,
 *   - the operator hard cap (`stats.hard_max_days`) and growth-alert threshold
 *     (`stats.alert_row_threshold`),
 *   - the last sweep outcome recorded by the prune command
 *     (`stats.prune.last_run`),
 *   - a `growth_unbounded` flag — a table is over the alert threshold AND
 *     nothing will prune it — so the dashboard can warn proactively.
 *
 * Everything is computed through {@see StatsRetentionPolicy} so the panel shows
 * exactly what the daily `stats:prune-history` sweep will do.
 */
class StatsStorageHealth
{
    private const CACHE_KEY = 'stats_storage_health';
    private const TTL = 120;

    /** Cached snapshot for the dashboard banner (cheap planner-stat reads). */
    public static function cached(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, self::TTL, fn () => self::compute());
        } catch (\Throwable $e) {
            return self::compute();
        }
    }

    public static function flush(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable $e) {
            // best-effort; a cache miss just recomputes
        }
    }

    /**
     * @return array{
     *   available:bool,
     *   plan_retention:int,
     *   hard_max_days:?int,
     *   alert_threshold:int,
     *   effective_days:?int,
     *   reason:string,
     *   growth_unbounded:bool,
     *   tables:array<string,array{estimated_rows:int,over_threshold:bool}>,
     *   last_run:mixed
     * }
     */
    public static function compute(): array
    {
        $hardMax       = StatsRetentionPolicy::hardMaxDays();
        $threshold     = StatsRetentionPolicy::alertThreshold();
        $planRetention = StatsRetentionPolicy::largestRetentionDays();

        [$effectiveDays, $reason] = StatsRetentionPolicy::effectiveRetention($planRetention, $hardMax);

        $tables          = [];
        $growthUnbounded = false;

        foreach (StatsRetentionPolicy::TABLES as $table => $column) {
            if (! StatsRetentionPolicy::tableExists($table)) {
                continue;
            }
            $rows          = StatsRetentionPolicy::estimateRows($table);
            $overThreshold = $rows >= $threshold;
            $tables[$table] = [
                'estimated_rows' => $rows,
                'over_threshold' => $overThreshold,
            ];
            // Unbounded growth = large AND nothing will trim it.
            if ($overThreshold && $effectiveDays === null) {
                $growthUnbounded = true;
            }
        }

        return [
            'available'        => ! empty($tables),
            'plan_retention'   => $planRetention,
            'hard_max_days'    => $hardMax,
            'alert_threshold'  => $threshold,
            'effective_days'   => $effectiveDays,
            'reason'           => $reason,
            'growth_unbounded' => $growthUnbounded,
            'tables'           => $tables,
            'last_run'         => AppSetting::get('stats.prune.last_run'),
        ];
    }
}
