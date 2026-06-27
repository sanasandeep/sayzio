<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\Calendar;
use App\Modules\User\Models\CalendarEvent;
use App\Modules\User\Models\CalendarFollow;
use App\Modules\User\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;

/**
 * Mobile parity for the followable `calendar` link type. Covers the calendars
 * the signed-in user owns + follows, the cross-calendar "My Calendar" agenda
 * (mirrors the web {@see \App\Modules\User\Controllers\CalendarController::myCalendar}),
 * today's-event reminders, and the public follow toggle (mirrors the web
 * {@see \App\Modules\Common\Controllers\PublicCalendarController::toggleFollow}).
 *
 * Stateless Sanctum: the bearer-token user IS the follower identity, so there
 * is no ViewerSession here. Event times are stored UTC and surfaced as ISO-8601.
 */
class MyCalendarController extends Controller
{
    use ApiResponses;

    /** Calendars the user owns or follows, with counts + following flag. */
    public function index(Request $request)
    {
        $user = $request->user();

        $ownedIds    = Calendar::where('user_id', $user->id)->pluck('id');
        $followedIds = CalendarFollow::where('follower_id', $user->id)->pluck('calendar_id');

        $calendars = Calendar::whereIn('id', $ownedIds->merge($followedIds)->unique())
            ->withCount('events')
            ->orderBy('title')
            ->get();

        $followedSet = $followedIds->flip();

        return $this->ok([
            'items' => $calendars->map(fn (Calendar $c) => [
                'id'              => $c->id,
                'title'           => $c->title,
                'description'     => $c->description,
                'timezone'        => $c->effectiveTimezone(),
                'accent_color'    => $c->accent_color,
                'is_public'       => (bool) $c->is_public,
                'followers_count' => (int) $c->followers_count,
                'events_count'    => (int) $c->events_count,
                'is_owner'        => (int) $c->user_id === (int) $user->id,
                'is_following'    => $followedSet->has($c->id),
            ])->all(),
        ]);
    }

    /** A single public (or owned) calendar with its upcoming events. */
    public function show(Request $request, int $calendar)
    {
        $user = $request->user();
        $cal  = Calendar::withCount('events')->find($calendar);
        if (!$cal) {
            return $this->notFound('Calendar not found');
        }

        $isOwner = (int) $cal->user_id === (int) $user->id;
        if (!$cal->is_public && !$isOwner) {
            return $this->notFound('Calendar not found');
        }

        $events = $cal->events()
            ->when(!$request->boolean('past'), fn ($q) => $q->where('start_at', '>=', now()->startOfDay()))
            ->orderBy('start_at')->orderBy('id')
            ->limit(200)->get();

        return $this->ok([
            'calendar' => [
                'id'              => $cal->id,
                'title'           => $cal->title,
                'description'     => $cal->description,
                'timezone'        => $cal->effectiveTimezone(),
                'accent_color'    => $cal->accent_color,
                'is_public'       => (bool) $cal->is_public,
                'followers_count' => (int) $cal->followers_count,
                'events_count'    => (int) $cal->events_count,
                'is_owner'        => $isOwner,
                'is_following'    => $cal->isFollowedBy($user),
                'ics_url'         => route('public.calendars.ics', $cal->id),
            ],
            'events' => $events->map(fn (CalendarEvent $e) => $this->event($e))->all(),
        ]);
    }

    /** Follow / unfollow a public calendar (mirrors the web toggle). */
    public function toggleFollow(Request $request, int $calendar)
    {
        $user = $request->user();
        $cal  = Calendar::find($calendar);
        if (!$cal) {
            return $this->notFound('Calendar not found');
        }
        if (!$cal->is_public) {
            return $this->forbidden('This calendar is not accepting followers.');
        }
        if ((int) $cal->user_id === (int) $user->id) {
            return $this->fail("You can't follow your own calendar.", 422, 'cannot_follow_self');
        }

        $existing = CalendarFollow::where('calendar_id', $cal->id)
            ->where('follower_id', $user->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $cal->decrement('followers_count');

            return $this->ok([
                'following'       => false,
                'followers_count' => max(0, (int) $cal->followers_count),
            ]);
        }

        CalendarFollow::create([
            'calendar_id' => $cal->id,
            'follower_id' => $user->id,
            'created_at'  => now(),
        ]);
        $cal->increment('followers_count');

        try {
            UserNotification::create([
                'user_id' => $cal->user_id,
                'type'    => 'calendar.new_follower',
                'data'    => [
                    'message'       => $user->name . ' followed your calendar "' . $cal->title . '"',
                    'calendar_id'   => $cal->id,
                    'follower_id'   => $user->id,
                    'follower_name' => $user->name,
                ],
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) { /* never block the follow */ }

        return $this->ok([
            'following'       => true,
            'followers_count' => (int) $cal->followers_count,
        ]);
    }

    /**
     * "My Calendar" agenda — events from every calendar the user owns OR
     * follows, with the same source / date / hashtag / search filters as web.
     */
    public function feed(Request $request)
    {
        $user = $request->user();

        $ownedIds    = Calendar::where('user_id', $user->id)->pluck('id');
        $followedIds = CalendarFollow::where('follower_id', $user->id)->pluck('calendar_id');

        $source = $request->query('source', 'all');
        $calendarIds = match ($source) {
            'owned'    => $ownedIds,
            'followed' => $followedIds,
            default    => $ownedIds->merge($followedIds)->unique()->values(),
        };

        // Optional per-calendar filter — only when owned or followed.
        $calendarId = $request->query('calendar');
        if ($calendarId !== null && $calendarId !== '' && $calendarIds->contains((int) $calendarId)) {
            $calendarIds = collect([(int) $calendarId]);
        }

        $query = CalendarEvent::query()
            ->with('calendar:id,title,accent_color,user_id')
            ->whereIn('calendar_id', $calendarIds);

        $from = $request->query('from');
        $to   = $request->query('to');
        if ($from) {
            $query->where('start_at', '>=', Carbon::parse($from)->startOfDay());
        } elseif (!$request->boolean('past')) {
            $query->where('start_at', '>=', now()->startOfDay());
        }
        if ($to) {
            $query->where('start_at', '<=', Carbon::parse($to)->endOfDay());
        }

        if ($tag = $request->query('tag')) {
            $tag = CalendarEvent::normalizeHashtags($tag)[0] ?? null;
            if ($tag) {
                $query->whereJsonContains('hashtags', $tag);
            }
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('title', 'ilike', $like)
                  ->orWhere('description', 'ilike', $like)
                  ->orWhere('location', 'ilike', $like);
            });
        }

        $page = $query->orderBy('start_at')->orderBy('id')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 40))));

        return $this->ok([
            'items' => collect($page->items())->map(fn (CalendarEvent $e) => $this->event($e))->all(),
            'meta'  => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }

    /**
     * Today's events across owned + followed calendars (the same set the
     * in-app reminder command notifies on), in the user's local timezone.
     */
    public function today(Request $request)
    {
        $user = $request->user();
        $tz   = $user->timezone ?: 'UTC';
        $now  = Carbon::now($tz);

        $ownedIds    = Calendar::where('user_id', $user->id)->pluck('id');
        $followedIds = CalendarFollow::where('follower_id', $user->id)->pluck('calendar_id');
        $calendarIds = $ownedIds->merge($followedIds)->unique()->values();

        $events = CalendarEvent::query()
            ->with('calendar:id,title,accent_color,user_id')
            ->whereIn('calendar_id', $calendarIds)
            ->whereBetween('start_at', [
                $now->copy()->startOfDay()->timezone('UTC'),
                $now->copy()->endOfDay()->timezone('UTC'),
            ])
            ->orderBy('start_at')->orderBy('id')
            ->get();

        return $this->ok([
            'date'  => $now->toDateString(),
            'items' => $events->map(fn (CalendarEvent $e) => $this->event($e))->all(),
        ]);
    }

    /** Serialize one event for the mobile client. */
    private function event(CalendarEvent $e): array
    {
        return [
            'id'           => $e->id,
            'calendar_id'  => $e->calendar_id,
            'title'        => $e->title,
            'description'  => $e->description,
            'start_at'     => optional($e->start_at)->toIso8601String(),
            'end_at'       => optional($e->end_at)->toIso8601String(),
            'all_day'      => (bool) $e->all_day,
            'timezone'     => $e->effectiveTimezone(),
            'location'     => $e->location,
            'lat'          => $e->lat,
            'lng'          => $e->lng,
            'hashtags'     => $e->hashtags ?? [],
            'payment_url'  => $e->payment_url,
            'params'       => $e->params,
            'calendar'     => $e->relationLoaded('calendar') && $e->calendar ? [
                'id'           => $e->calendar->id,
                'title'        => $e->calendar->title,
                'accent_color' => $e->calendar->accent_color,
            ] : null,
        ];
    }
}
