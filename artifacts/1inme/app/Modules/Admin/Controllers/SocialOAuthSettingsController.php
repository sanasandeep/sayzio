<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Services\SocialFollowers\SocialOAuthService;
use App\Services\Integrations\PlatformServiceSettings;
use Illuminate\Http\Request;

class SocialOAuthSettingsController extends Controller
{
    /**
     * Per-provider docs surfaced on the admin status page. Kept here (rather
     * than on the service itself) because the service is purely about the
     * runtime OAuth flow — the developer-app registration URL and human-
     * facing label are admin-UI concerns only.
     */
    private const PROVIDER_DOCS = [
        'facebook' => [
            'label'       => 'Facebook',
            'icon'        => 'fa-brands fa-facebook',
            'register_at' => 'https://developers.facebook.com/apps/',
            'notes'       => 'Create a Meta app, add the Facebook Login product, and add the redirect URI under Valid OAuth Redirect URIs. The same Meta app powers Instagram below.',
        ],
        'instagram' => [
            'label'       => 'Instagram',
            'icon'        => 'fa-brands fa-instagram',
            'register_at' => 'https://developers.facebook.com/apps/',
            'notes'       => 'Uses the same Meta app as Facebook. Enable the Instagram Graph API product and ensure the connected Instagram account is a Business or Creator account.',
        ],
        'linkedin' => [
            'label'       => 'LinkedIn',
            'icon'        => 'fa-brands fa-linkedin',
            'register_at' => 'https://www.linkedin.com/developers/apps',
            'notes'       => 'Create a LinkedIn app, request the Sign In with LinkedIn and Marketing Developer Platform products, and add the redirect URI under Auth → Authorized redirect URLs.',
        ],
        'google' => [
            'label'       => 'Google',
            'icon'        => 'fa-brands fa-google',
            'register_at' => 'https://console.cloud.google.com/apis/credentials',
            'notes'       => 'Create an OAuth 2.0 Client ID of type "Web application" in Google Cloud Console, add the redirect URI below under Authorized redirect URIs, and paste the Client ID + Client Secret here. The same client powers the "Continue with Google" web sign-in and native mobile Google sign-in.',
        ],
        'twitter' => [
            'label'       => 'X (Twitter)',
            'icon'        => 'fa-brands fa-x-twitter',
            'register_at' => 'https://developer.twitter.com/en/portal/projects-and-apps',
            'notes'       => 'In the X developer portal, set up User Authentication for your app with OAuth 2.0, type Web App, and paste the redirect URI as the Callback URL.',
        ],
        'pinterest' => [
            'label'       => 'Pinterest',
            'icon'        => 'fa-brands fa-pinterest',
            'register_at' => 'https://developers.pinterest.com/apps/',
            'notes'       => 'Create a Pinterest app and add the redirect URI under Redirect URIs. Request the user_accounts:read scope when submitting for review.',
        ],
        'tiktok' => [
            'label'       => 'TikTok',
            'icon'        => 'fa-brands fa-tiktok',
            'register_at' => 'https://developers.tiktok.com/apps',
            'notes'       => 'Create a TikTok app, add the Login Kit product, and register the redirect URI. TikTok uses CLIENT_KEY (not CLIENT_ID) — it is mapped to TIKTOK_CLIENT_KEY here.',
        ],
    ];

    public function index(SocialOAuthService $oauth)
    {
        $providers = [];
        foreach (SocialOAuthService::PROVIDERS as $key => $cfg) {
            $docs = self::PROVIDER_DOCS[$key] ?? [
                'label' => ucfirst($key), 'icon' => 'fa-solid fa-plug', 'register_at' => null, 'notes' => '',
            ];
            $providers[] = [
                'key'               => $key,
                'label'             => $docs['label'],
                'icon'              => $docs['icon'],
                'client_id_env'     => $cfg['client_id_env'],
                'client_secret_env' => $cfg['client_secret_env'],
                'configured'        => $oauth->isConfigured($key),
                'redirect_uri'      => $oauth->callbackUrl($key),
                'register_at'       => $docs['register_at'],
                'notes'             => $docs['notes'],
                // Admin-editable credential state (falls back to env vars).
                'admin_client_id'   => PlatformServiceSettings::socialOAuthAdminClientId($key),
                'has_admin_secret'  => PlatformServiceSettings::socialOAuthHasSecretAdminValue($key),
                'has_admin_value'   => PlatformServiceSettings::socialOAuthHasAdminValue($key),
                'env_client_id_set' => ! empty(env($cfg['client_id_env'])),
                'env_secret_set'    => ! empty(env($cfg['client_secret_env'])),
            ];
        }

        $configured   = collect($providers)->where('configured', true)->count();
        $unconfigured = count($providers) - $configured;

        return view('admin.social-oauth.index', compact('providers', 'configured', 'unconfigured'));
    }

    /**
     * Save (or clear) a provider's OAuth client id + secret. Stored in the
     * encrypted app_settings key/value store via PlatformServiceSettings and
     * used in preference to the env vars by SocialOAuthService. Leaving the
     * secret blank keeps the existing stored value; ticking "clear" removes
     * the admin-stored credentials so the env fallback applies again.
     */
    public function update(Request $request, string $provider)
    {
        abort_unless(isset(SocialOAuthService::PROVIDERS[$provider]), 404);

        $data = $request->validate([
            'client_id'     => ['nullable', 'string', 'max:512'],
            'client_secret' => ['nullable', 'string', 'max:512'],
            'clear'         => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('clear')) {
            PlatformServiceSettings::setSocialOAuthClientId($provider, null);
            PlatformServiceSettings::setSocialOAuthClientSecret($provider, null);

            return redirect()
                ->route('admin.social-oauth.index')
                ->with('success', ucfirst($provider) . ' OAuth credentials cleared. The server environment variables (if any) will be used instead.');
        }

        PlatformServiceSettings::setSocialOAuthClientId($provider, $data['client_id'] ?? null);

        // Only overwrite the secret when a new one is supplied — the field is
        // rendered masked/empty so an empty submit must preserve the stored value.
        $secret = trim((string) ($data['client_secret'] ?? ''));
        if ($secret !== '') {
            PlatformServiceSettings::setSocialOAuthClientSecret($provider, $secret);
        }

        return redirect()
            ->route('admin.social-oauth.index')
            ->with('success', ucfirst($provider) . ' OAuth credentials saved.');
    }
}
