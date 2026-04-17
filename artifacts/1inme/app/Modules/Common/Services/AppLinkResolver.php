<?php

namespace App\Modules\Common\Services;

/**
 * Resolves a destination URL to a known mobile app and returns the
 * matching deep-link / universal-link metadata used by the redirect
 * interstitial so that, on a phone, the link opens the native app
 * instead of the mobile browser.
 *
 * The resolver is intentionally a pure static class: no DB, no IO,
 * no Laravel container deps. Easy to unit-test, easy to call from
 * controllers and Blade.
 */
class AppLinkResolver
{
    /**
     * Resolve a URL to its app metadata, or null if no known app
     * matches (in which case callers should just open the web URL).
     *
     * Return shape:
     *  - key:           short stable identifier (e.g. "youtube")
     *  - name:          human-readable label
     *  - icon:          Font Awesome class for inline display
     *  - ios:           full URL with iOS custom scheme, or null
     *  - android:       full Android `intent://` URL, or null
     *  - web:           the original URL, normalised
     */
    public static function resolve(?string $url): ?array
    {
        if (!$url) return null;
        $parts = @parse_url($url);
        if (!$parts || empty($parts['host'])) return null;

        // Hard scheme gate — never resolve anything that isn't http(s).
        // Refuses javascript:, data:, file:, ftp:, custom URI schemes, etc.,
        // which must never make it into the interstitial as `href` or
        // `window.location` targets.
        $scheme = strtolower($parts['scheme'] ?? '');
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }

        $host = strtolower(preg_replace('/^www\./', '', $parts['host']));
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        // YouTube — supports youtu.be short links + youtube.com/watch + Shorts.
        if (in_array($host, ['youtube.com', 'm.youtube.com', 'youtu.be'], true)) {
            return [
                'key' => 'youtube',
                'name' => 'YouTube',
                'icon' => 'fa-brands fa-youtube',
                'ios' => $url, // YouTube respects http(s) universal links
                'android' => self::androidIntent($host . $path . $query, 'com.google.android.youtube'),
                'web' => $url,
            ];
        }

        if ($host === 'instagram.com' || $host === 'instagr.am') {
            return [
                'key' => 'instagram',
                'name' => 'Instagram',
                'icon' => 'fa-brands fa-instagram',
                'ios' => 'instagram://media?url=' . rawurlencode($url),
                'android' => self::androidIntent(ltrim($host . $path, '/'), 'com.instagram.android'),
                'web' => $url,
            ];
        }

        if ($host === 'tiktok.com' || $host === 'vm.tiktok.com') {
            return [
                'key' => 'tiktok',
                'name' => 'TikTok',
                'icon' => 'fa-brands fa-tiktok',
                'ios' => $url,
                'android' => self::androidIntent($host . $path . $query, 'com.zhiliaoapp.musically'),
                'web' => $url,
            ];
        }

        if (in_array($host, ['twitter.com', 'x.com', 'mobile.twitter.com'], true)) {
            return [
                'key' => 'twitter',
                'name' => 'X (Twitter)',
                'icon' => 'fa-brands fa-x-twitter',
                'ios' => 'twitter://' . ltrim($path, '/'),
                'android' => self::androidIntent($host . $path . $query, 'com.twitter.android'),
                'web' => $url,
            ];
        }

        if (in_array($host, ['facebook.com', 'fb.com', 'm.facebook.com'], true)) {
            return [
                'key' => 'facebook',
                'name' => 'Facebook',
                'icon' => 'fa-brands fa-facebook',
                'ios' => 'fb://facewebmodal/f?href=' . rawurlencode($url),
                'android' => self::androidIntent($host . $path . $query, 'com.facebook.katana'),
                'web' => $url,
            ];
        }

        if (in_array($host, ['open.spotify.com', 'spotify.com'], true)) {
            return [
                'key' => 'spotify',
                'name' => 'Spotify',
                'icon' => 'fa-brands fa-spotify',
                'ios' => 'spotify:' . str_replace('/', ':', trim($path, '/')),
                'android' => self::androidIntent($host . $path . $query, 'com.spotify.music'),
                'web' => $url,
            ];
        }

        if ($host === 'linkedin.com') {
            return [
                'key' => 'linkedin',
                'name' => 'LinkedIn',
                'icon' => 'fa-brands fa-linkedin',
                'ios' => 'linkedin://' . ltrim($path, '/'),
                'android' => self::androidIntent($host . $path . $query, 'com.linkedin.android'),
                'web' => $url,
            ];
        }

        if ($host === 'reddit.com' || $host === 'old.reddit.com' || $host === 'redd.it') {
            return [
                'key' => 'reddit',
                'name' => 'Reddit',
                'icon' => 'fa-brands fa-reddit',
                'ios' => 'reddit://' . ltrim($path, '/'),
                'android' => self::androidIntent($host . $path . $query, 'com.reddit.frontpage'),
                'web' => $url,
            ];
        }

        if ($host === 'pinterest.com' || $host === 'pin.it') {
            return [
                'key' => 'pinterest',
                'name' => 'Pinterest',
                'icon' => 'fa-brands fa-pinterest',
                'ios' => 'pinterest://' . ltrim($path, '/'),
                'android' => self::androidIntent($host . $path . $query, 'com.pinterest'),
                'web' => $url,
            ];
        }

        if ($host === 'snapchat.com') {
            return [
                'key' => 'snapchat',
                'name' => 'Snapchat',
                'icon' => 'fa-brands fa-snapchat',
                'ios' => 'snapchat://' . ltrim($path, '/'),
                'android' => self::androidIntent($host . $path . $query, 'com.snapchat.android'),
                'web' => $url,
            ];
        }

        if (in_array($host, ['whatsapp.com', 'wa.me', 'api.whatsapp.com'], true)) {
            return [
                'key' => 'whatsapp',
                'name' => 'WhatsApp',
                'icon' => 'fa-brands fa-whatsapp',
                'ios' => 'whatsapp://send?text=' . rawurlencode($url),
                'android' => self::androidIntent($host . $path . $query, 'com.whatsapp'),
                'web' => $url,
            ];
        }

        if (in_array($host, ['t.me', 'telegram.me', 'telegram.org'], true)) {
            return [
                'key' => 'telegram',
                'name' => 'Telegram',
                'icon' => 'fa-brands fa-telegram',
                'ios' => 'tg://resolve?domain=' . trim($path, '/'),
                'android' => self::androidIntent($host . $path . $query, 'org.telegram.messenger'),
                'web' => $url,
            ];
        }

        if ($host === 'threads.net') {
            return [
                'key' => 'threads',
                'name' => 'Threads',
                'icon' => 'fa-brands fa-threads',
                'ios' => 'barcelona://' . ltrim($path, '/'),
                'android' => self::androidIntent($host . $path . $query, 'com.instagram.barcelona'),
                'web' => $url,
            ];
        }

        if (in_array($host, ['maps.google.com', 'google.com', 'goo.gl', 'maps.app.goo.gl'], true)
            && (str_contains($path, '/maps') || $host === 'maps.app.goo.gl' || $host === 'maps.google.com')) {
            return [
                'key' => 'google_maps',
                'name' => 'Google Maps',
                'icon' => 'fa-solid fa-location-dot',
                'ios' => 'comgooglemaps://?q=' . rawurlencode($url),
                'android' => self::androidIntent($host . $path . $query, 'com.google.android.apps.maps'),
                'web' => $url,
            ];
        }

        if ($host === 'music.apple.com' || $host === 'itunes.apple.com') {
            return [
                'key' => 'apple_music',
                'name' => 'Apple Music',
                'icon' => 'fa-brands fa-apple',
                'ios' => 'music://' . $host . $path . $query,
                'android' => self::androidIntent($host . $path . $query, 'com.apple.android.music'),
                'web' => $url,
            ];
        }

        if ($host === 'twitch.tv') {
            return [
                'key' => 'twitch',
                'name' => 'Twitch',
                'icon' => 'fa-brands fa-twitch',
                'ios' => 'twitch://' . ltrim($path, '/'),
                'android' => self::androidIntent($host . $path . $query, 'tv.twitch.android.app'),
                'web' => $url,
            ];
        }

        return null;
    }

    /**
     * Build an Android `intent://` URL that opens the given package
     * with the original https URL as the data, falling back to the
     * Play Store if the app is not installed (S.browser_fallback_url).
     */
    private static function androidIntent(string $hostAndPath, string $package): string
    {
        $hostAndPath = ltrim($hostAndPath, '/');
        $fallback = 'https://play.google.com/store/apps/details?id=' . $package;
        return 'intent://' . $hostAndPath
            . '#Intent;scheme=https;package=' . $package
            . ';S.browser_fallback_url=' . rawurlencode($fallback)
            . ';end';
    }
}
