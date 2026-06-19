<?php

namespace App\Modules\Common\Support;

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
     * @var array<string,array<int,string>>
     */
    public const EXPECTED = [
        // 18+ creator columns added across the life of Task #1208 — the original
        // edited-after-applied drift that 500'd the public /creators directory
        // and the adult moderation / payout flows.
        'users' => [
            'adult_content_enabled',
            'adult_content_enabled_at',
            'age_verified_at',
            'adult_flag_suspended_at',
            'adult_flag_suspended_reason',
            'adult_flag_suspended_by',
        ],
        // Unified link settings (Task #1467) promoted these onto canonical Link
        // columns; public biolink/SEO rendering reads them directly.
        'links' => [
            'seo_title',
            'seo_description',
            'seo_image',
            'favicon',
            'visibility',
        ],
    ];

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

                if (! Schema::hasTable($table)) {
                    $missing[] = [
                        'table'         => $table,
                        'table_missing' => true,
                        'columns'       => array_values($columns),
                    ];
                    continue;
                }

                $missingCols = [];
                foreach ($columns as $column) {
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
