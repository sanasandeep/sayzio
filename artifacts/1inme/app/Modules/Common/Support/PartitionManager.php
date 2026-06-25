<?php

namespace App\Modules\Common\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Helper for native Postgres monthly RANGE partitioning of the high-volume
 * tracking tables (link_clicks, page_sessions).
 *
 * Once a table is partitioned by its time column, this manager keeps a rolling
 * set of monthly child partitions ahead of "now" (so inserts always find a
 * target) and lets the retention sweep DROP whole expired months in O(1)
 * instead of deleting tens of millions of rows.
 *
 * Conversion of an already-populated table into a partitioned one is NOT done
 * here — that is an operator task (see tracking:setup-partitions) because it
 * cannot be performed safely in-place on the shared cross-region RDS. This
 * manager assumes the table is already partitioned and is a no-op otherwise.
 *
 * Partition naming convention: "<table>_p<YYYY>_<MM>" (e.g. link_clicks_p2027_08).
 */
class PartitionManager
{
    /**
     * Is $table a partitioned (parent) table?
     */
    public function isPartitioned(string $table): bool
    {
        $row = DB::selectOne(
            'SELECT 1 AS yes FROM pg_partitioned_table pt '
            . 'JOIN pg_class c ON c.oid = pt.partrelid '
            . 'WHERE c.relname = ? LIMIT 1',
            [$table]
        );
        return $row !== null;
    }

    /**
     * Child partition names of $table.
     *
     * @return array<int, string>
     */
    public function partitionsOf(string $table): array
    {
        $rows = DB::select(
            'SELECT child.relname AS name FROM pg_inherits i '
            . 'JOIN pg_class parent ON parent.oid = i.inhparent '
            . 'JOIN pg_class child  ON child.oid  = i.inhrelid '
            . 'WHERE parent.relname = ? ORDER BY child.relname',
            [$table]
        );
        return array_map(fn ($r) => $r->name, $rows);
    }

    public function partitionName(string $table, Carbon $month): string
    {
        return $table . '_p' . $month->format('Y_m');
    }

    /**
     * Ensure monthly partitions exist for the current month and the next
     * $monthsAhead months. Returns the names of partitions it created.
     *
     * @return array<int, string>
     */
    public function ensureFuturePartitions(string $table, int $monthsAhead = 3): array
    {
        if (!$this->isPartitioned($table)) {
            return [];
        }

        $created  = [];
        $existing = array_flip($this->partitionsOf($table));
        $month    = now()->startOfMonth();

        for ($i = 0; $i <= $monthsAhead; $i++) {
            $current = $month->copy()->addMonths($i);
            $name    = $this->partitionName($table, $current);
            if (isset($existing[$name])) {
                continue;
            }
            $from = $current->copy()->startOfMonth()->toDateString();
            $to   = $current->copy()->addMonth()->startOfMonth()->toDateString();

            // CREATE TABLE IF NOT EXISTS ... PARTITION OF is idempotent.
            DB::statement(sprintf(
                'CREATE TABLE IF NOT EXISTS %s PARTITION OF %s FOR VALUES FROM (%s) TO (%s)',
                $this->quote($name),
                $this->quote($table),
                $this->literal($from),
                $this->literal($to)
            ));
            $created[] = $name;
        }

        return $created;
    }

    /**
     * Drop whole monthly partitions whose entire range is older than $cutoff.
     * Returns the dropped partition names. This is the fast retention path:
     * dropping a partition is metadata-only, vs deleting millions of rows.
     *
     * @return array<int, string>
     */
    public function dropPartitionsBefore(string $table, Carbon $cutoff): array
    {
        if (!$this->isPartitioned($table)) {
            return [];
        }

        $dropped = [];
        $prefix  = $table . '_p';

        foreach ($this->partitionsOf($table) as $name) {
            if (!str_starts_with($name, $prefix)) {
                continue;
            }
            $stamp = substr($name, strlen($prefix)); // YYYY_MM
            if (!preg_match('/^(\d{4})_(\d{2})$/', $stamp, $m)) {
                continue;
            }
            // A month partition is safe to drop only when its END (the first day
            // of the following month) is on/before the cutoff — i.e. no row in
            // it can be newer than the retention boundary.
            $monthEnd = Carbon::create((int) $m[1], (int) $m[2], 1)->addMonth()->startOfDay();
            if ($monthEnd->lessThanOrEqualTo($cutoff)) {
                DB::statement('DROP TABLE IF EXISTS ' . $this->quote($name));
                $dropped[] = $name;
            }
        }

        return $dropped;
    }

    private function quote(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    private function literal(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
