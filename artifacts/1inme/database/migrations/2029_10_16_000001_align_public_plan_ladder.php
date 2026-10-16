<?php

use Database\Seeders\PlansAndAddonsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Straightens the public plan ladder so every plan beats the one below it on
 * every gated feature.
 *
 * Production had drifted into an inversion: Bio Plus ($8) carried 10 bio
 * pages, 250 short links, one custom domain, badge removal and teams, while
 * Creator ($12) carried 3 pages, 50 links, no domain, no badge removal and no
 * teams. Professional ($25) still showed the Sayzio badge that $8 removed, and
 * priority support plus scheduled posts were off on every plan including the
 * $399 one. Each plan had been edited on its own admin screen, so no two were
 * ever compared and nothing failed.
 *
 * Unlike every other plan-feature migration in this directory, this one is
 * deliberately NOT overlay-only. The values it writes are exactly the ones
 * that are already present and wrong, so skipping present keys would be a
 * no-op. It writes the canonical matrix
 * (PlansAndAddonsSeeder::publicPlanLadder()) authoritatively over the listed
 * keys, and touches nothing else: keys outside the matrix, plans outside it
 * (developer, business, agency, enterprise-api, unlimited) and every non-
 * feature column are left exactly as the curator set them.
 *
 * `free` appears in the matrix at its CURRENT production values, so this
 * migration does not change it. It is the only plan with users, and lowering
 * a cap beneath a live user's existing pages is a support incident rather
 * than a pricing change. It is listed so the guard test can check the bottom
 * rung of the ladder rather than assume it.
 *
 * Idempotent: re-running writes the same values. Forward-only; down() is a
 * no-op, because the previous state was the bug.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('plans')) {
            return;
        }

        foreach (PlansAndAddonsSeeder::publicPlanLadder() as $slug => $ladder) {
            $row = DB::table('plans')->where('slug', $slug)->first(['id', 'features']);
            if (!$row) {
                // A deployment that has not yet seeded this slug. The seeder
                // definition carries the same values, so there is nothing to
                // repair here.
                continue;
            }

            $features = json_decode($row->features ?? '[]', true);
            if (!is_array($features)) {
                $features = [];
            }

            $changed = false;
            foreach ($ladder as $key => $value) {
                if (!array_key_exists($key, $features) || $features[$key] !== $value) {
                    $features[$key] = $value;
                    $changed = true;
                }
            }

            if (!$changed) {
                continue;
            }

            DB::table('plans')->where('id', $row->id)->update([
                'features'   => json_encode($features),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Forward-only: the state this replaced was the inverted ladder.
    }
};
