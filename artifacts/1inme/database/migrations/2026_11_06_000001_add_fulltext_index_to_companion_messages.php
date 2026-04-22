<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a fulltext / tsvector index on `companion_messages.content` (and
 * `companion_threads.title`) so the Companion sidebar search stops doing a
 * `LIKE '%term%'` table scan once a user racks up tens of thousands of
 * stored turns.
 *
 * Driver matrix:
 *   - pgsql : functional GIN index on to_tsvector('simple', content)
 *   - mysql : FULLTEXT index on content / title
 *   - sqlite/other : no-op — controller falls back to LIKE
 */
return new class extends Migration {
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(
                "CREATE INDEX IF NOT EXISTS companion_messages_content_fts_idx "
                . "ON companion_messages USING GIN (to_tsvector('simple', content))"
            );
            DB::statement(
                "CREATE INDEX IF NOT EXISTS companion_threads_title_fts_idx "
                . "ON companion_threads USING GIN (to_tsvector('simple', title))"
            );
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement(
                "ALTER TABLE companion_messages "
                . "ADD FULLTEXT companion_messages_content_fts_idx (content)"
            );
            DB::statement(
                "ALTER TABLE companion_threads "
                . "ADD FULLTEXT companion_threads_title_fts_idx (title)"
            );
        }
        // sqlite + anything else: skip silently. Search still works via LIKE.
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("DROP INDEX IF EXISTS companion_messages_content_fts_idx");
            DB::statement("DROP INDEX IF EXISTS companion_threads_title_fts_idx");
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE companion_messages DROP INDEX companion_messages_content_fts_idx");
            DB::statement("ALTER TABLE companion_threads DROP INDEX companion_threads_title_fts_idx");
        }
    }
};
