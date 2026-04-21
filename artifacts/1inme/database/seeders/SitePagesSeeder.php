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
                'slug' => 'services',
                'title' => 'What you can do with 1INME',
                'meta_description' => 'See how marketers, creators, agencies, small businesses and event organizers use 1INME as their link-in-bio, portfolio, and audience hub.',
                'sections' => self::servicesDefaultSections(),
                'cta_label' => 'Create your 1INME',
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
            // The /features page uses a category-structured sections shape
            // (id/icon/heading/intro/features) instead of the plain
            // heading/body shape used by every other rich page.
            $sections = $slug === 'features'
                ? SitePagesContent::featuresCategoriesDefault()
                : $data['sections'];
            $pages[] = [
                'slug' => $slug,
                'title' => $data['title'],
                'meta_description' => $data['meta_description'] ?? null,
                'sections' => $sections,
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

    public static function servicesDefaultSections(): array
    {
        return [
            [
                'heading' => 'Marketing channel',
                'tagline' => 'Run campaigns from a single, trackable hub.',
                'body' => 'Turn every social bio, ad and QR code into a measurable funnel. Spin up campaign landing pages in minutes and watch what converts.',
                'icon' => 'fa-bullhorn',
                'tint' => 'from-violet-500/30 to-fuchsia-500/10',
                'bullets' => [
                    'Branded link-in-bio with UTM-friendly short links',
                    'Per-link click analytics and traffic sources',
                    'Lead-capture forms wired to your audience list',
                    'A/B-able CTAs and pinned promo blocks',
                ],
                'cta_label' => 'Get started',
                'cta_url' => '/register',
            ],
            [
                'heading' => 'Personal portfolio',
                'tagline' => 'A polished one-page intro that travels with you.',
                'body' => 'Showcase work, link your socials and let people reach you — without paying for a custom site or worrying about hosting.',
                'icon' => 'fa-id-badge',
                'tint' => 'from-sky-500/30 to-violet-500/10',
                'bullets' => [
                    'Bio, avatar, headline and contact in one place',
                    'Featured projects with images and external links',
                    'All your social profiles in a single tap-friendly stack',
                    'Custom alias like 1inme.co/yourname',
                ],
                'cta_label' => 'Get started',
                'cta_url' => '/register',
            ],
            [
                'heading' => 'Agency / multi-client',
                'tagline' => 'Manage every client from one workspace.',
                'body' => 'Built for teams who ship for many brands. Keep client assets, links and access cleanly separated without juggling logins.',
                'icon' => 'fa-people-group',
                'tint' => 'from-fuchsia-500/30 to-pink-500/10',
                'bullets' => [
                    'Workspaces per client with isolated content',
                    'Invite teammates with role-based permissions',
                    'Shared client vault for credentials and assets',
                    'Per-client analytics for white-label reporting',
                ],
                'cta_label' => 'Get started',
                'cta_url' => '/register',
            ],
            [
                'heading' => 'Creator / influencer',
                'tagline' => 'Grow, post and monetize from one biolink.',
                'body' => 'Replace half a dozen tools with a single creator hub — biolink, posts, follower digests and tip jars all under your handle.',
                'icon' => 'fa-star',
                'tint' => 'from-amber-500/30 to-violet-500/10',
                'bullets' => [
                    'Biolink that doubles as a posting feed',
                    'Email digests to bring fans back',
                    'Tip jar, paid links and product blocks',
                    'Discoverable in the public creator feed',
                ],
                'cta_label' => 'Get started',
                'cta_url' => '/register',
            ],
            [
                'heading' => 'Small business',
                'tagline' => 'A lightweight site for shops and services.',
                'body' => 'Get a clean public page with your services, contact info and booking link — no developers, no monthly maintenance.',
                'icon' => 'fa-store',
                'tint' => 'from-emerald-500/30 to-sky-500/10',
                'bullets' => [
                    'Services / products list with pricing blocks',
                    'Click-to-call, WhatsApp and map directions',
                    'Inquiry forms that land straight in your inbox',
                    'Embed your scheduling or booking link',
                ],
                'cta_label' => 'Get started',
                'cta_url' => '/register',
            ],
            [
                'heading' => 'Events',
                'tagline' => 'One link for everything attendees need.',
                'body' => "Share schedule, venue, RSVP and updates from a single page that's easy to forward, print to a QR code, or pin in a bio.",
                'icon' => 'fa-calendar-days',
                'tint' => 'from-rose-500/30 to-violet-500/10',
                'bullets' => [
                    'Event hero with date, location and countdown',
                    'RSVP / signup form with capacity tracking',
                    'Schedule and speakers in tap-friendly cards',
                    'Share-ready QR code for posters and tickets',
                ],
                'cta_label' => 'Get started',
                'cta_url' => '/register',
            ],
        ];
    }
}
