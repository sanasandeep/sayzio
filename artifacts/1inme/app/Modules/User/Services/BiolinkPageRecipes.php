<?php

namespace App\Modules\User\Services;

/**
 * Turns the wizard's flat `answers` array into a TemplateService-compatible
 * snapshot:
 *
 *   [
 *     'biolink' => [...page-level settings (avatar, brand colour, headline)],
 *     'blocks'  => [
 *        ['type'=>'profile_card_v1', 'settings'=>[...], 'is_active'=>true, 'children'=>[]],
 *        ...
 *     ],
 *   ]
 *
 * Recipes are intentionally tolerant — every block helper checks whether the
 * relevant answer is present and silently skips itself when it isn't, so a
 * user who only fills in 2 fields still gets a coherent page (just shorter).
 */
class BiolinkPageRecipes
{
    /**
     * Build the snapshot for a (category, page_type, industry, answers) tuple.
     */
    public static function build(string $category, string $pageType, ?string $industry, array $answers): array
    {
        $a = self::normalise($answers);
        $brand = $a['brand_color'] ?? BiolinkWizardQuestions::defaultBrandColor($category);

        $biolink = [
            'theme_color'      => $brand,
            'background_type'  => 'solid',
            'background_color' => '#0d0818',
        ];

        // Avatar URL — use uploaded image if any, otherwise a category-themed
        // placeholder served by WizardPlaceholderController.
        $avatarUrl = self::resolveAvatar($a, $category, $industry);

        $blocks = [];
        $blocks[] = self::profileBlock($category, $pageType, $a, $avatarUrl, $brand);

        // Category-specific extras (run before generic helpers so they appear higher).
        $extra = self::extrasFor($category, $pageType, $industry, $a, $brand);
        foreach ($extra as $b) {
            if ($b !== null) $blocks[] = $b;
        }

        // Generic, end-of-page helpers (socials, contact form fallback).
        if ($social = self::socialsBlock($a)) $blocks[] = $social;
        if ($contact = self::contactBlock($category, $pageType, $a, $brand)) $blocks[] = $contact;

        // Re-index sort_order implicitly — the snapshot is consumed by
        // TemplateService::insertBlockTree which uses its own counter.
        return [
            'biolink' => $biolink,
            'blocks'  => array_values($blocks),
        ];
    }

    /** Normalise answers: trim strings, drop empty keys. */
    protected static function normalise(array $answers): array
    {
        $out = [];
        foreach ($answers as $k => $v) {
            if (is_string($v)) {
                $v = trim($v);
                if ($v === '') continue;
            } elseif ($v === null) {
                continue;
            }
            $out[$k] = $v;
        }
        return $out;
    }

    protected static function resolveAvatar(array $a, string $category, ?string $industry): string
    {
        if (!empty($a['avatar']) && is_string($a['avatar']) && preg_match('#^https?://#i', $a['avatar'])) {
            return $a['avatar'];
        }
        // Local placeholder route — see BiolinkWizardController::placeholder.
        $slug = $industry ?: $category;
        return url("/wizard-placeholders/{$slug}.svg");
    }

    /* ────────────────────────────────────────────────────────────── *
     *  Generic blocks
     * ────────────────────────────────────────────────────────────── */

    protected static function profileBlock(string $cat, string $pt, array $a, string $avatar, string $brand): array
    {
        $name = $a['display_name']
             ?? $a['business_name']
             ?? $a['venue_name']
             ?? $a['agent_name']
             ?? $a['coach_name']
             ?? $a['artist_name']
             ?? $a['band_name']
             ?? $a['dj_name']
             ?? $a['firm_name']
             ?? $a['org_name']
             ?? $a['product_name']
             ?? $a['agency_name']
             ?? $a['truck_name']
             ?? $a['couple']
             ?? $a['event_name']
             ?? $a['tutor_name']
             ?? $a['store_name']
             ?? 'Welcome';

        $headline = $a['headline']
                 ?? $a['tagline']
                 ?? $a['specialty']
                 ?? $a['niche']
                 ?? $a['cuisine']
                 ?? $a['date_range']
                 ?? $a['date']
                 ?? '';

        $bio = $a['bio'] ?? $a['mission'] ?? $a['instructor_bio'] ?? '';

        return [
            'type' => 'profile_card_v1',
            'settings' => [
                // Canonical profile_card_v1 keys: name, title, avatar, bio,
                // socials. We emit `title` (the live key used by the
                // renderer) and keep `headline` as a backwards-compatible
                // alias for any older sanitizers that still read it.
                'avatar'       => $avatar,
                'name'         => $name,
                'title'        => $headline,
                'headline'     => $headline,
                'bio'          => $bio,
                'name_color'   => '#ffffff',
                'accent_color' => $brand,
                'socials'      => [],
            ],
            'is_active' => true,
        ];
    }

    protected static function socialsBlock(array $a): ?array
    {
        $items = [];
        $map = [
            'instagram' => 'instagram',
            'tiktok'    => 'tiktok',
            'youtube'   => 'youtube',
            'twitter'   => 'twitter',
            'github'    => 'github',
            'linkedin'  => 'linkedin',
            'spotify'   => 'spotify',
            'soundcloud'=> 'soundcloud',
            'mixcloud'  => 'mixcloud',
            'apple_music' => 'apple-music',
            'apple'     => 'apple-podcasts',
            'medium'    => 'medium',
            'twitch'    => 'twitch',
            'discord'   => 'discord',
            'behance'   => 'behance',
            'dribbble'  => 'dribbble',
        ];
        foreach ($map as $key => $platform) {
            if (empty($a[$key])) continue;
            $val = (string) $a[$key];
            $url = self::socialUrl($platform, $val);
            if (!$url) continue;
            // Canonical socials sanitizer expects `platforms[*].{name,url}`
            // with `display = icon|follow|follow_count`.
            $items[] = [
                'name'    => $platform,
                'url'     => $url,
                'display' => 'icon',
            ];
        }
        if (count($items) < 1) return null;
        return [
            'type' => 'socials',
            'settings' => [
                'platforms' => $items,
                'shape'     => 'circle',
                'size'      => 'md',
                'align'     => 'center',
            ],
            'is_active' => true,
        ];
    }

    protected static function socialUrl(string $platform, string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') return null;
        if (preg_match('#^https?://#i', $raw)) return $raw;
        $handle = ltrim($raw, '@/');
        return match ($platform) {
            'instagram'      => "https://instagram.com/{$handle}",
            'tiktok'         => "https://tiktok.com/@{$handle}",
            'twitter'        => "https://x.com/{$handle}",
            'github'         => "https://github.com/{$handle}",
            'linkedin'       => str_starts_with($handle, 'in/') || str_starts_with($handle, 'company/')
                                ? "https://linkedin.com/{$handle}"
                                : "https://linkedin.com/in/{$handle}",
            'youtube'        => "https://youtube.com/@{$handle}",
            'twitch'         => "https://twitch.tv/{$handle}",
            'medium'         => "https://medium.com/@{$handle}",
            'behance'        => "https://behance.net/{$handle}",
            'dribbble'       => "https://dribbble.com/{$handle}",
            'spotify',
            'soundcloud',
            'mixcloud',
            'apple-music',
            'apple-podcasts',
            'discord'        => null, // these need a full URL — drop bare handles
            default          => null,
        };
    }

    protected static function contactBlock(string $cat, string $pt, array $a, string $brand): ?array
    {
        // Skip if the page already has a stronger CTA (booking form / signup).
        $email = $a['email'] ?? $a['contact_email'] ?? $a['booking_email']
              ?? $a['mgmt_email'] ?? $a['catering_email'] ?? $a['sponsor_email']
              ?? $a['collab_email'] ?? $a['speaking_email'] ?? $a['commissions_email']
              ?? $a['host_email'] ?? null;
        if (!$email) return null;
        return [
            'type' => 'contact_form',
            'settings' => [
                'title'         => 'Get in touch',
                'subject_label' => 'How can we help?',
                'submit_label'  => 'Send message',
                'recipient_email' => $email,
                'fields' => [
                    ['name' => 'name',    'label' => 'Your name',  'type' => 'text',     'required' => true],
                    ['name' => 'email',   'label' => 'Your email', 'type' => 'email',    'required' => true],
                    ['name' => 'message', 'label' => 'Message',    'type' => 'textarea', 'required' => true],
                ],
                'accent_color' => $brand,
            ],
            'is_active' => true,
        ];
    }

    /* ────────────────────────────────────────────────────────────── *
     *  Per-category recipes
     * ────────────────────────────────────────────────────────────── */

    /** @return array<int, array|null> */
    protected static function extrasFor(string $cat, string $pt, ?string $ind, array $a, string $brand): array
    {
        $extras = [];

        // Featured "headline" CTA button (works for nearly every recipe).
        if (!empty($a['cta_url']) && !empty($a['cta_label'])) {
            $extras[] = self::ctaBlock($a['cta_label'], $a['cta_url'], $brand);
        } elseif (!empty($a['featured_url'])) {
            $extras[] = self::ctaBlock($a['featured_label'] ?? '👉 Featured link', $a['featured_url'], $brand);
        } elseif (!empty($a['signup_url'])) {
            $extras[] = self::ctaBlock('Get started', $a['signup_url'], $brand);
        } elseif (!empty($a['tickets_url'])) {
            $extras[] = self::ctaBlock('🎟  Buy tickets', $a['tickets_url'], $brand);
        } elseif (!empty($a['rsvp_url'])) {
            $extras[] = self::ctaBlock('RSVP', $a['rsvp_url'], $brand);
        }

        switch ("{$cat}.{$pt}") {

            // ── Creator ─────────────────────────────────────────────
            case 'creator.youtuber':
                if (!empty($a['pinned_video'])) {
                    $extras[] = ['type' => 'youtube', 'settings' => ['url' => $a['pinned_video']], 'is_active' => true];
                }
                if (!empty($a['merch_url'])) {
                    $extras[] = self::linkButton('🛍  Merch store', $a['merch_url']);
                }
                break;

            case 'creator.podcaster':
                if (!empty($a['episode_url'])) {
                    $extras[] = self::linkButton('🎧 ' . ($a['episode_title'] ?? 'Latest episode'), $a['episode_url']);
                }
                if (!empty($a['guest_form'])) {
                    $extras[] = self::linkButton('Apply to be a guest', $a['guest_form']);
                }
                break;

            case 'creator.writer':
                if (!empty($a['newsletter_url'])) {
                    $extras[] = self::linkButton($a['newsletter_blurb'] ?? 'Subscribe to my newsletter', $a['newsletter_url']);
                }
                if (!empty($a['book_url'])) {
                    $extras[] = self::linkButton('📕 ' . ($a['book_title'] ?? 'Get the book'), $a['book_url']);
                }
                break;

            case 'creator.artist':
                if (!empty($a['shop_url']))    $extras[] = self::linkButton('🖼  Shop prints', $a['shop_url']);
                if (!empty($a['patreon_url'])) $extras[] = self::linkButton('Become a patron', $a['patreon_url']);
                if (!empty($a['commissions_open']) && $a['commissions_open'] === 'yes') {
                    $extras[] = self::alertBlock('Commissions are open — slots booking now.');
                } elseif (($a['commissions_open'] ?? null) === 'waitlist') {
                    $extras[] = self::alertBlock('Commissions are full — join the waitlist.');
                }
                break;

            // ── Business ────────────────────────────────────────────
            case 'business.local_shop':
                if (!empty($a['address'])) {
                    $extras[] = ['type' => 'map', 'settings' => ['address' => $a['address'], 'zoom' => 15], 'is_active' => true];
                }
                if (!empty($a['phone'])) {
                    $extras[] = self::linkButton('📞 Call ' . self::shortPhone($a['phone']), 'tel:' . self::digits($a['phone']));
                }
                if (!empty($a['whatsapp'])) {
                    $extras[] = self::linkButton('💬 WhatsApp us', 'https://wa.me/' . self::digits($a['whatsapp']));
                }
                if (!empty($a['hours'])) {
                    $extras[] = self::richText('🕒 Opening hours', $a['hours']);
                }
                if (!empty($a['review_blurb'])) {
                    $extras[] = self::badgeBlock($a['review_blurb']);
                }
                break;

            case 'business.online_store':
                $items = [];
                for ($i = 1; $i <= 3; $i++) {
                    if (!empty($a["best_seller_{$i}"]) && !empty($a["best_seller_{$i}_url"])) {
                        $items[] = self::linkButton('⭐ ' . $a["best_seller_{$i}"], $a["best_seller_{$i}_url"]);
                    }
                }
                $extras = array_merge($extras, $items);
                if (!empty($a['discount_code'])) {
                    $extras[] = self::alertBlock(($a['discount_blurb'] ?? 'Use code ') . ' — ' . $a['discount_code']);
                }
                if (!empty($a['newsletter_blurb'])) {
                    $extras[] = self::newsletterBlock($a['newsletter_blurb'], $brand);
                }
                break;

            case 'business.agency':
                $services = [];
                for ($i = 1; $i <= 3; $i++) {
                    if (!empty($a["service_{$i}"])) {
                        $services[] = [
                            'price'     => '',
                            'name'      => $a["service_{$i}"],
                            'description' => $a["service_{$i}_desc"] ?? '',
                        ];
                    }
                }
                if (!empty($services)) {
                    $extras[] = ['type' => 'list_pricing', 'settings' => ['title' => 'What we do', 'items' => $services], 'is_active' => true];
                }
                if (!empty($a['case_study_url']))   $extras[] = self::linkButton('📁 Featured case study', $a['case_study_url']);
                if (!empty($a['calendly_url']))     $extras[] = self::linkButton('📅 Book an intro call', $a['calendly_url']);
                break;

            case 'business.saas':
                $links = [];
                if (!empty($a['demo_url']))      $links[] = self::linkButton('🎬 Book a demo', $a['demo_url']);
                if (!empty($a['pricing_url']))   $links[] = self::linkButton('💰 Pricing', $a['pricing_url']);
                if (!empty($a['docs_url']))      $links[] = self::linkButton('📚 Docs', $a['docs_url']);
                if (!empty($a['changelog_url'])) $links[] = self::linkButton('📝 Changelog', $a['changelog_url']);
                $extras = array_merge($extras, $links);
                break;

            case 'business.nonprofit':
                if (!empty($a['donate_url'])) {
                    $extras[] = self::ctaBlock('💜 Donate', $a['donate_url'], $brand);
                }
                if (!empty($a['volunteer_form'])) {
                    $extras[] = self::linkButton('🙋 Volunteer with us', $a['volunteer_form']);
                }
                if (!empty($a['impact_blurb'])) {
                    $extras[] = self::badgeBlock($a['impact_blurb']);
                }
                break;

            // ── Restaurant ──────────────────────────────────────────
            case 'restaurant.restaurant':
            case 'restaurant.cafe':
            case 'restaurant.bar':
                if (!empty($a['menu_url']))     $extras[] = self::ctaBlock('📋 View menu', $a['menu_url'], $brand);
                if (!empty($a['reserve_url']))  $extras[] = self::linkButton('🍽  Reserve a table', $a['reserve_url']);
                if (!empty($a['order_url']))    $extras[] = self::linkButton('🛍  Order online', $a['order_url']);
                if (!empty($a['delivery_url'])) $extras[] = self::linkButton('🚚 Delivery', $a['delivery_url']);
                if (!empty($a['event_tonight'])) $extras[] = self::alertBlock($a['event_tonight']);
                if (!empty($a['hours']))        $extras[] = self::richText('🕒 Opening hours', $a['hours']);
                if (!empty($a['address']))      $extras[] = ['type' => 'map', 'settings' => ['address' => $a['address'], 'zoom' => 15], 'is_active' => true];
                if (!empty($a['phone']))        $extras[] = self::linkButton('📞 Call ' . self::shortPhone($a['phone']), 'tel:' . self::digits($a['phone']));
                if (!empty($a['review_url']))   $extras[] = self::linkButton('⭐ Read reviews', $a['review_url']);
                break;

            case 'restaurant.food_truck':
                if (!empty($a['today_spot'])) {
                    $extras[] = self::alertBlock('📍 Today: ' . $a['today_spot']
                        . (!empty($a['today_hours']) ? ' · ' . $a['today_hours'] : ''));
                }
                if (!empty($a['menu_url']))     $extras[] = self::ctaBlock('📋 Today\'s menu', $a['menu_url'], $brand);
                if (!empty($a['phone']))        $extras[] = self::linkButton('📞 Call ' . self::shortPhone($a['phone']), 'tel:' . self::digits($a['phone']));
                break;

            // ── Musician ────────────────────────────────────────────
            case 'musician.solo_artist':
            case 'musician.band':
            case 'musician.dj':
            case 'musician.classical':
                if (!empty($a['latest_release_url'])) {
                    $extras[] = self::ctaBlock('🎵 ' . ($a['latest_release_title'] ?? 'Latest release'), $a['latest_release_url'], $brand);
                }
                if (!empty($a['next_set']))     $extras[] = self::alertBlock('🔥 Next set: ' . $a['next_set']);
                if (!empty($a['next_concert'])) $extras[] = self::alertBlock('🎻 Next: ' . $a['next_concert']);
                if (!empty($a['tour_url']))     $extras[] = self::linkButton('📅 Tour dates', $a['tour_url']);
                if (!empty($a['merch_url']))    $extras[] = self::linkButton('🛍  Merch', $a['merch_url']);
                if (!empty($a['press_kit_url'])) $extras[] = self::linkButton('📁 Press kit', $a['press_kit_url']);
                if (!empty($a['recordings_url'])) $extras[] = self::linkButton('🎼 Recordings', $a['recordings_url']);
                if (!empty($a['members'])) $extras[] = self::richText('Band', $a['members']);
                break;

            // ── Real Estate ─────────────────────────────────────────
            case 'real_estate.residential':
            case 'real_estate.commercial':
            case 'real_estate.broker':
                if (!empty($a['featured_listing_url'])) {
                    $extras[] = self::ctaBlock('🏠 ' . ($a['featured_listing_title'] ?? 'Featured listing'), $a['featured_listing_url'], $brand);
                }
                if (!empty($a['listings_url']))    $extras[] = self::linkButton('🏘  All listings', $a['listings_url']);
                if (!empty($a['valuation_form_url'])) $extras[] = self::linkButton('💰 Free home valuation', $a['valuation_form_url']);
                if (!empty($a['calendly_url']))    $extras[] = self::linkButton('📅 Book a viewing', $a['calendly_url']);
                if (!empty($a['case_study_url'])) $extras[] = self::linkButton('📁 Case study', $a['case_study_url']);
                if (!empty($a['phone']))           $extras[] = self::linkButton('📞 Call ' . self::shortPhone($a['phone']), 'tel:' . self::digits($a['phone']));
                if (!empty($a['linkedin']))        $extras[] = self::linkButton('LinkedIn', self::socialUrl('linkedin', $a['linkedin']));
                if (!empty($a['service_area']))    $extras[] = self::badgeBlock('Serving ' . $a['service_area']);
                break;

            // ── Coach ───────────────────────────────────────────────
            case 'coach.fitness':
            case 'coach.life':
            case 'coach.business':
                $programs = [];
                for ($i = 1; $i <= 3; $i++) {
                    if (!empty($a["program_{$i}"])) {
                        $programs[] = [
                            'name'  => $a["program_{$i}"],
                            'price' => $a["program_{$i}_price"] ?? '',
                            'description' => '',
                        ];
                    }
                }
                if (!empty($a['mastermind_name'])) {
                    $programs[] = ['name' => $a['mastermind_name'], 'price' => $a['mastermind_price'] ?? '', 'description' => 'Mastermind / cohort'];
                }
                if (!empty($a['1to1_price'])) {
                    $programs[] = ['name' => '1:1 Coaching', 'price' => $a['1to1_price'], 'description' => ''];
                }
                if (!empty($programs)) {
                    $extras[] = ['type' => 'list_pricing', 'settings' => ['title' => 'Work with me', 'items' => $programs], 'is_active' => true];
                }
                if (!empty($a['free_intro_url']))  $extras[] = self::ctaBlock('🎁 Free intro session', $a['free_intro_url'], $brand);
                if (!empty($a['discovery_url']))   $extras[] = self::ctaBlock('☕ Free discovery call', $a['discovery_url'], $brand);
                if (!empty($a['application_url'])) $extras[] = self::ctaBlock('Apply now', $a['application_url'], $brand);
                if (!empty($a['testimonial'])) {
                    $extras[] = ['type' => 'testimonials', 'settings' => ['items' => [['quote' => $a['testimonial'], 'name' => $a['testimonial_name'] ?? 'Client']]], 'is_active' => true];
                }
                if (!empty($a['case_study_url'])) $extras[] = self::linkButton('📁 Case study', $a['case_study_url']);
                if (!empty($a['linkedin']))       $extras[] = self::linkButton('LinkedIn', self::socialUrl('linkedin', $a['linkedin']));
                break;

            case 'coach.tutor':
                if (!empty($a['subjects'])) $extras[] = self::badgeBlock('📚 ' . $a['subjects']);
                if (!empty($a['levels']))   $extras[] = self::richText('Levels', $a['levels']);
                if (!empty($a['price']))    $extras[] = self::badgeBlock($a['price']);
                if (!empty($a['booking_url'])) $extras[] = self::ctaBlock('📅 Book a session', $a['booking_url'], $brand);
                if (!empty($a['phone']))    $extras[] = self::linkButton('📞 Call ' . self::shortPhone($a['phone']), 'tel:' . self::digits($a['phone']));
                break;

            // ── Personal ────────────────────────────────────────────
            case 'personal.developer':
            case 'personal.designer':
            case 'personal.student':
            case 'personal.professional':
                $projects = [];
                for ($i = 1; $i <= 3; $i++) {
                    if (!empty($a["project_{$i}"]) && !empty($a["project_{$i}_url"])) {
                        $projects[] = self::linkButton('💼 ' . $a["project_{$i}"], $a["project_{$i}_url"]);
                    } elseif (!empty($a["project_{$i}_url"])) {
                        $projects[] = self::linkButton('💼 Featured project', $a["project_{$i}_url"]);
                    }
                }
                $extras = array_merge($extras, $projects);
                if (!empty($a['portfolio_url'])) $extras[] = self::ctaBlock('🎨 Portfolio', $a['portfolio_url'], $brand);
                if (!empty($a['blog_url']))      $extras[] = self::linkButton('✍ Blog', $a['blog_url']);
                if (!empty($a['cv_url']))        $extras[] = self::linkButton('📄 CV / Resume', $a['cv_url']);
                if (!empty($a['available_for'])) $extras[] = self::badgeBlock($a['available_for']);
                if (!empty($a['speaking_blurb']))$extras[] = self::richText('Speaking', $a['speaking_blurb']);
                break;

            // ── Event ───────────────────────────────────────────────
            case 'event.wedding':
                if (!empty($a['date']))         $extras[] = self::badgeBlock('💍 ' . $a['date']);
                if (!empty($a['venue']))        $extras[] = self::richText('Venue', $a['venue'] . (!empty($a['venue_address']) ? "\n" . $a['venue_address'] : ''));
                if (!empty($a['venue_address']))$extras[] = ['type' => 'map', 'settings' => ['address' => $a['venue_address']], 'is_active' => true];
                if (!empty($a['schedule']))     $extras[] = self::richText('Schedule', $a['schedule']);
                if (!empty($a['rsvp_url']))     $extras[] = self::ctaBlock('RSVP', $a['rsvp_url'], $brand);
                if (!empty($a['registry_url'])) $extras[] = self::linkButton('🎁 Registry', $a['registry_url']);
                if (!empty($a['dress_code']))   $extras[] = self::badgeBlock('Dress code: ' . $a['dress_code']);
                if (!empty($a['hashtag']))      $extras[] = self::badgeBlock($a['hashtag']);
                break;

            case 'event.conference':
                if (!empty($a['date_range']))   $extras[] = self::badgeBlock('📅 ' . $a['date_range']);
                if (!empty($a['venue']))        $extras[] = self::badgeBlock('📍 ' . $a['venue']);
                if (!empty($a['venue_address']))$extras[] = ['type' => 'map', 'settings' => ['address' => $a['venue_address']], 'is_active' => true];
                if (!empty($a['agenda_url']))   $extras[] = self::linkButton('🗓  Agenda', $a['agenda_url']);
                if (!empty($a['speakers_url'])) $extras[] = self::linkButton('🎤 Speakers', $a['speakers_url']);
                if (!empty($a['sponsors_url']))$extras[] = self::linkButton('🤝 Become a sponsor', $a['sponsors_url']);
                break;

            case 'event.workshop':
                if (!empty($a['date_range']))   $extras[] = self::badgeBlock('📅 ' . $a['date_range']);
                if (!empty($a['venue']))        $extras[] = self::badgeBlock('📍 ' . $a['venue']);
                if (!empty($a['price']))        $extras[] = self::badgeBlock('💰 ' . $a['price']);
                if (!empty($a['curriculum']))   $extras[] = self::richText('What you\'ll learn', self::bulletify($a['curriculum']));
                if (!empty($a['signup_url']))   $extras[] = self::ctaBlock('Reserve your spot', $a['signup_url'], $brand);
                if (!empty($a['instructor']) || !empty($a['instructor_bio'])) {
                    $extras[] = self::richText($a['instructor'] ?? 'Your instructor', $a['instructor_bio'] ?? '');
                }
                break;

            case 'event.party':
                if (!empty($a['date']))         $extras[] = self::badgeBlock('🎉 ' . $a['date']);
                if (!empty($a['venue']))        $extras[] = self::badgeBlock('📍 ' . $a['venue']);
                if (!empty($a['venue_address']))$extras[] = ['type' => 'map', 'settings' => ['address' => $a['venue_address']], 'is_active' => true];
                if (!empty($a['rsvp_url']))     $extras[] = self::ctaBlock('RSVP', $a['rsvp_url'], $brand);
                if (!empty($a['house_rules']))  $extras[] = self::richText('Good to know', self::bulletify($a['house_rules']));
                break;

            // ── Health & Wellness ───────────────────────────────────
            case 'health_wellness.fitness_trainer':
            case 'health_wellness.nutritionist':
                if (!empty($a['specialty'])) $extras[] = self::badgeBlock($a['specialty']);
                $programs = [];
                for ($i = 1; $i <= 3; $i++) {
                    if (!empty($a["program_{$i}"])) {
                        $programs[] = ['name' => $a["program_{$i}"], 'price' => $a["program_{$i}_price"] ?? '', 'description' => ''];
                    }
                }
                if (!empty($programs)) {
                    $extras[] = ['type' => 'list_pricing', 'settings' => ['title' => 'Work with me', 'items' => $programs], 'is_active' => true];
                }
                if (!empty($a['free_intro_url'])) $extras[] = self::ctaBlock('🎁 Free intro session', $a['free_intro_url'], $brand);
                if (!empty($a['consult_url']))    $extras[] = self::ctaBlock('☕ Free consultation', $a['consult_url'], $brand);
                if (!empty($a['booking_url']))    $extras[] = self::ctaBlock('📅 Book now', $a['booking_url'], $brand);
                if (!empty($a['plan_url']))       $extras[] = self::linkButton('🥗 Meal plans', $a['plan_url']);
                if (!empty($a['testimonial'])) {
                    $extras[] = ['type' => 'testimonials', 'settings' => ['items' => [['quote' => $a['testimonial'], 'name' => $a['testimonial_name'] ?? 'Client']]], 'is_active' => true];
                }
                break;

            case 'health_wellness.yoga':
                if (!empty($a['style']))          $extras[] = self::badgeBlock('🧘 ' . $a['style']);
                if (!empty($a['booking_url']))    $extras[] = self::ctaBlock('📅 Book a class', $a['booking_url'], $brand);
                if (!empty($a['class_pass_url'])) $extras[] = self::linkButton('▶ Online classes', $a['class_pass_url']);
                if (!empty($a['schedule']))       $extras[] = self::richText('Class schedule', $a['schedule']);
                break;

            case 'health_wellness.therapist':
                if (!empty($a['credentials'])) $extras[] = self::badgeBlock($a['credentials']);
                if (!empty($a['specialties'])) $extras[] = self::badgeBlock('Focus: ' . $a['specialties']);
                if (!empty($a['mode']))        $extras[] = self::badgeBlock(self::modeLabel($a['mode']));
                if (!empty($a['rates']))       $extras[] = self::badgeBlock('💷 ' . $a['rates']);
                if (!empty($a['booking_url'])) $extras[] = self::ctaBlock('📅 Book a session', $a['booking_url'], $brand);
                if (!empty($a['phone']))       $extras[] = self::linkButton('📞 Call ' . self::shortPhone($a['phone']), 'tel:' . self::digits($a['phone']));
                break;

            // ── Nonprofit / Charity ─────────────────────────────────
            case 'nonprofit.charity':
            case 'nonprofit.community_org':
            case 'nonprofit.social_enterprise':
            case 'nonprofit.activist':
                if (!empty($a['cause']))         $extras[] = self::badgeBlock('✊ ' . $a['cause']);
                if (!empty($a['donate_url']))    $extras[] = self::ctaBlock('💜 Donate', $a['donate_url'], $brand);
                if (!empty($a['petition_url']))  $extras[] = self::ctaBlock('✍ Sign the petition', $a['petition_url'], $brand);
                if (!empty($a['join_url']))      $extras[] = self::ctaBlock('🤝 Join us', $a['join_url'], $brand);
                if (!empty($a['volunteer_form']))$extras[] = self::linkButton('🙋 Get involved', $a['volunteer_form']);
                if (!empty($a['event_url']))     $extras[] = self::linkButton('📅 Upcoming event', $a['event_url']);
                if (!empty($a['partner_url']))   $extras[] = self::linkButton('🤝 Partner with us', $a['partner_url']);
                if (!empty($a['impact_blurb']))  $extras[] = self::badgeBlock($a['impact_blurb']);
                break;

            // ── Fashion & Beauty ────────────────────────────────────
            case 'fashion_beauty.fashion_brand':
                if (!empty($a['lookbook_url'])) $extras[] = self::ctaBlock('👗 Lookbook', $a['lookbook_url'], $brand);
                if (!empty($a['discount_code'])) {
                    $extras[] = self::alertBlock(($a['discount_blurb'] ?? 'Use code') . ' — ' . $a['discount_code']);
                }
                break;

            case 'fashion_beauty.beauty_artist':
                if (!empty($a['specialty']))     $extras[] = self::badgeBlock($a['specialty']);
                if (!empty($a['booking_url']))   $extras[] = self::ctaBlock('💄 Book me', $a['booking_url'], $brand);
                if (!empty($a['portfolio_url'])) $extras[] = self::linkButton('📸 Portfolio', $a['portfolio_url']);
                if (!empty($a['price_blurb']))   $extras[] = self::badgeBlock('💷 ' . $a['price_blurb']);
                break;

            case 'fashion_beauty.model':
                if (!empty($a['portfolio_url'])) $extras[] = self::ctaBlock('📸 Portfolio', $a['portfolio_url'], $brand);
                if (!empty($a['agency']))        $extras[] = self::badgeBlock('Repped by ' . $a['agency']);
                break;

            case 'fashion_beauty.stylist':
                if (!empty($a['specialty']))     $extras[] = self::badgeBlock($a['specialty']);
                if (!empty($a['booking_url']))   $extras[] = self::ctaBlock('📅 Book a session', $a['booking_url'], $brand);
                if (!empty($a['lookbook_url']))  $extras[] = self::linkButton('🖼  Portfolio', $a['lookbook_url']);
                if (!empty($a['shop_url']))      $extras[] = self::linkButton('🛍  Shop my picks', $a['shop_url']);
                break;

            case 'fashion_beauty.salon':
                if (!empty($a['booking_url'])) $extras[] = self::ctaBlock('📅 Book online', $a['booking_url'], $brand);
                $services = [];
                for ($i = 1; $i <= 3; $i++) {
                    if (!empty($a["service_{$i}"])) {
                        $services[] = ['name' => $a["service_{$i}"], 'price' => $a["service_{$i}_desc"] ?? '', 'description' => ''];
                    }
                }
                if (!empty($services)) {
                    $extras[] = ['type' => 'list_pricing', 'settings' => ['title' => 'Services', 'items' => $services], 'is_active' => true];
                }
                if (!empty($a['hours']))     $extras[] = self::richText('🕒 Opening hours', $a['hours']);
                if (!empty($a['address']))   $extras[] = ['type' => 'map', 'settings' => ['address' => $a['address'], 'zoom' => 15], 'is_active' => true];
                if (!empty($a['phone']))     $extras[] = self::linkButton('📞 Call ' . self::shortPhone($a['phone']), 'tel:' . self::digits($a['phone']));
                if (!empty($a['review_url']))$extras[] = self::linkButton('⭐ Read reviews', $a['review_url']);
                break;

            // ── Photo / Video ───────────────────────────────────────
            case 'photographer.photographer':
            case 'photographer.videographer':
            case 'photographer.wedding_photographer':
            case 'photographer.studio':
                if (!empty($a['specialty']))     $extras[] = self::badgeBlock($a['specialty']);
                if (!empty($a['showreel_url']))  $extras[] = self::ctaBlock('🎬 Watch the showreel', $a['showreel_url'], $brand);
                if (!empty($a['portfolio_url'])) $extras[] = self::ctaBlock('📸 Portfolio', $a['portfolio_url'], $brand);
                if (!empty($a['gallery_url']))   $extras[] = self::linkButton('🖼  Client galleries', $a['gallery_url']);
                $packages = [];
                for ($i = 1; $i <= 3; $i++) {
                    if (!empty($a["package_{$i}"])) {
                        $packages[] = ['name' => $a["package_{$i}"], 'price' => $a["package_{$i}_price"] ?? '', 'description' => ''];
                    }
                }
                $svcs = [];
                for ($i = 1; $i <= 3; $i++) {
                    if (!empty($a["service_{$i}"])) {
                        $svcs[] = ['name' => $a["service_{$i}"], 'price' => $a["service_{$i}_desc"] ?? '', 'description' => ''];
                    }
                }
                $pricingItems = array_merge($packages, $svcs);
                if (!empty($pricingItems)) {
                    $extras[] = ['type' => 'list_pricing', 'settings' => ['title' => 'Packages', 'items' => $pricingItems], 'is_active' => true];
                }
                if (!empty($a['booking_url']))   $extras[] = self::ctaBlock('📅 Book a shoot', $a['booking_url'], $brand);
                if (!empty($a['price_blurb']))   $extras[] = self::badgeBlock('💷 ' . $a['price_blurb']);
                if (!empty($a['address']))       $extras[] = ['type' => 'map', 'settings' => ['address' => $a['address'], 'zoom' => 15], 'is_active' => true];
                if (!empty($a['phone']))         $extras[] = self::linkButton('📞 Call ' . self::shortPhone($a['phone']), 'tel:' . self::digits($a['phone']));
                break;

            // ── Travel Creator ──────────────────────────────────────
            case 'travel_creator.travel_blogger':
            case 'travel_creator.digital_nomad':
                if (!empty($a['current_location'])) $extras[] = self::badgeBlock('📍 Currently in ' . $a['current_location']);
                if (!empty($a['blog_url']))         $extras[] = self::linkButton('✍ Blog', $a['blog_url']);
                if (!empty($a['gear_url']))         $extras[] = self::linkButton('🎒 Travel gear & resources', $a['gear_url']);
                break;

            case 'travel_creator.travel_agent':
                if (!empty($a['specialty']))   $extras[] = self::badgeBlock($a['specialty']);
                if (!empty($a['booking_url'])) $extras[] = self::ctaBlock('✈ Plan your trip', $a['booking_url'], $brand);
                $trips = [];
                for ($i = 1; $i <= 3; $i++) {
                    if (!empty($a["trip_{$i}"])) {
                        $trips[] = ['name' => $a["trip_{$i}"], 'price' => $a["trip_{$i}_price"] ?? '', 'description' => ''];
                    }
                }
                if (!empty($trips)) {
                    $extras[] = ['type' => 'list_pricing', 'settings' => ['title' => 'Featured trips', 'items' => $trips], 'is_active' => true];
                }
                if (!empty($a['phone'])) $extras[] = self::linkButton('📞 Call ' . self::shortPhone($a['phone']), 'tel:' . self::digits($a['phone']));
                break;

            case 'travel_creator.tour_guide':
                if (!empty($a['location']))    $extras[] = self::badgeBlock('📍 ' . $a['location']);
                if (!empty($a['tours_url']))   $extras[] = self::ctaBlock('🗺  Book a tour', $a['tours_url'], $brand);
                if (!empty($a['price_blurb'])) $extras[] = self::badgeBlock('💷 ' . $a['price_blurb']);
                if (!empty($a['whatsapp']))    $extras[] = self::linkButton('💬 WhatsApp', 'https://wa.me/' . self::digits($a['whatsapp']));
                break;

            // ── Community / Faith ───────────────────────────────────
            case 'faith.church':
            case 'faith.mosque_temple':
                if (!empty($a['denomination']))  $extras[] = self::badgeBlock($a['denomination']);
                if (!empty($a['tradition']))     $extras[] = self::badgeBlock($a['tradition']);
                if (!empty($a['service_times'])) $extras[] = self::richText('Service times', $a['service_times']);
                if (!empty($a['give_url']))      $extras[] = self::ctaBlock('💜 Give', $a['give_url'], $brand);
                if (!empty($a['livestream_url']))$extras[] = self::linkButton('▶ Watch live', $a['livestream_url']);
                if (!empty($a['events_url']))    $extras[] = self::linkButton('📅 Events', $a['events_url']);
                if (!empty($a['address']))       $extras[] = ['type' => 'map', 'settings' => ['address' => $a['address'], 'zoom' => 15], 'is_active' => true];
                if (!empty($a['phone']))         $extras[] = self::linkButton('📞 Call ' . self::shortPhone($a['phone']), 'tel:' . self::digits($a['phone']));
                break;

            case 'faith.faith_leader':
                if (!empty($a['role']))        $extras[] = self::badgeBlock($a['role']);
                if (!empty($a['sermons_url'])) $extras[] = self::ctaBlock('🎧 Sermons & talks', $a['sermons_url'], $brand);
                if (!empty($a['book_url']))    $extras[] = self::linkButton('📕 Book & writings', $a['book_url']);
                if (!empty($a['give_url']))    $extras[] = self::linkButton('💜 Give', $a['give_url']);
                break;

            case 'faith.community_group':
                if (!empty($a['focus']))        $extras[] = self::badgeBlock($a['focus']);
                if (!empty($a['join_url']))     $extras[] = self::ctaBlock('🤝 Join us', $a['join_url'], $brand);
                if (!empty($a['meeting_info'])) $extras[] = self::richText('When & where we meet', $a['meeting_info']);
                if (!empty($a['events_url']))   $extras[] = self::linkButton('📅 Events', $a['events_url']);
                if (!empty($a['whatsapp']))     $extras[] = self::linkButton('💬 WhatsApp', 'https://wa.me/' . self::digits($a['whatsapp']));
                break;

            // ── Education / School ──────────────────────────────────
            case 'education.tutor':
                if (!empty($a['subjects']))    $extras[] = self::badgeBlock('📚 ' . $a['subjects']);
                if (!empty($a['levels']))      $extras[] = self::richText('Levels', $a['levels']);
                if (!empty($a['mode']))        $extras[] = self::badgeBlock(self::modeLabel($a['mode']));
                if (!empty($a['price']))       $extras[] = self::badgeBlock('💷 ' . $a['price']);
                if (!empty($a['booking_url'])) $extras[] = self::ctaBlock('📅 Book a session', $a['booking_url'], $brand);
                if (!empty($a['phone']))       $extras[] = self::linkButton('📞 Call ' . self::shortPhone($a['phone']), 'tel:' . self::digits($a['phone']));
                break;

            case 'education.school':
                if (!empty($a['admissions_url'])) $extras[] = self::ctaBlock('🎓 Admissions / Apply', $a['admissions_url'], $brand);
                if (!empty($a['programs_url']))   $extras[] = self::linkButton('📚 Programs & courses', $a['programs_url']);
                if (!empty($a['events_url']))     $extras[] = self::linkButton('📅 Events / Open day', $a['events_url']);
                if (!empty($a['address']))        $extras[] = ['type' => 'map', 'settings' => ['address' => $a['address'], 'zoom' => 15], 'is_active' => true];
                if (!empty($a['phone']))          $extras[] = self::linkButton('📞 Call ' . self::shortPhone($a['phone']), 'tel:' . self::digits($a['phone']));
                break;

            case 'education.online_course':
                if (!empty($a['enroll_url']))  $extras[] = self::ctaBlock('🎓 Enrol now', $a['enroll_url'], $brand);
                if (!empty($a['price']))       $extras[] = self::badgeBlock('💷 ' . $a['price']);
                if (!empty($a['curriculum']))  $extras[] = self::richText('What you\'ll learn', self::bulletify($a['curriculum']));
                if (!empty($a['preview_url'])) $extras[] = self::linkButton('▶ Free preview', $a['preview_url']);
                break;

            case 'education.teacher':
                if (!empty($a['subject']))       $extras[] = self::badgeBlock($a['subject']);
                if (!empty($a['school']))        $extras[] = self::badgeBlock('🏫 ' . $a['school']);
                if (!empty($a['resources_url'])) $extras[] = self::ctaBlock('📚 Class resources', $a['resources_url'], $brand);
                if (!empty($a['syllabus_url']))  $extras[] = self::linkButton('📄 Syllabus', $a['syllabus_url']);
                if (!empty($a['office_hours']))  $extras[] = self::badgeBlock('🕒 Office hours: ' . $a['office_hours']);
                break;
        }

        // Top-level website button (most categories ask for it).
        if (!empty($a['website']) && !self::hasCtaWith($extras, $a['website'])) {
            $extras[] = self::linkButton('🌐 Visit website', $a['website']);
        }
        if (!empty($a['store_url']) && !self::hasCtaWith($extras, $a['store_url'])) {
            $extras[] = self::ctaBlock('🛍  Shop now', $a['store_url'], $brand);
        }

        // Universal newsletter helper (creators/podcasters/coaches/stores).
        if (!empty($a['newsletter_blurb']) && !self::contains($extras, 'email_subscribe')) {
            $extras[] = self::newsletterBlock($a['newsletter_blurb'], $brand);
        }

        return $extras;
    }

    /* ────────────────────────────────────────────────────────────── *
     *  Block builders
     * ────────────────────────────────────────────────────────────── */

    // ───────────────────────────────────────────────────────────────
    // Block setting keys mirror the canonical defaults in
    // BlockDefaults::contentForType() so that
    // TemplateService::applyPageToLink → BiolinkBlock::sanitizeSettings
    // accepts our payload without falling back to placeholder defaults.
    // ───────────────────────────────────────────────────────────────

    protected static function ctaBlock(string $label, string $url, string $color): array
    {
        return [
            'type' => 'cta_button',
            'settings' => [
                'text'       => $label,
                'url'        => $url,
                'color'      => $color,
                'text_color' => '#ffffff',
                'size'       => 'lg',
            ],
            'is_active' => true,
        ];
    }

    protected static function linkButton(string $label, string $url): array
    {
        return [
            'type' => 'link',
            'settings' => [
                'text'      => $label,
                'url'       => $url,
                'icon'      => '',
                'thumbnail' => '',
            ],
            'is_active' => true,
        ];
    }

    protected static function richText(string $title, string $body): array
    {
        $html = '<h3>' . htmlspecialchars($title) . '</h3>'
              . '<p>' . nl2br(htmlspecialchars($body)) . '</p>';
        return [
            'type' => 'paragraph_rich',
            'settings' => ['html' => $html],
            'is_active' => true,
        ];
    }

    protected static function alertBlock(string $msg): array
    {
        return [
            'type' => 'alert',
            'settings' => ['text' => $msg, 'type' => 'info', 'icon' => 'fa-info-circle'],
            'is_active' => true,
        ];
    }

    protected static function badgeBlock(string $msg): array
    {
        return [
            'type' => 'badge',
            'settings' => ['text' => $msg, 'color' => '#7c3aed', 'text_color' => '#ffffff'],
            'is_active' => true,
        ];
    }

    protected static function newsletterBlock(string $blurb, string $color): array
    {
        return [
            'type' => 'email_subscribe',
            'settings' => [
                'title'         => 'Stay in the loop',
                'description'   => $blurb,
                'submit_label'  => 'Subscribe',
                'placeholder'   => 'you@example.com',
                'accent_color'  => $color,
            ],
            'is_active' => true,
        ];
    }

    /* ────────────────────────────────────────────────────────────── *
     *  Helpers
     * ────────────────────────────────────────────────────────────── */

    protected static function digits(string $s): string
    {
        return preg_replace('/[^\d+]/', '', $s) ?: '';
    }

    protected static function shortPhone(string $s): string
    {
        $d = self::digits($s);
        return $d !== '' ? $d : $s;
    }

    protected static function modeLabel(string $mode): string
    {
        return match ($mode) {
            'online'    => '💻 Online',
            'in_person' => '📍 In-person',
            'both'      => '💻 Online & in-person',
            default     => $mode,
        };
    }

    protected static function bulletify(string $text): string
    {
        $lines = preg_split('/\r?\n/', $text);
        $lines = array_filter(array_map('trim', $lines));
        return implode("\n", array_map(fn($l) => '• ' . $l, $lines));
    }

    protected static function contains(array $blocks, string $type): bool
    {
        foreach ($blocks as $b) {
            if (is_array($b) && ($b['type'] ?? null) === $type) return true;
        }
        return false;
    }

    protected static function hasCtaWith(array $blocks, string $url): bool
    {
        foreach ($blocks as $b) {
            if (!is_array($b)) continue;
            $u = $b['settings']['url'] ?? null;
            if ($u && $u === $url) return true;
        }
        return false;
    }
}
