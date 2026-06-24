<?php

namespace App\Console\Commands;

use App\Modules\Admin\Models\Plan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Delete raw analytics history (link_clicks + page_sessions) older than the
 * largest stats-retention window across all active plans. Without this sweep
 * the click/session tables grow forever even though no plan can ever view
 * history beyond its retention window.
 *
 * Retention is intentionally pruned by the GLOBAL maximum across plans (not
 * per-user): the tables aren't partitioned by plan, so deleting anything newer
 * than the most generous plan's window could destroy data a paying user is
 * still entitled to see. If ANY active plan keeps history forever
 * (stats_retention_days = -1) the command is a safe no-op.
 */
class PruneStatsHistory extends Command
{
    protected $signature = 'stats:prune-history
        {--chunk=1000 : Rows to delete per batch (keeps each statement small)}
        {--max-batches=5000 : Safety cap on batches per table per run}';

    protected $description = 'Delete click/session history older than the largest stats-retention window across all active plans.';

    /** Tables to prune, keyed by their "created" timestamp column. */
    private const TABLES = [
        'link_clicks'   => 'clicked_at',
        'page_sessions' => 'started_at',
    ];

    public function handle(): int
    {
        $retention = $this->largestRetentionDays();

        if ($retention === -1) {
            $this->info('At least one active plan retains stats history forever (-1); nothing to prune.');
            return self::SUCCESS;
        }

        $cutoff     = now()->subDays($retention);
        $chunk      = max(1, (int) $this->option('chunk'));
        $maxBatches = max(1, (int) $this->option('max-batches'));

        $this->info("Pruning stats history older than {$retention} day(s) (before {$cutoff->toDateTimeString()}).");

        foreach (self::TABLES as $table => $column) {
            $deleted = $this->pruneTable($table, $column, $cutoff, $chunk, $maxBatches);
            $this->info("  {$table}: removed {$deleted} row(s).");
        }

        return self::SUCCESS;
    }

    /**
     * Largest stats_retention_days across active plans. Returns -1 ("unlimited
     * — do not prune") when any active plan is explicitly unlimited OR has no
     * retention configured yet. Defaulting an un-configured plan to "keep
     * forever" (rather than the 30-day floor) is deliberate: this is the
     * destructive path, so it must never delete data on a plan whose retention
     * hasn't been seeded/saved. Only when EVERY active plan has an explicit
     * finite window do we compute the max and prune beyond it.
     */
    private function largestRetentionDays(): int
    {
        $plans = Plan::where('status', 'active')->get(['features']);

        // No active plans at all is a misconfiguration; treat it as "unlimited"
        // (no-op) rather than falling through to the 30-day floor and deleting
        // history nobody asked to prune.
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
     * Delete rows older than $cutoff in id-keyed chunks. Postgres has no
     * `DELETE ... LIMIT`, so we select a batch of ids first and delete those.
     * Uses the raw query builder to bypass model global scopes (e.g. the
     * is_bot scope on link_clicks) and Eloquent timestamps.
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
}
