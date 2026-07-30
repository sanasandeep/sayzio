<?php

namespace App\Support;

/**
 * Presentation helpers for plan-limit numbers.
 *
 * `User::getPlanFeature()` returns PHP_INT_MAX for holders of the
 * `user.plan_limits.bypass` permission so every numeric limit becomes
 * effectively unlimited. That sentinel must NEVER reach a rendered page
 * or API payload — clients should see the conventional `-1` ("unlimited")
 * or a human "Unlimited" label instead. Route every plan-limit number
 * through these helpers before handing it to a view or JSON response.
 *
 * Guarded by tests/Feature/PlanLimitBypassSentinelLeakTest.php, which
 * sweeps the key surfaces as a bypass user and fails whenever the raw
 * 9-quintillion number appears in a response body.
 */
class PlanLimit
{
    /**
     * True when a plan-limit value means "no limit": the conventional -1,
     * or the PHP_INT_MAX sentinel minted for bypass-permission holders
     * (>= comparison also catches float representations that round up).
     */
    public static function isUnlimited(int|float $value): bool
    {
        return $value < 0 || $value >= PHP_INT_MAX;
    }

    /**
     * Normalize a plan-limit value for emission: unlimited (in any spelling)
     * becomes the conventional -1; finite values pass through as ints.
     */
    public static function normalize(int|float $value): int
    {
        return self::isUnlimited($value) ? -1 : (int) $value;
    }
}
