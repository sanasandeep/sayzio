<?php

namespace App\Modules\Common\Support;

/**
 * Single source of truth for the example goals the homepage AI Marketing
 * Strategist demo (home.partials.ai-marketing-strategist) cycles through.
 * Each example is a different goal with its own organic + paid plan, a weekly
 * cadence and 30-day targets, mirroring the kind of in-depth report the real
 * strategist writes so the marketing demo stays honest as it evolves.
 *
 * Both the resting/no-JS markup and the JS cycle read from here, so adding or
 * removing an example is a one-line data change — no markup edit. The FIRST
 * entry is the resting/final state the card shows without JS or under reduced
 * motion. Mirrors Common\Support\AiHeroExamples.
 *
 * Shape per example (FIXED slot counts so the card DOM is stable and the JS
 * can swap content in place — 4 organic plays · 3 paid plays · 4 cadence
 * lines · 3 targets):
 *   goal     string   the typed goal line
 *   summary  string   a short diagnosis line under the goal
 *   head     string   the plan heading
 *   organic  array    { tag: string, items: [{icon, text} ×4] }
 *   paid     array    { tag: string (incl. budget), items: [{icon, text} ×3] }
 *   cadence  array    { tag: string, items: [{icon, text} ×4] }
 *   targets  array    { tag: string, items: [{icon, text} ×3] }
 *   kpi      string   the KPI list rendered after "KPIs → "
 *
 * `icon` is a full Font Awesome class string (e.g. "fas fa-link" or
 * "fab fa-facebook") so brand and solid icons can mix.
 *
 * If you change any slot count here, update the matching DOM rows in
 * home.partials.ai-marketing-strategist in lockstep — the JS fills rows in
 * place and relies on the counts matching.
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
                'summary' => 'Your bio gets steady traffic, but only ~2% subscribe — the funnel leaks before the ask.',
                'head' => 'Turn bio traffic into owned subscribers',
                'organic' => [
                    'tag'   => 'Organic',
                    'items' => [
                        ['icon' => 'fas fa-link', 'text' => 'Add a "Free guide" subscribe block above the fold'],
                        ['icon' => 'fas fa-stream', 'text' => 'Schedule 3 Creator Feed posts a week'],
                        ['icon' => 'fas fa-qrcode', 'text' => 'A print-ready QR code on every touchpoint'],
                        ['icon' => 'fas fa-envelope-open-text', 'text' => 'Auto-send a welcome email to every new subscriber'],
                    ],
                ],
                'paid' => [
                    'tag'   => 'Paid · $5–10/day',
                    'items' => [
                        ['icon' => 'fab fa-facebook', 'text' => 'Retarget visitors via your connected Meta pixel'],
                        ['icon' => 'fas fa-bullseye', 'text' => 'Send every click to the same lead-magnet page'],
                        ['icon' => 'fab fa-instagram', 'text' => 'Boost your best subscribe post to a lookalike'],
                    ],
                ],
                'cadence' => [
                    'tag'   => 'Weekly cadence',
                    'items' => [
                        ['icon' => 'fas fa-calendar-day', 'text' => 'Mon · Publish the lead-magnet post + story'],
                        ['icon' => 'fas fa-calendar-day', 'text' => 'Wed · Share a subscriber-only tip'],
                        ['icon' => 'fas fa-calendar-day', 'text' => 'Fri · Post social proof from a happy subscriber'],
                        ['icon' => 'fas fa-calendar-day', 'text' => 'Sun · Review signups & refresh the offer'],
                    ],
                ],
                'targets' => [
                    'tag'   => '30-day targets',
                    'items' => [
                        ['icon' => 'fas fa-arrow-trend-up', 'text' => '+500 new subscribers'],
                        ['icon' => 'fas fa-percent', 'text' => '5%+ bio-to-subscribe rate'],
                        ['icon' => 'fas fa-coins', 'text' => 'Under $1.50 cost per lead'],
                    ],
                ],
                'kpi' => 'subscriber growth · click-through · cost per lead',
            ],
            [
                'goal' => 'Sell more of my products straight from my bio',
                'summary' => 'Strong product views, but visitors bounce before checkout — trust and urgency are missing.',
                'head' => 'Turn browsers into buyers',
                'organic' => [
                    'tag'   => 'Organic',
                    'items' => [
                        ['icon' => 'fas fa-bag-shopping', 'text' => 'Feature bestsellers in a native Product block'],
                        ['icon' => 'fas fa-tag', 'text' => 'Run a limited-time coupon on your store page'],
                        ['icon' => 'fas fa-star', 'text' => 'Surface a reviews wall to build trust'],
                        ['icon' => 'fas fa-bolt', 'text' => 'Add a "only a few left" nudge to hot items'],
                    ],
                ],
                'paid' => [
                    'tag'   => 'Paid · $10–20/day',
                    'items' => [
                        ['icon' => 'fab fa-instagram', 'text' => 'Boost your top product post to lookalikes'],
                        ['icon' => 'fas fa-rotate', 'text' => 'Retarget cart abandoners with a dynamic ad'],
                        ['icon' => 'fab fa-facebook', 'text' => 'Run a catalog ad across your full range'],
                    ],
                ],
                'cadence' => [
                    'tag'   => 'Weekly cadence',
                    'items' => [
                        ['icon' => 'fas fa-calendar-day', 'text' => 'Mon · Drop a new product or restock teaser'],
                        ['icon' => 'fas fa-calendar-day', 'text' => 'Wed · Share a customer review + photo'],
                        ['icon' => 'fas fa-calendar-day', 'text' => 'Fri · Launch a weekend flash coupon'],
                        ['icon' => 'fas fa-calendar-day', 'text' => 'Sun · Review orders & restock bestsellers'],
                    ],
                ],
                'targets' => [
                    'tag'   => '30-day targets',
                    'items' => [
                        ['icon' => 'fas fa-arrow-trend-up', 'text' => '+30% orders this month'],
                        ['icon' => 'fas fa-percent', 'text' => '3%+ store conversion rate'],
                        ['icon' => 'fas fa-sack-dollar', 'text' => '3×+ return on ad spend'],
                    ],
                ],
                'kpi' => 'orders · conversion rate · return on ad spend',
            ],
            [
                'goal' => 'Get more bookings for my coaching sessions',
                'summary' => 'Plenty of profile visits, but few reach your booking page — the path to "book" is buried.',
                'head' => 'Fill your calendar on autopilot',
                'organic' => [
                    'tag'   => 'Organic',
                    'items' => [
                        ['icon' => 'fas fa-calendar-check', 'text' => 'Pin a Calendly booking block up top'],
                        ['icon' => 'fas fa-video', 'text' => 'Post a weekly tip to your Creator Feed'],
                        ['icon' => 'fas fa-envelope-open-text', 'text' => 'Send a follow-up to every new subscriber'],
                        ['icon' => 'fas fa-quote-right', 'text' => 'Add client testimonials beside the booking CTA'],
                    ],
                ],
                'paid' => [
                    'tag'   => 'Paid · $8–15/day',
                    'items' => [
                        ['icon' => 'fab fa-google', 'text' => 'Run a search ad for your service + city'],
                        ['icon' => 'fas fa-location-crosshairs', 'text' => 'Geo-target ads to your service area'],
                        ['icon' => 'fab fa-facebook', 'text' => 'Retarget visitors who viewed but didn\'t book'],
                    ],
                ],
                'cadence' => [
                    'tag'   => 'Weekly cadence',
                    'items' => [
                        ['icon' => 'fas fa-calendar-day', 'text' => 'Mon · Share a quick win or client result'],
                        ['icon' => 'fas fa-calendar-day', 'text' => 'Wed · Post a "book this week" reminder'],
                        ['icon' => 'fas fa-calendar-day', 'text' => 'Fri · Go live or post a short Q&A clip'],
                        ['icon' => 'fas fa-calendar-day', 'text' => 'Sun · Confirm next week & follow up no-shows'],
                    ],
                ],
                'targets' => [
                    'tag'   => '30-day targets',
                    'items' => [
                        ['icon' => 'fas fa-arrow-trend-up', 'text' => '+20 booked calls'],
                        ['icon' => 'fas fa-percent', 'text' => '60%+ show-up rate'],
                        ['icon' => 'fas fa-coins', 'text' => 'Under $12 cost per booking'],
                    ],
                ],
                'kpi' => 'booked calls · show-up rate · cost per booking',
            ],
            [
                'goal' => 'Grow my audience and turn fans into followers',
                'summary' => 'Visitors arrive and leave — there\'s no reason to follow or come back yet.',
                'head' => 'Convert one-time visitors into a fanbase',
                'organic' => [
                    'tag'   => 'Organic',
                    'items' => [
                        ['icon' => 'fas fa-user-plus', 'text' => 'Add a one-tap Follow button to your page'],
                        ['icon' => 'fas fa-clapperboard', 'text' => 'Cross-post Reels into a video gallery'],
                        ['icon' => 'fas fa-gift', 'text' => 'Offer a freebie for joining your list'],
                        ['icon' => 'fas fa-bell', 'text' => 'Turn on new-post notifications for followers'],
                    ],
                ],
                'paid' => [
                    'tag'   => 'Paid · $5–12/day',
                    'items' => [
                        ['icon' => 'fab fa-tiktok', 'text' => 'Promote your best clip to a lookalike audience'],
                        ['icon' => 'fas fa-arrows-rotate', 'text' => 'Retarget profile visitors with a follow CTA'],
                        ['icon' => 'fab fa-instagram', 'text' => 'Boost a "follow me here" story to your fans'],
                    ],
                ],
                'cadence' => [
                    'tag'   => 'Weekly cadence',
                    'items' => [
                        ['icon' => 'fas fa-calendar-day', 'text' => 'Mon · Post your strongest hook of the week'],
                        ['icon' => 'fas fa-calendar-day', 'text' => 'Wed · Share behind-the-scenes content'],
                        ['icon' => 'fas fa-calendar-day', 'text' => 'Fri · Run a poll or question sticker'],
                        ['icon' => 'fas fa-calendar-day', 'text' => 'Sun · Reply to comments & reshare wins'],
                    ],
                ],
                'targets' => [
                    'tag'   => '30-day targets',
                    'items' => [
                        ['icon' => 'fas fa-arrow-trend-up', 'text' => '+1,000 new followers'],
                        ['icon' => 'fas fa-percent', 'text' => '6%+ engagement rate'],
                        ['icon' => 'fas fa-coins', 'text' => 'Under $0.30 cost per follow'],
                    ],
                ],
                'kpi' => 'new followers · engagement rate · cost per follow',
            ],
        ];
    }
}
