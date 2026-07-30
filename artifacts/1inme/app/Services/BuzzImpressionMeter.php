<?php

namespace App\Services;

use App\Modules\User\Models\BuzzImpressionCounter;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Per-creator monthly Buzz (social-proof) impression metering.
 *
 * Centralises everything related to the per-plan `max_buzz_impressions`
 * allowance so the public widget controller, the creator-facing usage
 * indicators, the plan-capability payload and the plan recommender all
 * agree:
 *
 *  - allowance resolution (with a safe unlimited fallback when the plan
 *    key is missing, so existing customers are never silently cut off
 *    until plans are reseeded — see {@see DEFAULT_ALLOWANCE});
 *  - period-scoped usage counting (one row per user per calendar month
 *    in `buzz_impression_counters`), so it resets automatically;
 *  - the "is serving paused?" gate used by the public config endpoint.
 *
 * Mirrors the api_usage_counters / MeterApiUsage metering pattern, minus
 * coin overage — exceeding the Buzz allowance simply pauses serving for
 * the rest of the period.
 */
class BuzzImpressionMeter
{
    public const FEATURE_KEY = 'max_buzz_impressions';

    /**
     * Safe fallback when a plan doesn't define the allowance key: treat
     * as unlimited (-1) so creators on un-reseeded plans keep serving.
     */
    public const DEFAULT_ALLOWANCE = -1;

    /** Current calendar-month bucket, e.g. "2026-06". */
    public static function currentPeriod(): string
    {
        return BuzzImpressionCounter::currentPeriod();
    }

    /**
     * Any allowance at or above this is treated as unlimited. Holders of
     * the `user.plan_limits.bypass` permission get PHP_INT_MAX from
     * {@see User::getPlanFeature()}, which must display as "∞" — never as
     * a raw 9-quintillion number.
     */
    public const UNLIMITED_THRESHOLD = PHP_INT_MAX;

    /**
     * Single source of truth for "is this allowance unlimited?" so the
     * pause gate and the usage summary can't drift: negative values
     * (the canonical -1) and absurdly large ones (plan-limit bypass)
     * both count as unlimited.
     */
    public static function isUnlimited(int $allowance): bool
    {
        return $allowance < 0 || $allowance >= self::UNLIMITED_THRESHOLD;
    }

    /**
     * Resolve the creator's monthly Buzz impression allowance.
     * -1 means unlimited. Null user => unlimited (defensive).
     * Unlimited allowances (including the PHP_INT_MAX plan-limit bypass
     * sentinel) are normalised to -1 so every consumer — including the
     * API capability payload — sees the canonical unlimited value.
     */
    public static function allowanceFor(?User $user): int
    {
        if (!$user) {
            return -1;
        }
        $allowance = (int) $user->getPlanFeature(self::FEATURE_KEY, self::DEFAULT_ALLOWANCE);
        return self::isUnlimited($allowance) ? -1 : $allowance;
    }

    /** Impressions used by a creator in the given (or current) period. */
    public static function used(int $userId, ?string $period = null): int
    {
        if ($userId <= 0) {
            return 0;
        }
        $period = $period ?: self::currentPeriod();
        return (int) BuzzImpressionCounter::query()
            ->where('user_id', $userId)
            ->where('period', $period)
            ->value('impressions_used');
    }

    /**
     * Record one impression against the creator's current-period counter.
     * Upserts the (user, period) row and atomically increments to stay
     * correct under the concurrent, unauthenticated public widget traffic.
     */
    public static function record(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }
        $period = self::currentPeriod();
        $now = now();

        DB::table('buzz_impression_counters')->upsert(
            [[
                'user_id'          => $userId,
                'period'           => $period,
                'impressions_used' => 1,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]],
            ['user_id', 'period'],
            // On conflict, bump the counter + touch updated_at.
            [
                'impressions_used' => DB::raw('buzz_impression_counters.impressions_used + 1'),
                'updated_at'       => $now,
            ],
        );
    }

    /**
     * True when the creator has reached their monthly allowance and their
     * Buzz widgets should stop serving notifications for the rest of the
     * period. A -1 (unlimited) allowance never pauses.
     */
    public static function servingPaused(?User $user): bool
    {
        if (!$user) {
            return false;
        }
        $allowance = self::allowanceFor($user);
        if (self::isUnlimited($allowance)) {
            return false; // unlimited
        }
        if ($allowance === 0) {
            return true; // no allowance at all
        }
        return self::used((int) $user->id) >= $allowance;
    }

    /**
     * View-friendly usage summary for the Buzz UI, capability payload and
     * recommender. `allowance === -1` => unlimited.
     *
     * @return array{allowance:int,used:int,remaining:?int,percent_used:int,unlimited:bool,paused:bool,period:string}
     */
    public static function usageSummary(?User $user): array
    {
        $allowance = self::allowanceFor($user);
        $used = $user ? self::used((int) $user->id) : 0;
        $unlimited = self::isUnlimited($allowance);
        $remaining = $unlimited ? null : max(0, $allowance - $used);
        $percent = ($unlimited || $allowance === 0)
            ? ($unlimited ? 0 : 100)
            : (int) min(100, round(($used / max($allowance, 1)) * 100));

        return [
            'allowance'    => $allowance,
            'used'         => $used,
            'remaining'    => $remaining,
            'percent_used' => $percent,
            'unlimited'    => $unlimited,
            'paused'       => self::servingPaused($user),
            'period'       => self::currentPeriod(),
        ];
    }
}
