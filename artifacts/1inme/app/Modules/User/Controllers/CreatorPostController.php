<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\FeedEvent;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\UserNotification;
use Illuminate\Http\Request;

class CreatorPostController extends Controller
{
    public function index()
    {
        $posts = CreatorPost::where('user_id', auth()->id())->latest()->paginate(20);
        return view('user.posts.index', compact('posts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:200',
            'body'  => 'required|string|max:5000',
            'image' => 'nullable|image|max:5120',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = '/storage/' . $request->file('image')->store('post-images', 'public');
        }

        $post = CreatorPost::create([
            'user_id' => auth()->id(),
            'title'   => $data['title'] ?? null,
            'body'    => $data['body'],
            'image'   => $imagePath,
        ]);

        $me = auth()->user();
        FeedEvent::create([
            'user_id'      => $me->id,
            'type'         => 'post',
            'subject_id'   => $post->id,
            'subject_type' => CreatorPost::class,
            'data'         => ['title' => $post->title, 'body_excerpt' => mb_substr($post->body, 0, 160), 'creator_name' => $me->name, 'creator_avatar' => $me->avatar],
            'occurred_at'  => now(),
        ]);

        $this->notifyFollowersDebounced($me, 'New post: ' . ($post->title ?: mb_substr($post->body, 0, 60)));

        return redirect()->route('user.posts.index')->with('success', 'Post published to your followers.');
    }

    public function destroy(CreatorPost $post)
    {
        abort_unless($post->user_id === auth()->id(), 403);
        $post->delete();
        return back()->with('success', 'Post deleted.');
    }

    /**
     * Record creator activity for each follower. Per-follower delivery is
     * controlled by their `follower_updates_mode` preference:
     *   - 'instant' : email immediately (and create the in-app notification)
     *   - 'digest'  : create the in-app notification; the daily
     *                 `followers:send-digest` job batches one email per day
     *   - 'off'     : create no in-app row and send no email
     *
     * The historical per-creator-per-day debounce was removed: the digest
     * mode (which is now the default) gives the same "at most one email per
     * day" behaviour without dropping in-app notifications.
     *
     * Method name kept for backwards compatibility with existing call sites
     * (LinkController, ProfileController, etc.).
     */
    public static function notifyFollowersDebounced($creator, string $message): void
    {
        $followerIds = Follow::where('creator_id', $creator->id)->pluck('follower_id');
        if ($followerIds->isEmpty()) return;

        $followers = \App\Modules\User\Models\User::whereIn('id', $followerIds)->get();

        foreach ($followers as $follower) {
            $mode = self::resolveFollowerMode($follower);
            if ($mode === 'off') continue;

            $notif = UserNotification::create([
                'user_id' => $follower->id,
                'type'    => 'follower_update',
                'data'    => [
                    'creator_id'     => (int) $creator->id,
                    'creator_name'   => $creator->name,
                    'creator_avatar' => $creator->avatar,
                    'message'        => $message,
                ],
                'created_at' => now(),
                'emailed_at' => null,
            ]);

            if ($mode === 'instant') {
                try {
                    \Mail::raw("{$creator->name}: {$message}", function ($m) use ($follower) {
                        $m->to($follower->email)->subject('New activity from a creator you follow');
                    });
                    $notif->emailed_at = now();
                    $notif->save();
                } catch (\Throwable $e) {}
            }
            // 'digest' mode: leave emailed_at null so the daily job picks it up.
        }
    }

    /**
     * Resolve the effective notification mode for a follower, with safe
     * fallback for users on rows that pre-date the new column.
     */
    private static function resolveFollowerMode($follower): string
    {
        $mode = $follower->follower_updates_mode ?? null;
        if (in_array($mode, ['instant', 'digest', 'off'], true)) return $mode;
        return $follower->notify_follower_updates ? 'digest' : 'off';
    }
}
