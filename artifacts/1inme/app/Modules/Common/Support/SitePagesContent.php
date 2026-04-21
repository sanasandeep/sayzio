<?php

namespace App\Modules\Common\Support;

/**
 * Source-of-truth for the rich, on-brand body copy that ships with every
 * footer-linked public site page. Used by both the seeder (fresh installs)
 * and the migration that backfills existing installs (only overwriting
 * pages still showing the original short placeholder copy).
 */
class SitePagesContent
{
    /**
     * Substantial defaults for every page surfaced from the public footer.
     * Pages already authored with multi-section content (workspace-team,
     * buzz, error pages, home) keep the copy already shipped in the seeder.
     */
    public static function richDefaults(): array
    {
        return [
            'features' => [
                'title' => 'Features',
                'meta_description' => 'Everything you get with 1INME — biolinks, short links, dynamic QR codes, deep analytics, forms, contacts, broadcasts and more.',
                'sections' => [
                    ['heading' => 'A drag & drop biolink page', 'body' => "Stack blocks for text, images, video, audio, embeds, products, donations and forms. Reorder by dragging, swap themes in a click, and publish a polished page in minutes — no design skills needed."],
                    ['heading' => 'Branded short links', 'body' => "Turn long URLs into clean, on-brand short links you can repoint at any time. Add UTMs automatically, password-protect sensitive links, expire them on a date or after N clicks, and route visitors by country, device or language."],
                    ['heading' => 'Dynamic QR codes', 'body' => "Every link gets a high-resolution QR code you can style with your logo and colours. Because the destination is editable, the same printed code can be repurposed forever — change the target without reprinting."],
                    ['heading' => 'Live, actionable analytics', 'body' => "See visitors arrive in real time, with country, city, device, referrer and conversion breakdowns. The Performance Coach watches your numbers and surfaces concrete fixes — slow pages, dead blocks, broken links, missing CTAs."],
                    ['heading' => 'Forms & contact capture', 'body' => "Build forms with conditional logic, embed them anywhere, and pipe submissions straight into your contact list. Tag, segment and export contacts to power broadcasts, the dialer or your favourite CRM."],
                    ['heading' => 'Broadcasts & follow-ups', 'body' => "Send email and SMS broadcasts to segmented audiences, schedule follow-ups, and track delivery, opens and replies — all from the same dashboard that already holds your audience."],
                    ['heading' => 'Workspaces & team roles', 'body' => "Create a workspace per brand or client, invite teammates with the right role (Owner, Admin, Editor, Viewer), and keep billing, analytics and contacts cleanly separated. Every action is attributed."],
                    ['heading' => 'Open API & integrations', 'body' => "Plug 1INME into the rest of your stack. A clean REST API, webhooks for every important event, and native integrations for Stripe, Mailchimp, Google Sheets, Zapier and more."],
                ],
            ],
            'how-it-works' => [
                'title' => 'How it works',
                'meta_description' => 'Get up and running in minutes — sign up, build your page, share one link everywhere, and let analytics guide your growth.',
                'sections' => [
                    ['heading' => '1. Sign up free in under a minute', 'body' => "Create an account with just an email or phone number. No credit card, no trial countdown — the Free plan gives you biolinks, short links and QR codes forever."],
                    ['heading' => '2. Pick a starting template', 'body' => "Choose from dozens of ready-made biolink templates designed for creators, coaches, freelancers, restaurants, agencies and small businesses. Tweak it or start from a blank canvas."],
                    ['heading' => '3. Build with drag & drop blocks', 'body' => "Add text, images, video, audio, products, donation buttons, social icons and embeds by dragging them in. Reorder, hide on mobile, schedule when blocks appear, and preview as you build."],
                    ['heading' => '4. Get one link to share everywhere', 'body' => "Your biolink lives at a friendly URL you can drop into Instagram, TikTok, YouTube, your email signature and printed QR codes. Connect a custom domain on paid plans."],
                    ['heading' => '5. Capture leads and conversations', 'body' => "Forms, follow buttons and built-in messaging turn visitors into contacts. Everyone you capture lands in your contacts panel ready for tagging, broadcasts or a quick reply."],
                    ['heading' => '6. Watch live analytics roll in', 'body' => "Visitors, clicks, geographies, devices and referrers update in real time. Spot what's working in seconds — and double down on it."],
                    ['heading' => '7. Let the Performance Coach guide you', 'body' => "Your dashboard surfaces a small, prioritised list of fixes — slow images, dead blocks, weak CTAs, broken links — with one-click jumps straight to the field that needs attention."],
                ],
            ],
            'about' => [
                'title' => 'About 1INME',
                'meta_description' => 'We help creators, freelancers, agencies and small businesses turn one link into a complete online presence.',
                'sections' => [
                    ['heading' => 'Our mission', 'body' => "One link should do everything: show your work, capture leads, sell, message and tell your story. We started 1INME because juggling ten different tools to do that felt absurd, and the existing biolink tools stopped at a list of buttons."],
                    ['heading' => 'Built for the people doing the work', 'body' => "We build for creators, coaches, freelancers, agencies and small businesses — the people who don't have a marketing team and need every minute back. Every feature is judged by whether it helps you ship faster and earn more."],
                    ['heading' => 'Opinionated, not bloated', 'body' => "We say no to feature creep. Each addition has to earn its place by making the core loop — capture attention, convert it, follow up — measurably better. If it doesn't, it doesn't ship."],
                    ['heading' => 'Privacy by default', 'body' => "We don't sell your data, we don't run third-party ad trackers on your pages, and we keep analytics aggregated and respectful. Your audience is yours."],
                    ['heading' => 'Transparent pricing', 'body' => "A genuinely useful Free plan, paid tiers that scale with what you actually use, and clear upgrade paths. No hidden seat charges, no surprise overages."],
                    ['heading' => 'Always-on support', 'body' => "Real humans, fast replies, and a support team that uses the product every day. If something's broken or unclear, tell us — we triage every message."],
                ],
            ],
            'contact' => [
                'title' => 'Contact us',
                'meta_description' => 'Get in touch with the 1INME team — sales, support, partnerships and press. We usually reply within one business day.',
                'sections' => [
                    ['heading' => 'We love hearing from you', 'body' => "Whether you have a question, hit a snag, want to suggest a feature or are exploring a partnership, drop us a note using the form below. A real person on our team will read it and reply, usually within one business day."],
                    ['heading' => 'Support', 'body' => "Stuck on something? Include your account email, the page or link you were on, and a screenshot if you can. The more detail you share, the faster we can help."],
                    ['heading' => 'Sales & teams', 'body' => "Looking at 1INME for a team, an agency, or a larger rollout? Tell us how many seats you need and the workflows you care about — we'll set up a tailored walkthrough."],
                    ['heading' => 'Press & partnerships', 'body' => "If you're writing about 1INME, building something on top of our API, or proposing a partnership, send us the details and we'll route you to the right person."],
                ],
            ],
            'faqs' => [
                'title' => 'Frequently asked questions',
                'meta_description' => 'Answers to the most common questions about 1INME, plans, billing, custom domains and getting started.',
                'sections' => [
                    ['heading' => 'Quick answers, in one place', 'body' => "We've gathered the questions people ask most often about plans, billing, custom domains, team access and the day-to-day of running your biolink. Browse the list below — and if your question isn't here, our support team is one message away."],
                ],
            ],
            'terms' => [
                'title' => 'Terms & Conditions',
                'meta_description' => 'The terms governing your use of 1INME — your account, what you can publish, billing, intellectual property and how we end the relationship.',
                'sections' => [
                    ['heading' => '1. Acceptance', 'body' => "By creating an account or using 1INME you agree to these terms. If you are using the service on behalf of a company you confirm you have authority to bind that company. If you do not agree, please do not use the service."],
                    ['heading' => '2. Your account', 'body' => "You are responsible for everything that happens under your account, including the actions of teammates you invite. Keep your sign-in details safe, use a strong unique password, and tell us straight away if you suspect unauthorised access."],
                    ['heading' => '3. Acceptable use', 'body' => "You must not use 1INME to host or distribute illegal content, run scams or phishing, send unsolicited bulk messages, infringe other people's intellectual property, or harass other users. We reserve the right to remove content and suspend accounts that break these rules."],
                    ['heading' => '4. Your content', 'body' => "You keep ownership of everything you upload or publish. You grant us the limited licence we need to host, display and back up your content so the service can run. You are responsible for making sure you have the right to publish what you upload."],
                    ['heading' => '5. Plans, billing and renewals', 'body' => "Paid plans renew automatically at the end of each billing period using the payment method on file. You can cancel or downgrade at any time from your account — changes take effect at the next renewal. See the Refunds Policy for refund eligibility."],
                    ['heading' => '6. Service availability', 'body' => "We work hard to keep 1INME fast and available, but we cannot guarantee 100% uptime. Planned maintenance is announced in advance where possible. The service is provided \"as is\" without implied warranties to the extent permitted by law."],
                    ['heading' => '7. Termination', 'body' => "You can close your account at any time from your settings. We may suspend or close accounts that violate these terms, with notice where reasonable. On termination, your published pages stop resolving and your data is removed within a reasonable period."],
                    ['heading' => '8. Changes to these terms', 'body' => "We may update these terms when the service changes or the law requires. Material changes are announced by email and inside the dashboard at least 14 days before they take effect."],
                ],
            ],
            'refunds' => [
                'title' => 'Refunds Policy',
                'meta_description' => 'How refunds work for 1INME paid plans — eligibility, timing, exceptions and how to request one.',
                'sections' => [
                    ['heading' => '7-day refund window', 'body' => "You can request a full refund within 7 days of any new paid plan purchase, no questions asked. The refund covers the most recent charge only — earlier billing periods are not refundable."],
                    ['heading' => 'Renewals', 'body' => "Renewals (monthly or yearly) are not automatically refundable. We send a reminder before each renewal so you can downgrade or cancel beforehand. If a renewal slipped past you and you didn't use the service in the new period, contact us — we look at these case by case."],
                    ['heading' => 'Add-ons and overages', 'body' => "Usage-based add-ons (extra short links, broadcasts, storage) are billed for what you've used and are non-refundable once consumed. Unused, prepaid add-on capacity within the 7-day window can be refunded on request."],
                    ['heading' => 'How to request a refund', 'body' => "Email our team from your account email, include the invoice number, and tell us briefly what didn't work for you (it helps us improve). We process approved refunds within 5 business days back to the original payment method."],
                    ['heading' => 'Chargebacks', 'body' => "Please contact us first before raising a chargeback — we can almost always resolve the issue faster than your bank can. Accounts with unresolved chargebacks may be suspended pending review."],
                ],
            ],
            'privacy' => [
                'title' => 'Privacy Policy',
                'meta_description' => 'How 1INME collects, uses, stores and protects your personal data — and the rights you have over it.',
                'sections' => [
                    ['heading' => 'What we collect', 'body' => "We collect three kinds of information: account details you give us (name, email, billing address), the content you publish or upload (pages, blocks, files, contacts), and basic usage data needed to run the service (IP address, browser, pages visited)."],
                    ['heading' => 'How we use it', 'body' => "We use your data to provide the service, support you, send essential service emails, prevent abuse, and improve the product. We do not sell your personal data, and we do not run third-party advertising trackers on the pages you publish."],
                    ['heading' => 'Who we share it with', 'body' => "We share data only with the sub-processors we need to run the service — hosting, payment processing, transactional email and analytics. Each is bound by a data-processing agreement and listed in our sub-processor register on request."],
                    ['heading' => 'How long we keep it', 'body' => "We keep your account data for as long as your account is open, plus a short retention window for backups. When you delete your account, your data is removed from our active systems within 30 days and from backups within 90."],
                    ['heading' => 'Security', 'body' => "Data is encrypted in transit and at rest. Access to production systems is restricted to a small team with multi-factor authentication, and every privileged action is logged."],
                    ['heading' => 'Your rights', 'body' => "You can access, export, correct or delete your data at any time from your account settings, or by emailing us. If you are in a region with additional privacy rights (such as the EU/EEA, UK, California or Brazil), see the GDPR Policy for the full list of rights and how to exercise them."],
                    ['heading' => 'Contacting us about privacy', 'body' => "If you have a privacy question or want to raise a concern, contact our team through the contact form. We respond to verified privacy requests within 30 days."],
                ],
            ],
            'gdpr' => [
                'title' => 'GDPR Policy',
                'meta_description' => 'Our compliance with the EU General Data Protection Regulation, including lawful bases, your rights and international data transfers.',
                'sections' => [
                    ['heading' => 'Who this applies to', 'body' => "This policy explains how we comply with the EU General Data Protection Regulation (GDPR) and the UK GDPR. It applies whenever we process personal data of users, teammates or visitors located in the EU/EEA or the UK."],
                    ['heading' => 'Roles', 'body' => "For your account information you are the data subject and we are the data controller. For the personal data of your visitors and contacts that you collect through 1INME (form submissions, leads, followers), you are the data controller and we act as your data processor."],
                    ['heading' => 'Lawful bases for processing', 'body' => "We process personal data on the basis of contract performance (running the service for you), legitimate interest (security, fraud prevention, product improvement), legal obligation (tax records, abuse reports), and your consent where required (optional analytics, marketing emails)."],
                    ['heading' => 'Your rights under GDPR', 'body' => "You can request access to your data, correction of inaccurate data, deletion (\"right to be forgotten\"), restriction of processing, data portability, and you can object to processing based on legitimate interest. Most of these are self-serve from your account; for the rest, contact us."],
                    ['heading' => 'International data transfers', 'body' => "Where personal data is transferred outside the EU/EEA or UK, we rely on the European Commission's Standard Contractual Clauses (and the UK addendum where applicable) with each sub-processor to provide an adequate level of protection."],
                    ['heading' => 'Breach notification', 'body' => "In the unlikely event of a personal data breach that is likely to result in a risk to your rights and freedoms, we will notify the relevant supervisory authority within 72 hours and inform affected users without undue delay."],
                    ['heading' => 'Data Processing Agreement', 'body' => "If you process personal data of EU/EEA or UK residents through 1INME on behalf of your own users, you can request our standard Data Processing Agreement (DPA) — we'll countersign it and send it back."],
                ],
            ],
            'cookies' => [
                'title' => 'Cookie Policy',
                'meta_description' => 'What cookies and similar technologies 1INME uses, why we use them, and how you can control them.',
                'sections' => [
                    ['heading' => 'What cookies are', 'body' => "Cookies are small text files a website places on your device to remember information between visits. We also use comparable technologies — local storage, session storage and pixel tags — for the same purposes; \"cookies\" in this policy covers all of them."],
                    ['heading' => 'Strictly necessary cookies', 'body' => "These keep you signed in, remember your workspace selection, protect form submissions from CSRF attacks and load-balance requests. The service cannot work without them and they cannot be disabled separately from disabling cookies entirely in your browser."],
                    ['heading' => 'Functional cookies', 'body' => "These remember your preferences — sidebar collapsed/expanded state, theme, language and recently viewed items — so the dashboard feels familiar between visits."],
                    ['heading' => 'Analytics cookies', 'body' => "We use first-party analytics to understand which features are used and where people get stuck, so we can improve the product. The data is aggregated and never sold. Where required by law we ask for your consent before setting these."],
                    ['heading' => 'Cookies on your published pages', 'body' => "Pages you publish on 1INME set only the strictly necessary cookies needed to run them. We do not inject third-party advertising or marketing cookies into your visitors' browsers."],
                    ['heading' => 'How to control cookies', 'body' => "You can clear and block cookies from your browser settings at any time. Disabling strictly necessary cookies will sign you out and break parts of the dashboard; disabling functional or analytics cookies is safe but may make the experience less convenient."],
                ],
            ],
            'discovery' => [
                'title' => 'Discover biolinks',
                'meta_description' => 'Browse public 1INME biolink pages — find creators, brands and businesses sharing their work.',
                'sections' => [
                    ['heading' => 'Find your next favourite link', 'body' => "Browse the latest public biolink pages on 1INME. Search by name, handle or topic, tap any card to open the page, and follow the creators whose work you love so you never miss a new post or drop."],
                    ['heading' => 'Curated, not crowded', 'body' => "Only pages whose creators have opted in to be discoverable show up here. That keeps the directory genuine — the people listed actually want new visitors and are actively keeping their pages fresh."],
                    ['heading' => 'Want to be listed?', 'body' => "Toggle \"Show me in Discover\" from your profile settings and your public biolink will appear here within a few minutes. You stay in control: turn it off any time and you disappear from the directory."],
                ],
            ],
            'creators-feed' => [
                'title' => 'Creators feed',
                'meta_description' => 'The latest posts from creators on 1INME — updates, drops, news and behind-the-scenes from people building in public.',
                'sections' => [
                    ['heading' => 'Fresh from the community', 'body' => "See what creators on 1INME are posting right now — product drops, behind-the-scenes notes, announcements and updates from people building their audience here. Scroll, discover and follow your favourites in one tap."],
                    ['heading' => 'How posts get here', 'body' => "Any creator with a public biolink can publish posts and have them surface in this feed. Pinned posts from staff or partners may appear at the top; everything else is ordered newest-first so you always see what just dropped."],
                    ['heading' => 'Build your following', 'body' => "Posting from your biolink is the easiest way to keep your audience warm between launches. Visitors can follow you straight from the post and they'll see your next one in their feed."],
                ],
            ],
        ];
    }

    /**
     * The original short placeholder copy that shipped with the first
     * version of each page. Used to detect whether a page is still
     * untouched — if the current sections match these byte-for-byte we
     * can safely overwrite with the richer defaults; otherwise an admin
     * has customised it and we leave it alone.
     */
    public static function originalPlaceholders(): array
    {
        return [
            'features' => [
                ['heading' => 'Drag & drop biolinks', 'body' => 'Stack blocks for text, images, video, audio and embeds. Reorder by dragging. Pick a theme. Go live.'],
                ['heading' => 'Short links & QR codes', 'body' => 'Branded short links and dynamic QR codes you can repoint at any time without reprinting.'],
                ['heading' => 'Live analytics', 'body' => 'See live visitors, geographic heatmaps, click trends and a Performance Coach that suggests fixes.'],
                ['heading' => 'Forms & contacts', 'body' => 'Embed forms anywhere, capture submissions, and sync contacts to power your dialer and broadcasts.'],
            ],
            'how-it-works' => [
                ['heading' => '1. Sign up free', 'body' => 'Create an account in under a minute. No credit card needed.'],
                ['heading' => '2. Build your page', 'body' => 'Drag and drop blocks to design your biolink. Add short links, QR codes, forms and more.'],
                ['heading' => '3. Share & grow', 'body' => 'Share one URL everywhere. Watch live analytics roll in and let the Performance Coach guide your next move.'],
            ],
            'about' => [
                ['heading' => 'Our mission', 'body' => 'One link should do everything — show your work, capture leads, sell, and tell your story. We make that easy.'],
                ['heading' => 'Built for creators', 'body' => 'Whether you are a creator, coach, freelancer or small business, 1INME gives you the tools to grow without juggling ten apps.'],
            ],
            'contact' => [
                ['heading' => 'We love hearing from you', 'body' => 'Have a question, a suggestion, or need help getting set up? Send us a note and we will get back to you within one business day.'],
            ],
            'faqs' => [],
            'terms' => [
                ['heading' => '1. Acceptance', 'body' => 'By accessing or using 1INME you agree to these terms. If you do not agree, please do not use the service.'],
                ['heading' => '2. Your account', 'body' => 'You are responsible for all activity under your account. Keep your sign-in details safe.'],
                ['heading' => '3. Acceptable use', 'body' => 'Do not use 1INME to host illegal content, send spam, or abuse other users.'],
                ['heading' => '4. Termination', 'body' => 'We may suspend or close accounts that violate these terms.'],
            ],
            'refunds' => [
                ['heading' => 'Refund window', 'body' => 'You can request a full refund within 7 days of any new paid plan purchase.'],
                ['heading' => 'How to request', 'body' => 'Email our team from your account email and include your invoice number. We process refunds within 5 business days.'],
            ],
            'privacy' => [
                ['heading' => 'What we collect', 'body' => 'We collect the information you give us (account details, content) and basic usage data needed to run the service.'],
                ['heading' => 'How we use it', 'body' => 'To provide the service, support you, and improve our product. We never sell your personal data.'],
                ['heading' => 'Your rights', 'body' => 'You can access, export or delete your data at any time from your account settings.'],
            ],
            'gdpr' => [
                ['heading' => 'Lawful basis', 'body' => 'We process your personal data on the basis of contract performance, legitimate interest, and your consent where required.'],
                ['heading' => 'Your rights under GDPR', 'body' => 'You can request access, correction, deletion, restriction, portability or object to processing at any time.'],
                ['heading' => 'Data transfers', 'body' => 'Where data leaves the EU we rely on Standard Contractual Clauses to protect your rights.'],
            ],
            'cookies' => [
                ['heading' => 'What cookies we use', 'body' => 'Strictly necessary cookies to keep you signed in, plus analytics cookies to understand how the product is used.'],
                ['heading' => 'Managing cookies', 'body' => 'You can disable non-essential cookies from your browser settings at any time.'],
            ],
            'discovery' => [
                ['heading' => 'Find your next favourite link', 'body' => 'Browse the latest public biolink pages on 1INME. Search by name, handle or topic and tap any card to open the page.'],
            ],
            'creators-feed' => [
                ['heading' => 'Fresh from the community', 'body' => 'See what creators on 1INME are posting right now. Follow your favourites from their biolink page to never miss an update.'],
            ],
        ];
    }

    /**
     * Decide whether the current sections stored for a slug are still the
     * original placeholder copy (and therefore safe to overwrite). A row
     * with no sections at all (faqs in the original seed) is also treated
     * as untouched.
     */
    public static function isStillPlaceholder(string $slug, $currentSections): bool
    {
        $placeholders = self::originalPlaceholders();
        if (!array_key_exists($slug, $placeholders)) {
            return false;
        }
        $current = is_array($currentSections) ? array_values($currentSections) : [];
        $expected = $placeholders[$slug];
        return self::sectionsEqual($current, $expected);
    }

    private static function sectionsEqual(array $a, array $b): bool
    {
        if (count($a) !== count($b)) return false;
        foreach ($a as $i => $row) {
            $h1 = trim((string) ($row['heading'] ?? ''));
            $b1 = trim((string) ($row['body'] ?? ''));
            $h2 = trim((string) ($b[$i]['heading'] ?? ''));
            $b2 = trim((string) ($b[$i]['body'] ?? ''));
            if ($h1 !== $h2 || $b1 !== $b2) return false;
        }
        return true;
    }

    /**
     * Defaults for any footer-linked slug that may be missing entirely
     * (so no footer link can 404), keyed by slug. Pulls from richDefaults
     * when available; supplies a tiny fallback for the rest.
     */
    public static function fallbackForMissing(string $slug): array
    {
        $rich = self::richDefaults();
        if (isset($rich[$slug])) return $rich[$slug];
        return [
            'title' => ucwords(str_replace('-', ' ', $slug)),
            'meta_description' => null,
            'sections' => [
                ['heading' => 'Coming soon', 'body' => 'This page is being prepared. Please check back shortly.'],
            ],
        ];
    }

    /**
     * Slugs that the public footer links to and which therefore must
     * resolve to a real page after the migration runs.
     */
    public static function footerSlugs(): array
    {
        return [
            'features', 'how-it-works', 'workspace-team', 'buzz',
            'discovery', 'creators-feed', 'faqs',
            'about', 'contact',
            'terms', 'refunds', 'privacy', 'gdpr', 'cookies',
        ];
    }

    /**
     * Structured default content for the dedicated /features page. Each
     * top-level entry is a category card on the page. Stored on the
     * `features` SitePage row as `sections` so admins can edit every
     * heading, intro, feature name and description from the admin UI
     * without touching the blade template.
     */
    public static function featuresCategoriesDefault(): array
    {
        return [
            [
                'id' => 'biolink',
                'icon' => 'fa-square-share-nodes',
                'heading' => 'Biolink & landing page builder',
                'intro' => 'Build a fully-customizable one-link landing page with a guided wizard and a deep block library, organised by sub-type so you only see what you need.',
                'features' => [
                    ['name' => 'Guided biolink wizard', 'description' => 'Step-by-step creation flow that helps you pick a layout, profile style, and starting blocks without any design experience.'],
                    ['name' => 'Essentials blocks', 'description' => 'Quick-add blocks for the basics: links, headings, paragraphs, dividers, and spacers to structure your page.'],
                    ['name' => 'Layout & profile blocks', 'description' => 'Profile cards, avatars, cover images, and section layouts to anchor your identity at the top of the page.'],
                    ['name' => 'Media blocks', 'description' => 'Embed images, image galleries, audio, video, and file downloads directly into the page.'],
                    ['name' => 'Engagement blocks', 'description' => 'Add countdowns, FAQs, testimonials, ratings, and call-to-action buttons to keep visitors interacting.'],
                    ['name' => 'Commerce blocks', 'description' => 'Sell products, accept payments, take tips, and showcase services right inside the biolink.'],
                    ['name' => 'Contact & lead blocks', 'description' => 'Drop in contact forms, booking requests, and lead capture fields without leaving the builder.'],
                    ['name' => 'Social & embed blocks', 'description' => 'Pull in social handles, feeds, maps, and third-party embeds in a single click.'],
                    ['name' => 'Visual customization', 'description' => 'Fine-tune colors, fonts, backgrounds, button styles, and spacing for a fully on-brand look.'],
                    ['name' => 'Splash pages', 'description' => 'Show a branded interstitial before visitors land on the main biolink to set the mood or run announcements.'],
                ],
            ],
            [
                'id' => 'links',
                'icon' => 'fa-link',
                'heading' => 'Short links & link tools',
                'intro' => 'Shorten, organise, and manage every kind of link you need to share, with project folders and lifecycle controls.',
                'features' => [
                    ['name' => 'Short URLs', 'description' => 'Turn long URLs into clean, branded short links you can share anywhere.'],
                    ['name' => 'Projects', 'description' => 'Group related links into project folders to keep large libraries tidy and easy to navigate.'],
                    ['name' => 'URL link type', 'description' => 'Standard short link that redirects visitors to any web address you choose.'],
                    ['name' => 'File link type', 'description' => 'Upload a file and share it through a short link that streams the download to visitors.'],
                    ['name' => 'ICS calendar link type', 'description' => 'Generate calendar event links that visitors can add straight to their own calendar.'],
                    ['name' => 'VCF contact card link type', 'description' => 'Share a downloadable contact card so people can save your details with one tap.'],
                    ['name' => 'Duplicate link', 'description' => 'Clone an existing link and tweak it instead of rebuilding from scratch.'],
                    ['name' => 'Reset link', 'description' => "Wipe a link's analytics and start counting visits fresh whenever you need a clean baseline."],
                    ['name' => 'Temporary status', 'description' => 'Mark a link as temporary so it expires automatically after the date or click limit you set.'],
                ],
            ],
            [
                'id' => 'qr',
                'icon' => 'fa-qrcode',
                'heading' => 'QR code studio',
                'intro' => 'Turn any link or piece of content into a scannable, brand-styled QR code, ready for print or screen.',
                'features' => [
                    ['name' => 'Per-link QR codes', 'description' => 'Every short link and biolink gets an instant downloadable QR code you can drop on flyers, packaging, or slides.'],
                    ['name' => 'Standalone QR generator', 'description' => "Generate one-off QR codes that aren't tied to a tracked link when you just need a quick code."],
                    ['name' => 'Text QR codes', 'description' => "Encode plain text messages so a scan reveals the words on the visitor's device."],
                    ['name' => 'Email QR codes', 'description' => "Open the visitor's email app pre-filled with your address, subject, and body."],
                    ['name' => 'SMS QR codes', 'description' => 'Pre-compose a text message with the right phone number so a scan starts the conversation.'],
                    ['name' => 'WiFi QR codes', 'description' => 'Let guests join your WiFi by scanning, with no manual password entry.'],
                    ['name' => 'VCard QR codes', 'description' => 'Hand out your contact card as a QR — perfect for business cards and event badges.'],
                    ['name' => 'Custom styling', 'description' => 'Adjust colors, add a logo in the centre, and pick from styled patterns to match your brand.'],
                ],
            ],
            [
                'id' => 'analytics',
                'icon' => 'fa-chart-line',
                'heading' => 'Analytics & performance',
                'intro' => 'Understand exactly how your links and pages perform, then feed that data into your existing marketing stack.',
                'features' => [
                    ['name' => 'Visitor analytics', 'description' => 'See visit counts, geography, devices, browsers, referrers, and trends across all your links and pages.'],
                    ['name' => 'Heatmaps', 'description' => 'Visualise which blocks on your biolink visitors actually click and where they drop off.'],
                    ['name' => 'CSV export', 'description' => 'Download raw analytics as CSV so you can crunch the numbers in your own spreadsheet or BI tool.'],
                    ['name' => 'Facebook tracking pixel', 'description' => 'Drop in your Facebook Pixel ID to retarget visitors and measure ad performance.'],
                    ['name' => 'Google Analytics tracking', 'description' => 'Connect a Google Analytics property and feed visits straight into your existing reporting.'],
                    ['name' => 'LinkedIn Insight tag', 'description' => 'Track LinkedIn ad audiences and conversions from your biolink visitors.'],
                    ['name' => 'Pinterest tag', 'description' => 'Attribute Pinterest-driven traffic to the right campaigns with the Pinterest tracking tag.'],
                    ['name' => 'TikTok Pixel', 'description' => 'Send visit and conversion events to TikTok Ads Manager for retargeting and measurement.'],
                ],
            ],
            [
                'id' => 'inbox',
                'icon' => 'fa-inbox',
                'heading' => 'Inbox & messaging',
                'intro' => 'Every conversation that reaches you through 1INME lands in one place so nothing slips through the cracks.',
                'features' => [
                    ['name' => 'Unified inbox', 'description' => 'A single inbox that pulls together every visitor message, form reply, and follow-up across all your links.'],
                    ['name' => 'Direct messages from visitors', 'description' => 'Visitors can message you straight from your biolink and you reply right inside the inbox.'],
                    ['name' => 'Form submissions', 'description' => 'Every contact form, lead form, and booking form submission lands in the same inbox thread.'],
                ],
            ],
            [
                'id' => 'subscribers',
                'icon' => 'fa-envelope-open-text',
                'heading' => 'Subscribers & broadcasts',
                'intro' => 'Grow your own audience list, then talk to it directly without depending on social platforms.',
                'features' => [
                    ['name' => 'Email list building', 'description' => 'Capture email subscribers through dedicated blocks and forms on your biolink.'],
                    ['name' => 'SMS list building', 'description' => 'Collect mobile numbers with consent so you can send time-sensitive updates by text.'],
                    ['name' => 'Broadcast sends', 'description' => 'Compose a message once and blast it to your full email or SMS list, or to a filtered segment.'],
                ],
            ],
            [
                'id' => 'feed',
                'icon' => 'fa-rss',
                'heading' => 'Creators feed & followers',
                'intro' => 'Run your own social-style feed where supporters can follow you, without sending them off to a third-party network.',
                'features' => [
                    ['name' => 'Social-style creators feed', 'description' => 'Post updates, photos, and announcements to a feed your audience can scroll like a social timeline.'],
                    ['name' => 'OTP follow via email', 'description' => 'Visitors confirm with a one-time code sent to their email address, so the follow is verified.'],
                    ['name' => 'OTP follow via SMS', 'description' => 'Visitors can also follow with a one-time code sent to their phone for verified mobile follows.'],
                    ['name' => 'Follow updates', 'description' => 'Followers automatically get notified when you publish new posts, so they never miss an update.'],
                ],
            ],
            [
                'id' => 'buzz',
                'icon' => 'fa-bell',
                'heading' => 'Social proof / Buzz widgets',
                'intro' => 'Build trust on your biolink by showing real activity from real visitors as it happens.',
                'features' => [
                    ['name' => 'Floating recent-activity notifications', 'description' => 'Small pop-up cards that surface recent visitors, signups, or purchases to nudge new visitors to take action.'],
                ],
            ],
            [
                'id' => 'workspaces',
                'icon' => 'fa-users',
                'heading' => 'Workspaces & team collaboration',
                'intro' => 'Work alongside teammates and clients with separate workspaces, granular roles, and clean invitations.',
                'features' => [
                    ['name' => 'Multi-workspace switching', 'description' => 'Keep separate workspaces for different brands or clients and switch between them with one click.'],
                    ['name' => 'Admin role', 'description' => 'Full control over the workspace, including billing, members, and every link or page.'],
                    ['name' => 'Editor role', 'description' => 'Create and edit links, biolinks, and posts without touching billing or member management.'],
                    ['name' => 'Replier role', 'description' => 'Read and reply to inbox messages without being able to change content or settings.'],
                    ['name' => 'Viewer role', 'description' => 'Read-only access to analytics and content for stakeholders who only need to look in.'],
                    ['name' => 'Invite landing pages', 'description' => 'Send a clean, branded invite page so new members can accept and onboard in seconds.'],
                ],
            ],
            [
                'id' => 'vault',
                'icon' => 'fa-vault',
                'heading' => 'Vault',
                'intro' => 'Store sensitive client information securely inside 1INME instead of scattering it across notes apps and chats.',
                'features' => [
                    ['name' => 'Encrypted credential storage', 'description' => 'Save logins, API keys, and secret notes encrypted at rest so only authorised members can decrypt them.'],
                    ['name' => 'Audit logging on reveal', 'description' => 'Every time a credential is revealed it gets logged with the user and timestamp for full accountability.'],
                    ['name' => 'Client records with notes', 'description' => 'Keep structured records of each client with notes you can update over time.'],
                    ['name' => 'Client attachments', 'description' => 'Attach contracts, briefs, and other files directly to a client record so everything stays in one place.'],
                ],
            ],
            [
                'id' => 'kanban',
                'icon' => 'fa-clipboard-check',
                'heading' => 'Kanban task boards',
                'intro' => 'Manage work without leaving 1INME using flexible boards that fit how your team actually operates.',
                'features' => [
                    ['name' => 'Boards with columns', 'description' => 'Spin up boards with custom columns to track work through any stage you define.'],
                    ['name' => 'Subtasks', 'description' => 'Break a card into subtasks and tick them off as the work progresses.'],
                    ['name' => 'Assignees', 'description' => "Assign one or more team members to a card so it's clear who owns what."],
                    ['name' => 'Labels', 'description' => 'Tag cards with colour-coded labels for quick categorisation and filtering.'],
                    ['name' => 'Comments', 'description' => 'Discuss work in-thread on each card without bouncing to another tool.'],
                    ['name' => 'Attachments', 'description' => 'Pin files and documents to a card so all the context lives with the task.'],
                ],
            ],
            [
                'id' => 'crm',
                'icon' => 'fa-address-book',
                'heading' => 'CRM address book & dialer',
                'intro' => 'Keep every contact you collect in a proper address book and reach out without juggling extra apps.',
                'features' => [
                    ['name' => 'Contacts address book', 'description' => 'A central directory of every person you talk to, with rich profile details.'],
                    ['name' => 'Import contacts', 'description' => "Bring contacts in from CSV files so you don't have to retype anything."],
                    ['name' => 'Export contacts', 'description' => 'Download your full contact list as CSV for backups or other tools.'],
                    ['name' => 'Built-in dialer', 'description' => 'Tap a contact to call them directly from inside 1INME without copy-pasting numbers.'],
                    ['name' => 'Google Contacts sync', 'description' => 'Two-way sync with your Google Contacts so changes flow between both sides automatically.'],
                ],
            ],
            [
                'id' => 'calendar',
                'icon' => 'fa-calendar-days',
                'heading' => 'Calendar sync',
                'intro' => 'Keep your real calendars in the loop whenever someone books with you or RSVPs to your event link.',
                'features' => [
                    ['name' => 'Google Calendar sync', 'description' => 'Connect a Google Calendar so 1INME events appear and update in your day-to-day schedule.'],
                    ['name' => 'Microsoft / Outlook sync', 'description' => 'Sync with Microsoft 365 or Outlook calendars for full visibility on the Microsoft side.'],
                    ['name' => 'CalDAV sync', 'description' => 'Use CalDAV to sync with Apple Calendar, Fastmail, and other standards-based calendars.'],
                    ['name' => 'RSVPs for event links', 'description' => 'Create event links visitors can RSVP to, with their response captured against the event.'],
                ],
            ],
            [
                'id' => 'account',
                'icon' => 'fa-user-shield',
                'heading' => 'Account & verification',
                'intro' => 'Flexible identity tools that fit creators, agencies, and people who wear multiple hats.',
                'features' => [
                    ['name' => 'Verified blue-tick', 'description' => 'Apply for a verified badge that proves your identity to your visitors and followers.'],
                    ['name' => 'Multi-identity login', 'description' => 'Sign in with email, phone, or social providers and link them all to the same account.'],
                    ['name' => 'Account merge', 'description' => 'Combine two accounts into one if you signed up twice by mistake, keeping all the content.'],
                    ['name' => 'Persona-based onboarding', 'description' => 'Pick the persona that matches you (creator, business, agency) and get a tailored setup flow.'],
                ],
            ],
            [
                'id' => 'billing',
                'icon' => 'fa-credit-card',
                'heading' => 'Billing & plans',
                'intro' => 'Transparent subscription billing with all the extras serious customers expect.',
                'features' => [
                    ['name' => 'Monthly subscriptions', 'description' => 'Pay month-to-month on any plan and cancel whenever you need to.'],
                    ['name' => 'Yearly subscriptions', 'description' => 'Switch to yearly billing and save compared to the monthly rate.'],
                    ['name' => 'Add-ons', 'description' => 'Top up your plan with add-ons for things like extra capacity without changing tiers.'],
                    ['name' => 'Automatic tax', 'description' => 'Sales tax and VAT are calculated and added to invoices automatically based on your location.'],
                    ['name' => 'PDF invoices', 'description' => 'Download a clean PDF invoice for every charge for your records or accountant.'],
                ],
            ],
            [
                'id' => 'referrals',
                'icon' => 'fa-gift',
                'heading' => 'Referral program',
                'intro' => 'Reward the people who tell their network about 1INME with a built-in referral system.',
                'features' => [
                    ['name' => 'Referral tracking', 'description' => 'Every signup that comes from your referral link is tracked back to you automatically.'],
                    ['name' => 'Custom referral codes', 'description' => "Pick a memorable referral code instead of a long URL so it's easy to share by voice or in a story."],
                ],
            ],
            [
                'id' => 'public-surfaces',
                'icon' => 'fa-globe',
                'heading' => 'Public marketing surfaces',
                'intro' => 'Discoverability features that bring new visitors to creators on 1INME without extra work.',
                'features' => [
                    ['name' => 'Discovery directory', 'description' => 'A public directory where creators with opted-in profiles can be found by category and interest.'],
                    ['name' => 'Creators Feed page', 'description' => 'A site-wide feed of recent creator posts that surfaces fresh activity to new visitors.'],
                    ['name' => 'API documentation page', 'description' => 'Public API docs that show developers exactly how to build on top of the 1INME platform.'],
                ],
            ],
        ];
    }

    /**
     * Normalise a stored features-page sections array into the strict
     * categories shape used by the public blade and admin editor. Drops
     * empty rows, trims strings, ensures every category has a stable id,
     * and always returns a clean numerically-indexed array.
     */
    public static function normalizeFeaturesCategories(array $sections): array
    {
        $out = [];
        foreach ($sections as $cat) {
            if (!is_array($cat)) {
                continue;
            }
            $heading = trim((string) ($cat['heading'] ?? ''));
            $intro   = trim((string) ($cat['intro'] ?? ($cat['body'] ?? '')));
            $icon    = trim((string) ($cat['icon'] ?? 'fa-circle'));
            $id      = trim((string) ($cat['id'] ?? ''));
            if ($id === '') {
                $id = \Illuminate\Support\Str::slug($heading) ?: ('cat-' . (count($out) + 1));
            }
            $features = [];
            foreach ((array) ($cat['features'] ?? []) as $f) {
                if (is_array($f) && array_is_list($f) && count($f) >= 2) {
                    $name = trim((string) $f[0]);
                    $desc = trim((string) $f[1]);
                } else {
                    $name = trim((string) ($f['name'] ?? ''));
                    $desc = trim((string) ($f['description'] ?? ''));
                }
                if ($name === '' && $desc === '') {
                    continue;
                }
                $features[] = ['name' => $name, 'description' => $desc];
            }
            if ($heading === '' && $intro === '' && empty($features)) {
                continue;
            }
            $out[] = [
                'id'       => $id,
                'icon'     => $icon !== '' ? $icon : 'fa-circle',
                'heading'  => $heading,
                'intro'    => $intro,
                'features' => $features,
            ];
        }
        return $out;
    }

    /**
     * Definition of the supported social networks: app-setting key,
     * human label, and the FontAwesome brand icon class to render in the
     * public footer.
     */
    public static function socialNetworks(): array
    {
        return [
            'social_link_twitter'   => ['label' => 'X (Twitter)', 'icon' => 'fa-x-twitter',  'placeholder' => 'https://x.com/your-handle'],
            'social_link_instagram' => ['label' => 'Instagram',   'icon' => 'fa-instagram',  'placeholder' => 'https://instagram.com/your-handle'],
            'social_link_facebook'  => ['label' => 'Facebook',    'icon' => 'fa-facebook',   'placeholder' => 'https://facebook.com/your-page'],
            'social_link_linkedin'  => ['label' => 'LinkedIn',    'icon' => 'fa-linkedin',   'placeholder' => 'https://linkedin.com/company/your-page'],
            'social_link_youtube'   => ['label' => 'YouTube',     'icon' => 'fa-youtube',    'placeholder' => 'https://youtube.com/@your-channel'],
            'social_link_tiktok'    => ['label' => 'TikTok',      'icon' => 'fa-tiktok',     'placeholder' => 'https://tiktok.com/@your-handle'],
            'social_link_github'    => ['label' => 'GitHub',      'icon' => 'fa-github',     'placeholder' => 'https://github.com/your-org'],
            'social_link_threads'   => ['label' => 'Threads',     'icon' => 'fa-threads',    'placeholder' => 'https://threads.net/@your-handle'],
        ];
    }
}
