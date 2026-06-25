<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refresh already-seeded marketing/template content from the old brand
 * purple (#7c3aed) to the new brand blue (#3d6bff).
 *
 * Task #2402 swapped the baked-in default colour at the source, but only
 * for fresh installs / newly-created content: the seeders use
 * firstOrCreate and the create-migrations had already inserted their rows,
 * so the live database still carries purple in:
 *   - starter / persona page-template snapshots (page_templates.snapshot)
 *   - marketing testimonials (testimonials.accent_color)
 *   - marketing site stats (site_stats.color)
 *
 * This is an additive, idempotent data migration (no schema change). It
 * only touches rows that still hold the OLD default colour, so admin
 * edits to a different colour are never clobbered. Page-template rows add
 * a created_at≈updated_at guard (mirroring the seeders' EDIT_DRIFT
 * tolerance) so an admin-edited template is left entirely alone even if
 * it still happens to contain purple. End-user biolinks/blocks are never
 * touched. Re-running is a no-op because nothing matches the old colour
 * once converted.
 */
return new class extends Migration {
    private const OLD = '#7c3aed';
    private const NEW = '#3d6bff';

    /** Seconds of created_at/updated_at drift still treated as "untouched". */
    private const EDIT_DRIFT_TOLERANCE = 2;

    public function up(): void
    {
        // Marketing testimonials seeded by the create-testimonials migration.
        // Only refresh untouched seeded rows (created_at≈updated_at) so a row
        // an admin edited — but happened to leave purple on — is preserved.
        if (Schema::hasTable('testimonials')) {
            DB::table('testimonials')
                ->where('accent_color', self::OLD)
                ->whereRaw('EXTRACT(EPOCH FROM (updated_at - created_at)) <= ?', [self::EDIT_DRIFT_TOLERANCE])
                ->update(['accent_color' => self::NEW]);
        }

        // Marketing site stats seeded by the create-site_stats migration.
        if (Schema::hasTable('site_stats')) {
            DB::table('site_stats')
                ->where('color', self::OLD)
                ->whereRaw('EXTRACT(EPOCH FROM (updated_at - created_at)) <= ?', [self::EDIT_DRIFT_TOLERANCE])
                ->update(['color' => self::NEW]);
        }

        // Starter + persona page-template snapshots (json). Only refresh
        // untouched seeded rows in those two slug namespaces whose snapshot
        // still bakes the old purple. `snapshot` is a json column, so cast
        // to text for the replace and back to json on write.
        if (Schema::hasTable('page_templates')) {
            DB::statement(
                "UPDATE page_templates
                    SET snapshot = replace(snapshot::text, ?, ?)::json
                  WHERE (slug LIKE 'starter-%' OR slug LIKE 'persona-%')
                    AND position(? in snapshot::text) > 0
                    AND EXTRACT(EPOCH FROM (updated_at - created_at)) <= ?",
                [self::OLD, self::NEW, self::OLD, self::EDIT_DRIFT_TOLERANCE]
            );
        }
    }

    public function down(): void
    {
        // No-op: the old purple brand colour is intentionally not restored.
    }
};
