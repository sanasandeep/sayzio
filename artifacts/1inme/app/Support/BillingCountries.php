<?php

namespace App\Support;

/**
 * Single source of truth for the billing-country option list rendered on the
 * profile page (Task #4726). The profile page historically inlined this list
 * directly in the Blade template, which risked the "Billing country" currency
 * picker and any Billing Address country picker silently drifting apart. Both
 * selects now render from this one array, so adding or removing a country
 * updates every picker at once.
 *
 * `''` is the "not set" placeholder (defaults to USD pricing) and `OTHER`
 * represents "everywhere else" — callers decide which sentinel entries to
 * render (the currency picker skips `OTHER`).
 */
class BillingCountries
{
    /** Country code => human label, in display order. */
    public const OPTIONS = [
        ''      => '— Not set (defaults to USD pricing)',
        'IN'    => 'India (₹ INR)',
        'US'    => 'United States ($ USD)',
        'GB'    => 'United Kingdom ($ USD)',
        'CA'    => 'Canada ($ USD)',
        'AU'    => 'Australia ($ USD)',
        'DE'    => 'Germany ($ USD)',
        'FR'    => 'France ($ USD)',
        'NL'    => 'Netherlands ($ USD)',
        'SG'    => 'Singapore ($ USD)',
        'AE'    => 'United Arab Emirates ($ USD)',
        'BR'    => 'Brazil ($ USD)',
        'MX'    => 'Mexico ($ USD)',
        'JP'    => 'Japan ($ USD)',
        'OTHER' => 'Other (everywhere else, $ USD)',
    ];

    /** The full option list (code => label), including sentinel entries. */
    public static function options(): array
    {
        return self::OPTIONS;
    }
}
