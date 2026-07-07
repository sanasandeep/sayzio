<?php

namespace App\Modules\Admin\Controllers;

use App\Console\Commands\CheckScheduledJobFailures;
use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\ScheduledJobRun;
use App\Modules\Admin\Support\CronJobsInspector;
use App\Modules\Admin\Support\ScheduledJobHealthAlerts;
use App\Modules\Admin\Support\ScheduledJobRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

/**
 * Admin "Scheduled Jobs" control panel. Lists every scheduled job — derived
 * live from the registry-driven schedule (ScheduledJobRegistry +
 * routes/console.php) via CronJobsInspector — grouped by functional area,
 * with per-job run history, and lets an operator:
 *
 *   - pause / resume a job (persisted via AppSetting, honoured by the
 *     scheduler's ->skip() filter; protected jobs can never be paused);
 *   - run a job now, in the background, outside its cadence
 *     (spawns `php artisan scheduled-jobs:run <key>`);
 *   - inspect the recent run history for a job (duration, exit code, error).
 *
 * Cadences are NOT editable here by design — they live in code
 * (routes/schedules/*.php) and change only via deployment.
 */
class CronJobsController extends Controller
{
    public function index(CronJobsInspector $inspector)
    {
        $jobs   = $inspector->jobs();
        $status = $inspector->schedulerStatus($jobs);

        // Group in registry group order; anything unresolvable (scheduled
        // outside the registry) lands in a trailing "Other" bucket.
        $grouped = [];
        foreach (ScheduledJobRegistry::GROUPS as $slug => $label) {
            $grouped[$slug] = ['label' => $label, 'jobs' => []];
        }
        $grouped['other'] = ['label' => 'Other', 'jobs' => []];

        foreach ($jobs as $job) {
            $slug = $job['group'] ?? 'other';
            $grouped[$slug]['jobs'][] = $job;
        }

        $grouped = array_filter($grouped, fn ($g) => $g['jobs'] !== []);

        return view('admin.cron-jobs.index', [
            'masterCronLine' => $inspector->masterCronLine(),
            'jobs'           => $jobs,
            'grouped'        => $grouped,
            'status'         => $status,
            'statusSeed'     => $this->statusPayload($status),
            'appPath'        => base_path(),
            // Open failure episodes (jobs in an active failure streak +
            // stale-scheduler episode) for the at-a-glance red banner.
            'failureEpisodes' => ScheduledJobHealthAlerts::openEpisodes(),
            'alertSettings'  => [
                'threshold'                  => CheckScheduledJobFailures::failureThreshold(),
                'cooldown_hours'             => CheckScheduledJobFailures::realertCooldownHours(),
                'default_threshold'          => CheckScheduledJobFailures::FAILURE_THRESHOLD,
                'default_cooldown_hours'     => CheckScheduledJobFailures::REALERT_COOLDOWN_HOURS,
                'min_threshold'              => CheckScheduledJobFailures::MIN_THRESHOLD,
                'max_threshold'              => CheckScheduledJobFailures::MAX_THRESHOLD,
                'min_cooldown_hours'         => CheckScheduledJobFailures::MIN_COOLDOWN_HOURS,
                'max_cooldown_hours'         => CheckScheduledJobFailures::MAX_COOLDOWN_HOURS,
                'stale_after_minutes'        => intdiv(ScheduledJobHealthAlerts::schedulerStaleAfterSeconds(), 60),
                'default_stale_after_minutes'=> intdiv(ScheduledJobHealthAlerts::SCHEDULER_STALE_AFTER_SECONDS, 60),
                'min_stale_after_minutes'    => intdiv(ScheduledJobHealthAlerts::MIN_STALE_AFTER_SECONDS, 60),
                'max_stale_after_minutes'    => intdiv(ScheduledJobHealthAlerts::MAX_STALE_AFTER_SECONDS, 60),
            ],
            'liveSeed'       => $this->liveMap($jobs),
            'mutedAlertJobs' => ScheduledJobHealthAlerts::mutedJobs(),
        ]);
    }

    /**
     * Save the admin-tunable failure-alert settings (consecutive-failure
     * threshold + re-alert cooldown) used by scheduled-jobs:check-failures.
     */
    public function updateFailureAlertSettings(Request $request)
    {
        $validated = $request->validate([
            'threshold' => [
                'required', 'integer',
                'min:' . CheckScheduledJobFailures::MIN_THRESHOLD,
                'max:' . CheckScheduledJobFailures::MAX_THRESHOLD,
            ],
            'cooldown_hours' => [
                'required', 'integer',
                'min:' . CheckScheduledJobFailures::MIN_COOLDOWN_HOURS,
                'max:' . CheckScheduledJobFailures::MAX_COOLDOWN_HOURS,
            ],
            'stale_after_minutes' => [
                'required', 'integer',
                'min:' . intdiv(ScheduledJobHealthAlerts::MIN_STALE_AFTER_SECONDS, 60),
                'max:' . intdiv(ScheduledJobHealthAlerts::MAX_STALE_AFTER_SECONDS, 60),
            ],
        ]);

        $all = AppSetting::get(CheckScheduledJobFailures::SETTINGS_KEY, []);
        $all = is_array($all) ? $all : [];

        $all['threshold']      = (int) $validated['threshold'];
        $all['cooldown_hours'] = (int) $validated['cooldown_hours'];

        AppSetting::put(CheckScheduledJobFailures::SETTINGS_KEY, $all);

        ScheduledJobHealthAlerts::setSchedulerStaleAfterSeconds((int) $validated['stale_after_minutes'] * 60);

        return back()->with(
            'success',
            "Alert settings saved — job alerts fire after {$all['threshold']} consecutive failures "
            . "(reminders for growing streaks at most every {$all['cooldown_hours']} hour(s)), and the scheduler "
            . "is reported down after {$validated['stale_after_minutes']} minute(s) without a tick."
        );
    }

    /**
     * Live per-job status map (JSON). The panel polls this every few seconds
     * while a run is in flight so status badges and last-run details update
     * in place without a manual page reload (parity with the mobile screen's
     * conditional polling).
     */
    public function status(CronJobsInspector $inspector)
    {
        $jobs = $inspector->jobs();

        return response()->json(['data' => [
            'jobs'   => $this->liveMap($jobs),
            'status' => $this->statusPayload($inspector->schedulerStatus($jobs)),
        ]]);
    }

    /**
     * JSON-safe scheduler health summary (state, last tick, overdue count),
     * shared by the index page's banner seed and the polling status endpoint
     * so the banner refreshes in place along with the job badges.
     *
     * @param  array{state:string, last_tick:?\Carbon\Carbon, overdue_count:int}  $status
     * @return array{state:string, last_tick:?string, last_tick_human:?string, overdue_count:int}
     */
    protected function statusPayload(array $status): array
    {
        return [
            'state'           => $status['state'],
            'last_tick'       => $status['last_tick']?->format('M j, H:i'),
            'last_tick_human' => $status['last_tick']?->diffForHumans(),
            'overdue_count'   => (int) $status['overdue_count'],
        ];
    }

    /**
     * Per-key live display fields for the panel's polling loop. "Running" is
     * the scheduler's overlap mutex OR an unfinished manual run-history row;
     * unfinished rows older than 15 minutes are ignored so an orphaned row
     * (e.g. a killed background runner) can't keep the panel polling forever.
     *
     * @param  array<int, array<string, mixed>>  $jobs  output of CronJobsInspector::jobs()
     * @return array<string, array<string, mixed>>
     */
    protected function liveMap(array $jobs): array
    {
        $runningKeys = [];

        try {
            $runningKeys = ScheduledJobRun::query()
                ->whereNull('finished_at')
                ->where('status', ScheduledJobRun::STATUS_RUNNING)
                ->where('started_at', '>=', now()->subMinutes(15))
                ->pluck('job_key')
                ->all();
        } catch (\Throwable $e) {
            // Table may not exist yet (pre-migration); mutex-only detection.
        }

        $map = [];

        foreach ($jobs as $job) {
            if (empty($job['key'])) {
                continue;
            }

            $map[$job['key']] = [
                'running_now'     => (bool) $job['running_now'] || in_array($job['key'], $runningKeys, true),
                'paused'          => (bool) $job['paused'],
                'overdue'         => (bool) $job['overdue'],
                'last_run'        => $job['last_run']?->format('M j, H:i'),
                'last_run_human'  => $job['last_run']?->diffForHumans(),
                'last_run_ok'     => $job['last_run_ok'],
                'last_runtime'    => $job['last_runtime'],
                'last_exit_code'  => $job['last_exit_code'],
                'last_run_error'  => $job['last_run_error'],
                'last_run_source' => $job['last_run_source'],
            ];
        }

        return $map;
    }

    /** Mute failure/recovery alerts for one job (noisy or experimental). */
    public function muteAlerts(string $key)
    {
        $error = $this->guardKey($key);
        if ($error !== null) {
            return back()->with('error', $error);
        }

        ScheduledJobHealthAlerts::muteJob($key);

        return back()->with('success', "Alerts muted for '{$key}'. It still runs on schedule, but failures will no longer notify ops admins.");
    }

    /** Re-enable failure/recovery alerts for a muted job. */
    public function unmuteAlerts(string $key)
    {
        $error = $this->guardKey($key);
        if ($error !== null) {
            return back()->with('error', $error);
        }

        ScheduledJobHealthAlerts::unmuteJob($key);

        return back()->with('success', "Alerts re-enabled for '{$key}'. A new failure streak will notify ops admins again.");
    }

    /** Pause a job: the scheduler will skip it until resumed. */
    public function pause(string $key)
    {
        $error = $this->guardKey($key, forPause: true);
        if ($error !== null) {
            return back()->with('error', $error);
        }

        ScheduledJobRegistry::pause($key);

        return back()->with('success', "Paused '{$key}'. The scheduler will skip it until you resume it.");
    }

    /** Resume a previously paused job. */
    public function resume(string $key)
    {
        $error = $this->guardKey($key);
        if ($error !== null) {
            return back()->with('error', $error);
        }

        ScheduledJobRegistry::resume($key);

        return back()->with('success', "Resumed '{$key}'. It will run again at its next scheduled time.");
    }

    /**
     * Run a job now, outside its cadence, without blocking the request:
     * spawns `php artisan scheduled-jobs:run <key>` in the background. The
     * command records a `manual` run-history row, so the outcome appears in
     * the job's history shortly after it finishes.
     */
    public function run(string $key)
    {
        $error = $this->guardKey($key);
        if ($error !== null) {
            return back()->with('error', $error);
        }

        $this->spawnRun($key);

        // `ran_job` tells the reloaded page to start a short polling watch
        // window even before the background run's history row appears.
        return back()
            ->with('success', "Started '{$key}' in the background. This page will update automatically when it finishes.")
            ->with('ran_job', $key);
    }

    /** Recent run history for one job (JSON, feeds the history drawer). */
    public function runs(string $key)
    {
        $error = $this->guardKey($key);
        if ($error !== null) {
            return response()->json(['error' => ['message' => $error]], 404);
        }

        $runs = ScheduledJobRun::where('job_key', $key)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (ScheduledJobRun $run) => $run->toDisplayArray())
            ->values();

        return response()->json(['data' => ['job_key' => $key, 'runs' => $runs]]);
    }

    /** Validate a job key (and, for pause, that the job is pausable). */
    protected function guardKey(string $key, bool $forPause = false): ?string
    {
        $def = ScheduledJobRegistry::find($key);

        if ($def === null) {
            return "Unknown scheduled job '{$key}'.";
        }

        if ($forPause && ! empty($def['protected'])) {
            return "'{$key}' is protected — pausing it could break billing, data integrity or platform health, so it cannot be paused.";
        }

        return null;
    }

    /**
     * Fire-and-forget spawn of the manual runner. In tests the spawn is
     * executed synchronously in-process so assertions can observe the result
     * without racing a detached process.
     */
    protected function spawnRun(string $key): void
    {
        if (app()->runningUnitTests()) {
            Artisan::call('scheduled-jobs:run', ['key' => $key]);

            return;
        }

        // escapeshellarg guards the key even though guardKey() already
        // restricted it to known registry keys.
        $command = sprintf(
            'nohup %s artisan scheduled-jobs:run %s >> /dev/null 2>&1 &',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($key),
        );

        Process::fromShellCommandline($command, base_path())
            ->setTimeout(5)
            ->run();
    }
}
