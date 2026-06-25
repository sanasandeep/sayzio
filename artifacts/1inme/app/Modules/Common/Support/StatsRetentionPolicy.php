<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Plan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Single source of truth for the analytics-history retention policy.
 *
 * The {@see \App\Console\Commands\PruneStatsHistory} daily sweep and the
 * admin-facing read surfaces ({@see StatsStorageHealth}) both resolve the
 * effective prune window through the same rules here, so the dashboard can show
 * exactly what the next sweep will do.
 *
 * Precedence (see docs/scaling-tracking.md):
 *   1. Plan retention — the GLOBAL maximum `stats_retention_days` across active
 *      plans (the tables aren't partitioned by plan, so we must never delete
 *      data the most generous plan can still display). Any active plan that
 *      keeps history forever (-1) or has no retention configured makes plan
 *      pruning a safe no-op.
 *   2. Hard physical cap — operator AppSetting `stats.hard_max_days`. When set
 *      it bounds storage even under unlimited plan retention.
 */
class StatsRetentionPolicy
{
    /** Tables pruned by retention, keyed by their "created" timestamp column. */
    public const TABLES = [
        'link_clicks'   => 'clicked_at',
        'page_sessions' => 'started_at',
    ];

    /** Default estimated-row threshold above which we raise a growth alert. */
    public const DEFAULT_ALERT_THRESHOLD = 50_000_000;

    /**
     * Largest stats_retention_days across active plans. Returns -1 ("unlimited")
     * when any active plan is explicitly unlimited OR has no retention configured
     * yet (deliberate: never delete data on a plan whose retention isn't seeded).
     */
    public static function largestRetentionDays(): int
    {
        $plans = Plan::where('status', 'active')->get(['features']);
        if ($plans->isEmpty()) {
            return -1;
        }

        $max = 30;
        foreach ($plans as $plan) {
            $raw = $plan->features['stats_retention_days'] ?? null;
            if ($raw === null) {
                return -1;
            }
            $days = (int) $raw;
            if ($days === -1) {
                return -1;
            }
            if ($days < 30) {
                $days = 30;
            }
            if ($days > $max) {
                $max = $days;
            }
        }
        return $max;
    }

    /**
     * Resolve the effective prune window (days) and a human reason.
     * Returns [null, reason] when nothing should be pruned.
     *
     * @return array{0:?int,1:string}
     */
    public static function effectiveRetention(int $planRetention, ?int $hardMax): array
    {
        if ($planRetention === -1) {
            if ($hardMax !== null && $hardMax > 0) {
                return [$hardMax, 'unlimited plan retention bounded by hard cap'];
            }
            return [null, 'a plan retains stats forever and no hard cap is set'];
        }

        if ($hardMax !== null && $hardMax > 0) {
            return [min($planRetention, $hardMax), 'min(plan retention, hard cap)'];
        }

        return [$planRetention, 'plan retention'];
    }

    /** Operator hard physical cap (days), or null when unset/invalid. */
    public static function hardMaxDays(): ?int
    {
        $configured = AppSetting::get('stats.hard_max_days');
        if ($configured === null || $configured === '') {
            return null;
        }
        $days = (int) $configured;
        return $days > 0 ? $days : null;
    }

    /** Estimated-row growth-alert threshold (falls back to the default). */
    public static function alertThreshold(): int
    {
        $v = AppSetting::get('stats.alert_row_threshold');
        if ($v === null || $v === '') {
            return self::DEFAULT_ALERT_THRESHOLD;
        }
        $n = (int) $v;
        return $n > 0 ? $n : self::DEFAULT_ALERT_THRESHOLD;
    }

    /** Fast row estimate from planner statistics (avoids count(*) on huge tables). */
    public static function estimateRows(string $table): int
    {
        try {
            $row = DB::selectOne(
                'SELECT reltuples::bigint AS n FROM pg_class WHERE relname = ? LIMIT 1',
                [$table]
            );
            $n = $row ? (int) $row->n : 0;
            return $n < 0 ? 0 : $n;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Whether a tracked table exists (degrades to false on un-migrated DBs). */
    public static function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
