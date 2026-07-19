<?php

namespace App\Services\Integrations;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proactive admin alerting for the GitHub push credential going stale.
 *
 * Code is mirrored to GitHub (config('services.github.repo')) using a
 * fine-grained personal access token managed at Admin > Integrations >
 * GitHub Token (with the GITHUB_TOKEN env secret as fallback).
 * Fine-grained tokens expire (the current one around mid-October 2026);
 * when that happens every "push to GitHub after publishing" step fails
 * with an auth error and the repo silently drifts behind the workspace.
 *
 * This service closes the loop, mirroring the StorageHealthAlerts pattern:
 * a scheduled `github:check-token` command makes a lightweight
 * authenticated GitHub API call and alerts ops admins (in-app + email +
 * best-effort Slack/Discord) when:
 *   - the token is missing entirely,
 *   - GitHub rejects it (expired/revoked/insufficient scope), or
 *   - it is valid but expires within WARN_DAYS (GitHub reports the expiry
 *     via the `github-authentication-token-expiration` response header on
 *     fine-grained tokens).
 * An all-clear is sent once a previously-broken token works again with a
 * comfortable expiry. Transient network / GitHub 5xx errors are treated as
 * inconclusive and never alert.
 *
 * Dedup/cooldown state lives in `app_settings` under `github_token_health`:
 *   - alerting      — true while a broken/expiring episode is open
 *   - last_sent_at  — ISO-8601 of the last alert (cooldown)
 *   - last_status   — the status at the last alert
 *   - last_probe    — last probe outcome {status,detail,expires_at,checked_at,source}
 *                     shared by the scheduled check and the admin "Verify token"
 *                     button, rendered on the GitHub Token admin page.
 */
class GitHubTokenHealth
{
    public const STATE_KEY = 'github_token_health';

    /** Warn this many days before the token's reported expiry. */
    public const WARN_DAYS = 14;

    /** Don't re-alert for the same open episode more often than this. */
    public const COOLDOWN_HOURS = 20;

    /**
     * Full check: probe the token against the GitHub API, alert when it is
     * missing / rejected / near expiry (cooldown-guarded), and send the
     * all-clear when a previously-open episode has recovered.
     *
     * @return array{status:string,detail:string,expires_at:?string,action:string}
     */
    public static function check(bool $force = false): array
    {
        $probe = self::probe();
        self::recordProbe($probe, 'scheduled');

        if ($probe['status'] === 'inconclusive') {
            // Network hiccup or GitHub outage — say nothing, try again next run.
            Log::warning('github-token-health probe inconclusive: ' . $probe['detail']);
            return $probe + ['action' => 'none'];
        }

        if ($probe['status'] === 'ok') {
            if (self::state('alerting', false)) {
                self::dispatchRecovery($probe);
                return $probe + ['action' => 'recovery_sent'];
            }
            return $probe + ['action' => 'none'];
        }

        // missing | rejected | expiring — loud marker for log-based alerting.
        Log::error('::1inme:: GITHUB PUSH TOKEN ' . strtoupper($probe['status']) . ' — ' . $probe['detail']
            . ' Pushes to GitHub will fail (or soon will) and the repo will drift behind the workspace.');

        if (! $force && self::withinCooldown()) {
            return $probe + ['action' => 'cooldown'];
        }

        self::dispatchAlert($probe);
        return $probe + ['action' => 'alert_sent'];
    }

    /**
     * Lightweight authenticated probe of the configured repo.
     *
     * @return array{status:string,detail:string,expires_at:?string}
     */
    public static function probe(): array
    {
        $token = (string) config('services.github.token', '');
        $repo  = (string) config('services.github.repo', '');

        if ($repo === '') {
            return ['status' => 'inconclusive', 'detail' => 'No GitHub repo configured (services.github.repo).', 'expires_at' => null];
        }
        if ($token === '') {
            return [
                'status'     => 'missing',
                'detail'     => 'No GitHub token is configured — pushes to ' . $repo . ' cannot authenticate. Add one at Admin > Integrations > GitHub Token.',
                'expires_at' => null,
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization'        => 'Bearer ' . $token,
                'Accept'               => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent'           => 'sayzio-token-health',
            ])->timeout(15)->get('https://api.github.com/repos/' . $repo);
        } catch (\Throwable $e) {
            return ['status' => 'inconclusive', 'detail' => 'GitHub API unreachable: ' . $e->getMessage(), 'expires_at' => null];
        }

        $status = $response->status();

        if (in_array($status, [401, 403], true)) {
            return [
                'status'     => 'rejected',
                'detail'     => "GitHub rejected the token (HTTP {$status}) for {$repo} — it has likely expired or been revoked.",
                'expires_at' => null,
            ];
        }
        if ($status === 404) {
            // Fine-grained tokens without access to the repo also see 404.
            return [
                'status'     => 'rejected',
                'detail'     => "GitHub returned 404 for {$repo} — the token no longer grants access to the repository (expired, revoked, or scope removed).",
                'expires_at' => null,
            ];
        }
        if ($status >= 500 || ! $response->successful()) {
            return ['status' => 'inconclusive', 'detail' => "GitHub API returned HTTP {$status}.", 'expires_at' => null];
        }

        // Fine-grained tokens report expiry, e.g. "2026-10-13 06:22:33 UTC".
        $expiresAt = null;
        $header    = $response->header('github-authentication-token-expiration');
        if ($header) {
            try {
                $expiresAt = Carbon::parse($header);
            } catch (\Throwable $e) {
                Log::warning('github-token-health could not parse expiry header: ' . $header);
            }
        }

        if ($expiresAt !== null && $expiresAt->lessThan(now()->addDays(self::WARN_DAYS))) {
            $days = max(0, (int) now()->diffInDays($expiresAt, false));
            return [
                'status'     => 'expiring',
                'detail'     => "The GitHub token for {$repo} expires on " . $expiresAt->toDateString()
                    . " ({$days} day(s) from now). Generate a new fine-grained token and save it at Admin > Integrations > GitHub Token before then.",
                'expires_at' => $expiresAt->toIso8601String(),
            ];
        }

        return [
            'status'     => 'ok',
            'detail'     => "GitHub token authenticates against {$repo}"
                . ($expiresAt ? ' (expires ' . $expiresAt->toDateString() . ').' : '.'),
            'expires_at' => $expiresAt?->toIso8601String(),
        ];
    }

    /**
     * On-demand probe for the admin "Verify token" button: runs the probe,
     * persists the outcome as the last-known health, and returns it. Never
     * sends alerts — that stays with the scheduled check's episode logic.
     *
     * @return array{status:string,detail:string,expires_at:?string}
     */
    public static function verify(): array
    {
        $probe = self::probe();
        self::recordProbe($probe, 'manual');
        return $probe;
    }

    /**
     * The last persisted probe outcome (from the scheduled check or the
     * "Verify token" button), or null if never probed.
     *
     * @return array{status:string,detail:string,expires_at:?string,checked_at:string,source:string}|null
     */
    public static function lastProbe(): ?array
    {
        $probe = self::state('last_probe');
        if (! is_array($probe) || empty($probe['checked_at']) || empty($probe['status'])) {
            return null;
        }
        return [
            'status'     => (string) $probe['status'],
            'detail'     => (string) ($probe['detail'] ?? ''),
            'expires_at' => isset($probe['expires_at']) && $probe['expires_at'] !== '' ? (string) $probe['expires_at'] : null,
            'checked_at' => (string) $probe['checked_at'],
            'source'     => (string) ($probe['source'] ?? 'scheduled'),
        ];
    }

    /**
     * Persist a probe outcome as the last-known health so the admin page
     * can always show current state, not just a transient flash.
     *
     * @param array{status:string,detail:string,expires_at:?string} $probe
     */
    private static function recordProbe(array $probe, string $source): void
    {
        self::putState([
            'last_probe' => [
                'status'     => $probe['status'],
                'detail'     => $probe['detail'],
                'expires_at' => $probe['expires_at'],
                'checked_at' => now()->toIso8601String(),
                'source'     => $source,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────

    /**
     * @param array{status:string,detail:string,expires_at:?string} $probe
     */
    private static function dispatchAlert(array $probe): void
    {
        $admins  = self::admins();
        $repo    = (string) config('services.github.repo', '');
        $subject = match ($probe['status']) {
            'expiring' => 'GitHub push token expires soon — renew it',
            'missing'  => 'GitHub push token missing — pushes cannot authenticate',
            default    => 'GitHub push token no longer works — pushes are failing',
        };
        $body = $probe['detail'] . ' Without a working token, code stops being mirrored to '
            . 'https://github.com/' . $repo . ' after each publish and the repo silently drifts behind the workspace. '
            . 'Fix: create a new fine-grained personal access token (with contents read/write on the repo) at '
            . 'https://github.com/settings/personal-access-tokens and save it in the admin panel at '
            . route('admin.integrations.github.edit') . ' (Admin > Integrations > GitHub Token). '
            . 'As a fallback, the GITHUB_TOKEN environment secret can be updated instead.';

        $inApp  = self::fanOutInApp($admins, 'github_token_unhealthy', $subject, $body, [
            'status'     => $probe['status'],
            'expires_at' => $probe['expires_at'],
        ]);
        $emails = self::fanOutEmail($admins, $subject, $body);

        try {
            InternalAlertDispatcher::send(
                $subject,
                $probe['detail'] . ' Renew the token via Admin > Integrations > GitHub Token (or the GITHUB_TOKEN env secret as fallback).',
                $probe['status'] === 'expiring' ? 'warning' : 'error',
                ['Repo' => $repo, 'Status' => $probe['status']]
            );
        } catch (\Throwable $e) {
            Log::warning('github-token-health webhook alert failed: ' . $e->getMessage());
        }

        self::putState([
            'alerting'     => true,
            'last_sent_at' => now()->toIso8601String(),
            'last_status'  => $probe['status'],
        ]);

        Log::info("github-token-health alert dispatched — in-app: {$inApp}, email: {$emails}.");
    }

    /**
     * @param array{status:string,detail:string,expires_at:?string} $probe
     */
    private static function dispatchRecovery(array $probe): void
    {
        $admins  = self::admins();
        $subject = 'GitHub push token healthy again';
        $body    = 'Good news — the GitHub push token authenticates again. ' . $probe['detail']
            . ' Pushes after publishing will work normally. No further action needed.';

        $inApp  = self::fanOutInApp($admins, 'github_token_healthy', $subject, $body, [
            'expires_at' => $probe['expires_at'],
        ]);
        $emails = self::fanOutEmail($admins, $subject, $body);

        try {
            InternalAlertDispatcher::send($subject, $body, 'success');
        } catch (\Throwable $e) {
            Log::warning('github-token-health webhook recovery alert failed: ' . $e->getMessage());
        }

        self::putState([
            'alerting'     => false,
            'recovered_at' => now()->toIso8601String(),
        ]);

        Log::info("github-token-health recovery dispatched — in-app: {$inApp}, email: {$emails}.");
    }

    private static function admins()
    {
        return User::query()->withPermission('user.ops_alerts.receive')->get();
    }

    /**
     * @param iterable $admins
     * @param array<string,mixed> $extra
     */
    private static function fanOutInApp($admins, string $type, string $subject, string $body, array $extra): int
    {
        $url       = 'https://github.com/' . config('services.github.repo', '');
        $delivered = 0;
        foreach ($admins as $u) {
            try {
                UserNotification::create([
                    'user_id' => $u->id,
                    'type'    => $type,
                    'data'    => array_merge([
                        'subject'    => $subject,
                        'body'       => $body,
                        'message'    => $body,
                        'url'        => $url,
                        'target_url' => $url,
                    ], $extra),
                    'created_at' => now(),
                ]);
                $delivered++;
            } catch (\Throwable $e) {
                Log::warning("github-token-health in-app alert failed for user {$u->id}: " . $e->getMessage());
            }
        }
        return $delivered;
    }

    /**
     * @param iterable $admins
     */
    private static function fanOutEmail($admins, string $subject, string $body): int
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
                    'body'    => $body,
                    'format'  => 'text',
                ]);
                $sent++;
            } catch (\Throwable $e) {
                Log::warning("github-token-health alert email to {$email} failed: " . $e->getMessage());
            }
        }
        return $sent;
    }

    private static function withinCooldown(): bool
    {
        if (! self::state('alerting', false)) {
            return false;
        }

        $lastSent = self::state('last_sent_at');
        if (! $lastSent) {
            return false;
        }
        try {
            return Carbon::parse($lastSent)->greaterThan(now()->subHours(self::COOLDOWN_HOURS));
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function state(string $key, $default = null)
    {
        $all = AppSetting::get(self::STATE_KEY, []);
        return is_array($all) ? ($all[$key] ?? $default) : $default;
    }

    /**
     * @param array<string,mixed> $patch
     */
    private static function putState(array $patch): void
    {
        try {
            $all = AppSetting::get(self::STATE_KEY, []);
            $all = is_array($all) ? $all : [];
            AppSetting::put(self::STATE_KEY, array_merge($all, $patch));
        } catch (\Throwable $e) {
            Log::warning('github-token-health state write failed: ' . $e->getMessage());
        }
    }
}
