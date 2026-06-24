<?php

namespace App\Services\AI;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;

/**
 * Centralizes per-plan gating for the first-class AI features so every
 * creation chokepoint, middleware case, and dashboard counter reads the
 * same source of truth.
 *
 * Two shapes:
 *  - Quantity features (Minds / Personas / Companions): a per-plan number,
 *    where -1 means Unlimited. Read from the plan via getPlanFeature(),
 *    falling back to the legacy GLOBAL admin cap
 *    (AiMindSettings / PersonaSettings / CompanionSettings) when the plan
 *    row predates these keys — so nothing regresses mid-rollout. The
 *    `user.plan_limits.bypass` permission lifts every cap (getPlanFeature
 *    already returns PHP_INT_MAX for those users).
 *  - Availability features (Ask Coach / Card & Brochure Scanner / AI Resume
 *    Tools): a per-plan boolean. Read from the plan flag when the key is
 *    present, falling back to today's gate (admin allow-list / always-on)
 *    when the key is absent so existing setups keep working.
 */
class AiPlanAccess
{
    /** Quantity feature alias => plan feature key. */
    public const QUANTITY_KEYS = [
        'minds'      => 'max_minds',
        'personas'   => 'max_personas',
        'companions' => 'max_companions',
    ];

    /** Effective per-plan quantity cap for a feature. -1 = unlimited. */
    public static function quantityCap(User $user, string $feature): int
    {
        $planKey = self::QUANTITY_KEYS[$feature] ?? null;
        if ($planKey === null) {
            return 0;
        }
        // getPlanFeature returns PHP_INT_MAX for bypass-holders and the
        // supplied global-cap fallback when the plan key is absent.
        return (int) $user->getPlanFeature($planKey, self::globalQuantityFallback($feature));
    }

    /** True when the user can create one more of $feature. */
    public static function underQuantityCap(User $user, string $feature, int $current): bool
    {
        $planKey = self::QUANTITY_KEYS[$feature] ?? null;
        if ($planKey === null) {
            return true;
        }
        return $user->planUnderLimit($planKey, $current, self::globalQuantityFallback($feature));
    }

    /** Cheapest active plan that raises the cap for $feature, or null. */
    public static function quantityUpgradePlan(User $user, string $feature, ?int $current = null): ?Plan
    {
        $planKey = self::QUANTITY_KEYS[$feature] ?? null;
        if ($planKey === null) {
            return null;
        }
        return $user->planThatUnlocks($planKey, $current);
    }

    /**
     * Build a clear "limit reached" message, optionally suffixed with the
     * cheapest plan that raises the cap.
     */
    public static function quantityLimitMessage(User $user, string $feature, string $noun, ?int $current = null): string
    {
        $cap = self::quantityCap($user, $feature);
        $msg = "You've reached your plan's {$noun} limit ({$cap}).";
        $plan = self::quantityUpgradePlan($user, $feature, $current);
        if ($plan) {
            $msg .= ' Upgrade to the ' . $plan->name . ' plan to add more.';
        }
        return $msg;
    }

    /** Legacy GLOBAL admin-cap fallback used when a plan predates the keys. */
    public static function globalQuantityFallback(string $feature): int
    {
        return match ($feature) {
            'minds'      => (int) AiMindSettings::cap('max_minds_per_user'),
            'personas'   => (int) PersonaSettings::cap('max_personas_per_user'),
            'companions' => (int) CompanionSettings::cap('max_companions_per_user'),
            default      => 0,
        };
    }

    /** True when the user's plan unlocks an availability AI feature. */
    public static function featureAllowed(User $user, string $feature): bool
    {
        // Explicit per-plan flag wins (bypass already handled inside
        // getPlanFeature). Only fall back to the legacy gate when the plan
        // row has not been seeded with the new key yet.
        if (self::planDefinesKey($user, $feature)) {
            return $user->planFeatureEnabled($feature);
        }
        if ($user->hasPermission('user.plan_limits.bypass')) {
            return true;
        }
        return self::legacyAvailabilityFallback($user, $feature);
    }

    /** Cheapest active plan that unlocks an availability feature, or null. */
    public static function featureUpgradePlan(User $user, string $feature): ?Plan
    {
        if (self::featureAllowed($user, $feature)) {
            return null;
        }
        // Prefer the per-plan unlock (works once every plan carries the key).
        $plan = $user->planThatUnlocks($feature);
        if ($plan) {
            return $plan;
        }
        // Otherwise defer to the legacy allow-list resolver.
        return match ($feature) {
            'ask_coach'          => AiEngineSettings::askCoachUpgradePlanFor($user),
            'ai_voice_assistant' => AiEngineSettings::voiceUpgradePlanFor($user),
            default              => null,
        };
    }

    private static function legacyAvailabilityFallback(User $user, string $feature): bool
    {
        return match ($feature) {
            'ask_coach'          => AiEngineSettings::askCoachAllowedFor($user),
            'ai_voice_assistant' => AiEngineSettings::voiceAllowedFor($user),
            // These never had per-plan gating before — keep them on.
            'ai_widget'          => true,
            'card_scan'          => true,
            'ai_resume_tools'    => true,
            default              => true,
        };
    }

    private static function planDefinesKey(User $user, string $key): bool
    {
        $plan = $user->plan;
        return $plan && is_array($plan->features) && array_key_exists($key, $plan->features);
    }
}
