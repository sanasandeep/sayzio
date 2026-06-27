<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\AppSetting;

/**
 * Single source of truth for the email-log retention policy.
 *
 * Every outbound email writes an {@see \App\Modules\Common\Models\EmailLog} row
 * (subject + full rendered body). Across ~70 email types and the whole user base
 * that table grows without bound, bloating the shared RDS and slowing the admin
 * Email Log screen. The {@see \App\Console\Commands\PruneEmailLogs} daily sweep
 * resolves its windows here.
 *
 * Three independent, operator-configurable knobs (all AppSetting-backed so they
 * can be tuned without a deploy):
 *   1. `email_logs.retention_days`      — delete whole rows older than this.
 *   2. `email_logs.body_retention_days` — null the heavy stored body of rows
 *      older than this (keeping the lightweight audit row: who/when/status) so
 *      most storage is reclaimed well before the row itself is deleted.
 *   3. `email_logs.max_body_bytes`      — write-time cap so a single pathological
 *      email can never store an unbounded blob in the first place.
 *
 * Safety (mirrors the stats-retention pattern): a 30-day floor stops an admin
 * accidentally nuking recent logs, and `-1` on either window disables that
 * sweep entirely ("keep forever"). The defaults bound growth out of the box so
 * the table stops growing forever even before anything is configured, while the
 * command's chunk/max-batch caps mean no single run can mass-delete the table.
 */
class EmailLogRetentionPolicy
{
    /** Delete whole rows older than this many days when unconfigured. */
    public const DEFAULT_RETENTION_DAYS = 365;

    /** Null the stored body of rows older than this many days when unconfigured. */
    public const DEFAULT_BODY_RETENTION_DAYS = 90;

    /** Write-time cap on a single stored body (256 KiB) when unconfigured. */
    public const DEFAULT_MAX_BODY_BYTES = 262_144;

    /** Lowest a retention window may be set to, so recent logs are never nuked. */
    public const MIN_RETENTION_DAYS = 30;

    /**
     * Days after which a whole log row is deleted. `-1` (or any explicit value
     * below the floor that isn't -1) is normalised: -1 stays -1 (keep forever),
     * everything else is clamped up to the {@see MIN_RETENTION_DAYS} floor.
     */
    public static function retentionDays(): int
    {
        return self::resolveWindow('email_logs.retention_days', self::DEFAULT_RETENTION_DAYS);
    }

    /** Days after which a row's heavy body is nulled (row kept). `-1` disables. */
    public static function bodyRetentionDays(): int
    {
        return self::resolveWindow('email_logs.body_retention_days', self::DEFAULT_BODY_RETENTION_DAYS);
    }

    /**
     * Max bytes of a stored body. `0`/`-1` disables capping (store full body).
     */
    public static function maxBodyBytes(): int
    {
        $raw = AppSetting::get('email_logs.max_body_bytes', self::DEFAULT_MAX_BODY_BYTES);
        $val = is_numeric($raw) ? (int) $raw : self::DEFAULT_MAX_BODY_BYTES;

        return $val <= 0 ? 0 : $val;
    }

    /**
     * Resolve a retention window from an AppSetting:
     *   - missing / non-numeric  => the supplied default,
     *   - explicit -1            => -1 (keep forever, sweep is a no-op),
     *   - any other value        => max(value, floor).
     */
    private static function resolveWindow(string $key, int $default): int
    {
        $raw = AppSetting::get($key, null);

        if ($raw === null || !is_numeric($raw)) {
            return $default;
        }

        $val = (int) $raw;
        if ($val === -1) {
            return -1;
        }

        return max(self::MIN_RETENTION_DAYS, $val);
    }
}
