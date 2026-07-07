<?php

namespace App\Modules\Admin\Support;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Proactive admin alerting for scheduled-job failures and a silently-dead
 * scheduler. The admin Scheduled Jobs panel (web + mobile) shows run health
 * on demand, but an operator still had to open it to notice a failure — this
 * closes the loop: when a job's run finishes failed, ops admins get an
 * in-app notification + email deep-linking to the panel, and an all-clear
 * once the job succeeds again. Likewise when the global scheduler heartbeat
 * goes stale (crontab dead) and when it recovers.
 *
 * Idempotency is streak-based, mirroring the SchemaHealth / StorageHealth
 * "episode" pattern: exactly one alert opens a per-job failure episode
 * (however many consecutive runs keep failing), and the first success closes
 * it with a recovery notice. Dedup state lives in `app_settings` under the
 * `scheduled_job_health` key so it survives deploys and multiple schedulers:
 *   - jobs.{key}   — ['alerting'=>true, 'last_sent_at'=>ISO, 'last_error'=>…]
 *                    present only while that job's episode is open
 *   - scheduler    — same shape, for the stale-heartbeat episode
 *
 * Triggers:
 *   - ScheduledJobRunRecorder::finished()  — every scheduled run (schedule source)
 *   - RunScheduledJob (panel "Run now")    — manual runs count toward/close streaks
 *   - checkFromBoot()                      — web boots watch the heartbeat, because
 *     a dead scheduler can't report on itself (throttled to one real check
 *     per few minutes; the common fresh-tick path is a single cache read)
 *
 * Wholly best-effort: alerting can never break a scheduled run or a boot.
 */
class ScheduledJobHealthAlerts
{
    public const STATE_KEY = 'scheduled_job_health';

    /**
     * Admin-tunable settings, stored in `app_settings` alongside the episode
     * state key above (mirrors CheckScheduledJobFailures::SETTINGS_KEY):
     *   - muted_jobs           — list of job keys whose failure/recovery
     *                            alerts are suppressed (noisy/experimental)
     *   - stale_after_seconds  — heartbeat age before the scheduler is
     *                            considered dead (clamped to sane bounds)
     */
    public const SETTINGS_KEY = 'scheduled_job_health_settings';

    /**
     * Default heartbeat age beyond which the scheduler is considered dead.
     * The panel flags "stale" after ~3 minutes (2× the shortest cadence +
     * grace); this boot-path alert is deliberately more conservative so a
     * briefly delayed tick never pages anyone. Admin-tunable from the
     * Scheduled Jobs panel — read the effective value via
     * {@see schedulerStaleAfterSeconds()}.
     */
    public const SCHEDULER_STALE_AFTER_SECONDS = 900;

    /** Sane bounds for the admin-tunable stale threshold. */
    public const MIN_STALE_AFTER_SECONDS = 300;   // 5 minutes
    public const MAX_STALE_AFTER_SECONDS = 86400; // 24 hours

    /**
     * Effective stale threshold (seconds): admin value from app_settings
     * clamped to sane bounds, falling back to the class default.
     */
    public static function schedulerStaleAfterSeconds(): int
    {
        try {
            $all   = AppSetting::get(self::SETTINGS_KEY, []);
            $value = is_array($all) ? ($all['stale_after_seconds'] ?? null) : null;
        } catch (\Throwable $e) {
            $value = null;
        }

        if (! is_numeric($value)) {
            return self::SCHEDULER_STALE_AFTER_SECONDS;
        }

        return max(self::MIN_STALE_AFTER_SECONDS, min(self::MAX_STALE_AFTER_SECONDS, (int) $value));
    }

    /** Persist the admin-tuned stale threshold (seconds, clamped). */
    public static function setSchedulerStaleAfterSeconds(int $seconds): void
    {
        self::putSettings([
            'stale_after_seconds' => max(self::MIN_STALE_AFTER_SECONDS, min(self::MAX_STALE_AFTER_SECONDS, $seconds)),
        ]);
    }

    /**
     * Job keys whose failure/recovery alerting is muted.
     *
     * @return array<int, string>
     */
    public static function mutedJobs(): array
    {
        try {
            $all   = AppSetting::get(self::SETTINGS_KEY, []);
            $muted = is_array($all) ? ($all['muted_jobs'] ?? []) : [];
        } catch (\Throwable $e) {
            $muted = [];
        }

        return is_array($muted) ? array_values(array_filter($muted, 'is_string')) : [];
    }

    public static function isJobMuted(string $jobKey): bool
    {
        return in_array($jobKey, self::mutedJobs(), true);
    }

    /** Mute failure/recovery alerts for one job (idempotent). */
    public static function muteJob(string $jobKey): void
    {
        $muted = self::mutedJobs();
        if (! in_array($jobKey, $muted, true)) {
            $muted[] = $jobKey;
        }
        self::putSettings(['muted_jobs' => array_values($muted)]);
    }

    /** Unmute a previously muted job (idempotent). */
    public static function unmuteJob(string $jobKey): void
    {
        $muted = array_values(array_filter(self::mutedJobs(), fn ($k) => $k !== $jobKey));
        self::putSettings(['muted_jobs' => $muted]);
    }

    /** Cache lock so at most one web request per window pays the check cost. */
    protected const BOOT_THROTTLE_KEY = 'scheduled_job_health:boot_check';
    protected const BOOT_THROTTLE_SECONDS = 180;

    /**
     * Open failure episodes for at-a-glance display on the admin Scheduled
     * Jobs panel (web + mobile) and the admin dashboard. Reads the same
     * `scheduled_job_health` dedup state the alert dispatchers maintain, so
     * the banner and the notifications can never disagree.
     *
     * @return array{jobs: list<array{key: string, since: string|null, last_error: string|null}>, scheduler: array{since: string|null}|null}
     */
    public static function openEpisodes(): array
    {
        $out = ['jobs' => [], 'scheduler' => null];

        try {
            $jobs = self::state('jobs', []);
            foreach (is_array($jobs) ? $jobs : [] as $key => $episode) {
                if (! is_array($episode) || empty($episode['alerting'])) {
                    continue;
                }

                $out['jobs'][] = [
                    'key'        => (string) $key,
                    'since'      => isset($episode['last_sent_at']) ? (string) $episode['last_sent_at'] : null,
                    'last_error' => isset($episode['last_error']) ? (string) $episode['last_error'] : null,
                ];
            }

            usort($out['jobs'], fn ($a, $b) => strcmp($a['key'], $b['key']));

            $scheduler = self::state('scheduler', []);
            if (is_array($scheduler) && ! empty($scheduler['alerting'])) {
                $out['scheduler'] = [
                    'since' => isset($scheduler['last_sent_at']) ? (string) $scheduler['last_sent_at'] : null,
                ];
            }
        } catch (\Throwable $e) {
            // Display-only: never let a state read break the panel.
            Log::warning('scheduled-job-health openEpisodes read failed: ' . $e->getMessage());
        }

        return $out;
    }

    /**
     * Record the outcome of a finished job run (scheduled or manual) and
     * alert / recover accordingly. One alert per failure streak: the first
     * failed run opens the episode and notifies; further failures are
     * silent; the first success closes it with an all-clear.
     */
    public static function jobFinished(string $jobKey, bool $ok, ?string $error = null, ?int $exitCode = null, string $source = 'schedule'): void
    {
        try {
            if ($ok) {
                self::maybeRecoverJob($jobKey);

                // Any run the scheduler itself executed proves the heartbeat
                // is alive again — close an open stale episode.
                if ($source === 'schedule') {
                    self::maybeRecoverScheduler();
                }

                return;
            }

            self::maybeAlertJobFailure($jobKey, $error, $exitCode, $source);
        } catch (\Throwable $e) {
            Log::warning('scheduled-job-health alert hook failed: ' . $e->getMessage());
        }
    }

    /**
     * Boot-path scheduler heartbeat watch. Called from web request boots
     * (a dead scheduler cannot run a scheduled check on itself). Cheap on
     * the hot path: console boots skipped, and a short-lived cache lock
     * means at most one request per window does any real work.
     */
    public static function checkFromBoot(): void
    {
        try {
            if (app()->runningInConsole()) {
                return;
            }

            // Cache::add is atomic-ish per store: only the first request in
            // each window proceeds.
            if (! Cache::add(self::BOOT_THROTTLE_KEY, 1, self::BOOT_THROTTLE_SECONDS)) {
                return;
            }

            self::checkSchedulerStale();
        } catch (\Throwable $e) {
            // Never let alerting break a boot.
            Log::warning('scheduled-job-health boot check failed: ' . $e->getMessage());
        }
    }

    /**
     * Evaluate the scheduler heartbeat now: alert when stale (once per
     * episode), all-clear when fresh again after an open episode.
     */
    public static function checkSchedulerStale(): void
    {
        $tick = app(CronRunLog::class)->lastTick();

        if ($tick === null) {
            // Never ran at all: either a brand-new deploy whose first minute
            // hasn't elapsed, or the crontab was never configured. Both are
            // "unknown" — don't page on it.
            return;
        }

        $age = now()->getTimestamp() - $tick->getTimestamp();

        if ($age <= self::schedulerStaleAfterSeconds()) {
            self::maybeRecoverScheduler();

            return;
        }

        $scheduler = self::state('scheduler', []);
        if (is_array($scheduler) && ! empty($scheduler['alerting'])) {
            return; // Episode already open — one alert per streak.
        }

        self::dispatchSchedulerStaleAlert($tick->diffForHumans());
    }

    // ─────────────────────────────────────────────────────────────

    protected static function maybeAlertJobFailure(string $jobKey, ?string $error, ?int $exitCode, string $source): void
    {
        if (self::isJobMuted($jobKey)) {
            return; // Admin muted alerting for this job — stay silent.
        }

        $jobs = self::state('jobs', []);
        $jobs = is_array($jobs) ? $jobs : [];

        if (! empty($jobs[$jobKey]['alerting'])) {
            return; // Streak already alerted.
        }

        $def     = ScheduledJobRegistry::find($jobKey);
        $purpose = is_array($def) ? (string) ($def['description'] ?? '') : '';
        $detail  = $error !== null && $error !== ''
            ? $error
            : ($exitCode !== null ? 'Exited with code ' . $exitCode : 'Unknown error');
        $detail  = Str::limit($detail, 500);

        $url     = self::panelUrl();
        $subject = "Scheduled job failed: {$jobKey}";
        $body    = "The scheduled job \"{$jobKey}\""
                 . ($purpose !== '' ? " ({$purpose})" : '')
                 . ' finished with a failure'
                 . ($source === 'manual' ? ' (manual run-now execution)' : '')
                 . ".\n\nError: {$detail}\n\n"
                 . 'You will not be re-alerted while it keeps failing; an all-clear follows its next successful run. '
                 . 'Review it in Admin > Scheduled Jobs.';

        $inApp  = self::fanOutInApp('scheduled_job_failed', $subject, $body, $url, [
            'job_key' => $jobKey,
            'error'   => $detail,
        ]);
        $emails = self::fanOutEmail($subject, $body, $url);

        self::webhookPing($subject, "\"{$jobKey}\" failed: {$detail}", 'error', ['Job' => $jobKey, 'Source' => $source]);

        $jobs[$jobKey] = [
            'alerting'     => true,
            'last_sent_at' => now()->toIso8601String(),
            'last_error'   => $detail,
        ];
        self::putState(['jobs' => $jobs]);

        Log::info("scheduled-job-health failure alert for '{$jobKey}' dispatched — in-app: {$inApp}, email: {$emails}.");
    }

    protected static function maybeRecoverJob(string $jobKey): void
    {
        $jobs = self::state('jobs', []);
        $jobs = is_array($jobs) ? $jobs : [];

        if (empty($jobs[$jobKey]['alerting'])) {
            return; // No open episode for this job.
        }

        if (self::isJobMuted($jobKey)) {
            // Muted mid-episode: close the episode silently so a stale entry
            // never lingers, but send no recovery noise for a muted job.
            unset($jobs[$jobKey]);
            self::putState(['jobs' => $jobs]);

            return;
        }

        $url     = self::panelUrl();
        $subject = "Scheduled job recovered: {$jobKey}";
        $body    = "Good news — the scheduled job \"{$jobKey}\" completed successfully again after failing. No further action needed.";

        $inApp  = self::fanOutInApp('scheduled_job_recovered', $subject, $body, $url, [
            'job_key' => $jobKey,
        ]);
        $emails = self::fanOutEmail($subject, $body, $url);

        self::webhookPing($subject, $body, 'success', ['Job' => $jobKey]);

        unset($jobs[$jobKey]);
        self::putState(['jobs' => $jobs]);

        Log::info("scheduled-job-health recovery for '{$jobKey}' dispatched — in-app: {$inApp}, email: {$emails}.");
    }

    protected static function dispatchSchedulerStaleAlert(string $lastTickHuman): void
    {
        $url     = self::panelUrl();
        $subject = 'Scheduler appears to be down — no jobs have run recently';
        $body    = "The background scheduler has not run any job since {$lastTickHuman}. "
                 . 'Scheduled work (health checks, syncs, digests, publishing) is NOT happening. '
                 . 'This usually means the server crontab entry for `php artisan schedule:run` stopped firing. '
                 . 'Check the server and the Admin > Scheduled Jobs panel.';

        $inApp  = self::fanOutInApp('scheduler_stale', $subject, $body, $url, []);
        $emails = self::fanOutEmail($subject, $body, $url);

        self::webhookPing($subject, $body, 'error');

        self::putState(['scheduler' => [
            'alerting'     => true,
            'last_sent_at' => now()->toIso8601String(),
        ]]);

        Log::info("scheduled-job-health scheduler-stale alert dispatched — in-app: {$inApp}, email: {$emails}.");
    }

    protected static function maybeRecoverScheduler(): void
    {
        $scheduler = self::state('scheduler', []);

        if (! is_array($scheduler) || empty($scheduler['alerting'])) {
            return;
        }

        $url     = self::panelUrl();
        $subject = 'Scheduler is running again';
        $body    = 'Good news — the background scheduler is executing jobs again. No further action needed.';

        $inApp  = self::fanOutInApp('scheduler_recovered', $subject, $body, $url, []);
        $emails = self::fanOutEmail($subject, $body, $url);

        self::webhookPing($subject, $body, 'success');

        self::putState(['scheduler' => [
            'alerting'     => false,
            'recovered_at' => now()->toIso8601String(),
        ]]);

        Log::info("scheduled-job-health scheduler recovery dispatched — in-app: {$inApp}, email: {$emails}.");
    }

    // ─────────────────────────────────────────────────────────────

    /**
     * Operators who opted in to operational alerts — the same audience as
     * the other ops health alerts (schema health, storage health, …).
     */
    protected static function admins()
    {
        return User::query()->withPermission('user.ops_alerts.receive')->get();
    }

    /**
     * Deep link into the Scheduled Jobs panel. The same URL serves both
     * surfaces: the web notification opens /admin/cron-jobs directly, and
     * the mobile notifications screen maps it to its native
     * /admin/scheduled-jobs screen (see nativeRouteFor in notifications.tsx).
     */
    protected static function panelUrl(): string
    {
        try {
            return route('admin.cron-jobs.index');
        } catch (\Throwable $e) {
            return url('/admin/cron-jobs');
        }
    }

    /**
     * @param array<string,mixed> $extra
     */
    protected static function fanOutInApp(string $type, string $subject, string $body, string $url, array $extra): int
    {
        $delivered = 0;
        foreach (self::admins() as $u) {
            try {
                UserNotification::create([
                    'user_id' => $u->id,
                    'type'    => $type,
                    'data'    => array_merge([
                        'subject'    => $subject,
                        'body'       => $body,
                        'message'    => $body, // legacy field rendered by the notifications view
                        'url'        => $url,  // canonical key consumed by the in-app list + mobile
                        'target_url' => $url,  // legacy alias for older renderers
                    ], $extra),
                    'created_at' => now(),
                ]);
                $delivered++;
            } catch (\Throwable $e) {
                Log::warning("scheduled-job-health in-app alert failed for user {$u->id}: " . $e->getMessage());
            }
        }

        return $delivered;
    }

    protected static function fanOutEmail(string $subject, string $body, string $url): int
    {
        $emails = collect(self::admins())
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
                Log::warning("scheduled-job-health alert email to {$email} failed: " . $e->getMessage());
            }
        }

        return $sent;
    }

    /**
     * Best-effort Slack/Discord ping via the internal alerts webhooks.
     *
     * @param array<string,string> $fields
     */
    protected static function webhookPing(string $title, string $body, string $level, array $fields = []): void
    {
        try {
            \App\Services\Integrations\InternalAlertDispatcher::send($title, $body, $level, $fields);
        } catch (\Throwable $e) {
            Log::warning('scheduled-job-health webhook alert failed: ' . $e->getMessage());
        }
    }

    protected static function state(string $key, $default = null)
    {
        $all = AppSetting::get(self::STATE_KEY, []);

        return is_array($all) ? ($all[$key] ?? $default) : $default;
    }

    /**
     * @param array<string,mixed> $patch
     */
    protected static function putState(array $patch): void
    {
        try {
            $all = AppSetting::get(self::STATE_KEY, []);
            $all = is_array($all) ? $all : [];
            AppSetting::put(self::STATE_KEY, array_merge($all, $patch));
        } catch (\Throwable $e) {
            Log::warning('scheduled-job-health state write failed: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string,mixed> $patch
     */
    protected static function putSettings(array $patch): void
    {
        try {
            $all = AppSetting::get(self::SETTINGS_KEY, []);
            $all = is_array($all) ? $all : [];
            AppSetting::put(self::SETTINGS_KEY, array_merge($all, $patch));
        } catch (\Throwable $e) {
            Log::warning('scheduled-job-health settings write failed: ' . $e->getMessage());
        }
    }
}
