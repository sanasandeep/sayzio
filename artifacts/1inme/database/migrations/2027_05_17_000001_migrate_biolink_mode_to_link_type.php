<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Promote the legacy `settings.biolink.mode` display modes to first-class
 * `links.type` values. Conversational & Slides biolinks used to be plain
 * `biolink` rows distinguished only by a JSON `mode` flag; they are now
 * distinct link types (`conversational` / `slides`) in the biolink family.
 *
 * Only rows still typed `biolink` are touched so re-running (or running
 * after some rows were already converted in the editor) is a no-op.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('links')
            ->where('type', 'biolink')
            ->whereRaw("settings::jsonb #>> '{biolink,mode}' = 'conversational'")
            ->update(['type' => 'conversational']);

        DB::table('links')
            ->where('type', 'biolink')
            ->whereRaw("settings::jsonb #>> '{biolink,mode}' = 'slides'")
            ->update(['type' => 'slides']);
    }

    public function down(): void
    {
        // Reversible: fold the dedicated types back into `biolink`. The
        // original `settings.biolink.mode` flag is left intact, so the
        // renderer's legacy fallback keeps these rendering correctly.
        DB::table('links')
            ->whereIn('type', ['conversational', 'slides'])
            ->update(['type' => 'biolink']);
    }
};
