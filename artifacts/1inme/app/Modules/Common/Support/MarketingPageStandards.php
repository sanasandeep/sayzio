<?php

namespace App\Modules\Common\Support;

/** Standards apply to marketing routes only, never the standalone home or customer pages. */
final class MarketingPageStandards
{
    public static function pages(): array
    {
        $pages = [];
        foreach (MarketingSeo::codeDrivenDefaults() as $key => $page) {
            $path = $page['url'] ?? '';
            if ($path === '' || $path === '/') continue;
            $pages[$path] = ['key' => $key] + $page;
        }
        foreach (MarketingSeo::sitePageLabels() as $key => $page) {
            $path = MarketingSeo::sitePagePath($key);
            $pages[$path] ??= ['key' => $key, 'url' => $path] + $page;
        }
        $extra = [
            '/events' => ['Discover events', 'Discover', 'Find upcoming public events on Sayzio. Explore event details, schedules and ways to RSVP or connect with other attendees.'],
            '/ai-dashboard' => ['AI dashboard', 'AI suite', 'Explore dashboard presets and arrange your Sayzio widgets with AI. Keep the metrics that matter to your work in one view.'],
            '/android' => ['Sayzio for Android', 'Apps', 'Download the Sayzio Android app to manage links, pages, QR codes and analytics on your phone. Check the latest available release.'],
            '/blogs' => ['Sayzio Blog', 'Resources', 'Read Sayzio product updates, practical guides and ideas for building your online presence and growing your audience.'],
            '/privacy/request' => ['Privacy requests', 'Legal', 'Learn how to request access, correction or deletion of your personal data held by Sayzio and contact the privacy team.'],
        ];
        foreach ($extra as $path => [$label, $group, $description]) {
            $pages[$path] ??= ['key' => trim($path, '/'), 'url' => $path, 'label' => $label, 'group' => $group, 'title' => $label, 'description' => $description];
        }
        return $pages;
    }

    public static function page(string $path): ?array
    {
        $path = '/' . trim(parse_url($path, PHP_URL_PATH) ?? '', '/');
        if ($path === '/') return null;
        $pages = self::pages();
        if (isset($pages[$path])) return $pages[$path];
        if (str_starts_with($path, '/privacy/request/')) {
            return array_replace($pages['/privacy/request'], ['url' => $path]);
        }
        if (str_starts_with($path, '/blogs/')) {
            return ['key' => 'blog-detail', 'url' => $path, 'label' => 'Blog', 'group' => 'Resources'];
        }
        if (preg_match('#^/(terms|privacy|refunds|cookies|gdpr)/history$#', $path, $match)) {
            return ['key' => 'policy-history', 'url' => $path, 'label' => ($pages['/'.$match[1]]['label'] ?? ucfirst($match[1])) . ' history', 'group' => 'Legal'];
        }
        return null;
    }

    public static function seo(array $seo, ?array $page, array $context = []): array
    {
        if ($page === null) return $seo;
        foreach (['title', 'description'] as $field) {
            $seo[$field] = self::text((string) ($seo[$field] ?? ''));
        }
        if ($seo['title'] === '' || $seo['title'] === config('app.name', 'Sayzio')) {
            $seo['title'] = $page['title'] ?? $page['label'];
        }
        if ($seo['description'] === '') {
            $defaults = self::descriptions();
            $candidate = $context['description'] ?? '';
            if (trim((string) $candidate) === '') {
                $candidate = $page['description'] ?? $defaults[$page['key']] ?? null;
            }
            if (!$candidate && !empty($context['sections'])) {
                $candidate = implode(' ', array_column($context['sections'], 'body'));
            }
            $seo['description'] = self::excerpt((string) ($candidate ?: ('Explore ' . $page['label'] . ' on Sayzio. Find product information, practical guidance and the next step for your online presence.')));
        }
        return $seo;
    }

    public static function canonical(string $url, array $query): string
    {
        $page = filter_var($query['page'] ?? null, FILTER_VALIDATE_INT);
        if ($page !== false && $page > 1) {
            return $url . (str_contains($url, '?') ? '&' : '?') . 'page=' . $page;
        }
        return $url;
    }

    public static function robots(string $path, array $query): ?string
    {
        $search = $query['q'] ?? '';
        if (str_starts_with($path, '/privacy/request') || !is_scalar($search) || trim((string) $search) !== '') {
            return 'noindex, follow';
        }
        return null;
    }

    public static function related(array $page): array
    {
        $related = [];
        foreach (self::pages() as $path => $candidate) {
            if ($path !== $page['url'] && $candidate['group'] === $page['group']) {
                $related[$path] = $candidate['label'];
                if (count($related) === 3) break;
            }
        }
        foreach (['/features' => 'Explore all features', '/how-it-works' => 'See how it works', '/pricing' => 'Compare plans'] as $path => $label) {
            if ($path !== $page['url'] && count($related) < 3) $related[$path] ??= $label;
        }
        return $related;
    }

    private static function text(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private static function excerpt(string $value): string
    {
        $text = self::text($value);
        if (mb_strlen($text) <= 170) return $text;
        $text = mb_substr($text, 0, 167);
        return preg_replace('/\s+\S*$/u', '', $text) . '…';
    }

    private static function descriptions(): array
    {
        return [
            'ai-dashboard' => 'Explore Sayzio dashboard presets and arrange your widgets with AI. Keep the metrics that matter to your work in one view.',
            'features' => 'Explore Sayzio pages, AI creation tools, QR codes, bookings, events, audience tools and workspaces. Find the right capability for your next step.',
            'how-it-works' => 'Learn how to create your Sayzio page, share it with a link or QR code, connect with visitors and measure your results.',
            'about' => 'Meet the people and ideas behind Sayzio, the connected workspace for your online presence, audience and business.',
            'contact' => 'Contact the Sayzio team for product help, account questions, partnerships and feedback. Find the right way to reach us.',
            'faqs' => 'Find answers about Sayzio pages, plans, AI tools, links, analytics, accounts and billing in our frequently asked questions.',
            'workspace-team' => 'Organize brands and client work in Sayzio workspaces. Invite teammates, assign roles and collaborate on pages, contacts and projects.',
            'buzz' => 'Explore Sayzio Buzz widgets for sharing recent activity and social proof on your page. Learn how to configure the signals visitors see.',
            'newsroom' => 'Read Sayzio news, product announcements and media coverage. Find company updates and resources for the press.',
            'services' => 'Discover how creators, agencies, coaches, musicians and small businesses use Sayzio to build pages, connect with people and grow.',
            'discovery' => 'Discover public creator and business profiles on Sayzio. Explore their pages and find new people and ideas to follow.',
            'creators-feed' => 'Explore public updates from creators on Sayzio. Discover posts, follow profiles and stay connected with the people you care about.',
            'terms' => 'Read the Sayzio Terms of Service, including account responsibilities, acceptable use, subscriptions and the terms governing the platform.',
            'privacy' => 'Read how Sayzio collects, uses and protects personal data, and learn about your privacy choices and data rights.',
            'cookies' => 'Learn which cookies Sayzio uses, why they are needed and how to manage your consent and cookie preferences.',
            'refunds' => 'Read Sayzio refund and cancellation policies, eligibility details and how to contact the team about a billing question.',
            'gdpr' => 'Learn about Sayzio data protection practices, GDPR rights and how to contact the team about your personal data.',
            'ai-chatbot' => 'Explore an AI chatbot for your Sayzio page. Use your own content to answer visitor questions and support lead capture.',
            'ai-agent' => 'Explore Sayzio AI Agent tools for multi-step tasks, lead qualification and follow-up workflows in your workspace.',
            'ai-widget' => 'Add a Sayzio AI assistant to your website to answer questions and capture enquiries using your own business content.',
            'ai-voice-assistant' => 'Explore the Sayzio AI Voice Assistant for handling incoming calls, answering questions and routing enquiries.',
            'whatsapp-agent' => 'Create and manage Sayzio links and QR codes through WhatsApp messages, photos and voice notes with the WhatsApp Agent.',
        ];
    }
}
