<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Apply pending migrations ADDITIVELY while tolerating "orphaned" migrations —
 * ones whose `up()` already COMMITed in a previous run that was killed before
 * the row could be written to the `migrations` ledger.
 *
 * Why this exists: the shared database is a distant, high-latency AWS RDS.
 * `php artisan migrate --force` runs each migration's `up()` and then logs it to
 * the `migrations` table in a *separate* statement. If a run is interrupted
 * (SIGKILL at a timeout) between the COMMIT and the log INSERT, the schema
 * change persists but is never recorded. The next plain `migrate` then retries
 * that migration and dies with an idempotency error (e.g. `42P07 already
 * exists`, `42P01 does not exist`), poisoning every subsequent deploy/merge.
 *
 * This command is the self-healing path: it walks the pending migrations in
 * order and runs each `up()`. On success it records the row; on an *idempotency*
 * error (the effect is already present ⇒ it was an orphan) it simply records the
 * row and moves on; on any *other* error it stops loudly without guessing.
 *
 * It is purely additive — it never drops tables or data. There is deliberately
 * no `migrate:fresh`-style rebuild here. It is the fallback the post-merge
 * script and the production deploy fall back to when a plain `migrate --force`
 * trips over an orphan.
 *
 * 1inme registers migrations only from `database/migrations` (no module-
 * registered paths), so enumerating `$migrator->paths()` + that directory is
 * complete coverage; the migrations are anonymous-class files, so `require`
 * returns the migration object directly.
 */
class ReconcilePendingMigrations extends Command
{
    protected $signature = 'db:reconcile-migrations
                            {--force : Required to run when APP_ENV=production (mirrors migrate --force)}
                            {--pretend : List what would be applied/reconciled without touching the database}';

    protected $description = 'Apply pending migrations additively and reconcile orphaned (applied-but-unrecorded) migrations left by interrupted runs.';

    /**
     * PostgreSQL SQLSTATEs that mean "the change this migration makes is already
     * present" — i.e. the migration's `up()` already committed but was never
     * recorded, so retrying it errors on the now-duplicate object. Detecting
     * these lets us reconcile the ledger instead of aborting.
     *
     * IMPORTANT: only *duplicate* ("already exists") states belong here. Missing-
     * object states (42P01 undefined_table / 42703 undefined_column / 42704
     * undefined_object) are deliberately EXCLUDED — they usually signal a real
     * ordering/dependency failure (a prerequisite migration that never ran), and
     * silently recording such a migration as "applied" would bake permanent
     * ledger/schema drift in. We abort loudly on those instead.
     */
    private const IDEMPOTENT_SQLSTATES = [
        '42P07', // duplicate_table
        '42P06', // duplicate_schema
        '42710', // duplicate_object (index / constraint / type / trigger)
        '42701', // duplicate_column
    ];

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to run in production without --force.');
            return self::FAILURE;
        }

        $migrator   = app('migrator');
        $repository  = $migrator->getRepository();

        if (! $migrator->repositoryExists()) {
            $repository->createRepository();
            $this->info('Created the migrations repository table.');
        }

        $paths = array_values(array_unique(array_merge(
            $migrator->paths(),
            [database_path('migrations')]
        )));

        $files = $migrator->getMigrationFiles($paths);
        $ran   = $repository->getRan();

        $pending = array_values(array_diff(array_keys($files), $ran));
        sort($pending);

        if (empty($pending)) {
            $this->info('Nothing to migrate — schema is up to date.');
            return self::SUCCESS;
        }

        $this->info(count($pending) . ' pending migration(s) to apply/reconcile.');

        if ($this->option('pretend')) {
            foreach ($pending as $name) {
                $this->line("  would run: {$name}");
            }
            return self::SUCCESS;
        }

        $batch      = $repository->getNextBatchNumber();
        $applied    = 0;
        $reconciled = 0;

        foreach ($pending as $name) {
            // 1inme migrations are anonymous classes, so `require` returns the
            // migration object itself.
            $migration = require $files[$name];

            if (! is_object($migration) || ! method_exists($migration, 'up')) {
                Log::error("::1inme:: db:reconcile-migrations: could not resolve migration object for {$name}");
                $this->error("  could not resolve migration: {$name} — aborting.");
                return self::FAILURE;
            }

            try {
                $migration->up();
                $repository->log($name, $batch);
                $applied++;
                $this->line("  <info>migrated:</info> {$name}");
            } catch (\Throwable $e) {
                if ($this->isIdempotencyError($e)) {
                    // The change is already present — this is an orphan from an
                    // interrupted run. Record it so the ledger matches reality.
                    $repository->log($name, $batch);
                    $reconciled++;
                    $this->warn("  reconciled orphan (already applied): {$name}");
                    continue;
                }

                Log::error("::1inme:: db:reconcile-migrations: non-idempotency failure on {$name}: " . $e->getMessage());
                $this->error("  failed: {$name} — " . $e->getMessage());
                $this->error("Stopping. {$applied} applied, {$reconciled} reconciled before this failure.");
                return self::FAILURE;
            }
        }

        $this->info("Done — {$applied} applied, {$reconciled} reconciled, 0 pending.");
        return self::SUCCESS;
    }

    /**
     * Is this error the signature of an orphaned migration (its effect is
     * already applied), rather than a genuine schema/logic failure?
     *
     * SQLSTATE codes are authoritative. The message fallback is deliberately
     * narrow: only the schema-object phrase "already exists" (e.g. "relation
     * ... already exists", "column ... already exists"). We do NOT match a bare
     * "duplicate", because Postgres uses that for DATA errors too — notably
     * 23505 "duplicate key value violates unique constraint" — which is a real
     * failure, not an orphaned-migration signal, and must abort loudly.
     */
    private function isIdempotencyError(\Throwable $e): bool
    {
        $states = [];
        if ($e instanceof QueryException) {
            $states[] = (string) $e->getCode();
        }
        $cursor = $e->getPrevious();
        while ($cursor) {
            $states[] = (string) $cursor->getCode();
            $cursor = $cursor->getPrevious();
        }

        foreach ($states as $state) {
            if ($state !== '' && in_array($state, self::IDEMPOTENT_SQLSTATES, true)) {
                return true;
            }
        }

        $msg = strtolower($e->getMessage());

        if (str_contains($msg, 'already exists')) {
            return true;
        }

        // A racing/orphaned CREATE (table, type, index, constraint, sequence)
        // surfaces NOT as 42P07 but as 23505 (unique_violation) on a pg_catalog
        // uniqueness index — e.g. pg_type_typname_nsp_index (a table's rowtype /
        // an enum already exists), pg_class_relname_nsp_index (a relation name),
        // pg_constraint_conname_nsp_index. We require BOTH the 23505 SQLSTATE and a
        // pg_-prefixed (system catalog) constraint name: user-table constraints
        // (e.g. site_pages_slug_unique) are never pg_-prefixed, so a genuine DATA
        // unique-violation still aborts loudly, and gating on the SQLSTATE avoids
        // depending on localized "violates unique constraint" message prose.
        if (in_array('23505', $states, true) && str_contains($msg, 'constraint "pg_')) {
            return true;
        }

        return false;
    }
}
