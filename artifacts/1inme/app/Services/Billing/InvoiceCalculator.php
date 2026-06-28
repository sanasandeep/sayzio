<?php

namespace App\Services\Billing;

use App\Modules\User\Models\TaxRule;

/**
 * Single source of truth for invoice money math, shared by web, REST API
 * and (mirrored by) mobile. Computes per-line + invoice-level tax,
 * supports inclusive/exclusive rules, an invoice-level discount applied
 * proportionally to the taxable base, and a grouped tax breakdown.
 *
 * Line item input shape (only `amount_minor` required):
 *   [
 *     'label'         => string,
 *     'amount_minor'  => int,   // unit price in minor units
 *     'quantity'      => int,   // default 1
 *     'tax_rate_bps'  => int,   // optional, basis points (2000 = 20%)
 *     'tax_inclusive' => bool,  // optional, price already includes tax
 *     'tax_name'      => string,// optional label for the breakdown
 *     'catalog_item_id' => int, // optional provenance
 *     'meta'          => array, // preserved untouched
 *   ]
 *
 * Output: subtotal_minor (net of tax), discount_minor, tax_total_minor,
 * tax_breakdown[], grand_total_minor, and the normalized line_items with
 * computed net_minor / tax_minor / total_minor folded in.
 */
class InvoiceCalculator
{
    /**
     * @param array      $items   Raw line items.
     * @param int        $discountMinor Invoice-level discount (applied to net).
     * @param TaxRule|null $fallbackRule Default rule for lines with no own rate.
     */
    public function compute(array $items, int $discountMinor = 0, ?TaxRule $fallbackRule = null): array
    {
        $normalized = [];
        $subtotalNet = 0;

        // First pass — resolve per-line net amounts.
        foreach ($items as $raw) {
            $unit = (int) ($raw['amount_minor'] ?? 0);
            $qty  = max(1, (int) ($raw['quantity'] ?? 1));
            $gross = $unit * $qty;

            [$rateBps, $inclusive, $taxName] = $this->resolveRate($raw, $fallbackRule);

            if ($inclusive && $rateBps > 0) {
                $net = (int) round($gross * 10000 / (10000 + $rateBps));
            } else {
                $net = $gross;
            }

            $normalized[] = [
                'label'           => (string) ($raw['label'] ?? ''),
                'amount_minor'    => $unit,
                'quantity'        => $qty,
                'tax_rate_bps'    => $rateBps,
                'tax_inclusive'   => $inclusive,
                'tax_name'        => $taxName,
                'catalog_item_id' => isset($raw['catalog_item_id']) ? (int) $raw['catalog_item_id'] : null,
                'meta'            => is_array($raw['meta'] ?? null) ? $raw['meta'] : ($raw['meta'] ?? ['kind' => 'manual']),
                '_net'            => $net,
                '_gross'          => $gross,
            ];
            $subtotalNet += $net;
        }

        $discount = max(0, min($discountMinor, $subtotalNet));
        $factor = $subtotalNet > 0 ? ($subtotalNet - $discount) / $subtotalNet : 1.0;

        $taxTotal = 0;
        $breakdown = [];
        $lineItems = [];

        foreach ($normalized as $line) {
            $taxableNet = (int) round($line['_net'] * $factor);
            $tax = $line['tax_rate_bps'] > 0
                ? (int) round($taxableNet * $line['tax_rate_bps'] / 10000)
                : 0;
            $taxTotal += $tax;

            if ($tax > 0) {
                $key = ($line['tax_name'] ?: 'Tax') . '|' . $line['tax_rate_bps'];
                if (!isset($breakdown[$key])) {
                    $breakdown[$key] = [
                        'name'         => $line['tax_name'] ?: 'Tax',
                        'rate_bps'     => $line['tax_rate_bps'],
                        'amount_minor' => 0,
                    ];
                }
                $breakdown[$key]['amount_minor'] += $tax;
            }

            unset($line['_net'], $line['_gross']);
            $line['net_minor']   = $taxableNet;
            $line['tax_minor']   = $tax;
            $line['total_minor'] = $taxableNet + $tax;
            $lineItems[] = $line;
        }

        $netAfterDiscount = $subtotalNet - $discount;

        return [
            'subtotal_minor'    => $subtotalNet,
            'discount_minor'    => $discount,
            'tax_total_minor'   => $taxTotal,
            'tax_breakdown'     => array_values($breakdown),
            'grand_total_minor' => max(0, $netAfterDiscount + $taxTotal),
            'line_items'        => $lineItems,
        ];
    }

    /** @return array{0:int,1:bool,2:?string} [rate_bps, inclusive, name] */
    protected function resolveRate(array $raw, ?TaxRule $fallback): array
    {
        if (array_key_exists('tax_rate_bps', $raw) && $raw['tax_rate_bps'] !== null && $raw['tax_rate_bps'] !== '') {
            return [
                max(0, (int) $raw['tax_rate_bps']),
                (bool) ($raw['tax_inclusive'] ?? false),
                $raw['tax_name'] ?? null,
            ];
        }
        if ($fallback) {
            return [max(0, (int) $fallback->rate_bps), (bool) $fallback->inclusive, $fallback->name];
        }
        return [0, false, $raw['tax_name'] ?? null];
    }
}
