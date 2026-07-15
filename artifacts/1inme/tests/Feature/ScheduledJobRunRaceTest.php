<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\ScheduledJobRun;
use App\Modules\Admin\Support\ScheduledJobHealthAlerts;
use App\Modules\Admin\Support\ScheduledJobRunRecorder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Regression tests for the duplicate-run / race conditions fixed in Jul 2025:
 *
 * 1. When both ScheduledTaskFailed and ScheduledTaskFinished fire for the
 *    same run (exception-throwing command), exactly one DB row is written.
 *
 * 2. A late failure callback from an earlier concurrent run must not reopen
 *    an episode that a subsequent successful run has already closed.
 *
 * 3. A genuine failure with no later success still opens the episode
 *    (regression guard: fix must not over-suppress real failures).
 */
class ScheduledJobRunRaceTest extends TestCase
{
    use RefreshDatabase;

    // ── Helper: recorder whose keyFor() always returns a fixed key ────────

    private function recorderFor(string $jobKey): ScheduledJobRunRecorder
    {
        return new class($jobKey) extends ScheduledJobRunRecorder {
            public function __construct(private readonly string $fixedKey) {}

            public function keyFor(Event $event): ?string
            {
                return $this->fixedKey;
            }
        };
    }

    private function mockEvent(): Event
    {
        return $this->createMock(Event::class);
    }

    // ── 1. Deduplication: one DB row per run ──────────────────────────────

    /**
     * When a command exits via an exception, Laravel dispatches:
     *   - ScheduledTaskFailed  (immediately, calls finished(ok=false))
     *   - ScheduledTaskFinished (after the process returns, also calls finished)
     *
     * The second call must be a no-op — not insert a second failed row.
     */
    public function test_both_failed_and_finished_events_write_exactly_one_row(): void
    {
        $recorder = $this->recorderFor('contacts:sync');
        $event    = $this->mockEvent();

        $recorder->starting($event);
        $this->assertDatabaseCount('scheduled_job_runs', 1);

        // ScheduledTaskFailed fires first (exception path).
        $recorder->finished($event, false, null, 'Task threw an exception', null);

        // Must update the existing row, not create a new one.
        $this->assertDatabaseCount('scheduled_job_runs', 1);

        // ScheduledTaskFinished fires second for the same run — must be a no-op.
        $recorder->finished($event, false, 0.5, 'Exited with code 1', 1);
        $this->assertDatabaseCount('scheduled_job_runs', 1);

        $run = ScheduledJobRun::first();
        $this->assertSame(ScheduledJobRun::STATUS_FAILED, $run->status);
    }

    /**
     * Same deduplication must hold even when starting() itself was never called
     * (the ScheduledTaskStarting insert failed) — the second call to finished()
     * must not insert a second row via the fallback path either.
     */
    public function test_deduplication_applies_even_without_a_prior_starting_insert(): void
    {
        $recorder = $this->recorderFor('contacts:sync');
        $event    = $this->mockEvent();

        // No starting() — the fallback row-creation path is taken on first finished().
        $recorder->finished($event, false, null, 'Error A', 1);
        $this->assertDatabaseCount('scheduled_job_runs', 1);

        // Second finished() for the same run must be swallowed.
        $recorder->finished($event, false, null, 'Error B', 1);
        $this->assertDatabaseCount('scheduled_job_runs', 1);
    }

    /**
     * A successful run followed by the two finish events still produces one row.
     */
    public function test_successful_run_records_exactly_one_row(): void
    {
        $recorder = $this->recorderFor('contacts:sync');
        $event    = $this->mockEvent();

        $recorder->starting($event);
        $recorder->finished($event, true, 1.2, null, 0);
        $recorder->finished($event, true, 1.2, null, 0); // duplicate — should be no-op

        $this->assertDatabaseCount('scheduled_job_runs', 1);
        $this->assertSame(ScheduledJobRun::STATUS_SUCCESS, ScheduledJobRun::first()->status);
    }

    // ── 2. Episode race: late failure must not reopen after success ────────

    /**
     * Scenario: concurrent duplicate runs of cv-uploads:prune-abandoned.
     *   - Run A (instance 1): fails at T1
     *   - Run B (instance 2): succeeds at T2 > T1 → episode closed
     *   - Run A's late failure callback arrives at T3 > T2 → must NOT reopen
     */
    public function test_late_failure_does_not_reopen_episode_after_later_success(): void
    {
        $jobKey = 'contacts:sync';

        // Seed run B (success, more recent finished_at) into the DB.
        ScheduledJobRun::create([
            'job_key'     => $jobKey,
            'source'      => ScheduledJobRun::SOURCE_SCHEDULE,
            'status'      => ScheduledJobRun::STATUS_SUCCESS,
            'started_at'  => Carbon::now()->subMinutes(5),
            'finished_at' => Carbon::now()->subMinutes(4),
        ]);

        // Success fires first and closes no episode (none open yet — OK).
        ScheduledJobHealthAlerts::jobFinished($jobKey, true, null, 0, 'schedule');

        $state = AppSetting::get(ScheduledJobHealthAlerts::STATE_KEY, []);
        $this->assertEmpty($state['jobs'][$jobKey] ?? []);

        // Run A's late failure callback now arrives.
        ScheduledJobHealthAlerts::jobFinished($jobKey, false, 'concurrent run failed', 1, 'schedule');

        $state = AppSetting::get(ScheduledJobHealthAlerts::STATE_KEY, []);
        $this->assertEmpty(
            $state['jobs'][$jobKey] ?? [],
            'a late failure must not open an episode when a newer success row exists in the DB'
        );
    }

    /**
     * Same scenario but the episode was already open before the success.
     * Success must close it; a subsequent late failure must not reopen it.
     */
    public function test_late_failure_does_not_reopen_episode_that_was_closed_by_success(): void
    {
        $jobKey = 'contacts:sync';

        // Episode opened by a real earlier failure.
        ScheduledJobHealthAlerts::jobFinished($jobKey, false, 'first failure', 1, 'schedule');

        $state = AppSetting::get(ScheduledJobHealthAlerts::STATE_KEY, []);
        $this->assertTrue((bool) ($state['jobs'][$jobKey]['alerting'] ?? false));

        // Seed the success run into the DB (as the recorder would have done).
        ScheduledJobRun::create([
            'job_key'     => $jobKey,
            'source'      => ScheduledJobRun::SOURCE_SCHEDULE,
            'status'      => ScheduledJobRun::STATUS_SUCCESS,
            'started_at'  => Carbon::now()->subMinutes(3),
            'finished_at' => Carbon::now()->subMinutes(2),
        ]);

        // Successful run closes the episode.
        ScheduledJobHealthAlerts::jobFinished($jobKey, true, null, 0, 'schedule');

        $state = AppSetting::get(ScheduledJobHealthAlerts::STATE_KEY, []);
        $this->assertArrayNotHasKey($jobKey, $state['jobs'] ?? []);

        // Late stale failure from an earlier concurrent run arrives now.
        ScheduledJobHealthAlerts::jobFinished($jobKey, false, 'stale failure from earlier run', 1, 'schedule');

        $state = AppSetting::get(ScheduledJobHealthAlerts::STATE_KEY, []);
        $this->assertEmpty(
            $state['jobs'][$jobKey] ?? [],
            'late failure must not reopen an episode that was closed by a later success'
        );
    }

    // ── 3. Genuine failures still work (no over-suppression) ─────────────

    /**
     * A failure with no later success in the DB must still open an episode.
     * This guards against the fix being too aggressive and swallowing real alerts.
     */
    public function test_genuine_failure_without_later_success_opens_episode(): void
    {
        $jobKey = 'contacts:sync';

        // Seed only a failure row in the DB (the most recent completed run is failed).
        ScheduledJobRun::create([
            'job_key'     => $jobKey,
            'source'      => ScheduledJobRun::SOURCE_SCHEDULE,
            'status'      => ScheduledJobRun::STATUS_FAILED,
            'started_at'  => Carbon::now()->subMinutes(5),
            'finished_at' => Carbon::now()->subMinutes(4),
            'error'       => 'real persistent failure',
        ]);

        ScheduledJobHealthAlerts::jobFinished($jobKey, false, 'real persistent failure', 1, 'schedule');

        $state = AppSetting::get(ScheduledJobHealthAlerts::STATE_KEY, []);
        $this->assertTrue(
            (bool) ($state['jobs'][$jobKey]['alerting'] ?? false),
            'a genuine failure with no later success must still open an episode'
        );
    }

    /**
     * When no run rows exist at all (empty DB), a failure must still open an
     * episode — the guard must fail open, not closed.
     */
    public function test_failure_with_no_db_rows_still_opens_episode(): void
    {
        $this->assertDatabaseCount('scheduled_job_runs', 0);

        ScheduledJobHealthAlerts::jobFinished('contacts:sync', false, 'error', 1, 'schedule');

        $state = AppSetting::get(ScheduledJobHealthAlerts::STATE_KEY, []);
        $this->assertTrue(
            (bool) ($state['jobs']['contacts:sync']['alerting'] ?? false),
            'failure with no DB rows must open an episode'
        );
    }
}
