<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Make the "Sell from your bio" (`ecommerce`) plan flag agree with
     * real product-block enforcement.
     *
     * The product block is gated two ways now: by the per-tier
     * `block_types_allowed` allowlist AND by the `ecommerce` flag (see
     * BiolinkBlockController::store). Any plan whose allowlist already
     * permits the `product` block (`'*'` or an explicit list containing
     * `product`) must therefore also have `ecommerce => true`, or the
     * pricing/upgrade matrix would advertise a gate that doesn't match
     * behaviour (e.g. the Pro tier shipped `*` + `ecommerce=false`).
     *
     * Idempotent + curator-safe: we only flip rows from a falsy value to
     * true where the allowlist includes the product block. Plans that
     * legitimately had it on are untouched; plans whose allowlist excludes
     * the product block keep their flag as-is.
     */
    public function up(): void
    {
        foreach (DB::table('plans')->get() as $plan) {
            $features = json_decode($plan->features ?? '[]', true) ?: [];
            if (!$this->allowlistIncludesProduct($features)) {
                continue;
            }
            if (!empty($features['ecommerce'])) {
                continue; // already enabled — leave curator edits alone
            }
            $features['ecommerce'] = true;
            DB::table('plans')->where('id', $plan->id)->update([
                'features' => json_encode($features),
            ]);
        }
    }

    public function down(): void
    {
        // No-op: this is a one-way data-correctness convergence. We cannot
        // tell which rows were `ecommerce=true` before this migration ran
        // versus which ones it flipped, so reverting would risk disabling
        // selling on tiers that legitimately had it (Premium/Enterprise).
    }

    /**
     * True when the plan's block allowlist permits the `product` block —
     * either `'*'` (all blocks) or an explicit array containing `product`.
     */
    private function allowlistIncludesProduct(array $features): bool
    {
        $allowed = $features['block_types_allowed'] ?? '*';
        if ($allowed === '*' || $allowed === null || $allowed === '') {
            return true;
        }
        return is_array($allowed) && in_array('product', $allowed, true);
    }
};
