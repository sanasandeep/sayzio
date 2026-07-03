<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\User;

/**
 * Task #3497 — lets a creator control what a *stranger* (someone who hasn't
 * already saved them as a contact) can see about them through the dialer's
 * caller-ID lookup and the universal finder: their phone number, email,
 * exact location, and socials, plus a fine-grained per-channel un-share list
 * on top of those categories.
 *
 * Preferences live on `$user->settings['contact_privacy']` (same JSON-column
 * pattern as {@see DialerChannels}). Each of the four category flags is
 * tri-state: `null` means the creator hasn't made an explicit choice yet, and
 * is treated as **shared** — there is no forced default, but until a creator
 * actively opts out the pre-feature behaviour (everything visible) holds, so
 * existing creators see no surprise change. Blocked/suspended gating and the
 * "already saved" / "looking up yourself" exemptions are handled by the
 * caller (see {@see DialerIdentity}) — this class only knows how to read/
 * write prefs and apply them to an already-resolved identity payload.
 */
class ContactPrivacy
{
    public const FIELDS = ['phone', 'email', 'location', 'socials'];

    /** Channel `type`s sourced from the dialed number itself (gated by `phone`). */
    private const PHONE_CHANNEL_TYPES = ['phone', 'sms', 'whatsapp', 'facetime_audio', 'facetime_video'];

    /** Per-request memo of resolved preferences, keyed by user id. */
    private static array $memo = [];

    /**
     * Resolved preferences for a user.
     *
     * @return array{share_phone: ?bool, share_email: ?bool, share_location: ?bool, share_socials: ?bool, hidden_channels: list<string>}
     */
    public static function forUser(?User $user): array
    {
        if (!$user) {
            return self::defaults();
        }
        if (array_key_exists($user->id, self::$memo)) {
            return self::$memo[$user->id];
        }

        $stored = $user->settings['contact_privacy'] ?? null;
        $stored = is_array($stored) ? $stored : [];

        $resolved = [
            'share_phone'     => self::tri($stored['share_phone'] ?? null),
            'share_email'     => self::tri($stored['share_email'] ?? null),
            'share_location'  => self::tri($stored['share_location'] ?? null),
            'share_socials'   => self::tri($stored['share_socials'] ?? null),
            'hidden_channels' => self::sanitizeHiddenChannels($stored['hidden_channels'] ?? []),
            // Whether the creator has ever visited the privacy settings /
            // made an explicit choice — drives the one-time onboarding nudge.
            'configured_at'   => $stored['configured_at'] ?? null,
        ];

        return self::$memo[$user->id] = $resolved;
    }

    /** @return array{share_phone: null, share_email: null, share_location: null, share_socials: null, hidden_channels: list<string>, configured_at: null} */
    public static function defaults(): array
    {
        return [
            'share_phone'     => null,
            'share_email'     => null,
            'share_location'  => null,
            'share_socials'   => null,
            'hidden_channels' => [],
            'configured_at'   => null,
        ];
    }

    /** Drop a memoized entry after preferences are saved. */
    public static function forget(int $userId): void
    {
        unset(self::$memo[$userId]);
    }

    /**
     * Persist a set of preference changes for the given user. Any field not
     * present in `$input` is left untouched. `null` explicitly clears a
     * field back to "not chosen" (shown by default).
     *
     * @param array{share_phone?: mixed, share_email?: mixed, share_location?: mixed, share_socials?: mixed, hidden_channels?: mixed} $input
     * @return array the resolved preferences after saving
     */
    public static function updateFor(User $user, array $input): array
    {
        $settings = $user->settings ?? [];
        $current = is_array($settings['contact_privacy'] ?? null) ? $settings['contact_privacy'] : [];

        foreach (['share_phone', 'share_email', 'share_location', 'share_socials'] as $field) {
            if (array_key_exists($field, $input)) {
                $current[$field] = self::tri($input[$field]);
            }
        }

        if (array_key_exists('hidden_channels', $input)) {
            $current['hidden_channels'] = self::sanitizeHiddenChannels($input['hidden_channels']);
        }

        $current['configured_at'] = $current['configured_at'] ?? now()->toIso8601String();

        $settings['contact_privacy'] = $current;
        $user->forceFill(['settings' => $settings])->save();
        self::forget($user->id);

        return self::forUser($user->fresh());
    }

    /** Mark that the creator has seen/completed the onboarding privacy step, without changing any choice. */
    public static function markConfigured(User $user): void
    {
        $settings = $user->settings ?? [];
        $current = is_array($settings['contact_privacy'] ?? null) ? $settings['contact_privacy'] : [];
        if (!empty($current['configured_at'] ?? null)) {
            return;
        }
        $current['configured_at'] = now()->toIso8601String();
        $settings['contact_privacy'] = $current;
        $user->forceFill(['settings' => $settings])->save();
        self::forget($user->id);
    }

    /** A stable identity key for a channel/social row, used for per-channel un-share. */
    public static function channelKey(string $kind, string $type, string $identifier): string
    {
        return strtolower($kind) . ':' . strtolower(trim($type)) . ':' . strtolower(trim($identifier));
    }

    /**
     * Apply this creator's privacy preferences to an already-resolved
     * {@see DialerIdentity::payload()} result. No-op when the viewer is the
     * creator themself or has already saved them as a contact — those two
     * cases always see everything, per spec.
     *
     * @param array $payload shape produced by DialerIdentity::payload()
     */
    public static function applyToPayload(User $creator, array $payload, bool $isSelf, bool $isSaved): array
    {
        if ($isSelf || $isSaved) {
            return $payload;
        }

        $prefs = self::forUser($creator);
        $channels = $payload['channels'] ?? [];
        $socials = $payload['socials'] ?? [];

        if ($prefs['share_phone'] === false) {
            $payload['number'] = '';
            $payload['number_e164'] = null;
            $channels = array_values(array_filter(
                $channels,
                fn (array $c) => ($c['source'] ?? '') !== 'number'
            ));
        }

        if ($prefs['share_email'] === false) {
            $channels = array_values(array_filter(
                $channels,
                fn (array $c) => ($c['type'] ?? '') !== 'email'
            ));
        }

        if ($prefs['share_location'] === false) {
            $payload['locations'] = [];
        }

        if ($prefs['share_socials'] === false) {
            $socials = [];
            $channels = array_values(array_filter(
                $channels,
                fn (array $c) => ($c['source'] ?? '') !== 'biolink'
            ));
        }

        $hidden = $prefs['hidden_channels'];
        if (!empty($hidden)) {
            $socials = array_values(array_filter(
                $socials,
                fn (array $s) => !in_array(self::channelKey('social', $s['platform'] ?? '', $s['url'] ?? ''), $hidden, true)
            ));
            $channels = array_values(array_filter(
                $channels,
                fn (array $c) => !in_array(self::channelKey('channel', $c['type'] ?? '', $c['url'] ?? ($c['value'] ?? '')), $hidden, true)
            ));
        }

        $payload['channels'] = $channels;
        $payload['socials'] = $socials;

        return $payload;
    }

    /**
     * Un-shareable channel/social candidates for the settings picker — the
     * creator's own current biolink channels + socials, each tagged with the
     * key used by `hidden_channels` and whether it's currently hidden.
     *
     * @return array{socials: list<array>, channels: list<array>}
     */
    public static function shareableCandidatesFor(User $user): array
    {
        $bio = \App\Modules\User\Models\Link::where('user_id', $user->id)
            ->whereIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        if (!$bio) {
            return ['socials' => [], 'channels' => []];
        }

        $extracted = DialerIdentity::extractFromBiolink($bio);
        $hidden = self::forUser($user)['hidden_channels'];

        $socials = array_map(function (array $s) use ($hidden) {
            $key = self::channelKey('social', $s['platform'] ?? '', $s['url'] ?? '');
            return $s + ['key' => $key, 'hidden' => in_array($key, $hidden, true)];
        }, $extracted['socials']);

        $channels = array_map(function (array $c) use ($hidden) {
            $key = self::channelKey('channel', $c['type'] ?? '', $c['url'] ?? ($c['value'] ?? ''));
            return $c + ['key' => $key, 'hidden' => in_array($key, $hidden, true)];
        }, $extracted['channels']);

        return ['socials' => $socials, 'channels' => $channels];
    }

    /** Normalize an incoming value to a tri-state bool|null. */
    private static function tri($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? null;
    }

    /** @return list<string> */
    private static function sanitizeHiddenChannels($value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $key) {
            if (!is_string($key)) continue;
            $key = trim($key);
            if ($key === '' || in_array($key, $out, true)) continue;
            $out[] = $key;
        }
        return $out;
    }
}
