<?php

namespace Database\Seeders;

use App\Modules\Admin\Models\OnboardingSlide;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds the default audience-focused onboarding slides for the mobile
 * splash slider. Idempotent: re-running won't overwrite slides admin
 * has edited (firstOrCreate by slug).
 *
 * Story arc:
 *   1. Brand intro  (welcome)     — introduces Sayzio
 *   2. Personas     (creators … coaches) — "this is for me"
 *   3. Breadth      (platform)    — everything Sayzio can do
 *   4. Value        (grow)        — analytics & outcomes
 *   5. CTA          (get-started) — clear call to action (final slide)
 *
 * The matching photographs are pre-generated and live on the public
 * disk under onboarding/<slug>.png (served via storage:link). If a
 * file is missing the slide is still created — admin can upload one
 * later from the admin UI.
 */
class OnboardingSlidesSeeder extends Seeder
{
    public function run(): void
    {
        $slides = [
            // ── 1. Brand intro ──────────────────────────────────────────────
            [
                'slug'       => 'welcome',
                'sort_order' => 5,
                'category'   => 'Welcome to Sayzio',
                'title'      => 'One link for everything you do',
                'body'       => 'Sayzio turns all your links, content and channels into a single smart profile people can tap, save and share — online or in person.',
            ],
            // ── 2. Personas ─────────────────────────────────────────────────
            [
                'slug'       => 'creators',
                'sort_order' => 10,
                'category'   => 'For creators',
                'title'      => 'Every link, every channel — one tap away',
                'body'       => 'Bundle your latest video, store, sponsorships and socials into a single biolink your audience can save, share, or tap.',
            ],
            [
                'slug'       => 'business',
                'sort_order' => 20,
                'category'   => 'For small businesses',
                'title'      => 'Your menu, hours and reviews on the counter',
                'body'       => 'Stick a Sayzio NFC tag at the till. Customers tap their phone to see your menu, opening hours, directions and leave a review — no app needed.',
            ],
            [
                'slug'       => 'freelancer',
                'sort_order' => 30,
                'category'   => 'For freelancers',
                'title'      => 'Pitch your portfolio in one link',
                'body'       => 'Send one tidy Sayzio profile instead of five attachments. Show case studies, rates and a booking link, and see exactly who clicked what.',
            ],
            [
                'slug'       => 'networker',
                'sort_order' => 40,
                'category'   => 'For networkers',
                'title'      => 'Replace your business card',
                'body'       => "Tap a Sayzio NFC card to share contact, LinkedIn, calendar and portfolio in seconds — and the other person doesn't need to install anything.",
            ],
            [
                'slug'       => 'students',
                'sort_order' => 50,
                'category'   => 'For students & job seekers',
                'title'      => 'One link for your CV, projects and socials',
                'body'       => 'Hand recruiters a single Sayzio link with your résumé, GitHub, portfolio and contact info — and watch which sections they actually open.',
            ],
            [
                'slug'       => 'coaches',
                'sort_order' => 60,
                'category'   => 'For coaches & educators',
                'title'      => 'Sell, schedule and stay in touch',
                'body'       => 'Group your courses, booking calendar, payment links and follower updates in one biolink — and broadcast announcements to everyone who follows you.',
            ],
            // ── 3. Breadth ──────────────────────────────────────────────────
            [
                'slug'       => 'platform',
                'sort_order' => 70,
                'category'   => 'One platform, endless possibilities',
                'title'      => 'Biolinks, QR codes, stores, forms and more',
                'body'       => 'Build a menu, sell products, collect leads, share a résumé or spin up a QR code — all from one Sayzio account, no extra tools needed.',
            ],
            // ── 4. Value & outcomes ─────────────────────────────────────────
            [
                'slug'       => 'grow',
                'sort_order' => 80,
                'category'   => 'Grow with confidence',
                'title'      => 'See what works and turn taps into results',
                'body'       => 'Real-time analytics show who\'s clicking, from where and what they love — so you can grow your audience, sales and reach with data on your side.',
            ],
            // ── 5. Call to action (final slide) ─────────────────────────────
            [
                'slug'       => 'get-started',
                'sort_order' => 90,
                'category'   => 'Ready when you are',
                'title'      => 'Your link is one tap away',
                'body'       => 'Create your free Sayzio profile in minutes — no code, no clutter. Let\'s build it together.',
            ],
        ];

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
