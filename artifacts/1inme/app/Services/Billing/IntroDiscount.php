<?php

namespace App\Services\Billing;

/**
 * First-term introductory plan discount: config + computation.
 *
 * Stored per-plan in `plans.intro_discount` (jsonb). It applies ONLY to
 * the FIRST term of a brand-new subscription (the {@see \App\Modules\User\Controllers\CheckoutController}
 * "new plan" path). Renewals (built inside the gateway adapters) and
 * upgrades (proration) always charge the full price, so the customer
 * automatically reverts to the normal rate on renewal — no expiry
 * bookkeeping needed.
 *
 * Canonical config shape (what {@see normalize()} emits, or null = off):
 *   [
 *     'enabled' => true,
 *     'type'    => 'percent'|'fixed',
 *     'percent' => int 1..100,                          // type=percent
 *     'fixed'   => ['USD'=>minorInt, 'INR'=>minorInt],  // type=fixed
 *     'cycles'  => ['monthly','annual'],                // applicable cycles
 *     'label'   => ?string,                             // optional marketing label
 *   ]
 *
 * Promo-code interaction: the platform plan checkout has NO promo-code
 * field, so the intro discount is the only automatic first-term
 * reduction — there is nothing to stack with. If a promo-code flow is
 * ever introduced for plan checkout it must NOT stack on top of an
 * active intro discount (intro wins, or the codes are mutually
 * exclusive); the two reductions never combine.
 */
class IntroDiscount
{
    public const CYCLES = ['monthly', 'annual'];
    public const CURRENCIES = ['USD', 'INR'];

    /**
     * Clean + validate a raw config (admin form or API) into the
     * canonical shape, or null when the discount is effectively off
     * (disabled, no usable amount, or an out-of-range percent).
     */
    public static function normalize($cfg): ?array
    {
        if (!is_array($cfg)) {
            return null;
        }

        $enabled = filter_var($cfg['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            return null;
        }

        $type = ($cfg['type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';

        $cycles = [];
        foreach ((array) ($cfg['cycles'] ?? []) as $c) {
            if (in_array($c, self::CYCLES, true) && !in_array($c, $cycles, true)) {
                $cycles[] = $c;
            }
        }
        // No explicit cycle picked → apply to every cycle.
        if (!$cycles) {
            $cycles = self::CYCLES;
        }

        $label = trim((string) ($cfg['label'] ?? ''));
        $label = $label !== '' ? mb_substr($label, 0, 120) : null;

        if ($type === 'percent') {
            $percent = (int) ($cfg['percent'] ?? 0);
            if ($percent < 1 || $percent > 100) {
                return null; // nothing meaningful to apply
            }
            return [
                'enabled' => true,
                'type'    => 'percent',
                'percent' => $percent,
                'cycles'  => $cycles,
                'label'   => $label,
            ];
        }

        // fixed: minor units per currency
        $fixed = [];
        $raw = (array) ($cfg['fixed'] ?? []);
        foreach (self::CURRENCIES as $cur) {
            $fixed[$cur] = max(0, (int) ($raw[$cur] ?? 0));
        }
        if (array_sum($fixed) <= 0) {
            return null; // no amount set in any currency
        }
        return [
            'enabled' => true,
            'type'    => 'fixed',
            'fixed'   => $fixed,
            'cycles'  => $cycles,
            'label'   => $label,
        ];
    }

    /**
     * Compute the first-term discount against a normal price.
     *
     * Returns null when no discount applies (config off, cycle excluded,
     * free/zero normal price, or a no-op reduction). Otherwise:
     *   [
     *     'first_minor'      => int,  // discounted first-term charge
     *     'normal_minor'     => int,  // the regular price
     *     'amount_off_minor' => int,  // reduction applied
     *     'percent_off'      => int,  // rounded % off (for badges)
     *     'type'             => 'percent'|'fixed',
     *     'label'            => ?string,
     *   ]
     */
    public static function compute($cfg, string $currency, string $cycle, int $normalMinor): ?array
    {
        $cfg = self::normalize($cfg);
        if (!$cfg) {
            return null;
        }
        $currency = in_array($currency, self::CURRENCIES, true) ? $currency : 'USD';
        $cycle = $cycle === 'annual' ? 'annual' : 'monthly';

        if ($normalMinor <= 0) {
            return null; // free plans have nothing to discount
        }
        if (!in_array($cycle, $cfg['cycles'], true)) {
            return null;
        }

        if ($cfg['type'] === 'percent') {
            $off = (int) round($normalMinor * $cfg['percent'] / 100);
        } else {
            $off = (int) ($cfg['fixed'][$currency] ?? 0);
        }

        // Clamp to the normal price and ignore a no-op reduction.
        $off = min($off, $normalMinor);
        if ($off <= 0) {
            return null;
        }

        $first = $normalMinor - $off;

        return [
            'first_minor'      => $first,
            'normal_minor'     => $normalMinor,
            'amount_off_minor' => $off,
            'percent_off'      => (int) round($off / $normalMinor * 100),
            'type'             => $cfg['type'],
            'label'            => $cfg['label'],
        ];
    }
}
