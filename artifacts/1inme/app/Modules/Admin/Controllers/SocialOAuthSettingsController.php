<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Services\SocialFollowers\SocialOAuthService;

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
            ];
        }

        $configured   = collect($providers)->where('configured', true)->count();
        $unconfigured = count($providers) - $configured;

        return view('admin.social-oauth.index', compact('providers', 'configured', 'unconfigured'));
    }
}
