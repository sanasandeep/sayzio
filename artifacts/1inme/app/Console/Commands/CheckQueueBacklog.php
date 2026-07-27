<?php

namespace App\Console\Commands;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use App\Services\Integrations\InternalAlertDispatcher;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Watchdog for a dead or stalled queue worker.
 *
 * Login-event recording (RecordLoginEventJob → login_events + last_login_at),
 * queued mail and notifications all run through the database queue. Jobs are
 * drained by the scheduler-driven `queue:work --stop-when-empty` loop; if the
 * worker stops draining (worker crash, scheduler asleep, poisoned job holding
 * the loop), jobs silently pile up in the `jobs` table and suspicious-login
 * history goes dark with no error anywhere.
 *
 * This scheduled check counts UNRESERVED jobs whose `available_at` is older
 * than a grace window. When the stale backlog reaches the threshold it fans
 * an alert out to ops admins (in-app + email via the centralized Emailer,
 * plus a best-effort Slack/Discord ping), and sends an all-clear once the
 * stale backlog clears. Mirrors {@see CheckScheduledJobFailures}.
 *
 * Episode state lives in `app_settings` under {@see STATE_KEY} (survives
 * deploys and multiple schedulers):
 *   - alerting     — true while a backlog episode is open
 *   - count        — the stale-job count at last alert
 *   - last_sent_at — ISO-8601 of the last alert
 *
 * An open episode is never re-alerted for the same size; if the backlog
 * KEEPS GROWING a reminder is allowed once the re-alert cooldown expires.
 * The episode closes — with an all-clear — as soon as the stale backlog
 * drops back to zero.
 *
 * Threshold / grace / cooldown are overridable via `app_settings` under
 * {@see SETTINGS_KEY} (keys: threshold, stale_minutes, cooldown_hours);
 * the class constants are only the defaults.
 *
 * Manual drain runbook (also included in the alert body):
 *   php artisan queue:work --stop-when-empty     # drain once
 *   php artisan queue:failed                     # inspect failed jobs
 *   php artisan queue:retry all                  # re-queue failed jobs
 */
class CheckQueueBacklog extends Command
{
    protected $signature = 'queue:check-backlog
                            {--force : Re-send the alert even if the open episode was already alerted}';

    protected $description = 'Alert ops admins when the database queue backlog is unhealthy (stale pending jobs — worker likely down), with an all-clear once it drains.';

    /** Default: alert once this many jobs are pending past the grace window. */
    public const BACKLOG_THRESHOLD = 10;

    /** Default: a pending job only counts as stale after this many minutes. */
    public const STALE_MINUTES = 10;

    /** Default: re-alert an OPEN episode only if the backlog grew AND this many hours passed. */
    public const REALERT_COOLDOWN_HOURS = 6;

    /** Sane bounds for the overridable values. */
    public const MIN_THRESHOLD      = 1;
    public const MAX_THRESHOLD      = 100000;
    public const MIN_STALE_MINUTES  = 2;
    public const MAX_STALE_MINUTES  = 1440;
    public const MIN_COOLDOWN_HOURS = 1;
    public const MAX_COOLDOWN_HOURS = 168;

    /** AppSetting key holding the episode state. */
    public const STATE_KEY = 'queue_backlog_health';

    /** AppSetting key holding optional overrides (threshold, stale_minutes, cooldown_hours). */
    public const SETTINGS_KEY = 'queue_backlog_alerts';

    public static function backlogThreshold(): int
    {
        return self::settingInt('threshold', self::BACKLOG_THRESHOLD, self::MIN_THRESHOLD, self::MAX_THRESHOLD);
    }

    public static function staleMinutes(): int
    {
        return self::settingInt('stale_minutes', self::STALE_MINUTES, self::MIN_STALE_MINUTES, self::MAX_STALE_MINUTES);
    }

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
        $connection = (string) config('queue.default');
        if ((string) config("queue.connections.{$connection}.driver") !== 'database') {
            $this->info("Queue connection '{$connection}' is not the database driver — nothing to inspect.");

            return self::SUCCESS;
        }

        $table = (string) config("queue.connections.{$connection}.table", 'jobs');

        $staleMinutes = self::staleMinutes();
        $threshold    = self::backlogThreshold();
        $cutoff       = now()->subMinutes($staleMinutes)->getTimestamp();

        // Pending = never reserved by a worker; stale = available for pickup
        // longer than the grace window. A healthy drain loop empties this in
        // well under a minute, so anything here means jobs are NOT moving.
        $staleQuery = DB::table($table)
            ->whereNull('reserved_at')
            ->where('available_at', '<=', $cutoff);

        $staleCount = (clone $staleQuery)->count();
        $oldestAt   = $staleQuery->min('available_at');

        $state = $this->state();

        if ($staleCount === 0) {
            if (! empty($state['alerting'])) {
                $this->dispatchRecovery((int) ($state['count'] ?? 0));
                $this->putState([]);
            } else {
                $this->info('Queue backlog healthy — no stale pending jobs.');
            }

            return self::SUCCESS;
        }

        if ($staleCount < $threshold) {
            // Below the alert threshold. Keep an open episode open (worker
            // still hasn't fully drained), but don't page for a small blip.
            $this->info("{$staleCount} stale pending job(s) — below the alert threshold of {$threshold}.");

            return self::SUCCESS;
        }

        $oldestAgeMinutes = $oldestAt !== null
            ? max(0, (int) floor((now()->getTimestamp() - (int) $oldestAt) / 60))
            : $staleMinutes;

        // Loud marker every run so log-based alerting catches it too.
        Log::error(
            "::1inme:: QUEUE BACKLOG — {$staleCount} pending job(s) older than {$staleMinutes} minute(s) on the '{$connection}' queue; "
            . 'the queue worker appears to be down. Login events, queued mail and notifications are NOT being delivered.'
        );

        $alerting  = ! empty($state['alerting']);
        $lastCount = (int) ($state['count'] ?? 0);

        $shouldSend = $this->option('force')
            || ! $alerting
            || ($staleCount > $lastCount && $this->cooldownExpired($state['last_sent_at'] ?? null));

        if (! $shouldSend) {
            $this->info("Backlog still unhealthy ({$staleCount} stale) — episode already alerted, not re-sending.");

            return self::SUCCESS;
        }

        $this->dispatchAlert($staleCount, $staleMinutes, $oldestAgeMinutes, $connection);

        $this->putState([
            'alerting'     => true,
            'count'        => $staleCount,
            'last_sent_at' => now()->toIso8601String(),
        ]);

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────

    private function dispatchAlert(int $count, int $staleMinutes, int $oldestAgeMinutes, string $connection): void
    {
        $admins  = $this->admins();
        $url     = $this->panelUrl();
        $subject = "Queue backlog unhealthy — {$count} job(s) stuck, worker may be down";

        $body = "The background queue worker does not appear to be draining jobs: {$count} pending job(s) on the "
              . "'{$connection}' queue have been waiting longer than {$staleMinutes} minute(s) "
              . "(oldest ~{$oldestAgeMinutes} minute(s)).\n\n"
              . "While the backlog persists, queued work is silently NOT happening — including login-event recording "
              . "(login_events history and last_login_at go stale, so suspicious-login alerts go dark), queued "
              . "emails and notifications.\n\n"
              . "Likely causes: the scheduler's queue:work drain loop stopped (scheduler asleep or crashed) or a "
              . "poisoned job is blocking the worker.\n\n"
              . "To drain manually, run on the server:\n"
              . "  php artisan queue:work --stop-when-empty\n"
              . "  php artisan queue:failed        (inspect failed jobs)\n"
              . "  php artisan queue:retry all     (re-queue failed jobs)\n\n"
              . 'You will get an all-clear once the backlog drains.';

        $inApp  = $this->fanOutInApp($admins, 'queue_backlog_unhealthy', $subject, $body, $url, [
            'count'         => $count,
            'stale_minutes' => $staleMinutes,
        ]);
        $emails = $this->fanOutEmail($admins, $subject, $body, $url);

        try {
            InternalAlertDispatcher::send(
                $subject,
                "Queue worker looks down: {$count} stale pending job(s) (oldest ~{$oldestAgeMinutes}m). "
                . 'Login events / queued mail are not being delivered.',
                'error',
                ['Stale jobs' => (string) $count, 'Connection' => $connection]
            );
        } catch (\Throwable $e) {
            Log::warning('queue-backlog webhook alert failed: ' . $e->getMessage());
        }

        $this->error("Queue backlog unhealthy — {$count} stale pending job(s).");
        $this->info("Alert dispatched — in-app: {$inApp}, email: {$emails}.");
    }

    private function dispatchRecovery(int $hadCount): void
    {
        $admins  = $this->admins();
        $url     = $this->panelUrl();
        $subject = 'Queue backlog drained — worker healthy again';

        $body = 'Good news — the background queue has drained'
              . ($hadCount > 0 ? " (had {$hadCount} stuck job(s))" : '')
              . ". Login events, queued mail and notifications are being delivered again.\n\n"
              . 'No further action needed. You will be alerted again if a new backlog builds up.';

        $inApp  = $this->fanOutInApp($admins, 'queue_backlog_recovered', $subject, $body, $url, []);
        $emails = $this->fanOutEmail($admins, $subject, $body, $url);

        try {
            InternalAlertDispatcher::send($subject, 'Queue backlog recovered.', 'success');
        } catch (\Throwable $e) {
            Log::warning('queue-backlog webhook recovery alert failed: ' . $e->getMessage());
        }

        $this->info("Recovery dispatched — in-app: {$inApp}, email: {$emails}.");
    }

    /**
     * Operators who opted in to operational alerts — same audience as the
     * other ops health commands (schema health, storage, scheduled jobs).
     */
    private function admins()
    {
        return User::query()->withPermission('user.ops_alerts.receive')->get();
    }

    private function panelUrl(): string
    {
        try {
            return \App\Modules\Common\Support\PlatformHosts::outboundUrl(route('admin.cron-jobs.index'));
        } catch (\Throwable $e) {
            return \App\Modules\Common\Support\PlatformHosts::outboundUrl(url('/admin/cron-jobs'));
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
                Log::warning("queue-backlog in-app alert failed for user {$u->id}: " . $e->getMessage());
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
                Log::warning("queue-backlog alert email to {$email} failed: " . $e->getMessage());
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

    /** @return array<string, mixed> */
    private function state(): array
    {
        try {
            $state = AppSetting::get(self::STATE_KEY, []);

            return is_array($state) ? $state : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $state
     */
    private function putState(array $state): void
    {
        try {
            AppSetting::put(self::STATE_KEY, $state);
        } catch (\Throwable $e) {
            Log::warning('queue-backlog state write failed: ' . $e->getMessage());
        }
    }
}
