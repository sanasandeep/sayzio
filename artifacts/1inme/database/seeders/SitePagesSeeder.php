<?php

namespace Database\Seeders;

use App\Modules\Common\Models\FaqItem;
use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Database\Seeder;

class SitePagesSeeder extends Seeder
{
    public function run(): void
    {
        $rich = SitePagesContent::richDefaults();

        // Pages whose content is not centralised in SitePagesContent — the
        // marketing-focused pages that already shipped with deep copy and
        // the error pages.
        $extra = [
            [
                'slug' => 'home',
                'title' => 'Your link, your page, your audience. All in one.',
                'meta_description' => '1INME is the all-in-one link platform: drag-and-drop biolinks, short links, dynamic QR codes, live analytics, and more.',
                'sections' => [
                    ['heading' => 'Hero tagline', 'body' => 'Build a beautiful biolink page, share it with short links and QR codes, and grow with live analytics.'],
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
                'slug' => 'error-500',
                'title' => 'Something went wrong',
                'meta_description' => 'An unexpected error occurred on our side. Please try again in a moment.',
                'sections' => [
                    ['heading' => 'We hit a snag', 'body' => "Sorry — something went wrong on our end. Our team has been notified and is looking into it. Please try again in a few minutes."],
                ],
                'cta_label' => 'Back to home',
                'cta_url' => '/',
            ],
            [
                'slug' => 'error-503',
                'title' => "We'll be right back",
                'meta_description' => 'The site is temporarily down for maintenance.',
                'sections' => [
                    ['heading' => 'Down for maintenance', 'body' => "We're making some quick improvements and will be back online shortly. Thanks for your patience — please check back in a few minutes."],
                ],
                'cta_label' => 'Check our status',
                'cta_url' => '/',
            ],
            [
                'slug' => 'error-419',
                'title' => 'Your session expired',
                'meta_description' => 'For your security, your session timed out. Please refresh and try again.',
                'sections' => [
                    ['heading' => 'Session expired', 'body' => "For your security, this page has been open too long without activity. Please go back, refresh the page, and try again."],
                ],
                'cta_label' => 'Back to home',
                'cta_url' => '/',
            ],
            [
                'slug' => 'error-429',
                'title' => 'Too many requests',
                'meta_description' => "You've sent too many requests in a short time. Please slow down and try again.",
                'sections' => [
                    ['heading' => 'Slow down a moment', 'body' => "You've made a lot of requests very quickly. Please wait a few seconds before trying again."],
                ],
                'cta_label' => 'Back to home',
                'cta_url' => '/',
            ],
        ];

        $pages = $extra;
        foreach ($rich as $slug => $data) {
            $pages[] = [
                'slug' => $slug,
                'title' => $data['title'],
                'meta_description' => $data['meta_description'] ?? null,
                'sections' => $data['sections'],
                'cta_label' => $data['cta_label'] ?? null,
                'cta_url' => $data['cta_url'] ?? null,
            ];
        }

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
