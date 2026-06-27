<?php

namespace App\Console\Commands;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\EmailLogRetentionPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prune the email_logs activity table so it can't grow forever.
 *
 * Every outbound email writes a row (subject + full rendered body); across ~70
 * email types and the whole user base this table grows without bound, bloating
 * the shared RDS and slowing the admin Email Log screen. This daily sweep
 * applies two tiers (both windows configurable via AppSetting — see
 * {@see EmailLogRetentionPolicy}):
 *
 *   1. Body trim — null the heavy stored body of rows older than
 *      `email_logs.body_retention_days`, keeping the lightweight audit row
 *      (who/when/status/subject). Reclaims the bulk of the storage early while
 *      preserving the searchable ledger.
 *   2. Row delete — delete whole rows older than `email_logs.retention_days`.
 *
 * Safety (mirrors stats:prune-history): each tier is chunked (Postgres has no
 * `DELETE/UPDATE ... LIMIT`, so we select ids then act on them) and capped by
 * --max-batches, so a single run can never mass-mutate the table. A retention
 * window of -1 makes that tier a no-op ("keep forever").
 */
class PruneEmailLogs extends Command
{
    protected $signature = 'email-logs:prune-history
        {--chunk=1000 : Rows to process per batch (keeps each statement small)}
        {--max-batches=5000 : Safety cap on batches per tier per run}
        {--retention-days= : Override the row-delete window for this run}
        {--body-retention-days= : Override the body-trim window for this run}
        {--dry-run : Report what would happen without changing anything}';

    protected $description = 'Trim email_logs by retention: null old bodies, delete older rows. Chunked + capped.';

    public function handle(): int
    {
        $dry        = (bool) $this->option('dry-run');
        $chunk      = max(1, (int) $this->option('chunk'));
        $maxBatches = max(1, (int) $this->option('max-batches'));

        if (!Schema::hasTable('email_logs')) {
            $this->warn('email_logs table does not exist yet — nothing to prune.');
            return self::SUCCESS;
        }

        $retentionDays = $this->resolveWindow('retention-days', EmailLogRetentionPolicy::retentionDays());
        $bodyDays      = $this->resolveWindow('body-retention-days', EmailLogRetentionPolicy::bodyRetentionDays());

        $report = [
            'ran_at'              => now()->toDateTimeString(),
            'retention_days'      => $retentionDays,
            'body_retention_days' => $bodyDays,
            'dry_run'             => $dry,
            'bodies_trimmed'      => 0,
            'rows_deleted'        => 0,
        ];

        // Tier 1: null heavy bodies of rows older than the body window but not yet
        // old enough to be deleted (those get removed wholesale below anyway).
        if ($bodyDays === -1) {
            $this->line('Body trim: disabled (body_retention_days = -1).');
        } else {
            $bodyCutoff   = now()->subDays($bodyDays);
            $deleteCutoff = $retentionDays === -1 ? null : now()->subDays($retentionDays);

            if ($dry) {
                $count = $this->countTrimmable($bodyCutoff, $deleteCutoff);
                $this->line("Body trim: ~{$count} row(s) older than {$bodyDays}d would have their body nulled.");
                $report['bodies_trimmed'] = $count;
            } else {
                $trimmed = $this->trimBodies($bodyCutoff, $deleteCutoff, $chunk, $maxBatches);
                $this->info("Body trim: nulled body on {$trimmed} row(s) older than {$bodyDays} day(s).");
                $report['bodies_trimmed'] = $trimmed;
            }
        }

        // Tier 2: delete whole rows older than the retention window.
        if ($retentionDays === -1) {
            $this->line('Row delete: disabled (retention_days = -1, keep forever).');
        } else {
            $deleteCutoff = now()->subDays($retentionDays);

            if ($dry) {
                $count = (int) DB::table('email_logs')->where('created_at', '<', $deleteCutoff)->count();
                $this->line("Row delete: ~{$count} row(s) older than {$retentionDays}d would be deleted.");
                $report['rows_deleted'] = $count;
            } else {
                $deleted = $this->deleteRows($deleteCutoff, $chunk, $maxBatches);
                $this->info("Row delete: removed {$deleted} row(s) older than {$retentionDays} day(s).");
                $report['rows_deleted'] = $deleted;
            }
        }

        $report['action'] = $dry ? 'dry-run' : 'pruned';
        AppSetting::put('email_logs.prune.last_run', $report);

        return self::SUCCESS;
    }

    /**
     * Null the heavy body of rows whose created_at is older than the body cutoff
     * but newer than the delete cutoff (rows past the delete cutoff are removed
     * wholesale, so trimming them first is wasted work). The small `error`
     * column is kept so failure diagnostics outlive the body. Only touches rows
     * that still have a body, so re-runs converge to zero.
     */
    private function trimBodies(\DateTimeInterface $bodyCutoff, ?\DateTimeInterface $deleteCutoff, int $chunk, int $maxBatches): int
    {
        $total = 0;
        for ($batch = 0; $batch < $maxBatches; $batch++) {
            $ids = DB::table('email_logs')
                ->where('created_at', '<', $bodyCutoff)
                ->when($deleteCutoff !== null, fn ($q) => $q->where('created_at', '>=', $deleteCutoff))
                ->whereNotNull('body')
                ->orderBy('id')
                ->limit($chunk)
                ->pluck('id')
                ->all();

            if (empty($ids)) {
                break;
            }

            $total += DB::table('email_logs')->whereIn('id', $ids)->update([
                'body' => null,
            ]);

            if (count($ids) < $chunk) {
                break;
            }
        }

        return $total;
    }

    /**
     * Delete rows older than the cutoff in id-keyed chunks (Postgres has no
     * DELETE ... LIMIT, so we select a batch of ids first and delete those).
     */
    private function deleteRows(\DateTimeInterface $cutoff, int $chunk, int $maxBatches): int
    {
        $total = 0;
        for ($batch = 0; $batch < $maxBatches; $batch++) {
            $ids = DB::table('email_logs')
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($chunk)
                ->pluck('id')
                ->all();

            if (empty($ids)) {
                break;
            }

            $total += DB::table('email_logs')->whereIn('id', $ids)->delete();

            if (count($ids) < $chunk) {
                break;
            }
        }

        return $total;
    }

    private function countTrimmable(\DateTimeInterface $bodyCutoff, ?\DateTimeInterface $deleteCutoff): int
    {
        return (int) DB::table('email_logs')
            ->where('created_at', '<', $bodyCutoff)
            ->when($deleteCutoff !== null, fn ($q) => $q->where('created_at', '>=', $deleteCutoff))
            ->whereNotNull('body')
            ->count();
    }

    /**
     * Resolve a per-run window override from a CLI option, falling back to the
     * policy value. An explicit -1 is honoured (disables that tier); other
     * values are floored to keep recent logs safe.
     */
    private function resolveWindow(string $option, int $default): int
    {
        $raw = $this->option($option);
        if ($raw === null || $raw === '') {
            return $default;
        }

        $val = (int) $raw;
        if ($val === -1) {
            return -1;
        }

        return max(EmailLogRetentionPolicy::MIN_RETENTION_DAYS, $val);
    }
}
