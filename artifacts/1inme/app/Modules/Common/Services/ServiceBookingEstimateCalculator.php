<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\ServiceBooking;

/**
 * Single source of truth for a Service Booking page's estimated price
 * (Task #3085). Mirrors RestaurantBillCalculator but with NO coupons — just the
 * services subtotal plus the page-level GST/tax line (added-on OR "incl.").
 *
 * IMPORTANT: this is an *estimate*, never a payable invoice. No money is ever
 * collected; the visitor settles with the provider directly. The same method
 * backs the live quote endpoint, the request snapshot and the mobile API so
 * every surface shows identical figures.
 */
class ServiceBookingEstimateCalculator
{
    /**
     * @return array{
     *   subtotal:float, tax_enabled:bool, tax_inclusive:bool, tax_rate:float,
     *   tax_label:string, tax_amount:float, total:float, currency:string
     * }
     */
    public function compute(ServiceBooking $config, float $subtotal): array
    {
        $subtotal = round(max(0, $subtotal), 2);

        $taxEnabled   = $config->taxEnabled();
        $taxInclusive = $config->taxInclusive();
        $rate         = max(0, $config->taxRate());
        $taxAmount    = 0.0;
        $total        = $subtotal;

        if ($taxEnabled && $rate > 0) {
            if ($taxInclusive) {
                // Prices already include tax — break the portion out for
                // display, but do NOT add it again.
                $taxAmount = round($subtotal - ($subtotal / (1 + ($rate / 100))), 2);
                $total = $subtotal;
            } else {
                $taxAmount = round($subtotal * ($rate / 100), 2);
                $total = round($subtotal + $taxAmount, 2);
            }
        }

        return [
            'subtotal'      => $subtotal,
            'tax_enabled'   => $taxEnabled && $rate > 0,
            'tax_inclusive' => $taxInclusive,
            'tax_rate'      => $rate,
            'tax_label'     => $config->taxLabel(),
            'tax_amount'    => $taxAmount,
            'total'         => round($total, 2),
            'currency'      => $config->currency,
        ];
    }

    /** Shape a breakdown into the public estimate payload (adds is_estimate). */
    public static function serialize(array $bill): array
    {
        return [
            'subtotal'      => round($bill['subtotal'], 2),
            'tax_enabled'   => $bill['tax_enabled'],
            'tax_inclusive' => $bill['tax_inclusive'],
            'tax_rate'      => $bill['tax_rate'],
            'tax_label'     => $bill['tax_label'],
            'tax_amount'    => round($bill['tax_amount'], 2),
            'total'         => round($bill['total'], 2),
            'currency'      => $bill['currency'],
            'is_estimate'   => true,
        ];
    }
}
