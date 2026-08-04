<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\AgeGate;
use App\Modules\Common\Services\CountryGate;
use App\Modules\Common\Services\MuteWordsService;
use App\Modules\Common\Services\ViewerSession;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\CreatorPostComment;
use App\Modules\User\Models\CreatorPostReaction;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserBlock;
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
        // account also bypass; otherwise we render the real profile
        // markup underneath a client-facing confirmation overlay so
        // search/AI crawlers still see the actual profile content
        // instead of a thin interstitial (Task #3883). The overlay
        // still posts to the same age-gate.confirm route as before.
        $ageGateRequired = !$isOwner && $creator->isAdultProfile() && !AgeGate::passed($request, $viewer);

        // Task #1211 — viewer has blocked this creator. We hide the
        // profile entirely so the block surface mirrors what a fan
        // expects from the same kebab menu on Twitter / Bluesky.
        if (!$isOwner && $viewer && in_array((int) $creator->id, UserBlock::blockedIdsFor($viewer->id), true)) {
            abort(404);
        }

        // Task #1211 — country gating. Profile-level lists apply here;
        // per-post lists are evaluated when the post media is fetched
        // through the SignedMediaController. Owners bypass.
        if (!$isOwner) {
            $decision = app(CountryGate::class)->decide($creator, null, $request->ip());
            if (empty($decision['allowed'])) {
                return response()->view('public.region-blocked', [
                    'creator' => $creator,
                    'reason'  => $decision['reason'] ?? 'The creator has restricted this content in your region.',
                ], 451);
            }
        }

        $sectionsVisible = $creator->profileSectionVisibility();

        // Task #3666 — a few upcoming public events for the profile's
        // "Events" section, capped at 3 with a "See all events" link out
        // to the standalone /@{handle}/events page.
        $upcomingEvents = static::upcomingEventsQuery($creator)->limit(3)->get();

        $feed = $this->buildFeedViewData($creator, $viewer, $isOwner);

        $primaryBiolink = Link::query()
            ->where('user_id', $creator->id)
            ->whereIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        // Task #1211 — related creators by overlapping niche tags. Cheap
        // static helper on CreatorsController so the directory and the
        // profile share one ranking definition.
        $relatedCreators = \App\Modules\Common\Controllers\CreatorsController::relatedCreators($creator, $viewer, 6);

        // Task #5431 — profile showcase data.
        $showcase          = $creator->resolvedProfileShowcase();
        $featuredLinks     = $this->resolveFeaturedLinks($creator, $showcase, $sectionsVisible);
        $showcaseCards     = $this->resolveShowcaseCards($creator, $showcase, $sectionsVisible);
        // Task #6618 — link surfaces scope to the profile's WORKSPACE when
        // the creator was resolved through a workspace profile.
        $totalPublicLinks  = Link::query()
            ->withoutGlobalScope('workspace')
            ->tap($this->workspaceLinkScope($creator))
            ->where('is_active', true)
            ->where('visibility', 'public')
            ->count();

        return view('public.creator-profile', array_merge($feed, [
            'creator'         => $creator,
            'primaryBiolink'  => $primaryBiolink,
            'sectionsVisible' => $sectionsVisible,
            'viewer'          => $viewer,
            'isOwner'         => $isOwner,
            'relatedCreators' => $relatedCreators,
            // Special dates (Task #6551).
            'specialDates'    => \App\Modules\User\Support\SpecialDates::publicEntries($creator),
            'upcomingEvents'  => $upcomingEvents,
            'ageGateRequired' => $ageGateRequired,
            // Showcase data (Task #5431).
            'showcase'        => $showcase,
            'featuredLinks'   => $featuredLinks,
            'showcaseCards'   => $showcaseCards,
            'totalPublicLinks'=> $totalPublicLinks,
        ]));
    }

    /**
     * Lightweight JSON summary used by the mini-profile popover widget.
     * Public (no auth required); returns 404 when the profile is not
     * published or the handle does not exist.  Intentionally smaller
     * than the full show() payload to keep hover-card loads fast.
     */
    public function mini(string $handle, Request $request): \Illuminate\Http\JsonResponse
    {
        $creator = $this->resolveCreator($handle);
        if (!$creator || !$creator->profile_published) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $isVerified = method_exists($creator, 'isVerified') ? $creator->isVerified() : !empty($creator->email_verified_at);

        return response()->json([
            'data' => [
                'handle'          => $creator->handle,
                'name'            => $creator->name,
                'avatar'          => \App\Support\PublicStorageUrl::resolve($creator->creatorAvatarRaw()),
                'tagline'         => $creator->tagline,
                'followers_count' => (int) $creator->followers_count,
                'is_verified'     => $isVerified,
                'theme_color'     => $creator->profile_theme_color ?: null,
                'profile_url'     => route('creator-profile.show', $creator->handle),
                'profile_published' => true,
            ],
        ]);
    }

    /**
     * Public /@{handle}/events — a creator's public, active, upcoming
     * ics-type events, styled like the /events directory but scoped to
     * one host and without its search/filter controls (Task #3666).
     * Mirrors show()'s visibility gates (published/age/blocked/country).
     */
    public function events(string $handle, Request $request)
    {
        $creator = $this->resolveCreator($handle);
        if (!$creator) abort(404);

        $viewer = ViewerSession::user() ?? auth()->user();
        $isOwner = $viewer && (int) $viewer->id === (int) $creator->id;
        if (!$isOwner && !$creator->profile_published) {
            abort(404);
        }

        if (!$isOwner && $creator->isAdultProfile() && !AgeGate::passed($request, $viewer)) {
            return response()->view('public.age-gate', [
                'creator' => $creator,
            ], 200);
        }

        if (!$isOwner && $viewer && in_array((int) $creator->id, UserBlock::blockedIdsFor($viewer->id), true)) {
            abort(404);
        }

        if (!$isOwner) {
            $decision = app(CountryGate::class)->decide($creator, null, $request->ip());
            if (empty($decision['allowed'])) {
                return response()->view('public.region-blocked', [
                    'creator' => $creator,
                    'reason'  => $decision['reason'] ?? 'The creator has restricted this content in your region.',
                ], 451);
            }
        }

        $events = static::upcomingEventsQuery($creator)->paginate(24)->withQueryString();

        return view('common.creator-events', [
            'creator'   => $creator,
            'events'    => $events,
            'organizer' => $creator->organizerProfile(),
        ]);
    }

    /**
     * Shared query for a creator's public, active, upcoming ics events —
     * used by both the /@{handle}/events listing and the profile's
     * "Events" preview section. Mirrors EventsDirectoryController's base
     * filters (type/active/visibility/upcoming/hide_from_directory)
     * scoped to one host, ordered soonest-first.
     */
    public static function upcomingEventsQuery(User $creator)
    {
        return Link::query()
            ->where('type', 'ics')
            ->where('user_id', $creator->id)
            ->where('is_active', true)
            ->where('visibility', 'public')
            ->with(['icsData', 'eventTicketTiers' => fn ($t) => $t->where('is_active', true)])
            ->whereHas('icsData', fn ($w) => $w->where('start_date', '>=', now()->subDay()))
            ->where(fn ($w) => $w->whereRaw("(settings->>'hide_from_directory') IS DISTINCT FROM 'true'"))
            ->orderBy(
                \App\Modules\User\Models\IcsData::select('start_date')
                    ->whereColumn('ics_data.link_id', 'links.id')->limit(1)
            );
    }

    /**
     * Build the shared per-creator feed payload (posts page, reaction
     * totals, viewer reactions, comments, tiers, viewer subscription,
     * per-post access and follow state). Reused verbatim by the /@handle
     * creator profile and the Paid Page link type so both surfaces share
     * one source of truth for the monetized feed + paywall data.
     *
     * @return array<string,mixed>
     */
    public function buildFeedViewData(User $creator, ?User $viewer, bool $isOwner): array
    {
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
        $reactionTotals = $this->reactionTotalsByPost($postIds);
        $myReactions    = $this->myReactionsByPost($postIds, $viewer);
        $commentsByPost = $this->commentsByPost($postIds);

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

        return [
            'posts'              => $posts,
            'reactionTotals'     => $reactionTotals,
            'myReactions'        => $myReactions,
            'commentsByPost'     => $commentsByPost,
            'reactionDefs'       => CreatorPostReaction::REACTIONS,
            'tiers'              => $tiers,
            'viewerSubscription' => $viewerSubscription,
            'accessByPost'       => $accessByPost,
            'isFollowing'        => $isFollowing,
        ];
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

        // Task #1211 — apply the creator's mute-words list before the
        // comment lands in their notification feed. Matches are still
        // saved (so admins can review) but flipped to `hidden` so the
        // creator never has to see them.
        $body = trim($data['body']);
        $muteHit = app(MuteWordsService::class)->firstMatch($creator, $body);
        $status  = $muteHit ? 'hidden' : 'visible';

        $c = CreatorPostComment::create([
            'post_id'        => $p->id,
            'parent_id'      => $parentId,
            'viewer_user_id' => $viewer->id,
            'body'           => $body,
            'status'         => $status,
        ]);
        if ($status === 'visible') {
            $p->increment('comments_count');
        }

        return response()->json([
            'success' => true,
            'comment' => [
                'id'        => $c->id,
                'parent_id' => $c->parent_id,
                'body'      => $c->body,
                'author'    => [
                    'id'     => $viewer->id,
                    'name'   => $viewer->name,
                    'avatar' => \App\Support\PublicStorageUrl::resolve($viewer->avatar),
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

    /**
     * Resolve featured links in the owner-defined order, respecting
     * visibility (only public links appear to visitors; owner sees all active).
     *
     * @return array<int, array<string,mixed>>
     */
    private function resolveFeaturedLinks(User $creator, array $showcase, array $sectionsVisible): array
    {
        if (empty($sectionsVisible['featured_links'])) return [];
        $rawItems = is_array($showcase['featured_links'] ?? null) ? $showcase['featured_links'] : [];
        // Keep only enabled entries and extract their IDs in owner-defined order.
        $enabledIds = array_values(array_filter(array_map(function ($item) {
            if (!is_array($item)) return 0;
            return ($item['enabled'] ?? true) ? (int) ($item['id'] ?? 0) : 0;
        }, $rawItems)));
        if (empty($enabledIds)) return [];

        $links = Link::query()
            ->withoutGlobalScope('workspace')
            ->tap($this->workspaceLinkScope($creator))
            ->where('is_active', true)
            ->where('visibility', 'public')
            ->whereIn('id', $enabledIds)
            ->get()
            ->keyBy('id');

        // Preserve owner-defined order (enabled + public links only).
        $ordered = [];
        foreach ($enabledIds as $id) {
            if (isset($links[$id])) {
                $ordered[] = $links[$id];
            }
        }
        return $ordered;
    }

    /**
     * Resolve showcase card data for each opt-in item, loading the
     * minimum data each card type needs to render.
     *
     * @return array<int, array<string,mixed>>
     */
    private function resolveShowcaseCards(User $creator, array $showcase, array $sectionsVisible): array
    {
        if (empty($sectionsVisible['showcase'])) return [];
        $items = is_array($showcase['showcase_items'] ?? null) ? $showcase['showcase_items'] : [];
        if (empty($items)) return [];

        $linkIds = array_values(array_unique(array_column($items, 'link_id')));
        $links = Link::query()
            ->withoutGlobalScope('workspace')
            ->tap($this->workspaceLinkScope($creator))
            ->where('is_active', true)
            ->where('visibility', 'public')
            ->whereIn('id', $linkIds)
            ->with(['icsData'])
            ->get()
            ->keyBy('id');

        $cards = [];
        foreach ($items as $item) {
            $linkId = (int) ($item['link_id'] ?? 0);
            $type   = (string) ($item['type'] ?? '');
            $link   = $links[$linkId] ?? null;
            if (!$link) continue;
            $cards[] = [
                'type'     => $type,
                'link'     => $link,
                'ics_data' => $link->icsData ?? null,
            ];
        }
        return $cards;
    }

    /**
     * Task #6618 — closure scoping a Link query to the creator's WORKSPACE
     * profile when the creator was resolved via a workspace profile
     * (falls back to the legacy owner-wide user_id filter).
     */
    private function workspaceLinkScope(User $creator): \Closure
    {
        $wid = $creator->relationLoaded('activeCreatorProfile')
            ? $creator->getRelation('activeCreatorProfile')?->workspace_id
            : null;
        return fn ($q) => $wid
            ? $q->where(fn ($w) => $w
                ->where('workspace_id', $wid)
                ->orWhere(fn ($n) => $n->whereNull('workspace_id')->where('user_id', $creator->id)))
            : $q->where('user_id', $creator->id);
    }

    private function resolveCreator(string $handle): ?User
    {
        // The route normalises @handle / handle equivalently — resolution
        // here is case-insensitive on the handle column.
        // Task #6618 — handles resolve to a WORKSPACE creator profile;
        // the owner User is returned with the profile fields overlaid.
        return \App\Modules\User\Models\CreatorProfile::ownerUserForHandle($handle);
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
