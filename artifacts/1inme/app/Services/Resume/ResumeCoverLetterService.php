<?php

namespace App\Services\Resume;

use App\Modules\User\Models\AiPersona;
use App\Modules\User\Models\Resume;
use App\Modules\User\Models\ResumeCoverLetter;
use App\Modules\User\Models\ResumeSectionItem;
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\OpenAiService;
use RuntimeException;

/**
 * "Generate a cover letter from this resume + job description" engine.
 *
 * Builds a prompt that combines:
 *  - the creator's resume (header, summary, experience, skills),
 *  - the pasted job description,
 *  - the creator's saved AI persona / voice (when present),
 *  - the requested tone preset (professional, warm, concise),
 * and asks the model to return a structured JSON letter (greeting +
 * body paragraphs + sign-off) so the editor can render per-section
 * controls without re-parsing prose.
 *
 * Charges happen inside OpenAiService against the
 * `resume_cover_letter` feature, so admins can tune the model
 * independently of resume tailoring. Results are persisted to
 * `resume_cover_letters` so the History panel can list every prior
 * draft and the creator can revise / re-export later without paying
 * for a fresh generation.
 */
class ResumeCoverLetterService
{
    public const FEATURE = 'resume_cover_letter';

    /** Available tone presets surfaced to the creator. */
    public const TONES = ['professional', 'warm', 'concise'];

    /** Sections the per-section regenerate flow can target. */
    public const SECTIONS = ['greeting', 'body', 'sign_off'];

    private const MAX_EXPERIENCE_ITEMS = 8;
    private const MAX_BULLET_LEN       = 900;
    private const MAX_JD_LEN           = 8000;
    private const MAX_OUTPUT_TOKENS    = 1400;
    private const MAX_BODY_PARAGRAPHS  = 5;

    public function __construct(protected OpenAiService $openai) {}

    /** Worst-case credit cost for the upfront confirmation step. */
    public function estimateCredits(Resume $resume, string $jd, string $tone, ?int $personaId = null, ?User $user = null): int
    {
        $model    = AiEngineSettings::featureModel(self::FEATURE);
        $messages = $this->buildMessages($resume, $jd, $tone, $personaId);
        return $this->openai->estimateChatCoins($model, $messages, self::MAX_OUTPUT_TOKENS, $user);
    }

    /** Estimate cost for regenerating a single section (smaller prompt). */
    public function estimateSectionCredits(
        Resume $resume,
        ResumeCoverLetter $letter,
        string $section,
        ?string $instruction = null,
        ?User $user = null,
    ): int {
        $model    = AiEngineSettings::featureModel(self::FEATURE);
        $messages = $this->buildSectionMessages($resume, $letter, $section, $instruction);
        return $this->openai->estimateChatCoins($model, $messages, 700, $user);
    }

    /**
     * Generate a fresh cover letter and persist it. Returns the saved
     * model + the credits actually charged (post-call).
     *
     * @return array{letter:ResumeCoverLetter, credits_spent:int}
     */
    public function generate(
        User $user,
        Resume $resume,
        string $jobDescription,
        string $tone,
        ?int $personaId = null,
    ): array {
        $tone = $this->normalizeTone($tone);
        $jd   = $this->normalizeJd($jobDescription);
        $personaId = $this->resolvePersonaId($user, $personaId);
        $messages = $this->buildMessages($resume, $jd, $tone, $personaId);
        $model    = AiEngineSettings::featureModel(self::FEATURE);

        $result = $this->openai->chat($user, $model, $messages, [
            'temperature'     => $tone === 'warm' ? 0.55 : 0.35,
            'max_tokens'      => self::MAX_OUTPUT_TOKENS,
            'response_format' => ['type' => 'json_object'],
            'feature'         => self::FEATURE,
            'related_id'      => $resume->id,
            'reason'          => 'Resume cover letter generated',
            'meta'            => [
                'jd_excerpt' => $this->jdExcerpt($jd),
                'jd_chars'   => mb_strlen($jd),
                'tone'       => $tone,
                'kind'       => 'full',
                'persona_id' => $personaId,
            ],
        ]);

        $content = $this->parseLetter((string) $result['content']);
        $title   = $this->deriveTitle($content, $jd);

        $letter = ResumeCoverLetter::create([
            'user_id'         => $user->id,
            'resume_id'       => $resume->id,
            'resume_revision' => (int) ($resume->share_revision ?? 0),
            'title'           => $title,
            'tone'            => $tone,
            'jd_text'         => $jd,
            'jd_excerpt'      => $this->jdExcerpt($jd),
            'language'        => $this->resumeLanguage($resume),
            'ai_persona_id'   => $personaId,
            'content'         => $content,
            'model'           => (string) ($result['model'] ?? $model),
            'credits_spent'   => (int) ($result['credits_spent'] ?? 0),
        ]);

        return [
            'letter'        => $letter,
            'credits_spent' => (int) ($result['credits_spent'] ?? 0),
        ];
    }

    /**
     * Regenerate a single section in place. Bumps `credits_spent` so
     * the running cost stays accurate.
     *
     * @return array{letter:ResumeCoverLetter, credits_spent:int}
     */
    public function regenerateSection(
        User $user,
        Resume $resume,
        ResumeCoverLetter $letter,
        string $section,
        ?string $instruction = null,
    ): array {
        $section = $this->normalizeSection($section);
        $messages = $this->buildSectionMessages($resume, $letter, $section, $instruction);
        $model    = AiEngineSettings::featureModel(self::FEATURE);

        $result = $this->openai->chat($user, $model, $messages, [
            'temperature'     => $letter->tone === 'warm' ? 0.55 : 0.35,
            'max_tokens'      => 700,
            'response_format' => ['type' => 'json_object'],
            'feature'         => self::FEATURE,
            'related_id'      => $letter->id,
            'reason'          => "Resume cover letter section regenerated ({$section})",
            'meta'            => [
                'section'    => $section,
                'tone'       => $letter->tone,
                'kind'       => 'section',
                'letter_id'  => $letter->id,
                'persona_id' => $letter->ai_persona_id,
            ],
        ]);

        $parsed = json_decode((string) $result['content'], true);
        if (!is_array($parsed)) {
            throw new RuntimeException('The assistant returned an unexpected response. Try again.');
        }

        $content = is_array($letter->content) ? $letter->content : [];
        $content = $this->mergeSection($content, $section, $parsed);

        $letter->update([
            'content'       => $content,
            'credits_spent' => (int) $letter->credits_spent + (int) ($result['credits_spent'] ?? 0),
        ]);

        return [
            'letter'        => $letter->fresh(),
            'credits_spent' => (int) ($result['credits_spent'] ?? 0),
        ];
    }

    /**
     * Build the chat messages for a full cover-letter generation.
     * Pulled out so estimate() and generate() use exactly the same
     * prompt — otherwise the surfaced price wouldn't match the charge.
     *
     * @return list<array{role:string,content:string}>
     */
    public function buildMessages(Resume $resume, string $jobDescription, string $tone, ?int $personaId = null): array
    {
        $jd   = $this->normalizeJd($jobDescription);
        $tone = $this->normalizeTone($tone);

        $payload = $this->resumePayload($resume);
        $persona = $this->personaSnippet($resume->user, $personaId);
        $lang    = $this->resumeLanguage($resume);

        $toneHint = match ($tone) {
            'warm'    => 'Use a warm, personable, slightly conversational voice. Show genuine enthusiasm for the role and team without being overfamiliar.',
            'concise' => 'Use a tight, no-fluff voice. Prefer short sentences. Keep the body to two paragraphs maximum.',
            default   => 'Use a professional, confident voice. Keep paragraphs focused and free of clichés.',
        };

        $schemaHint = "Return strict JSON with this shape (no extra keys, no markdown, no commentary):\n"
            . "{\n"
            . "  \"greeting\": string,\n"
            . "  \"body\": [string, ...],\n"
            . "  \"sign_off\": string,\n"
            . "  \"company\": string\n"
            . "}\n"
            . "Rules:\n"
            . "- `greeting` is a single line (e.g. \"Dear Hiring Team,\"). Use a named recipient only if the JD names one.\n"
            . "- `body` is between 2 and " . self::MAX_BODY_PARAGRAPHS . " paragraphs of plain text. No bullet lists. No newlines inside a paragraph.\n"
            . "- `sign_off` is a closing line plus the candidate's display name on the next line, separated by \\n.\n"
            . "- `company` is the company name extracted from the JD, or an empty string if unclear.\n"
            . "- Never invent employers, dates, metrics, degrees, certifications or contact details the resume doesn't supply.\n"
            . "- Reference 1–3 specific resume highlights that match the JD; do not list the entire resume.\n"
            . "- Write in the resume's language (`{$lang}`).\n";

        $system = "You write tailored cover letters for job applications. The candidate's resume "
            . "is provided as JSON. Your job is to produce a single cover letter that highlights "
            . "the most relevant experience and skills for the supplied job description. "
            . $toneHint . " " . $schemaHint;
        if ($persona !== '') {
            $system .= "\n\nVOICE / PERSONA OF THE CANDIDATE (apply this style on top of the tone):\n" . $persona;
        }

        $user = "RESUME (JSON):\n" . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . "\n\nJOB DESCRIPTION:\n" . $jd
            . "\n\nTONE PRESET: " . $tone;

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ];
    }

    /**
     * Build messages for regenerating a single section. Includes the
     * existing letter as context so the rewrite stays consistent with
     * the parts the creator is keeping.
     *
     * @return list<array{role:string,content:string}>
     */
    public function buildSectionMessages(
        Resume $resume,
        ResumeCoverLetter $letter,
        string $section,
        ?string $instruction = null,
    ): array {
        $section = $this->normalizeSection($section);
        $payload = $this->resumePayload($resume);
        $persona = $this->personaSnippet($resume->user, $letter->ai_persona_id);
        $lang    = $letter->language ?: $this->resumeLanguage($resume);

        $existing = is_array($letter->content) ? $letter->content : [];
        $current  = $this->sectionToString($existing, $section);

        $toneLine = "Tone preset: " . $letter->tone . ". Language: " . $lang . ".";

        $shapeHint = match ($section) {
            'greeting' => 'Return JSON: {"greeting": string}. A single line, max 80 characters, no trailing newline.',
            'sign_off' => 'Return JSON: {"sign_off": string}. Closing line plus the candidate name on the next line, separated by \\n.',
            default    => 'Return JSON: {"body": [string, ...]} with 2–' . self::MAX_BODY_PARAGRAPHS
                          . ' paragraphs of plain text. No bullet lists. No newlines inside a paragraph.',
        };

        $extra = trim((string) $instruction);
        $extraLine = $extra !== '' ? "\nUSER INSTRUCTION: " . mb_substr($extra, 0, 400) : '';

        $system = "You are revising a single section of an existing cover letter. "
            . "Keep the rest of the letter unchanged in spirit and stay truthful to the resume. "
            . $toneLine . " " . $shapeHint;
        if ($persona !== '') {
            $system .= "\n\nVOICE / PERSONA OF THE CANDIDATE:\n" . $persona;
        }

        $user = "SECTION TO REWRITE: " . $section
            . "\n\nCURRENT TEXT:\n" . ($current === '' ? '(empty)' : $current)
            . "\n\nOTHER SECTIONS (for context, do not repeat):\n"
            . json_encode($this->otherSections($existing, $section), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . "\n\nRESUME (JSON):\n"
            . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . "\n\nJOB DESCRIPTION:\n" . $this->normalizeJd((string) $letter->jd_text)
            . $extraLine;

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ];
    }

    /**
     * Apply a manual inline edit. Sanitises shape (paragraphs, length)
     * and returns the saved letter.
     */
    public function saveManualEdit(ResumeCoverLetter $letter, array $content, ?string $title = null): ResumeCoverLetter
    {
        $next = [
            'greeting' => trim((string) ($content['greeting'] ?? '')),
            'body'     => $this->cleanBody($content['body'] ?? []),
            'sign_off' => trim((string) ($content['sign_off'] ?? '')),
        ];

        $update = ['content' => $next];
        if ($title !== null) {
            $clean = trim($title);
            if ($clean !== '') $update['title'] = mb_substr($clean, 0, 200);
        }

        $letter->update($update);
        return $letter->fresh();
    }

    /**
     * Recent saved letters for this user + resume, newest first. Used
     * for the "History" panel inside the cover-letter modal.
     *
     * @return list<array<string,mixed>>
     */
    public function recentLetters(User $user, Resume $resume, int $limit = 20): array
    {
        return ResumeCoverLetter::where('user_id', $user->id)
            ->where('resume_id', $resume->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn(ResumeCoverLetter $l) => $this->present($l, false))
            ->all();
    }

    /**
     * @return array<string,mixed>
     */
    public function present(ResumeCoverLetter $letter, bool $includeJd = true): array
    {
        $personaName = null;
        if ($letter->ai_persona_id) {
            $personaName = AiPersona::where('id', $letter->ai_persona_id)
                ->where('user_id', $letter->user_id)
                ->value('name');
        }

        $row = [
            'id'              => (int) $letter->id,
            'title'           => (string) $letter->title,
            'tone'            => (string) $letter->tone,
            'language'        => (string) $letter->language,
            'ai_persona_id'   => $letter->ai_persona_id ? (int) $letter->ai_persona_id : null,
            'ai_persona_name' => $personaName,
            'jd_excerpt'      => (string) ($letter->jd_excerpt ?? ''),
            'content'         => is_array($letter->content) ? $letter->content : [],
            'credits_spent'   => (int) $letter->credits_spent,
            'model'           => $letter->model,
            'created_at'      => optional($letter->created_at)->toIso8601String(),
            'updated_at'      => optional($letter->updated_at)->toIso8601String(),
            'resume_revision' => (int) $letter->resume_revision,
        ];
        if ($includeJd) {
            $row['jd_text'] = (string) $letter->jd_text;
        }
        return $row;
    }

    /**
     * Light list of the user's saved personas, newest first, for the
     * Voice picker in the cover-letter modal. We only need id + name
     * for the dropdown — the full content is only injected server-
     * side at generation time so it never travels to the browser.
     *
     * @return list<array{id:int,name:string}>
     */
    public function userPersonas(User $user, int $limit = 50): array
    {
        return AiPersona::where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'name'])
            ->map(fn(AiPersona $p) => [
                'id'   => (int) $p->id,
                'name' => (string) $p->name,
            ])
            ->all();
    }

    public function normalizeTone(string $tone): string
    {
        $t = strtolower(trim($tone));
        return in_array($t, self::TONES, true) ? $t : 'professional';
    }

    public function normalizeSection(string $section): string
    {
        $s = strtolower(trim($section));
        if (!in_array($s, self::SECTIONS, true)) {
            throw new RuntimeException('Unknown letter section.');
        }
        return $s;
    }

    // ───────── internals ─────────

    protected function normalizeJd(string $jd): string
    {
        $jd = trim($jd);
        if ($jd === '') {
            throw new RuntimeException('Paste the job description first.');
        }
        if (mb_strlen($jd) > self::MAX_JD_LEN) {
            $jd = mb_substr($jd, 0, self::MAX_JD_LEN);
        }
        return $jd;
    }

    protected function jdExcerpt(string $jd): string
    {
        return mb_substr(trim($jd), 0, 200);
    }

    protected function resumeLanguage(Resume $resume): string
    {
        $owner = $resume->user;
        $lang = $owner?->language ?? 'en';
        $lang = preg_replace('/[^a-zA-Z\-]/', '', (string) $lang) ?: 'en';
        return mb_substr($lang, 0, 8);
    }

    /**
     * Compose a short voice/persona snippet from one of the creator's
     * saved AI persona library entries. The creator picks which voice
     * to use from the cover-letter panel; passing `null` (or `0`) means
     * "None" — the letter is generated using only the resume + tone
     * preset, with no persona styling injected.
     *
     * The id is always re-validated against the user so a leaked /
     * stale id from the client can't pull in another creator's voice.
     */
    protected function personaSnippet(?User $user, ?int $personaId): string
    {
        if (!$user || !$personaId) return '';
        $row = AiPersona::where('user_id', $user->id)
            ->where('id', $personaId)
            ->first(['tone', 'content']);
        if (!$row) return '';

        $tone    = trim((string) ($row->tone ?? ''));
        $content = trim((string) ($row->content ?? ''));
        if ($tone === '' && $content === '') return '';

        $snippet = '';
        if ($tone !== '')    $snippet .= "Tone: " . mb_substr($tone, 0, 200) . "\n";
        if ($content !== '') $snippet .= "Voice notes: " . mb_substr($content, 0, 1200);
        return trim($snippet);
    }

    /**
     * Validate the requested persona id against the user. Unknown or
     * not-owned ids fall back to `null` ("None") so the creator can
     * never accidentally pull in someone else's voice and so deleted
     * personas degrade cleanly instead of throwing mid-generation.
     */
    protected function resolvePersonaId(?User $user, ?int $personaId): ?int
    {
        if (!$user || !$personaId || $personaId <= 0) return null;
        $exists = AiPersona::where('user_id', $user->id)
            ->where('id', $personaId)
            ->exists();
        return $exists ? $personaId : null;
    }

    /** Trim the resume down to what cover-letter generation needs. */
    protected function resumePayload(Resume $resume): array
    {
        $sections = $resume->getMergedSections();
        $header   = (array) ($sections['header'] ?? []);

        $experience = $resume->itemsOfType('experience')
            ->orderBy('position')
            ->limit(self::MAX_EXPERIENCE_ITEMS)
            ->get()
            ->map(function (ResumeSectionItem $i) {
                $d = is_array($i->data) ? $i->data : [];
                return [
                    'role'        => (string) ($d['role'] ?? ''),
                    'company'     => (string) ($d['company'] ?? ''),
                    'dates'       => trim(((string) ($d['start_date'] ?? '')) . ' – '
                                     . ((!empty($d['is_current'])) ? 'Present' : (string) ($d['end_date'] ?? ''))),
                    'description' => mb_substr((string) ($d['description'] ?? ''), 0, self::MAX_BULLET_LEN),
                ];
            })->all();

        $skills = $resume->itemsOfType('skills')
            ->orderBy('position')
            ->get()
            ->map(fn(ResumeSectionItem $i) => (string) ((is_array($i->data) ? $i->data : [])['name'] ?? ''))
            ->filter()
            ->values()
            ->all();

        return [
            'name'       => (string) ($header['name'] ?? ''),
            'headline'   => (string) ($header['headline'] ?? ''),
            'location'   => (string) ($header['location'] ?? ''),
            'email'      => (string) ($header['email'] ?? ''),
            'summary'    => (string) ($sections['summary'] ?? ''),
            'experience' => $experience,
            'skills'     => $skills,
        ];
    }

    protected function parseLetter(string $raw): array
    {
        $parsed = json_decode($raw, true);
        if (!is_array($parsed)) {
            throw new RuntimeException('The assistant returned an unexpected response. Try again.');
        }

        return [
            'greeting' => trim((string) ($parsed['greeting'] ?? '')),
            'body'     => $this->cleanBody($parsed['body'] ?? []),
            'sign_off' => trim((string) ($parsed['sign_off'] ?? '')),
            'company'  => trim((string) ($parsed['company'] ?? '')),
        ];
    }

    /** Clamp body to plain-text paragraphs and the max paragraph count. */
    protected function cleanBody(mixed $body): array
    {
        $arr = [];
        if (is_array($body)) {
            foreach ($body as $p) {
                $line = trim((string) $p);
                if ($line === '') continue;
                // Collapse internal newlines so the JSON shape's "no
                // newlines inside a paragraph" rule is enforced even
                // if the model or a manual edit slips one in.
                $line = preg_replace("/\s*\n+\s*/u", ' ', $line);
                $arr[] = mb_substr($line, 0, 2000);
                if (count($arr) >= self::MAX_BODY_PARAGRAPHS) break;
            }
        }
        return $arr;
    }

    protected function deriveTitle(array $content, string $jd): string
    {
        $company = trim((string) ($content['company'] ?? ''));
        if ($company !== '') {
            return mb_substr('Cover letter — ' . $company, 0, 200);
        }
        $excerpt = trim(preg_replace('/\s+/u', ' ', mb_substr($jd, 0, 80)));
        return $excerpt !== '' ? mb_substr('Cover letter — ' . $excerpt, 0, 200) : 'Cover letter';
    }

    protected function sectionToString(array $content, string $section): string
    {
        return match ($section) {
            'greeting' => trim((string) ($content['greeting'] ?? '')),
            'sign_off' => trim((string) ($content['sign_off'] ?? '')),
            default    => implode("\n\n", $this->cleanBody($content['body'] ?? [])),
        };
    }

    protected function otherSections(array $content, string $section): array
    {
        $out = [
            'greeting' => trim((string) ($content['greeting'] ?? '')),
            'body'     => $this->cleanBody($content['body'] ?? []),
            'sign_off' => trim((string) ($content['sign_off'] ?? '')),
        ];
        unset($out[$section]);
        return $out;
    }

    protected function mergeSection(array $existing, string $section, array $parsed): array
    {
        $next = [
            'greeting' => trim((string) ($existing['greeting'] ?? '')),
            'body'     => $this->cleanBody($existing['body'] ?? []),
            'sign_off' => trim((string) ($existing['sign_off'] ?? '')),
        ];
        switch ($section) {
            case 'greeting':
                $g = trim((string) ($parsed['greeting'] ?? ''));
                if ($g !== '') $next['greeting'] = mb_substr($g, 0, 200);
                break;
            case 'sign_off':
                $s = trim((string) ($parsed['sign_off'] ?? ''));
                if ($s !== '') $next['sign_off'] = mb_substr($s, 0, 400);
                break;
            default:
                $body = $this->cleanBody($parsed['body'] ?? []);
                if ($body) $next['body'] = $body;
                break;
        }
        return $next;
    }
}
