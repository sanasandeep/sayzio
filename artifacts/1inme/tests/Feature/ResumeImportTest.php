<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\ResumeSectionItem;
use App\Modules\User\Models\SocialAccountConnection;
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\OpenAiService;
use App\Services\Resume\ResumeImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

/**
 * End-to-end coverage for the resume importer parsers.
 *
 * The importer fans out into three independent pipelines that the
 * Review & Merge UI then commits via applyMerge(). Each path has its
 * own quirks worth pinning down:
 *
 *   1. importFromUpload — DOCX and PDF go through different extraction
 *      libraries (PhpOffice\PhpWord vs Smalot\PdfParser) but must
 *      converge on the same "candidates" shape. We assert the heuristic
 *      header / experience / skills parser fires for both formats so a
 *      regression in either extractor surfaces immediately.
 *   2. importFromBiolink — pulls from three sources (social account
 *      connections, creator posts, biolink blocks). We seed one of
 *      each and assert both the type mapping (links vs projects) and
 *      the URL-validation gate that drops malformed biolink blocks.
 *   3. applyMerge — the only path that mutates the live resume. We
 *      cover replace/append/skip for header + summary plus the integer
 *      indexing into candidate items, including the sanitizer rejecting
 *      experience entries missing company/role.
 *
 * OpenAiService is mocked so the AI fallback path can never fire — the
 * heuristic parser must produce items on its own from the fixtures.
 */
class ResumeImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Belt and braces: the AI fallback in parseResumeText() only
        // fires when heuristics produce nothing AND the engine is
        // enabled AND a key is set. Force-disable here so even a bug
        // that shorts the heuristic still can't reach the network.
        AiEngineSettings::setEnabled(false);
        $this->app->instance(OpenAiService::class, Mockery::mock(OpenAiService::class));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeUser(string $tag = 'r'): User
    {
        return User::create([
            'name'     => $tag . ' ' . Str::random(4),
            'email'    => $tag . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
    }

    private function service(): ResumeImportService
    {
        return $this->app->make(ResumeImportService::class);
    }

    /**
     * Resume body shared by the DOCX + PDF fixtures so both formats
     * are asserted against the same expected parse.
     */
    private function fixtureLines(): array
    {
        return [
            'Jane Doe',
            'jane.doe@example.com',
            '+1 555-123-4567',
            '',
            'Summary',
            'Experienced software engineer with a focus on backend systems.',
            '',
            'Experience',
            'Senior Engineer at Acme Corp',
            '2020 - Present',
            '- Led the platform team',
            '- Shipped the v2 rewrite',
            '',
            'Skills',
            'PHP, Laravel, PostgreSQL, Redis',
        ];
    }

    public function test_import_from_upload_parses_docx(): void
    {
        $user = $this->makeUser('docx');

        $word = new PhpWord();
        $section = $word->addSection();
        foreach ($this->fixtureLines() as $line) {
            // PhpWord drops fully-empty Text elements during read, so
            // emit a non-breaking sentinel that the extractor still
            // surfaces as a blank line for the section splitter.
            $section->addText($line === '' ? ' ' : $line);
        }
        $tmp = tempnam(sys_get_temp_dir(), 'resume_') . '.docx';
        IOFactory::createWriter($word, 'Word2007')->save($tmp);

        $upload = new UploadedFile(
            $tmp,
            'resume.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true
        );

        $candidates = $this->service()->importFromUpload($user, $upload);

        $this->assertSame('file', $candidates['source']);
        $this->assertArrayHasKey('file_id', $candidates);
        $this->assertSame('Jane Doe', $candidates['header']['name'] ?? null);
        $this->assertSame('jane.doe@example.com', $candidates['header']['email'] ?? null);
        $this->assertNotEmpty($candidates['summary']);

        $exp = array_values(array_filter($candidates['items'],
            fn ($i) => $i['section_type'] === 'experience'));
        $this->assertNotEmpty($exp, 'expected at least one experience candidate');
        $this->assertSame('Senior Engineer', $exp[0]['data']['role']);
        $this->assertSame('Acme Corp', $exp[0]['data']['company']);
        $this->assertSame('2020-01', $exp[0]['data']['start_date']);
        $this->assertTrue($exp[0]['data']['is_current'] ?? false);

        $skills = array_values(array_filter($candidates['items'],
            fn ($i) => $i['section_type'] === 'skills'));
        $this->assertGreaterThanOrEqual(4, count($skills));
        $this->assertSame('PHP', $skills[0]['data']['name']);
    }

    public function test_import_from_upload_parses_pdf(): void
    {
        $user = $this->makeUser('pdf');

        // Render fixture lines through dompdf so we exercise the real
        // PDF extractor (Smalot\PdfParser) end to end.
        $html = '<html><body style="font-family: DejaVu Sans, sans-serif;">';
        foreach ($this->fixtureLines() as $line) {
            $html .= '<div>' . ($line === '' ? '&nbsp;' : htmlspecialchars($line)) . '</div>';
        }
        $html .= '</body></html>';

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();
        $tmp = tempnam(sys_get_temp_dir(), 'resume_') . '.pdf';
        file_put_contents($tmp, $dompdf->output());

        $upload = new UploadedFile($tmp, 'resume.pdf', 'application/pdf', null, true);

        $candidates = $this->service()->importFromUpload($user, $upload);

        $this->assertSame('file', $candidates['source']);
        $this->assertSame('jane.doe@example.com', $candidates['header']['email'] ?? null);

        // Experience block should yield at least one entry — dompdf
        // can re-flow whitespace, so we don't pin the role/company
        // strings exactly, just that an entry made it through.
        $exp = array_values(array_filter($candidates['items'],
            fn ($i) => $i['section_type'] === 'experience'));
        $this->assertNotEmpty($exp, 'expected at least one experience candidate from PDF');

        $skills = array_values(array_filter($candidates['items'],
            fn ($i) => $i['section_type'] === 'skills'));
        $this->assertNotEmpty($skills, 'expected at least one skill candidate from PDF');
    }

    public function test_import_from_biolink_collects_socials_posts_and_blocks(): void
    {
        $user = $this->makeUser('bio');
        $user->forceFill([
            'handle' => 'janedoe' . $user->id,
            'bio'    => 'Backend engineer turned indie hacker.',
        ])->save();

        // Social → Links candidate.
        SocialAccountConnection::create([
            'user_id'        => $user->id,
            'platform'       => 'twitter',
            'handle'         => 'janedoe',
            'profile_url'    => 'https://twitter.com/janedoe',
            'follower_count' => 12500,
        ]);

        // Creator post → Projects candidate.
        CreatorPost::create([
            'user_id'      => $user->id,
            'title'        => 'Launching a new tool',
            'body'         => 'I just shipped a new tool for indie devs.',
            'published_at' => now()->subDay(),
        ]);

        // Primary biolink + two blocks: a link block (kept) and a
        // product block with no URL (still kept — URL is optional for
        // products) plus a malformed link block (URL invalid → dropped).
        $bio = Link::create([
            'user_id'   => $user->id,
            'type'      => 'biolink',
            'alias'     => 'janedoe-bio-' . $user->id,
            'title'     => 'Jane Doe',
            'is_active' => true,
        ]);
        BiolinkBlock::create([
            'link_id'    => $bio->id,
            'type'       => 'link',
            'is_active'  => true,
            'sort_order' => 1,
            'settings'   => ['url' => 'https://example.com/blog', 'title' => 'My Blog'],
        ]);
        BiolinkBlock::create([
            'link_id'    => $bio->id,
            'type'       => 'product',
            'is_active'  => true,
            'sort_order' => 2,
            'settings'   => [
                'title'       => 'Indie Toolkit',
                'description' => 'A bundle of dev tools.',
                'url'         => 'https://example.com/toolkit',
            ],
        ]);
        BiolinkBlock::create([
            'link_id'    => $bio->id,
            'type'       => 'link',
            'is_active'  => true,
            'sort_order' => 3,
            // Malformed: URL fails filter_var so this entry must drop.
            'settings'   => ['url' => 'not-a-real-url', 'title' => 'Broken'],
        ]);

        $candidates = $this->service()->importFromBiolink($user);

        $this->assertSame('biolink', $candidates['source']);
        $this->assertSame($user->email, $candidates['header']['email'] ?? null);
        $this->assertSame($user->name, $candidates['header']['name'] ?? null);
        $this->assertNotEmpty($candidates['header']['website'] ?? '');
        $this->assertSame('Backend engineer turned indie hacker.', $candidates['summary']);

        $links = array_values(array_filter($candidates['items'],
            fn ($i) => $i['section_type'] === 'links'));
        // Twitter social + "My Blog" block; the broken block must not
        // appear because filter_var rejects "not-a-real-url".
        $this->assertCount(2, $links);
        $labels = array_column(array_column($links, 'data'), 'label');
        $this->assertContains('My Blog', $labels);
        // Twitter's PLATFORM_META label is "X" (post-rebrand).
        $this->assertStringContainsString('X (@janedoe)', implode(' ', $labels));
        $this->assertStringContainsString('12.5K followers', implode(' ', $labels));

        $projects = array_values(array_filter($candidates['items'],
            fn ($i) => $i['section_type'] === 'projects'));
        // 1 creator post + 1 product block.
        $this->assertCount(2, $projects);
        $names = array_column(array_column($projects, 'data'), 'name');
        $this->assertContains('Launching a new tool', $names);
        $this->assertContains('Indie Toolkit', $names);
    }

    public function test_import_from_biolink_returns_notes_when_empty(): void
    {
        $user = $this->makeUser('empty');
        $candidates = $this->service()->importFromBiolink($user);
        $this->assertSame('biolink', $candidates['source']);
        $this->assertSame([], $candidates['items']);
        $this->assertNotEmpty($candidates['notes']);
    }

    public function test_apply_merge_replaces_header_and_summary_and_commits_items(): void
    {
        $user = $this->makeUser('merge');
        $resume = $user->ensureResume();

        $candidates = [
            'header'  => [
                'name'    => 'Jane Doe',
                'email'   => 'jane@example.com',
                'website' => 'https://janedoe.dev',
            ],
            'summary' => 'Backend engineer.',
            'items'   => [
                // Index 0 is intentionally a placeholder we don't pick:
                // the controller serialises picks through array_filter
                // which would drop a literal `0`, so production callers
                // also avoid index 0. Mirror that here.
                ['section_type' => 'links', 'data' => ['label' => 'noop', 'url' => 'https://x']],
                ['section_type' => 'experience', 'data' => [
                    'role' => 'Senior Engineer', 'company' => 'Acme',
                    'start_date' => '2020-01', 'end_date' => '2022-12',
                ]],
                // Missing both company AND role → sanitizer drops it.
                ['section_type' => 'experience', 'data' => [
                    'description' => 'No company, no role',
                ]],
                ['section_type' => 'skills', 'data' => ['name' => 'PHP', 'level' => 4]],
            ],
        ];

        $picks = [
            'header'  => ['mode' => 'replace', 'fields' => ['name', 'email', 'website']],
            'summary' => ['mode' => 'replace'],
            'items'   => [1, 2, 3],
        ];

        $result = $this->service()->applyMerge($user, $candidates, $picks);

        $this->assertSame(3, $result['changed']['header_fields']);
        $this->assertTrue($result['changed']['summary']);
        // Item index 1 is silently dropped by sanitizer (no company/role).
        $this->assertSame(2, $result['changed']['items']);

        $sections = $resume->fresh()->getMergedSections();
        $this->assertSame('Jane Doe', $sections['header']['name']);
        $this->assertSame('jane@example.com', $sections['header']['email']);
        $this->assertSame('https://janedoe.dev', $sections['header']['website']);
        $this->assertSame('Backend engineer.', $sections['summary']);

        $this->assertSame(1, ResumeSectionItem::where('resume_id', $resume->id)
            ->where('section_type', 'experience')->count());
        $this->assertSame(1, ResumeSectionItem::where('resume_id', $resume->id)
            ->where('section_type', 'skills')->count());
    }

    public function test_apply_merge_skip_mode_leaves_existing_data_untouched(): void
    {
        $user = $this->makeUser('skip');
        $resume = $user->ensureResume();
        $existing = $resume->getMergedSections();
        $existing['header']['name'] = 'Original Name';
        $existing['summary'] = 'Original summary.';
        $resume->update(['sections' => $existing]);

        $candidates = [
            'header'  => ['name' => 'Should Not Win'],
            'summary' => 'Should not win either.',
            'items'   => [],
        ];
        $picks = [
            'header'  => ['mode' => 'skip', 'fields' => ['name']],
            'summary' => ['mode' => 'skip'],
            'items'   => [],
        ];

        $result = $this->service()->applyMerge($user, $candidates, $picks);

        $this->assertSame(0, $result['changed']['header_fields']);
        $this->assertFalse($result['changed']['summary']);

        $sections = $resume->fresh()->getMergedSections();
        $this->assertSame('Original Name', $sections['header']['name']);
        $this->assertSame('Original summary.', $sections['summary']);
    }

    public function test_apply_merge_append_mode_concatenates_header_and_summary(): void
    {
        $user = $this->makeUser('append');
        $resume = $user->ensureResume();
        $existing = $resume->getMergedSections();
        $existing['header']['name'] = 'Jane';
        $existing['summary'] = 'First paragraph.';
        $resume->update(['sections' => $existing]);

        $candidates = [
            'header'  => ['name' => 'Jane Doe'],
            'summary' => 'Second paragraph.',
            'items'   => [],
        ];
        $picks = [
            'header'  => ['mode' => 'append', 'fields' => ['name']],
            'summary' => ['mode' => 'append'],
            'items'   => [],
        ];

        $this->service()->applyMerge($user, $candidates, $picks);

        $sections = $resume->fresh()->getMergedSections();
        $this->assertSame('Jane / Jane Doe', $sections['header']['name']);
        $this->assertStringContainsString('First paragraph.', $sections['summary']);
        $this->assertStringContainsString('Second paragraph.', $sections['summary']);
    }
}
