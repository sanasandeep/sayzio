<?php

namespace App\Console\Commands;

use App\Modules\Common\Services\AnalyticsRollupReader;
use App\Modules\Common\Support\PartitionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Load / latency harness for the scaled tracking pipeline.
 *
 * Seeds a controlled batch of synthetic link_clicks (optionally via the
 * counter_deltas + rollup path) and measures the latency of the operations that
 * matter at 1M-user / 100M-row scale:
 *   - buffered insert throughput (rows/sec)
 *   - counter flush fold latency
 *   - daily rollup latency
 *   - dashboard read latency through AnalyticsRollupReader
 *
 * Synthetic rows are tagged (utm_source='verify-scale') so --cleanup can remove
 * exactly what it created without touching real history. Intended for staging /
 * load boxes, never as a scheduled job.
 */
class VerifyTrackingScale extends Command
{
    protected $signature = 'tracking:verify-scale
        {--link= : Existing link id to attribute clicks to (required unless --dry-run)}
        {--rows=10000 : Synthetic clicks to seed}
        {--batch=1000 : Insert batch size}
        {--days=14 : Spread clicks across this many past days}
        {--cleanup : Delete previously seeded synthetic rows and exit}
        {--dry-run : Print the plan without writing}';

    protected $description = 'Seed synthetic clicks and measure write/flush/rollup/read latency for the tracking pipeline.';

    private const TAG = 'verify-scale';

    public function handle(PartitionManager $partitions, AnalyticsRollupReader $reader): int
    {
        if (!Schema::hasTable('link_clicks')) {
            $this->error('link_clicks table is missing — run migrations first.');
            return self::FAILURE;
        }

        if ($this->option('cleanup')) {
            $deleted = DB::table('link_clicks')->where('utm_source', self::TAG)->delete();
            $this->info("Removed {$deleted} synthetic row(s).");
            return self::SUCCESS;
        }

        $rows  = max(1, (int) $this->option('rows'));
        $batch = max(1, (int) $this->option('batch'));
        $days  = max(1, (int) $this->option('days'));
        $link  = $this->option('link');

        if ($this->option('dry-run')) {
            $this->info("DRY RUN: would seed {$rows} clicks (batch {$batch}) across {$days} days.");
            $this->reportPartitionState($partitions);
            return self::SUCCESS;
        }

        if (!$link || !DB::table('links')->where('id', $link)->exists()) {
            $this->error('Provide an existing --link=<id> to attribute synthetic clicks to.');
            return self::FAILURE;
        }
        $link = (int) $link;

        $this->reportPartitionState($partitions);

        // 1. Buffered insert throughput.
        $insertMs = $this->time(function () use ($rows, $batch, $days, $link) {
            $this->seedClicks($link, $rows, $batch, $days);
        });
        $rate = $insertMs > 0 ? round($rows / ($insertMs / 1000)) : $rows;
        $this->info("Insert: {$rows} rows in {$insertMs}ms (~{$rate} rows/sec).");

        // 2. Counter flush latency.
        if (Schema::hasTable('counter_deltas')) {
            $flushMs = $this->time(fn () => $this->runMetered('analytics:flush-counters'));
            $this->info("Counter flush: {$flushMs}ms.");
        } else {
            $this->line('Counter flush: skipped (counter_deltas absent).');
        }

        // 3. Daily rollup latency.
        if (Schema::hasTable('link_click_daily')) {
            $rollupMs = $this->time(fn () => $this->runMetered('analytics:rollup-daily', ['--lookback' => $days]));
            $this->info("Daily rollup: {$rollupMs}ms.");
        } else {
            $this->line('Daily rollup: skipped (link_click_daily absent).');
        }

        // 4. Dashboard read latency through the rollup reader.
        $readMs = $this->time(function () use ($reader, $link, $days) {
            try {
                $reader->byDay($link, now()->subDays($days), now());
            } catch (\Throwable $e) {
                $this->warn('Reader byDay() not exercised: ' . $e->getMessage());
            }
        });
        $this->info("Dashboard read (byDay): {$readMs}ms.");

        $this->newLine();
        $this->info('Done. Run with --cleanup to remove the synthetic rows.');
        return self::SUCCESS;
    }

    private function seedClicks(int $link, int $rows, int $batch, int $days): void
    {
        $countries = ['US', 'GB', 'DE', 'IN', 'BR', 'FR', 'CA', 'AU'];
        $devices   = ['desktop', 'mobile', 'tablet'];
        $referrers = ['https://t.co/', 'https://www.google.com/', 'https://www.instagram.com/', ''];

        $remaining = $rows;
        while ($remaining > 0) {
            $take = min($batch, $remaining);
            $payload = [];
            for ($i = 0; $i < $take; $i++) {
                $when = now()->subDays(random_int(0, $days - 1))->subSeconds(random_int(0, 86399));
                $payload[] = [
                    'link_id'    => $link,
                    'clicked_at' => $when,
                    'created_at' => $when,
                    'updated_at' => $when,
                    'country'    => $countries[array_rand($countries)],
                    'device'     => $devices[array_rand($devices)],
                    'referer'    => $referrers[array_rand($referrers)],
                    'utm_source' => self::TAG,
                    'is_bot'     => false,
                    'event_id'   => (string) Str::uuid(),
                ];
            }
            // Drop columns the table doesn't have, so this works across schema drift.
            $payload = $this->filterColumns('link_clicks', $payload);
            DB::table('link_clicks')->insert($payload);
            $remaining -= $take;
        }
    }

    /** Keep only keys that are real columns on $table. */
    private function filterColumns(string $table, array $rows): array
    {
        if (empty($rows)) {
            return $rows;
        }
        $cols = array_flip(Schema::getColumnListing($table));
        return array_map(fn ($row) => array_intersect_key($row, $cols), $rows);
    }

    private function reportPartitionState(PartitionManager $partitions): void
    {
        foreach (['link_clicks', 'page_sessions'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $state = $partitions->isPartitioned($table)
                ? (count($partitions->partitionsOf($table)) . ' partitions')
                : 'not partitioned';
            $this->line("  {$table}: {$state}.");
        }
    }

    private function runMetered(string $command, array $args = []): void
    {
        try {
            $this->callSilently($command, $args);
        } catch (\Throwable $e) {
            $this->warn("{$command} failed: " . $e->getMessage());
        }
    }

    /** Milliseconds to run $fn. */
    private function time(callable $fn): int
    {
        $start = microtime(true);
        $fn();
        return (int) round((microtime(true) - $start) * 1000);
    }
}
