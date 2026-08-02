<?php

namespace App\Console\Commands;

use App\Modules\User\Models\ContactMergeAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prune expired contact_merge_audits rows.
 *
 * Each row carries a full JSON snapshot of the merged-away contact (names,
 * phones, emails) so the merge can be undone. Once the undo window
 * ({@see ContactMergeAudit::UNDO_WINDOW_DAYS}) has passed the row is dead
 * weight and keeps PII around longer than needed, so this daily sweep deletes
 * rows created more than UNDO_WINDOW_DAYS + grace days ago. The grace window
 * (default 7 days) keeps a short buffer past the hard undo cutoff so support
 * can still inspect a just-expired merge.
 *
 * Rows that were already undone are pruned on the same clock — the undo
 * restored the contact, so the snapshot is redundant, but keeping it through
 * the window preserves the recent-activity audit trail.
 *
 * Safety (mirrors events:prune-discoverability): deletes are id-keyed chunks
 * (Postgres has no DELETE ... LIMIT) capped by --max-batches.
 */
class PruneContactMergeAudits extends Command
{
    protected $signature = 'contacts:prune-merge-audits
        {--grace-days=7 : Extra days past the undo window before a row is deleted}
        {--chunk=1000 : Rows to delete per batch}
        {--max-batches=5000 : Safety cap on batches per run}
        {--dry-run : Report what would be deleted without changing anything}';

    protected $description = 'Delete contact_merge_audits rows older than the undo window plus a grace period. Chunked + capped.';

    public function handle(): int
    {
        if (!Schema::hasTable('contact_merge_audits')) {
            $this->warn('contact_merge_audits table does not exist yet — nothing to prune.');
            return self::SUCCESS;
        }

        $grace      = max(0, (int) $this->option('grace-days'));
        $chunk      = max(1, (int) $this->option('chunk'));
        $maxBatches = max(1, (int) $this->option('max-batches'));
        $days       = ContactMergeAudit::UNDO_WINDOW_DAYS + $grace;
        $cutoff     = now()->subDays($days);

        if ($this->option('dry-run')) {
            $count = (int) DB::table('contact_merge_audits')
                ->where('created_at', '<', $cutoff)
                ->count();
            $this->line("Dry run: {$count} merge-audit row(s) older than {$days} day(s) would be deleted.");
            return self::SUCCESS;
        }

        $total = 0;
        for ($batch = 0; $batch < $maxBatches; $batch++) {
            $ids = DB::table('contact_merge_audits')
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($chunk)
                ->pluck('id')
                ->all();

            if (empty($ids)) {
                break;
            }

            $total += DB::table('contact_merge_audits')->whereIn('id', $ids)->delete();

            if (count($ids) < $chunk) {
                break;
            }
        }

        $this->info("Deleted {$total} expired contact merge-audit row(s) (older than {$days} day(s)).");

        return self::SUCCESS;
    }
}
