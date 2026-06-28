<?php

namespace App\Modules\Common\Support;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Support\IntegrationConfigRegistry;

/**
 * Single source of truth for the admin "Create / Edit Plan" form.
 *
 * Every plan-controlled knob the codebase already understands is enumerated
 * here, grouped by section and tagged with the module it belongs to so the
 * Blade form can render and dim the right controls when a module is off.
 *
 * The form Blade file iterates this catalogue plus BiolinkBlock::TYPES /
 * BiolinkBlock::CATEGORIES and IntegrationConfigRegistry::kinds() / ::all().
 */
class PlanFormCatalogue
{
    /**
     * Top-level product modules. Toggling one off visually dims every
     * control beneath it (controls keep their values for round-tripping).
     *
     * Each module: key, label, description.
     */
    public static function modules(): array
    {
        return [
            'module_short_links'  => ['label' => 'Short Links',          'desc' => 'Shortening, custom aliases, link-level protection and tracking.'],
            'module_biolinks'     => ['label' => 'Link in Bio',          'desc' => 'Link in Bio pages and the block library used to build them.'],
            'module_conversational' => ['label' => 'Conversational Pages', 'desc' => 'Chat-style conversational link pages.'],
            'module_slides'       => ['label' => 'Slides Pages',         'desc' => 'Slide-deck link pages.'],
            'module_ai_chat'      => ['label' => 'AI Chatbot Pages',     'desc' => 'AI chatbot link pages with a configurable persona.'],
            'module_restaurant_menu' => ['label' => 'Restaurant Menu Pages', 'desc' => 'Restaurant / digital menu link pages.'],
            'module_reviews'      => ['label' => 'Reviews Pages',        'desc' => 'Review-collection and display link pages.'],
            'module_resume'       => ['label' => 'Resume / Portfolio',   'desc' => 'Standalone resume / portfolio link pages with PDF export.'],
            'module_calendar'     => ['label' => 'Calendars',            'desc' => 'Followable calendar link pages with events, ICS feed and Google sync.'],
            'module_brand_kit'    => ['label' => 'Brand / Press Kit',    'desc' => 'Shareable Brand / Press Kit pages (logos, colours, fonts, voice, boilerplate).'],
            'module_projects'     => ['label' => 'Projects',             'desc' => 'Group links into separate brands or campaigns.'],
            'module_forms'        => ['label' => 'Custom Forms',         'desc' => 'Branded lead-capture forms inside Link in Bio pages.'],
            'module_contacts'     => ['label' => 'Contacts / CRM',       'desc' => 'CRM entries, follower opt-ins and Google Contacts sync.'],
            'module_teams'        => ['label' => 'Teams & Workspaces',   'desc' => 'Multi-seat collaboration with role-based permissions.'],
            'module_ecommerce'    => ['label' => 'E-Commerce / Selling', 'desc' => 'Product blocks, prices and checkout on Link in Bio pages.'],
            'module_ai_suite'     => ['label' => 'AI Suite',             'desc' => 'AI chatbot, agent, embeddable widget and voice assistant.'],
            'module_branding'     => ['label' => 'Custom Branding',      'desc' => 'White-label colors, favicon and custom HTML / JS.'],
            'module_domains'      => ['label' => 'Custom Domains',       'desc' => 'Connect your own domain for short links and Link in Bio pages.'],
            'module_integrations' => ['label' => 'Integrations',         'desc' => 'Connected payment, SMS and email provider accounts.'],
        ];
    }

    /**
     * Quantity limits — every numeric limit in one grid. -1 = unlimited.
     * Each entry: key, label, default, module, hint.
     */
    public static function quantityLimits(): array
    {
        return [
            ['key' => 'max_links',            'label' => 'Max short links',         'default' => 10,  'module' => 'module_short_links', 'hint' => 'Total short links a user can create.'],
            ['key' => 'max_biolinks',         'label' => 'Max Link in Bio pages',   'default' => 1,   'module' => 'module_biolinks',    'hint' => 'How many separate Link in Bio pages a user can publish.'],
            ['key' => 'max_conversational',   'label' => 'Max conversational pages','default' => 1,   'module' => 'module_conversational', 'hint' => 'How many conversational link pages a user can publish.'],
            ['key' => 'max_slides',           'label' => 'Max slides pages',        'default' => 1,   'module' => 'module_slides',      'hint' => 'How many slide-deck link pages a user can publish.'],
            ['key' => 'max_ai_chat',          'label' => 'Max AI chatbot pages',    'default' => 1,   'module' => 'module_ai_chat',     'hint' => 'How many AI chatbot link pages a user can publish.'],
            ['key' => 'max_restaurant_menu',  'label' => 'Max restaurant menu pages','default' => 1,  'module' => 'module_restaurant_menu', 'hint' => 'How many restaurant / digital menu link pages a user can publish.'],
            ['key' => 'max_reviews',          'label' => 'Max reviews pages',       'default' => 1,   'module' => 'module_reviews',     'hint' => 'How many review-collection link pages a user can publish.'],
            ['key' => 'max_resume',           'label' => 'Max resume / portfolio pages','default' => 1, 'module' => 'module_resume',     'hint' => 'How many resume / portfolio link pages a user can publish.'],
            ['key' => 'max_calendars',        'label' => 'Max calendars',           'default' => 1,   'module' => 'module_calendar',    'hint' => 'How many followable calendar link pages a user can publish.'],
            ['key' => 'max_brand_kit_pages',  'label' => 'Max Brand / Press Kit pages','default' => 1, 'module' => 'module_brand_kit',   'hint' => 'How many shareable Brand / Press Kit link pages a user can publish. (Distinct from the AI brand-kit save limit above.)'],
            ['key' => 'max_calendar_events',  'label' => 'Max events per calendar', 'default' => 25,  'module' => 'module_calendar',    'hint' => 'How many events each calendar can hold. -1 = unlimited.'],
            ['key' => 'max_projects',         'label' => 'Max projects',            'default' => 3,   'module' => 'module_projects',    'hint' => 'Project / workspace buckets to organize links.'],
            ['key' => 'storage_limit_mb',     'label' => 'Total storage (MB)',      'default' => 100, 'module' => null,                 'hint' => 'Total disk space across all uploads. See the Storage section for a GB converter.'],
            ['key' => 'max_file_size_mb',     'label' => 'Max upload size (MB)',    'default' => 5,   'module' => null,                 'hint' => 'Largest single file a user can upload.'],
            ['key' => 'contacts_max',         'label' => 'Max contacts',            'default' => 100, 'module' => 'module_contacts',    'hint' => 'CRM entries this plan can keep stored.'],
            ['key' => 'max_custom_domains',   'label' => 'Max custom domains',      'default' => 0,   'module' => 'module_domains',     'hint' => 'How many of their own domains a user can connect. The Custom Domains toggle governs whether the feature is available at all.'],
            ['key' => 'max_aliases_per_link', 'label' => 'Extra aliases per link',  'default' => 0,   'module' => 'module_short_links', 'hint' => 'Global fallback for additional aliases beyond the primary one. Override per link type below. -1 = unlimited.'],
            ['key' => 'min_alias_length',     'label' => 'Min alias length',        'default' => 3,   'module' => 'module_short_links', 'hint' => 'Minimum length for the visitor-facing alias.', 'max' => 191],
            ['key' => 'max_alias_length',     'label' => 'Max alias length',        'default' => 50,  'module' => 'module_short_links', 'hint' => 'Hard cap is 191 characters.', 'max' => 191],
            ['key' => 'max_forms',            'label' => 'Max forms',               'default' => 1,   'module' => 'module_forms',       'hint' => 'Custom form definitions a user can publish.'],
            ['key' => 'max_brand_kits',       'label' => 'Max AI brand kits',       'default' => 0,   'module' => 'module_branding',    'hint' => 'AI-generated brand kits (palette, fonts, voice, taglines) a user can save. 0 = feature hidden / upgrade prompt; -1 = unlimited.'],
            ['key' => 'max_buzz_items',       'label' => 'Max buzz pop-ups',        'default' => 0,   'module' => null,                 'hint' => 'On-site notification pop-ups.'],
            ['key' => 'max_buzz_impressions', 'label' => 'Max buzz views / mo',     'default' => -1,  'module' => null,                 'hint' => 'Monthly Buzz notification views (impressions). Beyond this, widgets pause until next month. -1 = unlimited.'],
            ['key' => 'max_splash_pages',     'label' => 'Max splash pages',        'default' => 0,   'module' => null,                 'hint' => 'Branded splash / coming-soon pages.'],
            ['key' => 'max_files',            'label' => 'Max files',               'default' => 25,  'module' => null,                 'hint' => 'Files uploaded into the in-app file manager.'],
            ['key' => 'max_vault_items',      'label' => 'Max vault items',         'default' => 0,   'module' => null,                 'hint' => 'Encrypted vault entries.'],
            ['key' => 'max_task_boards',      'label' => 'Max task boards',         'default' => 1,   'module' => null,                 'hint' => 'Kanban boards a user can create.'],
            ['key' => 'max_leads',            'label' => 'Max leads',               'default' => 0,   'module' => null,                 'hint' => 'Lead-capture entries collected.'],
            ['key' => 'max_events',           'label' => 'Max events',              'default' => 0,   'module' => null,                 'hint' => 'Event listings / calendar entries.'],
            ['key' => 'api_calls_monthly',    'label' => 'API calls / month',       'default' => 0,   'module' => null,                 'hint' => 'Monthly included API-call allowance for API keys. Calls beyond this are paid with coins (admin-set overage rate). -1 = unlimited.'],
            ['key' => 'api_rate_per_min',     'label' => 'API requests / minute',   'default' => 0,   'module' => null,                 'hint' => 'Per-user rate limit for the public API.'],
            ['key' => 'stats_retention_days', 'label' => 'Stats history (days)',    'default' => 365, 'module' => null,                 'hint' => 'How far back analytics can be viewed. Older click/session history is pruned. Minimum 30 days (values below are raised to 30 on save); -1 = unlimited (kept forever).'],
        ];
    }

    /**
     * Link types that can carry their own per-type "extra aliases" cap.
     *
     * The plan feature `max_aliases_per_link` may be either a scalar (the
     * global allowance, applied to every type) OR a map keyed by these
     * type slugs with an optional `default` fallback. The admin form, the
     * writer and the seeder all iterate this single list so the surfaces
     * stay in lockstep. Slugs match `links.type`.
     *
     * @return array<string,string> slug => human label
     */
    public static function aliasLinkTypes(): array
    {
        return [
            'short'           => 'Short link',
            'biolink'         => 'Link in Bio',
            'conversational'  => 'Conversational page',
            'slides'          => 'Slides page',
            'ai_chat'         => 'AI chatbot page',
            'restaurant_menu' => 'Restaurant menu',
            'reviews'         => 'Reviews page',
            'resume'          => 'Resume / Portfolio',
            'calendar'        => 'Calendar',
            'paid_page'       => 'Paid page',
            'brand_kit'       => 'Brand / Press Kit',
            'file'            => 'File',
            'qr'              => 'QR code',
            'event'           => 'Event',
            'vcard'           => 'Digital card',
            'social'          => 'Social',
            'sms'             => 'SMS',
            'wifi'            => 'WiFi',
            'pdf'             => 'PDF',
        ];
    }

    /**
     * Referral program controls — referrer/referred bonus days and
     * sign-up bonus days. Stored as integers in features.
     */
    public static function referralFields(): array
    {
        return [
            ['key' => 'signup_bonus_days',   'label' => 'Sign-up bonus (days)',    'hint' => 'Bonus trial days when this plan is the sign-up target.'],
            ['key' => 'referrer_free_days',  'label' => 'Referrer reward (days)',  'hint' => 'Days credited to the inviting user when their referral activates.'],
            ['key' => 'referred_free_days',  'label' => 'Referred reward (days)',  'hint' => 'Days credited to the new user who joined via a referral on this plan.'],
        ];
    }

    /**
     * Boolean / select feature flags rendered in the "Features & analytics
     * depth" section. Helper text comes from PremiumFeatures::catalogue()
     * when available so we never duplicate descriptions.
     */
    public static function featureFlags(): array
    {
        return [
            ['key' => 'pixels',               'type' => 'bool',   'module' => 'module_short_links'],
            ['key' => 'utm_params',           'type' => 'bool',   'module' => 'module_short_links'],
            ['key' => 'custom_domains',       'type' => 'bool',   'module' => 'module_domains'],
            ['key' => 'seo_settings',         'type' => 'bool',   'module' => 'module_short_links'],
            ['key' => 'link_protection',      'type' => 'bool',   'module' => 'module_short_links'],
            ['key' => 'qr_customization',     'type' => 'bool',   'module' => 'module_short_links'],
            ['key' => 'custom_forms',         'type' => 'bool',   'module' => 'module_forms'],
            ['key' => 'paid_forms',           'type' => 'bool',   'module' => 'module_forms'],
            ['key' => 'form_analytics_advanced','type' => 'bool', 'module' => 'module_forms'],
            ['key' => 'contacts_google_sync', 'type' => 'bool',   'module' => 'module_contacts'],
            ['key' => 'custom_branding',      'type' => 'bool',   'module' => 'module_branding'],
            ['key' => 'remove_branding',      'type' => 'bool',   'module' => 'module_branding'],
            ['key' => 'custom_favicon',       'type' => 'bool',   'module' => 'module_branding'],
            ['key' => 'custom_code',          'type' => 'bool',   'module' => 'module_branding'],
            ['key' => 'ecommerce',            'type' => 'bool',   'module' => 'module_ecommerce'],
            ['key' => 'buzz_popups',          'type' => 'bool',   'module' => null],
            ['key' => 'splash_pages',         'type' => 'bool',   'module' => null],
            ['key' => 'files',                'type' => 'bool',   'module' => null],
            ['key' => 'vaults',               'type' => 'bool',   'module' => null],
            ['key' => 'tasks',                'type' => 'bool',   'module' => null],
            ['key' => 'leads',                'type' => 'bool',   'module' => null],
            ['key' => 'events',               'type' => 'bool',   'module' => null],
            ['key' => 'calendar_sync',        'type' => 'bool',   'module' => null],
            ['key' => 'creator_profile_public','type' => 'bool',  'module' => null],
            ['key' => 'verification_eligible','type' => 'bool',   'module' => null],
            ['key' => 'priority_support',     'type' => 'bool',   'module' => null],
            ['key' => 'scheduled_posts',      'type' => 'bool',   'module' => null],
            ['key' => 'social_proof_popup',   'type' => 'bool',   'module' => null],
            ['key' => 'templates_premium',    'type' => 'bool',   'module' => null],
            ['key' => 'api_access',           'type' => 'bool',   'module' => null],
            ['key' => 'link_password',        'type' => 'bool',   'module' => 'module_short_links'],
            ['key' => 'link_expiry',          'type' => 'bool',   'module' => 'module_short_links'],
            ['key' => 'link_geo_targeting',   'type' => 'bool',   'module' => 'module_short_links'],
            ['key' => 'link_device_targeting','type' => 'bool',   'module' => 'module_short_links'],
            ['key' => 'link_deep_link',       'type' => 'bool',   'module' => 'module_short_links'],
            ['key' => 'link_smart_rules',     'type' => 'bool',   'module' => 'module_short_links'],
            ['key' => 'link_active_window',   'type' => 'bool',   'module' => 'module_short_links'],
            ['key' => 'analytics',            'type' => 'select', 'module' => null,
             'options' => ['basic' => 'Basic — clicks, top countries', 'advanced' => 'Advanced — geo, device, referrer, cohorts'],
             'default' => 'basic'],
            ['key' => 'analytics_export',     'type' => 'bool',   'module' => null],
        ];
    }

    /** AI suite booleans (separate section). */
    public static function aiSuite(): array
    {
        return [
            ['key' => 'ai_chatbot',         'module' => 'module_ai_suite'],
            ['key' => 'ai_agent',           'module' => 'module_ai_suite'],
            ['key' => 'ai_widget',          'module' => 'module_ai_suite'],
            ['key' => 'ai_voice_assistant', 'module' => 'module_ai_suite'],
        ];
    }

    /**
     * Per-plan coin multipliers that scale the global base AI rates. One
     * multiplier per provider; a missing / zero value means 1× (no change).
     * These are deliberately NOT part of moduleKeys() — they apply to coin
     * pricing across every AI feature, not just the AI suite module, so we
     * never want the module reset to wipe them.
     */
    public static function aiCoinMultipliers(): array
    {
        return [
            [
                'key'   => 'ai_openai_coin_multiplier',
                'label' => 'OpenAI coin multiplier',
                'hint'  => 'Scales the base coin cost of every OpenAI call (chat, embeddings, Whisper STT) for this plan. Leave blank or 0 for 1× (no change). e.g. 0.5 = half price, 2 = double.',
            ],
            [
                'key'   => 'ai_elevenlabs_coin_multiplier',
                'label' => 'ElevenLabs coin multiplier',
                'hint'  => 'Scales the base coin cost of ElevenLabs text-to-speech for this plan. Leave blank or 0 for 1× (no change). e.g. 0.5 = half price, 2 = double.',
            ],
        ];
    }

    /**
     * PremiumFeatures::catalogue() indexed by key — used to pull plain-
     * English label + description for any feature that already lives there.
     */
    public static function copyFor(string $key): array
    {
        static $byKey = null;
        if ($byKey === null) {
            $byKey = [];
            foreach (PremiumFeatures::catalogue() as $entry) {
                $byKey[$entry['key']] = $entry;
            }
        }
        // Fall back to our local copy map for keys the public PremiumFeatures
        // catalogue doesn't document — every rendered control should have
        // helper text under it.
        return $byKey[$key] ?? (self::localCopy()[$key] ?? []);
    }

    /**
     * Local helper-copy fallbacks for plan feature keys that aren't in
     * PremiumFeatures::catalogue(). Each entry has at minimum a `name`
     * and a `description`. Keep these short and admin-facing.
     */
    private static function localCopy(): array
    {
        return [
            'paid_forms'            => ['name' => 'Paid forms',            'description' => 'Charge customers to submit a form, collected through the creator\'s own connected payment gateway (0% platform fee).'],
            'form_analytics_advanced'=> ['name' => 'Advanced form analytics','description' => 'Submission trends over time, per-field completion / drop-off, device & geo breakdowns and per-form revenue.'],
            'buzz_popups'           => ['name' => 'Buzz pop-ups',          'description' => 'On-site notification pop-ups (recent activity, social proof, etc.).'],
            'splash_pages'          => ['name' => 'Splash pages',          'description' => 'Branded coming-soon / landing pages a user can publish.'],
            'files'                 => ['name' => 'File manager',          'description' => 'Lets the user upload and organise files in the in-app file manager.'],
            'vaults'                => ['name' => 'Encrypted vaults',      'description' => 'Per-user encrypted vault for secrets and credentials.'],
            'tasks'                 => ['name' => 'Task boards',           'description' => 'Kanban-style task boards for personal or team work.'],
            'leads'                 => ['name' => 'Lead capture',          'description' => 'Inbound lead-capture inbox with form integrations.'],
            'events'                => ['name' => 'Events',                'description' => 'Public event listings the user can publish on their profile.'],
            'calendar_sync'         => ['name' => 'Calendar sync',         'description' => 'Two-way sync between events and Google / Outlook calendars.'],
            'creator_profile_public'=> ['name' => 'Public creator profile','description' => 'Exposes a public-facing creator profile page for the user.'],
            'verification_eligible' => ['name' => 'Verification eligible', 'description' => 'Marks accounts on this plan as eligible for the verified-creator badge.'],
            'priority_support'      => ['name' => 'Priority support',      'description' => 'Routes the user to the priority support queue.'],
            'scheduled_posts'       => ['name' => 'Scheduled posts',       'description' => 'Lets the user schedule Link in Bio / social posts in advance.'],
            'social_proof_popup'    => ['name' => 'Social proof pop-ups',  'description' => 'Live "X people just signed up" style notifications on Link in Bio pages.'],
            'templates_premium'     => ['name' => 'Premium templates',     'description' => 'Unlocks the premium template library for Link in Bio and pages.'],
            'api_access'            => ['name' => 'Public API access',     'description' => 'Allows the user to generate API tokens and call the public API.'],
            'link_password'         => ['name' => 'Password-protected links', 'description' => 'Require a visitor-supplied password before redirecting.'],
            'link_expiry'           => ['name' => 'Link expiry',           'description' => 'Schedule a link to stop redirecting after a date / click count.'],
            'link_geo_targeting'    => ['name' => 'Geo targeting',         'description' => 'Send visitors to different destinations by country / region.'],
            'link_device_targeting' => ['name' => 'Device targeting',      'description' => 'Send visitors to different destinations by device / OS.'],
            'link_deep_link'        => ['name' => 'Deep links',            'description' => 'Open native mobile apps directly when the link is tapped.'],
            'link_smart_rules'      => ['name' => 'Smart rules',           'description' => 'Compose multiple targeting rules with priority / fallback.'],
            'link_active_window'    => ['name' => 'Active windows',        'description' => 'Only let the link redirect during specific time windows.'],
            'ai_chatbot'            => ['name' => 'AI chatbot',            'description' => 'Embeddable AI chat widget for the user\'s Link in Bio.'],
            'ai_agent'              => ['name' => 'AI agent',              'description' => 'Autonomous AI agent that can take actions on the user\'s behalf.'],
            'ai_widget'             => ['name' => 'AI widget',             'description' => 'AI-powered content widget (FAQ, summary, etc.) on Link in Bio pages.'],
            'ai_voice_assistant'    => ['name' => 'AI voice assistant',    'description' => 'Voice-driven AI assistant for the user\'s public profile.'],
            'custom_favicon'        => ['name' => 'Custom favicon',        'description' => 'Per-account favicon shown on Link in Bio / short-link pages.'],
            'custom_code'           => ['name' => 'Custom code injection', 'description' => 'Inject custom HTML / JS / CSS into the user\'s public pages.'],
        ];
    }

    public static function labelFor(string $key, ?string $fallback = null): string
    {
        $copy = self::copyFor($key);
        return $copy['name'] ?? ($fallback ?? ucwords(str_replace('_', ' ', $key)));
    }

    public static function descriptionFor(string $key, ?string $fallback = null): ?string
    {
        $copy = self::copyFor($key);
        return $copy['description'] ?? $fallback;
    }

    /**
     * Visible (non-system) biolink block types, grouped by category, in the
     * canonical category order.
     */
    public static function blockTypesByCategory(): array
    {
        $out = [];
        foreach (BiolinkBlock::CATEGORIES as $catKey => $catLabel) {
            $out[$catKey] = ['label' => $catLabel, 'types' => []];
        }
        foreach (BiolinkBlock::pickerTypes() as $slug => $meta) {
            if (!empty($meta['system'])) continue;
            $cat = $meta['category'] ?? 'basic';
            if (!isset($out[$cat])) {
                $out[$cat] = ['label' => ucfirst($cat), 'types' => []];
            }
            $out[$cat]['types'][$slug] = $meta;
        }
        // Drop empty categories.
        return array_filter($out, fn($c) => !empty($c['types']));
    }

    /**
     * Integration kinds + provider slugs, used for the per-kind cap +
     * allowlist UI.
     */
    public static function integrationMatrix(): array
    {
        $kinds = IntegrationConfigRegistry::kinds();
        $out = [];
        foreach ($kinds as $kind => $meta) {
            $providers = IntegrationConfigRegistry::providers($kind);
            $out[$kind] = [
                'label'     => $meta['label'],
                'subtitle'  => $meta['subtitle'] ?? '',
                'icon'      => $meta['icon'] ?? null,
                'color'     => $meta['color'] ?? null,
                'providers' => array_map(fn($slug, $p) => ['slug' => $slug, 'label' => $p['label'] ?? $slug],
                                          array_keys($providers), $providers),
            ];
        }
        return $out;
    }

    /**
     * Map of module key → list of feature keys gated by that module.
     * Used by the controller to honour "module off ⇒ ignore sub-controls
     * on save" — the existing value of each gated key is preserved
     * (or reset to its default for a brand-new plan) instead of being
     * overwritten by whatever the visually-dimmed input posted.
     */
    public static function moduleKeys(): array
    {
        return [
            'module_short_links'  => ['max_links', 'max_aliases_per_link', 'max_aliases_per_link_by_type', 'min_alias_length', 'max_alias_length',
                                      'pixels', 'utm_params', 'link_protection', 'qr_customization', 'seo_settings',
                                      'link_password', 'link_expiry', 'link_geo_targeting', 'link_device_targeting',
                                      'link_deep_link', 'link_smart_rules', 'link_active_window'],
            'module_biolinks'     => ['max_biolinks', 'block_types_allowed'],
            'module_conversational' => ['max_conversational'],
            'module_slides'       => ['max_slides'],
            'module_ai_chat'      => ['max_ai_chat'],
            'module_restaurant_menu' => ['max_restaurant_menu'],
            'module_reviews'      => ['max_reviews'],
            'module_calendar'     => ['max_calendars', 'max_calendar_events'],
            'module_projects'     => ['max_projects'],
            'module_forms'        => ['custom_forms', 'max_forms', 'paid_forms', 'form_analytics_advanced'],
            'module_contacts'     => ['contacts_max', 'contacts_google_sync'],
            'module_teams'        => ['teams', 'max_workspaces', 'max_seats_per_workspace'],
            'module_ecommerce'    => ['ecommerce'],
            'module_ai_suite'     => ['ai_chatbot', 'ai_agent', 'ai_widget', 'ai_voice_assistant'],
            'module_branding'     => ['custom_branding', 'remove_branding', 'custom_favicon', 'custom_code'],
            'module_domains'      => ['custom_domains', 'max_custom_domains'],
            'module_integrations' => ['integration_accounts_max', 'integration_providers_allowed'],
        ];
    }

    /**
     * Sticky "On this page" jump-nav entries (id → label).
     */
    public static function sectionNav(): array
    {
        return [
            'sec-basics'        => 'Basics',
            'sec-pricing'       => 'Pricing',
            'sec-intro'         => 'Intro discount',
            'sec-trial'         => 'Trial & retention',
            'sec-referral'      => 'Referral program',
            'sec-modules'       => 'Modules',
            'sec-quantities'    => 'Quantity limits',
            'sec-team'          => 'Team management',
            'sec-storage'       => 'Storage',
            'sec-blocks'        => 'Link in Bio blocks',
            'sec-features'      => 'Features & analytics',
            'sec-ai'            => 'AI suite',
            'sec-integrations'  => 'Integration accounts',
            'sec-addons'        => 'Eligible addons',
        ];
    }
}
