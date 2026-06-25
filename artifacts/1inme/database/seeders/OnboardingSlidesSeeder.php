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
            [
                'slug'     => 'creators',
                'category' => 'For creators',
                'title'    => 'Every link, every channel — one tap away',
                'body'     => 'Bundle your latest video, store, sponsorships and socials into a single biolink your audience can save, share, or tap.',
            ],
            [
                'slug'     => 'business',
                'category' => 'For small businesses',
                'title'    => 'Your menu, hours and reviews on the counter',
                'body'     => 'Stick a Sayzio NFC tag at the till. Customers tap their phone to see your menu, opening hours, directions and leave a review — no app needed.',
            ],
            [
                'slug'     => 'freelancer',
                'category' => 'For freelancers',
                'title'    => 'Pitch your portfolio in one link',
                'body'     => 'Send one tidy Sayzio profile instead of five attachments. Show case studies, rates and a booking link, and see exactly who clicked what.',
            ],
            [
                'slug'     => 'networker',
                'category' => 'For networkers',
                'title'    => 'Replace your business card',
                'body'     => "Tap a Sayzio NFC card to share contact, LinkedIn, calendar and portfolio in seconds — and the other person doesn't need to install anything.",
            ],
            [
                'slug'     => 'students',
                'category' => 'For students & job seekers',
                'title'    => 'One link for your CV, projects and socials',
                'body'     => 'Hand recruiters a single Sayzio link with your résumé, GitHub, portfolio and contact info — and watch which sections they actually open.',
            ],
            [
                'slug'     => 'coaches',
                'category' => 'For coaches & educators',
                'title'    => 'Sell, schedule and stay in touch',
                'body'     => 'Group your courses, booking calendar, payment links and follower updates in one biolink — and broadcast announcements to everyone who follows you.',
            ],
        ];

        foreach ($slides as $i => $row) {
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
                    'sort_order'     => ($i + 1) * 10,
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
