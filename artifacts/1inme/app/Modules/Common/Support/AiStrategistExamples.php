<?php

namespace App\Modules\Common\Support;

/**
 * Single source of truth for the example goals the homepage AI Marketing
 * Strategist demo (home.partials.ai-marketing-strategist) cycles through.
 * Each example is a different goal with its own organic + paid plan and KPIs,
 * mirroring the kind of plan the real strategist writes so the marketing demo
 * stays honest as the strategist evolves.
 *
 * Both the resting/no-JS markup and the JS cycle read from here, so adding or
 * removing an example is a one-line data change — no markup edit. The FIRST
 * entry is the resting/final state the card shows without JS or under reduced
 * motion. Mirrors Common\Support\AiHeroExamples.
 *
 * Shape per example (FIXED slot counts so the card DOM is stable and the JS
 * can swap content in place — 3 organic plays + 2 paid plays):
 *   goal     string   the typed goal line
 *   head     string   the plan heading
 *   organic  array    { tag: string, items: [{icon, text} ×3] }
 *   paid     array    { tag: string, items: [{icon, text} ×2] }
 *   kpi      string   the KPI list rendered after "KPIs → "
 *
 * `icon` is a full Font Awesome class string (e.g. "fas fa-link" or
 * "fab fa-facebook") so brand and solid icons can mix.
 */
class AiStrategistExamples
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            [
                'goal' => 'Grow my email & WhatsApp subscribers from my Link in Bio',
                'head' => 'Turn bio traffic into owned subscribers',
                'organic' => [
                    'tag'   => 'Organic',
                    'items' => [
                        ['icon' => 'fas fa-link', 'text' => 'Add a "Free guide" subscribe block above the fold'],
                        ['icon' => 'fas fa-stream', 'text' => 'Schedule 3 Creator Feed posts a week'],
                        ['icon' => 'fas fa-qrcode', 'text' => 'A print-ready QR code on every touchpoint'],
                    ],
                ],
                'paid' => [
                    'tag'   => 'Paid · $5–10/day',
                    'items' => [
                        ['icon' => 'fab fa-facebook', 'text' => 'Retarget visitors via your connected Meta pixel'],
                        ['icon' => 'fas fa-bullseye', 'text' => 'Send every click to the same lead-magnet page'],
                    ],
                ],
                'kpi' => 'subscriber growth · click-through · cost per lead',
            ],
            [
                'goal' => 'Sell more of my products straight from my bio',
                'head' => 'Turn browsers into buyers',
                'organic' => [
                    'tag'   => 'Organic',
                    'items' => [
                        ['icon' => 'fas fa-bag-shopping', 'text' => 'Feature bestsellers in a native Product block'],
                        ['icon' => 'fas fa-tag', 'text' => 'Run a limited-time coupon on your store page'],
                        ['icon' => 'fas fa-star', 'text' => 'Surface a reviews wall to build trust'],
                    ],
                ],
                'paid' => [
                    'tag'   => 'Paid · $10–20/day',
                    'items' => [
                        ['icon' => 'fab fa-instagram', 'text' => 'Boost your top product post to lookalikes'],
                        ['icon' => 'fas fa-rotate', 'text' => 'Retarget cart abandoners with a dynamic ad'],
                    ],
                ],
                'kpi' => 'orders · conversion rate · return on ad spend',
            ],
            [
                'goal' => 'Get more bookings for my coaching sessions',
                'head' => 'Fill your calendar on autopilot',
                'organic' => [
                    'tag'   => 'Organic',
                    'items' => [
                        ['icon' => 'fas fa-calendar-check', 'text' => 'Pin a Calendly booking block up top'],
                        ['icon' => 'fas fa-video', 'text' => 'Post a weekly tip to your Creator Feed'],
                        ['icon' => 'fas fa-envelope-open-text', 'text' => 'Send a follow-up to every new subscriber'],
                    ],
                ],
                'paid' => [
                    'tag'   => 'Paid · $8–15/day',
                    'items' => [
                        ['icon' => 'fab fa-google', 'text' => 'Run a search ad for your service + city'],
                        ['icon' => 'fas fa-location-crosshairs', 'text' => 'Geo-target ads to your service area'],
                    ],
                ],
                'kpi' => 'booked calls · show-up rate · cost per booking',
            ],
            [
                'goal' => 'Grow my audience and turn fans into followers',
                'head' => 'Convert one-time visitors into a fanbase',
                'organic' => [
                    'tag'   => 'Organic',
                    'items' => [
                        ['icon' => 'fas fa-user-plus', 'text' => 'Add a one-tap Follow button to your page'],
                        ['icon' => 'fas fa-clapperboard', 'text' => 'Cross-post Reels into a video gallery'],
                        ['icon' => 'fas fa-gift', 'text' => 'Offer a freebie for joining your list'],
                    ],
                ],
                'paid' => [
                    'tag'   => 'Paid · $5–12/day',
                    'items' => [
                        ['icon' => 'fab fa-tiktok', 'text' => 'Promote your best clip to a lookalike audience'],
                        ['icon' => 'fas fa-arrows-rotate', 'text' => 'Retarget profile visitors with a follow CTA'],
                    ],
                ],
                'kpi' => 'new followers · engagement rate · cost per follow',
            ],
        ];
    }
}
