<?php

namespace Database\Seeders;

use App\Modules\Admin\Models\PageTemplate;
use App\Modules\User\Services\PersonaCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Ensures every persona has at least 10 active page templates so the
 * onboarding "Recommended for you" shelf is populated for everyone.
 *
 * Idempotent: a persona that already has >= 10 active templates tagged
 * with that slug in `recommended_personas` is skipped entirely so admin
 * curation isn't clobbered. Runs after PageTemplatePersonaSeeder.
 *
 * Templates are normal PageTemplate rows — admins can rename them,
 * swap thumbnails, or deactivate them from the standard admin UI.
 */
class ExpandedPageTemplateLibrarySeeder extends Seeder
{
    private const MIN_PER_PERSONA = 10;

    public function run(): void
    {
        // Index existing tagged templates per persona (PHP-side so this
        // works on Postgres / MySQL / SQLite without JSON-op gymnastics).
        $countsBySlug = [];
        PageTemplate::query()->where('is_active', true)->get(['recommended_personas'])->each(function ($t) use (&$countsBySlug) {
            foreach ((array) ($t->recommended_personas ?? []) as $slug) {
                $countsBySlug[$slug] = ($countsBySlug[$slug] ?? 0) + 1;
            }
        });

        foreach (PersonaCatalog::all() as $persona) {
            $slug = $persona['slug'];
            $have = $countsBySlug[$slug] ?? 0;
            if ($have >= self::MIN_PER_PERSONA) {
                continue;
            }

            $blueprints = $this->blueprintsFor($persona);
            $needed = self::MIN_PER_PERSONA - $have;
            $created = 0;

            foreach ($blueprints as $i => $bp) {
                if ($created >= $needed) break;
                $tplSlug = 'persona-' . $slug . '-' . Str::slug($bp['key']);

                // Skip if a template with this slug already exists — we never
                // want to overwrite admin edits on re-run. The "do we still
                // need more?" gate above ensures we top up to 10 only by
                // adding missing variants, not by clobbering existing rows.
                if (PageTemplate::where('slug', $tplSlug)->exists()) {
                    continue;
                }

                PageTemplate::create([
                    'slug'                 => $tplSlug,
                    'name'                 => $bp['name'],
                    'category'             => $slug,
                    'description'          => $bp['description'],
                    'thumbnail_url'        => $bp['thumb'],
                    'plan_tier'            => null,
                    'is_active'            => true,
                    'sort_order'           => 100 + $i,
                    'recommended_personas' => [$slug],
                    'snapshot'             => $bp['snapshot'],
                ]);
                $created++;
            }
        }
    }

    /**
     * Build the 10 blueprint variants for a persona. Each is parameterised
     * with the persona's label/blurb so names and copy stay on-brand
     * without requiring a hand-written entry per persona.
     *
     * @return array<int, array{key:string,name:string,description:string,thumb:string,snapshot:array}>
     */
    private function blueprintsFor(array $persona): array
    {
        $label = $persona['label'];
        $blurb = $persona['blurb'] ?? 'Welcome to my page.';
        $slug = $persona['slug'];

        $thumb = fn(string $variant) => "https://picsum.photos/seed/tpl-{$slug}-{$variant}/600/400";

        return [
            [
                'key' => 'starter',
                'name' => "{$label} — Starter",
                'description' => "A clean intro page tuned for {$label}. Profile, bio, and your top links.",
                'thumb' => $thumb('starter'),
                'snapshot' => $this->snapshot([
                    $this->profile($label, $blurb),
                    $this->heading('My Links', 'h3'),
                    $this->link('Website', 'https://example.com', 'fa-globe'),
                    $this->link('Latest project', 'https://example.com', 'fa-bookmark'),
                    $this->socials(),
                ]),
            ],
            [
                'key' => 'links-stack',
                'name' => "{$label} — Featured Links",
                'description' => "Four big featured buttons — perfect when links are the whole point.",
                'thumb' => $thumb('links'),
                'snapshot' => $this->snapshot([
                    $this->profile($label, $blurb),
                    $this->linkBig("What I'm working on now", 'https://example.com', 'fa-rocket'),
                    $this->linkBig('Latest update', 'https://example.com', 'fa-newspaper'),
                    $this->linkBig('Get in touch', 'https://example.com', 'fa-envelope'),
                    $this->linkBig('Subscribe', 'https://example.com', 'fa-bell'),
                    $this->socials(),
                ]),
            ],
            [
                'key' => 'about-cta',
                'name' => "{$label} — About + CTA",
                'description' => "About-me block with a single, focused call to action below.",
                'thumb' => $thumb('about'),
                'snapshot' => $this->snapshot([
                    $this->profile($label, $blurb),
                    $this->heading('About me', 'h3'),
                    $this->paragraph("I'm a {$label}. {$blurb}"),
                    $this->divider(),
                    $this->linkBig('Work with me', 'https://example.com', 'fa-handshake'),
                    $this->socials(),
                ]),
            ],
            [
                'key' => 'newsletter',
                'name' => "{$label} — Newsletter",
                'description' => "Lead with an email signup. Best when growing a list is your #1 goal.",
                'thumb' => $thumb('news'),
                'snapshot' => $this->snapshot([
                    $this->profile($label, $blurb),
                    $this->heading('Get updates from me', 'h3'),
                    $this->paragraph('One short note when there is something new. No spam.'),
                    $this->emailCollector(),
                    $this->socials(),
                ]),
            ],
            [
                'key' => 'gallery',
                'name' => "{$label} — Gallery",
                'description' => "Visual-first layout with a portfolio grid and a request-to-book CTA.",
                'thumb' => $thumb('gallery'),
                'snapshot' => $this->snapshot([
                    $this->profile($label, $blurb),
                    $this->heading('Portfolio', 'h3'),
                    $this->imageGrid(),
                    $this->linkBig('Inquire about my work', 'https://example.com', 'fa-envelope'),
                    $this->socials(),
                ]),
            ],
            [
                'key' => 'services',
                'name' => "{$label} — Services",
                'description' => "List of services or packages with a primary booking action.",
                'thumb' => $thumb('services'),
                'snapshot' => $this->snapshot([
                    $this->profile($label, $blurb),
                    $this->heading('What I offer', 'h3'),
                    $this->list([
                        '1:1 consultation — 60 min',
                        'Starter package — get up and running',
                        'Pro package — full hands-on engagement',
                    ]),
                    $this->linkBig('Book now', 'https://example.com', 'fa-calendar-check'),
                    $this->socials(),
                ]),
            ],
            [
                'key' => 'testimonials',
                'name' => "{$label} — Social Proof",
                'description' => "Lead with kind words from real people. Builds trust fast.",
                'thumb' => $thumb('proof'),
                'snapshot' => $this->snapshot([
                    $this->profile($label, $blurb),
                    $this->heading('What people say', 'h3'),
                    $this->paragraph('"Genuinely a delight to work with. Recommend without hesitation." — A. Customer'),
                    $this->divider(),
                    $this->paragraph('"Made a real difference for our team. Wish we\'d started sooner." — B. Client'),
                    $this->linkBig("See more reviews", 'https://example.com', 'fa-star'),
                    $this->socials(),
                ]),
            ],
            [
                'key' => 'contact',
                'name' => "{$label} — Contact Hub",
                'description' => "All the ways to reach you in one tidy spot.",
                'thumb' => $thumb('contact'),
                'snapshot' => $this->snapshot([
                    $this->profile($label, $blurb),
                    $this->heading('Get in touch', 'h3'),
                    $this->link('Email', 'mailto:hi@example.com', 'fa-envelope'),
                    $this->link('Call', 'tel:+10000000000', 'fa-phone'),
                    $this->link('WhatsApp', 'https://wa.me/10000000000', 'fa-whatsapp'),
                    $this->link('Book a slot', 'https://example.com', 'fa-calendar'),
                    $this->socials(),
                ]),
            ],
            [
                'key' => 'feature',
                'name' => "{$label} — Feature Image",
                'description' => "Big cover image up top, intro and primary action underneath.",
                'thumb' => $thumb('feature'),
                'snapshot' => $this->snapshot([
                    $this->image(),
                    $this->heading($label, 'h2'),
                    $this->paragraph($blurb),
                    $this->linkBig('Learn more', 'https://example.com', 'fa-arrow-right'),
                    $this->socials(),
                ]),
            ],
            [
                'key' => 'faq-contact',
                'name' => "{$label} — FAQ + Contact",
                'description' => "Answer the common questions then funnel into a single contact CTA.",
                'thumb' => $thumb('faq'),
                'snapshot' => $this->snapshot([
                    $this->profile($label, $blurb),
                    $this->heading('Frequently asked', 'h3'),
                    $this->list([
                        'How do we start? — Drop me a message and we\'ll set up a quick intro.',
                        'How much does it cost? — Depends on scope; I share a range upfront.',
                        'How fast can we start? — Usually within a week or two.',
                    ]),
                    $this->divider(),
                    $this->linkBig("Send me a message", 'https://example.com', 'fa-paper-plane'),
                    $this->socials(),
                ]),
            ],
        ];
    }

    // ───────── snapshot + block helpers (kept tiny — TemplateService re-sanitizes) ─────────

    private function snapshot(array $blocks): array
    {
        return ['biolink' => [], 'blocks' => $blocks];
    }

    private function block(string $type, array $settings): array
    {
        return ['type' => $type, 'settings' => $settings, 'is_active' => true];
    }

    private function profile(string $label, string $blurb): array
    {
        return $this->block('profile_card_v1', [
            'name'  => 'Your Name',
            'title' => $label,
            'bio'   => $blurb,
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

    private function socials(): array
    {
        return $this->block('socials_multi', []);
    }

    private function emailCollector(): array
    {
        return $this->block('email_collector', ['placeholder' => 'you@email.com', 'button_text' => 'Subscribe']);
    }

    private function image(): array
    {
        return $this->block('image', ['url' => '']);
    }

    private function imageGrid(): array
    {
        return $this->block('image_grid', []);
    }

    private function divider(): array
    {
        return $this->block('divider', []);
    }

    private function list(array $items): array
    {
        return $this->block('list', ['items' => $items]);
    }
}
