<?php

namespace Database\Seeders;

use App\Modules\Admin\Models\Admin;
use App\Modules\Common\Services\WhatsappOrderLink;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuCategory;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\RestaurantTable;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Support\BlockDefaults;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds 10 hand-written "explainer" biolink pages into the super-admin
 * account (sayzioapp@gmail.com), one per marketing headline link type.
 * Together they act as a live demo gallery that walks visitors through
 * every kind of link Sayzio can create.
 *
 * Idempotent: each page is keyed on its stable alias. A page is only
 * (re)built when it does not yet exist, or when it still exactly matches
 * the content this seeder last produced (an unedited row) AND the bundled
 * SEED_VERSION has advanced. Pages an admin has since edited are left
 * untouched and no duplicates are ever created.
 */
class LinkTypeExplainerSeeder extends Seeder
{
    /**
     * Bump this when the explainer copy/blocks below change so that
     * untouched pages get refreshed on the next seed. Edited pages are
     * still left alone (see refresh guard in seedPage()).
     */
    private const SEED_VERSION = 7;

    /**
     * Bump when the live demo restaurant menu (`/demo-restaurant`) content
     * below changes. The menu config (order mode + a sample WhatsApp number)
     * is always re-converged; categories/items are only (re)built when the
     * demo menu is still empty so admin edits are never clobbered.
     */
    private const DEMO_MENU_VERSION = 1;

    public function run(): void
    {
        $user = $this->resolveSuperAdminUser();
        if (! $user) {
            $this->command?->warn('LinkTypeExplainerSeeder: no super-admin user could be resolved; skipping.');
            return;
        }

        $ws = $user->ensureDefaultWorkspace();

        $tally = ['created' => 0, 'refreshed' => 0, 'skipped' => 0];

        foreach ($this->pages() as $page) {
            $tally[$this->seedPage($user, $ws, $page)]++;
        }

        // A real, working demo restaurant menu (order mode + a sample
        // WhatsApp number) so the demo gallery's restaurant page can hand
        // diners straight into the live ordering + WhatsApp confirmation flow.
        $this->seedRestaurantMenuDemo($user, $ws);

        $this->command?->info(sprintf(
            'Link-type explainer biolinks: %d created, %d refreshed, %d skipped.',
            $tally['created'], $tally['refreshed'], $tally['skipped']
        ));
    }

    /**
     * The super-admin owns these pages. The seed super-admin lives in the
     * `admins` table; biolinks need a matching `users` row (the two pools
     * are bridged by email — see User::adminAccount()). We resolve the
     * admin's email and ensure a user account exists for it so the pages
     * have an owner and the admin can edit them after switching to the
     * user-facing side.
     */
    private function resolveSuperAdminUser(): ?User
    {
        $admin = Admin::query()->orderBy('id')->first();
        $email = $admin?->email ?: 'sayzioapp@gmail.com';

        return User::firstOrCreate(
            ['email' => $email],
            [
                'name'     => $admin?->name ?: 'Sayzio',
                'password' => Hash::make('password'),
            ]
        );
    }

    /**
     * Create, refresh, or skip a single explainer page.
     *
     * @return 'created'|'refreshed'|'skipped'
     */
    private function seedPage(User $owner, Workspace $ws, array $page): string
    {
        $existing = Link::where('user_id', $owner->id)
            ->where('alias', $page['alias'])
            ->first();

        if ($existing) {
            $storedVersion = (int) data_get($existing->settings, 'explainer.seed_version', 0);
            if ($storedVersion >= self::SEED_VERSION) {
                // Already current. Backfill a missing fingerprint (e.g. from
                // an interrupted earlier run) so a future version bump can
                // still tell whether the page is untouched.
                if (data_get($existing->settings, 'explainer.fingerprint') === null) {
                    $this->stampFingerprint($existing, $page);
                }
                return 'skipped';
            }

            $storedFp = data_get($existing->settings, 'explainer.fingerprint');
            if ($storedFp === null || $storedFp !== $this->fingerprint($existing)) {
                return 'skipped'; // edited by an admin (or not ours) — leave it
            }

            // Untouched + outdated → safe to rebuild in place.
            $existing->biolinkBlocks()->delete();
            $existing->forceFill($this->linkAttrs($owner, $ws, $page))->save();
            $this->buildBlocks($existing, $page);
            $this->stampFingerprint($existing, $page);
            return 'refreshed';
        }

        $link = new Link();
        $link->forceFill($this->linkAttrs($owner, $ws, $page));
        $link->settings = $this->linkSettings($page);
        $link->save();

        $this->buildBlocks($link, $page);
        $this->stampFingerprint($link, $page);

        return 'created';
    }

    /**
     * Persist the fingerprint of the blocks we just produced so future runs
     * can tell whether the page is still untouched.
     */
    private function stampFingerprint(Link $link, array $page): void
    {
        $settings = $this->linkSettings($page);
        $settings['explainer']['fingerprint'] = $this->fingerprint($link);
        $link->forceFill(['settings' => $settings])->saveQuietly();
    }

    /** Base column attributes for an explainer link. */
    private function linkAttrs(User $owner, Workspace $ws, array $page): array
    {
        return [
            'user_id'           => $owner->id,
            'workspace_id'      => $ws->id,
            'created_by_user_id'=> $owner->id,
            'type'              => 'biolink',
            'alias'             => $page['alias'],
            'title'             => $page['title'],
            'is_active'         => true,
            'visibility'        => 'public',
        ];
    }

    /** Settings payload (biolink meta + explainer bookkeeping marker). */
    private function linkSettings(array $page): array
    {
        return [
            'biolink' => [
                'biolink_title'       => $page['title'],
                'biolink_description' => $page['intro'],
            ],
            'explainer' => [
                'seed_version' => self::SEED_VERSION,
                'link_type'    => $page['link_type'],
            ],
        ];
    }

    /**
     * Create the heading / intro / sub-heading / feature-list / CTA blocks
     * for an explainer page with friendly, populated style payloads (no
     * `_placeholder` first-paint banner).
     */
    private function buildBlocks(Link $link, array $page): void
    {
        $blocks = [
            ['type' => 'heading', 'settings' => [
                'text'  => $page['heading'],
                'size'  => 'h1',
                'align' => 'center',
                'style' => 'plain',
            ]],
            ['type' => 'paragraph', 'settings' => [
                'text'  => $page['intro'],
                'align' => 'center',
            ]],
            ['type' => 'heading', 'settings' => [
                'text'  => 'What you can do',
                'size'  => 'h3',
                'align' => 'center',
                'style' => 'plain',
            ]],
            // NB: the public biolink renderer echoes each list item directly
            // as a string, so `items` must be a flat string[] (not the
            // {text, icon} shape used by some editor-side renderers).
            ['type' => 'list', 'settings' => [
                'style' => 'checklist',
                'icon'  => 'fa-check',
                'items' => array_values($page['features']),
            ]],
            ['type' => 'link', 'settings' => [
                'text' => $page['cta_label'],
                'url'  => $page['cta_url'],
                'icon' => $page['cta_icon'] ?? 'fa-arrow-right',
            ]],
        ];

        $sort = 0;
        foreach ($blocks as $b) {
            $settings = $b['settings'];
            $settings['_style'] = array_merge(
                BiolinkBlock::STYLE_DEFAULTS,
                BlockDefaults::styleForType($b['type'])
            );

            BiolinkBlock::forceCreate([
                'link_id'    => $link->id,
                'type'       => $b['type'],
                'settings'   => $settings,
                'sort_order' => $sort++,
                'is_active'  => true,
            ]);
        }
    }

    /**
     * Stable hash of a link's current top-level blocks (type + content,
     * excluding the deterministic `_style` payload). Used to detect whether
     * an admin has edited a seeded page since we last produced it.
     */
    private function fingerprint(Link $link): string
    {
        $rows = $link->biolinkBlocks()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get(['type', 'settings']);

        $shape = $rows->map(function ($b) {
            $s = $b->settings ?? [];
            unset($s['_style'], $s['_visibility']);
            return ['type' => $b->type, 'settings' => $s];
        })->all();

        return sha1(json_encode($shape));
    }

    /**
     * Seed (or converge) the live, working demo restaurant menu reachable at
     * `/demo-restaurant`. The restaurant explainer page in the demo gallery
     * links straight here so visitors experience the real ordering flow —
     * including the "Send order via WhatsApp" confirmation when a number is
     * configured (mirrors the live public page, additive, orders still
     * record). Idempotent: the menu config is re-converged each run; the
     * categories/items are only built when the menu is still empty.
     */
    private function seedRestaurantMenuDemo(User $owner, Workspace $ws): void
    {
        $alias = 'demo-restaurant';

        $link = Link::where('user_id', $owner->id)->where('alias', $alias)->first();
        $created = false;

        if (! $link) {
            $link = new Link();
            $link->forceFill([
                'user_id'            => $owner->id,
                'workspace_id'       => $ws->id,
                'created_by_user_id' => $owner->id,
                'type'               => 'restaurant_menu',
                'alias'              => $alias,
                'title'              => 'Olive & Ember',
                'is_active'          => true,
                'visibility'         => 'public',
            ]);
            $link->settings = [
                'biolink' => [
                    'biolink_title'       => 'Olive & Ember',
                    'biolink_description' => 'Wood-fired kitchen: order right from your table.',
                ],
                'demo' => ['seed_version' => self::DEMO_MENU_VERSION],
            ];
            $link->save();
            $created = true;
        }

        // Ensure the menu config row exists in order mode with a sample
        // WhatsApp number so the demo always showcases the click-to-chat
        // order confirmation. We only fill the number when it's missing so an
        // admin who later set a real one is never overwritten.
        $menu = RestaurantMenu::firstOrNew(['link_id' => $link->id]);
        $settings = is_array($menu->settings) ? $menu->settings : [];
        if (empty($settings['whatsapp_number'])) {
            $settings['whatsapp_number'] = WhatsappOrderLink::normalizeNumber('+1 555 555 0123');
        }
        if (empty($settings['order_instructions'] ?? null)) {
            $settings['order_instructions'] = 'Add a few dishes, then tap “Place order”. You can also send your order to our team on WhatsApp to confirm.';
        }

        $menu->forceFill([
            'user_id'      => $owner->id,
            'mode'         => RestaurantMenu::MODE_ORDER,
            'currency'     => $menu->currency ?: 'USD',
            'accent_color' => $menu->accent_color ?: '#c2410c',
            'settings'     => $settings,
        ])->save();

        // Build the menu contents only once (when empty) so admin edits to the
        // demo are never clobbered on a re-seed.
        if ($menu->categories()->count() === 0) {
            $this->buildDemoMenuContents($menu);
        }

        // At least one table so the per-table QR ordering flow is reachable.
        if ($menu->tables()->count() === 0) {
            RestaurantTable::create(['menu_id' => $menu->id, 'label' => '1', 'sort_order' => 0]);
        }

        $this->command?->info($created
            ? "Live demo restaurant menu created (/{$alias})."
            : "Live demo restaurant menu ensured (/{$alias}).");
    }

    /** The dishes for the live demo restaurant menu. */
    private function buildDemoMenuContents(RestaurantMenu $menu): void
    {
        $catalog = [
            ['name' => 'Starters', 'items' => [
                ['Wood-fired Focaccia', 'Rosemary, sea salt, olive oil', 7.50, false],
                ['Charred Padrón Peppers', 'Smoked salt, lemon', 8.00, false],
                ['Burrata & Heirloom Tomato', 'Basil, aged balsamic', 12.00, false],
            ]],
            ['name' => 'Wood-fired Mains', 'items' => [
                ['Margherita Pizza', 'San Marzano, fior di latte, basil', 14.00, false],
                ['Diavola Pizza', 'Spicy salami, chilli honey', 16.50, false],
                ['Roasted Half Chicken', 'Charred lemon, herb jus', 21.00, false],
                ['Ember Ribeye', "300g, smoked butter, today's special", 29.00, true],
            ]],
            ['name' => 'Desserts', 'items' => [
                ['Olive Oil Cake', 'Citrus, mascarpone', 9.00, false],
                ['Affogato', 'Vanilla gelato, espresso', 7.00, false],
            ]],
            ['name' => 'Drinks', 'items' => [
                ['House Red (Glass)', 'Tuscan blend', 9.00, false],
                ['Sparkling Water', 'Chilled, 500ml', 4.00, false],
                ['Espresso', 'Single origin', 3.50, false],
            ]],
        ];

        $csort = 0;
        foreach ($catalog as $cat) {
            $category = RestaurantMenuCategory::create([
                'menu_id'    => $menu->id,
                'name'       => $cat['name'],
                'sort_order' => $csort++,
                'is_active'  => true,
            ]);

            $isort = 0;
            foreach ($cat['items'] as [$name, $desc, $price, $soldOut]) {
                RestaurantMenuItem::create([
                    'menu_id'     => $menu->id,
                    'category_id' => $category->id,
                    'name'        => $name,
                    'description' => $desc,
                    'price'       => $price,
                    'is_sold_out' => $soldOut,
                    'sort_order'  => $isort++,
                    'is_active'   => true,
                ]);
            }
        }
    }

    /**
     * The 15 marketing headline link types, each with consistent explainer
     * copy: a title, a heading, an intro, 4–5 feature bullets, and a CTA.
     */
    private function pages(): array
    {
        $create = fn (string $type = '') => url('/features');

        return [
            [
                'alias' => 'demo-type-short-link',
                'link_type' => 'short',
                'title' => 'Short Link: explained',
                'heading' => 'Short Links 🔗',
                'intro' => 'Turn long, ugly URLs into clean, branded links you can share anywhere. Perfect for marketers, creators and anyone who wants tidy, trackable links.',
                'features' => [
                    'Shorten any URL into a clean, on-brand link',
                    'Repoint the destination any time, the link never changes',
                    'Add UTMs, password protection and expiry rules',
                    'Route visitors by country, device or language',
                    'See every click in real-time analytics',
                ],
                'cta_label' => 'Create your own short link',
                'cta_url' => $create('short'),
                'cta_icon' => 'fa-link',
            ],
            [
                'alias' => 'demo-type-link-in-bio',
                'link_type' => 'biolink',
                'title' => 'Link in Bio: explained',
                'heading' => 'Link in Bio 🌟',
                'intro' => 'One beautiful page for all your links. Built for creators, coaches and small businesses who live in the "link in bio" slot on social media.',
                'features' => [
                    'Drag-and-drop blocks for text, media, products and more',
                    'Dozens of themes and full visual customization',
                    'Capture emails, sell products and take donations',
                    'Schedule blocks and hide them by device or location',
                    'Live analytics on every visit and click',
                ],
                'cta_label' => 'Build your bio page',
                'cta_url' => $create('biolink'),
                'cta_icon' => 'fa-star',
            ],
            [
                'alias' => 'demo-type-conversational',
                'link_type' => 'conversational',
                'title' => 'Conversational: explained',
                'heading' => 'Conversational 💬',
                'intro' => 'Guide visitors through your links one friendly message at a time. Great for onboarding, storytelling or walking people to the right place.',
                'features' => [
                    'A chat-style flow that reveals links step by step',
                    'Write a fixed script that feels personal',
                    'Keep visitors focused on one action at a time',
                    'Perfect for launches, funnels and FAQs',
                    'Fully themeable like any biolink page',
                ],
                'cta_label' => 'Try a conversational page',
                'cta_url' => $create('conversational'),
                'cta_icon' => 'fa-comments',
            ],
            [
                'alias' => 'demo-type-slides',
                'link_type' => 'slides',
                'title' => 'Slides: explained',
                'heading' => 'Slides 🖼️',
                'intro' => 'Tell your story as a swipeable, story-style presentation. Ideal for portfolios, product tours and step-by-step pitches.',
                'features' => [
                    'Swipeable, full-screen story slides',
                    'Mix text, images and links per slide',
                    'Great for portfolios, pitches and tutorials',
                    'Mobile-first, tap-to-advance experience',
                    'Shareable from a single link',
                ],
                'cta_label' => 'Create a slides page',
                'cta_url' => $create('slides'),
                'cta_icon' => 'fa-images',
            ],
            [
                'alias' => 'demo-type-ai-chatbot',
                'link_type' => 'ai_chat',
                'title' => 'AI Chatbot: explained',
                'heading' => 'AI Chatbot 🤖',
                'intro' => 'A smart AI companion that answers questions about you or your business 24/7. Built for creators and brands who get the same questions over and over.',
                'features' => [
                    'An AI that answers in your voice, around the clock',
                    'Trained on your bio, links and the details you choose',
                    'Handles FAQs so you do not have to',
                    'Lives on its own shareable chat page',
                    'Stays on-brand with full theming',
                ],
                'cta_label' => 'Set up your AI chatbot',
                'cta_url' => $create('ai_chat'),
                'cta_icon' => 'fa-robot',
            ],
            [
                'alias' => 'demo-type-restaurant-menu',
                'link_type' => 'restaurant_menu',
                'title' => 'Restaurant Menu: explained',
                'heading' => 'Restaurant Menu 🍽️',
                'intro' => 'A digital menu with photos, prices and table-side ordering. Made for restaurants, cafés and food trucks that want a QR menu that works.',
                'features' => [
                    'Build categories and items with photos and prices',
                    'Each table gets its own QR code and ordering link',
                    'Take orders from the page, or confirm them over WhatsApp in one tap',
                    'Live staff dashboard tracks every order',
                    'Update the menu any time, no reprinting',
                ],
                'cta_label' => 'Order from the live demo menu',
                'cta_url' => url('/demo-restaurant'),
                'cta_icon' => 'fa-utensils',
            ],
            [
                'alias' => 'demo-type-store-menu',
                'link_type' => 'store_menu',
                'title' => 'Store Menu: explained',
                'heading' => 'Store Menu 🛍️',
                'intro' => 'A simple online store you can share as a link: list products by category and let visitors send you an order request. No checkout, no fees.',
                'features' => [
                    'Organise products into categories with photos and prices',
                    'Visitors build a cart and send an order request, no payment needed',
                    'New orders land in your dashboard with a New → Completed flow',
                    'Pause ordering any time with a single toggle',
                    'Optional one-tap “send my order on WhatsApp” button',
                ],
                'cta_label' => 'Build your store menu',
                'cta_url' => $create('store_menu'),
                'cta_icon' => 'fa-store',
            ],
            [
                'alias' => 'demo-type-file-share',
                'link_type' => 'file',
                'title' => 'File Share: explained',
                'heading' => 'File Share 📁',
                'intro' => 'Share files behind a clean, branded download link. Perfect for sending PDFs, media kits, lead magnets and resources.',
                'features' => [
                    'Upload a file and get a shareable link instantly',
                    'Add a branded download page before the file',
                    'Password-protect or expire the link',
                    'Track every download with analytics',
                    'Swap the file without changing the link',
                ],
                'cta_label' => 'Share a file',
                'cta_url' => $create('file'),
                'cta_icon' => 'fa-file-download',
            ],
            [
                'alias' => 'demo-type-event',
                'link_type' => 'event',
                'title' => 'Event: explained',
                'heading' => 'Event 📅',
                'intro' => 'Promote an event and collect RSVPs from one link. Built for organizers, communities and anyone hosting something worth showing up to.',
                'features' => [
                    'Share date, time, location and details',
                    'Let visitors add the event to their calendar',
                    'Collect RSVPs in one place',
                    'Works for in-person and online events',
                    'Track interest with built-in analytics',
                ],
                'cta_label' => 'Create an event link',
                'cta_url' => $create('event'),
                'cta_icon' => 'fa-calendar',
            ],
            [
                'alias' => 'demo-type-calendar',
                'link_type' => 'calendar',
                'title' => 'Calendar: explained',
                'heading' => 'Calendar 🗓️',
                'intro' => 'Share all your upcoming events from a single link visitors can follow and sync. Great for communities, classes and anyone with a recurring schedule.',
                'features' => [
                    'List all your events on one shareable page',
                    'Visitors follow your calendar and get new events automatically',
                    'One-tap sync to Google or any device calendar',
                    'Updates push out to everyone who subscribed',
                    'Tracked and themeable like every Sayzio link',
                ],
                'cta_label' => 'Create your calendar',
                'cta_url' => $create('calendar'),
                'cta_icon' => 'fa-calendar-days',
            ],
            [
                'alias' => 'demo-type-contact-card',
                'link_type' => 'vcard',
                'title' => 'Contact Card: explained',
                'heading' => 'Contact Card 📇',
                'intro' => 'A digital business card people can save in one tap. Ideal for networkers, sales teams and professionals who hate paper cards.',
                'features' => [
                    'Share name, role, phones, emails and socials',
                    'One tap to save directly to a phone’s contacts',
                    'Standards-based vCard works everywhere',
                    'Pair it with a QR code for in-person meetings',
                    'Update your details any time',
                ],
                'cta_label' => 'Make your contact card',
                'cta_url' => $create('vcard'),
                'cta_icon' => 'fa-id-card',
            ],
            [
                'alias' => 'demo-type-resume-portfolio',
                'link_type' => 'resume',
                'title' => 'Resume / Portfolio: explained',
                'heading' => 'Resume / Portfolio 📄',
                'intro' => 'Turn your CV into a polished, shareable page with a one-tap PDF download. Built for job seekers, freelancers and anyone with work worth showing.',
                'features' => [
                    'Build a clean resume / portfolio page section by section',
                    'Let visitors download a formatted PDF in one tap',
                    'Keep multiple named versions for different roles',
                    'Tailor it to a job with AI and draft a matching cover letter',
                    'Import your details to get started in minutes',
                ],
                'cta_label' => 'Build your resume page',
                'cta_url' => $create('resume'),
                'cta_icon' => 'fa-file-lines',
            ],
            [
                'alias' => 'demo-type-bizs-profile',
                'link_type' => 'paid_page',
                'title' => 'Bizs Profile: explained',
                'heading' => 'Bizs Profile 👑',
                'intro' => 'A themeable home for your whole creator brand that automatically shows your posts, membership tiers and tips, no manual linking needed.',
                'features' => [
                    'Automatically surfaces all your posts in one feed',
                    'Offer membership tiers and collect tips in-page',
                    'Gate content for followers, members or subscribers',
                    'Fully themeable to match your brand',
                    'Visitors follow, react and comment in one place',
                ],
                'cta_label' => 'Create your Bizs Profile',
                'cta_url' => $create('paid_page'),
                'cta_icon' => 'fa-crown',
            ],
            [
                'alias' => 'demo-type-reviews-page',
                'link_type' => 'reviews',
                'title' => 'Reviews Page: explained',
                'heading' => 'Reviews Page ⭐',
                'intro' => 'Collect and showcase glowing reviews in one place. Built for businesses and creators who want social proof that converts.',
                'features' => [
                    'Collect new reviews right on the page',
                    'Import reviews from Google and Trustpilot',
                    'Moderate, pin and reply to reviews',
                    'Show a star summary that builds trust',
                    'Embed reviews on your biolink too',
                ],
                'cta_label' => 'Start collecting reviews',
                'cta_url' => $create('reviews'),
                'cta_icon' => 'fa-star',
            ],
            [
                'alias' => 'demo-type-brand-press-kit',
                'link_type' => 'brand_kit',
                'title' => 'Brand / Press Kit: explained',
                'heading' => 'Brand / Press Kit 🎨',
                'intro' => 'Turn your saved Brand Kit into a polished, shareable press page, with everything journalists, partners and collaborators need to represent you correctly, in one link.',
                'features' => [
                    'One-tap logo downloads in every variant you add',
                    'Copy-able colour swatches; click any hex to copy',
                    'Your heading and body font pairing, shown live',
                    'Brand voice, taglines and a copy-able press boilerplate',
                    'Social links and a press contact, all themeable',
                ],
                'cta_label' => 'Create your press kit',
                'cta_url' => $create('brand_kit'),
                'cta_icon' => 'fa-palette',
            ],
            [
                'alias' => 'demo-type-paid-page',
                'link_type' => 'paid_page',
                'title' => 'Paid Page: explained',
                'heading' => 'Paid Page 🔒',
                'intro' => 'A gated page that only unlocks after a one-time payment or subscription, perfect for premium content, courses and exclusive drops.',
                'features' => [
                    'Gate the whole page behind a one-time payment or subscription',
                    'Reuse the same creator feed and blocks as your other pages',
                    'Offer multiple price tiers for different access levels',
                    'Payments settle straight to your connected payout account',
                    'Track views, conversions and revenue in real time',
                ],
                'cta_label' => 'Create your paid page',
                'cta_url' => $create('paid_page'),
                'cta_icon' => 'fa-lock',
            ],
            [
                'alias' => 'demo-type-qr-code',
                'link_type' => 'qr',
                'title' => 'QR Code: explained',
                'heading' => 'QR Code 📱',
                'intro' => 'A dynamic, styleable QR code that redirects anywhere you choose. Print it once and keep it working forever; the destination is editable any time.',
                'features' => [
                    'Style the code with your logo, colours and a design template',
                    'Re-point the destination any time without reprinting',
                    'Track scans separately from regular link clicks',
                    'Export SVG or high-resolution PNG for print or packaging',
                    'Run a scannability check before you publish',
                ],
                'cta_label' => 'Create your QR code',
                'cta_url' => $create('qr'),
                'cta_icon' => 'fa-qrcode',
            ],
            [
                'alias' => 'demo-type-forms',
                'link_type' => 'form',
                'title' => 'Forms: explained',
                'heading' => 'Forms 📝',
                'intro' => 'A standalone form page with dozens of field types, conditional logic and instant notifications, built for signups, surveys, applications and lead capture.',
                'features' => [
                    'Add dozens of field types with conditional logic',
                    'Match your brand with full design customization',
                    'Get notified by email, SMS or webhook on every submission',
                    'Embed the form anywhere or share it as its own link',
                    'Every submission lands straight in your contacts',
                ],
                'cta_label' => 'Build your form',
                'cta_url' => $create('form'),
                'cta_icon' => 'fa-list-check',
            ],
        ];
    }
}
