<?php

namespace App\Console\Commands;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\WorkspaceColumnHealth;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Proactively detect (and optionally repair) workspace-scoping columns that are
 * missing from the live DB even though the migration that should have added them
 * is recorded as "Ran", and alert ops admins (in-app + email) before users hit
 * SQLSTATE[42703] 500s.
 *
 * Context: {@see \App\Modules\Common\Support\SchemaHealth} (db:check-pending-migrations)
 * only diffs migration *files* against the `migrations` table, so it is blind to
 * an interrupted run that committed its `migrations` row but never landed an
 * `ALTER TABLE ... ADD workspace_id`. That exact failure already bit
 * `form_submissions`; this command is the automated safety net for the same
 * failure class across every workspace-scoped table.
 *
 * Mirrors {@see CheckTemplateDesignHealth}. Dedup / cooldown state lives in
 * `app_settings` under the `workspace_column_health.*` keys:
 *   - workspace_column_health.alerting     — true while a missing-column episode is open
 *   - workspace_column_health.last_sent_at — ISO-8601 of the last alert (cooldown)
 *   - workspace_column_health.last_count   — missing-table count at the last alert
 *   - workspace_column_health.last_refs    — sorted "table:cols" refs at last alert
 *
 * The cooldown stops a frequent cadence from spamming admins, but a *change* in
 * the set of missing columns bypasses the cooldown so a newly-discovered gap is
 * surfaced promptly. --force bypasses the cooldown entirely; --repair applies the
 * idempotent guarded add+backfill in place before re-checking.
 */
class CheckWorkspaceColumns extends Command
{
    protected $signature = 'db:check-workspace-columns
                            {--repair : Add and backfill any missing columns in place before re-checking}
                            {--force : Bypass the cooldown window and re-send even if recently alerted}';

    protected $description = 'Detect (and optionally repair) missing workspace_id/created_by_user_id columns and alert admins.';

    /** Don't re-alert for the same unchanged missing set more often than this. */
    private const COOLDOWN_HOURS = 6;

    public function handle(): int
    {
        if ($this->option('repair')) {
            $result = WorkspaceColumnHealth::repair();
            $addedTables = array_keys($result['added']);
            if (! empty($addedTables)) {
                $this->info('Repaired columns on: ' . implode(', ', array_map(
                    fn ($t) => $t . ' (' . implode(', ', $result['added'][$t]) . ')',
                    $addedTables
                )));
            } else {
                $this->info('Nothing to repair — all workspace columns already present.');
            }
        }

        $report = WorkspaceColumnHealth::compute();

        // Refresh the cached report so the dashboard banner reflects reality
        // immediately after this run.
        WorkspaceColumnHealth::flush();

        if (! ($report['available'] ?? false)) {
            // DB unreachable or probe failed — don't alert on a transient error.
            $this->warn('Could not probe workspace columns: ' . ($report['error'] ?? 'unknown'));
            return self::SUCCESS;
        }

        $missing = $report['missing'];
        $count   = count($missing);

        if ($count === 0) {
            $this->info("All {$report['scanned']} workspace-scoped table(s) have their columns — no gaps.");
            // Recovery: if we previously alerted, send an all-clear.
            if ($this->state('alerting', false)) {
                $this->dispatchRecovery((int) $this->state('last_count', 0));
            }
            return self::SUCCESS;
        }

        // Loud marker so log-based alerting catches it regardless of cooldown.
        Log::error(
            "::1inme:: WORKSPACE COLUMNS MISSING — {$count} table(s) are missing a workspace_id/created_by_user_id "
            . 'column despite their migration being logged as ran; BelongsToWorkspace-scoped queries will 500. Run '
            . '`php artisan db:check-workspace-columns --repair`. Missing: '
            . implode(', ', array_map([WorkspaceColumnHealth::class, 'ref'], $missing))
        );

        $this->error("Missing workspace columns — {$count} table(s): " . implode(', ', array_map(
            fn ($r) => $r['table'] . ' (' . implode(', ', $r['columns']) . ')',
            $missing
        )));

        // Cooldown — skip the fan-out if we alerted recently AND the missing set
        // hasn't changed since (unless --force). A newly-missing column bypasses
        // the cooldown so it isn't hidden for up to COOLDOWN_HOURS.
        $refs       = $this->refsOf($missing);
        $lastRefs   = (array) $this->state('last_refs', []);
        $setChanged = $refs !== $lastRefs;
        $lastSent   = $this->state('last_sent_at');

        if (! $this->option('force') && ! $setChanged && $lastSent) {
            try {
                $lastSentAt = Carbon::parse($lastSent);
                if ($lastSentAt->greaterThan(now()->subHours(self::COOLDOWN_HOURS))) {
                    $this->info("Within cooldown window (last alert {$lastSentAt->diffForHumans()}), missing set unchanged — not re-sending.");
                    return self::SUCCESS;
                }
            } catch (\Throwable $e) {
                // Malformed timestamp — fall through and re-alert; the write
                // below heals the value.
            }
        }

        $this->dispatchAlert($missing, $refs);
        return self::SUCCESS;
    }

    /**
     * @param array<int,array{table:string,columns:array<int,string>}> $missing
     * @param array<int,string> $refs
     */
    private function dispatchAlert(array $missing, array $refs): void
    {
        $count   = count($missing);
        $admins  = $this->admins();
        $url     = $this->dashboardUrl();
        $subject = "Workspace columns missing: {$count} table(s) affected";

        $lines = [];
        foreach (array_slice($missing, 0, 15) as $r) {
            $lines[] = $r['table'] . ' — missing ' . implode(', ', $r['columns']);
        }
        if ($count > 15) {
            $lines[] = '… and ' . ($count - 15) . ' more.';
        }

        $body = "{$count} workspace-scoped table(s) are missing a workspace_id and/or created_by_user_id column in "
              . "the live database, even though the migration that should have added them is recorded as applied. "
              . "This is an interrupted/half-applied migration (the same class of failure that hit form_submissions) — "
              . "any page that runs a workspace-scoped query against these tables will return a 500 error. "
              . "Run `php artisan db:check-workspace-columns --repair` (or `php artisan migrate --force`) against "
              . "production to add and backfill the missing columns.\n\n"
              . implode("\n", $lines);

        $inApp = $this->fanOutInApp($admins, 'workspace_columns_missing', $subject, $body, $url, [
            'missing_count' => $count,
            'missing'       => array_slice($missing, 0, 25),
        ]);
        $emails = $this->fanOutEmail($admins, $subject, $body, $url);

        $this->putState([
            'alerting'     => true,
            'last_sent_at' => now()->toIso8601String(),
            'last_count'   => $count,
            'last_refs'    => $refs,
        ]);

        $this->info("Alert dispatched — in-app: {$inApp}, email: {$emails}.");
    }

    private function dispatchRecovery(int $previousCount): void
    {
        $admins  = $this->admins();
        $url     = $this->dashboardUrl();
        $subject = 'Workspace columns restored';
        $body    = "Good news — every workspace-scoped table has its workspace_id/created_by_user_id column again"
                 . ($previousCount > 0 ? " (was {$previousCount} table(s) affected)." : '.')
                 . ' No further action needed.';

        $inApp  = $this->fanOutInApp($admins, 'workspace_columns_ok', $subject, $body, $url, []);
        $emails = $this->fanOutEmail($admins, $subject, $body, $url);

        $this->putState([
            'alerting'     => false,
            'recovered_at' => now()->toIso8601String(),
            'last_count'   => 0,
            'last_refs'    => [],
        ]);

        $this->info("Recovery dispatched — in-app: {$inApp}, email: {$emails}.");
    }

    /**
     * Sorted "table:cols" refs for the current missing set, used to detect when
     * the set changes between runs.
     *
     * @param array<int,array{table:string,columns:array<int,string>}> $missing
     * @return array<int,string>
     */
    private function refsOf(array $missing): array
    {
        $refs = array_map([WorkspaceColumnHealth::class, 'ref'], $missing);
        sort($refs);
        return array_values($refs);
    }

    /**
     * Operators who opted in to operational alerts. Mirrors the audience used by
     * the other ops commands (schema health, template design health).
     */
    private function admins()
    {
        return User::query()->withPermission('user.ops_alerts.receive')->get();
    }

    private function dashboardUrl(): string
    {
        try {
            return \App\Modules\Common\Support\PlatformHosts::outboundUrl(route('admin.dashboard'));
        } catch (\Throwable $e) {
            return \App\Modules\Common\Support\PlatformHosts::outboundUrl(url('/admin'));
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
                Log::warning("workspace-column-health in-app alert failed for user {$u->id}: " . $e->getMessage());
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
                Log::warning("workspace-column-health alert email to {$email} failed: " . $e->getMessage());
            }
        }
        return $sent;
    }

    private function state(string $key, $default = null)
    {
        $all = AppSetting::get('workspace_column_health', []);
        return is_array($all) ? ($all[$key] ?? $default) : $default;
    }

    /**
     * @param array<string,mixed> $patch
     */
    private function putState(array $patch): void
    {
        try {
            $all = AppSetting::get('workspace_column_health', []);
            $all = is_array($all) ? $all : [];
            AppSetting::put('workspace_column_health', array_merge($all, $patch));
        } catch (\Throwable $e) {
            Log::warning('workspace-column-health state write failed: ' . $e->getMessage());
        }
    }
}
