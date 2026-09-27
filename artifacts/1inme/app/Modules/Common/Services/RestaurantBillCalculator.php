<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuCoupon;

/**
 * Single source of truth for the restaurant menu's estimated bill (Task #3067).
 *
 * Given a menu and a cart subtotal, it applies (at most) one owner-defined
 * coupon and the menu-level GST/tax setting to produce an itemised estimate:
 * subtotal → discount → tax line (added-on OR "incl.") → estimated total.
 *
 * IMPORTANT: this is an *estimate*, never a payable invoice. No money is ever
 * collected; the guest settles with staff directly. The same method backs the
 * live quote endpoint, the actual order snapshot, and the mobile API so every
 * surface shows identical figures.
 */
class RestaurantBillCalculator
{
    /**
     * Compute the breakdown for a cart subtotal on a given menu.
     *
     * @return array{
     *   subtotal:float, fulfilment:?string,
     *   charges:array<int,array{label:string,amount:float}>, charges_amount:float,
     *   coupon_code:?string, coupon_applied:bool,
     *   coupon_error:?string, discount_amount:float, taxable_base:float,
     *   tax_enabled:bool, tax_inclusive:bool, tax_rate:float, tax_label:string,
     *   tax_amount:float, total:float, currency:string
     * }
     */
    public function compute(RestaurantMenu $menu, float $subtotal, ?string $couponCode = null, ?string $fulfilment = null): array
    {
        $subtotal = round(max(0, $subtotal), 2);

        $couponCode = RestaurantMenuCoupon::normalizeCode($couponCode);
        $couponApplied = false;
        $couponError = null;
        $discount = 0.0;

        if ($couponCode !== '') {
            $coupon = $menu->coupons()
                ->where('code', $couponCode)
                ->first();

            if (!$coupon || !$coupon->is_active) {
                $couponError = 'This code isn’t valid for this menu.';
            } elseif ($subtotal < (float) $coupon->min_subtotal) {
                $couponError = 'Add '
                    . \App\Modules\User\Support\MenuMoney::format(
                        (float) $coupon->min_subtotal,
                        \App\Modules\User\Support\MenuMoney::resolve($menu->currency, (array) ($menu->settings ?? []))
                    )
                    . ' or more to use this code.';
            } else {
                $discount = $this->discountFor($coupon, $subtotal);
                $couponApplied = $discount > 0;
                if (!$couponApplied) {
                    $couponError = 'This code doesn’t apply to your order.';
                }
            }
        }

        $discount = round(min($discount, $subtotal), 2);
        $taxableBase = round($subtotal - $discount, 2);

        $taxEnabled = $menu->taxEnabled();
        $taxInclusive = $menu->taxInclusive();
        $rate = max(0, $menu->taxRate());
        $taxAmount = 0.0;
        $total = $taxableBase;

        if ($taxEnabled && $rate > 0) {
            if ($taxInclusive) {
                // Prices already include tax — break the portion out for
                // display, but do NOT add it again.
                $taxAmount = round($taxableBase - ($taxableBase / (1 + ($rate / 100))), 2);
                $total = $taxableBase;
            } else {
                // Tax added on top of the discounted subtotal.
                $taxAmount = round($taxableBase * ($rate / 100), 2);
                $total = round($taxableBase + $taxAmount, 2);
            }
        }

        // Charges land AFTER tax, and a percentage charge is taken on the
        // discounted subtotal rather than the taxed total -- the ordinary
        // reading of "10% service charge". See MenuFulfilment's docblock:
        // it is a tax question rather than a software one, so the order is
        // written down rather than assumed.
        $chargeLines = \App\Modules\User\Support\MenuFulfilment::applicable(
            (array) ($menu->settings ?? []), $fulfilment, $taxableBase
        );
        $chargesAmount = \App\Modules\User\Support\MenuFulfilment::total($chargeLines);
        $total = round($total + $chargesAmount, 2);

        return [
            'subtotal'        => $subtotal,
            'fulfilment'      => $fulfilment,
            'charges'         => $chargeLines,
            'charges_amount'  => $chargesAmount,
            'coupon_code'     => $couponApplied ? $couponCode : null,
            'coupon_applied'  => $couponApplied,
            'coupon_error'    => $couponError,
            'discount_amount' => $discount,
            'taxable_base'    => $taxableBase,
            'tax_enabled'     => $taxEnabled && $rate > 0,
            'tax_inclusive'   => $taxInclusive,
            'tax_rate'        => $rate,
            'tax_label'       => $menu->taxLabel(),
            'tax_amount'      => $taxAmount,
            'total'           => round($total, 2),
            'currency'        => $menu->currency,
        ];
    }

    protected function discountFor(RestaurantMenuCoupon $coupon, float $subtotal): float
    {
        if ($coupon->discount_type === RestaurantMenuCoupon::TYPE_FIXED) {
            return round(min((float) $coupon->discount_value, $subtotal), 2);
        }

        // Percentage off, clamped to 0–100.
        $pct = max(0, min(100, (float) $coupon->discount_value));

        return round($subtotal * ($pct / 100), 2);
    }
}
