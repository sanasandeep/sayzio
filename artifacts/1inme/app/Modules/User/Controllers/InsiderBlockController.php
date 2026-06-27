<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\CommunityMember;
use App\Modules\User\Models\CommunityPost;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\UserNotification;
use App\Mail\InsiderPostMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Creator-side dashboard for the Insider block on a biolink.
 *
 * Houses the gated-feed composer, the member roster, and per-post
 * analytics. Access is gated by the existing workspace.scope middleware.
 */
class InsiderBlockController extends Controller
{
    private function resolve(Request $request, Link $link, BiolinkBlock $block): void
    {
        abort_if($link->user_id !== $request->user()->id, 403);
        abort_if($block->link_id !== $link->id, 404);
        abort_if($block->type !== 'insider', 404);
    }

    public function index(Request $request, Link $link, BiolinkBlock $block)
    {
        $this->resolve($request, $link, $block);

        $posts = CommunityPost::query()
            ->where('block_id', $block->id)
            ->orderByDesc('id')
            ->paginate(20);

        $memberCounts = CommunityMember::query()
            ->where('block_id', $block->id)
            ->selectRaw("tier, COUNT(*) as c")
            ->groupBy('tier')
            ->pluck('c', 'tier')
            ->all();

        return view('user.insider.index', compact('link', 'block', 'posts', 'memberCounts'));
    }

    public function members(Request $request, Link $link, BiolinkBlock $block)
    {
        $this->resolve($request, $link, $block);

        $members = CommunityMember::query()
            ->where('block_id', $block->id)
            ->orderByDesc('joined_at')
            ->paginate(50);

        return view('user.insider.members', compact('link', 'block', 'members'));
    }

    public function storePost(Request $request, Link $link, BiolinkBlock $block)
    {
        $this->resolve($request, $link, $block);

        $data = $request->validate([
            'title'         => ['nullable', 'string', 'max:255'],
            'body'          => ['required', 'string'],
            'media_type'    => ['nullable', 'in:image,video'],
            'media_url'     => ['nullable', 'url', 'max:1024'],
            'access'        => ['required', 'in:' . implode(',', CommunityPost::ACCESS_LEVELS)],
            'status'        => ['required', 'in:draft,scheduled,published'],
            'scheduled_for' => ['nullable', 'date', 'after:now'],
        ]);

        $publishedAt = $data['status'] === 'published' ? now() : null;
        $scheduledFor = $data['status'] === 'scheduled' ? ($data['scheduled_for'] ?? null) : null;

        $post = CommunityPost::create([
            'user_id'       => $request->user()->id,
            'link_id'       => $link->id,
            'block_id'      => $block->id,
            'workspace_id'  => $link->workspace_id ?? null,
            'title'         => $data['title'] ?? null,
            'body'          => $data['body'],
            'media_type'    => $data['media_type'] ?? null,
            'media_url'     => $data['media_url'] ?? null,
            'access'        => $data['access'],
            'status'        => $data['status'],
            'scheduled_for' => $scheduledFor,
            'published_at'  => $publishedAt,
        ]);

        // When a post goes live, fan out an in-app notification to the
        // creator's followers (mirrors the existing biolink-block update
        // pipeline). Drafts and scheduled posts don't notify here; the
        // scheduler is responsible for fanning out at publish time.
        if ($post->status === 'published') {
            $this->notifyFollowersOfNewPost($request, $link, $post);
        }

        return redirect()
            ->route('user.links.insider.index', [$link, $block])
            ->with('success', 'Post saved.');
    }

    /**
     * Emit a `community_post` UserNotification on a new Insider post.
     *
     * The Insider audience is two sets that may overlap:
     *   1. Followers of the creator (existing biolink follower-update
     *      pipeline — in-app + any email digester already subscribed).
     *   2. CommunityMembers of this Insider block whose preferences
     *      allow notifications. Members may not have a User account
     *      (they joined with just an email), so for those we mark a
     *      lightweight delivery row on the member with a nullable
     *      user_id; in-app notifications only fire when the member is
     *      a logged-in user (viewer_user_id present), and the email
     *      side is left to the digester (see follow-up #882).
     *
     * We deduplicate by user_id so a follower who is also a member
     * doesn't receive the same in-app notification twice.
     */
    protected function notifyFollowersOfNewPost(Request $request, Link $link, CommunityPost $post): void
    {
        $creator = $request->user();
        if (!$creator) return;

        $title   = $post->title ?: 'New Insider post';
        $message = "{$creator->name} posted to their Insider feed: {$title}";
        $payload = [
            'creator_id'   => $creator->id,
            'creator_name' => $creator->name,
            'message'      => $message,
            'link_alias'   => $link->alias,
            'post_id'      => $post->id,
            'access'       => $post->access,
        ];

        $notifiedUserIds = [];

        // Followers (legacy creator-update fan-out, opt-in via the
        // creator's notify_follower_updates preference).
        if (!empty($creator->notify_follower_updates)) {
            $followerIds = Follow::where('creator_id', $creator->id)->pluck('follower_id');
            foreach ($followerIds as $fid) {
                if (isset($notifiedUserIds[$fid])) continue;
                UserNotification::create([
                    'user_id'    => $fid,
                    'type'       => 'community_post',
                    'data'       => $payload,
                    'created_at' => now(),
                ]);
                $notifiedUserIds[$fid] = true;
            }
        }

        // Insider members with notification preferences enabled. Tier
        // gating is honored by the post's `access` field: paid posts
        // only notify paid-tier members, members-only posts notify
        // free + paid members, public posts notify everyone (tier
        // doesn't apply to public). Banned members are excluded.
        $memberQuery = CommunityMember::query()
            ->withoutGlobalScope('workspace')
            ->where('block_id', $post->block_id)
            ->where('status', 'active');

        if ($post->access === 'paid') {
            $memberQuery->where('tier', 'paid');
        }

        $memberQuery->get()->each(function ($member) use ($payload, &$notifiedUserIds, $link, $post, $creator) {
            // Respect the member's own notification preference if they
            // expressed one (defaults to opted-in).
            $prefs = $member->preferences ?? [];
            if (is_array($prefs) && array_key_exists('notify_new_posts', $prefs) && !$prefs['notify_new_posts']) {
                return;
            }
            // For posts gated to followers, only notify members who are
            // actually following the creator — otherwise we'd alert
            // people about content their canSeePost() check will deny.
            if ($post->access === 'followers') {
                $followerCheckId = $member->viewer_user_id;
                if (!$followerCheckId) return;
                $isFollower = Follow::where('follower_id', $followerCheckId)
                    ->where('user_id', $link->user_id)
                    ->exists();
                if (!$isFollower) return;
            }
            $uid = $member->viewer_user_id;
            if ($uid && !isset($notifiedUserIds[$uid])) {
                UserNotification::create([
                    'user_id'    => $uid,
                    'type'       => 'community_post',
                    'data'       => $payload,
                    'created_at' => now(),
                ]);
                $notifiedUserIds[$uid] = true;
            }
            // Email delivery: members without a linked user account
            // (joined with just an email) get the post via direct mail.
            // Logged-in members also get email unless they opted out.
            $wantsEmail = !is_array($prefs)
                || !array_key_exists('notify_email', $prefs)
                || (bool) $prefs['notify_email'];
            if ($wantsEmail && !empty($member->email)) {
                try {
                    \App\Modules\Common\Services\Emailer::sendMailable('insider.new_post', $member->email,
                        new InsiderPostMail($link, $post, $creator->name),
                        ['creator_name' => $creator->name, 'title' => $post->title], ['related' => $post, 'queue' => true]);
                } catch (\Throwable $e) {
                    \Log::warning('Insider post email failed: ' . $e->getMessage());
                }
            }
        });
    }

    public function updatePost(Request $request, Link $link, BiolinkBlock $block, CommunityPost $post)
    {
        $this->resolve($request, $link, $block);
        abort_if($post->block_id !== $block->id, 404);

        $data = $request->validate([
            'title'         => ['nullable', 'string', 'max:255'],
            'body'          => ['required', 'string'],
            'media_type'    => ['nullable', 'in:image,video'],
            'media_url'     => ['nullable', 'url', 'max:1024'],
            'access'        => ['required', 'in:' . implode(',', CommunityPost::ACCESS_LEVELS)],
            'status'        => ['required', 'in:draft,scheduled,published,archived'],
            'scheduled_for' => ['nullable', 'date'],
        ]);

        $post->fill($data);
        if ($data['status'] === 'published' && !$post->published_at) {
            $post->published_at = now();
        }
        $post->save();

        return back()->with('success', 'Post updated.');
    }

    public function destroyPost(Request $request, Link $link, BiolinkBlock $block, CommunityPost $post)
    {
        $this->resolve($request, $link, $block);
        abort_if($post->block_id !== $block->id, 404);

        $post->delete();
        return back()->with('success', 'Post deleted.');
    }

    public function banMember(Request $request, Link $link, BiolinkBlock $block, CommunityMember $member)
    {
        $this->resolve($request, $link, $block);
        abort_if($member->block_id !== $block->id, 404);

        $member->status = 'banned';
        $member->save();

        return back()->with('success', 'Member banned.');
    }
}
