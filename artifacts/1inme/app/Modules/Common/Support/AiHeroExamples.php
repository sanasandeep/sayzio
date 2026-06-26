<?php

namespace App\Modules\Common\Support;

/**
 * Single source of truth for the example pages the homepage AI-builder demo
 * (home.partials.ai-hero) cycles through. These mirror the kind of biolink the
 * real AI builder produces (a profile card, a few link cards, a gallery) so the
 * marketing demo stays honest as the builder evolves.
 *
 * Both the resting/no-JS markup and the JS cycle read from here, so adding or
 * removing an example is a one-line data change — no markup edit. The FIRST
 * entry is the resting/final state the page shows without JS or under reduced
 * motion, so keep it photo-backed (avatar `img` + gallery `imgs`).
 *
 * Shape per example:
 *   prompt  string  the typed AI prompt
 *   name    string  profile name
 *   tag     string  profile tagline
 *   time    string  "page built in" time chip (e.g. "18s")
 *   avatar  array   one of { img: url } or { icon: 'fa-...' }
 *   links   array   up to 3 { icon, label, color, rating? }
 *   gallery array   one of { imgs: [url,url,url] } or { tiles: [{icon,color}] }
 */
class AiHeroExamples
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            [
                'prompt' => 'A link page for my coffee brand with shop, menu & reviews',
                'name'   => 'Daybreak Coffee',
                'tag'    => 'Roasted fresh · shipped daily',
                'time'   => '18s',
                'avatar' => ['img' => asset('images/marketing/ai-hero/avatar.webp')],
                'links'  => [
                    ['icon' => 'fa-store', 'label' => 'Shop the beans', 'color' => 'var(--c2)'],
                    ['icon' => 'fa-book-open', 'label' => 'See the menu', 'color' => 'var(--c1)'],
                    ['icon' => 'fa-star', 'label' => 'Read reviews', 'color' => 'var(--c5)', 'rating' => '4.9'],
                ],
                'gallery' => ['imgs' => [
                    asset('images/marketing/ai-hero/gallery-latte.webp'),
                    asset('images/marketing/ai-hero/gallery-beans.webp'),
                    asset('images/marketing/ai-hero/gallery-pastry.webp'),
                ]],
            ],
            [
                'prompt' => 'A coaching page for my fitness business with bookings & reviews',
                'name'   => 'Mia Strong',
                'tag'    => '1:1 coaching · online & in person',
                'time'   => '16s',
                'avatar' => ['img' => asset('images/marketing/ai-hero/avatar-fitness.webp')],
                'links'  => [
                    ['icon' => 'fa-calendar-check', 'label' => 'Book a session', 'color' => 'var(--c1)'],
                    ['icon' => 'fa-dumbbell', 'label' => 'Free workout plan', 'color' => 'var(--c2)'],
                    ['icon' => 'fa-star', 'label' => 'Client reviews', 'color' => 'var(--c5)', 'rating' => '5.0'],
                ],
                'gallery' => ['imgs' => [
                    asset('images/marketing/ai-hero/gallery-workout.webp'),
                    asset('images/marketing/ai-hero/gallery-meal.webp'),
                    asset('images/marketing/ai-hero/gallery-gym.webp'),
                ]],
            ],
            [
                'prompt' => 'A page for my music with new songs, tour dates & merch',
                'name'   => 'Lyra Vale',
                'tag'    => 'Indie folk · new single out now',
                'time'   => '15s',
                'avatar' => ['img' => asset('images/marketing/ai-hero/avatar-music.webp')],
                'links'  => [
                    ['icon' => 'fa-play', 'label' => 'Listen now', 'color' => 'var(--c5)'],
                    ['icon' => 'fa-calendar-day', 'label' => 'Tour dates', 'color' => 'var(--c1)'],
                    ['icon' => 'fa-shirt', 'label' => 'Shop merch', 'color' => 'var(--c2)'],
                ],
                'gallery' => ['imgs' => [
                    asset('images/marketing/ai-hero/gallery-live.webp'),
                    asset('images/marketing/ai-hero/gallery-vinyl.webp'),
                    asset('images/marketing/ai-hero/gallery-studio.webp'),
                ]],
            ],
            [
                'prompt' => 'A page for my restaurant with menu, bookings & directions',
                'name'   => 'Olive & Ember',
                'tag'    => 'Wood-fired · open every night',
                'time'   => '21s',
                'avatar' => ['icon' => 'fa-utensils'],
                'links'  => [
                    ['icon' => 'fa-book-open', 'label' => 'View the menu', 'color' => 'var(--c5)'],
                    ['icon' => 'fa-calendar-check', 'label' => 'Book a table', 'color' => 'var(--c2)'],
                    ['icon' => 'fa-location-dot', 'label' => 'Get directions', 'color' => 'var(--c1)'],
                ],
                'gallery' => ['tiles' => [
                    ['icon' => 'fa-pizza-slice', 'color' => 'var(--c5)'],
                    ['icon' => 'fa-wine-glass', 'color' => 'var(--c2)'],
                    ['icon' => 'fa-fire', 'color' => 'var(--c1)'],
                ]],
            ],
        ];
    }
}
