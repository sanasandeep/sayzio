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
    /** Bump when the starter blueprints below are redesigned. */
    public const SEED_VERSION = 2;

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
                    'thumbnail_url'        => null,
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
        $img = fn(string $key, int $w = 600, int $h = 400) => "https://picsum.photos/seed/starter-{$key}/{$w}/{$h}";
        $kits = $this->variantKits();

        return [
            // 1 — Personal landing: glass profile, about, FAQ, featured
            // links, a glowing review, socials.
            [
                'slug' => 'starter-personal-landing',
                'name' => 'Personal Landing',
                'category' => 'general',
                'description' => 'A friendly intro page with a glass profile, a short about, a quick FAQ, a few featured links, and your socials.',
                'recommended_personas' => ['creator', 'business', 'other'],
                'snapshot' => $this->snapshot([
                    $this->profile('Your Name', 'Welcome — here\'s where to find me online.', $img('personal-avatar', 200, 200), $kits['personal'], $img('personal-cover', 1200, 480)),
                    $this->heading('About me', 'h3'),
                    $this->paragraph("A short note about who you are and what you do. Replace this with your own intro."),
                    $this->paragraph("Working on something together? The links below are the fastest way in — or skim the FAQ first."),
                    $this->faq([
                        ['question' => 'What do you do?', 'answer' => 'Replace this with a one-line summary of what you help people with.'],
                        ['question' => 'How can I reach you?', 'answer' => 'Use any of the links below — I read every message that comes in.'],
                        ['question' => 'Where are you based?', 'answer' => 'Add your city and time zone here so people know when to expect a reply.'],
                    ]),
                    $this->link('My website', 'https://example.com', 'fa-globe', $kits['personal']),
                    $this->link('Latest project', 'https://example.com', 'fa-bookmark', $kits['personal']),
                    $this->link('Get in touch', 'mailto:hi@example.com', 'fa-envelope', $kits['personal']),
                    $this->review('Taylor M.', 5, "Friendly, fast, and genuinely good at what they do — exactly who you want to work with.", $img('personal-rev', 80, 80)),
                    $this->socials(),
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

            // 2 — Link in bio: cover-hero profile, big featured links,
            // testimonials, socials. The classic "linktree-style" start.
            [
                'slug' => 'starter-link-in-bio',
                'name' => 'Link in Bio',
                'category' => 'general',
                'description' => 'Classic link-in-bio layout — a cover-hero profile, a stack of big featured buttons, real testimonials, and socials.',
                'recommended_personas' => ['creator', 'influencer', 'artist'],
                'snapshot' => $this->snapshot([
                    $this->profile('Your Name', 'All my links in one place.', $img('linkbio-avatar', 200, 200), $kits['linkbio'], $img('linkbio-cover', 1200, 480)),
                    $this->linkBig('Latest video', 'https://example.com', 'fa-play', $kits['linkbio']),
                    $this->linkBig("What I'm working on", 'https://example.com', 'fa-rocket', $kits['linkbio']),
                    $this->linkBig('Newsletter', 'https://example.com', 'fa-envelope-open', $kits['linkbio']),
                    $this->linkBig('Shop', 'https://example.com', 'fa-bag-shopping', $kits['linkbio']),
                    $this->testimonials([
                        ['name' => 'Riya A.', 'avatar' => $img('linkbio-t1', 80, 80), 'rating' => 5, 'text' => "Followed for the videos, stayed for everything else. So good."],
                        ['name' => 'Kofi B.', 'avatar' => $img('linkbio-t2', 80, 80), 'rating' => 5, 'text' => "The newsletter alone is worth the follow. Highly recommend."],
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

            // 3 — Restaurant menu: badge profile, menu list, a promo
            // coupon, a diner review, and a reservation CTA.
            [
                'slug' => 'starter-restaurant-menu',
                'name' => 'Restaurant Menu',
                'category' => 'product',
                'description' => 'A badge profile header, today\'s menu as a list, a promo coupon, a diner review, and a clear "book a table" call-to-action.',
                'recommended_personas' => ['business'],
                'snapshot' => $this->snapshot([
                    $this->profile('Casa Verde', 'Open today · 12:00 — 22:00', $img('restaurant-avatar', 200, 200), $kits['restaurant'], $img('restaurant-cover', 1200, 480), [
                        'badges' => [['label' => 'Open now'], ['label' => 'Reservations'], ['label' => 'Vegan options']],
                    ]),
                    $this->image($img('restaurant-hero', 1200, 600)),
                    $this->heading("Today's menu", 'h3'),
                    $this->list([
                        'Burrata, heirloom tomato, basil — $14',
                        'Wood-fired margherita — $16',
                        'Slow-braised short rib, polenta — $28',
                        'Tiramisu (made in-house) — $9',
                    ]),
                    $this->coupon('FIRSTBITE', 'Show this code for a free dessert with any main.', 'Dec 31, 2026'),
                    $this->review('Elena R.', 5, "The short rib is unreal and the room is gorgeous. Already booked our next visit.", $img('restaurant-rev', 80, 80)),
                    $this->ctaButton('Book a table', 'https://example.com', '#dc2626', '#ffffff'),
                    $this->link('Call us', 'tel:+10000000000', 'fa-phone', $kits['restaurant']),
                    $this->link('Find us', 'https://maps.google.com', 'fa-map-marker-alt', $kits['restaurant']),
                    $this->socials(),
                ], [
                    'background_type'    => 'gradient',
                    'background_gradient' => 'linear-gradient(180deg, #1c1917 0%, #44403c 100%)',
                    'theme_color'        => '#dc2626',
                    'font_color'         => '#fef3c7',
                    'button_color'       => '#dc2626',
                    'button_text_color'  => '#ffffff',
                    'button_style'       => 'rounded',
                ]),
            ],

            // 4 — Event invite: countdown, hero image, a schedule
            // timeline, an audience poll, and an RSVP CTA.
            [
                'slug' => 'starter-event-invite',
                'name' => 'Event Invite',
                'category' => 'event',
                'description' => 'Countdown to the big day, hero image, a run-of-show timeline, an audience poll, and an RSVP button.',
                'recommended_personas' => ['business', 'musician', 'coach'],
                'snapshot' => $this->snapshot([
                    $this->heading("You're invited", 'h2'),
                    $this->paragraph('Saturday, August 22 · Doors at 7pm'),
                    $this->countdown('Starts in', '+30 days'),
                    $this->image($img('event-hero', 1200, 600)),
                    $this->heading('Run of show', 'h3'),
                    $this->timeline([
                        ['title' => 'Doors open', 'description' => 'Grab a drink and find your people.', 'date' => '7:00 PM'],
                        ['title' => 'Live music', 'description' => 'The main set kicks off.', 'date' => '8:00 PM'],
                        ['title' => 'Surprise guest', 'description' => 'You\'ll want to be there for this one.', 'date' => '12:00 AM'],
                    ]),
                    $this->poll('Which set are you most excited for?', ['The opener', 'The headliner', 'The midnight surprise']),
                    $this->ctaButton('RSVP now', 'https://example.com', '#7c3aed', '#ffffff'),
                    $this->link('Add to calendar', 'https://example.com', 'fa-calendar-plus', $kits['event']),
                    $this->link('Venue & directions', 'https://maps.google.com', 'fa-map-marker-alt', $kits['event']),
                    $this->socials(),
                ], [
                    'background_type'    => 'gradient',
                    'background_gradient' => 'linear-gradient(135deg, #0f172a 0%, #581c87 50%, #a855f7 100%)',
                    'theme_color'        => '#a855f7',
                    'font_color'         => '#ffffff',
                    'button_color'       => '#a855f7',
                    'button_text_color'  => '#ffffff',
                    'button_style'       => 'shadow',
                ]),
            ],

            // 5 — Portfolio: stats profile, six-shot image grid,
            // testimonials, a print product, and an inquire CTA.
            [
                'slug' => 'starter-portfolio',
                'name' => 'Portfolio',
                'category' => 'portfolio',
                'description' => 'Editorial portfolio layout — a stats profile, a six-shot image grid, client testimonials, a print to buy, and an inquiry CTA.',
                'recommended_personas' => ['artist', 'photographer', 'developer', 'writer'],
                'snapshot' => $this->snapshot([
                    $this->profile('Your Name', 'Selected work, 2024 — present.', $img('portfolio-avatar', 200, 200), $kits['portfolio'], $img('portfolio-cover', 1200, 480)),
                    $this->heading('Selected work', 'h3'),
                    $this->imageGrid([
                        $img('portfolio-1', 400, 400),
                        $img('portfolio-2', 400, 400),
                        $img('portfolio-3', 400, 400),
                        $img('portfolio-4', 400, 400),
                        $img('portfolio-5', 400, 400),
                        $img('portfolio-6', 400, 400),
                    ], 3),
                    $this->testimonials([
                        ['name' => 'Mara V.', 'avatar' => $img('portfolio-t1', 80, 80), 'rating' => 5, 'text' => "Captured exactly the mood we were after. A pleasure start to finish."],
                        ['name' => 'Owen D.', 'avatar' => $img('portfolio-t2', 80, 80), 'rating' => 5, 'text' => "Professional, creative, and fast. The work speaks for itself."],
                    ]),
                    $this->product('Signature Print', 'A museum-quality print of one of my favourite pieces, ready to frame.', '$45', $img('portfolio-print', 600, 600), 'https://example.com', 'New'),
                    $this->paragraph('Available for commissions and collaborations through the rest of the year.'),
                    $this->ctaButton('Inquire about my work', 'https://example.com', '#f59e0b', '#0f172a'),
                    $this->socials(),
                ], [
                    'background_type'   => 'color',
                    'background_color'  => '#0a0a0a',
                    'theme_color'       => '#f5f5f5',
                    'font_color'        => '#f5f5f5',
                    'button_color'      => '#f5f5f5',
                    'button_text_color' => '#0a0a0a',
                    'button_style'      => 'square',
                ]),
            ],
        ];
    }

    /* ──────────────────── snapshot + block helpers ──────────────────── */

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

    private function ctaButton(string $text, string $url, string $color = '#7c3aed', string $textColor = '#ffffff', string $size = 'lg'): array
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
}
