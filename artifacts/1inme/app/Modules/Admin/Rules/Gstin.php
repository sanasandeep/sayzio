<?php

namespace App\Modules\Admin\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validate an Indian GSTIN.
 *
 *  - 15 chars: SS PPPPPPPPPP E Z C
 *      SS    – 2-digit numeric state code (01–37)
 *      PPPPP… – 10-char PAN (5 alpha, 4 digit, 1 alpha)
 *      E     – entity code (1 alphanumeric)
 *      Z     – literal "Z"
 *      C     – check digit (mod-36)
 *
 * The check digit uses the GSTN-specified mod-36 algorithm:
 *   - alphabet "0-9A-Z"
 *   - multiply each of the first 14 chars by alternating factors 1 / 2
 *     starting from the LEFT
 *   - for each product, take floor(p/36) + (p mod 36)
 *   - sum all 14 results, then check digit = (36 - (sum mod 36)) mod 36
 *
 * Reference: Goods & Services Tax Network official spec.
 */
class Gstin implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || $value === '') {
            return; // nullable – optional fields handle "empty" upstream
        }
        $v = strtoupper(trim($value));
        if (!self::isValid($v)) {
            $fail('The :attribute is not a valid GSTIN.');
        }
    }

    public static function isValid(string $gstin): bool
    {
        $gstin = strtoupper($gstin);
        if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][0-9A-Z]Z[0-9A-Z]$/', $gstin)) {
            return false;
        }
        $state = (int) substr($gstin, 0, 2);
        // State codes 01-37 cover all 28 states + 8 UTs (current allocation).
        if ($state < 1 || $state > 37) {
            return false;
        }
        return self::checksum(substr($gstin, 0, 14)) === substr($gstin, 14, 1);
    }

    /** GSTN mod-36 check-digit. */
    public static function checksum(string $first14): string
    {
        $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $sum = 0;
        $factor = 1; // alternates 1,2,1,2,...
        for ($i = 0, $n = strlen($first14); $i < $n; $i++) {
            $idx = strpos($alphabet, $first14[$i]);
            if ($idx === false) return '?';
            $product = $idx * $factor;
            $sum += intdiv($product, 36) + ($product % 36);
            $factor = $factor === 1 ? 2 : 1;
        }
        $check = (36 - ($sum % 36)) % 36;
        return $alphabet[$check];
    }
}
