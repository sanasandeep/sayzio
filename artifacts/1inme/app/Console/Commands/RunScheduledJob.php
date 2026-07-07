<?php

namespace App\Console\Commands;

use App\Modules\Admin\Models\ScheduledJobRun;
use App\Modules\Admin\Support\ScheduledJobRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/**
 * Run one registered scheduled job immediately, outside its cadence, and
 * record a `manual` run-history row (status / runtime / exit code) so the
 * admin Scheduled Jobs panel shows run-now executions alongside scheduled
 * ones. This is what the panel's "Run now" button spawns in the background.
 *
 * Deliberately ignores the pause switch: an operator running a job by hand
 * wants it to run, paused or not. Artisan::call() fires no ScheduledTask*
 * events, so the scheduler-side recorder never double-records these runs.
 */
class RunScheduledJob extends Command
{
    protected $signature = 'scheduled-jobs:run {key : The registry key of the scheduled job (e.g. contacts:sync)}';

    protected $description = 'Run a registered scheduled job immediately and record a manual run-history row';

    public function handle(): int
    {
        $key = (string) $this->argument('key');
        $def = ScheduledJobRegistry::find($key);

        if ($def === null) {
            $this->error("Unknown scheduled job '{$key}'. Known keys: " . implode(', ', ScheduledJobRegistry::keys()));

            return self::FAILURE;
        }

        $run = null;

        try {
            $run = ScheduledJobRun::create([
                'job_key'    => $key,
                'source'     => ScheduledJobRun::SOURCE_MANUAL,
                'status'     => ScheduledJobRun::STATUS_RUNNING,
                'started_at' => Carbon::now(),
            ]);
        } catch (\Throwable $e) {
            // Recording is best-effort; still run the job.
        }

        $startedAt = microtime(true);

        try {
            if (isset($def['callback'])) {
                [$class, $method] = explode('@', $def['callback'], 2);
                app($class)->{$method}();
                $exit = 0;
            } else {
                $exit = Artisan::call($def['command'], [], $this->getOutput());
            }
        } catch (\Throwable $e) {
            $this->finish($run, false, microtime(true) - $startedAt, null, Str::limit($e->getMessage(), 1000));
            $this->error("Job '{$key}' threw: " . $e->getMessage());

            return self::FAILURE;
        }

        $ok = (int) $exit === 0;
        $this->finish($run, $ok, microtime(true) - $startedAt, (int) $exit, $ok ? null : 'Exited with code ' . (int) $exit);

        $this->{$ok ? 'info' : 'error'}(
            "Job '{$key}' finished with exit code " . (int) $exit . '.'
        );

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    protected function finish(?ScheduledJobRun $run, bool $ok, float $runtime, ?int $exitCode, ?string $error): void
    {
        // Manual runs count toward (and close) failure-alert streaks too:
        // a failing job an operator re-runs by hand shouldn't double-alert,
        // and a successful manual re-run sends the all-clear immediately.
        try {
            \App\Modules\Admin\Support\ScheduledJobHealthAlerts::jobFinished(
                (string) $this->argument('key'),
                $ok,
                $error,
                $exitCode,
                'manual',
            );
        } catch (\Throwable $e) {
            // Alerting is best-effort.
        }

        if ($run === null) {
            return;
        }

        try {
            $run->update([
                'status'      => $ok ? ScheduledJobRun::STATUS_SUCCESS : ScheduledJobRun::STATUS_FAILED,
                'finished_at' => Carbon::now(),
                'runtime'     => round($runtime, 3),
                'exit_code'   => $exitCode,
                'error'       => $error,
            ]);
        } catch (\Throwable $e) {
            // Best-effort.
        }
    }
}
