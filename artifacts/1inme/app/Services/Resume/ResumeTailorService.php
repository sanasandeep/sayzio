<?php

namespace App\Services\Resume;

use App\Modules\User\Models\WalletTransaction;
use App\Modules\User\Models\Resume;
use App\Modules\User\Models\ResumeSectionItem;
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\OpenAiService;
use RuntimeException;

/**
 * "Tailor my resume to this job description" engine.
 *
 * Sends the user's current resume payload (summary + experience bullets
 * + skills) plus the pasted JD to OpenAI with a strict JSON schema,
 * then returns a per-section list of suggested edits with rationale.
 * The controller hands those back to the editor's diff UI; the user
 * picks which to keep and posts the chosen subset to applySuggestions().
 *
 * Credits are charged inside OpenAiService::chat() against the
 * `resume_tailor` feature so admins can tune the model independently.
 * Recent runs are surfaced via AI-tagged coin wallet transactions (no
 * extra table needed) so the audit trail is the source of truth.
 *
 * Out of scope here: scraping JDs from URLs (paste-only by design),
 * saving the tailored resume as a new version (depends on the
 * still-pending multi-version feature — for v1 we only support
 * "replace the current resume").
 */
class ResumeTailorService
{
    public const FEATURE = 'resume_tailor';

    /** Hard caps so a giant resume doesn't blow the prompt window. */
    private const MAX_EXPERIENCE_ITEMS = 12;
    private const MAX_BULLET_LEN       = 1200;
    private const MAX_JD_LEN           = 8000;
    private const MAX_OUTPUT_TOKENS    = 1800;

    public function __construct(protected OpenAiService $openai) {}

    /**
     * Build the chat messages for a tailoring run. Pulled out so both
     * estimate() and run() use exactly the same prompt — otherwise the
     * shown price wouldn't match what the user actually gets charged.
     *
     * @return list<array{role:string,content:string}>
     */
    public function buildMessages(Resume $resume, string $jobDescription): array
    {
        $jd = trim($jobDescription);
        if ($jd === '') {
            throw new RuntimeException('Paste the job description first.');
        }
        if (mb_strlen($jd) > self::MAX_JD_LEN) {
            $jd = mb_substr($jd, 0, self::MAX_JD_LEN);
        }

        $payload = $this->resumePayload($resume);

        $schemaHint = "Return strict JSON with this shape (no extra keys, no markdown, no commentary):\n"
            . "{\n"
            . "  \"summary\": { \"current\": string, \"suggested\": string, \"rationale\": string },\n"
            . "  \"experience\": [ { \"item_id\": int, \"current\": string, \"suggested\": string, \"rationale\": string } ],\n"
            . "  \"skills\": { \"additions\": [ { \"name\": string, \"rationale\": string } ] },\n"
            . "  \"keywords\": [ string ]\n"
            . "}\n"
            . "Rules:\n"
            . "- Reuse the exact `item_id` values from the input resume; never invent ids.\n"
            . "- Only include experience entries you actually rewrote — skip ones that already match well.\n"
            . "- `suggested` for experience is a complete replacement bullet block (newline-separated bullets).\n"
            . "- `additions` are skills that the JD calls for and the resume is missing; never duplicate existing skills.\n"
            . "- `keywords` is the short list of JD-specific terms a hiring filter is likely to look for.\n"
            . "- Never invent employers, dates, metrics, or credentials the user didn't supply.\n"
            . "- Use empty strings or empty arrays rather than null.\n";

        $system = "You are a resume tailoring assistant. Rewrite the user's existing resume to "
            . "emphasize the experience, skills, and keywords that match the supplied job "
            . "description, while staying truthful to the source. Be concise and action-oriented; "
            . "lead bullets with strong verbs. " . $schemaHint;

        $user = "RESUME (JSON):\n" . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . "\n\nJOB DESCRIPTION:\n" . $jd;

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ];
    }

    /**
     * Worst-case credit cost the editor surfaces before the user clicks
     * Run. Uses the same messages buildMessages() will pass to chat().
     */
    public function estimateCredits(Resume $resume, string $jobDescription, ?User $user = null): int
    {
        $model    = AiEngineSettings::featureModel(self::FEATURE);
        $messages = $this->buildMessages($resume, $jobDescription);
        return $this->openai->estimateChatCoins($model, $messages, self::MAX_OUTPUT_TOKENS, $user);
    }

    /**
     * Run the tailoring AI call. Returns the structured suggestions plus
     * the credits actually spent (post-call, from token usage), suitable
     * for shipping straight to the diff UI.
     */
    public function run(User $user, Resume $resume, string $jobDescription): array
    {
        $messages = $this->buildMessages($resume, $jobDescription);
        $model    = AiEngineSettings::featureModel(self::FEATURE);

        $result = $this->openai->chat($user, $model, $messages, [
            'temperature'     => 0.3,
            'max_tokens'      => self::MAX_OUTPUT_TOKENS,
            'response_format' => ['type' => 'json_object'],
            'feature'         => self::FEATURE,
            'related_id'      => $resume->id,
            'reason'          => 'Resume tailored to job description',
            'meta'            => [
                'jd_excerpt' => mb_substr(trim($jobDescription), 0, 160),
                'jd_chars'   => mb_strlen(trim($jobDescription)),
            ],
        ]);

        $parsed = json_decode((string) $result['content'], true);
        if (!is_array($parsed)) {
            throw new RuntimeException('The assistant returned an unexpected response. Try again.');
        }

        return [
            'suggestions'   => $this->normalizeSuggestions($parsed, $resume),
            'credits_spent' => (int) ($result['credits_spent'] ?? 0),
            'model'         => (string) ($result['model'] ?? $model),
        ];
    }

    /**
     * Apply the user's accepted suggestions in place. `picks` is shaped:
     *   [
     *     'summary'    => bool,
     *     'experience' => [item_id, item_id, ...],
     *     'skills'     => [skill_index, ...],
     *   ]
     * Anything not in the picks list is left untouched. We re-validate
     * that experience item_ids belong to this resume so a forged id
     * can't reach into someone else's row.
     */
    public function applySuggestions(Resume $resume, array $suggestions, array $picks): array
    {
        $changed = ['summary' => false, 'experience' => 0, 'skills' => 0];

        // Summary
        if (!empty($picks['summary']) && !empty($suggestions['summary']['suggested'])) {
            $sections = $resume->getMergedSections();
            $next = trim((string) $suggestions['summary']['suggested']);
            if ($next !== '' && $next !== (string) ($sections['summary'] ?? '')) {
                $sections['summary'] = $next;
                $resume->update(['sections' => $sections]);
                $changed['summary'] = true;
            }
        }

        // Experience — update existing items in place. We treat the
        // suggested block as the new `description`; other fields are
        // preserved so the user keeps role/company/dates.
        $picksExp = array_values(array_unique(array_map('intval', $picks['experience'] ?? [])));
        if ($picksExp) {
            $byId = ResumeSectionItem::where('resume_id', $resume->id)
                ->where('section_type', 'experience')
                ->whereIn('id', $picksExp)
                ->get()
                ->keyBy('id');

            foreach ($suggestions['experience'] ?? [] as $row) {
                $id = (int) ($row['item_id'] ?? 0);
                if (!$id || !isset($byId[$id])) continue;
                $newDesc = trim((string) ($row['suggested'] ?? ''));
                if ($newDesc === '') continue;
                $item = $byId[$id];
                $data = is_array($item->data) ? $item->data : [];
                if (($data['description'] ?? '') === $newDesc) continue;
                $data['description'] = $newDesc;
                $item->update(['data' => $data]);
                $changed['experience']++;
            }
        }

        // Skills — append accepted additions as fresh items.
        $picksSk = array_values(array_unique(array_map('intval', $picks['skills'] ?? [])));
        $additions = is_array($suggestions['skills']['additions'] ?? null)
            ? $suggestions['skills']['additions'] : [];
        if ($picksSk && $additions) {
            $existingNames = $resume->itemsOfType('skills')->get()
                ->pluck('data.name')
                ->filter()
                ->map(fn($n) => mb_strtolower(trim((string) $n)))
                ->all();
            $maxPos = (int) $resume->itemsOfType('skills')->max('position');
            $offset = 0;
            foreach ($picksSk as $idx) {
                $cand = $additions[$idx] ?? null;
                if (!is_array($cand)) continue;
                $name = trim((string) ($cand['name'] ?? ''));
                if ($name === '') continue;
                if (in_array(mb_strtolower($name), $existingNames, true)) continue;
                $resume->items()->create([
                    'section_type' => 'skills',
                    'position'     => $maxPos + (++$offset),
                    'data'         => ['name' => $name, 'level' => 0, 'group' => ''],
                ]);
                $existingNames[] = mb_strtolower($name);
                $changed['skills']++;
            }
        }

        return [
            'changed' => $changed,
            'resume'  => $resume->fresh('items'),
        ];
    }

    /**
     * Most recent tailoring runs for this user, derived from the coin
     * wallet ledger. Each row has the JD excerpt (stored in `meta`),
     * how much it cost (coins), and the timestamp.
     *
     * @return list<array{id:int,when:string,credits:int,jd_excerpt:string,model:?string}>
     */
    public function recentRuns(User $user, int $limit = 10): array
    {
        $rows = WalletTransaction::where('user_id', $user->id)
            ->where('meta->ai', true)
            ->where('meta->feature', self::FEATURE)
            ->where('type', 'spend')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'created_at', 'delta_coins', 'meta']);

        return $rows->map(function (WalletTransaction $tx) {
            $meta = is_array($tx->meta) ? $tx->meta : [];
            return [
                'id'         => (int) $tx->id,
                'when'       => optional($tx->created_at)->toIso8601String(),
                'credits'    => abs((int) $tx->delta_coins),
                'jd_excerpt' => (string) ($meta['jd_excerpt'] ?? ''),
                'model'      => $meta['model'] ?? null,
            ];
        })->all();
    }

    // ───────── internals ─────────

    /** Trim the resume down to what tailoring actually needs. */
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
                    'item_id'     => (int) $i->id,
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
            'name'        => (string) ($header['name'] ?? ''),
            'headline'    => (string) ($header['headline'] ?? ''),
            'summary'     => (string) ($sections['summary'] ?? ''),
            'experience'  => $experience,
            'skills'      => $skills,
        ];
    }

    /**
     * Defensive normalisation so the diff UI always gets the same shape
     * regardless of what the model emitted. Anything missing becomes
     * an empty list / empty string.
     */
    protected function normalizeSuggestions(array $parsed, Resume $resume): array
    {
        $sections = $resume->getMergedSections();
        $currentSummary = (string) ($sections['summary'] ?? '');

        $sumIn   = is_array($parsed['summary'] ?? null) ? $parsed['summary'] : [];
        $summary = [
            'current'   => $currentSummary,
            'suggested' => trim((string) ($sumIn['suggested'] ?? '')),
            'rationale' => trim((string) ($sumIn['rationale'] ?? '')),
            'changed'   => false,
        ];
        $summary['changed'] = $summary['suggested'] !== ''
            && $summary['suggested'] !== $currentSummary;

        // Build current-experience-by-id so we can attach the original
        // description (the model may not echo it) and drop any forged ids.
        $expCurrent = $resume->itemsOfType('experience')->get()->keyBy('id');
        $experience = [];
        foreach ((array) ($parsed['experience'] ?? []) as $row) {
            $id = (int) ($row['item_id'] ?? 0);
            if (!$id || !$expCurrent->has($id)) continue;
            $item = $expCurrent[$id];
            $data = is_array($item->data) ? $item->data : [];
            $current = trim((string) ($data['description'] ?? ''));
            $next    = trim((string) ($row['suggested'] ?? ''));
            if ($next === '' || $next === $current) continue;
            $experience[] = [
                'item_id'   => $id,
                'role'      => (string) ($data['role'] ?? ''),
                'company'   => (string) ($data['company'] ?? ''),
                'current'   => $current,
                'suggested' => $next,
                'rationale' => trim((string) ($row['rationale'] ?? '')),
            ];
        }

        $existingSkills = $resume->itemsOfType('skills')->get()
            ->map(fn(ResumeSectionItem $i) => mb_strtolower(trim((string) ((is_array($i->data) ? $i->data : [])['name'] ?? ''))))
            ->filter()
            ->all();
        $skillAdditions = [];
        foreach ((array) ($parsed['skills']['additions'] ?? []) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') continue;
            if (in_array(mb_strtolower($name), $existingSkills, true)) continue;
            $skillAdditions[] = [
                'name'      => $name,
                'rationale' => trim((string) ($row['rationale'] ?? '')),
            ];
        }

        $keywords = [];
        foreach ((array) ($parsed['keywords'] ?? []) as $kw) {
            $kw = trim((string) $kw);
            if ($kw !== '') $keywords[] = $kw;
        }
        $keywords = array_values(array_unique($keywords));

        return [
            'summary'    => $summary,
            'experience' => $experience,
            'skills'     => ['additions' => $skillAdditions],
            'keywords'   => $keywords,
        ];
    }
}
