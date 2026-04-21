<?php

namespace Database\Seeders;

use App\Modules\Common\Models\FaqItem;
use App\Modules\Common\Models\SitePage;
use Illuminate\Database\Seeder;

class SitePagesSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'slug' => 'home',
                'title' => 'Your link, your page, your audience. All in one.',
                'meta_description' => '1INME is the all-in-one link platform: drag-and-drop biolinks, short links, dynamic QR codes, live analytics, and more.',
                'sections' => [
                    ['heading' => 'Hero tagline', 'body' => 'Build a beautiful biolink page, share it with short links and QR codes, and grow with live analytics.'],
                ],
            ],
            [
                'slug' => 'features',
                'title' => 'Features',
                'meta_description' => 'Everything you get with 1INME — biolinks, short links, QR codes, analytics, forms, and more.',
                'sections' => [
                    ['heading' => 'Drag & drop biolinks', 'body' => 'Stack blocks for text, images, video, audio and embeds. Reorder by dragging. Pick a theme. Go live.'],
                    ['heading' => 'Short links & QR codes', 'body' => 'Branded short links and dynamic QR codes you can repoint at any time without reprinting.'],
                    ['heading' => 'Live analytics', 'body' => 'See live visitors, geographic heatmaps, click trends and a Performance Coach that suggests fixes.'],
                    ['heading' => 'Forms & contacts', 'body' => 'Embed forms anywhere, capture submissions, and sync contacts to power your dialer and broadcasts.'],
                ],
            ],
            [
                'slug' => 'how-it-works',
                'title' => 'How it works',
                'meta_description' => 'Get started in three steps — sign up free, build your page, and share it everywhere.',
                'sections' => [
                    ['heading' => '1. Sign up free', 'body' => 'Create an account in under a minute. No credit card needed.'],
                    ['heading' => '2. Build your page', 'body' => 'Drag and drop blocks to design your biolink. Add short links, QR codes, forms and more.'],
                    ['heading' => '3. Share & grow', 'body' => 'Share one URL everywhere. Watch live analytics roll in and let the Performance Coach guide your next move.'],
                ],
            ],
            [
                'slug' => 'about',
                'title' => 'About 1INME',
                'meta_description' => 'We help creators, freelancers and small businesses turn one link into a complete online presence.',
                'sections' => [
                    ['heading' => 'Our mission', 'body' => 'One link should do everything — show your work, capture leads, sell, and tell your story. We make that easy.'],
                    ['heading' => 'Built for creators', 'body' => 'Whether you are a creator, coach, freelancer or small business, 1INME gives you the tools to grow without juggling ten apps.'],
                ],
            ],
            [
                'slug' => 'contact',
                'title' => 'Contact us',
                'meta_description' => 'Get in touch with the 1INME team. We usually reply within one business day.',
                'sections' => [
                    ['heading' => 'We love hearing from you', 'body' => 'Have a question, a suggestion, or need help getting set up? Send us a note and we will get back to you within one business day.'],
                ],
            ],
            [
                'slug' => 'faqs',
                'title' => 'Frequently asked questions',
                'meta_description' => 'Answers to the most common questions about 1INME, plans, billing and getting started.',
                'sections' => [],
            ],
            [
                'slug' => 'terms',
                'title' => 'Terms & Conditions',
                'meta_description' => 'The terms governing your use of 1INME.',
                'sections' => [
                    ['heading' => '1. Acceptance', 'body' => 'By accessing or using 1INME you agree to these terms. If you do not agree, please do not use the service.'],
                    ['heading' => '2. Your account', 'body' => 'You are responsible for all activity under your account. Keep your sign-in details safe.'],
                    ['heading' => '3. Acceptable use', 'body' => 'Do not use 1INME to host illegal content, send spam, or abuse other users.'],
                    ['heading' => '4. Termination', 'body' => 'We may suspend or close accounts that violate these terms.'],
                ],
            ],
            [
                'slug' => 'refunds',
                'title' => 'Refunds Policy',
                'meta_description' => 'How refunds work for 1INME paid plans.',
                'sections' => [
                    ['heading' => 'Refund window', 'body' => 'You can request a full refund within 7 days of any new paid plan purchase.'],
                    ['heading' => 'How to request', 'body' => 'Email our team from your account email and include your invoice number. We process refunds within 5 business days.'],
                ],
            ],
            [
                'slug' => 'privacy',
                'title' => 'Privacy Policy',
                'meta_description' => 'How 1INME collects, uses and protects your data.',
                'sections' => [
                    ['heading' => 'What we collect', 'body' => 'We collect the information you give us (account details, content) and basic usage data needed to run the service.'],
                    ['heading' => 'How we use it', 'body' => 'To provide the service, support you, and improve our product. We never sell your personal data.'],
                    ['heading' => 'Your rights', 'body' => 'You can access, export or delete your data at any time from your account settings.'],
                ],
            ],
            [
                'slug' => 'gdpr',
                'title' => 'GDPR Policy',
                'meta_description' => 'Our compliance with the EU General Data Protection Regulation.',
                'sections' => [
                    ['heading' => 'Lawful basis', 'body' => 'We process your personal data on the basis of contract performance, legitimate interest, and your consent where required.'],
                    ['heading' => 'Your rights under GDPR', 'body' => 'You can request access, correction, deletion, restriction, portability or object to processing at any time.'],
                    ['heading' => 'Data transfers', 'body' => 'Where data leaves the EU we rely on Standard Contractual Clauses to protect your rights.'],
                ],
            ],
            [
                'slug' => 'discovery',
                'title' => 'Discover biolinks',
                'meta_description' => 'Browse public 1INME biolink pages — find creators, brands and businesses sharing their work.',
                'sections' => [
                    ['heading' => 'Find your next favourite link', 'body' => 'Browse the latest public biolink pages on 1INME. Search by name, handle or topic and tap any card to open the page.'],
                ],
            ],
            [
                'slug' => 'creators-feed',
                'title' => 'Creators feed',
                'meta_description' => 'The latest posts from creators on 1INME — updates, drops, news and more.',
                'sections' => [
                    ['heading' => 'Fresh from the community', 'body' => 'See what creators on 1INME are posting right now. Follow your favourites from their biolink page to never miss an update.'],
                ],
            ],
            [
                'slug' => 'workspace-team',
                'title' => 'Workspaces & Team',
                'meta_description' => 'Run 1INME with your whole team — multiple workspaces, members, roles, granular permissions and per-workspace billing.',
                'sections' => [
                    ['heading' => 'A workspace for every brand or client', 'body' => "Spin up as many workspaces as you need — one per brand, client or side project. Switch between them in a click. Each workspace has its own biolinks, short links, analytics, contacts and settings, fully isolated from the rest."],
                    ['heading' => 'Invite the people who matter', 'body' => "Invite teammates, freelancers or clients by email. They join with their own account, see only the workspaces they belong to, and can be removed at any time. No more shared logins or password sharing."],
                    ['heading' => 'Roles that fit how you work', 'body' => "Owner, Admin, Editor and Viewer roles cover the common cases out of the box. Owners hold billing, admins manage the team, editors build pages, and viewers see analytics without changing anything."],
                    ['heading' => 'Granular permissions', 'body' => "Lock down who can publish pages, change short link destinations, see contacts, export data, or touch billing. Permissions are checked on every action — both in the dashboard and the API."],
                    ['heading' => 'Billing per workspace', 'body' => "Each workspace has its own plan, invoices and payment method. Bill agency clients separately, keep personal and work usage apart, and upgrade only the workspaces that need more."],
                    ['heading' => 'Audit & accountability', 'body' => "Every important change is attributed to the teammate who made it, so you always know who edited what — useful for agencies, larger teams and regulated workflows."],
                ],
                'cta_label' => 'Start your workspace free',
                'cta_url' => '/register',
            ],
            [
                'slug' => 'buzz',
                'title' => 'Buzz — social proof for your biolink',
                'meta_description' => 'Buzz shows live signups, visits and purchases on your 1INME biolink page so visitors see real momentum and are more likely to act.',
                'sections' => [
                    ['heading' => 'Real activity, in real time', 'body' => "Buzz pops up tasteful little notifications on your biolink page when something happens — a new follower, a recent visit, a purchase, a form submission. Visitors instantly see that other people are engaging, which builds trust and lifts conversion."],
                    ['heading' => 'Already wired into your biolink', 'body' => "Buzz is built into every 1INME biolink page. Turn it on from your dashboard, pick the events you want to surface, and it starts showing up on your page — no code, no embed, no extra setup."],
                    ['heading' => 'You decide what gets shown', 'body' => "Choose which events count as social proof: signups, follows, page views, purchases, form submissions or custom events. Hide the ones you don't want and reorder priorities to highlight what matters most for your goal."],
                    ['heading' => 'Privacy-respecting by default', 'body' => "Names are masked or anonymised, locations are coarse, and visitors can dismiss popups. You stay compliant with privacy expectations while still getting the conversion lift."],
                    ['heading' => 'Style it to match your page', 'body' => "Pick the position, animation, and accent colour so Buzz feels native to your biolink theme. Light, dark, glass — it adapts."],
                    ['heading' => 'Works beyond biolinks too', 'body' => "Drop the same widget on any other page you own with a single embed snippet, and pipe in custom events from your own apps when you want full control."],
                ],
                'cta_label' => 'Turn on Buzz on your page',
                'cta_url' => '/register',
            ],
            [
                'slug' => 'error-404',
                'title' => 'Page not found',
                'meta_description' => 'The page you were looking for does not exist or has been moved.',
                'sections' => [
                    ['heading' => "We can't find that page", 'body' => "The link you followed may be broken, or the page may have been removed. Try heading back to the homepage to find what you were looking for."],
                ],
                'cta_label' => 'Back to home',
                'cta_url' => '/',
            ],
            [
                'slug' => 'error-403',
                'title' => 'No access',
                'meta_description' => "You don't have permission to view this page.",
                'sections' => [
                    ['heading' => "You don't have access to this page", 'body' => "You may need to sign in with a different account, or ask the page owner for permission. If you think this is a mistake, please get in touch."],
                ],
                'cta_label' => 'Back to home',
                'cta_url' => '/',
            ],
            [
                'slug' => 'cookies',
                'title' => 'Cookie Policy',
                'meta_description' => 'How 1INME uses cookies and similar technologies.',
                'sections' => [
                    ['heading' => 'What cookies we use', 'body' => 'Strictly necessary cookies to keep you signed in, plus analytics cookies to understand how the product is used.'],
                    ['heading' => 'Managing cookies', 'body' => 'You can disable non-essential cookies from your browser settings at any time.'],
                ],
            ],
        ];

        foreach ($pages as $p) {
            SitePage::updateOrCreate(['slug' => $p['slug']], [
                'title' => $p['title'],
                'meta_description' => $p['meta_description'],
                'sections' => $p['sections'],
                'cta_label' => $p['cta_label'] ?? null,
                'cta_url' => $p['cta_url'] ?? null,
            ]);
        }

        $faqs = [
            ['Is there a free plan?', 'Yes — our Free plan is forever free and lets you create biolinks, short links and QR codes.'],
            ['Do I need a credit card to sign up?', 'No. Sign up with just your email or phone — no card required.'],
            ['Can I use my own domain?', 'Yes. Paid plans let you connect a custom domain for your short links and biolink page.'],
            ['How do I cancel?', 'You can downgrade to the Free plan or cancel from your account settings at any time.'],
            ['Do you offer refunds?', 'Yes — see our Refunds Policy. New paid plan purchases are refundable within 7 days.'],
        ];
        foreach ($faqs as $i => [$q, $a]) {
            FaqItem::updateOrCreate(
                ['page_slug' => 'faqs', 'question' => $q],
                ['answer' => $a, 'sort_order' => $i]
            );
        }
    }
}
