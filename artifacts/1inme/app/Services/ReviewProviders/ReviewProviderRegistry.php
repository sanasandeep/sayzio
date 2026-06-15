<?php

namespace App\Services\ReviewProviders;

/**
 * Static registry describing every 3rd-party review provider 1INME can pull
 * reviews from. Each entry is a thin descriptor; the live API call lives in
 * the matching adapter. When a provider's credentials (env keys) are absent
 * the adapter runs in a transparent "preview" mode so the connect/sync flow
 * is fully demonstrable in dev without leaking faux credentials.
 *
 * Listed providers:
 *   - google     — Google Business Profile reviews
 *   - trustpilot — Trustpilot business reviews
 */
class ReviewProviderRegistry
{
    public const PROVIDERS = [
        'google' => [
            'slug'      => 'google',
            'name'      => 'Google',
            'icon'      => 'fab fa-google',
            'tint'      => '#4285F4',
            'short'     => 'Pull reviews from your Google Business Profile.',
            'ref_label' => 'Google Place ID / Location ID',
            'env_keys'  => ['GOOGLE_PLACES_API_KEY'],
            'docs_url'  => 'https://developers.google.com/my-business',
        ],
        'trustpilot' => [
            'slug'      => 'trustpilot',
            'name'      => 'Trustpilot',
            'icon'      => 'fas fa-star',
            'tint'      => '#00b67a',
            'short'     => 'Pull reviews from your Trustpilot business unit.',
            'ref_label' => 'Trustpilot Business Unit ID',
            'env_keys'  => ['TRUSTPILOT_API_KEY'],
            'docs_url'  => 'https://developers.trustpilot.com/',
        ],
    ];

    public static function all(): array
    {
        return self::PROVIDERS;
    }

    public static function get(string $slug): ?array
    {
        return self::PROVIDERS[$slug] ?? null;
    }

    public static function exists(string $slug): bool
    {
        return isset(self::PROVIDERS[$slug]);
    }

    public static function adapter(string $slug): ReviewSyncAdapter
    {
        $provider = self::get($slug);
        if (!$provider) abort(404, "Unknown review provider: {$slug}");

        return match ($slug) {
            'google'     => new Adapters\GoogleReviewsAdapter($provider),
            'trustpilot' => new Adapters\TrustpilotAdapter($provider),
        };
    }
}
