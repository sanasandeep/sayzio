<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\Calendar;
use App\Modules\User\Models\CalendarEvent;
use App\Modules\User\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Task #6551 — "Special Dates" on the creator/brand profile: date of birth,
 * personal anniversary, company anniversary and product release dates.
 *
 * Entries live in the users.special_dates jsonb column:
 *   { id, kind, label, date: 'Y-m-d', public, notify, sync, calendar_event_id }
 *
 * - `public`  → the entry renders on the /@handle profile (behind the
 *               'special_dates' section-visibility toggle).
 * - `notify`  → the daily wish job fans out follower notifications on the day
 *               (public entries only — private dates never notify).
 * - `sync`    → the entry is mirrored as a yearly all-day event on the
 *               creator's public "Special Dates" calendar (slug sd-{user_id}),
 *               so it rides the followable-calendar ICS feed + Google push
 *               sync like any other calendar event. Events are kept in
 *               lockstep with edits/removals, and rolled forward to the next
 *               year's occurrence after each occurrence passes.
 */
class SpecialDates
{
    /** Slug prefix of the auto-provisioned public "Special Dates" calendar. */
    public const CALENDAR_SLUG_PREFIX = 'sd-';

    /** Max entries a profile can hold (singles + product releases). */
    public const MAX_ENTRIES = 20;

    /** kind => [label, icon, single?] — single kinds may appear once. */
    public const KINDS = [
        'birthday'            => ['label' => 'Birthday',            'icon' => 'fas fa-cake-candles', 'single' => true],
        'anniversary'         => ['label' => 'Anniversary',         'icon' => 'fas fa-heart',        'single' => true],
        'company_anniversary' => ['label' => 'Company anniversary', 'icon' => 'fas fa-building',     'single' => true],
        'product_release'     => ['label' => 'Product release',     'icon' => 'fas fa-rocket',       'single' => false],
    ];

    /** The stored entries, always a clean array. */
    public static function entries(User $user): array
    {
        return is_array($user->special_dates) ? array_values($user->special_dates) : [];
    }

    /**
     * Normalize raw editor input into the stored shape and assign it to the
     * model (does not save). Preserves entry ids + calendar_event_id pointers
     * of surviving entries so calendar lockstep can update instead of churn.
     *
     * @param array<int, mixed> $raw
     */
    public static function applyInput(User $user, array $raw): void
    {
        $existing = collect(self::entries($user))->keyBy('id');
        $seenSingles = [];
        $out = [];

        foreach ($raw as $item) {
            if (!is_array($item)) continue;
            if (count($out) >= self::MAX_ENTRIES) break;

            $kind = (string) ($item['kind'] ?? '');
            if (!isset(self::KINDS[$kind])) continue;

            // Single kinds (birthday etc.) may only appear once — first wins.
            if ((self::KINDS[$kind]['single'] ?? false)) {
                if (isset($seenSingles[$kind])) continue;
                $seenSingles[$kind] = true;
            }

            $date = (string) ($item['date'] ?? '');
            try {
                $parsed = Carbon::createFromFormat('Y-m-d', $date);
            } catch (\Throwable) {
                continue;
            }
            if (!$parsed || $parsed->format('Y-m-d') !== $date) continue;

            $label = trim((string) ($item['label'] ?? ''));
            if ($kind === 'product_release' && $label === '') continue; // releases need a name
            if (mb_strlen($label) > 120) $label = mb_substr($label, 0, 120);

            $id = (string) ($item['id'] ?? '');
            if ($id === '' || mb_strlen($id) > 64) {
                $id = (string) Str::uuid();
            }
            $prior = $existing->get($id);

            $out[] = [
                'id'                => $id,
                'kind'              => $kind,
                'label'             => $label,
                'date'              => $date,
                'public'            => filter_var($item['public'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'notify'            => filter_var($item['notify'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'sync'              => filter_var($item['sync'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'calendar_event_id' => is_array($prior) ? ($prior['calendar_event_id'] ?? null) : null,
            ];
        }

        $user->special_dates = $out;
    }

    /** Display title for an entry, e.g. "Birthday" or "Launch: Zio 2.0". */
    public static function title(array $entry): string
    {
        $kind = self::KINDS[$entry['kind'] ?? ''] ?? null;
        if (($entry['kind'] ?? '') === 'product_release') {
            return 'Release: ' . ($entry['label'] ?? 'Product');
        }
        return ($entry['label'] ?? '') !== '' ? (string) $entry['label'] : ($kind['label'] ?? 'Special date');
    }

    /**
     * The next occurrence of the entry's month/day on-or-after $from (a date
     * in the creator's timezone). Feb 29 celebrates on Feb 28 in common years.
     */
    public static function nextOccurrence(string $date, Carbon $from): ?Carbon
    {
        try {
            $orig = Carbon::createFromFormat('Y-m-d', $date, $from->getTimezone());
        } catch (\Throwable) {
            return null;
        }
        if (!$orig) return null;

        foreach ([$from->year, $from->year + 1] as $year) {
            $month = (int) $orig->month;
            $day   = (int) $orig->day;
            if ($month === 2 && $day === 29 && !Carbon::create($year)->isLeapYear()) {
                $day = 28;
            }
            $candidate = Carbon::create($year, $month, $day, 0, 0, 0, $from->getTimezone());
            if ($candidate->greaterThanOrEqualTo($from->copy()->startOfDay())) {
                return $candidate;
            }
        }
        return null;
    }

    /** Whether the entry's occurrence falls on the given local calendar day. */
    public static function occursOn(array $entry, Carbon $localDay): bool
    {
        $next = self::nextOccurrence((string) ($entry['date'] ?? ''), $localDay->copy()->startOfDay());
        return $next !== null && $next->isSameDay($localDay);
    }

    /**
     * Public entries for the /@handle page, sorted by next occurrence, each
     * decorated with title/icon/next_occurrence ("Mar 12") fields.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function publicEntries(User $creator): array
    {
        $tz  = \App\Support\PlatformTimezone::forUser($creator);
        $now = Carbon::now($tz);

        return collect(self::entries($creator))
            ->filter(fn ($e) => !empty($e['public']))
            ->map(function ($e) use ($now) {
                $next = self::nextOccurrence((string) ($e['date'] ?? ''), $now->copy()->startOfDay());
                if (!$next) return null;
                return [
                    'kind'       => $e['kind'],
                    'title'      => self::title($e),
                    'icon'       => self::KINDS[$e['kind']]['icon'] ?? 'fas fa-star',
                    'next'       => $next,
                    'next_label' => $next->format('M j'),
                    'is_today'   => $next->isSameDay($now),
                ];
            })
            ->filter()
            ->sortBy('next')
            ->values()
            ->all();
    }

    /* ── Calendar lockstep ─────────────────────────────────────────── */

    /**
     * The creator's public "Special Dates" calendar, created lazily on the
     * first synced entry. Public so followers can follow it and its events
     * flow into their aggregated ICS feed like any other followed calendar.
     */
    public static function ensureCalendar(User $user): Calendar
    {
        return Calendar::firstOrCreate(
            ['user_id' => $user->id, 'slug' => self::CALENDAR_SLUG_PREFIX . $user->id],
            [
                'title'       => 'Special Dates',
                'description' => 'Birthdays, anniversaries and release days worth celebrating.',
                'is_public'   => true,
                'timezone'    => \App\Support\PlatformTimezone::forUser($user),
            ]
        );
    }

    /**
     * Bring calendar events into lockstep with the stored entries: upsert an
     * all-day event at the next occurrence for every sync-flagged entry,
     * delete events of removed / sync-off entries, and persist updated
     * calendar_event_id pointers back onto the user (saveQuietly).
     */
    public static function syncCalendarEvents(User $user): void
    {
        $entries = self::entries($user);
        $tz      = \App\Support\PlatformTimezone::forUser($user);
        $now     = Carbon::now($tz);

        $wanted   = collect($entries)->filter(fn ($e) => !empty($e['sync']));
        $calendar = null;
        $dirty    = false;

        foreach ($entries as $i => $entry) {
            $eventId = $entry['calendar_event_id'] ?? null;

            if (empty($entry['sync'])) {
                if ($eventId) {
                    self::deleteEvent($user, (int) $eventId);
                    $entries[$i]['calendar_event_id'] = null;
                    $dirty = true;
                }
                continue;
            }

            $next = self::nextOccurrence((string) $entry['date'], $now->copy()->startOfDay());
            if (!$next) continue;

            $calendar ??= self::ensureCalendar($user);
            $attributes = [
                'calendar_id' => $calendar->id,
                'user_id'     => $user->id,
                'title'       => self::title($entry),
                'description' => 'Yearly special date from ' . ($user->name ?: 'this creator') . "'s profile.",
                'start_at'    => $next->copy()->startOfDay(),
                'end_at'      => $next->copy()->endOfDay(),
                'timezone'    => $tz,
                'all_day'     => true,
            ];

            $event = $eventId ? CalendarEvent::find($eventId) : null;
            if ($event && (int) $event->user_id !== (int) $user->id) {
                $event = null; // never adopt someone else's event
            }

            if ($event) {
                $event->fill($attributes)->save();
                self::bestEffortPush($user, $event);
            } else {
                $event = $calendar->events()->create($attributes);
                $entries[$i]['calendar_event_id'] = $event->id;
                $dirty = true;
                self::bestEffortPush($user, $event);
            }
        }

        // Events pointed at by no surviving entry were removed with their entry.
        // (Removed entries disappear from the array, so their events are found
        // by scanning the special-dates calendar for orphans.)
        $keptIds = collect($entries)->pluck('calendar_event_id')->filter()->map(fn ($v) => (int) $v)->all();
        $calendarId = Calendar::where('user_id', $user->id)
            ->where('slug', self::CALENDAR_SLUG_PREFIX . $user->id)
            ->value('id');
        if ($calendarId) {
            $orphans = CalendarEvent::where('calendar_id', $calendarId)
                ->when(!empty($keptIds), fn ($q) => $q->whereNotIn('id', $keptIds))
                ->get();
            foreach ($orphans as $orphan) {
                self::deleteEvent($user, (int) $orphan->id);
            }
        }

        if ($dirty) {
            $user->forceFill(['special_dates' => array_values($entries)])->saveQuietly();
        }
    }

    /**
     * Roll a synced entry's event forward to the next year's occurrence once
     * today's occurrence has been processed (called by the daily wish job).
     */
    public static function rollEventForward(User $user, array $entry, Carbon $localToday): void
    {
        $eventId = $entry['calendar_event_id'] ?? null;
        if (!$eventId || empty($entry['sync'])) return;

        $event = CalendarEvent::find($eventId);
        if (!$event || (int) $event->user_id !== (int) $user->id) return;

        $next = self::nextOccurrence((string) $entry['date'], $localToday->copy()->addDay()->startOfDay());
        if (!$next) return;

        $event->fill([
            'start_at' => $next->copy()->startOfDay(),
            'end_at'   => $next->copy()->endOfDay(),
        ])->save();
        self::bestEffortPush($user, $event);
    }

    /** Delete a mirrored event (and its pushed Google copy, best-effort). */
    protected static function deleteEvent(User $user, int $eventId): void
    {
        $event = CalendarEvent::find($eventId);
        if (!$event || (int) $event->user_id !== (int) $user->id) return;

        $account = self::pushAccount($user);
        if ($account) {
            try {
                app(\App\Modules\User\Services\Calendar\CalendarSyncService::class)
                    ->deletePushedEvent($account, $event);
            } catch (\Throwable $e) {
                \Log::warning('Special-date event push-delete failed: ' . $e->getMessage(), ['event' => $event->id]);
            }
        }
        $event->delete();
    }

    /** Push the event up to Google when the owner has push sync connected. */
    protected static function bestEffortPush(User $user, CalendarEvent $event): void
    {
        $account = self::pushAccount($user);
        if (!$account) return;
        try {
            app(\App\Modules\User\Services\Calendar\CalendarSyncService::class)
                ->pushCalendarEvent($account, $event);
        } catch (\Throwable $e) {
            \Log::warning('Special-date event push failed: ' . $e->getMessage(), ['event' => $event->id]);
        }
    }

    protected static function pushAccount(User $user): ?\App\Modules\User\Models\CalendarAccount
    {
        if (!$user->getPlanFeature('calendar_sync', false)) return null;

        return \App\Modules\User\Models\CalendarAccount::where('user_id', $user->id)
            ->where('provider', 'google')
            ->where('push_enabled', true)
            ->first();
    }
}
