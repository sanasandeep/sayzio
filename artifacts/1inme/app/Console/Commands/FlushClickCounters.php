<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fold buffered counter deltas into the real cached counters.
 *
 * The async click write path never UPDATEs the hot `links.total_clicks` /
 * `biolink_blocks.click_count` rows directly — under load that serialises every
 * concurrent visitor on a single contended row. Instead it APPENDs lock-free
 * delta rows to `counter_deltas`; this command (scheduled once a minute,
 * single-server) aggregates those deltas per entity and applies one UPDATE per
 * entity, then deletes the folded rows.
 *
 * Counters are therefore eventually-consistent (≈1 min lag). `analytics:recount-link-stats`
 * remains the source-of-truth backstop that reconciles any drift.
 *
 * Idempotent and safe to run any time. To avoid racing rows inserted mid-run,
 * it captures the max delta id up front and only folds/deletes id <= that.
 */
class FlushClickCounters extends Command
{
    protected $signature = 'analytics:flush-counters
        {--dry-run : Show what would change without writing}';

    protected $description = 'Fold buffered counter_deltas into links/biolink_blocks counters.';

    public function handle(): int
    {
        if (!Schema::hasTable('counter_deltas')) {
            $this->warn('counter_deltas table not present — nothing to flush.');
            return self::SUCCESS;
        }

        $dry   = (bool) $this->option('dry-run');
        $maxId = (int) (DB::table('counter_deltas')->max('id') ?? 0);
        if ($maxId === 0) {
            $this->info('No pending counter deltas.');
            return self::SUCCESS;
        }

        // Aggregate everything queued up to the captured high-water mark.
        $aggregates = DB::table('counter_deltas')
            ->where('id', '<=', $maxId)
            ->groupBy('entity_type', 'entity_id')
            ->get([
                'entity_type',
                'entity_id',
                DB::raw('SUM(total_delta) AS total_delta'),
                DB::raw('SUM(unique_delta) AS unique_delta'),
            ]);

        if ($dry) {
            foreach ($aggregates as $row) {
                $total  = (int) $row->total_delta;
                $unique = (int) $row->unique_delta;
                if ($total === 0 && $unique === 0) {
                    continue;
                }
                $this->line(sprintf(
                    '%s #%d  +%d total  +%d unique',
                    $row->entity_type, $row->entity_id, $total, $unique
                ));
            }
            $this->info("Dry run: {$aggregates->count()} entity rows would be folded (up to delta id {$maxId}).");
            return self::SUCCESS;
        }

        $links  = 0;
        $blocks = 0;

        // Apply the fold and clear the folded rows in ONE transaction. The
        // counter UPDATEs and the matching DELETE of counter_deltas must commit
        // together: if the process dies (or the DB connection drops) mid-fold,
        // the whole transaction rolls back, so the same deltas are never applied
        // a second time on the next run — which would silently overcount the
        // cached counters. recount-link-stats remains the exact backstop, but the
        // minute-by-minute counters must not drift on a partial run.
        DB::transaction(function () use ($aggregates, $maxId, &$links, &$blocks) {
            foreach ($aggregates as $row) {
                $total  = (int) $row->total_delta;
                $unique = (int) $row->unique_delta;
                if ($total === 0 && $unique === 0) {
                    continue;
                }

                if ($row->entity_type === 'link') {
                    $update = [];
                    if ($total !== 0) {
                        $update['total_clicks'] = DB::raw('total_clicks + ' . $total);
                    }
                    if ($unique !== 0) {
                        $update['unique_clicks'] = DB::raw('unique_clicks + ' . $unique);
                    }
                    if ($update) {
                        $links += DB::table('links')->where('id', $row->entity_id)->update($update);
                    }
                } elseif ($row->entity_type === 'block') {
                    if ($total !== 0 && Schema::hasTable('biolink_blocks')) {
                        $blocks += DB::table('biolink_blocks')
                            ->where('id', $row->entity_id)
                            ->update(['click_count' => DB::raw('click_count + ' . $total)]);
                    }
                }
            }

            // Only delete what we folded. Rows inserted after $maxId stay for the
            // next run, so nothing is lost even if persistence kept writing mid-flush.
            DB::table('counter_deltas')->where('id', '<=', $maxId)->delete();
        });

        $this->info("Flushed counters: {$links} links, {$blocks} blocks updated; deltas up to id {$maxId} cleared.");
        return self::SUCCESS;
    }
}
