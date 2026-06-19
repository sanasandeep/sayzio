<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\AppSetting;

/**
 * Single source of truth for the periodic email-verification reminder
 * cadence (the `users:send-email-verification-reminders` command).
 *
 * Historically the grace period, interval, and cap were private constants
 * baked into the command. They now live in AppSetting (JSONB key/value,
 * 5-min cached) so an admin can tune the cadence — or switch the whole
 * feature off — from the admin UI without a code change. The defaults below
 * preserve the original hardcoded behaviour for installations that have
 * never touched the settings.
 */
class EmailVerificationReminderSettings
{
    public const SETTING_ENABLED       = 'email_verification_reminder_enabled';
    public const SETTING_GRACE_DAYS    = 'email_verification_reminder_grace_days';
    public const SETTING_INTERVAL_DAYS = 'email_verification_reminder_interval_days';
    public const SETTING_MAX_REMINDERS = 'email_verification_reminder_max_reminders';

    /** Whether the periodic reminder runs at all. */
    public const DEFAULT_ENABLED = true;

    /** Wait this many days after sign-up before the first reminder. */
    public const DEFAULT_GRACE_DAYS = 3;

    /** Minimum gap between two reminders to the same user. */
    public const DEFAULT_INTERVAL_DAYS = 7;

    /** Hard cap on the total number of reminders a user ever receives. */
    public const DEFAULT_MAX_REMINDERS = 3;

    /** Sensible bounds so a typo can't make the cadence nonsensical. */
    public const MIN_GRACE_DAYS    = 0;
    public const MAX_GRACE_DAYS    = 90;
    public const MIN_INTERVAL_DAYS = 1;
    public const MAX_INTERVAL_DAYS = 90;
    public const MIN_MAX_REMINDERS = 1;
    public const MAX_MAX_REMINDERS = 20;

    /** Is the periodic reminder switched on? */
    public static function enabled(): bool
    {
        return (bool) AppSetting::get(self::SETTING_ENABLED, self::DEFAULT_ENABLED);
    }

    /** Days to wait after sign-up before the first reminder. */
    public static function graceDays(): int
    {
        return self::clampInt(
            AppSetting::get(self::SETTING_GRACE_DAYS, self::DEFAULT_GRACE_DAYS),
            self::MIN_GRACE_DAYS,
            self::MAX_GRACE_DAYS,
            self::DEFAULT_GRACE_DAYS
        );
    }

    /** Minimum days between two reminders to the same user. */
    public static function intervalDays(): int
    {
        return self::clampInt(
            AppSetting::get(self::SETTING_INTERVAL_DAYS, self::DEFAULT_INTERVAL_DAYS),
            self::MIN_INTERVAL_DAYS,
            self::MAX_INTERVAL_DAYS,
            self::DEFAULT_INTERVAL_DAYS
        );
    }

    /** Total number of reminders a single user can ever receive. */
    public static function maxReminders(): int
    {
        return self::clampInt(
            AppSetting::get(self::SETTING_MAX_REMINDERS, self::DEFAULT_MAX_REMINDERS),
            self::MIN_MAX_REMINDERS,
            self::MAX_MAX_REMINDERS,
            self::DEFAULT_MAX_REMINDERS
        );
    }

    /**
     * Coerce a stored value to an int and clamp it into [$min, $max],
     * falling back to $default when the value isn't a usable number.
     */
    private static function clampInt($value, int $min, int $max, int $default): int
    {
        if (!is_numeric($value)) {
            return $default;
        }
        $int = (int) $value;
        return max($min, min($max, $int));
    }
}
