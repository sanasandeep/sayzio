<?php

namespace App\Console\Commands;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Services\NotificationService;
use App\Modules\Common\Support\PartitionManager;
use App\Modules\Common\Support\StatsRetentionPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Delete raw analytics history (link_clicks + page_sessions) older than the
 * stats-retention window, with a hard physical safety cap so the tables can
 * never grow unbounded silently.
 *
 * Retention precedence:
 *   1. Plan retention — the GLOBAL maximum stats_retention_days across active
 *      plans (never per-user; the tables aren't partitioned by plan). If any
 *      active plan keeps history forever (-1) or has no retention configured,
 *      plan-based pruning is a safe no-op (never delete entitled data).
 *   2. Hard physical cap — operator-set AppSetting `stats.hard_max_days`. When
 *      present it bounds storage even when plan retention is unlimited: data
 *      older than the cap is pruned regardless, and admins are alerted that the
 *      cap (not plan policy) is doing the deleting.
 *
 * Visibility: every run records its outcome to AppSetting `stats.prune.last_run`
 * and, when a table's estimated size crosses `stats.alert_row_threshold`, raises
 * a (deduped) admin system alert — so "keep forever, no hard cap" growth is
 * surfaced rather than silently ignored.
 *
 * Partitioned tables drop whole expired month partitions (O(1) metadata) before
 * falling back to chunked row deletes for anything left (e.g. a DEFAULT partition
 * or a non-partitioned table).
 */
class PruneStatsHistory extends Command
{
    protected $signature = 'stats:prune-history
        {--chunk=1000 : Rows to delete per batch (keeps each statement small)}
        {--max-batches=5000 : Safety cap on batches per table per run}
        {--hard-max-days= : Override the configured hard physical cap for this run}
        {--dry-run : Report what would happen without deleting}';

    protected $description = 'Prune click/session history by plan retention with a hard physical safety cap and growth visibility.';

    /** Tables to prune, keyed by their "created" timestamp column. */
    private const TABLES = StatsRetentionPolicy::TABLES;

    public function handle(PartitionManager $partitions): int
    {
        $dry        = (bool) $this->option('dry-run');
        $chunk      = max(1, (int) $this->option('chunk'));
        $maxBatches = max(1, (int) $this->option('max-batches'));

        $planRetention = $this->largestRetentionDays();
        $hardMax       = $this->hardMaxDays();

        [$effectiveDays, $reason] = $this->effectiveRetention($planRetention, $hardMax);

        $report = [
            'ran_at'          => now()->toDateTimeString(),
            'plan_retention'  => $planRetention,
            'hard_max_days'   => $hardMax,
            'effective_days'  => $effectiveDays,
            'reason'          => $reason,
            'dry_run'         => $dry,
            'tables'          => [],
        ];

        // Always surface table sizes (visibility) and alert on unbounded growth.
        $this->reportSizesAndAlert($report, $effectiveDays, $hardMax);

        if ($effectiveDays === null) {
            $this->warn("No pruning: {$reason}. Set a hard cap via AppSetting 'stats.hard_max_days' to bound storage.");
            $report['action'] = 'noop';
            AppSetting::put('stats.prune.last_run', $report);
            return self::SUCCESS;
        }

        $cutoff = now()->subDays($effectiveDays);
        $this->info("Pruning stats older than {$effectiveDays} day(s) before {$cutoff->toDateTimeString()} ({$reason}).");

        foreach (self::TABLES as $table => $column) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $droppedPartitions = [];
            $rowsDeleted       = 0;

            if ($partitions->isPartitioned($table)) {
                if ($dry) {
                    $this->line("  {$table}: partitioned — would drop month partitions ending on/before cutoff.");
                } else {
                    $droppedPartitions = $partitions->dropPartitionsBefore($table, $cutoff);
                    $this->info("  {$table}: dropped " . (count($droppedPartitions) ?: 0) . ' partition(s).');
                }
            }

            // Chunked row deletes mop up anything not covered by a dropped
            // partition (DEFAULT partition rows, or a non-partitioned table).
            if (!$dry) {
                $rowsDeleted = $this->pruneTable($table, $column, $cutoff, $chunk, $maxBatches);
                $this->info("  {$table}: removed {$rowsDeleted} row(s).");
            } else {
                $remaining = $this->estimateOlderThan($table, $column, $cutoff);
                $this->line("  {$table}: ~{$remaining} row(s) older than cutoff would be deleted.");
            }

            $report['tables'][$table]['dropped_partitions'] = $droppedPartitions;
            $report['tables'][$table]['rows_deleted']       = $rowsDeleted;
        }

        if ($hardMax !== null && $planRetention === -1) {
            // We pruned under unlimited plan retention purely because of the
            // operator hard cap — make that explicit to admins.
            $this->raiseAlert(
                'Stats hard cap pruned unlimited-retention history',
                "A plan retains stats forever (-1) but the hard physical cap (stats.hard_max_days={$hardMax}) deleted data older than {$effectiveDays} days to bound storage.",
                ['effective_days' => $effectiveDays, 'cutoff' => $cutoff->toDateTimeString()],
                'warning'
            );
        }

        $report['action'] = 'pruned';
        AppSetting::put('stats.prune.last_run', $report);
        return self::SUCCESS;
    }

    /**
     * Resolve the effective prune window (days) and a human reason.
     * Returns [null, reason] when nothing should be pruned.
     *
     * @return array{0:?int,1:string}
     */
    private function effectiveRetention(int $planRetention, ?int $hardMax): array
    {
        return StatsRetentionPolicy::effectiveRetention($planRetention, $hardMax);
    }

    private function hardMaxDays(): ?int
    {
        $override = $this->option('hard-max-days');
        if ($override !== null && $override !== '') {
            return max(1, (int) $override);
        }
        return StatsRetentionPolicy::hardMaxDays();
    }

    /**
     * Estimate each table's size (fast — uses planner stats, not count(*)) and
     * raise a deduped admin alert when growth crosses the threshold while no
     * effective pruning will reclaim it.
     */
    private function reportSizesAndAlert(array &$report, ?int $effectiveDays, ?int $hardMax): void
    {
        $threshold = StatsRetentionPolicy::alertThreshold();

        foreach (self::TABLES as $table => $column) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $estRows = $this->estimateRows($table);
            $report['tables'][$table]['estimated_rows'] = $estRows;

            $this->line("  {$table}: ~" . number_format($estRows) . ' rows (estimated).');

            // Unbounded-growth alarm: large AND nothing will trim it this run.
            if ($estRows >= $threshold && $effectiveDays === null) {
                $this->alertOncePerDay(
                    "stats_growth_{$table}",
                    'Analytics table growing unbounded: ' . $table,
                    "{$table} holds ~" . number_format($estRows) . " rows and no retention or hard cap will prune it. "
                        . "Set a finite plan stats_retention_days or AppSetting 'stats.hard_max_days' to bound storage.",
                    ['table' => $table, 'estimated_rows' => $estRows, 'threshold' => $threshold]
                );
            }
        }
    }

    /**
     * Largest stats_retention_days across active plans. Returns -1 ("unlimited")
     * when any active plan is explicitly unlimited OR has no retention configured
     * yet (deliberate: never delete data on a plan whose retention isn't seeded).
     */
    private function largestRetentionDays(): int
    {
        return StatsRetentionPolicy::largestRetentionDays();
    }

    /**
     * Delete rows older than $cutoff in id-keyed chunks. Postgres has no
     * `DELETE ... LIMIT`, so we select a batch of ids first and delete those.
     * Bypasses model global scopes and Eloquent timestamps via the query builder.
     */
    private function pruneTable(string $table, string $column, \DateTimeInterface $cutoff, int $chunk, int $maxBatches): int
    {
        $total = 0;
        for ($batch = 0; $batch < $maxBatches; $batch++) {
            $ids = DB::table($table)
                ->where($column, '<', $cutoff)
                ->orderBy('id')
                ->limit($chunk)
                ->pluck('id')
                ->all();

            if (empty($ids)) {
                break;
            }
            $total += DB::table($table)->whereIn('id', $ids)->delete();
            if (count($ids) < $chunk) {
                break;
            }
        }
        return $total;
    }

    /** Fast row estimate from planner statistics (avoids count(*) on huge tables). */
    private function estimateRows(string $table): int
    {
        return StatsRetentionPolicy::estimateRows($table);
    }

    private function estimateOlderThan(string $table, string $column, \DateTimeInterface $cutoff): int
    {
        try {
            return (int) DB::table($table)->where($column, '<', $cutoff)->limit(1_000_001)->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function raiseAlert(string $title, string $body, array $context = [], string $level = 'warning'): void
    {
        try {
            app(NotificationService::class)->systemAlert($title, $body, $level, $context, 'analytics');
        } catch (\Throwable $e) {
            $this->warn('Admin alert failed: ' . $e->getMessage());
        }
    }

    /** Raise an alert at most once per calendar day per key (dedupe via AppSetting). */
    private function alertOncePerDay(string $key, string $title, string $body, array $context = []): void
    {
        $stateKey = "stats.alert.{$key}";
        $today    = now()->toDateString();
        if (AppSetting::get($stateKey) === $today) {
            return;
        }
        $this->raiseAlert($title, $body, $context, 'warning');
        AppSetting::put($stateKey, $today);
    }
}
