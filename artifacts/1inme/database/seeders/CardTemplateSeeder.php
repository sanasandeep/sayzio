<?php

namespace Database\Seeders;

use App\Modules\Admin\Models\CardTemplate;
use Illuminate\Database\Seeder;

/**
 * Seeds 50+ brand-new card-container templates across hero / cta / social /
 * contact / product / event / gallery / general categories, including a
 * curated premium gradient sub-set (~13 designs) inspired by the upsell-
 * style modal references.
 *
 * Each template is a snapshot of:
 *   { type: 'card', settings: {...}, is_active: true,
 *     children: [{type, settings, is_active}] }
 *
 * Children settings are intentionally minimal — TemplateService re-runs every
 * payload through BiolinkBlockController::sanitizeSettings, which fills in
 * defaults. We only set the few fields that meaningfully shape the template.
 *
 * grid_span on each child is on a 12-col grid; pick spans that line up
 * (12, 6+6, 4+4+4, etc).
 *
 * Idempotent — uses updateOrCreate by slug so re-running the seeder refreshes
 * existing templates instead of duplicating. Admin-customized rows are NEVER
 * overwritten. Old default templates whose slugs are no longer in this
 * blueprint AND that haven't been customized are deactivated so the gallery
 * is effectively replaced when SEED_VERSION is bumped.
 */
class CardTemplateSeeder extends Seeder
{
    /**
     * Bump this whenever the blueprints below change in a way you want
     * to push to existing untouched rows. Admin-edited rows are NEVER
     * overwritten regardless of this value.
     *
     * v2 (2026-05): Full library replacement — 50+ new designs with a
     * dedicated premium gradient sub-set inspired by the upsell modal
     * references. Old default templates not in this blueprint are
     * deactivated by run() (admin-customized ones are preserved).
     */
    public const SEED_VERSION = 2;

    public function run(): void
    {
        $blueprint = $this->templates();
        $knownSlugs = [];
        foreach ($blueprint as $i => $tpl) {
            $slug = $tpl['slug'];
            $knownSlugs[$slug] = true;
            $payload = [
                'name'         => $tpl['name'],
                'category'     => $tpl['category'],
                'description'  => $tpl['description'] ?? null,
                'plan_tier'    => $tpl['plan_tier'] ?? null,
                'is_active'    => true,
                'sort_order'   => $i,
                'snapshot'     => [
                    'type'      => 'card',
                    'settings'  => array_merge($this->defaultCardSettings(), $tpl['card'] ?? []),
                    'is_active' => true,
                    'children'  => $tpl['children'],
                ],
                'seed_version' => self::SEED_VERSION,
            ];

            $existing = CardTemplate::where('slug', $slug)->first();

            if (!$existing) {
                CardTemplate::create(['slug' => $slug] + $payload);
                continue;
            }

            if ($existing->wasCustomized()) {
                $fill = [];
                foreach (['name', 'category', 'description', 'plan_tier', 'snapshot'] as $k) {
                    if ($k === 'snapshot') {
                        if (empty($existing->snapshot)) $fill['snapshot'] = $payload['snapshot'];
                    } elseif ($existing->{$k} === null || $existing->{$k} === '') {
                        $fill[$k] = $payload[$k];
                    }
                }
                if ($fill) {
                    $existing->fill($fill)->save();
                }
                continue;
            }

            if ((int) $existing->seed_version >= self::SEED_VERSION) {
                continue;
            }

            $existing->fill($payload)->save();
        }

        // Replace-mode cleanup: any previously-seeded default templates whose
        // slugs are not in the new blueprint are deactivated. Admin-customized
        // rows are never touched, so admins keep their work even when the
        // library is fully replaced.
        CardTemplate::query()
            ->whereNotIn('slug', array_keys($knownSlugs))
            ->where('is_active', true)
            ->get()
            ->each(function (CardTemplate $t) {
                if ($t->wasCustomized()) return;
                $t->is_active = false;
                $t->save();
            });
    }

    private function defaultCardSettings(): array
    {
        return [
            'title'         => '',
            'gap'           => 12,
            'padding'       => 16,
            'columns'       => 1,
            'bg_type'       => 'glass',
            'bg_color'      => 'rgba(255,255,255,0.06)',
            'glass_blur'    => 12,
            'glass_opacity' => 6,
            'border_color'  => 'rgba(255,255,255,0.08)',
            'border_width'  => 1,
            'border_radius' => 16,
            'shadow'        => 'md',
            'shadow_color'  => '#00000040',
        ];
    }

    /** Premium gradient card preset — pill-y, big radius, soft shadow, no border. */
    private function gradientCard(string $css, array $extra = []): array
    {
        return array_merge([
            'bg_type'       => 'gradient',
            'bg_gradient'   => $css,
            'border_radius' => 24,
            'padding'       => 28,
            'gap'           => 14,
            'border_width'  => 0,
            'border_color'  => 'transparent',
            'shadow'        => 'lg',
            'shadow_color'  => '#00000033',
        ], $extra);
    }

    /** Solid-color card preset. */
    private function solidCard(string $color, array $extra = []): array
    {
        return array_merge([
            'bg_type'       => 'color',
            'bg_color'      => $color,
            'border_radius' => 18,
            'padding'       => 22,
            'border_width'  => 0,
            'border_color'  => 'transparent',
            'shadow'        => 'md',
        ], $extra);
    }

    private function span(int $n): array
    {
        return ['_style' => ['grid_span' => $n]];
    }

    private function child(string $type, array $settings, int $span = 12): array
    {
        $settings = array_merge($settings, $this->span($span));
        return ['type' => $type, 'settings' => $settings, 'is_active' => true];
    }

    private function templates(): array
    {
        // Compact builders for the most common children. Mirrors the v1
        // helpers but with a tighter palette of options.
        $h = fn(string $text, string $size = 'h3', string $align = 'center', int $span = 12)
            => $this->child('heading', ['text' => $text, 'size' => $size, 'align' => $align], $span);
        $hg = fn(string $text, string $size = 'h2', string $align = 'center', int $span = 12)
            => $this->child('heading', ['text' => $text, 'size' => $size, 'align' => $align, 'style' => 'gradient'], $span);
        $hm = fn(string $text, string $size = 'h2', string $align = 'center', int $span = 12)
            => $this->child('heading', ['text' => $text, 'size' => $size, 'align' => $align, 'style' => 'animated'], $span);
        $p = fn(string $text, string $align = 'center', int $span = 12)
            => $this->child('paragraph', ['text' => $text, 'align' => $align], $span);
        $btn = fn(string $text, string $url = 'https://example.com', int $span = 12, string $icon = '')
            => $this->child('link_big', ['text' => $text, 'url' => $url, 'icon' => $icon], $span);
        $link = fn(string $text, string $url = 'https://example.com', int $span = 12, string $icon = '')
            => $this->child('link', ['text' => $text, 'url' => $url, 'icon' => $icon], $span);
        $img = fn(string $url = '', int $span = 12)
            => $this->child('image', ['url' => $url], $span);
        $div = fn(int $span = 12)
            => $this->child('divider', [], $span);
        $alert = fn(string $text, string $type = 'info', int $span = 12)
            => $this->child('alert', ['text' => $text, 'type' => $type], $span);
        $badge = fn(string $text, int $span = 4)
            => $this->child('badge', ['text' => $text], $span);
        $list = fn(array $items, int $span = 12)
            => $this->child('list', ['items' => $items], $span);
        $socials = fn(int $span = 12)
            => $this->child('socials_multi', [], $span);
        $email = fn(string $heading = 'Subscribe', int $span = 12)
            => $this->child('email_subscribe', ['heading' => $heading, 'title' => $heading, 'button_text' => 'Subscribe'], $span);
        $emailC = fn(string $placeholder = 'you@email.com', int $span = 12)
            => $this->child('email_collector', ['placeholder' => $placeholder, 'button_text' => 'Join'], $span);
        $form = fn(int $span = 12)
            => $this->child('contact_form', ['heading' => 'Get in touch'], $span);
        $wa = fn(int $span = 12)
            => $this->child('whatsapp_widget', ['phone' => '+10000000000', 'message' => 'Hi! I have a question.'], $span);
        $countdown = fn(string $title = 'Launching soon', int $span = 12)
            => $this->child('countdown', ['title' => $title, 'date' => now()->addDays(14)->toIso8601String()], $span);

        // ─── Premium gradient palette (used by the ~13-card premium sub-set) ───
        $G_PEACH    = 'linear-gradient(135deg, #fef3c7 0%, #fbcfe8 50%, #f9a8d4 100%)';
        $G_AURORA   = 'linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%)';
        $G_GALAXY   = 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #db2777 100%)';
        $G_OCEAN    = 'linear-gradient(135deg, #0ea5e9 0%, #6366f1 100%)';
        $G_LAVA     = 'linear-gradient(135deg, #f5576c 0%, #f093fb 100%)';
        $G_MINT     = 'linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%)';
        $G_SUNSET   = 'linear-gradient(135deg, #ff9a9e 0%, #fad0c4 50%, #fbc2eb 100%)';
        $G_ROSE     = 'linear-gradient(135deg, #fbc2eb 0%, #a6c1ee 100%)';
        $G_FOREST   = 'linear-gradient(135deg, #134e5e 0%, #71b280 100%)';
        $G_MIDNIGHT = 'linear-gradient(135deg, #0f172a 0%, #4338ca 50%, #1e1b4b 100%)';
        $G_CHAMP    = 'linear-gradient(135deg, #FFE5B4 0%, #FFCBA4 100%)';
        $G_CORAL    = 'linear-gradient(135deg, #ff6e7f 0%, #bfe9ff 100%)';
        $G_CYBER    = 'linear-gradient(135deg, #06b6d4 0%, #8b5cf6 50%, #ec4899 100%)';

        return [
            // ============================================================
            // HERO (6) — profile intros, founder/brand opener
            // ============================================================
            [
                'slug' => 'hero-profile-clean', 'name' => 'Profile · Clean', 'category' => 'hero',
                'description' => 'Avatar, name, bio and a tidy social bar. The classic opener.',
                'children' => [
                    $this->child('profile_card_v1', ['name' => 'Your Name', 'title' => 'What you do', 'bio' => 'A short, friendly bio about yourself.'], 12),
                    $socials(12),
                ],
            ],
            [
                'slug' => 'hero-founder-photo', 'name' => 'Founder Photo Hero', 'category' => 'hero',
                'description' => 'Photo + headline + paragraph + primary CTA. Personal-brand staple.',
                'children' => [
                    $img('', 12),
                    $h('Building things people love', 'h2'),
                    $p('Founder, designer, and lifelong tinkerer. Currently shipping the next thing.'),
                    $btn('See my work →'),
                ],
            ],
            [
                'slug' => 'hero-creator-gradient', 'name' => 'Creator · Gradient Hero', 'category' => 'hero',
                'plan_tier' => 'pro',
                'description' => 'Premium peach gradient hero with sparkle accents and a pill CTA.',
                'card' => $this->gradientCard($G_PEACH),
                'children' => [
                    $badge('✨ NEW DROP', 12),
                    $hg('Hey, I\'m a Creator', 'h1'),
                    $p('Weekly drops on design, code and culture — sent to inboxes only.'),
                    $emailC('you@email.com'),
                    $p('No spam. Unsubscribe anytime.'),
                ],
            ],
            [
                'slug' => 'hero-minimal-mono', 'name' => 'Minimal Mono Hero', 'category' => 'hero',
                'description' => 'A single morphing headline and one supporting line. Editorial vibe.',
                'card' => ['padding' => 28, 'gap' => 8, 'bg_type' => 'transparent', 'border_width' => 0, 'shadow' => 'none'],
                'children' => [
                    $hm('Less, but better.', 'h1'),
                    $p('A small, sharp landing page for makers.'),
                ],
            ],
            [
                'slug' => 'hero-team-member', 'name' => 'Team Member Card', 'category' => 'hero',
                'description' => 'Headshot, role, short bio and a way to reach them. 4+8 split.',
                'card' => ['columns' => 12, 'padding' => 20],
                'children' => [
                    $img('', 4),
                    $this->child('heading', ['text' => 'Alex Rivera', 'size' => 'h3', 'align' => 'left'], 8),
                    $p('Engineering Lead — distributed systems & developer experience.', 'left', 12),
                    $emailC('alex@company.com'),
                ],
            ],
            [
                'slug' => 'hero-brand', 'name' => 'Brand Hero', 'category' => 'hero',
                'description' => 'Logo headline + tagline + CTA. Polished for businesses.',
                'children' => [
                    $this->child('heading_logo', ['text' => 'YOURBRAND'], 12),
                    $p('Tools for the modern web.'),
                    $btn('Start free →'),
                ],
            ],

            // ============================================================
            // CTA (8) — newsletters, books, promos, premium upsells
            // ============================================================
            [
                'slug' => 'cta-newsletter-clean', 'name' => 'Newsletter · Clean', 'category' => 'cta',
                'description' => 'Headline, value prop and an email field. The highest-converting basic CTA.',
                'children' => [
                    $h('Get the weekly drop', 'h2'),
                    $p('One short email every Friday. No spam, ever.'),
                    $email('Join 5,000+ readers'),
                ],
            ],
            [
                'slug' => 'cta-book-a-call', 'name' => 'Book a Call', 'category' => 'cta',
                'description' => 'Pitch + big calendar button.',
                'children' => [
                    $h('Let\'s talk', 'h2'),
                    $p('Book a free 20-minute intro to see if we\'re a fit.'),
                    $btn('📅 Pick a time'),
                ],
            ],
            [
                'slug' => 'cta-app-stores', 'name' => 'Get the App', 'category' => 'cta',
                'description' => 'iOS + Android side-by-side store buttons.',
                'children' => [
                    $h('Get the app', 'h2'),
                    $p('Available on iOS and Android — free to download.'),
                    $btn('App Store', 'https://apps.apple.com', 6, 'fa-apple'),
                    $btn('Google Play', 'https://play.google.com', 6, 'fa-google-play'),
                ],
            ],
            [
                'slug' => 'cta-premium-upsell', 'name' => 'Premium · Upsell Modal', 'category' => 'cta',
                'plan_tier' => 'pro',
                'description' => 'Reference-style premium upsell card: peach gradient, sparkle badge, glass logo tile, three checkmark bullets, big pill CTA, fine-print footer.',
                'card' => $this->gradientCard($G_PEACH, ['padding' => 32, 'gap' => 16]),
                'children' => [
                    $badge('✨ PREMIUM', 12),
                    $this->child('heading', ['text' => 'Unlock everything', 'size' => 'h1', 'align' => 'center'], 12),
                    $p('Go premium for one flat price.'),
                    $list([
                        '✓ Unlimited links and blocks',
                        '✓ Premium card templates',
                        '✓ Detailed analytics & exports',
                    ], 12),
                    $btn('Upgrade now — $9/mo'),
                    $p('Cancel anytime. 30-day money-back guarantee.'),
                ],
            ],
            [
                'slug' => 'cta-aurora-trial', 'name' => 'Premium · Aurora Trial', 'category' => 'cta',
                'plan_tier' => 'pro',
                'description' => 'Aurora gradient SaaS trial card with a single pill CTA.',
                'card' => $this->gradientCard($G_AURORA),
                'children' => [
                    $badge('14 DAYS · FREE', 12),
                    $hg('Start your free trial', 'h1'),
                    $p('Full access for 14 days. No credit card required.'),
                    $btn('Start free →'),
                ],
            ],
            [
                'slug' => 'cta-galaxy-waitlist', 'name' => 'Premium · Galaxy Waitlist', 'category' => 'cta',
                'plan_tier' => 'pro',
                'description' => 'Galaxy gradient coming-soon teaser with email capture.',
                'card' => $this->gradientCard($G_GALAXY),
                'children' => [
                    $badge('★ EARLY ACCESS', 12),
                    $hm('Coming soon', 'h1'),
                    $p('Drop your email to get the keys on launch day.'),
                    $emailC('you@email.com'),
                ],
            ],
            [
                'slug' => 'cta-urgency-promo', 'name' => 'Limited-Time Promo', 'category' => 'cta',
                'description' => 'Red alert + headline + redeem button. Use sparingly.',
                'children' => [
                    $alert('⏰ Ends Sunday at midnight', 'warning'),
                    $h('30% off everything', 'h2'),
                    $p('Use code FRIENDS at checkout.'),
                    $btn('Shop the sale'),
                ],
            ],
            [
                'slug' => 'cta-horizontal-promo', 'name' => 'Horizontal Promo', 'category' => 'cta',
                'description' => 'Compact image-left, copy-right promo card with a single CTA.',
                'card' => $this->solidCard('#0f172a', ['columns' => 12, 'padding' => 16]),
                'children' => [
                    $img('', 4),
                    $this->child('heading', ['text' => 'Save 20% this week', 'size' => 'h3', 'align' => 'left'], 8),
                    $this->child('paragraph', ['text' => 'Limited drop. Use code WEEK20.', 'align' => 'left'], 8),
                    $btn('Shop now →', 'https://example.com', 12),
                ],
            ],

            // ============================================================
            // SOCIAL / BUZZ (5) — testimonials, social proof, social links
            // ============================================================
            [
                'slug' => 'social-hub', 'name' => 'Social Hub', 'category' => 'social',
                'description' => 'A clean grid of every social profile.',
                'children' => [
                    $h('Find me here', 'h3'),
                    $socials(12),
                ],
            ],
            [
                'slug' => 'social-testimonial-quote', 'name' => 'Testimonial · Single Quote', 'category' => 'social',
                'description' => 'A pull-quote testimonial with attribution.',
                'children' => [
                    $h('What people are saying', 'h3'),
                    $this->child('paragraph', ['text' => '"Honestly the most useful tool I\'ve added this year. Pays for itself in week one."', 'align' => 'center'], 12),
                    $p('— Casey N., Founder · Studio Atlas'),
                ],
            ],
            [
                'slug' => 'social-testimonials-trio', 'name' => 'Testimonials · Three Up', 'category' => 'social',
                'description' => 'Three short testimonials in a 4+4+4 row.',
                'card' => ['columns' => 12, 'gap' => 14],
                'children' => [
                    $this->child('paragraph', ['text' => '"Game-changer."', 'align' => 'center'], 4),
                    $this->child('paragraph', ['text' => '"Just works."', 'align' => 'center'], 4),
                    $this->child('paragraph', ['text' => '"Worth every cent."', 'align' => 'center'], 4),
                ],
            ],
            [
                'slug' => 'social-press-row', 'name' => 'As Seen In', 'category' => 'social',
                'description' => 'Row of press logos to build credibility.',
                'card' => ['columns' => 12],
                'children' => [
                    $h('As seen in', 'h3'),
                    $img('', 3), $img('', 3), $img('', 3), $img('', 3),
                ],
            ],
            [
                'slug' => 'social-cyber-callout', 'name' => 'Premium · Cyber Social Callout', 'category' => 'social',
                'plan_tier' => 'pro',
                'description' => 'Cyber-bright gradient card — handle, follower stat, and a follow button.',
                'card' => $this->gradientCard($G_CYBER),
                'children' => [
                    $hg('@yourhandle', 'h1'),
                    $p('120k followers · daily drops on creative tooling'),
                    $btn('Follow on Instagram', 'https://instagram.com', 12, 'fab fa-instagram'),
                    $socials(12),
                ],
            ],

            // ============================================================
            // CONTACT (5) — lead capture, forms, chat
            // ============================================================
            [
                'slug' => 'contact-form-clean', 'name' => 'Contact Form · Clean', 'category' => 'contact',
                'description' => 'Heading, intro line and an inline contact form.',
                'children' => [
                    $h('Get in touch', 'h2'),
                    $p('I read every message. Expect a reply within a day or two.'),
                    $form(),
                ],
            ],
            [
                'slug' => 'contact-whatsapp-quick', 'name' => 'WhatsApp · Quick Chat', 'category' => 'contact',
                'description' => 'One-tap WhatsApp button with an opener message.',
                'children' => [
                    $h('Message me on WhatsApp', 'h3'),
                    $p('Fastest way to reach me — usually replies the same day.'),
                    $wa(),
                ],
            ],
            [
                'slug' => 'contact-multi-channel', 'name' => 'Multi-Channel Contact', 'category' => 'contact',
                'description' => 'Email, phone, WhatsApp — three quick links in a row.',
                'card' => ['columns' => 12],
                'children' => [
                    $h('Reach out', 'h3'),
                    $link('Email', 'mailto:hi@example.com', 4, 'fa-envelope'),
                    $link('Call', 'tel:+10000000000', 4, 'fa-phone'),
                    $link('WhatsApp', 'https://wa.me/10000000000', 4, 'fab fa-whatsapp'),
                ],
            ],
            [
                'slug' => 'contact-lead-capture', 'name' => 'Lead Capture', 'category' => 'contact',
                'description' => 'Pitch + email collector — for downloadables, lead magnets, etc.',
                'children' => [
                    $h('Free guide: Ship your first product', 'h3'),
                    $p('A 12-page PDF with the exact playbook I used.'),
                    $emailC('you@email.com'),
                ],
            ],
            [
                'slug' => 'contact-rose-vip', 'name' => 'Premium · VIP Contact', 'category' => 'contact',
                'plan_tier' => 'pro',
                'description' => 'Rose-gold gradient card for high-value enquiries.',
                'card' => $this->gradientCard($G_ROSE),
                'children' => [
                    $badge('VIP ENQUIRY', 12),
                    $hg('Work with me 1:1', 'h1'),
                    $p('A short application form, 24-hour reply, no waitlist for premium clients.'),
                    $btn('Apply now →'),
                ],
            ],

            // ============================================================
            // PRODUCT (8) — pricing, commerce, multi-column combos
            // ============================================================
            [
                'slug' => 'product-saas-trial', 'name' => 'SaaS · Free Trial', 'category' => 'product',
                'description' => 'Headline, three feature bullets and a trial CTA.',
                'children' => [
                    $h('Ship faster, with less', 'h2'),
                    $list(['No credit card required', 'Cancel anytime', 'Free for solo plans'], 12),
                    $btn('Start free trial'),
                ],
            ],
            [
                'slug' => 'product-image-left', 'name' => 'Product · Image Left', 'category' => 'product',
                'description' => 'Multi-column combo: image left (4), heading + copy + CTA right (8).',
                'card' => ['columns' => 12, 'padding' => 18],
                'children' => [
                    $img('', 4),
                    $this->child('heading', ['text' => '50 Notion templates', 'size' => 'h3', 'align' => 'left'], 8),
                    $this->child('paragraph', ['text' => 'Personal & commercial use. Lifetime updates included.', 'align' => 'left'], 8),
                    $btn('Buy on Gumroad — $19', 'https://gumroad.com', 8, 'fa-bag-shopping'),
                ],
            ],
            [
                'slug' => 'product-pricing-trio', 'name' => 'Pricing · Three Plans', 'category' => 'product',
                'description' => 'Three pricing tiers in a 4+4+4 row, each with a CTA.',
                'card' => ['columns' => 12, 'gap' => 12],
                'children' => [
                    $this->child('heading', ['text' => 'Solo · $9', 'size' => 'h4', 'align' => 'center'], 4),
                    $this->child('heading', ['text' => 'Team · $29', 'size' => 'h4', 'align' => 'center'], 4),
                    $this->child('heading', ['text' => 'Pro · $79', 'size' => 'h4', 'align' => 'center'], 4),
                    $btn('Pick Solo', 'https://example.com/solo', 4),
                    $btn('Pick Team', 'https://example.com/team', 4),
                    $btn('Pick Pro', 'https://example.com/pro', 4),
                ],
            ],
            [
                'slug' => 'product-bundle-deal', 'name' => 'Bundle Deal', 'category' => 'product',
                'description' => 'Two-product bundle with a single price and CTA.',
                'children' => [
                    $h('Bundle & save', 'h2'),
                    $p('Get the course + the workbook together for 30% less.'),
                    $btn('Buy the bundle', 'https://example.com', 12, 'fas fa-box'),
                ],
            ],
            [
                'slug' => 'product-coaching', 'name' => 'Coaching Package', 'category' => 'product',
                'description' => 'Outcome-led headline and call-booking CTA.',
                'children' => [
                    $h('1:1 Coaching', 'h2'),
                    $p('Six 60-min sessions to get unstuck and move on your big idea.'),
                    $list(['Weekly accountability', 'Voxer between sessions', 'Custom plan after week 1'], 12),
                    $btn('Book intro call', 'https://example.com', 12, 'far fa-calendar'),
                ],
            ],
            [
                'slug' => 'product-merch-row', 'name' => 'Merch · Three Up', 'category' => 'product',
                'description' => 'Three featured products in a row with a single shop-all CTA.',
                'card' => ['columns' => 12, 'gap' => 10],
                'children' => [
                    $img('', 4), $img('', 4), $img('', 4),
                    $btn('Shop all merch', 'https://example.com'),
                ],
            ],
            [
                'slug' => 'product-ocean-launch', 'name' => 'Premium · Ocean Launch', 'category' => 'product',
                'plan_tier' => 'pro',
                'description' => 'Ocean gradient launch announcement with two pill CTAs.',
                'card' => $this->gradientCard($G_OCEAN),
                'children' => [
                    $badge('🚀 NEW', 12),
                    $hg('We\'re live today.', 'h1'),
                    $p('Nine months of building — finally here.'),
                    $btn('Try it now', 'https://example.com', 6),
                    $btn('Read the post', 'https://example.com/blog', 6),
                ],
            ],
            [
                'slug' => 'product-lava-deal', 'name' => 'Premium · Lava Deal', 'category' => 'product',
                'plan_tier' => 'pro',
                'description' => 'Hot-pink lava gradient with a discount code callout and CTA.',
                'card' => $this->gradientCard($G_LAVA),
                'children' => [
                    $hg('30% off — this week only', 'h1'),
                    $p('Use code SUMMER30 at checkout. Ends Sunday.'),
                    $btn('Shop the sale →'),
                ],
            ],

            // ============================================================
            // EVENT (5) — meetups, livestreams, countdown
            // ============================================================
            [
                'slug' => 'event-rsvp-clean', 'name' => 'Event · Clean RSVP', 'category' => 'event',
                'description' => 'Headline, date/place line and a single RSVP button.',
                'children' => [
                    $h('Designers Coffee · NYC', 'h2'),
                    $p('Sat, Nov 22 · 10 AM · Greenpoint'),
                    $btn('RSVP', 'https://example.com', 12, 'far fa-calendar-check'),
                ],
            ],
            [
                'slug' => 'event-livestream', 'name' => 'Going Live Tonight', 'category' => 'event',
                'description' => 'One-time livestream with a "watch live" button.',
                'children' => [
                    $h('Going live tonight', 'h2'),
                    $p('Friday · 9 PM ET · Q&A and a live demo.'),
                    $btn('Watch live', 'https://youtube.com', 12, 'fab fa-youtube'),
                ],
            ],
            [
                'slug' => 'event-countdown-card', 'name' => 'Countdown Card', 'category' => 'event',
                'description' => 'Headline + live countdown timer + CTA.',
                'children' => [
                    $h('Doors open in', 'h3'),
                    $countdown('Doors open in'),
                    $btn('Save your spot'),
                ],
            ],
            [
                'slug' => 'event-mint-drop', 'name' => 'Premium · Mint Drop', 'category' => 'event',
                'plan_tier' => 'pro',
                'description' => 'Mint gradient teaser with a launch countdown and email capture.',
                'card' => $this->gradientCard($G_MINT, ['padding' => 32]),
                'children' => [
                    $badge('★ LAUNCH', 12),
                    $hg('Drop · 12.09', 'h1'),
                    $countdown('Drop in'),
                    $emailC('you@email.com'),
                ],
            ],
            [
                'slug' => 'event-recurring-demo', 'name' => 'Recurring Demo', 'category' => 'event',
                'description' => 'Weekly product-demo card with a single sign-up CTA.',
                'children' => [
                    $h('Weekly product demo', 'h2'),
                    $p('Every Thursday · 1 PM ET · 30 minutes.'),
                    $btn('Reserve a seat'),
                ],
            ],

            // ============================================================
            // GALLERY (6) — image grids, mood boards, recap
            // ============================================================
            [
                'slug' => 'gallery-product-grid', 'name' => 'Product Grid', 'category' => 'gallery',
                'description' => '2x3 product photo grid with a single shop CTA.',
                'card' => ['columns' => 12, 'gap' => 8],
                'children' => [
                    $img('', 6), $img('', 6),
                    $img('', 6), $img('', 6),
                    $img('', 6), $img('', 6),
                    $btn('Shop all'),
                ],
            ],
            [
                'slug' => 'gallery-event-recap', 'name' => 'Event Recap', 'category' => 'gallery',
                'description' => 'Three event photos in a row with a recap link.',
                'card' => ['columns' => 12, 'gap' => 8],
                'children' => [
                    $h('Recap — Maker Day', 'h2'),
                    $img('', 4), $img('', 4), $img('', 4),
                    $btn('Read the recap'),
                ],
            ],
            [
                'slug' => 'gallery-team', 'name' => 'Meet the Team', 'category' => 'gallery',
                'description' => 'Four headshots with a name caption block.',
                'card' => ['columns' => 12, 'gap' => 10],
                'children' => [
                    $h('Meet the team', 'h2'),
                    $img('', 6), $img('', 6),
                    $img('', 6), $img('', 6),
                ],
            ],
            [
                'slug' => 'gallery-moodboard', 'name' => 'Mood Board', 'category' => 'gallery',
                'description' => 'Loose six-image collage for visual inspiration.',
                'card' => ['columns' => 12, 'gap' => 6],
                'children' => [
                    $img('', 4), $img('', 4), $img('', 4),
                    $img('', 4), $img('', 4), $img('', 4),
                ],
            ],
            [
                'slug' => 'gallery-hero-image', 'name' => 'Hero Image · Wide', 'category' => 'gallery',
                'description' => 'Single hero image with a caption and CTA.',
                'children' => [
                    $img('', 12),
                    $p('From the latest campaign — shot in Brooklyn, summer.'),
                    $btn('See the full set'),
                ],
            ],
            [
                'slug' => 'gallery-champagne-portfolio', 'name' => 'Premium · Champagne Portfolio', 'category' => 'gallery',
                'plan_tier' => 'pro',
                'description' => 'Champagne gradient portfolio card with a 6+6 image pair.',
                'card' => $this->gradientCard($G_CHAMP, ['columns' => 12]),
                'children' => [
                    $badge('PORTFOLIO', 12),
                    $hg('Selected work', 'h1'),
                    $img('', 6), $img('', 6),
                    $btn('See full portfolio'),
                ],
            ],

            // ============================================================
            // GENERAL (10) — FAQ, info, list, link grid, premium variants
            // ============================================================
            [
                'slug' => 'general-faq', 'name' => 'FAQ · Accordion', 'category' => 'general',
                'description' => 'Five common questions in an accordion. Use for support pages.',
                'children' => [
                    $h('Frequently asked', 'h3'),
                    $this->child('faq_v2', ['items' => [
                        ['question' => 'How fast is delivery?', 'answer' => 'Most orders ship within 24 hours.'],
                        ['question' => 'What\'s your return policy?', 'answer' => '30-day no-questions-asked returns.'],
                        ['question' => 'Do you ship internationally?', 'answer' => 'Yes — we ship to 60+ countries.'],
                        ['question' => 'How do I track my order?', 'answer' => 'You\'ll get a tracking link by email.'],
                        ['question' => 'Need help?', 'answer' => 'Reply to your order email — we read every one.'],
                    ]], 12),
                ],
            ],
            [
                'slug' => 'general-link-list', 'name' => 'Big Link List', 'category' => 'general',
                'description' => 'A long, single-column stack of full-width buttons.',
                'children' => [
                    $btn('Latest blog post', 'https://example.com', 12, 'fas fa-pen-nib'),
                    $btn('Newsletter archive', 'https://example.com', 12, 'fas fa-envelope-open-text'),
                    $btn('Open-source projects', 'https://example.com', 12, 'fas fa-code-branch'),
                    $btn('Talks & interviews', 'https://example.com', 12, 'fas fa-microphone-lines'),
                ],
            ],
            [
                'slug' => 'general-link-grid', 'name' => 'Link Grid · 4-Up', 'category' => 'general',
                'description' => 'A four-up grid of icon-led link buttons.',
                'card' => ['columns' => 12, 'gap' => 10],
                'children' => [
                    $link('Blog', 'https://example.com', 6, 'fas fa-newspaper'),
                    $link('Shop', 'https://example.com', 6, 'fas fa-bag-shopping'),
                    $link('Podcast', 'https://example.com', 6, 'fas fa-microphone'),
                    $link('YouTube', 'https://youtube.com', 6, 'fab fa-youtube'),
                ],
            ],
            [
                'slug' => 'general-stats-row', 'name' => 'Stats Row', 'category' => 'general',
                'description' => 'Three big numbers — followers, projects, years.',
                'card' => ['columns' => 12],
                'children' => [
                    $hg('120k', 'h1', 'center', 4),
                    $hg('48', 'h1', 'center', 4),
                    $hg('7yrs', 'h1', 'center', 4),
                    $p('Followers · Projects · Years', 'center', 12),
                ],
            ],
            [
                'slug' => 'general-announcement', 'name' => 'Announcement', 'category' => 'general',
                'description' => 'An alert + CTA — for one-time, temporary news.',
                'children' => [
                    $alert('Heads up — site moving to a new domain on Dec 1.', 'warning'),
                    $btn('Read the details'),
                ],
            ],
            [
                'slug' => 'general-now-page', 'name' => 'Now Page', 'category' => 'general',
                'description' => 'A /now-style "currently working on" block.',
                'children' => [
                    $h('Right now', 'h2'),
                    $list([
                        '🛠 Shipping a small Notion plugin',
                        '📕 Re-reading "Range" by David Epstein',
                        '🏃 Slowly running a 10K',
                    ], 12),
                ],
            ],
            [
                'slug' => 'general-changelog', 'name' => 'Changelog', 'category' => 'general',
                'description' => 'Recent product updates — three latest entries + full link.',
                'children' => [
                    $h('What\'s new', 'h3'),
                    $list([
                        'v1.4 — Dark mode + share links',
                        'v1.3 — Notion sync + bug fixes',
                        'v1.2 — New onboarding flow',
                    ], 12),
                    $btn('Full changelog →'),
                ],
            ],
            [
                'slug' => 'general-info-split', 'name' => 'Info · Two-Column Split', 'category' => 'general',
                'description' => 'Multi-column combo: heading + checklist on the left (8), badge tile on the right (4).',
                'card' => ['columns' => 12, 'gap' => 14, 'padding' => 22],
                'children' => [
                    $this->child('heading', ['text' => 'Why us?', 'size' => 'h3', 'align' => 'left'], 8),
                    $badge('★ TRUSTED', 4),
                    $this->child('list', ['items' => [
                        '✓ Set up in under five minutes',
                        '✓ Cancel anytime, no contracts',
                        '✓ Used by 10,000+ creators',
                    ]], 8),
                    $img('', 4),
                ],
            ],
            [
                'slug' => 'general-sunset-thanks', 'name' => 'Premium · Sunset Thank You', 'category' => 'general',
                'plan_tier' => 'pro',
                'description' => 'Soft sunset gradient supporter thank-you with two tip-jar buttons.',
                'card' => $this->gradientCard($G_SUNSET),
                'children' => [
                    $hg('Thank you', 'h1'),
                    $p('Your support pays for hosting, tools and the occasional coffee.'),
                    $btn('Patreon', 'https://patreon.com', 6, 'fab fa-patreon'),
                    $btn('Ko-fi', 'https://ko-fi.com', 6, 'fas fa-mug-hot'),
                ],
            ],
            [
                'slug' => 'general-midnight-portfolio', 'name' => 'Premium · Midnight Portfolio', 'category' => 'general',
                'plan_tier' => 'pro',
                'description' => 'Deep-midnight gradient portfolio splash with stats and a pill CTA.',
                'card' => $this->gradientCard($G_MIDNIGHT, ['columns' => 12]),
                'children' => [
                    $badge('★ AVAILABLE FOR HIRE', 12),
                    $hm('Designer & builder', 'h1'),
                    $p('Working with founders on brand, product and motion since 2018.'),
                    $hg('48', 'h1', 'center', 4),
                    $hg('12', 'h1', 'center', 4),
                    $hg('7y', 'h1', 'center', 4),
                    $btn('See selected work →'),
                ],
            ],
            [
                'slug' => 'general-forest-supporters', 'name' => 'Premium · Forest Supporters', 'category' => 'general',
                'plan_tier' => 'pro',
                'description' => 'Forest gradient public thank-you wall with email capture.',
                'card' => $this->gradientCard($G_FOREST),
                'children' => [
                    $h('Thanks to our supporters', 'h2'),
                    $p('Your monthly support keeps this work going.'),
                    $emailC('Become a supporter'),
                ],
            ],
            [
                'slug' => 'general-coral-quote', 'name' => 'Premium · Coral Quote', 'category' => 'general',
                'plan_tier' => 'pro',
                'description' => 'Soft coral gradient pull-quote card with attribution.',
                'card' => $this->gradientCard($G_CORAL),
                'children' => [
                    $hm('"Best decision I made all year."', 'h2'),
                    $p('— Jordan M., creator of Studio Atlas'),
                    $btn('Read the case study →'),
                ],
            ],
        ];
    }
}
