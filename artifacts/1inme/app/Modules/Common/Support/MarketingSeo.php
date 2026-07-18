<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Models\SitePage;

/**
 * Single source of truth for public marketing-page SEO meta (title,
 * description, keywords).
 *
 * Two kinds of marketing pages exist:
 *
 *  - "Code-driven" pages (home, pricing, the comparison
 *    pages, the capability pages, the creators directory, etc.) are NOT
 *    backed by a `site_pages` row. Their seeded defaults live in
 *    {@see self::codeDrivenDefaults()} and admins override them through the
 *    central key/value AppSetting registry under {@see self::SETTING_KEY}.
 *
 *  - "Content" pages backed by a `site_pages` row (features, about, the
 *    policy pages, the AI suite, the use-case pages, …). Admins already edit
 *    their title/meta_description (and now meta_keywords) directly on the row
 *    through the Site Pages editor, so those rows ARE the admin override.
 *    Keyword defaults for them live in {@see self::sitePageKeywordDefaults()}.
 *
 * Resolution order for every field is:
 *   explicit page value → admin override → seeded default → app name/''.
 */
class MarketingSeo
{
    /** AppSetting key holding the per-page override map for code-driven pages. */
    public const SETTING_KEY = 'marketing_seo';

    /**
     * Seeded SEO defaults for every code-driven marketing page, grouped so the
     * unified admin screen can render tidy sections. Each entry:
     *   key => [label, group, url, title, description, keywords].
     *
     * The /compare competitor entries are appended dynamically from
     * ComparisonContent so the rival list stays in lockstep.
     */
    public static function codeDrivenDefaults(): array
    {
        $pages = [
            'home' => [
                'label' => 'Home',
                'group' => 'Core',
                'url' => '/',
                'title' => 'Link in Bio, Short Links & QR Codes',
                'description' => 'Sayzio is the all-in-one link platform: build a drag-and-drop Link in Bio page, shorten links, generate dynamic QR codes, and grow with live analytics.',
                'keywords' => 'link in bio, biolink, link management, short links, url shortener, qr code generator, link analytics, sayzio',
            ],
            'pricing' => [
                'label' => 'Pricing',
                'group' => 'Core',
                'url' => '/pricing',
                'title' => 'Pricing Plans — Free, Pro & Business Link-in-Bio',
                'description' => 'Compare Sayzio plans and pick the right one for you. Start free, then upgrade for custom branding, advanced analytics, more links and team workspaces.',
                'keywords' => 'pricing, link in bio pricing, biolink plans, free plan, pro plan, subscription, sayzio pricing',
            ],
            'creators' => [
                'label' => 'Creators directory',
                'group' => 'Discover',
                'url' => '/creators',
                'title' => 'Discover Creators — Browse Sayzio Profiles',
                'description' => 'Explore and follow creators on Sayzio. Browse public Link in Bio profiles, find new people to follow and discover what they share.',
                'keywords' => 'creators directory, discover creators, find creators, public profiles, follow creators, sayzio creators',
            ],
            'analytics' => [
                'label' => 'Analytics',
                'group' => 'Capabilities',
                'url' => '/analytics',
                'title' => 'Link Analytics & Insights — Track Every Click',
                'description' => 'Understand your audience with Sayzio analytics: real-time clicks, geographic heatmaps, devices, referrers and conversion tracking for every link and Link in Bio.',
                'keywords' => 'link analytics, click tracking, geographic heatmap, audience insights, conversion tracking, utm analytics',
            ],
            'audience' => [
                'label' => 'Audience & followers',
                'group' => 'Capabilities',
                'url' => '/audience',
                'title' => 'Audience & Followers — Grow Your Community',
                'description' => 'Turn visitors into followers and subscribers with Sayzio. Build an audience, capture email and WhatsApp subscribers, and bring people back with updates.',
                'keywords' => 'followers, audience growth, subscribers, email capture, whatsapp subscribers, community, creator audience',
            ],
            'integrations' => [
                'label' => 'Integrations',
                'group' => 'Capabilities',
                'url' => '/integrations',
                'title' => 'Integrations — Connect Sayzio to Your Stack',
                'description' => 'Embed and connect everything to your Sayzio page: Spotify, YouTube, Instagram, TikTok, Calendly, Typeform, tracking pixels and dozens more integrations.',
                'keywords' => 'integrations, embeds, spotify, youtube, instagram, tiktok, calendly, tracking pixels, sayzio integrations',
            ],
            'domains' => [
                'label' => 'Domains & URL aliases',
                'group' => 'Capabilities',
                'url' => '/domains',
                'title' => 'Custom Domains & URL Aliases — Brand Every Link',
                'description' => 'Launch on a branded Sayzio domain like 1in.me, bizs.club, getbio.one, sayzio.link or Sayzio.app, connect your own custom domain with simple CNAME verification, and give links memorable slugs with multiple aliases.',
                'keywords' => 'custom domain, branded domain, url alias, link slug, cname verification, vanity url, biolink domain, 1in.me, bizs.club, getbio.one, sayzio.app, sayzio.link',
            ],
            'api-docs' => [
                'label' => 'API documentation',
                'group' => 'Capabilities',
                'url' => '/docs/api',
                'title' => 'API Documentation — Build on the Sayzio REST API',
                'description' => 'Developer documentation for the Sayzio REST API: authenticate, manage links and biolinks, generate QR codes and read analytics programmatically.',
                'keywords' => 'api documentation, rest api, developer api, link api, biolink api, qr code api, sayzio api',
            ],
            'resume-builder' => [
                'label' => 'Résumé builder',
                'group' => 'Capabilities',
                'url' => '/resume-builder',
                'title' => 'Résumé & Portfolio Builder — A Shareable CV Link',
                'description' => 'Build a polished online résumé and portfolio with Sayzio and share it from a single link or QR code. Export to PDF and keep it always up to date.',
                'keywords' => 'resume builder, online cv, portfolio builder, shareable resume, pdf resume, personal page',
            ],
            'dialer-contacts' => [
                'label' => 'Dialer & Contacts',
                'group' => 'Capabilities',
                'url' => '/dialer-contacts',
                'title' => 'Smart Dialer & Contacts — Call, Sync & Turn Numbers Into Profiles',
                'description' => 'A T9 smart dialer, quick call / SMS / WhatsApp / Telegram channels, two-way Google Contacts sync, phone-to-biolink resolution, an AI business-card scanner and shareable vCards — your whole address book, supercharged on Sayzio.',
                'keywords' => 'smart dialer, contacts, t9 dialer, google contacts sync, business card scanner, vcard, phone to biolink, speed dial, call log, caller id, sayzio dialer',
            ],
            'ai-marketing-strategist' => [
                'label' => 'AI Marketing Strategist',
                'group' => 'AI suite',
                'url' => '/ai-marketing-strategist',
                'title' => 'AI Marketing Strategist — A Growth Plan Built From Your Data',
                'description' => 'Sayzio\'s AI Marketing Strategist turns your goal and your own account data into a practical organic + paid marketing plan built around real Sayzio features — with one-click actions you can apply instantly.',
                'keywords' => 'ai marketing strategist, marketing strategy generator, ai growth plan, organic and paid marketing, link in bio marketing, ai marketing plan, sayzio ai',
            ],
            'forms' => [
                'label' => 'Form builder',
                'group' => 'Capabilities',
                'url' => '/forms',
                'title' => 'Form Builder — Collect Leads & Responses from Your Page',
                'description' => 'Build forms with 21 field types, full design customization, and instant email, SMS and webhook notifications — then embed them in any Sayzio Link in Bio page.',
                'keywords' => 'form builder, online forms, lead capture form, contact form, survey, webhook form, biolink form, sayzio forms',
            ],
            'notifications' => [
                'label' => 'Notifications',
                'group' => 'Capabilities',
                'url' => '/notifications',
                'title' => 'Notifications — In-App, Email & Push Alerts Your Way',
                'description' => 'Stay on top of everything on Sayzio with a unified notification feed plus in-app, email and mobile push alerts, and per-event preferences across 20+ event types.',
                'keywords' => 'notifications, notification preferences, push notifications, email alerts, in-app notifications, notification feed, sayzio notifications',
            ],
            'compare-index' => [
                'label' => 'Compare overview',
                'group' => 'Compare',
                'url' => '/compare',
                'title' => 'Compare Sayzio vs Linktree, Beacons, Bitly & More',
                'description' => 'See how Sayzio stacks up against the link-in-bio and short-link tools you already use across ' . ComparisonContent::totalFeatures() . ' features — Link in Bio pages, short links, QR codes, analytics, monetisation and more.',
                'keywords' => 'link in bio comparison, linktree alternative, beacons alternative, bitly alternative, biolink comparison, sayzio vs',
            ],
        ];

        foreach (ComparisonContent::rivalKeys() as $key) {
            $data = ComparisonContent::competitor($key);
            if (!is_array($data)) {
                continue;
            }
            $name = (string) ($data['name'] ?? ucfirst($key));
            $pages['compare-' . $key] = [
                'label' => 'Sayzio vs ' . $name,
                'group' => 'Compare',
                'url' => '/compare/' . $key,
                'title' => ComparisonContent::shareTitle($data),
                'description' => ComparisonContent::shareDescription($data),
                'keywords' => strtolower($name) . ' alternative, sayzio vs ' . strtolower($name) . ', ' . strtolower($name) . ' comparison, link in bio, biolink, switch from ' . strtolower($name),
            ];
        }

        return $pages;
    }

    /**
     * Seeded keyword defaults for the marketing pages that ARE backed by a
     * `site_pages` row. Title + description for these already ship as seeded
     * row values; only keywords are net-new, so we keep their defaults here
     * (and the seeding migration copies them onto the rows).
     */
    public static function sitePageKeywordDefaults(): array
    {
        return [
            'features' => 'features, biolink builder, short links, qr codes, analytics, forms, link management, sayzio features',
            'how-it-works' => 'how it works, getting started, link in bio guide, setup, biolink tutorial, sayzio guide',
            'about' => 'about sayzio, our story, company, link in bio platform, team, mission',
            'contact' => 'contact, support, get in touch, help, customer service, sayzio contact',
            'faqs' => 'faq, frequently asked questions, help, support, link in bio questions, sayzio faq',
            'terms' => 'terms of service, terms and conditions, user agreement, legal, sayzio terms',
            'refunds' => 'refund policy, refunds, billing, money back, cancellation, sayzio refunds',
            'privacy' => 'privacy policy, data protection, personal data, gdpr, privacy, sayzio privacy',
            'gdpr' => 'gdpr, data protection, eu privacy, data rights, compliance, sayzio gdpr',
            'cookies' => 'cookie policy, cookies, tracking, consent, privacy, sayzio cookies',
            'discovery' => 'discover, explore profiles, find creators, public biolinks, directory, sayzio discovery',
            'creators-feed' => 'creators feed, creator posts, follow creators, social feed, updates, sayzio feed',
            'workspace-team' => 'workspaces, team, collaboration, roles, permissions, agency, multi client, sayzio team',
            'buzz' => 'social proof, buzz, live notifications, conversion, visitor activity, sayzio buzz',
            'services' => 'use cases, link in bio for business, creators, agencies, portfolio, marketing, sayzio services',
            'ai-chatbot' => 'ai chatbot, biolink chatbot, lead capture, automated chat, ai assistant, sayzio ai',
            'ai-agent' => 'ai agent, automation, ai tasks, lead qualification, workflows, ai teammate, sayzio ai',
            'ai-widget' => 'ai widget, website chatbot, embeddable ai, ai assistant, lead capture, sayzio ai',
            'ai-voice-assistant' => 'ai voice assistant, ai receptionist, call answering, voice ai, missed call, sayzio ai',
            'whatsapp-agent' => 'whatsapp agent, whatsapp ai, build links on whatsapp, whatsapp link builder, qr code on whatsapp, voice note links, sayzio ai',
            'for-creators' => 'link in bio for creators, creator tools, monetise audience, biolink, influencer, sayzio for creators',
            'for-agencies' => 'link in bio for agencies, agency tools, multi client, white label, workspaces, sayzio for agencies',
            'for-coaches' => 'link in bio for coaches, coaching tools, booking, lead capture, biolink, sayzio for coaches',
            'for-musicians' => 'link in bio for musicians, music smart link, spotify, streaming links, biolink, sayzio for musicians',
            'for-small-business' => 'link in bio for small business, business page, booking, contact, biolink, sayzio for business',
        ];
    }

    /**
     * Human labels + grouping for the site-page-backed marketing pages, used
     * by the unified admin SEO screen. Order here is the display order.
     */
    public static function sitePageLabels(): array
    {
        return [
            'features' => ['label' => 'Features', 'group' => 'Capabilities'],
            'how-it-works' => ['label' => 'How it works', 'group' => 'Capabilities'],
            'workspace-team' => ['label' => 'Workspaces & team', 'group' => 'Capabilities'],
            'buzz' => ['label' => 'Buzz (social proof)', 'group' => 'Capabilities'],
            'services' => ['label' => 'Use cases (services)', 'group' => 'Capabilities'],
            'ai-chatbot' => ['label' => 'AI chatbot', 'group' => 'AI suite'],
            'ai-agent' => ['label' => 'AI agent', 'group' => 'AI suite'],
            'ai-widget' => ['label' => 'AI widget', 'group' => 'AI suite'],
            'ai-voice-assistant' => ['label' => 'AI voice assistant', 'group' => 'AI suite'],
            'whatsapp-agent' => ['label' => 'WhatsApp agent', 'group' => 'AI suite'],
            'for-creators' => ['label' => 'Sayzio for creators', 'group' => 'Use cases'],
            'for-agencies' => ['label' => 'Sayzio for agencies', 'group' => 'Use cases'],
            'for-coaches' => ['label' => 'Sayzio for coaches', 'group' => 'Use cases'],
            'for-musicians' => ['label' => 'Sayzio for musicians', 'group' => 'Use cases'],
            'for-small-business' => ['label' => 'Sayzio for small business', 'group' => 'Use cases'],
            'discovery' => ['label' => 'Discovery', 'group' => 'Discover'],
            'creators-feed' => ['label' => 'Creators feed', 'group' => 'Discover'],
            'about' => ['label' => 'About', 'group' => 'Company'],
            'contact' => ['label' => 'Contact', 'group' => 'Company'],
            'faqs' => ['label' => 'FAQs', 'group' => 'Company'],
            'terms' => ['label' => 'Terms of service', 'group' => 'Legal'],
            'privacy' => ['label' => 'Privacy policy', 'group' => 'Legal'],
            'refunds' => ['label' => 'Refunds policy', 'group' => 'Legal'],
            'gdpr' => ['label' => 'GDPR statement', 'group' => 'Legal'],
            'cookies' => ['label' => 'Cookie policy', 'group' => 'Legal'],
        ];
    }

    /** Slugs of the marketing pages backed by a `site_pages` row. */
    public static function sitePageSlugs(): array
    {
        return array_keys(self::sitePageLabels());
    }

    /**
     * The public URL path for a `site_pages`-backed slug. Most pages live at
     * `/{slug}`; the use-case pages are mounted under `/for/{persona}` and
     * their rows are stored as `for-{persona}` (see SitePageController::useCase).
     */
    public static function sitePagePath(string $slug): string
    {
        if (str_starts_with($slug, 'for-')) {
            return '/for/' . substr($slug, 4);
        }

        return '/' . $slug;
    }

    /**
     * Every public marketing URL for the XML sitemap, kept in lockstep with
     * the SEO registry: the code-driven pages (their seeded `url`) plus the
     * `site_pages`-backed pages. Returns a de-duplicated list of
     * `['path' => string, 'slug' => ?string]`; `slug` is set only for
     * site-page-backed entries so the caller can resolve a per-row lastmod.
     */
    public static function sitemapPaths(): array
    {
        $paths = [];

        foreach (self::codeDrivenDefaults() as $def) {
            $url = trim((string) ($def['url'] ?? ''));
            if ($url !== '') {
                $paths[$url] = ['path' => $url, 'slug' => null];
            }
        }

        foreach (self::sitePageSlugs() as $slug) {
            $url = self::sitePagePath($slug);
            // Don't let a site-page entry clobber a code-driven one that
            // already claimed the same path.
            if (!isset($paths[$url])) {
                $paths[$url] = ['path' => $url, 'slug' => $slug];
            }
        }

        return array_values($paths);
    }

    /** The full per-page override map for code-driven pages. */
    public static function overrides(): array
    {
        $value = AppSetting::get(self::SETTING_KEY, []);
        return is_array($value) ? $value : [];
    }

    /**
     * Resolve SEO for a single code-driven page key:
     * admin override (per field) → seeded default → app name / ''.
     */
    public static function resolveCode(string $key): array
    {
        $defaults = self::codeDrivenDefaults();
        $def = is_array($defaults[$key] ?? null) ? $defaults[$key] : [];
        $ov = self::overrides()[$key] ?? [];
        $ov = is_array($ov) ? $ov : [];

        $appName = config('app.name', 'Sayzio');

        return [
            'title' => self::firstNonEmpty([$ov['title'] ?? null, $def['title'] ?? null]) ?? $appName,
            'description' => self::firstNonEmpty([$ov['description'] ?? null, $def['description'] ?? null]) ?? '',
            'keywords' => self::firstNonEmpty([$ov['keywords'] ?? null, $def['keywords'] ?? null]) ?? '',
        ];
    }

    /**
     * Resolve SEO for a `site_pages`-backed page: the row's explicit
     * admin-edited values, falling back to the seeded keyword default when
     * the admin hasn't set keywords yet.
     */
    public static function resolveSitePage(SitePage $page): array
    {
        $appName = config('app.name', 'Sayzio');

        $title = trim((string) $page->title) !== '' ? (string) $page->title : $appName;
        $description = (string) ($page->meta_description ?? '');

        $keywords = trim((string) ($page->meta_keywords ?? ''));
        if ($keywords === '') {
            $keywords = self::sitePageKeywordDefaults()[$page->slug] ?? '';
        }

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
        ];
    }

    /**
     * Central resolver used by the public site layout (and the home/creators
     * standalone heads). Picks the right strategy from the rendering context:
     *
     *   - 'seoKey'  → code-driven page (registry + AppSetting override)
     *   - 'page'    → a SitePage model (row values + keyword default)
     *   - otherwise → legacy behaviour for out-of-scope pages (blogs, etc.)
     *
     * Always returns ['title','description','keywords'].
     */
    public static function resolveForView(array $ctx): array
    {
        $appName = config('app.name', 'Sayzio');

        $seoKey = $ctx['seoKey'] ?? null;
        if (is_string($seoKey) && $seoKey !== '') {
            return self::resolveCode($seoKey);
        }

        $page = $ctx['page'] ?? null;
        if ($page instanceof SitePage) {
            return self::resolveSitePage($page);
        }

        // Out-of-scope / generic pages: preserve the previous head behaviour
        // (explicit @section('title') / $shareTitle, then $page->title).
        $title = self::firstNonEmpty([
            $ctx['yieldTitle'] ?? null,
            $ctx['shareTitle'] ?? null,
            is_object($page) ? ($page->title ?? null) : null,
        ]) ?? $appName;

        $description = self::firstNonEmpty([
            $ctx['shareDescription'] ?? null,
            is_object($page) ? ($page->meta_description ?? null) : null,
        ]) ?? '';

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => '',
        ];
    }

    /** Return the first trimmed non-empty string from the list, or null. */
    private static function firstNonEmpty(array $candidates): ?string
    {
        foreach ($candidates as $c) {
            if ($c === null) {
                continue;
            }
            $s = trim((string) $c);
            if ($s !== '') {
                return $s;
            }
        }
        return null;
    }
}
