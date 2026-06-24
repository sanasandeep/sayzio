<?php

namespace App\Modules\Admin\Support;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Persists "last actually ran" state for scheduled jobs. Laravel keeps no
 * built-in record of when a scheduled event last *finished*, so the admin Cron
 * Jobs page can only show each job's *next* due time — which tells an operator
 * nothing about whether the server crontab is actually firing.
 *
 * The scheduler lifecycle (ScheduledTaskFinished / ScheduledTaskFailed, wired in
 * AppServiceProvider) calls {@see record()} for every event it runs, keyed by the
 * event's mutex name (stable across the listener and the inspector). It also
 * stamps a single global "heartbeat" timestamp on every run — because at least
 * one every-minute job always fires, a fresh heartbeat is the most reliable
 * signal that `schedule:run` is alive at all (vs. a silently-dead scheduler).
 *
 * State lives in the cache (not a table) to mirror how Laravel already stores the
 * `withoutOverlapping` mutexes; the cache store is local/Redis, never the distant
 * shared RDS, so the admin page stays fast.
 */
class CronRunLog
{
    /** Per-job cache key prefix; suffixed with the event mutex name. */
    protected const PREFIX = 'cron_last_run:';

    /** Single global "the scheduler ran something" heartbeat. */
    protected const TICK_KEY = 'cron_scheduler_last_tick';

    /** How long recorded state is retained. */
    protected const TTL_DAYS = 60;

    /**
     * Record that a scheduled event just finished (successfully or not).
     * Best-effort: a cache write failure must never break the scheduler.
     */
    public function record(Event $event, bool $ok, ?float $runtime = null, ?string $error = null): void
    {
        try {
            $ts = Carbon::now()->getTimestamp();

            Cache::put(self::PREFIX . $this->key($event), [
                'ran_at'  => $ts,
                'ok'      => $ok,
                'runtime' => $runtime,
                'error'   => $error,
            ], Carbon::now()->addDays(self::TTL_DAYS));

            // Heartbeat: bumped on every run regardless of which job, so a recent
            // value proves `schedule:run` itself is firing.
            Cache::put(self::TICK_KEY, $ts, Carbon::now()->addDays(self::TTL_DAYS));
        } catch (\Throwable $e) {
            // Swallow — recording last-run state is observability, not core work.
        }
    }

    /**
     * Bulk-fetch recorded state for a list of event mutex names in one round-trip.
     *
     * @param  array<int, string>  $keys
     * @return array<string, array{ran_at:int, ok:bool, runtime:?float, error:?string}>
     */
    public function many(array $keys): array
    {
        $keys = array_values(array_unique(array_filter($keys)));

        if ($keys === []) {
            return [];
        }

        try {
            $prefixed = array_map(fn (string $k): string => self::PREFIX . $k, $keys);
            $raw = Cache::many($prefixed);
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($keys as $k) {
            $val = $raw[self::PREFIX . $k] ?? null;
            if (is_array($val) && isset($val['ran_at'])) {
                $out[$k] = $val;
            }
        }

        return $out;
    }

    /**
     * The stable key for an event — its scheduler mutex name, which both the
     * runtime listener and the inspector compute identically.
     */
    public function key(Event $event): string
    {
        try {
            return $event->mutexName();
        } catch (\Throwable $e) {
            return 'fallback:' . sha1((string) ($event->command ?? $event->expression ?? ''));
        }
    }

    /**
     * When the scheduler last ran any job at all, or null if it never has.
     */
    public function lastTick(): ?Carbon
    {
        try {
            $ts = Cache::get(self::TICK_KEY);
        } catch (\Throwable $e) {
            return null;
        }

        return is_numeric($ts) ? Carbon::createFromTimestamp((int) $ts) : null;
    }
}
