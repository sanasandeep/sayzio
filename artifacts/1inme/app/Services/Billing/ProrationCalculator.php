<?php

namespace App\Services\Billing;

use App\Modules\Admin\Models\Plan;

/**
 * Pure, side-effect-free proration math. Separated out so we can
 * unit-test the edge cases (last day of cycle, downgrade attempt,
 * same-plan no-op) without standing up the full checkout pipeline.
 *
 * Invariants:
 *   - All money is in MINOR units (paise / cents / pence).
 *   - Downgrades and same-plan switches return 0 — the user doesn't
 *     pay anything mid-cycle; downgrades apply at renewal.
 *   - Last-day-of-cycle upgrades: days_left = 1 (never 0), so the
 *     user at least pays for today.
 *   - Rounded DOWN per spec — never charge more than the exact
 *     prorated share.
 */
class ProrationCalculator
{
    /**
     * @return array{amount_minor:int, days_left:int, days_in_cycle:int, is_upgrade:bool}
     */
    public static function prorate(
        Plan $from,
        Plan $to,
        string $cycle,
        \DateTimeInterface $now,
        \DateTimeInterface $currentPeriodEnd,
        string $currency = 'INR'
    ): array {
        $cycle  = $cycle === 'annual' ? 'annual' : 'monthly';
        $days   = $cycle === 'annual' ? 365 : 30;
        $nowTs  = (new \DateTimeImmutable($now->format('c')))->setTime(0, 0);
        $endTs  = (new \DateTimeImmutable($currentPeriodEnd->format('c')))->setTime(0, 0);
        $daysLeft = max(1, (int) $nowTs->diff($endTs)->format('%r%a') + 1);
        $daysLeft = min($daysLeft, $days);

        $toPrice   = self::fullPriceMinor($to, $cycle);
        $fromPrice = self::fullPriceMinor($from, $cycle);

        // Same-plan or downgrade — no mid-cycle charge.
        if ($from->id === $to->id || $toPrice <= $fromPrice) {
            return [
                'amount_minor'  => 0,
                'days_left'     => $daysLeft,
                'days_in_cycle' => $days,
                'is_upgrade'    => false,
            ];
        }

        // Charge only the DELTA for the remaining days — not the full
        // new plan price. The user already paid for plan A for those
        // days; billing them again for the overlap would double-bill.
        $delta     = $toPrice - $fromPrice;
        $chargeRaw = intdiv($delta * $daysLeft, $days); // floor

        return [
            'amount_minor'  => max(0, $chargeRaw),
            'days_left'     => $daysLeft,
            'days_in_cycle' => $days,
            'is_upgrade'    => true,
        ];
    }

    public static function fullPriceMinor(Plan $plan, string $cycle): int
    {
        $decimal = $cycle === 'annual' ? (float) $plan->annual_price : (float) $plan->monthly_price;
        return (int) round($decimal * 100);
    }
}
