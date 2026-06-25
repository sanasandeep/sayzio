<?php

namespace App\Console\Commands;

use App\Modules\Common\Support\PartitionManager;
use Illuminate\Console\Command;

/**
 * Keep the high-volume tracking tables' monthly partitions healthy:
 *  - create a rolling buffer of FUTURE month partitions so inserts always find
 *    a target (a missing partition for "now" would reject every click insert);
 *  - report current partition coverage.
 *
 * This is a no-op for any table that hasn't been converted to a partitioned
 * table yet (see tracking:setup-partitions), so it is always safe to schedule.
 * Dropping expired partitions is handled by the retention sweep (stats:prune-history),
 * which owns the retention window.
 */
class MaintainTrackingPartitions extends Command
{
    protected $signature = 'tracking:maintain-partitions
        {--months-ahead=3 : How many future month partitions to keep provisioned}';

    protected $description = 'Provision future monthly partitions for the tracking tables (no-op if not partitioned).';

    private const TABLES = ['link_clicks', 'page_sessions'];

    public function handle(PartitionManager $partitions): int
    {
        $ahead = max(1, (int) $this->option('months-ahead'));

        foreach (self::TABLES as $table) {
            if (!$partitions->isPartitioned($table)) {
                $this->line("  {$table}: not partitioned — skipping.");
                continue;
            }

            $created = $partitions->ensureFuturePartitions($table, $ahead);
            $have    = count($partitions->partitionsOf($table));

            if ($created) {
                $this->info("  {$table}: created " . implode(', ', $created) . " ({$have} total).");
            } else {
                $this->line("  {$table}: up to date ({$have} partitions).");
            }
        }

        return self::SUCCESS;
    }
}
