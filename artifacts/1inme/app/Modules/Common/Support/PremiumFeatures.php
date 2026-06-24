<?php

namespace App\Modules\Common\Support;

/**
 * Single source of plain-English descriptions for every plan feature
 * key (and which plans unlock it). Read by both the public web pages
 * (Premium Features page, Pricing page) and the mobile API (so the
 * Premium Features screen can render the same copy without duplicating
 * the catalogue across platforms).
 */
class PremiumFeatures
{
    /**
     * Grouped catalogue: each entry is one premium feature, with
     * `key`, human `name`, plain-language `description`, optional
     * numeric `unit` label and the feature `group`.
     */
    public static function catalogue(): array
    {
        return [
            // ---- Limits ----
            ['key' => 'max_links', 'group' => 'Limits', 'name' => 'Short links', 'description' => 'How many short links and biolink pages you can create across all of your projects.', 'unit' => 'links'],
            ['key' => 'max_biolinks', 'group' => 'Limits', 'name' => 'Link in Bio pages', 'description' => 'How many separate biolink (Link in Bio) pages you can publish.', 'unit' => 'pages'],
            ['key' => 'max_projects', 'group' => 'Limits', 'name' => 'Projects', 'description' => 'Buckets you can use to organize your links into separate brands or campaigns.', 'unit' => 'projects'],
            ['key' => 'max_file_size_mb', 'group' => 'Limits', 'name' => 'Max upload size', 'description' => 'The largest single file you can upload (PDFs, images, downloadable files for your link blocks).', 'unit' => 'MB / file'],
            ['key' => 'storage_limit_mb', 'group' => 'Limits', 'name' => 'Storage', 'description' => 'Total disk space available for uploaded files, images and downloads across your account.', 'unit' => 'MB total'],
            ['key' => 'contacts_max', 'group' => 'Limits', 'name' => 'Contacts', 'description' => 'How many contacts (CRM entries from forms, dialer, follower opt-ins) you can keep stored.', 'unit' => 'contacts'],
            ['key' => 'max_workspaces', 'group' => 'Limits', 'name' => 'Team workspaces', 'description' => 'How many separate team workspaces you can own — each one has its own links, members and billing scope.', 'unit' => 'workspaces'],
            ['key' => 'max_seats_per_workspace', 'group' => 'Limits', 'name' => 'Seats per workspace', 'description' => 'How many teammates you can invite into each workspace to collaborate on links and posts.', 'unit' => 'seats'],

            // ---- Tracking & analytics ----
            ['key' => 'pixels', 'group' => 'Tracking & analytics', 'name' => 'Marketing pixels', 'description' => 'Drop Meta, TikTok, Google Ads and other tracking pixels onto your links and biolink pages so your ad campaigns can attribute clicks and conversions.'],
            ['key' => 'utm_params', 'group' => 'Tracking & analytics', 'name' => 'UTM parameters', 'description' => 'Auto-append UTM source/medium/campaign tags to your links so the visits show up cleanly in Google Analytics and other tools.'],
            ['key' => 'analytics', 'group' => 'Tracking & analytics', 'name' => 'Analytics depth', 'description' => 'Basic plans show clicks and top countries; advanced unlocks geographic, device, referrer and cohort breakdowns plus the Performance Coach.'],
            ['key' => 'analytics_export', 'group' => 'Tracking & analytics', 'name' => 'Stats CSV export', 'description' => 'Download your link click logs, follower lists and slide analytics as CSV files for spreadsheets and offline reporting.'],
            ['key' => 'audience_followers', 'group' => 'Tracking & analytics', 'name' => 'Audience & followers', 'description' => 'Lightweight viewer accounts, live follower counts, daily digest emails and one-tap follow buttons on every social block — so visitors become an audience you actually own.'],

            // ---- Audience growth ----
            ['key' => 'social_integrations', 'group' => 'Audience growth', 'name' => 'Social integrations', 'description' => 'Plug in Instagram, TikTok, Facebook, X, LinkedIn and Pinterest with a single tap. Connections auto-retry when tokens expire and you get notified the moment something needs your attention.'],
            ['key' => 'scheduling', 'group' => 'Audience growth', 'name' => 'Scheduling', 'description' => 'Schedule blocks and pages to publish or expire on a date and time you set, with visitor-timezone-aware drops and a test-send for digest emails.'],
            ['key' => 'events_rsvps', 'group' => 'Audience growth', 'name' => 'Events & RSVPs', 'description' => 'Run launches, drops and meet-ups inside your biolink with countdown blocks, RSVP capture, reminder emails, capacity caps and add-to-calendar (.ics) downloads.'],
            ['key' => 'referrals', 'group' => 'Audience growth', 'name' => 'Referral program', 'description' => 'Give every creator a personal /r/<code> share link, custom referral codes and live tracking of who joined through them — turn happy users into your biggest growth channel.'],

            // ---- Domains & SEO ----
            ['key' => 'custom_domains', 'group' => 'Domains & SEO', 'name' => 'Custom domains', 'description' => 'Connect your own domain (yourbrand.com) so short links and biolink pages live under your URL instead of 1inme.co.'],
            ['key' => 'seo_settings', 'group' => 'Domains & SEO', 'name' => 'SEO & social previews', 'description' => 'Edit page title, meta description, Open Graph image and canonical URL on every link so they look great in search and when shared.'],

            // ---- Security & control ----
            ['key' => 'link_protection', 'group' => 'Security & control', 'name' => 'Link protection', 'description' => 'Lock individual links with a password, an expiry date, a max-clicks cap or geo/device rules.'],
            ['key' => 'qr_customization', 'group' => 'Security & control', 'name' => 'Custom QR codes', 'description' => 'Style the QR codes generated for each link — colors, logo in the centre, dot shape and frame.'],

            // ---- Forms & lead capture ----
            ['key' => 'custom_forms', 'group' => 'Forms & lead capture', 'name' => 'Custom forms', 'description' => 'Build branded lead-capture forms inside your biolink pages, route submissions to your inbox or a webhook, and export them.'],

            // ---- Contacts & CRM ----
            ['key' => 'contacts_google_sync', 'group' => 'Contacts & CRM', 'name' => 'Google Contacts sync', 'description' => 'Two-way sync your 1INME contacts with your Google Contacts account so you can call and message them from your phone.'],

            // ---- Team & collaboration ----
            ['key' => 'teams', 'group' => 'Team & collaboration', 'name' => 'Team workspaces', 'description' => 'Invite teammates to collaborate on the same links, biolink pages and inbox with role-based permissions.'],

            // ---- Branding ----
            ['key' => 'custom_branding', 'group' => 'Branding', 'name' => 'White-label branding', 'description' => 'Replace 1INME branding with your own colors, logo and footer attribution on every public page.'],
            ['key' => 'remove_branding', 'group' => 'Branding', 'name' => 'Remove "powered by" badge', 'description' => 'Hide the small "powered by 1INME" wordmark from the bottom of your public biolink pages.'],
            ['key' => 'custom_favicon', 'group' => 'Branding', 'name' => 'Custom favicon', 'description' => 'Use your own favicon (browser tab icon) on every public page served from your account or custom domain.'],
            ['key' => 'custom_code', 'group' => 'Branding', 'name' => 'Custom HTML / JS', 'description' => 'Drop in custom <head> snippets, scripts and CSS overrides for advanced theming and integrations.'],

            // ---- Selling ----
            ['key' => 'ecommerce', 'group' => 'Selling', 'name' => 'Sell from your bio', 'description' => 'Add product blocks with prices and checkout to your biolink pages so you can sell directly from your link.'],

            // ---- AI suite ----
            ['key' => 'ai_chatbot', 'group' => 'AI suite', 'name' => 'AI Chatbot', 'description' => 'A 24/7 AI chatbot on your biolink that answers visitor questions in your voice, captures leads and books calls.'],
            ['key' => 'ai_agent', 'group' => 'AI suite', 'name' => 'AI Agent', 'description' => 'A multi-step AI agent that runs playbooks across your contacts, inbox and calendar — qualifying leads and following up on its own.'],
            ['key' => 'ai_widget', 'group' => 'AI suite', 'name' => 'AI Widget', 'description' => 'Embed an AI assistant on any external site with a single snippet — answers questions and captures leads into your unified inbox.'],
            ['key' => 'ai_voice_assistant', 'group' => 'AI suite', 'name' => 'AI Voice Assistant', 'description' => 'AI receptionist that picks up calls to your number, qualifies callers and books or routes them — no missed leads.'],
        ];
    }

    /**
     * Build a list of which plan slugs unlock each feature, computed
     * from the plan rows (avoids duplicating "Pro & up" in copy).
     * Returns: ['feature_key' => ['pro','business','enterprise'], ...]
     */
    public static function unlocksByFeature(iterable $plans): array
    {
        $out = [];
        foreach (self::catalogue() as $entry) {
            $key = $entry['key'];
            $slugs = [];
            foreach ($plans as $p) {
                $features = is_array($p->features ?? null) ? $p->features : [];
                if (!array_key_exists($key, $features)) continue;
                $val = $features[$key];
                $unlocked = match (true) {
                    is_bool($val)            => $val === true,
                    is_int($val) || is_float($val) => $val !== 0,
                    is_string($val)          => $val !== '' && strtolower($val) !== 'basic' && strtolower($val) !== 'none',
                    default                  => false,
                };
                if ($unlocked) {
                    $slugs[] = $p->slug;
                }
            }
            $out[$key] = $slugs;
        }
        return $out;
    }
}
