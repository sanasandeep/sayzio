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
        // Lazily publish any due scheduled posts in this workspace before listing.
        // workspace_owner is bound by SetActiveWorkspace; falls back to auth user.
        $ownerId = app()->bound('workspace_owner') ? app('workspace_owner')->id : auth()->id();
        CreatorPost::publishDuePosts($ownerId);

        $posts = CreatorPost::query()
            ->orderByDesc('pinned_at')
            ->orderByDesc('created_at')
            ->paginate(20);
        return view('user.posts.index', compact('posts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'        => 'nullable|string|max:200',
            'body'         => 'required|string|max:5000',
            'image'        => 'nullable|image|max:5120',
            'scheduled_at' => 'nullable|date|after:now',
            'is_pinned'    => 'nullable|boolean',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = '/storage/' . $request->file('image')->store('post-images', 'public');
        }

        $scheduledAt = !empty($data['scheduled_at']) ? \Carbon\Carbon::parse($data['scheduled_at']) : null;
        $isFuture = $scheduledAt && $scheduledAt->isFuture();

        // Attribute the post to the workspace owner (so existing per-creator
        // queries — feed events, follower notifications — keep working) while
        // workspace_id + created_by_user_id are auto-filled by the trait.
        $ownerId = app()->bound('workspace_owner') ? app('workspace_owner')->id : auth()->id();
        $post = CreatorPost::create([
            'user_id'      => $ownerId,
            'title'        => $data['title'] ?? null,
            'body'         => $data['body'],
            'image'        => $imagePath,
            'scheduled_at' => $scheduledAt,
            'published_at' => $isFuture ? null : now(),
        ]);

        $me = app()->bound('workspace_owner') ? app('workspace_owner') : auth()->user();

        if (!$isFuture) {
            FeedEvent::create([
                'user_id'      => $me->id,
                'type'         => 'post',
                'subject_id'   => $post->id,
                'subject_type' => CreatorPost::class,
                'data'         => ['title' => $post->title, 'body_excerpt' => mb_substr($post->body, 0, 160), 'creator_name' => $me->name, 'creator_avatar' => $me->avatar],
                'occurred_at'  => now(),
            ]);

            $this->notifyFollowersDebounced($me, 'New post: ' . ($post->title ?: mb_substr($post->body, 0, 60)), $post);
        }

        if (!empty($data['is_pinned'])) {
            // Only published posts can be pinned. If this one is scheduled,
            // it will be pinnable from the list once it goes live.
            if (!$isFuture) {
                // Workspace-scoped: clears any other pinned post in this workspace.
                CreatorPost::query()->whereNotNull('pinned_at')->update(['pinned_at' => null]);
                $post->pinned_at = now();
                $post->save();
            }
        }

        $msg = $isFuture
            ? 'Post scheduled for ' . $scheduledAt->format('M j, Y g:i A') . '.'
            : 'Post published to your followers.';

        return redirect()->route('user.posts.index')->with('success', $msg);
    }

    public function pin(CreatorPost $post)
    {
        // The route binding + workspace global scope guarantees this post belongs
        // to the active workspace. The `workspace.can:posts.edit` middleware on
        // the route is the authorization gate.
        if (!$post->isPublished()) {
            return back()->with('error', 'You can only pin a published post.');
        }
        // Unpin any other pinned post in this workspace.
        CreatorPost::query()
            ->whereNotNull('pinned_at')
            ->where('id', '!=', $post->id)
            ->update(['pinned_at' => null]);
        $post->pinned_at = now();
        $post->save();
        return back()->with('success', 'Post pinned.');
    }

    public function unpin(CreatorPost $post)
    {
        // Auth handled by route middleware + global workspace scope.
        $post->pinned_at = null;
        $post->save();
        return back()->with('success', 'Post unpinned.');
    }

    public function destroy(CreatorPost $post)
    {
        // Auth handled by route middleware + global workspace scope.
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
    public static function notifyFollowersDebounced($creator, string $message, ?CreatorPost $post = null): void
    {
        $followerIds = Follow::where('creator_id', $creator->id)->pluck('follower_id');
        if ($followerIds->isEmpty()) return;

        $followers = \App\Modules\User\Models\User::whereIn('id', $followerIds)->get();

        foreach ($followers as $follower) {
            $mode = self::resolveFollowerMode($follower);
            if ($mode === 'off') continue;

            $data = [
                'creator_id'     => (int) $creator->id,
                'creator_name'   => $creator->name,
                'creator_avatar' => $creator->avatar,
                'message'        => $message,
            ];
            if ($post) {
                $data['post_id']    = (int) $post->id;
                $data['post_image'] = $post->image;
            }

            $notif = UserNotification::create([
                'user_id' => $follower->id,
                'type'    => 'follower_update',
                'data'    => $data,
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
