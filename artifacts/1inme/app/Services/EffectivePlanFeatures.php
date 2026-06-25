<?php

namespace App\Services;

use App\Modules\Admin\Models\Addon;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Subscription;
use App\Modules\User\Models\User;

/**
 * Resolves the effective feature set for a user by merging their plan's
 * features with any active addons attached to that user's current
 * subscription.
 *
 * Addons are read from the user's active subscription (`subscription_addons`)
 * together with their purchased quantity, so a buyer who bought e.g.
 * 3× "Extra Biolinks (+5)" gets +15 toward `max_biolinks`. The merge is
 * quantity-aware ONLY for additive `*_extra` keys — booleans and tier
 * strings ignore quantity (you either have the capability or you don't).
 */
class EffectivePlanFeatures
{
    public static function for(?User $user): array
    {
        if (!$user || !$user->plan) {
            return [];
        }

        return self::mergeFeatures(
            is_array($user->plan->features) ? $user->plan->features : [],
            self::addonsForUser($user),
        );
    }

    /**
     * Merge a plan against a set of addons. Each entry of $addons may be
     * a bare Addon model (treated as quantity 1) or a `[Addon, qty]` pair.
     */
    public static function forPlan(Plan $plan, iterable $addons = []): array
    {
        return self::mergeFeatures(
            is_array($plan->features) ? $plan->features : [],
            self::normalizePairs($addons),
        );
    }

    /**
     * Pure merge of base plan features with a list of `[Addon, qty]`
     * pairs. Additive `*_extra` keys add `value × qty` to their base key;
     * booleans never downgrade; tier strings prefer the higher rank.
     *
     * @param array<int,array{0:Addon,1:int}> $addonsWithQty
     */
    public static function mergeFeatures(array $baseFeatures, array $addonsWithQty): array
    {
        $features = $baseFeatures;

        foreach (self::normalizePairs($addonsWithQty) as [$addon, $qty]) {
            $qty = max(1, (int) $qty);
            $addonFeatures = is_array($addon->features ?? null) ? $addon->features : [];

            foreach ($addonFeatures as $key => $value) {
                // *_extra keys add to the matching base key, scaled by the
                // purchased quantity (e.g. max_biolinks_extra:5 × qty 3 → +15).
                if (str_ends_with($key, '_extra')) {
                    $base = substr($key, 0, -6);
                    $current = $features[$base] ?? 0;
                    if ($current === -1) {
                        continue; // already unlimited
                    }
                    $features[$base] = (int) $current + ((int) $value * $qty);
                    continue;
                }

                // Booleans never downgrade — once granted, stay granted.
                // Quantity is irrelevant to a capability flag.
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

    /**
     * Normalize a mixed iterable of Addon models and/or `[Addon, qty]`
     * pairs into a uniform list of `[Addon, qty]` pairs.
     *
     * @return array<int,array{0:Addon,1:int}>
     */
    private static function normalizePairs(iterable $addons): array
    {
        $pairs = [];
        foreach ($addons as $entry) {
            if (is_array($entry)) {
                $addon = $entry[0] ?? null;
                $qty   = (int) ($entry[1] ?? 1);
            } else {
                $addon = $entry;
                $qty   = 1;
            }
            if ($addon instanceof Addon) {
                $pairs[] = [$addon, max(1, $qty)];
            }
        }
        return $pairs;
    }

    /**
     * Active addons (with quantity) attached to the user's current
     * subscription. Reads the most-recent active subscription's
     * `subscription_addons` rows. Returns an empty list when the user has
     * no active subscription / no addons.
     *
     * @return array<int,array{0:Addon,1:int}>
     */
    public static function addonsForUser(User $user): array
    {
        try {
            $sub = Subscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->latest('id')
                ->first();
            if (!$sub) {
                return [];
            }
            $pairs = [];
            foreach ($sub->addons()->with('addon')->get() as $sa) {
                if ($sa->addon) {
                    $pairs[] = [$sa->addon, (int) $sa->qty];
                }
            }
            return $pairs;
        } catch (\Throwable $e) {
            // Never let feature resolution hard-fail on a DB hiccup —
            // fall back to plan-only features.
            return [];
        }
    }
}
