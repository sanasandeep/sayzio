<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\Link;
use Illuminate\Support\Carbon;

/**
 * Single source of truth for whether an `ics` event link is currently
 * discoverable in the public Events directory. Mirrors the exact
 * eligibility filters used by EventsDirectoryController::index() (type ics,
 * active, public visibility, not `hide_from_directory`, upcoming) so the
 * creator-facing nudges never contradict what actually shows on `/events`.
 *
 * This is a pure, stateless read helper — no persistent event↔directory
 * association is stored anywhere; discoverability is always recomputed.
 */
class EventDirectoryEligibility
{
    public static function isDiscoverable(Link $link): bool
    {
        return empty(static::reasons($link));
    }

    /**
     * Human-readable reasons this event is NOT currently discoverable in the
     * public Events directory. An empty array means it is discoverable.
     *
     * @return array<int, string>
     */
    public static function reasons(Link $link): array
    {
        if ($link->type !== 'ics') {
            return ['this link is not an event'];
        }

        $reasons = [];

        if (!$link->is_active) {
            $reasons[] = 'the event is currently inactive';
        }

        if ($link->visibility !== 'public') {
            $reasons[] = 'its visibility isn\'t set to Public';
        }

        // Match EventsDirectoryController's SQL exactly: only a literal
        // `true` (boolean or the string "true") counts as hidden, mirroring
        // `(settings->>'hide_from_directory') IS DISTINCT FROM 'true'`.
        $hideFromDirectory = $link->settings['hide_from_directory'] ?? false;
        if ($hideFromDirectory === true || $hideFromDirectory === 'true') {
            $reasons[] = '"Hide from /events directory" is turned on';
        }

        $link->loadMissing('icsData');
        $start = $link->icsData?->start_date;
        if (!$start) {
            $reasons[] = 'it has no scheduled start date yet';
        } elseif (Carbon::parse($start)->lt(now()->subDay())) {
            $reasons[] = 'it has already ended';
        }

        return $reasons;
    }

    /**
     * This owner's `ics` event links that are currently NOT discoverable in
     * the directory — used to power the reverse nudge banner on `/events`.
     * Small per-owner cardinality makes a straight fetch-then-filter (reusing
     * the exact same `reasons()` logic) simpler and safer than duplicating
     * the eligibility SQL, at the cost of loading all of the owner's events.
     *
     * @return \Illuminate\Support\Collection<int, Link>
     */
    public static function nonDiscoverableForOwner(int $ownerId)
    {
        return Link::query()
            ->where('user_id', $ownerId)
            ->where('type', 'ics')
            ->with('icsData')
            ->orderByDesc('created_at')
            ->get()
            ->reject(fn (Link $link) => static::isDiscoverable($link))
            ->values();
    }
}
