<?php

namespace App\Services\Billing;

use App\Modules\Admin\Models\CoinPackage;
use App\Modules\User\Models\User;

/**
 * Single source of truth for the plan-based bonus coins granted on larger
 * coin-package purchases.
 *
 * Rule: when a subscriber on a paid plan buys a coin package of tier Pro or
 * above, they receive an extra "plan bonus" — a percentage of the package's
 * base coin_amount (rounded down) — on TOP of the package's built-in
 * bonus_coins. Packages below Pro never get a plan bonus, and free-plan
 * users never get one either.
 *
 * The bonus is always resolved server-side from the buyer's currently
 * active plan (users.plan_id, which ActivateSubscription/the lifecycle keep
 * in sync with the active subscription) — never trusted from the client.
 */
class CoinPlanBonus
{
    /** Plan slug → bonus percent applied to eligible coin packages. */
    public const PLAN_BONUS_PCT = [
        'free'           => 0,
        'creator'        => 2,
        'professional'   => 3,
        'business'       => 4,
        'agency'         => 5,
        'developer'      => 6,
        'enterprise-api' => 7,
        'unlimited'      => 10,
    ];

    /** Coin-package slugs of tier Pro and above (plan-bonus eligible). */
    public const ELIGIBLE_PACKAGE_SLUGS = [
        'coins-pro',
        'coins-business',
        'coins-enterprise',
        'coins-scale',
        'coins-ultimate',
    ];

    /** Teaser range shown to logged-out visitors: [min%, max%] among paid plans. */
    public static function teaserRange(): array
    {
        $paid = array_values(array_filter(self::PLAN_BONUS_PCT, fn ($p) => $p > 0));
        return [min($paid), max($paid)];
    }

    public static function packageEligible(CoinPackage $package): bool
    {
        return in_array((string) $package->slug, self::ELIGIBLE_PACKAGE_SLUGS, true);
    }

    /** Bonus percent for this (user, package) pair. 0 when not eligible. */
    public static function percentFor(?User $user, CoinPackage $package): int
    {
        if (!$user || !self::packageEligible($package)) {
            return 0;
        }
        $slug = (string) ($user->plan?->slug ?? 'free');
        return (int) (self::PLAN_BONUS_PCT[$slug] ?? 0);
    }

    /** Plan-bonus coins: percent of the package's BASE coin_amount, floored. */
    public static function bonusCoinsFor(?User $user, CoinPackage $package): int
    {
        $pct = self::percentFor($user, $package);
        if ($pct <= 0) {
            return 0;
        }
        return (int) floor((int) $package->coin_amount * $pct / 100);
    }

    /** Full breakdown used by display + API surfaces. */
    public static function breakdownFor(?User $user, CoinPackage $package): array
    {
        $pct = self::percentFor($user, $package);
        $bonus = self::bonusCoinsFor($user, $package);
        return [
            'plan_bonus_pct'   => $pct,
            'plan_bonus_coins' => $bonus,
            'plan_name'        => $pct > 0 ? (string) ($user?->plan?->name ?? '') : null,
            'total_with_plan_bonus' => $package->totalCoins() + $bonus,
        ];
    }
}
