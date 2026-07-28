<?php

namespace Database\Seeders;

use App\Modules\Admin\Models\PageTemplate;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Support\BlockVariantCatalog;
use Illuminate\Database\Seeder;

/**
 * Seeds a small, broadly-useful set of starter Page Templates so the
 * page-template picker (`user/links/templates/picker`) is never empty
 * on a fresh install. These complement the persona-tied templates from
 * ExpandedPageTemplateLibrarySeeder: they're not gated on PersonaCatalog
 * (which can be empty on day-one or in a stripped-down install) and
 * cover several legacy "shape" categories so the picker's category
 * chips, blueprint thumbnails, and "what's inside" chips look
 * meaningfully different across tiles.
 *
 * Idempotent: every row is keyed by a stable `starter-*` slug and
 * inserted with `firstOrCreate` so re-running the seeder never
 * duplicates rows or clobbers admin edits to the same row.
 *
 * Auto-refresh: like the persona seeder, each snapshot carries a
 * `meta.seed_version` stamp. When the starter blueprints are redesigned
 * the version is bumped and `autoRefreshStale()` drops untouched seeded
 * rows whose stored version is older, so the firstOrCreate loop below
 * recreates them with the new design on the next deploy. Admin-edited
 * rows (updated after creation) and unknown `starter-*` slugs are left
 * alone.
 *
 * Slug namespace `starter-*` is intentionally distinct from the
 * `persona-*` namespace owned by ExpandedPageTemplateLibrarySeeder so
 * its auto-refresh / outdated-blueprint logic leaves these alone.
 */
class StarterPageTemplatesSeeder extends Seeder
{
    /**
     * Bump when the starter blueprints below are redesigned.
     *
     * v7 (2026-07): New template generation. Replaces the retired legacy
     * starter set with 5 redesigned blueprints (personal hub, link-in-bio,
     * restaurant, event, portfolio) — each with its own theme, profile
     * layout, link design variant and a distinct block mix so the picker's
     * category chips and "what's inside" chips look meaningfully different.
     */
    public const SEED_VERSION = 8;

    /** Tolerance (seconds) for treating updated_at == created_at. */
    private const EDIT_DRIFT_TOLERANCE = 2;

    public function run(): void
    {
        // Drop untouched seeded rows from an older blueprint version so
        // the firstOrCreate loop below recreates them with the new design.
        $this->autoRefreshStale();

        foreach ($this->templates() as $i => $tpl) {
            PageTemplate::firstOrCreate(
                ['slug' => $tpl['slug']],
                [
                    'name'                 => $tpl['name'],
                    'category'             => $tpl['category'],
                    'description'          => $tpl['description'],
                    'thumbnail_url'        => $this->thumbUrl($tpl['slug']),
                    'plan_tier'            => null,
                    'is_active'            => true,
                    'sort_order'           => 10 + $i,
                    'recommended_personas' => $tpl['recommended_personas'],
                    'snapshot'             => $tpl['snapshot'],
                ]
            );
        }
    }

    /**
     * Delete untouched `starter-*` rows whose stored seed_version is
     * older than the current SEED_VERSION. Mirrors the persona seeder's
     * auto-refresh: unknown slugs (admin-added) and admin-edited rows
     * (updated after creation) are preserved.
     */
    private function autoRefreshStale(): void
    {
        $knownSlugs = array_column($this->templates(), 'slug');

        $rows = PageTemplate::query()
            ->where('slug', 'like', 'starter-%')
            ->get(['id', 'slug', 'snapshot', 'created_at', 'updated_at']);

        foreach ($rows as $row) {
            if (!in_array($row->slug, $knownSlugs, true)) {
                continue; // unknown slug — admin-added, leave alone.
            }
            if ($row->updated_at && $row->created_at
                && $row->updated_at->getTimestamp() - $row->created_at->getTimestamp() > self::EDIT_DRIFT_TOLERANCE) {
                continue; // admin edited through the panel — preserve.
            }
            $stored = (int) (((array) $row->snapshot)['meta']['seed_version'] ?? 0);
            if ($stored >= self::SEED_VERSION) {
                continue;
            }

            PageTemplate::whereKey($row->id)->delete();
        }
    }

    /**
     * @return array<int, array{slug:string,name:string,category:string,description:string,recommended_personas:array<int,string>,snapshot:array}>
     */
    private function templates(): array
    {
        $kits = $this->variantKits();

        return [
            // 1 — Personal hub: warm gradient, glass profile, quick links.
            [
                'slug'                 => 'starter-personal-hub',
                'name'                 => 'Personal Hub',
                'category'             => 'personal',
                'description'          => 'A friendly all-in-one personal page: profile, socials, your top links and a way to reach you.',
                'recommended_personas' => ['other', 'student', 'freelancer'],
                'snapshot'             => $this->snapshot([
                    $this->profile('Your Name', 'A little about you — what you do, what you love, and where people can find you.', $this->face('starter-personal-face'), $kits['personal']),
                    $this->socials(),
                    $this->link('My latest project', 'https://example.com/project', 'fas fa-rocket', $kits['personal']),
                    $this->link('Read my blog', 'https://example.com/blog', 'fas fa-pen-nib', $kits['personal']),
                    $this->link('Book a call', 'https://example.com/call', 'fas fa-calendar', $kits['personal']),
                    $this->divider(),
                    $this->ctaButton('Say hello', 'mailto:you@example.com'),
                ], [
                    'background_type'    => 'gradient',
                    'background_gradient' => 'linear-gradient(160deg, #1e293b 0%, #334155 55%, #3d6bff 130%)',
                    'theme_color'        => '#3d6bff',
                    'font_color'         => '#f8fafc',
                    'button_color'       => '#3d6bff',
                    'button_text_color'  => '#ffffff',
                    'button_style'       => 'rounded',
                ]),
            ],

            // 2 — Link-in-bio: bold sunset gradient, cover-hero profile, big links.
            [
                'slug'                 => 'starter-link-in-bio',
                'name'                 => 'Creator Link-in-Bio',
                'category'             => 'biolink',
                'description'          => 'A bold creator page with a cover hero, big tappable links, a highlight reel and an email capture-style CTA.',
                'recommended_personas' => ['creator', 'influencer', 'youtuber'],
                'snapshot'             => $this->snapshot([
                    $this->profile('Your Name', 'Creator. Storyteller. New drops every week — everything I make lives here.', $this->face('starter-linkbio-face'), $kits['linkbio'], $this->photo('creative,lifestyle', 1200, 480, 'starter-linkbio-cover')),
                    $this->badge('NEW DROP', '#ec4899'),
                    $this->linkBig('Watch my latest video', 'https://youtube.com/@yourhandle', 'fab fa-youtube', $kits['linkbio']),
                    $this->linkBig('Shop the merch', 'https://example.com/shop', 'fas fa-bag-shopping', $kits['linkbio']),
                    $this->linkBig('Join the newsletter', 'https://example.com/newsletter', 'fas fa-envelope-open-text', $kits['linkbio']),
                    $this->imageSlider([
                        $this->photo('creative,lifestyle', 900, 1200, 'starter-linkbio-s1'),
                        $this->photo('creative,lifestyle', 900, 1200, 'starter-linkbio-s2'),
                        $this->photo('creative,lifestyle', 900, 1200, 'starter-linkbio-s3'),
                    ]),
                    $this->socials(),
                ], [
                    'background_type'    => 'gradient',
                    'background_gradient' => 'linear-gradient(135deg, #f97316 0%, #ec4899 50%, #8b5cf6 100%)',
                    'theme_color'        => '#ec4899',
                    'font_color'         => '#ffffff',
                    'button_color'       => '#ffffff',
                    'button_text_color'  => '#1f2937',
                    'button_style'       => 'pill',
                ]),
            ],

            // 3 — Restaurant: dark minimal profile, tabbed menu, prices, WhatsApp.
            [
                'slug'                 => 'starter-restaurant-menu',
                'name'                 => 'Restaurant & Menu',
                'category'             => 'restaurant',
                'description'          => 'A moody restaurant page with a tabbed menu, signature-dish pricing, opening hours and one-tap WhatsApp reservations.',
                'recommended_personas' => ['restaurant', 'cafe', 'chef'],
                'snapshot'             => $this->snapshot([
                    $this->profile('Your Restaurant', 'Seasonal plates, natural wine, and a room that feels like home. Walk-ins welcome.', $this->face('starter-restaurant-face'), $kits['restaurant'], $this->photo('restaurant-hero', 1200, 480, 'starter-restaurant-cover')),
                    $this->ticker(['Open Tue–Sun · 12:00–23:00', 'Happy hour 17:00–19:00', 'Private dining available']),
                    $this->tabs([
                        ['label' => 'Starters', 'text' => 'Burrata & blood orange — 12. Crispy artichokes — 10. Sourdough & cultured butter — 6.'],
                        ['label' => 'Mains',    'text' => 'Wood-fired sea bass — 28. Short rib agnolotti — 24. Charred cauliflower steak — 19.'],
                        ['label' => 'Dessert',  'text' => 'Burnt basque cheesecake — 9. Olive-oil gelato — 7.'],
                    ]),
                    $this->listPricing([
                        ['name' => 'Tasting menu (5 courses)', 'price' => '$65'],
                        ['name' => 'Wine pairing',             'price' => '$35'],
                        ['name' => 'Chef\'s counter seat',     'price' => '$80'],
                    ]),
                    $this->imageGrid([
                        $this->photo('restaurant-1', 600, 600, 'starter-restaurant-g1'),
                        $this->photo('restaurant-2', 600, 600, 'starter-restaurant-g2'),
                        $this->photo('restaurant-3', 600, 600, 'starter-restaurant-g3'),
                    ], 3),
                    $this->review('Amelia R.', 5, 'Best table in town — the tasting menu is worth every penny.', $this->face('starter-restaurant-review')),
                    $this->whatsapp('+15551234567', 'Reserve on WhatsApp', 'Hi! I\'d like to book a table.'),
                    $this->link('See the full menu (PDF)', 'https://example.com/menu.pdf', 'fas fa-utensils', $kits['restaurant']),
                ], [
                    'background_type'   => 'color',
                    'background_color'  => '#0a0a0a',
                    'theme_color'       => '#f59e0b',
                    'font_color'        => '#f5f5f5',
                    'button_color'      => '#f59e0b',
                    'button_text_color' => '#1a1a1a',
                    'button_style'      => 'square',
                ]),
            ],

            // 4 — Event: countdown, schedule timeline, FAQ, RSVP CTA.
            [
                'slug'                 => 'starter-event-page',
                'name'                 => 'Event & RSVP',
                'category'             => 'event',
                'description'          => 'Everything one event needs: countdown to doors, the day\'s schedule, an FAQ and a big RSVP button.',
                'recommended_personas' => ['event', 'community', 'church'],
                'snapshot'             => $this->snapshot([
                    $this->profile('The Big Night', 'One evening. Live music, great food, and people worth meeting. Save your seat below.', $this->face('starter-event-face'), $kits['event'], $this->photo('concert,event', 1200, 480, 'starter-event-cover')),
                    $this->countdown('Doors open in', '+21 days'),
                    $this->ctaButton('RSVP — it\'s free', 'https://example.com/rsvp', '#3d6bff'),
                    $this->heading('Schedule'),
                    $this->timeline([
                        ['title' => 'Doors & welcome drinks', 'description' => 'Grab a badge and settle in.',            'date' => '6:00 PM'],
                        ['title' => 'Live set',               'description' => 'An hour of music you\'ll talk about.',   'date' => '7:30 PM'],
                        ['title' => 'Afterparty',             'description' => 'Lights down, volume up.',                'date' => '10:00 PM'],
                    ]),
                    $this->faq([
                        ['question' => 'Where is it?',        'answer' => 'The Warehouse, 42 River St. Doors at 6 PM sharp.'],
                        ['question' => 'Is there parking?',   'answer' => 'Street parking plus a paid lot next door.'],
                        ['question' => 'Can I bring a +1?',   'answer' => 'Yes — just add them to your RSVP.'],
                    ]),
                    $this->link('Get directions', 'https://maps.google.com', 'fas fa-map-marker-alt', $kits['event']),
                ], [
                    'background_type'    => 'gradient',
                    'background_gradient' => 'linear-gradient(180deg, #020617 0%, #1e1b4b 100%)',
                    'theme_color'        => '#3d6bff',
                    'font_color'         => '#e0e7ff',
                    'button_color'       => '#3d6bff',
                    'button_text_color'  => '#ffffff',
                    'button_style'       => 'pill',
                ]),
            ],

            // 5 — Portfolio: light editorial look, work grid, stats, social proof.
            [
                'slug'                 => 'starter-portfolio',
                'name'                 => 'Portfolio & Work',
                'category'             => 'portfolio',
                'description'          => 'A clean portfolio with a work grid, at-a-glance stats, client praise and a hire-me link.',
                'recommended_personas' => ['artist', 'photographer', 'developer'],
                'snapshot'             => $this->snapshot([
                    $this->profile('Your Name', 'Designer & maker. Selected work below — currently booking new projects.', $this->face('starter-portfolio-face'), $kits['portfolio']),
                    $this->stats('At a glance', [
                        ['value' => '9 yrs',  'label' => 'Experience'],
                        ['value' => '120+',   'label' => 'Projects shipped'],
                        ['value' => '40',     'label' => 'Happy clients'],
                    ]),
                    $this->heading('Selected work'),
                    $this->imageGrid([
                        $this->photo('art,print', 600, 600, 'starter-portfolio-g1'),
                        $this->photo('art,print', 600, 600, 'starter-portfolio-g2'),
                        $this->photo('art,print', 600, 600, 'starter-portfolio-g3'),
                        $this->photo('art,print', 600, 600, 'starter-portfolio-g4'),
                        $this->photo('art,print', 600, 600, 'starter-portfolio-g5'),
                        $this->photo('art,print', 600, 600, 'starter-portfolio-g6'),
                    ], 3),
                    $this->testimonialCarousel([
                        ['quote' => 'Sharp eye, fast turnaround, zero drama. We rebooked immediately.', 'name' => 'Jordan P.', 'title' => 'Brand Lead'],
                        ['quote' => 'The work speaks for itself — our launch looked incredible.',       'name' => 'Sam K.',    'title' => 'Founder'],
                    ]),
                    $this->linkBig('View full portfolio', 'https://example.com/work', 'fas fa-images', $kits['portfolio']),
                    $this->link('Download my CV', 'https://example.com/cv.pdf', 'fas fa-file-arrow-down', $kits['portfolio']),
                    $this->ctaButton('Hire me', 'mailto:you@example.com', '#10b981'),
                ], [
                    'background_type'    => 'gradient',
                    'background_gradient' => 'linear-gradient(180deg, #fdf6e3 0%, #fce7f3 100%)',
                    'theme_color'        => '#0ea5e9',
                    'font_color'         => '#1f2937',
                    'button_color'       => '#0ea5e9',
                    'button_text_color'  => '#ffffff',
                    'button_style'       => 'rounded',
                ]),
            ],
        ];
    }

    /* ──────────────────── snapshot + block helpers ──────────────────── */

    /**
     * Self-hosted, theme-aware SVG preview for a template card. Rendered
     * live by PublicTemplateThumbController from the row's own snapshot,
     * so every card reflects its actual theme + block layout. Stored
     * root-relative (absolutized by PageTemplate's accessor) so rows are
     * portable across hosts; ?v=SEED_VERSION re-renders on redesigns.
     */
    private function thumbUrl(string $templateSlug): string
    {
        return '/template-thumbs/' . $templateSlug . '.svg?v=' . self::SEED_VERSION;
    }

    private function snapshot(array $blocks, array $biolink = []): array
    {
        // `meta.seed_version` lets a future seeder run detect rows
        // generated by an older blueprint version and auto-refresh them
        // (see `autoRefreshStale()`).
        return [
            'biolink' => $biolink,
            'blocks'  => $blocks,
            'meta'    => ['seed_version' => self::SEED_VERSION],
        ];
    }

    private function block(string $type, array $settings): array
    {
        return ['type' => $type, 'settings' => $settings, 'is_active' => true];
    }

    /**
     * Per-template "variant kit": the profile block type + identity
     * variant (which carries the `_profile_layout`) plus the link design
     * variant key. Keyed by template slug-fragment for readability.
     *
     * @return array<string, array{ptype:string,pvar:string,link:string}>
     */
    private function variantKits(): array
    {
        return [
            'personal'   => ['ptype' => 'profile_card_v1', 'pvar' => 'identity_glass',        'link' => 'frosted_pill'],
            'linkbio'    => ['ptype' => 'profile_card_v2', 'pvar' => 'identity_cover_hero',   'link' => 'pill_solid'],
            'restaurant' => ['ptype' => 'profile_card_v4', 'pvar' => 'identity_minimal_dark', 'link' => 'corporate_row'],
            'event'      => ['ptype' => 'profile_card_v1', 'pvar' => 'identity_classic',      'link' => 'card_lifted'],
            'portfolio'  => ['ptype' => 'profile_card_v3', 'pvar' => 'identity_founder',      'link' => 'outline_pill'],
        ];
    }

    /**
     * Resolve a curated design variant into a full baked `_style` payload
     * (STYLE_DEFAULTS + the catalog variant's style + the current catalog
     * VERSION stamp) so BOTH the applied block and the no-DB template
     * preview render the variant identically.
     *
     * @param  array<string,mixed>  $extra
     * @return array<string,mixed>
     */
    private function variantStyle(string $type, string $key, array $extra = []): array
    {
        $variant = BlockVariantCatalog::find($type, $key);
        $style = is_array($variant['style'] ?? null) ? $variant['style'] : [];
        $merged = array_merge(BiolinkBlock::STYLE_DEFAULTS, $style, $extra);
        if ($variant !== null) {
            $merged['_variant'] = $key;
            $merged['_variant_version'] = BlockVariantCatalog::VERSION;
        }
        return $merged;
    }

    /** @return array<int, array{name:string,url:string}> */
    private function profileSocials(): array
    {
        return [
            ['name' => 'instagram', 'url' => 'https://instagram.com/yourhandle'],
            ['name' => 'tiktok',    'url' => 'https://tiktok.com/@yourhandle'],
            ['name' => 'youtube',   'url' => 'https://youtube.com/@yourhandle'],
            ['name' => 'linkedin',  'url' => 'https://linkedin.com/in/yourhandle'],
        ];
    }

    /**
     * Rich profile card. The `$kit` selects the profile block type
     * (profile_card_v1..v4) and the `identity_*` design variant. v3
     * (Stats) and v4 (Badges) get placeholder stats/badges so they
     * render full unless overridden via `$extra`.
     *
     * @param  array{ptype:string,pvar:string,link:string}  $kit
     * @param  array<string,mixed>  $extra
     */
    private function profile(string $name, string $bio, string $avatar, array $kit, string $cover = '', array $extra = []): array
    {
        $type = $kit['ptype'];
        $settings = array_merge([
            'name'      => $name,
            'title'     => '',
            'avatar'    => $avatar,
            'cover'     => $cover,
            'bio'       => $bio,
            'verified'  => true,
            'location'  => 'Your City, Country',
            'website'   => 'https://example.com',
            'cta_label' => 'Get in touch',
            'cta_url'   => 'https://example.com',
            'socials'   => $this->profileSocials(),
            '_style'    => $this->variantStyle($type, $kit['pvar']),
        ], $extra);

        if ($type === 'profile_card_v3' && !isset($settings['stats'])) {
            $settings['stats'] = [
                ['label' => 'Followers', 'value' => '12.4K'],
                ['label' => 'Projects',  'value' => '87'],
                ['label' => 'Years',     'value' => '6'],
            ];
        }
        if ($type === 'profile_card_v4' && !isset($settings['badges'])) {
            $settings['badges'] = [
                ['label' => 'Top Rated'],
                ['label' => 'Verified'],
            ];
        }

        return $this->block($type, $settings);
    }

    private function heading(string $text, string $size = 'h3'): array
    {
        return $this->block('heading', ['text' => $text, 'size' => $size, 'align' => 'center']);
    }

    private function paragraph(string $text): array
    {
        return $this->block('paragraph', ['text' => $text, 'align' => 'center']);
    }

    /** @param  array{ptype:string,pvar:string,link:string}|null  $kit */
    private function link(string $text, string $url, string $icon = '', ?array $kit = null): array
    {
        $settings = ['text' => $text, 'url' => $url, 'icon' => $icon];
        if ($kit !== null) {
            $settings['_style'] = $this->variantStyle('link', $kit['link']);
        }
        return $this->block('link', $settings);
    }

    /** @param  array{ptype:string,pvar:string,link:string}|null  $kit */
    private function linkBig(string $text, string $url, string $icon = '', ?array $kit = null): array
    {
        $settings = ['text' => $text, 'url' => $url, 'icon' => $icon];
        if ($kit !== null) {
            $settings['_style'] = $this->variantStyle('link_big', $kit['link']);
        }
        return $this->block('link_big', $settings);
    }

    private function divider(): array
    {
        return $this->block('divider', []);
    }

    private function image(string $url): array
    {
        return $this->block('image', ['url' => $url, 'alt' => '']);
    }

    private function imageGrid(array $images, int $columns = 3): array
    {
        return $this->block('image_grid', [
            'columns' => $columns,
            'gap'     => 2,
            'images'  => array_map(fn($u) => ['url' => $u], $images),
        ]);
    }

    private function list(array $items): array
    {
        return $this->block('list', ['items' => $items]);
    }

    private function countdown(string $title, string $relative = '+30 days'): array
    {
        $target = now()->modify($relative)->toIso8601String();
        return $this->block('countdown', ['title' => $title, 'target_date' => $target]);
    }

    /** @param  array<int, array{question:string,answer:string}>  $items */
    private function faq(array $items): array
    {
        return $this->block('faq', ['items' => array_values($items)]);
    }

    /** @param  array<int, array{title:string,description:string,date:string}>  $items */
    private function timeline(array $items): array
    {
        return $this->block('timeline', ['items' => array_values($items)]);
    }

    private function testimonials(array $items): array
    {
        return $this->block('testimonials', ['items' => array_values($items)]);
    }

    private function review(string $name, int $rating, string $text, string $avatar = ''): array
    {
        return $this->block('review', [
            'name'   => $name,
            'avatar' => $avatar,
            'rating' => $rating,
            'text'   => $text,
        ]);
    }

    private function poll(string $question, array $options): array
    {
        return $this->block('poll', ['question' => $question, 'options' => array_values($options)]);
    }

    private function coupon(string $code, string $description, string $expires): array
    {
        return $this->block('coupon', ['code' => $code, 'description' => $description, 'expires' => $expires]);
    }

    private function product(string $name, string $description, string $price, string $image, string $url, string $badge = ''): array
    {
        return $this->block('product', [
            'name'            => $name,
            'description'     => $description,
            'price'          => $price,
            'image'          => $image,
            'url'            => $url,
            'badge'          => $badge,
            'native_checkout' => false,
        ]);
    }

    private function ctaButton(string $text, string $url, string $color = '#3d6bff', string $textColor = '#ffffff', string $size = 'lg'): array
    {
        return $this->block('cta_button', [
            'text'       => $text,
            'url'        => $url,
            'color'      => $color,
            'text_color' => $textColor,
            'size'       => $size,
        ]);
    }

    private function socials(): array
    {
        return $this->block('socials_multi', [
            'groups' => [[
                'label'     => 'Personal',
                'platforms' => [
                    ['name' => 'instagram', 'url' => 'https://instagram.com/yourhandle',    'display' => 'icon'],
                    ['name' => 'tiktok',    'url' => 'https://tiktok.com/@yourhandle',      'display' => 'icon'],
                    ['name' => 'youtube',   'url' => 'https://youtube.com/@yourhandle',     'display' => 'icon'],
                    ['name' => 'twitter',   'url' => 'https://x.com/yourhandle',            'display' => 'icon'],
                    ['name' => 'linkedin',  'url' => 'https://linkedin.com/in/yourhandle',  'display' => 'icon'],
                ],
            ]],
            'size'  => 'md',
            'style' => 'rounded',
        ]);
    }

    /* ──────────────────── realistic demo imagery ──────────────────── */

    /** Map a starter image key to an on-topic photo keyword. */
    private function starterKeyword(string $key): string
    {
        return match (true) {
            str_starts_with($key, 'restaurant') => str_contains($key, 'hero') ? 'gourmet,food' : 'restaurant,interior',
            str_starts_with($key, 'event')      => 'concert,event',
            str_starts_with($key, 'portfolio')  => str_contains($key, 'print') ? 'art,print' : 'photography,art',
            str_starts_with($key, 'linkbio')    => 'creative,lifestyle',
            str_starts_with($key, 'personal')   => 'lifestyle,portrait',
            default                              => 'minimal,abstract',
        };
    }

    /**
     * Self-hosted placeholder image bundled with the app
     * (public/block-placeholders/*.svg). External photo CDNs (loremflickr)
     * can rate-limit, change, or disappear — which would make seeded template
     * previews look broken over time. Picked by aspect ratio so square slots
     * get the square art and wide banners get the cover art.
     */
    private function photo(string $keywords, int $w, int $h, string $seed): string
    {
        if ($w === $h) {
            return asset('block-placeholders/image-square.svg');
        }
        if ($h > 0 && $w / $h >= 2) {
            return asset('block-placeholders/cover.svg');
        }
        return asset('block-placeholders/image.svg');
    }

    /** Self-hosted avatar placeholder bundled with the app. */
    private function face(string $seed, int $size = 200): string
    {
        return asset('block-placeholders/avatar.svg');
    }

    /* ──────────────────── newer block helpers ──────────────────── */

    /** @param  array<int, array{question:string,answer:string,icon?:string}>  $items */
    private function faqV2(array $items): array
    {
        return $this->block('faq_v2', ['items' => array_values($items)]);
    }

    private function badge(string $text, string $color = '#3d6bff', string $textColor = '#ffffff'): array
    {
        return $this->block('badge', ['text' => $text, 'color' => $color, 'text_color' => $textColor]);
    }

    private function alert(string $text, string $type = 'info', string $icon = 'fa-info-circle'): array
    {
        return $this->block('alert', ['text' => $text, 'type' => $type, 'icon' => $icon]);
    }

    /** @param  array<int, array{label:string,value:int,color?:string}>  $items */
    private function progress(array $items): array
    {
        return $this->block('progress', ['items' => array_values($items)]);
    }

    /** @param  array<int, array{name:string,price:string,included?:bool}>  $items */
    private function listPricing(array $items): array
    {
        return $this->block('list_pricing', ['items' => array_values($items)]);
    }

    private function oneTimeOffer(string $title, string $description, string $price, string $originalPrice, string $url): array
    {
        return $this->block('one_time_offer', [
            'title'          => $title,
            'description'    => $description,
            'price'          => $price,
            'original_price' => $originalPrice,
            'url'            => $url,
        ]);
    }

    /** @param  array<int,string>  $images */
    private function imageSlider(array $images, int $interval = 3500): array
    {
        return $this->block('image_slider', [
            'images'   => array_map(fn($u) => ['url' => $u], array_values($images)),
            'interval' => $interval,
            'effect'   => 'fade',
        ]);
    }

    private function whatsapp(string $phone, string $buttonText = 'Chat on WhatsApp', string $message = ''): array
    {
        return $this->block('whatsapp_widget', [
            'phone'       => $phone,
            'button_text' => $buttonText,
            'message'     => $message,
        ]);
    }

    /** @param  array<int,int>  $amounts */
    private function donation(string $title, string $description, array $amounts, string $url): array
    {
        return $this->block('donation', [
            'title'       => $title,
            'description' => $description,
            'amounts'     => array_values($amounts),
            'url'         => $url,
        ]);
    }

    /** @param  array<int,int>  $amounts */
    private function buyMeCoffee(string $username, string $text, string $description, array $amounts): array
    {
        return $this->block('buy_me_coffee', [
            'username'    => $username,
            'text'        => $text,
            'description' => $description,
            'amounts'     => array_values($amounts),
        ]);
    }

    /* ──────────────── rich-media block helpers (v5) ──────────────── */

    private function spotify(string $url = 'https://open.spotify.com/track/4cOdK2wGLETKBW3PvgPWqT', string $type = 'track'): array
    {
        return $this->block('spotify', ['url' => $url, 'type' => $type]);
    }

    private function appleMusic(string $url = 'https://music.apple.com/us/album/abbey-road-remastered/1441164426'): array
    {
        return $this->block('apple_music', ['url' => $url, 'type' => 'album']);
    }

    private function soundcloud(string $url = 'https://soundcloud.com/forss/flickermood'): array
    {
        return $this->block('soundcloud', ['url' => $url]);
    }

    private function audio(string $title, string $url = ''): array
    {
        return $this->block('audio', ['title' => $title, 'url' => $url !== '' ? $url : asset('block-placeholders/sample.mp3')]);
    }

    /** @param  array<int, array{title:string,artist?:string,url:string,cover?:string,duration?:string}>  $tracks */
    private function audioList(string $title, array $tracks): array
    {
        return $this->block('audio_list', ['title' => $title, 'layout' => 'compact', 'tracks' => array_values($tracks)]);
    }

    private function instagramMedia(string $url = 'https://www.instagram.com/p/CkQ7-gDgF8B/'): array
    {
        return $this->block('instagram_media', ['url' => $url]);
    }

    private function tiktokVideo(string $url = 'https://www.tiktok.com/@scout2015/video/6718335390845095173'): array
    {
        return $this->block('tiktok_video', ['url' => $url]);
    }

    private function twitterTweet(string $url = 'https://twitter.com/Twitter/status/1445078208190291973'): array
    {
        return $this->block('twitter_tweet', ['url' => $url]);
    }

    private function pdfDocument(string $title, string $url = ''): array
    {
        return $this->block('pdf_document', ['title' => $title, 'url' => $url !== '' ? $url : asset('block-placeholders/sample.pdf')]);
    }

    /** @param  array<int, array{label:string,text:string}>  $tabs */
    private function tabs(array $tabs): array
    {
        return $this->block('tabs', ['layout' => 'tabs', 'tabs' => array_values($tabs)]);
    }

    /** @param  array<int, array{title:string,body:string}>  $items */
    private function accordion(array $items): array
    {
        return $this->block('accordion', ['layout' => 'plain', 'items' => array_values($items)]);
    }

    /** @param  array<int,string>  $items */
    private function ticker(array $items): array
    {
        return $this->block('ticker', ['items' => array_values($items), 'speed' => 'normal']);
    }

    /** @param  array<int, array{value:string,label:string,caption?:string}>  $items */
    private function stats(string $title, array $items): array
    {
        return $this->block('stats', ['title' => $title, 'layout' => 'row', 'items' => array_values($items)]);
    }

    /** @param  array<int, array{quote:string,name:string,title?:string,avatar?:string}>  $items */
    private function testimonialCarousel(array $items): array
    {
        return $this->block('testimonial_carousel', ['layout' => 'carousel', 'items' => array_values($items)]);
    }

    private function reviewsWall(string $heading = 'What people are saying'): array
    {
        return $this->block('reviews_wall', ['heading' => $heading, 'source' => 'native', 'layout' => 'grid', 'limit' => 6]);
    }
}
