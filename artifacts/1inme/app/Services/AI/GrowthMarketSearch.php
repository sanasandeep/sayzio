<?php

namespace App\Services\AI;

use App\Services\Integrations\GoogleCseUsage;
use App\Services\Integrations\GoogleImageSearchService;
use App\Services\Integrations\PlatformServiceSettings;
use Illuminate\Support\Facades\Http;

class GrowthMarketSearch
{
    public function enabled(): bool
    {
        return PlatformServiceSettings::googleCseConfigured();
    }

    public function search(string $query, int $userId): array
    {
        abort_unless($this->enabled(), 422, 'Market research is not configured by the administrator.');
        abort_if(GoogleCseUsage::capReached($userId), 429, 'Daily market search limit reached. Try again tomorrow.');
        GoogleCseUsage::record($userId);
        $response = Http::timeout(10)->get(GoogleImageSearchService::ENDPOINT, ['key' => PlatformServiceSettings::googleCseApiKey(), 'cx' => PlatformServiceSettings::googleCseEngineId(), 'q' => mb_substr($query, 0, 200), 'num' => 5, 'safe' => 'active']);
        if (! $response->successful()) {
            throw new \RuntimeException('Market search unavailable.');
        }
        $sources = [];
        foreach (array_slice((array) $response->json('items', []), 0, 5) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $url = $item['link'] ?? '';
            if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'])) {
                continue;
            }
            $sources[] = ['id' => count($sources) + 1, 'url' => mb_substr($url, 0, 2048), 'title' => mb_substr(strip_tags(is_string($item['title'] ?? null) ? $item['title'] : ''), 0, 200), 'snippet' => mb_substr(strip_tags(is_string($item['snippet'] ?? null) ? $item['snippet'] : ''), 0, 600), 'retrieved_at' => now()->toIso8601String()];
        }
        if (! $sources) {
            throw new \RuntimeException('No market sources were found. Try a more specific location and purpose.');
        }

        return $sources;
    }
}
