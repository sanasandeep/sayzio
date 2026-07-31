<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Additive, one-time data migration that backfills the new `max_text_pages`
 * per-plan cap (Text Page link type, Task #6319) onto existing plan rows in
 * the shared production database, mirroring each plan's `max_updates_pages`
 * value so the Text Page allowance matches the seeder lineup without
 * touching any curator-edited plan fields.
 *
 * Idempotent: only plans whose features JSON lacks a `max_text_pages` key
 * are updated, so re-running after a killed/partial migrate is safe and
 * later admin edits are never clobbered.
 *
 * Forward-only: down() intentionally does nothing (removing the key would
 * silently change gating for users on those plans).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('plans') || !Schema::hasColumn('plans', 'features')) {
            return;
        }

        $plans = DB::table('plans')->select('id', 'features')->get();

        foreach ($plans as $plan) {
            $features = json_decode($plan->features ?? '[]', true);
            if (!is_array($features) || array_key_exists('max_text_pages', $features)) {
                continue;
            }

            // Mirror the plan's Updates/Changelog cap (same tier positioning
            // in the seeder); fall back to the catalogue default of 1.
            $features['max_text_pages'] = $features['max_updates_pages'] ?? 1;

            DB::table('plans')->where('id', $plan->id)->update([
                'features' => json_encode($features),
            ]);
        }
    }

    public function down(): void
    {
        // Forward-only: never strip a cap that gating may already rely on.
    }
};
