<?php

use Database\Seeders\PlansAndAddonsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive, idempotent backfill for the Marketing Plan Calculator plan
 * gating (Task #6766): writes the two new feature keys onto existing plan
 * rows on the shared database so the tool is actually gated per tier
 * instead of silently falling back to call-site defaults.
 *
 * Keys (values come from PlansAndAddonsSeeder::gatingSyncFeatureDefaults(),
 * the single source of truth shared with the seeder):
 * - `marketing_plan_calculator` — bool toggle enabling the tool.
 * - `max_marketing_plans` — saved-plan cap (0 = none, -1 = unlimited).
 *
 * Overlay-only: each key is added only when NOT already present in the
 * row's features JSON, never overwriting a curator's existing value. Plans
 * with no entry in the canonical map are left untouched. Forward-only;
 * down() is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('plans')) {
            return;
        }

        $gating = PlansAndAddonsSeeder::gatingSyncFeatureDefaults();
        $keys   = ['marketing_plan_calculator', 'max_marketing_plans'];

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
