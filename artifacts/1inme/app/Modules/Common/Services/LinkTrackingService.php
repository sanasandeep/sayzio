<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkClick;
use Illuminate\Http\Request;

class LinkTrackingService
{
    public function track(Link $link, Request $request): LinkClick
    {
        $userAgent = $request->userAgent();

        $geo = $this->detectGeo($request->ip());

        $click = LinkClick::create([
            'link_id' => $link->id,
            'ip_address' => $request->ip(),
            'browser' => $this->detectBrowser($userAgent),
            'os' => $this->detectOS($userAgent),
            'device_type' => $this->detectDeviceType($userAgent),
            'referrer' => $request->header('referer'),
            'language' => $this->detectLanguage($request),
            'country_code' => $geo['country_code'] ?? null,
            'city' => $geo['city'] ?? null,
            'utm_params' => $this->extractUtmParams($request),
            'clicked_at' => now(),
        ]);

        $link->increment('total_clicks');

        $isUnique = !LinkClick::where('link_id', $link->id)
            ->where('ip_address', $request->ip())
            ->where('clicked_at', '>=', now()->subDay())
            ->where('id', '!=', $click->id)
            ->exists();

        if ($isUnique) {
            $link->increment('unique_clicks');
        }

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

    protected function detectGeo(string $ip): array
    {
        if (in_array($ip, ['127.0.0.1', '::1', '0.0.0.0']) || str_starts_with($ip, '10.') || str_starts_with($ip, '192.168.')) {
            return [];
        }

        try {
            $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=countryCode,city", false, stream_context_create([
                'http' => ['timeout' => 2],
            ]));

            if ($response) {
                $data = json_decode($response, true);
                return [
                    'country_code' => $data['countryCode'] ?? null,
                    'city' => $data['city'] ?? null,
                ];
            }
        } catch (\Exception $e) {
            \Log::debug('GeoIP lookup failed in tracking: ' . $e->getMessage());
        }

        return [];
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
