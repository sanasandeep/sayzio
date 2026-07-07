<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\ScheduledJobRun;
use App\Modules\Admin\Support\ScheduledJobRegistry;
use App\Modules\Admin\Support\ScheduledJobRunPruner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers scheduled_job_runs retention: the daily `scheduled-runs:prune`
 * sweep and the shared ScheduledJobRunPruner policy — a row is deleted
 * only when it is BOTH older than the keep-days window AND beyond the
 * newest keep-N rows for its job ("last 30 days or last 200 runs per
 * job, whichever is larger"), so the table can never grow unbounded.
 */
class ScheduledJobRunRetentionTest extends TestCase
{
    use RefreshDatabase;

    private function makeRun(string $jobKey, int $ageDays): int
    {
        $run = ScheduledJobRun::create([
            'job_key'     => $jobKey,
            'source'      => ScheduledJobRun::SOURCE_SCHEDULE,
            'status'      => ScheduledJobRun::STATUS_SUCCESS,
            'started_at'  => now()->subDays($ageDays),
            'finished_at' => now()->subDays($ageDays),
            'runtime'     => 0.5,
            'exit_code'   => 0,
        ]);

        return $run->id;
    }

    public function test_prune_job_is_registered_in_analytics_cleanup_group(): void
    {
        ScheduledJobRegistry::flush();
        $def = ScheduledJobRegistry::find('scheduled-runs:prune');

        $this->assertNotNull($def, 'scheduled-runs:prune must be registered in the schedule registry.');
        $this->assertSame('analytics-cleanup', $def['group']);
        $this->assertSame('dailyAt', $def['cadence'][0]);
    }

    public function test_prune_deletes_only_rows_outside_both_windows(): void
    {
        // Recent rows are kept regardless of count; old rows are kept while
        // they remain within the newest-N window.
        $recent = $this->makeRun('analytics:flush-counters', 1);
        $oldButWithinCount = $this->makeRun('analytics:flush-counters', 45);

        $this->artisan('scheduled-runs:prune')->assertExitCode(0);

        $this->assertDatabaseHas('scheduled_job_runs', ['id' => $recent]);
        $this->assertDatabaseHas('scheduled_job_runs', ['id' => $oldButWithinCount]);
    }

    public function test_prune_deletes_old_overflow_rows_but_keeps_newest_n_and_recent_rows(): void
    {
        $keep = 5;

        // 8 old rows (beyond the days window): with keep=5, the 3 oldest go.
        $oldIds = [];
        for ($i = 0; $i < 8; $i++) {
            $oldIds[] = $this->makeRun('analytics:flush-counters', 60 - $i);
        }

        // 4 recent rows: always kept, but they push old rows out of the
        // newest-N window.
        $recentIds = [];
        for ($i = 0; $i < 4; $i++) {
            $recentIds[] = $this->makeRun('analytics:flush-counters', 2);
        }

        // Another job's old rows must be untouched (per-job counting).
        $otherJobOld = $this->makeRun('analytics:rollup-daily', 90);

        $this->artisan('scheduled-runs:prune', ['--days' => 30, '--keep' => $keep])
            ->assertExitCode(0);

        // Newest 5 = the 4 recent + the newest old row (oldIds[7]); recent
        // rows also survive via the days window.
        foreach ($recentIds as $id) {
            $this->assertDatabaseHas('scheduled_job_runs', ['id' => $id]);
        }
        $this->assertDatabaseHas('scheduled_job_runs', ['id' => $oldIds[7]]);

        // The 7 older old rows fall outside BOTH windows — deleted.
        foreach (array_slice($oldIds, 0, 7) as $id) {
            $this->assertDatabaseMissing('scheduled_job_runs', ['id' => $id]);
        }

        $this->assertDatabaseHas('scheduled_job_runs', ['id' => $otherJobOld]);
    }

    public function test_rare_job_keeps_full_run_count_even_when_all_rows_are_old(): void
    {
        // A monthly job whose entire history is older than the days window
        // still keeps its newest N rows — count is the larger window here.
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = $this->makeRun('tracking:maintain-partitions', 200 - $i * 30);
        }

        $this->artisan('scheduled-runs:prune', ['--days' => 30, '--keep' => 5])
            ->assertExitCode(0);

        foreach ($ids as $id) {
            $this->assertDatabaseHas('scheduled_job_runs', ['id' => $id]);
        }
    }

    public function test_pruner_covers_job_keys_no_longer_in_the_registry(): void
    {
        // History for removed/renamed jobs is swept too (dual-bound applies).
        $keptNewest = [];
        for ($i = 0; $i < 2; $i++) {
            $keptNewest[] = $this->makeRun('legacy:removed-job', 60 - $i);
        }
        $overflow = [];
        for ($i = 0; $i < 3; $i++) {
            $overflow[] = $this->makeRun('legacy:removed-job', 90 - $i);
        }

        // Overflow rows were created AFTER keptNewest so they hold the newest
        // ids; rebuild expectation from actual id order (newest 2 survive).
        $all = ScheduledJobRun::where('job_key', 'legacy:removed-job')
            ->orderByDesc('id')->pluck('id')->all();

        $deleted = ScheduledJobRunPruner::pruneAll(30, 2);

        $this->assertSame(3, $deleted['legacy:removed-job'] ?? 0);
        $this->assertDatabaseHas('scheduled_job_runs', ['id' => $all[0]]);
        $this->assertDatabaseHas('scheduled_job_runs', ['id' => $all[1]]);
        foreach (array_slice($all, 2) as $id) {
            $this->assertDatabaseMissing('scheduled_job_runs', ['id' => $id]);
        }
    }
}
