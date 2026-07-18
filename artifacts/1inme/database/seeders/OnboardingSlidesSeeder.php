<?php

namespace Database\Seeders;

use App\Modules\Admin\Models\OnboardingSlide;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds the AI-business-companion intro slides for the mobile splash slider.
 * Idempotent: re-running won't overwrite slides admin has edited (firstOrCreate
 * by slug).
 *
 * Story arc (5 slides):
 *   1. welcome          — introduce Sayzio as the AI-powered companion
 *   2. every-business   — breadth of business types served
 *   3. everything-you-need — 9 business tools in one place (icon grid)
 *   4. work-smarter     — AI automation value prop
 *   5. start-free       — call to action / get started
 */
class OnboardingSlidesSeeder extends Seeder
{
    /**
     * The canonical default copy for every intro slide, keyed positionally
     * (each row carries its own `slug`). This is the single source of truth
     * for both:
     *   - seeding fresh installs (see run()), and
     *   - detecting when an admin has edited a live slide away from its
     *     shipped default (see OnboardingSlide::customizationState()).
     *
     * Image discovery lives in run() — this method only owns the wording so
     * the mobile bundled fallback, the seeder and the drift check all agree.
     *
     * @return array<int, array{slug:string, sort_order:int, category:string, title:string, body:string}>
     */
    public static function defaults(): array
    {
        $slides = [
            // ── 1. Brand intro ──────────────────────────────────────────────
            [
                'slug'       => 'welcome',
                'sort_order' => 10,
                'category'   => 'Welcome',
                'title'      => 'Meet Zio',
                'body'       => 'Your AI-powered business companion for smarter customer engagement.',
            ],
            // ── 2. Audience breadth ─────────────────────────────────────────
            [
                'slug'       => 'every-business',
                'sort_order' => 20,
                'category'   => 'Built for Every Business',
                'title'      => 'One Platform. Every Business.',
                'body'       => "Whether you're a startup, coach, restaurant, retailer, clinic, freelancer, or nonprofit, Sayzio helps you connect and grow.",
            ],
            // ── 3. Tools grid ───────────────────────────────────────────────
            [
                'slug'       => 'everything-you-need',
                'sort_order' => 30,
                'category'   => 'Everything You Need',
                'title'      => 'All Your Business Tools in One Place',
                'body'       => null,
            ],
            // ── 4. AI value prop ────────────────────────────────────────────
            [
                'slug'       => 'work-smarter',
                'sort_order' => 40,
                'category'   => 'Work Smarter',
                'title'      => 'Automate. Engage. Grow.',
                'body'       => 'Let AI answer questions, capture leads, schedule appointments, and support customers 24/7.',
            ],
            // ── 5. Call to action (final slide) ─────────────────────────────
            [
                'slug'       => 'start-free',
                'sort_order' => 50,
                'category'   => 'Start Free',
                'title'      => 'Ready to Grow?',
                'body'       => "Create your free workspace in minutes and upgrade whenever you're ready.",
            ],
        ];

        return $slides;
    }

    public function run(): void
    {
        $slides = self::defaults();

        foreach ($slides as $row) {
            // Primary background image (legacy single image).
            $imagePath = "onboarding/{$row['slug']}.png";
            $hasImage  = Storage::disk('public')->exists($imagePath);

            // Gallery: <slug>.png + <slug>_2.png + <slug>_3.png if present.
            $gallery = [];
            foreach ([
                "onboarding/{$row['slug']}.png",
                "onboarding/{$row['slug']}_2.png",
                "onboarding/{$row['slug']}_3.png",
            ] as $candidate) {
                if (Storage::disk('public')->exists($candidate)) {
                    $gallery[] = $candidate;
                }
            }

            $slide = OnboardingSlide::firstOrCreate(
                ['slug' => $row['slug']],
                [
                    'category'       => $row['category'],
                    'title'          => $row['title'],
                    'body'           => $row['body'],
                    'image_path'     => $hasImage ? $imagePath : null,
                    'gallery_images' => !empty($gallery) ? $gallery : null,
                    'status'         => 'active',
                    'sort_order'     => $row['sort_order'],
                ],
            );

            // Backfill the gallery on existing rows that pre-date the
            // gallery feature so the demo data shows the slider.
            if (empty($slide->gallery_images) && !empty($gallery)) {
                $slide->update(['gallery_images' => $gallery]);
            }
        }
    }
}
