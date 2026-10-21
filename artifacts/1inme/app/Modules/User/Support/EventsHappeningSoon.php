<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\EventTicket;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Rsvp;
use Illuminate\Support\Collection;

/**
 * What this account needs to know about today, as host or as guest.
 *
 * A creator with a door opening in an hour, and a guest holding a ticket
 * for it, both have the same problem: the event is the most urgent thing
 * in their account and the dashboard has never mentioned it. They each
 * have to remember it exists and go looking.
 *
 * Deliberately narrow. Only what is running now or starting within a day,
 * because anything further out is a calendar question rather than a
 * dashboard one, and a panel that is always populated stops being read.
 */
class EventsHappeningSoon
{
    /** How far ahead counts as "soon". */
    public const WINDOW_HOURS = 24;

    /** Beyond this the strip collapses to a count rather than a list. */
    public const LIST_LIMIT = 2;

    public static function for($user): Collection
    {
        $hosting = self::hosting($user);
        $attending = self::attending($user, $hosting->pluck('link.id')->all());

        return $hosting->concat($attending)
            ->sortBy(fn (array $row) => $row['starts_at'])
            ->values();
    }

    /** Events this account owns. */
    private static function hosting($user): Collection
    {
        $links = $user->links()
            ->where('type', 'ics')
            ->whereHas('icsData', fn ($q) => self::withinWindow($q))
            ->with('icsData')
            ->get();

        return self::shape($links, 'host');
    }

    /**
     * Events this account is going to.
     *
     * Two sources, because a seat can come from either: a ticket (paid, or
     * minted for a free RSVP) or the RSVP itself. They overlap heavily and
     * the ids are merged rather than the rows, so an attendee with both
     * appears once.
     */
    private static function attending($user, array $excludeLinkIds): Collection
    {
        $ticketLinkIds = EventTicket::query()
            ->where(function ($q) use ($user) {
                $q->where('buyer_user_id', $user->id);
                if (!empty($user->email)) {
                    $q->orWhere('attendee_email', $user->email);
                }
            })
            ->whereIn('status', ['valid', 'checked_in'])
            ->pluck('link_id');

        $rsvpLinkIds = Rsvp::query()
            ->forAccount($user)
            ->where('response', 'yes')
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->pluck('link_id');

        $linkIds = $ticketLinkIds->concat($rsvpLinkIds)
            ->unique()
            ->diff($excludeLinkIds)
            ->all();

        if (empty($linkIds)) {
            return collect();
        }

        // Someone else's event, so the workspace scope has to come off --
        // it exists to keep a creator inside their own workspace, and a
        // guest is by definition outside it.
        $links = Link::withoutGlobalScope('workspace')
            ->whereIn('id', $linkIds)
            ->where('type', 'ics')
            ->whereHas('icsData', fn ($q) => self::withinWindow($q))
            ->with(['icsData', 'user'])
            ->get();

        return self::shape($links, 'guest');
    }

    /** Running now, or starting inside the window. */
    private static function withinWindow($query): void
    {
        $now = now();

        $query->where(function ($q) use ($now) {
            // Under way. end_date is NOT NULL on ics_data, so there is no
            // open-ended event to allow for.
            $q->where(function ($w) use ($now) {
                $w->where('start_date', '<=', $now)
                    ->where('end_date', '>=', $now);
            });

            // Or not started yet, but close enough to matter.
            $q->orWhere(function ($w) use ($now) {
                $w->where('start_date', '>', $now)
                    ->where('start_date', '<=', $now->copy()->addHours(self::WINDOW_HOURS));
            });
        });
    }

    private static function shape(Collection $links, string $role): Collection
    {
        return $links
            ->filter(fn (Link $link) => $link->icsData && !self::isCancelled($link))
            ->map(fn (Link $link) => [
                'link'      => $link,
                'ics'       => $link->icsData,
                'role'      => $role,
                'live'      => $link->icsData->start_date <= now(),
                'starts_at' => $link->icsData->start_date,
            ])
            ->values();
    }

    /**
     * A cancelled event is still a row with a date on it. Showing one on
     * the morning it would have started is worse than showing nothing.
     */
    private static function isCancelled(Link $link): bool
    {
        return !empty(((array) $link->settings)['event_cancelled']);
    }
}
