<?php

use Database\Seeders\PlansAndAddonsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive, idempotent backfill for the Zio Browser sync plan gate
 * (Task #6647): writes the two new feature keys onto existing plan rows on
 * the shared database.
 *
 * Keys (values come from PlansAndAddonsSeeder::gatingSyncFeatureDefaults(),
 * the single source of truth shared with the seeder):
 * - `browser_sync` — device sync was previously ungated; defaults ON on
 *   every tier so behaviour does not change (admins can switch it off).
 * - `max_browser_sync_items` — per-entity row cap (bookmarks / collections /
 *   history / reading list each count separately). -1 = unlimited (still
 *   hard-capped server-side).
 *
 * Overlay-only: each key is added only when NOT already present in the
 * row's features JSON, never overwriting a curator's existing value. Plans
 * with no entry in the canonical map are left untouched, so the runtime
 * legacy-safe defaults (ON / unlimited) keep applying for them.
 * Forward-only; down() is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('plans')) {
            return;
        }

        $gating = PlansAndAddonsSeeder::gatingSyncFeatureDefaults();
        $keys   = ['browser_sync', 'max_browser_sync_items'];

        $rows = DB::table('plans')->get(['id', 'slug', 'features']);
        foreach ($rows as $row) {
            $features = json_decode($row->features ?? '[]', true);
            if (!is_array($features)) {
                $features = [];
            }

            $changed = false;
            foreach ($keys as $key) {
                if (!isset($gating[$row->slug]) || !array_key_exists($key, $gating[$row->slug])) {
                    continue;
                }
                if (array_key_exists($key, $features)) {
                    continue;
                }
                $features[$key] = $gating[$row->slug][$key];
                $changed = true;
            }

            if ($changed) {
                DB::table('plans')
                    ->where('id', $row->id)
                    ->update([
                        'features'   => json_encode($features),
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // Forward-only additive backfill.
    }
};
