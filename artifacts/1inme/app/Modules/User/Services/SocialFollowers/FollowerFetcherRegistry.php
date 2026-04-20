<?php

namespace App\Modules\User\Services\SocialFollowers;

use App\Modules\User\Models\SocialAccountConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Per-platform follower-count fetchers.
 *
 * Each method returns ['count' => int|null, 'meta' => array, 'profile_url' => ?string].
 * On any error it MUST throw — the caller will catch and mark the connection
 * with status=error so the public renderer silently falls back to no count.
 */
class FollowerFetcherRegistry
{
    /**
     * Refresh a single connection's cached follower count.
     * Updates the model in place and returns the new status.
     */
    public function refresh(SocialAccountConnection $c): string
    {
        try {
            $result = match ($c->platform) {
                'github'    => $this->github($c),
                'youtube'   => $this->youtube($c),
                'twitch'    => $this->twitch($c),
                'instagram' => $this->instagramGraph($c),
                'facebook'  => $this->facebookGraph($c),
                'tiktok'    => $this->tiktokOEmbed($c),
                'twitter'   => $this->twitterApi($c),
                'linkedin'  => $this->linkedinApi($c),
                'pinterest' => $this->pinterestApi($c),
                default     => null,
            };

            if ($result === null) {
                $c->update([
                    'last_refreshed_at'   => now(),
                    'last_refresh_status' => 'unsupported',
                    'last_refresh_error'  => null,
                ]);
                return 'unsupported';
            }

            $c->fill([
                'follower_count'      => $result['count'] ?? null,
                'meta'                => array_merge((array) $c->meta, $result['meta'] ?? []),
                'last_refreshed_at'   => now(),
                'last_refresh_status' => 'ok',
                'last_refresh_error'  => null,
            ]);
            if (! empty($result['profile_url']) && ! $c->profile_url) $c->profile_url = $result['profile_url'];
            if (! empty($result['display_name']) && ! $c->display_name) $c->display_name = $result['display_name'];
            if (! empty($result['avatar_url']) && ! $c->avatar_url) $c->avatar_url = $result['avatar_url'];
            if (! empty($result['external_id']) && ! $c->external_id) $c->external_id = $result['external_id'];
            $c->save();
            return 'ok';
        } catch (Throwable $e) {
            Log::warning('SocialFollower refresh failed', [
                'connection' => $c->id, 'platform' => $c->platform, 'err' => $e->getMessage(),
            ]);
            $c->update([
                'last_refreshed_at'   => now(),
                'last_refresh_status' => 'error',
                'last_refresh_error'  => substr($e->getMessage(), 0, 500),
            ]);
            return 'error';
        }
    }

    // ── Platform implementations ─────────────────────────────────────────

    /** Public GitHub user endpoint — no auth required (rate-limited to ~60 req/h/IP). */
    private function github(SocialAccountConnection $c): array
    {
        $h = ltrim((string) $c->handle, '@');
        $r = Http::timeout(8)->withHeaders(['Accept' => 'application/vnd.github+json'])
            ->get("https://api.github.com/users/" . rawurlencode($h));
        if (! $r->ok()) throw new \RuntimeException("GitHub HTTP {$r->status()}");
        $j = $r->json();
        return [
            'count'        => (int) ($j['followers'] ?? 0),
            'profile_url'  => $j['html_url'] ?? null,
            'display_name' => $j['name'] ?? $j['login'] ?? null,
            'avatar_url'   => $j['avatar_url'] ?? null,
            'external_id'  => isset($j['id']) ? (string) $j['id'] : null,
            'meta'         => ['public_repos' => $j['public_repos'] ?? null],
        ];
    }

    /** YouTube Data API v3 channels endpoint — needs YOUTUBE_API_KEY env var. */
    private function youtube(SocialAccountConnection $c): array
    {
        $key = env('YOUTUBE_API_KEY');
        if (! $key) throw new \RuntimeException('YOUTUBE_API_KEY not configured');
        $h = ltrim((string) $c->handle, '@');

        // Resolve channel id either from stored external_id or from handle/username.
        $channelId = $c->external_id;
        if (! $channelId) {
            $r = Http::timeout(8)->get('https://www.googleapis.com/youtube/v3/search', [
                'part' => 'snippet', 'type' => 'channel', 'q' => $h, 'maxResults' => 1, 'key' => $key,
            ]);
            if (! $r->ok()) throw new \RuntimeException("YouTube search HTTP {$r->status()}");
            $channelId = $r->json('items.0.snippet.channelId');
            if (! $channelId) throw new \RuntimeException('YouTube channel not found');
        }

        $r = Http::timeout(8)->get('https://www.googleapis.com/youtube/v3/channels', [
            'part' => 'statistics,snippet', 'id' => $channelId, 'key' => $key,
        ]);
        if (! $r->ok()) throw new \RuntimeException("YouTube channels HTTP {$r->status()}");
        $item = $r->json('items.0');
        if (! $item) throw new \RuntimeException('YouTube channel not found');

        return [
            'count'        => (int) ($item['statistics']['subscriberCount'] ?? 0),
            'profile_url'  => "https://youtube.com/channel/{$channelId}",
            'display_name' => $item['snippet']['title'] ?? null,
            'avatar_url'   => $item['snippet']['thumbnails']['default']['url'] ?? null,
            'external_id'  => $channelId,
            'meta'         => ['video_count' => $item['statistics']['videoCount'] ?? null],
        ];
    }

    /** Twitch helix users + followers — needs TWITCH_CLIENT_ID and an app token. */
    private function twitch(SocialAccountConnection $c): array
    {
        $clientId = env('TWITCH_CLIENT_ID');
        $secret   = env('TWITCH_CLIENT_SECRET');
        if (! $clientId || ! $secret) throw new \RuntimeException('Twitch app credentials not configured');

        $tokR = Http::timeout(8)->asForm()->post('https://id.twitch.tv/oauth2/token', [
            'client_id' => $clientId, 'client_secret' => $secret, 'grant_type' => 'client_credentials',
        ]);
        if (! $tokR->ok()) throw new \RuntimeException("Twitch token HTTP {$tokR->status()}");
        $tok = $tokR->json('access_token');

        $h = ltrim((string) $c->handle, '@');
        $u = Http::timeout(8)->withToken($tok)->withHeaders(['Client-Id' => $clientId])
            ->get('https://api.twitch.tv/helix/users', ['login' => $h]);
        if (! $u->ok()) throw new \RuntimeException("Twitch users HTTP {$u->status()}");
        $user = $u->json('data.0');
        if (! $user) throw new \RuntimeException('Twitch user not found');

        $f = Http::timeout(8)->withToken($tok)->withHeaders(['Client-Id' => $clientId])
            ->get('https://api.twitch.tv/helix/channels/followers', ['broadcaster_id' => $user['id']]);

        return [
            'count'        => (int) ($f->json('total') ?? 0),
            'profile_url'  => "https://twitch.tv/{$user['login']}",
            'display_name' => $user['display_name'] ?? null,
            'avatar_url'   => $user['profile_image_url'] ?? null,
            'external_id'  => (string) $user['id'],
            'meta'         => [],
        ];
    }

    /** Instagram Graph API — needs a long-lived user access token (Business/Creator account). */
    private function instagramGraph(SocialAccountConnection $c): array
    {
        if (! $c->access_token) throw new \RuntimeException('Instagram access token missing');
        $r = Http::timeout(8)->get('https://graph.instagram.com/me', [
            'fields' => 'id,username,account_type,followers_count,profile_picture_url',
            'access_token' => $c->access_token,
        ]);
        if (! $r->ok()) throw new \RuntimeException("Instagram HTTP {$r->status()}");
        $j = $r->json();
        return [
            'count'        => isset($j['followers_count']) ? (int) $j['followers_count'] : null,
            'profile_url'  => isset($j['username']) ? "https://instagram.com/{$j['username']}" : null,
            'display_name' => $j['username'] ?? null,
            'avatar_url'   => $j['profile_picture_url'] ?? null,
            'external_id'  => isset($j['id']) ? (string) $j['id'] : null,
            'meta'         => ['account_type' => $j['account_type'] ?? null],
        ];
    }

    /** Facebook Graph API — needs a page access token (Pages: returns fan_count). */
    private function facebookGraph(SocialAccountConnection $c): array
    {
        if (! $c->access_token) throw new \RuntimeException('Facebook access token missing');
        $r = Http::timeout(8)->get('https://graph.facebook.com/v19.0/me', [
            'fields' => 'id,name,fan_count,followers_count,picture',
            'access_token' => $c->access_token,
        ]);
        if (! $r->ok()) throw new \RuntimeException("Facebook HTTP {$r->status()}");
        $j = $r->json();
        $count = $j['followers_count'] ?? $j['fan_count'] ?? null;
        return [
            'count'        => $count !== null ? (int) $count : null,
            'profile_url'  => isset($j['id']) ? "https://facebook.com/{$j['id']}" : null,
            'display_name' => $j['name'] ?? null,
            'avatar_url'   => $j['picture']['data']['url'] ?? null,
            'external_id'  => isset($j['id']) ? (string) $j['id'] : null,
            'meta'         => [],
        ];
    }

    /** TikTok oEmbed — public, returns profile metadata but NOT follower count. */
    private function tiktokOEmbed(SocialAccountConnection $c): array
    {
        // If a TikTok user access token is present we'd query the TikTok Display API
        // (user.info.basic returns follower_count). Stub: try Display API first.
        if ($c->access_token) {
            $r = Http::timeout(8)->withToken($c->access_token)
                ->get('https://open.tiktokapis.com/v2/user/info/', [
                    'fields' => 'open_id,display_name,avatar_url,follower_count,profile_deep_link',
                ]);
            if ($r->ok() && ($u = $r->json('data.user'))) {
                return [
                    'count'        => isset($u['follower_count']) ? (int) $u['follower_count'] : null,
                    'profile_url'  => $u['profile_deep_link'] ?? null,
                    'display_name' => $u['display_name'] ?? null,
                    'avatar_url'   => $u['avatar_url'] ?? null,
                    'external_id'  => $u['open_id'] ?? null,
                    'meta'         => [],
                ];
            }
        }
        // No token / failed: store profile metadata only, leave count untouched.
        $h = ltrim((string) $c->handle, '@');
        return [
            'count'       => null,
            'profile_url' => "https://tiktok.com/@{$h}",
            'meta'        => ['note' => 'follower count requires connected TikTok account'],
        ];
    }

    /** X (Twitter) v2 users/me — needs a user OAuth2 token with users.read scope. */
    private function twitterApi(SocialAccountConnection $c): array
    {
        if (! $c->access_token) throw new \RuntimeException('X access token missing');
        $r = Http::timeout(8)->withToken($c->access_token)
            ->get('https://api.twitter.com/2/users/me', [
                'user.fields' => 'public_metrics,profile_image_url,username,name',
            ]);
        if (! $r->ok()) throw new \RuntimeException("X HTTP {$r->status()}");
        $u = $r->json('data');
        return [
            'count'        => (int) ($u['public_metrics']['followers_count'] ?? 0),
            'profile_url'  => isset($u['username']) ? "https://x.com/{$u['username']}" : null,
            'display_name' => $u['name'] ?? null,
            'avatar_url'   => $u['profile_image_url'] ?? null,
            'external_id'  => $u['id'] ?? null,
            'meta'         => [],
        ];
    }

    /** LinkedIn — networkSize endpoint requires `r_1st_connections_size` scope. */
    private function linkedinApi(SocialAccountConnection $c): array
    {
        if (! $c->access_token) throw new \RuntimeException('LinkedIn access token missing');
        $r = Http::timeout(8)->withToken($c->access_token)
            ->get('https://api.linkedin.com/v2/networkSizes/urn:li:person:' . urlencode((string) ($c->external_id ?: 'me')), [
                'edgeType' => 'CompanyFollowedByMember',
            ]);
        if (! $r->ok()) throw new \RuntimeException("LinkedIn HTTP {$r->status()}");
        return [
            'count' => (int) ($r->json('firstDegreeSize') ?? 0),
            'meta'  => [],
        ];
    }

    /** Pinterest user_account endpoint (v5). */
    private function pinterestApi(SocialAccountConnection $c): array
    {
        if (! $c->access_token) throw new \RuntimeException('Pinterest access token missing');
        $r = Http::timeout(8)->withToken($c->access_token)
            ->get('https://api.pinterest.com/v5/user_account');
        if (! $r->ok()) throw new \RuntimeException("Pinterest HTTP {$r->status()}");
        $j = $r->json();
        return [
            'count'        => isset($j['follower_count']) ? (int) $j['follower_count'] : null,
            'profile_url'  => isset($j['username']) ? "https://pinterest.com/{$j['username']}" : null,
            'display_name' => $j['username'] ?? null,
            'avatar_url'   => $j['profile_image'] ?? null,
            'external_id'  => $j['id'] ?? null,
            'meta'         => ['account_type' => $j['account_type'] ?? null],
        ];
    }
}
