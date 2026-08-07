<?php

namespace App\Services;

/**
 * Task #6767 — Industry benchmark presets for the Marketing Plan Calculator.
 *
 * Curated per-vertical starting benchmarks layered as overrides on top of
 * the generic 16-channel table in {@see MarketingPlanDefaults::CHANNELS}.
 * Each preset re-weights channel allocations (the 15 paid channels always
 * sum to 100%), adjusts cost-per-visitor and conversion rates where the
 * vertical meaningfully differs, and sets a vertical-typical average
 * customer value. Static, curated tables only — no live market data.
 */
class MarketingPlanIndustryPresets
{
    /** Payload key that remembers which preset a plan started from. */
    public const PAYLOAD_KEY = 'industry_preset';

    /** The "no preset" key — the generic spreadsheet defaults. */
    public const GENERIC = 'generic';

    /**
     * Per-vertical presets. `acv` is the vertical's default average
     * customer value (INR) applied to every channel row; `channels` holds
     * per-channel overrides (alloc / cpv / vl / lc / acv / notes) merged
     * over the generic table. Channels not listed keep their generic
     * benchmark numbers (with the preset ACV applied).
     *
     * @var array<string,array<string,mixed>>
     */
    public const PRESETS = [
        'ecommerce' => [
            'label'       => 'E-commerce / D2C',
            'description' => 'Online stores & direct-to-consumer brands — social + retail media heavy, low order values, fast purchase cycles.',
            'acv'         => 6000,
            'channels'    => [
                'sayzio'     => ['vl' => 6.0, 'lc' => 18.0, 'notes' => 'Bio-page, QR & short links driving shoppers straight to product pages'],
                'instagram'  => ['alloc' => 14.0, 'cpv' => 25, 'vl' => 3.0, 'lc' => 8.0, 'notes' => 'Reels & shopping ads — core discovery channel for D2C'],
                'facebook'   => ['alloc' => 12.0, 'cpv' => 30, 'vl' => 3.0, 'lc' => 9.0, 'notes' => 'Catalog & retargeting ads — strongest ROAS lever for stores'],
                'whatsapp'   => ['alloc' => 3.0,  'cpv' => 20, 'vl' => 8.0, 'lc' => 20.0, 'notes' => 'Abandoned-cart nudges & order updates'],
                'twitter'    => ['alloc' => 1.0,  'cpv' => 60, 'vl' => 1.5, 'lc' => 5.0],
                'linkedin'   => ['alloc' => 1.0,  'cpv' => 150, 'vl' => 2.0, 'lc' => 6.0],
                'gdisplay'   => ['alloc' => 4.0,  'cpv' => 25, 'vl' => 2.0, 'lc' => 6.0, 'notes' => 'Dynamic remarketing across the Display Network'],
                'youtube'    => ['alloc' => 5.0,  'cpv' => 40, 'vl' => 1.5, 'lc' => 6.0],
                'amazon'     => ['alloc' => 10.0, 'cpv' => 45, 'vl' => 8.0, 'lc' => 12.0, 'notes' => 'Sponsored products — shoppers already in buying mode'],
                'othersoc'   => ['alloc' => 2.0,  'cpv' => 35, 'vl' => 2.0, 'lc' => 6.0, 'notes' => 'Pinterest & Snapchat — visual product discovery'],
                'search'     => ['alloc' => 16.0, 'cpv' => 55, 'vl' => 4.0, 'lc' => 12.0, 'notes' => 'Shopping + brand & category search terms'],
                'seo'        => ['alloc' => 12.0, 'cpv' => 12, 'vl' => 2.0, 'lc' => 8.0, 'notes' => 'Category & product content — compounding organic traffic'],
                'email'      => ['alloc' => 9.0,  'cpv' => 4,  'vl' => 4.0, 'lc' => 10.0, 'notes' => 'Lifecycle flows: welcome, cart, win-back'],
                'influencer' => ['alloc' => 8.0,  'cpv' => 45, 'vl' => 3.0, 'lc' => 8.0, 'notes' => 'Creator seeding & affiliate codes'],
                'events'     => ['alloc' => 1.0,  'cpv' => 400, 'vl' => 10.0, 'lc' => 15.0, 'notes' => 'Pop-ups & marketplace fairs — small share for most stores'],
                'pr'         => ['alloc' => 2.0,  'cpv' => 60, 'vl' => 2.0, 'lc' => 6.0],
            ],
        ],
        'b2b_saas' => [
            'label'       => 'B2B SaaS',
            'description' => 'Software sold to businesses — LinkedIn, search, content & webinars; long cycles, high contract values.',
            'acv'         => 400000,
            'channels'    => [
                'sayzio'     => ['vl' => 6.0, 'lc' => 20.0, 'notes' => 'Bio-page & QR on decks, signatures and event badges'],
                'instagram'  => ['alloc' => 2.0,  'cpv' => 900,  'vl' => 3.0, 'lc' => 10.0],
                'facebook'   => ['alloc' => 2.0,  'cpv' => 950,  'vl' => 3.0, 'lc' => 12.0, 'notes' => 'Mostly retargeting — rarely a primary B2B channel'],
                'whatsapp'   => ['alloc' => 1.0,  'cpv' => 600,  'vl' => 10.0, 'lc' => 25.0],
                'twitter'    => ['alloc' => 3.0,  'cpv' => 1100, 'vl' => 4.0, 'lc' => 14.0, 'notes' => 'Founder-led + developer audiences'],
                'linkedin'   => ['alloc' => 18.0, 'cpv' => 2400, 'vl' => 12.0, 'lc' => 30.0, 'notes' => 'Primary paid channel — precise firmographic targeting'],
                'gdisplay'   => ['alloc' => 3.0,  'cpv' => 800,  'vl' => 4.0, 'lc' => 14.0],
                'youtube'    => ['alloc' => 4.0,  'cpv' => 1200, 'vl' => 4.0, 'lc' => 15.0, 'notes' => 'Product demos & thought-leadership video'],
                'amazon'     => ['alloc' => 0.0,  'vl' => 5.0,  'lc' => 15.0, 'notes' => 'Rarely relevant for B2B SaaS'],
                'othersoc'   => ['alloc' => 1.0,  'cpv' => 900,  'vl' => 4.0, 'lc' => 12.0, 'notes' => 'Reddit & niche communities'],
                'search'     => ['alloc' => 20.0, 'cpv' => 2000, 'vl' => 10.0, 'lc' => 28.0, 'notes' => 'High-intent category & competitor terms'],
                'seo'        => ['alloc' => 18.0, 'cpv' => 200,  'vl' => 5.0, 'lc' => 22.0, 'notes' => 'Pillar content, comparisons, integrations pages'],
                'email'      => ['alloc' => 8.0,  'cpv' => 50,   'vl' => 6.0, 'lc' => 18.0, 'notes' => 'Nurture sequences & product-led lifecycle'],
                'influencer' => ['alloc' => 3.0,  'cpv' => 900,  'vl' => 5.0, 'lc' => 16.0, 'notes' => 'Industry analysts & niche newsletters'],
                'events'     => ['alloc' => 12.0, 'cpv' => 2800, 'vl' => 22.0, 'lc' => 35.0, 'notes' => 'Webinars, conferences & field events — highest close rate'],
                'pr'         => ['alloc' => 5.0,  'cpv' => 400,  'vl' => 5.0, 'lc' => 16.0],
            ],
        ],
        'local_services' => [
            'label'       => 'Local business / services',
            'description' => 'Salons, clinics, repair, home services — local search, maps & WhatsApp; leads close over the phone.',
            'acv'         => 25000,
            'channels'    => [
                'sayzio'     => ['vl' => 8.0, 'lc' => 25.0, 'notes' => 'QR on storefront & flyers, bio-page as the mini-website'],
                'instagram'  => ['alloc' => 10.0, 'cpv' => 60,  'vl' => 5.0, 'lc' => 18.0, 'notes' => 'Before/after content & local awareness'],
                'facebook'   => ['alloc' => 12.0, 'cpv' => 55,  'vl' => 6.0, 'lc' => 20.0, 'notes' => 'Local awareness + lead forms'],
                'whatsapp'   => ['alloc' => 8.0,  'cpv' => 30,  'vl' => 15.0, 'lc' => 35.0, 'notes' => 'Bookings & quote requests — highest-intent channel'],
                'twitter'    => ['alloc' => 0.5,  'cpv' => 120, 'vl' => 2.0, 'lc' => 10.0],
                'linkedin'   => ['alloc' => 1.0,  'cpv' => 400, 'vl' => 4.0, 'lc' => 15.0],
                'gdisplay'   => ['alloc' => 5.0,  'cpv' => 45,  'vl' => 3.0, 'lc' => 12.0],
                'youtube'    => ['alloc' => 2.0,  'cpv' => 90,  'vl' => 3.0, 'lc' => 12.0],
                'amazon'     => ['alloc' => 0.0,  'vl' => 5.0,  'lc' => 15.0, 'notes' => 'Not relevant for local services'],
                'othersoc'   => ['alloc' => 0.5,  'cpv' => 70,  'vl' => 3.0, 'lc' => 12.0],
                'search'     => ['alloc' => 22.0, 'cpv' => 90,  'vl' => 12.0, 'lc' => 30.0, 'notes' => '"Near me" & service-intent searches — best close rate'],
                'seo'        => ['alloc' => 14.0, 'cpv' => 20,  'vl' => 6.0, 'lc' => 22.0, 'notes' => 'Google Business Profile, reviews & local landing pages'],
                'email'      => ['alloc' => 8.0,  'cpv' => 6,   'vl' => 5.0, 'lc' => 15.0, 'notes' => 'Repeat-visit reminders & offers'],
                'influencer' => ['alloc' => 4.0,  'cpv' => 70,  'vl' => 4.0, 'lc' => 15.0, 'notes' => 'Local micro-influencers'],
                'events'     => ['alloc' => 8.0,  'cpv' => 300, 'vl' => 20.0, 'lc' => 35.0, 'notes' => 'Community events & local sponsorships'],
                'pr'         => ['alloc' => 5.0,  'cpv' => 60,  'vl' => 4.0, 'lc' => 15.0, 'notes' => 'Local press & directories'],
            ],
        ],
        'real_estate' => [
            'label'       => 'Real estate',
            'description' => 'Brokers & developers — portals, search & site visits; very high ticket, low conversion rates.',
            'acv'         => 250000,
            'channels'    => [
                'sayzio'     => ['vl' => 4.0, 'lc' => 8.0, 'notes' => 'QR on hoardings & brochures linking to project pages'],
                'instagram'  => ['alloc' => 12.0, 'cpv' => 90,  'vl' => 3.0, 'lc' => 4.0, 'notes' => 'Walkthrough reels & project launches'],
                'facebook'   => ['alloc' => 14.0, 'cpv' => 80,  'vl' => 4.0, 'lc' => 5.0, 'notes' => 'Lead-form campaigns by locality & budget'],
                'whatsapp'   => ['alloc' => 6.0,  'cpv' => 50,  'vl' => 10.0, 'lc' => 8.0, 'notes' => 'Site-visit scheduling & brochure sharing'],
                'twitter'    => ['alloc' => 1.0,  'cpv' => 150, 'vl' => 2.0, 'lc' => 3.0],
                'linkedin'   => ['alloc' => 2.0,  'cpv' => 500, 'vl' => 4.0, 'lc' => 6.0, 'notes' => 'NRI & commercial investors'],
                'gdisplay'   => ['alloc' => 6.0,  'cpv' => 60,  'vl' => 3.0, 'lc' => 4.0],
                'youtube'    => ['alloc' => 8.0,  'cpv' => 100, 'vl' => 3.0, 'lc' => 4.0, 'notes' => 'Project walkthroughs & locality guides'],
                'amazon'     => ['alloc' => 0.0,  'vl' => 3.0, 'lc' => 4.0, 'notes' => 'Not relevant for real estate'],
                'othersoc'   => ['alloc' => 1.0,  'cpv' => 90,  'vl' => 2.0, 'lc' => 3.0],
                'search'     => ['alloc' => 18.0, 'cpv' => 180, 'vl' => 8.0, 'lc' => 6.0, 'notes' => 'Project & locality searches — highest intent'],
                'seo'        => ['alloc' => 12.0, 'cpv' => 30,  'vl' => 4.0, 'lc' => 5.0, 'notes' => 'Locality pages & buying guides'],
                'email'      => ['alloc' => 6.0,  'cpv' => 10,  'vl' => 4.0, 'lc' => 5.0, 'notes' => 'New-launch & price-update nurture'],
                'influencer' => ['alloc' => 3.0,  'cpv' => 100, 'vl' => 3.0, 'lc' => 4.0],
                'events'     => ['alloc' => 8.0,  'cpv' => 600, 'vl' => 25.0, 'lc' => 10.0, 'notes' => 'Site visits & property expos — where deals close'],
                'pr'         => ['alloc' => 3.0,  'cpv' => 80,  'vl' => 3.0, 'lc' => 4.0],
            ],
        ],
        'education' => [
            'label'       => 'Education',
            'description' => 'Courses, coaching & ed-tech — search, YouTube & counselling funnels; seasonal admission cycles.',
            'acv'         => 80000,
            'channels'    => [
                'sayzio'     => ['vl' => 6.0, 'lc' => 15.0, 'notes' => 'Bio-page linking course catalog, QR on prospectus'],
                'instagram'  => ['alloc' => 10.0, 'cpv' => 50,  'vl' => 4.0, 'lc' => 10.0, 'notes' => 'Student stories & campus content'],
                'facebook'   => ['alloc' => 8.0,  'cpv' => 55,  'vl' => 5.0, 'lc' => 12.0, 'notes' => 'Parent-facing campaigns & lead forms'],
                'whatsapp'   => ['alloc' => 5.0,  'cpv' => 30,  'vl' => 12.0, 'lc' => 22.0, 'notes' => 'Counselling & admission follow-ups'],
                'twitter'    => ['alloc' => 1.0,  'cpv' => 110, 'vl' => 2.0, 'lc' => 8.0],
                'linkedin'   => ['alloc' => 4.0,  'cpv' => 350, 'vl' => 6.0, 'lc' => 15.0, 'notes' => 'Executive & upskilling programs'],
                'gdisplay'   => ['alloc' => 4.0,  'cpv' => 45,  'vl' => 3.0, 'lc' => 10.0],
                'youtube'    => ['alloc' => 10.0, 'cpv' => 60,  'vl' => 4.0, 'lc' => 10.0, 'notes' => 'Demo lectures & faculty content — key trust builder'],
                'amazon'     => ['alloc' => 0.0,  'vl' => 4.0, 'lc' => 10.0, 'notes' => 'Rarely relevant for education'],
                'othersoc'   => ['alloc' => 1.0,  'cpv' => 60,  'vl' => 3.0, 'lc' => 8.0],
                'search'     => ['alloc' => 16.0, 'cpv' => 130, 'vl' => 10.0, 'lc' => 18.0, 'notes' => 'Course & exam-intent searches'],
                'seo'        => ['alloc' => 16.0, 'cpv' => 18,  'vl' => 5.0, 'lc' => 14.0, 'notes' => 'Exam guides, syllabus & career content'],
                'email'      => ['alloc' => 10.0, 'cpv' => 5,   'vl' => 6.0, 'lc' => 12.0, 'notes' => 'Admission-cycle nurture & webinars invites'],
                'influencer' => ['alloc' => 5.0,  'cpv' => 55,  'vl' => 4.0, 'lc' => 10.0, 'notes' => 'Educator-creators & alumni'],
                'events'     => ['alloc' => 8.0,  'cpv' => 500, 'vl' => 25.0, 'lc' => 25.0, 'notes' => 'Open days, seminars & counselling fairs'],
                'pr'         => ['alloc' => 2.0,  'cpv' => 70,  'vl' => 3.0, 'lc' => 10.0],
            ],
        ],
        'hospitality' => [
            'label'       => 'Hospitality / restaurant',
            'description' => 'Restaurants, cafés & hotels — Instagram-first, local discovery & repeat visits; small ticket sizes.',
            'acv'         => 4000,
            'channels'    => [
                'sayzio'     => ['vl' => 10.0, 'lc' => 30.0, 'notes' => 'QR menus, table tents & bio-page with reservations'],
                'instagram'  => ['alloc' => 18.0, 'cpv' => 30, 'vl' => 4.0, 'lc' => 15.0, 'notes' => 'Food & ambience reels — the primary discovery channel'],
                'facebook'   => ['alloc' => 12.0, 'cpv' => 35, 'vl' => 4.0, 'lc' => 15.0, 'notes' => 'Local events, offers & group targeting'],
                'whatsapp'   => ['alloc' => 6.0,  'cpv' => 15, 'vl' => 15.0, 'lc' => 35.0, 'notes' => 'Reservations & repeat-order broadcasts'],
                'twitter'    => ['alloc' => 1.0,  'cpv' => 70, 'vl' => 2.0, 'lc' => 8.0],
                'linkedin'   => ['alloc' => 0.5,  'cpv' => 300, 'vl' => 3.0, 'lc' => 12.0, 'notes' => 'Corporate catering & events only'],
                'gdisplay'   => ['alloc' => 4.0,  'cpv' => 30, 'vl' => 2.0, 'lc' => 10.0],
                'youtube'    => ['alloc' => 4.0,  'cpv' => 50, 'vl' => 2.0, 'lc' => 10.0],
                'amazon'     => ['alloc' => 0.0,  'vl' => 4.0, 'lc' => 12.0, 'notes' => 'Not relevant for hospitality'],
                'othersoc'   => ['alloc' => 3.5,  'cpv' => 35, 'vl' => 3.0, 'lc' => 10.0, 'notes' => 'Zomato/Swiggy ads & Snapchat'],
                'search'     => ['alloc' => 14.0, 'cpv' => 60, 'vl' => 8.0, 'lc' => 25.0, 'notes' => '"Restaurants near me" & booking searches'],
                'seo'        => ['alloc' => 10.0, 'cpv' => 12, 'vl' => 4.0, 'lc' => 18.0, 'notes' => 'Google Business Profile & review presence'],
                'email'      => ['alloc' => 8.0,  'cpv' => 3,  'vl' => 5.0, 'lc' => 20.0, 'notes' => 'Loyalty offers & event invites'],
                'influencer' => ['alloc' => 12.0, 'cpv' => 35, 'vl' => 4.0, 'lc' => 12.0, 'notes' => 'Food bloggers & local creators — high impact'],
                'events'     => ['alloc' => 5.0,  'cpv' => 250, 'vl' => 20.0, 'lc' => 30.0, 'notes' => 'Tastings, live nights & festivals'],
                'pr'         => ['alloc' => 2.0,  'cpv' => 45, 'vl' => 3.0, 'lc' => 12.0, 'notes' => 'Food-media listings & reviews'],
            ],
        ],
        'healthcare' => [
            'label'       => 'Healthcare',
            'description' => 'Clinics, hospitals & wellness — trust-led search, content & PR; ad platforms restrict targeting.',
            'acv'         => 40000,
            'channels'    => [
                'sayzio'     => ['vl' => 7.0, 'lc' => 22.0, 'notes' => 'Bio-page with appointment booking, QR at reception'],
                'instagram'  => ['alloc' => 6.0,  'cpv' => 70,  'vl' => 4.0, 'lc' => 14.0, 'notes' => 'Doctor Q&As & wellness content (ad policies limit targeting)'],
                'facebook'   => ['alloc' => 8.0,  'cpv' => 65,  'vl' => 5.0, 'lc' => 16.0, 'notes' => 'Awareness & health-camp promotion'],
                'whatsapp'   => ['alloc' => 4.0,  'cpv' => 35,  'vl' => 14.0, 'lc' => 30.0, 'notes' => 'Appointment booking & reports follow-up'],
                'twitter'    => ['alloc' => 1.0,  'cpv' => 130, 'vl' => 2.0, 'lc' => 10.0],
                'linkedin'   => ['alloc' => 3.0,  'cpv' => 450, 'vl' => 5.0, 'lc' => 18.0, 'notes' => 'Corporate health tie-ups & B2B referrals'],
                'gdisplay'   => ['alloc' => 4.0,  'cpv' => 55,  'vl' => 3.0, 'lc' => 12.0],
                'youtube'    => ['alloc' => 4.0,  'cpv' => 80,  'vl' => 3.0, 'lc' => 12.0, 'notes' => 'Patient education & doctor introductions'],
                'amazon'     => ['alloc' => 0.0,  'vl' => 4.0, 'lc' => 12.0, 'notes' => 'Not relevant for care providers'],
                'othersoc'   => ['alloc' => 1.0,  'cpv' => 75,  'vl' => 3.0, 'lc' => 10.0, 'notes' => 'Practo & health-platform listings'],
                'search'     => ['alloc' => 20.0, 'cpv' => 150, 'vl' => 12.0, 'lc' => 28.0, 'notes' => 'Symptom, specialty & doctor searches — highest intent'],
                'seo'        => ['alloc' => 18.0, 'cpv' => 22,  'vl' => 6.0, 'lc' => 20.0, 'notes' => 'Condition guides & doctor profiles — trust compounding'],
                'email'      => ['alloc' => 8.0,  'cpv' => 6,   'vl' => 6.0, 'lc' => 16.0, 'notes' => 'Checkup reminders & health newsletters'],
                'influencer' => ['alloc' => 2.0,  'cpv' => 80,  'vl' => 3.0, 'lc' => 10.0, 'notes' => 'Doctor-creators only — compliance sensitive'],
                'events'     => ['alloc' => 6.0,  'cpv' => 350, 'vl' => 22.0, 'lc' => 30.0, 'notes' => 'Health camps & screening drives'],
                'pr'         => ['alloc' => 15.0, 'cpv' => 50,  'vl' => 5.0, 'lc' => 16.0, 'notes' => 'Medical press, accreditations & doctor citations — key trust channel'],
            ],
        ],
    ];

    /** Whether a preset key exists (generic included). */
    public static function exists(?string $key): bool
    {
        return $key === self::GENERIC || isset(self::PRESETS[$key]);
    }

    /** Human label for a stored preset key — unknown/missing keys read "Custom". */
    public static function label(?string $key): string
    {
        if ($key === self::GENERIC) return 'Generic';

        return isset(self::PRESETS[$key]) ? self::PRESETS[$key]['label'] : 'Custom';
    }

    /**
     * The full 16-row channel table for a preset (generic table with the
     * vertical's overrides + default ACV applied).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function channels(string $key): array
    {
        $base = MarketingPlanDefaults::CHANNELS;
        if (!isset(self::PRESETS[$key])) return $base;

        $preset = self::PRESETS[$key];

        return array_map(function (array $row) use ($preset) {
            $row['acv'] = $preset['acv'];
            $over = $preset['channels'][$row['key']] ?? [];
            foreach (['alloc', 'cpv', 'vl', 'lc', 'acv', 'notes'] as $f) {
                if (array_key_exists($f, $over)) $row[$f] = $over[$f];
            }
            return $row;
        }, $base);
    }

    /**
     * Apply a preset onto an existing assumptions payload — replaces the
     * channel table and stamps the preset key. Everything else (budget,
     * weights, tools…) is left untouched.
     *
     * @param  array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public static function apply(array $payload, string $key): array
    {
        if (!self::exists($key)) return $payload;

        $payload['channels']       = self::channels($key);
        $payload[self::PAYLOAD_KEY] = $key;

        return $payload;
    }

    /**
     * Client-side catalog for the editor's preset picker: every preset
     * (generic first) with its label, description and full channel table.
     *
     * @return array<string,array{label:string,description:string,channels:array<int,array<string,mixed>>}>
     */
    public static function forClient(): array
    {
        $out = [
            self::GENERIC => [
                'label'       => 'Generic',
                'description' => 'The default cross-industry benchmark table.',
                'channels'    => MarketingPlanDefaults::CHANNELS,
            ],
        ];
        foreach (self::PRESETS as $key => $preset) {
            $out[$key] = [
                'label'       => $preset['label'],
                'description' => $preset['description'],
                'channels'    => self::channels($key),
            ];
        }
        return $out;
    }
}
