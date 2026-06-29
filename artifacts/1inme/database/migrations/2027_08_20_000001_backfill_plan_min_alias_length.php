<?php

use App\Modules\Admin\Models\Plan;
use Illuminate\Database\Migrations\Migration;

/**
 * Backfill the per-plan `min_alias_length` feature so the lineup matches the
 * new scheme where free/entry plans keep the LARGEST custom-URL minimum and
 * paid tiers step down (free = 4, creator/professional = 3, business+ = 2,
 * enterprise-api = 1).
 *
 * Convergence rule: only move a plan that is still sitting on the historical
 * global default (3) or has no value configured yet. Any minimum an admin has
 * already customized away from the old default is left untouched — the overlay
 * seeder never overwrites curator edits, so this one-time backfill mirrors that
 * promise. Idempotent: re-running is a no-op once the rows are on the new value.
 */
return new class extends Migration
{
    /** Historical global default that signals "never customized". */
    private const OLD_DEFAULT = 3;

    /** New per-plan minimums keyed by slug. */
    private const TARGETS = [
        'free'           => 4,
        'creator'        => 3,
        'professional'   => 3,
        'business'       => 2,
        'agency'         => 2,
        'developer'      => 2,
        'enterprise-api' => 1,
    ];

    public function up(): void
    {
        $plans = Plan::whereIn('slug', array_keys(self::TARGETS))->get();

        foreach ($plans as $plan) {
            $new = self::TARGETS[$plan->slug];
            $features = $plan->features ?? [];
            $current = $features['min_alias_length'] ?? null;

            // Leave admin-customized values alone: only fill when unset or
            // still on the old default. Skip no-op writes (already on target).
            $onOldDefaultOrUnset = $current === null || (int) $current === self::OLD_DEFAULT;
            if (! $onOldDefaultOrUnset || (int) $current === $new) {
                continue;
            }

            $features['min_alias_length'] = $new;
            $plan->features = $features;
            $plan->save();
        }
    }

    public function down(): void
    {
        // One-time data backfill — prior per-plan values aren't recoverable, so
        // this is intentionally not reversed.
    }
};
