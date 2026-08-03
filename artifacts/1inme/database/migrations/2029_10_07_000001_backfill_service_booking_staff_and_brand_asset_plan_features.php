<?php

use Database\Seeders\PlansAndAddonsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive, idempotent backfill for two previously-unseeded plan quantity
 * limits (Task #6652): writes the new feature keys onto existing plan rows
 * on the shared database so they stop silently falling back to call-site
 * defaults (often unlimited) in production.
 *
 * Keys (values come from PlansAndAddonsSeeder::gatingSyncFeatureDefaults(),
 * the single source of truth shared with the seeder):
 * - `max_service_booking_staff` — staff members per service booking page
 *   (0 = staff feature hidden, -1 = unlimited). Tiers mirror
 *   `max_service_booking`.
 * - `max_brand_asset_versions` — generations/regenerations per Brand Kit
 *   visual asset (-1 = unlimited). 0 on free, which has `brand_kit_assets`
 *   off anyway.
 *
 * (`brand_kit_assets`, the third gap this task covers, was already seeded
 * per tier in aiFeatureLimits() and backfilled by
 * 2029_10_06_000001_backfill_remaining_ai_tool_plan_features.)
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
        $keys   = ['max_service_booking_staff', 'max_brand_asset_versions'];

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
