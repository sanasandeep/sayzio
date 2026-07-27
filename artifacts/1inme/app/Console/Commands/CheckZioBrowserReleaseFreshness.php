<?php

namespace App\Console\Commands;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\ZioBrowserRelease;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use App\Services\Integrations\InternalAlertDispatcher;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Watchdog for the /download page's cached SayZio Browser release.
 *
 * The page only ever reads the cache; freshness comes from the scheduled
 * `zio-browser:refresh-release` job. A single failed refresh is harmless
 * (the last-known release keeps serving), so per-run failure alerts would be
 * noise — but if refreshes keep failing for DAYS (GitHub rate limit, tag
 * rename, asset naming change) the page silently serves an increasingly
 * stale release. This check alerts ops admins once refreshes have been
 * failing CONTINUOUSLY beyond {@see STALE_AFTER_HOURS}.
 *
 * Mirrors {@see CheckTemplateGallery} / CheckScheduledJobFailures. Refresh
 * outcomes are stamped by ZioBrowserRelease::refresh() into `app_settings`
 * under {@see ZioBrowserRelease::HEALTH_KEY} (failing_since opens on the
 * first failure after a success and clears on the next success); this
 * command stores its episode state under the same key:
 *   - alerting     — true while a staleness episode is open
 *   - last_sent_at — ISO-8601 of the last alert (re-alert cooldown)
 *
 * One alert opens the episode (no per-failure spam); while it stays open a
 * reminder is allowed only after {@see REALERT_COOLDOWN_HOURS}. The first
 * successful refresh closes the episode with an all-clear notification.
 */
class CheckZioBrowserReleaseFreshness extends Command
{
    protected $signature = 'zio-browser:check-freshness
                            {--force : Re-send the alert even if the open episode was already alerted}';

    protected $description = 'Alert ops admins when the cached SayZio Browser release has not refreshed successfully for over a day (download links going stale), with an all-clear once a refresh succeeds.';

    /** Alert once refreshes have been failing continuously this long. */
    public const STALE_AFTER_HOURS = 24;

    /** Remind about a still-open episode at most this often. */
    public const REALERT_COOLDOWN_HOURS = 24;

    public function handle(): int
    {
        $state = self::healthState();

        $failingSince = self::parseTime($state['failing_since'] ?? null);
        $alerting     = ! empty($state['alerting']);

        // Healthy (no open failure streak): close any open episode.
        if ($failingSince === null) {
            if ($alerting) {
                $this->dispatchRecovery($state);
                unset($state['alerting'], $state['last_sent_at']);
                self::putHealthState($state);
                $this->info('Refresh recovered — all-clear dispatched.');
            } else {
                $this->info('Zio Browser release refreshes are healthy — nothing to do.');
            }

            return self::SUCCESS;
        }

        $staleHours = (int) floor($failingSince->diffInMinutes(now()) / 60);

        if (now()->lessThan($failingSince->copy()->addHours(self::STALE_AFTER_HOURS))) {
            $this->info("Refreshes failing since {$failingSince->toIso8601String()} but under the "
                . self::STALE_AFTER_HOURS . 'h threshold — not alerting yet.');

            return self::SUCCESS;
        }

        // Loud marker every run so log-based alerting catches it too.
        Log::error(
            '::1inme:: ZIO BROWSER DOWNLOAD LINKS GOING STALE — release refreshes have been failing '
            . "continuously for ~{$staleHours}h; the /download page is serving the last-known release."
        );

        $shouldSend = $this->option('force')
            || ! $alerting
            || $this->cooldownExpired($state['last_sent_at'] ?? null);

        if (! $shouldSend) {
            $this->info("Still stale (~{$staleHours}h) — episode already alerted, not re-sending.");

            return self::SUCCESS;
        }

        $this->dispatchAlert($state, $failingSince, $staleHours);

        $state['alerting']     = true;
        $state['last_sent_at'] = now()->toIso8601String();
        self::putHealthState($state);

        return self::SUCCESS;
    }

    /**
     * Cheap banner seed for the admin dashboard: the currently open staleness
     * episode, or null when healthy. Never throws (a missing app_settings
     * table on a fresh env must not break the dashboard).
     *
     * @return array{failing_since: ?string, last_success_at: ?string, last_error: ?string, version: ?string}|null
     */
    public static function openEpisode(): ?array
    {
        try {
            $state = self::healthState();
            if (empty($state['alerting'])) {
                return null;
            }

            $release = ZioBrowserRelease::current();

            return [
                'failing_since'   => $state['failing_since'] ?? null,
                'last_success_at' => $state['last_success_at'] ?? null,
                'last_error'      => $state['last_error'] ?? null,
                'version'         => isset($release['version']) ? (string) $release['version'] : null,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────

    /** @param array<string,mixed> $state */
    private function dispatchAlert(array $state, Carbon $failingSince, int $staleHours): void
    {
        $admins  = $this->admins();
        $url     = $this->panelUrl();
        $release = ZioBrowserRelease::current();
        $version = (string) ($release['version'] ?? 'unknown');

        $lastSuccess = self::parseTime($state['last_success_at'] ?? null);
        $lastError   = trim((string) ($state['last_error'] ?? ''));

        $subject = 'SayZio Browser download links are going stale';

        $body = "The scheduled refresh of the SayZio Browser release (zio-browser:refresh-release) has been failing "
              . "continuously for about {$staleHours} hours (since {$failingSince->toIso8601String()}). "
              . "The public /download page keeps serving the last-known release (currently v{$version}), so visitors "
              . "still get working installer links — but they will fall further behind every release that ships.\n\n"
              . 'Last successful refresh: '
              . ($lastSuccess ? $lastSuccess->toIso8601String() : 'never recorded') . "\n"
              . ($lastError !== '' ? "Last error: {$lastError}\n" : '')
              . "\nLikely causes: GitHub API rate limiting, a renamed release tag (expected prefix '"
              . ZioBrowserRelease::TAG_PREFIX . "'), or renamed installer assets (the fetch requires the macOS arm64/x64 "
              . ".dmg files and the Windows .exe). Check the latest release on GitHub ('" . ZioBrowserRelease::REPO . "'), "
              . 'then re-run the job from Admin > Scheduled Jobs (Run now). You will get an all-clear once a refresh succeeds.';

        $inApp  = $this->fanOutInApp($admins, 'zio_browser_release_stale', $subject, $body, $url, [
            'failing_since' => $failingSince->toIso8601String(),
            'stale_hours'   => $staleHours,
            'version'       => $version,
        ]);
        $emails = $this->fanOutEmail($admins, $subject, $body, $url);

        try {
            InternalAlertDispatcher::send(
                $subject,
                "zio-browser release refreshes failing for ~{$staleHours}h; /download serving last-known v{$version}. "
                . 'Re-run zio-browser:refresh-release from Admin > Scheduled Jobs.',
                'error',
                ['Failing since' => $failingSince->toIso8601String(), 'Serving version' => $version]
            );
        } catch (\Throwable $e) {
            Log::warning('zio-browser staleness webhook alert failed: ' . $e->getMessage());
        }

        $this->error("Download links stale (~{$staleHours}h without a successful refresh).");
        $this->info("Alert dispatched — in-app: {$inApp}, email: {$emails}.");
    }

    /** @param array<string,mixed> $state */
    private function dispatchRecovery(array $state): void
    {
        $admins  = $this->admins();
        $url     = $this->panelUrl();
        $release = ZioBrowserRelease::current();
        $version = (string) ($release['version'] ?? 'unknown');

        $subject = 'SayZio Browser download links are fresh again';

        $body = "Good news — the SayZio Browser release refresh succeeded again after a run of failures. "
              . "The /download page is now serving the latest release (v{$version}).\n\n"
              . 'No further action needed. You will be alerted again if refreshes start failing for over '
              . self::STALE_AFTER_HOURS . ' hours.';

        $inApp  = $this->fanOutInApp($admins, 'zio_browser_release_recovered', $subject, $body, $url, [
            'version' => $version,
        ]);
        $emails = $this->fanOutEmail($admins, $subject, $body, $url);

        try {
            InternalAlertDispatcher::send($subject, "Recovered: /download now serving v{$version}.", 'success');
        } catch (\Throwable $e) {
            Log::warning('zio-browser staleness webhook recovery alert failed: ' . $e->getMessage());
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
                Log::warning("zio-browser staleness in-app alert failed for user {$u->id}: " . $e->getMessage());
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
                Log::warning("zio-browser staleness alert email to {$email} failed: " . $e->getMessage());
            }
        }

        return $sent;
    }

    private function cooldownExpired($lastSentAt): bool
    {
        $sent = self::parseTime($lastSentAt);

        return $sent === null || $sent->lessThanOrEqualTo(now()->subHours(self::REALERT_COOLDOWN_HOURS));
    }

    private static function parseTime($value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @return array<string,mixed> */
    private static function healthState(): array
    {
        try {
            $state = AppSetting::get(ZioBrowserRelease::HEALTH_KEY, []);

            return is_array($state) ? $state : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @param array<string,mixed> $state */
    private static function putHealthState(array $state): void
    {
        try {
            AppSetting::put(ZioBrowserRelease::HEALTH_KEY, $state);
        } catch (\Throwable $e) {
            Log::warning('zio-browser staleness state write failed: ' . $e->getMessage());
        }
    }
}
