<?php

namespace App\Modules\Admin\Support;

use Cron\CronExpression;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

/**
 * Derives the list of scheduled jobs live from Laravel's registered schedule
 * (routes/console.php) so the admin "Cron Jobs" reference page never has to be
 * maintained in a second place. For each event it extracts the artisan command,
 * the cron expression, a plain-English frequency, a short purpose, and the next
 * due time.
 *
 * Purpose text is sourced, in order of preference, from: (1) a description
 * explicitly attached to the scheduled event (->description()/->name()), then
 * (2) the registered artisan command's own description, then (3) a centralized
 * override map below for the handful of commands whose built-in description is
 * too terse to be operator-friendly.
 */
class CronJobsInspector
{
    public function __construct(protected CronRunLog $runLog)
    {
    }

    /**
     * Plain-English purpose overrides keyed by artisan command name. Only used
     * when the event/command does not already carry a clearer description.
     */
    protected const PURPOSE_OVERRIDES = [
        'tasks:send-due-reminders'              => 'Email task assignees about cards due today or overdue (delivered at 8 AM in each workspace\'s own timezone).',
        'biolink:promote-experiment-winners'    => 'End any biolink layout A/B test whose sample-size or end-date stop condition has been met.',
        'coach:snapshot-scores'                 => 'Record yesterday\'s Performance Coach score for every active link, powering the 30-day trend sparkline.',
        'calendars:sync'                        => 'Pull external calendar events for connected accounts and mirror them as Event Invite (.ics) links.',
        'contacts:sync'                         => 'Two-way sync of Google Contacts for every connected account.',
        'dialer:send-callback-reminders'        => 'Deliver due dialer call-back reminders (in-app + push), once each.',
        'socials:refresh-follower-counts'       => 'Refresh cached social follower counts so biolink Follow buttons show fresh numbers.',
        'followers:send-digest'                 => 'Email opted-in followers a digest of new posts and links from creators they follow, at their chosen local hour.',
        'backlinks:send-weekly-digest'          => 'Email opted-in creators a weekly digest of newly found backlinks pointing at their links and domains.',
        'users:send-email-verification-reminders' => 'Gently remind users who still haven\'t verified their email (self rate-limited).',
        'starter:send-free-window-reminders'    => 'Remind free Starter-plan users to re-confirm their plan near the end of their 1-year free window.',
        'domains:check-health'                  => 'Probe verified custom domains for DNS drift and run the takeover-protection state machine.',
        'queue:work --stop-when-empty --tries=1 --queue=default' => 'Drain queued background jobs (e.g. large contact imports), then exit so the next tick is clean.',
        'inbox:check-sla'                       => 'Notify assignees about Inbox 2.0 threads whose SLA elapsed without a reply.',
        'inbox:retry-forwards'                  => 'Retry transiently failed inbox-forward deliveries (email/webhook) with exponential backoff.',
        'imports:prune-abandoned'               => 'Delete contact-import preview stash files older than 24h that were never confirmed.',
        'socials:refresh-oauth-tokens'          => 'Refresh near-expiry social OAuth access tokens and flag broken connections for reconnect.',
        'subscriptions:renew-due'               => 'Charge gateways for subscriptions renewing within 24h and expire any past their grace window.',
        'events:send-rsvp-reminders'            => 'Email confirmed RSVP guests the configured number of hours before each event occurrence.',
        'links:check-health'                    => 'Probe Link Insurance destinations and run failover/restore on each link\'s chosen cadence.',
        'clicks:backfill-source'                => 'Re-tag any link_clicks rows missing a traffic source so they don\'t show as "Unknown".',
        'cloud-connections:check'               => 'Refresh near-expiry tokens and flag cloud connections whose OAuth was revoked or expired.',
        'minds:refresh-links'                   => 'Re-crawl AI Mind link sources whose refresh window has elapsed (capped per day).',
        'site-assistant:check-cutoffs'          => 'Alert admins when the Site Assistant abandon rate exceeds the configured threshold.',
        'site-assistant:prune-cutoff-alerts'    => 'Trim old Site Assistant cut-off alert rows beyond the retention window.',
        'blogs:publish-scheduled'               => 'Flip blog posts whose scheduled time has passed from scheduled to published.',
        'biolinks:apply-scheduled-themes'       => 'Activate due biolink theme schedules and revert ones whose window has ended.',
        'images:backfill-reoptimize'            => 'Downscale oversized images that slipped past the upload-time compression pipeline.',
        'carbon:snapshot-monthly'               => 'Estimate the prior month\'s per-biolink CO2 and auto-purchase carbon offsets for opted-in links.',
        'cv-uploads:prune-abandoned'            => 'Delete orphaned conversational-flow visitor uploads not referenced by any completed session.',
        'reviews:sync'                          => 'Pull third-party reviews (Google, Trustpilot, …) into external_reviews for connected providers.',
        'db:check-pending-migrations'           => 'Alert admins when the database schema is out of date (pending migrations) before users hit 500s.',
        'db:check-workspace-columns'            => 'Probe the live DB for missing workspace-scoping columns from half-applied migrations and alert admins.',
        'db:check-expected-columns'             => 'Probe the live DB for any code-required columns missing despite their migration being recorded as run.',
        'templates:check-design-health'         => 'Re-validate saved page/card template snapshots and alert admins when one develops design issues.',
        'plans:revert-expired-comps'            => 'Revert accounts whose admin-granted complimentary / time-limited plan window has elapsed.',
        'users:reactivate-due'                  => 'Auto-lift admin temporary account holds whose scheduled reactivation date has arrived.',
    ];

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

        $jobs = [];

        foreach ($events as $event) {
            $isCallback = $event instanceof CallbackEvent;

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
                ? Carbon::createFromTimestamp((int) $run['ran_at'])->setTimezone(config('app.timezone', 'UTC'))
                : null;

            $lastOk = (is_array($run) && array_key_exists('ok', $run) && is_bool($run['ok'])) ? $run['ok'] : null;

            // Overdue = we have evidence the job ran before, but its most recent
            // recorded run is more than one whole interval older than the most
            // recent scheduled fire time — i.e. the scheduler missed at least one
            // cycle. A previously-working-then-dead scheduler is exactly this.
            $overdue = $lastRun !== null && $prevRun !== null && $interval !== null && $interval > 0
                && ($prevRun->getTimestamp() - $lastRun->getTimestamp()) > $interval;

            $jobs[] = [
                'is_callback'         => $isCallback,
                'command'             => $commandWithArgs,
                'manual_command'      => $manualCommand,
                'expression'          => $expression,
                'frequency'           => $this->humanFrequency($expression),
                'purpose'             => $this->purposeFor($event, $artisanName, $descriptions),
                'next_run'            => $nextRun,
                'interval_seconds'    => $interval,
                'last_run'            => $lastRun,
                'last_run_ok'         => $lastOk,
                'last_run_error'      => (is_array($run) && is_string($run['error'] ?? null)) ? $run['error'] : null,
                'last_runtime'        => (is_array($run) && is_numeric($run['runtime'] ?? null)) ? (float) $run['runtime'] : null,
                'never_run'           => $lastRun === null,
                'overdue'             => $overdue,
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
            'last_tick'     => $tick->setTimezone(config('app.timezone', 'UTC')),
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
     * Resolve a plain-English purpose for the event.
     */
    protected function purposeFor(Event $event, ?string $artisanName, array $descriptions): string
    {
        if (! empty($event->description)) {
            return $event->description;
        }

        if ($artisanName !== null) {
            // Prefer a tailored override (full command string first, then bare name).
            $fullCommand = trim(preg_replace('/^php\s+artisan\s+/', '', Event::normalizeCommand($event->command ?? '')));

            if (isset(self::PURPOSE_OVERRIDES[$fullCommand])) {
                return self::PURPOSE_OVERRIDES[$fullCommand];
            }

            if (isset(self::PURPOSE_OVERRIDES[$artisanName])) {
                return self::PURPOSE_OVERRIDES[$artisanName];
            }

            if (! empty($descriptions[$artisanName])) {
                return $descriptions[$artisanName];
            }
        }

        return '—';
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
            $tz = $event->timezone ?: config('app.timezone', 'UTC');

            return Carbon::instance(
                (new CronExpression($event->expression))
                    ->getNextRunDate(Carbon::now()->setTimezone($tz))
            )->setTimezone(config('app.timezone', 'UTC'));
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
            $tz = $event->timezone ?: config('app.timezone', 'UTC');

            return Carbon::instance(
                (new CronExpression($event->expression))
                    ->getPreviousRunDate(Carbon::now()->setTimezone($tz))
            )->setTimezone(config('app.timezone', 'UTC'));
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
            . ' ' . config('app.timezone', 'UTC');
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
