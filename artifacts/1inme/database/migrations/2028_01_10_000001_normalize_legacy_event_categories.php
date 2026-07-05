<?php

use App\Modules\User\Support\EventCategories;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-time normalization of legacy free-text `settings['event_category']`
 * values onto the curated slugs introduced in Task #3615. Events created
 * before the curated category list stored arbitrary free text ("music",
 * "Music", "live music"), which render as separate pills in the /events
 * directory browse row so old events never group under the new categories.
 *
 * Reuses EventCategories::slugForLegacy() (the same keyword map that drives
 * icon guessing): a recognizable legacy value is remapped to its curated
 * slug; genuinely custom values that match no keyword are left untouched.
 *
 * Idempotent: once a value is a curated slug, slugForLegacy() returns null,
 * so re-running finds nothing to change. Additive-only — no schema changes.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('links')
            ->where('type', 'ics')
            ->whereRaw("settings->>'event_category' IS NOT NULL")
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $settings = json_decode($row->settings, true);
                    if (!is_array($settings)) {
                        continue;
                    }

                    $current = $settings['event_category'] ?? null;
                    if (!is_string($current) || $current === '') {
                        continue;
                    }

                    $slug = EventCategories::slugForLegacy($current);
                    if ($slug === null || $slug === $current) {
                        continue;
                    }

                    $settings['event_category'] = $slug;
                    DB::table('links')->where('id', $row->id)->update([
                        'settings' => json_encode($settings),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // No-op: normalization is one-way; we don't restore the free-text values.
    }
};
