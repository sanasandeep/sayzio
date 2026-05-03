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
     * Slugs handled by the dedicated policy renderer (richer layout with
     * a sticky table of contents, anchored sections, intro, last-updated
     * date, and a footer contact block).
     */
    public static function policySlugs(): array
    {
        return ['terms', 'privacy', 'refunds', 'cookies', 'gdpr'];
    }

    /**
     * Lawyer-style default copy for the five long-form policy pages.
     * Each section ships with a stable `id` (used as the anchor and as
     * the merge key when backfilling), `heading`, `body`, and `visible`
     * flag (admins can hide a section without deleting it).
     */
    public static function policyDefaults(): array
    {
        $today = date('Y-m-d');

        return [
            'terms' => [
                'title' => 'Terms of Service',
                'meta_description' => 'The terms governing your use of 1INME — your account, what you can publish, billing, intellectual property and how the relationship can end.',
                'intro' => 'These Terms of Service ("Terms") govern your access to and use of the 1INME website, dashboard, APIs and related services (the "Service"). Please read them carefully — by creating an account or using the Service you agree to be bound by them.',
                'last_updated_at' => $today,
                'sections' => [
                    ['id' => 'acceptance', 'heading' => '1. Acceptance of these terms', 'body' => "By creating an account, accessing or otherwise using the Service you confirm that you have read, understood and agree to be bound by these Terms and our Privacy Policy. If you are entering into these Terms on behalf of a company or other legal entity, you represent that you have the authority to bind that entity, in which case \"you\" and \"your\" refer to that entity. If you do not agree, you must not use the Service."],
                    ['id' => 'eligibility-accounts', 'heading' => '2. Eligibility and accounts', 'body' => "You must be at least 16 years old (or the age of digital consent in your jurisdiction, whichever is higher) to create an account. You agree to provide accurate, current and complete information during registration and to keep it up to date. You are responsible for safeguarding your credentials, for all activity under your account, and for any actions taken by teammates or collaborators you invite. Notify us immediately of any unauthorised access."],
                    ['id' => 'description-of-service', 'heading' => '3. Description of the service', 'body' => "1INME is a link-in-bio and short-link platform that lets you publish public profile pages, shorten URLs, generate dynamic QR codes, capture leads through forms, manage contacts, send broadcasts, view analytics and run related workflows. Features evolve over time and we may add, change or remove functionality. Material changes are announced inside the dashboard or by email."],
                    ['id' => 'acceptable-use', 'heading' => '4. Acceptable use', 'body' => "You agree not to use the Service to: (a) violate any law, regulation or third-party right; (b) host, link to or distribute illegal, harmful, defamatory, obscene, hateful, fraudulent, deceptive or infringing content; (c) operate phishing, malware, scams, pyramid or unauthorised affiliate schemes; (d) send unsolicited bulk messages or spam through any channel; (e) interfere with, disrupt or attempt to gain unauthorised access to the Service, other accounts or our infrastructure; (f) reverse engineer, scrape or rate-abuse the Service; or (g) impersonate any person or misrepresent your affiliation. We may remove content and suspend or terminate accounts that breach this section."],
                    ['id' => 'user-content', 'heading' => '5. Your content and licence to us', 'body' => "You retain all ownership of the content you upload, publish or transmit through the Service (\"User Content\"). You grant 1INME a worldwide, non-exclusive, royalty-free licence to host, store, reproduce, modify (for technical purposes such as resizing or formatting), display and distribute your User Content solely as needed to operate, provide, improve and promote the Service. You represent and warrant that you have all rights necessary to grant this licence and that your User Content does not infringe any third-party rights."],
                    ['id' => 'intellectual-property', 'heading' => '6. Intellectual property', 'body' => "The Service, including its software, design, templates, logos, trademarks and documentation, is owned by 1INME or its licensors and is protected by intellectual property laws. Subject to your compliance with these Terms, we grant you a limited, non-exclusive, non-transferable, revocable licence to access and use the Service for its intended purpose. You may not copy, modify, distribute, sell or create derivative works of the Service except as expressly permitted."],
                    ['id' => 'paid-plans-billing', 'heading' => '7. Paid plans, billing and renewals', 'body' => "Paid plans are billed in advance on a monthly or yearly cycle and renew automatically using the payment method on file until cancelled. Prices are listed on our pricing page and may change with at least 30 days' notice for new billing periods. Taxes may apply based on your billing address. You authorise us (and our payment processors) to charge the applicable fees, taxes and any usage-based add-ons to your payment method. Failed payments may result in downgrade or suspension after a grace period."],
                    ['id' => 'free-trials', 'heading' => '8. Free trials and promotional offers', 'body' => "We may from time to time offer free trials or promotional pricing. Unless explicitly stated otherwise, at the end of any trial your account will automatically convert to the relevant paid plan and the payment method on file will be charged. You can cancel before the trial ends to avoid being billed. Promotional offers are limited to one per customer and may not be combined with other offers."],
                    ['id' => 'cancellation', 'heading' => '9. Cancellation and downgrade', 'body' => "You can cancel or downgrade your paid plan at any time from your account settings. Cancellation takes effect at the end of the current billing period — you keep paid features until then and are not billed again. Downgrades may reduce limits and disable certain features immediately or at renewal. See the Refunds Policy for refund eligibility."],
                    ['id' => 'third-party-services', 'heading' => '10. Third-party services and integrations', 'body' => "The Service may integrate with third-party platforms (e.g. payment processors, email providers, analytics, social networks, CRMs). Your use of those services is governed by their own terms and privacy policies, and we are not responsible for their availability, accuracy or actions. You are responsible for the configuration of any integrations you enable."],
                    ['id' => 'disclaimers', 'heading' => '11. Disclaimers', 'body' => "The Service is provided \"as is\" and \"as available\" without warranties of any kind, express or implied, including warranties of merchantability, fitness for a particular purpose, non-infringement and accuracy. We do not warrant that the Service will be uninterrupted, error-free, secure or that defects will be corrected. Some jurisdictions do not allow the exclusion of certain warranties, in which case those exclusions may not apply to you."],
                    ['id' => 'limitation-of-liability', 'heading' => '12. Limitation of liability', 'body' => "To the maximum extent permitted by law, in no event will 1INME, its affiliates, officers, directors, employees or agents be liable for any indirect, incidental, special, consequential, exemplary or punitive damages, or for any loss of profits, revenue, data, goodwill or other intangible losses, arising out of or in connection with your use of the Service. Our aggregate liability for any direct damages will not exceed the greater of (a) the amounts you paid us for the Service in the twelve months preceding the event giving rise to liability, or (b) USD 100."],
                    ['id' => 'indemnification', 'heading' => '13. Indemnification', 'body' => "You agree to defend, indemnify and hold harmless 1INME and its affiliates from and against any claims, liabilities, damages, losses and expenses (including reasonable attorneys' fees) arising out of or in any way connected with: (a) your User Content; (b) your use of the Service; (c) your violation of these Terms; or (d) your violation of any third-party right, including intellectual property or privacy rights."],
                    ['id' => 'termination', 'heading' => '14. Termination', 'body' => "You may close your account at any time from your settings. We may suspend or terminate your access to the Service, in whole or in part, if you breach these Terms, if required by law, or to protect the security or integrity of the Service or other users. We will give reasonable notice where appropriate. Upon termination, your right to use the Service stops, your published pages will no longer resolve, and your data will be deleted in accordance with our Privacy Policy and retention schedule."],
                    ['id' => 'governing-law', 'heading' => '15. Governing law', 'body' => "These Terms and any dispute arising out of or in connection with them are governed by the laws of the jurisdiction in which 1INME is established, without regard to its conflict-of-law principles. Mandatory consumer protection laws of your country of residence are not affected."],
                    ['id' => 'dispute-resolution', 'heading' => '16. Dispute resolution', 'body' => "Before filing any formal claim, you agree to first contact us at the address in Section 18 and try to resolve the dispute informally for at least 30 days. If we cannot resolve it, the dispute will be submitted to the exclusive jurisdiction of the courts of the place where 1INME is established, except where mandatory law assigns jurisdiction to another court (such as your local consumer court)."],
                    ['id' => 'changes-to-terms', 'heading' => '17. Changes to these terms', 'body' => "We may update these Terms from time to time. When we make material changes, we will notify you by email or inside the dashboard at least 14 days before they take effect, and we will update the \"Last updated\" date above. Your continued use of the Service after the changes take effect constitutes your acceptance of the updated Terms."],
                    ['id' => 'contact', 'heading' => '18. Contact us', 'body' => "If you have any questions about these Terms, please contact us through the [contact page](/contact)."],
                ],
            ],
            'privacy' => [
                'title' => 'Privacy Policy',
                'meta_description' => 'How 1INME collects, uses, shares, retains and protects your personal data — and the rights you have over it.',
                'intro' => 'This Privacy Policy explains what personal data 1INME collects when you use our website, dashboard, APIs and related services, why we collect it, how we use and share it, how long we keep it, and the rights you have. We are committed to handling your data lawfully, transparently and with care.',
                'last_updated_at' => $today,
                'sections' => [
                    ['id' => 'data-we-collect', 'heading' => '1. What data we collect', 'body' => "We collect data in three broad categories:\n\n- **Account data** you give us — name, email address, phone number, password (hashed), billing address, profile handle and avatar.\n- **Profile and content data** — pages, blocks, links, files, images, forms, messages, contacts, posts and other content you create or upload.\n- **Payment data** — billing details handled by our payment processors. We never see or store full card numbers; we only retain the last four digits, brand and expiry for receipts.\n- **Usage and analytics data** — pages visited inside the dashboard, features used, clicks on your public pages, referrers and conversion events.\n- **Device, log and cookie data** — IP address, browser, operating system, language, time zone, device identifiers, and cookies/local storage entries necessary to keep you signed in and to remember preferences."],
                    ['id' => 'how-we-use', 'heading' => '2. How we use your data', 'body' => "We use your personal data to: (a) provide, maintain and improve the Service; (b) authenticate you and protect accounts; (c) process payments and prevent fraud; (d) send essential service emails (receipts, security alerts, important changes); (e) provide customer support; (f) understand how the product is used so we can improve it; (g) comply with legal obligations; and (h) with your consent where required, send product updates or marketing."],
                    ['id' => 'legal-bases', 'heading' => '3. Legal bases for processing', 'body' => "We rely on the following legal bases under the GDPR and similar laws:\n\n- **Performance of a contract** — to provide the Service you signed up for.\n- **Legitimate interests** — to secure the Service, prevent abuse, and improve our product.\n- **Legal obligation** — to keep records required for tax, accounting and law-enforcement purposes.\n- **Consent** — for optional marketing communications and any non-essential cookies, where required by law. You can withdraw consent at any time."],
                    ['id' => 'sharing', 'heading' => '4. Who we share data with', 'body' => "We share personal data only with the categories of recipient strictly required to run the Service:\n\n- **Sub-processors** — cloud hosting, transactional email, SMS gateways, payment processors, analytics, customer support tooling and error monitoring. Each is bound by a written data-processing agreement.\n- **Professional advisers** — lawyers, accountants and auditors when needed.\n- **Authorities** — when required by valid legal process or to protect our rights, users or the public.\n- **Acquirers** — in the event of a merger, acquisition or sale, in which case we will notify you before your data is transferred and becomes subject to a different privacy policy.\n\nWe do not sell your personal data and we do not run third-party advertising trackers on the public pages you publish."],
                    ['id' => 'international-transfers', 'heading' => '5. International data transfers', 'body' => "Your data may be processed in countries other than the one you live in, including outside the EU/EEA or the UK. Where we transfer personal data internationally, we rely on appropriate safeguards such as the European Commission's Standard Contractual Clauses (and the UK addendum where relevant) with each sub-processor."],
                    ['id' => 'retention', 'heading' => '6. How long we keep your data', 'body' => "We keep your account data for as long as your account is open. When you delete your account or close it, we remove personal data from active systems within 30 days and from backups within 90 days, except where we are required to keep certain records for longer (for example, invoices and tax records, typically 6–10 years depending on jurisdiction). Anonymised, aggregated statistics may be retained indefinitely."],
                    ['id' => 'security', 'heading' => '7. Security', 'body' => "We use industry-standard measures to protect personal data, including encryption in transit (TLS) and at rest, network isolation, role-based access controls, multi-factor authentication for privileged accounts, audit logging, regular backups and routine security reviews. No system is perfectly secure — please use a strong unique password and enable any additional security features available to you."],
                    ['id' => 'your-rights', 'heading' => '8. Your rights', 'body' => "Depending on where you live, you have the right to: access your personal data; correct inaccurate or incomplete data; delete your data (right to be forgotten); restrict or object to certain processing; receive your data in a portable format; and withdraw any consent you previously gave. You can exercise most of these directly from your account settings or by contacting us. We will respond to verified requests within 30 days. You also have the right to lodge a complaint with your local data-protection authority."],
                    ['id' => 'childrens-privacy', 'heading' => "9. Children's privacy", 'body' => "The Service is not directed to children under 16, and we do not knowingly collect personal data from children. If you believe we have collected data from a child, please contact us so we can delete it."],
                    ['id' => 'do-not-track', 'heading' => '10. Do Not Track', 'body' => "Some browsers can transmit a \"Do Not Track\" (DNT) signal. There is currently no industry standard for how to respond to DNT signals, so we do not respond to them. You can still control non-essential cookies via your browser settings — see our Cookie Policy for details."],
                    ['id' => 'changes', 'heading' => '11. Changes to this policy', 'body' => "We may update this Privacy Policy from time to time. When we make material changes we will notify you by email or inside the dashboard, and we will update the \"Last updated\" date above."],
                    ['id' => 'contact', 'heading' => '12. Contact us / Data Protection Officer', 'body' => "If you have any questions, requests or complaints about this Privacy Policy or how we handle your data, please contact us through the [contact page](/contact). For data-protection enquiries you can address your message to our Data Protection Officer at the same address."],
                ],
            ],
            'refunds' => [
                'title' => 'Refunds Policy',
                'meta_description' => 'How refunds work for 1INME paid plans and add-ons — eligibility, timing, exceptions and how to request one.',
                'intro' => 'We want you to be happy with 1INME. This Refunds Policy explains when you can get a refund on a paid plan or add-on, how to request one, and how long the process takes.',
                'last_updated_at' => $today,
                'sections' => [
                    ['id' => 'eligibility-window', 'heading' => '1. Eligibility window', 'body' => "You may request a full refund within **7 days** of the original purchase of any new paid plan, no questions asked. The 7-day window starts on the date of the first successful charge for the plan. The refund covers the most recent charge only — earlier billing periods are not refundable. Renewals are governed by Section 3 below."],
                    ['id' => 'how-to-request', 'heading' => '2. How to request a refund', 'body' => "To request a refund, contact us from the email address on your account through the [contact page](/contact) or by replying to your invoice email. Please include:\n\n- the invoice number,\n- the email address on the account,\n- and a brief note about what didn't work for you (this helps us improve the product).\n\nWe will confirm receipt within one business day."],
                    ['id' => 'processing-time', 'heading' => '3. Processing time', 'body' => "Approved refunds are issued back to the original payment method within **5 business days** of approval. The time it takes for the funds to appear on your statement depends on your bank or card issuer and may take an additional 3–10 business days."],
                    ['id' => 'non-refundable', 'heading' => '4. Non-refundable items', 'body' => "The following are not eligible for a refund:\n\n- usage-based add-ons (extra short links, broadcasts, storage, message credits) once they have been consumed;\n- one-off services such as setup, migration or custom design work that has already been delivered;\n- accounts terminated by us for breach of our Terms of Service.\n\nUnused, prepaid add-on capacity within the 7-day window can be refunded on request."],
                    ['id' => 'prorated-downgrades', 'heading' => '5. Downgrades and prorated credits', 'body' => "When you downgrade a paid plan mid-cycle, we do not issue a cash refund for the unused portion. Instead, the unused value is converted into account credit that is automatically applied to your next invoice."],
                    ['id' => 'renewals', 'heading' => '6. Renewals', 'body' => "Recurring renewals (monthly or yearly) are not automatically refundable. We send a renewal reminder by email before each renewal so you can cancel or downgrade beforehand. If a renewal slipped past you and you did not use the Service in the renewed period, contact us — we review these requests on a case-by-case basis."],
                    ['id' => 'chargebacks', 'heading' => '7. Chargebacks', 'body' => "Please contact us before opening a chargeback with your bank or card issuer — we can almost always resolve the issue faster and more flexibly than a chargeback can. Accounts with unresolved chargebacks may be suspended pending review, and any related fees passed on to us may be charged back to the account."],
                    ['id' => 'contact', 'heading' => '8. Contact us', 'body' => "Questions about this policy or about a specific charge? Get in touch via the [contact page](/contact)."],
                ],
            ],
            'cookies' => [
                'title' => 'Cookie Policy',
                'meta_description' => 'What cookies and similar technologies 1INME uses, why we use them, and how you can control them.',
                'intro' => 'This Cookie Policy explains what cookies and similar technologies 1INME uses on its website and dashboard, why we use them, and the choices you have. It complements our Privacy Policy.',
                'last_updated_at' => $today,
                'sections' => [
                    ['id' => 'what-are-cookies', 'heading' => '1. What cookies are', 'body' => "Cookies are small text files that a website places on your device to remember information about you between visits. We also use comparable technologies — local storage, session storage, web beacons and pixel tags — for similar purposes. In this policy we refer to all of these collectively as \"cookies\"."],
                    ['id' => 'categories', 'heading' => '2. Categories of cookies we use', 'body' => "We group cookies into the following categories:\n\n- **Strictly necessary** — required to operate the Service (sign-in, security, load balancing).\n- **Functional** — remember your preferences (theme, sidebar state, language) so the Service feels familiar.\n- **Analytics** — help us understand how the product is used so we can improve it. The data is aggregated and not used for advertising.\n- **Marketing** — used only on our marketing pages, and only with your consent where required, to measure the effectiveness of campaigns and to show relevant content."],
                    ['id' => 'cookies-table', 'heading' => '3. Specific cookies we set', 'body' => "The list below describes the main cookies we use. The exact set may vary slightly over time as the product evolves. Format: **name** — purpose — duration — provider.\n\n- **XSRF-TOKEN** — protects forms against cross-site request forgery — session — 1INME.\n- **1inme_session** — keeps you signed in and remembers your workspace — 2 weeks — 1INME.\n- **theme** — remembers your light/dark theme preference — 1 year — 1INME.\n- **sidebar** — remembers whether the dashboard sidebar is collapsed — 1 year — 1INME.\n- **_pa** — first-party product analytics — 13 months — 1INME.\n- **cookie_consent** — remembers your cookie consent choice — 1 year — 1INME."],
                    ['id' => 'pages-you-publish', 'heading' => '4. Cookies on the pages you publish', 'body' => "Pages you publish on 1INME set only the strictly necessary cookies needed to run them. We do not inject third-party advertising or marketing cookies into your visitors' browsers."],
                    ['id' => 'how-to-control', 'heading' => '5. How to control cookies', 'body' => "You can clear and block cookies at any time from your browser's settings. Most browsers also let you block third-party cookies by default. Disabling **strictly necessary** cookies will sign you out and break parts of the dashboard; disabling **functional** or **analytics** cookies is safe but may make the experience less convenient.\n\nUseful links for the most common browsers: [Chrome](https://support.google.com/chrome/answer/95647), [Firefox](https://support.mozilla.org/kb/cookies-information-websites-store-on-your-computer), [Safari](https://support.apple.com/guide/safari/manage-cookies-sfri11471/), [Edge](https://support.microsoft.com/microsoft-edge)."],
                    ['id' => 'changes', 'heading' => '6. Changes to this policy', 'body' => "We may update this Cookie Policy when we add or remove cookies, or when the law requires. We will update the \"Last updated\" date above when we do."],
                    ['id' => 'contact', 'heading' => '7. Contact us', 'body' => "Questions about cookies on 1INME? Get in touch via the [contact page](/contact)."],
                ],
            ],
            'gdpr' => [
                'title' => 'GDPR Statement',
                'meta_description' => 'Our compliance with the EU General Data Protection Regulation, including lawful bases, your rights, sub-processors and international data transfers.',
                'intro' => 'This GDPR Statement explains how 1INME complies with the EU General Data Protection Regulation (GDPR) and the UK GDPR, including the lawful bases on which we process personal data, the rights you have as a data subject, our sub-processor arrangements and how you can contact us with any concerns.',
                'last_updated_at' => $today,
                'sections' => [
                    ['id' => 'who-we-are', 'heading' => '1. Who we are (controller information)', 'body' => "1INME is the legal entity operating the Service described in our Terms. For your account information, you are the data subject and 1INME is the data controller. For personal data of your visitors and contacts that you collect through 1INME (e.g. form submissions, leads, followers), you are the data controller and 1INME acts as your data processor under a Data Processing Agreement (\"DPA\")."],
                    ['id' => 'lawful-bases', 'heading' => '2. Lawful bases', 'body' => "We process personal data on the following lawful bases under Article 6 GDPR:\n\n- **Performance of a contract (Art. 6(1)(b))** — to provide the Service to you.\n- **Legitimate interests (Art. 6(1)(f))** — to secure the Service, prevent abuse and improve our product, balanced against your rights and interests.\n- **Legal obligation (Art. 6(1)(c))** — to keep records required by tax, accounting and abuse-reporting laws.\n- **Consent (Art. 6(1)(a))** — for optional marketing communications and non-essential cookies, which you can withdraw at any time."],
                    ['id' => 'data-subjects', 'heading' => '3. Data subjects and categories of data', 'body' => "We process personal data of:\n\n- 1INME account holders and the teammates they invite;\n- visitors to our marketing site and to public pages published on 1INME;\n- contacts captured by account holders through forms, follows and other engagement features.\n\nThe categories of personal data processed are described in our Privacy Policy."],
                    ['id' => 'your-rights', 'heading' => '4. Your rights as a data subject', 'body' => "Under the GDPR you have the right to:\n\n- **Access** — request a copy of the personal data we hold about you (Art. 15).\n- **Rectification** — request that we correct inaccurate or incomplete data (Art. 16).\n- **Erasure** — request that we delete your data, subject to legal exceptions (Art. 17).\n- **Restriction** — request that we limit how we process your data (Art. 18).\n- **Portability** — receive your data in a structured, commonly used, machine-readable format (Art. 20).\n- **Objection** — object to processing based on legitimate interest, including profiling (Art. 21).\n- **Withdraw consent** — at any time, where consent is the lawful basis (Art. 7(3)).\n- **Lodge a complaint** with your supervisory authority (Art. 77)."],
                    ['id' => 'how-to-exercise', 'heading' => '5. How to exercise your rights', 'body' => "Most rights are self-service from your account settings (export, delete, correct). For the rest, contact us through the [contact page](/contact) from the email address on your account, or — if the data we hold about you was collected by another 1INME customer (e.g. you submitted their form) — contact that customer directly. We respond to verified requests within 30 days, extendable by a further two months for complex requests."],
                    ['id' => 'transfers', 'heading' => '6. International transfers and SCCs', 'body' => "Where personal data is transferred outside the EU/EEA or the UK, we rely on the European Commission's Standard Contractual Clauses (Module 2 or 3 as applicable) and the UK International Data Transfer Addendum with each sub-processor. Where additional supplementary measures are required by local law, we apply them."],
                    ['id' => 'sub-processors', 'heading' => '7. Sub-processors', 'body' => "We engage trusted sub-processors to help us deliver the Service (cloud hosting, transactional email, SMS, payments, analytics, support tooling, error monitoring). Each sub-processor is bound by a written data-processing agreement that imposes obligations equivalent to those we accept under our DPA. The current list of sub-processors is available on request."],
                    ['id' => 'breach-notification', 'heading' => '8. Personal data breach notification', 'body' => "In the unlikely event of a personal data breach that is likely to result in a risk to the rights and freedoms of natural persons, we will notify the competent supervisory authority within **72 hours** of becoming aware of it, in accordance with Article 33 GDPR, and we will inform affected users without undue delay where required by Article 34."],
                    ['id' => 'dpo', 'heading' => '9. Data Protection Officer', 'body' => "You can contact our Data Protection Officer at the address on the [contact page](/contact). Please mark your message clearly so it can be routed to the DPO."],
                    ['id' => 'supervisory-authority', 'heading' => '10. Supervisory authority', 'body' => "If you live in the EU/EEA or the UK and believe that we have not handled your personal data in accordance with the law, you have the right to lodge a complaint with your local data-protection supervisory authority. We would, however, appreciate the opportunity to address your concerns first — please contact us before going to the authority."],
                ],
            ],
        ];
    }

    /**
     * Ensure every section has a stable id and a visibility flag. Used
     * both when seeding fresh installs and when migrating existing rows
     * so the renderer and the editor can rely on these fields.
     */
    public static function normalizeSections(array $sections): array
    {
        $usedIds = [];
        $out = [];
        foreach (array_values($sections) as $i => $s) {
            $heading = trim((string) ($s['heading'] ?? ''));
            $body    = (string) ($s['body'] ?? '');
            $id      = trim((string) ($s['id'] ?? ''));
            if ($id === '') {
                $id = \Illuminate\Support\Str::slug($heading) ?: ('section-' . ($i + 1));
            }
            $base = $id;
            $n = 2;
            while (isset($usedIds[$id])) {
                $id = $base . '-' . $n;
                $n++;
            }
            $usedIds[$id] = true;
            $out[] = [
                'id'      => $id,
                'heading' => $heading,
                'body'    => $body,
                'visible' => array_key_exists('visible', $s) ? (bool) $s['visible'] : true,
            ];
        }
        return $out;
    }

    /**
     * True when the current sections still match a previously-seeded set
     * exactly (heading + body, in order), meaning no admin has edited them
     * yet and we can safely replace them wholesale with newer defaults.
     */
    public static function sectionsMatchExactly(array $current, array $previous): bool
    {
        $current = array_values($current);
        $previous = array_values($previous);
        if (count($current) !== count($previous)) {
            return false;
        }
        foreach ($current as $i => $s) {
            $h1 = trim((string) ($s['heading'] ?? ''));
            $b1 = trim((string) ($s['body'] ?? ''));
            $h2 = trim((string) ($previous[$i]['heading'] ?? ''));
            $b2 = trim((string) ($previous[$i]['body'] ?? ''));
            if ($h1 !== $h2 || $b1 !== $b2) {
                return false;
            }
        }
        return true;
    }

    /**
     * Append any default sections that are missing from the current page
     * (matched by stable id) without touching admin-edited bodies. Used
     * by both the seeder and the migration to backfill richer policy
     * defaults idempotently.
     */
    public static function mergeMissingSections(array $current, array $defaults): array
    {
        $current = self::normalizeSections($current);
        $existingIds = array_flip(array_column($current, 'id'));
        $defaults = self::normalizeSections($defaults);
        foreach ($defaults as $section) {
            if (!isset($existingIds[$section['id']])) {
                $current[] = $section;
            }
        }
        return $current;
    }

    /**
     * Slugs that the public footer links to and which therefore must
     * resolve to a real page after the migration runs.
     */
    public static function footerSlugs(): array
    {
        return [
            'features', 'how-it-works', 'workspace-team', 'buzz',
            'ai-chatbot', 'ai-agent', 'ai-widget', 'ai-voice-assistant',
            'discovery', 'creators-feed', 'faqs',
            'about', 'contact',
            'terms', 'refunds', 'privacy', 'gdpr', 'cookies',
        ];
    }

    /**
     * The four AI suite marketing pages. Each entry is admin-editable
     * via the standard SitePage editor (title, meta_description,
     * sections [heading/body], cta_label, cta_url).
     */
    public static function aiProductSlugs(): array
    {
        return ['ai-chatbot', 'ai-agent', 'ai-widget', 'ai-voice-assistant'];
    }

    /**
     * Default content for the four AI suite product pages. Returned as a
     * slug-keyed array so the seeder and migration can iterate it. Each
     * entry follows the standard SitePage shape (title, meta_description,
     * sections, cta_label, cta_url) plus an `extra` block that holds the
     * marketing-only fields (eyebrow, hero tagline, FAQ list and feature
     * key for plan gating) used by the shared ai-product blade.
     */
    public static function aiProductsDefault(): array
    {
        return [
            'ai-chatbot' => [
                'title' => 'AI Chatbot',
                'meta_description' => 'Drop a 24/7 AI chatbot onto your biolink that answers visitor questions in your voice, captures leads and books calls — no scripts to write.',
                'cta_label' => 'Add an AI chatbot to my page',
                'cta_url' => '/register',
                'sections' => [
                    ['heading' => 'Always on, always on-brand', 'body' => 'A trained AI chatbot greets every visitor, answers in your tone, and never logs off. Train it on your bio, your FAQs, your products and your past replies — and it stays in character on every conversation.'],
                    ['heading' => 'Train it on what you already have', 'body' => 'Point it at your biolink content, paste in your product copy, upload PDFs and link your FAQ page. The chatbot learns from the same source material your audience already trusts, so the answers stay accurate and on-brand.'],
                    ['heading' => 'Captures leads while you sleep', 'body' => 'When a visitor shows buying intent, the chatbot asks for the right details — name, email, what they want — and drops them straight into your contacts list, tagged and ready for follow-up.'],
                    ['heading' => 'Books calls without back-and-forth', 'body' => 'Connect a calendar and the chatbot can offer real availability, hand off to your booking link, and confirm by email so visitors leave with a slot, not a maybe.'],
                    ['heading' => 'Hand-off to a human', 'body' => 'When a question is out of scope, the chatbot quietly escalates the thread to your unified inbox so you can take over from where it left off — visitor never has to repeat themselves.'],
                    ['heading' => 'You stay in control', 'body' => 'Review every conversation, tweak the tone, blacklist topics and set guardrails. Turn the chatbot off for a campaign or pause it overnight — your settings, your call.'],
                ],
            ],
            'ai-agent' => [
                'title' => 'AI Agent',
                'meta_description' => 'A multi-step AI agent that runs real tasks for you — qualifying leads, drafting outreach, updating your CRM and following up on its own.',
                'cta_label' => 'Put an AI agent to work',
                'cta_url' => '/register',
                'sections' => [
                    ['heading' => 'A teammate, not just a chatbot', 'body' => "Where a chatbot answers questions, the AI agent gets things done. Hand it a goal — qualify this lead, follow up on this thread, draft this campaign — and it strings together the steps to deliver a result, not just a reply."],
                    ['heading' => 'Connects your tools', 'body' => 'Wire the agent into your contacts, calendar, inbox and short-link library. It reads, writes and updates across all of them so your data stays in one place and your follow-ups stay coordinated.'],
                    ['heading' => 'Runs playbooks you can edit', 'body' => "Start from ready-made playbooks — lead qualification, abandoned form recovery, post-event follow-up — then tweak the steps in plain English. No code, no JSON, just the way you'd brief a teammate."],
                    ['heading' => 'Knows when to ask', 'body' => "The agent flags judgement calls and asks for confirmation before sending the first email or booking a meeting. You stay in the loop on the moves that matter and the routine work just gets done."],
                    ['heading' => 'Memory that compounds', 'body' => "Every conversation, every contact, every result feeds back into the agent's memory so it keeps getting better at how you work — without you having to retrain it from scratch."],
                    ['heading' => 'Full audit trail', 'body' => 'Every action the agent takes is logged with the prompt, the inputs, the output and the result, so you can review what happened, roll back a step, or share the trail with your team.'],
                ],
            ],
            'ai-widget' => [
                'title' => 'AI Widget',
                'meta_description' => 'Embed an AI assistant on any website with a single snippet — answers visitor questions, captures leads and routes hot ones to your inbox.',
                'cta_label' => 'Embed an AI widget on my site',
                'cta_url' => '/register',
                'sections' => [
                    ['heading' => 'One snippet, any site', 'body' => "Paste a one-line embed snippet on your WordPress, Shopify, Webflow, Squarespace or custom site and the AI widget pops up in the corner — ready to chat with every visitor."],
                    ['heading' => 'Looks like part of your brand', 'body' => 'Match colours, font, position, avatar and greeting so the widget feels native to your site. Light, dark and glass themes ship out of the box, with full CSS overrides for advanced theming.'],
                    ['heading' => 'Trained on your content', 'body' => "Point it at any number of pages, upload product sheets and PDFs, and the widget answers from your real material — no hallucinated specs, no off-topic chatter."],
                    ['heading' => 'Captures leads in context', 'body' => "When a visitor shows intent, the widget asks for the right info, drops the contact straight into your 1INME inbox, and tags the thread with the page they were on so you have full context."],
                    ['heading' => 'Multi-language out of the box', 'body' => "The widget detects the visitor's language and replies in kind — English, Spanish, French, German, Portuguese, Italian, Arabic, Hindi and more — so you can serve a global audience without spinning up extra setups."],
                    ['heading' => 'Privacy-first analytics', 'body' => "See conversation volume, top intents, conversion to lead and drop-off — without logging anything you don't need. Visitors can clear their session in one tap, and you control retention windows."],
                ],
            ],
            'ai-voice-assistant' => [
                'title' => 'AI Voice Assistant',
                'meta_description' => "An AI voice assistant that picks up calls to your bio, answers in your voice, qualifies the caller and books or routes them — no missed leads.",
                'cta_label' => 'Turn on AI voice on my number',
                'cta_url' => '/register',
                'sections' => [
                    ['heading' => 'Never miss another call', 'body' => "Point a number at your AI voice assistant and it picks up every call — day, night, weekend — answers in your voice and handles the basics so leads never bounce to voicemail."],
                    ['heading' => 'Sounds like you, not a robot', 'body' => "Pick from a library of natural voices or clone your own from a short sample. The assistant speaks at a normal cadence, uses your wording, and feels like a real receptionist instead of a hold-music menu."],
                    ['heading' => 'Qualifies and routes', 'body' => "Trained on your services, prices and availability, the assistant answers the common questions, qualifies the caller, and either books a slot, takes a message or warm-transfers the call to your phone — based on rules you set."],
                    ['heading' => 'Books real meetings', 'body' => "Connect a calendar and the assistant offers genuine open slots, confirms by SMS and email, and adds the event to both calendars — so callers leave the line with an actual time, not a callback promise."],
                    ['heading' => 'Full transcript and recap', 'body' => "Every call gets a typed transcript, a one-paragraph recap, the contact details captured and the next step — delivered to your unified inbox so nothing falls through the cracks."],
                    ['heading' => 'You stay in control', 'body' => "Listen to recordings, edit the script, change voices, and set blackout hours when the assistant should hand straight to voicemail. Turn it off any time without losing the history."],
                ],
            ],
        ];
    }

    /**
     * Per-product FAQ entries for each AI suite marketing page. Keyed by
     * slug — returns a small list of {q, a} rows used by the shared
     * ai-product blade. Kept here so admins/devs can tune copy without
     * touching the template.
     */
    public static function aiProductFaqs(string $slug): array
    {
        $common = [
            ['q' => 'Do I need to write any prompts or code?', 'a' => 'No. You point it at your biolink, your site or your inbox, set a tone, and it learns from your existing content. You can refine answers from the dashboard at any time.'],
            ['q' => 'What languages does it support?', 'a' => 'Out of the box it understands and replies in 30+ languages and auto-detects what each visitor uses, so you can serve a global audience without setting up separate flows.'],
            ['q' => 'Can I hand off to a human?', 'a' => 'Yes. Every conversation can escalate to your unified inbox the moment a visitor asks for a person, books a call, or hits a topic you mark as human-only.'],
            ['q' => 'How is my plan billed for usage?', 'a' => 'Each plan includes a monthly conversation allowance. If you grow past it, you can top up with credits or upgrade — no surprise charges.'],
        ];
        $extra = [
            'ai-chatbot' => [
                ['q' => 'Where does the chatbot show up?', 'a' => 'It lives directly on your 1INME biolink as a chat bubble visitors can open from any device — no separate page or login required.'],
            ],
            'ai-agent' => [
                ['q' => 'What kind of tasks can the agent run?', 'a' => 'Multi-step playbooks like qualifying inbound leads, drafting follow-up sequences, updating contact fields, and scheduling calls — chained together without you in the loop.'],
            ],
            'ai-widget' => [
                ['q' => 'Does the widget work on any website?', 'a' => 'Yes. Drop the snippet into any HTML site (WordPress, Shopify, Webflow, custom) and the widget loads asynchronously — no impact on page speed.'],
            ],
            'ai-voice-assistant' => [
                ['q' => 'Do I get a phone number?', 'a' => 'Yes — we provision a number for you in supported regions. You can also forward an existing number to it so the assistant only answers when you would have missed the call.'],
            ],
        ];
        return array_merge($extra[$slug] ?? [], $common);
    }

    /**
     * AI suite category card for the dedicated /features page. Returned
     * separately so the migration that backfills the AI suite onto the
     * existing features SitePage can append it idempotently without
     * pulling in the full features defaults.
     */
    public static function aiSuiteFeaturesCategory(): array
    {
        return [
            'id' => 'ai-suite',
            'icon' => 'fa-robot',
            'heading' => 'AI suite',
            'intro' => 'A set of AI products that plug into your 1INME — a chatbot for your biolink, an agent that runs multi-step tasks, an embeddable widget for any site, and a voice assistant that picks up your calls.',
            'features' => [
                ['name' => 'AI Chatbot', 'description' => 'Trained 24/7 chatbot on your biolink that answers in your voice, captures leads and hands off to a human when needed.', 'link' => '/ai-chatbot'],
                ['name' => 'AI Agent', 'description' => 'A multi-step agent that runs playbooks across your contacts, inbox and calendar — qualifying leads and following up on its own.', 'link' => '/ai-agent'],
                ['name' => 'AI Widget', 'description' => 'Embeddable AI assistant for any website — answers questions, captures leads and routes the hot ones to your unified inbox.', 'link' => '/ai-widget'],
                ['name' => 'AI Voice Assistant', 'description' => 'AI receptionist that picks up calls to your number, qualifies callers and books or routes them — never a missed lead.', 'link' => '/ai-voice-assistant'],
            ],
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
            self::aiSuiteFeaturesCategory(),
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
                $link = '';
                if (is_array($f) && array_is_list($f) && count($f) >= 2) {
                    $name = trim((string) $f[0]);
                    $desc = trim((string) $f[1]);
                    $link = trim((string) ($f[2] ?? ''));
                } else {
                    $name = trim((string) ($f['name'] ?? ''));
                    $desc = trim((string) ($f['description'] ?? ''));
                    $link = trim((string) ($f['link'] ?? ''));
                }
                if ($name === '' && $desc === '') {
                    continue;
                }
                $features[] = ['name' => $name, 'description' => $desc, 'link' => $link];
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
     * Default narrative sections for the public /about page (intro + story).
     * Each row is the simple heading/body shape used by the generic
     * page renderer.
     */
    public static function aboutSectionsDefault(): array
    {
        return [
            ['heading' => 'About 1INME', 'body' => "We're building the simplest way for creators, freelancers and small businesses to turn one link into a complete online presence — biolinks, short links, QR codes, analytics, and more, all in one tidy place."],
            ['heading' => 'Our story', 'body' => "1INME started in 2023 in a tiny workspace in Hyderabad. Our founder kept watching small businesses and creators juggle five different tools to do one simple thing: share their work and capture leads. We thought there was a better way.\n\nWe shipped the first version of 1INME — just biolinks and short links — to a handful of friends. They loved it, broke it, told us what was missing, and we kept iterating. Today, thousands of creators across the world use 1INME to run their online presence from one URL."],
            ['heading' => 'What we believe', 'body' => "Software should respect your time and your audience. We don't sell your data, we don't bolt on features that don't earn their keep, and we ship every week. If something's broken or unclear, our team is one message away."],
        ];
    }

    /**
     * Canonical slugs (in default order) for the lower /about sections
     * that admins can re-order. Used by both the editor and the public
     * Blade view so a change here is picked up everywhere.
     */
    public static function aboutLowerSectionSlugs(): array
    {
        return ['story', 'team_band', 'founder', 'co_founders', 'team', 'milestones', 'cta'];
    }

    /**
     * Default structured "extra" payload for the public /about page —
     * founder, co-founders, team grid, and milestones timeline. All copy
     * is intentionally placeholder so admins can swap in real names,
     * bios, photos and dates from the editor without touching code.
     */
    public static function aboutExtraDefault(): array
    {
        return [
            'hero' => [
                'badge_label'      => 'About',
                'badge_icon'       => 'fa-heart',
                'side_image'       => '',
                'side_image_alt'   => 'The 1INME studio in Hyderabad',
                'location_title'   => 'Hyderabad · India',
                'location_subtitle'=> 'Remote-friendly',
                'location_icon'    => 'fa-location-dot',
                'stats' => [
                    ['value' => '120000', 'suffix' => '+', 'label' => 'Creators served', 'visible' => true],
                    ['value' => '3',      'suffix' => '',  'label' => 'Years young',    'visible' => true],
                    ['value' => '9',      'suffix' => '',  'label' => 'Teammates',      'visible' => true],
                ],
            ],
            'values' => [
                'heading'    => 'What we believe in',
                'subheading' => 'Four ideas that show up in every line of code, support reply, and roadmap call.',
                'cards' => [
                    ['icon' => 'fa-bolt',          'title' => 'Ship fast, ship calm', 'desc' => 'New things every week, never on a Friday at 5pm.'],
                    ['icon' => 'fa-users',         'title' => 'Creators first',       'desc' => 'Every line of code earns its keep by helping a creator.'],
                    ['icon' => 'fa-shield-halved', 'title' => 'Privacy by default',   'desc' => 'No spying, no shady resale, no dark patterns.'],
                    ['icon' => 'fa-globe',         'title' => 'Built remote-first',   'desc' => 'A small team across three timezones, talking by writing.'],
                ],
            ],
            'story_images' => [
                'office'    => ['url' => '', 'alt' => 'Our office'],
                'values'    => ['url' => '', 'alt' => 'Working at 1INME'],
                'team_band' => ['url' => '', 'alt' => 'The 1INME team'],
            ],
            'section_titles' => [
                'founder'            => 'Meet the founder',
                'co_founders'        => 'Co-founders',
                'team_title'         => 'The team',
                'team_subtitle'      => 'The folks shipping 1INME every week.',
                'milestones_title'   => 'Milestones',
                'milestones_subtitle'=> 'A short history of how we got here.',
            ],
            'section_order' => self::aboutLowerSectionSlugs(),
            'section_visibility' => array_fill_keys(self::aboutLowerSectionSlugs(), true),
            'cta' => [
                'heading'         => 'Want to build with us?',
                'body'            => 'Whether you are a creator with feedback or a developer who wants to join, we love hearing from you.',
                'primary_label'   => 'Try 1INME free',
                'primary_url'     => '',
                'secondary_label' => 'Say hello',
                'secondary_url'   => '',
            ],
            'founder' => [
                'name'   => 'Aarav Reddy',
                'role'   => 'Founder & CEO',
                'photo'  => '',
                'bio'    => "Aarav started 1INME after a decade of helping small businesses get online. He still does the first reply on every founder-tier support email.",
                'links'  => ['twitter' => '', 'linkedin' => ''],
            ],
            'co_founders' => [
                ['name' => 'Meera Iyer',  'role' => 'Co-founder & CTO',     'photo' => '', 'bio' => "Meera leads engineering. Previously shipped scale at two fintechs.",        'links' => ['twitter' => '', 'linkedin' => '']],
                ['name' => 'Rohan Shah',  'role' => 'Co-founder & Design',  'photo' => '', 'bio' => "Rohan owns the look and feel of 1INME — every pixel, every motion.",     'links' => ['twitter' => '', 'linkedin' => '']],
                ['name' => 'Priya Menon', 'role' => 'Co-founder & Growth',  'photo' => '', 'bio' => "Priya makes sure the people who would love 1INME actually find it.",     'links' => ['twitter' => '', 'linkedin' => '']],
            ],
            'team' => [
                ['name' => 'Karthik Rao',     'role' => 'Senior Engineer',     'photo' => '', 'bio' => "Backend & APIs."],
                ['name' => 'Anjali Verma',    'role' => 'Frontend Engineer',   'photo' => '', 'bio' => "Builder behind the dashboard."],
                ['name' => 'Sandeep Kumar',   'role' => 'Product Designer',    'photo' => '', 'bio' => "Designs the everyday flows."],
                ['name' => 'Lakshmi Nair',    'role' => 'Customer Success',    'photo' => '', 'bio' => "Probably replied to your last ticket."],
                ['name' => 'Vikram Joshi',    'role' => 'DevOps',              'photo' => '', 'bio' => "Keeps the lights on, 24/7."],
                ['name' => 'Neha Bansal',     'role' => 'Marketing',           'photo' => '', 'bio' => "Tells the 1INME story to the world."],
            ],
            'milestones' => [
                ['date' => '2023-04', 'title' => 'Idea on a whiteboard', 'description' => "An offhand conversation about how messy social bios are turns into the first sketch of 1INME."],
                ['date' => '2023-09', 'title' => 'First public beta',    'description' => "We open the doors to a handful of friends and creators. Biolinks and short links only — but it works."],
                ['date' => '2024-03', 'title' => 'Crossed 10,000 users', 'description' => "Word spreads. Creators across India and South-East Asia start moving their link-in-bio to 1INME."],
                ['date' => '2024-11', 'title' => 'Analytics & QR codes', 'description' => "Live analytics, the Performance Coach and dynamic QR codes ship — turning 1INME into a real growth tool."],
                ['date' => '2025-06', 'title' => 'Workspaces for teams', 'description' => "Agencies and small teams get proper workspaces, roles and per-workspace billing."],
                ['date' => '2026-02', 'title' => 'Hello, world',         'description' => "1INME crosses 100k creators across more than 60 countries. We're just getting started."],
            ],
        ];
    }

    /**
     * Default narrative sections for the public /contact page (intro copy).
     */
    public static function contactSectionsDefault(): array
    {
        return [
            ['heading' => 'We love hearing from you', 'body' => "Whether you have a question, hit a snag, want to suggest a feature or are exploring a partnership, drop us a note using the form below. A real person on our team will read it and reply, usually within one business day."],
        ];
    }

    /**
     * Default structured "extra" payload for the public /contact page —
     * address, email, phone, hours, social links and OpenStreetMap
     * coordinates centred on Hyderabad.
     */
    public static function contactExtraDefault(): array
    {
        return [
            'address' => "1INME Technologies Pvt Ltd\n4th Floor, Cyber Heights\nHITEC City, Madhapur\nHyderabad 500081, India",
            'email'   => 'hello@1inme.example',
            'phone'   => '+91 40 1234 5678',
            'hours'   => "Mon–Fri · 10:00 – 18:00 IST\nClosed on public holidays",
            'social'  => [
                'twitter'   => 'https://x.com/1inme',
                'instagram' => 'https://instagram.com/1inme',
                'linkedin'  => 'https://linkedin.com/company/1inme',
                'youtube'   => '',
                'facebook'  => '',
            ],
            'map' => [
                'lat'  => 17.4435,
                'lng'  => 78.3772,
                'zoom' => 14,
                'label'=> 'Our Hyderabad office',
            ],
            // Hero pill, availability/language line, side image and the small
            // floating "Friendly humans" card. Defaults mirror the literals
            // that used to be hard-coded in resources/views/public/contact.blade.php
            // so a fresh install renders identically to the pre-task state.
            'hero' => [
                'badge_label'        => 'Contact',
                'badge_icon'         => 'fa-envelope',
                'availability_text'  => 'Replies within 1 business day',
                // Empty icon = keep the current pulsing emerald dot. Set a FA class
                // (e.g. 'fa-circle') to swap the dot for a Font Awesome glyph.
                'availability_icon'  => '',
                'languages'          => 'EN · हिन्दी',
                'side_image'         => '/images/marketing/contact/hero.png',
                'side_image_alt'     => 'The 1INME support team',
                'floating_card' => [
                    'title'    => 'Friendly humans',
                    'subtitle' => 'Behind every reply',
                    'icon'     => 'fa-headset',
                ],
            ],
            // "Contact details" heading above the address/email card.
            'details_heading' => 'Contact details',
            // Three small cards rendered between the map and the contact form.
            'feature_cards' => [
                ['icon' => 'fa-bolt',      'title' => 'Fast replies',  'desc' => 'Most messages get a real human reply within a few hours.'],
                ['icon' => 'fa-handshake', 'title' => 'Partnerships',  'desc' => 'Press, integrations, agencies — pitch us, we read every one.'],
                ['icon' => 'fa-lightbulb', 'title' => 'Feature ideas', 'desc' => 'Tell us what to build next — your name is on the changelog.'],
            ],
            // Image shown next to the contact form (hidden on mobile by the public view).
            'office_image' => [
                'url' => '/images/marketing/contact/office.png',
                'alt' => 'Our office',
            ],
            // Editable copy for the contact form. Field names ("name", "email",
            // "subject", "message") and the form action stay hard-coded — only
            // the surrounding labels/placeholders/heading/submit copy is editable.
            'form' => [
                'heading'             => 'Send us a message',
                'intro'               => '',
                'name_label'          => 'Your name',
                'name_placeholder'    => '',
                'email_label'         => 'Email',
                'email_placeholder'   => '',
                'subject_label'       => 'Subject',
                'subject_placeholder' => '',
                'message_label'       => 'Message',
                'message_placeholder' => '',
                'submit_label'        => 'Send message',
            ],
            // Post-submit messages: the green success flash shown after a
            // successful submission and the (optional) custom wording for the
            // most common required-field validation errors. All blank by
            // default so the controller falls back to its literal success
            // sentence and Laravel's built-in :attribute-based phrasing.
            'messages' => [
                'success'          => '',
                'name_required'    => '',
                'email_required'   => '',
                'subject_required' => '',
                'message_required' => '',
                'email_invalid'    => '',
                'rate_limited'     => '',
            ],
        ];
    }

    /**
     * Coerce admin-submitted About "extra" payload into the canonical
     * shape: trims string fields, normalizes link sub-arrays, and drops
     * fully-empty repeatable rows. Missing top-level keys collapse to
     * empty strings/arrays so the public Blade view can always render.
     */
    public static function normalizeAboutExtra(array $input): array
    {
        // --- Hero (badge, side image, location card, three stats) ---
        $heroIn = (array) ($input['hero'] ?? []);
        $statsIn = (array) ($heroIn['stats'] ?? []);
        $stats = [];
        foreach (array_values($statsIn) as $row) {
            if (!is_array($row)) continue;
            $value = trim((string) ($row['value'] ?? ''));
            $suffix = trim((string) ($row['suffix'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));
            // A row is fully-empty if it has no value AND no label — drop it.
            if ($value === '' && $label === '' && $suffix === '') continue;
            $visibleRaw = $row['visible'] ?? true;
            $stats[] = [
                'value'   => mb_substr($value, 0, 40),
                'suffix'  => mb_substr($suffix, 0, 10),
                'label'   => mb_substr($label, 0, 120),
                'visible' => filter_var($visibleRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
            ];
            if (count($stats) >= 6) break;
        }
        $hero = [
            'badge_label'       => trim((string) ($heroIn['badge_label'] ?? '')),
            'badge_icon'        => trim((string) ($heroIn['badge_icon'] ?? '')),
            'side_image'        => trim((string) ($heroIn['side_image'] ?? '')),
            'side_image_alt'    => trim((string) ($heroIn['side_image_alt'] ?? '')),
            'location_title'    => trim((string) ($heroIn['location_title'] ?? '')),
            'location_subtitle' => trim((string) ($heroIn['location_subtitle'] ?? '')),
            'location_icon'     => trim((string) ($heroIn['location_icon'] ?? '')),
            'stats'             => $stats,
        ];

        // --- Values (heading + repeatable cards) ---
        $valuesIn = (array) ($input['values'] ?? []);
        $cardsIn = (array) ($valuesIn['cards'] ?? []);
        $cards = [];
        foreach (array_values($cardsIn) as $row) {
            if (!is_array($row)) continue;
            $icon = trim((string) ($row['icon'] ?? ''));
            $title = trim((string) ($row['title'] ?? ''));
            $desc = trim((string) ($row['desc'] ?? ''));
            if ($icon === '' && $title === '' && $desc === '') continue;
            $cards[] = [
                'icon'  => mb_substr($icon, 0, 60),
                'title' => mb_substr($title, 0, 200),
                'desc'  => mb_substr($desc, 0, 500),
            ];
            if (count($cards) >= 8) break;
        }
        $values = [
            'heading'    => trim((string) ($valuesIn['heading'] ?? '')),
            'subheading' => trim((string) ($valuesIn['subheading'] ?? '')),
            'cards'      => $cards,
        ];

        // --- Story images (office + values + team band) ---
        $storyIn = (array) ($input['story_images'] ?? []);
        $cleanImage = function ($v): array {
            $v = is_array($v) ? $v : [];
            return [
                'url' => trim((string) ($v['url'] ?? '')),
                'alt' => trim((string) ($v['alt'] ?? '')),
            ];
        };
        $storyImages = [
            'office'    => $cleanImage($storyIn['office']    ?? []),
            'values'    => $cleanImage($storyIn['values']    ?? []),
            'team_band' => $cleanImage($storyIn['team_band'] ?? []),
        ];

        // --- Section titles for the lower four sections ---
        $titlesIn = (array) ($input['section_titles'] ?? []);
        $sectionTitles = [
            'founder'             => trim((string) ($titlesIn['founder']             ?? '')),
            'co_founders'         => trim((string) ($titlesIn['co_founders']         ?? '')),
            'team_title'          => trim((string) ($titlesIn['team_title']          ?? '')),
            'team_subtitle'       => trim((string) ($titlesIn['team_subtitle']       ?? '')),
            'milestones_title'    => trim((string) ($titlesIn['milestones_title']    ?? '')),
            'milestones_subtitle' => trim((string) ($titlesIn['milestones_subtitle'] ?? '')),
        ];

        // --- Bottom CTA block ---
        $ctaIn = (array) ($input['cta'] ?? []);
        $cta = [
            'heading'         => trim((string) ($ctaIn['heading']         ?? '')),
            'body'            => trim((string) ($ctaIn['body']            ?? '')),
            'primary_label'   => trim((string) ($ctaIn['primary_label']   ?? '')),
            'primary_url'     => trim((string) ($ctaIn['primary_url']     ?? '')),
            'secondary_label' => trim((string) ($ctaIn['secondary_label'] ?? '')),
            'secondary_url'   => trim((string) ($ctaIn['secondary_url']   ?? '')),
        ];

        $founderIn = (array) ($input['founder'] ?? []);
        $founder = [
            'name'  => trim((string) ($founderIn['name']  ?? '')),
            'role'  => trim((string) ($founderIn['role']  ?? '')),
            'photo' => trim((string) ($founderIn['photo'] ?? '')),
            'bio'   => trim((string) ($founderIn['bio']   ?? '')),
            'links' => [
                'twitter'  => trim((string) ($founderIn['links']['twitter']  ?? '')),
                'linkedin' => trim((string) ($founderIn['links']['linkedin'] ?? '')),
            ],
        ];

        $cleanPersonRow = function ($p): ?array {
            if (!is_array($p)) return null;
            $name = trim((string) ($p['name'] ?? ''));
            $role = trim((string) ($p['role'] ?? ''));
            $photo = trim((string) ($p['photo'] ?? ''));
            $bio = trim((string) ($p['bio'] ?? ''));
            $tw = trim((string) ($p['links']['twitter'] ?? ''));
            $ln = trim((string) ($p['links']['linkedin'] ?? ''));
            if ($name === '' && $role === '' && $bio === '' && $photo === '') return null;
            return [
                'name' => $name, 'role' => $role, 'photo' => $photo, 'bio' => $bio,
                'links' => ['twitter' => $tw, 'linkedin' => $ln],
            ];
        };
        $coFounders = array_values(array_filter(array_map($cleanPersonRow, (array) ($input['co_founders'] ?? []))));
        $team = array_values(array_filter(array_map($cleanPersonRow, (array) ($input['team'] ?? []))));

        $milestones = [];
        foreach ((array) ($input['milestones'] ?? []) as $m) {
            if (!is_array($m)) continue;
            $date = trim((string) ($m['date'] ?? ''));
            $title = trim((string) ($m['title'] ?? ''));
            $desc = trim((string) ($m['description'] ?? ''));
            if ($date === '' && $title === '' && $desc === '') continue;
            $milestones[] = ['date' => $date, 'title' => $title, 'description' => $desc];
        }

        // --- Lower-section render order ---
        // Admins can re-order the lower seven /about sections. We accept
        // a flat list of slugs, drop unknowns/duplicates, and (when the
        // submitted list isn't empty) pad missing slugs at the end so the
        // public view never silently hides a section because the admin
        // submitted a partial list.
        $validSlugs = self::aboutLowerSectionSlugs();
        $orderIn = (array) ($input['section_order'] ?? []);
        $sectionOrder = [];
        $seenSlugs = [];
        foreach (array_values($orderIn) as $slug) {
            if (!is_string($slug)) continue;
            $slug = trim($slug);
            if (!in_array($slug, $validSlugs, true)) continue;
            if (in_array($slug, $seenSlugs, true)) continue;
            $sectionOrder[] = $slug;
            $seenSlugs[] = $slug;
        }
        if (!empty($sectionOrder)) {
            foreach ($validSlugs as $slug) {
                if (!in_array($slug, $seenSlugs, true)) {
                    $sectionOrder[] = $slug;
                }
            }
        }

        // --- Per-section visibility toggle ---
        // Admins can hide individual lower /about sections without
        // wiping their content. We accept a slug => bool map, drop
        // unknown slugs, coerce values to real booleans, and default
        // any missing slug to visible (true) so a partial submission
        // never silently hides a section.
        $visibilityIn = (array) ($input['section_visibility'] ?? []);
        $sectionVisibility = [];
        foreach ($validSlugs as $slug) {
            if (array_key_exists($slug, $visibilityIn)) {
                $bool = filter_var($visibilityIn[$slug], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                $sectionVisibility[$slug] = $bool === null ? true : $bool;
            } else {
                $sectionVisibility[$slug] = true;
            }
        }

        return [
            'hero'               => $hero,
            'values'             => $values,
            'story_images'       => $storyImages,
            'section_titles'     => $sectionTitles,
            'section_order'      => $sectionOrder,
            'section_visibility' => $sectionVisibility,
            'cta'                => $cta,
            'founder'            => $founder,
            'co_founders'        => $coFounders,
            'team'               => $team,
            'milestones'         => $milestones,
        ];
    }

    /**
     * Coerce admin-submitted Contact "extra" payload into the canonical
     * shape (address/email/phone/hours/social/map). Latitude/longitude
     * are clamped to valid ranges and zoom to 1–19; missing fields fall
     * back to safe defaults so the public page always renders.
     */
    public static function normalizeContactExtra(array $input): array
    {
        $defaults = self::contactExtraDefault();
        // Note: defaults are only consulted to backfill missing/non-numeric
        // map coordinates and zoom — text fields collapse to empty strings
        // and rely on the public view's literal fallbacks.
        $socialIn = (array) ($input['social'] ?? []);
        $social = [];
        foreach (['twitter', 'instagram', 'linkedin', 'youtube', 'facebook'] as $k) {
            $social[$k] = trim((string) ($socialIn[$k] ?? ''));
        }
        $mapIn = (array) ($input['map'] ?? []);
        $lat  = is_numeric($mapIn['lat']  ?? null) ? (float) $mapIn['lat']  : (float) $defaults['map']['lat'];
        $lng  = is_numeric($mapIn['lng']  ?? null) ? (float) $mapIn['lng']  : (float) $defaults['map']['lng'];
        $zoom = is_numeric($mapIn['zoom'] ?? null) ? (int)   $mapIn['zoom'] : (int)   $defaults['map']['zoom'];
        $lat  = max(-90.0, min(90.0, $lat));
        $lng  = max(-180.0, min(180.0, $lng));
        $zoom = max(1, min(19, $zoom));

        // --- Hero (badge, availability, languages, image, floating card) ---
        $heroIn = (array) ($input['hero'] ?? []);
        $floatIn = (array) ($heroIn['floating_card'] ?? []);
        $hero = [
            'badge_label'       => mb_substr(trim((string) ($heroIn['badge_label']       ?? '')), 0, 60),
            'badge_icon'        => mb_substr(trim((string) ($heroIn['badge_icon']        ?? '')), 0, 60),
            'availability_text' => mb_substr(trim((string) ($heroIn['availability_text'] ?? '')), 0, 200),
            'availability_icon' => mb_substr(trim((string) ($heroIn['availability_icon'] ?? '')), 0, 60),
            'languages'         => mb_substr(trim((string) ($heroIn['languages']         ?? '')), 0, 200),
            'side_image'        => trim((string) ($heroIn['side_image']        ?? '')),
            'side_image_alt'    => mb_substr(trim((string) ($heroIn['side_image_alt']    ?? '')), 0, 200),
            'floating_card'     => [
                'title'    => mb_substr(trim((string) ($floatIn['title']    ?? '')), 0, 120),
                'subtitle' => mb_substr(trim((string) ($floatIn['subtitle'] ?? '')), 0, 120),
                'icon'     => mb_substr(trim((string) ($floatIn['icon']     ?? '')), 0, 60),
            ],
        ];

        // --- Feature cards (between map and contact form) ---
        $featuresIn = (array) ($input['feature_cards'] ?? []);
        $features = [];
        foreach (array_values($featuresIn) as $row) {
            if (!is_array($row)) continue;
            $icon  = trim((string) ($row['icon']  ?? ''));
            $title = trim((string) ($row['title'] ?? ''));
            $desc  = trim((string) ($row['desc']  ?? ''));
            if ($icon === '' && $title === '' && $desc === '') continue;
            $features[] = [
                'icon'  => mb_substr($icon, 0, 60),
                'title' => mb_substr($title, 0, 200),
                'desc'  => mb_substr($desc, 0, 500),
            ];
            if (count($features) >= 6) break;
        }

        // --- Office image next to the form ---
        $officeIn = (array) ($input['office_image'] ?? []);
        $officeImage = [
            'url' => trim((string) ($officeIn['url'] ?? '')),
            'alt' => mb_substr(trim((string) ($officeIn['alt'] ?? '')), 0, 200),
        ];

        // --- Form copy (heading, intro, labels, placeholders, submit) ---
        $formIn = (array) ($input['form'] ?? []);
        $form = [
            'heading'             => mb_substr(trim((string) ($formIn['heading']             ?? '')), 0, 200),
            'intro'               => mb_substr(trim((string) ($formIn['intro']               ?? '')), 0, 500),
            'name_label'          => mb_substr(trim((string) ($formIn['name_label']          ?? '')), 0, 80),
            'name_placeholder'    => mb_substr(trim((string) ($formIn['name_placeholder']    ?? '')), 0, 200),
            'email_label'         => mb_substr(trim((string) ($formIn['email_label']         ?? '')), 0, 80),
            'email_placeholder'   => mb_substr(trim((string) ($formIn['email_placeholder']   ?? '')), 0, 200),
            'subject_label'       => mb_substr(trim((string) ($formIn['subject_label']       ?? '')), 0, 80),
            'subject_placeholder' => mb_substr(trim((string) ($formIn['subject_placeholder'] ?? '')), 0, 200),
            'message_label'       => mb_substr(trim((string) ($formIn['message_label']       ?? '')), 0, 80),
            'message_placeholder' => mb_substr(trim((string) ($formIn['message_placeholder'] ?? '')), 0, 200),
            'submit_label'        => mb_substr(trim((string) ($formIn['submit_label']        ?? '')), 0, 80),
        ];

        // --- Post-submit messages (success flash + required-field overrides) ---
        // Blank values are kept blank so the submission controller can detect
        // "use the default" without needing extra plumbing.
        $messagesIn = (array) ($input['messages'] ?? []);
        $messages = [
            'success'          => mb_substr(trim((string) ($messagesIn['success']          ?? '')), 0, 500),
            'name_required'    => mb_substr(trim((string) ($messagesIn['name_required']    ?? '')), 0, 200),
            'email_required'   => mb_substr(trim((string) ($messagesIn['email_required']   ?? '')), 0, 200),
            'subject_required' => mb_substr(trim((string) ($messagesIn['subject_required'] ?? '')), 0, 200),
            'message_required' => mb_substr(trim((string) ($messagesIn['message_required'] ?? '')), 0, 200),
            'email_invalid'    => mb_substr(trim((string) ($messagesIn['email_invalid']    ?? '')), 0, 200),
            'rate_limited'     => mb_substr(trim((string) ($messagesIn['rate_limited']     ?? '')), 0, 200),
        ];

        return [
            'address' => trim((string) ($input['address'] ?? '')),
            'email'   => trim((string) ($input['email']   ?? '')),
            'phone'   => trim((string) ($input['phone']   ?? '')),
            'hours'   => trim((string) ($input['hours']   ?? '')),
            'social'  => $social,
            'map'     => [
                'lat'   => $lat,
                'lng'   => $lng,
                'zoom'  => $zoom,
                'label' => trim((string) ($mapIn['label'] ?? '')),
            ],
            'hero'            => $hero,
            'details_heading' => mb_substr(trim((string) ($input['details_heading'] ?? '')), 0, 200),
            'feature_cards'   => $features,
            'office_image'    => $officeImage,
            'form'            => $form,
            'messages'        => $messages,
        ];
    }

    /**
     * Default trust-strip metrics shown under the landing hero.
     */
    public static function trustStripDefault(): array
    {
        return [
            ['value' => '12,000+', 'label' => 'Active creators',  'icon' => 'fa-users'],
            ['value' => '99.9%',   'label' => 'Uptime SLA',       'icon' => 'fa-bolt'],
            ['value' => '4.8/5',   'label' => 'Average rating',   'icon' => 'fa-star'],
            ['value' => '< 60s',   'label' => 'Time to first link','icon' => 'fa-stopwatch'],
        ];
    }

    /**
     * Coerce admin input into a sane trust-strip array (drops empty rows,
     * trims, caps lengths). Returns at most 6 items.
     */
    public static function normalizeTrustStrip(array $input): array
    {
        $out = [];
        foreach (array_values($input) as $row) {
            if (!is_array($row)) continue;
            $value = trim((string) ($row['value'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));
            if ($value === '' && $label === '') continue;
            $out[] = [
                'value' => mb_substr($value, 0, 60),
                'label' => mb_substr($label, 0, 120),
                'icon'  => trim((string) ($row['icon'] ?? '')) ?: 'fa-circle-check',
            ];
            if (count($out) >= 6) break;
        }
        return $out;
    }

    /**
     * Default testimonials shown on the landing & Features pages.
     */
    public static function testimonialsDefault(): array
    {
        return [
            [
                'quote' => '1INME replaced three different tools for me. The drag-and-drop biolink and the live analytics map are honestly addictive.',
                'name'  => 'Maya R.',
                'role'  => 'Content creator · 240k followers',
                'photo' => '',
            ],
            [
                'quote' => 'We onboarded the whole team in an afternoon. Workspaces and per-link analytics make client reporting trivial.',
                'name'  => 'Daniel K.',
                'role'  => 'Founder, indie agency',
                'photo' => '',
            ],
            [
                'quote' => 'The Performance Coach actually finds the weak links. Conversions on my biolink went up 32% in two weeks.',
                'name'  => 'Priya S.',
                'role'  => 'Coach & podcaster',
                'photo' => '',
            ],
        ];
    }

    /**
     * Coerce admin input into a sane testimonials array (drops empty rows,
     * trims, caps lengths). Returns at most 24 items.
     */
    public static function normalizeTestimonials(array $input): array
    {
        $out = [];
        foreach (array_values($input) as $row) {
            if (!is_array($row)) continue;
            $quote = trim((string) ($row['quote'] ?? ''));
            $name  = trim((string) ($row['name']  ?? ''));
            if ($quote === '' && $name === '') continue;
            $out[] = [
                'quote' => mb_substr($quote, 0, 1000),
                'name'  => mb_substr($name, 0, 120),
                'role'  => mb_substr(trim((string) ($row['role']  ?? '')), 0, 160),
                'photo' => trim((string) ($row['photo'] ?? '')),
            ];
            if (count($out) >= 24) break;
        }
        return $out;
    }

    /**
     * Default "Why 1INME" comparison rows shown on the landing page just
     * before pricing. Each row pairs a feature label with what 1INME offers
     * versus a typical competitor.
     */
    public static function whyComparisonDefault(): array
    {
        return [
            ['feature' => 'Drag-and-drop biolink page',                'ours' => 'Yes', 'theirs' => 'Limited'],
            ['feature' => 'Branded short links + dynamic QR',          'ours' => 'Yes', 'theirs' => 'Add-on / no'],
            ['feature' => 'Live analytics with map of clicks',         'ours' => 'Yes', 'theirs' => 'Basic charts'],
            ['feature' => 'Built-in Performance Coach (AI-style tips)','ours' => 'Yes', 'theirs' => 'No'],
            ['feature' => 'Free forever plan, no credit card',         'ours' => 'Yes', 'theirs' => 'Trial only'],
            ['feature' => 'Unlimited blocks on the free tier',         'ours' => 'Yes', 'theirs' => 'Capped'],
        ];
    }

    /**
     * Coerce admin input into a sane comparison-rows array (drops empty
     * rows, trims, caps lengths). Returns at most 12 rows.
     */
    public static function normalizeWhyComparison(array $input): array
    {
        $out = [];
        foreach (array_values($input) as $row) {
            if (!is_array($row)) continue;
            $feature = trim((string) ($row['feature'] ?? ''));
            $ours    = trim((string) ($row['ours']    ?? ''));
            $theirs  = trim((string) ($row['theirs']  ?? ''));
            if ($feature === '' && $ours === '' && $theirs === '') continue;
            $out[] = [
                'feature' => mb_substr($feature, 0, 200),
                'ours'    => mb_substr($ours, 0, 80),
                'theirs'  => mb_substr($theirs, 0, 80),
            ];
            if (count($out) >= 12) break;
        }
        return $out;
    }

    /**
     * The full categorised FAQ knowledge base shipped with the marketing
     * site. Returned as ['Category' => [[question, answer], ...]]. Used by
     * the public /faqs page, the homepage FAQ block, and the seeder.
     */
    public static function homepageFaqs(): array
    {
        return [
            'Getting started' => [
                ['Is there really a free plan?', 'Yes — the Free plan is free forever. No trial countdown, no surprise paywall, no credit card required to use it.'],
                ['Do I need a credit card to sign up?', 'No. You can sign up with just an email or phone number and start building a biolink right away.'],
                ['How long does it take to get my first link live?', 'Most people are live in under two minutes — pick a template, drop your socials in, and share the URL.'],
                ['Can I import my existing Linktree or Beacons page?', 'Yes — paste your existing page URL into the importer and we will pull the blocks, icons and links into a starter page you can edit.'],
                ['Do I need any design skills?', 'No. Every block is drag-and-drop with sensible defaults; the templates are professionally designed and fully editable.'],
                ['What is a "biolink" exactly?', 'A biolink is a single mobile-first page that holds all your links, content and contact options — perfect for the link slot in your social bios.'],
                ['Can I have more than one biolink page?', 'Yes — make as many as your plan allows. Use one per brand, project or campaign and switch between them in a click.'],
                ['Will my page work on mobile?', 'Yes. Every page is mobile-first, with optional desktop-tuned layouts and per-block visibility for either device.'],
                ['Can I preview my page before publishing?', 'Yes — the live preview updates as you edit, and an unpublished page is only visible to you and your team.'],
                ['Is 1INME suitable for non-creators (small business, freelancers)?', 'Absolutely — it is designed for creators, freelancers, agencies, restaurants, coaches, networking pros and small businesses alike.'],
            ],
            'Biolinks' => [
                ['What blocks can I add to a biolink?', 'Text, images, video, audio, products, donation buttons, social icons, embeds, forms, NFTs, calendars, maps, lead magnets and more.'],
                ['Can I reorder or hide blocks?', 'Yes — drag to reorder, click to hide, or schedule blocks to appear and disappear on specific dates.'],
                ['Can I sell products from my biolink?', 'Yes — add product blocks with images, pricing, variants, stock and Stripe-powered checkout.'],
                ['Can I collect tips or donations?', 'Yes — add a tip jar block with custom amounts, suggested tiers and an optional thank-you message.'],
                ['Can I embed a video or playlist?', 'Yes — paste a YouTube, Vimeo, Spotify, SoundCloud or Apple Music link and the embed renders inline.'],
                ['Can I add a contact form to my biolink?', 'Yes — drop in a form block; submissions land in your contacts and can fire emails or webhooks.'],
                ['Can I add multiple languages?', 'Yes — set a default language and add per-block translations; visitors see the version that matches their browser.'],
                ['Can I theme my biolink to match my brand?', 'Yes — pick from premium themes or override colours, fonts, backgrounds, button shapes and animations.'],
                ['Can I schedule a biolink to launch later?', 'Yes — schedule a publish date and time, and the page swaps from "draft" to "live" automatically.'],
                ['Can I password-protect a biolink?', 'Yes — toggle on a password; visitors must enter it before the page renders.'],
            ],
            'Short links' => [
                ['What is a branded short link?', 'A clean URL on your domain (or our default) that redirects to a long destination — repointable any time, no reprinting needed.'],
                ['Can I use my own domain for short links?', 'Yes, on paid plans. Add a CNAME record and your links read https://yourbrand.co/launch instead of the default host.'],
                ['Can I add UTMs automatically?', 'Yes — set per-link or per-domain UTM defaults and we append them on every redirect.'],
                ['Can I expire a short link?', 'Yes — expire by date, by click count, by geo, or rotate to a fallback URL once a cap is hit.'],
                ['Can I redirect by country, language or device?', 'Yes — add geo, language or device rules and route visitors to different destinations from the same short link.'],
                ['Can I password-protect a short link?', 'Yes — visitors must enter a password before being redirected.'],
                ['What link types do you support?', 'Standard redirects, file downloads, splash pages, iframes, deep links, vCards, calendar files, Wi-Fi credentials and more.'],
                ['Can I A/B test short link destinations?', 'Yes — define multiple destinations with weights and we will split traffic and measure the winner.'],
                ['What happens if a destination URL goes 404?', 'We detect broken targets and surface them in your dashboard so you can repoint without breaking shared links.'],
                ['Are short links rate-limited?', 'No — redirects are served from the edge with no per-link click cap on any plan.'],
            ],
            'QR codes' => [
                ['How do I generate a QR code?', 'Every link gets a QR code automatically — open the link, switch to the QR tab and download the size you need.'],
                ['Can I customise the QR code design?', 'Yes — colours, dot styles, eye styles, your logo in the centre, and on-brand accent gradients.'],
                ['What size should I download for print?', 'Use the SVG or 1024×1024 PNG for posters; the 512×512 PNG is plenty for stickers and packaging.'],
                ['Can I change the QR destination after printing?', 'Yes — that is the whole point of dynamic QR codes. Re-point the link any time and the printed code keeps working.'],
                ['Do QR scans count as clicks?', 'Yes — scans are tracked separately from web clicks, so you can compare offline vs online performance.'],
                ['Can a QR open my biolink directly?', 'Yes — generate the QR for your biolink URL and any phone camera will open it instantly.'],
                ['Do QR codes work without internet?', 'The scan does — but the destination needs internet, like any URL.'],
                ['Can I batch-generate QR codes?', 'Yes — bulk-create dozens of links and export the QR codes as a zip in your preferred size.'],
            ],
            'Analytics & AI Coach' => [
                ['What analytics do I get on the Free plan?', 'Real-time visitor count, geographic breakdown, device, referrer and per-block click-through — for life.'],
                ['Can I see who is visiting in real time?', 'Yes — live visitor pins on a world map show exactly where your audience is right now.'],
                ['How does the AI Performance Coach work?', 'It watches your live numbers, compares against best practice, and surfaces a small prioritised list of one-click fixes.'],
                ['Can I export analytics?', 'Yes — CSV or JSON export of clicks, sessions and conversions, plus a webhook stream for real-time pipelines.'],
                ['Do you respect Do Not Track?', 'Yes — visitors with Do Not Track enabled are counted anonymously without device fingerprints.'],
                ['Can I track conversions (signups, sales)?', 'Yes — fire a tracking event from a thank-you page, a webhook, or our JavaScript SDK and conversions show up alongside clicks.'],
                ['How is data different from Google Analytics?', 'Ours is link-level and visitor-friendly: cookieless by default, no third-party scripts on your page, ready in seconds.'],
                ['Can I see which block converts best?', 'Yes — every block reports its own clicks, view rate and CTR so you can prune dead weight in seconds.'],
                ['How long is analytics data retained?', 'Forever on paid plans; 12 months on Free. Exports work for the full retention window.'],
                ['Can I share analytics with a client?', 'Yes — generate a read-only share link or invite the client to a workspace as a Viewer.'],
            ],
            'Team & workspaces' => [
                ['What is a workspace?', 'A workspace is an isolated container for content, links, contacts and billing. Use one per brand, client or side project.'],
                ['How many teammates can I invite?', 'Depends on your plan — Free supports just you, paid plans scale from 3 to unlimited seats.'],
                ['What roles can I assign?', 'Owner, Admin, Editor, Viewer — each maps to a clear set of permissions across pages, links, contacts and billing.'],
                ['Can I limit what a teammate sees?', 'Yes — Editors only see content, Viewers only see analytics, and granular permissions can lock down specific actions.'],
                ['Can I switch workspaces quickly?', 'Yes — a switcher in the dashboard top bar jumps between workspaces in one click.'],
                ['Is billing separate per workspace?', 'Yes — each workspace has its own plan and invoices, so agencies can bill clients separately.'],
                ['Can I transfer ownership of a workspace?', 'Yes — Owners can hand off to another Admin from the workspace settings; the old Owner becomes an Admin.'],
                ['Are actions audit-logged?', 'Yes — every important change is attributed to the teammate who made it, with a 90-day audit history on paid plans.'],
                ['Can I work with freelancers without giving them my login?', 'Yes — invite them as a teammate; they get their own account and you can revoke access at any time.'],
                ['Do teammates count against my page or link limits?', 'No — limits are per-workspace, not per-seat. Add seats freely without worrying about caps.'],
            ],
            'Billing & plans' => [
                ['Does the Free plan ever expire?', 'No — the Free plan is free forever. There is no trial countdown, no automatic conversion to a paid plan, and no card on file required to sign up. You can stay on Free as long as you like; we will only ever ask for payment if you choose to upgrade.'],
                ['Can I cancel or downgrade any time?', 'Yes — cancel or downgrade from your account billing settings in a couple of clicks. Cancellations take effect at the end of the current billing period, so you keep paid features until then and are not charged again. Downgrades follow the same rule: you keep your current plan until renewal, then drop to the lower tier (your existing links, pages and contacts stay intact even if you fall under the new limits).'],
                ['Do you offer refunds?', 'Yes — new paid plan purchases are refundable in full within 7 days, no questions asked. Renewals are not automatically refundable, but if a renewal slipped past you and you did not use the service, contact us and we review it case by case. See our Refunds Policy for the full terms, including how prepaid add-ons and downgrades are handled.'],
                ['What payment methods do you accept?', 'Major credit and debit cards (Visa, Mastercard, American Express, Discover), Apple Pay, Google Pay and PayPal in supported regions. SEPA Direct Debit and iDEAL are available for European customers on annual plans. All payments are processed by our PCI-compliant payment provider — we never see or store your full card number.'],
                ['Will I be charged tax (VAT, GST, sales tax)?', 'Yes, where required by law. Sales tax, VAT or GST is calculated automatically based on the billing address you provide, shown clearly at checkout before you confirm, and itemised separately on every invoice. If you have a valid VAT or GST id, add it to your billing profile and we will reverse-charge or zero-rate the invoice where the rules allow.'],
                ['Are invoices available?', 'Yes — every charge generates a downloadable PDF invoice in your account, with company name and VAT/GST id support.'],
                ['Can I switch from monthly to annual billing?', 'Yes — switch any time from billing settings; we credit the unused portion of your current period.'],
                ['Do you offer discounts for annual billing?', 'Yes — annual plans typically include a 2-month discount versus paying monthly.'],
                ['Do you offer discounts for non-profits or students?', 'Yes — verified non-profits and students get up to 50% off paid plans. Contact us with proof.'],
                ['What happens if my payment fails?', 'We retry the charge a few times and email you. After 7 days of failed retries the workspace downgrades to the Free plan.'],
                ['Can I change plans mid-cycle?', 'Yes — upgrades take effect immediately with a pro-rated charge; downgrades take effect at next renewal.'],
                ['Will my links keep working if I downgrade?', 'Yes — your existing links keep working forever, even if you downgrade past the limits, but new links are blocked above the cap.'],
            ],
            'Custom domains' => [
                ['Can I use my own domain for my biolink?', 'Yes, on paid plans. Connect a domain or subdomain via a CNAME record and your biolink lives at your URL.'],
                ['Can I use my own domain for short links?', 'Yes — bring a custom domain and use it for branded short links across all your campaigns.'],
                ['Do you provide an SSL certificate?', 'Yes — we provision and renew a free SSL certificate for every connected domain automatically.'],
                ['Can I use the same domain for biolink and short links?', 'Yes — point the apex to your biolink and use a path or subdomain for short links.'],
                ['How long does DNS propagation take?', 'Usually 5–30 minutes. We retry validation for up to 48 hours and email you when the certificate is live.'],
                ['Can I use multiple custom domains?', 'Yes — add as many as your plan allows; each can be assigned to a different workspace or biolink.'],
                ['Do I need to host anything?', 'No — we host the domain end to end. You only configure DNS at your registrar.'],
                ['Can I set redirects from old URLs?', 'Yes — add 301 or 302 redirects from any path on your custom domain to any destination.'],
            ],
            'Security & privacy' => [
                ['Is my data encrypted?', 'Yes — TLS 1.3 in transit and AES-256 at rest, with regular external audits.'],
                ['Do you sell my data?', 'No — we never sell personal data and we do not run third-party advertising trackers on the pages you publish.'],
                ['Are you GDPR-compliant?', 'Yes — we follow GDPR for EU/EEA and UK GDPR for the UK, including standard contractual clauses for international transfers.'],
                ['Do you offer a Data Processing Agreement?', 'Yes — request our DPA from your account; we counter-sign and return it within a couple of business days.'],
                ['How do I enable two-factor authentication?', 'Open security settings and add an authenticator app or SMS — required for Owners on paid plans.'],
                ['Where is my data stored?', 'Primary storage is in the EU with replicated backups in additional regions; specifics are listed in our sub-processor register.'],
                ['How do I report a security issue?', 'Email security@1inme.app with the details and we will respond within one business day. Responsible disclosures are eligible for a thank-you bounty.'],
                ['Can visitors opt out of analytics?', 'Yes — DNT-enabled visitors are counted anonymously, and you can disable analytics entirely from your page settings.'],
                ['How long are backups kept?', 'Daily backups are retained for 30 days, with a weekly snapshot held for 90 days.'],
                ['Do you have a status page?', 'Yes — a public status page reports current uptime, scheduled maintenance and any historical incidents.'],
            ],
            'Mobile & integrations' => [
                ['Is there a mobile app?', 'Yes — native iOS and Android apps let you edit pages, scan QR codes, manage contacts and reply to messages on the go.'],
                ['Do you have an open API?', 'Yes — a documented REST API plus webhooks for every important event. Free for any plan that includes API access.'],
                ['Do you integrate with Stripe?', 'Yes — connect your Stripe account to take payments for products, tips, donations and paid links.'],
                ['Do you integrate with Mailchimp / Klaviyo?', 'Yes — sync new contacts and form submissions into either platform with a one-click integration.'],
                ['Do you integrate with Google Sheets?', 'Yes — append form submissions or new contacts to a Google Sheet in real time.'],
                ['Is there a Zapier app?', 'Yes — trigger Zaps from any 1INME event and call any 1INME action from a Zap.'],
                ['Can I add a Facebook or Google Analytics pixel?', 'Yes — drop your pixel ID in once and it loads on every page or short link in the workspace.'],
                ['Do you support webhooks for clicks and form submissions?', 'Yes — point a URL at any of our webhook events and receive a signed payload within seconds of the event.'],
                ['Can I use 1INME with my CRM?', 'Yes — sync contacts to HubSpot, Pipedrive or Salesforce via native integrations or Zapier.'],
                ['Can I disable integrations per workspace?', 'Yes — each workspace has its own integration settings and credentials.'],
            ],
            'Account & data' => [
                ['How do I export my data?', 'From account settings, request an export — we package biolinks, links, contacts, analytics and files into a downloadable archive within minutes.'],
                ['Can I delete my account?', 'Yes — request deletion from account settings; data is removed from active systems within 30 days and from backups within 90.'],
                ['Can I deactivate without deleting?', 'Yes — deactivation hides your pages and pauses billing without deleting any data; reactivate any time.'],
                ['Do you back up my data?', 'Yes — daily encrypted backups in multiple regions, with point-in-time restore for paid plans.'],
                ['How do I change my email or password?', 'Update either from security settings; an email confirmation is required to change either.'],
                ['What happens to my links if I close my account?', 'They stop resolving immediately. Visitors land on a "page not found" until you re-activate or migrate them.'],
                ['Can I transfer ownership of a biolink to another account?', 'Yes — transfer ownership from page settings; the new owner accepts the transfer in their dashboard.'],
                ['Are my pages discoverable on search engines?', 'They can be — pages set to public are indexed; private or password-protected pages are not.'],
                ['Can I hide my page from the Discover directory?', 'Yes — toggle "Show me in Discover" off in profile settings and your page disappears from the directory within minutes.'],
                ['How do I get human support?', 'Email support@1inme.app or use in-app chat — paid plans get priority response targets, with the Pro tier guaranteeing same-business-day replies.'],
            ],
        ];
    }

    /**
     * The "best of" subset of FAQs surfaced inline on the homepage. Pulls
     * one or two from each category so the homepage feels representative
     * without overwhelming the visitor.
     */
    public static function homepageFaqHighlights(): array
    {
        // Curated 6-question shortlist for the homepage. Search + the
        // full 100+ catalogue live on the dedicated /faqs page.
        $picks = [
            'Getting started'        => ['Is there really a free plan?'],
            'Biolinks'               => ['What blocks can I add to a biolink?'],
            'QR codes'               => ['Can I change the QR destination after printing?'],
            'Analytics & AI Coach'   => ['How does the AI Performance Coach work?'],
            'Billing & plans'        => ['Can I cancel or downgrade any time?'],
            'Security & privacy'     => ['Do you sell my data?'],
        ];
        $all = self::homepageFaqs();
        $out = [];
        foreach ($picks as $cat => $questions) {
            foreach ($all[$cat] ?? [] as $pair) {
                if (in_array($pair[0], $questions, true)) {
                    $out[] = ['q' => $pair[0], 'a' => $pair[1], 'category' => $cat];
                }
            }
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
