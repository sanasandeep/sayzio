<?php

namespace App\Modules\User\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Single source of truth for resolving a visitor-analytics date window from
 * request query params. Shared by the per-link Visitor Insights page and the
 * account-wide Visitors page so both honor the same preset pills, the same
 * custom start/end range, and the same plan stats-retention clamp.
 */
class AnalyticsRangeResolver
{
    /** Preset pills shown on both visitor analytics pages. */
    public const PRESETS = [
        'today' => 'Today',
        '7d'    => '7d',
        '30d'   => '30d',
        '90d'   => '90d',
        'year'  => 'Year',
        'all'   => 'All',
    ];

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string} [$start, $end, $period]
     */
    public static function resolve(Request $request, int $retentionDays): array
    {
        $period = $request->query('period', '30d');
        $now = now();

        if ($period === 'custom') {
            $start = self::parseDate($request->query('start')) ?? $now->copy()->subDays(30)->startOfDay();
            $end   = self::parseDate($request->query('end'), true) ?? $now->copy()->endOfDay();

            // Guard against an inverted or future-dated custom range.
            if ($end->lt($start)) {
                $end = $start->copy()->endOfDay();
            }
            if ($end->gt($now->copy()->endOfDay())) {
                $end = $now->copy()->endOfDay();
            }
        } else {
            $end = $now->copy()->endOfDay();
            $start = match ($period) {
                'today' => $now->copy()->startOfDay(),
                '7d'    => $now->copy()->subDays(7)->startOfDay(),
                '90d'   => $now->copy()->subDays(90)->startOfDay(),
                'year'  => $now->copy()->subYear()->startOfDay(),
                'all'   => $now->copy()->subYears(10)->startOfDay(),
                default => $now->copy()->subDays(30)->startOfDay(),
            };
        }

        // Clamp the start of the range to the plan's stats-history retention
        // (applies to presets AND custom ranges alike) so users can't query
        // analytics older than their plan allows.
        if ($retentionDays !== -1) {
            $earliest = $now->copy()->subDays($retentionDays)->startOfDay();
            if ($start->lt($earliest)) {
                $start = $earliest;
            }
        }

        // Final safety net: retention/future clamps above are applied
        // independently and could otherwise leave an inverted window (e.g. a
        // very short retention plan clamping $start past $end). Never return
        // a window where the end precedes the start.
        if ($end->lt($start)) {
            $end = $start->copy()->endOfDay();
        }

        return [$start, $end, $period];
    }

    private static function parseDate(?string $value, bool $endOfDay = false): ?Carbon
    {
        if (!$value) {
            return null;
        }
        try {
            $date = Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
        return $endOfDay ? $date->endOfDay() : $date->startOfDay();
    }
}
