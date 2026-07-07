<?php

namespace App\Console\Commands;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\ScheduledJobRun;
use App\Modules\Admin\Support\ScheduledJobRegistry;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use App\Services\Integrations\InternalAlertDispatcher;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Watchdog for scheduled jobs that keep failing silently.
 *
 * Run outcomes (success/failed, exit code, runtime) are recorded in
 * `scheduled_job_runs` by ScheduledJobRunRecorder, but until now nobody was
 * told when a job failed over and over — an operator only noticed by opening
 * the Scheduled Jobs panel. This scheduled check scans the run history for
 * jobs with {@see FAILURE_THRESHOLD}+ consecutive failures (failed runs since
 * the job's last success) and fans an alert out to ops admins (in-app +
 * email via the centralized Emailer, plus a best-effort Slack/Discord ping).
 *
 * Mirrors {@see CheckTemplateGallery} / StorageHealthAlerts. Idempotency and
 * episode state live in `app_settings` under `scheduled_job_failure_health`
 * (survives deploys and multiple schedulers):
 *   - jobs.{key}.alerting     — true while a failure episode is open
 *   - jobs.{key}.streak       — the consecutive-failure count at last alert
 *   - jobs.{key}.last_sent_at — ISO-8601 of the last alert for that job
 *
 * An open episode is never re-alerted for the same streak (so an hourly
 * cadence can't spam admins). If the streak KEEPS GROWING, a reminder is
 * allowed once the last alert is older than the re-alert cooldown.
 * The episode closes — with an all-clear notification — as soon as the job
 * succeeds again, re-arming the alert for any future streak.
 *
 * Both the failure threshold and the re-alert cooldown are admin-tunable
 * from the Scheduled Jobs panel (persisted in `app_settings` under
 * {@see SETTINGS_KEY}); the class constants are only the defaults. Read the
 * effective values via {@see failureThreshold()} / {@see realertCooldownHours()}.
 */
class CheckScheduledJobFailures extends Command
{
    protected $signature = 'scheduled-jobs:check-failures
                            {--force : Re-send alerts for open episodes even if already alerted}';

    protected $description = 'Alert ops admins when a scheduled job keeps failing consecutively (in-app + email), with an all-clear once it recovers. Threshold and cooldown are admin-tunable.';

    /** Default: alert once a job has this many consecutive failed runs. */
    public const FAILURE_THRESHOLD = 3;

    /** Default: re-alert an OPEN episode only if the streak grew AND this many hours passed. */
    public const REALERT_COOLDOWN_HOURS = 24;

    /** Sane bounds for the admin-tunable values. */
    public const MIN_THRESHOLD      = 2;
    public const MAX_THRESHOLD      = 50;
    public const MIN_COOLDOWN_HOURS = 1;
    public const MAX_COOLDOWN_HOURS = 168; // one week

    /** AppSetting key holding all episode state. */
    public const STATE_KEY = 'scheduled_job_failure_health';

    /** AppSetting key holding the admin-tunable alert settings. */
    public const SETTINGS_KEY = 'scheduled_job_failure_alerts';

    /**
     * Effective failure threshold: admin value from app_settings clamped to
     * sane bounds, falling back to the class default.
     */
    public static function failureThreshold(): int
    {
        return self::settingInt('threshold', self::FAILURE_THRESHOLD, self::MIN_THRESHOLD, self::MAX_THRESHOLD);
    }

    /**
     * Effective re-alert cooldown (hours): admin value from app_settings
     * clamped to sane bounds, falling back to the class default.
     */
    public static function realertCooldownHours(): int
    {
        return self::settingInt('cooldown_hours', self::REALERT_COOLDOWN_HOURS, self::MIN_COOLDOWN_HOURS, self::MAX_COOLDOWN_HOURS);
    }

    private static function settingInt(string $key, int $default, int $min, int $max): int
    {
        try {
            $all   = AppSetting::get(self::SETTINGS_KEY, []);
            $value = is_array($all) ? ($all[$key] ?? null) : null;
        } catch (\Throwable $e) {
            $value = null;
        }

        if (! is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }

    public function handle(): int
    {
        $registry  = ScheduledJobRegistry::all();
        $state     = $this->jobsState();
        $threshold = self::failureThreshold();

        // Keys worth inspecting: registry jobs that have ever failed, plus any
        // key with an open episode (so recovery still fires even if the failed
        // rows have since been pruned).
        $failedKeys = ScheduledJobRun::query()
            ->where('status', ScheduledJobRun::STATUS_FAILED)
            ->distinct()
            ->pluck('job_key')
            ->all();

        $keys = array_values(array_unique(array_merge(
            array_intersect($failedKeys, array_keys($registry)),
            array_keys($state),
        )));

        $toAlert   = []; // job payloads for a (re-)alert
        $recovered = []; // job payloads for the all-clear

        foreach ($keys as $key) {
            $jobState = $state[$key] ?? [];

            // Dropped from the registry — forget any stale episode silently.
            if (! isset($registry[$key])) {
                unset($state[$key]);
                continue;
            }

            $streak = $this->consecutiveFailures($key);

            if ($streak === 0) {
                // Latest finished run succeeded — close an open episode with
                // an all-clear, otherwise nothing to do.
                if (! empty($jobState['alerting'])) {
                    $recovered[] = [
                        'key'         => $key,
                        'description' => (string) ($registry[$key]['description'] ?? ''),
                        'streak'      => (int) ($jobState['streak'] ?? 0),
                    ];
                    unset($state[$key]);
                }
                continue;
            }

            if ($streak < $threshold) {
                // Below the threshold. If an episode is open (job still hasn't
                // succeeded), keep it open without re-alerting.
                continue;
            }

            $alerting   = ! empty($jobState['alerting']);
            $lastStreak = (int) ($jobState['streak'] ?? 0);

            $shouldSend = $this->option('force')
                || ! $alerting
                || ($streak > $lastStreak && $this->cooldownExpired($jobState['last_sent_at'] ?? null));

            // Loud marker every run so log-based alerting catches it too.
            Log::error(
                "::1inme:: SCHEDULED JOB FAILING — '{$key}' has {$streak} consecutive failed run(s); "
                . 'check Admin > Scheduled Jobs for the error details.'
            );

            if (! $shouldSend) {
                $this->info("'{$key}' still failing (streak {$streak}) — episode already alerted, not re-sending.");
                continue;
            }

            $lastRun = ScheduledJobRun::query()
                ->where('job_key', $key)
                ->where('status', ScheduledJobRun::STATUS_FAILED)
                ->orderByDesc('started_at')
                ->orderByDesc('id')
                ->first();

            $toAlert[] = [
                'key'          => $key,
                'description'  => (string) ($registry[$key]['description'] ?? ''),
                'streak'       => $streak,
                'last_failed'  => $lastRun?->started_at?->toIso8601String(),
                'exit_code'    => $lastRun?->exit_code,
                'error'        => $this->truncate((string) ($lastRun->error ?? '')),
            ];

            $state[$key] = [
                'alerting'     => true,
                'streak'       => $streak,
                'last_sent_at' => now()->toIso8601String(),
            ];
        }

        if ($toAlert !== []) {
            $this->dispatchAlert($toAlert);
        }
        if ($recovered !== []) {
            $this->dispatchRecovery($recovered);
        }

        $this->putJobsState($state);

        if ($toAlert === [] && $recovered === []) {
            $this->info('No new failing-job streaks and no recoveries — nothing to send.');
        }

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────

    /**
     * Consecutive failed runs since the job's last successful run — running
     * rows are ignored, so an in-flight retry never masks the streak.
     */
    private function consecutiveFailures(string $key): int
    {
        $lastSuccessAt = ScheduledJobRun::query()
            ->where('job_key', $key)
            ->where('status', ScheduledJobRun::STATUS_SUCCESS)
            ->max('started_at');

        $q = ScheduledJobRun::query()
            ->where('job_key', $key)
            ->where('status', ScheduledJobRun::STATUS_FAILED);

        if ($lastSuccessAt) {
            $q->where('started_at', '>', $lastSuccessAt);
        }

        return $q->count();
    }

    /**
     * @param array<int, array<string, mixed>> $jobs
     */
    private function dispatchAlert(array $jobs): void
    {
        $admins = $this->admins();
        $url    = $this->panelUrl();

        $names   = collect($jobs)->pluck('key')->all();
        $subject = count($jobs) === 1
            ? "Scheduled job '{$names[0]}' keeps failing"
            : count($jobs) . ' scheduled jobs keep failing';

        $lines = [];
        foreach ($jobs as $job) {
            $line = "- {$job['key']}: {$job['streak']} consecutive failed run(s)";
            if (! empty($job['last_failed'])) {
                $line .= ", last failed at {$job['last_failed']}";
            }
            if ($job['exit_code'] !== null) {
                $line .= " (exit code {$job['exit_code']})";
            }
            if (! empty($job['error'])) {
                $line .= ". Last error: {$job['error']}";
            }
            $lines[] = $line;
        }

        $body = "These scheduled background jobs have been failing repeatedly — every run since their last success has errored, "
              . "so whatever they automate (syncs, digests, health probes, cleanups) is silently not happening:\n\n"
              . implode("\n", $lines) . "\n\n"
              . 'Open Admin > Scheduled Jobs to inspect the run history and error output, then fix the underlying cause '
              . 'or use Run now to retry. You will get an all-clear once each job succeeds again.';

        $inApp  = $this->fanOutInApp($admins, 'scheduled_job_failing', $subject, $body, $url, [
            'jobs' => collect($jobs)->map(fn ($j) => ['key' => $j['key'], 'streak' => $j['streak']])->all(),
        ]);
        $emails = $this->fanOutEmail($admins, $subject, $body, $url);

        // Best-effort Slack/Discord ping via the internal alerts webhooks.
        try {
            InternalAlertDispatcher::send(
                $subject,
                'Failing scheduled job(s): ' . implode(', ', $names) . '. Fix in Admin > Scheduled Jobs.',
                'error',
                ['Jobs' => implode(', ', $names)]
            );
        } catch (\Throwable $e) {
            Log::warning('scheduled-job-failure webhook alert failed: ' . $e->getMessage());
        }

        $this->error('Failing scheduled job(s): ' . implode(', ', $names) . '.');
        $this->info("Alert dispatched — in-app: {$inApp}, email: {$emails}.");
    }

    /**
     * @param array<int, array<string, mixed>> $jobs
     */
    private function dispatchRecovery(array $jobs): void
    {
        $admins = $this->admins();
        $url    = $this->panelUrl();

        $names   = collect($jobs)->pluck('key')->all();
        $subject = count($jobs) === 1
            ? "Scheduled job '{$names[0]}' is healthy again"
            : count($jobs) . ' scheduled jobs are healthy again';

        $lines = collect($jobs)
            ->map(fn ($j) => "- {$j['key']}" . ($j['streak'] > 0 ? " (had {$j['streak']} consecutive failures)" : ''))
            ->all();

        $body = "Good news — these scheduled jobs completed successfully again after a run of failures:\n\n"
              . implode("\n", $lines) . "\n\n"
              . 'No further action needed. You will be alerted again if a new failure streak starts.';

        $inApp  = $this->fanOutInApp($admins, 'scheduled_job_recovered', $subject, $body, $url, [
            'jobs' => collect($jobs)->map(fn ($j) => ['key' => $j['key']])->all(),
        ]);
        $emails = $this->fanOutEmail($admins, $subject, $body, $url);

        try {
            InternalAlertDispatcher::send($subject, 'Recovered: ' . implode(', ', $names) . '.', 'success');
        } catch (\Throwable $e) {
            Log::warning('scheduled-job-failure webhook recovery alert failed: ' . $e->getMessage());
        }

        $this->info("Recovery dispatched — in-app: {$inApp}, email: {$emails}.");
    }

    /**
     * Operators who opted in to operational alerts — same audience as the
     * other ops health commands (schema health, storage, template gallery).
     */
    private function admins()
    {
        return User::query()->withPermission('user.ops_alerts.receive')->get();
    }

    private function panelUrl(): string
    {
        try {
            return route('admin.cron-jobs.index');
        } catch (\Throwable $e) {
            return url('/admin/cron-jobs');
        }
    }

    /**
     * @param iterable $admins
     * @param array<string,mixed> $extra
     */
    private function fanOutInApp($admins, string $type, string $subject, string $body, string $url, array $extra): int
    {
        $delivered = 0;
        foreach ($admins as $u) {
            try {
                UserNotification::create([
                    'user_id' => $u->id,
                    'type'    => $type,
                    'data'    => array_merge([
                        'subject'    => $subject,
                        'body'       => $body,
                        'message'    => $body, // legacy field rendered by the notifications view
                        'url'        => $url,  // canonical key consumed by the in-app list
                        'target_url' => $url,  // legacy alias for older renderers
                    ], $extra),
                    'created_at' => now(),
                ]);
                $delivered++;
            } catch (\Throwable $e) {
                Log::warning("scheduled-job-failure in-app alert failed for user {$u->id}: " . $e->getMessage());
            }
        }
        return $delivered;
    }

    /**
     * @param iterable $admins
     */
    private function fanOutEmail($admins, string $subject, string $body, string $url): int
    {
        $emails = collect($admins)
            ->filter(fn ($u) => $u->email && $u->email_verified_at)
            ->pluck('email')
            ->unique()
            ->values()
            ->all();

        $sent = 0;
        foreach ($emails as $email) {
            try {
                \App\Modules\Common\Services\Emailer::send('system.health_alert', $email, [], [
                    'subject' => $subject,
                    'body'    => $body . "\n\n" . $url,
                    'format'  => 'text',
                ]);
                $sent++;
            } catch (\Throwable $e) {
                Log::warning("scheduled-job-failure alert email to {$email} failed: " . $e->getMessage());
            }
        }
        return $sent;
    }

    private function cooldownExpired($lastSentAt): bool
    {
        if (! $lastSentAt) {
            return true;
        }
        try {
            return Carbon::parse($lastSentAt)->lessThanOrEqualTo(now()->subHours(self::realertCooldownHours()));
        } catch (\Throwable $e) {
            // Malformed timestamp — treat as expired; the next write heals it.
            return true;
        }
    }

    private function truncate(string $text, int $max = 200): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if ($text === '') {
            return '';
        }
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max) . '…' : $text;
    }

    /** @return array<string, array<string, mixed>> per-job episode state */
    private function jobsState(): array
    {
        try {
            $all  = AppSetting::get(self::STATE_KEY, []);
            $jobs = is_array($all) ? ($all['jobs'] ?? []) : [];
            return is_array($jobs) ? $jobs : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param array<string, array<string, mixed>> $jobs
     */
    private function putJobsState(array $jobs): void
    {
        try {
            $all = AppSetting::get(self::STATE_KEY, []);
            $all = is_array($all) ? $all : [];
            $all['jobs'] = $jobs;
            AppSetting::put(self::STATE_KEY, $all);
        } catch (\Throwable $e) {
            Log::warning('scheduled-job-failure state write failed: ' . $e->getMessage());
        }
    }
}
