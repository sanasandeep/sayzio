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
