<?php

namespace App\Modules\User\Support;

/**
 * The code on an order's QR, and what a counter does with it when scanned.
 *
 * Sana, 2026-10-05: "QR code per order".
 *
 * ---- There was already an identifier; it just had no picture ----------
 *
 * Every order has carried a `public_token` since ordering shipped -- a
 * UUID, minted on create, which is how the guest's own confirmation screen
 * polls its status without logging in. Nothing was missing from the data.
 * What was missing was a way to get that token off the guest's phone and
 * into the counter's hands without someone reading thirty-two characters
 * out loud.
 *
 * So this class adds no column and mints nothing. It is the two
 * translations a QR needs: the token as a scanner emits it, and a scanned
 * string back to the token a query can use.
 *
 * ---- Why a scan can never be mistaken for a meal coupon ---------------
 *
 * The counter panel has one box and one camera, and they already take
 * meal-coupon codes. Those are eight characters from an alphabet with no
 * 0, 1, I, L, O or U in it (MenuOrderCoupon::ALPHABET). An order code is
 * thirty-two hex characters. The two can never collide, because they are
 * never the same length -- which is the whole reason the counter can tell
 * what it is holding from the string alone, with no prefix, no marker
 * character, and nothing for a guest to get wrong.
 *
 * That is a property worth stating out loud rather than leaving implied,
 * so there is a test that asserts the lengths differ and will fail the day
 * somebody changes either one.
 *
 * ---- Scanned, not typed -----------------------------------------------
 *
 * Thirty-two characters is not a thing anyone types, and this class does
 * not pretend otherwise. When the camera will not focus, the fallback is
 * not this code: it is the token number the guest is already shown, big,
 * on the same screen, and the orders board that searches on it. A code
 * that is awkward to type is fine as long as something else is not.
 */
class MenuOrderCode
{
    /** A UUID with its hyphens taken out. */
    public const LENGTH = 32;

    /**
     * The string an order's QR carries.
     *
     * Compact and upper case: four characters shorter than the hyphenated
     * form, which is four characters of QR a counter phone does not have
     * to resolve, and identical to what normalize() produces from a scan
     * so the two ends cannot drift.
     */
    public static function of(?string $publicToken): string
    {
        $compact = self::normalize($publicToken);

        return $compact;
    }

    /**
     * A scanned or typed string, reduced to the form we compare.
     *
     * Deliberately forgiving about hyphens, spaces and case: a decoder may
     * hand back the hyphenated UUID, somebody may paste it out of a URL,
     * and none of that should be the difference between a lunch being
     * handed over and not. Returns '' for anything that is not an order
     * code, so a caller can branch on the empty string.
     */
    public static function normalize(mixed $raw): string
    {
        if (! is_string($raw)) {
            return '';
        }

        $compact = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '');

        return self::isOne($compact) ? $compact : '';
    }

    /** Is this normalized string an order code rather than a coupon? */
    public static function isOne(string $normalized): bool
    {
        return strlen($normalized) === self::LENGTH
            && preg_match('/^[0-9A-F]{'.self::LENGTH.'}$/', $normalized) === 1;
    }

    /**
     * Back to the exact value stored in `public_token`.
     *
     * The column holds what Str::uuid() produced: lower case, hyphenated
     * 8-4-4-4-12. Rebuilding that canonical form means the lookup is a
     * plain indexed equality check rather than a REPLACE() across every
     * row in the table, which on a busy Saturday is the difference between
     * a scan that feels instant and one that does not.
     *
     * Returns '' when the input is not an order code, so a caller never
     * ends up querying with a half-built value.
     */
    public static function toToken(mixed $raw): string
    {
        $compact = self::normalize($raw);
        if ($compact === '') {
            return '';
        }

        $lower = strtolower($compact);

        return substr($lower, 0, 8).'-'
            .substr($lower, 8, 4).'-'
            .substr($lower, 12, 4).'-'
            .substr($lower, 16, 4).'-'
            .substr($lower, 20, 12);
    }
}
