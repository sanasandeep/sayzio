<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data migration: permanently delete the old bulk persona link-in-bio
 * library — every `page_templates` row in the `persona-*` slug namespace
 * owned by the (now neutralized) ExpandedPageTemplateLibrarySeeder.
 *
 * A previous purge (2029_09_21_000001_purge_all_seeded_page_templates)
 * already ran, but the seeder still shipped its full blueprint bank, so
 * `db:seed` / `templates:refresh-persona-seed` / auto-refresh recreated
 * ~396 `persona-*` rows. The seeder's blueprint bank is emptied in the
 * same change as this migration, so this deletion is final.
 *
 * Explicitly preserved: the designer `starter-*` templates and any
 * admin-created rows — only slugs starting with `persona-` are removed.
 * (The 4 link-type explainer pages are demo LINKS from
 * LinkTypeExplainerSeeder, not page_templates rows, so they are
 * unaffected by this migration.)
 *
 * Idempotent: deleting already-absent rows is a no-op.
 * Additive-only merge policy: rows only, no schema changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('page_templates')) {
            return;
        }
        DB::table('page_templates')->where('slug', 'like', 'persona-%')->delete();
    }

    public function down(): void
    {
        // Data purge — nothing to restore. The retired blueprints no
        // longer exist in the seeder, by design.
    }
};
