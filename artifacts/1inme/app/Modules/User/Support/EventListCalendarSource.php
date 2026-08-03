<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\Link;

/**
 * Task #6615 — Smart Calendar as a biolink block.
 *
 * Resolves the live upcoming-event payload for an `event_list` block
 * that points at one of the owner's followable `calendar` links
 * (settings['calendar_link_id']). Shared by the public Blade renderer
 * (common/blocks/event-list.blade.php) and the mobile biolink API
 * (Api\BiolinkController), so both surfaces show identical data.
 *
 * Fails closed: returns null when the block has no calendar source, or
 * the referenced link no longer exists / is inactive / is not a
 * calendar link / is not owned by the biolink page's owner. Callers
 * then fall back to the block's manual `events`/`items` payload.
 */
final class EventListCalendarSource
{
    public const MAX_COUNT = 20;
    public const DEFAULT_COUNT = 5;
    public const MAX_RANGE_DAYS = 365;

    /**
     * @param  Link  $pageLink  the biolink page the block lives on (owner scope)
     * @param  array<string,mixed>  $settings  the block's settings payload
     * @return array{events: list<array<string,mixed>>, calendar_title: string, calendar_url: string, subscribe_url: string}|null
     */
    public static function resolve(Link $pageLink, array $settings): ?array
    {
        $calendarLinkId = (int) ($settings['calendar_link_id'] ?? 0);
        if ($calendarLinkId <= 0) {
            return null;
        }

        // Public render path — resolve across all of the owner's
        // workspaces, but NEVER across owners (fail closed on foreign or
        // deleted links).
        $calLink = Link::withoutGlobalScopes()
            ->where('id', $calendarLinkId)
            ->where('user_id', $pageLink->user_id)
            ->where('type', Link::TYPE_CALENDAR)
            ->where('is_active', true)
            ->first();

        $calendar = $calLink?->calendar;
        if (!$calLink || !$calendar) {
            return null;
        }

        $count = (int) ($settings['count'] ?? self::DEFAULT_COUNT);
        $count = max(1, min(self::MAX_COUNT, $count));

        $rangeDays = (int) ($settings['range_days'] ?? 0);
        $rangeDays = max(0, min(self::MAX_RANGE_DAYS, $rangeDays));

        $query = $calendar->upcomingEvents();
        if ($rangeDays > 0) {
            $query->where('start_at', '<=', now()->addDays($rangeDays));
        }

        $calendarUrl = url('/' . $calLink->alias);

        $events = $query->limit($count)->get()->map(static fn ($e) => [
            'title'       => (string) $e->title,
            'date'        => $e->start_at?->toIso8601String(),
            'location'    => $e->location,
            'description' => $e->description,
            'all_day'     => (bool) $e->all_day,
            'url'         => $calendarUrl,
        ])->values()->all();

        return [
            'events'         => $events,
            'calendar_title' => (string) $calendar->title,
            'calendar_url'   => $calendarUrl,
            'subscribe_url'  => route('public.calendars.ics', $calendar->id),
        ];
    }
}
