<?php

namespace App\Services;

use App\Modules\Admin\Models\TaxJurisdiction;
use App\Modules\Admin\Rules\Gstin;
use App\Modules\Admin\Rules\Vatin;

/**
 * Pure tax calculator. Takes a list of cart items (each with an
 * `amount_minor` integer) and a billing address, returns the
 * subtotal, the tax breakdown (one entry per applicable tax line)
 * and the total — all in MINOR units to preserve cent/paise
 * precision.
 *
 * Decision tree:
 *   1. No buyer country  -> 0% (treat as unknown jurisdiction).
 *   2. Buyer country == merchant country == "IN":
 *        - intra-state (buyer state == merchant gst_state) -> CGST 9% + SGST 9%
 *        - inter-state                                     -> IGST 18%
 *        - Holding a B2B GSTIN does NOT zero the tax (input-tax-credit
 *          handles it on the buyer side); we just print the GSTIN on
 *          the invoice.
 *   3. Buyer country in EU (or UK/NO/CH):
 *        - merchant in same country -> domestic VAT
 *        - merchant in different EU country, buyer has valid VATIN of
 *          buyer's own country -> 0% reverse-charge
 *        - merchant in different EU country, buyer has NO valid VATIN
 *          -> charge buyer's country VAT (B2C / OSS)
 *        - merchant outside EU exporting in -> charge destination VAT
 *          (treated like B2C OSS unless reverse-charge applies)
 *   4. Buyer country == "IN" but merchant elsewhere -> 0% (export of
 *      services from buyer's POV; merchant's home country handles it).
 *   5. Anything else (US, AU, ROW) -> 0% in this initial pass. Admins
 *      can add jurisdictions through /admin/taxes later.
 *
 * `place_of_supply` and `reverse_charge_note` are surfaced so the
 * invoice generator can print them.
 *
 * The implementation is intentionally read-only against
 * `tax_jurisdictions` so it stays unit-testable with seeded fixtures.
 */
class TaxCalculator
{
    /** ISO 3166-2 codes for India's 28 states + 8 UTs (current allocation). */
    public const IN_STATES = [
        'JK' => 'Jammu and Kashmir',     'HP' => 'Himachal Pradesh',     'PB' => 'Punjab',
        'CH' => 'Chandigarh',            'UT' => 'Uttarakhand',          'HR' => 'Haryana',
        'DL' => 'Delhi',                 'RJ' => 'Rajasthan',            'UP' => 'Uttar Pradesh',
        'BR' => 'Bihar',                 'SK' => 'Sikkim',               'AR' => 'Arunachal Pradesh',
        'NL' => 'Nagaland',              'MN' => 'Manipur',              'MZ' => 'Mizoram',
        'TR' => 'Tripura',               'ML' => 'Meghalaya',            'AS' => 'Assam',
        'WB' => 'West Bengal',           'JH' => 'Jharkhand',            'OR' => 'Odisha',
        'CG' => 'Chhattisgarh',          'MP' => 'Madhya Pradesh',       'GJ' => 'Gujarat',
        'DD' => 'Daman and Diu',         'DN' => 'Dadra and Nagar Haveli','MH' => 'Maharashtra',
        'KA' => 'Karnataka',             'GA' => 'Goa',                  'LD' => 'Lakshadweep',
        'KL' => 'Kerala',                'TN' => 'Tamil Nadu',           'PY' => 'Puducherry',
        'AN' => 'Andaman and Nicobar',   'TG' => 'Telangana',            'AP' => 'Andhra Pradesh',
        'LA' => 'Ladakh',
    ];

    /** EU member states (post-Brexit). */
    public const EU_COUNTRIES = [
        'AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE',
        'IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE',
    ];

    /**
     * @param array<int,array{label:string,amount_minor:int,quantity?:int}> $items
     * @param array{country:?string,region:?string,tax_id:?string,tax_id_kind:?string} $address
     * @return array{
     *   subtotal_minor:int,
     *   tax_total_minor:int,
     *   grand_total_minor:int,
     *   currency:string,
     *   tax_breakdown:array<int,array{label:string,rate_percent:float,amount_minor:int}>,
     *   place_of_supply:?string,
     *   reverse_charge_note:?string,
     *   line_items:array<int,array{label:string,amount_minor:int,quantity:int,line_total_minor:int}>
     * }
     */
    public static function calculate(array $items, array $address, string $currency = 'USD'): array
    {
        $subtotal = 0;
        $lineItems = [];
        foreach ($items as $it) {
            $qty = max(1, (int) ($it['quantity'] ?? 1));
            $lineTotal = (int) $it['amount_minor'] * $qty;
            $subtotal += $lineTotal;
            $lineItems[] = [
                'label'           => (string) $it['label'],
                'amount_minor'    => (int) $it['amount_minor'],
                'quantity'        => $qty,
                'line_total_minor'=> $lineTotal,
            ];
        }

        $merchantCountry = strtoupper((string) config('billing.merchant.country', 'IN'));
        $merchantState   = strtoupper((string) config('billing.merchant.gst_state', ''));
        $buyerCountry    = strtoupper((string) ($address['country'] ?? ''));
        $buyerState      = strtoupper((string) ($address['region'] ?? ''));
        $taxId           = strtoupper(trim((string) ($address['tax_id'] ?? '')));
        $taxIdKind       = strtoupper((string) ($address['tax_id_kind'] ?? ''));

        $breakdown = [];
        $place     = null;
        $rcNote    = null;

        if ($buyerCountry === '') {
            // Unknown jurisdiction → defer tax. Caller should warn user.
            return self::pack($subtotal, $breakdown, $lineItems, $currency, null, null);
        }

        // ----- INDIA path -----
        if ($buyerCountry === 'IN' && $merchantCountry === 'IN') {
            $place = 'IN-' . ($buyerState ?: '??');
            if ($buyerState && $buyerState === $merchantState) {
                // Intra-state — split into CGST + SGST.
                $cgst = self::activeRow('IN', $buyerState, 'GST_INTRA');
                $rate = $cgst?->rate_percent ?? 18.0; // sensible default if seeder hasn't run
                // Combined rate is the row's `rate_percent` (e.g. 18) — split half/half.
                $half = round($rate / 2, 3);
                $cgstAmt = self::pct($subtotal, $half);
                $sgstAmt = self::pct($subtotal, $half);
                $breakdown[] = ['label' => 'CGST ' . self::pctStr($half) . '%', 'rate_percent' => $half, 'amount_minor' => $cgstAmt];
                $breakdown[] = ['label' => 'SGST ' . self::pctStr($half) . '%', 'rate_percent' => $half, 'amount_minor' => $sgstAmt];
            } else {
                // Inter-state — single IGST line.
                $igst = self::activeRow('IN', $buyerState ?: null, 'GST_INTER');
                $rate = $igst?->rate_percent ?? 18.0;
                $breakdown[] = ['label' => 'IGST ' . self::pctStr($rate) . '%', 'rate_percent' => $rate, 'amount_minor' => self::pct($subtotal, $rate)];
            }
            return self::pack($subtotal, $breakdown, $lineItems, $currency, $place, $rcNote);
        }

        // Buyer in IN but merchant elsewhere — export of services TO India.
        // Out of scope for this initial pass; treat as 0% with a note.
        if ($buyerCountry === 'IN') {
            return self::pack($subtotal, $breakdown, $lineItems, $currency, 'IN-Export', 'Export of services — buyer responsible for any local tax.');
        }

        // Indian merchant selling to a non-Indian buyer — this is an "export
        // of services" / OIDAR transaction under Indian GST. Per IGST rules
        // such supplies are zero-rated as long as payment is received in
        // convertible foreign exchange. We therefore short-circuit before
        // the destination-country VAT branches below: charging foreign VAT
        // would be wrong (the merchant is not VAT-registered in those
        // jurisdictions) and so would charging IGST.
        if ($merchantCountry === 'IN') {
            return self::pack(
                $subtotal,
                $breakdown,
                $lineItems,
                $currency,
                $buyerCountry,
                'Export of services / OIDAR — zero-rated under Indian GST.'
            );
        }

        // ----- EU + UK/NO/CH path -----
        if (in_array($buyerCountry, self::EU_COUNTRIES, true) || in_array($buyerCountry, ['GB', 'NO', 'CH'], true)) {
            $place = $buyerCountry;
            $merchantInSameCountry = ($merchantCountry === $buyerCountry);
            $merchantInEu = in_array($merchantCountry, self::EU_COUNTRIES, true);

            $vatRow = self::activeRow($buyerCountry, null, 'VAT');
            $rate   = $vatRow?->rate_percent ?? 0.0;

            if ($merchantInSameCountry) {
                // Domestic VAT regardless of B2B/B2C.
                if ($rate > 0) {
                    $breakdown[] = ['label' => 'VAT ' . self::pctStr($rate) . '%', 'rate_percent' => $rate, 'amount_minor' => self::pct($subtotal, $rate)];
                }
                return self::pack($subtotal, $breakdown, $lineItems, $currency, $place, null);
            }

            // Cross-border within EU: B2B reverse-charge if buyer has a
            // valid VATIN matching their own country.
            if ($merchantInEu && in_array($buyerCountry, self::EU_COUNTRIES, true)) {
                if ($taxIdKind === 'VATIN' && $taxId !== '' && Vatin::isValid($taxId) && Vatin::countryOf($taxId) === $buyerCountry && $vatRow?->b2b_reverse_charge) {
                    return self::pack($subtotal, $breakdown, $lineItems, $currency, $place, 'Reverse charge — customer to account for VAT.');
                }
                // B2C → charge buyer's country VAT (OSS).
                if ($rate > 0) {
                    $breakdown[] = ['label' => 'VAT ' . self::pctStr($rate) . '%', 'rate_percent' => $rate, 'amount_minor' => self::pct($subtotal, $rate)];
                }
                return self::pack($subtotal, $breakdown, $lineItems, $currency, $place, null);
            }

            // Merchant outside EU selling into EU/UK/NO/CH — destination VAT
            // (or reverse-charge if buyer is registered with a declared VATIN).
            if ($taxIdKind === 'VATIN' && $taxId !== '' && Vatin::isValid($taxId) && Vatin::countryOf($taxId) === $buyerCountry) {
                return self::pack($subtotal, $breakdown, $lineItems, $currency, $place, 'Reverse charge — customer to account for VAT.');
            }
            if ($rate > 0) {
                $breakdown[] = ['label' => 'VAT ' . self::pctStr($rate) . '%', 'rate_percent' => $rate, 'amount_minor' => self::pct($subtotal, $rate)];
            }
            return self::pack($subtotal, $breakdown, $lineItems, $currency, $place, null);
        }

        // ----- ROW (US, AU, etc.) -----
        $place = $buyerCountry;
        return self::pack($subtotal, $breakdown, $lineItems, $currency, $place, null);
    }

    private static function activeRow(string $country, ?string $region, string $kind): ?TaxJurisdiction
    {
        // Tax-rate applicability is window-bounded: a row only counts if today
        // falls in [effective_from, effective_to]. NULL bounds mean "no
        // start / no end". When multiple rows match (e.g. an admin schedules
        // a future replacement), we pick the most recent `effective_from`,
        // tie-broken by id desc so the choice is deterministic.
        $today = now()->toDateString();
        // We compare on DATE() so SQLite (where dates can be persisted as
        // 'YYYY-MM-DD HH:MM:SS') and PostgreSQL agree. Without this,
        // lexicographic '2026-04-20 00:00:00' <= '2026-04-20' is FALSE in
        // SQLite and validly-active rows get silently filtered out.
        $apply = function ($q) use ($today) {
            return $q->where('is_active', true)
                ->where(function ($w) use ($today) {
                    $w->whereNull('effective_from')->orWhereRaw('DATE(effective_from) <= ?', [$today]);
                })
                ->where(function ($w) use ($today) {
                    $w->whereNull('effective_to')->orWhereRaw('DATE(effective_to) >= ?', [$today]);
                })
                // COALESCE so NULL effective_from sorts as a very old date,
                // making any explicitly-dated newer row win deterministically.
                ->orderByRaw("COALESCE(DATE(effective_from), '1970-01-01') DESC")
                ->orderByDesc('id');
        };

        $base = TaxJurisdiction::where('country', $country)->where('kind', $kind);

        if ($region !== null) {
            $exact = $apply((clone $base)->where('region', $region))->first();
            if ($exact) return $exact;
        }
        return $apply((clone $base)->whereNull('region'))->first();
    }

    /** Banker-friendly percentage of a minor amount, returned in minor units. */
    private static function pct(int $minor, float $ratePct): int
    {
        return (int) round(($minor * $ratePct) / 100);
    }

    private static function pctStr(float $rate): string
    {
        return rtrim(rtrim(number_format($rate, 3, '.', ''), '0'), '.');
    }

    private static function pack(int $subtotal, array $breakdown, array $lineItems, string $currency, ?string $place, ?string $rcNote): array
    {
        $taxTotal = array_sum(array_map(fn ($b) => $b['amount_minor'], $breakdown));
        return [
            'subtotal_minor'      => $subtotal,
            'tax_total_minor'     => (int) $taxTotal,
            'grand_total_minor'   => $subtotal + (int) $taxTotal,
            'currency'            => $currency,
            'tax_breakdown'       => $breakdown,
            'place_of_supply'     => $place,
            'reverse_charge_note' => $rcNote,
            'line_items'          => $lineItems,
        ];
    }
}
