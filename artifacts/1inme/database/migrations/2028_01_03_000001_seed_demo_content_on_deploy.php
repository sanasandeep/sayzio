<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Do NOT wrap this migration in a transaction. It only spawns a background
     * process; the seeder it launches manages its own per-phase commits (it has
     * no internal transaction and writes incrementally against the RDS).
     */
    public $withinTransaction = false;

    /**
     * Stable advisory-lock key so that if the deploy boots several instances at
     * once (each runs `migrate --force`), only ONE of them spawns the seeder.
     */
    private const SPAWN_LOCK_KEY = 1988170403;

    /**
     * Populate rich demo content on the LIVE deploy server only.
     *
     * The demo populator (DemoContentSeeder) builds the demo account's links of
     * every type, the team workspaces + task boards, and the multi-creator
     * discover feed. It issues thousands of writes, so it is slow when reached
     * over a high-latency link (dev workspace / post-merge) but fast when run
     * co-located with the database on the deploy server.
     *
     * Guards that make this safe:
     *   1. Runs only when APP_ENV=production (the deploy run phase sets this), so
     *      a dev or post-merge `migrate` no-ops here instead of hanging.
     *   2. Launches the seeder in the BACKGROUND so it never blocks the deploy's
     *      `migrate --force` step nor delays the web server coming up for the
     *      startup health check (/up). A blocking seed could otherwise push
     *      server startup past the promote timeout and fail the deploy.
     *   3. Takes a Postgres advisory lock first so concurrent instance boots
     *      cannot launch two seeders (which would wipe/rebuild simultaneously).
     *
     * The seeder is idempotent: it wipes its own previously-seeded demo content
     * and rebuilds everything from scratch, so a re-run is safe.
     */
    public function up(): void
    {
        if (! app()->environment('production')) {
            return;
        }

        // Only one booting instance should spawn the seeder. The lock is held
        // for the life of this migrate connection and auto-releases when it
        // closes (right after we have spawned), which is all we need.
        $locked = (bool) optional(
            DB::selectOne('SELECT pg_try_advisory_lock(?) AS locked', [self::SPAWN_LOCK_KEY])
        )?->locked;

        if (! $locked) {
            return;
        }

        $class   = \Database\Seeders\DemoContentSeeder::class;
        $php     = PHP_BINARY ?: 'php';
        $artisan = base_path('artisan');
        $log     = storage_path('logs/deploy_demo_seed.log');

        $command = sprintf(
            'nohup %s %s db:seed --class=%s --force >> %s 2>&1 &',
            escapeshellarg($php),
            escapeshellarg($artisan),
            escapeshellarg($class),
            escapeshellarg($log)
        );

        // Fire-and-forget via a detaching shell so the seed runs AFTER the web
        // server has already started. We deliberately do NOT fall back to a
        // synchronous run: blocking the deploy is worse than skipping the seed,
        // and the run can be retried by shipping a follow-up migration.
        if (function_exists('exec')) {
            exec($command);

            return;
        }

        if (function_exists('shell_exec')) {
            shell_exec($command);

            return;
        }

        Log::warning('Demo-content deploy seed skipped: no shell-detach function available (exec/shell_exec disabled).');
    }

    public function down(): void
    {
        // One-time data population; there is nothing to reverse.
    }
};
