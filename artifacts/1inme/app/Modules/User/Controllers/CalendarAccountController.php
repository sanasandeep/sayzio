<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\CalendarAccount;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Services\Calendar\CalendarProviderRegistry;
use App\Modules\User\Services\Calendar\CalendarSyncService;
use App\Modules\User\Services\Calendar\GoogleCalendarProvider;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CalendarAccountController extends Controller
{
    public function __construct(
        protected CalendarProviderRegistry $registry,
        protected CalendarSyncService $sync,
    ) {}

    public function index(Request $request)
    {
        $accounts = CalendarAccount::where('user_id', workspace_owner_id())
            ->orderByDesc('id')->get();

        $googleConfigured = (new GoogleCalendarProvider())->isConfigured();

        return view('user.settings.calendar', [
            'accounts'         => $accounts,
            'googleConfigured' => $googleConfigured,
        ]);
    }

    /**
     * Events calendar — month / week / day / list views of every event
     * (Event-type Links + extra schedules attached to them) belonging to
     * the authenticated user.
     */
    public function events(Request $request)
    {
        $userId = workspace_owner_id();

        $upcoming = IcsData::query()
            ->whereHas('link', fn ($q) => $q->where('user_id', $userId))
            ->where('start_date', '>=', now()->subDay())
            ->orderBy('start_date')
            ->with('link:id,title,alias,type')
            ->limit(8)
            ->get();

        $totalEvents = Link::where('user_id', $userId)->where('type', 'ics')->count();

        return view('user.events.index', compact('upcoming', 'totalEvents'));
    }

    /**
     * JSON feed for FullCalendar. Returns one entry per primary event plus
     * one per `extra_schedules` row, scoped to the [start..end] window.
     */
    public function eventsFeed(Request $request)
    {
        $userId = workspace_owner_id();
        $start  = $request->query('start') ? Carbon::parse($request->query('start')) : now()->subMonth();
        $end    = $request->query('end')   ? Carbon::parse($request->query('end'))   : now()->addMonths(2);

        $rows = IcsData::query()
            ->whereHas('link', fn ($q) => $q->where('user_id', $userId))
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start, $end])
                  ->orWhereBetween('end_date',   [$start, $end])
                  ->orWhere(function ($q2) use ($start, $end) {
                      $q2->where('start_date', '<=', $start)->where('end_date', '>=', $end);
                  });
            })
            ->with('link:id,title,alias,type')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            if (!$r->link) continue;
            $out[] = [
                'id'      => 'l'.$r->link_id,
                'title'   => $r->event_name ?: $r->link->title,
                'start'   => optional($r->start_date)?->toIso8601String(),
                'end'     => optional($r->end_date)?->toIso8601String(),
                'allDay'  => (bool) $r->all_day,
                'url'     => route('user.links.show', $r->link),
                'extendedProps' => [
                    'location'    => $r->location,
                    'description' => $r->description,
                    'recurring'   => !empty($r->recurrence_freq),
                ],
                'color'   => '#7c3aed',
            ];

            // Extra schedules attached to this event.
            foreach ((array) $r->extra_schedules as $i => $ex) {
                if (empty($ex['start']) || empty($ex['end'])) continue;
                try {
                    $s = Carbon::parse($ex['start']);
                    $e = Carbon::parse($ex['end']);
                } catch (\Throwable $err) { continue; }
                if ($e < $start || $s > $end) continue;
                $out[] = [
                    'id'      => 'l'.$r->link_id.'-x'.$i,
                    'title'   => ($ex['label'] ?? $r->event_name) . ' (extra)',
                    'start'   => $s->toIso8601String(),
                    'end'     => $e->toIso8601String(),
                    'url'     => route('user.links.show', $r->link),
                    'extendedProps' => [
                        'location'    => $ex['location']    ?? $r->location,
                        'description' => $ex['description'] ?? null,
                        'extra'       => true,
                    ],
                    'color'   => '#ec4899',
                ];
            }
        }

        return response()->json($out);
    }

    public function connect(Request $request, string $provider)
    {
        if ($provider === 'microsoft' || $provider === 'caldav') {
            return back()->with('error', ucfirst($provider) . ' integration is coming soon.');
        }

        try {
            $driver = $this->registry->get($provider);
        } catch (\Throwable $e) {
            return back()->with('error', 'Unknown provider.');
        }

        $state = Str::random(40);
        $request->session()->put('calendar_oauth_state', [
            'state'    => $state,
            'provider' => $provider,
            'user_id'  => workspace_owner_id(),
        ]);

        $redirect = route('user.calendar.callback', ['provider' => $provider]);
        return redirect()->away($driver->authorizationUrl($state, $redirect));
    }

    public function callback(Request $request, string $provider)
    {
        $state = $request->query('state');
        $code  = $request->query('code');
        $err   = $request->query('error');

        $stored = $request->session()->pull('calendar_oauth_state');
        if (!$stored || $stored['state'] !== $state || $stored['provider'] !== $provider) {
            return redirect()->route('user.calendar.index')->with('error', 'Connection request expired or invalid. Please try again.');
        }
        if ($err || !$code) {
            return redirect()->route('user.calendar.index')->with('error', 'Authorization was cancelled or denied.');
        }

        try {
            $driver  = $this->registry->get($provider);
            $account = $driver->exchangeCode($stored['user_id'], $code, route('user.calendar.callback', ['provider' => $provider]));
            // Kick off an initial sync so the user sees events right away.
            $this->sync->syncAccount($account);
            return redirect()->route('user.calendar.index')->with('success', 'Calendar connected — your upcoming events are syncing now.');
        } catch (\Throwable $e) {
            Log::error('Calendar callback failed', ['err' => $e->getMessage()]);
            return redirect()->route('user.calendar.index')->with('error', 'Could not connect: ' . $e->getMessage());
        }
    }

    public function syncNow(Request $request, CalendarAccount $account)
    {
        abort_if($account->user_id !== workspace_owner_id(), 403);
        $stats = $this->sync->syncAccount($account);
        $msg = "Synced — {$stats['created']} new, {$stats['updated']} updated, {$stats['deleted']} removed.";
        if ($stats['errors']) $msg .= " ({$stats['errors']} errors — see logs.)";
        return back()->with('success', $msg);
    }

    public function update(Request $request, CalendarAccount $account)
    {
        abort_if($account->user_id !== workspace_owner_id(), 403);
        $account->update([
            'mirror_enabled' => $request->boolean('mirror_enabled'),
            'push_enabled'   => $request->boolean('push_enabled'),
            'display_name'   => $request->input('display_name', $account->display_name),
        ]);
        return back()->with('success', 'Calendar settings updated.');
    }

    public function destroy(Request $request, CalendarAccount $account)
    {
        abort_if($account->user_id !== workspace_owner_id(), 403);

        // Optionally delete mirrored Event Invite links (keep by default — user may want them).
        if ($request->boolean('purge_mirrored')) {
            foreach ($account->mirrors()->where('source', 'pull')->with('link')->get() as $m) {
                if ($m->link) $m->link->delete();
            }
        }
        $account->delete();
        return redirect()->route('user.calendar.index')->with('success', 'Calendar disconnected.');
    }
}
