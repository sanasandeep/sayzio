<?php

namespace Database\Seeders;

use App\Modules\Admin\Models\CardTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds 50+ card-container templates across hero / cta / social / contact /
 * product / event / gallery / general categories.
 *
 * Each template is a snapshot of:
 *   { type: 'card', settings: {...}, is_active: true, children: [{type, settings, is_active}] }
 *
 * Children settings are intentionally minimal — TemplateService re-runs every
 * payload through BiolinkBlockController::sanitizeSettings, which fills in
 * defaults. We only set the few fields that meaningfully shape the template.
 *
 * grid_span on each child is on a 12-col grid; pick spans that line up
 * (12, 6+6, 4+4+4, etc).
 *
 * Idempotent — uses updateOrCreate by slug so re-running the seeder refreshes
 * existing templates instead of duplicating.
 */
class CardTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $i => $tpl) {
            $slug = $tpl['slug'];
            CardTemplate::updateOrCreate(
                ['slug' => $slug],
                [
                    'name'        => $tpl['name'],
                    'category'    => $tpl['category'],
                    'description' => $tpl['description'] ?? null,
                    'plan_tier'   => $tpl['plan_tier'] ?? null,
                    'is_active'   => true,
                    'sort_order'  => $i,
                    'snapshot'    => [
                        'type'      => 'card',
                        'settings'  => array_merge($this->defaultCardSettings(), $tpl['card'] ?? []),
                        'is_active' => true,
                        'children'  => $tpl['children'],
                    ],
                ]
            );
        }
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

    private function span(int $n): array
    {
        return ['_style' => ['grid_span' => $n]];
    }

    /** Quick child builders. */
    private function child(string $type, array $settings, int $span = 12): array
    {
        $settings = array_merge($settings, $this->span($span));
        return ['type' => $type, 'settings' => $settings, 'is_active' => true];
    }

    private function templates(): array
    {
        $h = fn(string $text, string $size = 'h3', string $align = 'center', int $span = 12)
            => $this->child('heading', ['text' => $text, 'size' => $size, 'align' => $align], $span);
        $hg = fn(string $text, string $size = 'h2', string $align = 'center', int $span = 12)
            => $this->child('heading', ['text' => $text, 'size' => $size, 'align' => $align, 'style' => 'gradient'], $span);
        $hm = fn(string $text, string $size = 'h2', string $align = 'center', int $span = 12)
            => $this->child('heading', ['text' => $text, 'size' => $size, 'align' => $align, 'style' => 'animated'], $span);
        $p  = fn(string $text, string $align = 'center', int $span = 12)
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
        $list = fn(array $items, int $span = 12, string $type = 'list')
            => $this->child($type, ['items' => $items], $span);
        $socials = fn(int $span = 12)
            => $this->child('socials_multi', [], $span);
        $email = fn(string $heading = 'Subscribe', int $span = 12)
            => $this->child('email_subscribe', ['heading' => $heading, 'button_text' => 'Subscribe'], $span);
        $emailC = fn(string $placeholder = 'you@email.com', int $span = 12)
            => $this->child('email_collector', ['placeholder' => $placeholder, 'button_text' => 'Join'], $span);
        $form = fn(int $span = 12)
            => $this->child('contact_form', ['heading' => 'Get in touch'], $span);
        $wa = fn(int $span = 12)
            => $this->child('whatsapp_widget', ['phone' => '+10000000000', 'message' => 'Hi! I have a question.'], $span);

        return [
            // ==================== HERO (8) ====================
            [
                'slug' => 'hero-profile-classic', 'name' => 'Profile Intro — Classic', 'category' => 'hero',
                'description' => 'Avatar, name, bio and a tidy social bar. Great as a page opener.',
                'children' => [
                    $this->child('profile_card_v1', ['name' => 'Your Name', 'title' => 'What you do', 'bio' => 'A short, friendly bio about yourself.'], 12),
                    $socials(12),
                ],
            ],
            [
                'slug' => 'hero-founder-intro', 'name' => 'Founder Intro', 'category' => 'hero',
                'description' => 'Photo + headline + paragraph + primary CTA. Good for personal brands.',
                'children' => [
                    $img('', 12),
                    $h('Building things people love', 'h2'),
                    $p('Founder, designer, and lifelong tinkerer. Currently building the next big thing.'),
                    $btn('See my work →', 'https://example.com', 12),
                ],
            ],
            [
                'slug' => 'hero-influencer', 'name' => 'Creator Hero', 'category' => 'hero',
                'description' => 'Bold gradient headline with email capture and socials.',
                'children' => [
                    $hg('Hey, I\'m a Creator', 'h1'),
                    $p('I share weekly drops on design, code and culture.'),
                    $email('Get the weekly drop'),
                    $socials(12),
                ],
            ],
            [
                'slug' => 'hero-minimal-centered', 'name' => 'Minimal Centered Hero', 'category' => 'hero',
                'description' => 'Clean morphing headline with a single supporting line.',
                'card' => ['padding' => 28, 'gap' => 8],
                'children' => [
                    $hm('Less, but better.', 'h1'),
                    $p('A small, sharp landing page for makers.'),
                ],
            ],
            [
                'slug' => 'hero-team-member', 'name' => 'Team Member Card', 'category' => 'hero',
                'description' => 'Headshot, role, short bio and a way to reach them.',
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
            [
                'slug' => 'hero-coach', 'name' => 'Coach / Consultant', 'category' => 'hero',
                'description' => 'Photo, intro, two services and your socials.',
                'children' => [
                    $img('', 12),
                    $h('1:1 Coaching for builders', 'h2'),
                    $p('I help solo founders ship faster without burning out.'),
                    $link('Free intro call', 'https://example.com', 6, 'fa-phone'),
                    $link('Pricing', 'https://example.com', 6, 'fa-tag'),
                    $socials(12),
                ],
            ],
            [
                'slug' => 'hero-verified-creator', 'name' => 'Verified Creator', 'category' => 'hero',
                'description' => 'Profile + verified badge + socials. Signals legitimacy.',
                'children' => [
                    $this->child('profile_card_v1', ['name' => 'Jamie Park', 'title' => 'Photographer · Brooklyn', 'bio' => 'Brand photography for indie founders & creative studios.'], 12),
                    $badge('VERIFIED CREATOR', 12),
                    $socials(12),
                ],
            ],

            // ==================== CTA (8) ====================
            [
                'slug' => 'cta-newsletter', 'name' => 'Newsletter Signup', 'category' => 'cta',
                'description' => 'Headline, value prop and an email field. Highest-converting basic CTA.',
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
                    $btn('📅 Pick a time', 'https://example.com'),
                ],
            ],
            [
                'slug' => 'cta-download-app', 'name' => 'Download App', 'category' => 'cta',
                'description' => 'iOS + Android side-by-side store badges.',
                'children' => [
                    $h('Get the app', 'h2'),
                    $p('Available on iOS and Android — free to download.'),
                    $btn('App Store', 'https://apps.apple.com', 6, 'fa-apple'),
                    $btn('Google Play', 'https://play.google.com', 6, 'fa-google-play'),
                ],
            ],
            [
                'slug' => 'cta-free-trial', 'name' => 'Free Trial', 'category' => 'cta',
                'description' => 'Gradient hero CTA for SaaS — start free, no credit card.',
                'children' => [
                    $hg('Start your free trial', 'h2'),
                    $p('14 days, full access. No credit card required.'),
                    $btn('Start free →', 'https://example.com'),
                ],
            ],
            [
                'slug' => 'cta-waitlist', 'name' => 'Join the Waitlist', 'category' => 'cta',
                'description' => 'Coming-soon teaser with email capture.',
                'children' => [
                    $h('Coming soon', 'h2'),
                    $p('We\'re launching in a few weeks. Drop your email for early access.'),
                    $emailC('Get early access'),
                ],
            ],
            [
                'slug' => 'cta-urgency-promo', 'name' => 'Limited-Time Promo', 'category' => 'cta',
                'description' => 'Red alert + headline + redeem button. Use sparingly.',
                'children' => [
                    $alert('⏰ Ends Sunday at midnight', 'warning'),
                    $h('30% off everything', 'h2'),
                    $p('Use code BLACKFRIDAY at checkout.'),
                    $btn('Shop the sale', 'https://example.com'),
                ],
            ],
            [
                'slug' => 'cta-video-pitch', 'name' => 'Video Pitch + CTA', 'category' => 'cta',
                'description' => 'Header video, hook, and a single primary action.',
                'children' => [
                    $this->child('header_video', ['url' => ''], 12),
                    $h('See it in 60 seconds', 'h2'),
                    $btn('Try it free →', 'https://example.com'),
                ],
            ],
            [
                'slug' => 'cta-dual', 'name' => 'Dual CTA', 'category' => 'cta',
                'description' => 'Two equal-weight buttons — primary and secondary.',
                'children' => [
                    $h('Pick a path', 'h2'),
                    $p('Watch a quick tour or jump straight in.'),
                    $link('▶ Watch demo', 'https://example.com', 6),
                    $link('Sign up free', 'https://example.com', 6),
                ],
            ],

            // ==================== SOCIAL (6) ====================
            [
                'slug' => 'social-bar-only', 'name' => 'Social Bar', 'category' => 'social',
                'description' => 'Just your socials in a compact row. Drop it on any page.',
                'card' => ['padding' => 12],
                'children' => [
                    $socials(12),
                ],
            ],
            [
                'slug' => 'social-instagram-showcase', 'name' => 'Instagram Showcase', 'category' => 'social',
                'description' => 'Featured Instagram post with a header.',
                'children' => [
                    $h('Latest from Instagram', 'h3'),
                    $this->child('instagram_media', ['url' => ''], 12),
                ],
            ],
            [
                'slug' => 'social-tiktok-showcase', 'name' => 'TikTok Showcase', 'category' => 'social',
                'description' => 'Latest TikTok video with a heading.',
                'children' => [
                    $h('Latest TikTok', 'h3'),
                    $this->child('tiktok_video', ['url' => ''], 12),
                ],
            ],
            [
                'slug' => 'social-twitter-feed', 'name' => 'Twitter / X Feed', 'category' => 'social',
                'description' => 'Embed your X profile timeline.',
                'children' => [
                    $h('Latest on X', 'h3'),
                    $this->child('twitter_profile', ['url' => ''], 12),
                ],
            ],
            [
                'slug' => 'social-proof', 'name' => 'Social Proof Numbers', 'category' => 'social',
                'description' => 'Three big-number badges side-by-side.',
                'children' => [
                    $h('Trusted worldwide', 'h2'),
                    $badge('10,000+ users', 4),
                    $badge('4.9★ rating', 4),
                    $badge('Featured in TC', 4),
                ],
            ],
            [
                'slug' => 'social-testimonials', 'name' => 'Testimonials', 'category' => 'social',
                'description' => 'Three short customer quotes stacked.',
                'children' => [
                    $h('What people say', 'h2'),
                    $p('"This changed how I work. Faster, cleaner, simpler." — Mia K.'),
                    $div(),
                    $p('"Beautiful out of the box, customizable when I need it." — Dev R.'),
                    $div(),
                    $p('"Finally a tool that respects my time." — Sam L.'),
                ],
            ],

            // ==================== CONTACT (5) ====================
            [
                'slug' => 'contact-form-card', 'name' => 'Contact Form', 'category' => 'contact',
                'description' => 'Heading, blurb and a full contact form.',
                'children' => [
                    $h('Get in touch', 'h2'),
                    $p('Fill the form — we usually reply within a day.'),
                    $form(12),
                ],
            ],
            [
                'slug' => 'contact-whatsapp', 'name' => 'WhatsApp Chat', 'category' => 'contact',
                'description' => 'One-tap WhatsApp chat with a friendly intro.',
                'children' => [
                    $h('Chat on WhatsApp', 'h3'),
                    $p('Quick questions? Message us — we\'re online most days.'),
                    $wa(12),
                ],
            ],
            [
                'slug' => 'contact-quick-email', 'name' => 'Quick Email', 'category' => 'contact',
                'description' => 'One field, one button. Drop your email and we\'ll reach out.',
                'children' => [
                    $h('Drop your email', 'h3'),
                    $emailC('we\'ll reach out'),
                ],
            ],
            [
                'slug' => 'contact-multi-channel', 'name' => 'Multi-Channel Contact', 'category' => 'contact',
                'description' => 'Email · Phone · WhatsApp in a 3-up row.',
                'children' => [
                    $h('Reach us', 'h2'),
                    $link('Email', 'mailto:hi@example.com', 4, 'fa-envelope'),
                    $link('Call', 'tel:+10000000000', 4, 'fa-phone'),
                    $link('WhatsApp', 'https://wa.me/10000000000', 4, 'fa-whatsapp'),
                ],
            ],
            [
                'slug' => 'contact-business-hours', 'name' => 'Business Hours', 'category' => 'contact',
                'description' => 'Hours-of-operation list with an email fallback.',
                'children' => [
                    $h('Hours', 'h3'),
                    $list(['Mon–Fri  9:00 – 18:00', 'Sat  10:00 – 14:00', 'Sun  Closed'], 12),
                    $emailC('Email us anytime'),
                ],
            ],

            // ==================== PRODUCT (8) ====================
            [
                'slug' => 'product-hero', 'name' => 'Product Hero', 'category' => 'product',
                'description' => 'Big image, name, pitch and buy button.',
                'children' => [
                    $img('', 12),
                    $h('Product name', 'h2'),
                    $p('A one-line value proposition that makes someone want it.'),
                    $btn('Buy now — $29', 'https://example.com'),
                ],
            ],
            [
                'slug' => 'product-pricing-simple', 'name' => 'Simple Pricing', 'category' => 'product',
                'description' => 'One-tier pricing card with feature list and CTA.',
                'children' => [
                    $h('Pro — $9/mo', 'h2'),
                    $list(['Unlimited links', 'Custom domains', 'Advanced analytics', 'Priority support'], 12, 'list_pricing'),
                    $btn('Start free trial', 'https://example.com'),
                ],
            ],
            [
                'slug' => 'product-pricing-three-tier', 'name' => 'Three-Tier Pricing', 'category' => 'product',
                'description' => 'Free / Pro / Business comparison row.',
                'card' => ['gap' => 16],
                'children' => [
                    $h('Pricing', 'h2'),
                    $this->child('heading', ['text' => 'Free', 'size' => 'h4', 'align' => 'center'], 4),
                    $this->child('heading', ['text' => 'Pro', 'size' => 'h4', 'align' => 'center'], 4),
                    $this->child('heading', ['text' => 'Business', 'size' => 'h4', 'align' => 'center'], 4),
                    $list(['5 links', 'Basic analytics'], 4),
                    $list(['100 links', 'Advanced analytics', 'Custom domain'], 4),
                    $list(['Unlimited', 'Teams', 'SSO', 'Priority'], 4),
                    $link('Get Free', 'https://example.com', 4),
                    $link('Start Pro', 'https://example.com', 4),
                    $link('Contact us', 'https://example.com', 4),
                ],
            ],
            [
                'slug' => 'product-ebook', 'name' => 'Ebook Promo', 'category' => 'product',
                'description' => 'Cover image, hook, and a download CTA.',
                'children' => [
                    $img('', 12),
                    $h('The Indie Maker\'s Handbook', 'h2'),
                    $p('142 pages on shipping faster, charging more and not burning out.'),
                    $btn('Download — $19', 'https://example.com'),
                ],
            ],
            [
                'slug' => 'product-course', 'name' => 'Course Card', 'category' => 'product',
                'description' => 'Course thumb + curriculum bullets + enroll button.',
                'children' => [
                    $img('', 12),
                    $h('Design Systems 101', 'h2'),
                    $list(['8 modules · 4 hours', 'Lifetime access', 'Figma + code samples', 'Certificate of completion'], 12),
                    $btn('Enroll — $99', 'https://example.com'),
                ],
            ],
            [
                'slug' => 'product-app-promo', 'name' => 'App Promo', 'category' => 'product',
                'description' => 'App screenshot, pitch and dual store CTAs.',
                'children' => [
                    $img('', 12),
                    $h('The everyday companion', 'h2'),
                    $p('Track your day, stay focused, sleep better.'),
                    $btn('App Store', 'https://apps.apple.com', 6, 'fa-apple'),
                    $btn('Google Play', 'https://play.google.com', 6, 'fa-google-play'),
                ],
            ],
            [
                'slug' => 'product-comparison', 'name' => 'Plan Comparison', 'category' => 'product',
                'description' => 'Pricing list highlighting what each tier includes.',
                'children' => [
                    $h('Compare plans', 'h2'),
                    $list(['Free — basic features', 'Pro — power users', 'Business — teams & priority support'], 12, 'list_pricing'),
                ],
            ],
            [
                'slug' => 'product-service', 'name' => 'Service Offer', 'category' => 'product',
                'description' => 'Image, what\'s-included list and "book now" CTA.',
                'children' => [
                    $img('', 12),
                    $h('Brand Audit — $499', 'h2'),
                    $list(['90-min strategy call', 'Full visual + tone audit', 'Action-prioritized PDF report'], 12),
                    $btn('Book your slot', 'https://example.com'),
                ],
            ],

            // ==================== EVENT (5) ====================
            [
                'slug' => 'event-details', 'name' => 'Event Details', 'category' => 'event',
                'description' => 'Date, location, agenda bullets and RSVP button.',
                'children' => [
                    $h('Friday Night Mixer', 'h2'),
                    $p('📅 Fri, Sep 12 · 🕢 7:30 PM · 📍 Brooklyn, NY'),
                    $list(['Welcome drinks', 'Lightning talks', 'Open networking until late'], 12),
                    $btn('RSVP free', 'https://example.com'),
                ],
            ],
            [
                'slug' => 'event-webinar', 'name' => 'Webinar Registration', 'category' => 'event',
                'description' => 'Webinar title, summary and email signup.',
                'children' => [
                    $h('Live Webinar — Free', 'h2'),
                    $p('Wednesday at 11 AM PT · 45 minutes + Q&A'),
                    $email('Save my seat'),
                ],
            ],
            [
                'slug' => 'event-conference', 'name' => 'Conference Card', 'category' => 'event',
                'description' => 'Hero image + dates + tickets button.',
                'children' => [
                    $img('', 12),
                    $h('FoundersCon 2026', 'h2'),
                    $p('March 4–6 · San Francisco · 600 founders, 30 speakers'),
                    $btn('Get tickets', 'https://example.com'),
                ],
            ],
            [
                'slug' => 'event-workshop', 'name' => 'Workshop Card', 'category' => 'event',
                'description' => 'Hands-on workshop with what-you\'ll-learn list.',
                'children' => [
                    $img('', 12),
                    $h('Notion for Founders — Workshop', 'h3'),
                    $list(['Build your operating system', 'Templates you keep', 'Live Q&A at the end'], 12),
                    $link('Reserve a spot', 'https://example.com', 12),
                ],
            ],
            [
                'slug' => 'event-countdown', 'name' => 'Countdown Event', 'category' => 'event',
                'description' => 'Urgent alert + headline + RSVP. Drives clicks before launch.',
                'children' => [
                    $alert('🚀 Doors open in 48 hours', 'info'),
                    $h('Product Launch — Live', 'h2'),
                    $p('Be the first to see it. Limited livestream seats.'),
                    $btn('Reserve a seat', 'https://example.com'),
                ],
            ],

            // ==================== GALLERY (5) ====================
            [
                'slug' => 'gallery-portfolio-grid', 'name' => 'Portfolio Grid', 'category' => 'gallery',
                'description' => 'Responsive image grid topped with a heading.',
                'children' => [
                    $h('Selected work', 'h2'),
                    $this->child('image_grid', [], 12),
                ],
            ],
            [
                'slug' => 'gallery-image-carousel', 'name' => 'Image Carousel', 'category' => 'gallery',
                'description' => 'Swipeable slider for a series of images.',
                'children' => [
                    $h('Lookbook', 'h2'),
                    $this->child('image_slider', [], 12),
                ],
            ],
            [
                'slug' => 'gallery-video-trio', 'name' => 'Video Trio', 'category' => 'gallery',
                'description' => 'Three videos in a 3-column row.',
                'children' => [
                    $h('Watch', 'h2'),
                    $this->child('video', ['url' => ''], 4),
                    $this->child('video', ['url' => ''], 4),
                    $this->child('video', ['url' => ''], 4),
                ],
            ],
            [
                'slug' => 'gallery-before-after', 'name' => 'Before / After', 'category' => 'gallery',
                'description' => 'Two images side-by-side to show transformation.',
                'children' => [
                    $h('Before / After', 'h3'),
                    $img('', 6),
                    $img('', 6),
                    $p('A short note on what changed.'),
                ],
            ],
            [
                'slug' => 'gallery-inspiration', 'name' => 'Inspiration Board', 'category' => 'gallery',
                'description' => 'Polished slider with bigger captions.',
                'children' => [
                    $h('Inspiration', 'h2'),
                    $this->child('image_slider_v2', [], 12),
                ],
            ],

            // ==================== GENERAL (6) ====================
            [
                'slug' => 'general-blog-post-tile', 'name' => 'Blog Post Tile', 'category' => 'general',
                'description' => 'Cover image, title, excerpt and read-more link. Repeat for a feed.',
                'children' => [
                    $img('', 12),
                    $this->child('heading', ['text' => 'Post title goes here', 'size' => 'h3', 'align' => 'left'], 12),
                    $p('A short excerpt that previews what the post is about. Two lines is plenty.', 'left', 12),
                    $this->child('link', ['text' => 'Read more →', 'url' => 'https://example.com'], 12),
                ],
            ],
            [
                'slug' => 'general-faq', 'name' => 'FAQ Card', 'category' => 'general',
                'description' => 'Three Q&A pairs with subtle dividers.',
                'children' => [
                    $h('FAQ', 'h2'),
                    $this->child('heading', ['text' => 'Is there a free trial?', 'size' => 'h4', 'align' => 'left'], 12),
                    $p('Yes — 14 days, full access, no credit card.', 'left', 12),
                    $div(),
                    $this->child('heading', ['text' => 'Can I cancel anytime?', 'size' => 'h4', 'align' => 'left'], 12),
                    $p('Of course. One-click cancel from your dashboard.', 'left', 12),
                    $div(),
                    $this->child('heading', ['text' => 'Do you support teams?', 'size' => 'h4', 'align' => 'left'], 12),
                    $p('Yes — on the Business plan, with seats and roles.', 'left', 12),
                ],
            ],
            [
                'slug' => 'general-quote', 'name' => 'Quote Card', 'category' => 'general',
                'description' => 'Big gradient quote with an attribution line.',
                'card' => ['padding' => 28],
                'children' => [
                    $hg('"Make something people want."', 'h2'),
                    $p('— Paul Graham'),
                ],
            ],
            [
                'slug' => 'general-about', 'name' => 'About Card', 'category' => 'general',
                'description' => 'Photo + bio + socials. Compact "about me" block.',
                'children' => [
                    $img('', 4),
                    $this->child('heading', ['text' => 'About me', 'size' => 'h3', 'align' => 'left'], 8),
                    $p('Two-or-three sentences about who you are, what you make and why people should care.', 'left', 12),
                    $socials(12),
                ],
            ],
            [
                'slug' => 'general-timeline', 'name' => 'Timeline / Steps', 'category' => 'general',
                'description' => 'Numbered list — perfect for "how it works" or roadmap steps.',
                'children' => [
                    $h('How it works', 'h2'),
                    $list([
                        'Sign up — takes about 30 seconds.',
                        'Build your page from blocks and templates.',
                        'Share your link anywhere — track every click.',
                    ], 12, 'list_numbered'),
                ],
            ],
            [
                'slug' => 'general-spacer', 'name' => 'Section Break', 'category' => 'general',
                'description' => 'A clean divider with a tiny label between two sections.',
                'card' => ['padding' => 12, 'gap' => 8],
                'children' => [
                    $div(12),
                    $p('— · —', 'center', 12),
                    $div(12),
                ],
            ],
        ];
    }
}
