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
     *
     * v3 (2026-06): Library expansion — ~24 new designs covering
     * previously under-represented block categories: real image
     * grids/sliders, social-media embeds (Instagram/TikTok/X/Facebook),
     * audio & music (Spotify/Apple Music/SoundCloud/audio playlist/
     * podcast), documents (PDF/deck press kit), advanced UI (tabs,
     * accordion, news ticker, stats band), reviews wall + testimonial
     * carousel, conversion blocks (flash offer, coupon, tip jar) and a
     * categorised menu board.
     */
    public const SEED_VERSION = 3;

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

        // ─── Rich-media child builders (image grids/sliders, embeds, audio,
        //     documents, advanced UI). Content is real and on-topic so the
        //     children render fully on the public page — never blank. ───
        $photo = fn(string $keywords, int $w = 600, int $h = 600, string $seed = '')
            => 'https://loremflickr.com/' . $w . '/' . $h . '/' . rawurlencode($keywords)
                . '?lock=' . ((crc32($seed !== '' ? $seed : $keywords) % 100000) + 1);
        $imgGrid = fn(array $urls, int $columns = 3, int $span = 12)
            => $this->child('image_grid', [
                'images'  => array_map(static fn($u) => ['url' => $u, 'alt' => ''], $urls),
                'columns' => $columns,
                'gap'     => 8,
            ], $span);
        $slider = fn(array $urls, int $span = 12)
            => $this->child('image_slider', [
                'images'   => array_map(static fn($u) => ['url' => $u, 'alt' => ''], $urls),
                'interval' => 3500,
                'effect'   => 'fade',
            ], $span);
        $ig = fn(string $url = 'https://www.instagram.com/p/CkQ7-gDgF8B/', int $span = 12)
            => $this->child('instagram_media', ['url' => $url], $span);
        $tt = fn(string $url = 'https://www.tiktok.com/@scout2015/video/6718335390845095173', int $span = 12)
            => $this->child('tiktok_video', ['url' => $url], $span);
        $tw = fn(string $url = 'https://twitter.com/Twitter/status/1445078208190291973', int $span = 12)
            => $this->child('twitter_tweet', ['url' => $url], $span);
        $fb = fn(string $url = 'https://www.facebook.com/20531316728/posts/10154009990506729/', int $span = 12)
            => $this->child('facebook_post', ['url' => $url], $span);
        $spotify = fn(string $url = 'https://open.spotify.com/track/4cOdK2wGLETKBW3PvgPWqT', string $type = 'track', int $span = 12)
            => $this->child('spotify', ['url' => $url, 'type' => $type], $span);
        $appleMusic = fn(string $url = 'https://music.apple.com/us/album/abbey-road-remastered/1441164426', int $span = 12)
            => $this->child('apple_music', ['url' => $url, 'type' => 'album'], $span);
        $soundcloud = fn(string $url = 'https://soundcloud.com/forss/flickermood', int $span = 12)
            => $this->child('soundcloud', ['url' => $url], $span);
        $audio = fn(string $title = 'Latest episode', string $url = 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3', int $span = 12)
            => $this->child('audio', ['title' => $title, 'url' => $url], $span);
        $audioList = fn(array $tracks, string $title = 'Playlist', int $span = 12)
            => $this->child('audio_list', ['title' => $title, 'layout' => 'compact', 'tracks' => $tracks], $span);
        $pdf = fn(string $title = 'Download the guide', string $url = 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf', int $span = 12)
            => $this->child('pdf_document', ['title' => $title, 'url' => $url], $span);
        $ppt = fn(string $title = 'View the deck', string $url = 'https://example.com/deck.pptx', int $span = 12)
            => $this->child('powerpoint', ['title' => $title, 'url' => $url], $span);
        $tabs = fn(array $tabs, int $span = 12)
            => $this->child('tabs', ['layout' => 'tabs', 'tabs' => $tabs], $span);
        $accordion = fn(array $items, int $span = 12)
            => $this->child('accordion', ['layout' => 'plain', 'items' => $items], $span);
        $ticker = fn(array $items, int $span = 12)
            => $this->child('ticker', ['items' => $items, 'speed' => 'normal'], $span);
        $stats = fn(array $items, string $title = 'By the numbers', int $span = 12)
            => $this->child('stats', ['title' => $title, 'layout' => 'row', 'items' => $items], $span);
        $testiCarousel = fn(array $items, int $span = 12)
            => $this->child('testimonial_carousel', ['layout' => 'carousel', 'items' => $items], $span);
        $reviewsWall = fn(string $heading = 'What people are saying', int $span = 12)
            => $this->child('reviews_wall', ['heading' => $heading, 'source' => 'native', 'layout' => 'grid', 'limit' => 6], $span);
        $review = fn(string $name, int $rating, string $text, int $span = 12)
            => $this->child('review', ['name' => $name, 'rating' => $rating, 'text' => $text], $span);
        $menu = fn(array $sections, string $title = 'Today\'s Menu', int $span = 12)
            => $this->child('menu', ['title' => $title, 'layout' => 'classic', 'sections' => $sections], $span);
        $oneTime = fn(string $title, string $desc, string $price, string $orig, string $url = 'https://example.com', int $span = 12)
            => $this->child('one_time_offer', ['title' => $title, 'description' => $desc, 'price' => $price, 'original_price' => $orig, 'url' => $url], $span);
        $coupon = fn(string $code, string $desc, string $expires = '', int $span = 12)
            => $this->child('coupon', ['code' => $code, 'description' => $desc, 'expires' => $expires], $span);
        $donation = fn(string $title, string $desc, array $amounts = [5, 10, 25], int $span = 12)
            => $this->child('donation', ['title' => $title, 'description' => $desc, 'amounts' => $amounts, 'url' => 'https://example.com'], $span);

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

            // ============================================================
            // GALLERY — rich image grids & sliders (true media blocks)
            // ============================================================
            [
                'slug' => 'gallery-photo-grid', 'name' => 'Photo Grid · 3-Up', 'category' => 'gallery',
                'description' => 'A real three-column image grid with a heading and a view-all CTA.',
                'children' => [
                    $h('Latest shots', 'h2'),
                    $imgGrid([
                        $photo('photography,portrait', 600, 600, 'pg1'),
                        $photo('photography,street', 600, 600, 'pg2'),
                        $photo('photography,landscape', 600, 600, 'pg3'),
                        $photo('photography,architecture', 600, 600, 'pg4'),
                        $photo('photography,nature', 600, 600, 'pg5'),
                        $photo('photography,travel', 600, 600, 'pg6'),
                    ], 3, 12),
                    $btn('View the full gallery →'),
                ],
            ],
            [
                'slug' => 'gallery-slider-showcase', 'name' => 'Slider · Showcase', 'category' => 'gallery',
                'description' => 'An auto-playing image slider for a hero carousel or lookbook.',
                'children' => [
                    $h('Featured work', 'h2'),
                    $slider([
                        $photo('design,studio', 1200, 700, 'ss1'),
                        $photo('interior,minimal', 1200, 700, 'ss2'),
                        $photo('product,branding', 1200, 700, 'ss3'),
                    ], 12),
                    $p('Swipe through a few recent highlights.'),
                ],
            ],
            [
                'slug' => 'gallery-lookbook-aurora', 'name' => 'Premium · Aurora Lookbook', 'category' => 'gallery',
                'plan_tier' => 'pro',
                'description' => 'Aurora gradient lookbook with a two-column image grid and a shop CTA.',
                'card' => $this->gradientCard($G_AURORA, ['columns' => 12]),
                'children' => [
                    $badge('LOOKBOOK', 12),
                    $hg('Spring collection', 'h1'),
                    $imgGrid([
                        $photo('fashion,editorial', 700, 900, 'lb1'),
                        $photo('fashion,style', 700, 900, 'lb2'),
                        $photo('fashion,model', 700, 900, 'lb3'),
                        $photo('fashion,accessory', 700, 900, 'lb4'),
                    ], 2, 12),
                    $btn('Shop the collection →'),
                ],
            ],

            // ============================================================
            // SOCIAL — embeds, reviews wall & testimonial carousel
            // ============================================================
            [
                'slug' => 'social-instagram-feature', 'name' => 'Instagram · Featured Post', 'category' => 'social',
                'description' => 'Embed a real Instagram post with a heading and a follow link.',
                'children' => [
                    $h('From the \'gram', 'h3'),
                    $ig(),
                    $link('Follow on Instagram', 'https://instagram.com/yourhandle', 12, 'fab fa-instagram'),
                ],
            ],
            [
                'slug' => 'social-tiktok-feature', 'name' => 'TikTok · Featured Video', 'category' => 'social',
                'description' => 'Embed a TikTok video with a heading and a follow link.',
                'children' => [
                    $h('Latest on TikTok', 'h3'),
                    $tt(),
                    $link('Follow on TikTok', 'https://tiktok.com/@yourhandle', 12, 'fab fa-tiktok'),
                ],
            ],
            [
                'slug' => 'social-x-highlight', 'name' => 'X · Pinned Post', 'category' => 'social',
                'description' => 'Embed a featured post from X (Twitter) with a heading.',
                'children' => [
                    $h('Pinned post', 'h3'),
                    $tw(),
                ],
            ],
            [
                'slug' => 'social-facebook-feature', 'name' => 'Facebook · Featured Post', 'category' => 'social',
                'description' => 'Embed a Facebook post with a heading and a follow link.',
                'children' => [
                    $h('From our page', 'h3'),
                    $fb(),
                    $link('Follow on Facebook', 'https://facebook.com', 12, 'fab fa-facebook'),
                ],
            ],
            [
                'slug' => 'social-reviews-wall', 'name' => 'Reviews Wall', 'category' => 'social',
                'description' => 'A live wall of customer reviews with a heading.',
                'children' => [
                    $h('Loved by customers', 'h2'),
                    $reviewsWall('What people are saying'),
                ],
            ],
            [
                'slug' => 'social-testimonial-carousel', 'name' => 'Testimonial · Carousel', 'category' => 'social',
                'description' => 'A swipeable carousel of quoted testimonials with avatars.',
                'children' => [
                    $h('Kind words', 'h2'),
                    $testiCarousel([
                        ['quote' => 'Genuinely the best service I\'ve used this year.', 'name' => 'Alex Carter', 'title' => 'Founder, Bright Studio'],
                        ['quote' => 'The whole team was a delight to work with.', 'name' => 'Sam Lopez', 'title' => 'Head of Marketing, Northwind'],
                        ['quote' => 'Sharp, fast, and on time — every single time.', 'name' => 'Priya Shah', 'title' => 'Product Lead, Lumen'],
                    ], 12),
                ],
            ],
            [
                'slug' => 'social-review-spotlight', 'name' => 'Review · Spotlight', 'category' => 'social',
                'description' => 'A single five-star review with the reviewer\'s name.',
                'children' => [
                    $h('Review of the week', 'h3'),
                    $review('Casey N.', 5, 'Honestly the most useful tool I\'ve added this year. Pays for itself in week one.', 12),
                ],
            ],

            // ============================================================
            // GENERAL — audio/music, documents & advanced UI blocks
            // ============================================================
            [
                'slug' => 'general-spotify-feature', 'name' => 'Spotify · Featured Track', 'category' => 'general',
                'description' => 'Embed a Spotify track or playlist with a heading and a follow link.',
                'children' => [
                    $h('Now playing', 'h3'),
                    $spotify(),
                    $link('Follow on Spotify', 'https://open.spotify.com', 12, 'fab fa-spotify'),
                ],
            ],
            [
                'slug' => 'general-streaming-links', 'name' => 'Listen Everywhere', 'category' => 'general',
                'description' => 'Apple Music + SoundCloud embeds stacked for a release.',
                'children' => [
                    $h('Out now everywhere', 'h2'),
                    $appleMusic(),
                    $soundcloud(),
                ],
            ],
            [
                'slug' => 'general-audio-playlist', 'name' => 'Audio · Playlist', 'category' => 'general',
                'description' => 'A compact multi-track audio playlist with cover art.',
                'children' => [
                    $h('My playlist', 'h3'),
                    $audioList([
                        ['title' => 'Sunrise', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3', 'cover' => $photo('music,album', 300, 300, 'al1'), 'duration' => '6:00'],
                        ['title' => 'Midday', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-2.mp3', 'cover' => $photo('music,vinyl', 300, 300, 'al2'), 'duration' => '5:12'],
                        ['title' => 'Nightfall', 'artist' => 'SoundHelix', 'url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-3.mp3', 'cover' => $photo('music,studio', 300, 300, 'al3'), 'duration' => '7:04'],
                    ], 'Featured playlist', 12),
                ],
            ],
            [
                'slug' => 'general-podcast-episode', 'name' => 'Podcast · Latest Episode', 'category' => 'general',
                'description' => 'A single-track audio player with a heading and a subscribe link.',
                'children' => [
                    $h('Latest episode', 'h3'),
                    $p('Episode 42 — building in public, one link at a time.'),
                    $audio('Episode 42 — Building in public'),
                    $btn('Subscribe to the show', 'https://example.com', 12, 'fas fa-podcast'),
                ],
            ],
            [
                'slug' => 'general-document-download', 'name' => 'Document · Download', 'category' => 'general',
                'description' => 'A PDF document viewer with a heading and a download CTA.',
                'children' => [
                    $h('Free guide', 'h3'),
                    $p('A 12-page PDF with the exact playbook I used.'),
                    $pdf('Ship your first product (PDF)'),
                    $btn('Download the guide', 'https://example.com', 12, 'fas fa-download'),
                ],
            ],
            [
                'slug' => 'general-press-kit', 'name' => 'Press Kit', 'category' => 'general',
                'description' => 'A document + deck pair for press and partners.',
                'children' => [
                    $h('Press kit', 'h2'),
                    $p('Everything you need to write about us.'),
                    $pdf('One-pager (PDF)'),
                    $ppt('Brand deck (slides)'),
                ],
            ],
            [
                'slug' => 'general-tabs-info', 'name' => 'Tabs · Info', 'category' => 'general',
                'description' => 'A tabbed block to organise About / Services / Contact.',
                'children' => [
                    $h('All the details', 'h3'),
                    $tabs([
                        ['label' => 'About', 'text' => 'A short intro about you or your project, what you do and who it\'s for.'],
                        ['label' => 'Services', 'text' => 'Brand strategy, product design and motion — done end to end.'],
                        ['label' => 'Contact', 'text' => 'Email hi@example.com or book a call. I reply within a day.'],
                    ], 12),
                ],
            ],
            [
                'slug' => 'general-accordion-faq', 'name' => 'Accordion · Q&A', 'category' => 'general',
                'description' => 'A collapsible accordion for questions and details.',
                'children' => [
                    $h('Good to know', 'h3'),
                    $accordion([
                        ['title' => 'How does it work?', 'body' => 'Pick a template, swap in your content, and publish — it\'s live instantly.'],
                        ['title' => 'What\'s included?', 'body' => 'Unlimited blocks, analytics, and every card template in the gallery.'],
                        ['title' => 'Can I cancel anytime?', 'body' => 'Yes — no contracts, cancel in one click from your dashboard.'],
                    ], 12),
                ],
            ],
            [
                'slug' => 'general-news-ticker', 'name' => 'News Ticker', 'category' => 'general',
                'description' => 'A scrolling ticker of headlines or announcements.',
                'children' => [
                    $ticker([
                        '🚀 New product launching this Friday',
                        '🎉 We just crossed 10,000 users',
                        '📰 Featured in the weekly roundup',
                    ], 12),
                    $h('Latest updates', 'h3'),
                    $p('Catch up on everything new this week.'),
                ],
            ],
            [
                'slug' => 'general-stats-band', 'name' => 'Stats Band', 'category' => 'general',
                'description' => 'A row of headline metrics with captions.',
                'children' => [
                    $stats([
                        ['value' => '10k', 'label' => 'Followers', 'caption' => 'across socials'],
                        ['value' => '4.9', 'label' => 'Rating', 'caption' => 'from 230 reviews'],
                        ['value' => '120', 'label' => 'Projects', 'caption' => 'shipped to date'],
                    ], 'By the numbers', 12),
                ],
            ],

            // ============================================================
            // CTA — conversion blocks (offers, coupons, tips)
            // ============================================================
            [
                'slug' => 'cta-flash-offer', 'name' => 'Flash Offer', 'category' => 'cta',
                'description' => 'A limited-time offer block with original vs. sale price.',
                'children' => [
                    $h('Today only', 'h3'),
                    $oneTime('The complete bundle', 'Everything you need to launch — courses, templates and support.', '$49', '$129', 'https://example.com', 12),
                ],
            ],
            [
                'slug' => 'cta-coupon-drop', 'name' => 'Coupon Drop', 'category' => 'cta',
                'description' => 'A copyable coupon code with a shop CTA.',
                'children' => [
                    $h('Here\'s 20% off', 'h3'),
                    $coupon('WELCOME20', 'Get 20% off your first order — tap to copy.', '', 12),
                    $btn('Shop now →'),
                ],
            ],
            [
                'slug' => 'cta-tip-jar', 'name' => 'Tip Jar', 'category' => 'cta',
                'description' => 'A donation block with preset amounts to support your work.',
                'children' => [
                    $h('Support my work', 'h3'),
                    $donation('Buy me a coffee', 'Every contribution helps me keep creating, ad-free.', [5, 10, 25, 50], 12),
                ],
            ],

            // ============================================================
            // PRODUCT — menu showcase
            // ============================================================
            [
                'slug' => 'product-menu-board', 'name' => 'Menu Board', 'category' => 'product',
                'description' => 'A categorised menu with prices and item descriptions.',
                'children' => [
                    $h('Today\'s menu', 'h2'),
                    $menu([
                        ['name' => 'Starters', 'items' => [
                            ['name' => 'House focaccia', 'price' => '$6', 'description' => 'With rosemary and flaky salt.', 'thumbnail' => $photo('food,bread', 300, 300, 'm1')],
                            ['name' => 'Caesar salad', 'price' => '$11', 'description' => 'Romaine, anchovy dressing, parmesan.', 'thumbnail' => $photo('food,salad', 300, 300, 'm2')],
                        ]],
                        ['name' => 'Mains', 'items' => [
                            ['name' => 'Margherita pizza', 'price' => '$14', 'description' => 'San Marzano tomato, fior di latte, basil.', 'thumbnail' => $photo('food,pizza', 300, 300, 'm3')],
                            ['name' => 'Tagliatelle ragù', 'price' => '$17', 'description' => 'Slow-cooked beef, hand-cut pasta.', 'thumbnail' => $photo('food,pasta', 300, 300, 'm4')],
                        ]],
                    ], 'Today\'s menu', 12),
                ],
            ],
        ];
    }
}
