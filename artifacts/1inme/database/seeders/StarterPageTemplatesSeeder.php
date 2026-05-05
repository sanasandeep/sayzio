<?php

namespace Database\Seeders;

use App\Modules\Admin\Models\PageTemplate;
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
 * Slug namespace `starter-*` is intentionally distinct from the
 * `persona-*` namespace owned by ExpandedPageTemplateLibrarySeeder so
 * its auto-refresh / outdated-blueprint logic leaves these alone.
 */
class StarterPageTemplatesSeeder extends Seeder
{
    public function run(): void
    {
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
     * @return array<int, array{slug:string,name:string,category:string,description:string,recommended_personas:array<int,string>,snapshot:array}>
     */
    private function templates(): array
    {
        $img = fn(string $key, int $w = 600, int $h = 400) => "https://picsum.photos/seed/starter-{$key}/{$w}/{$h}";

        return [
            // 1 — Personal landing: profile, about-me, featured links, socials.
            [
                'slug' => 'starter-personal-landing',
                'name' => 'Personal Landing',
                'category' => 'general',
                'description' => 'A friendly intro page with a short about, a few featured links, and your socials.',
                'recommended_personas' => ['creator', 'business', 'other'],
                'snapshot' => $this->snapshot([
                    $this->profile('Your Name', 'Welcome — here\'s where to find me online.', $img('personal-avatar', 200, 200)),
                    $this->heading('About me', 'h3'),
                    $this->paragraph("A short note about who you are and what you do. Replace this with your own intro."),
                    $this->divider(),
                    $this->link('My website', 'https://example.com', 'fa-globe'),
                    $this->link('Latest project', 'https://example.com', 'fa-bookmark'),
                    $this->link('Get in touch', 'mailto:hi@example.com', 'fa-envelope'),
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

            // 2 — Link in bio: profile, big featured links, socials. The
            // classic "linktree-style" starting point.
            [
                'slug' => 'starter-link-in-bio',
                'name' => 'Link in Bio',
                'category' => 'general',
                'description' => 'Classic link-in-bio layout — profile up top, a stack of featured buttons, socials below.',
                'recommended_personas' => ['creator', 'influencer', 'artist'],
                'snapshot' => $this->snapshot([
                    $this->profile('Your Name', 'All my links in one place.', $img('linkbio-avatar', 200, 200)),
                    $this->linkBig('Latest video', 'https://example.com', 'fa-play'),
                    $this->linkBig("What I'm working on", 'https://example.com', 'fa-rocket'),
                    $this->linkBig('Newsletter', 'https://example.com', 'fa-envelope-open'),
                    $this->linkBig('Shop', 'https://example.com', 'fa-bag-shopping'),
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

            // 3 — Restaurant menu: hero image, menu list, reservation CTA.
            [
                'slug' => 'starter-restaurant-menu',
                'name' => 'Restaurant Menu',
                'category' => 'product',
                'description' => 'Hero photo, today\'s menu as a list, and a clear "book a table" call-to-action.',
                'recommended_personas' => ['business'],
                'snapshot' => $this->snapshot([
                    $this->heading('Casa Verde', 'h2'),
                    $this->paragraph('Open today · 12:00 — 22:00'),
                    $this->image($img('restaurant-hero', 1200, 600)),
                    $this->heading("Today's menu", 'h3'),
                    $this->list([
                        'Burrata, heirloom tomato, basil — $14',
                        'Wood-fired margherita — $16',
                        'Slow-braised short rib, polenta — $28',
                        'Tiramisu (made in-house) — $9',
                    ]),
                    $this->ctaButton('Book a table', 'https://example.com', '#dc2626', '#ffffff'),
                    $this->link('Call us', 'tel:+10000000000', 'fa-phone'),
                    $this->link('Find us', 'https://maps.google.com', 'fa-map-marker-alt'),
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

            // 4 — Event invite: countdown, hero image, details, RSVP CTA.
            [
                'slug' => 'starter-event-invite',
                'name' => 'Event Invite',
                'category' => 'event',
                'description' => 'Countdown to the big day, hero image, key details, and an RSVP button.',
                'recommended_personas' => ['business', 'musician', 'coach'],
                'snapshot' => $this->snapshot([
                    $this->heading("You're invited", 'h2'),
                    $this->paragraph('Saturday, August 22 · Doors at 7pm'),
                    $this->countdown('Starts in', '+30 days'),
                    $this->image($img('event-hero', 1200, 600)),
                    $this->heading('What to expect', 'h3'),
                    $this->list([
                        'Live music from 8pm',
                        'Drinks and bites all night',
                        'Surprise guest at midnight',
                    ]),
                    $this->ctaButton('RSVP now', 'https://example.com', '#7c3aed', '#ffffff'),
                    $this->link('Add to calendar', 'https://example.com', 'fa-calendar-plus'),
                    $this->link('Venue & directions', 'https://maps.google.com', 'fa-map-marker-alt'),
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

            // 5 — Portfolio: profile, six-shot image grid, inquire CTA.
            [
                'slug' => 'starter-portfolio',
                'name' => 'Portfolio',
                'category' => 'portfolio',
                'description' => 'Editorial portfolio layout — profile, a six-shot image grid, and an inquiry CTA.',
                'recommended_personas' => ['artist', 'photographer', 'developer', 'writer'],
                'snapshot' => $this->snapshot([
                    $this->profile('Your Name', 'Selected work, 2024 — present.', $img('portfolio-avatar', 200, 200)),
                    $this->heading('Selected work', 'h3'),
                    $this->imageGrid([
                        $img('portfolio-1', 400, 400),
                        $img('portfolio-2', 400, 400),
                        $img('portfolio-3', 400, 400),
                        $img('portfolio-4', 400, 400),
                        $img('portfolio-5', 400, 400),
                        $img('portfolio-6', 400, 400),
                    ], 3),
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
        // No `meta.seed_version` here: starter rows aren't owned by the
        // persona-blueprint auto-refresh path, so they don't need (and
        // shouldn't carry) the persona seeder's version stamp.
        return [
            'biolink' => $biolink,
            'blocks'  => $blocks,
        ];
    }

    private function block(string $type, array $settings): array
    {
        return ['type' => $type, 'settings' => $settings, 'is_active' => true];
    }

    private function profile(string $name, string $bio, string $avatar = ''): array
    {
        return $this->block('profile_card_v1', [
            'name'   => $name,
            'title'  => '',
            'avatar' => $avatar,
            'bio'    => $bio,
        ]);
    }

    private function heading(string $text, string $size = 'h3'): array
    {
        return $this->block('heading', ['text' => $text, 'size' => $size, 'align' => 'center']);
    }

    private function paragraph(string $text): array
    {
        return $this->block('paragraph', ['text' => $text, 'align' => 'center']);
    }

    private function link(string $text, string $url, string $icon = ''): array
    {
        return $this->block('link', ['text' => $text, 'url' => $url, 'icon' => $icon]);
    }

    private function linkBig(string $text, string $url, string $icon = ''): array
    {
        return $this->block('link_big', ['text' => $text, 'url' => $url, 'icon' => $icon]);
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
