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

    /**
     * Convert an already-populated PLAIN table into a monthly RANGE partitioned
     * table in a single transaction, faithfully recreating its primary key,
     * unique keys, secondary indexes, foreign keys and owning sequence.
     *
     * Postgres cannot partition a populated table in place, so this does the
     * safe "create partitioned twin → copy → swap" dance. Two hard partitioning
     * rules are handled automatically:
     *   - a partitioned table's PRIMARY KEY and UNIQUE keys MUST include the
     *     partition key column, so it is appended when missing;
     *   - unique indexes on a partitioned table cannot carry a partial (WHERE)
     *     predicate, so the predicate is dropped (NULLs never collide in a
     *     unique index anyway, preserving the original intent).
     *
     * The original rows are retained as "<table>_old_plain" for verification;
     * the caller drops that table once verified.
     *
     * Only safe for tables small enough to copy in one transaction — the
     * operator command gates this off for large populated tables and prints the
     * manual runbook instead. Historical rows whose month has no dedicated
     * partition land in the catch-all DEFAULT partition.
     *
     * @return array{partitions: array<int,string>, indexes: int, rows: int, old_table: string}
     */
    public function convertToPartitioned(string $table, string $column, int $monthsAhead = 3): array
    {
        $old = $table . '_old_plain';

        // Capture the full schema BEFORE anything is renamed.
        $pkCols       = $this->primaryKeyColumns($table);
        $uniqueCons   = $this->uniqueConstraints($table);
        $uniqueIdx    = $this->uniqueIndexesWithoutConstraint($table);
        $secondaryIdx = $this->secondaryIndexDefs($table);
        $foreignKeys  = $this->foreignKeyDefs($table);
        $sequences    = $this->ownedSequences($table);
        $allIndexes   = $this->allIndexNames($table);

        $summary = ['partitions' => [], 'indexes' => 0, 'rows' => 0, 'old_table' => $old];

        DB::transaction(function () use ($table, $old, $column, $monthsAhead, $pkCols, $uniqueCons, $uniqueIdx, $secondaryIdx, $foreignKeys, $sequences, $allIndexes, &$summary) {
            // 1. Detach owning sequences so moving/dropping the old table never
            //    cascades a DROP onto them (the new table's default reuses them).
            foreach ($sequences as $s) {
                DB::statement('ALTER SEQUENCE ' . $this->quote($s['seq']) . ' OWNED BY NONE');
            }

            // 2. Move the original table aside, keeping its data for verification.
            DB::statement('ALTER TABLE ' . $this->quote($table) . ' RENAME TO ' . $this->quote($old));

            // 3. Free the canonical index/constraint names still held by the old
            //    table so the new table can reuse them verbatim.
            $i = 0;
            foreach ($allIndexes as $idx) {
                DB::statement('ALTER INDEX ' . $this->quote($idx) . ' RENAME TO ' . $this->quote($this->tempName($idx, $i++)));
            }
            foreach ($foreignKeys as $fk) {
                DB::statement('ALTER TABLE ' . $this->quote($old) . ' RENAME CONSTRAINT ' . $this->quote($fk['name']) . ' TO ' . $this->quote($this->tempName($fk['name'], $i++)));
            }

            // 4. Partitioned parent with identical columns / defaults / NOT NULL / CHECK.
            DB::statement('CREATE TABLE ' . $this->quote($table) . ' (LIKE ' . $this->quote($old) . ' INCLUDING DEFAULTS INCLUDING CONSTRAINTS) PARTITION BY RANGE (' . $this->quote($column) . ')');

            // 5. Catch-all DEFAULT partition + rolling future month partitions.
            DB::statement('CREATE TABLE ' . $this->quote($table . '_pdefault') . ' PARTITION OF ' . $this->quote($table) . ' DEFAULT');
            $summary['partitions'] = $this->ensureFuturePartitions($table, $monthsAhead);

            // 6. Copy the data across (historical rows fall into DEFAULT).
            DB::statement('INSERT INTO ' . $this->quote($table) . ' SELECT * FROM ' . $this->quote($old));
            $summary['rows'] = (int) DB::table($table)->count();

            // 7. Recreate keys/indexes/FKs, injecting the partition key into PK
            //    and unique keys and dropping any partial predicate.
            if ($pkCols) {
                $cols = $this->withPartitionKey($pkCols, $column);
                DB::statement('ALTER TABLE ' . $this->quote($table) . ' ADD CONSTRAINT ' . $this->quote($table . '_pkey') . ' PRIMARY KEY (' . $this->colList($cols) . ')');
                $summary['indexes']++;
            }
            foreach ($uniqueCons as $u) {
                $cols = $this->withPartitionKey($u['cols'], $column);
                DB::statement('ALTER TABLE ' . $this->quote($table) . ' ADD CONSTRAINT ' . $this->quote($u['name']) . ' UNIQUE (' . $this->colList($cols) . ')');
                $summary['indexes']++;
            }
            foreach ($uniqueIdx as $u) {
                $cols = $this->withPartitionKey($u['cols'], $column);
                DB::statement('CREATE UNIQUE INDEX ' . $this->quote($u['name']) . ' ON ' . $this->quote($table) . ' (' . $this->colList($cols) . ')');
                $summary['indexes']++;
            }
            foreach ($secondaryIdx as $idx) {
                DB::statement($idx['def']);
                $summary['indexes']++;
            }
            foreach ($foreignKeys as $fk) {
                DB::statement('ALTER TABLE ' . $this->quote($table) . ' ADD CONSTRAINT ' . $this->quote($fk['name']) . ' ' . $fk['def']);
            }

            // 8. Reattach sequences to the new table and fast-forward them.
            foreach ($sequences as $s) {
                DB::statement('ALTER SEQUENCE ' . $this->quote($s['seq']) . ' OWNED BY ' . $this->quote($table) . '.' . $this->quote($s['col']));
                DB::statement('SELECT setval(' . $this->literal($s['seq']) . ', GREATEST((SELECT COALESCE(MAX(' . $this->quote($s['col']) . '), 0) FROM ' . $this->quote($table) . '), 1))');
            }
        });

        return $summary;
    }

    /** @return array<int,string> ordered primary-key column names */
    private function primaryKeyColumns(string $table): array
    {
        $rows = DB::select(
            'SELECT a.attname AS col FROM pg_index ix '
            . 'JOIN pg_class t ON t.oid = ix.indrelid '
            . 'JOIN unnest(ix.indkey) WITH ORDINALITY AS k(attnum, ord) ON true '
            . 'JOIN pg_attribute a ON a.attrelid = ix.indrelid AND a.attnum = k.attnum '
            . 'WHERE t.relname = ? AND ix.indisprimary ORDER BY k.ord',
            [$table]
        );
        return array_map(fn ($r) => $r->col, $rows);
    }

    /** @return array<int,array{name:string,cols:array<int,string>}> */
    private function uniqueConstraints(string $table): array
    {
        $rows = DB::select(
            'SELECT c.conname AS name, array_to_string(ARRAY('
            . 'SELECT a.attname FROM unnest(c.conkey) WITH ORDINALITY AS k(attnum, ord) '
            . 'JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = k.attnum ORDER BY k.ord), \',\') AS cols '
            . 'FROM pg_constraint c JOIN pg_class t ON t.oid = c.conrelid '
            . 'WHERE t.relname = ? AND c.contype = \'u\'',
            [$table]
        );
        return array_map(fn ($r) => ['name' => $r->name, 'cols' => $r->cols === '' ? [] : explode(',', $r->cols)], $rows);
    }

    /**
     * Unique indexes that are NOT primary and NOT backing a constraint.
     *
     * @return array<int,array{name:string,cols:array<int,string>}>
     */
    private function uniqueIndexesWithoutConstraint(string $table): array
    {
        $rows = DB::select(
            'SELECT i.relname AS name, array_to_string(ARRAY('
            . 'SELECT a.attname FROM unnest(ix.indkey) WITH ORDINALITY AS k(attnum, ord) '
            . 'JOIN pg_attribute a ON a.attrelid = ix.indrelid AND a.attnum = k.attnum ORDER BY k.ord), \',\') AS cols '
            . 'FROM pg_index ix JOIN pg_class i ON i.oid = ix.indexrelid '
            . 'JOIN pg_class t ON t.oid = ix.indrelid '
            . 'LEFT JOIN pg_constraint c ON c.conindid = ix.indexrelid '
            . 'WHERE t.relname = ? AND ix.indisunique AND NOT ix.indisprimary AND c.oid IS NULL',
            [$table]
        );
        return array_map(fn ($r) => ['name' => $r->name, 'cols' => $r->cols === '' ? [] : explode(',', $r->cols)], $rows);
    }

    /** @return array<int,array{name:string,def:string}> non-unique secondary index definitions */
    private function secondaryIndexDefs(string $table): array
    {
        $rows = DB::select(
            'SELECT i.relname AS name, pg_get_indexdef(ix.indexrelid) AS def '
            . 'FROM pg_index ix JOIN pg_class i ON i.oid = ix.indexrelid '
            . 'JOIN pg_class t ON t.oid = ix.indrelid '
            . 'WHERE t.relname = ? AND NOT ix.indisunique',
            [$table]
        );
        return array_map(fn ($r) => ['name' => $r->name, 'def' => $r->def], $rows);
    }

    /** @return array<int,array{name:string,def:string}> foreign-key definitions */
    private function foreignKeyDefs(string $table): array
    {
        $rows = DB::select(
            'SELECT conname AS name, pg_get_constraintdef(oid) AS def '
            . 'FROM pg_constraint WHERE conrelid = ?::regclass AND contype = \'f\'',
            [$table]
        );
        return array_map(fn ($r) => ['name' => $r->name, 'def' => $r->def], $rows);
    }

    /** @return array<int,array{seq:string,col:string}> sequences owned by this table */
    private function ownedSequences(string $table): array
    {
        $rows = DB::select(
            'SELECT s.relname AS seq, a.attname AS col '
            . 'FROM pg_class s JOIN pg_depend d ON d.objid = s.oid '
            . 'JOIN pg_class t ON t.oid = d.refobjid '
            . 'JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = d.refobjsubid '
            . 'WHERE s.relkind = \'S\' AND t.relname = ? AND d.deptype = \'a\'',
            [$table]
        );
        return array_map(fn ($r) => ['seq' => $r->seq, 'col' => $r->col], $rows);
    }

    /** @return array<int,string> every index name on the table */
    private function allIndexNames(string $table): array
    {
        $rows = DB::select(
            'SELECT i.relname AS name FROM pg_index ix '
            . 'JOIN pg_class i ON i.oid = ix.indexrelid '
            . 'JOIN pg_class t ON t.oid = ix.indrelid WHERE t.relname = ?',
            [$table]
        );
        return array_map(fn ($r) => $r->name, $rows);
    }

    /**
     * Append the partition key column to a key's column list if it is missing
     * (required for PRIMARY KEY / UNIQUE on a partitioned table).
     *
     * @param  array<int,string> $cols
     * @return array<int,string>
     */
    private function withPartitionKey(array $cols, string $column): array
    {
        return in_array($column, $cols, true) ? $cols : [...$cols, $column];
    }

    /** @param array<int,string> $cols */
    private function colList(array $cols): string
    {
        return implode(', ', array_map(fn ($c) => $this->quote($c), $cols));
    }

    /** A collision-free temporary identifier kept within Postgres' 63-byte limit. */
    private function tempName(string $name, int $i): string
    {
        return substr($name, 0, 48) . '_oldp' . $i;
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
