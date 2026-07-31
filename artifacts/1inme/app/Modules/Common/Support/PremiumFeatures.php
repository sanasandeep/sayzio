<?php

namespace App\Modules\Common\Support;

/**
 * Single source of plain-English descriptions for every plan feature
 * key (and which plans unlock it). Read by both the public web pages
 * (Premium Features page, Pricing page) and the mobile API (so the
 * Premium Features screen can render the same copy without duplicating
 * the catalogue across platforms).
 *
 * This catalogue is the canonical documentation for every plan-gated
 * knob the admin "Create / Edit Plan" form understands
 * ({@see PlanFormCatalogue}). The drift-guard unit test
 * {@see \Tests\Unit\PremiumFeaturesCatalogueDriftTest} fails the build if
 * any quantity-limit, feature-flag or AI-suite key the form exposes is
 * missing a catalogue entry, so the public Premium Features grid, the
 * /pricing comparison and the mobile billing API can never silently fall
 * out of sync with what plans actually grant.
 */
class PremiumFeatures
{
    /**
     * Grouped catalogue: each entry is one premium feature, with
     * `key`, human `name`, plain-language `description`, optional
     * numeric `unit` label and the feature `group`.
     *
     * Presence of a `unit` marks the entry as a numeric allowance
     * (rendered as a number / "Unlimited" / "—"); the special `analytics`
     * key is a select; every other entry is a boolean capability. See
     * {@see self::kindOf()} / {@see self::resolveCell()}.
     */
    public static function catalogue(): array
    {
        return [
            // ---- Limits ----
            ['key' => 'max_links', 'group' => 'Limits', 'name' => 'Short links', 'description' => 'How many short links you can create across all of your projects.', 'unit' => 'links'],
            ['key' => 'max_biolinks', 'group' => 'Limits', 'name' => 'Link in Bio pages', 'description' => 'How many separate Link in Bio pages you can publish.', 'unit' => 'pages'],
            ['key' => 'max_projects', 'group' => 'Limits', 'name' => 'Projects', 'description' => 'Buckets you can use to organize your links into separate brands or campaigns.', 'unit' => 'projects'],
            ['key' => 'storage_limit_mb', 'group' => 'Limits', 'name' => 'Storage', 'description' => 'Total disk space available for uploaded files, images and downloads across your account.', 'unit' => 'MB total'],
            ['key' => 'max_file_size_mb', 'group' => 'Limits', 'name' => 'Max upload size', 'description' => 'The largest single file you can upload (PDFs, images, downloadable files for your link blocks).', 'unit' => 'MB / file'],
            ['key' => 'contacts_max', 'group' => 'Limits', 'name' => 'Contacts', 'description' => 'How many contacts (CRM entries from forms, dialer, follower opt-ins) you can keep stored.', 'unit' => 'contacts'],
            ['key' => 'max_workspaces', 'group' => 'Limits', 'name' => 'Team workspaces', 'description' => 'How many separate team workspaces you can own — each one has its own links, members and billing scope.', 'unit' => 'workspaces'],
            ['key' => 'max_seats_per_workspace', 'group' => 'Limits', 'name' => 'Seats per workspace', 'description' => 'How many teammates you can invite into each workspace to collaborate on links and posts.', 'unit' => 'seats'],

            // ---- Pages & link types ----
            ['key' => 'max_conversational', 'group' => 'Pages & link types', 'name' => 'Conversational pages', 'description' => 'How many chat-style conversational link pages you can publish.', 'unit' => 'pages'],
            ['key' => 'max_slides', 'group' => 'Pages & link types', 'name' => 'Slides pages', 'description' => 'How many swipeable slide-deck link pages you can publish.', 'unit' => 'pages'],
            ['key' => 'max_ai_chat', 'group' => 'Pages & link types', 'name' => 'AI Chatbot pages', 'description' => 'How many standalone AI chatbot link pages (with their own persona) you can publish.', 'unit' => 'pages'],
            ['key' => 'max_restaurant_menu', 'group' => 'Pages & link types', 'name' => 'Restaurant Menu pages', 'description' => 'How many restaurant / digital menu link pages — with categories, items and table QR ordering — you can publish.', 'unit' => 'pages'],
            ['key' => 'max_store_menu', 'group' => 'Pages & link types', 'name' => 'Store Menu pages', 'description' => 'How many store / product catalog link pages — with categories, products and an order-request cart — you can publish.', 'unit' => 'pages'],
            ['key' => 'max_service_booking', 'group' => 'Pages & link types', 'name' => 'Service Booking pages', 'description' => 'How many service booking / appointment-request link pages — with services, availability and a bookings dashboard — you can publish.', 'unit' => 'pages'],
            ['key' => 'max_service_booking_staff', 'group' => 'Pages & link types', 'name' => 'Booking staff members', 'description' => 'How many staff / team members each service booking page can list, each with their own services, hours and days off.', 'unit' => 'staff'],
            ['key' => 'service_booking_calendar_sync', 'group' => 'Pages & link types', 'name' => 'Booking calendar sync', 'description' => 'Two-way Google Calendar sync for booking pages: busy time blocks slots and confirmed bookings appear on your calendar.'],
            ['key' => 'max_reviews', 'group' => 'Pages & link types', 'name' => 'Reviews pages', 'description' => 'How many review-collection link pages (native plus imported Google / Trustpilot reviews) you can publish.', 'unit' => 'pages'],
            ['key' => 'max_resume', 'group' => 'Pages & link types', 'name' => 'Resume / Portfolio pages', 'description' => 'How many shareable resume / portfolio pages — with versions and PDF export — you can publish.', 'unit' => 'pages'],
            ['key' => 'max_calendars', 'group' => 'Pages & link types', 'name' => 'Calendars', 'description' => 'How many followable calendar link pages (events, ICS feed, Google sync) you can publish.', 'unit' => 'calendars'],
            ['key' => 'max_calendar_events', 'group' => 'Pages & link types', 'name' => 'Events per calendar', 'description' => 'How many events each calendar page can hold.', 'unit' => 'events / calendar'],
            ['key' => 'max_brand_kit_pages', 'group' => 'Pages & link types', 'name' => 'Brand / Press Kit pages', 'description' => 'How many shareable Brand / Press Kit pages (logos, colours, fonts, voice, boilerplate) you can publish.', 'unit' => 'pages'],
            ['key' => 'max_paid_page', 'group' => 'Pages & link types', 'name' => 'Paid / members pages', 'description' => 'How many paid, members-only content pages you can publish — gated behind a follow, subscription or one-off payment.', 'unit' => 'pages'],
            ['key' => 'max_updates_pages', 'group' => 'Pages & link types', 'name' => 'Updates / Changelog pages', 'description' => 'How many public changelog pages — dated entries with follower notifications — you can publish.', 'unit' => 'pages'],
            ['key' => 'max_text_pages', 'group' => 'Pages & link types', 'name' => 'Text Pages', 'description' => 'How many Text Pages — paste any text and share it as a clean page with a copy button — you can publish.', 'unit' => 'pages'],
            ['key' => 'calendar_sync', 'group' => 'Pages & link types', 'name' => 'Calendar sync', 'description' => 'Two-way sync between your calendar events and Google Calendar so updates flow both ways automatically.'],

            // ---- Short links & aliases ----
            ['key' => 'max_aliases_per_link', 'group' => 'Short links', 'name' => 'Extra aliases per link', 'description' => 'How many additional custom aliases you can point at the same link beyond its primary one.', 'unit' => 'aliases / link'],
            ['key' => 'min_alias_length', 'group' => 'Short links', 'name' => 'Minimum alias length', 'description' => 'The shortest custom alias this plan can claim — paid tiers can grab punchier, shorter links.', 'unit' => 'characters'],
            ['key' => 'max_alias_length', 'group' => 'Short links', 'name' => 'Maximum alias length', 'description' => 'The longest custom alias this plan can use (hard cap is 191 characters).', 'unit' => 'characters'],

            // ---- Tracking & analytics ----
            ['key' => 'pixels', 'group' => 'Tracking & analytics', 'name' => 'Marketing pixels', 'description' => 'Drop Meta, TikTok, Google Ads and other tracking pixels onto your links and Link in Bio pages so your ad campaigns can attribute clicks and conversions.'],
            ['key' => 'utm_params', 'group' => 'Tracking & analytics', 'name' => 'UTM parameters', 'description' => 'Auto-append UTM source/medium/campaign tags to your links so the visits show up cleanly in Google Analytics and other tools.'],
            ['key' => 'analytics', 'group' => 'Tracking & analytics', 'name' => 'Analytics depth', 'description' => 'Basic plans show clicks and top countries; advanced unlocks geographic, device, referrer and cohort breakdowns plus the Performance Coach.'],
            ['key' => 'analytics_export', 'group' => 'Tracking & analytics', 'name' => 'Stats CSV export', 'description' => 'Download your link click logs, follower lists and slide analytics as CSV files for spreadsheets and offline reporting.'],
            ['key' => 'stats_retention_days', 'group' => 'Tracking & analytics', 'name' => 'Stats history', 'description' => 'How far back your analytics reach. Older click and session history is pruned beyond this window.', 'unit' => 'days'],
            ['key' => 'audience_followers', 'group' => 'Tracking & analytics', 'name' => 'Audience & followers', 'description' => 'Lightweight viewer accounts, live follower counts, daily digest emails and one-tap follow buttons on every social block — so visitors become an audience you actually own.'],

            // ---- Audience growth ----
            ['key' => 'social_integrations', 'group' => 'Audience growth', 'name' => 'Social integrations', 'description' => 'Plug in Instagram, TikTok, Facebook, X, LinkedIn and Pinterest with a single tap. Connections auto-retry when tokens expire and you get notified the moment something needs your attention.'],
            ['key' => 'scheduling', 'group' => 'Audience growth', 'name' => 'Scheduling', 'description' => 'Schedule blocks and pages to publish or expire on a date and time you set, with visitor-timezone-aware drops and a test-send for digest emails.'],
            ['key' => 'scheduled_posts', 'group' => 'Audience growth', 'name' => 'Scheduled posts', 'description' => 'Queue Link in Bio and social posts in advance so your page keeps fresh content dropping on a schedule.'],
            ['key' => 'social_proof_popup', 'group' => 'Audience growth', 'name' => 'Social proof pop-ups', 'description' => 'Live "X people just signed up / bought" style notifications on your Link in Bio pages to build trust and urgency.'],
            ['key' => 'events_rsvps', 'group' => 'Audience growth', 'name' => 'Events & RSVPs', 'description' => 'Run launches, drops and meet-ups inside your Link in Bio with countdown blocks, RSVP capture, reminder emails, capacity caps and add-to-calendar (.ics) downloads.'],
            ['key' => 'referrals', 'group' => 'Audience growth', 'name' => 'Referral program', 'description' => 'Give every creator a personal /r/<code> share link, custom referral codes and live tracking of who joined through them — turn happy users into your biggest growth channel.'],

            // ---- Domains & SEO ----
            ['key' => 'custom_domains', 'group' => 'Domains & SEO', 'name' => 'Custom domains', 'description' => 'Connect your own domain (yourbrand.com) so short links and Link in Bio pages live under your URL instead of 1inme.co.'],
            ['key' => 'max_custom_domains', 'group' => 'Domains & SEO', 'name' => 'Custom domains included', 'description' => 'How many of your own domains you can connect and verify on this plan.', 'unit' => 'domains'],
            ['key' => 'seo_settings', 'group' => 'Domains & SEO', 'name' => 'SEO & social previews', 'description' => 'Edit page title, meta description, Open Graph image and canonical URL on every link so they look great in search and when shared.'],

            // ---- Security & control ----
            ['key' => 'link_protection', 'group' => 'Security & control', 'name' => 'Link protection', 'description' => 'The master switch for the per-link protection controls below — password, expiry, targeting, smart rules and active windows.'],
            ['key' => 'link_password', 'group' => 'Security & control', 'name' => 'Password-protected links', 'description' => 'Require a visitor-supplied password before a link redirects to its destination.'],
            ['key' => 'link_expiry', 'group' => 'Security & control', 'name' => 'Link expiry', 'description' => 'Schedule a link to stop redirecting after a chosen date or once it hits a maximum click count.'],
            ['key' => 'link_geo_targeting', 'group' => 'Security & control', 'name' => 'Geo targeting', 'description' => 'Send visitors to different destinations depending on their country or region.'],
            ['key' => 'link_device_targeting', 'group' => 'Security & control', 'name' => 'Device targeting', 'description' => 'Send visitors to different destinations depending on their device, OS or browser.'],
            ['key' => 'link_deep_link', 'group' => 'Security & control', 'name' => 'Deep links', 'description' => 'Open native mobile apps directly when a link is tapped, with a web fallback when the app is not installed.'],
            ['key' => 'link_smart_rules', 'group' => 'Security & control', 'name' => 'Smart redirect rules', 'description' => 'Compose multiple targeting rules with priority and fallback so one link routes everyone to exactly the right place.'],
            ['key' => 'link_active_window', 'group' => 'Security & control', 'name' => 'Active windows', 'description' => 'Only let a link redirect during the specific days and times you choose.'],
            ['key' => 'qr_customization', 'group' => 'Security & control', 'name' => 'Custom QR codes', 'description' => 'Style the QR codes generated for each link — colors, logo in the centre, dot shape and frame.'],

            // ---- Forms & lead capture ----
            ['key' => 'custom_forms', 'group' => 'Forms & lead capture', 'name' => 'Custom forms', 'description' => 'Build branded lead-capture forms inside your Link in Bio pages, route submissions to your inbox or a webhook, and export them.'],
            ['key' => 'max_forms', 'group' => 'Forms & lead capture', 'name' => 'Forms included', 'description' => 'How many separate custom form definitions you can publish.', 'unit' => 'forms'],
            ['key' => 'paid_forms', 'group' => 'Forms & lead capture', 'name' => 'Paid forms', 'description' => 'Charge customers to submit a form, collected through your own connected payment gateway (0% platform fee).'],
            ['key' => 'form_analytics_advanced', 'group' => 'Forms & lead capture', 'name' => 'Advanced form analytics', 'description' => 'Submission trends over time, per-field completion and drop-off, device and geo breakdowns and per-form revenue.'],
            ['key' => 'leads', 'group' => 'Forms & lead capture', 'name' => 'Lead capture', 'description' => 'A dedicated inbound lead inbox that collects and organises submissions from your forms and pages.'],
            ['key' => 'max_leads', 'group' => 'Forms & lead capture', 'name' => 'Leads included', 'description' => 'How many lead-capture entries this plan can collect and store.', 'unit' => 'leads'],

            // ---- Contacts & CRM ----
            ['key' => 'contacts_google_sync', 'group' => 'Contacts & CRM', 'name' => 'Google Contacts sync', 'description' => 'Two-way sync your Sayzio contacts with your Google Contacts account so you can call and message them from your phone.'],
            ['key' => 'connected_apps', 'group' => 'Contacts & CRM', 'name' => 'Connected apps (CRM sync)', 'description' => 'Two-way sync your leads, subscribers and form submissions with Salesforce, HubSpot or Zoho, pull their contacts back into Sayzio, and forward click & conversion events to Google Analytics 4 — all on a scheduled background sync.'],

            // ---- Team & collaboration ----
            ['key' => 'teams', 'group' => 'Team & collaboration', 'name' => 'Team workspaces', 'description' => 'Invite teammates to collaborate on the same links, Link in Bio pages and inbox with role-based permissions.'],

            // ---- Account & support ----
            ['key' => 'creator_profile_public', 'group' => 'Account & support', 'name' => 'Public creator profile', 'description' => 'Expose a public-facing creator profile that collects all of your links and shows up in the creators discovery feed.'],
            ['key' => 'verification_eligible', 'group' => 'Account & support', 'name' => 'Verification eligible', 'description' => 'Makes accounts on this plan eligible to apply for the verified-creator badge.'],
            ['key' => 'priority_support', 'group' => 'Account & support', 'name' => 'Priority support', 'description' => 'Routes your requests to the priority support queue for faster responses.'],

            // ---- Tools & extras ----
            ['key' => 'files', 'group' => 'Tools & extras', 'name' => 'File manager', 'description' => 'Upload and organise files in the in-app file manager and attach them to your link blocks.'],
            ['key' => 'max_files', 'group' => 'Tools & extras', 'name' => 'Files included', 'description' => 'How many files you can keep in the in-app file manager.', 'unit' => 'files'],
            ['key' => 'vaults', 'group' => 'Tools & extras', 'name' => 'Encrypted vault', 'description' => 'A per-account encrypted vault for storing secrets, credentials and private notes.'],
            ['key' => 'max_vault_items', 'group' => 'Tools & extras', 'name' => 'Vault items included', 'description' => 'How many encrypted entries you can keep in the vault.', 'unit' => 'items'],
            ['key' => 'tasks', 'group' => 'Tools & extras', 'name' => 'Task boards', 'description' => 'Kanban-style task boards for organising personal or team work alongside your links.'],
            ['key' => 'max_task_boards', 'group' => 'Tools & extras', 'name' => 'Task boards included', 'description' => 'How many Kanban task boards you can create.', 'unit' => 'boards'],
            ['key' => 'buzz_popups', 'group' => 'Tools & extras', 'name' => 'Buzz pop-ups', 'description' => 'On-site notification pop-ups (recent activity, announcements, social proof) you can show on your pages.'],
            ['key' => 'max_buzz_items', 'group' => 'Tools & extras', 'name' => 'Buzz pop-ups included', 'description' => 'How many distinct Buzz notification pop-ups you can configure.', 'unit' => 'pop-ups'],
            ['key' => 'max_buzz_impressions', 'group' => 'Tools & extras', 'name' => 'Buzz views / month', 'description' => 'How many times your Buzz pop-ups can be shown each month before they pause until the next cycle.', 'unit' => 'views / mo'],
            ['key' => 'splash_pages', 'group' => 'Tools & extras', 'name' => 'Splash pages', 'description' => 'Branded coming-soon / landing pages you can publish ahead of a launch.'],
            ['key' => 'max_splash_pages', 'group' => 'Tools & extras', 'name' => 'Splash pages included', 'description' => 'How many branded splash / coming-soon pages you can publish.', 'unit' => 'pages'],
            ['key' => 'events', 'group' => 'Tools & extras', 'name' => 'Events', 'description' => 'Publish public event listings on your profile with details, dates and RSVPs.'],
            ['key' => 'max_events', 'group' => 'Tools & extras', 'name' => 'Events included', 'description' => 'How many event listings you can publish.', 'unit' => 'events'],
            ['key' => 'templates_premium', 'group' => 'Tools & extras', 'name' => 'Premium templates', 'description' => 'Unlock the premium template library for Link in Bio pages and the other page types.'],

            // ---- Webhooks & integrations ----
            ['key' => 'webhook_triggers', 'group' => 'Webhooks & integrations', 'name' => 'Outbound webhook triggers', 'description' => 'Receive real-time HTTP notifications (or email alerts) whenever a link is created, expires, or reaches a click milestone — connect Sayzio events to any automation tool, CRM or custom pipeline.'],

            // ---- Included coins ----
            ['key' => 'included_coins_monthly', 'group' => 'Included coins', 'name' => 'Monthly coin grant', 'description' => 'Coins credited to your wallet automatically each month as part of this plan — spend them on AI features, API overage and other coin-priced add-ons.', 'unit' => 'coins / mo'],
            ['key' => 'included_coins_yearly', 'group' => 'Included coins', 'name' => 'Yearly coin grant', 'description' => 'Coins credited to your wallet automatically each year as part of this plan — spend them on AI features, API overage and other coin-priced add-ons.', 'unit' => 'coins / yr'],

            // ---- Branding ----
            ['key' => 'custom_branding', 'group' => 'Branding', 'name' => 'White-label branding', 'description' => 'Replace Sayzio branding with your own colors, logo and footer attribution on every public page.'],
            ['key' => 'remove_branding', 'group' => 'Branding', 'name' => 'Remove "powered by" badge', 'description' => 'Hide the small "powered by Sayzio" wordmark from the bottom of your public Link in Bio pages.'],
            ['key' => 'custom_favicon', 'group' => 'Branding', 'name' => 'Custom favicon', 'description' => 'Use your own favicon (browser tab icon) on every public page served from your account or custom domain.'],
            ['key' => 'custom_code', 'group' => 'Branding', 'name' => 'Custom HTML / JS', 'description' => 'Drop in custom <head> snippets, scripts and CSS overrides for advanced theming and integrations.'],
            ['key' => 'max_brand_kits', 'group' => 'Branding', 'name' => 'AI brand kits', 'description' => 'How many AI-generated brand kits (palette, fonts, voice, taglines) you can save and reuse.', 'unit' => 'brand kits'],
            ['key' => 'brand_kit_assets', 'group' => 'Branding', 'name' => 'AI brand visual assets', 'description' => 'Generate ready-to-use brand images from your Brand Kit — logo, favicon, letterhead, social banners, avatar, share image, business card, email banner, background and watermark.'],
            ['key' => 'max_brand_asset_versions', 'group' => 'Branding', 'name' => 'Brand asset generations', 'description' => 'How many times each Brand Kit visual asset can be generated or regenerated.', 'unit' => 'generations / asset'],

            // ---- Selling ----
            ['key' => 'ecommerce', 'group' => 'Selling', 'name' => 'Sell from your bio', 'description' => 'Add product blocks with prices and checkout to your Link in Bio pages so you can sell directly from your link.'],

            // ---- Developer API ----
            ['key' => 'api_access', 'group' => 'Developer API', 'name' => 'Public API access', 'description' => 'Generate API tokens and call the public REST API to manage links, pages and contacts programmatically.'],
            ['key' => 'api_calls_monthly', 'group' => 'Developer API', 'name' => 'API calls / month', 'description' => 'Your included monthly API-call allowance. Calls beyond this are covered with coins, so nothing hard-stops.', 'unit' => 'calls / mo'],
            ['key' => 'api_rate_per_min', 'group' => 'Developer API', 'name' => 'API rate limit', 'description' => 'How many API requests you can make per minute.', 'unit' => 'requests / min'],

            // ---- AI suite ----
            ['key' => 'ai_chatbot', 'group' => 'AI suite', 'name' => 'AI Chatbot', 'description' => 'A 24/7 AI chatbot on your Link in Bio that answers visitor questions in your voice, captures leads and books calls.'],
            ['key' => 'ai_agent', 'group' => 'AI suite', 'name' => 'AI Agent', 'description' => 'A multi-step AI agent that runs playbooks across your contacts, inbox and calendar — qualifying leads and following up on its own.'],
            ['key' => 'ai_widget', 'group' => 'AI suite', 'name' => 'AI Widget', 'description' => 'Embed an AI assistant on any external site with a single snippet — answers questions and captures leads into your unified inbox.'],
            ['key' => 'ai_voice_assistant', 'group' => 'AI suite', 'name' => 'AI Voice Assistant', 'description' => 'AI receptionist that picks up calls to your number, qualifies callers and books or routes them — no missed leads.'],
            ['key' => 'max_minds', 'group' => 'AI suite', 'name' => 'AI Minds', 'description' => 'Labelled AI Minds your AI agents and coach can draw on — add text, FAQs, documents, links or live Sayzio data.', 'unit' => 'AI Minds'],
            ['key' => 'max_personas', 'group' => 'AI suite', 'name' => 'AI Agents', 'description' => 'Configurable AI agents that combine a system prompt, tone and the AI Minds you choose.', 'unit' => 'agents'],
            ['key' => 'max_companions', 'group' => 'AI suite', 'name' => 'Chat Widgets', 'description' => 'Deploy an AI Agent as a Link in Bio chatbot, an external website embed, or an inbox auto-reply bot.', 'unit' => 'widgets'],
            ['key' => 'ask_coach', 'group' => 'AI suite', 'name' => 'AI Coach', 'description' => 'Chat with an AI advisor for plain-English, one-tap tips on improving your account.'],
            ['key' => 'card_scan', 'group' => 'AI suite', 'name' => 'Card & Brochure Scanner', 'description' => 'Snap a business card or brochure and let AI extract the details straight into a Link in Bio or contact.'],
            ['key' => 'ai_resume_tools', 'group' => 'AI suite', 'name' => 'AI Resume Tools', 'description' => 'AI tailoring, cover-letter writing and resume import to build a polished, shareable resume in minutes.'],
            ['key' => 'inbox_agent', 'group' => 'AI suite', 'name' => 'AI Inbox Agent', 'description' => 'AI that triages your unified inbox, drafts on-brand replies and can run on autopilot — staging anything sensitive for your review.'],
            ['key' => 'brand_consistency', 'group' => 'AI suite', 'name' => 'Brand Consistency AI', 'description' => 'A live Brand Consistency Score plus on-brand prompt injection so every AI-generated page, reply and asset stays true to your brand kit.'],
            ['key' => 'qr_art', 'group' => 'AI suite', 'name' => 'AI Artistic QR', 'description' => 'Generate eye-catching, on-brand artistic QR codes with AI that still scan reliably.'],
            ['key' => 'whatsapp_agent', 'group' => 'AI suite', 'name' => 'WhatsApp AI Agent', 'description' => 'An AI responder for inbound WhatsApp messages that answers questions and captures leads in your voice, around the clock.'],
            ['key' => 'marketing_strategist', 'group' => 'AI suite', 'name' => 'AI Marketing Strategist', 'description' => 'An AI strategist that analyses your account and audience to build saved, actionable marketing plans and campaign ideas.'],
            ['key' => 'brand_studio', 'group' => 'AI suite', 'name' => 'AI Brand Studio', 'description' => 'Turn one plain-language brief into a whole on-brand asset kit — Link in Bio page, short links, QR codes, a form and a digital card — reviewed before anything is created.'],
            ['key' => 'max_brand_studio_bulk', 'group' => 'AI suite', 'name' => 'AI Brand Studio bulk variations', 'description' => 'How many on-brand variants one AI Brand Studio bulk run can generate at once (e.g. 20 personalized QR codes or short links).', 'unit' => 'variants / run'],
            ['key' => 'competitor_teardown', 'group' => 'AI suite', 'name' => 'Competitor Biolink Teardown', 'description' => 'Paste any competitor\'s link-in-bio URL and get an AI-scored teardown — strengths, weaknesses, missing elements and CTA quality — then build a better version with one click.'],
        ];
    }

    /**
     * The two included-coin-grant catalogue entries (monthly + yearly),
     * keyed for surfaces that render them as a dedicated "Included coins"
     * row (public pricing cards, /user/upgrade comparison, mobile
     * feature_highlights). Same entries as catalogue() — this is a
     * filtered view, not a second source of truth.
     *
     * @return list<array{key:string,group:string,name:string,description:string,unit:string}>
     */
    public static function includedCoinGrants(): array
    {
        return array_values(array_filter(
            self::catalogue(),
            fn (array $e) => in_array($e['key'], ['included_coins_monthly', 'included_coins_yearly'], true)
        ));
    }

    /**
     * Classify a catalogue entry for rendering:
     *  - 'analytics' the basic/advanced select
     *  - 'number'    any entry carrying a numeric `unit`
     *  - 'bool'      a plain on/off capability
     */
    public static function kindOf(array $entry): string
    {
        if (($entry['key'] ?? null) === 'analytics') {
            return 'analytics';
        }
        return array_key_exists('unit', $entry) ? 'number' : 'bool';
    }

    /**
     * Resolve how a single catalogue entry should display for one plan,
     * reading that plan's `features` JSON. Centralises the
     * number / "Unlimited" / included / excluded resolution so the public
     * comparison grid (and any other surface) never re-implements it.
     *
     * @return array{kind:string,on:bool,unlimited:bool,text:string}
     *   - on        the plan includes / raises this feature
     *   - unlimited the numeric value is -1
     *   - text      display string for numeric / analytics cells ('' for bools)
     */
    public static function resolveCell($plan, array $entry): array
    {
        $features = is_array($plan->features ?? null) ? $plan->features : [];
        $key = $entry['key'];
        $kind = self::kindOf($entry);
        $val = array_key_exists($key, $features) ? $features[$key] : null;

        if ($kind === 'analytics') {
            $advanced = is_string($val) && strtolower($val) === 'advanced';
            return ['kind' => 'analytics', 'on' => $advanced, 'unlimited' => false, 'text' => $advanced ? 'Advanced' : 'Basic'];
        }

        if ($kind === 'number') {
            // `max_aliases_per_link` may be a per-type map instead of a scalar.
            if (is_array($val)) {
                $on = count(array_filter($val, fn ($v) => (int) $v !== 0)) > 0;
                return ['kind' => 'number', 'on' => $on, 'unlimited' => false, 'text' => $on ? 'Custom' : ''];
            }
            $n = (int) $val;
            if ($n === -1) {
                return ['kind' => 'number', 'on' => true, 'unlimited' => true, 'text' => 'Unlimited'];
            }
            if ($n === 0) {
                return ['kind' => 'number', 'on' => false, 'unlimited' => false, 'text' => ''];
            }
            return ['kind' => 'number', 'on' => true, 'unlimited' => false, 'text' => number_format($n)];
        }

        $on = match (true) {
            is_bool($val)                  => $val === true,
            is_int($val) || is_float($val) => $val != 0,
            is_string($val)                => $val !== '' && ! in_array(strtolower($val), ['basic', 'none', 'false', '0'], true),
            default                        => false,
        };

        return ['kind' => 'bool', 'on' => $on, 'unlimited' => false, 'text' => ''];
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
                if (self::resolveCell($p, $entry)['on']) {
                    $slugs[] = $p->slug;
                }
            }
            $out[$key] = $slugs;
        }
        return $out;
    }

    /**
     * Build an ordered list of plain-English highlight strings for one plan,
     * derived from the SAME catalogue + resolveCell() the public comparison
     * grid uses. Only features the plan actually includes are returned (in
     * catalogue order), so the mobile upgrade/plan cards mirror the web copy
     * without the bundle duplicating any strings. Callers typically slice the
     * first few for a card preview.
     *
     * @return list<string>
     */
    public static function planHighlights($plan): array
    {
        $out = [];
        foreach (self::catalogue() as $entry) {
            $cell = self::resolveCell($plan, $entry);
            if (! $cell['on']) {
                continue;
            }
            $name = $entry['name'];
            if ($cell['kind'] === 'bool') {
                $out[] = $name;
                continue;
            }
            if ($cell['kind'] === 'analytics') {
                $out[] = $name . ' — ' . $cell['text'];
                continue;
            }
            // Numeric: "Short links — Unlimited links" / "Storage — 1,024 MB total".
            $unit = $entry['unit'] ?? '';
            $value = $cell['unlimited'] ? 'Unlimited' : $cell['text'];
            $out[] = $unit !== '' ? "{$name} — {$value} {$unit}" : "{$name} — {$value}";
        }
        return $out;
    }
}
