<?php

namespace App\Modules\Admin\Support;

use App\Modules\Admin\Models\ScheduledJobRun;
use Cron\CronExpression;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

/**
 * Derives the list of scheduled jobs live from Laravel's registered schedule
 * (routes/console.php, itself driven by ScheduledJobRegistry) so the admin
 * "Scheduled Jobs" panel never has to be maintained in a second place. For
 * each event it extracts the registry key/group, the artisan command, the
 * cron expression, a plain-English frequency, the purpose, pause/protected
 * state, the next due time and last-run detail.
 *
 * Purpose text is sourced, in order of preference, from: (1) the registry
 * definition's description (the single source — routes/console.php attaches
 * it to command events too), then (2) a description explicitly attached to
 * the scheduled event, then (3) the registered artisan command's own
 * description.
 *
 * Last-run detail merges two recorders: the DB run history
 * (ScheduledJobRun — durable, covers manual run-now executions) and the
 * cache-based CronRunLog (scheduler heartbeat + pre-migration continuity).
 * Whichever recorded the more recent run wins.
 */
class CronJobsInspector
{
    public function __construct(
        protected CronRunLog $runLog,
        protected ScheduledJobRunRecorder $recorder,
    ) {
    }

    /**
     * Build the structured list of scheduled jobs.
     *
     * @return array<int, array<string, mixed>>
     */
    public function jobs(): array
    {
        /** @var Schedule $schedule */
        $schedule = app(Schedule::class);

        $descriptions = $this->commandDescriptions();

        $events = $schedule->events();

        // Pull every recorded last-run state in a single cache round-trip,
        // keyed by the same mutex name the runtime listener writes under.
        $runs = $this->runLog->many(array_map(
            fn ($event) => $this->runLog->key($event),
            $events
        ));

        // Latest *finished* DB history row per job (single query). Durable and
        // covers manual run-now executions, unlike the cache log.
        $dbRuns = $this->latestDbRuns();

        // Consecutive-failure streak per job (single query) — powers the
        // "failing repeatedly" badge. Computed live from the run history so
        // the badge clears the moment the job succeeds again, without waiting
        // for the hourly watchdog to update its episode state.
        $streaks = $this->failureStreaks();

        $pausedKeys = ScheduledJobRegistry::pausedKeys();

        $jobs = [];

        foreach ($events as $event) {
            $isCallback = $event instanceof CallbackEvent;

            $key = $this->recorder->keyFor($event);
            $def = $key !== null ? ScheduledJobRegistry::find($key) : null;

            $artisanName = $isCallback ? null : $this->artisanName($event);
            $cleanCommand = $isCallback
                ? $this->callbackLabel($event)
                : Event::normalizeCommand($event->command ?? '');

            // The portion after "php artisan " — i.e. command + its args. Shown
            // in full (with flags) so argumented schedules aren't ambiguous.
            $commandWithArgs = $isCallback
                ? $cleanCommand
                : trim(preg_replace('/^php\s+artisan\s+/', '', $cleanCommand));

            $manualCommand = null;
            if (! $isCallback && $artisanName !== null) {
                $manualCommand = 'php artisan ' . $commandWithArgs;
            }

            $expression = $event->expression;

            $nextRun = $this->nextRun($event);
            $prevRun = $this->prevRun($event);

            // The cron cadence in seconds (gap between two scheduled fires),
            // used to decide whether a recorded run is stale.
            $interval = ($nextRun !== null && $prevRun !== null)
                ? max(0, $nextRun->getTimestamp() - $prevRun->getTimestamp())
                : null;

            $run = $runs[$this->runLog->key($event)] ?? null;

            $lastRun = ($run !== null && isset($run['ran_at']))
                ? Carbon::createFromTimestamp((int) $run['ran_at'])->setTimezone(\App\Support\PlatformTimezone::platformDefault())
                : null;

            $lastOk = (is_array($run) && array_key_exists('ok', $run) && is_bool($run['ok'])) ? $run['ok'] : null;

            $lastError   = (is_array($run) && is_string($run['error'] ?? null)) ? $run['error'] : null;
            $lastRuntime = (is_array($run) && is_numeric($run['runtime'] ?? null)) ? (float) $run['runtime'] : null;
            $lastExit    = null;
            $lastSource  = $lastRun !== null ? 'schedule' : null;

            // Prefer the DB history row when it recorded a more recent run
            // (e.g. a manual run-now, or simply post-migration operation).
            $dbRun = $key !== null ? ($dbRuns[$key] ?? null) : null;
            if ($dbRun !== null && ($lastRun === null || $dbRun->started_at->greaterThan($lastRun))) {
                $lastRun = $dbRun->started_at->copy()->setTimezone(\App\Support\PlatformTimezone::platformDefault());
                $lastOk = $dbRun->status === ScheduledJobRun::STATUS_SUCCESS;
                $lastError = $dbRun->error;
                $lastRuntime = $dbRun->runtime;
                $lastExit = $dbRun->exit_code;
                $lastSource = $dbRun->source;
            }

            // Overdue = we have evidence the job ran before, but its most recent
            // recorded run is more than one whole interval older than the most
            // recent scheduled fire time — i.e. the scheduler missed at least one
            // cycle. A previously-working-then-dead scheduler is exactly this.
            $overdue = $lastRun !== null && $prevRun !== null && $interval !== null && $interval > 0
                && ($prevRun->getTimestamp() - $lastRun->getTimestamp()) > $interval;

            $group = is_array($def) ? ($def['group'] ?? null) : null;

            // Consecutive failed runs since the job's last success. Mirrors
            // CheckScheduledJobFailures::consecutiveFailures() (the watchdog
            // that alerts ops admins); the badge threshold is shared so the
            // panel and the alerts always agree on "failing repeatedly".
            $failingStreak = $key !== null ? ($streaks[$key] ?? 0) : 0;

            $jobs[] = [
                'key'                 => $key,
                'group'               => $group,
                'group_label'         => $group !== null ? (ScheduledJobRegistry::GROUPS[$group] ?? $group) : null,
                'protected'           => is_array($def) && ! empty($def['protected']),
                'paused'              => $key !== null && in_array($key, $pausedKeys, true),
                'is_callback'         => $isCallback,
                'command'             => $commandWithArgs,
                'manual_command'      => $manualCommand,
                'expression'          => $expression,
                'frequency'           => $this->humanFrequency($expression),
                'purpose'             => $this->purposeFor($event, $artisanName, $descriptions, $def),
                'next_run'            => $nextRun,
                'interval_seconds'    => $interval,
                'last_run'            => $lastRun,
                'last_run_ok'         => $lastOk,
                'last_run_error'      => $lastError,
                'last_runtime'        => $lastRuntime,
                'last_exit_code'      => $lastExit,
                'last_run_source'     => $lastSource,
                'never_run'           => $lastRun === null,
                'overdue'             => $overdue,
                'failing_streak'      => $failingStreak,
                'failing_repeatedly'  => $failingStreak >= \App\Console\Commands\CheckScheduledJobFailures::FAILURE_THRESHOLD,
                'without_overlapping' => (bool) $event->withoutOverlapping,
                'on_one_server'       => (bool) $event->onOneServer,
                'running_now'         => $this->isRunning($event),
            ];
        }

        return $jobs;
    }

    /**
     * Overall scheduler health, derived from the global heartbeat that every
     * scheduled run bumps (see CronRunLog). Because at least one every-minute job
     * always fires, a fresh heartbeat is the most reliable signal that
     * `schedule:run` is actually wired into the server crontab.
     *
     * @param  array<int, array<string, mixed>>  $jobs  the output of jobs()
     * @return array{state:string, last_tick:?Carbon, overdue_count:int}
     */
    public function schedulerStatus(array $jobs): array
    {
        $tick = $this->runLog->lastTick();

        $overdueCount = count(array_filter($jobs, fn ($j) => ! empty($j['overdue'])));

        if ($tick === null) {
            // Nothing has ever run: either the crontab isn't configured, or the
            // app was only just deployed and the first minute hasn't elapsed.
            return ['state' => 'unknown', 'last_tick' => null, 'overdue_count' => $overdueCount];
        }

        // Healthy threshold scales off the shortest registered cadence (an
        // every-minute job ⇒ ~60s), with generous grace so a slightly delayed
        // tick doesn't flap the warning.
        $intervals = array_filter(
            array_map(fn ($j) => $j['interval_seconds'] ?? null, $jobs),
            fn ($v) => is_int($v) && $v > 0
        );
        $minInterval = $intervals === [] ? 60 : min($intervals);
        $threshold = ($minInterval * 2) + 60;

        $age = Carbon::now()->getTimestamp() - $tick->getTimestamp();

        return [
            'state'         => $age <= $threshold ? 'healthy' : 'stale',
            'last_tick'     => $tick->setTimezone(\App\Support\PlatformTimezone::platformDefault()),
            'overdue_count' => $overdueCount,
        ];
    }

    /**
     * The single master cron line an operator must add to the server crontab.
     */
    public function masterCronLine(): string
    {
        return '* * * * * cd ' . base_path() . ' && php artisan schedule:run >> /dev/null 2>&1';
    }

    /**
     * Extract the bare artisan command name (first token) from an event.
     */
    protected function artisanName(Event $event): ?string
    {
        $command = Event::normalizeCommand($event->command ?? '');
        $command = trim(preg_replace('/^php\s+artisan\s+/', '', $command));

        if ($command === '') {
            return null;
        }

        return explode(' ', $command)[0];
    }

    protected function callbackLabel(CallbackEvent $event): string
    {
        $summary = $event->getSummaryForDisplay();

        if (in_array($summary, ['Closure', 'Callback'], true)) {
            return $event->description ?: 'Scheduled closure';
        }

        return $summary;
    }

    /**
     * Resolve a plain-English purpose for the event. The registry definition
     * is the single source of truth; the event/command descriptions are only
     * fallbacks for anything scheduled outside the registry.
     */
    protected function purposeFor(Event $event, ?string $artisanName, array $descriptions, ?array $def = null): string
    {
        if (is_array($def) && ! empty($def['description'])) {
            return $def['description'];
        }

        if (! empty($event->description)) {
            return $event->description;
        }

        if ($artisanName !== null && ! empty($descriptions[$artisanName])) {
            return $descriptions[$artisanName];
        }

        return '—';
    }

    /**
     * Latest finished DB run-history row per job key, in one query.
     *
     * @return array<string, ScheduledJobRun>
     */
    protected function latestDbRuns(): array
    {
        try {
            return ScheduledJobRun::query()
                ->whereIn('id', ScheduledJobRun::query()
                    ->selectRaw('max(id)')
                    ->whereNotNull('finished_at')
                    ->groupBy('job_key'))
                ->get()
                ->keyBy('job_key')
                ->all();
        } catch (\Throwable $e) {
            // Table may not exist yet (pre-migration); degrade to cache-only.
            return [];
        }
    }

    /**
     * Consecutive-failure streak per job key — the number of failed runs
     * since each job's last successful run — in one query. Running rows are
     * ignored (they are neither success nor failure), so an in-flight retry
     * never masks or inflates a streak. Jobs with no streak are absent.
     *
     * @return array<string, int>
     */
    protected function failureStreaks(): array
    {
        try {
            $table = (new ScheduledJobRun)->getTable();

            $lastSuccess = ScheduledJobRun::query()
                ->selectRaw('job_key, max(started_at) as last_success_at')
                ->where('status', ScheduledJobRun::STATUS_SUCCESS)
                ->groupBy('job_key');

            return ScheduledJobRun::query()
                ->from($table . ' as f')
                ->leftJoinSub($lastSuccess, 's', 's.job_key', '=', 'f.job_key')
                ->where('f.status', ScheduledJobRun::STATUS_FAILED)
                ->where(function ($q) {
                    $q->whereNull('s.last_success_at')
                        ->orWhereColumn('f.started_at', '>', 's.last_success_at');
                })
                ->groupBy('f.job_key')
                ->selectRaw('f.job_key as job_key, count(*) as streak')
                ->pluck('streak', 'job_key')
                ->map(fn ($v) => (int) $v)
                ->all();
        } catch (\Throwable $e) {
            // Table may not exist yet (pre-migration); degrade gracefully.
            return [];
        }
    }

    /**
     * Map of registered artisan command name => its own description.
     *
     * @return array<string, string>
     */
    protected function commandDescriptions(): array
    {
        $map = [];

        foreach (Artisan::all() as $name => $command) {
            $map[$name] = $command->getDescription();
        }

        return $map;
    }

    /**
     * Compute the next due date for an event in the app timezone.
     */
    protected function nextRun(Event $event): ?Carbon
    {
        try {
            $tz = $event->timezone ?: \App\Support\PlatformTimezone::platformDefault();

            return Carbon::instance(
                (new CronExpression($event->expression))
                    ->getNextRunDate(Carbon::now()->setTimezone($tz))
            )->setTimezone(\App\Support\PlatformTimezone::platformDefault());
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Compute the most recent *past* scheduled fire time for an event in the app
     * timezone. Used to judge whether a recorded run is stale (the scheduler is
     * expected to have run the job at, or just after, this time).
     */
    protected function prevRun(Event $event): ?Carbon
    {
        try {
            $tz = $event->timezone ?: \App\Support\PlatformTimezone::platformDefault();

            return Carbon::instance(
                (new CronExpression($event->expression))
                    ->getPreviousRunDate(Carbon::now()->setTimezone($tz))
            )->setTimezone(\App\Support\PlatformTimezone::platformDefault());
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function isRunning(Event $event): bool
    {
        try {
            return $event->withoutOverlapping && $event->mutex->exists($event);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Translate a 5-field cron expression into a plain-English frequency.
     */
    public function humanFrequency(string $expression): string
    {
        $parts = preg_split('/\s+/', trim($expression));

        if (count($parts) !== 5) {
            return $expression;
        }

        [$min, $hour, $dom, $month, $dow] = $parts;

        // Every minute.
        if ($expression === '* * * * *') {
            return 'Every minute';
        }

        // Every N minutes — "*/N * * * *".
        if (preg_match('#^\*/(\d+)$#', $min, $m) && $hour === '*' && $dom === '*' && $month === '*' && $dow === '*') {
            return 'Every ' . $m[1] . ' minutes';
        }

        // Every N hours — "M */N * * *".
        if (ctype_digit($min) && preg_match('#^\*/(\d+)$#', $hour, $m) && $dom === '*' && $month === '*' && $dow === '*') {
            return 'Every ' . $m[1] . ' hours' . $this->atMinuteSuffix($min);
        }

        // Hourly — "M * * * *".
        if (ctype_digit($min) && $hour === '*' && $dom === '*' && $month === '*' && $dow === '*') {
            return 'Hourly' . $this->atMinuteSuffix($min);
        }

        // Monthly on a given day — "M H D * *".
        if (ctype_digit($min) && ctype_digit($hour) && ctype_digit($dom) && $month === '*' && $dow === '*') {
            return 'Monthly on day ' . $dom . ' at ' . $this->time($hour, $min);
        }

        // Weekly on a given weekday — "M H * * W".
        if (ctype_digit($min) && ctype_digit($hour) && $dom === '*' && $month === '*' && ctype_digit($dow)) {
            return 'Weekly on ' . $this->weekday($dow) . ' at ' . $this->time($hour, $min);
        }

        // Daily — "M H * * *".
        if (ctype_digit($min) && ctype_digit($hour) && $dom === '*' && $month === '*' && $dow === '*') {
            return 'Daily at ' . $this->time($hour, $min);
        }

        return $expression;
    }

    protected function atMinuteSuffix(string $min): string
    {
        $m = (int) $min;

        return $m === 0 ? '' : ' (at :' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . ')';
    }

    protected function time(string $hour, string $min): string
    {
        return str_pad((string) (int) $hour, 2, '0', STR_PAD_LEFT)
            . ':' . str_pad((string) (int) $min, 2, '0', STR_PAD_LEFT)
            . ' ' . \App\Support\PlatformTimezone::platformDefault();
    }

    protected function weekday(string $dow): string
    {
        $days = [
            '0' => 'Sunday', '1' => 'Monday', '2' => 'Tuesday', '3' => 'Wednesday',
            '4' => 'Thursday', '5' => 'Friday', '6' => 'Saturday', '7' => 'Sunday',
        ];

        return $days[$dow] ?? ('weekday ' . $dow);
    }
}
