<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\AppSetting;

/**
 * Admin-authored public announcement banners ("public notify"), targeted
 * independently to four audiences and rendered across the app's public, user
 * and marketing surfaces.
 *
 * Storage: a single `public_announcements` key in the app-settings store
 * holding one normalized row per audience. Each row carries its own enabled
 * flag, message text, optional call-to-action link, and a monotonically
 * increasing `version`. The version is bumped whenever the visible content
 * changes on save; visitor-side dismissals are keyed by (audience, version)
 * so editing a message automatically re-shows it to everyone who dismissed
 * the previous wording, while a no-op save keeps it dismissed.
 *
 * Audience semantics:
 *  - marketing       → marketing/public pages (Laravel site + React site), all visitors
 *  - guests          → marketing/public pages, logged-OUT visitors only
 *  - users           → marketing/public pages, logged-IN visitors only
 *  - user_dashboard  → the logged-in user dashboard layout
 */
class PublicAnnouncements
{
    public const STORE_KEY = 'public_announcements';

    /** Audience id => human label (drives the admin form + validation). */
    public const AUDIENCES = [
        'marketing'      => 'Marketing / public pages',
        'guests'         => 'Guests only (logged-out visitors)',
        'users'          => 'Logged-in users (on public pages)',
        'user_dashboard' => 'User dashboard',
    ];

    /**
     * All four audience rows, normalized, keyed by audience id. Always returns
     * every audience (disabled/empty rows included) so the admin form can bind
     * to a stable shape.
     */
    public static function all(): array
    {
        $raw = (array) AppSetting::get(self::STORE_KEY, []);

        $out = [];
        foreach (array_keys(self::AUDIENCES) as $audience) {
            $out[$audience] = self::normalizeRow($audience, $raw[$audience] ?? []);
        }

        return $out;
    }

    /**
     * Active (enabled + non-empty) announcements to render on a given surface,
     * honoring the visitor's auth state. Returns a list of presentation-ready
     * rows.
     *
     * @param 'site'|'dashboard' $surface
     */
    public static function forSurface(string $surface, bool $authenticated): array
    {
        $all = self::all();
        $result = [];

        foreach ($all as $row) {
            if (!self::isActive($row)) {
                continue;
            }
            if (!self::appliesTo($row['audience'], $surface, $authenticated)) {
                continue;
            }
            $result[] = $row;
        }

        return $result;
    }

    /**
     * Announcements exposed over the CORS-open public feed for the standalone
     * React marketing site. That site has no auth, so its visitors are treated
     * as guests: it receives the `marketing` (everyone) and `guests` rows.
     */
    public static function publicFeed(): array
    {
        $all = self::all();
        $result = [];

        foreach (['marketing', 'guests'] as $audience) {
            $row = $all[$audience] ?? null;
            if ($row && self::isActive($row)) {
                $result[] = [
                    'audience'  => $row['audience'],
                    'message'   => $row['message'],
                    'linkUrl'   => $row['link_url'],
                    'linkLabel' => $row['link_label'],
                    'version'   => $row['version'],
                ];
            }
        }

        return $result;
    }

    /**
     * Validate, normalize and persist the admin form payload. Bumps a row's
     * version when its visible content (message + link) changed versus what is
     * stored, so prior dismissals reset only when the wording actually changed.
     *
     * @param array<string,mixed> $input keyed by audience id => [enabled, message, link_url, link_label]
     */
    public static function save(array $input): void
    {
        $existing = self::all();
        $store = [];

        foreach (array_keys(self::AUDIENCES) as $audience) {
            $row = (array) ($input[$audience] ?? []);
            $message   = trim((string) ($row['message'] ?? ''));
            $linkUrl   = trim((string) ($row['link_url'] ?? ''));
            $linkLabel = trim((string) ($row['link_label'] ?? ''));
            $enabled   = (bool) ($row['enabled'] ?? false);

            if ($linkUrl !== '' && !preg_match('#^https?://#i', $linkUrl)) {
                $linkUrl = '';
            }
            if ($linkUrl === '') {
                $linkLabel = '';
            }

            $prev = $existing[$audience];
            $contentChanged = $message !== $prev['message']
                || $linkUrl !== $prev['link_url']
                || $linkLabel !== $prev['link_label'];

            $version = (int) $prev['version'];
            if ($contentChanged) {
                $version = max(1, $version + 1);
            } elseif ($version < 1) {
                $version = 1;
            }

            $store[$audience] = [
                'enabled'    => $enabled,
                'message'    => $message,
                'link_url'   => $linkUrl,
                'link_label' => $linkLabel,
                'version'    => $version,
            ];
        }

        AppSetting::put(self::STORE_KEY, $store);
    }

    private static function isActive(array $row): bool
    {
        return $row['enabled'] && $row['message'] !== '';
    }

    /**
     * @param 'site'|'dashboard' $surface
     */
    private static function appliesTo(string $audience, string $surface, bool $authenticated): bool
    {
        if ($surface === 'dashboard') {
            return $audience === 'user_dashboard';
        }

        // 'site' — Laravel public/marketing pages.
        return match ($audience) {
            'marketing' => true,
            'guests'    => !$authenticated,
            'users'     => $authenticated,
            default     => false,
        };
    }

    private static function normalizeRow(string $audience, $raw): array
    {
        $raw = (array) $raw;
        $linkUrl = trim((string) ($raw['link_url'] ?? ''));
        if ($linkUrl !== '' && !preg_match('#^https?://#i', $linkUrl)) {
            $linkUrl = '';
        }
        $linkLabel = $linkUrl === '' ? '' : trim((string) ($raw['link_label'] ?? ''));

        return [
            'audience'   => $audience,
            'enabled'    => (bool) ($raw['enabled'] ?? false),
            'message'    => trim((string) ($raw['message'] ?? '')),
            'link_url'   => $linkUrl,
            'link_label' => $linkLabel,
            'version'    => max(1, (int) ($raw['version'] ?? 1)),
        ];
    }
}
