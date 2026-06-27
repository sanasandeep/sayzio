<?php

namespace App\Console\Commands;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Services\NotificationService;
use App\Modules\Common\Support\ExpectedSchemaHealth;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Proactively detect critical tables/columns that the application code depends
 * on but that are MISSING from the live DB even though their migration is
 * recorded as "Ran", and alert ops admins (in-app + email) before users hit
 * SQLSTATE[42703] 500s.
 *
 * Context: {@see \App\Modules\Common\Support\SchemaHealth} (db:check-pending-migrations)
 * only diffs migration *files* against the `migrations` table, so it is blind to
 * an already-applied migration that was later *edited* to add new columns —
 * Laravel never re-runs a recorded migration, so those columns silently never
 * land while `migrate:status` still reports 0 pending. That exact failure took
 * the public /creators page down (the 18+ columns on `users`). This command is
 * the automated safety net for that drift class across the full expected schema,
 * which {@see ExpectedSchemaHealth} now derives automatically by replaying the
 * migration files (see {@see \App\Modules\Common\Support\SchemaManifest}) rather
 * than from a hand-maintained list — so drift on ANY column is caught.
 *
 * Mirrors {@see CheckWorkspaceColumns}. Dedup / cooldown state lives in
 * `app_settings` under the `expected_schema_health.*` keys:
 *   - expected_schema_health.alerting     — true while a missing-column episode is open
 *   - expected_schema_health.last_sent_at — ISO-8601 of the last alert (cooldown)
 *   - expected_schema_health.last_count   — missing-table count at the last alert
 *   - expected_schema_health.last_refs    — sorted "table:cols" refs at last alert
 *
 * The cooldown stops a frequent cadence from spamming admins, but a *change* in
 * the set of missing tables/columns bypasses the cooldown so a newly-discovered
 * gap is surfaced promptly. --force bypasses the cooldown entirely.
 *
 * Detector-blind alerting: the expected schema is derived by replaying the
 * migration files ({@see SchemaManifest}). When that replay can't run (e.g.
 * unreadable migration files, a migration that throws fatally under pretend, or
 * the DB is unreachable), {@see ExpectedSchemaHealth::compute()} reports
 * `available => false` rather than a false "all healthy". In that state the
 * safety net is BLIND — drift would go undetected — so this command raises a
 * SEPARATE alert (distinct subject + notification type) so ops know the detector
 * itself is down, not that the schema is out of date. That episode is tracked
 * independently under the `expected_schema_health.blind_*` keys:
 *   - blind_alerting     — true while a detector-blind episode is open
 *   - blind_last_sent_at — ISO-8601 of the last blind alert (cooldown)
 *   - blind_error        — the detector error at the last blind alert (a changed
 *                          error bypasses the cooldown)
 * It sends an all-clear when the detector can run again.
 *
 * --repair adds and backfills any missing columns in place (guarded, idempotent
 * — see {@see ExpectedSchemaHealth::repair()}) so ops can close the drift without
 * shell access to `php artisan migrate --force`. Whole-missing tables still need a
 * full migrate; --repair reports those rather than guessing a schema.
 */
class CheckExpectedColumns extends Command
{
    protected $signature = 'db:check-expected-columns
                            {--repair : Add and backfill any missing columns in place before re-checking}
                            {--force : Bypass the cooldown window and re-send even if recently alerted}';

    protected $description = 'Detect (and optionally repair) missing expected tables/columns (edited-after-applied migration drift) and alert admins.';

    /** Don't re-alert for the same unchanged missing set more often than this. */
    private const COOLDOWN_HOURS = 6;

    public function handle(): int
    {
        if ($this->option('repair')) {
            $result      = ExpectedSchemaHealth::repair();
            $addedTables = array_keys($result['added']);
            if (! empty($addedTables)) {
                $this->info('Repaired columns on: ' . implode(', ', array_map(
                    fn ($t) => $t . ' (' . implode(', ', $result['added'][$t]) . ')',
                    $addedTables
                )));
            } else {
                $this->info('Nothing to repair — all expected columns already present (or only whole tables are missing).');
            }
            if (! empty($result['unrepairable'])) {
                $this->warn('Could not auto-repair (whole table missing — run `php artisan migrate --force`): '
                    . implode(', ', $result['unrepairable']));
            }
        }

        $report = ExpectedSchemaHealth::compute();

        // Refresh the cached report so the dashboard banner / readiness endpoint
        // reflect reality immediately after this run.
        ExpectedSchemaHealth::flush();

        if (! ($report['available'] ?? false)) {
            // The detector itself can't run (migration replay failed / DB
            // unreachable). This is the safety net going BLIND — drift would go
            // undetected — so surface it to ops as a distinct alert rather than
            // silently swallowing it as a "transient" warning.
            $this->handleDetectorBlind((string) ($report['error'] ?? 'unknown'));
            return self::SUCCESS;
        }

        // The detector ran. If a blind episode was open, the safety net is back
        // online — send an all-clear and close it.
        if ($this->state('blind_alerting', false)) {
            $this->dispatchBlindRecovery();
        }

        $missing = $report['missing'];
        $count   = count($missing);

        if ($count === 0) {
            $this->info("All {$report['scanned']} expected table(s) have their columns — no drift.");
            // Recovery: if we previously alerted, send an all-clear.
            if ($this->state('alerting', false)) {
                $this->dispatchRecovery((int) $this->state('last_count', 0));
            }
            return self::SUCCESS;
        }

        // Loud marker so log-based alerting catches it regardless of cooldown.
        Log::error(
            "::1inme:: EXPECTED COLUMNS MISSING — {$count} table(s) are missing a column the code depends on despite "
            . 'their migration being logged as ran (edited-after-applied drift); affected pages will 500. Run '
            . '`php artisan migrate --force`. Missing: '
            . implode(', ', array_map([ExpectedSchemaHealth::class, 'ref'], $missing))
        );

        $this->error("Missing expected schema — {$count} table(s): " . implode(', ', array_map(
            fn ($r) => $r['table'] . ' (' . ($r['table_missing'] ? 'table missing' : implode(', ', $r['columns'])) . ')',
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
     * @param array<int,array{table:string,table_missing:bool,columns:array<int,string>}> $missing
     * @param array<int,string> $refs
     */
    private function dispatchAlert(array $missing, array $refs): void
    {
        $count   = count($missing);
        $admins  = $this->admins();
        $url     = $this->dashboardUrl();
        $subject = "Database schema drift: {$count} table(s) missing expected columns";

        $lines = [];
        foreach (array_slice($missing, 0, 15) as $r) {
            $what = $r['table_missing'] ? 'entire table missing' : 'missing ' . implode(', ', $r['columns']);
            $lines[] = $r['table'] . ' — ' . $what;
        }
        if ($count > 15) {
            $lines[] = '… and ' . ($count - 15) . ' more.';
        }

        $body = "{$count} table(s) the application code depends on are missing an expected column (or the whole table) in "
              . "the live database, even though the migration that should have added them is recorded as applied. "
              . "This is edited-after-applied migration drift — a recorded migration was later changed to add columns, "
              . "so Laravel never re-applied them and `migrate:status` still shows 0 pending. Any page that reads these "
              . "columns will return a 500 error. Run `php artisan migrate --force` against production to apply the "
              . "guarded additive migrations that backfill them.\n\n"
              . implode("\n", $lines);

        $inApp = $this->fanOutInApp($admins, 'expected_columns_missing', $subject, $body, $url, [
            'missing_count' => $count,
            'missing'       => array_slice($missing, 0, 25),
        ]);
        $emails = $this->fanOutEmail($admins, $subject, $body, $url);

        // Push the same alert to on-call admins' phones so they don't have to
        // be looking at the admin screen to learn the schema has drifted. The
        // push deliberately carries NO web `url` so the mobile app deep-links to
        // its native /admin dashboard (where the warning + Repair action live)
        // rather than opening the web dashboard in a browser. Cooldown / set-
        // change dedup is already enforced by the caller (handle()), so this
        // only fires once per drift episode like the in-app + email fan-out.
        $pushTitle = 'Database schema drift detected';
        $pushBody  = $count === 1
            ? '1 table is missing an expected column — open the admin dashboard to repair before users hit errors.'
            : "{$count} tables are missing expected columns — open the admin dashboard to repair before users hit errors.";
        $pushes = $this->fanOutPush($admins, 'expected_columns_missing', $pushTitle, $pushBody, [
            'missing_count' => $count,
        ]);

        $this->putState([
            'alerting'     => true,
            'last_sent_at' => now()->toIso8601String(),
            'last_count'   => $count,
            'last_refs'    => $refs,
        ]);

        $this->info("Alert dispatched — in-app: {$inApp}, email: {$emails}, push: {$pushes}.");
    }

    private function dispatchRecovery(int $previousCount): void
    {
        $admins  = $this->admins();
        $url     = $this->dashboardUrl();
        $subject = 'Database schema drift resolved';
        $body    = "Good news — every expected table has its required columns again"
                 . ($previousCount > 0 ? " (was {$previousCount} table(s) affected)." : '.')
                 . ' No further action needed.';

        $inApp  = $this->fanOutInApp($admins, 'expected_columns_ok', $subject, $body, $url, []);
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
     * The drift detector itself could not run (migration replay failed / DB
     * unreachable), so it cannot tell whether the schema has drifted. Log a loud
     * marker and — cooldown-guarded, with a changed-error bypass — alert ops that
     * the safety net is blind, kept distinct from the "schema out of date" alert.
     */
    private function handleDetectorBlind(string $error): void
    {
        // Loud marker so log-based alerting catches it the same way as the
        // missing-columns and deploy markers.
        Log::error(
            '::1inme:: SCHEMA DRIFT DETECTOR BLIND — the expected-schema drift detector could not run '
            . '(migration replay failed), so edited-after-applied column drift is going UNDETECTED until '
            . 'this is fixed. Investigate the migration set / DB connectivity. Detector error: ' . $error
        );

        $this->error('Schema drift detector is blind — could not derive the expected schema: ' . $error);

        // Cooldown — skip the fan-out if we alerted recently for the SAME error
        // (a changed error message bypasses the cooldown), unless --force.
        $lastError  = (string) $this->state('blind_error', '');
        $errChanged = $error !== $lastError;
        $lastSent   = $this->state('blind_last_sent_at');

        if (! $this->option('force') && ! $errChanged && $lastSent) {
            try {
                $lastSentAt = Carbon::parse($lastSent);
                if ($lastSentAt->greaterThan(now()->subHours(self::COOLDOWN_HOURS))) {
                    $this->info("Within cooldown window (last detector-blind alert {$lastSentAt->diffForHumans()}), error unchanged — not re-sending.");
                    return;
                }
            } catch (\Throwable $e) {
                // Malformed timestamp — fall through and re-alert; the write
                // below heals the value.
            }
        }

        $this->dispatchBlindAlert($error);
    }

    private function dispatchBlindAlert(string $error): void
    {
        $admins  = $this->admins();
        $url     = $this->dashboardUrl();
        $subject = 'Database schema drift detector cannot run';
        $body    = "The Sayzio schema drift detector could not run. It derives the expected database schema by "
                 . "replaying the migration files, but that replay failed — so it cannot tell whether the live "
                 . "database has drifted. Edited-after-applied migration drift (a recorded migration later changed "
                 . "to add columns, which Laravel never re-applies) will go UNDETECTED until this is fixed. This is "
                 . "the safety net going blind, NOT a confirmed schema problem. Investigate the migration set and "
                 . "database connectivity on the host running the scheduler.\n\n"
                 . "Detector error: {$error}";

        $inApp = $this->fanOutInApp($admins, 'expected_columns_detector_blind', $subject, $body, $url, [
            'detector_error' => $error,
        ]);
        $emails = $this->fanOutEmail($admins, $subject, $body, $url);

        $this->putState([
            'blind_alerting'     => true,
            'blind_last_sent_at' => now()->toIso8601String(),
            'blind_error'        => $error,
        ]);

        $this->info("Detector-blind alert dispatched — in-app: {$inApp}, email: {$emails}.");
    }

    private function dispatchBlindRecovery(): void
    {
        $admins  = $this->admins();
        $url     = $this->dashboardUrl();
        $subject = 'Database schema drift detector is back online';
        $body    = "Good news — the Sayzio schema drift detector can run again and is back to actively checking the "
                 . "live database for edited-after-applied migration drift. No further action needed.";

        $inApp  = $this->fanOutInApp($admins, 'expected_columns_detector_ok', $subject, $body, $url, []);
        $emails = $this->fanOutEmail($admins, $subject, $body, $url);

        $this->putState([
            'blind_alerting'     => false,
            'blind_recovered_at' => now()->toIso8601String(),
            'blind_error'        => '',
        ]);

        $this->info("Detector-online recovery dispatched — in-app: {$inApp}, email: {$emails}.");
    }

    /**
     * Sorted "table:cols" refs for the current missing set, used to detect when
     * the set changes between runs.
     *
     * @param array<int,array{table:string,table_missing:bool,columns:array<int,string>}> $missing
     * @return array<int,string>
     */
    private function refsOf(array $missing): array
    {
        $refs = array_map([ExpectedSchemaHealth::class, 'ref'], $missing);
        sort($refs);
        return array_values($refs);
    }

    /**
     * Operators who opted in to operational alerts. Mirrors the audience used by
     * the other ops commands (schema health, workspace columns, template design).
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
                Log::warning("expected-schema-health in-app alert failed for user {$u->id}: " . $e->getMessage());
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
                Log::warning("expected-schema-health alert email to {$email} failed: " . $e->getMessage());
            }
        }
        return $sent;
    }

    /**
     * Fan a push notification out to on-call admins' phones via the shared
     * Expo transport. Delivery honors each admin's per-type push preference
     * and is wholly best-effort (NotificationService::pushToUser never throws),
     * so a dead token or network hiccup can't stop the command. Passing no
     * `url` in $data lets the mobile app deep-link to its native /admin screen
     * by type instead of opening the web dashboard in a browser.
     *
     * @param  iterable  $admins
     * @param  array<string,mixed>  $data
     */
    private function fanOutPush($admins, string $type, string $title, string $body, array $data): int
    {
        $service = app(NotificationService::class);
        $sent    = 0;
        foreach ($admins as $u) {
            try {
                $sent += $service->pushToUser($u, $type, $title, $body, $data);
            } catch (\Throwable $e) {
                Log::warning("expected-schema-health push alert failed for user {$u->id}: " . $e->getMessage());
            }
        }
        return $sent;
    }

    private function state(string $key, $default = null)
    {
        $all = AppSetting::get('expected_schema_health', []);
        return is_array($all) ? ($all[$key] ?? $default) : $default;
    }

    /**
     * @param array<string,mixed> $patch
     */
    private function putState(array $patch): void
    {
        try {
            $all = AppSetting::get('expected_schema_health', []);
            $all = is_array($all) ? $all : [];
            AppSetting::put('expected_schema_health', array_merge($all, $patch));
        } catch (\Throwable $e) {
            Log::warning('expected-schema-health state write failed: ' . $e->getMessage());
        }
    }
}
