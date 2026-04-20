<?php

namespace App\Services;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;

/**
 * Resolves the effective feature set for a user by merging their plan's
 * features with any active addons attached to that user.
 *
 * NOTE for billing tasks (#193+): once user-addon assignment is wired
 * (a `user_addons` table is the natural place for that), update
 * `addonsForUser()` below to read from it. For now there is no per-user
 * addon assignment, so this just returns the plan features unchanged.
 * The merge logic itself is already complete and ready.
 */
class EffectivePlanFeatures
{
    public static function for(?User $user): array
    {
        if (!$user || !$user->plan) {
            return [];
        }

        return self::merge($user->plan, self::addonsForUser($user));
    }

    public static function forPlan(Plan $plan, iterable $addons = []): array
    {
        return self::merge($plan, $addons);
    }

    private static function merge(Plan $plan, iterable $addons): array
    {
        $features = $plan->features ?? [];

        foreach ($addons as $addon) {
            $addonFeatures = $addon->features ?? [];

            foreach ($addonFeatures as $key => $value) {
                // *_extra keys add to the matching base key (e.g. max_biolinks_extra -> max_biolinks).
                if (str_ends_with($key, '_extra')) {
                    $base = substr($key, 0, -6);
                    $current = $features[$base] ?? 0;
                    if ($current === -1) {
                        continue; // already unlimited
                    }
                    $features[$base] = (int) $current + (int) $value;
                    continue;
                }

                // Booleans never downgrade — once granted, stay granted.
                if (is_bool($value)) {
                    $features[$key] = ($features[$key] ?? false) || $value;
                    continue;
                }

                // For tier-style strings prefer the "higher" value.
                if ($key === 'analytics') {
                    $rank = ['basic' => 1, 'advanced' => 2];
                    $current = $features[$key] ?? null;
                    if (($rank[$value] ?? 0) > ($rank[$current] ?? 0)) {
                        $features[$key] = $value;
                    }
                    continue;
                }

                // Default: addon wins for unknown keys.
                $features[$key] = $value;
            }
        }

        return $features;
    }

    /** @return iterable<\App\Modules\Admin\Models\Addon> */
    private static function addonsForUser(User $user): iterable
    {
        // Per-user addon assignment lands in a later billing task.
        // Until then we report no active addons — features come purely
        // from the plan, preserving today's behavior.
        return [];
    }
}
