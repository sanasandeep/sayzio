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
