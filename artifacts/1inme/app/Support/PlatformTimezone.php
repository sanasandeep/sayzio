<?php

namespace App\Support;

use App\Modules\User\Models\User;

/**
 * Single source of truth for the platform's default (fallback) timezone
 * (Task #3480). The Laravel framework/storage timezone (`config('app.timezone')`)
 * stays UTC — all timestamps remain stored in UTC. This class only governs the
 * *display/interpretation* fallback used whenever a user (or a resource tied to
 * one) has not chosen a personal timezone: it becomes IST (Asia/Kolkata)
 * instead of UTC. A user's explicitly chosen timezone always wins.
 */
class PlatformTimezone
{
    /** The platform-wide fallback timezone. Exposed via `config('app.platform_default_timezone')`. */
    public const DEFAULT = 'Asia/Kolkata';

    /** The effective platform default, honoring the config override if set. */
    public static function platformDefault(): string
    {
        $tz = trim((string) config('app.platform_default_timezone', self::DEFAULT));

        return $tz !== '' ? $tz : self::DEFAULT;
    }

    /**
     * Resolve an effective timezone string: the given value if non-empty,
     * otherwise the platform default.
     */
    public static function resolve(?string $timezone): string
    {
        $tz = trim((string) $timezone);

        return $tz !== '' ? $tz : self::platformDefault();
    }

    /** Effective timezone for a user (or workspace owner), falling back to the platform default. */
    public static function forUser(?User $user): string
    {
        return self::resolve($user?->timezone);
    }

    /**
     * Convert a stored (UTC) timestamp to the platform default timezone for
     * display. Returns a copy — never mutates the given instance.
     */
    public static function display(?\DateTimeInterface $time, ?string $timezone = null): ?\Illuminate\Support\Carbon
    {
        if ($time === null) {
            return null;
        }

        return \Illuminate\Support\Carbon::instance($time)
            ->copy()
            ->setTimezone(self::resolve($timezone));
    }

    /**
     * Format a stored (UTC) timestamp in the platform default timezone,
     * appending the timezone abbreviation (e.g. "IST") when $withAbbr is
     * true. Null-safe: returns null for a null input.
     */
    public static function format(?\DateTimeInterface $time, string $format, bool $withAbbr = true, ?string $timezone = null): ?string
    {
        $local = self::display($time, $timezone);
        if ($local === null) {
            return null;
        }

        return $local->format($format) . ($withAbbr ? ' ' . $local->format('T') : '');
    }
}
