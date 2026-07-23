<?php

namespace App\Services\AI\Builder;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\Resume;
use App\Modules\User\Models\ResumeSectionItem;
use App\Modules\User\Models\User;

/**
 * AI builder for the Resume / Portfolio link type.
 *
 * Materializes into the user's default resume (the record the resume link
 * bridges to): fills the header + summary sections and replaces the section
 * items for every section the model provided.
 */
class AiResumeBuilderService extends AbstractAiTypeBuilderService
{
    public const FEATURE = 'resume_builder';

    public const MAX_ITEMS_PER_SECTION = 20;

    /** Sections the builder may write (custom sections stay hand-managed). */
    public const SECTION_TYPES = [
        'experience', 'education', 'skills', 'projects',
        'certifications', 'awards', 'languages', 'links',
    ];

    public function feature(): string  { return self::FEATURE; }
    public function linkType(): string { return Link::TYPE_RESUME; }
    public function label(): string    { return 'AI Resume builder'; }

    public function supportsImages(): bool
    {
        return false;
    }

    protected function systemPrompt(User $user): string
    {
        return <<<'PROMPT'
You are a professional resume writer. Answer with ONE JSON object only — no prose, no markdown fences.

Schema:
{
  "header": {"name": "...", "headline": "...", "location": "...", "email": "...", "phone": "...", "website": "..."},
  "summary": "2-4 sentence professional summary",
  "sections": {
    "experience":     [{"company": "...", "role": "...", "location": "...", "start_date": "YYYY-MM", "end_date": "YYYY-MM or omit", "is_current": false, "description": "..."}],
    "education":      [{"school": "...", "degree": "...", "field": "...", "start_date": "YYYY-MM", "end_date": "YYYY-MM", "description": "..."}],
    "skills":         [{"name": "...", "level": 1-5, "group": "..."}],
    "projects":       [{"name": "...", "role": "...", "url": "ONLY a supplied URL", "description": "...", "start_date": "YYYY-MM", "end_date": "YYYY-MM"}],
    "certifications": [{"name": "...", "issuer": "...", "issued_on": "YYYY-MM", "credential_url": "ONLY a supplied URL"}],
    "awards":         [{"title": "...", "issuer": "...", "date": "YYYY-MM", "description": "..."}],
    "languages":      [{"name": "...", "proficiency": "basic|conversational|professional|fluent|native"}],
    "links":          [{"label": "...", "url": "ONLY a supplied URL", "icon": "..."}]
  }
}

Rules:
- Only include sections you have real material for; omit empty ones.
- All dates use "YYYY-MM". Never invent employers, degrees, or credentials not implied by the brief.
- Only use URLs the user explicitly supplied; keep them EXACTLY as given. Omit url fields otherwise.
- Write specific, achievement-oriented copy from the brief — never lorem ipsum.
PROMPT;
    }

    protected function materialize(User $user, Link $link, array $parsed, array $links, array $images): array
    {
        $resume = $user->ensureResume();

        $sections = $resume->getMergedSections();
        $headerIn = is_array($parsed['header'] ?? null) ? $parsed['header'] : [];
        foreach (['name' => 160, 'headline' => 200, 'location' => 160, 'email' => 160, 'phone' => 60, 'website' => 255] as $key => $max) {
            $value = $this->str($headerIn[$key] ?? null, $max);
            if ($value !== null) {
                $sections['header'][$key] = $value;
            }
        }
        $summary = $this->str($parsed['summary'] ?? null, 2000);
        if ($summary !== null) {
            $sections['summary'] = $summary;
        }

        $sectionsIn = is_array($parsed['sections'] ?? null) ? $parsed['sections'] : [];
        $itemCount = 0;
        $writtenSections = [];

        foreach (self::SECTION_TYPES as $type) {
            $itemsIn = is_array($sectionsIn[$type] ?? null) ? $sectionsIn[$type] : [];
            $rows = [];
            foreach (array_slice(array_values(array_filter($itemsIn, 'is_array')), 0, self::MAX_ITEMS_PER_SECTION) as $itemIn) {
                $data = $this->cleanItem($type, $itemIn, $links);
                if ($data !== null) {
                    $rows[] = $data;
                }
            }
            if (!$rows) continue;

            // Replace this section wholesale with the generated items.
            $resume->itemsOfType($type)->delete();
            foreach ($rows as $pos => $data) {
                ResumeSectionItem::create([
                    'resume_id'    => $resume->id,
                    'section_type' => $type,
                    'position'     => $pos,
                    'data'         => $data,
                ]);
                $itemCount++;
            }
            $writtenSections[] = $type;
        }

        if ($itemCount === 0 && $summary === null) {
            throw new \RuntimeException('The AI response contained no usable resume content. Your coins were refunded — please try again.');
        }

        $resume->update(['sections' => $sections]);

        return ['sections' => count($writtenSections), 'items' => $itemCount];
    }

    /** Sanitize one section item; null when its required fields are missing. */
    private function cleanItem(string $type, array $in, array $links): ?array
    {
        $ym  = fn ($v) => (is_string($v) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', trim($v))) ? trim($v) : null;
        $url = fn ($v) => (is_string($v) && in_array(trim($v), $links, true)) ? trim($v) : null;

        switch ($type) {
            case 'experience':
                $company = $this->str($in['company'] ?? null, 160);
                $role    = $this->str($in['role'] ?? null, 160);
                if ($company === null || $role === null) return null;
                return array_filter([
                    'company'     => $company,
                    'role'        => $role,
                    'location'    => $this->str($in['location'] ?? null, 160),
                    'start_date'  => $ym($in['start_date'] ?? null),
                    'end_date'    => $ym($in['end_date'] ?? null),
                    'is_current'  => (bool) ($in['is_current'] ?? false),
                    'description' => $this->str($in['description'] ?? null, 2000),
                ], fn ($v) => $v !== null);
            case 'education':
                $school = $this->str($in['school'] ?? null, 160);
                if ($school === null) return null;
                return array_filter([
                    'school'      => $school,
                    'degree'      => $this->str($in['degree'] ?? null, 160),
                    'field'       => $this->str($in['field'] ?? null, 160),
                    'start_date'  => $ym($in['start_date'] ?? null),
                    'end_date'    => $ym($in['end_date'] ?? null),
                    'description' => $this->str($in['description'] ?? null, 1000),
                ], fn ($v) => $v !== null);
            case 'skills':
                $name = $this->str($in['name'] ?? null, 80);
                if ($name === null) return null;
                $level = is_numeric($in['level'] ?? null) ? (int) $in['level'] : null;
                return array_filter([
                    'name'  => $name,
                    'level' => ($level !== null && $level >= 1 && $level <= 5) ? $level : null,
                    'group' => $this->str($in['group'] ?? null, 80),
                ], fn ($v) => $v !== null);
            case 'projects':
                $name = $this->str($in['name'] ?? null, 160);
                if ($name === null) return null;
                return array_filter([
                    'name'        => $name,
                    'role'        => $this->str($in['role'] ?? null, 160),
                    'url'         => $url($in['url'] ?? null),
                    'description' => $this->str($in['description'] ?? null, 2000),
                    'start_date'  => $ym($in['start_date'] ?? null),
                    'end_date'    => $ym($in['end_date'] ?? null),
                ], fn ($v) => $v !== null);
            case 'certifications':
                $name = $this->str($in['name'] ?? null, 160);
                if ($name === null) return null;
                return array_filter([
                    'name'           => $name,
                    'issuer'         => $this->str($in['issuer'] ?? null, 160),
                    'issued_on'      => $ym($in['issued_on'] ?? null),
                    'expires_on'     => $ym($in['expires_on'] ?? null),
                    'credential_url' => $url($in['credential_url'] ?? null),
                ], fn ($v) => $v !== null);
            case 'awards':
                $title = $this->str($in['title'] ?? null, 160);
                if ($title === null) return null;
                return array_filter([
                    'title'       => $title,
                    'issuer'      => $this->str($in['issuer'] ?? null, 160),
                    'date'        => $ym($in['date'] ?? null),
                    'description' => $this->str($in['description'] ?? null, 1000),
                ], fn ($v) => $v !== null);
            case 'languages':
                $name = $this->str($in['name'] ?? null, 80);
                if ($name === null) return null;
                $prof = is_string($in['proficiency'] ?? null) ? strtolower(trim($in['proficiency'])) : null;
                return array_filter([
                    'name'        => $name,
                    'proficiency' => in_array($prof, ['basic', 'conversational', 'professional', 'fluent', 'native'], true) ? $prof : null,
                ], fn ($v) => $v !== null);
            case 'links':
                $label   = $this->str($in['label'] ?? null, 80);
                $linkUrl = $url($in['url'] ?? null);
                if ($label === null || $linkUrl === null) return null;
                return array_filter([
                    'label' => $label,
                    'url'   => $linkUrl,
                    'icon'  => $this->str($in['icon'] ?? null, 40),
                ], fn ($v) => $v !== null);
        }

        return null;
    }
}
