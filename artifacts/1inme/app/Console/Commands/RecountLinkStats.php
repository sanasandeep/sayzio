<?php

namespace App\Console\Commands;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkClick;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recompute the cached `total_clicks` and `unique_clicks` counters on the
 * `links` table from the source-of-truth `link_clicks` rows.
 *
 * Why: counters are incremented inline by LinkTrackingService, but rows can
 * land in `link_clicks` through other paths (seeders, imports, backfills)
 * which leaves the cached counters out of sync. This command reconciles them.
 *
 * Idempotent. Safe to run any time.
 */
class RecountLinkStats extends Command
{
    protected $signature = 'analytics:recount-link-stats
        {--link= : Optional link id to recount (default: all links)}
        {--dry-run : Show what would change without writing}';

    protected $description = 'Recompute links.total_clicks and links.unique_clicks from the link_clicks table.';

    public function handle(): int
    {
        $linkId  = $this->option('link');
        $dry     = (bool) $this->option('dry-run');

        $query = Link::query();
        if ($linkId) {
            $query->where('id', $linkId);
        }

        $total   = (clone $query)->count();
        $updated = 0;
        $changed = 0;

        $this->info("Recounting stats for {$total} link(s)" . ($dry ? ' [dry-run]' : ''));
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->select(['id', 'total_clicks', 'unique_clicks'])
            ->chunkById(500, function ($links) use (&$updated, &$changed, $dry, $bar) {
                $ids = $links->pluck('id')->all();

                $totals = LinkClick::whereIn('link_id', $ids)
                    ->select('link_id', DB::raw('COUNT(*) as c'), DB::raw('COUNT(DISTINCT ip_address) as u'))
                    ->groupBy('link_id')
                    ->get()
                    ->keyBy('link_id');

                foreach ($links as $link) {
                    $row    = $totals->get($link->id);
                    $newT   = (int) ($row->c ?? 0);
                    $newU   = (int) ($row->u ?? 0);
                    $oldT   = (int) $link->total_clicks;
                    $oldU   = (int) $link->unique_clicks;

                    if ($newT !== $oldT || $newU !== $oldU) {
                        $changed++;
                        if (!$dry) {
                            Link::whereKey($link->id)->update([
                                'total_clicks'  => $newT,
                                'unique_clicks' => $newU,
                            ]);
                        }
                    }
                    $updated++;
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);
        $this->info("Processed: {$updated} link(s). Out of sync: {$changed}.");
        if ($dry && $changed > 0) {
            $this->comment('Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }
}
