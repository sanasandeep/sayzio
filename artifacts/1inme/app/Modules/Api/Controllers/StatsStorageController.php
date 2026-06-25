<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Support\StatsRetentionPolicy;
use App\Modules\Common\Support\StatsStorageHealth;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Bearer-token parity for the admin "Analytics Storage" panel so a platform
 * admin can watch analytics-history growth and bound it from the 1INME Mobile
 * app. Mobile counterpart of
 * {@see \App\Modules\Admin\Controllers\StatsStorageController}; both surfaces
 * read the same {@see StatsStorageHealth} / {@see StatsRetentionPolicy}, so the
 * screen shows exactly what the daily `stats:prune-history` sweep will do:
 *
 *   GET /api/v1/admin/stats-storage   (read-only health report)
 *   PUT /api/v1/admin/stats-storage   (set/clear hard cap + alert threshold)
 *
 * Gated behind the same `settings.manage` permission the web routes use; a
 * regular sanctum token is rejected with 403.
 */
class StatsStorageController extends Controller
{
    use ApiResponses;

    public function status(Request $request)
    {
        if (! $request->user()->hasPermission('settings.manage')) {
            return $this->forbidden('You are not allowed to view analytics storage.');
        }

        return $this->ok($this->payload(StatsStorageHealth::compute()));
    }

    public function update(Request $request)
    {
        if (! $request->user()->hasPermission('settings.manage')) {
            return $this->forbidden('You are not allowed to change analytics storage settings.');
        }

        $data = $request->validate([
            'hard_max_days'             => ['nullable', 'integer', 'min:1', 'max:36500'],
            'clear_hard_max_days'       => ['nullable', 'boolean'],
            'alert_row_threshold'       => ['nullable', 'integer', 'min:1'],
            'clear_alert_row_threshold' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('clear_hard_max_days')) {
            AppSetting::put('stats.hard_max_days', null);
        } elseif (($data['hard_max_days'] ?? null) !== null) {
            AppSetting::put('stats.hard_max_days', (int) $data['hard_max_days']);
        }

        if ($request->boolean('clear_alert_row_threshold')) {
            AppSetting::put('stats.alert_row_threshold', null);
        } elseif (($data['alert_row_threshold'] ?? null) !== null) {
            AppSetting::put('stats.alert_row_threshold', (int) $data['alert_row_threshold']);
        }

        StatsStorageHealth::flush();

        return $this->ok($this->payload(StatsStorageHealth::compute()));
    }

    /**
     * Shape a {@see StatsStorageHealth::compute()} report for the mobile screen:
     * scalar retention fields, a flat `tables` array (objects, not a map), the
     * default-threshold hint, and the normalized last-sweep summary.
     *
     * @param array<string,mixed> $health
     */
    private function payload(array $health): array
    {
        $tables = [];
        foreach (($health['tables'] ?? []) as $name => $t) {
            $tables[] = [
                'table'          => $name,
                'estimated_rows' => (int) ($t['estimated_rows'] ?? 0),
                'over_threshold' => (bool) ($t['over_threshold'] ?? false),
            ];
        }

        return [
            'available'         => (bool) ($health['available'] ?? false),
            'plan_retention'    => $health['plan_retention'] ?? -1,
            'hard_max_days'     => $health['hard_max_days'] ?? null,
            'alert_threshold'   => (int) ($health['alert_threshold'] ?? 0),
            'default_threshold' => StatsRetentionPolicy::DEFAULT_ALERT_THRESHOLD,
            'effective_days'    => $health['effective_days'] ?? null,
            'reason'            => (string) ($health['reason'] ?? ''),
            'growth_unbounded'  => (bool) ($health['growth_unbounded'] ?? false),
            'tables'            => $tables,
            'last_run'          => $this->lastRunPayload($health['last_run'] ?? null),
        ];
    }

    /**
     * Normalize the recorded last-sweep report (`stats.prune.last_run`) into the
     * subset the mobile screen renders, or null when no sweep has run.
     *
     * @param mixed $lastRun
     */
    private function lastRunPayload($lastRun): ?array
    {
        if (! is_array($lastRun)) {
            return null;
        }

        return [
            'ran_at'         => $lastRun['ran_at'] ?? null,
            'action'         => $lastRun['action'] ?? null,
            'reason'         => $lastRun['reason'] ?? null,
            'dry_run'        => (bool) ($lastRun['dry_run'] ?? false),
            'effective_days' => $lastRun['effective_days'] ?? null,
            'tables'         => is_array($lastRun['tables'] ?? null) ? $lastRun['tables'] : [],
        ];
    }
}
