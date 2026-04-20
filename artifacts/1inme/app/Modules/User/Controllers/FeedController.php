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

        // Publish any due scheduled posts from creators this viewer follows so
        // their feed reflects scheduled content as soon as it goes live.
        \App\Modules\User\Models\CreatorPost::whereIn('user_id', $followingIds)
            ->dueForPublish()
            ->get()
            ->each(function ($p) {
                \App\Modules\User\Models\CreatorPost::publishDuePosts($p->user_id);
            });

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

        // Pinned posts from followed creators (one per creator, most recent pin first).
        $pinnedPosts = \App\Modules\User\Models\CreatorPost::with('user')
            ->whereIn('user_id', $followingIds)
            ->whereNotNull('pinned_at')
            ->whereNotNull('published_at')
            ->orderByDesc('pinned_at')
            ->limit(10)
            ->get();

        return view('user.feed.index', compact('events', 'me', 'pinnedPosts'));
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
