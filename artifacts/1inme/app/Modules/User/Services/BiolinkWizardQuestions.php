<?php

namespace App\Modules\User\Services;

/**
 * Taxonomy + question bank for the guided biolink creation wizard.
 *
 * The wizard walks the user through 4 short steps:
 *   1. Category    (Creator, Business, Restaurant, Musician, Real Estate,
 *                   Coach, Personal, Event)
 *   2. Page type   (e.g. Business → Local shop / Online store / Agency / SaaS)
 *   3. Industry    (optional — only for some categories, e.g.
 *                   Business/Local-shop → Bakery / Salon / Gym / Pet store / …)
 *   4. Detailed Q&A (8–12 questions tailored to the combo above) — answers
 *                   are turned into ordered biolink blocks by BiolinkPageRecipes.
 *
 * Each question is a plain associative array. The shape is intentionally small
 * so the Blade view can render it generically and so we can extend it without
 * a schema change.
 */
class BiolinkWizardQuestions
{
    /** Top-level categories shown on step 1. */
    public static function categories(): array
    {
        return [
            ['slug' => 'creator',     'label' => 'Creator / Influencer',  'icon' => 'fa-camera-retro', 'blurb' => 'YouTube, Instagram, TikTok creators, podcasters, writers.'],
            ['slug' => 'business',    'label' => 'Business',              'icon' => 'fa-briefcase',     'blurb' => 'Local shops, online stores, agencies, SaaS — anything you sell.'],
            ['slug' => 'restaurant',  'label' => 'Restaurant / Cafe',     'icon' => 'fa-utensils',      'blurb' => 'Menus, reservations, delivery and reviews in one place.'],
            ['slug' => 'musician',    'label' => 'Musician / Band / DJ',  'icon' => 'fa-music',         'blurb' => 'Streaming links, gigs, merch and a bookings contact.'],
            ['slug' => 'real_estate', 'label' => 'Real Estate Agent',     'icon' => 'fa-house-user',    'blurb' => 'Listings, viewings calendar and a lead-capture form.'],
            ['slug' => 'coach',       'label' => 'Coach / Consultant',    'icon' => 'fa-chalkboard-teacher', 'blurb' => 'Programs, testimonials, booking and a free intro call.'],
            ['slug' => 'personal',    'label' => 'Personal / Portfolio',  'icon' => 'fa-id-badge',      'blurb' => 'Showcase your work, links and a way to get in touch.'],
            ['slug' => 'event',       'label' => 'Event',                 'icon' => 'fa-calendar-day',  'blurb' => 'Wedding, conference, workshop or party — RSVPs included.'],
        ];
    }

    /** Page types per category. */
    public static function pageTypes(string $category): array
    {
        $map = [
            'creator' => [
                ['slug' => 'influencer',  'label' => 'Social Influencer',     'blurb' => 'Lots of socials, latest content, partnerships.'],
                ['slug' => 'youtuber',    'label' => 'YouTuber / Streamer',   'blurb' => 'Latest videos, live streams, channel feed.'],
                ['slug' => 'podcaster',   'label' => 'Podcaster',             'blurb' => 'Episodes, follow on Spotify/Apple, newsletter signup.'],
                ['slug' => 'writer',      'label' => 'Writer / Newsletter',   'blurb' => 'Posts, books, subscribe form.'],
                ['slug' => 'artist',      'label' => 'Artist / Illustrator',  'blurb' => 'Gallery, prints for sale, commissions form.'],
            ],
            'business' => [
                ['slug' => 'local_shop',  'label' => 'Local Shop / Service',  'blurb' => 'Hours, address, call, directions and reviews.'],
                ['slug' => 'online_store','label' => 'Online Store',          'blurb' => 'Featured products, catalog and a discount code.'],
                ['slug' => 'agency',      'label' => 'Agency / Studio',       'blurb' => 'Services, case studies and a contact form.'],
                ['slug' => 'saas',        'label' => 'SaaS / Tech Product',   'blurb' => 'Pricing, demo, signup and integrations.'],
                ['slug' => 'nonprofit',   'label' => 'Nonprofit / Charity',   'blurb' => 'Mission, donate button, volunteer signup.'],
            ],
            'restaurant' => [
                ['slug' => 'restaurant',  'label' => 'Restaurant',            'blurb' => 'Full menu, reservations, hours and directions.'],
                ['slug' => 'cafe',        'label' => 'Cafe / Bakery',         'blurb' => 'Specials, hours, instagram and order online.'],
                ['slug' => 'food_truck',  'label' => 'Food Truck',            'blurb' => 'Today\'s spot, menu and follow-for-locations.'],
                ['slug' => 'bar',         'label' => 'Bar / Nightlife',       'blurb' => 'Events tonight, cocktail menu, reservations.'],
            ],
            'musician' => [
                ['slug' => 'solo_artist', 'label' => 'Solo Artist',           'blurb' => 'New release, streaming links, gigs, merch.'],
                ['slug' => 'band',        'label' => 'Band',                  'blurb' => 'Members, tour dates, bookings contact.'],
                ['slug' => 'dj',          'label' => 'DJ / Producer',         'blurb' => 'Mixes, upcoming sets, bookings.'],
                ['slug' => 'classical',   'label' => 'Classical / Jazz',      'blurb' => 'Performances, recordings, press kit.'],
            ],
            'real_estate' => [
                ['slug' => 'residential', 'label' => 'Residential Agent',     'blurb' => 'Listings, market reports, viewings calendar.'],
                ['slug' => 'commercial',  'label' => 'Commercial Agent',      'blurb' => 'Office/retail spaces, lead form, case studies.'],
                ['slug' => 'broker',      'label' => 'Brokerage / Team',      'blurb' => 'Team members, listings, valuation request.'],
            ],
            'coach' => [
                ['slug' => 'fitness',     'label' => 'Fitness / Health Coach','blurb' => 'Programs, transformations, free intro session.'],
                ['slug' => 'life',        'label' => 'Life Coach',            'blurb' => 'Niches, testimonials, discovery call.'],
                ['slug' => 'business',    'label' => 'Business Coach',        'blurb' => 'Programs, case studies, application form.'],
                ['slug' => 'tutor',       'label' => 'Tutor / Educator',      'blurb' => 'Subjects, schedule, pricing and signup.'],
            ],
            'personal' => [
                ['slug' => 'developer',   'label' => 'Developer / Engineer',  'blurb' => 'Bio, projects, GitHub, blog and contact.'],
                ['slug' => 'designer',    'label' => 'Designer',              'blurb' => 'Portfolio gallery, behance/dribbble, contact.'],
                ['slug' => 'student',     'label' => 'Student',               'blurb' => 'About, projects, social handles.'],
                ['slug' => 'professional','label' => 'Professional / CV',     'blurb' => 'CV download, LinkedIn, contact email.'],
            ],
            'event' => [
                ['slug' => 'wedding',     'label' => 'Wedding',               'blurb' => 'Story, schedule, RSVP and registry.'],
                ['slug' => 'conference',  'label' => 'Conference',            'blurb' => 'Speakers, agenda, tickets, sponsors.'],
                ['slug' => 'workshop',    'label' => 'Workshop / Class',      'blurb' => 'What you\'ll learn, dates, signup.'],
                ['slug' => 'party',       'label' => 'Party / Meetup',        'blurb' => 'When, where, RSVP and house rules.'],
            ],
        ];
        return $map[$category] ?? [];
    }

    /**
     * Optional industry refinement. Returns [] when this combination has no
     * industry sub-step (the wizard simply skips step 3).
     */
    public static function industries(string $category, string $pageType): array
    {
        $map = [
            'business.local_shop' => [
                ['slug' => 'bakery',       'label' => 'Bakery'],
                ['slug' => 'salon',        'label' => 'Hair / Beauty Salon'],
                ['slug' => 'gym',          'label' => 'Gym / Studio'],
                ['slug' => 'pet_store',    'label' => 'Pet Store / Groomer'],
                ['slug' => 'auto',         'label' => 'Auto Repair / Wash'],
                ['slug' => 'florist',      'label' => 'Florist'],
                ['slug' => 'cleaning',     'label' => 'Cleaning Service'],
                ['slug' => 'other',        'label' => 'Something else'],
            ],
            'business.online_store' => [
                ['slug' => 'fashion',      'label' => 'Fashion / Apparel'],
                ['slug' => 'beauty',       'label' => 'Beauty / Skincare'],
                ['slug' => 'food',         'label' => 'Food / Drink'],
                ['slug' => 'home',         'label' => 'Home / Decor'],
                ['slug' => 'digital',      'label' => 'Digital / Downloads'],
                ['slug' => 'other',        'label' => 'Something else'],
            ],
            'business.agency' => [
                ['slug' => 'marketing',    'label' => 'Marketing Agency'],
                ['slug' => 'design',       'label' => 'Design Studio'],
                ['slug' => 'dev',          'label' => 'Software / Dev Agency'],
                ['slug' => 'consulting',   'label' => 'Consulting'],
                ['slug' => 'other',        'label' => 'Something else'],
            ],
            'restaurant.restaurant' => [
                ['slug' => 'italian',      'label' => 'Italian'],
                ['slug' => 'asian',        'label' => 'Asian'],
                ['slug' => 'mexican',      'label' => 'Mexican / Latin'],
                ['slug' => 'american',     'label' => 'American / Burgers'],
                ['slug' => 'mediterranean','label' => 'Mediterranean'],
                ['slug' => 'vegan',        'label' => 'Vegan / Plant-based'],
                ['slug' => 'fine_dining',  'label' => 'Fine Dining'],
                ['slug' => 'other',        'label' => 'Other cuisine'],
            ],
            'creator.influencer' => [
                ['slug' => 'lifestyle',    'label' => 'Lifestyle'],
                ['slug' => 'beauty',       'label' => 'Beauty / Fashion'],
                ['slug' => 'fitness',      'label' => 'Fitness / Health'],
                ['slug' => 'travel',       'label' => 'Travel'],
                ['slug' => 'food',         'label' => 'Food'],
                ['slug' => 'gaming',       'label' => 'Gaming'],
                ['slug' => 'parenting',    'label' => 'Parenting / Family'],
                ['slug' => 'other',        'label' => 'Other niche'],
            ],
            'coach.fitness' => [
                ['slug' => 'pt',           'label' => 'Personal Trainer'],
                ['slug' => 'yoga',         'label' => 'Yoga Instructor'],
                ['slug' => 'nutrition',    'label' => 'Nutrition Coach'],
                ['slug' => 'crossfit',     'label' => 'CrossFit / Strength'],
                ['slug' => 'pilates',      'label' => 'Pilates'],
                ['slug' => 'other',        'label' => 'Other'],
            ],
        ];
        return $map["{$category}.{$pageType}"] ?? [];
    }

    /**
     * Detailed questions for the combination. Falls back to the page-type
     * defaults, which fall back to category defaults, which fall back to
     * a generic set. Each question is:
     *   ['key', 'label', 'type', 'placeholder'?, 'help'?, 'options'?, 'required'?]
     * Types: text|textarea|url|email|phone|color|select|image|tags
     */
    public static function questions(string $category, string $pageType, ?string $industry = null): array
    {
        $base = self::baseIdentity();

        $byCombo = [
            // ── Creator ─────────────────────────────────────────────────
            'creator.influencer' => array_merge($base, [
                ['key' => 'instagram', 'label' => 'Instagram username', 'type' => 'text', 'placeholder' => '@yourhandle'],
                ['key' => 'tiktok',    'label' => 'TikTok username',    'type' => 'text', 'placeholder' => '@yourhandle'],
                ['key' => 'youtube',   'label' => 'YouTube channel URL','type' => 'url'],
                ['key' => 'twitter',   'label' => 'X / Twitter handle', 'type' => 'text', 'placeholder' => '@yourhandle'],
                ['key' => 'collab_email', 'label' => 'Collaboration email', 'type' => 'email', 'help' => 'For brand partnership inquiries.'],
                ['key' => 'featured_url',  'label' => 'Featured link (latest video, drop, etc.)', 'type' => 'url'],
                ['key' => 'featured_label','label' => 'Featured link label', 'type' => 'text', 'placeholder' => '👉 Watch my latest video'],
                ['key' => 'newsletter_blurb', 'label' => 'Newsletter pitch (1 line)', 'type' => 'text', 'placeholder' => 'Get my weekly drop in your inbox', 'help' => 'Leave blank to skip the newsletter signup.'],
            ]),
            'creator.youtuber' => array_merge($base, [
                ['key' => 'youtube',   'label' => 'YouTube channel URL', 'type' => 'url', 'required' => true],
                ['key' => 'twitch',    'label' => 'Twitch URL (if any)', 'type' => 'url'],
                ['key' => 'discord',   'label' => 'Discord invite URL',  'type' => 'url'],
                ['key' => 'instagram', 'label' => 'Instagram username',  'type' => 'text', 'placeholder' => '@yourhandle'],
                ['key' => 'twitter',   'label' => 'X / Twitter handle',  'type' => 'text'],
                ['key' => 'merch_url', 'label' => 'Merch store URL',     'type' => 'url'],
                ['key' => 'sponsor_email', 'label' => 'Sponsorship email', 'type' => 'email'],
                ['key' => 'pinned_video',  'label' => 'Pinned video URL',  'type' => 'url', 'help' => 'Shown front and center.'],
            ]),
            'creator.podcaster' => array_merge($base, [
                ['key' => 'spotify',     'label' => 'Spotify show URL',  'type' => 'url'],
                ['key' => 'apple',       'label' => 'Apple Podcasts URL','type' => 'url'],
                ['key' => 'youtube',     'label' => 'YouTube channel URL','type' => 'url'],
                ['key' => 'rss',         'label' => 'RSS feed URL',      'type' => 'url'],
                ['key' => 'episode_url', 'label' => 'Latest episode URL','type' => 'url'],
                ['key' => 'episode_title','label' => 'Latest episode title', 'type' => 'text'],
                ['key' => 'guest_form',  'label' => 'Guest application form URL', 'type' => 'url'],
                ['key' => 'newsletter_blurb', 'label' => 'Newsletter pitch (1 line)', 'type' => 'text'],
            ]),
            'creator.writer' => array_merge($base, [
                ['key' => 'newsletter_url',  'label' => 'Newsletter URL (Substack/Beehiiv)', 'type' => 'url'],
                ['key' => 'newsletter_blurb','label' => 'Newsletter pitch (1 line)', 'type' => 'text', 'placeholder' => 'Weekly essays on X'],
                ['key' => 'twitter',         'label' => 'X / Twitter handle', 'type' => 'text'],
                ['key' => 'medium',          'label' => 'Medium URL',        'type' => 'url'],
                ['key' => 'book_url',        'label' => 'Book / Amazon URL', 'type' => 'url'],
                ['key' => 'book_title',      'label' => 'Book title',        'type' => 'text'],
                ['key' => 'speaking_email',  'label' => 'Speaking inquiries email', 'type' => 'email'],
            ]),
            'creator.artist' => array_merge($base, [
                ['key' => 'instagram',     'label' => 'Instagram username', 'type' => 'text'],
                ['key' => 'shop_url',      'label' => 'Shop / Print store URL', 'type' => 'url'],
                ['key' => 'commissions_open','label' => 'Are commissions open?', 'type' => 'select', 'options' => [['v'=>'yes','l'=>'Yes — open'], ['v'=>'waitlist','l'=>'Waitlist'], ['v'=>'no','l'=>'Closed']]],
                ['key' => 'commissions_email','label' => 'Commissions inquiry email', 'type' => 'email'],
                ['key' => 'gallery_blurb', 'label' => 'Short gallery intro (1 line)', 'type' => 'text', 'placeholder' => 'Selected works, 2024–2026'],
                ['key' => 'patreon_url',   'label' => 'Patreon / membership URL', 'type' => 'url'],
            ]),

            // ── Business ────────────────────────────────────────────────
            'business.local_shop' => array_merge($base, [
                ['key' => 'business_name','label' => 'Business name',     'type' => 'text', 'required' => true],
                ['key' => 'address',      'label' => 'Street address',    'type' => 'text', 'required' => true],
                ['key' => 'phone',        'label' => 'Phone number',      'type' => 'phone'],
                ['key' => 'whatsapp',     'label' => 'WhatsApp number (optional)', 'type' => 'phone'],
                ['key' => 'hours',        'label' => 'Opening hours',     'type' => 'textarea', 'placeholder' => "Mon–Fri 9am–6pm\nSat 10am–4pm\nSun closed"],
                ['key' => 'website',      'label' => 'Website URL',       'type' => 'url'],
                ['key' => 'cta_label',    'label' => 'Main button label', 'type' => 'text', 'placeholder' => 'Book Appointment'],
                ['key' => 'cta_url',      'label' => 'Main button URL',   'type' => 'url'],
                ['key' => 'review_blurb', 'label' => 'Customer reviews note (1 line)', 'type' => 'text', 'placeholder' => 'Loved by 200+ happy customers'],
            ]),
            'business.online_store' => array_merge($base, [
                ['key' => 'store_name',     'label' => 'Store name',             'type' => 'text', 'required' => true],
                ['key' => 'store_url',      'label' => 'Store URL',              'type' => 'url',  'required' => true],
                ['key' => 'best_seller_1',  'label' => 'Best-seller #1 name',    'type' => 'text'],
                ['key' => 'best_seller_1_url','label' => 'Best-seller #1 URL',   'type' => 'url'],
                ['key' => 'best_seller_2',  'label' => 'Best-seller #2 name',    'type' => 'text'],
                ['key' => 'best_seller_2_url','label' => 'Best-seller #2 URL',   'type' => 'url'],
                ['key' => 'best_seller_3',  'label' => 'Best-seller #3 name',    'type' => 'text'],
                ['key' => 'best_seller_3_url','label' => 'Best-seller #3 URL',   'type' => 'url'],
                ['key' => 'discount_code',  'label' => 'Discount code (optional)', 'type' => 'text', 'placeholder' => 'WELCOME10'],
                ['key' => 'discount_blurb', 'label' => 'Discount blurb',         'type' => 'text', 'placeholder' => 'Get 10% off your first order'],
                ['key' => 'newsletter_blurb','label' => 'Newsletter pitch',      'type' => 'text'],
            ]),
            'business.agency' => array_merge($base, [
                ['key' => 'agency_name', 'label' => 'Agency name',             'type' => 'text', 'required' => true],
                ['key' => 'tagline',     'label' => 'Tagline (1 line)',        'type' => 'text', 'placeholder' => 'We build brands that move'],
                ['key' => 'service_1',   'label' => 'Service #1',              'type' => 'text'],
                ['key' => 'service_1_desc','label' => 'Service #1 description','type' => 'text'],
                ['key' => 'service_2',   'label' => 'Service #2',              'type' => 'text'],
                ['key' => 'service_2_desc','label' => 'Service #2 description','type' => 'text'],
                ['key' => 'service_3',   'label' => 'Service #3',              'type' => 'text'],
                ['key' => 'service_3_desc','label' => 'Service #3 description','type' => 'text'],
                ['key' => 'case_study_url','label' => 'Featured case study URL','type' => 'url'],
                ['key' => 'contact_email','label' => 'New-business email',     'type' => 'email', 'required' => true],
                ['key' => 'calendly_url','label' => 'Calendly / booking URL',  'type' => 'url'],
            ]),
            'business.saas' => array_merge($base, [
                ['key' => 'product_name','label' => 'Product name',            'type' => 'text', 'required' => true],
                ['key' => 'tagline',     'label' => 'One-line pitch',          'type' => 'text', 'required' => true, 'placeholder' => 'The fastest way to ship X'],
                ['key' => 'signup_url',  'label' => 'Signup / Get started URL','type' => 'url',  'required' => true],
                ['key' => 'demo_url',    'label' => 'Book-a-demo URL',         'type' => 'url'],
                ['key' => 'pricing_url', 'label' => 'Pricing page URL',        'type' => 'url'],
                ['key' => 'docs_url',    'label' => 'Docs URL',                'type' => 'url'],
                ['key' => 'changelog_url','label' => 'Changelog / blog URL',   'type' => 'url'],
                ['key' => 'twitter',     'label' => 'X / Twitter handle',      'type' => 'text'],
                ['key' => 'github',      'label' => 'GitHub URL',              'type' => 'url'],
            ]),
            'business.nonprofit' => array_merge($base, [
                ['key' => 'org_name',    'label' => 'Organisation name',  'type' => 'text', 'required' => true],
                ['key' => 'mission',     'label' => 'Mission (2 sentences)', 'type' => 'textarea', 'required' => true],
                ['key' => 'donate_url',  'label' => 'Donation URL',       'type' => 'url'],
                ['key' => 'volunteer_form','label' => 'Volunteer form URL','type' => 'url'],
                ['key' => 'impact_blurb','label' => 'Impact stat (1 line)','type' => 'text', 'placeholder' => '12,000 meals served in 2025'],
                ['key' => 'contact_email','label' => 'Contact email',     'type' => 'email'],
                ['key' => 'instagram',   'label' => 'Instagram username', 'type' => 'text'],
            ]),

            // ── Restaurant ──────────────────────────────────────────────
            'restaurant.restaurant' => array_merge($base, [
                ['key' => 'venue_name',  'label' => 'Restaurant name',    'type' => 'text', 'required' => true],
                ['key' => 'cuisine',     'label' => 'Cuisine (1 word)',   'type' => 'text', 'placeholder' => 'Italian'],
                ['key' => 'address',     'label' => 'Address',            'type' => 'text', 'required' => true],
                ['key' => 'phone',       'label' => 'Phone number',       'type' => 'phone'],
                ['key' => 'hours',       'label' => 'Opening hours',      'type' => 'textarea'],
                ['key' => 'menu_url',    'label' => 'Menu URL (or PDF)',  'type' => 'url'],
                ['key' => 'reserve_url', 'label' => 'Reservation URL (OpenTable, Resy…)', 'type' => 'url'],
                ['key' => 'delivery_url','label' => 'Delivery URL (UberEats…)', 'type' => 'url'],
                ['key' => 'instagram',   'label' => 'Instagram username', 'type' => 'text'],
                ['key' => 'review_url',  'label' => 'Google reviews URL', 'type' => 'url'],
            ]),
            'restaurant.cafe' => array_merge($base, [
                ['key' => 'venue_name',  'label' => 'Cafe name',          'type' => 'text', 'required' => true],
                ['key' => 'address',     'label' => 'Address',            'type' => 'text', 'required' => true],
                ['key' => 'hours',       'label' => 'Opening hours',      'type' => 'textarea'],
                ['key' => 'specialty',   'label' => 'Signature item',     'type' => 'text', 'placeholder' => 'Sourdough croissants'],
                ['key' => 'order_url',   'label' => 'Order online URL',   'type' => 'url'],
                ['key' => 'instagram',   'label' => 'Instagram username', 'type' => 'text'],
                ['key' => 'phone',       'label' => 'Phone number',       'type' => 'phone'],
            ]),
            'restaurant.food_truck' => array_merge($base, [
                ['key' => 'truck_name',  'label' => 'Food truck name',    'type' => 'text', 'required' => true],
                ['key' => 'today_spot',  'label' => 'Today\'s location',  'type' => 'text', 'help' => 'You can update this any time.'],
                ['key' => 'today_hours', 'label' => 'Today\'s hours',     'type' => 'text', 'placeholder' => '11am – 8pm'],
                ['key' => 'menu_url',    'label' => 'Menu URL or PDF',    'type' => 'url'],
                ['key' => 'instagram',   'label' => 'Instagram username (for daily updates)', 'type' => 'text'],
                ['key' => 'phone',       'label' => 'Phone number',       'type' => 'phone'],
                ['key' => 'catering_email','label' => 'Catering enquiries email', 'type' => 'email'],
            ]),
            'restaurant.bar' => array_merge($base, [
                ['key' => 'venue_name',  'label' => 'Bar name',           'type' => 'text', 'required' => true],
                ['key' => 'address',     'label' => 'Address',            'type' => 'text'],
                ['key' => 'hours',       'label' => 'Opening hours',      'type' => 'textarea'],
                ['key' => 'event_tonight','label' => 'Event tonight (1 line)', 'type' => 'text', 'placeholder' => 'Live jazz · 9pm'],
                ['key' => 'reserve_url', 'label' => 'Reservation URL',    'type' => 'url'],
                ['key' => 'menu_url',    'label' => 'Cocktail menu URL',  'type' => 'url'],
                ['key' => 'instagram',   'label' => 'Instagram username', 'type' => 'text'],
            ]),

            // ── Musician ────────────────────────────────────────────────
            'musician.solo_artist' => array_merge($base, [
                ['key' => 'artist_name', 'label' => 'Artist / stage name','type' => 'text', 'required' => true],
                ['key' => 'spotify',     'label' => 'Spotify artist URL', 'type' => 'url'],
                ['key' => 'apple_music', 'label' => 'Apple Music URL',    'type' => 'url'],
                ['key' => 'youtube',     'label' => 'YouTube channel URL','type' => 'url'],
                ['key' => 'soundcloud',  'label' => 'SoundCloud URL',     'type' => 'url'],
                ['key' => 'latest_release_url',  'label' => 'Latest release smart-link', 'type' => 'url'],
                ['key' => 'latest_release_title','label' => 'Latest release title',     'type' => 'text'],
                ['key' => 'merch_url',   'label' => 'Merch store URL',    'type' => 'url'],
                ['key' => 'tour_url',    'label' => 'Tour dates / Bandsintown URL', 'type' => 'url'],
                ['key' => 'booking_email','label' => 'Booking email',     'type' => 'email'],
            ]),
            'musician.band' => array_merge($base, [
                ['key' => 'band_name',     'label' => 'Band name',         'type' => 'text', 'required' => true],
                ['key' => 'genre',         'label' => 'Genre',             'type' => 'text', 'placeholder' => 'Indie rock'],
                ['key' => 'members',       'label' => 'Members (one per line)', 'type' => 'textarea'],
                ['key' => 'spotify',       'label' => 'Spotify URL',       'type' => 'url'],
                ['key' => 'youtube',       'label' => 'YouTube URL',       'type' => 'url'],
                ['key' => 'instagram',     'label' => 'Instagram',         'type' => 'text'],
                ['key' => 'tour_url',      'label' => 'Tour dates URL',    'type' => 'url'],
                ['key' => 'press_kit_url', 'label' => 'Press kit URL',     'type' => 'url'],
                ['key' => 'booking_email', 'label' => 'Booking email',     'type' => 'email'],
            ]),
            'musician.dj' => array_merge($base, [
                ['key' => 'dj_name',      'label' => 'DJ name',            'type' => 'text', 'required' => true],
                ['key' => 'soundcloud',   'label' => 'SoundCloud URL',     'type' => 'url'],
                ['key' => 'mixcloud',     'label' => 'Mixcloud URL',       'type' => 'url'],
                ['key' => 'spotify',      'label' => 'Spotify URL',        'type' => 'url'],
                ['key' => 'next_set',     'label' => 'Next set (venue + date)', 'type' => 'text', 'placeholder' => 'Berghain · Apr 27'],
                ['key' => 'tour_url',     'label' => 'All upcoming sets URL', 'type' => 'url'],
                ['key' => 'instagram',    'label' => 'Instagram',          'type' => 'text'],
                ['key' => 'booking_email','label' => 'Bookings email',     'type' => 'email'],
            ]),
            'musician.classical' => array_merge($base, [
                ['key' => 'artist_name',  'label' => 'Artist / ensemble name', 'type' => 'text', 'required' => true],
                ['key' => 'instrument',   'label' => 'Instrument / voice',   'type' => 'text'],
                ['key' => 'next_concert', 'label' => 'Next concert (1 line)','type' => 'text'],
                ['key' => 'tour_url',     'label' => 'Concert calendar URL','type' => 'url'],
                ['key' => 'recordings_url','label' => 'Recordings URL',    'type' => 'url'],
                ['key' => 'press_kit_url','label' => 'Press kit URL',      'type' => 'url'],
                ['key' => 'mgmt_email',   'label' => 'Management email',   'type' => 'email'],
            ]),

            // ── Real Estate ─────────────────────────────────────────────
            'real_estate.residential' => array_merge($base, [
                ['key' => 'agent_name',     'label' => 'Agent name',         'type' => 'text', 'required' => true],
                ['key' => 'brokerage',      'label' => 'Brokerage',          'type' => 'text'],
                ['key' => 'service_area',   'label' => 'Service area / city','type' => 'text', 'placeholder' => 'Greater Boston'],
                ['key' => 'phone',          'label' => 'Phone number',       'type' => 'phone'],
                ['key' => 'email',          'label' => 'Contact email',      'type' => 'email'],
                ['key' => 'listings_url',   'label' => 'All listings URL',   'type' => 'url'],
                ['key' => 'featured_listing_url',  'label' => 'Featured listing URL', 'type' => 'url'],
                ['key' => 'featured_listing_title','label' => 'Featured listing title', 'type' => 'text', 'placeholder' => '4-bed colonial · $850k'],
                ['key' => 'calendly_url',   'label' => 'Booking / viewings URL', 'type' => 'url'],
                ['key' => 'valuation_form_url', 'label' => 'Free home valuation form URL', 'type' => 'url'],
                ['key' => 'instagram',      'label' => 'Instagram',          'type' => 'text'],
            ]),
            'real_estate.commercial' => array_merge($base, [
                ['key' => 'agent_name',     'label' => 'Agent name',         'type' => 'text', 'required' => true],
                ['key' => 'firm',           'label' => 'Firm',               'type' => 'text'],
                ['key' => 'sectors',        'label' => 'Sectors (office, retail, industrial…)', 'type' => 'text'],
                ['key' => 'phone',          'label' => 'Phone number',       'type' => 'phone'],
                ['key' => 'email',          'label' => 'Contact email',      'type' => 'email'],
                ['key' => 'listings_url',   'label' => 'All listings URL',   'type' => 'url'],
                ['key' => 'case_study_url', 'label' => 'Case study URL',     'type' => 'url'],
                ['key' => 'linkedin',       'label' => 'LinkedIn URL',       'type' => 'url'],
            ]),
            'real_estate.broker' => array_merge($base, [
                ['key' => 'firm_name',      'label' => 'Firm name',          'type' => 'text', 'required' => true],
                ['key' => 'tagline',        'label' => 'Tagline',            'type' => 'text'],
                ['key' => 'service_area',   'label' => 'Service area',       'type' => 'text'],
                ['key' => 'team_size',      'label' => 'Team size',          'type' => 'text', 'placeholder' => '12 agents'],
                ['key' => 'listings_url',   'label' => 'All listings URL',   'type' => 'url'],
                ['key' => 'phone',          'label' => 'Office phone',       'type' => 'phone'],
                ['key' => 'email',          'label' => 'Contact email',      'type' => 'email'],
                ['key' => 'valuation_form_url','label' => 'Free valuation form URL', 'type' => 'url'],
            ]),

            // ── Coach ───────────────────────────────────────────────────
            'coach.fitness' => array_merge($base, [
                ['key' => 'coach_name',    'label' => 'Coach name',         'type' => 'text', 'required' => true],
                ['key' => 'specialty',     'label' => 'Specialty (1 line)', 'type' => 'text', 'placeholder' => 'Strength training for women over 40'],
                ['key' => 'program_1',     'label' => 'Program #1 name',    'type' => 'text'],
                ['key' => 'program_1_price','label' => 'Program #1 price',  'type' => 'text', 'placeholder' => '$199 / month'],
                ['key' => 'program_2',     'label' => 'Program #2 name',    'type' => 'text'],
                ['key' => 'program_2_price','label' => 'Program #2 price',  'type' => 'text'],
                ['key' => 'program_3',     'label' => 'Program #3 name',    'type' => 'text'],
                ['key' => 'program_3_price','label' => 'Program #3 price',  'type' => 'text'],
                ['key' => 'free_intro_url','label' => 'Free intro session URL', 'type' => 'url'],
                ['key' => 'testimonial',   'label' => 'Best client quote',  'type' => 'textarea'],
                ['key' => 'testimonial_name','label' => 'Quote — client name','type' => 'text'],
                ['key' => 'instagram',     'label' => 'Instagram',          'type' => 'text'],
            ]),
            'coach.life' => array_merge($base, [
                ['key' => 'coach_name',    'label' => 'Coach name',         'type' => 'text', 'required' => true],
                ['key' => 'niche',         'label' => 'Coaching niche',     'type' => 'text', 'placeholder' => 'Confidence & career changes'],
                ['key' => 'discovery_url', 'label' => 'Free discovery call URL', 'type' => 'url'],
                ['key' => 'program_1',     'label' => 'Program #1 name',    'type' => 'text'],
                ['key' => 'program_1_price','label' => 'Program #1 price',  'type' => 'text'],
                ['key' => 'program_2',     'label' => 'Program #2 name',    'type' => 'text'],
                ['key' => 'program_2_price','label' => 'Program #2 price',  'type' => 'text'],
                ['key' => 'testimonial',   'label' => 'Best client quote',  'type' => 'textarea'],
                ['key' => 'testimonial_name','label' => 'Quote — client name','type' => 'text'],
                ['key' => 'newsletter_blurb','label' => 'Newsletter pitch (optional)', 'type' => 'text'],
                ['key' => 'instagram',     'label' => 'Instagram',          'type' => 'text'],
            ]),
            'coach.business' => array_merge($base, [
                ['key' => 'coach_name',    'label' => 'Your name',          'type' => 'text', 'required' => true],
                ['key' => 'niche',         'label' => 'Who you help (1 line)', 'type' => 'text', 'placeholder' => 'Founders scaling from $1M to $10M'],
                ['key' => 'application_url','label' => 'Application form URL','type' => 'url'],
                ['key' => 'mastermind_name','label' => 'Mastermind / cohort name', 'type' => 'text'],
                ['key' => 'mastermind_price','label' => 'Mastermind price', 'type' => 'text'],
                ['key' => '1to1_price',    'label' => '1:1 coaching price', 'type' => 'text'],
                ['key' => 'testimonial',   'label' => 'Best client quote',  'type' => 'textarea'],
                ['key' => 'testimonial_name','label' => 'Quote — client name','type' => 'text'],
                ['key' => 'case_study_url','label' => 'Case study URL',     'type' => 'url'],
                ['key' => 'linkedin',      'label' => 'LinkedIn URL',       'type' => 'url'],
            ]),
            'coach.tutor' => array_merge($base, [
                ['key' => 'tutor_name', 'label' => 'Tutor name',            'type' => 'text', 'required' => true],
                ['key' => 'subjects',   'label' => 'Subjects',              'type' => 'text', 'placeholder' => 'Math, Physics — GCSE & A-Level'],
                ['key' => 'levels',     'label' => 'Levels / age groups',   'type' => 'text'],
                ['key' => 'mode',       'label' => 'In-person, online or both?', 'type' => 'select', 'options' => [['v'=>'online','l'=>'Online'], ['v'=>'in_person','l'=>'In-person'], ['v'=>'both','l'=>'Both']]],
                ['key' => 'price',      'label' => 'Hourly rate',           'type' => 'text', 'placeholder' => '£40 / hour'],
                ['key' => 'booking_url','label' => 'Booking URL',           'type' => 'url'],
                ['key' => 'email',      'label' => 'Contact email',         'type' => 'email'],
                ['key' => 'phone',      'label' => 'Phone (optional)',      'type' => 'phone'],
            ]),

            // ── Personal ────────────────────────────────────────────────
            'personal.developer' => array_merge($base, [
                ['key' => 'role',        'label' => 'Role / title',       'type' => 'text', 'placeholder' => 'Senior backend engineer'],
                ['key' => 'github',      'label' => 'GitHub URL',         'type' => 'url'],
                ['key' => 'linkedin',    'label' => 'LinkedIn URL',       'type' => 'url'],
                ['key' => 'twitter',     'label' => 'X / Twitter handle', 'type' => 'text'],
                ['key' => 'blog_url',    'label' => 'Blog / personal site','type' => 'url'],
                ['key' => 'project_1',   'label' => 'Project #1 name',    'type' => 'text'],
                ['key' => 'project_1_url','label' => 'Project #1 URL',    'type' => 'url'],
                ['key' => 'project_2',   'label' => 'Project #2 name',    'type' => 'text'],
                ['key' => 'project_2_url','label' => 'Project #2 URL',    'type' => 'url'],
                ['key' => 'cv_url',      'label' => 'CV / Resume URL',    'type' => 'url'],
                ['key' => 'email',       'label' => 'Contact email',      'type' => 'email'],
            ]),
            'personal.designer' => array_merge($base, [
                ['key' => 'role',          'label' => 'Discipline',         'type' => 'text', 'placeholder' => 'Brand designer & illustrator'],
                ['key' => 'portfolio_url', 'label' => 'Portfolio URL',      'type' => 'url'],
                ['key' => 'behance',       'label' => 'Behance URL',        'type' => 'url'],
                ['key' => 'dribbble',      'label' => 'Dribbble URL',       'type' => 'url'],
                ['key' => 'instagram',     'label' => 'Instagram',          'type' => 'text'],
                ['key' => 'project_1_url', 'label' => 'Featured project URL', 'type' => 'url'],
                ['key' => 'project_1',     'label' => 'Featured project name', 'type' => 'text'],
                ['key' => 'available_for', 'label' => 'Available for…',     'type' => 'text', 'placeholder' => 'Freelance · Mar 2026 onwards'],
                ['key' => 'email',         'label' => 'Contact email',      'type' => 'email'],
            ]),
            'personal.student' => array_merge($base, [
                ['key' => 'school',     'label' => 'School / University',  'type' => 'text'],
                ['key' => 'major',      'label' => 'Major / Subject',      'type' => 'text'],
                ['key' => 'project_1_url','label' => 'Project URL',        'type' => 'url'],
                ['key' => 'project_1',  'label' => 'Project name',         'type' => 'text'],
                ['key' => 'instagram',  'label' => 'Instagram',            'type' => 'text'],
                ['key' => 'github',     'label' => 'GitHub URL',           'type' => 'url'],
                ['key' => 'cv_url',     'label' => 'CV URL (if any)',      'type' => 'url'],
                ['key' => 'email',      'label' => 'Email',                'type' => 'email'],
            ]),
            'personal.professional' => array_merge($base, [
                ['key' => 'title',      'label' => 'Job title',            'type' => 'text', 'placeholder' => 'Product Manager at Acme'],
                ['key' => 'cv_url',     'label' => 'CV / Resume URL',      'type' => 'url'],
                ['key' => 'linkedin',   'label' => 'LinkedIn URL',         'type' => 'url',  'required' => true],
                ['key' => 'twitter',    'label' => 'X / Twitter handle',   'type' => 'text'],
                ['key' => 'blog_url',   'label' => 'Blog / writing URL',   'type' => 'url'],
                ['key' => 'speaking_blurb','label' => 'Speaking / availability blurb', 'type' => 'text'],
                ['key' => 'email',      'label' => 'Contact email',        'type' => 'email'],
                ['key' => 'phone',      'label' => 'Phone (optional)',     'type' => 'phone'],
            ]),

            // ── Event ───────────────────────────────────────────────────
            'event.wedding' => array_merge(self::baseEvent(), [
                ['key' => 'couple',        'label' => 'Couple names',       'type' => 'text', 'required' => true, 'placeholder' => 'Sam & Alex'],
                ['key' => 'date',          'label' => 'Wedding date',       'type' => 'text', 'placeholder' => 'June 14, 2026'],
                ['key' => 'venue',         'label' => 'Venue name',         'type' => 'text'],
                ['key' => 'venue_address', 'label' => 'Venue address',      'type' => 'text'],
                ['key' => 'schedule',      'label' => 'Day-of schedule',    'type' => 'textarea', 'placeholder' => "3:00pm · Ceremony\n4:30pm · Drinks\n7:00pm · Dinner"],
                ['key' => 'rsvp_url',      'label' => 'RSVP form URL',      'type' => 'url'],
                ['key' => 'registry_url',  'label' => 'Registry URL',       'type' => 'url'],
                ['key' => 'dress_code',    'label' => 'Dress code',         'type' => 'text', 'placeholder' => 'Black-tie optional'],
                ['key' => 'hashtag',       'label' => 'Wedding hashtag',    'type' => 'text', 'placeholder' => '#SamAndAlex2026'],
            ]),
            'event.conference' => array_merge(self::baseEvent(), [
                ['key' => 'event_name',   'label' => 'Conference name',    'type' => 'text', 'required' => true],
                ['key' => 'tagline',      'label' => 'Tagline',            'type' => 'text'],
                ['key' => 'date_range',   'label' => 'Dates',              'type' => 'text', 'placeholder' => 'Sep 18–20, 2026'],
                ['key' => 'venue',        'label' => 'Venue',              'type' => 'text'],
                ['key' => 'venue_address','label' => 'Venue address',      'type' => 'text'],
                ['key' => 'tickets_url',  'label' => 'Buy tickets URL',    'type' => 'url',  'required' => true],
                ['key' => 'agenda_url',   'label' => 'Agenda URL',         'type' => 'url'],
                ['key' => 'speakers_url', 'label' => 'Speakers URL',       'type' => 'url'],
                ['key' => 'sponsors_url', 'label' => 'Become a sponsor URL', 'type' => 'url'],
                ['key' => 'twitter',      'label' => 'X / Twitter handle', 'type' => 'text'],
            ]),
            'event.workshop' => array_merge(self::baseEvent(), [
                ['key' => 'event_name',   'label' => 'Workshop name',      'type' => 'text', 'required' => true],
                ['key' => 'date_range',   'label' => 'Date(s)',            'type' => 'text'],
                ['key' => 'venue',        'label' => 'Venue (or "Online")','type' => 'text'],
                ['key' => 'price',        'label' => 'Price',              'type' => 'text', 'placeholder' => '$199 · early bird $149'],
                ['key' => 'signup_url',   'label' => 'Signup URL',         'type' => 'url',  'required' => true],
                ['key' => 'curriculum',   'label' => 'What you\'ll learn (one bullet per line)', 'type' => 'textarea'],
                ['key' => 'instructor',   'label' => 'Instructor name',    'type' => 'text'],
                ['key' => 'instructor_bio','label' => 'Instructor short bio', 'type' => 'textarea'],
            ]),
            'event.party' => array_merge(self::baseEvent(), [
                ['key' => 'event_name',   'label' => 'Party / meetup name', 'type' => 'text', 'required' => true],
                ['key' => 'date',         'label' => 'Date & time',         'type' => 'text', 'placeholder' => 'Sat, May 9 · 8pm'],
                ['key' => 'venue',        'label' => 'Venue',               'type' => 'text'],
                ['key' => 'venue_address','label' => 'Address',             'type' => 'text'],
                ['key' => 'rsvp_url',     'label' => 'RSVP URL',            'type' => 'url'],
                ['key' => 'house_rules',  'label' => 'Good-to-knows (one per line)', 'type' => 'textarea', 'placeholder' => "BYOB\n21+ only\nDress: smart casual"],
                ['key' => 'host_email',   'label' => 'Host email',          'type' => 'email'],
            ]),
        ];

        $key = "{$category}.{$pageType}";
        if (isset($byCombo[$key])) {
            return $byCombo[$key];
        }

        // Fallback: identity + the most useful generic links.
        return array_merge($base, [
            ['key' => 'website',      'label' => 'Website URL',        'type' => 'url'],
            ['key' => 'instagram',    'label' => 'Instagram username', 'type' => 'text'],
            ['key' => 'twitter',      'label' => 'X / Twitter handle', 'type' => 'text'],
            ['key' => 'cta_label',    'label' => 'Main button label',  'type' => 'text'],
            ['key' => 'cta_url',      'label' => 'Main button URL',    'type' => 'url'],
            ['key' => 'email',        'label' => 'Contact email',      'type' => 'email'],
        ]);
    }

    /**
     * Identity questions every page asks (display name, headline, profile
     * pic + brand color). Industry-specific question sets append to this.
     */
    public static function baseIdentity(): array
    {
        return [
            ['key' => 'display_name', 'label' => 'Display name on the page', 'type' => 'text',     'required' => true, 'placeholder' => 'How should visitors see your name?'],
            ['key' => 'headline',     'label' => 'Headline (1 short line)',  'type' => 'text',     'required' => true, 'placeholder' => 'A few words about you'],
            ['key' => 'bio',          'label' => 'Short bio (2–3 sentences)','type' => 'textarea', 'help' => 'Shown right under your name.'],
            ['key' => 'avatar',       'label' => 'Profile picture',          'type' => 'image',    'help' => 'We pick a placeholder if you skip — change it later in the editor.'],
            ['key' => 'brand_color',  'label' => 'Brand colour',             'type' => 'color',    'help' => 'Used for buttons and accents.'],
        ];
    }

    /**
     * Identity questions for events — events use the event name as the
     * "display name" so we keep the avatar/brand colour but skip the
     * personal name + bio fields (they're asked in the per-event set).
     */
    public static function baseEvent(): array
    {
        return [
            ['key' => 'avatar',      'label' => 'Cover image',  'type' => 'image', 'help' => 'A placeholder is used if you skip.'],
            ['key' => 'brand_color', 'label' => 'Brand colour', 'type' => 'color', 'help' => 'Used for buttons and accents.'],
        ];
    }

    /** True if the (category, page_type) needs an industry sub-step. */
    public static function hasIndustryStep(string $category, string $pageType): bool
    {
        return !empty(self::industries($category, $pageType));
    }

    /** Default brand colour for a category — used when the user skips. */
    public static function defaultBrandColor(string $category): string
    {
        return [
            'creator'     => '#ec4899',
            'business'    => '#7c3aed',
            'restaurant'  => '#f97316',
            'musician'    => '#a855f7',
            'real_estate' => '#0ea5e9',
            'coach'       => '#10b981',
            'personal'    => '#6366f1',
            'event'       => '#f59e0b',
        ][$category] ?? '#7c3aed';
    }

    /**
     * The answer keys that can serve as the page's display name / title, in
     * priority order. The wizard requires at least one to be present and uses
     * the first non-empty value as the generated Link's title. Shared by the
     * web wizard (BiolinkWizardController::finish) and the mobile API wizard
     * so both honour exactly the same "needs a name" contract.
     */
    public static function nameKeys(): array
    {
        return [
            'display_name', 'business_name', 'event_name', 'couple', 'venue_name',
            'agency_name', 'store_name', 'artist_name', 'band_name', 'dj_name',
            'agent_name', 'coach_name', 'firm_name', 'org_name', 'product_name',
            'truck_name', 'tutor_name',
        ];
    }

    /** True when the answers contain at least one usable name field. */
    public static function hasName(array $answers): bool
    {
        foreach (self::nameKeys() as $k) {
            if (!empty($answers[$k])) return true;
        }
        return false;
    }

    /** First non-empty name field, or a friendly default. */
    public static function resolveTitle(array $answers): string
    {
        foreach (self::nameKeys() as $k) {
            if (!empty($answers[$k])) return (string) $answers[$k];
        }
        return 'My Link in Bio';
    }

    /**
     * Sanitise a flat JSON answers map against the question set for a combo.
     *
     * Mirrors the per-type validation the web wizard applies in
     * BiolinkWizardController::collectAnswers() for non-file fields. Used by
     * the mobile API wizard, which submits every answer as JSON — including
     * image fields, which here accept a URL string (file uploads remain a
     * web-only nicety). Unknown keys are dropped; values that fail their
     * type's validation are silently skipped, matching the web behaviour.
     */
    public static function sanitizeAnswers(string $category, string $pageType, ?string $industry, array $raw): array
    {
        $questions = self::questions($category, $pageType, $industry);
        $out = [];

        foreach ($questions as $q) {
            $key  = $q['key'];
            $type = $q['type'] ?? 'text';

            if (!array_key_exists($key, $raw)) continue;
            $val = $raw[$key];
            if (!is_string($val)) continue;
            $val = trim($val);
            if ($val === '') continue;

            switch ($type) {
                case 'url':
                    if (!preg_match('#^https?://#i', $val)) {
                        $val = 'https://' . ltrim($val, '/');
                    }
                    if (!filter_var($val, FILTER_VALIDATE_URL)) continue 2;
                    $val = mb_substr($val, 0, 2048);
                    break;
                case 'email':
                    if (!filter_var($val, FILTER_VALIDATE_EMAIL)) continue 2;
                    $val = mb_substr($val, 0, 255);
                    break;
                case 'color':
                    if (!preg_match('/^#[0-9a-f]{3,8}$/i', $val)) continue 2;
                    break;
                case 'phone':
                    $val = mb_substr(preg_replace('/[^\d+\s\-()]/', '', $val), 0, 30);
                    break;
                case 'select':
                    $opts = array_column($q['options'] ?? [], 'v');
                    if (!in_array($val, $opts, true)) continue 2;
                    break;
                case 'image':
                    // Accept a URL string; uploads are web-only.
                    $val = mb_substr($val, 0, 2048);
                    break;
                case 'textarea':
                    $val = mb_substr($val, 0, 2000);
                    break;
                default:
                    $val = mb_substr($val, 0, 500);
            }

            $out[$key] = $val;
        }

        return $out;
    }
}
