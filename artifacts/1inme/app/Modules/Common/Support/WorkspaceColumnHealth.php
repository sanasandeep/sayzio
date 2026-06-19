<?php

namespace App\Modules\Common\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detects (and optionally repairs) workspace-scoping columns that are missing
 * from the live database even though the migration that should have added them
 * is recorded as "Ran".
 *
 * Why this exists: {@see SchemaHealth} only diffs migration *files* against the
 * `migrations` repository table — it cannot see a migration that committed its
 * `migrations` row but whose `ALTER TABLE ... ADD workspace_id` never actually
 * landed (an interrupted run). That exact failure already bit `form_submissions`
 * (fixed by 2027_06_22_000001_fix_missing_form_submissions_workspace_id). Any of
 * the other ~20 tables in the original workspace migration could be silently
 * missing its `workspace_id` (or `created_by_user_id`) column from the same
 * interrupted run and would throw SQLSTATE[42703] 500s the moment a
 * {@see \App\Modules\User\Concerns\BelongsToWorkspace}-scoped query runs.
 *
 * This probes the columns directly, independent of what the `migrations` table
 * claims, so the gap is surfaced proactively (admin dashboard banner, scheduled
 * alert email/in-app) instead of via user-facing errors — and the same guarded,
 * idempotent add+backfill used by the form_submissions fix can repair it.
 *
 * Every entry point is defensive: a DB that is unreachable (or otherwise errors
 * while we probe it) reports `available => false` rather than throwing, so the
 * surfaces that consume this never crash because of the very tool meant to keep
 * them healthy. Mirrors {@see SchemaHealth} and {@see TemplateDesignHealth}.
 */
class WorkspaceColumnHealth
{
    private const CACHE_KEY = 'workspace_column_health:report';
    private const CACHE_TTL = 120; // seconds

    /**
     * Every table that the workspace migration adds `workspace_id` to. Kept in
     * lockstep with 2026_05_02_000001_create_workspaces_tables (the canonical
     * list) — the single source of truth for what *should* carry the column.
     *
     * @var array<int,string>
     */
    public const TABLES = [
        'links', 'projects', 'creator_posts', 'forms', 'subscribers',
        'pixels', 'qr_codes', 'splash_pages', 'user_files', 'contacts',
        'referrals', 'referral_rewards', 'social_proofs',
        'calendar_accounts', 'integration_configs', 'inbox_replies',
        'inbox_forward_destinations', 'link_performance_snapshots',
        'follows', 'form_submissions', 'domains',
    ];

    /**
     * Tables that do NOT get a `created_by_user_id` column — rows representing
     * visitors / system attribution. Mirrors the original migration's
     * `$skipAttribution` list exactly.
     *
     * @var array<int,string>
     */
    private const SKIP_ATTRIBUTION = [
        'subscribers', 'follows', 'form_submissions',
        'referrals', 'referral_rewards', 'inbox_forward_deliveries',
        'link_performance_snapshots', 'domains',
    ];

    /**
     * Direct owner column used to map a row to its workspace during backfill
     * (workspace = owner's default workspace). Mirrors the
     * 2026_05_02_000002_backfill_default_workspaces table specs.
     *
     * @var array<string,string>
     */
    private const OWNER_COLUMNS = [
        'links' => 'user_id', 'projects' => 'user_id', 'creator_posts' => 'user_id',
        'forms' => 'user_id', 'subscribers' => 'user_id', 'pixels' => 'user_id',
        'qr_codes' => 'user_id', 'splash_pages' => 'user_id', 'user_files' => 'user_id',
        'contacts' => 'user_id', 'referrals' => 'referrer_id', 'referral_rewards' => 'user_id',
        'social_proofs' => 'user_id', 'calendar_accounts' => 'user_id',
        'integration_configs' => 'user_id', 'inbox_replies' => 'user_id',
        'inbox_forward_destinations' => 'user_id', 'follows' => 'creator_id',
        'domains' => 'user_id',
    ];

    /**
     * Tables with no direct owner column — workspace is derived from a parent
     * row instead. [childTable => [parentTable, foreignKey]].
     *
     * @var array<string,array{0:string,1:string}>
     */
    private const DERIVED_PARENTS = [
        'form_submissions'            => ['forms', 'form_id'],
        'link_performance_snapshots'  => ['links', 'link_id'],
    ];

    /** Whether a table should carry a `created_by_user_id` column. */
    public static function expectsAttribution(string $table): bool
    {
        return in_array($table, self::TABLES, true)
            && ! in_array($table, self::SKIP_ATTRIBUTION, true);
    }

    /**
     * Freshly probe the live DB for missing workspace-scoping columns.
     *
     * @return array{available:bool, scanned:int, missing:array<int,array{table:string,columns:array<int,string>}>, error?:string}
     */
    public static function compute(): array
    {
        try {
            $scanned = 0;
            $missing = [];

            foreach (self::TABLES as $table) {
                // A table that does not exist at all is the migration / pending
                // -migration system's concern (SchemaHealth), not ours — we only
                // flag columns missing from tables that DO exist.
                if (! Schema::hasTable($table)) {
                    continue;
                }
                $scanned++;

                $cols = [];
                if (! Schema::hasColumn($table, 'workspace_id')) {
                    $cols[] = 'workspace_id';
                }
                if (self::expectsAttribution($table) && ! Schema::hasColumn($table, 'created_by_user_id')) {
                    $cols[] = 'created_by_user_id';
                }

                if (! empty($cols)) {
                    $missing[] = ['table' => $table, 'columns' => $cols];
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
     * Cached variant for hot paths (dashboard render). Falls back to a direct
     * compute if the cache store itself is unavailable.
     *
     * @return array{available:bool, scanned:int, missing:array<int,array{table:string,columns:array<int,string>}>, error?:string}
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
     * Add any missing workspace-scoping columns (guarded by Schema::hasColumn so
     * it is a no-op where already present) and backfill them, mirroring the
     * column definitions + backfill of the original workspace migrations and the
     * form_submissions repair. Safe to run repeatedly.
     *
     * @param  array<int,array{table:string,columns:array<int,string>}>|null  $missing
     *         Pre-computed missing set; recomputed when null.
     * @return array{added:array<string,array<int,string>>, backfilled:array<int,string>}
     */
    public static function repair(?array $missing = null): array
    {
        if ($missing === null) {
            $report  = self::compute();
            $missing = $report['available'] ? $report['missing'] : [];
        }

        $added      = [];
        $repaired   = [];

        foreach ($missing as $entry) {
            $table = $entry['table'] ?? null;
            if (! $table || ! Schema::hasTable($table)) {
                continue;
            }

            $addedCols = [];
            Schema::table($table, function (Blueprint $t) use ($table, &$addedCols) {
                if (! Schema::hasColumn($table, 'workspace_id')) {
                    $t->unsignedBigInteger('workspace_id')->nullable()->after('id');
                    $t->index('workspace_id');
                    $addedCols[] = 'workspace_id';
                }
                if (self::expectsAttribution($table) && ! Schema::hasColumn($table, 'created_by_user_id')) {
                    $after = Schema::hasColumn($table, 'workspace_id') ? 'workspace_id' : 'id';
                    $t->unsignedBigInteger('created_by_user_id')->nullable()->after($after);
                    $addedCols[] = 'created_by_user_id';
                }
            });

            if (! empty($addedCols)) {
                $added[$table] = $addedCols;
                $repaired[]    = $table;
            }
        }

        if (! empty($repaired)) {
            self::backfill($repaired);
            self::flush();
        }

        return ['added' => $added, 'backfilled' => array_values($repaired)];
    }

    /**
     * Backfill workspace_id (and created_by_user_id) on the given tables,
     * mirroring 2026_05_02_000002_backfill_default_workspaces. Idempotent —
     * only fills rows still NULL.
     *
     * @param array<int,string> $tables
     */
    private static function backfill(array $tables): void
    {
        $ownerToWs = DB::table('workspaces')->pluck('id', 'owner_user_id')->all();

        foreach ($tables as $table) {
            if (! Schema::hasColumn($table, 'workspace_id')) {
                continue;
            }

            // Direct-owner tables: map via the owner's default workspace.
            if (isset(self::OWNER_COLUMNS[$table])
                && Schema::hasColumn($table, self::OWNER_COLUMNS[$table])) {
                $ownerColumn = self::OWNER_COLUMNS[$table];
                DB::table($table)->whereNull('workspace_id')->orderBy('id')
                    ->chunkById(500, function ($rows) use ($table, $ownerColumn, $ownerToWs) {
                        foreach ($rows as $row) {
                            $oid = $row->{$ownerColumn} ?? null;
                            if (! $oid) continue;
                            $wsId = $ownerToWs[$oid] ?? null;
                            if (! $wsId) continue;
                            $patch = ['workspace_id' => $wsId];
                            if (Schema::hasColumn($table, 'created_by_user_id') && empty($row->created_by_user_id)) {
                                $patch['created_by_user_id'] = $oid;
                            }
                            DB::table($table)->where('id', $row->id)->update($patch);
                        }
                    });
            }

            // Derived tables: inherit workspace_id from a parent row.
            if (isset(self::DERIVED_PARENTS[$table])) {
                [$parent, $fk] = self::DERIVED_PARENTS[$table];
                if (Schema::hasTable($parent) && Schema::hasColumn($parent, 'workspace_id')) {
                    DB::statement("UPDATE {$table}
                                   SET workspace_id = (SELECT workspace_id FROM {$parent} WHERE {$parent}.id = {$table}.{$fk})
                                   WHERE workspace_id IS NULL");
                }
            }
        }
    }

    /**
     * Stable identifier for a missing-column entry ("links" / "links:workspace_id")
     * used by the alert command to detect when the set changes between runs.
     *
     * @param array{table:string,columns:array<int,string>} $row
     */
    public static function ref(array $row): string
    {
        $cols = $row['columns'] ?? [];
        sort($cols);
        return ($row['table'] ?? '?') . ':' . implode('+', $cols);
    }
}
