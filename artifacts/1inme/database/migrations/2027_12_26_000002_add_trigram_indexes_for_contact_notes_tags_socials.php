<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Follow-on to 2027_12_26_000001_add_trigram_indexes_for_dialer_search.php.
 *
 * That migration trigram-indexed the contact name/org columns, but the
 * advanced contacts search (App\Modules\User\Support\DialerSearch::
 * contactsAdvanced) also runs `ILIKE '%term%'` over three columns it left
 * uncovered:
 *
 *   - contacts.notes         : plain text
 *   - contacts.tags::text    : json cast to text (`tags::text ilike ?`)
 *   - contacts.socials::text : json cast to text (`socials::text ilike ?`)
 *
 * Without an index those three paths stay sequential scans, so a user whose
 * search term only appears in a contact's notes/tags/socials still triggers a
 * full table scan once the address book gets large.
 *
 * `notes` gets a straight `gin_trgm_ops` GIN index. `tags` and `socials` are
 * queried as `<col>::text`, so they need an EXPRESSION index whose expression
 * matches the WHERE clause exactly — `((<col>)::text) gin_trgm_ops` — for the
 * planner to use it.
 *
 * Driver / privilege matrix mirrors the sibling migration:
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

    /**
     * @var array<int,array{0:string,1:string,2:string}>
     *   [index name, guard column, index expression]
     */
    private array $indexes = [
        ['contacts_notes_trgm_idx', 'notes', 'notes gin_trgm_ops'],
        ['contacts_tags_trgm_idx', 'tags', '((tags)::text) gin_trgm_ops'],
        ['contacts_socials_trgm_idx', 'socials', '((socials)::text) gin_trgm_ops'],
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

        if (!Schema::hasTable('contacts')) {
            return;
        }

        foreach ($this->indexes as [$index, $column, $expression]) {
            if (!Schema::hasColumn('contacts', $column)) {
                continue;
            }
            DB::statement(
                "CREATE INDEX CONCURRENTLY IF NOT EXISTS {$index} "
                . "ON contacts USING GIN ({$expression})"
            );
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->indexes as [$index]) {
            DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$index}");
        }
    }
};
