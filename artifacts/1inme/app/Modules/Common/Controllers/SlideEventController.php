<?php

namespace App\Modules\Common\Controllers;

use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkSlideDeck;
use App\Modules\User\Models\LinkSlideViewEvent;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SlideEventController extends Controller
{
    /** Anonymous slide-view event from the web slides viewer. */
    public function view(Request $request, string $alias)
    {
        $link = Link::withoutGlobalScope('workspace')
            ->where('alias', $alias)->where('type', 'biolink')->first();
        if (!$link || !$link->is_active) abort(404);
        if (!$link->isAccessible())     abort(404);

        if (!$this->viewerCanSee($link, $request->user())) {
            return response()->json(['ok' => true, 'tracked' => false]);
        }

        $data = $request->validate([
            'slide_index'     => ['required', 'integer', 'min:0', 'max:200'],
            'page_session_id' => ['nullable', 'string', 'max:60'],
            'completed'       => ['nullable', 'boolean'],
        ]);

        $deck = LinkSlideDeck::withoutGlobalScope('workspace')
            ->where('link_id', $link->id)->where('is_published', true)->first();
        if (!$deck) return response()->json(['ok' => true, 'tracked' => false]);

        try {
            LinkSlideViewEvent::create([
                'deck_id'         => $deck->id,
                'link_id'         => $link->id,
                'slide_index'     => (int) $data['slide_index'],
                'completed'       => (bool) ($data['completed'] ?? false),
                'page_session_id' => $data['page_session_id'] ?? null,
                'source'          => 'web',
            ]);
        } catch (\Throwable $e) {
            logger()->warning('SlideEventController::view failed: ' . $e->getMessage());
        }

        return response()->json(['ok' => true, 'tracked' => true]);
    }

    /**
     * Mirrors BiolinkController::checkVisibility so analytics from the
     * web viewer respects the same registered/followers/subscribers
     * gates as the API. Returns true when the viewer is allowed.
     */
    protected function viewerCanSee(Link $link, $viewer): bool
    {
        $vis = $link->visibility ?? 'public';
        if ($vis === 'public') return true;

        $owner = $link->user;
        if ($viewer && $owner && (int) $viewer->id === (int) $owner->id) return true;
        if (!$viewer) return false;
        if ($vis === 'registered') return true;

        if ($vis === 'followers') {
            return Follow::where('follower_id', $viewer->id)
                ->where('creator_id', $owner->id)->exists();
        }
        if ($vis === 'subscribers') {
            return Subscriber::where('user_id', $owner->id)
                ->where('email', $viewer->email)
                ->where('status', 'active')->exists();
        }
        return false;
    }
}
