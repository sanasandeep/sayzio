<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\ViewerSession;
use App\Modules\User\Models\FeedEvent;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\UserNotification;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    /**
     * Resolve the current viewer/user. The feed page must be accessible by
     * either a dashboard-authenticated creator OR a viewer who signed in via
     * the OTP modal (ViewerSession). This is the core requirement that the
     * feed not be locked behind the dashboard guard.
     */
    protected function currentViewer()
    {
        return ViewerSession::user() ?: auth()->user();
    }

    public function index(Request $request)
    {
        $me = $this->currentViewer();
        if (!$me) {
            return redirect()->route('creators.index')
                ->with('viewer_login_required', true);
        }

        $followingIds = Follow::where('follower_id', $me->id)->pluck('creator_id');

        $events = FeedEvent::with('user')
            ->whereIn('user_id', $followingIds)
            ->orderByDesc('occurred_at')
            ->paginate(20);

        if ($request->wantsJson()) {
            return response()->json([
                'items'         => $events->items(),
                'next_page_url' => $events->nextPageUrl(),
            ]);
        }

        return view('user.feed.index', compact('events', 'me'));
    }

    public function markAllRead(Request $request)
    {
        $me = $this->currentViewer();
        if (!$me) return response()->json(['success' => false], 401);

        UserNotification::where('user_id', $me->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($request->wantsJson()) return response()->json(['success' => true]);
        return back()->with('success', 'All notifications marked as read.');
    }
}
