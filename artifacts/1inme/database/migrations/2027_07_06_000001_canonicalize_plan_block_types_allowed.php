<?php

use App\Modules\User\Support\BlockTypeRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Reconcile pricing block names with real per-plan gating.
     *
     * Seeded `block_types_allowed` lists historically used friendly slugs
     * that don't match canonical `BiolinkBlock::TYPES` keys — e.g.
     * `link_button` (canonical `link`), `social_icons` (canonical
     * `socials`), `tiktok` (canonical `tiktok_video` / `tiktok_profile`),
     * `twitter` (canonical `twitter_*`), `paragraph` (canonical
     * `paragraph_rich`). The editor gate compares the canonical POST type,
     * so the advertised allowlist didn't match what was enforced.
     *
     * This converges existing plan rows onto canonical slugs (via the
     * shared {@see BlockTypeRegistry::canonicalizeAllowlist()} resolver) so
     * pricing labels read straight from the registry and gating agrees.
     *
     * Idempotent + curator-safe: `'*'` rows are skipped, lists already in
     * canonical form produce no change, and we only write when the
     * normalized list differs from the stored one.
     */
    public function up(): void
    {
        foreach (DB::table('plans')->get() as $plan) {
            $features = json_decode($plan->features ?? '[]', true) ?: [];
            $allowed = $features['block_types_allowed'] ?? null;

            if (!is_array($allowed) || $allowed === []) {
                continue; // '*' / null / empty == all blocks, nothing to map
            }

            $canonical = BlockTypeRegistry::canonicalizeAllowlist($allowed);

            // No-op when nothing actually changed (order-insensitive compare).
            $before = $allowed;
            sort($before, SORT_STRING);
            $after = $canonical;
            sort($after, SORT_STRING);
            if ($before === $after) {
                continue;
            }

            $features['block_types_allowed'] = $canonical;
            DB::table('plans')->where('id', $plan->id)->update([
                'features' => json_encode($features),
            ]);
        }
    }

    public function down(): void
    {
        // No-op: one-way data-correctness convergence. The friendly→canonical
        // mapping is lossy (e.g. `tiktok` → two canonical slugs), so we can't
        // faithfully reconstruct the original friendly slugs.
    }
};
