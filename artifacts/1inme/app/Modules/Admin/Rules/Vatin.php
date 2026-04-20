<?php

namespace App\Modules\Admin\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Format-check a European VAT identification number (VATIN).
 *
 * The format is per-country: a 2-letter ISO country prefix followed
 * by a country-specific national pattern. We deliberately keep this
 * to a regex-only check (no VIES API call) — that's a separate task
 * and shouldn't block tax-engine plumbing.
 */
class Vatin implements ValidationRule
{
    /**
     * Country → regex (national portion only — the ISO prefix is
     * stripped before matching). Covers EU-27 + GB + NO + CH for now.
     */
    public const PATTERNS = [
        'AT' => '/^U[0-9]{8}$/',
        'BE' => '/^[0-1][0-9]{9}$/',
        'BG' => '/^[0-9]{9,10}$/',
        'CY' => '/^[0-9]{8}[A-Z]$/',
        'CZ' => '/^[0-9]{8,10}$/',
        'DE' => '/^[0-9]{9}$/',
        'DK' => '/^[0-9]{8}$/',
        'EE' => '/^[0-9]{9}$/',
        'EL' => '/^[0-9]{9}$/', // Greece uses EL prefix per VIES
        'GR' => '/^[0-9]{9}$/',
        'ES' => '/^[A-Z0-9][0-9]{7}[A-Z0-9]$/',
        'FI' => '/^[0-9]{8}$/',
        'FR' => '/^[A-Z0-9]{2}[0-9]{9}$/',
        'GB' => '/^([0-9]{9}|[0-9]{12}|GD[0-9]{3}|HA[0-9]{3})$/',
        'HR' => '/^[0-9]{11}$/',
        'HU' => '/^[0-9]{8}$/',
        'IE' => '/^[0-9]{7}[A-Z]{1,2}$/',
        'IT' => '/^[0-9]{11}$/',
        'LT' => '/^([0-9]{9}|[0-9]{12})$/',
        'LU' => '/^[0-9]{8}$/',
        'LV' => '/^[0-9]{11}$/',
        'MT' => '/^[0-9]{8}$/',
        'NL' => '/^[0-9]{9}B[0-9]{2}$/',
        'PL' => '/^[0-9]{10}$/',
        'PT' => '/^[0-9]{9}$/',
        'RO' => '/^[0-9]{2,10}$/',
        'SE' => '/^[0-9]{12}$/',
        'SI' => '/^[0-9]{8}$/',
        'SK' => '/^[0-9]{10}$/',
        'NO' => '/^[0-9]{9}(MVA)?$/',
        'CH' => '/^[A-Z0-9]{6,12}$/',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || $value === '') return;
        if (!self::isValid($value)) {
            $fail('The :attribute does not look like a valid VAT number.');
        }
    }

    public static function isValid(string $vatin): bool
    {
        $v = strtoupper(preg_replace('/\s+/', '', $vatin) ?? '');
        if (strlen($v) < 4) return false;
        $prefix = substr($v, 0, 2);
        $rest = substr($v, 2);
        if (!isset(self::PATTERNS[$prefix])) return false;
        return (bool) preg_match(self::PATTERNS[$prefix], $rest);
    }

    /** Extract the country prefix from a normalized VATIN. */
    public static function countryOf(string $vatin): ?string
    {
        $v = strtoupper(preg_replace('/\s+/', '', $vatin) ?? '');
        if (strlen($v) < 2) return null;
        $prefix = substr($v, 0, 2);
        // Greece: customers may enter GR but VIES uses EL — treat both as Greece.
        if ($prefix === 'EL') return 'GR';
        return isset(self::PATTERNS[$prefix]) ? $prefix : null;
    }
}
