<?php

namespace App\Modules\Common\Support;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Detects broken migration *ordering* before it ships: a migration that modifies
 * or foreign-keys a table which is only created by a LATER-dated migration. That
 * class of bug builds fine on any database that already happens to have the table
 * (the shared dev/live RDS, where migrations were applied incrementally), but
 * breaks `migrate:fresh` from an empty database — exactly the regression Task
 * #2989 fixed, which slipped through only because the shared DB already had the
 * referenced table.
 *
 * Why a static replay instead of `migrate:fresh`: the real-database guard lives
 * in CI (.github/workflows/laravel-migrations.yml runs `migrate:fresh` against a
 * throwaway Postgres). But that can't run as the Replit pre-merge validation
 * gate — there is no local empty database, and pointing `migrate:fresh` at the
 * shared RDS would wipe it (and is blocked by the destructive-DB guard). This
 * inspector reproduces the *ordering* signal without any database: it replays
 * every migration's `up()` in filename order under `Connection::pretend()` with
 * the `Schema` facade swapped for an in-memory recorder, tracking which tables
 * exist at each step, and flags any forward reference.
 *
 * How it stays safe (it never touches data — mirrors {@see SchemaManifest}):
 *   - The `Schema` facade is swapped for {@see MigrationOrderRecorder}, which only
 *     records table/column existence in memory; `build()` is never called, so no
 *     DDL is ever generated or executed.
 *   - The whole replay runs inside `Connection::pretend()`, so any data-backfill
 *     statement a migration runs is inert and `select`s return `[]` — nothing is
 *     written and no PDO connection is required.
 *   - Each migration's `up()` is wrapped in a try/catch: a backfill line that
 *     needs real rows can throw under pretend, but the schema operations recorded
 *     before the throw still count, so the inspector degrades by *under*-detecting
 *     rather than producing a false positive.
 *
 * Known blind spot (acceptable — it only under-detects, never false-positives):
 * tables created, or foreign keys declared, via raw `DB::statement('CREATE/ALTER
 * TABLE ...')` are invisible to the Blueprint recorder. There are currently no
 * raw `CREATE TABLE` statements in this codebase, so a forward reference to such
 * a table cannot arise; a forward FK declared in raw SQL would simply not be
 * checked.
 */
class MigrationOrderInspector
{
    /**
     * Replay every migration in order and collect ordering violations.
     *
     * @return array{available:bool, violations:array<int,array{migration:string,type:string,message:string}>, scanned:int, error?:string}
     */
    public static function inspect(): array
    {
        try {
            $migrator = app('migrator');

            $paths = array_values(array_unique(array_merge(
                $migrator->paths(),
                [database_path('migrations')]
            )));

            // name => path, already ordered by migration name (date prefix).
            $files = $migrator->getMigrationFiles($paths);

            $connection = DB::connection();
            $recorder   = new MigrationOrderRecorder($connection);

            $original = app('db.schema');

            $replay = function () use ($files, $recorder, $connection, &$scanned) {
                Schema::swap($recorder);

                foreach ($files as $name => $path) {
                    $recorder->setCurrentMigration(self::migrationName($name, $path));

                    // Snapshot the pretend query log length before this migration
                    // so we can inspect exactly the write statements (incl. any
                    // run by a seeder the migration invokes) it produced.
                    $before = count($connection->getQueryLog());

                    try {
                        $migration = self::resolveMigration($path);
                        if ($migration === null || ! method_exists($migration, 'up')) {
                            continue;
                        }
                        $scanned++;
                        $migration->up();
                    } catch (\Throwable $e) {
                        // A data-backfill line can throw under pretend (no real
                        // rows); the schema ops already recorded — keep going.
                    } finally {
                        // Feed the INSERT/UPDATE statements this migration (and
                        // any seeder it called) emitted to the recorder so it can
                        // catch a write to a column that no earlier migration has
                        // created yet — the class of bug that broke a fresh
                        // `migrate:fresh` when the plan seeder wrote `is_internal`
                        // before its add-column migration. Schema DDL from the
                        // Blueprint recorder never hits the query log (build() is
                        // never called), so this only ever sees data writes plus
                        // any raw DB::statement SQL.
                        $log = $connection->getQueryLog();
                        for ($i = $before; $i < count($log); $i++) {
                            $recorder->inspectWriteQuery($log[$i]['query'] ?? '');
                        }
                    }
                }
            };

            $scanned = 0;

            // Replaying backfill migrations under pretend makes their inserts
            // inert, but the id-fetch select then returns [] and Postgres'
            // processor emits cosmetic "undefined array key 0" warnings. Swallow
            // low-severity notices for the duration so a CLI run / validation
            // step isn't spammed; fatals are unaffected.
            set_error_handler(
                static fn () => true,
                E_WARNING | E_NOTICE | E_DEPRECATED | E_USER_WARNING | E_USER_NOTICE | E_USER_DEPRECATED
            );

            try {
                $connection->pretend($replay);
            } finally {
                restore_error_handler();
                Schema::swap($original);
            }

            return [
                'available'  => true,
                'violations' => $recorder->violations(),
                'scanned'    => $scanned,
            ];
        } catch (\Throwable $e) {
            return [
                'available'  => false,
                'violations' => [],
                'scanned'    => 0,
                'error'      => $e->getMessage(),
            ];
        }
    }

    /**
     * Normalise the migration identifier for reporting: prefer the array key
     * (Laravel hands `getMigrationFiles()` back as name => path), else the file
     * basename without extension.
     */
    private static function migrationName($name, string $path): string
    {
        if (is_string($name) && $name !== '') {
            return $name;
        }

        return basename($path, '.php');
    }

    /**
     * Resolve a migration file to its instance. All migrations in this project
     * use the anonymous-class form (`return new class extends Migration`), so a
     * plain require returns the object; the named-class fallback mirrors
     * Laravel's own resolution for completeness.
     */
    private static function resolveMigration(string $path)
    {
        $migration = require $path;

        if (is_object($migration)) {
            return $migration;
        }

        $name  = basename($path, '.php');
        $class = Str::studly(implode('_', array_slice(explode('_', $name), 4)));

        return class_exists($class) ? new $class : null;
    }
}

/**
 * In-memory stand-in for the schema Builder used only during ordering replay. It
 * records which tables (and their columns, so conditional `whenTableHasColumn`
 * blocks behave like a real build) exist so far, and flags two forward-reference
 * classes against the tables created up to the current migration:
 *   - modifying a table (`Schema::table`/`rename`) that no earlier migration
 *     created, and
 *   - declaring a foreign key whose referenced table no earlier migration
 *     created.
 *
 * It never generates or runs SQL. Guard probes inside migrations
 * (`Schema::hasTable(...)`, `hasColumn(...)`) are answered from the schema
 * accumulated so far, so additive/guarded migrations behave exactly as a
 * build-from-scratch would.
 *
 * @internal
 */
class MigrationOrderRecorder
{
    private $connection;

    /** @var array<string,array<string,true>> table => set of columns */
    private array $tables = [];

    /** @var array<int,array{migration:string,type:string,message:string}> */
    private array $violations = [];

    /** @var array<string,true> dedup key => true */
    private array $seen = [];

    private string $currentMigration = '';

    public function __construct($connection)
    {
        $this->connection = $connection;
    }

    public function setCurrentMigration(string $name): void
    {
        $this->currentMigration = $name;
    }

    /**
     * @return array<int,array{migration:string,type:string,message:string}>
     */
    public function violations(): array
    {
        return $this->violations;
    }

    public function create($table, Closure $callback): void
    {
        // A create starts the table fresh; mark it present BEFORE inspecting its
        // own blueprint so a legitimate self-referencing foreign key (e.g.
        // parent_id -> the same table) is not flagged.
        $this->tables[$table] = [];

        $blueprint = $this->makeBlueprint($table, $callback);
        $this->applyColumns($table, $blueprint);
        $this->inspectForeignKeys($table, $blueprint);
    }

    public function table($table, Closure $callback): void
    {
        if (! isset($this->tables[$table])) {
            $this->flag(
                'modify_before_create',
                "modifies table '{$table}', which is not created by any earlier migration "
                . '(it only exists because the shared database already had it — a fresh '
                . '`migrate` from an empty database would fail here).'
            );
            // Treat it as created from here on so we surface the root cause once
            // instead of cascading the same table through every later reference.
            $this->tables[$table] = [];
        }

        $blueprint = $this->makeBlueprint($table, $callback);
        $this->applyColumns($table, $blueprint);
        $this->inspectForeignKeys($table, $blueprint);
    }

    public function drop($table): void
    {
        unset($this->tables[$table]);
    }

    public function dropIfExists($table): void
    {
        unset($this->tables[$table]);
    }

    public function rename($from, $to): void
    {
        if (! isset($this->tables[$from])) {
            $this->flag(
                'rename_before_create',
                "renames table '{$from}', which is not created by any earlier migration."
            );
        }
        $this->tables[$to] = $this->tables[$from] ?? [];
        unset($this->tables[$from]);
    }

    /** @param string|array<int,string> $columns */
    public function dropColumns($table, $columns): void
    {
        foreach ((array) $columns as $column) {
            unset($this->tables[$table][$column]);
        }
    }

    public function hasTable($table): bool
    {
        return isset($this->tables[$table]);
    }

    public function hasColumn($table, $column): bool
    {
        return isset($this->tables[$table][$column]);
    }

    /** @param array<int,string> $columns */
    public function hasColumns($table, $columns): bool
    {
        foreach ($columns as $column) {
            if (! isset($this->tables[$table][$column])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Inspect a data-write statement emitted during the current migration's
     * replay (an INSERT or UPDATE run directly, or by a seeder the migration
     * invokes) and flag any target column that no migration replayed so far has
     * created. This catches the "seeder writes a not-yet-created column" class
     * of ordering bug — invisible to the schema-only checks above because the
     * offending write lives in PHP (a seeder), not in a Blueprint.
     *
     * Only writes to a table the recorder already knows about are checked: an
     * unknown table means either a forward table reference (already caught
     * elsewhere for schema ops) or a table this static replay simply cannot see
     * (raw CREATE) — in the spirit of the inspector we under-detect rather than
     * risk a false positive. Raw `ALTER TABLE ... ADD COLUMN` DDL (rare; only
     * driver-gated branches use it) is parsed first so a column it adds is not
     * then falsely flagged by a later write in the same batch.
     */
    public function inspectWriteQuery(string $sql): void
    {
        $sql = trim($sql);
        if ($sql === '') {
            return;
        }

        // Absorb any raw ADD COLUMN so a subsequent write to that column in the
        // same migration is not falsely flagged. (Blueprint-driven schema
        // changes never reach the query log, so this only matters for raw SQL.)
        if (preg_match('/^alter\s+table\s+(["`]?)([a-z0-9_.]+)\1\s+(.*)$/is', $sql, $m)) {
            $table = $m[2];
            if (preg_match_all('/add\s+column\s+(["`]?)([a-z0-9_]+)\1/is', $m[3], $cols)) {
                foreach ($cols[2] as $col) {
                    $this->tables[$table][$col] = true;
                }
            }

            return;
        }

        // INSERT INTO "table" ("c1", "c2", ...) VALUES ...
        if (preg_match('/^insert\s+into\s+(["`]?)([a-z0-9_.]+)\1\s*\((.*?)\)\s+values/is', $sql, $m)) {
            $this->checkWrittenColumns($m[2], $this->parseColumnList($m[3]));

            return;
        }

        // UPDATE "table" SET "c1" = ?, "c2" = ? [WHERE ...]
        if (preg_match('/^update\s+(["`]?)([a-z0-9_.]+)\1\s+set\s+(.*)$/is', $sql, $m)) {
            $setClause = $m[3];
            // Trim the trailing WHERE so we only check the columns being written.
            if (($pos = stripos($setClause, ' where ')) !== false) {
                $setClause = substr($setClause, 0, $pos);
            }
            if (preg_match_all('/(["`]?)([a-z0-9_]+)\1\s*=/is', $setClause, $cols)) {
                $this->checkWrittenColumns($m[2], $cols[2]);
            }
        }
    }

    /** @param array<int,string> $columns */
    private function checkWrittenColumns(string $table, array $columns): void
    {
        // Only known tables are checked (see method doc: under-detect, never
        // false-positive on a table the static replay can't see).
        if (! isset($this->tables[$table])) {
            return;
        }

        foreach ($columns as $column) {
            if ($column === '' || isset($this->tables[$table][$column])) {
                continue;
            }

            $this->flag(
                'write_column_before_create',
                "writes column '{$column}' on table '{$table}', which is not created by any earlier "
                . 'migration (the write — typically from a seeder this migration runs — would fail on a '
                . 'fresh `migrate` from an empty database with a missing-column error). Add the column '
                . 'before this migration, or guard the write with `Schema::hasColumn` until the column exists.'
            );
        }
    }

    /**
     * Split a quoted, comma-separated column list ("a", "b", "c") into bare
     * column names, tolerating single/double/back-quoting and whitespace.
     *
     * @return array<int,string>
     */
    private function parseColumnList(string $list): array
    {
        $columns = [];
        foreach (explode(',', $list) as $part) {
            $name = trim($part);
            $name = trim($name, "\"`' \t\r\n");
            if ($name !== '') {
                $columns[] = $name;
            }
        }

        return $columns;
    }

    public function whenTableHasColumn(string $table, string $column, Closure $callback): void
    {
        if ($this->hasColumn($table, $column)) {
            $this->table($table, $callback);
        }
    }

    public function whenTableDoesntHaveColumn(string $table, string $column, Closure $callback): void
    {
        if (! $this->hasColumn($table, $column)) {
            $this->table($table, $callback);
        }
    }

    public function withoutForeignKeyConstraints(Closure $callback)
    {
        return $callback();
    }

    /**
     * Everything else a migration might call on the Schema facade (index helpers,
     * foreign-key toggles, introspection) is irrelevant to ordering, so swallow
     * it. Returning null keeps boolean probes falsy and never throws.
     *
     * @param array<int,mixed> $arguments
     */
    public function __call(string $name, array $arguments)
    {
        return null;
    }

    private function makeBlueprint($table, Closure $callback): Blueprint
    {
        // Build the Blueprint purely as a data structure — its callback runs and
        // records columns/commands, but build() is never invoked so no SQL is
        // generated and the connection is never queried.
        return new Blueprint($this->connection, $table, $callback);
    }

    private function applyColumns(string $table, Blueprint $blueprint): void
    {
        foreach ($blueprint->getAddedColumns() as $column) {
            $name = $column->name ?? null;
            if (is_string($name) && $name !== '') {
                $this->tables[$table][$name] = true;
            }
        }

        foreach ($blueprint->getCommands() as $command) {
            $type = $command->name ?? null;

            if ($type === 'dropColumn') {
                foreach ((array) ($command->columns ?? []) as $dropped) {
                    unset($this->tables[$table][$dropped]);
                }
            } elseif ($type === 'renameColumn') {
                $from = $command->from ?? null;
                $to   = $command->to ?? null;
                if (is_string($from) && is_string($to) && isset($this->tables[$table][$from])) {
                    unset($this->tables[$table][$from]);
                    $this->tables[$table][$to] = true;
                }
            }
        }
    }

    /**
     * Flag a foreign key whose referenced table has not been created by any
     * migration replayed so far. `constrained()` and
     * `foreign(...)->references(...)->on(...)` both register a `foreign` command
     * on the blueprint with its target table in the `on` attribute (constrained
     * derives it from the column name), so we can resolve the target without any
     * database introspection.
     */
    private function inspectForeignKeys(string $table, Blueprint $blueprint): void
    {
        foreach ($blueprint->getCommands() as $command) {
            if (($command->name ?? null) !== 'foreign') {
                continue;
            }

            $on = $command->on ?? null;
            if ($on === null) {
                continue; // FK target not resolvable (e.g. raw / incomplete) — skip.
            }

            $target = (string) $on; // may be a Stringable from constrained()
            if ($target === '' || $target === $table) {
                continue; // self-reference: the table already exists at this point.
            }

            if (! isset($this->tables[$target])) {
                $this->flag(
                    'foreign_key',
                    "table '{$table}' declares a foreign key to '{$target}', which is not created "
                    . 'by any earlier migration — a fresh `migrate` from an empty database would fail '
                    . 'with a missing-relation error. Move the migration that creates '
                    . "'{$target}' before this one (or rename this migration to a later date)."
                );
            }
        }
    }

    private function flag(string $type, string $message): void
    {
        $key = $this->currentMigration . '|' . $type . '|' . $message;
        if (isset($this->seen[$key])) {
            return;
        }
        $this->seen[$key] = true;

        $this->violations[] = [
            'migration' => $this->currentMigration,
            'type'      => $type,
            'message'   => $message,
        ];
    }
}
