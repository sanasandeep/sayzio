<?php

namespace App\Modules\User\Support;

use Illuminate\Support\Carbon;

/**
 * Which orders the kitchen screen is looking at.
 *
 * Sana, 2026-09-28: "wht is too many order? selected with dates?"
 *
 * ---- What the screen did before --------------------------------------
 *
 * `latest()->limit(100)`. On a quiet menu that is every order ever; on a
 * busy Saturday the hundred-and-first simply did not exist, and there was
 * no way to look at yesterday AT ALL. Both failures are silent: the screen
 * shows a hundred orders and says nothing about the rest.
 *
 * ---- Why the default is today ----------------------------------------
 *
 * This is a screen somebody has open beside a pass during service. What
 * they need is today, and "today" stays a useful size forever, where "the
 * last hundred" stops being today the moment a restaurant gets busy --
 * which is exactly when they need it most.
 *
 * ---- Whose today -----------------------------------------------------
 *
 * The owner's. Timestamps are stored UTC, so a kitchen in Chennai asking
 * for "today" at 10pm would otherwise be shown a window that ended four
 * and a half hours ago. Same reasoning as the order-number reset, and the
 * same source for the timezone.
 */
class MenuOrderRange
{
    public const TODAY = 'today';

    public const YESTERDAY = 'yesterday';

    public const WEEK = '7d';

    public const MONTH = '30d';

    public const ALL = 'all';

    public const CUSTOM = 'custom';

    /** What the buttons say, in the order they appear. */
    public const LABELS = [
        self::TODAY     => 'Today',
        self::YESTERDAY => 'Yesterday',
        self::WEEK      => 'Last 7 days',
        self::MONTH     => 'Last 30 days',
        self::ALL       => 'All time',
    ];

    /** How many orders one page of the screen holds. */
    public const PER_PAGE = 50;

    public static function keys(): array
    {
        return array_merge(array_keys(self::LABELS), [self::CUSTOM]);
    }

    /**
     * Resolve a request into a window, in UTC, plus everything the screen
     * needs to describe itself.
     *
     * `from`/`to` are read as plain dates in the owner's zone and are
     * INCLUSIVE at both ends: somebody asking for the 3rd to the 5th means
     * all of the 5th, not up to midnight at its start.
     *
     * @return array{key:string, label:string, from:?Carbon, to:?Carbon, from_date:?string, to_date:?string, is_live:bool}
     */
    public static function resolve(?string $key, ?string $from, ?string $to, string $timezone): array
    {
        $timezone = $timezone ?: 'UTC';
        $key = in_array($key, self::keys(), true) ? $key : self::TODAY;
        $now = Carbon::now($timezone);

        if ($key === self::CUSTOM) {
            $start = self::day($from, $timezone);
            $end = self::day($to, $timezone);

            // Nothing usable typed in: fall back rather than showing an
            // empty screen with no explanation.
            if (! $start && ! $end) {
                $key = self::TODAY;
            } else {
                // One end only is a perfectly sensible thing to ask for
                // ("everything since the 1st").
                $start = $start ?: null;
                $end = $end ? $end->copy()->endOfDay() : null;

                // Backwards is a typo, not a request for nothing.
                if ($start && $end && $start->greaterThan($end)) {
                    [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
                }

                return [
                    'key'       => self::CUSTOM,
                    'label'     => self::describe($start, $end),
                    'from'      => $start?->copy()->utc(),
                    'to'        => $end?->copy()->utc(),
                    'from_date' => $start?->format('Y-m-d'),
                    'to_date'   => $end?->format('Y-m-d'),
                    // Live only if the window actually reaches now.
                    'is_live'   => ! $end || $end->greaterThanOrEqualTo($now),
                ];
            }
        }

        [$start, $end] = match ($key) {
            self::YESTERDAY => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            self::WEEK      => [$now->copy()->subDays(6)->startOfDay(), null],
            self::MONTH     => [$now->copy()->subDays(29)->startOfDay(), null],
            self::ALL       => [null, null],
            default         => [$now->copy()->startOfDay(), null],
        };

        return [
            'key'       => $key,
            'label'     => self::LABELS[$key],
            'from'      => $start?->copy()->utc(),
            'to'        => $end?->copy()->utc(),
            'from_date' => $start?->format('Y-m-d'),
            'to_date'   => $end?->format('Y-m-d'),
            // Yesterday is the only preset that has already closed, so it
            // is the only one where a new order will not appear.
            'is_live'   => $key !== self::YESTERDAY,
        ];
    }

    /** A yyyy-mm-dd typed by a person, at the start of that day. */
    private static function day(?string $value, string $timezone): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value, $timezone)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function describe(?Carbon $start, ?Carbon $end): string
    {
        $fmt = fn (Carbon $d) => $d->format('j M Y');

        if ($start && $end) {
            return $start->isSameDay($end) ? $fmt($start) : $fmt($start).' to '.$fmt($end);
        }
        if ($start) {
            return 'Since '.$fmt($start);
        }

        return 'Up to '.$fmt($end);
    }

    /** Narrow a query to the window. Nulls mean "no bound that end". */
    public static function apply($query, array $range)
    {
        if ($range['from']) {
            $query->where('created_at', '>=', $range['from']);
        }
        if ($range['to']) {
            $query->where('created_at', '<=', $range['to']);
        }

        return $query;
    }

    /**
     * How to say "none" for a window, in English rather than assembled.
     *
     * Gluing "in " onto a lowercased label gives "No orders in yesterday",
     * which is how this read the first time it was looked at. Each window
     * takes a different preposition or none at all, so each one says its
     * own sentence.
     */
    public static function emptyPhrase(array $range, string $noun): string
    {
        return match ($range['key']) {
            self::TODAY     => 'No '.$noun.' today yet. New ones appear here automatically.',
            self::YESTERDAY => 'No '.$noun.' yesterday.',
            self::WEEK      => 'No '.$noun.' in the last 7 days.',
            self::MONTH     => 'No '.$noun.' in the last 30 days.',
            self::ALL       => 'No '.$noun.' yet.',
            default         => 'No '.$noun.' in '.$range['label'].'.',
        };
    }

    /** And the same for the one-line count above the list. */
    public static function summarySuffix(array $range): string
    {
        return match ($range['key']) {
            self::TODAY     => 'today',
            self::YESTERDAY => 'yesterday',
            self::WEEK      => 'in the last 7 days',
            self::MONTH     => 'in the last 30 days',
            self::ALL       => 'all time',
            default         => $range['label'],
        };
    }

    /** What the page hands the board, so the board can describe itself. */
    public static function forPage(array $range, int $total, int $shown, string $noun = 'orders'): array
    {
        return [
            'empty'     => self::emptyPhrase($range, $noun),
            'suffix'    => self::summarySuffix($range),
            'key'       => $range['key'],
            'label'     => $range['label'],
            'from_date' => $range['from_date'],
            'to_date'   => $range['to_date'],
            'is_live'   => $range['is_live'],
            // Milliseconds, because the board compares against an ISO
            // timestamp from the server and Date.parse is what it has.
            'from_ms'   => $range['from']?->getTimestampMs(),
            'to_ms'     => $range['to']?->getTimestampMs(),
            'total'     => $total,
            'shown'     => $shown,
            'per_page'  => self::PER_PAGE,
        ];
    }
}
