<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Run the additive schema sync under a CROSS-PROCESS Postgres advisory lock.
 *
 * Every isolated task-agent environment, every merge's post-merge script, and
 * the deployed app all point DB_* at the SAME distant RDS `postgres` database.
 * When two merges land close together their post-merge `migrate` runs execute
 * concurrently against that one shared database. That race is what produced the
 * intermittent post-merge failures we saw:
 *   - one run ALTERs `site_pages` while another holds a cached `select *` plan
 *     -> SQLSTATE[0A000] "cached plan must not change result type", and
 *   - a backlog drain that stops partway, leaving the schema half-applied.
 *
 * A session-scoped `pg_advisory_lock` serializes the runs: the second waiter
 * blocks until the first finishes (or its connection drops, which auto-releases
 * the lock — so there is no deadlock risk if a run dies). Plain `migrate` stays
 * ADDITIVE; this only changes *when* it runs, never *what* it does. The
 * orphan-healing fallback (`db:reconcile-migrations`) runs inside the same lock
 * so the whole heal is serialized end to end.
 */
class MigrateGuarded extends Command
{
    protected $signature = 'migrate:guarded {--timeout=540 : Max seconds to wait for the migration lock before proceeding anyway}';

    protected $description = 'Additive migrate (with reconcile fallback) under a cross-process Postgres advisory lock so concurrent post-merge runs cannot race the shared database.';

    /**
     * Fixed application-wide advisory-lock key. The exact value is irrelevant as
     * long as every post-merge run uses the same one; advisory locks live in a
     * single global namespace per database.
     */
    private const LOCK_KEY = 718273645;

    public function handle(): int
    {
        $connection = DB::connection();

        // Non-Postgres (e.g. a local sqlite/mysql dev DB) is not the shared RDS,
        // so there is no cross-environment race to guard against — run directly.
        if ($connection->getDriverName() !== 'pgsql') {
            return $this->runMigrations();
        }

        // FAIL CLOSED: only ever migrate the shared database while holding the
        // lock. If we cannot acquire it within the timeout (another run is
        // actively migrating) — or the lock probe itself errors — we skip rather
        // than migrate unlocked, because migrating unlocked is exactly the
        // concurrent-migrate race this command exists to prevent. Whatever is
        // left unapplied is picked up by the holding run, the next merge's
        // (locked) run, or surfaced by the hourly db:check-pending-migrations
        // alert. We exit 0 so benign contention does not raise a false
        // "schema may be incomplete" alarm in post-merge.
        if (! $this->acquireLock($connection, (int) $this->option('timeout'))) {
            $this->warn('migrate:guarded: another migration run holds the lock; skipping to avoid a concurrent race. Remaining migrations will be applied by that run, the next merge, or surfaced by the hourly pending-migration check.');

            return self::SUCCESS;
        }

        try {
            return $this->runMigrations();
        } finally {
            try {
                $connection->statement('SELECT pg_advisory_unlock(?)', [self::LOCK_KEY]);
            } catch (\Throwable $e) {
                // Session advisory locks auto-release when this process's
                // connection closes, so a failed explicit unlock is harmless.
            }
        }
    }

    /**
     * Poll pg_try_advisory_lock until acquired or the deadline passes. We never
     * block forever — bounding the wait keeps us safely under the post-merge
     * timeout. Returns false on exhaustion or probe error; the caller then fails
     * closed (skips migrating) rather than running unlocked.
     */
    private function acquireLock(Connection $connection, int $timeoutSeconds): bool
    {
        $deadline = microtime(true) + max(1, $timeoutSeconds);

        while (microtime(true) < $deadline) {
            try {
                $row = $connection->selectOne('SELECT pg_try_advisory_lock(?) AS locked', [self::LOCK_KEY]);
            } catch (\Throwable $e) {
                // If the lock probe itself errors, don't spin — let the caller
                // proceed unlocked rather than loop on a broken connection.
                return false;
            }

            if ($row && ($row->locked === true || $row->locked === 't' || $row->locked == 1)) {
                return true;
            }

            usleep(500000); // 0.5s between attempts
        }

        return false;
    }

    private function runMigrations(): int
    {
        try {
            $code = Artisan::call('migrate', ['--force' => true]);
            $this->getOutput()->write(Artisan::output());

            if ($code === 0) {
                return 0;
            }
        } catch (\Throwable $e) {
            $this->getOutput()->write(Artisan::output());
            $this->warn('migrate:guarded: migrate failed (' . $e->getMessage() . '); attempting db:reconcile-migrations.');
        }

        // Fallback: heal orphaned/idempotency-failed migrations and apply the rest.
        try {
            $code = Artisan::call('db:reconcile-migrations', ['--force' => true]);
            $this->getOutput()->write(Artisan::output());

            return $code;
        } catch (\Throwable $e) {
            $this->getOutput()->write(Artisan::output());
            $this->error('migrate:guarded: reconcile also failed: ' . $e->getMessage());

            return 1;
        }
    }
}
