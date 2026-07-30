<?php

namespace Database\Seeders;

use App\Modules\Admin\Models\PageTemplate;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Support\BlockVariantCatalog;
use App\Modules\User\Support\PlatformAssetCatalog;
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
     *
     * v10 (2026-07): Added "Pink Boutique" — a boutique/seller page with a
     * hero cover, chunky lavender tile buttons with square photo thumbs,
     * and a two-column shop grid mixing image tiles and labeled buttons.
     *
     * v11 (2026-07): Added "Floral Editorial" — a full-bleed botanical
     * photo background, a decorative display-font name heading, and six
     * chartreuse pill links in a two-column desktop grid (grid_span 6)
     * that stacks to one column on mobile.
     *
     * v12 (2026-07): Added "Split Hero Tiles" — a tall yellow hero panel
     * (script name + spaced tagline + photo) sitting beside a 2×3 grid of
     * flat solid-colour link tiles on desktop, stacking vertically on
     * phones via the new grid_span_md / grid_row_span_md overrides.
     *
     * v13 (2026-07): Added "Pastel Tile Grid" — a cream apparel-brand page
     * with a green serif brand title, orange socials and six pastel card
     * tiles (heading + short blurb) in a responsive auto grid (3 columns
     * on desktop, stacked on phones).
     *
     * v16 (2026-07): Added "Pressed Botanicals" — an artisan/botanical
     * scrapbook page showcasing the Paper Collage profile-card look
     * (identity_paper_collage): torn-paper name card over grid paper
     * with a pressed-sprig accent, plus handwritten-note links on a
     * soft off-white botanical theme.
     *
     * v17 (2026-07): Added "Torn Paper Studio" — the first template built
     * on the new torn-paper page background (`background_type: torn`):
     * a dusty-blue paper sheet with a jagged torn right edge over a
     * studio backdrop photo, minimal profile + clean rounded links.
     */
    public const SEED_VERSION = 19;

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
                    $this->profile('Your Name', 'A little about you: what you do, what you love, and where people can find you.', $this->face('starter-personal-face'), $kits['personal']),
                    $this->socials(),
                    // Block-level catalog preset showcase (Task #5970).
                    $this->withPreset($this->link('My latest project', 'https://example.com/project', 'fas fa-rocket', $kits['personal']), 'abstract_fourtyfive', 80),
                    $this->link('Read my blog', 'https://example.com/blog', 'fas fa-pen-nib', $kits['personal']),
                    $this->link('Book a call', 'https://example.com/call', 'fas fa-calendar', $kits['personal']),
                    $this->divider(),
                    $this->ctaButton('Say hello', 'mailto:you@example.com'),
                ], [
                    // Catalog preset background (Task #5970): deep blue-violet
                    // radial from BgPresetCatalog, softened slightly via the
                    // page-level preset transparency over the dark fallback.
                    'background_type'    => 'preset',
                    'bg_preset_key'      => 'abstract_fiftythree',
                    'bg_preset_opacity'  => 90,
                    'bg_fallback_color'  => '#0f172a',
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
                    $this->profile('Your Name', 'Creator. Storyteller. New drops every week: everything I make lives here.', $this->face('starter-linkbio-face'), $kits['linkbio'], $this->photo('creative,lifestyle', 1200, 480, 'starter-linkbio-cover')),
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
                    // Catalog preset background (Task #5970): warm sunset radial.
                    'background_type'    => 'preset',
                    'bg_preset_key'      => 'abstract_fiftyfour',
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
                        ['label' => 'Starters', 'text' => 'Burrata & blood orange: 12. Crispy artichokes: 10. Sourdough & cultured butter: 6.'],
                        ['label' => 'Mains',    'text' => 'Wood-fired sea bass: 28. Short rib agnolotti: 24. Charred cauliflower steak: 19.'],
                        ['label' => 'Dessert',  'text' => 'Burnt basque cheesecake: 9. Olive-oil gelato: 7.'],
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
                    $this->review('Amelia R.', 5, 'Best table in town, the tasting menu is worth every penny.', $this->face('starter-restaurant-review')),
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
                    $this->ctaButton('RSVP: it\'s free', 'https://example.com/rsvp', '#3d6bff'),
                    $this->heading('Schedule'),
                    $this->timeline([
                        ['title' => 'Doors & welcome drinks', 'description' => 'Grab a badge and settle in.',            'date' => '6:00 PM'],
                        ['title' => 'Live set',               'description' => 'An hour of music you\'ll talk about.',   'date' => '7:30 PM'],
                        ['title' => 'Afterparty',             'description' => 'Lights down, volume up.',                'date' => '10:00 PM'],
                    ]),
                    $this->faq([
                        ['question' => 'Where is it?',        'answer' => 'The Warehouse, 42 River St. Doors at 6 PM sharp.'],
                        ['question' => 'Is there parking?',   'answer' => 'Street parking plus a paid lot next door.'],
                        ['question' => 'Can I bring a +1?',   'answer' => 'Yes, just add them to your RSVP.'],
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
                    $this->profile('Your Name', 'Designer & maker. Selected work below: currently booking new projects.', $this->face('starter-portfolio-face'), $kits['portfolio']),
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
                        ['quote' => 'The work speaks for itself: our launch looked incredible.',       'name' => 'Sam K.',    'title' => 'Founder'],
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

            // 6 — Overlap hero: cream page, tall cover with the white
            // profile card pulled up over it (avatar straddling the card
            // edge) and dark pill links below — screenshot-inspired.
            [
                'slug'                 => 'starter-overlap-hero',
                'name'                 => 'Overlap Hero',
                'category'             => 'personal',
                'description'          => 'A warm, editorial profile: a big cover photo with your card overlapping it, then bold pill links below.',
                'recommended_personas' => ['creator', 'freelancer', 'artist'],
                'snapshot'             => $this->snapshot([
                    $this->profile(
                        'Your Name',
                        'Storyteller & content creator. Sharing my favourite work, projects and ways to connect below.',
                        $this->face('starter-overlap-face'),
                        $kits['overlap'],
                        $this->photo('mountains,travel', 900, 600, 'starter-overlap-cover'),
                        ['title' => 'Content Creator']
                    ),
                    $this->link('Watch my latest video', 'https://example.com/video', 'fas fa-play', $kits['overlap']),
                    $this->link('Read the blog', 'https://example.com/blog', 'fas fa-pen-nib', $kits['overlap']),
                    $this->link('Shop my favourites', 'https://example.com/shop', 'fas fa-bag-shopping', $kits['overlap']),
                    $this->ctaButton('Work with me', 'mailto:you@example.com', '#1c1917'),
                ], [
                    'background_type'  => 'color',
                    'background_color' => '#f3ede2',
                    'theme_color'      => '#1c1917',
                    'font_color'       => '#1c1917',
                    'button_color'     => '#1c1917',
                    'button_text_color' => '#ffffff',
                    'button_style'     => 'pill',
                ]),
            ],

            // 7 — Pink Boutique: off-white page, big hero cover, chunky
            // lavender-pink tile buttons with square photo thumbnails,
            // then a two-column shop grid mixing image tiles and labeled
            // buttons — screenshot-inspired boutique/seller layout.
            [
                'slug'                 => 'starter-pink-boutique',
                'name'                 => 'Pink Boutique',
                'category'             => 'fashion',
                'description'          => 'A soft boutique storefront: hero photo, chunky lavender shop buttons with photo thumbnails, and a two-column grid of new arrivals, sale and gallery tiles.',
                'recommended_personas' => ['fashion', 'business', 'creator'],
                'snapshot'             => $this->snapshot([
                    $this->boutiqueImage($this->photo('fashion,boutique', 1200, 480, 'starter-boutique-hero')),
                    $this->boutiqueTile('SHOP', 'https://example.com/shop', $this->photo('fashion,shop', 600, 600, 'starter-boutique-shop')),
                    $this->boutiqueTile('MARKETPLACE', 'https://example.com/marketplace', $this->photo('fashion,market', 600, 600, 'starter-boutique-market')),
                    $this->boutiqueImage($this->photo('fashion,new', 600, 800, 'starter-boutique-g1'), 6),
                    $this->boutiqueImage($this->photo('fashion,sale', 600, 800, 'starter-boutique-g2'), 6),
                    $this->boutiqueTile('NEW ARRIVALS', 'https://example.com/new-arrivals', '', 6),
                    $this->boutiqueTile('GALLERY', 'https://example.com/gallery', '', 6),
                    $this->boutiqueImage($this->photo('fashion,style', 600, 800, 'starter-boutique-g3'), 6),
                    $this->boutiqueTile('ON SALE', 'https://example.com/sale', '', 6),
                ], [
                    'background_type'   => 'color',
                    'background_color'  => '#f2f0ee',
                    'theme_color'       => '#ddb8e4',
                    'font_color'        => '#241b26',
                    'button_color'      => '#ddb8e4',
                    'button_text_color' => '#241b26',
                    'button_style'      => 'rounded',
                ]),
            ],

            // 8 — Split hero: full-page blurred photo background. Desktop is
            // a two-column split — big circular avatar + social icons on the
            // left (identity_split_hero layout), name/tagline/outline-pill
            // links stacked in a transparent card on the right. Both halves
            // carry `stack_mobile` so phones collapse to a single column.
            [
                'slug'                 => 'starter-split-hero',
                'name'                 => 'Split Hero',
                'category'             => 'personal',
                'description'          => 'A striking split layout over a blurred photo: your portrait and socials on one side, your name and bold outline links on the other.',
                'recommended_personas' => ['creator', 'influencer', 'freelancer'],
                'snapshot'             => $this->snapshot([
                    $this->profile(
                        'Your Name',
                        '',
                        $this->face('starter-splithero-face'),
                        $kits['splithero'],
                        '',
                        [
                            'title'   => '',
                            'socials' => [
                                ['name' => 'instagram', 'url' => 'https://instagram.com/yourhandle'],
                                ['name' => 'facebook',  'url' => 'https://facebook.com/yourhandle'],
                                ['name' => 'twitter',   'url' => 'https://x.com/yourhandle'],
                            ],
                            '_style'  => $this->variantStyle('profile_card_v1', 'identity_split_hero', [
                                'grid_span'    => 5,
                                'stack_mobile' => 1,
                            ]),
                        ]
                    ),
                    [
                        'type'     => 'card',
                        'is_active' => true,
                        'settings' => [
                            'columns'      => 1,
                            'gap'          => 14,
                            'padding'      => 8,
                            'bg_type'      => 'transparent',
                            'border_width' => 0,
                            'shadow'       => 'none',
                            '_style'       => array_merge(BiolinkBlock::STYLE_DEFAULTS, [
                                'grid_span'    => 7,
                                'stack_mobile' => 1,
                            ]),
                        ],
                        'children' => [
                            $this->heading('Your Name', 'h2'),
                            $this->paragraph('BEAUTY AND FASHION'),
                            $this->link('ABOUT ME',       'https://example.com/about',   '', $kits['splithero']),
                            $this->link('LOOKBOOK',       'https://example.com/lookbook', '', $kits['splithero']),
                            $this->link('COLLABORATIONS', 'https://example.com/collabs', '', $kits['splithero']),
                            $this->link('WORK WITH ME',   'mailto:you@example.com',      '', $kits['splithero']),
                        ],
                    ],
                ], [
                    'background_type'  => 'image',
                    'background_image' => $this->photo('lifestyle,portrait', 900, 600, 'starter-splithero-bg'),
                    'bg_blur'          => 40,
                    'theme_color'      => '#ffffff',
                    'font_color'       => '#ffffff',
                    'button_color'     => 'transparent',
                    'button_text_color' => '#ffffff',
                    'button_style'     => 'pill',
                ]),
            ],

            // 9 — Floral Editorial: full-bleed botanical photo background,
            // a big decorative display-font name heading, and six
            // chartreuse pill links laid out two-per-row on desktop
            // (grid_span 6) that stack to one column on mobile —
            // screenshot-inspired floral link-in-bio.
            [
                'slug'                 => 'starter-floral-editorial',
                'name'                 => 'Floral Editorial',
                'category'             => 'biolink',
                'description'          => 'A dreamy botanical link-in-bio: a full-page floral photo, your name in a decorative display font, and chartreuse pill buttons in a two-column grid.',
                'recommended_personas' => ['creator', 'influencer', 'other'],
                'snapshot'             => $this->snapshot([
                    $this->floralHeading('Your Name'),
                    $this->floralPill('ABOUT ME', 'https://example.com/about'),
                    $this->floralPill('CONNECT WITH ME', 'https://example.com/contact'),
                    $this->floralPill('MY WORK', 'https://example.com/work'),
                    $this->floralPill('JOIN MY GIVEAWAY', 'https://example.com/giveaway'),
                    $this->floralPill('COLLABS', 'https://example.com/collabs'),
                    $this->floralPill('READ MY BLOG', 'https://example.com/blog'),
                ], [
                    'background_type'   => 'image',
                    'background_image'  => asset('template-assets/floral-editorial-bg.png'),
                    'bg_fallback_color' => '#57614a',
                    'bg_overlay_color'  => '#2c3324',
                    'bg_overlay_opacity' => 12,
                    'theme_color'       => '#d9ed6f',
                    'font_color'        => '#e9f3c4',
                    'button_color'      => '#d9ed6f',
                    'button_text_color' => '#242b14',
                    'button_style'      => 'pill',
                ]),
            ],

            // 10 — Split Hero Tiles: tall yellow hero panel (script name,
            // spaced tagline, big photo) beside a 2×3 grid of flat
            // solid-colour link tiles on desktop; on phones everything
            // stacks full-width with the hero first — screenshot-inspired.
            [
                'slug'                 => 'starter-split-hero-grid',
                'name'                 => 'Split Hero Tiles',
                'category'             => 'business',
                'description'          => 'A bold split layout: a tall hero panel with your name and photo beside a colourful grid of flat link tiles. Stacks neatly on phones.',
                'recommended_personas' => ['business', 'creator', 'freelancer'],
                'snapshot'             => $this->snapshot([
                    $this->profile(
                        'Madison Lee',
                        '',
                        $this->photo('portrait,entrepreneur', 600, 800, 'starter-splithero-photo'),
                        $kits['splitherogrid'],
                        '',
                        [
                            'title'    => 'SHE-EO · ENTREPRENEUR',
                            'verified' => false,
                            'socials'  => [],
                            '_style'   => $this->variantStyle('profile_card_v1', 'identity_split_hero_panel', [
                                'grid_span_md'     => 4,
                                'grid_row_span_md' => 3,
                            ]),
                        ]
                    ),
                    $this->heroTile('MY WEBSITE',   'https://example.com',            '#14b8a6'),
                    $this->heroTile('THE PODCAST',  'https://example.com/podcast',    '#ec4899'),
                    $this->heroTile('COURSES',      'https://example.com/courses',    '#8b5cf6'),
                    $this->heroTile('BOOK A CALL',  'https://example.com/call',       '#f97316'),
                    $this->heroTile('NEWSLETTER',   'https://example.com/newsletter', '#4f46e5'),
                    $this->heroTile('SHOP MERCH',   'https://example.com/shop',       '#eab308'),
                ], [
                    'background_type'   => 'color',
                    'background_color'  => '#111111',
                    'theme_color'       => '#f4c531',
                    'font_color'        => '#ffffff',
                    'button_color'      => '#14b8a6',
                    'button_text_color' => '#ffffff',
                    'button_style'      => 'square',
                    'max_width_desktop' => 960,
                    'block_gap'         => 8,
                ]),
            ],

            // 11 — Pastel Tile Grid: cream apparel-brand page — centered
            // green serif brand title, orange social icons, then six
            // pastel info tiles (about, shop, sale, subscribe, contact,
            // press) in a responsive auto-fit grid: 3 columns on desktop,
            // stacked on phones — screenshot-inspired.
            [
                'slug'                 => 'starter-pastel-tiles',
                'name'                 => 'Pastel Tile Grid',
                'category'             => 'fashion',
                'description'          => 'A soft apparel-brand page: cream background, green serif wordmark, orange socials and six pastel tiles for about, shop, sale, subscribe, contact and press.',
                'recommended_personas' => ['fashion', 'business', 'creator'],
                'snapshot'             => $this->snapshot([
                    $this->pastelBrandTitle('Your Brand'),
                    $this->socials(),
                    $this->pastelTileGrid([
                        $this->pastelTile('about us',     'Learn how we started and our commitment to sustainable, ethical fashion.',                      '#e8925a', '#fdf3e3'),
                        $this->pastelTile('shop all',     'Explore all our pieces, available in-store and for pre-order.',                                 '#c5b3e6', '#4a3d6b'),
                        $this->pastelTile('archive sale', 'Get up to 50% off select pieces from last season.',                                             '#b5cc8e', '#3f4d26'),
                        $this->pastelTile('subscribe',    'Get exclusive deals and be the first to know about the latest drops and collaborations.',       '#c5b3e6', '#4a3d6b'),
                        $this->pastelTile('contact us',   'Reach out to customer support for inquiries and order status.',                                 '#b5cc8e', '#3f4d26'),
                        $this->pastelTile('press',        'See what the press is saying about us.',                                                        '#e8925a', '#fdf3e3'),
                    ]),
                ], [
                    'background_type'   => 'color',
                    'background_color'  => '#faf5ea',
                    'theme_color'       => '#e8925a',
                    'font_color'        => '#4a5238',
                    'button_color'      => '#7ba05b',
                    'button_text_color' => '#faf5ea',
                    'button_style'      => 'rounded',
                ]),
            ],

            // 12 — Astrid: warm off-white editorial two-column layout.
            // Desktop: lowercase name + big rounded photo on the left;
            // socials, three tinted info rows and a labeled 3-tile gallery
            // on the right. Mobile: the grid's `stack_mobile` flag
            // collapses it to a single column (left column first).
            [
                'slug'                 => 'starter-astrid',
                'name'                 => 'Astrid Two-Column',
                'category'             => 'biolink',
                'description'          => 'A warm editorial two-column page: your name and a big photo on the left, socials, tinted highlight rows and a labeled mini-gallery on the right. Stacks neatly on mobile.',
                'recommended_personas' => ['creator', 'artist', 'influencer'],
                'snapshot'             => $this->snapshot([
                    $this->container('grid', [
                        'columns'      => 2,
                        'gap'          => 32,
                        'padding'      => 0,
                        'stack_mobile' => true,
                    ], [
                        // Left column — lowercase heading, big rounded photo, caption.
                        $this->container('card', $this->plainColumn(), [
                            $this->astridBlock('heading', ['text' => 'astrid sanchez', 'size' => 'h1', 'align' => 'left']),
                            $this->astridBlock('image', [
                                'url' => $this->photo('lifestyle,portrait', 900, 900, 'starter-astrid-photo'),
                                'alt' => 'all about me',
                                '_style' => array_merge(BiolinkBlock::STYLE_DEFAULTS, [
                                    'grid_span'   => 12,
                                    '_photo_mask' => 'arch',
                                ]),
                            ]),
                            $this->astridBlock('paragraph', ['text' => 'all about me ↓', 'align' => 'center']),
                        ]),
                        // Right column — socials, three tinted rows, labeled gallery.
                        $this->container('card', $this->plainColumn(), [
                            $this->astridBlock('socials', [
                                'platforms' => [
                                    ['name' => 'instagram', 'url' => 'https://instagram.com/yourhandle', 'display' => 'icon'],
                                    ['name' => 'facebook',  'url' => 'https://facebook.com/yourhandle',  'display' => 'icon'],
                                    ['name' => 'twitter',   'url' => 'https://x.com/yourhandle',         'display' => 'icon'],
                                    ['name' => 'email',     'url' => 'https://example.com/contact',      'display' => 'icon'],
                                ],
                                'size'  => 'md',
                                'style' => 'rounded',
                            ]),
                            $this->astridRow('Art live stream', 'Catch me on Twitch every Saturday as I make art live', '#f7f3ec'),
                            $this->astridRow('Tutorials', 'Videos to guide and to inspire you create', '#e3d3b9'),
                            $this->astridRow('Top picks + recos', 'Materials I stand by and would love for you to try', '#c08a3e'),
                            $this->container('grid', ['columns' => 3, 'gap' => 14, 'padding' => 0], [
                                $this->astridTile('starter-astrid-g1'),
                                $this->astridTile('starter-astrid-g2'),
                                $this->astridTile('starter-astrid-g3'),
                                $this->astridLabel('art'),
                                $this->astridLabel('inspo'),
                                $this->astridLabel('fashion'),
                            ]),
                        ]),
                    ]),
                ], [
                    'background_type'   => 'color',
                    'background_color'  => '#eceae6',
                    'theme_color'       => '#c08a3e',
                    'font_color'        => '#1f2937',
                    'button_color'      => '#c08a3e',
                    'button_text_color' => '#ffffff',
                    'button_style'      => 'rounded',
                ]),
            ],

            // 13 — Purple Split: bold purple split layout modeled on the
            // "Lillian Pratt" reference — oversized yellow-green display
            // name (grid_span 7), italic serif tagline (5), portrait photo
            // (5) beside a transparent card stacking six action-word link
            // rows (7). All split blocks carry `stack_mobile` so the page
            // collapses to a single column on phones.
            [
                'slug'                 => 'starter-purple-split',
                'name'                 => 'Purple Split',
                'category'             => 'biolink',
                'description'          => 'A bold purple split layout: oversized display name, italic serif tagline, and a portrait photo beside a stack of action-word links. Stacks to one column on mobile.',
                'recommended_personas' => ['musician', 'creator', 'artist'],
                'snapshot'             => $this->snapshot([
                    $this->block('heading', [
                        'text'   => 'Your Name',
                        'size'   => 'h1',
                        'align'  => 'left',
                        '_style' => $this->purpleSplitStyle(7, [
                            'font_family' => 'Archivo Black',
                            'font_size'   => '42',
                            'text_color'  => '#e3f77e',
                        ]),
                    ]),
                    $this->block('paragraph', [
                        'text'   => "I'm a musician, producer, and goal-getter",
                        'align'  => 'left',
                        '_style' => $this->purpleSplitStyle(5, [
                            'font_family' => 'Playfair Display',
                            'font_style'  => 'italic',
                            'font_weight' => '600',
                            'font_size'   => '15',
                            'text_color'  => '#2a1a45',
                        ]),
                    ]),
                    $this->block('image', [
                        'url'    => $this->photo('musician,portrait', 900, 1200, 'starter-purple-split-portrait'),
                        'alt'    => 'Portrait photo',
                        '_style' => $this->purpleSplitStyle(5, [
                            '_photo_mask'    => 'arch',
                            '_photo_accents' => 'starburst,dots',
                        ]),
                    ]),
                    // NOTE: `children` sits at the BLOCK level (sibling of
                    // `settings`), not inside settings — the settings
                    // sanitizer strips unknown keys, and insertBlockTree
                    // reads $b['children'] from the block array.
                    array_merge(
                        $this->block('card', [
                            'columns' => 1,
                            'bg_type' => 'transparent',
                            '_style'  => $this->purpleSplitStyle(7, [
                                'bg_color'      => 'transparent',
                                'border_style'  => 'none',
                                'border_width'  => '0',
                                'shadow_preset' => 'none',
                                'padding'       => '0',
                            ]),
                        ]),
                        ['children' => [
                            $this->purpleSplitAction('Listen', 'Latest single: My Dream', 'https://example.com/listen'),
                            $this->purpleSplitAction('Stream', 'Music on Spotify', 'https://open.spotify.com/artist/yourhandle'),
                            $this->purpleSplitAction('Watch', 'Videos on Vimeo', 'https://vimeo.com/yourhandle'),
                            $this->purpleSplitAction('Join', 'Live music sets on Twitch', 'https://twitch.tv/yourhandle'),
                            $this->purpleSplitAction('Donate', 'To the causes I support', 'https://example.com/donate'),
                            $this->purpleSplitAction('Shop', 'Merch and collectibles', 'https://example.com/shop'),
                        ]],
                    ),
                ], [
                    'background_type'   => 'color',
                    'background_color'  => '#b28cf0',
                    'theme_color'       => '#e3f77e',
                    'font_color'        => '#ffffff',
                    'button_color'      => '#e3f77e',
                    'button_text_color' => '#2a1a45',
                    'button_style'      => 'square',
                    'layout'            => [
                        'max_width_desktop' => 960,
                        'max_width_tablet'  => 720,
                    ],
                ]),
            ],

            // 14 — Pressed Botanicals: artisan/botanical scrapbook page
            // built around the Paper Collage profile-card look
            // (identity_paper_collage): a torn-paper name card layered
            // over a grid-paper panel with a pressed-sprig accent, then
            // handwritten-note links and a small square photo gallery on
            // a soft off-white botanical theme.
            [
                'slug'                 => 'starter-pressed-botanicals',
                'name'                 => 'Pressed Botanicals',
                'category'             => 'personal',
                'description'          => 'An artisan scrapbook page: a torn-paper collage name card with a pressed-botanical accent, handwritten-note links and a small photo gallery on a soft paper backdrop.',
                'recommended_personas' => ['artist', 'creator', 'other'],
                'snapshot'             => $this->snapshot([
                    $this->profile(
                        'Willow & Fern',
                        'Small-batch botanical prints, pressed-flower keepsakes and slow-made paper goods from my garden studio.',
                        '',
                        $kits['papercollage'],
                        '',
                        [
                            'title'    => 'Botanical artist · Paper maker',
                            'verified' => false,
                            'socials'  => [],
                            'location' => '',
                            'website'  => '',
                            'cta_label' => '',
                            'cta_url'   => '',
                        ]
                    ),
                    $this->link('Shop pressed-flower prints', 'https://example.com/shop',      '', $kits['papercollage']),
                    $this->link('Workshops & studio visits',  'https://example.com/workshops', '', $kits['papercollage']),
                    $this->link('Commission a keepsake',      'https://example.com/commissions', '', $kits['papercollage']),
                    $this->link('Read the studio journal',    'https://example.com/journal',   '', $kits['papercollage']),
                    $this->botanicalTile('starter-botanical-g1'),
                    $this->botanicalTile('starter-botanical-g2'),
                    $this->botanicalTile('starter-botanical-g3'),
                ], [
                    'background_type'   => 'color',
                    'background_color'  => '#efece3',
                    'theme_color'       => '#5f6f52',
                    'font_color'        => '#57534e',
                    'button_color'      => '#fcfbf7',
                    'button_text_color' => '#5b4636',
                    'button_style'      => 'rounded',
                ]),
            ],

            // 15 — Torn Paper Studio: showcases the torn-paper page
            // background — a dusty-blue paper sheet with a jagged torn
            // right edge over a studio backdrop photo peeking out beyond
            // the tear.
            [
                'slug'                 => 'starter-torn-paper-studio',
                'name'                 => 'Torn Paper Studio',
                'category'             => 'personal',
                'description'          => 'A torn-paper page: a dusty-blue paper sheet with a jagged torn edge over a moody studio photo, with a minimal profile and clean rounded links.',
                'recommended_personas' => ['artist', 'creator', 'other'],
                'snapshot'             => $this->snapshot([
                    $this->profile(
                        'Mara Voss',
                        'Analog photographer & zine maker. Everything here is shot on film and printed by hand.',
                        $this->photo('portrait,analog', 400, 400, 'starter-torn-avatar'),
                        $kits['personal'],
                        '',
                        [
                            'title'    => 'Photographer · Zine maker',
                            'verified' => false,
                            'location' => '',
                            'website'  => '',
                            'cta_label' => '',
                            'cta_url'   => '',
                        ]
                    ),
                    $this->link('Buy the latest zine',      'https://example.com/zine',    '', $kits['personal']),
                    $this->link('Print shop',               'https://example.com/prints',  '', $kits['personal']),
                    $this->link('Darkroom workshop dates',  'https://example.com/workshops', '', $kits['personal']),
                    $this->link('Say hello',                'https://example.com/contact', '', $kits['personal']),
                ], [
                    'background_type'   => 'torn',
                    'torn_image'        => $this->photo('film,studio,moody', 1600, 2000, 'starter-torn-backdrop'),
                    'torn_paper_color'  => '#cfe0e6',
                    'bg_fallback_color' => '#46626f',
                    'bg_attachment'     => 'fixed',
                    'theme_color'       => '#35525f',
                    'font_color'        => '#2b3a41',
                    'button_color'      => '#ffffff',
                    'button_text_color' => '#2b3a41',
                    'button_style'      => 'rounded',
                ]),
            ],
        ];
    }

    /* ─────────── Pressed Botanicals helpers (template 14) ─────────── */

    /** One third-width square gallery tile for the botanical mini-gallery. */
    private function botanicalTile(string $seed): array
    {
        return $this->block('image', [
            'url'    => $this->photo('botanical,pressed-flowers', 600, 600, $seed),
            'alt'    => '',
            '_style' => array_merge(BiolinkBlock::STYLE_DEFAULTS, [
                'grid_span'   => 4,
                '_photo_mask' => 'torn',
            ]),
        ]);
    }

    /* ────────────── Purple Split helpers (template 13) ────────────── */

    /**
     * Split-column style: a grid_span with the opt-in `stack_mobile` flag
     * so the block collapses to a full-width row on phones.
     */
    private function purpleSplitStyle(int $span, array $extra = []): array
    {
        return array_merge(
            BiolinkBlock::STYLE_DEFAULTS,
            ['grid_span' => $span, 'stack_mobile' => '1'],
            $extra,
        );
    }

    /**
     * One action-word link row (big word left, small description right)
     * using the `action_word_row` catalog variant.
     */
    private function purpleSplitAction(string $word, string $desc, string $url): array
    {
        return $this->block('link', [
            'text'        => $word,
            'description' => $desc,
            'url'         => $url,
            '_style'      => $this->variantStyle('link', 'action_word_row'),
        ]);
    }

    /* ────────────── Pastel Tile Grid helpers (template 11) ────────────── */

    /**
     * Centered green serif brand wordmark. A plain heading block with a
     * baked `_style` (no catalog variant key) carrying the serif family
     * and brand-green colour so the wordmark survives variant migrations.
     */
    private function pastelBrandTitle(string $text): array
    {
        return $this->block('heading', [
            'text'   => $text,
            'size'   => 'h1',
            'align'  => 'center',
            '_style' => $this->variantStyle('heading', '', [
                'display_mode' => 'content',
                'text_color'   => '#7ba05b',
                'font_family'  => 'Playfair Display',
                'padding'      => '0',
            ]),
        ]);
    }

    /**
     * Responsive auto-fit grid container for the pastel tiles: with a
     * 200px min tile width the page column fits 3 tiles per row on
     * desktop (680px column), 2 on tablets and stacks to 1 on phones.
     *
     * @param array<int, array> $tiles serialized child blocks
     */
    private function pastelTileGrid(array $tiles): array
    {
        return [
            'type'      => 'grid_auto',
            'settings'  => ['min_width' => 200, 'gap' => 14],
            'is_active' => true,
            'children'  => $tiles,
        ];
    }

    /**
     * One pastel info tile: a `card` container painted with the pastel
     * background holding a centered heading (tile colour) and a short
     * description, mirroring the screenshot's tile grid.
     */
    private function pastelTile(string $title, string $description, string $bg, string $titleColor): array
    {
        return [
            'type'     => 'card',
            'settings' => [
                'title'         => '',
                'columns'       => 1,
                'gap'           => 4,
                'padding'       => 18,
                'border_radius' => 18,
                'bg_type'       => 'color',
                'bg_color'      => $bg,
                'border_width'  => 0,
            ],
            'is_active' => true,
            'children'  => [
                $this->block('heading', [
                    'text'   => $title,
                    'size'   => 'h3',
                    'align'  => 'center',
                    '_style' => $this->variantStyle('heading', '', [
                        'display_mode' => 'content',
                        'text_color'   => $titleColor,
                        'padding'      => '0',
                    ]),
                ]),
                $this->paragraph($description),
            ],
        ];
    }

    /* ─────────────── Floral Editorial helpers (template 9) ─────────────── */

    /**
     * Big decorative name heading: art-nouveau-flavoured display font
     * (Yeseva One, in FontCatalog's display set so the public page loads
     * it) in light chartreuse, centered over the botanical photo.
     */
    private function floralHeading(string $text): array
    {
        return $this->block('heading', [
            'text'   => $text,
            'size'   => 'h1',
            'align'  => 'center',
            '_style' => array_merge(BiolinkBlock::STYLE_DEFAULTS, [
                'display_mode'  => 'content',
                'bg_color'      => 'transparent',
                'border_style'  => 'none',
                'shadow_preset' => 'none',
                'font_family'   => 'Yeseva One',
                'font_size'     => '44',
                'text_color'    => '#d9ed6f',
                'padding'       => '8',
                'margin_top'    => '24',
                'margin_bottom' => '16',
            ]),
        ]);
    }

    /**
     * Chartreuse pill link: solid lime fill, fully rounded, dark
     * uppercase label, half-width on the public page's 12-col grid
     * (grid_span 6 → two columns on desktop, stacked on mobile).
     * Baked style (no catalog variant key) so a future variant
     * migration can never strip the colour overrides.
     */
    private function floralPill(string $text, string $url): array
    {
        return $this->block('link', [
            'text'   => $text,
            'url'    => $url,
            '_style' => $this->variantStyle('link', '', [
                'display_mode'  => 'card',
                'bg_color'      => '#d9ed6f',
                'border_style'  => 'none',
                'border_width'  => '0',
                'border_color'  => 'transparent',
                'border_radius' => '999',
                'shadow_preset' => 'soft',
                'text_color'    => '#242b14',
                'padding'       => '16',
                'font_weight'   => '600',
                'link_layout'   => '',
                'grid_span'     => 6,
            ]),
        ]);
    }

    /* ───────────── Split Hero Tiles helpers (template 10) ───────────── */

    /**
     * Flat solid-colour tile: a full-width link block on phones that
     * becomes a third-width tile on desktop (`grid_span_md` 4), so six
     * of them wrap into a 2×3 grid beside the row-spanning hero panel.
     * Baked style (no catalog variant key) — square corners, no shadow,
     * bold white centred label, generous vertical padding.
     */
    private function heroTile(string $text, string $url, string $bg): array
    {
        $style = $this->variantStyle('link', '', [
            'display_mode'   => 'card',
            'bg_color'       => $bg,
            'border_style'   => 'none',
            'border_width'   => '0',
            'border_color'   => 'transparent',
            'border_radius'  => '0',
            'shadow_preset'  => 'none',
            'text_color'     => '#ffffff',
            'padding_top'    => '44',
            'padding_bottom' => '44',
            'font_weight'    => '800',
            'grid_span_md'   => 4,
        ]);

        return $this->block('link', ['text' => $text, 'url' => $url, '_style' => $style]);
    }

    /* ─────────────── Pink Boutique helpers (template 7) ─────────────── */

    /**
     * Chunky lavender boutique tile: a link block with a baked style
     * (no catalog variant key, so a future variant migration can never
     * strip the colour overrides). With a `$thumb` it uses the
     * `image_left` layout so the square photo sits flush on the left of
     * the tile; without one it's a centred label button. `$span` places
     * tiles on the public page's 12-col grid (6 = half width).
     */
    private function boutiqueTile(string $text, string $url, string $thumb = '', int $span = 12): array
    {
        $style = $this->variantStyle('link', '', [
            'display_mode'  => 'card',
            'bg_color'      => '#ddb8e4',
            'border_style'  => 'none',
            'border_width'  => '0',
            'border_color'  => 'transparent',
            'border_radius' => '14',
            'shadow_preset' => 'none',
            'text_color'    => '#241b26',
            'padding'       => '22',
            'font_weight'   => '700',
            'link_layout'   => $thumb !== '' ? 'image_left' : '',
        ]);
        if ($span !== 12) {
            $style['grid_span'] = $span;
        }

        $settings = ['text' => $text, 'url' => $url, '_style' => $style];
        if ($thumb !== '') {
            $settings['thumbnail'] = $thumb;
        }

        return $this->block('link', $settings);
    }

    /** Image tile with an optional half-width grid span. */
    private function boutiqueImage(string $url, int $span = 12): array
    {
        $settings = ['url' => $url, 'alt' => ''];
        if ($span !== 12) {
            $settings['_style'] = ['grid_span' => $span];
        }
        return $this->block('image', $settings);
    }

    /* ──────────────────── snapshot + block helpers ──────────────────── */

    /**
     * Container block (card / grid / grid_auto) with nested children.
     * TemplateService::insertBlockTree recurses into any container type,
     * so nested container trees seed correctly.
     */
    private function container(string $type, array $settings, array $children): array
    {
        $block = $this->block($type, $settings);
        $block['children'] = array_values($children);
        return $block;
    }

    /** Chrome-less card settings used as a plain column inside the Astrid grid. */
    private function plainColumn(): array
    {
        return [
            'columns'      => 1,
            'gap'          => 12,
            'padding'      => 0,
            'border_radius' => 0,
            'bg_type'      => 'transparent',
            'border_width' => 0,
            'shadow'       => 'none',
            // Half-width span so each column card occupies one cell of the
            // parent 2-column grid instead of stretching full width.
            '_style'       => array_merge(BiolinkBlock::STYLE_DEFAULTS, ['grid_span' => 6]),
        ];
    }

    /**
     * Astrid child block: baked `_style` with a full-width grid span so the
     * block occupies its whole column cell.
     */
    private function astridBlock(string $type, array $settings): array
    {
        if (!isset($settings['_style'])) {
            $settings['_style'] = array_merge(BiolinkBlock::STYLE_DEFAULTS, ['grid_span' => 12]);
        }
        return $this->block($type, $settings);
    }

    /**
     * One tinted Astrid info row: a chrome-styled 2-column card with the
     * bold title on the left and the description on the right.
     */
    private function astridRow(string $title, string $description, string $bgColor): array
    {
        $half = array_merge(BiolinkBlock::STYLE_DEFAULTS, ['grid_span' => 6]);
        return $this->container('card', [
            'columns'       => 2,
            'gap'           => 12,
            'padding'       => 18,
            'border_radius' => 18,
            'bg_type'       => 'color',
            'bg_color'      => $bgColor,
            'border_width'  => 0,
            'shadow'        => 'none',
        ], [
            $this->block('heading', ['text' => $title, 'size' => 'h3', 'align' => 'left', '_style' => $half]),
            $this->block('paragraph', ['text' => $description, 'align' => 'left', '_style' => $half]),
        ]);
    }

    /** One square gallery tile for the Astrid mini-gallery grid. */
    private function astridTile(string $seed): array
    {
        return $this->block('image', [
            'url'    => $this->photo('art,inspo', 600, 600, $seed),
            'alt'    => '',
            '_style' => array_merge(BiolinkBlock::STYLE_DEFAULTS, ['grid_span' => 4, 'border_radius' => 12]),
        ]);
    }

    /** One centered lowercase caption under a gallery tile. */
    private function astridLabel(string $text): array
    {
        return $this->block('paragraph', [
            'text'   => $text,
            'align'  => 'center',
            '_style' => array_merge(BiolinkBlock::STYLE_DEFAULTS, ['grid_span' => 4]),
        ]);
    }

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
     * Stamp a catalog preset background (Task #5970) onto an already-built
     * block: merges `bg_preset_key`/`bg_preset_opacity` into its `_style`
     * (seeding STYLE_DEFAULTS when the block had no style yet) so the
     * public renderer paints the preset layer behind the block content.
     */
    private function withPreset(array $block, string $presetKey, int $opacity = 100): array
    {
        $style = $block['settings']['_style'] ?? BiolinkBlock::STYLE_DEFAULTS;
        $block['settings']['_style'] = array_merge($style, [
            'bg_preset_key'     => $presetKey,
            'bg_preset_opacity' => max(0, min(100, $opacity)),
        ]);
        return $block;
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
            'overlap'    => ['ptype' => 'profile_card_v1', 'pvar' => 'identity_overlap_hero', 'link' => 'pill_solid'],
            'splithero'  => ['ptype' => 'profile_card_v1', 'pvar' => 'identity_split_hero',   'link' => 'outline_pill'],
            'splitherogrid' => ['ptype' => 'profile_card_v1', 'pvar' => 'identity_split_hero_panel', 'link' => 'pill_solid'],
            'papercollage'  => ['ptype' => 'profile_card_v1', 'pvar' => 'identity_paper_collage',    'link' => 'handwritten_note'],
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
     * Curated platform-asset picks for every starter-image slot, keyed by
     * the (unique) seed slug each call site passes. Values are S3 object
     * keys relative to the PlatformAssetCatalog `assets/` root, resolved
     * to CDN URLs via `PlatformAssetCatalog::urlForKey()`. Seeds missing
     * from the map fall back to the bundled placeholder SVGs so a new
     * call site can never break seeding.
     */
    private const ASSET_MAP = [
        // 1 — Personal Hub
        'starter-personal-face'         => 'people-avatars/man-glasses-yellow-shirt-smiling.jpg',
        // 2 — Creator Link-in-Bio
        'starter-linkbio-face'          => 'people-avatars/woman-red-hair-city-backlit-portrait.jpg',
        'starter-linkbio-cover'         => 'grid-images/man-sunglasses-neon-sign.jpg',
        'starter-linkbio-s1'            => 'grid-images/photographer-studio-shoot-poses.jpg',
        'starter-linkbio-s2'            => 'grid-images/woman-vintage-camera-outdoor-shoot.jpg',
        'starter-linkbio-s3'            => 'grid-images/skateboarder-jump-trick-sunset.jpg',
        // 3 — Restaurant & Menu
        'starter-restaurant-face'       => 'people-avatars/woman-apron-short-hair-kitchen.jpg',
        'starter-restaurant-cover'      => 'biolink-backgrounds/wood-herringbone-texture.jpg',
        'starter-restaurant-g1'         => 'grid-images/couple-eating-outdoors-tree.jpg',
        'starter-restaurant-g2'         => 'grid-images/vintage-oil-lamp-wooden-door.jpg',
        'starter-restaurant-g3'         => 'grid-images/flowers-vase-sheer-curtain-figurine.jpg',
        'starter-restaurant-review'     => 'people-avatars/woman-coffee-cafe-portrait.jpg',
        // 4 — Event & RSVP
        'starter-event-face'            => 'people-avatars/woman-striped-shirt-city-night.jpg',
        'starter-event-cover'           => 'grid-images/woman-sitting-outdoor-event-crowd.jpg',
        // 5 — Portfolio & Work
        'starter-portfolio-face'        => 'people-avatars/man-beard-glasses-thoughtful-portrait.jpg',
        'starter-portfolio-g1'          => 'grid-images/artist-painting-canvas-window.jpg',
        'starter-portfolio-g2'          => 'grid-images/charcoal-drawing-hands-sketching.jpg',
        'starter-portfolio-g3'          => 'grid-images/pantone-color-swatches-vintage-photos.jpg',
        'starter-portfolio-g4'          => 'grid-images/hand-drawing-eye-sketch.jpg',
        'starter-portfolio-g5'          => 'grid-images/woman-painting-sculpture-busts.jpg',
        'starter-portfolio-g6'          => 'grid-images/colorful-project-booklets-shelf.jpg',
        // 6 — Overlap Hero
        'starter-overlap-face'          => 'people-avatars/man-cap-jacket-mountain-smiling.jpg',
        'starter-overlap-cover'         => 'grid-images/man-filming-camera-beach.jpg',
        // 7 — Pink Boutique
        'starter-boutique-hero'         => 'grid-images/women-selfie-shopping-bags-street.jpg',
        'starter-boutique-shop'         => 'grid-images/white-roses-gift-box.jpg',
        'starter-boutique-market'       => 'grid-images/perfume-bottle-blurred-flowers.jpg',
        'starter-boutique-g1'           => 'grid-images/bw-studio-photoshoot-woman-posing.jpg',
        'starter-boutique-g2'           => 'grid-images/women-sitting-steps-phones.jpg',
        'starter-boutique-g3'           => 'grid-images/two-women-grass-selfie.jpg',
        // 8 — Split Hero
        'starter-splithero-face'        => 'people-avatars/woman-blonde-white-outfit-studio.jpg',
        'starter-splithero-bg'          => 'biolink-backgrounds/blurred-pink-flowers-motion.jpg',
        // 10 — Split Hero Tiles
        'starter-splithero-photo'       => 'people-avatars/woman-blazer-office-window-portrait.jpg',
        // 12 — Astrid Two-Column
        'starter-astrid-photo'          => 'grid-images/woman-painting-easel-sunset-field.jpg',
        'starter-astrid-g1'             => 'grid-images/man-painting-mural-brick-wall.jpg',
        'starter-astrid-g2'             => 'grid-images/notebook-quote-tea-glasses.jpg',
        'starter-astrid-g3'             => 'grid-images/woman-sitting-trashcan-urban-wall.jpg',
        // 13 — Purple Split
        'starter-purple-split-portrait' => 'people-avatars/man-curly-hair-purple-lighting.jpg',
        // 14 — Pressed Botanicals
        'starter-botanical-g1'          => 'grid-images/laptop-flowers-coffee-flatlay.jpg',
        'starter-botanical-g2'          => 'grid-images/camera-flower-map-flatlay.jpg',
        'starter-botanical-g3'          => 'grid-images/wedding-stage-decor-flowers.jpg',
        // 15 — Torn Paper Studio
        'starter-torn-avatar'           => 'people-avatars/bw-man-camera-photographer-portrait.jpg',
        'starter-torn-backdrop'         => 'grid-images/aerial-photographer-studio-shoot.jpg',
    ];

    /** CDN URL for a curated platform asset, or null when unmapped. */
    private function mappedAsset(string $seed): ?string
    {
        $rel = self::ASSET_MAP[$seed] ?? null;
        return $rel === null ? null : PlatformAssetCatalog::urlForKey('assets/' . $rel);
    }

    /**
     * Real platform photography for starter templates: each seed slug maps
     * to a curated S3 platform asset (served via CDN). Unmapped seeds fall
     * back to the self-hosted placeholder SVGs bundled with the app
     * (public/block-placeholders/*.svg), picked by aspect ratio.
     */
    private function photo(string $keywords, int $w, int $h, string $seed): string
    {
        if (($url = $this->mappedAsset($seed)) !== null) {
            return $url;
        }
        if ($w === $h) {
            return asset('block-placeholders/image-square.svg');
        }
        if ($h > 0 && $w / $h >= 2) {
            return asset('block-placeholders/cover.svg');
        }
        return asset('block-placeholders/image.svg');
    }

    /** Curated platform avatar, falling back to the bundled placeholder. */
    private function face(string $seed, int $size = 200): string
    {
        return $this->mappedAsset($seed) ?? asset('block-placeholders/avatar.svg');
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
