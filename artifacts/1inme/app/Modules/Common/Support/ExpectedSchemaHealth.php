<?php

namespace App\Modules\Common\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detects critical tables/columns that the application code depends on but that
 * are MISSING from the live database — even though the migration that should
 * have created them is recorded as "Ran".
 *
 * Why this exists: {@see SchemaHealth} only diffs migration *files* against the
 * `migrations` repository table (the `migrate:status` comparison), so it is
 * completely blind to the most insidious drift class — a migration that was
 * already applied (and recorded) on a long-lived database and was *later edited*
 * to add new columns. Laravel never re-runs a recorded migration, so those
 * newer columns silently never reach environments that applied the earlier
 * version, while `migrate:status` keeps reporting 0 pending. That exact failure
 * took the public /creators page down with a 500 when the Task #1208 migration
 * was edited to add the 18+ moderation columns to `users`
 * (see 2027_06_26_000001_backfill_missing_adult_flag_columns_on_users).
 *
 * {@see WorkspaceColumnHealth} covers the same failure shape but ONLY for the
 * workspace-scoping columns (workspace_id / created_by_user_id). This class is
 * the general counterpart: a curated manifest of high-value, public-path
 * columns/tables that, if missing, would 500 a user — probed directly against
 * the live schema regardless of what the `migrations` table claims.
 *
 * Every entry point is defensive: a DB that is unreachable (or otherwise errors
 * while we probe it) reports `available => false` rather than throwing, so the
 * surfaces that consume this never crash because of the very tool meant to keep
 * them healthy. Mirrors {@see SchemaHealth}, {@see WorkspaceColumnHealth} and
 * {@see TemplateDesignHealth}.
 */
class ExpectedSchemaHealth
{
    private const CACHE_KEY = 'expected_schema_health:report';
    private const CACHE_TTL = 120; // seconds

    /**
     * Tables that exist in the migration replay but are intentionally NOT diffed
     * against the live DB, to control false positives. These are framework /
     * infrastructure tables whose presence depends on runtime configuration
     * (queue/cache/session driver) rather than on the application schema, so a
     * legitimately-absent one must not raise a drift alert.
     *
     * @var array<int,string>
     */
    public const IGNORED_TABLES = [
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
        'password_reset_tokens',
        'password_resets',
        'migrations',
    ];

    /**
     * Specific columns that the migration replay records but that may legitimately
     * be absent from the live DB (e.g. columns created/altered only through raw
     * `DB::statement` SQL that the Blueprint recorder cannot see). Keyed by table.
     *
     * @var array<string,array<int,string>>
     */
    public const IGNORED_COLUMNS = [];

    /**
     * Repair metadata for high-value columns that {@see self::repair()} can
     * re-create in place, so ops can resolve drift without shell access.
     *
     * NOTE: this is NO LONGER the detection source — {@see self::compute()} now
     * derives the full expected schema automatically by replaying the migration
     * files (see {@see SchemaManifest}), so drift on ANY column is caught, not
     * just a curated few. This map only supplies the column definition that
     * repair() needs to ALTER a missing column back into place, mirroring the
     * column definition from the migration that owns it:
     *   - type     : Blueprint method (boolean|timestamp|string|text|unsignedBigInteger)
     *   - nullable : whether the column is nullable (default true)
     *   - default  : default value applied to existing + new rows (omit for none)
     *   - length   : optional length for string columns
     * Columns surfaced by the manifest that are absent here are still detected
     * and reported; repair() falls back to a nullable string when re-creating one
     * it has no spec for. Keep these in lockstep with the owning migration so a
     * repaired column is byte-for-byte what a fresh migrate would have produced.
     *
     * @var array<string,array<string,array{type:string,nullable?:bool,default?:mixed,length?:int}>>
     */
    public const EXPECTED = [
        // 18+ creator columns added across the life of Task #1208 — the original
        // edited-after-applied drift that 500'd the public /creators directory
        // and the adult moderation / payout flows.
        // (see 2027_06_26_000001_backfill_missing_adult_flag_columns_on_users and
        //  2027_05_07_010000_create_creator_payment_connections)
        'users' => [
            'adult_content_enabled'        => ['type' => 'boolean', 'nullable' => false, 'default' => false],
            'adult_content_enabled_at'     => ['type' => 'timestamp', 'nullable' => true],
            'age_verified_at'              => ['type' => 'timestamp', 'nullable' => true],
            'adult_flag_suspended_at'      => ['type' => 'timestamp', 'nullable' => true],
            'adult_flag_suspended_reason'  => ['type' => 'string', 'length' => 500, 'nullable' => true],
            'adult_flag_suspended_by'      => ['type' => 'unsignedBigInteger', 'nullable' => true],
        ],
        // Unified link settings (Task #1467) promoted these onto canonical Link
        // columns; public biolink/SEO rendering reads them directly.
        // (see 2024_01_02_000004_create_links_table, 2026_04_13_144834_add_favicon_to_links_table,
        //  2026_05_01_020000_add_visibility_and_demo_flags)
        'links' => [
            'seo_title'       => ['type' => 'string', 'nullable' => true],
            'seo_description' => ['type' => 'text', 'nullable' => true],
            'seo_image'       => ['type' => 'string', 'nullable' => true],
            'favicon'         => ['type' => 'string', 'nullable' => true],
            'visibility'      => ['type' => 'string', 'length' => 20, 'nullable' => false, 'default' => 'public'],
        ],
    ];

    /**
     * Column names that have repair metadata for the given table (the keys of its
     * {@see self::EXPECTED} spec map). Used by {@see self::repair()}.
     *
     * @return array<int,string>
     */
    public static function columnsFor(string $table): array
    {
        return array_keys(self::EXPECTED[$table] ?? []);
    }

    /**
     * Freshly probe the live DB for missing expected tables/columns.
     *
     * The expected set is no longer hand-maintained: it is derived from the net
     * effect of every migration file via {@see SchemaManifest}, so drift on ANY
     * column added by an edited-after-applied migration is caught — not just a
     * curated few. Intentionally-dropped columns are already absent from the
     * manifest (the replay folds in `dropColumn`/`dropMorphs`/etc.), and the
     * ignore lists above suppress the residual false-positive risk.
     *
     * @return array{available:bool, scanned:int, missing:array<int,array{table:string,table_missing:bool,columns:array<int,string>}>, error?:string}
     */
    public static function compute(): array
    {
        try {
            $manifest = SchemaManifest::cached();

            // If the manifest could not be derived (e.g. migration files
            // unreadable), report unknown so callers degrade gracefully rather
            // than treating an empty expected set as "all healthy".
            if (! ($manifest['available'] ?? false)) {
                return [
                    'available' => false,
                    'scanned'   => 0,
                    'missing'   => [],
                    'error'     => $manifest['error'] ?? 'schema manifest unavailable',
                ];
            }

            $expected = self::filterIgnored($manifest['tables'] ?? []);

            // Snapshot the live schema in as few round-trips as possible — the
            // shared DB is cross-region, so one bulk query beats ~250 per-table
            // probes by two orders of magnitude.
            $live = self::liveSchema();

            $scanned = 0;
            $missing = [];

            foreach ($expected as $table => $columns) {
                $scanned++;

                if (! isset($live[$table])) {
                    $missing[] = [
                        'table'         => $table,
                        'table_missing' => true,
                        'columns'       => array_values($columns),
                    ];
                    continue;
                }

                $liveCols    = $live[$table];
                $missingCols = [];
                foreach ($columns as $column) {
                    if (! isset($liveCols[$column])) {
                        $missingCols[] = $column;
                    }
                }

                if (! empty($missingCols)) {
                    $missing[] = [
                        'table'         => $table,
                        'table_missing' => false,
                        'columns'       => $missingCols,
                    ];
                }
            }

            return [
                'available' => true,
                'scanned'   => $scanned,
                'missing'   => $missing,
            ];
        } catch (\Throwable $e) {
            return [
                'available' => false,
                'scanned'   => 0,
                'missing'   => [],
                'error'     => $e->getMessage(),
            ];
        }
    }

    /**
     * Snapshot the live database schema as table => set<column> in as few
     * round-trips as possible. On Postgres this is a single information_schema
     * query (the shared DB is cross-region, so a per-table fallback of ~250
     * queries would be unacceptably slow). Other drivers fall back to the
     * portable per-table builder API.
     *
     * @return array<string,array<string,true>>
     */
    private static function liveSchema(): array
    {
        $connection = DB::connection();

        if ($connection->getDriverName() === 'pgsql') {
            $rows = $connection->select(
                'select table_name, column_name from information_schema.columns '
                . 'where table_schema = any (current_schemas(false))'
            );

            $map = [];
            foreach ($rows as $row) {
                $map[$row->table_name][$row->column_name] = true;
            }

            return $map;
        }

        // Portable fallback (e.g. sqlite test DBs): one listing query + one per
        // existing table. Acceptable because those databases are local/fast.
        $map = [];
        foreach (Schema::getTableListing() as $table) {
            $table = self::unqualify($table);
            $map[$table] = array_fill_keys(Schema::getColumnListing($table), true);
        }

        return $map;
    }

    /** Strip a leading "schema." qualifier some drivers prepend to table names. */
    private static function unqualify(string $table): string
    {
        $pos = strrpos($table, '.');

        return $pos === false ? $table : substr($table, $pos + 1);
    }

    /**
     * Apply the ignore lists to the derived manifest: drop whole ignored tables
     * and strip ignored columns. Tables with no columns left to check are dropped.
     *
     * @param array<string,array<int,string>> $tables
     * @return array<string,array<int,string>>
     */
    private static function filterIgnored(array $tables): array
    {
        $ignoredTables  = array_flip(self::IGNORED_TABLES);
        $ignoredColumns = self::IGNORED_COLUMNS;

        $out = [];
        foreach ($tables as $table => $columns) {
            if (isset($ignoredTables[$table])) {
                continue;
            }

            if (! empty($ignoredColumns[$table])) {
                $columns = array_values(array_diff($columns, $ignoredColumns[$table]));
            }

            $out[$table] = $columns;
        }

        return $out;
    }

    /**
     * Cached variant for hot paths (dashboard render, readiness endpoint).
     * Falls back to a direct compute if the cache store itself is unavailable.
     *
     * @return array{available:bool, scanned:int, missing:array<int,array{table:string,table_missing:bool,columns:array<int,string>}>, error?:string}
     */
    public static function cached(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => self::compute());
        } catch (\Throwable $e) {
            return self::compute();
        }
    }

    /** Drop the cached report so the next read reflects reality immediately. */
    public static function flush(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable $e) {
            // best-effort
        }
    }

    /**
     * Add any missing expected columns in place, re-creating each one from its
     * {@see self::EXPECTED} spec (type / nullable / default / length) so the
     * repaired column matches what the owning migration would have produced.
     *
     * Every add is guarded by `Schema::hasColumn`, so this is idempotent and a
     * no-op where the column is already present. A column with a `default` lands
     * that default on existing rows too (Postgres applies it during the ALTER),
     * which is the backfill for `visibility` ('public') and `adult_content_enabled`
     * (false); columns without a sensible default are added nullable. A column
     * with no spec falls back to a nullable string so an unknown manifest entry
     * still repairs rather than throwing.
     *
     * Whole-missing tables cannot be repaired here (we have no full table schema)
     * — they are returned under `unrepairable` and still need `migrate --force`.
     *
     * @param  array<int,array{table:string,table_missing:bool,columns:array<int,string>}>|null  $missing
     *         Pre-computed missing set; recomputed when null.
     * @return array{added:array<string,array<int,string>>, unrepairable:array<int,string>}
     */
    public static function repair(?array $missing = null): array
    {
        if ($missing === null) {
            $report  = self::compute();
            $missing = $report['available'] ? $report['missing'] : [];
        }

        $added        = [];
        $unrepairable = [];

        foreach ($missing as $entry) {
            $table = $entry['table'] ?? null;
            if (! $table) {
                continue;
            }

            // A whole missing table needs its full create migration — we can
            // only re-create individual columns here.
            if (! empty($entry['table_missing']) || ! Schema::hasTable($table)) {
                $unrepairable[] = $table;
                continue;
            }

            $cols      = $entry['columns'] ?? self::columnsFor($table);
            $addedCols = [];

            Schema::table($table, function (Blueprint $t) use ($table, $cols, &$addedCols) {
                $previous = null;
                foreach ($cols as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $previous = $column;
                        continue;
                    }
                    self::defineColumn($t, $column, self::EXPECTED[$table][$column] ?? [], $previous);
                    $addedCols[] = $column;
                    $previous    = $column;
                }
            });

            if (! empty($addedCols)) {
                $added[$table] = $addedCols;
            }
        }

        if (! empty($added)) {
            self::flush();
        }

        return ['added' => $added, 'unrepairable' => array_values(array_unique($unrepairable))];
    }

    /**
     * Add a single column to the running Blueprint from its manifest spec.
     *
     * @param array{type?:string,nullable?:bool,default?:mixed,length?:int} $spec
     */
    private static function defineColumn(Blueprint $t, string $column, array $spec, ?string $after): void
    {
        $type = $spec['type'] ?? 'string';

        switch ($type) {
            case 'boolean':
                $col = $t->boolean($column);
                break;
            case 'timestamp':
                $col = $t->timestamp($column);
                break;
            case 'text':
                $col = $t->text($column);
                break;
            case 'unsignedBigInteger':
                $col = $t->unsignedBigInteger($column);
                break;
            case 'string':
            default:
                $col = isset($spec['length'])
                    ? $t->string($column, (int) $spec['length'])
                    : $t->string($column);
                break;
        }

        // Nullable unless the spec explicitly says otherwise (default true).
        if ($spec['nullable'] ?? true) {
            $col->nullable();
        }

        if (array_key_exists('default', $spec)) {
            $col->default($spec['default']);
        }

        if ($after !== null) {
            $col->after($after);
        }
    }

    public static function missingCount(bool $fresh = false): int
    {
        $report = $fresh ? self::compute() : self::cached();
        return count($report['missing'] ?? []);
    }

    public static function hasMissing(bool $fresh = false): bool
    {
        return self::missingCount($fresh) > 0;
    }

    /**
     * Stable identifier for a missing entry ("users:age_verified_at+..." or
     * "users:__table__") used by the alert command to detect when the set
     * changes between runs (so newly-missing columns re-alert mid-episode).
     *
     * @param array{table:string,table_missing:bool,columns:array<int,string>} $row
     */
    public static function ref(array $row): string
    {
        if (! empty($row['table_missing'])) {
            return ($row['table'] ?? '?') . ':__table__';
        }
        $cols = $row['columns'] ?? [];
        sort($cols);
        return ($row['table'] ?? '?') . ':' . implode('+', $cols);
    }
}
