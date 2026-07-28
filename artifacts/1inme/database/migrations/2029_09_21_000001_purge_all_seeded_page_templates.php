<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data migration: remove ALL page templates (seeded and otherwise) so the
 * "Choose a template" gallery starts as a clean, empty slate ahead of the
 * new template designs. The seeders (StarterPageTemplatesSeeder /
 * ExpandedPageTemplateLibrarySeeder) were neutralized in the same change —
 * their blueprint lists are now empty — so reseeding or
 * `templates:refresh-persona-seed` cannot recreate the old templates.
 *
 * Safe to re-run: deleting from an already-empty table is a no-op.
 * Additive-only merge policy: this touches rows only, never schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('page_templates')) {
            return;
        }
        DB::table('page_templates')->delete();
    }

    public function down(): void
    {
        // Data purge — nothing to restore. The retired blueprints are gone
        // from the seeders by design.
    }
};
