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
        'google' => [
            // Shares the GOOGLE_CLIENT_ID/SECRET Google project that the
            // mobile native sign-in (Api\SocialAuthController::verifyGoogle)
            // already verifies tokens against, so a single OAuth client
            // covers web + mobile. The web client must be registered as a
            // "Web application" with this controller's callback URL.
            'client_id_env'     => 'GOOGLE_CLIENT_ID',
            'client_secret_env' => 'GOOGLE_CLIENT_SECRET',
            'authorize_url'     => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url'         => 'https://oauth2.googleapis.com/token',
            'scope'             => 'openid email profile',
            // userinfo v2 returns `id` (== the OIDC `sub` the mobile native
            // flow keys on) plus `email` and `name`, matching the id/handle
            // extraction below.
            'profile_url'       => 'https://www.googleapis.com/oauth2/v2/userinfo',
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
     * Whether a connection is eligible for automatic token renewal at all.
     * Each provider defines its own renewal strategy:
     *
     *   - Meta (facebook, instagram): no refresh_token; long-lived user tokens
     *     are extended via the `fb_exchange_token` grant on the existing
     *     access_token. Eligible whenever an access_token is present.
     *   - LinkedIn, Pinterest, X/Twitter, TikTok: standard OAuth 2 refresh-
     *     token grant. Eligible whenever a refresh_token is present.
     *
     * Returns false for any connection whose provider isn't OAuth-configured
     * on this server, or which lacks the credentials its strategy needs.
     */
    public function canRefreshToken(SocialAccountConnection $c): bool
    {
        if (! isset(self::PROVIDERS[$c->platform])) return false;
        if (! $this->isConfigured($c->platform)) return false;

        return match ($c->platform) {
            'facebook', 'instagram' => ! empty($c->access_token),
            default                 => ! empty($c->refresh_token),
        };
    }

    /**
     * Renew this connection's access_token in place using the per-provider
     * strategy. Returns true if a fresh token was persisted, false if there
     * was nothing to do (provider unconfigured, missing credentials), and
     * throws on a real failure so the caller can mark the connection broken.
     */
    public function refreshAccessToken(SocialAccountConnection $c): bool
    {
        if (! $this->canRefreshToken($c)) return false;

        return match ($c->platform) {
            'facebook', 'instagram' => $this->refreshMeta($c),
            'tiktok'                => $this->refreshTikTok($c),
            'twitter'               => $this->refreshTwitter($c),
            default                 => $this->refreshGenericOAuth2($c),
        };
    }

    /**
     * Meta long-lived token extension. Exchanges the current (still-valid)
     * access_token for a new ~60-day token via fb_exchange_token. There is no
     * refresh_token in this flow — it's a token-for-token swap.
     */
    private function refreshMeta(SocialAccountConnection $c): bool
    {
        $cfg = self::PROVIDERS[$c->platform];
        $resp = Http::acceptJson()->get($cfg['token_url'], [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => env($cfg['client_id_env']),
            'client_secret'     => env($cfg['client_secret_env']),
            'fb_exchange_token' => $c->access_token,
        ]);
        return $this->persistRefreshedToken($c, $resp);
    }

    /**
     * TikTok refresh — note the body uses `client_key` (TikTok's name for
     * the OAuth client id) rather than `client_id`.
     */
    private function refreshTikTok(SocialAccountConnection $c): bool
    {
        $cfg = self::PROVIDERS['tiktok'];
        $resp = Http::asForm()->acceptJson()->post($cfg['token_url'], [
            'client_key'    => env($cfg['client_id_env']),
            'client_secret' => env($cfg['client_secret_env']),
            'grant_type'    => 'refresh_token',
            'refresh_token' => $c->refresh_token,
        ]);
        return $this->persistRefreshedToken($c, $resp);
    }

    /**
     * X (Twitter) v2 refresh — public OAuth2 client, body carries
     * client_id; confidential clients additionally need HTTP Basic auth.
     * We send both so it works regardless of how the developer app is
     * registered.
     */
    private function refreshTwitter(SocialAccountConnection $c): bool
    {
        $cfg = self::PROVIDERS['twitter'];
        $clientId     = (string) env($cfg['client_id_env']);
        $clientSecret = (string) env($cfg['client_secret_env']);
        $resp = Http::asForm()->acceptJson()
            ->withBasicAuth($clientId, $clientSecret)
            ->post($cfg['token_url'], [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $c->refresh_token,
                'client_id'     => $clientId,
            ]);
        return $this->persistRefreshedToken($c, $resp);
    }

    /** Standard OAuth2 refresh-token grant for LinkedIn / Pinterest. */
    private function refreshGenericOAuth2(SocialAccountConnection $c): bool
    {
        $cfg = self::PROVIDERS[$c->platform];
        $resp = Http::asForm()->acceptJson()->post($cfg['token_url'], [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $c->refresh_token,
            'client_id'     => env($cfg['client_id_env']),
            'client_secret' => env($cfg['client_secret_env']),
        ]);
        return $this->persistRefreshedToken($c, $resp);
    }

    /** Shared response handler — extracts the token, persists, throws on error. */
    private function persistRefreshedToken(SocialAccountConnection $c, $resp): bool
    {
        if (! $resp->ok()) {
            throw new \RuntimeException("Token refresh failed for {$c->platform}: HTTP " . $resp->status());
        }
        $token = $resp->json();
        $access = $token['access_token'] ?? null;
        if (! $access) {
            throw new \RuntimeException("Token refresh for {$c->platform} returned no access_token.");
        }

        $c->access_token = $access;
        // Some providers (e.g. TikTok, sometimes LinkedIn) rotate refresh_token on every swap.
        if (! empty($token['refresh_token'])) $c->refresh_token = $token['refresh_token'];
        $c->token_expires_at = isset($token['expires_in'])
            ? now()->addSeconds((int) $token['expires_in'])
            : null;
        $c->save();
        return true;
    }

    /**
     * Exchange the auth code and fetch the user's external id, handle and
     * (when the provider returns one) email, without persisting a
     * connection. Used by the login and merge-challenge OAuth flows where
     * we just need to identify the remote user.
     *
     * The email is only populated by providers whose profile_url requests
     * it (currently Google — `openid email profile`). Every other provider
     * here fetches id/name only, so the email is null for them and the
     * email-based account resolution in the controller is effectively a
     * Google-only path.
     *
     * @return array{0:string, 1:?string, 2:?string} [externalId, handle, email]
     */
    public function fetchProfile(string $provider, string $code, string $state): array
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
        $access = $tokenResp->json('access_token');
        if (! $access) throw new \RuntimeException('Provider returned no access_token.');

        $profile = Http::withToken($access)->acceptJson()->get($cfg['profile_url'])->json();
        $externalId = (string) (
            $profile['id']
            ?? $profile['data']['user']['open_id']
            ?? $profile['data']['id']
            ?? ''
        );
        if ($externalId === '') {
            throw new \RuntimeException('Provider profile lookup returned no id.');
        }
        $handle = $profile['username']
            ?? $profile['data']['username']
            ?? $profile['name']
            ?? $profile['data']['display_name']
            ?? null;
        $email = $profile['email']
            ?? $profile['data']['email']
            ?? null;
        $email = is_string($email) && $email !== '' ? strtolower(trim($email)) : null;
        return [$externalId, $handle, $email];
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
                // Match the same set of id locations fetchProfile() reads —
                // notably Twitter/X returns the id under data.id, and TikTok
                // under data.user.open_id. Keeping the two readers aligned
                // is critical: linked_identifiers are keyed off this id, so
                // a mismatch here means a user can connect a provider but
                // never sign in with it.
                $external_id = (string) (
                    $profile['id']
                    ?? $profile['data']['user']['open_id']
                    ?? $profile['data']['id']
                    ?? ''
                );
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $handle = $handle ?: ('user-' . substr(md5($access), 0, 8));

        return SocialAccountConnection::updateOrCreate(
            ['user_id' => $userId, 'platform' => $provider, 'handle' => $handle],
            [
                'access_token'             => $access,
                'refresh_token'            => $refresh,
                'token_expires_at'         => $expires,
                'external_id'              => $external_id ?: null,
                'last_refresh_status'      => 'pending',
                'last_refresh_error'       => null,
                // Reconnecting clears any prior backoff so the next refresh
                // treats this account as a fresh, healthy connection.
                'consecutive_failures'     => 0,
                'last_failure_notified_at' => null,
            ]
        );
    }
}
