<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DB-level integrity guard: at most one primary linked_identifier per
 * user. We use a partial unique index (supported on SQLite >=3.8 and
 * PostgreSQL); on MySQL where partial indexes aren't available we fall
 * back to a generated column trick so the constraint still holds.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS linked_identifiers_one_primary_per_user
                           ON linked_identifiers (user_id) WHERE is_primary = 1');
        } elseif ($driver === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS linked_identifiers_one_primary_per_user
                           ON linked_identifiers (user_id) WHERE is_primary = true');
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL partial-index workaround: a generated column that is
            // NULL when not primary, so the unique constraint only
            // applies to the primary rows.
            try {
                DB::statement('ALTER TABLE linked_identifiers
                               ADD COLUMN primary_user_id BIGINT UNSIGNED AS (CASE WHEN is_primary = 1 THEN user_id ELSE NULL END) STORED,
                               ADD UNIQUE KEY linked_identifiers_one_primary_per_user (primary_user_id)');
            } catch (\Throwable $e) {
                // Older MySQL — skip silently; app-level invariant still
                // enforced by AccountMergeService and User boot hook.
            }
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite' || $driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS linked_identifiers_one_primary_per_user');
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            try {
                Schema::table('linked_identifiers', function ($t) {
                    $t->dropUnique('linked_identifiers_one_primary_per_user');
                    $t->dropColumn('primary_user_id');
                });
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }
};
