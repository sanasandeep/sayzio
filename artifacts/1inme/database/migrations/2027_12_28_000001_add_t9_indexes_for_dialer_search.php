<?php

use App\Modules\User\Support\DialerT9;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Functional trigram (pg_trgm) GIN indexes on the T9 encoding of names so the
 * dialer's keypad-spelled-name search (T9 smart-dial) can be matched entirely
 * in SQL instead of loading up to 200 candidate users / 300 contacts into PHP
 * and looping with DialerT9::matches() on every keystroke.
 *
 * The search now runs `DialerT9::sqlEncode(<name>) LIKE '%526%'`; these indexes
 * (built on the *identical* immutable expression) turn that substring match
 * into an index probe so it no longer scales with the size of a creator's
 * reachable set (huge follow / contact lists):
 *   - users    : sqlEncode(name)                       (People group)
 *   - contacts : sqlEncode(display_name ?: given+family) (Contacts group)
 *
 * Driver / privilege matrix mirrors the plain-column trigram index migration:
 *   - pgsql with pg_trgm : index-backed T9 substring search
 *   - pgsql without the extension (locked-down role) : skipped silently;
 *     the SQL T9 match still works, just as a sequential scan
 *   - sqlite / mysql / other : no-op — the SQL used here is Postgres-specific
 *
 * Runs outside a transaction and builds CONCURRENTLY so it never takes a
 * blocking write lock on the shared production RDS.
 */
return new class extends Migration {
    /** Build indexes concurrently — cannot run inside a transaction. */
    public $withinTransaction = false;

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return; // functional trigram indexes are Postgres-only
        }

        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        } catch (\Throwable $e) {
            return;
        }

        if (!DB::table('pg_extension')->where('extname', 'pg_trgm')->exists()) {
            return;
        }

        foreach ($this->indexes() as [$table, $index, $expr]) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            DB::statement(
                "CREATE INDEX CONCURRENTLY IF NOT EXISTS {$index} "
                . "ON {$table} USING GIN (({$expr}) gin_trgm_ops)"
            );
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->indexes() as [, $index]) {
            DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$index}");
        }
    }

    /** @return array<int,array{0:string,1:string,2:string}> [table, index, expression] */
    private function indexes(): array
    {
        return [
            ['users', 'users_name_t9_trgm_idx', DialerT9::sqlEncode('name')],
            ['contacts', 'contacts_name_t9_trgm_idx', DialerT9::sqlEncode(DialerT9::CONTACT_NAME_SQL)],
        ];
    }
};
