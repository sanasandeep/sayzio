<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Services\Resume\ResumeImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Sana, 2026-09-23: "Parser not working correctly... combining multiple
 * thing in same point. ATS friendly resumes should work".
 *
 * Both halves of that were one line of code. The experience parser split
 * entries on BLANK LINES -- and text pulled out of a PDF or a .docx very
 * often has none. Seven jobs arrived as one entry: line one became the
 * role, line two became the company, and the entire rest of the career
 * became that first job's description.
 *
 * The second half is the part worth pausing on. An ATS-friendly resume
 * is plain, single-column, tightly set, no tables and no decorative
 * spacing -- which is to say, no blank lines. So the parser failed worst
 * on exactly the resumes it is supposed to handle best, and worked fine
 * on the prettily-spaced ones nobody sends to an applicant tracker.
 *
 * Every fixture below is a layout a real extractor produces. None of
 * them has a blank line between entries, because that is the point.
 */
class TheResumeParserKeepsOneJobPerEntryTest extends TestCase
{
    use RefreshDatabase;

    private ResumeImportService $svc;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc  = app(ResumeImportService::class);
        $this->user = User::factory()->create(['onboarded_at' => now()]);
    }

    /** @return array<int, array<string, mixed>> */
    private function itemsOfType(string $text, string $type): array
    {
        $parsed = $this->svc->parseResumeText($this->user, $text, false);

        return array_values(array_map(
            fn ($i) => $i['data'],
            array_filter($parsed['items'] ?? [], fn ($i) => $i['section_type'] === $type)
        ));
    }

    // ===== 1. The shape Sana's own resume is in =====

    /**
     * Role, company, dates, bullets -- four times, no blank lines. This
     * used to import as ONE job whose description was everyone else's.
     */
    public function test_four_jobs_with_no_blank_lines_import_as_four_jobs(): void
    {
        $text = <<<TXT
        PROFESSIONAL EXPERIENCE
        Founder and CEO
        Sayzio
        Jan 2023 - Present
        - Built the link management platform from zero to 375,000 users
        - Ran product, engineering and support single handed for the first year
        Product Delivery and Implementation
        Ustay
        Mar 2021 - Dec 2022
        - Shipped the booking stack across four markets
        Associate Firmware Engineer
        Tesco HSC
        Jun 2018 - Feb 2021
        - Wrote the device layer for in-store scanners
        Business Operations and Leadership
        Myslate
        Jan 2017 - May 2018
        - Owned operations for a team of twelve
        TXT;

        $jobs = $this->itemsOfType($text, 'experience');

        $this->assertCount(4, $jobs, 'four jobs, four entries -- this used to be one');
        $this->assertSame('Founder and CEO', $jobs[0]['role']);
        $this->assertSame('Sayzio', $jobs[0]['company']);
        $this->assertSame('Associate Firmware Engineer', $jobs[2]['role']);
        $this->assertSame('Tesco HSC', $jobs[2]['company']);
    }

    /**
     * And each job keeps only its own bullets. The merge bug is only half
     * fixed if entry one still carries entry two's accomplishments.
     */
    public function test_each_job_keeps_only_its_own_bullets(): void
    {
        $text = <<<TXT
        EXPERIENCE
        Founder and CEO
        Sayzio
        Jan 2023 - Present
        - Built the platform
        Associate Firmware Engineer
        Tesco HSC
        Jun 2018 - Feb 2021
        - Wrote the device layer
        TXT;

        $jobs = $this->itemsOfType($text, 'experience');

        $this->assertStringContainsString('Built the platform', $jobs[0]['description']);
        $this->assertStringNotContainsString('device layer', $jobs[0]['description'],
            'the second job leaked into the first -- this is the reported bug');
        $this->assertStringContainsString('device layer', $jobs[1]['description']);
    }

    /** The dates belong to their own job, not to the first one. */
    public function test_each_job_keeps_its_own_dates(): void
    {
        $text = <<<TXT
        EXPERIENCE
        Founder and CEO
        Sayzio
        Jan 2023 - Present
        - Built the platform
        Associate Firmware Engineer
        Tesco HSC
        Jun 2018 - Feb 2021
        - Wrote the device layer
        TXT;

        $jobs = $this->itemsOfType($text, 'experience');

        $this->assertSame('2023-01', $jobs[0]['start_date']);
        $this->assertTrue($jobs[0]['is_current']);
        $this->assertSame('2018-06', $jobs[1]['start_date']);
        $this->assertSame('2021-02', $jobs[1]['end_date']);
    }

    // ===== 2. Layouts with no bullets at all =====

    /**
     * A two-column template extracts as "Role, Company" then the dates on
     * their own row. With no bullets, the only boundary signal is the
     * second date range.
     */
    public function test_jobs_separated_only_by_their_date_lines(): void
    {
        $text = <<<TXT
        WORK EXPERIENCE
        Senior Product Designer, Monzo
        Feb 2020 - Present
        Led the redesign of the account switching flow.
        Product Designer, Deliveroo
        Aug 2017 - Jan 2020
        Owned the rider app end to end.
        TXT;

        $jobs = $this->itemsOfType($text, 'experience');

        $this->assertCount(2, $jobs);
        $this->assertSame('Senior Product Designer', $jobs[0]['role']);
        $this->assertSame('Monzo', $jobs[0]['company']);
        $this->assertSame('Product Designer', $jobs[1]['role']);
        $this->assertSame('Deliveroo', $jobs[1]['company']);
    }

    /**
     * A date on its own line is a date, not the company. This is how most
     * two-column layouts extract, and it used to produce a job at a
     * company called "Feb 2020 - Present".
     */
    public function test_a_date_line_is_never_mistaken_for_the_company(): void
    {
        $text = <<<TXT
        EXPERIENCE
        Senior Product Designer
        Feb 2020 - Present
        Monzo
        Led the redesign.
        TXT;

        $jobs = $this->itemsOfType($text, 'experience');

        $this->assertCount(1, $jobs);
        $this->assertNotSame('Feb 2020 - Present', $jobs[0]['company'] ?? '');
        $this->assertSame('Monzo', $jobs[0]['company'] ?? '');
    }

    // ===== 3. Blank lines still win when they are there =====

    /**
     * A resume that IS spaced out is unambiguous, and nothing heuristic
     * reads it better than the author's own paragraphing. The old
     * behaviour has to survive.
     */
    public function test_a_spaced_out_resume_still_parses_by_its_spacing(): void
    {
        $text = "EXPERIENCE\n"
            ."Founder and CEO\nSayzio\nJan 2023 - Present\nBuilt the platform.\n"
            ."\n"
            ."Associate Firmware Engineer\nTesco HSC\nJun 2018 - Feb 2021\nWrote the device layer.\n";

        $jobs = $this->itemsOfType($text, 'experience');

        $this->assertCount(2, $jobs);
        $this->assertSame('Sayzio', $jobs[0]['company']);
    }

    // ===== 4. Bullets come out as bullets =====

    /**
     * The visible half of "combining multiple thing in same point": the
     * glyphs a PDF emits became one paragraph with dots in it. Each
     * accomplishment must survive as its own line.
     */
    public function test_bullet_glyphs_become_one_bullet_per_line(): void
    {
        $text = <<<TXT
        EXPERIENCE
        Founder and CEO
        Sayzio
        Jan 2023 - Present
        • Built the platform
        • Grew it to 375,000 users
        • Kept the lights on
        TXT;

        $jobs = $this->itemsOfType($text, 'experience');
        $desc = $jobs[0]['description'];

        $this->assertSame(
            ['- Built the platform', '- Grew it to 375,000 users', '- Kept the lights on'],
            explode("\n", $desc)
        );
    }

    // ===== 5. Headings, which is the other half of "ATS should work" =====

    /**
     * The heading had to match an alias EXACTLY once lowercased. So a
     * trailing colon, a rule of box characters, a leading bullet or a
     * parenthetical all meant the section was never found -- and when no
     * heading matched, the import produced nothing whatsoever.
     */
    #[DataProvider('decoratedHeadings')]
    public function test_a_decorated_heading_still_names_its_section(string $heading): void
    {
        $text = $heading."\n"
            ."Founder and CEO\nSayzio\nJan 2023 - Present\n- Built the platform\n";

        $this->assertCount(1, $this->itemsOfType($text, 'experience'),
            'this heading was not recognised: '.$heading);
    }

    /** @return array<string, array<int, string>> */
    public static function decoratedHeadings(): array
    {
        return [
            'plain'            => ['EXPERIENCE'],
            'trailing colon'   => ['WORK EXPERIENCE:'],
            'box rule'         => ['Experience ────────────────'],
            'leading bullet'   => ['• Experience'],
            'numbered'         => ['2. Professional Experience'],
            'parenthetical'    => ['EXPERIENCE (2017 - 2024)'],
            'underscored'      => ['EMPLOYMENT HISTORY ____'],
            'trailing spaces'  => ['   Career History   '],
        ];
    }

    /**
     * And the shape guard has to hold, or every resume gets cut in half
     * by its own prose.
     */
    public function test_a_sentence_that_starts_with_a_heading_word_is_not_a_heading(): void
    {
        $text = <<<TXT
        EXPERIENCE
        Platform Engineer
        Acme
        Jan 2020 - Present
        - Experience with Kubernetes and Terraform across three product teams
        - Skills in Go, Rust and a great deal of YAML
        TXT;

        $jobs = $this->itemsOfType($text, 'experience');

        $this->assertCount(1, $jobs);
        $this->assertStringContainsString('Kubernetes', $jobs[0]['description'],
            'a bullet beginning with "Experience" must stay a bullet');
        $this->assertSame([], $this->itemsOfType($text, 'skills'),
            'and must not open a Skills section mid-job');
    }

    // ===== 6. Education gets the same treatment =====

    public function test_two_degrees_with_no_blank_line_import_as_two(): void
    {
        $text = <<<TXT
        EDUCATION
        University of Southern California
        M.S. Computer Science
        2015 - 2017
        Sathyabama University
        B.E. Electronics
        2011 - 2015
        TXT;

        $schools = $this->itemsOfType($text, 'education');

        $this->assertCount(2, $schools);
        $this->assertSame('University of Southern California', $schools[0]['school']);
        $this->assertSame('M.S. Computer Science', $schools[0]['degree']);
        $this->assertSame('Sathyabama University', $schools[1]['school']);
    }

    /** School and degree on one line is as common as on two. */
    public function test_a_school_and_degree_on_one_line_split_apart(): void
    {
        $text = "EDUCATION\nSathyabama University - B.E. Electronics\n2011 - 2015\n";

        $schools = $this->itemsOfType($text, 'education');

        $this->assertCount(1, $schools);
        $this->assertSame('Sathyabama University', $schools[0]['school']);
        $this->assertSame('B.E. Electronics', $schools[0]['degree']);
    }

    // ===== 7. What must not be imported =====

    /** A stray line is not a job; an empty card is worse than nothing. */
    public function test_a_fragment_with_no_role_and_no_company_is_dropped(): void
    {
        $text = "EXPERIENCE\n- \n- \n";

        $this->assertSame([], $this->itemsOfType($text, 'experience'));
    }
}
