<?php

namespace App\Modules\Common\Support;

use App\Modules\User\Support\LinkTypeCategories;

/** Public presentation of the editable catalogue; never rewrites stored content. */
final class FeatureDirectory
{
    public static function categories(array $stored): array
    {
        $categories = SitePagesContent::normalizeFeaturesCategories($stored);
        $positions = array_flip(array_column($categories, 'id'));
        foreach (array_merge(SitePagesContent::featuresCategoriesDefault(), self::additions()) as $default) {
            $id = $default['id'];
            if (!isset($positions[$id])) {
                $positions[$id] = count($categories);
                $categories[] = $default;
                continue;
            }
            // Old stored categories used to hide newly shipped rows indefinitely.
            // Keep edited copy/links, while adding missing features by name.
            $index = $positions[$id];
            $names = array_map(self::key(...), array_column($categories[$index]['features'], 'name'));
            foreach ($default['features'] as $feature) {
                if (!in_array(self::key($feature['name']), $names, true)) {
                    $categories[$index]['features'][] = $feature;
                    $names[] = self::key($feature['name']);
                }
            }
        }
        // Source page formats from the actual picker so industry pages cannot drift.
        $index = $positions['link-types'];
        $names = array_map(self::key(...), array_column($categories[$index]['features'], 'name'));
        foreach (LinkTypeCategories::categories() as $group) {
            foreach ($group['types'] as $type) {
                if (!in_array(self::key($type['label']), $names, true)) {
                    $categories[$index]['features'][] = ['name' => $type['label'], 'description' => $type['desc'], 'icon' => $type['icon']];
                    $names[] = self::key($type['label']);
                }
            }
        }
        return SitePagesContent::normalizeFeaturesCategories($categories);
    }

    private static function key(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    public static function groups(array $categories): array
    {
        $definitions = [
            ['create', 'Create & publish', 'Your ideas, ready to share.', 'Build your home on the web, choose a page format and make it your own.', 'fa-layer-group', ['link-types', 'biolink', 'templates', 'scheduling']],
            ['ai', 'AI & creative tools', 'A little help. A lot more possibility.', 'Create pages, shape your brand and turn your own knowledge into useful answers.', 'fa-wand-magic-sparkles', ['ai-creation', 'ai-suite', 'knowledge-bases', 'ai-branding', 'ai-marketing-strategist']],
            ['share', 'Links, QR & domains', 'Make every connection count.', 'Share online or in the real world with branded links, QR codes and your own domain.', 'fa-qrcode', ['links', 'qr', 'domains']],
            ['sell', 'Sell & take bookings', 'Turn interest into business.', 'Bring your products, menus, services and paid content closer to your customers.', 'fa-store', ['storefront', 'booking-commerce']],
            ['events', 'Events & calendars', 'Bring people together.', 'From the invitation to the follow-up, keep your events and attendees connected.', 'fa-calendar-days', ['events', 'event-networking', 'calendar']],
            ['audience', 'Audience & conversations', 'Build relationships that last.', 'Capture leads, reply to messages and keep your audience coming back.', 'fa-comments', ['inbox', 'inbox-agent', 'crm', 'subscribers', 'feed', 'reviews', 'buzz', 'public-surfaces', 'referrals']],
            ['measure', 'Analytics & integrations', 'See what is working.', 'Follow the visitor journey, understand results and connect your existing tools.', 'fa-chart-line', ['analytics', 'flow', 'integrations']],
            ['organize', 'Workspace & organization', 'Keep your work together.', 'Give your team a shared place for projects, client information and everyday work.', 'fa-users', ['workspaces', 'kanban', 'vault', 'account', 'billing']],
            ['apps', 'Apps & extensions', 'Take Sayzio with you.', 'Keep your links and tools close, wherever you work. Check each app page for availability.', 'fa-laptop', ['cross-platform']],
        ];
        $byId = array_column($categories, null, 'id');
        $groups = [];
        foreach ($definitions as [$id, $title, $heading, $intro, $icon, $ids]) {
            $items = [];
            foreach ($ids as $categoryId) {
                if (isset($byId[$categoryId])) {
                    $items[] = $byId[$categoryId];
                    unset($byId[$categoryId]);
                }
            }
            if ($items !== []) {
                $groups[] = compact('id', 'title', 'heading', 'intro', 'icon', 'items');
            }
        }
        // Custom admin categories always remain visible and searchable.
        if ($byId !== []) {
            $groups[] = ['id' => 'more', 'title' => 'More from Sayzio', 'heading' => 'More to explore.', 'intro' => 'Additional tools and capabilities from the Sayzio team.', 'icon' => 'fa-compass', 'items' => array_values($byId)];
        }
        return $groups;
    }

    private static function additions(): array
    {
        $rows = [
            ['ai-creation', 'fa-wand-magic-sparkles', 'AI creation & Marketing Studio', 'Go from an idea to a page, a plan or a piece of marketing material.', [
                ['AI page builders', 'Generate a starting point for Link in Bio pages, slides, restaurant menus, stores, service booking pages and resumes. Review and edit the result before sharing.'],
                ['Competitor Biolink Teardown', 'Analyze another Link in Bio for strengths, gaps and calls to action, then use the findings to start your own page.'],
                ['Marketing Studio', 'Create marketing materials with your saved brand context and review the estimated AI cost before generating.'],
                ['Notes to action plan', 'Turn your notes into a structured action plan with AI.'],
                ['Card / brochure scan', 'Extract contact information from a card or brochure instead of typing it again.'],
                ['AI resume tailoring', 'Import a resume and adapt a version to the role you are applying for.'],
            ]],
            ['domains', 'fa-globe', 'Custom domains & branding', 'Make your shared presence feel like your business.', [
                ['Custom domains', 'Connect your own domain to your Sayzio links and pages.', '/domains'],
                ['Custom aliases', 'Choose a memorable path for a link and manage it from its settings.'],
                ['Brand Kit', 'Keep your logo, colors, fonts and brand voice together for pages and AI tools.'],
            ]],
            ['booking-commerce', 'fa-calendar-check', 'Services, menus & monetization', 'Give visitors a clear next step when they are ready to buy or book.', [
                ['Service booking', 'List services and availability so visitors can request a slot, with booking status, rescheduling and cancellation flows.'],
                ['Restaurant ordering', 'Publish a digital menu and collect table-side orders through a QR code.'],
                ['Store order requests', 'Let visitors build a cart and submit an order request without requiring an online payment.'],
                ['Memberships & tips', 'Offer paid creator content and membership tiers, and accept supporter tips. Payment and payout availability depends on the connected provider.'],
                ['Staff-assisted orders & bookings', 'Use the dashboard to help customers place orders or request service bookings.'],
            ]],
            ['event-networking', 'fa-people-group', 'Event management & networking', 'Give an event a home before, during and after the day.', [
                ['Event pages & agendas', 'Present the event, its media, schedule and people from a shareable event page.'],
                ['Attendee connections', 'Let attendees opt in to networking and join Who else is here from a browser.'],
                ['Event reports', 'Review attendance and engagement alongside event timing and revenue reporting.'],
                ['Event reminders', 'Keep attendees informed with reminders around the event.'],
            ]],
            ['flow', 'fa-diagram-project', 'Journey & project reporting', 'Understand how your pages and tools connect.', [
                ['Dashboard flow map', 'Explore connected links and folders as a visual map, with filters and a closer look at each resource.'],
                ['Project reports', 'Review results across related resources instead of checking every link separately.'],
            ]],
        ];
        return array_map(static function ($row) {
            [$id, $icon, $heading, $intro, $features] = $row;
            return compact('id', 'icon', 'heading', 'intro') + ['features' => array_map(static fn ($f) => ['name' => $f[0], 'description' => $f[1], 'link' => $f[2] ?? ''], $features)];
        }, $rows);
    }
}
