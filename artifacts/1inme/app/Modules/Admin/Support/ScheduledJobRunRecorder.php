<?php

namespace App\Modules\Admin\Support;

use App\Modules\Admin\Models\ScheduledJobRun;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Persists a DB run-history row for every scheduled job execution:
 * insert-on-start (ScheduledTaskStarting), update-on-finish/fail
 * (ScheduledTaskFinished / ScheduledTaskFailed). Complements the
 * cache-based CronRunLog heartbeat (which stays authoritative for
 * "is the scheduler alive at all") with durable per-run duration,
 * exit code and error detail for the admin Scheduled Jobs panel.
 *
 * Registered as a singleton so the open-run id survives from the
 * Starting listener to the Finished listener within one schedule:run
 * process. Wholly best-effort: every DB touch is wrapped so recording
 * can never break a scheduled run.
 */
class ScheduledJobRunRecorder
{
    /** @var array<string, int> job key => in-flight run row id */
    protected array $open = [];

    /**
     * Keys whose finish has already been recorded (either by ScheduledTaskFailed
     * or ScheduledTaskFinished, whichever fired first). When an exception-throwing
     * job exits, Laravel dispatches both ScheduledTaskFailed (immediately) AND
     * ScheduledTaskFinished (after the process returns) — without this guard the
     * second call hits the "no open row" fallback path and inserts a duplicate row.
     *
     * @var array<string, true>
     */
    protected array $closed = [];

    /**
     * Temp output files created in the ScheduledTaskStarting listener so the
     * Finished/Failed listeners can read the actual command output and include
     * it in the run-row error column (rather than just "Exited with code N").
     *
     * @var array<string, string> job key => file path
     */
    protected array $outputFiles = [];

    /**
     * Register a temp output file for the given job key. Called from the
     * ScheduledTaskStarting listener after redirecting the event's output so
     * the Finished/Failed listener can read it and surface real error text.
     */
    public function setOutputFile(string $key, string $path): void
    {
        $this->outputFiles[$key] = $path;
    }

    /**
     * Read the tail of the output file captured for the given job key (if
     * any), then clean up the temp file. Returns null when nothing was
     * captured. The tail is stripped of shell color escape codes since the
     * scheduler captures raw terminal output.
     */
    public function readOutputTail(string $key, int $maxBytes = 800): ?string
    {
        $path = $this->outputFiles[$key] ?? null;
        unset($this->outputFiles[$key]);

        if ($path === null || !is_readable($path)) {
            return null;
        }

        try {
            $content = file_get_contents($path);
        } catch (\Throwable $e) {
            return null;
        } finally {
            @unlink($path);
        }

        if ($content === false || trim($content) === '') {
            return null;
        }

        // Strip ANSI escape codes that contaminate shell-captured output.
        $clean = (string) preg_replace('/\x1b\[[0-9;]*m/', '', $content);

        return trim(substr($clean, -$maxBytes)) ?: null;
    }

    public function starting(Event $event): void
    {
        try {
            $key = $this->keyFor($event);
            if ($key === null) {
                return;
            }

            $run = ScheduledJobRun::create([
                'job_key'    => $key,
                'source'     => ScheduledJobRun::SOURCE_SCHEDULE,
                'status'     => ScheduledJobRun::STATUS_RUNNING,
                'started_at' => Carbon::now(),
            ]);

            $this->open[$key] = $run->id;
        } catch (\Throwable $e) {
            // Best-effort observability — never break the scheduler.
        }
    }

    public function finished(Event $event, bool $ok, ?float $runtime = null, ?string $error = null, ?int $exitCode = null): void
    {
        try {
            $key = $this->keyFor($event);
            if ($key === null) {
                return;
            }

            // Deduplication: when a job exits with an exception Laravel fires
            // both ScheduledTaskFailed (immediately) and ScheduledTaskFinished
            // (after the process returns). Without this guard the second call
            // consumes the null open-run fallback path and inserts a duplicate
            // row. The first event to arrive wins; the second is a no-op.
            if (isset($this->closed[$key])) {
                return;
            }

            $attrs = [
                'status'      => $ok ? ScheduledJobRun::STATUS_SUCCESS : ScheduledJobRun::STATUS_FAILED,
                'finished_at' => Carbon::now(),
                'runtime'     => $runtime,
                'exit_code'   => $exitCode,
                'error'       => $error !== null ? Str::limit($error, 1000) : null,
            ];

            $id = $this->open[$key] ?? null;
            unset($this->open[$key]);

            if ($id !== null && ScheduledJobRun::whereKey($id)->update($attrs) > 0) {
                $this->closed[$key] = true;
                $this->maybePrune($key);
                $this->alert($key, $ok, $error, $exitCode);

                return;
            }

            // No open row (e.g. a Failed event on a run whose Starting insert
            // itself failed) — record a complete row so the failure is visible.
            ScheduledJobRun::create($attrs + [
                'job_key'    => $key,
                'source'     => ScheduledJobRun::SOURCE_SCHEDULE,
                'started_at' => $runtime !== null
                    ? Carbon::now()->subSeconds((int) ceil($runtime))
                    : Carbon::now(),
            ]);

            $this->closed[$key] = true;
            $this->maybePrune($key);
            $this->alert($key, $ok, $error, $exitCode);
        } catch (\Throwable $e) {
            // Swallow — see class docblock.
        }
    }

    /**
     * Alert ops admins on a failed run / all-clear on recovery. Streak-based
     * dedup lives in ScheduledJobHealthAlerts; wholly best-effort.
     */
    protected function alert(string $key, bool $ok, ?string $error, ?int $exitCode): void
    {
        try {
            ScheduledJobHealthAlerts::jobFinished($key, $ok, $error, $exitCode, 'schedule');
        } catch (\Throwable $e) {
            // Alerting must never break the scheduler.
        }
    }

    /**
     * Resolve a live scheduler event back to its registry key using the same
     * normalization the inspector uses: strip the php binary + artisan prefix
     * for command events; use the scheduled name for callback events.
     */
    public function keyFor(Event $event): ?string
    {
        try {
            $lookup = $event instanceof CallbackEvent
                ? (string) $event->description
                : trim((string) preg_replace(
                    '/^php\s+artisan\s+/',
                    '',
                    Event::normalizeCommand($event->command ?? '')
                ));

            return ScheduledJobRegistry::commandKeyMap()[$lookup] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Lottery prune (~2% of finishes): opportunistic safety valve applying
     * the shared retention policy (ScheduledJobRunPruner — keep the last
     * 30 days or the newest 200 rows per job, whichever is larger) so
     * high-frequency jobs stay bounded even if the daily
     * `scheduled-runs:prune` sweep is paused or missed.
     */
    protected function maybePrune(string $key): void
    {
        try {
            if (random_int(1, 50) !== 1) {
                return;
            }

            ScheduledJobRunPruner::pruneJob($key);
        } catch (\Throwable $e) {
            // Pruning is opportunistic.
        }
    }
}
