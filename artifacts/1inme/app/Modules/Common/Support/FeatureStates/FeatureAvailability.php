<?php

namespace App\Modules\Common\Support\FeatureStates;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\User\Models\FeatureNotifyInterest;
use Illuminate\Support\Str;

/**
 * Single reusable resolver for the app-wide "Coming soon" feature-state
 * system. Every surface — the sidebar badge, the route guard, the branded
 * preview page, the admin override screen and the mobile API — reads its
 * state from here so the behaviour and look stay identical everywhere.
 *
 * A feature resolves to one of:
 *  - ready                         : available, behaves exactly as before.
 *  - coming_soon (reason=auto)     : the feature is enabled/exposed but its
 *                                    integration/config isn't connected yet
 *                                    (auto-detected via the catalogue's
 *                                    `configured` signal).
 *  - coming_soon (reason=forced)   : an admin manually marked it coming soon,
 *                                    even if it would otherwise be ready.
 *
 * "Enabled" is already handled by the app's existing plan/capability gating:
 * a feature's nav item and routes are only exposed to a user when their plan
 * allows it, so this resolver never needs to re-check enablement. It layers on
 * top of that, distinguishing — among already-enabled features — those that
 * are configured (ready) from those that are enabled-but-not-connected
 * (coming_soon:auto), plus the admin's explicit forced override.
 *
 * Config-independent features (no `configured` callable in the catalogue) work
 * standalone with user-entered data (e.g. pixels, buzz, domains) and are only
 * ever "coming soon" when an admin forces them.
 */
final class FeatureAvailability
{
    public const STATUS_READY        = 'ready';
    public const STATUS_COMING_SOON  = 'coming_soon';

    public const REASON_AUTO   = 'auto';
    public const REASON_FORCED = 'forced';

    /** AppSetting key holding the array of feature keys forced to coming soon. */
    public const OVERRIDE_KEY = 'feature_states.forced_coming_soon';

    /** @return array<string,array<string,mixed>> */
    public static function catalog(): array
    {
        return FeatureCatalog::all();
    }

    /** @return array<string,mixed>|null */
    public static function definition(string $key): ?array
    {
        return FeatureCatalog::all()[$key] ?? null;
    }

    /** Feature keys an admin has manually forced to "coming soon". @return string[] */
    public static function forcedKeys(): array
    {
        $raw = AppSetting::get(self::OVERRIDE_KEY, []);
        if (!is_array($raw)) {
            return [];
        }

        // Only keep keys that still exist in the catalogue.
        return array_values(array_intersect(array_map('strval', $raw), array_keys(FeatureCatalog::all())));
    }

    /** Add/remove a manual "coming soon" override for a catalogue feature. */
    public static function setForced(string $key, bool $on): void
    {
        if (!isset(FeatureCatalog::all()[$key])) {
            return;
        }

        $keys = self::forcedKeys();
        if ($on) {
            if (!in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        } else {
            $keys = array_values(array_filter($keys, fn ($k) => $k !== $key));
        }

        AppSetting::put(self::OVERRIDE_KEY, array_values(array_unique($keys)));
    }

    /**
     * Whether the underlying integration/config for a feature is connected.
     * Config-independent features (no `configured` callable) are always
     * considered configured.
     */
    public static function isConfigured(string $key): bool
    {
        $def = self::definition($key);
        if (!$def) {
            return true;
        }

        $check = $def['configured'] ?? null;
        if ($check === null) {
            return true;
        }

        return (bool) call_user_func($check);
    }

    /**
     * Resolve a feature's state.
     *
     * @return array{status:string,reason:?string}
     */
    public static function state(string $key): array
    {
        if (!self::definition($key)) {
            return ['status' => self::STATUS_READY, 'reason' => null];
        }

        if (in_array($key, self::forcedKeys(), true)) {
            return ['status' => self::STATUS_COMING_SOON, 'reason' => self::REASON_FORCED];
        }

        if (!self::isConfigured($key)) {
            return ['status' => self::STATUS_COMING_SOON, 'reason' => self::REASON_AUTO];
        }

        return ['status' => self::STATUS_READY, 'reason' => null];
    }

    public static function isComingSoon(string $key): bool
    {
        return self::state($key)['status'] === self::STATUS_COMING_SOON;
    }

    /**
     * Find the catalogue feature (if any) that owns the given route name, by
     * matching its `routes` glob patterns. Used by the guard middleware.
     *
     * @return array{key:string,definition:array<string,mixed>}|null
     */
    public static function forRouteName(?string $name): ?array
    {
        if (!$name) {
            return null;
        }

        foreach (FeatureCatalog::all() as $key => $def) {
            foreach (($def['routes'] ?? []) as $pattern) {
                if (Str::is($pattern, $name)) {
                    return ['key' => $key, 'definition' => $def];
                }
            }
        }

        return null;
    }

    /** Whether the given user has already asked to be notified about a feature. */
    public static function hasNotifyInterest(?int $userId, string $key): bool
    {
        if (!$userId) {
            return false;
        }

        return FeatureNotifyInterest::where('user_id', $userId)
            ->where('feature_key', $key)
            ->exists();
    }

    /**
     * Record a "notify me" interest for a user, deduped per (user, feature).
     * Returns true when a new interest was created, false when it already
     * existed (idempotent / confirmed state).
     */
    public static function recordNotifyInterest(int $userId, string $key): bool
    {
        if (!isset(FeatureCatalog::all()[$key])) {
            return false;
        }

        $existing = FeatureNotifyInterest::where('user_id', $userId)
            ->where('feature_key', $key)
            ->first();

        if ($existing) {
            return false;
        }

        FeatureNotifyInterest::create([
            'user_id'     => $userId,
            'feature_key' => $key,
        ]);

        return true;
    }

    /**
     * A view-model list of every catalogue feature with its resolved state —
     * used by the admin override screen and the mobile API.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function overview(?int $userId = null): array
    {
        $out = [];
        foreach (FeatureCatalog::all() as $key => $def) {
            $state = self::state($key);
            $out[] = [
                'key'          => $key,
                'label'        => $def['label'],
                'icon'         => $def['icon'],
                'blurb'        => $def['blurb'],
                'capabilities' => $def['capabilities'] ?? [],
                'status'       => $state['status'],
                'reason'       => $state['reason'],
                'auto_ready'   => self::isConfigured($key),
                'forced'       => in_array($key, self::forcedKeys(), true),
                'notified'     => self::hasNotifyInterest($userId, $key),
            ];
        }

        return $out;
    }
}
