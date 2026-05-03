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

            // ==================== HERO (extra 8 → 16 total) ====================
            [
                'slug' => 'hero-podcaster', 'name' => 'Podcaster Hero', 'category' => 'hero',
                'description' => 'Cover art, show name, tagline and listen-on links.',
                'children' => [
                    $img('', 12),
                    $h('The Friday Show', 'h1'),
                    $p('Conversations on craft, code and creativity. New episode every Friday.'),
                    $btn('Listen on Spotify', 'https://open.spotify.com', 6, 'fab fa-spotify'),
                    $btn('Listen on Apple', 'https://podcasts.apple.com', 6, 'fab fa-apple'),
                ],
            ],
            [
                'slug' => 'hero-musician', 'name' => 'Musician Hero', 'category' => 'hero',
                'description' => 'Album cover, artist name and streaming buttons.',
                'children' => [
                    $img('', 12),
                    $hg('New Single — "Out Tonight"', 'h2'),
                    $p('Available everywhere you stream music.'),
                    $btn('Spotify', 'https://open.spotify.com', 4, 'fab fa-spotify'),
                    $btn('Apple Music', 'https://music.apple.com', 4, 'fab fa-apple'),
                    $btn('YouTube', 'https://youtube.com', 4, 'fab fa-youtube'),
                ],
            ],
            [
                'slug' => 'hero-photographer', 'name' => 'Photographer Hero', 'category' => 'hero',
                'description' => 'Hero image, name, location and portfolio CTA.',
                'children' => [
                    $img('', 12),
                    $h('Alex Chen — Photographer', 'h2'),
                    $p('Brooklyn, NY · weddings · portraits · editorial', 'center', 12),
                    $btn('See the portfolio', 'https://example.com', 12),
                ],
            ],
            [
                'slug' => 'hero-author', 'name' => 'Author Hero', 'category' => 'hero',
                'description' => 'Book cover, author name, blurb and pre-order button.',
                'children' => [
                    $img('', 6),
                    $h('My new book', 'h2', 'left', 6),
                    $p('A 240-page guide on building joyful side projects.', 'left', 12),
                    $btn('Pre-order now', 'https://example.com', 12),
                ],
            ],
            [
                'slug' => 'hero-developer', 'name' => 'Developer Hero', 'category' => 'hero',
                'description' => 'Avatar, role, tech-stack badges and GitHub CTA.',
                'children' => [
                    $this->child('profile_card_v1', ['name' => 'Dev Name', 'title' => 'Full-stack engineer', 'bio' => 'I build small, fast tools.'], 12),
                    $badge('TypeScript', 3), $badge('Rust', 3), $badge('Postgres', 3), $badge('Linux', 3),
                    $btn('View GitHub', 'https://github.com', 12, 'fab fa-github'),
                ],
            ],
            [
                'slug' => 'hero-restaurant', 'name' => 'Restaurant Hero', 'category' => 'hero',
                'description' => 'Cover photo, restaurant name, cuisine and reservation button.',
                'children' => [
                    $img('', 12),
                    $hg('La Petite Cuisine', 'h1'),
                    $p('Modern French · Williamsburg · est. 2018', 'center', 12),
                    $btn('Reserve a table', 'https://example.com', 12),
                ],
            ],
            [
                'slug' => 'hero-real-estate', 'name' => 'Real Estate Agent', 'category' => 'hero',
                'description' => 'Headshot, agent name, agency and contact.',
                'children' => [
                    $img('', 4),
                    $this->child('heading', ['text' => 'Maria Park', 'size' => 'h2', 'align' => 'left'], 8),
                    $p('Licensed agent · Bay Area · 12+ years', 'left', 12),
                    $btn('Book a viewing', 'https://example.com', 6),
                    $btn('Call now', 'tel:+10000000000', 6, 'fas fa-phone'),
                ],
            ],
            [
                'slug' => 'hero-fitness-coach', 'name' => 'Fitness Coach Hero', 'category' => 'hero',
                'description' => 'Action photo, coach name, specialties and program CTA.',
                'children' => [
                    $img('', 12),
                    $h('Train with Sam', 'h1'),
                    $p('Strength · Conditioning · Nutrition'),
                    $btn('Start the program', 'https://example.com', 12),
                ],
            ],

            // ==================== CTA (extra 8 → 16 total) ====================
            [
                'slug' => 'cta-discord-invite', 'name' => 'Join the Discord', 'category' => 'cta',
                'description' => 'Invite people into your community Discord with a single button.',
                'children' => [
                    $h('Hang out with us', 'h2'),
                    $p('A friendly community of makers, designers and builders.'),
                    $btn('Join Discord', 'https://discord.gg', 12, 'fab fa-discord'),
                ],
            ],
            [
                'slug' => 'cta-telegram-channel', 'name' => 'Subscribe on Telegram', 'category' => 'cta',
                'description' => 'Push followers into your Telegram channel for daily updates.',
                'children' => [
                    $h('Get daily updates', 'h2'),
                    $btn('Open Telegram', 'https://t.me', 12, 'fab fa-telegram'),
                ],
            ],
            [
                'slug' => 'cta-tip-jar', 'name' => 'Tip Jar', 'category' => 'cta',
                'description' => 'A friendly support button — perfect for creators.',
                'children' => [
                    $h('Enjoying the work?', 'h2'),
                    $p('Tips help me keep building. Even $1 means a lot.'),
                    $btn('Leave a tip', 'https://example.com', 12, 'fas fa-mug-hot'),
                ],
            ],
            [
                'slug' => 'cta-share-page', 'name' => 'Share This Page', 'category' => 'cta',
                'description' => 'Quick row of share buttons for the most common channels.',
                'children' => [
                    $h('Liked this? Share it', 'h3'),
                    $btn('Twitter', 'https://twitter.com/intent/tweet', 4, 'fab fa-x-twitter'),
                    $btn('LinkedIn', 'https://linkedin.com/share', 4, 'fab fa-linkedin-in'),
                    $btn('WhatsApp', 'https://wa.me', 4, 'fab fa-whatsapp'),
                ],
            ],
            [
                'slug' => 'cta-vote-poll', 'name' => 'Vote in the Poll', 'category' => 'cta',
                'description' => 'Drop in a quick poll to engage your audience.',
                'children' => [
                    $h('Quick question', 'h2'),
                    $this->child('poll', ['question' => 'Which post should I write next?', 'options' => ['How I use AI daily','My homelab setup','Side project income']], 12),
                ],
            ],
            [
                'slug' => 'cta-buy-merch', 'name' => 'Buy the Merch', 'category' => 'cta',
                'description' => 'Photo + price + buy button for a single product drop.',
                'children' => [
                    $img('', 12),
                    $h('Limited edition tee — $29', 'h3'),
                    $btn('Buy now', 'https://example.com', 12, 'fas fa-bag-shopping'),
                ],
            ],
            [
                'slug' => 'cta-resume-download', 'name' => 'Download Resume', 'category' => 'cta',
                'description' => 'A single, clear call-to-action for recruiters.',
                'children' => [
                    $h('Hiring?', 'h3'),
                    $p('Grab my up-to-date resume below.'),
                    $btn('Download PDF', 'https://example.com/resume.pdf', 12, 'fas fa-file-arrow-down'),
                ],
            ],
            [
                'slug' => 'cta-survey', 'name' => 'Take the Survey', 'category' => 'cta',
                'description' => 'Push people to a single survey link with context.',
                'children' => [
                    $h('Your turn — 60 seconds', 'h2'),
                    $p('Help me figure out what to build next.'),
                    $btn('Take the survey', 'https://example.com', 12, 'fas fa-clipboard-list'),
                ],
            ],

            // ==================== SOCIAL (extra 6 → 12 total) ====================
            [
                'slug' => 'social-youtube-latest', 'name' => 'YouTube Latest Video', 'category' => 'social',
                'description' => 'Embed your most recent video plus a subscribe CTA.',
                'children' => [
                    $this->child('video_embed', ['url' => 'https://youtube.com'], 12),
                    $btn('Subscribe on YouTube', 'https://youtube.com', 12, 'fab fa-youtube'),
                ],
            ],
            [
                'slug' => 'social-twitch-live', 'name' => 'Twitch Live Card', 'category' => 'social',
                'description' => 'Streaming schedule and a follow-on-Twitch button.',
                'children' => [
                    $h('Catch me live', 'h2'),
                    $p('Tue / Thu / Sat · 8 PM ET'),
                    $btn('Follow on Twitch', 'https://twitch.tv', 12, 'fab fa-twitch'),
                ],
            ],
            [
                'slug' => 'social-pinterest-board', 'name' => 'Pinterest Board', 'category' => 'social',
                'description' => 'Showcase your board with three pin previews and a follow CTA.',
                'children' => [
                    $img('', 4), $img('', 4), $img('', 4),
                    $btn('Follow on Pinterest', 'https://pinterest.com', 12, 'fab fa-pinterest'),
                ],
            ],
            [
                'slug' => 'social-substack', 'name' => 'Substack Newsletter', 'category' => 'social',
                'description' => 'Featured newsletter cover with a subscribe button.',
                'children' => [
                    $h('Subscribe to the newsletter', 'h2'),
                    $p('Weekly essays on building software with intent.'),
                    $email('Subscribe'),
                ],
            ],
            [
                'slug' => 'social-mastodon', 'name' => 'Mastodon Profile', 'category' => 'social',
                'description' => 'For folks on the fediverse — direct profile link.',
                'children' => [
                    $h('Find me on Mastodon', 'h3'),
                    $btn('Open Mastodon', 'https://mastodon.social', 12, 'fab fa-mastodon'),
                ],
            ],
            [
                'slug' => 'social-bluesky', 'name' => 'Bluesky Profile', 'category' => 'social',
                'description' => 'Quick link out to your Bluesky handle.',
                'children' => [
                    $h('I\'m also on Bluesky', 'h3'),
                    $btn('Open Bluesky', 'https://bsky.app', 12, 'fas fa-cloud'),
                ],
            ],

            // ==================== CONTACT (extra 5 → 10 total) ====================
            [
                'slug' => 'contact-calendly', 'name' => 'Calendly Booking', 'category' => 'contact',
                'description' => 'A direct calendar booking button for sales/intro calls.',
                'children' => [
                    $h('Book a 30-min call', 'h2'),
                    $p('Pick a slot that works — calendars open 4 weeks out.'),
                    $btn('Open calendar', 'https://calendly.com', 12, 'far fa-calendar'),
                ],
            ],
            [
                'slug' => 'contact-location-map', 'name' => 'Location & Map', 'category' => 'contact',
                'description' => 'Address card with a directions button.',
                'children' => [
                    $h('Find us', 'h3'),
                    $p('123 Example St, Brooklyn NY 11211'),
                    $btn('Get directions', 'https://maps.google.com', 12, 'fas fa-map-pin'),
                ],
            ],
            [
                'slug' => 'contact-press-kit', 'name' => 'Press / Media Kit', 'category' => 'contact',
                'description' => 'A landing block for press contacts and asset downloads.',
                'children' => [
                    $h('Press inquiries', 'h3'),
                    $p('For interviews and brand assets.'),
                    $btn('Download press kit', 'https://example.com/press.zip', 6, 'fas fa-folder-arrow-down'),
                    $btn('Email press@', 'mailto:press@example.com', 6, 'fas fa-envelope'),
                ],
            ],
            [
                'slug' => 'contact-support-hours', 'name' => 'Support & Hours', 'category' => 'contact',
                'description' => 'Customer support hours with chat + email links.',
                'children' => [
                    $h('Need help?', 'h3'),
                    $p('Mon–Fri · 9 AM – 6 PM ET'),
                    $btn('Live chat', 'https://example.com/chat', 6, 'fas fa-comments'),
                    $btn('Email support', 'mailto:help@example.com', 6, 'fas fa-envelope'),
                ],
            ],
            [
                'slug' => 'contact-faq-redirect', 'name' => 'Self-Serve FAQ', 'category' => 'contact',
                'description' => 'Push people to a help center before they reach out.',
                'children' => [
                    $h('Got a question?', 'h3'),
                    $p('Most answers live in our help center.'),
                    $btn('Browse the help center', 'https://example.com/help', 12, 'fas fa-circle-question'),
                ],
            ],

            // ==================== PRODUCT (extra 8 → 16 total) ====================
            [
                'slug' => 'product-saas-trial', 'name' => 'SaaS Free Trial', 'category' => 'product',
                'description' => 'Headline, three feature bullets and a trial CTA.',
                'children' => [
                    $hg('Ship faster, with less', 'h1'),
                    $list(['No credit card required','Cancel anytime','Free for solo plans'], 12),
                    $btn('Start free trial', 'https://example.com', 12),
                ],
            ],
            [
                'slug' => 'product-launch-day', 'name' => 'Launch Day', 'category' => 'product',
                'description' => 'Big launch announcement with countdown vibe and CTA.',
                'children' => [
                    $hm('We\'re live today.', 'h1'),
                    $p('After 9 months of building — it\'s finally here.'),
                    $btn('Try it now', 'https://example.com', 12),
                    $btn('Read the launch post', 'https://example.com/blog', 12),
                ],
            ],
            [
                'slug' => 'product-affiliate', 'name' => 'Affiliate Tools', 'category' => 'product',
                'description' => 'Curated stack of recommended tools (with referral links).',
                'children' => [
                    $h('My toolkit', 'h2'),
                    $link('Notion — my second brain', 'https://example.com', 12, 'fas fa-book'),
                    $link('Figma — design everything', 'https://example.com', 12, 'fab fa-figma'),
                    $link('Replit — build & deploy', 'https://example.com', 12, 'fas fa-code'),
                    $p('Some links are affiliate. They cost you nothing extra.', 'center', 12),
                ],
            ],
            [
                'slug' => 'product-template-pack', 'name' => 'Template Pack', 'category' => 'product',
                'description' => 'Sell a downloadable pack of templates.',
                'children' => [
                    $img('', 12),
                    $h('50 Notion templates — $19', 'h2'),
                    $p('Personal & commercial use. Lifetime updates.'),
                    $btn('Buy on Gumroad', 'https://gumroad.com', 12, 'fas fa-bag-shopping'),
                ],
            ],
            [
                'slug' => 'product-coaching', 'name' => 'Coaching Package', 'category' => 'product',
                'description' => 'Outcome-led headline and call-booking CTA.',
                'children' => [
                    $h('1:1 Coaching', 'h2'),
                    $p('Six 60-min sessions to get unstuck and move on your big idea.'),
                    $list(['Weekly accountability','Voxer between sessions','Custom plan after week 1'], 12),
                    $btn('Book intro call', 'https://example.com', 12, 'far fa-calendar'),
                ],
            ],
            [
                'slug' => 'product-merch-store', 'name' => 'Merch Store', 'category' => 'product',
                'description' => 'Three featured products in a row with a "shop all" link.',
                'children' => [
                    $img('', 4), $img('', 4), $img('', 4),
                    $btn('Shop all merch', 'https://example.com/shop', 12),
                ],
            ],
            [
                'slug' => 'product-discount-code', 'name' => 'Discount Code', 'category' => 'product',
                'description' => 'Promo code callout with a single click-through.',
                'children' => [
                    $alert('Use code FRIENDS20 at checkout for 20% off.', 'success'),
                    $btn('Shop the sale', 'https://example.com', 12),
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

            // ==================== EVENT (extra 5 → 10 total) ====================
            [
                'slug' => 'event-meetup', 'name' => 'Local Meetup', 'category' => 'event',
                'description' => 'Cozy, IRL meetup card with venue + RSVP.',
                'children' => [
                    $h('Designers Coffee · NYC', 'h2'),
                    $p('Sat, Nov 22 · 10 AM · Greenpoint'),
                    $btn('RSVP', 'https://example.com', 12, 'far fa-calendar-check'),
                ],
            ],
            [
                'slug' => 'event-livestream', 'name' => 'Livestream', 'category' => 'event',
                'description' => 'Promote a one-time live event with a "watch live" button.',
                'children' => [
                    $h('Going live tonight', 'h2'),
                    $p('Friday · 9 PM ET · Q&A and a live demo.'),
                    $btn('Watch live', 'https://youtube.com', 12, 'fab fa-youtube'),
                ],
            ],
            [
                'slug' => 'event-launch-party', 'name' => 'Launch Party', 'category' => 'event',
                'description' => 'Party-style invite with date, venue and dress code.',
                'children' => [
                    $hg('You\'re invited', 'h1'),
                    $p('Launch party · Dec 9 · 7–11 PM · Brooklyn'),
                    $btn('Save your spot', 'https://example.com', 12),
                ],
            ],
            [
                'slug' => 'event-product-demo', 'name' => 'Product Demo', 'category' => 'event',
                'description' => 'Recurring weekly demo with a single sign-up CTA.',
                'children' => [
                    $h('Weekly product demo', 'h2'),
                    $p('Every Thursday · 1 PM ET · 30 minutes'),
                    $btn('Reserve a seat', 'https://example.com', 12),
                ],
            ],
            [
                'slug' => 'event-launch-waitlist', 'name' => 'Pre-Launch Waitlist', 'category' => 'event',
                'description' => 'Coming-soon style block with email capture.',
                'children' => [
                    $hm('Coming Soon', 'h1'),
                    $p('Drop your email to get early access on launch day.'),
                    $emailC('you@email.com'),
                ],
            ],

            // ==================== GALLERY (extra 5 → 10 total) ====================
            [
                'slug' => 'gallery-product-grid', 'name' => 'Product Grid', 'category' => 'gallery',
                'description' => 'A 2x3 grid of product photos with a single shop CTA.',
                'children' => [
                    $img('', 6), $img('', 6),
                    $img('', 6), $img('', 6),
                    $img('', 6), $img('', 6),
                    $btn('Shop all', 'https://example.com', 12),
                ],
            ],
            [
                'slug' => 'gallery-event-recap', 'name' => 'Event Recap', 'category' => 'gallery',
                'description' => 'Three photos from a recent event + recap link.',
                'children' => [
                    $h('Recap — Maker Day', 'h2'),
                    $img('', 4), $img('', 4), $img('', 4),
                    $btn('Read the recap', 'https://example.com', 12),
                ],
            ],
            [
                'slug' => 'gallery-team-grid', 'name' => 'Meet the Team', 'category' => 'gallery',
                'description' => 'Four headshots with names — perfect for an "about" section.',
                'children' => [
                    $h('Meet the team', 'h2'),
                    $img('', 6), $img('', 6),
                    $img('', 6), $img('', 6),
                ],
            ],
            [
                'slug' => 'gallery-press-logos', 'name' => 'As Seen In', 'category' => 'gallery',
                'description' => 'A row of press logos to build credibility.',
                'children' => [
                    $h('As seen in', 'h3'),
                    $img('', 3), $img('', 3), $img('', 3), $img('', 3),
                ],
            ],
            [
                'slug' => 'gallery-mood-board', 'name' => 'Mood Board', 'category' => 'gallery',
                'description' => 'Loose 6-image collage for visual inspiration.',
                'children' => [
                    $img('', 4), $img('', 4), $img('', 4),
                    $img('', 4), $img('', 4), $img('', 4),
                ],
            ],

            // ==================== GENERAL (extra 8 → 14 total) ====================
            [
                'slug' => 'general-stats-row', 'name' => 'Stats Row', 'category' => 'general',
                'description' => 'Three big numbers — followers, projects, years.',
                'children' => [
                    $hg('120k', 'h1', 'center', 4),
                    $hg('48', 'h1', 'center', 4),
                    $hg('7yrs', 'h1', 'center', 4),
                    $p('Followers · Projects · Years', 'center', 12),
                ],
            ],
            [
                'slug' => 'general-link-grid', 'name' => 'Link Grid', 'category' => 'general',
                'description' => 'A four-up grid of icon-led link buttons.',
                'children' => [
                    $link('Blog', 'https://example.com', 6, 'fas fa-newspaper'),
                    $link('Shop', 'https://example.com', 6, 'fas fa-bag-shopping'),
                    $link('Podcast', 'https://example.com', 6, 'fas fa-microphone'),
                    $link('YouTube', 'https://youtube.com', 6, 'fab fa-youtube'),
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
                'slug' => 'general-announcement', 'name' => 'Announcement', 'category' => 'general',
                'description' => 'An alert + CTA — for one-time, temporary news.',
                'children' => [
                    $alert('Heads up — site moving to a new domain on Dec 1.', 'warning'),
                    $btn('Read the details', 'https://example.com', 12),
                ],
            ],
            [
                'slug' => 'general-favorites', 'name' => 'My Favorites', 'category' => 'general',
                'description' => 'A short "currently loving" list — books, tools, places.',
                'children' => [
                    $h('Currently loving', 'h3'),
                    $list(['📕 The Creative Act','🎧 NTS Radio','☕ Sey Coffee','🛠 Linear'], 12),
                ],
            ],
            [
                'slug' => 'general-now-page', 'name' => 'Now Page', 'category' => 'general',
                'description' => '"What I\'m working on right now" — a /now-style block.',
                'children' => [
                    $h('Right now', 'h2'),
                    $p('• Shipping a small Notion plugin\n• Re-reading "Range" by David Epstein\n• Slowly running a 10K', 'left', 12),
                ],
            ],
            [
                'slug' => 'general-supporters', 'name' => 'Thanks, Supporters', 'category' => 'general',
                'description' => 'Public thank-you wall for supporters and patrons.',
                'children' => [
                    $h('Thanks to my supporters', 'h2'),
                    $p('You make the work possible. Become a supporter on Patreon or Ko-fi.', 'center', 12),
                    $btn('Support on Patreon', 'https://patreon.com', 6, 'fab fa-patreon'),
                    $btn('Buy me a coffee', 'https://ko-fi.com', 6, 'fas fa-mug-hot'),
                ],
            ],
            [
                'slug' => 'general-changelog', 'name' => 'Changelog', 'category' => 'general',
                'description' => 'Recent product updates — three latest entries.',
                'children' => [
                    $h('What\'s new', 'h3'),
                    $list(['v1.4 — Dark mode + share links','v1.3 — Notion sync + bug fixes','v1.2 — New onboarding flow'], 12),
                    $btn('Full changelog →', 'https://example.com', 12),
                ],
            ],
        ];
    }
}
