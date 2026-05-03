<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\BlockComment;
use App\Modules\User\Models\BlockPoll;
use App\Modules\User\Models\BlockPollVote;
use App\Modules\User\Models\BlockReaction;
use App\Modules\User\Models\CommunityMember;
use App\Modules\User\Models\CommunityPost;
use App\Modules\User\Models\FanLeaderboardSetting;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Services\FanPointsEngine;
use App\Modules\Admin\Models\BannedName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Public-facing endpoints for the community layer rendered inline on a
 * biolink page: gated feed reader, comments/reactions/polls and the
 * Insider join flow. All actions accept anonymous viewers but bind to
 * the ViewerSession user when one is present.
 */
class CommunityPublicController extends Controller
{
    public function __construct(private FanPointsEngine $points) {}

    private function viewerUserId(Request $request): ?int
    {
        $vu = $request->session()->get('viewer_user_id');
        return $vu ? (int)$vu : null;
    }

    private function fingerprint(Request $request): string
    {
        $sid = $request->session()->getId() ?: $request->ip();
        return substr(hash('sha256', $sid . '|' . ($request->userAgent() ?? '')), 0, 32);
    }

    private function displayName(Request $request, ?string $fallback = null): ?string
    {
        if ($name = $request->session()->get('viewer_user_name')) return $name;
        return $fallback;
    }

    private function passesNameModeration(?string $name): bool
    {
        if (!$name) return true;
        $banned = BannedName::query()->pluck('name')->map(fn ($n) => mb_strtolower($n))->all();
        return !in_array(mb_strtolower($name), $banned, true);
    }

    /**
     * Join the Insider feed for a biolink. Free tier is honoured here;
     * paid tier is initiated through the existing Stripe gateway and
     * activated via webhook (out of scope for this endpoint).
     */
    public function joinInsider(Request $request, Link $link, BiolinkBlock $block)
    {
        abort_if($block->link_id !== $link->id, 404);
        abort_if($block->type !== 'insider', 404);

        $data = $request->validate([
            'email'        => ['required', 'email', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:80'],
        ]);

        if (!$this->passesNameModeration($data['display_name'] ?? null)) {
            return response()->json(['ok' => false, 'error' => 'Display name not allowed.'], 422);
        }

        // Honor the block's access mode: when the creator marks the
        // Insider block as paid-only, free email signups are refused
        // here. Paid members can only be created via the dedicated
        // billing webhook (joinInsiderPaid below), which requires a
        // signed Stripe payload via the existing billing flow.
        $accessMode = $this->blockSetting($block, 'access_mode', 'free');
        if ($accessMode === 'paid') {
            // Paid Insider blocks can't be joined for free, but we still
            // need a way for an *already-paid* subscriber to authenticate
            // into the gated feed. The billing webhook (joinInsiderPaid)
            // creates the paid CommunityMember up front; here we let that
            // person "claim" their seat by re-entering the same email
            // they paid with, which establishes their viewer session.
            $existingPaid = CommunityMember::query()
                ->withoutGlobalScope('workspace')
                ->where('block_id', $block->id)
                ->where('email', $data['email'])
                ->where('tier', 'paid')
                ->active()
                ->first();
            if (!$existingPaid) {
                return response()->json([
                    'ok' => false,
                    'error' => 'This Insider feed requires a paid subscription.',
                    'requires' => 'paid',
                ], 402);
            }
            $request->session()->put('viewer_user_email', $data['email']);
            if (!empty($data['display_name'])) {
                $request->session()->put('viewer_user_name', $data['display_name']);
            } elseif (!empty($existingPaid->display_name)) {
                $request->session()->put('viewer_user_name', $existingPaid->display_name);
            }
            // Backfill viewer_user_id linkage if the visitor is logged in
            // so leaderboard attribution works for paid members too.
            if (!$existingPaid->viewer_user_id && ($vid = $this->viewerUserId($request))) {
                $existingPaid->viewer_user_id = $vid;
                $existingPaid->save();
            }
            return response()->json([
                'ok' => true,
                'member_id' => $existingPaid->id,
                'tier' => 'paid',
                'claimed' => true,
            ]);
        }

        // Persist the joined identity into the viewer session so the same
        // request — and any follow-up reads of the gated feed/comments —
        // immediately treats this visitor as an active community member.
        $request->session()->put('viewer_user_email', $data['email']);
        if (!empty($data['display_name'])) {
            $request->session()->put('viewer_user_name', $data['display_name']);
        }

        $member = CommunityMember::query()->withoutGlobalScope('workspace')->firstOrCreate(
            ['link_id' => $link->id, 'email' => $data['email']],
            [
                'user_id'        => $link->user_id,
                'block_id'       => $block->id,
                'workspace_id'   => $link->workspace_id ?? null,
                'viewer_user_id' => $this->viewerUserId($request),
                'display_name'   => $data['display_name'] ?? null,
                'tier'           => 'free',
                'status'         => 'active',
                'joined_at'      => now(),
                'preferences'    => ['notify_email' => true, 'notify_inapp' => true],
            ]
        );

        // Record a subscriber row too so existing notification pipelines
        // (digests, broadcasts) pick the member up automatically.
        Subscriber::query()->withoutGlobalScope('workspace')->firstOrCreate(
            ['user_id' => $link->user_id, 'type' => 'insider', 'email' => $data['email']],
            [
                'link_id'  => $link->id,
                'block_id' => $block->id,
                'name'     => $data['display_name'] ?? null,
                'status'   => 'active',
                'source'   => 'insider_block',
                'subscribed_at' => now(),
            ]
        );

        $this->points->award(
            $link, 'signup', $member,
            $this->viewerUserId($request),
            $this->fingerprint($request),
            $member->display_name ?: $data['email']
        );

        return response()->json(['ok' => true, 'member_id' => $member->id]);
    }

    public function feed(Request $request, Link $link, BiolinkBlock $block)
    {
        abort_if($block->link_id !== $link->id, 404);
        abort_if($block->type !== 'insider', 404);

        $member = $this->resolveMember($request, $link, $block);

        $posts = CommunityPost::query()
            ->withoutGlobalScope('workspace')
            ->where('block_id', $block->id)
            ->published()
            ->orderByDesc('published_at')
            ->limit(50)
            ->get();

        $viewerId = $this->viewerUserId($request);
        $visible = $posts->filter(fn ($p) => $this->canSeePost($p, $member, $link, $viewerId))->values();

        return response()->json([
            'is_member' => (bool)$member,
            'tier'      => $member?->tier,
            'posts'     => $visible->map(fn ($p) => [
                'id'              => $p->id,
                'title'           => $p->title,
                'body'            => $p->body,
                'media_type'      => $p->media_type,
                'media_url'       => $p->media_url,
                'access'          => $p->access,
                'published_at'    => $p->published_at?->toIso8601String(),
                'reactions_count' => $p->reactions_count,
                'comments_count'  => $p->comments_count,
            ]),
        ]);
    }

    private function resolveMember(Request $request, Link $link, BiolinkBlock $block): ?CommunityMember
    {
        $email = $request->session()->get('viewer_user_email');
        if (!$email) return null;
        // Membership is link-scoped, not block-scoped: a visitor who
        // joined the Insider block on this biolink is a "member" for
        // gating purposes on every other block on the same link too,
        // so members-only comments/polls/reactions on non-Insider
        // blocks accept Insider members. We still prefer a member
        // record tied to the current block when one exists so block-
        // local data (preferences, tier) wins.
        $q = CommunityMember::query()
            ->withoutGlobalScope('workspace')
            ->where('link_id', $link->id)
            ->where('email', $email)
            ->active();
        $blockSpecific = (clone $q)->where('block_id', $block->id)->first();
        return $blockSpecific ?: $q->first();
    }

    private function canSeePost(CommunityPost $post, ?CommunityMember $member, ?Link $link = null, ?int $viewerId = null): bool
    {
        return match ($post->access) {
            'public'    => true,
            'members'   => (bool)$member,
            'paid'      => $member && $member->tier === 'paid',
            'followers' => $link ? $this->isFollowing($viewerId, $link) : false,
            default     => false,
        };
    }

    /**
     * Returns true when the viewer is a registered viewer who follows the
     * link's creator. Anonymous visitors never qualify as followers.
     */
    private function isFollowing(?int $viewerId, Link $link): bool
    {
        if (!$viewerId) return false;
        return Follow::query()
            ->withoutGlobalScope('workspace')
            ->where('follower_id', $viewerId)
            ->where('creator_id', $link->user_id)
            ->exists();
    }

    /**
     * Resolve the visibility setting for a given concern (comments, polls)
     * on a block. Falls back to the most permissive option if unset.
     */
    private function blockVisibility(BiolinkBlock $block, string $key, string $default = 'public'): string
    {
        $v = ($block->settings[$key] ?? $default);
        return in_array($v, ['public', 'members', 'followers'], true) ? $v : $default;
    }

    /**
     * Read a free-form scalar block setting with a default fallback.
     * Used for non-visibility settings such as the Insider access mode.
     */
    private function blockSetting(BiolinkBlock $block, string $key, $default = null)
    {
        $settings = $block->settings ?? [];
        return $settings[$key] ?? $default;
    }

    /**
     * Reject community interactions targeted at a block whose creator
     * has not opted into that feature. Insider and fan_leaderboard
     * blocks ship comments/polls/reactions on by default since they're
     * the whole point of those block types; for any other block type
     * the creator has to flip the per-block toggle in settings.
     */
    /**
     * Returns true if this viewer (by email and/or display name) is
     * banned from interacting with this block. Honors the per-link ban
     * BlockCommentController::banAuthor writes to CommunityMember.status,
     * which means a banned author cannot re-post or react even if they
     * supply a different email — we cross-check name and viewer_user_id
     * too.
     */
    private function isBannedAuthor(Request $request, Link $link, BiolinkBlock $block, ?string $email = null, ?string $name = null): bool
    {
        $email = $email ?: $request->session()->get('viewer_user_email');
        $name  = $name  ?: $request->session()->get('viewer_user_name');
        $vid   = $this->viewerUserId($request);
        if (!$email && !$name && !$vid) return false;

        return CommunityMember::query()
            ->withoutGlobalScope('workspace')
            ->where('link_id', $link->id)
            ->where('status', 'banned')
            ->where(function ($q) use ($email, $name, $vid) {
                if ($email) $q->orWhere('email', $email);
                if ($name)  $q->orWhere('display_name', $name);
                if ($vid)   $q->orWhere('viewer_user_id', $vid);
            })
            ->exists();
    }

    private function ensureFeatureEnabled(BiolinkBlock $block, string $feature): void
    {
        if (in_array($block->type, ['insider', 'fan_leaderboard'], true)) {
            return;
        }
        $key = 'enable_' . $feature;
        $enabled = (bool) $this->blockSetting($block, $key, false);
        abort_unless($enabled, 404);
    }

    /**
     * Gate-check a viewer for a given visibility level. Returns null on
     * success or a JSON error response on failure.
     */
    private function gateCheck(string $visibility, Request $request, Link $link, BiolinkBlock $block)
    {
        $member = $this->resolveMember($request, $link, $block);
        $viewerId = $this->viewerUserId($request);

        if ($visibility === 'members' && !$member) {
            return response()->json(['ok' => false, 'error' => 'Members only.'], 403);
        }
        if ($visibility === 'followers' && !$this->isFollowing($viewerId, $link)) {
            return response()->json(['ok' => false, 'error' => 'Followers only.'], 403);
        }
        return null;
    }

    public function postComment(Request $request, Link $link, BiolinkBlock $block)
    {
        abort_if($block->link_id !== $link->id, 404);
        $this->ensureFeatureEnabled($block, 'comments');

        $visibility = $this->blockVisibility($block, 'comments_visibility');
        if ($denied = $this->gateCheck($visibility, $request, $link, $block)) return $denied;

        // Honor per-link bans written by BlockCommentController::banAuthor.
        if ($this->isBannedAuthor(
            $request, $link, $block,
            $request->input('author_email'),
            $request->input('author_name')
        )) {
            return response()->json(['ok' => false, 'error' => 'You are not allowed to comment here.'], 403);
        }

        $member = $this->resolveMember($request, $link, $block);
        $viewerId = $this->viewerUserId($request);

        $data = $request->validate([
            'body'         => ['required', 'string', 'max:2000'],
            'parent_id'    => ['nullable', 'integer'],
            // Optional: when set, the comment is attached to one specific
            // Insider feed post rather than to the block as a whole.
            'post_id'      => ['nullable', 'integer'],
            'author_name'  => ['nullable', 'string', 'max:80'],
            'author_email' => ['nullable', 'email', 'max:255'],
        ]);

        if (!empty($data['post_id'])) {
            $post = CommunityPost::query()->withoutGlobalScope('workspace')
                ->where('block_id', $block->id)->whereKey($data['post_id'])->first();
            abort_unless($post, 404);
            // Comments inherit the post's gating: members/paid/followers
            // posts must not let arbitrary visitors read or write comments
            // on them, regardless of the block-wide comments_visibility.
            if (!$this->canSeePost($post, $member, $link, $viewerId)) {
                return response()->json(['ok' => false, 'error' => 'Not allowed.'], 403);
            }
        }

        $name = $data['author_name'] ?? $request->session()->get('viewer_user_name');
        if (!$this->passesNameModeration($name)) {
            return response()->json(['ok' => false, 'error' => 'Display name not allowed.'], 422);
        }

        // Honour locked threads on the parent comment.
        if (!empty($data['parent_id'])) {
            $parent = BlockComment::query()->withoutGlobalScope('workspace')
                ->where('block_id', $block->id)->find($data['parent_id']);
            abort_unless($parent, 404);
            abort_if($parent->is_locked, 423, 'Thread is locked.');
        }

        $comment = BlockComment::create([
            'link_id'        => $link->id,
            'block_id'       => $block->id,
            'workspace_id'   => $link->workspace_id ?? null,
            'parent_id'      => $data['parent_id'] ?? null,
            'post_id'        => $data['post_id'] ?? null,
            'viewer_user_id' => $viewerId,
            'member_id'      => $member?->id,
            'author_name'    => $name,
            'author_email'   => $data['author_email'] ?? $request->session()->get('viewer_user_email'),
            'body'           => $data['body'],
            'status'         => 'visible',
            'ip_address'     => $request->ip(),
            'user_agent'     => substr((string)$request->userAgent(), 0, 500),
        ]);

        $this->points->award(
            $link, 'comment', $comment,
            $viewerId, $this->fingerprint($request), $name
        );

        // Maintain the denormalized engagement counter on the parent
        // CommunityPost so creator analytics stay in sync without a
        // count(*) query on every render.
        if (!empty($data['post_id'])) {
            CommunityPost::query()->withoutGlobalScope('workspace')
                ->whereKey((int)$data['post_id'])->increment('comments_count');
        }

        return response()->json(['ok' => true, 'comment_id' => $comment->id]);
    }

    public function listComments(Request $request, Link $link, BiolinkBlock $block)
    {
        abort_if($block->link_id !== $link->id, 404);
        $this->ensureFeatureEnabled($block, 'comments');

        // Same visibility key the post endpoint uses, applied to the read
        // side so member/follower-only threads aren't dumped to the public.
        $visibility = $this->blockVisibility($block, 'comments_visibility');
        if ($denied = $this->gateCheck($visibility, $request, $link, $block)) return $denied;

        $postId = $request->query('post_id');
        if ($postId !== null) {
            $post = CommunityPost::query()->withoutGlobalScope('workspace')
                ->where('block_id', $block->id)->whereKey((int)$postId)->first();
            abort_unless($post, 404);
            // Per-post comment threads inherit the post's gating, so paid
            // or members-only posts can't be read by enumerating IDs.
            $member = $this->resolveMember($request, $link, $block);
            $viewerId = $this->viewerUserId($request);
            if (!$this->canSeePost($post, $member, $link, $viewerId)) {
                return response()->json(['ok' => false, 'error' => 'Not allowed.'], 403);
            }
        }

        $comments = BlockComment::query()
            ->withoutGlobalScope('workspace')
            ->where('block_id', $block->id)
            ->when($postId !== null, fn ($q) => $q->where('post_id', (int)$postId))
            ->when($postId === null, fn ($q) => $q->whereNull('post_id'))
            ->visible()
            ->whereNull('parent_id')
            ->latest()
            ->limit(100)
            ->get();

        return response()->json([
            'comments' => $comments->map(fn ($c) => [
                'id'          => $c->id,
                'author_name' => $c->author_name ?: 'Guest',
                'body'        => $c->body,
                'is_pinned'   => $c->is_pinned,
                'is_locked'   => $c->is_locked,
                'created_at'  => $c->created_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Public read of poll data for a block. Honors each poll's own
     * visibility setting (public/members/followers); polls the viewer
     * isn't allowed to see are silently filtered out rather than 403'd
     * so a single block can mix gated and ungated polls.
     */
    public function listPolls(Request $request, Link $link, BiolinkBlock $block)
    {
        abort_if($block->link_id !== $link->id, 404);
        $this->ensureFeatureEnabled($block, 'polls');

        $member = $this->resolveMember($request, $link, $block);
        $viewerId = $this->viewerUserId($request);

        $polls = BlockPoll::query()
            ->withoutGlobalScope('workspace')
            ->where('block_id', $block->id)
            ->orderByDesc('id')
            ->get()
            ->filter(function ($poll) use ($request, $link, $block, $member, $viewerId) {
                // Polls attached to a CommunityPost inherit that post's
                // gating (public/members/paid/followers) so a poll on a
                // paid Insider post is never surfaced to a free viewer.
                if ($poll->post_id) {
                    $post = CommunityPost::query()->withoutGlobalScope('workspace')
                        ->whereKey($poll->post_id)->first();
                    if (!$post || !$this->canSeePost($post, $member, $link, $viewerId)) {
                        return false;
                    }
                }
                $vis = $poll->visibility ?: 'public';
                if ($vis === 'public') return true;
                if ($vis === 'members') return (bool) $member;
                if ($vis === 'followers') {
                    return $viewerId && $this->isFollowing($viewerId, $link);
                }
                return false;
            })
            ->map(fn ($p) => [
                'id'           => $p->id,
                'question'     => $p->question,
                'visibility'   => $p->visibility,
                'multi_select' => (bool) $p->multi_select,
                'is_open'      => $p->isOpen(),
                'closes_at'    => $p->closes_at?->toIso8601String(),
                'tally'        => $p->tally(),
            ])
            ->values();

        return response()->json(['polls' => $polls]);
    }

    public function react(Request $request, Link $link, BiolinkBlock $block)
    {
        abort_if($block->link_id !== $link->id, 404);
        $this->ensureFeatureEnabled($block, 'reactions');

        // Banned authors must not be able to keep engaging via reactions.
        if ($this->isBannedAuthor($request, $link, $block)) {
            return response()->json(['ok' => false, 'error' => 'You are not allowed to react here.'], 403);
        }

        $data = $request->validate([
            'emoji'      => ['required', 'string', 'max:16'],
            'comment_id' => ['nullable', 'integer'],
            'post_id'    => ['nullable', 'integer'],
        ]);

        $fp = $this->fingerprint($request);
        $viewerId = $this->viewerUserId($request);
        $member   = $this->resolveMember($request, $link, $block);

        $commentId = $data['comment_id'] ?? null;
        $postId    = $data['post_id'] ?? null;

        // Validate that the targeted comment / post actually belongs to
        // this block — prevents cross-block ID spoofing.
        if ($commentId !== null) {
            $belongs = BlockComment::query()->withoutGlobalScope('workspace')
                ->where('block_id', $block->id)->whereKey((int)$commentId)->exists();
            abort_unless($belongs, 404);
        }
        if ($postId !== null) {
            $post = CommunityPost::query()->withoutGlobalScope('workspace')
                ->where('block_id', $block->id)->whereKey((int)$postId)->first();
            abort_unless($post, 404);
            // Reactions on a gated Insider post inherit the post's access
            // (public/members/paid/followers) — non-members must not be
            // able to react via ID enumeration.
            if (!$this->canSeePost($post, $member, $link, $viewerId)) {
                return response()->json(['ok' => false, 'error' => 'Not allowed.'], 403);
            }
        } else {
            // Block-scoped reactions follow the block's comments_visibility
            // gate (the same gate that controls who can see/post comments
            // on the block). This stops non-members from reacting to
            // members-only / followers-only blocks.
            $visibility = $this->blockVisibility($block, 'comments_visibility');
            if ($denied = $this->gateCheck($visibility, $request, $link, $block)) return $denied;
        }

        // NULL-safe lookup: stored rows use NULL for the non-applicable
        // target column, so equality with 0 would never match. Use
        // whereNull for missing targets so toggle-off works correctly
        // and we don't double-insert duplicate reactions.

        $existing = BlockReaction::query()
            ->withoutGlobalScope('workspace')
            ->where('block_id', $block->id)
            ->where('voter_fingerprint', $fp)
            ->where('emoji', $data['emoji'])
            ->when($commentId !== null, fn ($q) => $q->where('comment_id', (int)$commentId), fn ($q) => $q->whereNull('comment_id'))
            ->when($postId !== null,    fn ($q) => $q->where('post_id',    (int)$postId),    fn ($q) => $q->whereNull('post_id'))
            ->first();

        if ($existing) {
            $existing->delete();
            // Mirror the increment on toggle-off so the post-level
            // reactions_count stays accurate.
            if ($postId !== null) {
                CommunityPost::query()->withoutGlobalScope('workspace')
                    ->whereKey((int)$postId)->where('reactions_count', '>', 0)
                    ->decrement('reactions_count');
            }
            return response()->json(['ok' => true, 'toggled' => 'off']);
        }

        $r = BlockReaction::create([
            'link_id'           => $link->id,
            'block_id'          => $block->id,
            'comment_id'        => $data['comment_id'] ?? null,
            'post_id'           => $data['post_id'] ?? null,
            'workspace_id'      => $link->workspace_id ?? null,
            'viewer_user_id'    => $viewerId,
            'voter_fingerprint' => $fp,
            'emoji'             => $data['emoji'],
        ]);

        $this->points->award(
            $link, 'reaction', $r,
            $viewerId, $fp, $this->displayName($request)
        );

        // Keep the post-level reactions counter in sync for analytics.
        if ($postId !== null) {
            CommunityPost::query()->withoutGlobalScope('workspace')
                ->whereKey((int)$postId)->increment('reactions_count');
        }

        return response()->json(['ok' => true, 'toggled' => 'on']);
    }

    public function votePoll(Request $request, Link $link, BiolinkBlock $block, BlockPoll $poll)
    {
        abort_if($block->link_id !== $link->id, 404);
        $this->ensureFeatureEnabled($block, 'polls');

        // Banned authors lose poll-voting privileges too.
        if ($this->isBannedAuthor($request, $link, $block)) {
            return response()->json(['ok' => false, 'error' => 'You are not allowed to vote here.'], 403);
        }
        abort_if($poll->block_id !== $block->id, 404);
        abort_unless($poll->isOpen(), 423, 'Poll closed.');

        // Polls attached to a CommunityPost inherit that post's access
        // (public/members/paid/followers). Enforce it before the
        // poll-level visibility check so paid posts can't be voted on
        // by free viewers via the votePoll endpoint either.
        if ($poll->post_id) {
            $post = CommunityPost::query()->withoutGlobalScope('workspace')
                ->whereKey($poll->post_id)->first();
            $member = $this->resolveMember($request, $link, $block);
            $viewerId = $this->viewerUserId($request);
            if (!$post || !$this->canSeePost($post, $member, $link, $viewerId)) {
                return response()->json(['ok' => false, 'error' => 'Not allowed.'], 403);
            }
        }

        // The poll carries its own visibility (public/members/followers) so
        // a creator can keep an Insider-only poll inside an otherwise public
        // block. Enforce it before recording any votes.
        $visibility = in_array($poll->visibility, ['public', 'members', 'followers'], true)
            ? $poll->visibility
            : 'public';
        if ($denied = $this->gateCheck($visibility, $request, $link, $block)) return $denied;

        $optionCount = is_array($poll->options) ? count($poll->options) : 0;
        abort_if($optionCount === 0, 422, 'Poll has no options.');

        $data = $request->validate([
            'options'   => ['required', 'array', 'min:1'],
            // Bound the option index to the actual options on the poll
            // so out-of-range votes can't be inserted to skew tallies.
            'options.*' => ['integer', 'min:0', 'max:' . ($optionCount - 1)],
        ]);

        $picks = $poll->multi_select ? array_unique($data['options']) : [reset($data['options'])];
        $fp = $this->fingerprint($request);
        $viewerId = $this->viewerUserId($request);

        DB::transaction(function () use ($poll, $picks, $fp, $viewerId) {
            // Replace this voter's existing votes so re-voting is idempotent.
            BlockPollVote::where('poll_id', $poll->id)
                ->where('voter_fingerprint', $fp)
                ->delete();
            foreach ($picks as $idx) {
                BlockPollVote::create([
                    'poll_id'           => $poll->id,
                    'option_index'      => (int)$idx,
                    'viewer_user_id'    => $viewerId,
                    'voter_fingerprint' => $fp,
                ]);
            }
        });

        return response()->json(['ok' => true, 'tally' => $poll->tally()]);
    }

    public function leaderboard(Request $request, Link $link)
    {
        // Per-link opt-in: when the creator hasn't enabled the leaderboard
        // (or has disabled it after the fact), the historical points
        // ledger must NOT be queryable from the public surface.
        $settings = FanLeaderboardSetting::query()
            ->withoutGlobalScope('workspace')
            ->where('link_id', $link->id)
            ->first();
        if (!$settings || !$settings->is_enabled) {
            return response()->json(['ok' => false, 'fans' => [], 'enabled' => false], 404);
        }
        return response()->json([
            'enabled' => true,
            'fans'    => $this->points->topFans($link, 25),
        ]);
    }

    /**
     * Webhook entry point used by the billing pipeline (e.g. Stripe
     * subscription activated) to provision or upgrade a paid Insider
     * member. Protected by a shared secret in the X-Insider-Signature
     * header that's compared in constant time against
     * config('services.insider.webhook_secret').
     *
     * Requests without a configured secret are rejected — there is no
     * "open" mode here on purpose, because this endpoint can mint paid
     * members. Pair this with the existing Stripe webhook handler in a
     * follow-up to fully automate paid signup (#881).
     */
    public function joinInsiderPaid(Request $request, Link $link, BiolinkBlock $block)
    {
        abort_if($block->link_id !== $link->id, 404);
        abort_if($block->type !== 'insider', 404);

        $configured = (string) config('services.insider.webhook_secret', '');
        $provided   = (string) $request->header('X-Insider-Signature', '');
        if ($configured === '' || !hash_equals($configured, $provided)) {
            return response()->json(['ok' => false, 'error' => 'Invalid signature.'], 401);
        }

        $data = $request->validate([
            'email'                  => ['required', 'email', 'max:255'],
            'display_name'           => ['nullable', 'string', 'max:80'],
            'stripe_subscription_id' => ['nullable', 'string', 'max:255'],
            'expires_at'             => ['nullable', 'date'],
        ]);

        $member = CommunityMember::query()->withoutGlobalScope('workspace')->updateOrCreate(
            ['link_id' => $link->id, 'email' => $data['email']],
            [
                'user_id'                => $link->user_id,
                'block_id'               => $block->id,
                'workspace_id'           => $link->workspace_id ?? null,
                'display_name'           => $data['display_name'] ?? null,
                'tier'                   => 'paid',
                'status'                 => 'active',
                'stripe_subscription_id' => $data['stripe_subscription_id'] ?? null,
                'expires_at'             => $data['expires_at'] ?? null,
                'joined_at'              => now(),
            ]
        );

        return response()->json(['ok' => true, 'member_id' => $member->id, 'tier' => 'paid']);
    }

    /**
     * Fire-and-forget tracker the public biolink page calls when a viewer
     * shares the link or clicks an outbound link. Awards points so the
     * leaderboard reflects word-of-mouth activity.
     */
    public function trackEngagement(Request $request, Link $link)
    {
        $data = $request->validate([
            'action' => ['required', 'in:share,click'],
        ]);

        $this->points->award(
            $link, $data['action'], $link,
            $this->viewerUserId($request),
            $this->fingerprint($request),
            $this->displayName($request)
        );

        return response()->json(['ok' => true]);
    }
}
