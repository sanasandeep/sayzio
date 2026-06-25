<?php

namespace App\Console\Commands;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Services\AnalyticsRollupReader;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Finalise whole past days of link_clicks into the pre-aggregated rollup tables
 * so long-window dashboards never scan the raw 100M-row table.
 *
 * - Rolls up every day from the last watermark through yesterday (today is never
 *   finalised — it still receives late deferred clicks).
 * - Re-rolls a small trailing lookback window (default 2 days) so clicks that
 *   landed late via the async job after a day was first rolled are corrected.
 * - Each (link, day) is recomputed from scratch and upserted, so the command is
 *   fully idempotent and safe to re-run.
 *
 * The watermark (AppSetting analytics.rollup.last_date) records the last fully
 * finalised day; AnalyticsRollupReader uses it to decide rollup-vs-raw.
 */
class RollupDailyClicks extends Command
{
    protected $signature = 'analytics:rollup-daily
        {--days= : How many trailing days to (re)roll, overriding the watermark}
        {--lookback=2 : Re-roll this many days behind the watermark for late clicks}';

    protected $description = 'Aggregate link_clicks into daily rollup tables (link_click_daily).';

    private const DIMENSIONS = [
        'channel'  => 'channel',
        'device'   => 'device_type',
        'country'  => 'country_code',
        'source'   => 'source',
        'referrer' => 'referrer',
    ];

    public function handle(): int
    {
        if (!Schema::hasTable('link_clicks') || !Schema::hasTable('link_click_daily')) {
            $this->warn('Rollup tables or link_clicks not present — skipping.');
            return self::SUCCESS;
        }

        $hasThrottled = Schema::hasColumn('link_clicks', 'is_throttled');
        $yesterday    = now()->subDay()->startOfDay();

        $start = $this->resolveStart($yesterday);
        if ($start->gt($yesterday)) {
            $this->info('Nothing to roll up — already current.');
            return self::SUCCESS;
        }

        $this->info("Rolling up {$start->toDateString()} .. {$yesterday->toDateString()}");

        for ($day = $start->copy(); $day->lte($yesterday); $day->addDay()) {
            $this->rollupDay($day->copy(), $hasThrottled);
        }

        // Advance the watermark to the last finalised day (yesterday).
        AppSetting::put(AnalyticsRollupReader::WATERMARK_KEY, $yesterday->toDateString());
        $this->info("Watermark set to {$yesterday->toDateString()}.");

        return self::SUCCESS;
    }

    private function resolveStart(Carbon $yesterday): Carbon
    {
        if ($days = $this->option('days')) {
            return now()->subDays(max(1, (int) $days) - 1)->startOfDay()->min($yesterday);
        }

        $lookback  = max(0, (int) $this->option('lookback'));
        $watermark = AppSetting::get(AnalyticsRollupReader::WATERMARK_KEY);

        if (!$watermark) {
            // First run: bound the initial backfill to a sane window; an
            // operator can widen it with --days for a full historical backfill.
            return now()->subDays(30)->startOfDay();
        }

        // Resume the day after the watermark, minus the late-click lookback.
        return Carbon::parse($watermark)->addDay()->subDays($lookback)->startOfDay();
    }

    private function rollupDay(Carbon $day, bool $hasThrottled): void
    {
        $dayStart = $day->copy()->startOfDay();
        $dayEnd   = $day->copy()->endOfDay();
        $dateStr  = $day->toDateString();
        $now      = now();

        // ---- Per-link totals (human + unique + bot) -------------------------
        $human = DB::table('link_clicks')
            ->whereBetween('clicked_at', [$dayStart, $dayEnd])
            ->where('is_bot', false);
        if ($hasThrottled) {
            $human->where('is_throttled', false);
        }
        $totals = (clone $human)
            ->selectRaw('link_id, count(*) as total_clicks, count(distinct ip_address) as unique_visitors')
            ->groupBy('link_id')
            ->get()
            ->keyBy('link_id');

        $botQuery = DB::table('link_clicks')
            ->whereBetween('clicked_at', [$dayStart, $dayEnd]);
        if ($hasThrottled) {
            $botQuery->where(function ($w) {
                $w->where('is_bot', true)->orWhere('is_throttled', true);
            });
        } else {
            $botQuery->where('is_bot', true);
        }
        $bots = $botQuery->selectRaw('link_id, count(*) as bot_clicks')
            ->groupBy('link_id')->get()->keyBy('link_id');

        $linkIds = $totals->keys()->merge($bots->keys())->unique();

        foreach ($linkIds->chunk(500) as $chunk) {
            $upserts = [];
            foreach ($chunk as $linkId) {
                $upserts[] = [
                    'link_id'         => $linkId,
                    'click_date'      => $dateStr,
                    'total_clicks'    => (int) ($totals[$linkId]->total_clicks ?? 0),
                    'unique_visitors' => (int) ($totals[$linkId]->unique_visitors ?? 0),
                    'bot_clicks'      => (int) ($bots[$linkId]->bot_clicks ?? 0),
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }
            if ($upserts) {
                DB::table('link_click_daily')->upsert(
                    $upserts,
                    ['link_id', 'click_date'],
                    ['total_clicks', 'unique_visitors', 'bot_clicks', 'updated_at']
                );
            }
        }

        // ---- Per-dimension breakdowns (human-only) --------------------------
        if (!Schema::hasTable('link_click_daily_dimensions')) {
            return;
        }

        foreach (self::DIMENSIONS as $dimension => $column) {
            $rows = (clone $human)
                ->selectRaw("link_id, $column as dim_value, count(*) as clicks")
                ->groupBy('link_id', $column)
                ->get();

            // Referrer is stored as a full URL on the raw row; reduce to host
            // and re-aggregate so the rollup matches the reader's raw-tail.
            $folded = [];
            foreach ($rows as $r) {
                $value = $dimension === 'referrer' ? $this->referrerHost($r->dim_value) : $r->dim_value;
                $value = $value === null || $value === '' ? null : mb_substr((string) $value, 0, 191);
                $key = $r->link_id . '|' . ($value ?? '');
                if (!isset($folded[$key])) {
                    $folded[$key] = ['link_id' => $r->link_id, 'dim_value' => $value, 'clicks' => 0];
                }
                $folded[$key]['clicks'] += (int) $r->clicks;
            }

            // Replace the day's rows for this dimension so a re-roll is exact.
            DB::table('link_click_daily_dimensions')
                ->where('click_date', $dateStr)
                ->where('dimension', $dimension)
                ->whereIn('link_id', $folded ? array_values(array_unique(array_map(fn ($f) => $f['link_id'], $folded))) : [0])
                ->delete();

            foreach (array_chunk(array_values($folded), 500) as $batch) {
                $insert = array_map(fn ($f) => [
                    'link_id'    => $f['link_id'],
                    'click_date' => $dateStr,
                    'dimension'  => $dimension,
                    'dim_value'  => $f['dim_value'],
                    'clicks'     => $f['clicks'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $batch);
                if ($insert) {
                    DB::table('link_click_daily_dimensions')->insert($insert);
                }
            }
        }
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
