<?php

namespace App\Console\Commands;

use App\Modules\Admin\Support\ScheduledJobRunPruner;
use Illuminate\Console\Command;

/**
 * Daily retention sweep for the scheduled_job_runs history table. With ~59
 * registered jobs (one every minute, several every 2–5 minutes) the table
 * gains thousands of rows per day; this keeps it bounded so the admin
 * history drawer and last-run joins stay fast.
 *
 * Policy (shared with the recorder's lottery prune via
 * ScheduledJobRunPruner): a row survives while it is within the last
 * --days OR among the newest --keep rows for its job — whichever window
 * is larger wins.
 */
class PruneScheduledJobRuns extends Command
{
    protected $signature = 'scheduled-runs:prune
        {--days=' . ScheduledJobRunPruner::KEEP_DAYS . ' : Always keep runs newer than this many days}
        {--keep=' . ScheduledJobRunPruner::KEEP_PER_JOB . ' : Always keep the newest N runs per job}';

    protected $description = 'Prune scheduled-job run history beyond the retention window (last N days or newest N per job, whichever is larger).';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $keep = max(1, (int) $this->option('keep'));

        $deleted = ScheduledJobRunPruner::pruneAll($days, $keep);
        $total   = array_sum($deleted);

        foreach ($deleted as $key => $count) {
            $this->line("  {$key}: pruned {$count} row(s)");
        }

        $this->info("Pruned {$total} scheduled-job run row(s) (keeping last {$days} day(s) or newest {$keep} per job, whichever is larger).");

        return self::SUCCESS;
    }
}
