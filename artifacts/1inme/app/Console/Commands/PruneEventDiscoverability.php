<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prune expired event_discoverability opt-in rows.
 *
 * A row means a user opted in to "People at this event"; expires_at is
 * stamped with the event end so the opt-in auto-expires. Rows were only ever
 * filtered out at query time by the active() scope, so the table accumulated
 * a dead row for every past event — extra query weight plus lat/lng location
 * data retained longer than needed. This daily sweep deletes rows whose
 * expires_at is comfortably in the past (default > 7 days), leaving a grace
 * window so a just-ended event's data survives any trailing reads.
 *
 * Rows with expires_at NULL are never touched — they are still considered
 * active by the model's active() scope.
 *
 * Safety (mirrors email-logs:prune-history): deletes are id-keyed chunks
 * (Postgres has no DELETE ... LIMIT) capped by --max-batches.
 */
class PruneEventDiscoverability extends Command
{
    protected $signature = 'events:prune-discoverability
        {--days=7 : Grace window — only rows expired more than this many days ago are deleted}
        {--chunk=1000 : Rows to delete per batch}
        {--max-batches=5000 : Safety cap on batches per run}
        {--dry-run : Report what would be deleted without changing anything}';

    protected $description = 'Delete event_discoverability rows whose expires_at is past the grace window. Chunked + capped.';

    public function handle(): int
    {
        if (!Schema::hasTable('event_discoverability')) {
            $this->warn('event_discoverability table does not exist yet — nothing to prune.');
            return self::SUCCESS;
        }

        $days       = max(0, (int) $this->option('days'));
        $chunk      = max(1, (int) $this->option('chunk'));
        $maxBatches = max(1, (int) $this->option('max-batches'));
        $cutoff     = now()->subDays($days);

        if ($this->option('dry-run')) {
            $count = (int) DB::table('event_discoverability')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', $cutoff)
                ->count();
            $this->line("Dry run: {$count} row(s) expired more than {$days} day(s) ago would be deleted.");
            return self::SUCCESS;
        }

        $total = 0;
        for ($batch = 0; $batch < $maxBatches; $batch++) {
            $ids = DB::table('event_discoverability')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($chunk)
                ->pluck('id')
                ->all();

            if (empty($ids)) {
                break;
            }

            $total += DB::table('event_discoverability')->whereIn('id', $ids)->delete();

            if (count($ids) < $chunk) {
                break;
            }
        }

        $this->info("Deleted {$total} expired event-discoverability row(s) (expired > {$days} day(s) ago).");

        return self::SUCCESS;
    }
}
