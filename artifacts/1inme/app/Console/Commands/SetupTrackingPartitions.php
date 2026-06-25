<?php

namespace App\Console\Commands;

use App\Modules\Common\Support\PartitionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OPERATOR command — convert a high-volume tracking table to native monthly
 * RANGE partitioning.
 *
 * IMPORTANT: This is deliberately NOT an automatic migration. Postgres cannot
 * turn an already-populated plain table into a partitioned one in place; the
 * safe conversion is "create a partitioned twin, copy data, swap names", which
 * on the shared cross-region RDS with ~100M rows must be run in a planned
 * maintenance window — never as part of `migrate --force` on deploy.
 *
 * Behaviour:
 *  - Already partitioned          → no-op.
 *  - Empty (or --execute on a small table) → performs the swap in a transaction.
 *  - Populated table without --execute     → prints the exact runbook SQL and exits
 *    WITHOUT touching anything, so an operator can review/run it deliberately.
 *
 * See docs/scaling-tracking.md for the full runbook.
 */
class SetupTrackingPartitions extends Command
{
    protected $signature = 'tracking:setup-partitions
        {table : link_clicks or page_sessions}
        {--execute : Actually perform the conversion (otherwise dry-run / print runbook)}
        {--months-ahead=3 : Future month partitions to provision after conversion}';

    protected $description = 'Convert a tracking table to monthly partitioning (operator-gated; safe by default).';

    /** Partition key column per table. */
    private const KEY_COLUMN = [
        'link_clicks'   => 'clicked_at',
        'page_sessions' => 'started_at',
    ];

    public function handle(PartitionManager $partitions): int
    {
        $table = (string) $this->argument('table');
        if (!isset(self::KEY_COLUMN[$table])) {
            $this->error("Unsupported table '{$table}'. Use one of: " . implode(', ', array_keys(self::KEY_COLUMN)));
            return self::FAILURE;
        }
        if (!Schema::hasTable($table)) {
            $this->error("Table '{$table}' does not exist.");
            return self::FAILURE;
        }

        if ($partitions->isPartitioned($table)) {
            $this->info("'{$table}' is already partitioned — provisioning future partitions only.");
            $created = $partitions->ensureFuturePartitions($table, max(1, (int) $this->option('months-ahead')));
            $this->info($created ? ('Created: ' . implode(', ', $created)) : 'No new partitions needed.');
            return self::SUCCESS;
        }

        $column   = self::KEY_COLUMN[$table];
        $rowCount = (int) DB::table($table)->count();
        $execute  = (bool) $this->option('execute');

        // Refuse to silently rewrite a large populated table — that is a planned
        // maintenance-window operation. Print the runbook instead.
        $LARGE = 100_000;
        if ($rowCount > $LARGE && $execute) {
            $this->warn("'{$table}' has {$rowCount} rows — too large to convert via this command.");
            $this->warn('Run the printed runbook manually in a maintenance window instead.');
            $execute = false;
        }

        if (!$execute) {
            $this->printRunbook($table, $column, $rowCount);
            return self::SUCCESS;
        }

        $this->convert($table, $column, $partitions);
        return self::SUCCESS;
    }

    /**
     * Perform the create-twin / copy / swap conversion in a single transaction.
     * Only reached for empty or small tables (guarded above).
     */
    private function convert(string $table, string $column, PartitionManager $partitions): void
    {
        $this->info("Converting '{$table}' → partitioned by {$column} (month).");

        // The manager performs the full create-twin / copy / swap conversion in
        // one transaction, faithfully recreating the primary key, unique keys,
        // secondary indexes, foreign keys and owning sequence (injecting the
        // partition key into PK/unique keys as Postgres requires).
        $summary = $partitions->convertToPartitioned(
            $table,
            $column,
            max(1, (int) $this->option('months-ahead'))
        );

        $this->info("Copied {$summary['rows']} row(s); recreated {$summary['indexes']} key/index(es).");
        $this->info('Future partitions: ' . (implode(', ', $summary['partitions']) ?: 'none needed'));
        $this->warn("Original data retained as '{$summary['old_table']}'. Verify, then DROP it manually.");
    }

    private function printRunbook(string $table, string $column, int $rowCount): void
    {
        $twin = $table . '_partitioned';
        $this->newLine();
        $this->warn("DRY RUN — '{$table}' has {$rowCount} row(s). Review and run this in a maintenance window:");
        $this->newLine();
        $this->line("BEGIN;");
        $this->line("CREATE TABLE {$twin} (LIKE {$table} INCLUDING DEFAULTS INCLUDING CONSTRAINTS) PARTITION BY RANGE ({$column});");
        $this->line("CREATE TABLE {$table}_pdefault PARTITION OF {$twin} DEFAULT;");
        $this->line("-- create the month partitions you need, e.g.:");
        $this->line("CREATE TABLE {$table}_p" . now()->format('Y_m') . " PARTITION OF {$twin} FOR VALUES FROM ('" . now()->startOfMonth()->toDateString() . "') TO ('" . now()->addMonth()->startOfMonth()->toDateString() . "');");
        $this->line("INSERT INTO {$twin} SELECT * FROM {$table};  -- batch this for large tables");
        $this->line("ALTER TABLE {$table} RENAME TO {$table}_old_plain;");
        $this->line("ALTER TABLE {$twin} RENAME TO {$table};");
        $this->line("-- Recreate keys/indexes. A partitioned table's PRIMARY KEY and UNIQUE keys");
        $this->line("-- MUST include the partition key ({$column}); unique indexes cannot be partial:");
        $this->line("--   ALTER TABLE {$table} ADD CONSTRAINT {$table}_pkey PRIMARY KEY (id, {$column});");
        $this->line("--   CREATE UNIQUE INDEX <name> ON {$table} (<cols>, {$column});  -- drop any WHERE predicate");
        $this->line("--   recreate the remaining secondary indexes + foreign keys, then reattach the id sequence.");
        $this->line("-- Verify row counts match, then:");
        $this->line("-- DROP TABLE {$table}_old_plain;");
        $this->line("COMMIT;");
        $this->newLine();
        $this->info("After conversion, `tracking:maintain-partitions` keeps future months provisioned and `stats:prune-history` drops expired months.");
    }
}
