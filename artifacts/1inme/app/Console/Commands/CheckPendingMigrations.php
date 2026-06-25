<?php

namespace App\Console\Commands;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\SchemaHealth;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Proactively detect an out-of-date database schema and alert admins before
 * users hit 500s.
 *
 * Context: the production deploy runs `php artisan migrate --force` but
 * deliberately keeps serving when it fails (to avoid full downtime), logging a
 * loud "::1inme:: DEPLOY MIGRATION FAILED" marker to stderr. That marker only
 * helps if a human is reading the deploy log — so this scheduled check is the
 * automated safety net: it runs the equivalent of `migrate:status`, and when
 * any migration is pending it fans the warning out to admins (in-app + email)
 * and re-logs a loud marker, then sends an all-clear once the schema is back in
 * sync.
 *
 * Dedup / cooldown state lives in `app_settings` (so it survives deploys and
 * multiple schedulers) under the `schema_health.*` keys:
 *   - schema_health.alerting        — true while an out-of-date episode is open
 *   - schema_health.last_sent_at    — ISO-8601 of the last alert (cooldown)
 *   - schema_health.last_count      — pending count at the last alert
 *
 * The cooldown stops a per-hour cadence from spamming admins; --force bypasses
 * it for manual runs.
 */
class CheckPendingMigrations extends Command
{
    protected $signature = 'db:check-pending-migrations
                            {--force : Bypass the cooldown window and re-send even if recently alerted}';

    protected $description = 'Detect an out-of-date DB schema (pending migrations) and alert admins (in-app + email).';

    /** Don't re-alert for the same open episode more often than this. */
    private const COOLDOWN_HOURS = 6;

    public function handle(): int
    {
        $report = SchemaHealth::compute();

        // Refresh the cached report so the dashboard banner / readiness
        // endpoint reflect reality immediately after this run.
        SchemaHealth::flush();

        if (! ($report['available'] ?? false)) {
            // DB unreachable or probe failed — don't alert on a transient
            // error, just note it. A genuinely broken DB surfaces elsewhere.
            $this->warn('Could not determine migration status: ' . ($report['error'] ?? 'unknown'));
            return self::SUCCESS;
        }

        $pending = $report['pending'];
        $count   = count($pending);

        if ($count === 0) {
            $this->info('Schema is up to date — no pending migrations.');
            // Recovery: if we previously alerted, send an all-clear and close
            // the episode.
            if ($this->state('alerting', false)) {
                $this->dispatchRecovery((int) $this->state('last_count', 0));
            }
            return self::SUCCESS;
        }

        // Loud marker mirroring the deploy-step marker, so log-based alerting
        // catches it the same way regardless of where the gap appeared.
        Log::error(
            "::1inme:: SCHEMA OUT OF DATE — {$count} pending migration(s); "
            . 'workspace-backed and other pages may 500 until `php artisan migrate --force` runs. Pending: '
            . implode(', ', $pending)
        );

        $this->error("Schema out of date — {$count} pending migration(s): " . implode(', ', $pending));

        // Cooldown — skip the fan-out if we alerted recently for the same open
        // episode (unless --force).
        $lastSent = $this->state('last_sent_at');
        if (! $this->option('force') && $lastSent) {
            try {
                $lastSentAt = Carbon::parse($lastSent);
                if ($lastSentAt->greaterThan(now()->subHours(self::COOLDOWN_HOURS))) {
                    $this->info("Within cooldown window (last alert {$lastSentAt->diffForHumans()}) — not re-sending.");
                    return self::SUCCESS;
                }
            } catch (\Throwable $e) {
                // Malformed timestamp — fall through and re-alert; the write
                // below heals the value.
            }
        }

        $this->dispatchAlert($pending);
        return self::SUCCESS;
    }

    /**
     * @param array<int,string> $pending
     */
    private function dispatchAlert(array $pending): void
    {
        $count   = count($pending);
        $admins  = $this->admins();
        $url     = $this->dashboardUrl();
        $subject = "Database schema out of date: {$count} pending migration(s)";
        $preview = implode(', ', array_slice($pending, 0, 5)) . ($count > 5 ? ', …' : '');
        $body    = "Sayzio has {$count} pending database migration(s) that have not been applied. "
                 . "This usually means the deploy's `php artisan migrate --force` step failed, leaving the schema "
                 . "incomplete — workspace-backed and other pages may return 500 errors until it's fixed. "
                 . "Run `php artisan migrate --force` against production as soon as possible.\n\n"
                 . "Pending: {$preview}";

        $inApp = $this->fanOutInApp($admins, 'schema_out_of_date', $subject, $body, $url, [
            'pending_count' => $count,
            'pending'       => array_slice($pending, 0, 20),
        ]);
        $emails = $this->fanOutEmail($admins, $subject, $body, $url);

        $this->putState([
            'alerting'     => true,
            'last_sent_at' => now()->toIso8601String(),
            'last_count'   => $count,
        ]);

        $this->info("Alert dispatched — in-app: {$inApp}, email: {$emails}.");
    }

    private function dispatchRecovery(int $previousCount): void
    {
        $admins  = $this->admins();
        $url     = $this->dashboardUrl();
        $subject = 'Database schema back in sync';
        $body    = "Good news — all pending database migrations have been applied and the Sayzio schema is back in sync"
                 . ($previousCount > 0 ? " (was {$previousCount} pending)." : '.')
                 . ' No further action needed.';

        $inApp  = $this->fanOutInApp($admins, 'schema_in_sync', $subject, $body, $url, []);
        $emails = $this->fanOutEmail($admins, $subject, $body, $url);

        $this->putState([
            'alerting'      => false,
            'recovered_at'  => now()->toIso8601String(),
        ]);

        $this->info("Recovery dispatched — in-app: {$inApp}, email: {$emails}.");
    }

    /**
     * Operators who opted in to operational alerts. Mirrors the audience used
     * by the other ops commands (site-assistant cut-offs, image reoptimize).
     */
    private function admins()
    {
        return User::query()->withPermission('user.ops_alerts.receive')->get();
    }

    private function dashboardUrl(): string
    {
        try {
            return route('admin.dashboard');
        } catch (\Throwable $e) {
            return url('/admin');
        }
    }

    /**
     * @param  iterable  $admins
     * @param  array<string,mixed>  $extra
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
                Log::warning("schema-health in-app alert failed for user {$u->id}: " . $e->getMessage());
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
                Mail::raw($body . "\n\n" . $url, function ($m) use ($email, $subject) {
                    $m->to($email)->subject($subject);
                });
                $sent++;
            } catch (\Throwable $e) {
                Log::warning("schema-health alert email to {$email} failed: " . $e->getMessage());
            }
        }
        return $sent;
    }

    private function state(string $key, $default = null)
    {
        $all = AppSetting::get('schema_health', []);
        return is_array($all) ? ($all[$key] ?? $default) : $default;
    }

    /**
     * @param array<string,mixed> $patch
     */
    private function putState(array $patch): void
    {
        try {
            $all = AppSetting::get('schema_health', []);
            $all = is_array($all) ? $all : [];
            AppSetting::put('schema_health', array_merge($all, $patch));
        } catch (\Throwable $e) {
            Log::warning('schema-health state write failed: ' . $e->getMessage());
        }
    }
}
