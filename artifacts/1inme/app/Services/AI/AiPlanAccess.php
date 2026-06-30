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
        'brand_kits' => 'max_brand_kits',
        // Saved AI Marketing Strategist plans (Task #3060).
        'marketing_strategies' => 'max_marketing_strategies',
    ];

    /**
     * Default saved-strategy cap for plans that predate the
     * `max_marketing_strategies` key. Generous so paid users who already
     * had the feature unlocked never hit a surprise wall mid-rollout; the
     * per-plan key overrides this once seeded. Free users are blocked
     * upstream by the availability gate, not this cap.
     */
    public const MARKETING_STRATEGIES_FALLBACK = 25;

    /**
     * AI coin-cost multipliers per provider => plan feature key. Each
     * scales the GLOBAL per-call base coin cost (1× = the base rate,
     * 0.5× = half price). Whisper STT is an OpenAI service so it shares
     * the `openai` multiplier.
     */
    public const COIN_MULTIPLIER_KEYS = [
        'openai'     => 'ai_openai_coin_multiplier',
        'elevenlabs' => 'ai_elevenlabs_coin_multiplier',
    ];

    /** Default multiplier when a plan predates the key (no behaviour change). */
    public const COIN_MULTIPLIER_DEFAULT = 1.0;

    /**
     * Effective coin-cost multiplier for $user against a provider. A plan
     * with no multiplier set (or set to 0 / negative) behaves exactly as
     * today (1×), so nothing regresses and we never charge free or
     * broken amounts. Bypass-permission users are unaffected: they aren't
     * gated and `getPlanFeature` would return PHP_INT_MAX for a numeric
     * default, which is meaningless as a multiplier, so we normalise to 1×.
     */
    public static function coinMultiplier(User $user, string $provider): float
    {
        $key = self::COIN_MULTIPLIER_KEYS[$provider] ?? null;
        if ($key === null) {
            return self::COIN_MULTIPLIER_DEFAULT;
        }
        if ($user->hasPermission('user.plan_limits.bypass')) {
            return self::COIN_MULTIPLIER_DEFAULT;
        }
        $mult = (float) $user->getPlanFeature($key, self::COIN_MULTIPLIER_DEFAULT);
        return $mult > 0 ? $mult : self::COIN_MULTIPLIER_DEFAULT;
    }

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
            'marketing_strategies' => self::MARKETING_STRATEGIES_FALLBACK,
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
            // Brand consistency score + On-Brand AI (Task #2664) — legacy-safe
            // default-on; per-plan unlock applies once plans carry the key.
            'brand_consistency'  => true,
            // AI Artistic QR — no per-plan gating before this; keep it on by
            // default so the per-plan flag is purely additive when seeded.
            'qr_art'             => true,
            // WhatsApp AI agent (Task #2759) — a paid-plan perk. Until plans
            // carry the explicit key, gate it to any non-free plan so free
            // accounts can't drive paid AI spend through the inbound webhook.
            'whatsapp_agent'     => !$user->isOnFreePlan(),
            // AI Marketing Strategist (Task #3060) — a paid-plan perk that
            // drives metered AI spend. Until plans carry the explicit key,
            // gate it to any non-free plan so free accounts can't run it.
            'marketing_strategist' => !$user->isOnFreePlan(),
            default              => true,
        };
    }

    private static function planDefinesKey(User $user, string $key): bool
    {
        $plan = $user->plan;
        return $plan && is_array($plan->features) && array_key_exists($key, $plan->features);
    }
}
