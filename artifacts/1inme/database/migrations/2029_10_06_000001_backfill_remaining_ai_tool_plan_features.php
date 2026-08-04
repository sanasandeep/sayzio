<?php

use Database\Seeders\PlansAndAddonsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive, idempotent backfill for the remaining paid-only AI tools that
 * were still gated only by the legacy "any non-free plan" fallback in
 * AiPlanAccess::legacyAvailabilityFallback(): writes the newly catalogued
 * feature keys onto existing plan rows on the shared database.
 *
 * Keys — all AI-suite/branding perks whose values come from
 * PlansAndAddonsSeeder::aiFeatureLimits() and mirror that fallback exactly
 * (free = off, paid = on):
 * - `brand_kit_assets`
 * - `dashboard_designer`
 * - `ai_staff_billing` / `ai_staff_contacts` / `ai_staff_inbox` / `ai_staff_general`
 *
 * Overlay-only: each key is added only when NOT already present in the row's
 * features JSON, never overwriting a curator's existing value. Plans with no
 * entry in the canonical map are left untouched, so runtime helpers keep
 * their legacy fallbacks for them. Forward-only; down() is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('plans')) {
            return;
        }

        $map = PlansAndAddonsSeeder::aiFeatureLimits();
        $keys = [
            'brand_kit_assets',
            'dashboard_designer',
            'ai_staff_billing',
            'ai_staff_contacts',
            'ai_staff_inbox',
            'ai_staff_general',
        ];

        $rows = DB::table('plans')->get(['id', 'slug', 'features']);
        foreach ($rows as $row) {
            $features = json_decode($row->features ?? '[]', true);
            if (!is_array($features)) {
                $features = [];
            }

            $changed = false;
            foreach ($keys as $key) {
                if (!isset($map[$row->slug]) || !array_key_exists($key, $map[$row->slug])) {
                    continue;
                }
                if (array_key_exists($key, $features)) {
                    continue;
                }
                $features[$key] = $map[$row->slug][$key];
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
