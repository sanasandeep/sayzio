<?php

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Custom Search JSON API client in image-search mode, used by the
 * AI biolink builder to suggest candidate images for the page topic/brand.
 *
 * Credentials (API key + Programmable Search Engine ID) are admin-managed
 * via PlatformServiceSettings with config/env fallback. When either piece
 * is missing the service reports disabled and callers hide the feature
 * gracefully (preview mode). In the manual image-search UI results are
 * suggestions the user explicitly picks from. The AI builder's automatic
 * tier (BuilderImageSourcer::searchFromDescription) also consumes this
 * service: candidates surface in the source-preview review step, and are
 * used directly only when the creator submits without reviewing.
 */
class GoogleImageSearchService
{
    public const ENDPOINT = 'https://www.googleapis.com/customsearch/v1';

    /** Google caps num at 10 per request; we never page. */
    public const MAX_RESULTS = 10;

    public function enabled(): bool
    {
        return PlatformServiceSettings::googleCseConfigured();
    }

    /**
     * Run an image search. Returns [] when disabled, on API errors, or
     * when the query is blank — callers fall back to the existing image
     * waterfall, so failures here must never sink a build.
     *
     * @return list<array{url:string,thumbnail:?string,title:?string,source:?string,width:?int,height:?int}>
     */
    public function search(string $query, int $count = 8, ?int $userId = null): array
    {
        $query = trim($query);
        if ($query === '' || !$this->enabled()) {
            return [];
        }

        $count = max(1, min($count, self::MAX_RESULTS));

        // Every outbound request consumes Google CSE quota (100/day free),
        // so count it regardless of the eventual response status.
        GoogleCseUsage::record($userId);

        try {
            $response = Http::timeout(8)->get(self::ENDPOINT, [
                'key'        => PlatformServiceSettings::googleCseApiKey(),
                'cx'         => PlatformServiceSettings::googleCseEngineId(),
                'q'          => mb_substr($query, 0, 200),
                'searchType' => 'image',
                'num'        => $count,
                'safe'       => 'active',
            ]);
        } catch (\Throwable $e) {
            Log::info('Google image search failed: ' . $e->getMessage());
            return [];
        }

        if (!$response->successful()) {
            Log::info('Google image search HTTP ' . $response->status());
            return [];
        }

        $out = [];
        foreach ((array) $response->json('items', []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $link = (string) ($item['link'] ?? '');
            if (!preg_match('#^https?://#i', $link)) {
                continue;
            }
            $image = is_array($item['image'] ?? null) ? $item['image'] : [];

            $thumb = (string) ($image['thumbnailLink'] ?? '');
            $out[] = [
                'url'       => mb_substr($link, 0, 2048),
                'thumbnail' => preg_match('#^https?://#i', $thumb) ? mb_substr($thumb, 0, 2048) : null,
                'title'     => isset($item['title']) ? mb_substr((string) $item['title'], 0, 160) : null,
                'source'    => isset($item['displayLink']) ? mb_substr((string) $item['displayLink'], 0, 160) : null,
                'width'     => isset($image['width']) ? (int) $image['width'] : null,
                'height'    => isset($image['height']) ? (int) $image['height'] : null,
            ];
            if (count($out) >= $count) {
                break;
            }
        }

        return $out;
    }
}
