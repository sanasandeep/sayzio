<?php

namespace App\Console\Commands;

use App\Modules\Admin\Models\AppSetting;
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
 * the automated safety net for that drift class across the curated manifest in
 * {@see ExpectedSchemaHealth::EXPECTED}.
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
            // DB unreachable or probe failed — don't alert on a transient error.
            $this->warn('Could not probe expected schema: ' . ($report['error'] ?? 'unknown'));
            return self::SUCCESS;
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
                Mail::raw($body . "\n\n" . $url, function ($m) use ($email, $subject) {
                    $m->to($email)->subject($subject);
                });
                $sent++;
            } catch (\Throwable $e) {
                Log::warning("expected-schema-health alert email to {$email} failed: " . $e->getMessage());
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
