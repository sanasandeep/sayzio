<?php

use Database\Seeders\PlansAndAddonsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive, idempotent backfill of the first-class per-plan AI feature keys
 * (max_minds / max_personas / max_companions + ask_coach / card_scan /
 * ai_resume_tools) onto existing plan rows on the shared database.
 *
 * Strategy: overlay-only — for each known plan slug, add any of the new keys
 * that are NOT already present in the row's features JSON, never overwriting a
 * curator's existing value. Plans with no entry in the canonical map (custom
 * curator plans) are left untouched, so the runtime helper falls back to the
 * global admin caps / allow-lists for them. Forward-only; down() is a no-op.
 *
 * Values come straight from PlansAndAddonsSeeder::aiFeatureLimits() so the
 * migration and the seeder can never drift.
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
            if (!isset($limits[$row->slug])) {
                continue;
            }

            $features = json_decode($row->features ?? '[]', true);
            if (!is_array($features)) {
                $features = [];
            }

            $changed = false;
            foreach ($limits[$row->slug] as $key => $value) {
                if (!array_key_exists($key, $features)) {
                    $features[$key] = $value;
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
        // Forward-only: keep the additive keys on rollback.
    }
};
