<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\AgeGate;
use App\Modules\Common\Services\ViewerSession;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\CreatorPostComment;
use App\Modules\User\Models\CreatorPostReaction;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Public surface for the new fixed-layout Creator Profile that lives
 * at /@handle. Independent of the biolink renderer at /{alias}.
 *
 * The Creator Profile has its own posts feed, branded reactions, and
 * one-level threaded comments. Reactions and comments require a
 * ViewerSession (the same lightweight viewer auth used elsewhere on
 * public surfaces); follow already does.
 */
class CreatorProfilePublicController extends Controller
{
    /**
     * Render the public profile page for /@{handle}.
     */
    public function show(string $handle, Request $request)
    {
        $creator = $this->resolveCreator($handle);
        if (!$creator) abort(404);

        // Owners can always see their own profile (even unpublished); other
        // viewers only see published profiles. The discoverable flag still
        // governs the /creators directory; profile_published governs whether
        // the /@handle page itself answers to anyone but the owner.
        $viewer = ViewerSession::user() ?? auth()->user();
        $isOwner = $viewer && (int) $viewer->id === (int) $creator->id;
        if (!$isOwner && !$creator->profile_published) {
            abort(404);
        }

        // Visitor 18+ age gate (Task #1208). Owners always bypass.
        // Logged-in viewers who have ever affirmed 18+ on their own
        // account also bypass; otherwise we set a 30-day cookie via the
        // interstitial form.
        if (!$isOwner && $creator->isAdultProfile() && !AgeGate::passed($request, $viewer)) {
            return response()->view('public.age-gate', [
                'creator' => $creator,
            ], 200);
        }

        $sectionsVisible = $creator->profileSectionVisibility();

        $posts = CreatorPost::query()
            ->withoutGlobalScope('workspace')
            ->where('user_id', $creator->id)
            ->whereNotNull('published_at')
            ->orderByDesc('pinned_at')
            ->orderByDesc('published_at')
            ->paginate(10);

        // Bulk-load reactions and comment counts for the visible page so we
        // don't N+1 the rendering loop.
        $postIds = $posts->pluck('id')->all();
        $reactionTotals  = $this->reactionTotalsByPost($postIds);
        $myReactions     = $this->myReactionsByPost($postIds, $viewer);
        $commentsByPost  = $this->commentsByPost($postIds);

        $primaryBiolink = Link::query()
            ->where('user_id', $creator->id)
            ->where('type', 'biolink')
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        $isFollowing = false;
        if ($viewer && !$isOwner) {
            $isFollowing = Follow::where('follower_id', $viewer->id)
                ->where('creator_id', $creator->id)->exists();
        }

        // Monetization (Task #1209). Surface the creator's active tiers
        // for the Subscribe/Tip CTAs and pre-compute per-post access so
        // the partial can render locked/blurred variants without N+1.
        $tiers = \App\Modules\User\Models\SubscriptionTier::query()
            ->where('user_id', $creator->id)
            ->where('is_active', true)
            ->where('is_free', false)
            ->orderBy('price_monthly_cents')
            ->get();
        $viewerSubscription = null;
        if ($viewer && !$isOwner) {
            $viewerSubscription = \App\Modules\User\Models\CreatorSubscription::query()
                ->where('creator_user_id', $creator->id)
                ->where('fan_user_id', $viewer->id)
                ->whereIn('status', ['active', 'trialing', 'past_due'])
                ->latest('id')
                ->first();
        }
        $accessByPost = \App\Services\Monetization\PostAccessPolicy::evaluateMany($viewer, $posts->getCollection());

        return view('public.creator-profile', [
            'creator'             => $creator,
            'posts'               => $posts,
            'reactionTotals'      => $reactionTotals,
            'myReactions'         => $myReactions,
            'commentsByPost'      => $commentsByPost,
            'primaryBiolink'      => $primaryBiolink,
            'sectionsVisible'     => $sectionsVisible,
            'viewer'              => $viewer,
            'isOwner'             => $isOwner,
            'isFollowing'         => $isFollowing,
            'reactionDefs'        => CreatorPostReaction::REACTIONS,
            'tiers'               => $tiers,
            'viewerSubscription'  => $viewerSubscription,
            'accessByPost'        => $accessByPost,
        ]);
    }

    /**
     * Toggle a viewer's branded reaction on a post. POST /@handle/p/{post}/react
     * Body: { reaction: <key> }. Same key twice removes; different key swaps.
     */
    public function react(Request $request, string $handle, int $post)
    {
        $viewer = ViewerSession::user();
        if (!$viewer) return response()->json(['success' => false, 'message' => 'Sign in to react.'], 401);

        $creator = $this->resolveCreator($handle);
        if (!$creator) return response()->json(['success' => false], 404);

        $p = CreatorPost::query()->withoutGlobalScope('workspace')
            ->where('user_id', $creator->id)->whereKey($post)->first();
        if (!$p || !$p->published_at) return response()->json(['success' => false], 404);

        $data = $request->validate([
            'reaction' => 'required|string|in:' . implode(',', CreatorPostReaction::reactionKeys()),
        ]);

        $existing = CreatorPostReaction::where('post_id', $p->id)
            ->where('viewer_user_id', $viewer->id)->first();

        DB::transaction(function () use (&$existing, $p, $viewer, $data) {
            if ($existing && $existing->reaction === $data['reaction']) {
                $existing->delete();
                $existing = null;
                $p->decrement('reactions_count');
            } elseif ($existing) {
                $existing->reaction = $data['reaction'];
                $existing->save();
            } else {
                $existing = CreatorPostReaction::create([
                    'post_id' => $p->id,
                    'viewer_user_id' => $viewer->id,
                    'reaction' => $data['reaction'],
                    'created_at' => now(),
                ]);
                $p->increment('reactions_count');
            }
        });

        return response()->json([
            'success'  => true,
            'reaction' => $existing?->reaction,
            'totals'   => $this->reactionTotalsByPost([$p->id])[$p->id] ?? [],
            'count'    => $p->fresh()->reactions_count,
        ]);
    }

    /**
     * Add a top-level or one-level reply comment.
     * POST /@handle/p/{post}/comment.
     */
    public function comment(Request $request, string $handle, int $post)
    {
        $viewer = ViewerSession::user();
        if (!$viewer) return response()->json(['success' => false, 'message' => 'Sign in to comment.'], 401);

        $creator = $this->resolveCreator($handle);
        if (!$creator) return response()->json(['success' => false], 404);

        $p = CreatorPost::query()->withoutGlobalScope('workspace')
            ->where('user_id', $creator->id)->whereKey($post)->first();
        if (!$p || !$p->published_at) return response()->json(['success' => false], 404);

        $data = $request->validate([
            'body'      => 'required|string|max:2000',
            'parent_id' => 'nullable|integer|exists:creator_post_comments,id',
        ]);

        // Per-viewer rate limit to keep abusive flooding off the surface.
        $rateKey = 'cp-comment:' . $viewer->id;
        if (RateLimiter::tooManyAttempts($rateKey, 30)) {
            return response()->json([
                'success' => false,
                'message' => 'You are commenting too quickly. Please slow down.',
            ], 429);
        }
        RateLimiter::hit($rateKey, 60);

        // Enforce the "one reply level" rule: a parent comment cannot itself
        // be a reply, otherwise threads would grow unbounded.
        $parentId = null;
        if (!empty($data['parent_id'])) {
            $parent = CreatorPostComment::find($data['parent_id']);
            if (!$parent || $parent->post_id !== $p->id) {
                return response()->json(['success' => false, 'message' => 'Invalid parent comment.'], 422);
            }
            if ($parent->parent_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Replies are limited to one level — reply to the original comment instead.',
                ], 422);
            }
            $parentId = $parent->id;
        }

        $c = CreatorPostComment::create([
            'post_id'        => $p->id,
            'parent_id'      => $parentId,
            'viewer_user_id' => $viewer->id,
            'body'           => trim($data['body']),
            'status'         => 'visible',
        ]);
        $p->increment('comments_count');

        return response()->json([
            'success' => true,
            'comment' => [
                'id'        => $c->id,
                'parent_id' => $c->parent_id,
                'body'      => $c->body,
                'author'    => [
                    'id'     => $viewer->id,
                    'name'   => $viewer->name,
                    'avatar' => $viewer->avatar,
                    'handle' => $viewer->handle,
                ],
                'created_at' => $c->created_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Soft-delete a comment. The comment author and the creator who owns
     * the post can both delete; deletion preserves the row so reply
     * threads don't collapse, but blanks the body.
     */
    public function deleteComment(Request $request, string $handle, int $comment)
    {
        $viewer = ViewerSession::user();
        if (!$viewer) return response()->json(['success' => false], 401);

        $creator = $this->resolveCreator($handle);
        if (!$creator) return response()->json(['success' => false], 404);

        $c = CreatorPostComment::with('post')->find($comment);
        if (!$c || !$c->post || $c->post->user_id !== $creator->id) {
            return response()->json(['success' => false], 404);
        }

        $isAuthor  = (int) $c->viewer_user_id === (int) $viewer->id;
        $isCreator = (int) $creator->id === (int) $viewer->id;
        if (!$isAuthor && !$isCreator) {
            return response()->json(['success' => false], 403);
        }

        if ($c->status !== 'deleted') {
            $c->status = 'deleted';
            $c->body = '[deleted]';
            $c->save();
            $c->post->decrement('comments_count');
        }

        return response()->json(['success' => true]);
    }

    private function resolveCreator(string $handle): ?User
    {
        // The route normalises @handle / handle equivalently — resolution
        // here is case-insensitive on the handle column.
        $handle = ltrim($handle, '@');
        if ($handle === '' || strlen($handle) > 60) return null;
        return User::query()->whereRaw('LOWER(handle) = ?', [strtolower($handle)])->first();
    }

    /**
     * @return array<int, array<string, int>>  post_id => [reaction_key => count]
     */
    private function reactionTotalsByPost(array $postIds): array
    {
        if (empty($postIds)) return [];
        $rows = DB::table('creator_post_reactions')
            ->select('post_id', 'reaction', DB::raw('COUNT(*) as c'))
            ->whereIn('post_id', $postIds)
            ->groupBy('post_id', 'reaction')
            ->get();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->post_id][$r->reaction] = (int) $r->c;
        }
        return $out;
    }

    /**
     * @return array<int, string>  post_id => reaction_key chosen by the viewer
     */
    private function myReactionsByPost(array $postIds, ?User $viewer): array
    {
        if (empty($postIds) || !$viewer) return [];
        return CreatorPostReaction::query()
            ->whereIn('post_id', $postIds)
            ->where('viewer_user_id', $viewer->id)
            ->pluck('reaction', 'post_id')->all();
    }

    /**
     * Bulk-load comments grouped by post (top level) with their visible
     * replies pre-loaded so the page can render the tree without N+1.
     */
    private function commentsByPost(array $postIds): array
    {
        if (empty($postIds)) return [];
        $top = CreatorPostComment::query()
            ->whereIn('post_id', $postIds)
            ->whereNull('parent_id')
            ->where('status', 'visible')
            ->with(['viewer:id,name,avatar,handle', 'replies.viewer:id,name,avatar,handle'])
            ->orderBy('created_at')
            ->get();

        $by = [];
        foreach ($top as $c) $by[(int) $c->post_id][] = $c;
        return $by;
    }
}
