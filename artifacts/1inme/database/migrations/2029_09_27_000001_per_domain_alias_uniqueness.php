<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Per-domain alias namespaces on platform domains.
 *
 * Aliases become unique per domain (case-insensitively, across both
 * links.alias and link_aliases.alias) instead of globally unique across every
 * platform domain. This migration:
 *
 *   1. Ensures the default platform domain row (the primary brand domain,
 *      sayzio.app) exists as an admin-global `domains` row.
 *   2. Backfills legacy NULL domain_id rows in links + link_aliases onto that
 *      default domain, so "no domain" unambiguously means the default
 *      namespace going forward (the app still treats NULL ≡ default).
 *   3. Relaxes the DB-level GLOBAL unique constraints on links.alias and
 *      link_aliases.alias, replacing them with per-domain case-insensitive
 *      unique indexes plus plain lookup indexes.
 *
 * Everything is additive/idempotent (IF EXISTS / IF NOT EXISTS) so replays on
 * the shared database are safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Default platform domain row (admin-global: user_id IS NULL).
        $brandHost = mb_strtolower(\App\Modules\Common\Support\PlatformHosts::primaryBrandDomain());
        $default = DB::table('domains')->whereRaw('LOWER(domain) = ?', [$brandHost])->whereNull('user_id')->first();
        if (! $default) {
            $id = DB::table('domains')->insertGetId([
                'user_id'     => null,
                'domain'      => $brandHost,
                'is_active'   => true,
                'is_verified' => true,
                'is_primary'  => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } else {
            $id = $default->id;
        }

        // 2. Backfill legacy NULL bindings onto the default platform domain.
        DB::table('links')->whereNull('domain_id')->update(['domain_id' => $id]);
        DB::table('link_aliases')->whereNull('domain_id')->update(['domain_id' => $id]);

        // 3. Relax global uniqueness → per-domain (case-insensitive).
        // Laravel's ->unique() created either a constraint or a bare unique
        // index depending on version — drop both spellings defensively.
        DB::statement('ALTER TABLE links DROP CONSTRAINT IF EXISTS links_alias_unique');
        DB::statement('DROP INDEX IF EXISTS links_alias_unique');
        DB::statement('ALTER TABLE link_aliases DROP CONSTRAINT IF EXISTS link_aliases_alias_unique');
        DB::statement('DROP INDEX IF EXISTS link_aliases_alias_unique');

        // Per-domain case-insensitive uniqueness at the DB level (defense in
        // depth behind AliasNamespace::isTaken, which also spans both tables
        // and folds NULL into the default domain's namespace).
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS links_alias_domain_ci_unique ON links (LOWER(alias), COALESCE(domain_id, 0)) WHERE alias IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS link_aliases_alias_domain_ci_unique ON link_aliases (LOWER(alias), COALESCE(domain_id, 0))');

        // Plain lookup indexes so exact-match alias resolution stays fast now
        // that the unique index no longer covers the raw column.
        DB::statement('CREATE INDEX IF NOT EXISTS links_alias_index ON links (alias)');
        DB::statement('CREATE INDEX IF NOT EXISTS link_aliases_alias_index ON link_aliases (alias)');
    }

    public function down(): void
    {
        // Per-domain aliasing may have introduced duplicates across domains,
        // so the old GLOBAL unique constraints cannot be safely restored.
        DB::statement('DROP INDEX IF EXISTS links_alias_domain_ci_unique');
        DB::statement('DROP INDEX IF EXISTS link_aliases_alias_domain_ci_unique');
    }
};
