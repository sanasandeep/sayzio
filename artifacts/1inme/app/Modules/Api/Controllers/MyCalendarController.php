<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\Calendar;
use App\Modules\User\Models\CalendarEvent;
use App\Modules\User\Models\CalendarFollow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Support\ExportsCalendarEvents;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
    use ExportsCalendarEvents;

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

    /**
     * Task #6477 — read the per-user mirror toggles (task due dates / note
     * reminders → personal "Tasks & Reminders" calendar). Mobile parity for
     * the web My Calendar preferences form.
     */
    public function mirrorPreferences(Request $request)
    {
        return $this->ok(
            \App\Modules\User\Support\PersonalCalendarSync::preferences($request->user())
        );
    }

    /** Task #6477 — update the mirror toggles (removes/backfills mirrored events). */
    public function updateMirrorPreferences(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'task_due_dates' => ['sometimes', 'boolean'],
            'note_reminders' => ['sometimes', 'boolean'],
        ]);

        $current = \App\Modules\User\Support\PersonalCalendarSync::preferences($user);

        \App\Modules\User\Support\PersonalCalendarSync::applyPreferences(
            $user,
            (bool) ($data['task_due_dates'] ?? $current['task_due_dates']),
            (bool) ($data['note_reminders'] ?? $current['note_reminders']),
        );

        return $this->ok(
            \App\Modules\User\Support\PersonalCalendarSync::preferences($user->fresh())
        );
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
     * Export the "My Calendar" agenda as a downloadable ICS or CSV file,
     * honouring the same source / date / hashtag / search filters as {@see feed}.
     * Mobile parity for the web
     * {@see \App\Modules\User\Controllers\CalendarController::myCalendarExport}.
     *
     * Returns a StreamedResponse (not the JSON envelope) so the client can
     * download the file directly and hand it to the native share sheet.
     */
    public function export(Request $request)
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

        $userTz = $user->timezone ?: config('app.timezone', 'UTC');

        $query = CalendarEvent::query()
            ->with('calendar:id,title,accent_color,user_id,timezone')
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

        $events = $query->orderBy('start_at')->orderBy('id')->limit(5000)->get();

        $format = strtolower((string) $request->query('format', 'ics'));
        if (!in_array($format, ['ics', 'csv'], true)) {
            $format = 'ics';
        }

        $filename = 'my-calendar-' . now($userTz)->format('Y-m-d') . '.' . $format;

        if ($format === 'csv') {
            return $this->exportCalendarCsv($events, $filename, $userTz);
        }

        return $this->exportCalendarIcs($events, $filename, $userTz, $user->name ?? 'My Calendar');
    }

    /**
     * Today's events across owned + followed calendars (the same set the
     * in-app reminder command notifies on), in the user's local timezone.
     */
    public function today(Request $request)
    {
        $user = $request->user();
        $tz   = \App\Support\PlatformTimezone::forUser($user);
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

    /**
     * Create a new followable calendar from mobile. Mirrors the web flow where
     * a `calendar` Link is bridged 1:1 to a {@see Calendar}: the API
     * {@see LinkController::store} deliberately doesn't allow the `calendar`
     * type, so we create the link + calendar together here.
     *
     * Plan-gated: requires the `module_calendar` feature and respects the
     * `max_calendars` cap (counting calendars the user already owns).
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user->planFeatureEnabled('module_calendar')) {
            return $this->planGate(
                "Publishing your own followable calendar isn't available on your current plan.",
                'module_calendar',
                $user
            );
        }

        $cap = (int) $user->getPlanFeature('max_calendars', -1);
        if ($cap >= 0) {
            $owned = Calendar::where('user_id', $user->id)->count();
            if ($owned >= $cap) {
                return $this->planGate(
                    "Your plan includes {$cap} calendar" . ($cap === 1 ? '' : 's') . '. Upgrade to publish more.',
                    'max_calendars',
                    $user,
                    402,
                    'plan_limit_reached',
                    $owned
                );
            }
        }

        $data = $request->validate([
            'title'        => 'required|string|max:120',
            'description'  => 'nullable|string|max:2000',
            'timezone'     => 'required|timezone',
            'accent_color' => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'is_public'    => 'nullable|boolean',
        ]);

        $alias = Str::lower(Str::random(7));
        while (Link::where('alias', $alias)->exists()) {
            $alias = Str::lower(Str::random(7));
        }

        $workspaceId = $this->resolveWorkspaceId($user);

        $calendar = DB::transaction(function () use ($user, $data, $alias, $workspaceId, $request) {
            $link = new Link([
                'user_id'    => $user->id,
                'type'       => Link::TYPE_CALENDAR,
                'alias'      => $alias,
                'title'      => $data['title'],
                'visibility' => 'public',
                'is_active'  => true,
            ]);
            // The Sanctum path skips SetActiveWorkspace, so set workspace_id
            // directly (it is intentionally not mass-assignable) or the calendar
            // link would be hidden from the workspace-scoped web list.
            if ($workspaceId !== null && Schema::hasColumn('links', 'workspace_id')) {
                $link->workspace_id = $workspaceId;
            }
            $link->save();

            $calendar = Calendar::create([
                'link_id'      => $link->id,
                'user_id'      => $user->id,
                'title'        => $data['title'],
                'slug'         => $alias,
                'description'  => $data['description'] ?? '',
                'timezone'     => $data['timezone'],
                'accent_color' => $data['accent_color'] ?? '#3d6bff',
                'is_public'    => $request->boolean('is_public'),
            ]);

            if (Schema::hasColumn('links', 'calendar_id')) {
                $link->calendar_id = $calendar->id;
                $link->save();
            }

            return $calendar;
        });

        $calendar->loadCount('events');

        return $this->created(['calendar' => $this->summary($calendar, $user)]);
    }

    /** Update an owned calendar's settings (mirrors web updateSettings). */
    public function update(Request $request, int $calendar)
    {
        $user = $request->user();
        $cal  = $this->ownedCalendar($calendar, $user);
        if (!$cal instanceof Calendar) {
            return $cal; // notFound / forbidden response
        }

        $data = $request->validate([
            'title'        => 'required|string|max:120',
            'description'  => 'nullable|string|max:2000',
            'timezone'     => 'required|timezone',
            'accent_color' => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'is_public'    => 'nullable|boolean',
        ]);

        $cal->update([
            'title'        => $data['title'],
            'description'  => $data['description'] ?? '',
            'timezone'     => $data['timezone'],
            'accent_color' => $data['accent_color'] ?? '#3d6bff',
            'is_public'    => $request->boolean('is_public'),
        ]);

        // Keep the bridged link's title in sync for share cards / dashboards.
        $link = $cal->link;
        if ($link && $link->title !== $data['title']) {
            $link->title = $data['title'];
            $link->save();
        }

        $cal->loadCount('events');

        return $this->ok(['calendar' => $this->summary($cal, $user)]);
    }

    /** Add an event to an owned calendar (mirrors web storeEvent). */
    public function storeEvent(Request $request, int $calendar)
    {
        $user = $request->user();
        $cal  = $this->ownedCalendar($calendar, $user);
        if (!$cal instanceof Calendar) {
            return $cal;
        }

        $cap = (int) $user->getPlanFeature('max_calendar_events', -1);
        if ($cap >= 0 && $cal->events()->count() >= $cap) {
            return $this->planGate(
                "You've reached the {$cap}-event limit for a calendar on your current plan. Upgrade to add more.",
                'max_calendar_events',
                $user,
                402,
                'plan_limit_reached',
                $cal->events()->count()
            );
        }

        $data  = $this->validateEventInput($request);
        $event = $cal->events()->create($this->buildEventPayload($cal, $data, $request));

        return $this->created(['event' => $this->event($event)]);
    }

    /** Edit an event on an owned calendar (mirrors web updateEvent). */
    public function updateEvent(Request $request, int $calendar, int $event)
    {
        $user = $request->user();
        $cal  = $this->ownedCalendar($calendar, $user);
        if (!$cal instanceof Calendar) {
            return $cal;
        }

        $model = $cal->events()->find($event);
        if (!$model) {
            return $this->notFound('Event not found');
        }

        $data = $this->validateEventInput($request);
        $model->update($this->buildEventPayload($cal, $data, $request));

        return $this->ok(['event' => $this->event($model->fresh())]);
    }

    /** Delete an event from an owned calendar (mirrors web destroyEvent). */
    public function destroyEvent(Request $request, int $calendar, int $event)
    {
        $user = $request->user();
        $cal  = $this->ownedCalendar($calendar, $user);
        if (!$cal instanceof Calendar) {
            return $cal;
        }

        $model = $cal->events()->find($event);
        if (!$model) {
            return $this->notFound('Event not found');
        }

        $model->delete();

        return $this->ok(['deleted' => true]);
    }

    /**
     * Resolve a calendar the signed-in user OWNS, or return the appropriate
     * error JsonResponse (404 when missing, 403 when not theirs). API writes
     * are owner-only — following a calendar never grants edit rights.
     */
    private function ownedCalendar(int $calendar, $user)
    {
        $cal = Calendar::find($calendar);
        if (!$cal) {
            return $this->notFound('Calendar not found');
        }
        if ((int) $cal->user_id !== (int) $user->id) {
            return $this->forbidden('You can only manage calendars you own.');
        }

        return $cal;
    }

    /** Shared validation for create/update event (mirrors web validateEvent). */
    private function validateEventInput(Request $request): array
    {
        return $request->validate([
            'title'       => 'required|string|max:160',
            'description' => 'nullable|string|max:5000',
            'start_at'    => 'required|date',
            'end_at'      => 'nullable|date|after_or_equal:start_at',
            'all_day'     => 'nullable|boolean',
            'timezone'    => 'nullable|timezone',
            'location'    => 'nullable|string|max:255',
            'lat'         => 'nullable|numeric|between:-90,90',
            'lng'         => 'nullable|numeric|between:-180,180',
            'hashtags'    => 'nullable|string|max:500',
            'payment_url' => 'nullable|url|max:2048',
        ]);
    }

    /**
     * Build a CalendarEvent attribute payload from validated input. Mirrors the
     * web buildEventPayload: start/end are parsed as wall-clock in the event's
     * timezone, then cast to UTC on save (so Google push sync via
     * CalendarEventMirror keeps working unchanged).
     */
    private function buildEventPayload(Calendar $calendar, array $data, Request $request): array
    {
        $tz  = $data['timezone'] ?? $calendar->effectiveTimezone();
        $lat = $data['lat'] ?? null;
        $lng = $data['lng'] ?? null;

        return [
            'user_id'     => $calendar->user_id,
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'start_at'    => Carbon::parse($data['start_at'], $tz),
            'end_at'      => !empty($data['end_at']) ? Carbon::parse($data['end_at'], $tz) : null,
            'all_day'     => $request->boolean('all_day'),
            'timezone'    => $tz,
            'location'    => $data['location'] ?? null,
            'lat'         => $lat === '' ? null : $lat,
            'lng'         => $lng === '' ? null : $lng,
            'hashtags'    => CalendarEvent::normalizeHashtags($data['hashtags'] ?? ''),
            'payment_url' => $data['payment_url'] ?? null,
        ];
    }

    /** Serialize a calendar summary for write responses (matches index shape). */
    private function summary(Calendar $cal, $user): array
    {
        return [
            'id'              => $cal->id,
            'title'           => $cal->title,
            'description'     => $cal->description,
            'timezone'        => $cal->effectiveTimezone(),
            'accent_color'    => $cal->accent_color,
            'is_public'       => (bool) $cal->is_public,
            'followers_count' => (int) $cal->followers_count,
            'events_count'    => (int) $cal->events_count,
            'is_owner'        => (int) $cal->user_id === (int) $user->id,
            'is_following'    => false,
            'ics_url'         => route('public.calendars.ics', $cal->id),
        ];
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
