<?php

namespace App\Modules\User\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The number a guest is told to listen for.
 *
 * Sana, 2026-09-28: "i need token no. to be generated for each order...
 * need options in setting like: reset tokeno. by day, week, month or all
 * time".
 *
 * ---- Why this cannot be max(token_number) + 1 --------------------------
 *
 * Two people at two tables tap Place order in the same second. A read of
 * the highest number followed by a write hands them both 14, and the
 * person at the counter then has two order 14s and no way to tell them
 * apart. `next()` increments a row inside a locked transaction, so the
 * second request waits for the first and gets 15.
 *
 * ---- Why the period is part of the key ---------------------------------
 *
 * "Reset daily" is a new counter row tomorrow, not a job that empties
 * something at midnight. Nothing has to be scheduled, nothing has to be
 * cleaned up, and an owner who switches from daily to monthly halfway
 * through the month keeps both runs intact rather than colliding with
 * numbers already called out.
 *
 * ---- Whose midnight ----------------------------------------------------
 *
 * The owner's. A restaurant in Chennai closing at 23:30 wants one run of
 * numbers for that evening; UTC would split it at 05:30 local and start
 * the evening over at 1.
 */
class MenuOrderToken
{
    public const RESET_NEVER = 'never';

    public const RESET_DAY = 'day';

    public const RESET_WEEK = 'week';

    public const RESET_MONTH = 'month';

    /** What the setting may hold, and what each one means to an owner. */
    public const RESETS = [
        self::RESET_DAY   => 'Every day',
        self::RESET_WEEK  => 'Every week',
        self::RESET_MONTH => 'Every month',
        self::RESET_NEVER => 'Never, keep counting',
    ];

    /**
     * A restaurant numbering from 1 every morning is the norm, so that is
     * the default rather than "never" -- an unset menu should behave the
     * way the person who never opened the setting expects.
     */
    public const DEFAULT_RESET = self::RESET_DAY;

    /** Whether this menu hands out token numbers at all. */
    public static function enabled(array $settings): bool
    {
        // Opt-out rather than opt-in: a number to listen for is useful on
        // essentially every counter-service menu, and an owner who does
        // not want one can say so.
        return (bool) ($settings['tokens']['enabled'] ?? true);
    }

    public static function reset(array $settings): string
    {
        $mode = $settings['tokens']['reset'] ?? null;

        return array_key_exists($mode, self::RESETS) ? $mode : self::DEFAULT_RESET;
    }

    /** The shape the editor reads and writes. */
    public static function resolve(array $settings): array
    {
        return [
            'enabled' => self::enabled($settings),
            'reset'   => self::reset($settings),
        ];
    }

    public static function sanitize(mixed $raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $mode = $raw['reset'] ?? null;

        return [
            'enabled' => (bool) ($raw['enabled'] ?? true),
            'reset'   => array_key_exists($mode, self::RESETS) ? $mode : self::DEFAULT_RESET,
        ];
    }

    /**
     * Which run of numbers `$at` belongs to, in the owner's own day.
     *
     * Kept to 16 characters because that is the column, and distinct
     * across modes so switching the setting cannot land on a key the
     * previous mode already used.
     */
    public static function periodKey(string $reset, string $timezone, ?Carbon $at = null): string
    {
        $at = ($at ? $at->copy() : Carbon::now())->setTimezone($timezone ?: 'UTC');

        return match ($reset) {
            self::RESET_DAY   => $at->format('Y-m-d'),
            // ISO week, so the run turns over on the same weekday
            // everywhere rather than on whichever day the month started.
            self::RESET_WEEK  => $at->format('o-\WW'),
            self::RESET_MONTH => $at->format('Y-m'),
            default           => 'all',
        };
    }

    /**
     * The next number for this menu, reserved for the caller.
     *
     * Runs in its own transaction with the counter row locked, so two
     * orders placed in the same second get two different numbers. The
     * caller is expected to be inside its own transaction already; this
     * one nests, which on every driver we support means the lock is held
     * until the outer commit -- fine, because the row is only ever
     * contended by other orders on the SAME menu.
     */
    public static function next(string $menuType, int $menuId, string $periodKey): int
    {
        return DB::transaction(function () use ($menuType, $menuId, $periodKey) {
            $row = DB::table('menu_order_counters')
                ->where('menu_type', $menuType)
                ->where('menu_id', $menuId)
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                // Another request may create it between the read and the
                // insert; the unique index catches that and we re-read.
                try {
                    DB::table('menu_order_counters')->insert([
                        'menu_type'  => $menuType,
                        'menu_id'    => $menuId,
                        'period_key' => $periodKey,
                        'next_value' => 2,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    return 1;
                } catch (\Illuminate\Database\QueryException) {
                    $row = DB::table('menu_order_counters')
                        ->where('menu_type', $menuType)
                        ->where('menu_id', $menuId)
                        ->where('period_key', $periodKey)
                        ->lockForUpdate()
                        ->first();
                }
            }

            $value = (int) $row->next_value;

            DB::table('menu_order_counters')
                ->where('id', $row->id)
                ->update(['next_value' => $value + 1, 'updated_at' => now()]);

            return $value;
        });
    }

    /**
     * The whole job for one order: the period and the number, or nulls
     * when this menu does not hand them out.
     *
     * @return array{0: ?int, 1: ?string}
     */
    public static function reserve(string $menuType, int $menuId, array $settings, string $timezone): array
    {
        if (! self::enabled($settings)) {
            return [null, null];
        }

        $period = self::periodKey(self::reset($settings), $timezone);

        return [self::next($menuType, $menuId, $period), $period];
    }

    /** "Plain", as asked: the number, nothing around it. */
    public static function label(?int $number): string
    {
        return $number === null ? '' : (string) $number;
    }
}
