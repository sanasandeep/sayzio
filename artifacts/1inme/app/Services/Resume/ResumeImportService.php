<?php

namespace App\Services\Resume;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\Resume;
use App\Modules\User\Models\ResumeSectionItem;
use App\Modules\User\Models\SocialAccountConnection;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\OpenAiService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Cross-method engine that turns external resume-shaped data
 * (uploaded PDF/DOCX, LinkedIn export, the user's own bio link,
 * or a short AI prompt) into a normalised "candidate" payload
 * the editor's Review & Merge screen can consume.
 *
 * The output shape is always:
 *   [
 *     'header'  => ['name' => …, 'email' => …, …],   // optional keys
 *     'summary' => '…',                                // optional
 *     'items'   => [
 *        ['section_type' => 'experience', 'data' => [...]],
 *        ['section_type' => 'education',  'data' => [...]],
 *        …
 *     ],
 *     'source'  => 'file' | 'linkedin' | 'biolink' | 'ai',
 *     'notes'   => string|null,                        // user-facing hints
 *   ]
 *
 * Items are NOT persisted here — the controller's `merge()` endpoint
 * commits them through the same validators the manual editor uses.
 */
class ResumeImportService
{
    public function __construct(protected OpenAiService $openai) {}

    /** Section keys the heuristic + AI parser may produce. */
    public const SUPPORTED_TYPES = [
        'experience', 'education', 'skills', 'projects',
        'certifications', 'awards', 'languages', 'links',
    ];

    // ───────── File / LinkedIn PDF + DOCX upload ─────────

    /**
     * Store the upload in the user-files vault, extract text, parse it
     * into candidates. `$linkedinHint` flips on LinkedIn-specific
     * heuristics (different headings, contact-info block layout).
     */
    public function importFromUpload(User $user, UploadedFile $file, bool $linkedinHint = false, ?string $linkedinUrl = null): array
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        if (!in_array($ext, ['pdf', 'doc', 'docx'], true)) {
            throw new RuntimeException('Only PDF and DOCX files are supported.');
        }

        // Vault storage with the same SSRF/permission/quota protections
        // as every other upload path. We allow doc/docx/pdf which are
        // already in the document allowlist.
        $stored = UserFile::createFromUpload($file, $user);

        try {
            $disk = $stored->disk === 's3' ? 's3' : 'user_files';
            $isRemote = config("filesystems.disks.{$disk}.driver") === 's3';
            $absPath = $isRemote
                ? Storage::disk($disk)->temporaryUrl($stored->path, now()->addMinutes(5))
                : Storage::disk($disk)->path($stored->path);

            $text = $this->extractText($absPath, $ext, $isRemote);
        } catch (\Throwable $e) {
            // Don't keep an uploaded blob in the vault we couldn't read —
            // the user gets back nothing useful from it anyway.
            try { $stored->deleteFile(); } catch (\Throwable $ignored) {}
            throw new RuntimeException('Could not read this file: ' . $e->getMessage());
        }

        $candidates = $this->parseResumeText($user, $text, $linkedinHint);
        $candidates['source']   = $linkedinHint ? 'linkedin' : 'file';
        $candidates['file_id']  = $stored->id;
        if ($linkedinUrl && empty($candidates['header']['website'])) {
            $candidates['header']['website'] = $linkedinUrl;
        }
        return $candidates;
    }

    /**
     * LinkedIn URL-only mode: we can't scrape LinkedIn, so we return an
     * empty candidate set whose `notes` field tells the user to also
     * upload their exported PDF. The URL itself becomes a candidate
     * "Links" entry so it isn't lost.
     */
    public function importFromLinkedinUrl(User $user, string $url): array
    {
        return [
            'header'  => [],
            'summary' => '',
            'items'   => [[
                'section_type' => 'links',
                'data' => ['label' => 'LinkedIn', 'url' => $url, 'icon' => 'fab fa-linkedin-in'],
            ]],
            'source'  => 'linkedin',
            'notes'   => 'LinkedIn does not let us read profiles directly. To pull in your experience, education, and skills, also upload your "Save to PDF" export from your LinkedIn profile.',
        ];
    }

    // ───────── Bio link import ─────────

    /**
     * Build candidates from the signed-in user's own Sayzio bio link
     * universe: profile fields, social account connections (with
     * follower counts), creator posts (mapped to portfolio projects),
     * and link-style biolink blocks.
     */
    public function importFromBiolink(User $user): array
    {
        $items = [];

        // Social account connections → Links section, plus a folded
        // skills hint when the platform has follower counts.
        $socials = SocialAccountConnection::where('user_id', $user->id)->get();
        foreach ($socials as $sa) {
            $url = $sa->resolvedProfileUrl();
            if (!$url) continue;
            $label = SocialAccountConnection::platformLabel($sa->platform);
            if ($sa->handle) $label .= ' (@' . ltrim((string) $sa->handle, '@') . ')';
            if ($sa->follower_count !== null && $sa->follower_count > 0) {
                $label .= ' — ' . SocialAccountConnection::formatCount((int) $sa->follower_count) . ' followers';
            }
            $items[] = [
                'section_type' => 'links',
                'data' => [
                    'label' => mb_substr($label, 0, 80),
                    'url'   => $url,
                    'icon'  => $sa->brandIcon(),
                ],
            ];
        }

        // Creator posts → Projects (portfolio).
        $posts = CreatorPost::where('user_id', $user->id)
            ->whereNotNull('published_at')
            ->latest('published_at')
            ->limit(20)
            ->get();
        foreach ($posts as $p) {
            $title = $p->title ?: mb_substr((string) $p->body, 0, 80);
            if (trim((string) $title) === '') continue;
            $items[] = [
                'section_type' => 'projects',
                'data' => [
                    'name'        => mb_substr($title, 0, 160),
                    'role'        => 'Creator post',
                    'description' => mb_substr((string) $p->body, 0, 1500),
                    'start_date'  => optional($p->published_at)->format('Y-m'),
                    'end_date'    => optional($p->published_at)->format('Y-m'),
                ],
            ];
        }

        // Biolink blocks: link / link_big / external_item → Links;
        // service / product → Projects.
        $bio = $user->primaryBiolink();
        if ($bio) {
            $blocks = BiolinkBlock::where('link_id', $bio->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();
            foreach ($blocks as $b) {
                $s = $b->settings ?? [];
                if (in_array($b->type, ['link', 'link_big', 'external_item', 'cta_button'], true)) {
                    $url   = trim((string) ($s['url'] ?? $s['link'] ?? ''));
                    $label = trim((string) ($s['title'] ?? $s['label'] ?? $s['text'] ?? ''));
                    if ($url !== '' && $label !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
                        $items[] = [
                            'section_type' => 'links',
                            'data' => [
                                'label' => mb_substr($label, 0, 80),
                                'url'   => $url,
                                'icon'  => '',
                            ],
                        ];
                    }
                } elseif (in_array($b->type, ['product', 'service'], true)) {
                    $name = trim((string) ($s['title'] ?? $s['name'] ?? ''));
                    $desc = trim((string) ($s['description'] ?? $s['body'] ?? ''));
                    $url  = trim((string) ($s['url'] ?? $s['link'] ?? ''));
                    if ($name !== '') {
                        $items[] = [
                            'section_type' => 'projects',
                            'data' => array_filter([
                                'name'        => mb_substr($name, 0, 160),
                                'role'        => $b->type === 'service' ? 'Service' : 'Product',
                                'description' => mb_substr($desc, 0, 2000),
                                'url'         => filter_var($url, FILTER_VALIDATE_URL) ? $url : null,
                            ], fn ($v) => $v !== null && $v !== ''),
                        ];
                    }
                }
            }
        }

        $header = array_filter([
            'name'    => $user->name,
            'email'   => $user->email,
            'phone'   => $user->mobile,
            'website' => $bio ? url('/' . $user->publicHandle()) : null,
        ], fn ($v) => is_string($v) && trim($v) !== '');

        return [
            'header'  => $header,
            'summary' => trim((string) $user->bio),
            'items'   => $items,
            'source'  => 'biolink',
            'notes'   => $items ? null : 'No Link in Bio content found yet. Add posts, social accounts, or Link in Bio blocks first.',
        ];
    }

    // ───────── AI-assisted drafting ─────────

    /**
     * Generate a draft summary / experience bullets / skills list from
     * a short user prompt. Section selection lets the user re-run for
     * just one part. Charges credits via the dedicated `resume_import`
     * feature map so admins can tune the model independently of Coach.
     */
    public function importFromAi(User $user, string $prompt, array $sections, array $context = []): array
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            throw new RuntimeException('Please describe yourself in a sentence or two first.');
        }
        $sections = array_values(array_intersect(
            $sections ?: ['summary', 'experience', 'skills'],
            ['summary', 'experience', 'skills', 'projects']
        ));
        if (!$sections) {
            throw new RuntimeException('Pick at least one section to draft.');
        }

        // The prompt box now takes 10,000 characters -- a whole career
        // rather than two paragraphs -- so the list cap and the output
        // budget below had to move with it. Leaving them where they were
        // is what makes a model weld three jobs into one entry: it is
        // asked for everything and given room for a third of it.
        $schemaHint = "Return strict JSON with optional keys: " .
            "summary (string), experience (array of {company,role,location,start_date YYYY-MM,end_date YYYY-MM,is_current,description}), " .
            "skills (array of {name, level 1-5, group}), " .
            "projects (array of {name,role,url,description}). " .
            "Only include the keys: " . implode(', ', $sections) . ". " .
            "Use empty strings rather than nulls. Limit each list to 15 items. " .
            "ONE ENTRY PER ROLE: never merge two jobs, two companies or two date ranges " .
            "into a single experience entry, and never put a second role inside a " .
            "description. If a role is unclear, emit it on its own with whatever is known. " .
            "Write each description as newline-separated bullets prefixed with \"- \", " .
            "one accomplishment per bullet, so the result stays readable to an ATS.";

        $contextLine = '';
        if (!empty($context['header']['name'])) $contextLine .= "Name: " . $context['header']['name'] . "\n";
        if (!empty($context['summary']))        $contextLine .= "Existing summary: " . mb_substr($context['summary'], 0, 400) . "\n";

        $messages = [
            ['role' => 'system', 'content' =>
                "You are a resume writing assistant. Be concise, action-oriented and truthful. " .
                "Never invent specific employers, dates, or metrics that the user didn't provide. " .
                "Output JSON only — no markdown, no commentary. " . $schemaHint],
            ['role' => 'user', 'content' =>
                ($contextLine ? "Context:\n{$contextLine}\n" : '') .
                "User prompt:\n{$prompt}"],
        ];

        // Output budget scales with how much was actually written, so a
        // long prompt is not silently truncated into one merged entry --
        // while a two-line prompt still costs what it always did.
        $model  = AiEngineSettings::featureModel('resume_import', $user);
        $result = $this->openai->chat($user, $model, $messages, [
            'temperature'     => 0.5,
            'max_tokens'      => max(1200, min(4000, (int) ceil(mb_strlen($prompt) / 2) + 800)),
            'response_format' => ['type' => 'json_object'],
            'feature'         => 'resume_import',
            'reason'          => 'Resume AI draft',
        ]);

        $parsed = json_decode((string) $result['content'], true);
        if (!is_array($parsed)) {
            throw new RuntimeException('The assistant returned an unexpected response. Try rephrasing your prompt.');
        }

        return [
            'header'  => [],
            'summary' => isset($parsed['summary']) && is_string($parsed['summary'])
                ? trim($parsed['summary']) : '',
            'items'   => $this->candidatesFromAiPayload($parsed),
            'source'  => 'ai',
            'notes'   => null,
            'credits_spent' => (int) ($result['credits_spent'] ?? 0),
        ];
    }

    // ───────── Merge into the live resume ─────────

    /**
     * Apply a curated subset of candidates to the user's resume.
     *
     * `$picks` is the client's confirmed selection:
     *   [
     *     'header'  => ['mode' => 'replace'|'skip'|'append', 'fields' => ['name','email',…]],
     *     'summary' => ['mode' => 'replace'|'skip'|'append'],
     *     'items'   => [int $candidateIndex, …],   // indexes into $candidates['items']
     *   ]
     *
     * Returns the count of fields/items committed plus the fresh resume.
     */
    public function applyMerge(User $user, array $candidates, array $picks): array
    {
        $resume = $user->ensureResume();
        $sections = $resume->getMergedSections();
        $changed = ['header_fields' => 0, 'summary' => false, 'items' => 0];

        // ── Header ──
        $hdrPick = $picks['header'] ?? [];
        $hdrMode = (string) ($hdrPick['mode'] ?? 'skip');
        $hdrFields = is_array($hdrPick['fields'] ?? null) ? $hdrPick['fields'] : [];
        if ($hdrMode !== 'skip' && !empty($candidates['header'])) {
            foreach ($candidates['header'] as $f => $v) {
                if (!in_array($f, $hdrFields, true)) continue;
                if (!array_key_exists($f, $sections['header'])) continue;
                $current = (string) ($sections['header'][$f] ?? '');
                $incoming = is_string($v) ? trim($v) : '';
                if ($incoming === '') continue;
                if ($hdrMode === 'replace' || $current === '') {
                    $sections['header'][$f] = $incoming;
                    $changed['header_fields']++;
                } elseif ($hdrMode === 'append' && $current !== $incoming) {
                    $sections['header'][$f] = trim($current . ' / ' . $incoming);
                    $changed['header_fields']++;
                }
            }
        }

        // ── Summary ──
        $sumMode = (string) ($picks['summary']['mode'] ?? 'skip');
        $incomingSum = isset($candidates['summary']) && is_string($candidates['summary'])
            ? trim($candidates['summary']) : '';
        if ($sumMode !== 'skip' && $incomingSum !== '') {
            $current = (string) ($sections['summary'] ?? '');
            if ($sumMode === 'replace' || $current === '') {
                $sections['summary'] = $incomingSum;
                $changed['summary'] = true;
            } elseif ($sumMode === 'append' && stripos($current, $incomingSum) === false) {
                $sections['summary'] = trim($current . "\n\n" . $incomingSum);
                $changed['summary'] = true;
            }
        }

        $resume->update(['sections' => $sections]);

        // ── List items ──
        $picked = array_values(array_filter(array_map('intval', $picks['items'] ?? [])));
        $picked = array_unique($picked);
        $byType = [];
        $allCandidates = $candidates['items'] ?? [];
        foreach ($picked as $idx) {
            $cand = $allCandidates[$idx] ?? null;
            if (!is_array($cand)) continue;
            $type = (string) ($cand['section_type'] ?? '');
            if (!ResumeSectionItem::isValidType($type) || $type === 'custom') continue;
            $data = $this->sanitizeItemData($type, (array) ($cand['data'] ?? []));
            if ($data === null) continue;
            $byType[$type] = ($byType[$type] ?? 0);
            $maxPos = (int) $resume->itemsOfType($type)->max('position');
            $resume->items()->create([
                'section_type' => $type,
                'position'     => $maxPos + $byType[$type] + 1,
                'data'         => $data,
            ]);
            $byType[$type]++;
            $changed['items']++;
        }

        return [
            'changed' => $changed,
            'resume'  => $resume->fresh('items'),
        ];
    }

    // ───────── Internals: text extraction ─────────

    protected function extractText(string $path, string $ext, bool $isRemote): string
    {
        // For S3 we download to a temp file once so both parsers can use
        // a normal local path; the file is unlinked in finally.
        $local = $path;
        $tmp   = null;
        if ($isRemote) {
            $tmp = tempnam(sys_get_temp_dir(), 'resume_');
            $bytes = @file_get_contents($path);
            if ($bytes === false) throw new RuntimeException('Could not download file from storage.');
            file_put_contents($tmp, $bytes);
            $local = $tmp;
        }

        try {
            if ($ext === 'pdf') {
                return $this->extractPdfText($local);
            }
            return $this->extractDocxText($local, $ext);
        } finally {
            if ($tmp) @unlink($tmp);
        }
    }

    protected function extractPdfText(string $path): string
    {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf    = $parser->parseFile($path);
            $text   = (string) $pdf->getText();
        } catch (\Throwable $e) {
            throw new RuntimeException('PDF parsing failed: ' . $e->getMessage());
        }
        $text = preg_replace("/[ \t]+\n/", "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        $text = trim((string) $text);
        if ($text === '') {
            throw new RuntimeException('This PDF appears to be image-only. Try uploading a text-based PDF or DOCX.');
        }
        return $text;
    }

    protected function extractDocxText(string $path, string $ext): string
    {
        $reader = $ext === 'doc' ? 'MsDoc' : 'Word2007';
        try {
            $doc = \PhpOffice\PhpWord\IOFactory::load($path, $reader);
        } catch (\Throwable $e) {
            throw new RuntimeException('Could not open the document: ' . $e->getMessage());
        }
        $lines = [];
        foreach ($doc->getSections() as $section) {
            foreach ($section->getElements() as $el) {
                $this->renderWordElement($el, $lines);
            }
        }
        $text = trim(implode("\n", $lines));
        if ($text === '') {
            throw new RuntimeException('No extractable text found in this document.');
        }
        return $text;
    }

    protected function renderWordElement($el, array &$lines): void
    {
        if ($el instanceof \PhpOffice\PhpWord\Element\Title) {
            $lines[] = (string) $el->getText();
            return;
        }
        if ($el instanceof \PhpOffice\PhpWord\Element\TextRun) {
            $buf = '';
            foreach ($el->getElements() as $sub) {
                if (method_exists($sub, 'getText')) $buf .= (string) $sub->getText();
            }
            if (trim($buf) !== '') $lines[] = $buf;
            return;
        }
        if ($el instanceof \PhpOffice\PhpWord\Element\Text) {
            $lines[] = (string) $el->getText();
            return;
        }
        if ($el instanceof \PhpOffice\PhpWord\Element\ListItem) {
            $lines[] = '• ' . (string) $el->getTextObject()->getText();
            return;
        }
        if (method_exists($el, 'getElements')) {
            foreach ($el->getElements() as $child) {
                $this->renderWordElement($child, $lines);
            }
        }
    }

    // ───────── Internals: heuristic + AI parsing ─────────

    /**
     * Run cheap regex heuristics first so users without an OpenAI key /
     * with the AI engine disabled still get a sensible parse, then ask
     * the AI to fill blanks when it's available.
     */
    public function parseResumeText(User $user, string $text, bool $linkedinHint): array
    {
        $candidates = [
            'header'  => $this->guessContact($text),
            'summary' => '',
            'items'   => [],
            'notes'   => null,
        ];

        $sections = $this->splitSections($text, $linkedinHint);
        $candidates['summary'] = $this->guessSummary($sections, $text);

        foreach ($sections as $kind => $body) {
            switch ($kind) {
                case 'experience':
                    foreach ($this->parseExperienceBlock($body) as $row) {
                        $candidates['items'][] = ['section_type' => 'experience', 'data' => $row];
                    }
                    break;
                case 'education':
                    foreach ($this->parseEducationBlock($body) as $row) {
                        $candidates['items'][] = ['section_type' => 'education', 'data' => $row];
                    }
                    break;
                case 'skills':
                    foreach ($this->parseSkillsBlock($body) as $row) {
                        $candidates['items'][] = ['section_type' => 'skills', 'data' => $row];
                    }
                    break;
                case 'certifications':
                    foreach ($this->parseSimpleListBlock($body) as $line) {
                        $candidates['items'][] = ['section_type' => 'certifications', 'data' => ['name' => $line]];
                    }
                    break;
                case 'languages':
                    foreach ($this->parseSimpleListBlock($body) as $line) {
                        $candidates['items'][] = ['section_type' => 'languages', 'data' => ['name' => mb_substr($line, 0, 80)]];
                    }
                    break;
            }
        }

        // AI fallback: only fire when we got essentially nothing AND the
        // engine is available. Keeps the heuristic path free for users
        // without AI credits.
        $heuristicEmpty = empty($candidates['items']) && $candidates['summary'] === '';
        if ($heuristicEmpty && AiEngineSettings::isEnabled() && AiEngineSettings::openAiKey()) {
            try {
                $aiCandidates = $this->aiStructure($user, $text);
                $candidates['summary'] = $candidates['summary'] ?: ($aiCandidates['summary'] ?? '');
                $candidates['items']   = array_merge($candidates['items'], $aiCandidates['items'] ?? []);
                if (!empty($aiCandidates['header'])) {
                    $candidates['header'] = array_merge($aiCandidates['header'], $candidates['header']);
                }
                $candidates['credits_spent'] = (int) ($aiCandidates['credits_spent'] ?? 0);
            } catch (\Throwable $e) {
                $candidates['notes'] = 'We extracted what we could; AI parsing was unavailable (' . $e->getMessage() . ').';
            }
        }

        if (empty($candidates['items']) && $candidates['summary'] === '' && empty($candidates['header'])) {
            $candidates['notes'] = $candidates['notes']
                ?: 'We could not detect any resume sections in this file. You can still paste content into the editor manually.';
        }

        return $candidates;
    }

    protected function guessContact(string $text): array
    {
        $head = mb_substr($text, 0, 1500);
        $out = [];
        if (preg_match('/[\w\.\-+]+@[\w\.\-]+\.[a-z]{2,}/i', $head, $m)) $out['email'] = $m[0];
        if (preg_match('/\+?\d[\d \-\(\)]{7,}\d/', $head, $m))           $out['phone'] = trim($m[0]);
        if (preg_match('/\bhttps?:\/\/[^\s<>"\']+/i', $head, $m))        $out['website'] = $m[0];

        // Name: first non-empty line if it looks like a personal name
        // (1–4 words, mostly alphabetic, no email/phone).
        foreach (preg_split('/\r?\n/', trim($head)) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (preg_match('/@|\d{3}|http/i', $line)) continue;
            $words = preg_split('/\s+/', $line);
            if (count($words) >= 1 && count($words) <= 4 && preg_match('/^[\p{L}\p{M}\.\'\-\s]+$/u', $line)) {
                $out['name'] = mb_substr($line, 0, 120);
            }
            break;
        }
        return $out;
    }

    /** Return [section_kind => body_text] keyed by canonical kind. */
    /**
     * Cut the text into its sections by their headings.
     *
     * This used to require a heading line to be EXACTLY one of the
     * aliases once lowercased -- so "EXPERIENCE" matched and
     * "PROFESSIONAL EXPERIENCE" matched, but "WORK EXPERIENCE:",
     * "Experience ────────", "• Experience" and "EXPERIENCE (8 YEARS)"
     * all did not. When nothing matched, every section came back empty
     * and the import produced nothing at all.
     *
     * A heading is now recognised by what a heading looks like: a short
     * line, no sentence punctuation, that begins with a known alias once
     * the decoration is stripped off it.
     *
     * @return array<string, string>
     */
    protected function splitSections(string $text, bool $linkedinHint): array
    {
        $headings = [
            'experience'     => ['experience', 'work experience', 'professional experience', 'employment',
                                 'employment history', 'work history', 'career', 'career history',
                                 'professional background', 'relevant experience'],
            'education'      => ['education', 'academic background', 'studies', 'academics',
                                 'education and training', 'qualifications'],
            'skills'         => ['skills', 'technical skills', 'core skills', 'competencies', 'technologies',
                                 'core competencies', 'key skills', 'areas of expertise', 'expertise'],
            'certifications' => ['certifications', 'certificates', 'licenses', 'licenses & certifications',
                                 'licenses and certifications', 'accreditations'],
            'languages'      => ['languages', 'language skills'],
            'projects'       => ['projects', 'portfolio', 'selected projects', 'key projects', 'personal projects'],
            'summary'        => ['summary', 'about', 'profile', 'objective', 'professional summary',
                                 'career summary', 'about me', 'personal statement'],
        ];

        $lines   = preg_split('/\r?\n/', $text);
        $current = null;
        $buckets = [];

        foreach ($lines as $line) {
            $matched = $this->headingKind(trim($line), $headings);

            if ($matched !== null) {
                $current = $matched;
                $buckets[$current] = $buckets[$current] ?? '';

                continue;
            }

            if ($current) {
                $buckets[$current] .= $line."\n";
            }
        }

        return array_map('trim', $buckets);
    }

    /**
     * Which section, if any, this line is the heading of.
     *
     * Deliberately strict about SHAPE and forgiving about decoration: a
     * heading is short, is not a sentence, and starts with a known word.
     * Being strict about shape is what stops a bullet reading "Experience
     * with Kubernetes and Terraform across three teams" from opening a
     * new section in the middle of someone's job description.
     *
     * @param  array<string, array<int, string>>  $headings
     */
    protected function headingKind(string $line, array $headings): ?string
    {
        if ($line === '') {
            return null;
        }

        // Strip leading bullets/numbers and trailing rules, colons and
        // parenthetical asides: "• 2. WORK EXPERIENCE (2015-2024) ────"
        // is a heading wearing four kinds of decoration.
        $clean = preg_replace('/^\s*(?:[•·▪◦‣∙*+]|[-–—]|\d+[.)])\s*/u', '', $line);
        $clean = preg_replace('/[\s:_=~.\-–—─━▬]+$/u', '', (string) $clean);
        $clean = preg_replace('/\s*\([^)]*\)\s*$/u', '', (string) $clean);
        $clean = trim((string) $clean);

        // A heading is short and is not a sentence. Both guards matter:
        // without them, any line opening with the word "Summary" would
        // cut the resume in half.
        if ($clean === '' || mb_strlen($clean) > 48 || preg_match('/[.!?,;]/u', $clean)) {
            return null;
        }

        $low = mb_strtolower($clean);

        foreach ($headings as $kind => $aliases) {
            foreach ($aliases as $alias) {
                if ($low === $alias) {
                    return $kind;
                }
            }
        }

        // "EXPERIENCE" written as "EXPERIENCE  |  2015 - 2024", or a
        // heading with one extra word after it, still names its section
        // -- as long as the whole line stayed short enough to be one.
        foreach ($headings as $kind => $aliases) {
            foreach ($aliases as $alias) {
                if (str_starts_with($low, $alias.' ') && mb_strlen($low) <= mb_strlen($alias) + 16) {
                    return $kind;
                }
            }
        }

        return null;
    }

    protected function guessSummary(array $sections, string $text): string
    {
        if (!empty($sections['summary'])) {
            return mb_substr($sections['summary'], 0, 1500);
        }
        return '';
    }

    /**
     * Where one entry ends and the next begins.
     *
     * Sana, 2026-09-23: "Parser not working correctly... combining
     * multiple thing in same point. ATS friendly resumes should work".
     *
     * Both of those were the same line of code. The parser split entries
     * on BLANK LINES -- and text pulled out of a PDF or a .docx very
     * often has none. Seven jobs arrived as one block: line one became
     * the role, line two became the company, and the entire rest of the
     * career became that first job's description. Exactly one bullet
     * containing everything.
     *
     * And it failed worst on precisely the resumes it should handle best.
     * An ATS-friendly resume is plain, single-column, tightly set, no
     * tables and no decorative spacing -- which is to say, no blank lines.
     * The prettier the layout, the better this used to work.
     *
     * So boundaries are found by structure instead, in this order:
     *
     *   1. a blank line, when the text has them and they actually
     *      separate entries (the old behaviour, still the best signal);
     *   2. a non-bullet line that follows a bullet -- bullets belong to
     *      the entry above them, so plain text after them starts a new
     *      one;
     *   3. a second date range -- one entry has one span of employment,
     *      so a line carrying another one begins the next job.
     *
     * @param  string  $body  one section's text
     * @return array<int, array<int, string>>  entries, each a list of lines
     */
    protected function splitEntries(string $body): array
    {
        $lines = [];
        foreach (preg_split('/\r?\n/', $body) as $raw) {
            $lines[] = rtrim($raw);
        }

        // Rule 1. Blank lines, when they separate more than one block.
        // A resume laid out with spacing is unambiguous, and nothing
        // below reads it better than the author's own paragraphing.
        $byBlank = array_values(array_filter(
            array_map('trim', preg_split('/\n\s*\n/', $body)),
            fn ($b) => $b !== ''
        ));
        if (count($byBlank) > 1) {
            return array_map(
                fn ($b) => array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $b)), fn ($l) => $l !== '')),
                $byBlank
            );
        }

        // Rules 2 and 3, walking the lines.
        $entries      = [];
        $current      = [];
        $sawBullet    = false;
        $sawDate      = false;
        $headerLines  = 0;      // non-bullet, non-date lines seen in this entry
        $prevWasDate  = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $isBullet   = $this->looksLikeBullet($line);
            $isDateOnly = $this->isDateOnlyLine($line);
            $hasDate    = $this->extractDateRange($line) !== [];

            // Rule 2: bullets belong to the entry above them, so a plain
            // line after a bullet opens the next one.
            $starts = $current !== [] && ! $isBullet && $sawBullet;

            // Rule 3: an entry has one span of employment. Once this one
            // has its dates, a line that LOOKS like a header -- short, no
            // sentence-ending punctuation -- starts the next entry.
            //
            // The two extra clauses are what separate the two layouts
            // that are otherwise identical in shape:
            //
            //   School / Degree / 2015-2017 / School / Degree / 2011-2015
            //   Role / Feb 2020 - Present / Company / Led the redesign.
            //
            // In the first, the line after the dates is a new entry. In
            // the second it is this entry's company. The difference is
            // that the first already had two header lines before its
            // dates -- so a header-like line directly after the date row
            // stays with the entry unless the header is already complete.
            if (! $starts && $current !== [] && $sawDate && ! $isBullet
                && $this->looksLikeEntryHeader($line)
                && ($headerLines >= 2 || ! $prevWasDate)) {
                $starts = true;
            }

            if ($starts) {
                $entries[]   = $current;
                $current     = [];
                $sawBullet   = false;
                $sawDate     = false;
                $headerLines = 0;
            }

            $current[] = $line;
            $sawBullet = $sawBullet || $isBullet;
            $sawDate   = $sawDate || $hasDate;
            if (! $isBullet && ! $isDateOnly) {
                $headerLines++;
            }
            $prevWasDate = $isDateOnly;
        }

        if ($current !== []) {
            $entries[] = $current;
        }

        return $entries;
    }

    /**
     * A bullet, in any of the shapes a PDF extractor emits.
     *
     * Includes a bare "o " and a lone hyphen, because Word's bullet
     * characters land as those often enough to matter.
     */
    protected function looksLikeBullet(string $line): bool
    {
        // A bare glyph on its own line counts too: an empty bullet is
        // still a bullet, and treating it as text turns it into a job.
        return preg_match('/^\s*(?:[•·▪◦‣∙*+]|[-–—](?:\s|$)|o\s)/u', $line) === 1;
    }

    /**
     * Does this line read as the start of a new entry rather than as
     * prose belonging to the one above it?
     *
     * A company or a school is short and does not end in a full stop. A
     * description sentence does. That is the whole discriminator, and it
     * is the one that keeps "Led the redesign of the account switching
     * flow." attached to the job it describes.
     */
    protected function looksLikeEntryHeader(string $line): bool
    {
        $line = trim($line);

        return $line !== ''
            && mb_strlen($line) <= 70
            && ! preg_match('/[.!?;]$/u', $line)
            && ! $this->isDateOnlyLine($line);
    }

    /** A line that is nothing but a date range, e.g. "Jan 2023 - Present". */
    protected function isDateOnlyLine(string $line): bool
    {
        if ($this->extractDateRange($line) === []) {
            return false;
        }

        // Strip everything a date line is made of; if what is left is
        // shorter than a word, the line carried no other information.
        $rest = preg_replace(
            '/(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec)[a-z]*|\d{4}|present|now|current|[-–—|,()·•]|\s|to\b/i',
            '',
            $line
        );

        return mb_strlen(trim((string) $rest)) < 3;
    }

    /**
     * One job.
     *
     * The header of an entry is its first line or two; the description is
     * what follows. Two things that used to go wrong here:
     *
     *  - a date-only line ("Jan 2023 - Present" on its own row, which is
     *    how most two-column layouts extract) was taken as the company;
     *  - the description kept the raw bullet glyphs, so an entry that had
     *    been a tidy list arrived as one paragraph of dots.
     */
    protected function parseExperienceBlock(string $body): array
    {
        $entries = [];

        foreach ($this->splitEntries($body) as $lines) {
            if ($lines === []) {
                continue;
            }

            $dates = $this->extractDateRange(implode("\n", $lines));

            // Header lines are the leading non-bullet, non-date-only rows.
            // Everything from the first bullet (or the third header line)
            // onwards is the description.
            $header = [];
            $rest   = [];
            foreach ($lines as $line) {
                // A date row is never content. It is read once, off the
                // whole entry, and then it is out of the way -- wherever
                // it sits, which in two-column layouts is anywhere.
                if ($this->isDateOnlyLine($line)) {
                    continue;
                }

                // The first line is always the header. A second one only
                // joins it if it still READS like a header -- otherwise a
                // resume with no bullets hands its opening sentence over
                // as the company name.
                $canHead = $header === [] || $this->looksLikeEntryHeader($line);

                if ($rest === [] && count($header) < 2 && $canHead && ! $this->looksLikeBullet($line)) {
                    $header[] = $line;

                    continue;
                }

                $rest[] = $line;
            }

            $first  = $header[0] ?? '';
            $second = $header[1] ?? '';

            // "Role, Company" on one line is as common as "Role at
            // Company", but a comma only means that when no second header
            // line is offering itself as the company -- otherwise
            // "Founder, CEO and Chairman" would import a company called
            // "CEO and Chairman".
            $sep = $second === ''
                ? '/^(.+?)\s*(?:\s(?:at|@)\s|[,\|·—–]|\s-\s)\s*(.+)$/iu'
                : '/^(.+?)\s+(?:at|@|\-|\||·|—|–)\s+(.+)$/iu';

            if (preg_match($sep, $first, $m)) {
                $role    = trim($m[1]);
                $company = trim($m[2]);
                // "Role at Company" already names both, so the second
                // header line is part of the description, not the company.
                if ($second !== '') {
                    array_unshift($rest, $second);
                }
            } else {
                $role    = $first;
                $company = $second;
            }

            // A company cell often carries its location after a separator.
            $location = '';
            if ($company !== '' && preg_match('/^(.+?)\s*[·|,]\s*(.+)$/u', $company, $m)) {
                $company  = trim($m[1]);
                $location = trim($m[2]);
            }

            $description = $this->cleanDescription($rest);

            $row = array_filter([
                'role'        => mb_substr($role, 0, 160),
                'company'     => mb_substr($company, 0, 160),
                'location'    => mb_substr($location, 0, 160),
                'start_date'  => $dates['start'] ?? null,
                'end_date'    => $dates['end'] ?? null,
                'is_current'  => $dates['current'] ?? false,
                'description' => mb_substr($description, 0, 2000),
            ], fn ($v) => $v !== null && $v !== '' && $v !== false);

            // An entry with neither a role nor a company is a stray line,
            // not a job. Dropping it beats importing an empty card --
            // and a row of leftover punctuation counts as nothing.
            $named = trim(preg_replace('/[^\p{L}\p{N}]+/u', '', ($row['role'] ?? '').($row['company'] ?? '')));
            if ($named === '') {
                continue;
            }

            $entries[] = $row;
        }

        return $entries;
    }

    /**
     * Description lines, as one bullet per line.
     *
     * Every bullet glyph becomes "- " so the editor and the renderer see
     * one list rather than a paragraph with dots in it -- which is the
     * visible half of "combining multiple thing in same point".
     *
     * @param  array<int, string>  $lines
     */
    protected function cleanDescription(array $lines): string
    {
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if ($this->looksLikeBullet($line)) {
                $line = '- '.trim(preg_replace('/^\s*(?:[•·▪◦‣∙*+]|[-–—]\s|o\s)\s*/u', '', $line));
            }
            $out[] = $line;
        }

        return trim(implode("\n", $out));
    }

    protected function parseEducationBlock(string $body): array
    {
        $entries = [];

        foreach ($this->splitEntries($body) as $lines) {
            if ($lines === []) {
                continue;
            }

            $dates  = $this->extractDateRange(implode("\n", $lines));
            $header = array_values(array_filter(
                $lines,
                fn ($l) => ! $this->looksLikeBullet($l) && ! $this->isDateOnlyLine($l)
            ));

            $school = $header[0] ?? '';
            $degree = $header[1] ?? '';

            // "University of X - B.Sc Computer Science" on one line is as
            // common as two lines, and used to import with no degree.
            if ($degree === '' && preg_match('/^(.+?)\s+(?:[-–—|·]|,)\s+(.+)$/u', $school, $m)) {
                $school = trim($m[1]);
                $degree = trim($m[2]);
            }

            if ($school === '') {
                continue;
            }

            $entries[] = array_filter([
                'school'     => mb_substr($school, 0, 160),
                'degree'     => mb_substr($degree, 0, 160),
                'start_date' => $dates['start'] ?? null,
                'end_date'   => $dates['end'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');
        }

        return $entries;
    }

    protected function parseSkillsBlock(string $body): array
    {
        $tokens = preg_split('/[,;\n•\|]+/', $body);
        $skills = [];
        foreach ($tokens as $t) {
            $t = trim($t, " \t-•·*");
            if ($t === '' || mb_strlen($t) > 80) continue;
            $skills[] = ['name' => $t, 'level' => 3];
            if (count($skills) >= 30) break;
        }
        return $skills;
    }

    protected function parseSimpleListBlock(string $body): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', $body) as $line) {
            $line = trim($line, " \t-•·*");
            if ($line === '' || mb_strlen($line) > 160) continue;
            $out[] = $line;
            if (count($out) >= 20) break;
        }
        return $out;
    }

    /** @return array{start?:?string,end?:?string,current?:bool} */
    protected function extractDateRange(string $block): array
    {
        $months = '(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec)[a-z]*';
        $pat = "/(?:({$months})\s+)?(\d{4})\s*(?:-|–|to|—)\s*(?:({$months})\s+)?(\d{4}|Present|Now|Current)/i";
        if (preg_match($pat, $block, $m)) {
            $startMonth = $this->monthToNum($m[1] ?? '');
            $endMonth   = $this->monthToNum($m[3] ?? '');
            $startYear  = $m[2];
            $endYearRaw = $m[4];
            $current = preg_match('/present|now|current/i', $endYearRaw) === 1;
            $start   = $startYear . '-' . ($startMonth ?: '01');
            $end     = $current ? null : ($endYearRaw . '-' . ($endMonth ?: '12'));
            return ['start' => $start, 'end' => $end, 'current' => $current];
        }
        return [];
    }

    protected function monthToNum(string $m): ?string
    {
        if ($m === '') return null;
        $key = strtolower(substr($m, 0, 3));
        $map = ['jan'=>'01','feb'=>'02','mar'=>'03','apr'=>'04','may'=>'05','jun'=>'06',
                'jul'=>'07','aug'=>'08','sep'=>'09','oct'=>'10','nov'=>'11','dec'=>'12'];
        return $map[$key] ?? null;
    }

    /**
     * Ask the chat model to structure the resume text. Schema-locked to
     * the exact shape our merge endpoint expects.
     */
    protected function aiStructure(User $user, string $text): array
    {
        $text = mb_substr($text, 0, 12000);
        $messages = [
            ['role' => 'system', 'content' =>
                "You convert resume text into structured JSON. " .
                "Return strict JSON with keys: header (object with name,email,phone,website), summary (string), " .
                "experience (array of {role,company,location,start_date YYYY-MM,end_date YYYY-MM,is_current,description}), " .
                "education (array of {school,degree,field,start_date,end_date,description}), " .
                "skills (array of {name, level 1-5}), " .
                "certifications (array of {name,issuer,issued_on}), " .
                "languages (array of {name,proficiency}). " .
                "ONE ENTRY PER ROLE: never merge two jobs, two companies or two date " .
                "ranges into a single experience entry, and never leave a second role " .
                "inside another entry's description. " .
                "Write each description as newline-separated bullets prefixed with \"- \". " .
                "Output JSON only. Use empty strings for unknown values."],
            ['role' => 'user', 'content' => $text],
        ];
        // Same reasoning as importFromAi(): a fixed ceiling under a long
        // resume is what makes a model merge entries rather than drop
        // them, and merged entries are the harder failure to spot.
        $model = AiEngineSettings::featureModel('coach', $user);
        $result = $this->openai->chat($user, $model, $messages, [
            'temperature'     => 0.1,
            'max_tokens'      => max(1800, min(4000, (int) ceil(mb_strlen($text) / 4) + 800)),
            'response_format' => ['type' => 'json_object'],
            'feature'         => 'resume_import',
            'reason'          => 'Resume parse fallback',
        ]);
        $parsed = json_decode((string) $result['content'], true);
        if (!is_array($parsed)) return ['items' => []];
        return [
            'header'  => is_array($parsed['header'] ?? null) ? $parsed['header'] : [],
            'summary' => is_string($parsed['summary'] ?? null) ? trim($parsed['summary']) : '',
            'items'   => $this->candidatesFromAiPayload($parsed),
            'credits_spent' => (int) ($result['credits_spent'] ?? 0),
        ];
    }

    protected function candidatesFromAiPayload(array $p): array
    {
        $items = [];
        foreach (self::SUPPORTED_TYPES as $type) {
            if (empty($p[$type]) || !is_array($p[$type])) continue;
            foreach ($p[$type] as $row) {
                if (!is_array($row)) continue;
                $items[] = ['section_type' => $type, 'data' => $row];
            }
        }
        return $items;
    }

    /**
     * Trim/coerce candidate item data into a shape the editor's section
     * validators will accept. Returns null when the required field for
     * the type is missing — the merge step skips those silently.
     */
    protected function sanitizeItemData(string $type, array $data): ?array
    {
        $str = fn ($k, $max) => isset($data[$k]) && is_scalar($data[$k]) ? mb_substr(trim((string) $data[$k]), 0, $max) : null;
        $url = function ($k) use ($data) {
            $v = isset($data[$k]) && is_string($data[$k]) ? trim($data[$k]) : '';
            return $v !== '' && filter_var($v, FILTER_VALIDATE_URL) ? $v : null;
        };
        $month = function ($k) use ($data) {
            $v = isset($data[$k]) && is_string($data[$k]) ? trim($data[$k]) : '';
            return preg_match('/^\d{4}-\d{2}$/', $v) ? $v : null;
        };

        $clean = [];
        switch ($type) {
            case 'experience':
                $clean = array_filter([
                    'company'     => $str('company', 160),
                    'role'        => $str('role', 160),
                    'location'    => $str('location', 160),
                    'start_date'  => $month('start_date'),
                    'end_date'    => $month('end_date'),
                    'is_current'  => !empty($data['is_current']),
                    'description' => $str('description', 2000),
                ], fn ($v) => $v !== null && $v !== '' && $v !== false);
                if (empty($clean['company']) || empty($clean['role'])) return null;
                if (!empty($clean['start_date']) && !empty($clean['end_date']) && $clean['end_date'] < $clean['start_date']) {
                    unset($clean['end_date']);
                }
                break;
            case 'education':
                $clean = array_filter([
                    'school'      => $str('school', 160),
                    'degree'      => $str('degree', 160),
                    'field'       => $str('field', 160),
                    'start_date'  => $month('start_date'),
                    'end_date'    => $month('end_date'),
                    'description' => $str('description', 1000),
                ], fn ($v) => $v !== null && $v !== '');
                if (empty($clean['school'])) return null;
                if (!empty($clean['start_date']) && !empty($clean['end_date']) && $clean['end_date'] < $clean['start_date']) {
                    unset($clean['end_date']);
                }
                break;
            case 'skills':
                $name = $str('name', 80);
                if (!$name) return null;
                $clean = array_filter([
                    'name'  => $name,
                    'level' => isset($data['level']) ? max(1, min(5, (int) $data['level'])) : 3,
                    'group' => $str('group', 80),
                ], fn ($v) => $v !== null && $v !== '');
                break;
            case 'projects':
                $name = $str('name', 160);
                if (!$name) return null;
                $clean = array_filter([
                    'name'        => $name,
                    'role'        => $str('role', 160),
                    'url'         => $url('url'),
                    'description' => $str('description', 2000),
                    'start_date'  => $month('start_date'),
                    'end_date'    => $month('end_date'),
                ], fn ($v) => $v !== null && $v !== '');
                break;
            case 'certifications':
                $name = $str('name', 160);
                if (!$name) return null;
                $clean = array_filter([
                    'name'           => $name,
                    'issuer'         => $str('issuer', 160),
                    'issued_on'      => $month('issued_on'),
                    'expires_on'     => $month('expires_on'),
                    'credential_url' => $url('credential_url'),
                ], fn ($v) => $v !== null && $v !== '');
                break;
            case 'awards':
                $title = $str('title', 160);
                if (!$title) return null;
                $clean = array_filter([
                    'title'       => $title,
                    'issuer'      => $str('issuer', 160),
                    'date'        => $month('date'),
                    'description' => $str('description', 1000),
                ], fn ($v) => $v !== null && $v !== '');
                break;
            case 'languages':
                $name = $str('name', 80);
                if (!$name) return null;
                $allowed = ['basic', 'conversational', 'professional', 'fluent', 'native'];
                $prof = isset($data['proficiency']) ? strtolower((string) $data['proficiency']) : '';
                $clean = array_filter([
                    'name'        => $name,
                    'proficiency' => in_array($prof, $allowed, true) ? $prof : null,
                ], fn ($v) => $v !== null && $v !== '');
                break;
            case 'links':
                $u = $url('url');
                $label = $str('label', 80);
                if (!$u || !$label) return null;
                $clean = array_filter([
                    'label' => $label,
                    'url'   => $u,
                    'icon'  => $str('icon', 40),
                ], fn ($v) => $v !== null && $v !== '');
                break;
            default:
                return null;
        }
        return $clean;
    }
}
