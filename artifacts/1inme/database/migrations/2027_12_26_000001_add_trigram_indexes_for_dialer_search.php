<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trigram (pg_trgm) GIN indexes so the universal Dialer finder
 * (App\Modules\User\Support\DialerSearch) stops doing sequential
 * `ILIKE '%term%'` table scans once an account racks up thousands of
 * contacts / links / followed creators.
 *
 * The finder runs substring ILIKE matches per keystroke across:
 *   - contacts   : display_name / given_name / family_name / organization
 *   - links      : alias / title / seo_title / verified_name
 *   - link_aliases: alias (back-half aliases)
 *   - users      : name / handle (People group)
 *
 * A plain btree index can't serve `ILIKE '%x%'`; a GIN index with
 * `gin_trgm_ops` can, turning full scans into index probes.
 *
 * Driver / privilege matrix:
 *   - pgsql with pg_trgm : index-backed substring search
 *   - pgsql without the extension (locked-down role) : skipped silently;
 *     search still works via sequential ILIKE, just slower
 *   - sqlite / mysql / other : no-op — search falls back to LIKE
 *
 * Runs outside a transaction and builds CONCURRENTLY so it never takes a
 * blocking write lock on the shared production RDS.
 */
return new class extends Migration {
    /** Build indexes concurrently — cannot run inside a transaction. */
    public $withinTransaction = false;

    /** @var array<int,array{0:string,1:string,2:string}> [table, index, column] */
    private array $indexes = [
        ['contacts', 'contacts_display_name_trgm_idx', 'display_name'],
        ['contacts', 'contacts_given_name_trgm_idx', 'given_name'],
        ['contacts', 'contacts_family_name_trgm_idx', 'family_name'],
        ['contacts', 'contacts_organization_trgm_idx', 'organization'],
        ['links', 'links_alias_trgm_idx', 'alias'],
        ['links', 'links_title_trgm_idx', 'title'],
        ['links', 'links_seo_title_trgm_idx', 'seo_title'],
        ['links', 'links_verified_name_trgm_idx', 'verified_name'],
        ['link_aliases', 'link_aliases_alias_trgm_idx', 'alias'],
        ['users', 'users_name_trgm_idx', 'name'],
        ['users', 'users_handle_trgm_idx', 'handle'],
    ];

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return; // trigram indexes are Postgres-only
        }

        // pg_trgm is a trusted extension on PG13+, so a normal DB user can
        // enable it. Wrap in try/catch so a locked-down role never breaks the
        // migration — the finder still works via sequential ILIKE.
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        } catch (\Throwable $e) {
            return;
        }

        if (!DB::table('pg_extension')->where('extname', 'pg_trgm')->exists()) {
            return;
        }

        foreach ($this->indexes as [$table, $index, $column]) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                continue;
            }
            DB::statement(
                "CREATE INDEX CONCURRENTLY IF NOT EXISTS {$index} "
                . "ON {$table} USING GIN ({$column} gin_trgm_ops)"
            );
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->indexes as [, $index]) {
            DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$index}");
        }
    }
};
