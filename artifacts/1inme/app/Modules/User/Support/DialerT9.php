<?php

namespace App\Modules\User\Support;

/**
 * T9 smart-dial helper. Converts contact names to their keypad-digit
 * representation so a sequence typed on the number pad matches both a
 * phone number AND a name (e.g. "526" matches "Jan" → J=5 A=2 N=6).
 *
 * Shared by the web and API dialer controllers so the matching contract
 * is identical across surfaces.
 */
class DialerT9
{
    /** Letter → keypad digit, lowercased input expected. */
    private const MAP = [
        'a' => '2', 'b' => '2', 'c' => '2',
        'd' => '3', 'e' => '3', 'f' => '3',
        'g' => '4', 'h' => '4', 'i' => '4',
        'j' => '5', 'k' => '5', 'l' => '5',
        'm' => '6', 'n' => '6', 'o' => '6',
        'p' => '7', 'q' => '7', 'r' => '7', 's' => '7',
        't' => '8', 'u' => '8', 'v' => '8',
        'w' => '9', 'x' => '9', 'y' => '9', 'z' => '9',
    ];

    /**
     * Encode an arbitrary string to its T9 digit sequence. Non-letters are
     * dropped (so spaces between first/last name don't break a contiguous
     * digit match). Digits already present pass through unchanged.
     */
    public static function encode(string $value): string
    {
        $out = '';
        foreach (mb_str_split(mb_strtolower($value)) as $ch) {
            if (isset(self::MAP[$ch])) {
                $out .= self::MAP[$ch];
            } elseif (ctype_digit($ch)) {
                $out .= $ch;
            }
        }
        return $out;
    }

    /**
     * Does $name's T9 encoding contain the typed digit $sequence?
     * Returns false for empty/too-short sequences (caller decides the floor).
     */
    public static function matches(string $name, string $sequence): bool
    {
        $seq = preg_replace('/\D+/', '', $sequence);
        if ($seq === '' || $seq === null) return false;
        return str_contains(self::encode($name), $seq);
    }

    /** Is the typed query a digit sequence (keypad) vs free text (search box)? */
    public static function isDigitSequence(string $value): bool
    {
        $v = trim($value);
        return $v !== '' && preg_match('/^[+]?[\d\s().*#-]+$/', $v) === 1;
    }
}
