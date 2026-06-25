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
 *  - US:    one SALES row per state at the standard state-level rate
 *           (NOMAD states seeded at 0%), plus a country-level US SALES
 *           row at 0% as the fallback for unseeded states/territories.
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

        // United States: one SALES row per state at its standard state-level
        // rate (admin-overrideable). NOMAD states (no state sales tax) are
        // seeded explicitly at 0% so they resolve cleanly. A country-level
        // US row at 0% is the fallback for any state without a specific row
        // (territories, unseeded codes). Local/city/county taxes are out of
        // scope — admins can add finer rows through /admin/taxes.
        $usStateRates = [
            'AL' => 4.0,   'AK' => 0.0,   'AZ' => 5.6,   'AR' => 6.5,   'CA' => 7.25,
            'CO' => 2.9,   'CT' => 6.35,  'DE' => 0.0,   'DC' => 6.0,   'FL' => 6.0,
            'GA' => 4.0,   'HI' => 4.0,   'ID' => 6.0,   'IL' => 6.25,  'IN' => 7.0,
            'IA' => 6.0,   'KS' => 6.5,   'KY' => 6.0,   'LA' => 4.45,  'ME' => 5.5,
            'MD' => 6.0,   'MA' => 6.25,  'MI' => 6.0,   'MN' => 6.875, 'MS' => 7.0,
            'MO' => 4.225, 'MT' => 0.0,   'NE' => 5.5,   'NV' => 6.85,  'NH' => 0.0,
            'NJ' => 6.625, 'NM' => 4.875, 'NY' => 4.0,   'NC' => 4.75,  'ND' => 5.0,
            'OH' => 5.75,  'OK' => 4.5,   'OR' => 0.0,   'PA' => 6.0,   'RI' => 7.0,
            'SC' => 6.0,   'SD' => 4.2,   'TN' => 7.0,   'TX' => 6.25,  'UT' => 6.1,
            'VT' => 6.0,   'VA' => 5.3,   'WA' => 6.5,   'WV' => 6.0,   'WI' => 5.0,
            'WY' => 4.0,
        ];
        foreach ($usStateRates as $state => $rate) {
            TaxJurisdiction::updateOrCreate(
                ['country' => 'US', 'region' => $state, 'kind' => 'SALES'],
                [
                    'label'        => $state . ' Sales Tax',
                    'rate_percent' => $rate,
                    'b2b_reverse_charge' => false,
                    'is_active'    => true,
                ]
            );
        }
        TaxJurisdiction::updateOrCreate(
            ['country' => 'US', 'region' => null, 'kind' => 'SALES'],
            [
                'label'        => 'US Sales Tax (no state-specific rate)',
                'rate_percent' => 0.000,
                'b2b_reverse_charge' => false,
                'is_active'    => true,
            ]
        );
    }
}
