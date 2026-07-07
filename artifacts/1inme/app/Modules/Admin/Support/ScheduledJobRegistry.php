<?php

namespace App\Modules\Admin\Support;

use App\Modules\Admin\Models\AppSetting;

/**
 * Central registry of every scheduled (cron) job the platform runs.
 *
 * Job definitions live in per-group files under routes/schedules/*.php — one
 * file per operational group — and this class loads, validates and exposes
 * them. routes/console.php registers the actual Laravel schedule by looping
 * over {@see all()}, so the registry IS the schedule: a job cannot exist in
 * one place without the other (guarded by a lockstep feature test).
 *
 * Each definition:
 *   - key                  Stable identifier (the artisan command's base name,
 *                          e.g. "contacts:sync"; the callback job uses its
 *                          scheduled name). Used for pause state, run history
 *                          rows, and URLs.
 *   - command              Full artisan command incl. flags (defaults to key).
 *                          Mutually exclusive with `callback`.
 *   - callback             "Fully\Qualified\Class@method" for closure jobs.
 *   - description          Operator-friendly purpose line (single source —
 *                          the admin screen and API read this).
 *   - cadence              [method, ...args] applied to the scheduled event
 *                          (e.g. ['hourlyAt', 20]) — cadences are defined
 *                          exactly once, here.
 *   - without_overlapping  Lock expiry in minutes (defaults to Laravel's 1440).
 *   - protected            True for platform-critical jobs that can never be
 *                          paused from the UI/API (billing, queue drain,
 *                          schema-health alerts, partition maintenance).
 *
 * Pause state is persisted in AppSetting `scheduled_jobs.paused` (array of
 * keys) so it survives restarts and is honoured by the scheduler via a
 * ->skip() filter on every non-protected event.
 */
class ScheduledJobRegistry
{
    /** AppSetting key holding the array of paused job keys. */
    public const PAUSED_SETTING = 'scheduled_jobs.paused';

    /** Group slug (= definition filename) => operator-facing label. */
    public const GROUPS = [
        'reminders-digests'      => 'Reminders & Digests',
        'syncing-integrations'   => 'Syncing & Integrations',
        'health-checks'          => 'Health Checks & Alerts',
        'analytics-cleanup'      => 'Analytics & Cleanup',
        'billing-plans'          => 'Billing & Plans',
        'publishing-automation'  => 'Publishing & Automation',
    ];

    /** @var array<string, array<string, mixed>>|null memoized key => definition */
    protected static ?array $jobs = null;

    /**
     * Every job definition, keyed by job key, validated and normalized
     * (group, command default, protected/without_overlapping defaults).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        if (static::$jobs !== null) {
            return static::$jobs;
        }

        $jobs = [];

        foreach (self::GROUPS as $group => $label) {
            $file = base_path('routes/schedules/' . $group . '.php');

            if (! is_file($file)) {
                throw new \RuntimeException("Scheduled job group file missing: routes/schedules/{$group}.php");
            }

            $defs = require $file;

            if (! is_array($defs)) {
                throw new \RuntimeException("routes/schedules/{$group}.php must return an array of job definitions.");
            }

            foreach ($defs as $def) {
                $key = $def['key'] ?? null;

                if (! is_string($key) || $key === '') {
                    throw new \RuntimeException("A job in group '{$group}' is missing its 'key'.");
                }
                if (isset($jobs[$key])) {
                    throw new \RuntimeException("Duplicate scheduled job key '{$key}'.");
                }
                if (empty($def['description']) || ! is_string($def['description'])) {
                    throw new \RuntimeException("Scheduled job '{$key}' is missing its 'description'.");
                }
                if (empty($def['cadence']) || ! is_array($def['cadence']) || ! is_string($def['cadence'][0] ?? null)) {
                    throw new \RuntimeException("Scheduled job '{$key}' needs a 'cadence' => [method, ...args] spec.");
                }
                if (isset($def['command'], $def['callback'])) {
                    throw new \RuntimeException("Scheduled job '{$key}' cannot define both 'command' and 'callback'.");
                }

                $def['group']       = $group;
                $def['group_label'] = $label;
                $def['protected']   = (bool) ($def['protected'] ?? false);

                if (! isset($def['callback'])) {
                    $def['command'] = $def['command'] ?? $key;
                }

                $jobs[$key] = $def;
            }
        }

        return static::$jobs = $jobs;
    }

    /** @return array<string, mixed>|null */
    public static function find(string $key): ?array
    {
        return static::all()[$key] ?? null;
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(static::all());
    }

    /**
     * Definitions grouped for display: group slug => ['label' => …, 'jobs' => defs[]].
     *
     * @return array<string, array{label: string, jobs: array<int, array<string, mixed>>}>
     */
    public static function grouped(): array
    {
        $out = [];

        foreach (self::GROUPS as $group => $label) {
            $out[$group] = ['label' => $label, 'jobs' => []];
        }

        foreach (static::all() as $def) {
            $out[$def['group']]['jobs'][] = $def;
        }

        return $out;
    }

    /**
     * Map used by the run recorder / inspector to resolve a live scheduler
     * event back to its registry key: full command-with-args => key for
     * command jobs, and the scheduled name => key for callback jobs.
     *
     * @return array<string, string>
     */
    public static function commandKeyMap(): array
    {
        $map = [];

        foreach (static::all() as $key => $def) {
            $map[isset($def['callback']) ? $key : $def['command']] = $key;
        }

        return $map;
    }

    // ── Pause / resume (persisted in AppSetting, survives restarts) ─────────

    /** @return array<int, string> currently paused job keys (pruned to known keys) */
    public static function pausedKeys(): array
    {
        try {
            $paused = AppSetting::get(self::PAUSED_SETTING, []);
        } catch (\Throwable $e) {
            // A DB hiccup must never break schedule registration — treat as
            // "nothing paused" rather than blocking every job.
            return [];
        }

        if (! is_array($paused)) {
            return [];
        }

        return array_values(array_intersect(array_map('strval', $paused), static::keys()));
    }

    public static function isPaused(string $key): bool
    {
        return in_array($key, static::pausedKeys(), true);
    }

    /**
     * Pause a job (idempotent). Protected jobs refuse — enforce again at the
     * controller layer for a friendly message; this is the hard backstop.
     */
    public static function pause(string $key): void
    {
        $def = static::find($key);

        if ($def === null) {
            throw new \InvalidArgumentException("Unknown scheduled job '{$key}'.");
        }
        if ($def['protected']) {
            throw new \InvalidArgumentException("Scheduled job '{$key}' is protected and cannot be paused.");
        }

        $paused = static::pausedKeys();

        if (! in_array($key, $paused, true)) {
            $paused[] = $key;
            AppSetting::put(self::PAUSED_SETTING, array_values($paused));
        }
    }

    /** Resume a paused job (idempotent). */
    public static function resume(string $key): void
    {
        if (static::find($key) === null) {
            throw new \InvalidArgumentException("Unknown scheduled job '{$key}'.");
        }

        $paused = static::pausedKeys();
        $next   = array_values(array_diff($paused, [$key]));

        if ($next !== $paused) {
            AppSetting::put(self::PAUSED_SETTING, $next);
        }
    }

    /** Reset memoization (tests). */
    public static function flush(): void
    {
        static::$jobs = null;
    }
}
