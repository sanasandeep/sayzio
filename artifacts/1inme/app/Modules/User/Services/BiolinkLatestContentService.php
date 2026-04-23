<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\BiolinkBlock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Best-effort fetcher that keeps the `latest_youtube` and `latest_instagram`
 * biolink blocks fresh without a paid API key.
 *
 * Strategy:
 *  - YouTube: parse the public RSS feed (works for any channel/handle, no auth).
 *  - Instagram: prefer the user's connected Instagram OAuth token if present
 *    (re-using the long-lived token persisted by the existing socials flow);
 *    otherwise fall back to the public oEmbed for a manually pasted post URL.
 *  - Both paths cache resolved data on the block itself (`video_id`,
 *    `thumbnail`, `title`, `cached_at`) and only refresh after the TTL.
 *  - Any failure logs once and degrades silently (the renderer falls back to
 *    a "open channel/profile" link, which already exists).
 */
class BiolinkLatestContentService
{
    public const TTL_SECONDS = 6 * 3600; // 6 hours

    /**
     * Ensure the block's cached "latest" data is reasonably fresh. Mutates
     * settings in-place and persists if anything changed. Safe to call from
     * any render path — short-circuits when within TTL.
     */
    public function refreshIfStale(BiolinkBlock $block): void
    {
        $settings = $block->settings ?? [];
        $cachedAt = $settings['cached_at'] ?? null;
        if (is_string($cachedAt) && (time() - strtotime($cachedAt)) < self::TTL_SECONDS) {
            return;
        }

        try {
            if ($block->type === 'latest_youtube') {
                $this->refreshYoutube($block);
            } elseif ($block->type === 'latest_instagram') {
                $this->refreshInstagram($block);
            }
        } catch (\Throwable $e) {
            Log::warning('BiolinkLatestContentService refresh failed', [
                'block_id' => $block->id,
                'type' => $block->type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function refreshYoutube(BiolinkBlock $block): void
    {
        $settings = $block->settings ?? [];
        $raw = trim((string) ($settings['channel'] ?? ''));
        if ($raw === '') {
            return;
        }

        $channelId = $this->resolveYoutubeChannelId($raw);
        if (!$channelId) {
            return;
        }

        $feedUrl = "https://www.youtube.com/feeds/videos.xml?channel_id={$channelId}";
        $cacheKey = 'yt_feed:' . md5($feedUrl);
        $body = Cache::remember($cacheKey, 1800, function () use ($feedUrl) {
            $resp = Http::timeout(6)->get($feedUrl);
            return $resp->successful() ? $resp->body() : null;
        });

        if (!$body) {
            return;
        }

        // Lightweight regex parse — full XML parsing is overkill for the
        // first <entry> in the feed.
        if (!preg_match('#<entry>.*?<yt:videoId>([^<]+)</yt:videoId>.*?<title>([^<]+)</title>#s', $body, $m)) {
            return;
        }

        $vid = $m[1];
        $title = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $settings['video_id'] = $vid;
        $settings['title'] = $title;
        $settings['thumbnail'] = "https://i.ytimg.com/vi/{$vid}/hqdefault.jpg";
        $settings['cached_at'] = date('c');
        $block->settings = $settings;
        $block->saveQuietly();
    }

    /**
     * Accept channel IDs (UC...), @handles, /c/ vanity slugs, /user/ legacy
     * names, or full youtube.com URLs, and resolve them to a canonical
     * channel_id. Resolution result is cached for 30 days because handle ↔ id
     * is effectively immutable per channel.
     */
    private function resolveYoutubeChannelId(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') return null;

        // Strip a full URL down to its path/handle component.
        if (preg_match('#youtube\.com/(channel/|@|c/|user/)?([^/?#]+)#i', $raw, $m)) {
            $prefix = strtolower($m[1] ?? '');
            $value = $m[2];
            if ($prefix === 'channel/') return str_starts_with($value, 'UC') ? $value : null;
            $raw = ($prefix === '@' ? '@' : '') . $value;
        }

        $clean = ltrim($raw, '@/');
        if (str_starts_with($clean, 'UC') && strlen($clean) >= 20) {
            return $clean;
        }

        // Treat anything else as a handle / vanity slug. Resolve by fetching
        // the channel page once and extracting the canonical channelId.
        $cacheKey = 'yt_handle:' . md5(strtolower($clean));
        return Cache::remember($cacheKey, 60 * 60 * 24 * 30, function () use ($clean) {
            $candidates = [
                "https://www.youtube.com/@{$clean}",
                "https://www.youtube.com/c/{$clean}",
                "https://www.youtube.com/user/{$clean}",
            ];
            foreach ($candidates as $url) {
                try {
                    $resp = Http::timeout(6)->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (compatible; 1inme-biolink/1.0)',
                    ])->get($url);
                    if (!$resp->successful()) continue;
                    $html = $resp->body();
                    // Try meta tag first, then JSON blob — both appear on the page.
                    if (preg_match('#<meta itemprop="(?:channelId|identifier)" content="(UC[\w-]{20,})"#', $html, $m)) {
                        return $m[1];
                    }
                    if (preg_match('#"channelId":"(UC[\w-]{20,})"#', $html, $m)) {
                        return $m[1];
                    }
                    if (preg_match('#"externalId":"(UC[\w-]{20,})"#', $html, $m)) {
                        return $m[1];
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }
            return null;
        });
    }

    private function refreshInstagram(BiolinkBlock $block): void
    {
        $settings = $block->settings ?? [];
        $handle = trim((string) ($settings['handle'] ?? ''));
        $manualUrl = trim((string) ($settings['post_url'] ?? ''));

        // 1) If the user has a connected Instagram account on their profile,
        //    use the long-lived token via the Graph API to fetch their latest
        //    media. We look this up via the existing social_accounts table.
        if ($handle !== '' && $this->tryGraphApi($block, $handle, $settings)) {
            return;
        }

        // 2) Fall back to oEmbed for a manually-pasted post URL. This requires
        //    no auth and returns thumbnail + caption without rate concerns.
        if ($manualUrl !== '') {
            $this->tryOembed($block, $manualUrl, $settings);
        }
    }

    private function tryGraphApi(BiolinkBlock $block, string $handle, array $settings): bool
    {
        // The connected-accounts table is owned by the existing socials flow —
        // we read-only here and never refresh tokens. If unavailable, skip.
        try {
            $userId = $block->link?->user_id;
            if (!$userId) {
                return false;
            }
            // Use the Eloquent model so `access_token` is auto-decrypted.
            $row = \App\Modules\User\Models\SocialAccountConnection::query()
                ->where('user_id', $userId)
                ->where('platform', 'instagram')
                ->whereNotNull('access_token')
                ->first();
            if (!$row || empty($row->access_token)) {
                return false;
            }
            $resp = Http::timeout(6)->get('https://graph.instagram.com/me/media', [
                'fields' => 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp',
                'limit' => 1,
                'access_token' => $row->access_token,
            ]);
            if (!$resp->successful()) {
                return false;
            }
            $data = $resp->json('data.0');
            if (!$data) {
                return false;
            }
            $settings['post_url'] = $data['permalink'] ?? '';
            $settings['thumbnail'] = $data['thumbnail_url'] ?? $data['media_url'] ?? '';
            $settings['caption'] = isset($data['caption']) ? mb_substr($data['caption'], 0, 240) : '';
            $settings['cached_at'] = date('c');
            $block->settings = $settings;
            $block->saveQuietly();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function tryOembed(BiolinkBlock $block, string $url, array $settings): void
    {
        // Instagram's public oEmbed has tightened — we attempt a generic
        // fetch and only update fields we actually got back. If nothing
        // resolves, we leave the manually-entered fields in place.
        $resp = Http::timeout(6)->get('https://www.instagram.com/api/v1/oembed/', [
            'url' => $url,
        ]);
        if (!$resp->successful()) {
            return;
        }
        $j = $resp->json();
        if (!is_array($j)) {
            return;
        }
        if (!empty($j['thumbnail_url'])) {
            $settings['thumbnail'] = $j['thumbnail_url'];
        }
        if (!empty($j['title'])) {
            $settings['caption'] = mb_substr($j['title'], 0, 240);
        }
        $settings['cached_at'] = date('c');
        $block->settings = $settings;
        $block->saveQuietly();
    }
}
