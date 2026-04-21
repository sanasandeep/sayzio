<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * Serves the iOS "apple-app-site-association" and Android
 * "assetlinks.json" files used to claim https://<host>/* URLs for the
 * 1INME mobile app. When the app is installed, biolink/short URLs open
 * directly in the app; otherwise the OS falls back to the website
 * automatically. The bundle/app IDs are read from config/services.php
 * so they can be overridden per environment via env vars without code
 * changes.
 */
class UniversalLinksController extends Controller
{
    public function appleAppSiteAssociation(): Response
    {
        $teamId   = config('services.apple.team_id', env('APPLE_TEAM_ID', 'TEAMIDXXXX'));
        $bundleId = config('services.apple.bundle_id', env('APPLE_BUNDLE_ID', 'com.oneinme.app'));
        $appId = $teamId . '.' . $bundleId;

        // We claim every path EXCEPT the dashboard, admin, well-known, and
        // a few legal / marketing pages — those should always open in the
        // browser even when the app is installed.
        // Modern AASA shape (iOS 13+): use `components` exclusively.
        // Mixing the legacy `paths` array on the same details object can
        // confuse some parsers, so we omit it.
        $payload = [
            'applinks' => [
                'details' => [[
                    'appIDs' => [$appId],
                    'components' => [
                        ['/' => '/user/*',         'exclude' => true],
                        ['/' => '/admin/*',        'exclude' => true],
                        ['/' => '/.well-known/*', 'exclude' => true],
                        ['/' => '/sanctum/*',     'exclude' => true],
                        ['/' => '/api/*',         'exclude' => true],
                        ['/' => '/webhooks/*',    'exclude' => true],
                        ['/' => '/storage/*',     'exclude' => true],
                        ['/' => '*'],
                    ],
                ]],
            ],
            'webcredentials' => [
                'apps' => [$appId],
            ],
        ];

        return response($payload, 200)
            ->header('Content-Type', 'application/json')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function androidAssetLinks(): Response
    {
        $package    = config('services.android.package', env('ANDROID_PACKAGE_NAME', 'com.oneinme.app'));
        $sha256List = array_filter(array_map('trim', explode(',', (string) env('ANDROID_SHA256_FINGERPRINTS', ''))));

        // When no fingerprint is configured (dev), still serve a
        // syntactically valid file with a placeholder so the app can
        // verify intent registration and tooling like the assetlinks
        // tester gives an actionable "fingerprint mismatch" message
        // rather than 404.
        if (empty($sha256List)) {
            $sha256List = ['XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX:XX'];
        }

        $payload = [[
            'relation' => ['delegate_permission/common.handle_all_urls', 'delegate_permission/common.get_login_creds'],
            'target' => [
                'namespace'                => 'android_app',
                'package_name'             => $package,
                'sha256_cert_fingerprints' => array_values($sha256List),
            ],
        ]];

        return response($payload, 200)
            ->header('Content-Type', 'application/json')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
