<?php

use Database\Seeders\PlansAndAddonsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive, idempotent backfill of the per-plan `max_brand_kits` quantity
 * cap (Task #2662) onto existing plan rows on the shared database.
 *
 * Overlay-only: for each known plan slug, add `max_brand_kits` only when it
 * is NOT already present in the row's features JSON, never overwriting a
 * curator's existing value. Plans with no entry in the canonical map are
 * left untouched, so the runtime helper falls back to the global default
 * (0 → upgrade prompt) for them. Forward-only; down() is a no-op.
 *
 * The value comes straight from PlansAndAddonsSeeder::aiFeatureLimits() so
 * the migration and the seeder can never drift.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('plans')) {
            return;
        }

        $limits = PlansAndAddonsSeeder::aiFeatureLimits();

        $rows = DB::table('plans')->get(['id', 'slug', 'features']);
        foreach ($rows as $row) {
            if (!isset($limits[$row->slug]['max_brand_kits'])) {
                continue;
            }

            $features = json_decode($row->features ?? '[]', true);
            if (!is_array($features)) {
                $features = [];
            }
            if (array_key_exists('max_brand_kits', $features)) {
                continue;
            }

            $features['max_brand_kits'] = $limits[$row->slug]['max_brand_kits'];

            DB::table('plans')
                ->where('id', $row->id)
                ->update([
                    'features'   => json_encode($features),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Forward-only additive backfill.
    }
};
