<?php

namespace App\Modules\User\Services;

/**
 * Registry of visual templates available for the Resume / Portfolio
 * builder. Each template advertises which sections it natively supports
 * so the editor can hide section pickers a template can't lay out, and
 * the public renderer / PDF exporter can read from one source of truth.
 *
 * Templates are intentionally just metadata + a thumbnail path here —
 * the actual layout lives in Blade partials added by the renderer task.
 */
class ResumeTemplateRegistry
{
    /**
     * Section keys that any template MAY render. The editor uses this
     * to validate user input; templates whitelist a subset.
     */
    public const ALL_SECTIONS = [
        'header', 'summary', 'experience', 'education', 'skills',
        'projects', 'certifications', 'awards', 'languages', 'links',
        'custom',
    ];

    /** @return array<int, array<string,mixed>> */
    public static function all(): array
    {
        return [
            [
                'id'          => 'classic',
                'name'        => 'Classic',
                'description' => 'Traditional single-column layout with serif headings — the safe choice for formal applications.',
                'thumbnail'   => '/img/resume-templates/classic.svg',
                'sections'    => ['header', 'summary', 'experience', 'education', 'skills', 'certifications', 'awards', 'languages', 'links', 'custom'],
                'style'       => ['layout' => 'single', 'headings' => 'serif', 'density' => 'comfortable'],
            ],
            [
                'id'          => 'modern',
                'name'        => 'Modern',
                'description' => 'Two-column layout with a sidebar for skills and contact info — clean and recruiter-friendly.',
                'thumbnail'   => '/img/resume-templates/modern.svg',
                'sections'    => ['header', 'summary', 'experience', 'education', 'skills', 'projects', 'certifications', 'awards', 'languages', 'links', 'custom'],
                'style'       => ['layout' => 'sidebar', 'headings' => 'sans', 'density' => 'comfortable'],
            ],
            [
                'id'          => 'compact',
                'name'        => 'Compact',
                'description' => 'Tight typography that fits a long career on a single page — great for senior CVs.',
                'thumbnail'   => '/img/resume-templates/compact.svg',
                'sections'    => ['header', 'summary', 'experience', 'education', 'skills', 'certifications', 'awards', 'languages', 'links', 'custom'],
                'style'       => ['layout' => 'single', 'headings' => 'sans', 'density' => 'tight'],
            ],
            [
                'id'          => 'creative',
                'name'        => 'Creative',
                'description' => 'Portfolio-first layout with a featured projects strip — perfect for designers, photographers, and creators.',
                'thumbnail'   => '/img/resume-templates/creative.svg',
                'sections'    => ['header', 'summary', 'projects', 'experience', 'skills', 'education', 'awards', 'languages', 'links', 'custom'],
                'style'       => ['layout' => 'portfolio', 'headings' => 'display', 'density' => 'spacious'],
            ],
        ];
    }

    /** @return array<int, string> */
    public static function ids(): array
    {
        return array_column(self::all(), 'id');
    }

    public static function find(?string $id): ?array
    {
        if (!$id) return null;
        foreach (self::all() as $tpl) {
            if ($tpl['id'] === $id) return $tpl;
        }
        return null;
    }

    public static function isValid(?string $id): bool
    {
        return $id !== null && in_array($id, self::ids(), true);
    }

    public static function defaultId(): string
    {
        return 'classic';
    }

    /**
     * Templates available to a particular user, honoring the
     * `resume.templates` plan-feature key. Defaults to "all" so that
     * nothing is gated until product/billing decides to lock specific
     * templates behind paid tiers.
     *
     * Plan-feature value shapes accepted:
     *   - '*' / null / ''       → every template
     *   - array<string>         → whitelist of template ids
     *
     * Super admins always see every template (handled inside
     * User::getPlanFeature).
     *
     * @return array<int, array<string,mixed>>
     */
    public static function availableFor($user): array
    {
        $allowed = $user ? $user->getPlanFeature('resume.templates', '*') : '*';
        if ($allowed === '*' || $allowed === null || $allowed === '' || !is_array($allowed)) {
            return self::all();
        }
        return array_values(array_filter(
            self::all(),
            fn (array $t) => in_array($t['id'], $allowed, true)
        ));
    }

    public static function userCanUse($user, string $id): bool
    {
        foreach (self::availableFor($user) as $tpl) {
            if ($tpl['id'] === $id) return true;
        }
        return false;
    }
}
