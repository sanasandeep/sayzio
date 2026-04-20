<?php

namespace App\Modules\User\Services\SocialFollowers;

use App\Modules\User\Models\SocialAccountConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

/**
 * Generic, configuration-driven OAuth 2.0 authorization-code flow for the
 * social platforms that need a per-user access token to read follower counts.
 *
 * Each provider is gated on the presence of its CLIENT_ID + CLIENT_SECRET env
 * vars — when those aren't configured, the UI falls back to manual token paste
 * (so the feature still works for self-hosted installs that haven't registered
 * a developer app yet). Once configured, the public "Connect with …" button
 * runs the full authorize → callback → token-exchange → handle/profile fetch
 * dance and persists the result on social_account_connections.
 */
class SocialOAuthService
{
    /**
     * Per-provider OAuth endpoints + scopes. Profile lookup is platform-
     * specific; we only fetch the bare minimum (user id + handle) — the
     * existing FollowerFetcherRegistry then takes over for live counts.
     */
    public const PROVIDERS = [
        'facebook' => [
            'client_id_env'     => 'FACEBOOK_CLIENT_ID',
            'client_secret_env' => 'FACEBOOK_CLIENT_SECRET',
            'authorize_url'     => 'https://www.facebook.com/v19.0/dialog/oauth',
            'token_url'         => 'https://graph.facebook.com/v19.0/oauth/access_token',
            'scope'             => 'public_profile,pages_show_list,pages_read_engagement',
            'profile_url'       => 'https://graph.facebook.com/v19.0/me?fields=id,name',
        ],
        'instagram' => [
            // Instagram Business uses the same Meta app as Facebook.
            'client_id_env'     => 'FACEBOOK_CLIENT_ID',
            'client_secret_env' => 'FACEBOOK_CLIENT_SECRET',
            'authorize_url'     => 'https://www.facebook.com/v19.0/dialog/oauth',
            'token_url'         => 'https://graph.facebook.com/v19.0/oauth/access_token',
            'scope'             => 'instagram_basic,pages_show_list',
            'profile_url'       => 'https://graph.facebook.com/v19.0/me?fields=id,name',
        ],
        'linkedin' => [
            'client_id_env'     => 'LINKEDIN_CLIENT_ID',
            'client_secret_env' => 'LINKEDIN_CLIENT_SECRET',
            'authorize_url'     => 'https://www.linkedin.com/oauth/v2/authorization',
            'token_url'         => 'https://www.linkedin.com/oauth/v2/accessToken',
            'scope'             => 'r_liteprofile r_organization_social',
            'profile_url'       => 'https://api.linkedin.com/v2/me',
        ],
        'twitter' => [
            'client_id_env'     => 'TWITTER_CLIENT_ID',
            'client_secret_env' => 'TWITTER_CLIENT_SECRET',
            'authorize_url'     => 'https://twitter.com/i/oauth2/authorize',
            'token_url'         => 'https://api.twitter.com/2/oauth2/token',
            'scope'             => 'tweet.read users.read offline.access',
            'profile_url'       => 'https://api.twitter.com/2/users/me',
            'pkce'              => true,
        ],
        'pinterest' => [
            'client_id_env'     => 'PINTEREST_CLIENT_ID',
            'client_secret_env' => 'PINTEREST_CLIENT_SECRET',
            'authorize_url'     => 'https://www.pinterest.com/oauth/',
            'token_url'         => 'https://api.pinterest.com/v5/oauth/token',
            'scope'             => 'user_accounts:read',
            'profile_url'       => 'https://api.pinterest.com/v5/user_account',
        ],
        'tiktok' => [
            'client_id_env'     => 'TIKTOK_CLIENT_KEY',
            'client_secret_env' => 'TIKTOK_CLIENT_SECRET',
            'authorize_url'     => 'https://www.tiktok.com/v2/auth/authorize/',
            'token_url'         => 'https://open.tiktokapis.com/v2/oauth/token/',
            'scope'             => 'user.info.basic',
            'profile_url'       => 'https://open.tiktokapis.com/v2/user/info/?fields=open_id,union_id,display_name',
        ],
    ];

    public function isConfigured(string $provider): bool
    {
        $cfg = self::PROVIDERS[$provider] ?? null;
        if (! $cfg) return false;
        return ! empty(env($cfg['client_id_env'])) && ! empty(env($cfg['client_secret_env']));
    }

    public function configuredProviders(): array
    {
        $out = [];
        foreach (array_keys(self::PROVIDERS) as $p) {
            if ($this->isConfigured($p)) $out[] = $p;
        }
        return $out;
    }

    public function callbackUrl(string $provider): string
    {
        return URL::route('user.social-oauth.callback', ['provider' => $provider]);
    }

    /** Build the authorize URL the user should be redirected to. */
    public function authorizeUrl(string $provider, string $state): string
    {
        $cfg = self::PROVIDERS[$provider];
        $params = [
            'client_id'     => env($cfg['client_id_env']),
            'redirect_uri'  => $this->callbackUrl($provider),
            'response_type' => 'code',
            'scope'         => $cfg['scope'],
            'state'         => $state,
        ];
        if (! empty($cfg['pkce'])) {
            // Bare PKCE — using `plain` so we don't need to stash a verifier
            // separately. Real apps should swap to S256; the path is here.
            $params['code_challenge']        = $state;
            $params['code_challenge_method'] = 'plain';
        }
        return $cfg['authorize_url'] . '?' . http_build_query($params);
    }

    /**
     * Exchange the authorization code for an access token and persist a
     * SocialAccountConnection for the user. Returns the connection on
     * success; throws on failure.
     */
    public function exchangeAndPersist(string $provider, int $userId, string $code, string $state): SocialAccountConnection
    {
        $cfg = self::PROVIDERS[$provider];

        $body = [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $this->callbackUrl($provider),
            'client_id'     => env($cfg['client_id_env']),
            'client_secret' => env($cfg['client_secret_env']),
        ];
        if (! empty($cfg['pkce'])) $body['code_verifier'] = $state;

        $tokenResp = Http::asForm()->acceptJson()->post($cfg['token_url'], $body);
        if (! $tokenResp->ok()) {
            throw new \RuntimeException('Token exchange failed: HTTP ' . $tokenResp->status());
        }

        $token = $tokenResp->json();
        $access  = $token['access_token']  ?? null;
        $refresh = $token['refresh_token'] ?? null;
        $expires = isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null;

        if (! $access) {
            throw new \RuntimeException('Provider returned no access_token.');
        }

        // Best-effort profile lookup — purely cosmetic; failure is non-fatal.
        $handle      = null;
        $external_id = null;
        try {
            $profile = Http::withToken($access)->acceptJson()->get($cfg['profile_url'])->json();
            if (is_array($profile)) {
                $handle = $profile['username']
                    ?? $profile['data']['username']
                    ?? $profile['name']
                    ?? $profile['data']['display_name']
                    ?? null;
                $external_id = (string) ($profile['id'] ?? $profile['data']['user']['open_id'] ?? '');
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $handle = $handle ?: ('user-' . substr(md5($access), 0, 8));

        return SocialAccountConnection::updateOrCreate(
            ['user_id' => $userId, 'platform' => $provider, 'handle' => $handle],
            [
                'access_token'        => $access,
                'refresh_token'       => $refresh,
                'token_expires_at'    => $expires,
                'external_id'         => $external_id ?: null,
                'last_refresh_status' => 'pending',
            ]
        );
    }
}
