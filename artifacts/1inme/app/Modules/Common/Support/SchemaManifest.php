<?php

namespace App\Modules\Common\Support;

use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Derives the *expected* database schema (table => columns) by replaying every
 * migration file's net effect, so nothing has to be hand-maintained.
 *
 * Why this exists: {@see ExpectedSchemaHealth} used to diff a hand-curated array
 * of critical tables/columns against the live DB. That only ever protected the
 * columns someone remembered to add, so a future edited-after-applied migration
 * on an un-listed column would still silently slip through. This class removes
 * the manual upkeep: it walks the migration files in order, records each
 * `create`/`table`/`drop`/`rename` operation, and folds them into the net set of
 * columns each table *should* have — which {@see ExpectedSchemaHealth} then
 * diffs against the live database to catch drift on ANY column.
 *
 * How it stays safe (it never touches data):
 *   - The `Schema` facade is swapped for a {@see SchemaManifestRecorder} that
 *     only records column adds/drops in memory; `build()` is never called, so no
 *     DDL is ever generated or executed.
 *   - The whole replay runs inside `Connection::pretend()`, so any data-backfill
 *     statement a migration runs (via the `DB` facade / Eloquent) is inert and
 *     `select`s return `[]` — nothing is written, even against production.
 *   - Each migration's `up()` is wrapped in a try/catch: a backfill line that
 *     needs real rows can throw under pretend, but the schema operations in the
 *     closure already recorded before the throw, so replay degrades gracefully
 *     (it under-detects rather than producing a false positive).
 *
 * Known blind spot (mirrors the previous curated approach): columns added or
 * dropped via raw `DB::statement('ALTER TABLE ...')` are invisible to the
 * recorder. Raw *adds* are simply not expected (a missed detection, never a
 * false positive); there are currently no raw column *drops* in this codebase,
 * which is what would otherwise produce a false positive.
 *
 * Defensive like its siblings ({@see SchemaHealth}, {@see ExpectedSchemaHealth}):
 * any failure reports `available => false` rather than throwing.
 */
class SchemaManifest
{
    private const CACHE_TTL = 3600; // seconds — manifest only changes when migration files do

    /**
     * Build the expected table => columns map by replaying the migration files.
     *
     * @return array{available:bool, tables:array<string,array<int,string>>, error?:string}
     */
    public static function build(): array
    {
        try {
            $migrator = app('migrator');

            $paths = array_values(array_unique(array_merge(
                $migrator->paths(),
                [database_path('migrations')]
            )));

            // name => path, already ordered by migration name.
            $files = $migrator->getMigrationFiles($paths);

            $connection = DB::connection();
            $recorder   = new SchemaManifestRecorder($connection);

            // Capture the real schema builder so we can restore it afterwards.
            $original = app('db.schema');

            $replay = function () use ($files, $recorder) {
                Schema::swap($recorder);

                foreach ($files as $path) {
                    try {
                        $migration = self::resolveMigration($path);
                        if ($migration === null || ! method_exists($migration, 'up')) {
                            continue;
                        }
                        $migration->up();
                    } catch (\Throwable $e) {
                        // A data-backfill line can throw under pretend (no real
                        // rows); the schema ops already recorded — keep going.
                    }
                }
            };

            // Replaying backfill migrations under pretend makes their inserts
            // inert, but the id-fetch select then returns [] and Postgres'
            // processor emits cosmetic "undefined array key 0" warnings. Swallow
            // low-severity notices for the duration so the hourly command and
            // dashboard render don't spam the logs; fatals are unaffected.
            set_error_handler(
                static fn () => true,
                E_WARNING | E_NOTICE | E_DEPRECATED | E_USER_WARNING | E_USER_NOTICE | E_USER_DEPRECATED
            );

            try {
                // pretend() neutralises any DB writes the migrations attempt.
                $connection->pretend($replay);
            } finally {
                restore_error_handler();
                // Always restore the real schema builder, even on failure.
                Schema::swap($original);
            }

            return [
                'available' => true,
                'tables'    => $recorder->tables(),
            ];
        } catch (\Throwable $e) {
            return [
                'available' => false,
                'tables'    => [],
                'error'     => $e->getMessage(),
            ];
        }
    }

    /**
     * Cached variant. The cache key is fingerprinted by the migration files on
     * disk (name + mtime), so the manifest is rebuilt automatically whenever a
     * migration is added or edited — no manual cache busting needed.
     *
     * @return array{available:bool, tables:array<string,array<int,string>>, error?:string}
     */
    public static function cached(): array
    {
        try {
            return Cache::remember(self::cacheKey(), self::CACHE_TTL, fn () => self::build());
        } catch (\Throwable $e) {
            return self::build();
        }
    }

    /** Drop every cached manifest variant so the next read rebuilds it. */
    public static function flush(): void
    {
        try {
            Cache::forget(self::cacheKey());
        } catch (\Throwable $e) {
            // best-effort
        }
    }

    /**
     * Fingerprint the migration files so the cache key changes the moment a
     * migration is added or its contents change (mtime moves on edit).
     */
    private static function cacheKey(): string
    {
        try {
            $dir   = database_path('migrations');
            $files = glob($dir . DIRECTORY_SEPARATOR . '*.php') ?: [];
            sort($files);

            $parts = [];
            foreach ($files as $file) {
                $parts[] = basename($file) . ':' . (@filemtime($file) ?: 0);
            }

            return 'schema_manifest:' . md5(implode('|', $parts));
        } catch (\Throwable $e) {
            return 'schema_manifest:fallback';
        }
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

        // Named-class fallback: derive the class from the file name.
        $name  = basename($path, '.php');
        $class = Str::studly(implode('_', array_slice(explode('_', $name), 4)));

        return class_exists($class) ? new $class : null;
    }
}

/**
 * In-memory stand-in for the schema Builder used only during manifest replay.
 * It records what each migration declares (added/dropped columns, created and
 * dropped tables) without ever generating or running SQL. Guard calls inside
 * migrations (`Schema::hasColumn(...)`) are answered from the schema accumulated
 * so far, so additive backfills behave exactly as a build-from-scratch would.
 *
 * @internal
 */
class SchemaManifestRecorder
{
    private $connection;

    /** @var array<string,array<string,true>> table => set of columns */
    private array $tables = [];

    public function __construct($connection)
    {
        $this->connection = $connection;
    }

    /**
     * Net expected schema: table => ordered list of columns.
     *
     * @return array<string,array<int,string>>
     */
    public function tables(): array
    {
        $out = [];
        foreach ($this->tables as $table => $columns) {
            $out[$table] = array_keys($columns);
        }
        ksort($out);

        return $out;
    }

    public function create($table, Closure $callback): void
    {
        $blueprint = $this->makeBlueprint($table, $callback);
        // A create starts the table fresh.
        $this->tables[$table] = [];
        $this->applyBlueprint($table, $blueprint);
    }

    public function table($table, Closure $callback): void
    {
        $blueprint = $this->makeBlueprint($table, $callback);
        $this->tables[$table] ??= [];
        $this->applyBlueprint($table, $blueprint);
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
        if (isset($this->tables[$from])) {
            $this->tables[$to] = $this->tables[$from];
            unset($this->tables[$from]);
        }
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
     * Migrations sometimes branch on the driver via
     * `Schema::getConnection()->getDriverName()`. Without this method the
     * magic __call returned null and the null-deref aborted the whole
     * migration's replay, silently dropping any columns recorded later in
     * the same up() (e.g. users.mobile in the create_otps migration).
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Everything else a migration might call on the Schema facade (index helpers,
     * foreign-key toggles, introspection) is irrelevant to the column manifest,
     * so swallow it. Returning null keeps boolean probes falsy and never throws.
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

    private function applyBlueprint(string $table, Blueprint $blueprint): void
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
}
