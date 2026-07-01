<?php

namespace App\Modules\Common\Support\FeatureStates;

use App\Modules\User\Support\ConnectedApps\ConnectedAppRegistry;
use App\Services\CreatorPayouts\PayoutProviderRegistry;
use App\Services\Integrations\PlatformServiceSettings;

/**
 * The catalogue of user-facing features/modules that participate in the
 * app-wide "Coming soon" system.
 *
 * Each entry declares:
 *  - label / icon / tint / blurb : how the feature is presented on the
 *    branded preview page and the "Soon" badge.
 *  - capabilities : the short "what you'll be able to do" bullet list shown
 *    on the preview page.
 *  - landing : the primary route name the sidebar item links to (used as the
 *    canonical target for the preview page and admin listing).
 *  - routes : route-name glob patterns that belong to this feature. When the
 *    feature resolves to "coming soon" every one of these routes is guarded
 *    and redirected to the branded preview page.
 *  - configured : an optional callable returning bool. It reuses the app's
 *    EXISTING readiness signals (integration/config connected?) to
 *    auto-detect whether a feature that an admin has enabled is actually
 *    wired up yet. `null` means the feature is config-independent — it is
 *    always "ready" unless an admin manually forces it to "coming soon".
 *  - admin_hint : optional {label, route} pointer an admin viewer follows to
 *    connect the missing integration.
 *
 * Adding a feature here is all that's required — the resolver, guard, badge,
 * preview page, admin toggle and mobile API all read from this single
 * catalogue so every feature behaves and looks identical.
 */
final class FeatureCatalog
{
    /** @return array<string,array<string,mixed>> keyed by feature key */
    public static function all(): array
    {
        return [
            'connected_apps' => [
                'label'        => 'Connected Apps',
                'icon'         => 'fa-plug-circle-bolt',
                'tint'         => '#22c55e',
                'blurb'        => 'Push new leads, subscribers and form submissions straight to your CRM, pull CRM contacts back into your account, and forward click events to your analytics — automatically.',
                'capabilities' => [
                    'Two-way CRM sync (Salesforce, HubSpot, Zoho)',
                    'Forward click & conversion events to Google Analytics 4',
                    'Field mapping between your data and each provider',
                    'Scheduled background sync with per-record counts',
                ],
                'landing'    => 'user.connected-apps.index',
                'routes'     => ['user.connected-apps.*'],
                'admin_hint' => ['label' => 'Set up integrations', 'route' => 'admin.integrations.index'],
                'configured' => [self::class, 'connectedAppsConfigured'],
            ],

            'monetization' => [
                'label'        => 'Monetization',
                'icon'         => 'fa-gem',
                'tint'         => '#a855f7',
                'blurb'        => 'Turn your audience into revenue with paid pages, subscriptions and creator earnings — all tracked in one place.',
                'capabilities' => [
                    'Sell paid pages and gated content',
                    'Track earnings by source in one dashboard',
                    'Manage subscribers and one-off purchases',
                ],
                'landing'    => 'user.monetization.earnings',
                'routes'     => ['user.monetization.*'],
                'admin_hint' => ['label' => 'Connect a payment provider', 'route' => 'admin.integrations.index'],
                // Needs a platform payment gateway to actually collect money.
                'configured' => [self::class, 'paymentProviderConfigured'],
            ],

            'payouts' => [
                'label'        => 'Earnings & Payouts',
                'icon'         => 'fa-sack-dollar',
                'tint'         => '#f59e0b',
                'blurb'        => 'Connect a payout provider to get paid your creator earnings with zero platform fees.',
                'capabilities' => [
                    'Hosted onboarding with Stripe, PayPal, Razorpay and more',
                    '0% platform fee on your earnings',
                    'Track balances and payout history',
                ],
                'landing'    => 'user.payouts.show',
                'routes'     => ['user.payouts.*', 'user.adult-content.*'],
                'admin_hint' => ['label' => 'Connect a payout provider', 'route' => 'admin.integrations.index'],
                // Ready once any payout provider's platform credentials are set.
                'configured' => [self::class, 'paymentProviderConfigured'],
            ],

            'dialer' => [
                'label'        => 'Dialer',
                'icon'         => 'fa-phone',
                'tint'         => '#0ea5e9',
                'blurb'        => 'A built-in number pad and call history that resolves phone numbers to the right biolink.',
                'capabilities' => [
                    'Number-pad dialer with recents and favourites',
                    'Phone → biolink resolution with caller ID',
                    'Silent biolink auto-attach',
                ],
                'landing'    => 'user.dialer.index',
                'routes'     => ['user.dialer.*'],
                'admin_hint' => ['label' => 'Connect Google Contacts', 'route' => 'admin.integrations.index'],
                // Caller-ID / contacts sync needs Google Contacts OAuth wired up.
                'configured' => [self::class, 'dialerConfigured'],
            ],

            'social_proofs' => [
                'label'        => 'Buzz',
                'icon'         => 'fa-bolt',
                'tint'         => '#ec4899',
                'blurb'        => 'Embeddable social-proof notifications that boost conversions on your pages.',
                'capabilities' => [
                    'Seven notification widget types',
                    'Targeting rules and design controls',
                    'Drop-in embed on any biolink',
                ],
                'landing'    => 'user.social-proofs.index',
                'routes'     => ['user.social-proofs.*'],
                // Config-independent: works standalone (no external integration).
                // Only enters "coming soon" via an admin forced override.
                'configured' => null,
            ],

            'pixels' => [
                'label'        => 'Pixel',
                'icon'         => 'fa-bullseye',
                'tint'         => '#ef4444',
                'blurb'        => 'Fire tracking pixels for every major ad platform on your links and pages.',
                'capabilities' => [
                    'Facebook, Google, TikTok, LinkedIn and more',
                    'Per-link and account-wide pixels',
                    'Retarget visitors across platforms',
                ],
                'landing'    => 'user.pixels.index',
                'routes'     => ['user.pixels.*'],
                // Config-independent: users enter their own pixel IDs (no
                // platform integration). Only "coming soon" via admin override.
                'configured' => null,
            ],

            'integrations' => [
                'label'        => 'Integrations',
                'icon'         => 'fa-plug',
                'tint'         => '#6366f1',
                'blurb'        => 'Connect the tools you already use to automate your workflow.',
                'capabilities' => [
                    'Webhooks and third-party app connections',
                    'Automate lead and event delivery',
                ],
                'landing'    => 'user.integrations.index',
                'routes'     => ['user.integrations.*'],
                'admin_hint' => ['label' => 'Set up integrations', 'route' => 'admin.integrations.index'],
                // Ready once any third-party provider is wired at the platform.
                'configured' => [self::class, 'connectedAppsConfigured'],
            ],

            'domains' => [
                'label'        => 'Domains',
                'icon'         => 'fa-globe',
                'tint'         => '#14b8a6',
                'blurb'        => 'Bring your own domain and serve your links and pages under your brand.',
                'capabilities' => [
                    'Add and DNS-verify your own domains',
                    'Use shared branded domains',
                    'Pick the default host when creating links',
                ],
                'landing'    => 'user.domains.index',
                'routes'     => ['user.domains.*'],
                // Config-independent: users add & DNS-verify their own domains
                // (no external integration). Only "coming soon" via admin override.
                'configured' => null,
            ],
        ];
    }

    /**
     * Auto-detect readiness for Connected Apps by reusing the existing
     * ConnectedAppRegistry platform-configuration signal: the area is only
     * "ready" once an admin has wired up at least one CRM/analytics provider.
     */
    public static function connectedAppsConfigured(): bool
    {
        foreach (array_keys(ConnectedAppRegistry::all()) as $key) {
            if (ConnectedAppRegistry::isPlatformConfigured($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Auto-detect readiness for the Dialer by reusing the existing Google
     * Contacts platform-configuration signal — caller ID and contacts sync,
     * the dialer's headline capabilities, need Google Contacts OAuth wired up.
     */
    public static function dialerConfigured(): bool
    {
        return PlatformServiceSettings::googleContactsConfigured();
    }

    /**
     * Auto-detect readiness for Monetization / Payouts by reusing the existing
     * payout-provider credential signal: the area is only "ready" once an admin
     * has configured the platform credentials for at least one payment/payout
     * provider (Stripe, PayPal, Razorpay, CCBill, Segpay). Mirrors the same
     * check the payout adapters use for their "preview mode when keys absent".
     */
    public static function paymentProviderConfigured(): bool
    {
        foreach (array_keys(PayoutProviderRegistry::all()) as $slug) {
            if (PayoutProviderRegistry::adapter($slug)->credentialsConfigured()) {
                return true;
            }
        }

        return false;
    }
}
