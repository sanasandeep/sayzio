<?php

namespace App\Services\Billing;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;
use App\Services\PricingResolver;

/**
 * Pure, side-effect-free proration math. Separated out so we can
 * unit-test the edge cases (last day of cycle, downgrade attempt,
 * same-plan no-op) without standing up the full checkout pipeline.
 *
 * Spec — upgrade charge formula:
 *
 *     charge_minor = floor( planB_price_minor × days_left / days_in_cycle )
 *
 * This is gross proration: the user is billed for the remaining days
 * AT THE NEW PLAN's rate. The original invoice for plan A is not
 * credited automatically — admins can issue a partial refund / credit
 * note if a goodwill adjustment is wanted.
 *
 * Invariants:
 *   - All money is in MINOR units (paise / cents / pence).
 *   - Downgrades and same-plan switches return 0 — apply at renewal.
 *   - Last-day-of-cycle upgrades: days_left = 1 (never 0).
 *   - Rounded DOWN per spec — never charge more than the exact share.
 */
class ProrationCalculator
{
    /**
     * Low-level entry point: caller supplies the resolved prices in
     * minor units (usually from PricingResolver so the correct
     * currency and `prices` table row is used). This keeps the math
     * unit-testable without touching the DB or a PricingResolver.
     *
     * @return array{amount_minor:int, days_left:int, days_in_cycle:int, is_upgrade:bool}
     */
    public static function prorateMinor(
        int $fromPriceMinor,
        int $toPriceMinor,
        string $cycle,
        \DateTimeInterface $now,
        \DateTimeInterface $currentPeriodEnd
    ): array {
        $cycle    = $cycle === 'annual' ? 'annual' : 'monthly';
        $days     = $cycle === 'annual' ? 365 : 30;
        $nowTs    = (new \DateTimeImmutable($now->format('c')))->setTime(0, 0);
        $endTs    = (new \DateTimeImmutable($currentPeriodEnd->format('c')))->setTime(0, 0);
        $daysLeft = max(1, (int) $nowTs->diff($endTs)->format('%r%a') + 1);
        $daysLeft = min($daysLeft, $days);

        // Same-plan (fromPrice === toPrice is the best proxy at the
        // minor-unit level) or downgrade → no mid-cycle charge. At the
        // plan level, callers compare by id first and short-circuit.
        if ($toPriceMinor <= $fromPriceMinor) {
            return [
                'amount_minor'  => 0,
                'days_left'     => $daysLeft,
                'days_in_cycle' => $days,
                'is_upgrade'    => false,
            ];
        }

        // Spec formula: planB_price × days_left / days_in_cycle, floor.
        $charge = intdiv($toPriceMinor * $daysLeft, $days);
        return [
            'amount_minor'  => max(0, $charge),
            'days_left'     => $daysLeft,
            'days_in_cycle' => $days,
            'is_upgrade'    => true,
        ];
    }

    /**
     * Plan-level entry point used by the upgrade controller. Resolves
     * prices from the authoritative `prices` table via PricingResolver
     * in the CURRENCY LOCKED ON THE SUBSCRIPTION (never re-derived from
     * the user's country/session — that would let a currency change
     * between sessions silently recompute the upgrade charge in the
     * wrong currency). Callers must pass `$currency` explicitly; the
     * `$user` arg is retained for backwards compatibility with legacy
     * call-sites but is ignored when `$currency` is supplied.
     */
    public static function prorate(
        Plan $from,
        Plan $to,
        string $cycle,
        \DateTimeInterface $now,
        \DateTimeInterface $currentPeriodEnd,
        ?User $user = null,
        ?string $currency = null
    ): array {
        if ($from->id === $to->id) {
            return ['amount_minor' => 0, 'days_left' => 0, 'days_in_cycle' => $cycle === 'annual' ? 365 : 30, 'is_upgrade' => false];
        }
        $fromMinor = self::resolveMinor($from, $cycle, $user, $currency);
        $toMinor   = self::resolveMinor($to,   $cycle, $user, $currency);
        return self::prorateMinor($fromMinor, $toMinor, $cycle, $now, $currentPeriodEnd);
    }

    /**
     * Resolve a plan's price in minor units using the authoritative
     * `prices` table. When `$currency` is provided (lifecycle paths),
     * the lookup is LOCKED to that currency — no country/session
     * re-derivation. Legacy call-sites that only pass `$user` still
     * work via country/session resolution for display pricing.
     * Falls back to the legacy decimal column only if no row exists.
     */
    public static function resolveMinor(Plan $plan, string $cycle, ?User $user = null, ?string $currency = null): int
    {
        $cycle = $cycle === 'annual' ? 'annual' : 'monthly';
        $resolved = $currency !== null
            ? PricingResolver::priceForCurrency($plan, $currency, $cycle)
            : PricingResolver::priceFor($plan, $user, $cycle);
        $minor = (int) ($resolved['amount_minor'] ?? 0);
        if ($minor > 0) return $minor;
        // Legacy fallback: tests and pre-backfill rows.
        $decimal = $cycle === 'annual' ? (float) $plan->annual_price : (float) $plan->monthly_price;
        return (int) round($decimal * 100);
    }

    /** @deprecated Use resolveMinor(). Kept for call-site compatibility. */
    public static function fullPriceMinor(Plan $plan, string $cycle): int
    {
        return self::resolveMinor($plan, $cycle, null);
    }
}
