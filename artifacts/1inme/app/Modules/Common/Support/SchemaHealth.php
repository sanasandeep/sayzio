<?php

namespace App\Modules\Common\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Detects an out-of-date database schema by diffing the migration files on
 * disk against the `migrations` repository table — i.e. the same comparison
 * `php artisan migrate:status` performs.
 *
 * Why this exists: the production deploy intentionally keeps serving even when
 * `php artisan migrate --force` fails (to avoid full downtime), which can leave
 * the app running against an incomplete schema until a user hits a 500. This
 * gives the rest of the app a cheap, reusable signal so an incomplete schema is
 * surfaced proactively (admin dashboard banner, scheduled alert email/in-app,
 * readiness endpoint) instead of being discovered via user-facing errors.
 *
 * Every entry point is defensive: a DB that is unreachable (or otherwise
 * errors while we probe it) reports `available => false` rather than throwing,
 * so the surfaces that consume this — the admin dashboard especially — never
 * crash because of the very tool meant to keep them healthy.
 */
class SchemaHealth
{
    private const CACHE_KEY = 'schema_health:pending_migrations';
    private const CACHE_TTL = 120; // seconds

    /**
     * Freshly compute the pending-migration report straight from the DB.
     *
     * @return array{available:bool, files:int, ran:int, pending:array<int,string>, error?:string}
     */
    public static function compute(): array
    {
        try {
            $migrator = app('migrator');

            $paths = array_values(array_unique(array_merge(
                $migrator->paths(),
                [database_path('migrations')]
            )));

            $files = $migrator->getMigrationFiles($paths);

            // No migrations table at all ⇒ a completely un-migrated DB; every
            // migration on disk is pending.
            if (! $migrator->repositoryExists()) {
                return [
                    'available' => true,
                    'files'     => count($files),
                    'ran'       => 0,
                    'pending'   => array_values(array_keys($files)),
                ];
            }

            $ran     = $migrator->getRepository()->getRan();
            $pending = array_values(array_diff(array_keys($files), $ran));

            return [
                'available' => true,
                'files'     => count($files),
                'ran'       => count($ran),
                'pending'   => $pending,
            ];
        } catch (\Throwable $e) {
            // DB unreachable / repository read failed — report "unknown" so
            // callers degrade gracefully instead of 500-ing.
            return [
                'available' => false,
                'files'     => 0,
                'ran'       => 0,
                'pending'   => [],
                'error'     => $e->getMessage(),
            ];
        }
    }

    /**
     * Cached variant for hot paths (dashboard render, readiness endpoint).
     * Falls back to a direct compute if the cache store itself is unavailable.
     *
     * @return array{available:bool, files:int, ran:int, pending:array<int,string>, error?:string}
     */
    public static function cached(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => self::compute());
        } catch (\Throwable $e) {
            return self::compute();
        }
    }

    /**
     * Drop the cached report so the next read reflects reality immediately —
     * called right after the scheduled check so a freshly-migrated (or freshly
     * broken) schema shows up on the dashboard without waiting out the TTL.
     */
    public static function flush(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable $e) {
            // best-effort
        }
    }

    public static function pendingCount(bool $fresh = false): int
    {
        $report = $fresh ? self::compute() : self::cached();
        return count($report['pending'] ?? []);
    }

    public static function hasPending(bool $fresh = false): bool
    {
        return self::pendingCount($fresh) > 0;
    }
}
