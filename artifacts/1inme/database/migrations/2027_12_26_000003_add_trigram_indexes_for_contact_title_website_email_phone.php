<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Follow-on to 2027_12_26_000002_add_trigram_indexes_for_contact_notes_tags_socials.php.
 *
 * The advanced contacts search (App\Modules\User\Support\DialerSearch::
 * contactsAdvanced) runs `ILIKE '%term%'` over several more columns that the
 * earlier trigram migrations left uncovered:
 *
 *   - contacts.job_title       : plain text (`job_title ilike ?`)
 *   - contacts.website         : plain text (`website ilike ?`)
 *   - contact_emails.value     : via orWhereHas('emails', ...)
 *   - contact_phones.value     : via orWhereHas('phones', ...)
 *   - contact_phones.value_e164: via orWhereHas('phones', ...)
 *
 * The phone columns carry plain btree indexes, but a btree can't serve an
 * `ILIKE '%term%'` substring match — only a trigram GIN index can. Without
 * these indexes a search term that only appears in a job title, website,
 * email, or phone still triggers a sequential scan on those tables once the
 * address book gets large.
 *
 * Each column gets a straight `gin_trgm_ops` GIN index (no JSON cast, so no
 * expression index needed here).
 *
 * Driver / privilege matrix mirrors the sibling migrations:
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
     * @var array<int,array{0:string,1:string,2:string,3:string}>
     *   [index name, table, guard column, index expression]
     */
    private array $indexes = [
        ['contacts_job_title_trgm_idx', 'contacts', 'job_title', 'job_title gin_trgm_ops'],
        ['contacts_website_trgm_idx', 'contacts', 'website', 'website gin_trgm_ops'],
        ['contact_emails_value_trgm_idx', 'contact_emails', 'value', 'value gin_trgm_ops'],
        ['contact_phones_value_trgm_idx', 'contact_phones', 'value', 'value gin_trgm_ops'],
        ['contact_phones_value_e164_trgm_idx', 'contact_phones', 'value_e164', 'value_e164 gin_trgm_ops'],
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

        foreach ($this->indexes as [$index, $table, $column, $expression]) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                continue;
            }
            DB::statement(
                "CREATE INDEX CONCURRENTLY IF NOT EXISTS {$index} "
                . "ON {$table} USING GIN ({$expression})"
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
