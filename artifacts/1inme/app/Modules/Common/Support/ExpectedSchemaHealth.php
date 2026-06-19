<?php

namespace App\Modules\Common\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
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
     * The single source of truth for the critical tables/columns that public,
     * hot-path code references and that have a history of (or a high risk of)
     * being added by an edited-after-applied migration.
     *
     * Keep entries here when a migration adds a column to an EXISTING table that
     * page/controller code then reads — those are the ones that silently drift.
     * A whole-table entry (empty column list) flags a table that must exist at
     * all; missing-table detection for un-applied *files* is still SchemaHealth's
     * job, this only catches a table the code hard-depends on.
     *
     * Each column carries the metadata needed to re-create it in place during a
     * one-click {@see self::repair()} (so ops can resolve drift without shell
     * access), mirroring the column definition from the migration that owns it:
     *   - type     : Blueprint method (boolean|timestamp|string|text|unsignedBigInteger)
     *   - nullable : whether the column is nullable (default true)
     *   - default  : default value applied to existing + new rows (omit for none)
     *   - length   : optional length for string columns
     * Keep these in lockstep with the owning migration so a repaired column is
     * byte-for-byte what a fresh migrate would have produced.
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
     * Column names expected on a table (the keys of its {@see self::EXPECTED}
     * spec map). Drives the detection probe in {@see self::compute()}.
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
     * @return array{available:bool, scanned:int, missing:array<int,array{table:string,table_missing:bool,columns:array<int,string>}>, error?:string}
     */
    public static function compute(): array
    {
        try {
            $scanned = 0;
            $missing = [];

            foreach (self::EXPECTED as $table => $columns) {
                $scanned++;
                $columnNames = self::columnsFor($table);

                if (! Schema::hasTable($table)) {
                    $missing[] = [
                        'table'         => $table,
                        'table_missing' => true,
                        'columns'       => $columnNames,
                    ];
                    continue;
                }

                $missingCols = [];
                foreach ($columnNames as $column) {
                    if (! Schema::hasColumn($table, $column)) {
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
