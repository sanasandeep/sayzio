<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * Serves the iOS "apple-app-site-association" and Android
 * "assetlinks.json" files used to claim https://<host>/* URLs for the
 * Sayzio mobile app. When the app is installed, biolink/short URLs open
 * directly in the app; otherwise the OS falls back to the website
 * automatically. The bundle/app IDs are read from config/services.php
 * so they can be overridden per environment via env vars without code
 * changes.
 *
 * These files are host-agnostic: they are served verbatim for whatever
 * host requests `/.well-known/...`, so every platform brand domain
 * (the primary sayzio.app and the legacy 1in.me, plus their www. hosts)
 * is claimed automatically once that host is listed in the mobile app's
 * `app.json` (ios.associatedDomains / android.intentFilters). No per-host
 * code change is needed when adding a brand domain.
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
        //
        // The mobile app should ONLY claim biolink alias URLs (single-segment
        // paths like `https://1inme.com/{alias}`). Everything else — the
        // dashboard, admin, well-known, marketing pages, sub-paths of an
        // alias such as `/{alias}/rsvp` — must keep opening in the browser.
        // First-match-wins, so excludes come first, then the catch-all
        // single-segment match.
        $reservedSegments = [
            '/', // homepage
            '/user/*', '/admin/*', '/.well-known/*', '/sanctum/*', '/api/*',
            '/webhooks/*', '/storage/*', '/qr/*', '/sp/*', '/f/*', '/viewer/*',
            '/admin-assets/*', '/feed', '/discovery', '/discovery/*',
            '/creators', '/creators/*',
            '/login', '/register', '/logout',
            '/features', '/how-it-works', '/about', '/contact', '/faqs',
            '/terms', '/refunds', '/privacy', '/gdpr', '/cookies',
            '/creators-feed', '/workspace-team', '/buzz', '/docs', '/newsletter',
        ];
        $components = array_map(
            fn ($p) => ['/' => $p, 'exclude' => true],
            $reservedSegments
        );
        // Exclude every multi-segment path: `/foo/bar`, `/foo/bar/baz`, etc.
        // This prevents `/{alias}/rsvp`, `/{alias}/download`, etc. from
        // opening in the app while still allowing the bare alias to.
        $components[] = ['/' => '/*/*', 'exclude' => true];
        // Final catch-all: claim any remaining single-segment path — these
        // are the biolink aliases the mobile app should open natively.
        $components[] = ['/' => '/*'];

        $payload = [
            'applinks' => [
                'details' => [[
                    'appIDs'     => [$appId],
                    'components' => $components,
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
