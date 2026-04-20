<?php

namespace Database\Seeders;

use App\Modules\Admin\Models\TaxJurisdiction;
use App\Services\TaxCalculator;
use Illuminate\Database\Seeder;

/**
 * Idempotent seeder for tax jurisdictions.
 *
 *  - India: one GST_INTRA row per state/UT at 18% (split into CGST 9 +
 *           SGST 9 by the calculator), and one country-wide GST_INTER
 *           row at 18% IGST.
 *  - EU:    one VAT row per member state at the standard SaaS rate
 *           (typical 2026 standard rate; admin can override). All
 *           marked `b2b_reverse_charge=true`.
 *  - UK:    one VAT row at 20% (B2B reverse-charge enabled for non-UK
 *           VAT-registered buyers).
 *
 * Re-runnable: uses updateOrCreate keyed by (country, region, kind).
 */
class TaxJurisdictionsSeeder extends Seeder
{
    public function run(): void
    {
        // India: one row per state for intra-state, one country-wide IGST.
        foreach (array_keys(TaxCalculator::IN_STATES) as $state) {
            TaxJurisdiction::updateOrCreate(
                ['country' => 'IN', 'region' => $state, 'kind' => 'GST_INTRA'],
                [
                    'label'        => 'GST (CGST+SGST) — ' . TaxCalculator::IN_STATES[$state],
                    'rate_percent' => 18.000,
                    'b2b_reverse_charge' => false,
                    'is_active'    => true,
                ]
            );
        }
        TaxJurisdiction::updateOrCreate(
            ['country' => 'IN', 'region' => null, 'kind' => 'GST_INTER'],
            [
                'label'        => 'IGST — inter-state',
                'rate_percent' => 18.000,
                'b2b_reverse_charge' => false,
                'is_active'    => true,
            ]
        );

        // EU: standard VAT rates as of 2026 (admin-overrideable).
        $euRates = [
            'AT' => 20, 'BE' => 21, 'BG' => 20, 'HR' => 25, 'CY' => 19, 'CZ' => 21,
            'DK' => 25, 'EE' => 22, 'FI' => 25.5, 'FR' => 20, 'DE' => 19, 'GR' => 24,
            'HU' => 27, 'IE' => 23, 'IT' => 22, 'LV' => 21, 'LT' => 21, 'LU' => 17,
            'MT' => 18, 'NL' => 21, 'PL' => 23, 'PT' => 23, 'RO' => 19, 'SK' => 23,
            'SI' => 22, 'ES' => 21, 'SE' => 25,
        ];
        foreach ($euRates as $cc => $rate) {
            TaxJurisdiction::updateOrCreate(
                ['country' => $cc, 'region' => null, 'kind' => 'VAT'],
                [
                    'label'        => 'VAT — ' . $cc,
                    'rate_percent' => $rate,
                    'b2b_reverse_charge' => true,
                    'is_active'    => true,
                ]
            );
        }

        // United Kingdom (post-Brexit, separate from EU but reverse-charge applies).
        TaxJurisdiction::updateOrCreate(
            ['country' => 'GB', 'region' => null, 'kind' => 'VAT'],
            [
                'label'        => 'VAT — United Kingdom',
                'rate_percent' => 20.000,
                'b2b_reverse_charge' => true,
                'is_active'    => true,
            ]
        );
    }
}
