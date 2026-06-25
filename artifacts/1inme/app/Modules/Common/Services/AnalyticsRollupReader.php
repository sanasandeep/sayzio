<?php

namespace App\Modules\Common\Services;

use App\Modules\Admin\Models\AppSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-side helper that serves click analytics over a window WITHOUT scanning
 * the full raw link_clicks table on every request.
 *
 * Strategy: days strictly on/ before the rollup watermark are read from the
 * pre-aggregated link_click_daily(_dimensions) tables; the (small) tail of days
 * after the watermark — including "today", which still receives late deferred
 * clicks — is read raw and summed on top. Because the rollups are EXACT
 * human-only aggregates produced with the same is_bot/is_throttled filter the
 * raw read uses, rollup(finalised) + raw(tail) equals a full raw aggregate, so
 * dashboard numbers never drift.
 *
 * Falls back to a pure raw read (current behaviour, just slower) when the rollup
 * tables or watermark are unavailable.
 */
class AnalyticsRollupReader
{
    public const WATERMARK_KEY = 'analytics.rollup.last_date';

    /** Map a logical dimension to its raw link_clicks column. */
    private const DIMENSION_COLUMN = [
        'country'  => 'country_code',
        'device'   => 'device_type',
        'source'   => 'source',
        'channel'  => 'channel',
        'referrer' => 'referrer',
    ];

    /**
     * Per-day human click counts: [['day' => 'YYYY-MM-DD', 'clicks' => int], ...]
     */
    public function byDay(int $linkId, Carbon $from, Carbon $to): array
    {
        [$rollupTo, $rawFrom] = $this->split($from, $to);

        $byDay = [];

        if ($rollupTo !== null && Schema::hasTable('link_click_daily')) {
            $rows = DB::table('link_click_daily')
                ->where('link_id', $linkId)
                ->whereBetween('click_date', [$from->toDateString(), $rollupTo->toDateString()])
                ->orderBy('click_date')
                ->get(['click_date', 'total_clicks']);
            foreach ($rows as $r) {
                $day = Carbon::parse($r->click_date)->toDateString();
                $byDay[$day] = ($byDay[$day] ?? 0) + (int) $r->total_clicks;
            }
        }

        if ($rawFrom !== null) {
            $rows = $this->humanRaw($linkId, $rawFrom, $to)
                ->selectRaw("to_char(clicked_at, 'YYYY-MM-DD') as day, count(*) as clicks")
                ->groupBy('day')
                ->get();
            foreach ($rows as $r) {
                $byDay[$r->day] = ($byDay[$r->day] ?? 0) + (int) $r->clicks;
            }
        }

        ksort($byDay);
        $out = [];
        foreach ($byDay as $day => $clicks) {
            $out[] = ['day' => $day, 'clicks' => $clicks];
        }
        return $out;
    }

    /**
     * Per-dimension breakdown: [['value' => string|null, 'clicks' => int], ...]
     * sorted by clicks desc and limited.
     */
    public function byDimension(int $linkId, Carbon $from, Carbon $to, string $dimension, int $limit = 50): array
    {
        $column = self::DIMENSION_COLUMN[$dimension] ?? null;
        if ($column === null) {
            return [];
        }

        [$rollupTo, $rawFrom] = $this->split($from, $to);

        $totals = [];

        if ($rollupTo !== null && Schema::hasTable('link_click_daily_dimensions')) {
            $rows = DB::table('link_click_daily_dimensions')
                ->where('link_id', $linkId)
                ->where('dimension', $dimension)
                ->whereBetween('click_date', [$from->toDateString(), $rollupTo->toDateString()])
                ->groupBy('dim_value')
                ->get(['dim_value', DB::raw('SUM(clicks) as clicks')]);
            foreach ($rows as $r) {
                $key = (string) ($r->dim_value ?? '');
                $totals[$key] = ($totals[$key] ?? 0) + (int) $r->clicks;
            }
        }

        if ($rawFrom !== null) {
            $rows = $this->humanRaw($linkId, $rawFrom, $to)
                ->selectRaw("$column as value, count(*) as clicks")
                ->groupBy($column)
                ->get();
            foreach ($rows as $r) {
                $value = $dimension === 'referrer' ? $this->referrerHost($r->value) : (string) ($r->value ?? '');
                $totals[$value] = ($totals[$value] ?? 0) + (int) $r->clicks;
            }
        }

        arsort($totals);
        $out = [];
        foreach (array_slice($totals, 0, $limit, true) as $value => $clicks) {
            $out[] = ['value' => $value === '' ? null : $value, 'clicks' => $clicks];
        }
        return $out;
    }

    /**
     * Decide the rollup/raw cut. Returns [rollupTo|null, rawFrom|null]:
     *  - rollupTo : last date (inclusive) to read from rollups, capped at $to.
     *  - rawFrom  : first datetime to read raw, capped at $from.
     * Either side may be null when the window doesn't reach it.
     */
    private function split(Carbon $from, Carbon $to): array
    {
        $watermark = $this->watermark();

        // No usable rollup state ⇒ read everything raw (safe, just slower).
        if ($watermark === null || !Schema::hasTable('link_click_daily')) {
            return [null, $from->copy()];
        }

        // Rollups cover whole days through (and including) the watermark date.
        $rollupTo = $watermark->lt($to) ? $watermark->copy() : $to->copy();
        // Nothing finalised within the window.
        if ($rollupTo->lt($from)) {
            $rollupTo = null;
        }

        // Raw picks up everything strictly after the watermark day.
        $rawStart = $watermark->copy()->addDay()->startOfDay();
        if ($rawStart->lt($from)) {
            $rawStart = $from->copy();
        }
        $rawFrom = $rawStart->lte($to) ? $rawStart : null;

        return [$rollupTo, $rawFrom];
    }

    private function watermark(): ?Carbon
    {
        $raw = AppSetting::get(self::WATERMARK_KEY);
        if (!$raw) {
            return null;
        }
        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Base human-only raw query (mirrors the LinkClick global scope: exclude
     * bots and, when the column exists, throttled rows).
     */
    private function humanRaw(int $linkId, Carbon $from, Carbon $to)
    {
        $q = DB::table('link_clicks')
            ->where('link_id', $linkId)
            ->whereBetween('clicked_at', [$from, $to])
            ->where('is_bot', false);
        if (Schema::hasColumn('link_clicks', 'is_throttled')) {
            $q->where('is_throttled', false);
        }
        return $q;
    }

    private function referrerHost(?string $referrer): string
    {
        if (!$referrer) {
            return '';
        }
        $host = parse_url($referrer, PHP_URL_HOST);
        return is_string($host) ? $host : '';
    }
}
