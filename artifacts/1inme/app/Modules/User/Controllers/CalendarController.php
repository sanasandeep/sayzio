<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Calendar;
use App\Modules\User\Models\CalendarEvent;
use App\Modules\User\Models\CalendarFollow;
use App\Modules\User\Models\Link;
use App\Modules\User\Support\ExportsCalendarEvents;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Creator-side management for the followable `calendar` link type. A calendar
 * is a user-owned, publishable collection of {@see CalendarEvent}s bridged
 * 1:1 to a `calendar` {@see Link}. This controller owns the event CRUD, the
 * per-calendar settings, and the cross-calendar "My Calendar" aggregation of
 * everything the user owns + follows.
 *
 * The link record itself (alias / visibility / SEO) is created through the
 * shared LinkController::store() flow; here we only manage the calendar
 * collection and its events.
 */
class CalendarController extends Controller
{
    use ExportsCalendarEvents;

    /** Resolve the Calendar for an owned `calendar` link or 404/403. */
    private function calendarForLink(Link $link): Calendar
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);
        abort_if($link->type !== Link::TYPE_CALENDAR, 404);

        $calendar = Calendar::firstOrCreate(
            ['link_id' => $link->id],
            [
                'user_id'  => $link->user_id,
                'title'    => $link->title ?: 'My Calendar',
                'slug'     => $link->alias,
                'timezone' => workspace_owner()->timezone ?: 'UTC',
            ]
        );

        if (!$link->calendar_id) {
            $link->calendar_id = $calendar->id;
            $link->save();
        }

        return $calendar;
    }

    /** Step 2 of link creation — name + alias + project + timezone. */
    public function create(Request $request)
    {
        $projects = workspace_owner()->projects()->orderBy('name')->get();
        $domains  = \App\Modules\User\Models\Domain::availableTo($request->user())->get();

        return view('user.links.create-calendar', [
            'projects'        => $projects,
            'domains'         => $domains,
            'defaultDomainId' => $domains->firstWhere('is_primary', true)?->id,
            'prefillAlias'    => (string) $request->query('alias', ''),
            'aliasLimits'     => workspace_owner()->getAliasLengthLimits(),
            'timezones'       => timezone_identifiers_list(),
            'defaultTimezone' => workspace_owner()->timezone ?: 'UTC',
        ]);
    }

    /** The event-management editor for one owned calendar. */
    public function editor(Request $request, Link $link)
    {
        $calendar = $this->calendarForLink($link);
        $calendar->load(['events']);

        $owner       = workspace_owner();
        $canSync     = (bool) $owner->getPlanFeature('calendar_sync', false);
        $syncAccount = $canSync
            ? \App\Modules\User\Models\CalendarAccount::where('user_id', $owner->id)
                ->where('provider', 'google')
                ->where('push_enabled', true)
                ->first()
            : null;

        return view('user.calendars.editor', [
            'link'        => $link,
            'calendar'    => $calendar,
            'events'      => $calendar->events,
            'timezones'   => timezone_identifiers_list(),
            'icsUrl'      => route('public.calendars.ics', $calendar->id),
            'canSync'     => $canSync,
            'syncAccount' => $syncAccount,
        ]);
    }

    /**
     * Two-way sync an owned calendar with a connected Google account, reusing
     * the existing CalendarSyncService / CalendarEventMirror machinery: pushes
     * every Sayzio event up, then pulls the owner's Google edits/deletes back
     * down. Plan-gated behind the `calendar_sync` feature; supports an optional
     * ?from / ?to date range for a partial sync.
     */
    public function syncToGoogle(Request $request, Link $link)
    {
        $calendar = $this->calendarForLink($link);
        $owner    = workspace_owner();

        if (!$owner->getPlanFeature('calendar_sync', false)) {
            return redirect()->route('user.upgrade')
                ->with('error', 'Google Calendar sync is available on a higher plan. Upgrade to push your calendar.');
        }

        $account = \App\Modules\User\Models\CalendarAccount::where('user_id', $owner->id)
            ->where('provider', 'google')
            ->where('push_enabled', true)
            ->first();

        if (!$account) {
            return back()->with('error', 'Connect a Google Calendar with sync enabled first, then try again.');
        }

        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : null;
        $to   = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : null;

        try {
            $result = app(\App\Modules\User\Services\Calendar\CalendarSyncService::class)
                ->syncCalendar($account, $calendar, $from, $to);
        } catch (\Throwable $e) {
            return back()->with('error', 'Google sync failed: ' . $e->getMessage());
        }

        $msg = "Synced with {$account->providerLabel()}: pushed {$result['pushed']} event(s)";
        $pulled = ($result['updated'] ?? 0) + ($result['deleted'] ?? 0);
        if ($pulled > 0) {
            $msg .= ", pulled {$result['updated']} edit(s)";
            if (($result['deleted'] ?? 0) > 0) {
                $msg .= " and removed {$result['deleted']} deleted event(s)";
            }
        }
        $msg .= '.';
        if (($result['failed'] ?? 0) > 0 || ($result['errors'] ?? 0) > 0) {
            $failed = ($result['failed'] ?? 0) + ($result['errors'] ?? 0);
            $msg .= " {$failed} item(s) couldn't be synced — try again later.";
        }

        return back()->with('success', $msg);
    }

    /** Update calendar-level settings (title / description / tz / accent / public). */
    public function updateSettings(Request $request, Link $link)
    {
        $calendar = $this->calendarForLink($link);

        $data = $request->validate([
            'title'        => 'required|string|max:120',
            'description'  => 'nullable|string|max:2000',
            'timezone'     => 'required|timezone',
            'accent_color' => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'is_public'    => 'nullable|boolean',
        ]);

        $calendar->update([
            'title'        => $data['title'],
            'description'  => $data['description'] ?? '',
            'timezone'     => $data['timezone'],
            'accent_color' => $data['accent_color'] ?? '#3d6bff',
            'is_public'    => $request->boolean('is_public'),
        ]);

        // Keep the bridged link's title in sync for share cards / dashboards.
        if ($link->title !== $data['title']) {
            $link->title = $data['title'];
            $link->save();
        }

        return back()->with('success', 'Calendar settings saved.');
    }

    /** Create one event in an owned calendar (enforces the per-plan event cap). */
    public function storeEvent(Request $request, Link $link)
    {
        $calendar = $this->calendarForLink($link);

        if ($cap = $this->eventCapError($calendar)) {
            return back()->withInput()->with('error', $cap);
        }

        $data = $this->validateEvent($request);
        $payload = $this->buildEventPayload($calendar, $data, $request);

        $calendar->events()->create($payload);

        return back()->with('success', 'Event added.');
    }

    /** Update one event in an owned calendar. */
    public function updateEvent(Request $request, Link $link, CalendarEvent $event)
    {
        $calendar = $this->calendarForLink($link);
        abort_if($event->calendar_id !== $calendar->id, 404);

        $data = $this->validateEvent($request);
        $event->update($this->buildEventPayload($calendar, $data, $request));

        return back()->with('success', 'Event updated.');
    }

    /** Delete one event from an owned calendar. */
    public function destroyEvent(Request $request, Link $link, CalendarEvent $event)
    {
        $calendar = $this->calendarForLink($link);
        abort_if($event->calendar_id !== $calendar->id, 404);

        $event->delete();

        return back()->with('success', 'Event removed.');
    }

    /**
     * "My Calendar" — a single agenda aggregating events from every calendar
     * the user owns OR follows, with optional date-range / hashtag / source
     * filters. The dashboard user is the follower identity here (the same
     * account also used by the public follow toggle when signed in).
     */
    public function myCalendar(Request $request)
    {
        $user = $request->user();

        $ownedIds = Calendar::where('user_id', $user->id)->pluck('id');
        $followedIds = CalendarFollow::where('follower_id', $user->id)->pluck('calendar_id');

        $source = $request->query('source', 'all');
        $calendarIds = match ($source) {
            'owned'    => $ownedIds,
            'followed' => $followedIds,
            default    => $ownedIds->merge($followedIds)->unique()->values(),
        };

        // Optional per-calendar filter — only honoured when the calendar is one
        // the user actually owns or follows.
        $calendarId = $request->query('calendar');
        if ($calendarId !== null && $calendarId !== '' && $calendarIds->contains((int) $calendarId)) {
            $calendarIds = collect([(int) $calendarId]);
        } else {
            $calendarId = '';
        }

        // Selected view (Month / Week / Day / Agenda) + the focus date the grid
        // is centred on. Both ride the query string so they survive navigation.
        $view = $request->query('view', 'agenda');
        if (!in_array($view, ['month', 'week', 'day', 'agenda'], true)) {
            $view = 'agenda';
        }

        $userTz = $user->timezone ?: config('app.timezone', 'UTC');
        try {
            $focus = $request->filled('date')
                ? Carbon::parse((string) $request->query('date'), $userTz)
                : Carbon::now($userTz);
        } catch (\Throwable) {
            $focus = Carbon::now($userTz);
        }
        $focus = $focus->startOfDay();

        $query = CalendarEvent::query()
            ->with('calendar:id,link_id,title,accent_color,user_id')
            ->whereIn('calendar_id', $calendarIds);

        $from = $request->query('from');
        $to   = $request->query('to');
        $past = $request->boolean('past');

        // Hashtag filter (single tag, normalized) — applies to every view.
        $tag = $request->query('tag');
        if ($tag) {
            $tag = CalendarEvent::normalizeHashtags($tag)[0] ?? null;
            if ($tag) {
                $query->whereJsonContains('hashtags', $tag);
            }
        }

        // Free-text search across title + description + location — every view.
        if ($search = trim((string) $request->query('q', ''))) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('title', 'ilike', $like)
                  ->orWhere('description', 'ilike', $like)
                  ->orWhere('location', 'ilike', $like);
            });
        }

        $events     = null;
        $gridEvents = collect();

        if ($view === 'agenda') {
            // Date range — default to "from now" so the agenda is forward-looking.
            if ($from) {
                $query->where('start_at', '>=', Carbon::parse($from)->startOfDay());
            } elseif (!$past) {
                $query->where('start_at', '>=', now()->startOfDay());
            }
            if ($to) {
                $query->where('start_at', '<=', Carbon::parse($to)->endOfDay());
            }

            $events = $query->orderBy('start_at')->orderBy('id')->paginate(40)->withQueryString();
        } else {
            // Grid views widen the date window to the visible period. The window
            // is computed in the viewer's tz, then padded a day on each side and
            // queried in UTC so events authored in other zones near a boundary
            // still land in the right cell (the blade only renders visible days).
            [$winStart, $winEnd] = match ($view) {
                'month' => [
                    $focus->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY),
                    $focus->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY),
                ],
                'week'  => [
                    $focus->copy()->startOfWeek(Carbon::SUNDAY),
                    $focus->copy()->endOfWeek(Carbon::SUNDAY),
                ],
                default => [ // day
                    $focus->copy()->startOfDay(),
                    $focus->copy()->endOfDay(),
                ],
            };

            $query->where('start_at', '>=', $winStart->copy()->subDay()->utc())
                  ->where('start_at', '<=', $winEnd->copy()->addDay()->utc());

            // Honour an explicit date-range filter on top of the window.
            if ($from) {
                $query->where('start_at', '>=', Carbon::parse($from)->startOfDay());
            } elseif (!$past) {
                // "Include past events" off (default) clamps the lower bound to
                // today — mirrors the agenda view so the filter behaves the same
                // across every view (e.g. earlier days of the current month grid
                // hide their past events unless the toggle is on).
                $query->where('start_at', '>=', now()->startOfDay());
            }
            if ($to) {
                $query->where('start_at', '<=', Carbon::parse($to)->endOfDay());
            }

            $gridEvents = $query->orderBy('start_at')->orderBy('id')->limit(2000)->get();
        }

        $calendars = Calendar::whereIn('id', $ownedIds->merge($followedIds)->unique())
            ->withCount('events')
            ->orderBy('title')
            ->get();

        // Distinct event tags across everything the user owns/follows, for the
        // toggleable tag-filter chips.
        $availableTags = CalendarEvent::whereIn('calendar_id', $ownedIds->merge($followedIds)->unique())
            ->whereNotNull('hashtags')
            ->pluck('hashtags')
            ->flatMap(fn ($h) => is_array($h) ? $h : [])
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->take(40);

        return view('user.calendars.my-calendar', [
            'events'        => $events,
            'gridEvents'    => $gridEvents,
            'calendars'     => $calendars,
            'ownedIds'      => $ownedIds,
            'followedIds'   => $followedIds,
            'view'          => $view,
            'focusDate'     => $focus,
            'userTz'        => $userTz,
            'availableTags' => $availableTags,
            'filters'       => [
                'source'   => $source,
                'calendar' => $calendarId,
                'from'     => $from,
                'to'       => $to,
                'tag'      => $tag,
                'q'        => $search ?? '',
                'past'     => $past,
            ],
        ]);
    }

    /**
     * Export the user's "My Calendar" events (honouring the same filters as
     * {@see myCalendar}) as a downloadable ICS or CSV file.
     *
     * Route: GET /user/my-calendar/export?format=ics|csv[&source=…&calendar=…&from=…&to=…&q=…&tag=…&past=…]
     */
    public function myCalendarExport(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $user = $request->user();

        $ownedIds    = Calendar::where('user_id', $user->id)->pluck('id');
        $followedIds = CalendarFollow::where('follower_id', $user->id)->pluck('calendar_id');

        $source      = $request->query('source', 'all');
        $calendarIds = match ($source) {
            'owned'    => $ownedIds,
            'followed' => $followedIds,
            default    => $ownedIds->merge($followedIds)->unique()->values(),
        };

        $calendarId = $request->query('calendar');
        if ($calendarId !== null && $calendarId !== '' && $calendarIds->contains((int) $calendarId)) {
            $calendarIds = collect([(int) $calendarId]);
        }

        $userTz = $user->timezone ?: config('app.timezone', 'UTC');

        // Resolve the view + focus date so the export matches the visible period —
        // mirrors the same logic myCalendar() uses for the grid window.
        $view = $request->query('view', 'agenda');
        if (!in_array($view, ['month', 'week', 'day', 'agenda'], true)) {
            $view = 'agenda';
        }

        try {
            $focus = $request->filled('date')
                ? Carbon::parse((string) $request->query('date'), $userTz)
                : Carbon::now($userTz);
        } catch (\Throwable) {
            $focus = Carbon::now($userTz);
        }
        $focus = $focus->startOfDay();

        $query = CalendarEvent::query()
            ->with('calendar:id,link_id,title,accent_color,user_id,timezone')
            ->whereIn('calendar_id', $calendarIds);

        if ($tag = $request->query('tag')) {
            $normalized = CalendarEvent::normalizeHashtags($tag)[0] ?? null;
            if ($normalized) {
                $query->whereJsonContains('hashtags', $normalized);
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

        $from = $request->query('from');
        $to   = $request->query('to');
        $past = $request->boolean('past');

        if ($view !== 'agenda') {
            // Constrain to the visible grid window (same as myCalendar's windowing).
            [$winStart, $winEnd] = match ($view) {
                'month' => [
                    $focus->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY),
                    $focus->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY),
                ],
                'week' => [
                    $focus->copy()->startOfWeek(Carbon::SUNDAY),
                    $focus->copy()->endOfWeek(Carbon::SUNDAY),
                ],
                default => [ // day
                    $focus->copy()->startOfDay(),
                    $focus->copy()->endOfDay(),
                ],
            };

            $query->where('start_at', '>=', $winStart->copy()->subDay()->utc())
                  ->where('start_at', '<=', $winEnd->copy()->addDay()->utc());

            // Explicit date-range filters further narrow within the window.
            if ($from) {
                $query->where('start_at', '>=', Carbon::parse($from)->startOfDay());
            } elseif (!$past) {
                $query->where('start_at', '>=', now()->startOfDay());
            }
            if ($to) {
                $query->where('start_at', '<=', Carbon::parse($to)->endOfDay());
            }
        } else {
            // Agenda: open-ended range, default to forward-looking.
            if ($from) {
                $query->where('start_at', '>=', Carbon::parse($from)->startOfDay());
            } elseif (!$past) {
                $query->where('start_at', '>=', now()->startOfDay());
            }
            if ($to) {
                $query->where('start_at', '<=', Carbon::parse($to)->endOfDay());
            }
        }

        $events = $query->orderBy('start_at')->orderBy('id')->limit(5000)->get();

        $format = strtolower((string) $request->query('format', 'ics'));
        if (!in_array($format, ['ics', 'csv'], true)) {
            $format = 'ics';
        }

        $slug      = 'my-calendar-' . $view . '-' . $focus->format('Y-m-d');
        $filename  = $slug . '.' . $format;

        if ($format === 'csv') {
            return $this->exportCalendarCsv($events, $filename, $userTz);
        }

        return $this->exportCalendarIcs($events, $filename, $userTz, $user->name ?? 'My Calendar');
    }

    // ── helpers ───────────────────────────────────────────────────────────

    private function validateEvent(Request $request): array
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
            'params_json' => 'nullable|string|max:5000',
        ]);
    }

    /** Map validated input + tz into the persisted event attributes. */
    private function buildEventPayload(Calendar $calendar, array $data, Request $request): array
    {
        $tz = $data['timezone'] ?? $calendar->effectiveTimezone();

        $params = null;
        if (!empty($data['params_json'])) {
            $decoded = json_decode($data['params_json'], true);
            if (is_array($decoded)) {
                $params = $decoded;
            }
        }

        $lat = $data['lat'] ?? null;
        $lng = $data['lng'] ?? null;

        return [
            'user_id'     => $calendar->user_id,
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            // Store as the authored wall-clock instant in its own tz; cast to UTC on save.
            'start_at'    => Carbon::parse($data['start_at'], $tz),
            'end_at'      => !empty($data['end_at']) ? Carbon::parse($data['end_at'], $tz) : null,
            'all_day'     => $request->boolean('all_day'),
            'timezone'    => $tz,
            'location'    => $data['location'] ?? null,
            'lat'         => $lat === '' ? null : $lat,
            'lng'         => $lng === '' ? null : $lng,
            'hashtags'    => CalendarEvent::normalizeHashtags($data['hashtags'] ?? ''),
            'payment_url' => $data['payment_url'] ?? null,
            'params'      => $params,
        ];
    }

    /** Per-plan event cap guard — null when allowed, else an upgrade message. */
    private function eventCapError(Calendar $calendar): ?string
    {
        $owner = workspace_owner();
        $cap = (int) $owner->getPlanFeature('max_calendar_events', -1);
        if ($cap < 0) {
            return null;
        }
        if ($calendar->events()->count() >= $cap) {
            return "You've reached the {$cap}-event limit for a calendar on your current plan. Upgrade to add more.";
        }

        return null;
    }
}
