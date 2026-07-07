<?php

namespace App\Modules\Admin\Support;

use App\Modules\Admin\Models\ScheduledJobRun;
use Illuminate\Support\Carbon;

/**
 * Single source of truth for the scheduled_job_runs retention policy.
 *
 * A run row is kept while EITHER bound still covers it — it is deleted only
 * when it is BOTH older than KEEP_DAYS AND beyond the newest KEEP_PER_JOB
 * rows for its job ("last 30 days or last 200 runs per job, whichever is
 * larger"). High-frequency jobs (every minute) stay bounded by the age
 * window; rare jobs (monthly) keep a useful run count even though every
 * row is "old".
 *
 * Two callers, one policy:
 *  - the daily `scheduled-runs:prune` registry job (canonical sweep);
 *  - ScheduledJobRunRecorder's opportunistic lottery prune (safety valve
 *    when the daily job itself is paused or misses).
 */
class ScheduledJobRunPruner
{
    /** Runs newer than this many days are always kept. */
    public const KEEP_DAYS = 30;

    /** The newest N rows per job are always kept, regardless of age. */
    public const KEEP_PER_JOB = 200;

    /**
     * Prune one job's history under the dual-bound policy.
     *
     * @return int rows deleted
     */
    public static function pruneJob(string $jobKey, ?int $keepDays = null, ?int $keepPerJob = null): int
    {
        $keepDays   = max(1, $keepDays ?? self::KEEP_DAYS);
        $keepPerJob = max(1, $keepPerJob ?? self::KEEP_PER_JOB);

        // Id of the oldest row inside the keep-N window; everything at or
        // below the row AFTER it (skip N) is a count-overflow candidate.
        $countCutoffId = ScheduledJobRun::where('job_key', $jobKey)
            ->orderByDesc('id')
            ->skip($keepPerJob)
            ->value('id');

        if ($countCutoffId === null) {
            return 0; // Fewer than N rows — nothing can be deleted.
        }

        return ScheduledJobRun::where('job_key', $jobKey)
            ->where('id', '<=', $countCutoffId)
            ->where('started_at', '<', Carbon::now()->subDays($keepDays))
            ->delete();
    }

    /**
     * Prune every job key present in the table (not just registry keys, so
     * history for removed/renamed jobs is eventually cleared too).
     *
     * @return array<string, int> job key => rows deleted (only non-zero entries)
     */
    public static function pruneAll(?int $keepDays = null, ?int $keepPerJob = null): array
    {
        $deleted = [];

        foreach (ScheduledJobRun::query()->distinct()->pluck('job_key') as $key) {
            $count = static::pruneJob($key, $keepDays, $keepPerJob);

            if ($count > 0) {
                $deleted[$key] = $count;
            }
        }

        return $deleted;
    }
}
