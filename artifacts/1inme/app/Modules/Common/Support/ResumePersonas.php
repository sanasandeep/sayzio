<?php

namespace App\Modules\Common\Support;

/**
 * Single source of truth for the personas the homepage résumé-builder demo
 * (home.partials.resume) cycles through. Mirrors Common\Support\AiHeroExamples:
 * the résumé "watch it build" animation rotates through a few professions
 * (designer, developer, marketer, student) so the demo shows the builder works
 * for any career, not just one.
 *
 * Both the resting/no-JS markup and the JS build cycle read from here, so adding
 * or removing a persona is a one-line data change — no markup edit. The FIRST
 * entry is the resting/final state the page shows without JS or under reduced
 * motion, so keep it complete and representative.
 *
 * Shape per persona:
 *   initials    string    avatar monogram (1–2 chars)
 *   name        string    full name
 *   role        string    job title (used in the header subtitle AND the
 *                         Experience block title)
 *   location    string    city / institution shown after the role
 *   tags        string[]  up to 4 header skill chips
 *   company     string    Experience block company + dates line
 *   experience  string    the AI-written experience bullet (typed out live)
 *   skills      array     exactly 3 { label, value } skill bars (value 0–100)
 */
class ResumePersonas
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            [
                'initials'   => 'MA',
                'name'       => 'Maya Anders',
                'role'       => 'Senior Product Designer',
                'location'   => 'Berlin',
                'tags'       => ['Figma', 'Design Systems', 'Prototyping', 'UX Research'],
                'company'    => 'Linear · 2023 — Now',
                'experience' => 'Shipped onboarding redesign, +28% activation. Led design system across 4 squads.',
                'skills'     => [
                    ['label' => 'Product design', 'value' => 95],
                    ['label' => 'Design systems', 'value' => 88],
                    ['label' => 'Front-end (React)', 'value' => 72],
                ],
            ],
            [
                'initials'   => 'DR',
                'name'       => 'Diego Ramos',
                'role'       => 'Full-Stack Developer',
                'location'   => 'Lisbon',
                'tags'       => ['TypeScript', 'React', 'Node.js', 'AWS'],
                'company'    => 'Stripe · 2022 — Now',
                'experience' => 'Cut API latency 40% and shipped a payments SDK used by 12k developers.',
                'skills'     => [
                    ['label' => 'TypeScript', 'value' => 94],
                    ['label' => 'System design', 'value' => 86],
                    ['label' => 'DevOps (AWS)', 'value' => 70],
                ],
            ],
            [
                'initials'   => 'AK',
                'name'       => 'Aisha Khan',
                'role'       => 'Growth Marketing Lead',
                'location'   => 'Toronto',
                'tags'       => ['SEO', 'Paid Ads', 'Lifecycle', 'Analytics'],
                'company'    => 'Notion · 2021 — Now',
                'experience' => 'Scaled organic signups 3× and ran paid campaigns at a 4.2 ROAS.',
                'skills'     => [
                    ['label' => 'Performance marketing', 'value' => 92],
                    ['label' => 'SEO & content', 'value' => 85],
                    ['label' => 'Data & analytics', 'value' => 78],
                ],
            ],
            [
                'initials'   => 'LM',
                'name'       => 'Leo Martin',
                'role'       => 'Computer Science Student',
                'location'   => 'Austin',
                'tags'       => ['Python', 'Java', 'Git', 'Teamwork'],
                'company'    => 'UT Austin · Class of 2026',
                'experience' => 'Built a campus events app with 2k users and led a 5-person hackathon team.',
                'skills'     => [
                    ['label' => 'Python', 'value' => 88],
                    ['label' => 'Algorithms', 'value' => 82],
                    ['label' => 'Teamwork', 'value' => 90],
                ],
            ],
        ];
    }
}
