<?php

namespace App\Modules\User\Support;

use Illuminate\Support\Carbon;

/**
 * When a takeaway or delivery order is wanted.
 *
 * Sana, 2026-09-28: "if take away, need to tell slots/time / setting should
 * have min time for prep / if delivery option, then other address also
 * mandatory, need to tell slots/time".
 *
 * ---- Why slots rather than a free time field ---------------------------
 *
 * A guest typing "7pm" into a box can type a time the kitchen is shut, a
 * time twenty minutes from now on an order that takes forty, or yesterday.
 * Each of those is a phone call. A list of times the kitchen can actually
 * hit is shorter to tap and cannot express any of them.
 *
 * ---- Why "as soon as possible" is always first -------------------------
 *
 * It is what most people want and it is the only option that needs no
 * thought. Making somebody pick a clock time to order a dosa now is a
 * worse checkout than the one this replaces.
 *
 * ---- Where prep time goes ----------------------------------------------
 *
 * It moves the FIRST slot, not the list. A kitchen with a 30-minute prep
 * time at 6:52 offers 7:30 onward, not 7:00. The owner sets one number and
 * never has to think about rounding.
 *
 * ---- Whose clock --------------------------------------------------------
 *
 * The owner's. Everything here is computed in their timezone and stored
 * UTC, the same way order numbers and the orders screen do it.
 */
class MenuHandoverTiming
{
    /** How far ahead a guest may book, in days. */
    public const MAX_DAYS = 7;

    /** The intervals an owner may choose between. */
    public const INTERVALS = [15, 30, 60];

    public const DEFAULT_INTERVAL = 30;

    /** A sane service window for a menu whose owner never opened this. */
    public const DEFAULT_OPEN = '10:00';

    public const DEFAULT_CLOSE = '22:00';

    /** More than this many slots is a scroll, not a choice. */
    public const MAX_SLOTS = 800;

    /**
     * The settings as the editor and the page read them.
     *
     * Off by default. Every existing menu takes orders with no timing at
     * all, and switching that on for 375,000 pages because a feature
     * shipped would be a change nobody asked for.
     */
    public static function resolve(array $menuSettings): array
    {
        $raw = $menuSettings['handover_timing'] ?? [];
        $raw = is_array($raw) ? $raw : [];

        return [
            'enabled'      => (bool) ($raw['enabled'] ?? false),
            'interval'     => self::interval($raw['interval'] ?? null),
            'open'         => self::clock($raw['open'] ?? null) ?? self::DEFAULT_OPEN,
            'close'        => self::clock($raw['close'] ?? null) ?? self::DEFAULT_CLOSE,
            'prep_minutes' => self::prep($raw['prep_minutes'] ?? null),
        ];
    }

    public static function sanitize(mixed $raw): array
    {
        return self::resolve(['handover_timing' => is_array($raw) ? $raw : []]);
    }

    private static function interval(mixed $value): int
    {
        $value = (int) $value;

        return in_array($value, self::INTERVALS, true) ? $value : self::DEFAULT_INTERVAL;
    }

    private static function prep(mixed $value): int
    {
        // Six hours is already absurd for a prep time; beyond that it is a
        // typo, and a typo here empties the list of slots entirely.
        return max(0, min(360, (int) $value));
    }

    /** 'HH:MM', or null when it is not a time. */
    private static function clock(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^(\d{1,2}):(\d{2})$/', trim($value), $m)) {
            return null;
        }
        $h = (int) $m[1];
        $i = (int) $m[2];

        return ($h > 23 || $i > 59) ? null : sprintf('%02d:%02d', $h, $i);
    }

    /** Whether this mode asks the guest for a time at all. */
    public static function appliesTo(?string $mode): bool
    {
        // Dine-in can also be reserved for a future arrival.
        return in_array($mode, ['dine_in', 'takeaway', 'delivery'], true);
    }

    /**
     * The times a guest may choose, as [{value, label, day}].
     *
     * `value` is a UTC ISO string, which is what the order stores and what
     * the server checks a submission against. The guest never sees it.
     *
     * @return array<int, array{value: string, label: string, day: string}>
     */
    public static function slots(array $menuSettings, string $timezone, ?Carbon $now = null): array
    {
        $config = self::resolve($menuSettings);
        if (! $config['enabled']) {
            return [];
        }

        $timezone = $timezone ?: 'UTC';
        $now = ($now ? $now->copy() : Carbon::now())->setTimezone($timezone);

        // The earliest the kitchen could hand anything over, rounded UP to
        // the next interval so the list reads as clock times rather than
        // as "7:22, 7:52".
        $earliest = $now->copy()->addMinutes($config['prep_minutes']);
        $earliest = self::ceilTo($earliest, $config['interval']);

        $out = [];
        $day = $earliest->copy()->startOfDay();
        $limit = $now->copy()->addDays(self::MAX_DAYS)->endOfDay();

        while ($day->lessThanOrEqualTo($limit) && count($out) < self::MAX_SLOTS) {
            [$openH, $openM] = array_map('intval', explode(':', $config['open']));
            [$closeH, $closeM] = array_map('intval', explode(':', $config['close']));

            $open = $day->copy()->setTime($openH, $openM);
            $close = $day->copy()->setTime($closeH, $closeM);

            // A window that closes before it opens runs past midnight --
            // a kitchen open 18:00 to 01:00 is an ordinary thing to be.
            if ($close->lessThanOrEqualTo($open)) {
                $close->addDay();
            }

            $at = $open->copy();
            if ($at->lessThan($earliest)) {
                $at = self::ceilTo($earliest->copy(), $config['interval']);
            }

            while ($at->lessThanOrEqualTo($close) && count($out) < self::MAX_SLOTS) {
                if ($at->greaterThanOrEqualTo($earliest)) {
                    $out[] = [
                        'value' => $at->copy()->utc()->toIso8601String(),
                        'label' => $at->format('g:i a'),
                        'day'   => self::dayLabel($at, $now),
                    ];
                }
                $at->addMinutes($config['interval']);
            }

            $day->addDay();
        }

        return $out;
    }

    private static function ceilTo(Carbon $at, int $interval): Carbon
    {
        $at = $at->copy()->seconds(0);
        $over = $at->minute % $interval;
        if ($over !== 0) {
            $at->addMinutes($interval - $over);
        }

        return $at;
    }

    private static function dayLabel(Carbon $at, Carbon $now): string
    {
        if ($at->isSameDay($now)) {
            return 'Today';
        }
        if ($at->isSameDay($now->copy()->addDay())) {
            return 'Tomorrow';
        }

        return $at->format('D j M');
    }

    /**
     * Check a time a guest submitted, and return it, or null.
     *
     * Null means "as soon as possible", which is always allowed. Anything
     * that is not in the offered list is REFUSED rather than snapped to a
     * nearby slot: a guest who asked for 7:30 and silently got 8:00 finds
     * out when they arrive.
     *
     * @throws \InvalidArgumentException with a message meant for the guest
     */
    public static function accept(array $menuSettings, ?string $mode, string $timezone, mixed $wanted, ?Carbon $now = null): ?Carbon
    {
        $wanted = is_string($wanted) ? trim($wanted) : '';

        if ($wanted === '' || $wanted === 'asap') {
            return null;
        }

        // Timing off, or a mode that does not take a time: a value here is
        // from a stale page, so ignore it rather than failing the order.
        if (! self::resolve($menuSettings)['enabled'] || ! self::appliesTo($mode)) {
            return null;
        }

        try {
            $at = Carbon::parse($wanted)->utc();
        } catch (\Throwable) {
            throw new \InvalidArgumentException('That is not a time we can read. Please pick one from the list.');
        }

        foreach (self::slots($menuSettings, $timezone, $now) as $slot) {
            if (Carbon::parse($slot['value'])->equalTo($at)) {
                return $at;
            }
        }

        throw new \InvalidArgumentException(
            'That time is no longer available. Please pick another one.'
        );
    }

    /** How a chosen time reads on a confirmation and on the kitchen screen. */
    public static function describe(?Carbon $at, string $timezone): string
    {
        if (! $at) {
            return 'As soon as possible';
        }

        $local = $at->copy()->setTimezone($timezone ?: 'UTC');
        $now = Carbon::now($timezone ?: 'UTC');

        return self::dayLabel($local, $now).' at '.$local->format('g:i a');
    }
}
