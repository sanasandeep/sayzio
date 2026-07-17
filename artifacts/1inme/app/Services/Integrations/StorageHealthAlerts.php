<?php

namespace App\Services\Integrations;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Proactive admin alerting for a misconfigured S3 user-content storage
 * backend. Task #3874 made a broken S3 setup fail loudly (RuntimeException
 * on upload + Log::warning at boot) instead of silently degrading to local
 * disk — but an admin still only found out by reading logs or via a user
 * report. This closes the loop: when S3 becomes unconfigured/misconfigured,
 * ops admins get an in-app notification + email (and a best-effort Slack /
 * Discord ping), and an all-clear once it is fixed.
 *
 * Mirrors the SchemaHealth alert pattern (db:check-pending-migrations):
 * dedup/cooldown state lives in `app_settings` under the `storage_health`
 * key so it survives deploys and multiple schedulers:
 *   - alerting      — true while a misconfigured episode is open
 *   - last_sent_at  — ISO-8601 of the last alert (cooldown)
 *   - last_missing  — the missing pieces at the last alert
 *
 * Two triggers share this service:
 *   - PlatformServiceSettings::applyRuntimeConfig() at boot (web requests
 *     only — console/test boots are skipped) via alertFromBoot(), and
 *   - the hourly `storage:check-s3-config` scheduled command via check(),
 *     which also handles the recovery all-clear.
 */
class StorageHealthAlerts
{
    public const STATE_KEY = 'storage_health';

    /** Don't re-alert for the same open episode more often than this. */
    public const COOLDOWN_HOURS = 6;

    /**
     * Full check: alert when misconfigured (cooldown-guarded), send the
     * all-clear when a previously-open episode has recovered.
     *
     * @return array{configured:bool,missing:array<int,string>,action:string}
     */
    public static function check(bool $force = false): array
    {
        $missing    = PlatformServiceSettings::s3MissingPieces();
        $configured = $missing === [];

        if ($configured) {
            if (self::state('alerting', false)) {
                self::dispatchRecovery((array) self::state('last_missing', []));
                return ['configured' => true, 'missing' => [], 'action' => 'recovery_sent'];
            }
            return ['configured' => true, 'missing' => [], 'action' => 'none'];
        }

        // Loud marker so log-based alerting catches it too.
        Log::error(
            '::1inme:: S3 STORAGE MISCONFIGURED — missing ' . implode(', ', $missing)
            . '; user file uploads will fail until an admin fixes this in Admin > Integrations > Storage.'
        );

        if (! $force && self::withinCooldown()) {
            return ['configured' => false, 'missing' => $missing, 'action' => 'cooldown'];
        }

        self::dispatchAlert($missing);
        return ['configured' => false, 'missing' => $missing, 'action' => 'alert_sent'];
    }

    /**
     * Boot-path trigger, called from applyRuntimeConfig() when the effective
     * S3 config is incomplete. Wholly best-effort and cheap on the hot path:
     * skipped for console boots (artisan / scheduler / tests — the scheduled
     * command covers those), and the cooldown check short-circuits before any
     * fan-out work, so at most one web request per cooldown window pays the
     * dispatch cost.
     */
    public static function alertFromBoot(): void
    {
        try {
            if (app()->runningInConsole()) {
                return;
            }
            if (self::withinCooldown()) {
                return;
            }
            $missing = PlatformServiceSettings::s3MissingPieces();
            if ($missing === []) {
                return;
            }
            self::dispatchAlert($missing);
        } catch (\Throwable $e) {
            // Never let alerting break a boot.
            Log::warning('storage-health boot alert failed: ' . $e->getMessage());
        }
    }

    /**
     * Real-time trigger from the public `/storage/{path}` bridge route
     * (`storage.cdn.fallback`). Unlike alertFromBoot(), this fires even when
     * s3MissingPieces() is empty — the S3 disk can throw during URL
     * resolution with a config that *looks* complete (bad credentials,
     * malformed region/bucket, SDK init failure). A broken bridge means ALL
     * user file retrievals 404 and avatars/covers silently degrade, so ops
     * admins must hear about it.
     *
     * Cooldown-guarded via the same shared episode state, so a burst of
     * broken avatar requests costs at most one fan-out per cooldown window.
     * Wholly best-effort: never lets alerting break the (already failing)
     * request.
     */
    public static function alertFromBridge(\Throwable $e): void
    {
        try {
            if (self::withinCooldown()) {
                return;
            }
            $missing = PlatformServiceSettings::s3MissingPieces();
            if ($missing === []) {
                // Config looks complete but the disk still threw — surface
                // the underlying error as the "missing piece" so the alert
                // body/webhook carry an actionable hint.
                $missing = ['URL resolution failed: ' . mb_substr($e->getMessage(), 0, 200)];
            }
            self::dispatchAlert($missing);
        } catch (\Throwable $inner) {
            // Never let alerting break the bridge route.
            Log::warning('storage-health bridge alert failed: ' . $inner->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────

    /**
     * @param array<int,string> $missing
     */
    private static function dispatchAlert(array $missing): void
    {
        $admins  = self::admins();
        $url     = self::storagePageUrl();
        $list    = implode(', ', $missing);
        $subject = 'S3 storage misconfigured — user uploads are failing';
        $body    = 'The S3 user-content storage backend is not fully configured (missing: ' . $list . '). '
                 . 'User content is S3-only — there is no local-disk fallback — so every file upload will fail '
                 . 'with an error until this is fixed. Add the missing values in Admin > Integrations > Storage '
                 . '(or via the AWS_* environment variables).';

        $inApp  = self::fanOutInApp($admins, 'storage_misconfigured', $subject, $body, $url, [
            'missing' => $missing,
        ]);
        $emails = self::fanOutEmail($admins, $subject, $body, $url);

        // Best-effort Slack/Discord ping via the internal alerts webhooks
        // (uncategorised ⇒ always sends when alerts are enabled).
        try {
            InternalAlertDispatcher::send(
                'S3 storage misconfigured',
                'User uploads are failing — the S3 backend is missing: ' . $list . '. Fix in Admin > Integrations > Storage.',
                'error',
                ['Missing' => $list]
            );
        } catch (\Throwable $e) {
            Log::warning('storage-health webhook alert failed: ' . $e->getMessage());
        }

        self::putState([
            'alerting'     => true,
            'last_sent_at' => now()->toIso8601String(),
            'last_missing' => array_values($missing),
        ]);

        Log::info("storage-health alert dispatched — in-app: {$inApp}, email: {$emails}.");
    }

    /**
     * @param array<int,string> $previousMissing
     */
    private static function dispatchRecovery(array $previousMissing): void
    {
        $admins  = self::admins();
        $url     = self::storagePageUrl();
        $subject = 'S3 storage configuration restored';
        $body    = 'Good news — the S3 user-content storage backend is fully configured again'
                 . ($previousMissing !== [] ? ' (was missing: ' . implode(', ', $previousMissing) . ').' : '.')
                 . ' User file uploads should work normally. No further action needed.';

        $inApp  = self::fanOutInApp($admins, 'storage_configured', $subject, $body, $url, []);
        $emails = self::fanOutEmail($admins, $subject, $body, $url);

        try {
            InternalAlertDispatcher::send('S3 storage configuration restored', $body, 'success');
        } catch (\Throwable $e) {
            Log::warning('storage-health webhook recovery alert failed: ' . $e->getMessage());
        }

        self::putState([
            'alerting'     => false,
            'recovered_at' => now()->toIso8601String(),
        ]);

        Log::info("storage-health recovery dispatched — in-app: {$inApp}, email: {$emails}.");
    }

    /**
     * Operators who opted in to operational alerts — same audience as the
     * other ops health commands (schema health, workspace columns, …).
     */
    private static function admins()
    {
        return User::query()->withPermission('user.ops_alerts.receive')->get();
    }

    private static function storagePageUrl(): string
    {
        try {
            return route('admin.integrations.storage.edit');
        } catch (\Throwable $e) {
            return url('/admin/integrations/storage');
        }
    }

    /**
     * @param iterable $admins
     * @param array<string,mixed> $extra
     */
    private static function fanOutInApp($admins, string $type, string $subject, string $body, string $url, array $extra): int
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
                Log::warning("storage-health in-app alert failed for user {$u->id}: " . $e->getMessage());
            }
        }
        return $delivered;
    }

    /**
     * @param iterable $admins
     */
    private static function fanOutEmail($admins, string $subject, string $body, string $url): int
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
                Log::warning("storage-health alert email to {$email} failed: " . $e->getMessage());
            }
        }
        return $sent;
    }

    private static function withinCooldown(): bool
    {
        // The cooldown only guards repeat alerts for the SAME open episode.
        // Once a recovery closed the episode (alerting=false), a fresh
        // misconfiguration must alert immediately — even if the previous
        // alert was sent within the cooldown window.
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
            // Malformed timestamp — treat as expired; the next alert heals it.
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
            Log::warning('storage-health state write failed: ' . $e->getMessage());
        }
    }
}
