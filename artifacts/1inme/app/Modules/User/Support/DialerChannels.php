<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\User;

/**
 * Single source of truth for the dialer's direct-action channels (call, SMS,
 * WhatsApp, Telegram, Signal, Viber) shared by the web + mobile dialer so the
 * one-tap channel row never drifts between surfaces.
 *
 * Each channel is a plain device/deep-link handoff that needs no Google
 * Contacts or any integration. Users pick which channels they actually use
 * (per-user preference stored in the `settings` JSON); the keypad, favourites,
 * frequent strip and recents channel rows only render the chosen channels.
 *
 * The former "WhatsApp call" action was removed: there is no public deep link
 * for a WhatsApp *call*, so it only ever opened the same wa.me chat as the
 * WhatsApp message button — a misleading duplicate. The WhatsApp channel is
 * now labelled as opening a chat.
 */
class DialerChannels
{
    /**
     * Catalog of selectable channels. `js` is the client-side URL builder
     * mode; `fa` is the Font Awesome class (web) and `feather` the Feather
     * icon name (mobile). `label` doubles as the button title/accessible label.
     *
     * @var array<string, array{label:string,short:string,color:string,fa:string,feather:string,js:string}>
     */
    private const CATALOG = [
        'call' => [
            'label'   => 'Call',
            'short'   => 'Call',
            'color'   => '#22c55e',
            'fa'      => 'fas fa-phone',
            'feather' => 'phone',
            'js'      => 'tel',
        ],
        'sms' => [
            'label'   => 'Text message',
            'short'   => 'Text',
            'color'   => '#38bdf8',
            'fa'      => 'fas fa-comment-sms',
            'feather' => 'message-square',
            'js'      => 'sms',
        ],
        'whatsapp' => [
            'label'   => 'Chat on WhatsApp',
            'short'   => 'WhatsApp',
            'color'   => '#25d366',
            'fa'      => 'fab fa-whatsapp',
            'feather' => 'message-circle',
            'js'      => 'wa',
        ],
        'telegram' => [
            'label'   => 'Open in Telegram',
            'short'   => 'Telegram',
            'color'   => '#3390ec',
            'fa'      => 'fab fa-telegram',
            'feather' => 'send',
            'js'      => 'tg',
        ],
        'signal' => [
            'label'   => 'Message on Signal',
            'short'   => 'Signal',
            'color'   => '#3a76f0',
            'fa'      => 'fab fa-signal-messenger',
            'feather' => 'shield',
            'js'      => 'signal',
        ],
        'viber' => [
            'label'   => 'Message on Viber',
            'short'   => 'Viber',
            'color'   => '#7360f2',
            'fa'      => 'fab fa-viber',
            'feather' => 'phone-forwarded',
            'js'      => 'viber',
        ],
    ];

    /**
     * Default enabled channels (order matters). Preserves the historical
     * dialer behaviour — call, SMS, WhatsApp, Telegram — minus the removed
     * "WhatsApp call" duplicate. Signal/Viber are opt-in.
     *
     * @var list<string>
     */
    private const DEFAULTS = ['call', 'sms', 'whatsapp', 'telegram'];

    /** Per-request memo of resolved preferences, keyed by user id. */
    private static array $memo = [];

    /** @return array<string, array{label:string,short:string,color:string,fa:string,feather:string,js:string}> */
    public static function catalog(): array
    {
        return self::CATALOG;
    }

    /** @return list<string> */
    public static function allKeys(): array
    {
        return array_keys(self::CATALOG);
    }

    /** @return list<string> */
    public static function defaults(): array
    {
        return self::DEFAULTS;
    }

    /**
     * Keep only known keys, de-duplicated and in the given order. An empty or
     * fully-unknown list falls back to the defaults so a surface is never left
     * with zero channels.
     *
     * @param  mixed  $keys
     * @return list<string>
     */
    public static function sanitize($keys): array
    {
        if (!is_array($keys)) {
            return self::DEFAULTS;
        }
        $out = [];
        foreach ($keys as $k) {
            if (!is_string($k)) continue;
            $k = trim($k);
            if (isset(self::CATALOG[$k]) && !in_array($k, $out, true)) {
                $out[] = $k;
            }
        }
        return $out === [] ? self::DEFAULTS : $out;
    }

    /**
     * Resolved, ordered enabled channel keys for a user (from the `settings`
     * JSON, falling back to the defaults). Memoized per request.
     *
     * @return list<string>
     */
    public static function forUser(?User $user): array
    {
        if (!$user) {
            return self::DEFAULTS;
        }
        if (array_key_exists($user->id, self::$memo)) {
            return self::$memo[$user->id];
        }
        $stored = $user->settings['dialer_channels'] ?? null;
        $resolved = $stored === null ? self::DEFAULTS : self::sanitize($stored);
        return self::$memo[$user->id] = $resolved;
    }

    /** Drop a memoized entry after the preference is saved. */
    public static function forget(int $userId): void
    {
        unset(self::$memo[$userId]);
    }

    /**
     * Enabled channels resolved to full catalog rows (with the key folded in),
     * ordered by the user's preference. Used to render the channel row.
     *
     * @return list<array{key:string,label:string,short:string,color:string,fa:string,feather:string,js:string}>
     */
    public static function enabledFor(?User $user): array
    {
        return array_map(
            fn (string $key) => ['key' => $key] + self::CATALOG[$key],
            self::forUser($user),
        );
    }

    /**
     * Client-facing catalog + selection payload for the mobile/web pickers.
     *
     * @return array{catalog:list<array{key:string,label:string,short:string,color:string,fa:string,feather:string,js:string}>,enabled:list<string>}
     */
    public static function payloadFor(?User $user): array
    {
        return [
            'catalog' => array_map(
                fn (string $key) => ['key' => $key] + self::CATALOG[$key],
                self::allKeys(),
            ),
            'enabled' => self::forUser($user),
        ];
    }
}
