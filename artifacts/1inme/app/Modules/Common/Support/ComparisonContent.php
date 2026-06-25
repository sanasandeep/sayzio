<?php

namespace App\Modules\Common\Support;

use Illuminate\Support\Str;

/**
 * Single source of truth for the competitor comparison data used by:
 *   - the shared "How we compare" section (`public.partials._compare`)
 *   - the homepage + pricing comparison blocks
 *   - the dedicated /compare index and /compare/{competitor} landing pages
 *
 * The feature matrix (competitors + grouped features) lives here so the
 * homepage/pricing section and the full comparison pages can never drift
 * apart. The extra per-competitor copy a full landing page needs (hero
 * narrative, "where they win", migration steps, FAQ) also lives here.
 */
class ComparisonContent
{
    /**
     * Ordered competitor columns. "ours" (Sayzio) is always first.
     *
     * @return array<int, array{key:string,name:string,tagline:string,badge:string,isOurs:bool}>
     */
    public static function competitors(): array
    {
        return [
            ['key' => 'ours',     'name' => 'Sayzio',    'tagline' => 'The whole growth stack', 'badge' => 'All-in-one',        'isOurs' => true],
            ['key' => 'linktree', 'name' => 'Linktree', 'tagline' => 'Link in Bio page',          'badge' => 'Half the cost',     'isOurs' => false],
            ['key' => 'bitly',    'name' => 'Bitly',    'tagline' => 'Short links & QR',       'badge' => 'More features',     'isOurs' => false],
            ['key' => 'beacons',  'name' => 'Beacons',  'tagline' => 'Creator bio',            'badge' => 'Lower price',       'isOurs' => false],
            ['key' => 'carrd',    'name' => 'Carrd',    'tagline' => 'One-page sites',         'badge' => 'Way more inside',   'isOurs' => false],
            ['key' => 'taplink',  'name' => 'Taplink',  'tagline' => 'Insta micro-landing',    'badge' => 'Bigger toolkit',    'isOurs' => false],
            ['key' => 'stan',     'name' => 'Stan',     'tagline' => 'Creator store',          'badge' => 'Free forever plan', 'isOurs' => false],
        ];
    }

    /**
     * Feature support matrix, grouped into categories.
     * Each row is [label, [competitorKey => bool, ...]].
     *
     * @return array<string, array<int, array{0:string,1:array<string,bool>}>>
     */
    public static function groups(): array
    {
        return [
            'Link in Bio page' => [
                ['Drag-and-drop Link in Bio builder',     ['ours'=>true,'linktree'=>true,  'bitly'=>false,'beacons'=>true, 'carrd'=>true, 'taplink'=>true, 'stan'=>true ]],
                ['Multiple bio pages per account',    ['ours'=>true,'linktree'=>true,  'bitly'=>false,'beacons'=>false,'carrd'=>true, 'taplink'=>false,'stan'=>true ]],
                ['Embed video, music & forms',        ['ours'=>true,'linktree'=>true,  'bitly'=>false,'beacons'=>true, 'carrd'=>true, 'taplink'=>true, 'stan'=>true ]],
                ['Custom themes & fonts',             ['ours'=>true,'linktree'=>true,  'bitly'=>false,'beacons'=>true, 'carrd'=>true, 'taplink'=>true, 'stan'=>true ]],
                ['Custom domains',                    ['ours'=>true,'linktree'=>true,  'bitly'=>true, 'beacons'=>true, 'carrd'=>true, 'taplink'=>true, 'stan'=>true ]],
            ],
            'Links & QR' => [
                ['Branded short links',               ['ours'=>true,'linktree'=>false, 'bitly'=>true, 'beacons'=>false,'carrd'=>false,'taplink'=>false,'stan'=>false]],
                ['Dynamic QR codes',                  ['ours'=>true,'linktree'=>true,  'bitly'=>true, 'beacons'=>true, 'carrd'=>false,'taplink'=>true, 'stan'=>false]],
                ['QR styling, logos & colors',        ['ours'=>true,'linktree'=>false, 'bitly'=>true, 'beacons'=>false,'carrd'=>false,'taplink'=>false,'stan'=>false]],
                ['Bulk link import',                  ['ours'=>true,'linktree'=>false, 'bitly'=>true, 'beacons'=>false,'carrd'=>false,'taplink'=>false,'stan'=>false]],
            ],
            'Analytics' => [
                ['Built-in click analytics',          ['ours'=>true,'linktree'=>true,  'bitly'=>true, 'beacons'=>true, 'carrd'=>true, 'taplink'=>true, 'stan'=>true ]],
                ['Live visitor map',                  ['ours'=>true,'linktree'=>false, 'bitly'=>false,'beacons'=>false,'carrd'=>false,'taplink'=>false,'stan'=>false]],
                ['Click heatmap',                     ['ours'=>true,'linktree'=>false, 'bitly'=>false,'beacons'=>false,'carrd'=>false,'taplink'=>false,'stan'=>false]],
                ['UTM builder',                       ['ours'=>true,'linktree'=>false, 'bitly'=>true, 'beacons'=>false,'carrd'=>false,'taplink'=>false,'stan'=>false]],
            ],
            'Growth & AI' => [
                ['AI Performance coach',              ['ours'=>true,'linktree'=>false, 'bitly'=>false,'beacons'=>false,'carrd'=>false,'taplink'=>false,'stan'=>false]],
                ['Scheduled posts',                   ['ours'=>true,'linktree'=>false, 'bitly'=>false,'beacons'=>true, 'carrd'=>false,'taplink'=>false,'stan'=>true ]],
                ['A/B testing',                       ['ours'=>true,'linktree'=>true,  'bitly'=>true, 'beacons'=>false,'carrd'=>false,'taplink'=>false,'stan'=>false]],
            ],
            'Monetization' => [
                ['Tip jar / donations',               ['ours'=>true,'linktree'=>true,  'bitly'=>false,'beacons'=>true, 'carrd'=>false,'taplink'=>true, 'stan'=>true ]],
                ['Sell digital products',             ['ours'=>true,'linktree'=>true,  'bitly'=>false,'beacons'=>true, 'carrd'=>false,'taplink'=>true, 'stan'=>true ]],
                ['Coin / wallet rewards',             ['ours'=>true,'linktree'=>false, 'bitly'=>false,'beacons'=>false,'carrd'=>false,'taplink'=>false,'stan'=>false]],
            ],
            'Team & workflow' => [
                ['Team workspaces',                   ['ours'=>true,'linktree'=>true,  'bitly'=>true, 'beacons'=>false,'carrd'=>false,'taplink'=>false,'stan'=>false]],
                ['Direct messaging',                  ['ours'=>true,'linktree'=>false, 'bitly'=>false,'beacons'=>false,'carrd'=>false,'taplink'=>false,'stan'=>false]],
                ['Roles & permissions',               ['ours'=>true,'linktree'=>true,  'bitly'=>true, 'beacons'=>false,'carrd'=>false,'taplink'=>false,'stan'=>false]],
            ],
            'Plans & access' => [
                ['Free forever (no credit card)',     ['ours'=>true,'linktree'=>true,  'bitly'=>true, 'beacons'=>true, 'carrd'=>true, 'taplink'=>true, 'stan'=>false]],
                ['Native mobile app',                 ['ours'=>true,'linktree'=>true,  'bitly'=>true, 'beacons'=>true, 'carrd'=>false,'taplink'=>true, 'stan'=>true ]],
            ],
        ];
    }

    /**
     * Flat list of every [label, support] row across all groups.
     *
     * @return array<int, array{0:string,1:array<string,bool>}>
     */
    public static function featuresFlat(): array
    {
        $flat = [];
        foreach (static::groups() as $rows) {
            foreach ($rows as $r) {
                $flat[] = $r;
            }
        }
        return $flat;
    }

    /** Total number of compared features. */
    public static function totalFeatures(): int
    {
        return count(static::featuresFlat());
    }

    /**
     * Per-competitor count of supported features.
     *
     * @return array<string, int>
     */
    public static function scores(): array
    {
        $scores = [];
        foreach (static::competitors() as $c) {
            $n = 0;
            foreach (static::featuresFlat() as [$label, $support]) {
                if (!empty($support[$c['key']])) {
                    $n++;
                }
            }
            $scores[$c['key']] = $n;
        }
        return $scores;
    }

    /**
     * Non-"ours" competitor keys (the ones that get a /compare/{key} page).
     *
     * @return array<int, string>
     */
    public static function rivalKeys(): array
    {
        $keys = [];
        foreach (static::competitors() as $c) {
            if (empty($c['isOurs'])) {
                $keys[] = $c['key'];
            }
        }
        return $keys;
    }

    /** Route constraint for {competitor}, e.g. "linktree|bitly|...". */
    public static function rivalKeysPattern(): string
    {
        return implode('|', static::rivalKeys());
    }

    /**
     * Rich, per-competitor copy for the dedicated /compare/{key} pages.
     * Keyed by competitor key. Honest framing — "where they win" reflects
     * each tool's genuine strengths; we never quote a competitor's price.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function extras(): array
    {
        return [
            'linktree' => [
                'accent'   => '#39e09b',
                'icon'     => 'fa-tree',
                'headline' => 'A Link in Bio is the start, not the finish.',
                'intro'    => "Linktree popularised the link-in-bio and does that one job cleanly. But once you need branded short links, deep analytics, a built-in CRM, broadcasts and AI growth tools, you end up bolting on three or four more subscriptions. Sayzio folds the whole stack into one link — for less.",
                'they_win' => [
                    'The most recognised name in link-in-bio, with a huge template gallery.',
                    'A large marketplace of one-tap app integrations.',
                    'The simplest possible onboarding if a list of buttons is all you need.',
                ],
                'we_win'   => [
                    'Branded short links and a full QR Studio, not just the bio page.',
                    'Live visitor map, click heatmaps and an AI Performance Coach.',
                    'Built-in contacts, forms, broadcasts and direct messaging.',
                    'Coin/wallet rewards and richer monetisation on top of tips and products.',
                ],
                'faqs'     => [
                    ['q' => 'Can I move my Linktree to Sayzio?', 'a' => 'Yes. Recreate your page in minutes with the drag-and-drop builder, or bulk-import your existing links. Your audience just sees a better page at the same link.'],
                    ['q' => 'Will my QR codes still work after switching?', 'a' => 'Point your custom domain or Link in Bio at Sayzio and the destination updates everywhere — including any printed QR codes you generate here, which stay editable forever.'],
                    ['q' => 'Is Sayzio cheaper than Linktree?', 'a' => 'Our paid tiers are designed to undercut a stack of single-purpose tools while bundling far more. Compare the free plans first — Sayzio gives you short links, QR codes and analytics with no credit card.'],
                ],
            ],
            'bitly' => [
                'accent'   => '#ee6123',
                'icon'     => 'fa-link',
                'headline' => 'Short links plus everything that happens after the click.',
                'intro'    => "Bitly is a serious link-management tool, but it stops at the link. Sayzio gives you the same branded short links, QR codes and UTM tooling, then adds a full Link in Bio page, audience CRM, monetisation and growth AI — so the click actually turns into a follower or a sale.",
                'they_win' => [
                    'Enterprise-grade link management built to operate at massive scale.',
                    'A long-established API and integration ecosystem.',
                    'Deep, link-first reporting for large marketing teams.',
                ],
                'we_win'   => [
                    'A real drag-and-drop Link in Bio page, not just a link redirect.',
                    'Live visitor map and click heatmaps on top of click analytics.',
                    'Contacts, forms, broadcasts and monetisation built in.',
                    'AI Performance Coach that tells you what to fix next.',
                ],
                'faqs'     => [
                    ['q' => 'Does Sayzio do branded short links like Bitly?', 'a' => 'Yes — branded short links, custom domains, bulk import, UTM builder and dynamic QR codes are all included, alongside the rest of the growth stack.'],
                    ['q' => 'Can I keep my existing short links?', 'a' => 'Point your custom domain at Sayzio and rebuild your key links here. New links get the full analytics and Link in Bio toolkit automatically.'],
                    ['q' => 'What does Sayzio add that Bitly does not?', 'a' => 'A complete Link in Bio builder, audience CRM, forms, broadcasts, monetisation, team roles and an AI coach — the things you do after someone clicks.'],
                ],
            ],
            'beacons' => [
                'accent'   => '#7c5cff',
                'icon'     => 'fa-store',
                'headline' => 'Creator tools, without giving up the marketing stack.',
                'intro'    => "Beacons is a strong creator store with a media kit and email built in. Sayzio matches the creator essentials — store, tips, scheduled posts — and adds branded short links, a QR Studio, live geo analytics and team workspaces, so you can run a brand or an agency from the same place.",
                'they_win' => [
                    'A polished, creator-first store and media-kit experience.',
                    'Email marketing aimed squarely at creators.',
                    'Tight focus on the influencer monetisation workflow.',
                ],
                'we_win'   => [
                    'Branded short links, bulk import and a full QR Studio.',
                    'Live visitor map, click heatmaps and UTM tooling.',
                    'Team workspaces, roles and direct messaging.',
                    'Coin/wallet rewards plus tips and digital products.',
                ],
                'faqs'     => [
                    ['q' => 'Can I sell products on Sayzio like on Beacons?', 'a' => 'Yes — sell digital products, take tips and donations, and reward fans with coins, all from your Link in Bio.'],
                    ['q' => 'Does Sayzio schedule posts?', 'a' => 'Scheduled posts are built in, alongside an AI Performance Coach that flags what to improve before you publish.'],
                    ['q' => 'I run more than just a creator page — is Sayzio overkill?', 'a' => 'No. The same account scales from a single creator page to multi-brand team workspaces with roles and permissions.'],
                ],
            ],
            'carrd' => [
                'accent'   => '#2f9bff',
                'icon'     => 'fa-file-lines',
                'headline' => 'Beyond a static one-page site.',
                'intro'    => "Carrd is brilliant for cheap, simple one-page sites. But it is a website builder, not a growth platform — there are no branded short links, no QR analytics, no CRM and no monetisation rewards. Sayzio gives you a page that also captures, tracks and converts your audience.",
                'they_win' => [
                    'Dead-simple, very low-cost one-page websites.',
                    'Pixel-level layout control for static landing pages.',
                    'Great when you only need a brochure page and nothing else.',
                ],
                'we_win'   => [
                    'Branded short links, dynamic QR codes and bulk import.',
                    'Live visitor map, click heatmaps, UTM builder and click analytics.',
                    'Contacts, forms, broadcasts, tips and digital products.',
                    'Native mobile app and an AI Performance Coach.',
                ],
                'faqs'     => [
                    ['q' => 'Is Sayzio as flexible as Carrd for layout?', 'a' => 'You get drag-and-drop blocks, custom themes, fonts and even custom CSS/JS on higher plans — with growth tooling Carrd does not offer.'],
                    ['q' => 'Does Sayzio have analytics?', 'a' => 'Far more than a static site: live visitor map, click heatmaps, UTM tracking and an AI coach, all built in.'],
                    ['q' => 'Can I still keep things simple?', 'a' => 'Absolutely — start with one page and a template, then turn on extra tools only when you need them.'],
                ],
            ],
            'taplink' => [
                'accent'   => '#19c3a6',
                'icon'     => 'fa-mobile-screen',
                'headline' => 'More than an Instagram micro-landing.',
                'intro'    => "Taplink does a tidy Instagram landing page. Sayzio covers the same micro-landing use case and then keeps going — branded short links, a QR Studio, live geo analytics, a CRM and team workspaces — so your Link in Bio grows with you instead of capping out.",
                'they_win' => [
                    'Purpose-built, fast Instagram micro-landing pages.',
                    'Simple, low-cost entry point for a single social profile.',
                    'Straightforward blocks for a quick mobile page.',
                ],
                'we_win'   => [
                    'Branded short links, bulk import and a full QR Studio.',
                    'Live visitor map, click heatmaps and UTM tooling.',
                    'Team workspaces, roles and direct messaging.',
                    'Coin/wallet rewards and an AI Performance Coach.',
                ],
                'faqs'     => [
                    ['q' => 'Is Sayzio good for an Instagram Link in Bio?', 'a' => 'Yes — build a fast, mobile-first micro-landing in minutes, then add short links, QR codes and analytics as you grow.'],
                    ['q' => 'Can I capture leads from my page?', 'a' => 'Embed forms, collect contacts into a built-in CRM, and follow up with broadcasts — all without extra tools.'],
                    ['q' => 'Will it stay simple?', 'a' => 'Start minimal with a template; the heavier tools only appear when you choose to switch them on.'],
                ],
            ],
            'stan' => [
                'accent'   => '#ff5c8a',
                'icon'     => 'fa-graduation-cap',
                'headline' => 'A creator store that also markets for you.',
                'intro'    => "Stan is a neat all-in-one for selling courses and digital products. Sayzio matches the store and adds the marketing layer Stan lacks — branded short links, a QR Studio, live geo analytics, a CRM, team workspaces and a genuinely free plan — so selling and growing live in one place.",
                'they_win' => [
                    'A streamlined storefront for courses and digital products.',
                    'Simple, opinionated all-in-one for course sellers.',
                    'Built-in tips, products and scheduled posts.',
                ],
                'we_win'   => [
                    'A free-forever plan with no credit card required.',
                    'Branded short links, dynamic QR codes and bulk import.',
                    'Live visitor map, click heatmaps and UTM tooling.',
                    'Team workspaces, roles, direct messaging and coin rewards.',
                ],
                'faqs'     => [
                    ['q' => 'Can I sell digital products on Sayzio?', 'a' => 'Yes — sell digital products, take tips and donations, and reward buyers with coins, straight from your Link in Bio.'],
                    ['q' => 'Does Sayzio have a free plan?', 'a' => 'Yes, a genuinely free-forever plan including Link in Bio pages, short links and QR codes — no credit card needed.'],
                    ['q' => 'What does Sayzio add over a pure creator store?', 'a' => 'Branded short links, QR analytics, a CRM, broadcasts, team workspaces and an AI coach — the marketing layer that drives traffic to the store.'],
                ],
            ],
        ];
    }

    /**
     * Full, normalised data for one competitor's landing page, or null if
     * the key is unknown / is "ours".
     *
     * @return array<string, mixed>|null
     */
    public static function competitor(string $key): ?array
    {
        $key = strtolower(trim($key));
        if ($key === 'ours' || $key === '') {
            return null;
        }
        $meta = null;
        foreach (static::competitors() as $c) {
            if ($c['key'] === $key) {
                $meta = $c;
                break;
            }
        }
        if ($meta === null) {
            return null;
        }
        $extra  = static::extras()[$key] ?? [];
        $scores = static::scores();

        return array_merge($meta, [
            'accent'      => $extra['accent']   ?? '#7c3aed',
            'icon'        => $extra['icon']     ?? 'fa-circle-nodes',
            'headline'    => $extra['headline'] ?? ('Why creators switch to Sayzio from ' . $meta['name'] . '.'),
            'intro'       => $extra['intro']    ?? '',
            'they_win'    => array_values($extra['they_win'] ?? []),
            'we_win'      => array_values($extra['we_win'] ?? []),
            'faqs'        => array_values($extra['faqs'] ?? []),
            'our_score'   => $scores['ours'] ?? 0,
            'rival_score' => $scores[$key] ?? 0,
            'total'       => static::totalFeatures(),
            'wins'        => max(0, ($scores['ours'] ?? 0) - ($scores[$key] ?? 0)),
        ]);
    }

    /**
     * Lightweight list of all rival competitors for the /compare index,
     * each with score/lead pre-computed.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function index(): array
    {
        $out = [];
        foreach (static::rivalKeys() as $key) {
            $c = static::competitor($key);
            if ($c !== null) {
                $out[] = $c;
            }
        }
        return $out;
    }

    /** SEO/share title for a competitor page. */
    public static function shareTitle(array $competitor): string
    {
        return 'Sayzio vs ' . $competitor['name'] . ' — full feature & value comparison';
    }

    /** SEO/share description for a competitor page. */
    public static function shareDescription(array $competitor): string
    {
        return 'Sayzio vs ' . $competitor['name'] . ': compare '
            . $competitor['total'] . ' features side by side. Sayzio leads on '
            . $competitor['wins'] . ' of them, with the whole growth stack in one link. '
            . 'See where each tool wins and how to switch.';
    }

    /** Slugify helper for FAQ anchors. */
    public static function anchor(string $text): string
    {
        return Str::slug($text);
    }
}
