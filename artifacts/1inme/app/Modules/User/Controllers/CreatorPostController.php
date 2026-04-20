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
     * Notify followers about creator activity. Debounced: at most one
     * "follower update" notification per creator per follower per day.
     */
    public static function notifyFollowersDebounced($creator, string $message): void
    {
        $followerIds = Follow::where('creator_id', $creator->id)->pluck('follower_id');
        $cutoff = now()->subDay();

        foreach ($followerIds as $fid) {
            $recent = UserNotification::where('user_id', $fid)
                ->where('type', 'follower_update')
                ->whereJsonContains('data->creator_id', (int) $creator->id)
                ->where('created_at', '>=', $cutoff)
                ->exists();
            if ($recent) continue;

            $follower = \App\Modules\User\Models\User::find($fid);
            if (!$follower) continue;

            UserNotification::create([
                'user_id' => $fid,
                'type'    => 'follower_update',
                'data'    => [
                    'creator_id'     => (int) $creator->id,
                    'creator_name'   => $creator->name,
                    'creator_avatar' => $creator->avatar,
                    'message'        => $message,
                ],
                'created_at' => now(),
            ]);

            if ($follower->notify_follower_updates) {
                try {
                    \Mail::raw("{$creator->name}: {$message}", function ($m) use ($follower) {
                        $m->to($follower->email)->subject('New activity from a creator you follow');
                    });
                } catch (\Throwable $e) {}
            }
        }
    }
}
