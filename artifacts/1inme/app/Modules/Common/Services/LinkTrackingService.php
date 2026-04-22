<?php

namespace App\Modules\Common\Services;

use App\Modules\Common\Services\BotDetector;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkClick;
use Illuminate\Http\Request;

use App\Modules\Common\Services\ChannelClassifier;

class LinkTrackingService
{
    public function track(Link $link, Request $request, ?string $usedAlias = null, ?string $source = null): LinkClick
    {
        $userAgent = $this->resolveUserAgent($request);
        $isBot = app(BotDetector::class)->isBot($userAgent);

        $geoService = app(GeoIpService::class);
        $geo = $geoService->detectGeo($request->ip());

        $click = LinkClick::create([
            'link_id' => $link->id,
            'alias' => $usedAlias ?: $link->alias,
            'viewer_user_id' => \App\Modules\Common\Services\ViewerSession::id() ?? auth()->id(),
            'ip_address' => $request->ip(),
            'browser' => $this->detectBrowser($userAgent),
            'os' => $this->detectOS($userAgent),
            'device_type' => $this->detectDeviceType($userAgent),
            'referrer' => $request->header('referer'),
            'source' => $source,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 512) : null,
            'channel' => ChannelClassifier::classify($userAgent),
            'is_bot' => $isBot,
            'language' => $this->detectLanguage($request),
            'country_code' => $geo['country_code'] ?? null,
            'city' => $geo['city'] ?? null,
            'latitude' => $geo['latitude'] ?? null,
            'longitude' => $geo['longitude'] ?? null,
            'utm_params' => $this->extractUtmParams($request),
            'clicked_at' => now(),
        ]);

        // Skip incrementing the cached counters for obvious bot/scraper hits
        // so creator-facing totals and unique-visitor stats reflect real humans.
        // We also short-circuit BEFORE firing the LinkClicked event — that
        // event is the entry point for outbound click webhooks and "new
        // visitor" notifications, and creators should never be paged for
        // a scraper.
        if ($isBot) {
            return $click;
        }

        $link->increment('total_clicks');

        $isUnique = !LinkClick::where('link_id', $link->id)
            ->where('ip_address', $request->ip())
            ->where('clicked_at', '>=', now()->subDay())
            ->where('id', '!=', $click->id)
            ->exists();

        if ($isUnique) {
            $link->increment('unique_clicks');
        }

        // Broadcast to downstream listeners (webhooks, notifications,
        // realtime push). Reaching this line guarantees `is_bot = false`.
        \App\Events\LinkClicked::dispatch($link, $click);

        return $click;
    }

    protected function detectBrowser(?string $ua): ?string
    {
        if (!$ua) return null;

        $browsers = [
            'Edge' => '/Edg\//i',
            'Chrome' => '/Chrome\//i',
            'Firefox' => '/Firefox\//i',
            'Safari' => '/Safari\//i',
            'Opera' => '/OPR\//i',
            'IE' => '/MSIE|Trident/i',
        ];

        foreach ($browsers as $name => $pattern) {
            if (preg_match($pattern, $ua)) return $name;
        }

        return 'Other';
    }

    protected function detectOS(?string $ua): ?string
    {
        if (!$ua) return null;

        $systems = [
            'Windows' => '/Windows/i',
            'macOS' => '/Macintosh/i',
            'Linux' => '/Linux/i',
            'Android' => '/Android/i',
            'iOS' => '/iPhone|iPad/i',
        ];

        foreach ($systems as $name => $pattern) {
            if (preg_match($pattern, $ua)) return $name;
        }

        return 'Other';
    }

    protected function detectDeviceType(?string $ua): ?string
    {
        if (!$ua) return null;

        if (preg_match('/Mobile|Android.*Mobile|iPhone/i', $ua)) return 'mobile';
        if (preg_match('/iPad|Android(?!.*Mobile)|Tablet/i', $ua)) return 'tablet';

        return 'desktop';
    }

    protected function detectLanguage(Request $request): ?string
    {
        $lang = $request->header('Accept-Language');
        if (!$lang) return null;

        return substr($lang, 0, 2);
    }

    public function trackBlockClick(Link $link, BiolinkBlock $block, string $destinationUrl, Request $request, ?string $usedAlias = null, ?string $source = null): LinkClick
    {
        $userAgent = $this->resolveUserAgent($request);
        $isBot = app(BotDetector::class)->isBot($userAgent);
        $geoService = app(GeoIpService::class);
        $geo = $geoService->detectGeo($request->ip());

        $click = LinkClick::create([
            'link_id' => $link->id,
            'alias' => $usedAlias ?: $link->alias,
            'viewer_user_id' => \App\Modules\Common\Services\ViewerSession::id() ?? auth()->id(),
            'block_id' => $block->id,
            'block_type' => $block->type,
            'destination_url' => substr($destinationUrl, 0, 2048),
            'ip_address' => $request->ip(),
            'browser' => $this->detectBrowser($userAgent),
            'os' => $this->detectOS($userAgent),
            'device_type' => $this->detectDeviceType($userAgent),
            'referrer' => $request->header('referer'),
            'source' => $source,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 512) : null,
            'channel' => ChannelClassifier::classify($userAgent),
            'is_bot' => $isBot,
            'language' => $this->detectLanguage($request),
            'country_code' => $geo['country_code'] ?? null,
            'city' => $geo['city'] ?? null,
            'latitude' => $geo['latitude'] ?? null,
            'longitude' => $geo['longitude'] ?? null,
            'utm_params' => $this->extractUtmParams($request),
            'clicked_at' => now(),
        ]);

        // Bot/scraper hits are recorded but excluded from the cached counters
        // so creator dashboards reflect real humans. We also bail BEFORE
        // dispatching BlockClicked so webhooks / "new visitor" notifications
        // never fire on scraper traffic.
        if ($isBot) {
            return $click;
        }

        $link->increment('total_clicks');

        $isUnique = !LinkClick::where('link_id', $link->id)
            ->where('ip_address', $request->ip())
            ->where('clicked_at', '>=', now()->subDay())
            ->where('id', '!=', $click->id)
            ->exists();

        if ($isUnique) {
            $link->increment('unique_clicks');
        }

        // Broadcast to downstream listeners. Reaching this line guarantees
        // `is_bot = false`.
        \App\Events\BlockClicked::dispatch($link, $block, $click, $destinationUrl);

        return $click;
    }

    protected function resolveUserAgent(Request $request): ?string
    {
        $ua = $request->userAgent();
        if ($ua) return $ua;

        $client = $request->header('X-1INME-Client');
        return $client ?: null;
    }

    protected function extractUtmParams(Request $request): ?array
    {
        $params = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $key) {
            if ($val = $request->query($key)) {
                $params[$key] = $val;
            }
        }

        return empty($params) ? null : $params;
    }
}
