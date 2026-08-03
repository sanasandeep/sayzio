<?php

use Database\Seeders\PlansAndAddonsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive, idempotent backfill for the plan-gating sync sweep (Task #6646):
 * writes the newly catalogued feature keys onto existing plan rows on the
 * shared database.
 *
 * Keys:
 * - `competitor_teardown` / `audience_type_estimation` — AI-suite perks that
 *   were gated by the legacy "any non-free plan" fallback in AiPlanAccess;
 *   values come from PlansAndAddonsSeeder::aiFeatureLimits() and mirror that
 *   fallback exactly (free = off, paid = on).
 * - `special_dates` / `max_smart_rules` — previously ungated / invisible
 *   caps; values come from PlansAndAddonsSeeder::gatingSyncFeatureDefaults()
 *   and preserve current behaviour for special dates (on everywhere).
 *
 * Overlay-only: each key is added only when NOT already present in the row's
 * features JSON, never overwriting a curator's existing value. Plans with no
 * entry in the canonical maps are left untouched, so runtime helpers keep
 * their legacy fallbacks for them. Forward-only; down() is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('plans')) {
            return;
        }

        $ai     = PlansAndAddonsSeeder::aiFeatureLimits();
        $gating = PlansAndAddonsSeeder::gatingSyncFeatureDefaults();

        $keysBySource = [
            ['map' => $ai,     'keys' => ['competitor_teardown', 'audience_type_estimation']],
            ['map' => $gating, 'keys' => ['special_dates', 'max_smart_rules']],
        ];

        $rows = DB::table('plans')->get(['id', 'slug', 'features']);
        foreach ($rows as $row) {
            $features = json_decode($row->features ?? '[]', true);
            if (!is_array($features)) {
                $features = [];
            }

            $changed = false;
            foreach ($keysBySource as $source) {
                foreach ($source['keys'] as $key) {
                    if (!isset($source['map'][$row->slug]) || !array_key_exists($key, $source['map'][$row->slug])) {
                        continue;
                    }
                    if (array_key_exists($key, $features)) {
                        continue;
                    }
                    $features[$key] = $source['map'][$row->slug][$key];
                    $changed = true;
                }
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
