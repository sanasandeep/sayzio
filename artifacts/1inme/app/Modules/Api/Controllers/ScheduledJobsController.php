<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Admin\Models\ScheduledJobRun;
use App\Modules\Admin\Support\CronJobsInspector;
use App\Modules\Admin\Support\ScheduledJobRegistry;
use App\Modules\Api\Controllers\Concerns\ApiResponses;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

/**
 * Bearer-token parity for the web admin "Scheduled Jobs" control panel
 * ({@see \App\Modules\Admin\Controllers\CronJobsController}) so a platform
 * admin can review, pause/resume and run scheduled jobs from the Sayzio
 * Mobile app. Both surfaces share the SAME engine (ScheduledJobRegistry +
 * CronJobsInspector), so the two views never drift apart:
 *
 *   GET  /api/v1/admin/scheduled-jobs                (grouped list + scheduler status)
 *   POST /api/v1/admin/scheduled-jobs/{key}/pause    (persisted; protected jobs 422)
 *   POST /api/v1/admin/scheduled-jobs/{key}/resume
 *   POST /api/v1/admin/scheduled-jobs/{key}/run      (background run-now)
 *   GET  /api/v1/admin/scheduled-jobs/{key}/runs     (recent run history)
 *
 * All endpoints are gated behind the same `settings.manage` permission the
 * web routes use; a regular sanctum token is rejected with 403.
 */
class ScheduledJobsController extends Controller
{
    use ApiResponses;

    public function index(Request $request, CronJobsInspector $inspector)
    {
        if ($forbidden = $this->authorizeAdmin($request)) {
            return $forbidden;
        }

        $jobs = $inspector->jobs();
        $status = $inspector->schedulerStatus($jobs);

        $groups = [];
        foreach (ScheduledJobRegistry::GROUPS as $slug => $label) {
            $groups[$slug] = ['slug' => $slug, 'label' => $label, 'jobs' => []];
        }
        $groups['other'] = ['slug' => 'other', 'label' => 'Other', 'jobs' => []];

        foreach ($jobs as $job) {
            $groups[$job['group'] ?? 'other']['jobs'][] = $this->jobPayload($job);
        }

        return $this->ok([
            'master_cron_line' => $inspector->masterCronLine(),
            'scheduler'        => [
                'state'         => $status['state'],
                'last_tick'     => $status['last_tick']?->toIso8601String(),
                'overdue_count' => $status['overdue_count'],
            ],
            'groups' => array_values(array_filter($groups, fn ($g) => $g['jobs'] !== [])),
        ]);
    }

    public function pause(Request $request, string $key)
    {
        if ($forbidden = $this->authorizeAdmin($request)) {
            return $forbidden;
        }

        $def = ScheduledJobRegistry::find($key);
        if ($def === null) {
            return $this->notFound("Unknown scheduled job '{$key}'.");
        }

        if (! empty($def['protected'])) {
            return $this->fail(
                "'{$key}' is protected — pausing it could break billing, data integrity or platform health.",
                422,
                'job_protected',
            );
        }

        ScheduledJobRegistry::pause($key);

        return $this->ok(['job_key' => $key, 'paused' => true]);
    }

    public function resume(Request $request, string $key)
    {
        if ($forbidden = $this->authorizeAdmin($request)) {
            return $forbidden;
        }

        if (ScheduledJobRegistry::find($key) === null) {
            return $this->notFound("Unknown scheduled job '{$key}'.");
        }

        ScheduledJobRegistry::resume($key);

        return $this->ok(['job_key' => $key, 'paused' => false]);
    }

    /** Run a job now, in the background, outside its cadence. */
    public function run(Request $request, string $key)
    {
        if ($forbidden = $this->authorizeAdmin($request)) {
            return $forbidden;
        }

        if (ScheduledJobRegistry::find($key) === null) {
            return $this->notFound("Unknown scheduled job '{$key}'.");
        }

        $this->spawnRun($key);

        return $this->ok([
            'job_key' => $key,
            'started' => true,
            'message' => 'Job started in the background; check its run history shortly for the result.',
        ]);
    }

    /** Recent run history for one job. */
    public function runs(Request $request, string $key)
    {
        if ($forbidden = $this->authorizeAdmin($request)) {
            return $forbidden;
        }

        if (ScheduledJobRegistry::find($key) === null) {
            return $this->notFound("Unknown scheduled job '{$key}'.");
        }

        $runs = ScheduledJobRun::where('job_key', $key)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (ScheduledJobRun $run) => $run->toDisplayArray())
            ->values();

        return $this->ok(['job_key' => $key, 'runs' => $runs]);
    }

    protected function authorizeAdmin(Request $request)
    {
        if (! $request->user()->hasPermission('settings.manage')) {
            return $this->forbidden('You are not allowed to manage scheduled jobs.');
        }

        return null;
    }

    /**
     * Mirrors CronJobsController::spawnRun on the web side: fire-and-forget
     * in production, synchronous in-process during tests.
     */
    protected function spawnRun(string $key): void
    {
        if (app()->runningUnitTests()) {
            Artisan::call('scheduled-jobs:run', ['key' => $key]);

            return;
        }

        $command = sprintf(
            'nohup %s artisan scheduled-jobs:run %s >> /dev/null 2>&1 &',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($key),
        );

        Process::fromShellCommandline($command, base_path())
            ->setTimeout(5)
            ->run();
    }

    /**
     * @param array<string, mixed> $job
     * @return array<string, mixed>
     */
    private function jobPayload(array $job): array
    {
        $iso = fn ($v) => $v instanceof Carbon ? $v->toIso8601String() : null;

        return [
            'key'                 => $job['key'],
            'group'               => $job['group'],
            'protected'           => (bool) $job['protected'],
            'paused'              => (bool) $job['paused'],
            'is_callback'         => (bool) $job['is_callback'],
            'command'             => $job['command'],
            'manual_command'      => $job['manual_command'],
            'expression'          => $job['expression'],
            'frequency'           => $job['frequency'],
            'purpose'             => $job['purpose'],
            'next_run'            => $iso($job['next_run'] ?? null),
            'last_run'            => $iso($job['last_run'] ?? null),
            'last_run_ok'         => $job['last_run_ok'],
            'last_run_error'      => $job['last_run_error'],
            'last_runtime'        => $job['last_runtime'],
            'last_exit_code'      => $job['last_exit_code'],
            'last_run_source'     => $job['last_run_source'],
            'overdue'             => (bool) $job['overdue'],
            'failing_streak'      => (int) ($job['failing_streak'] ?? 0),
            'failing_repeatedly'  => (bool) ($job['failing_repeatedly'] ?? false),
            'without_overlapping' => (bool) $job['without_overlapping'],
            'on_one_server'       => (bool) $job['on_one_server'],
            'running_now'         => (bool) $job['running_now'],
        ];
    }
}
