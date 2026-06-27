<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\ViewerSession;
use App\Modules\User\Models\Calendar;
use App\Modules\User\Models\CalendarFollow;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Support\CalendarIcs;
use Illuminate\Http\Request;

/**
 * Public-facing endpoints for the followable `calendar` link type: the
 * follow/unfollow toggle (ViewerSession OR dashboard auth) and the ICS feed
 * that calendar apps subscribe to. The public page itself is rendered by
 * RedirectController::handleCalendarPage().
 */
class PublicCalendarController extends Controller
{
    /** Follow / unfollow a public calendar (mirrors the creator follow flow). */
    public function toggleFollow(Request $request, int $calendar)
    {
        $me = ViewerSession::user() ?? $request->user();
        if (!$me) {
            return response()->json(['success' => false, 'message' => 'Not signed in.'], 401);
        }

        $cal = Calendar::find($calendar);
        if (!$cal) {
            return response()->json(['success' => false], 404);
        }

        if (!$cal->is_public) {
            return response()->json(['success' => false, 'message' => 'This calendar is not accepting followers.'], 403);
        }

        if ((int) $cal->user_id === (int) $me->id) {
            return response()->json(['success' => false, 'message' => "You can't follow your own calendar."], 422);
        }

        $existing = CalendarFollow::where('calendar_id', $cal->id)
            ->where('follower_id', $me->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $cal->decrement('followers_count');

            return response()->json([
                'success'         => true,
                'following'       => false,
                'followers_count' => max(0, (int) $cal->followers_count),
            ]);
        }

        CalendarFollow::create([
            'calendar_id' => $cal->id,
            'follower_id' => $me->id,
            'created_at'  => now(),
        ]);
        $cal->increment('followers_count');

        // In-app notification to the calendar owner (best-effort).
        try {
            UserNotification::create([
                'user_id' => $cal->user_id,
                'type'    => 'calendar.new_follower',
                'data'    => [
                    'message'       => $me->name . ' followed your calendar "' . $cal->title . '"',
                    'calendar_id'   => $cal->id,
                    'follower_id'   => $me->id,
                    'follower_name' => $me->name,
                ],
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) { /* never block the follow */ }

        return response()->json([
            'success'         => true,
            'following'       => true,
            'followers_count' => (int) $cal->followers_count,
        ]);
    }

    /**
     * Serve the calendar as an ICS feed. Public calendars are open; private
     * ones still serve the feed so the owner can subscribe with the same URL,
     * but they 404 to anonymous visitors who aren't the owner.
     */
    public function icsFeed(Request $request, int $calendar)
    {
        $cal = Calendar::with('events')->find($calendar);
        if (!$cal) {
            abort(404);
        }

        if (!$cal->is_public) {
            $viewer = ViewerSession::user() ?? $request->user();
            abort_unless($viewer && (int) $viewer->id === (int) $cal->user_id, 404);
        }

        // Optional partial export: ?from=YYYY-MM-DD&to=YYYY-MM-DD (inclusive).
        $from = null;
        $to   = null;
        if ($raw = $request->query('from')) {
            try { $from = \Illuminate\Support\Carbon::parse($raw)->startOfDay(); } catch (\Throwable $e) { $from = null; }
        }
        if ($raw = $request->query('to')) {
            try { $to = \Illuminate\Support\Carbon::parse($raw)->endOfDay(); } catch (\Throwable $e) { $to = null; }
        }

        $content  = CalendarIcs::build($cal, $from, $to);
        $filename = preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string) ($cal->slug ?: $cal->title)) . '.ics';

        return response($content, 200, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
            'Cache-Control'       => 'public, max-age=900',
        ]);
    }
}
