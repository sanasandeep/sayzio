<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\CreatorPostComment;
use App\Modules\User\Models\CreatorPostReaction;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\SubscriptionTier;
use App\Modules\User\Models\CreatorSubscription;
use App\Modules\User\Models\User;
use App\Services\Monetization\PostAccessPolicy;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * JSON API surface for the new Creator Profile so the Expo mobile app
 * can render the same /@handle page natively. Mirrors the web
 * controller's behaviour and shares its underlying tables.
 */
class CreatorProfileApiController extends Controller
{
    use ApiResponses;

    public function show(Request $request, string $handle)
    {
        $handle  = ltrim($handle, '@');
        $creator = User::query()
            ->whereRaw('LOWER(handle) = ?', [strtolower($handle)])
            ->first();
        if (!$creator) return $this->notFound('Creator not found');

        $viewer  = $request->user();
        $isOwner = $viewer && (int) $viewer->id === (int) $creator->id;
        if (!$isOwner && !$creator->profile_published) {
            return $this->notFound('Creator not found');
        }

        $isFollowing = $viewer && !$isOwner
            ? Follow::where('follower_id', $viewer->id)->where('creator_id', $creator->id)->exists()
            : false;

        $primaryBiolink = Link::where('user_id', $creator->id)
            ->where('type', 'biolink')->where('is_active', true)
            ->orderBy('id')->first();

        // ── Monetization (Task #1209) ────────────────────────────
        // Active tiers for the Subscribe sheet, plus the viewer's
        // own current subscription if they have one. Mirrors the
        // shape exposed by the web profile controller.
        $tiers = SubscriptionTier::query()
            ->where('user_id', $creator->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price_monthly_cents')
            ->get()
            ->map(fn (SubscriptionTier $t) => [
                'id'                       => $t->id,
                'slug'                     => $t->slug,
                'name'                     => $t->name,
                'is_free'                  => (bool) $t->is_free,
                'is_active'                => (bool) $t->is_active,
                'price_monthly_cents'      => (int) $t->price_monthly_cents,
                'price_yearly_cents'       => $t->price_yearly_cents !== null ? (int) $t->price_yearly_cents : null,
                'currency'                 => $t->currency,
                'trial_days'               => (int) $t->trial_days,
                'badge'                    => $t->badge,
                'color'                    => $t->color,
                'perks'                    => $t->visiblePerks(),
                'yearly_discount_percent'  => $t->yearlyDiscountPercent(),
            ])->values();

        $viewerSubscription = null;
        if ($viewer && !$isOwner) {
            $sub = CreatorSubscription::query()
                ->where('fan_user_id', $viewer->id)
                ->where('creator_user_id', $creator->id)
                ->whereIn('status', [
                    CreatorSubscription::STATUS_ACTIVE,
                    CreatorSubscription::STATUS_TRIALING,
                    CreatorSubscription::STATUS_PAST_DUE,
                ])
                ->first();
            if ($sub) {
                $tier = SubscriptionTier::find($sub->tier_id);
                $viewerSubscription = [
                    'id'                       => $sub->id,
                    'tier_id'                  => $sub->tier_id,
                    'tier_name'                => $tier?->name,
                    'tier_badge'               => $tier?->badge,
                    'status'                   => $sub->status,
                    'status_label'             => $sub->statusLabel(),
                    'billing_cycle'            => $sub->billing_cycle,
                    'price_cents'              => (int) $sub->price_cents,
                    'currency'                 => $sub->currency,
                    'current_period_end'       => optional($sub->current_period_end)->toIso8601String(),
                    'cancel_at_period_end'     => (bool) $sub->cancel_at_period_end,
                    'is_current'               => $sub->isCurrent(),
                ];
            }
        }

        return $this->ok([
            'profile' => [
                'id'              => $creator->id,
                'handle'          => $creator->handle,
                'name'            => $creator->name,
                'avatar'          => $creator->avatar,
                'cover_image'     => $creator->cover_image,
                'tagline'         => $creator->tagline,
                'bio'             => $creator->bio,
                'location'        => $creator->location,
                'niche_tags'      => is_array($creator->niche_tags) ? $creator->niche_tags : [],
                'socials'         => is_array($creator->socials) ? $creator->socials : [],
                'sections'        => $creator->profileSectionVisibility(),
                'profile_published' => (bool) $creator->profile_published,
                'followers_count' => (int) $creator->followers_count,
                'posts_count'     => (int) $creator->posts_count,
                'is_following'    => $isFollowing,
                'is_owner'        => $isOwner,
                'biolink_url'     => $primaryBiolink ? url('/' . $primaryBiolink->alias) : null,
            ],
            'reactions_catalog'   => CreatorPostReaction::REACTIONS,
            'tiers'               => $tiers,
            'viewer_subscription' => $viewerSubscription,
        ]);
    }

    public function feed(Request $request, string $handle)
    {
        $handle  = ltrim($handle, '@');
        $creator = User::query()->whereRaw('LOWER(handle) = ?', [strtolower($handle)])->first();
        if (!$creator) return $this->notFound('Creator not found');

        $viewer  = $request->user();
        $isOwner = $viewer && (int) $viewer->id === (int) $creator->id;
        if (!$isOwner && !$creator->profile_published) {
            return $this->notFound('Creator not found');
        }

        $page = CreatorPost::query()
            ->withoutGlobalScope('workspace')
            ->where('user_id', $creator->id)
            ->whereNotNull('published_at')
            ->orderByDesc('pinned_at')
            ->orderByDesc('published_at')
            ->paginate(min(50, max(1, (int) $request->input('per_page', 15))));

        $postIds = collect($page->items())->pluck('id')->all();
        $totals  = $this->reactionTotalsByPost($postIds);
        $mine    = $viewer
            ? CreatorPostReaction::whereIn('post_id', $postIds)
                ->where('viewer_user_id', $viewer->id)
                ->pluck('reaction', 'post_id')->all()
            : [];

        // Per-post paywall access (Task #1209). Bulk-evaluated so we
        // don't issue a query per post when rendering a page of 15.
        $access = PostAccessPolicy::evaluateMany($viewer, collect($page->items()));

        $items = collect($page->items())->map(function (CreatorPost $p) use ($totals, $mine, $access) {
            $a = $access[$p->id] ?? ['can' => true, 'reason' => 'free', 'requires_subscription' => false, 'requires_ppv' => false];
            $lowest = $a['lowest_tier'] ?? null;
            $payload = [
                'id'              => $p->id,
                'post_type'       => $p->effectiveType(),
                'title'           => $p->title,
                'body'            => $p->body,
                'teaser_caption'  => $p->teaser_caption,
                'image'           => $p->image,
                'media'           => is_array($p->media) ? $p->media : null,
                'is_pinned'       => $p->isPinned(),
                'published_at'    => optional($p->published_at)->toIso8601String(),
                'reactions_count' => (int) $p->reactions_count,
                'comments_count'  => (int) $p->comments_count,
                'reaction_totals' => $totals[$p->id] ?? new \stdClass(),
                'my_reaction'     => $mine[$p->id] ?? null,
                'visibility'      => $p->visibility,
                'ppv_price_cents' => $p->ppv_price_cents !== null ? (int) $p->ppv_price_cents : null,
                'blur_intensity'  => $p->blur_intensity ?? 'medium',
                'access'          => [
                    'can'                   => (bool) $a['can'],
                    'reason'                => $a['reason'],
                    'requires_subscription' => (bool) ($a['requires_subscription'] ?? false),
                    'requires_ppv'          => (bool) ($a['requires_ppv'] ?? false),
                    'lowest_tier'           => $lowest ? [
                        'id'    => $lowest->id,
                        'name'  => $lowest->name,
                        'badge' => $lowest->badge,
                        'price_monthly_cents' => (int) $lowest->price_monthly_cents,
                        'currency' => $lowest->currency,
                    ] : null,
                ],
            ];
            // Server-side gating (Task #1209): when the viewer can't see the
            // post, strip body + media URLs entirely so the client cannot
            // bypass the paywall by ignoring `access.can` or removing CSS
            // blur. Only safe metadata (teaser_caption, blur_intensity)
            // and a body excerpt remain.
            if (!($a['can'] ?? true)) {
                $payload = PostAccessPolicy::maskForLockedViewer($payload, $p);
            }
            return $payload;
        })->all();

        return $this->ok([
            'items' => $items,
            'meta'  => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }

    public function react(Request $request, string $handle, int $post)
    {
        $viewer = $request->user();
        if (!$viewer) return $this->fail('Sign in to react.', 401);

        $creator = User::query()->whereRaw('LOWER(handle) = ?', [strtolower(ltrim($handle, '@'))])->first();
        if (!$creator) return $this->notFound();

        $p = CreatorPost::query()->withoutGlobalScope('workspace')
            ->where('user_id', $creator->id)->whereKey($post)->first();
        if (!$p || !$p->published_at) return $this->notFound();

        $data = $request->validate([
            'reaction' => 'required|string|in:' . implode(',', CreatorPostReaction::reactionKeys()),
        ]);

        $existing = CreatorPostReaction::where('post_id', $p->id)
            ->where('viewer_user_id', $viewer->id)->first();

        DB::transaction(function () use (&$existing, $p, $viewer, $data) {
            if ($existing && $existing->reaction === $data['reaction']) {
                $existing->delete(); $existing = null;
                $p->decrement('reactions_count');
            } elseif ($existing) {
                $existing->reaction = $data['reaction']; $existing->save();
            } else {
                $existing = CreatorPostReaction::create([
                    'post_id' => $p->id, 'viewer_user_id' => $viewer->id,
                    'reaction' => $data['reaction'], 'created_at' => now(),
                ]);
                $p->increment('reactions_count');
            }
        });

        return $this->ok([
            'reaction' => $existing?->reaction,
            'totals'   => $this->reactionTotalsByPost([$p->id])[$p->id] ?? [],
            'count'    => (int) $p->fresh()->reactions_count,
        ]);
    }

    public function comments(Request $request, string $handle, int $post)
    {
        $creator = User::query()->whereRaw('LOWER(handle) = ?', [strtolower(ltrim($handle, '@'))])->first();
        if (!$creator) return $this->notFound();

        $p = CreatorPost::query()->withoutGlobalScope('workspace')
            ->where('user_id', $creator->id)->whereKey($post)->first();
        if (!$p) return $this->notFound();

        $rows = CreatorPostComment::query()
            ->where('post_id', $p->id)
            ->whereNull('parent_id')
            ->where('status', 'visible')
            ->with(['viewer:id,name,handle,avatar', 'replies.viewer:id,name,handle,avatar'])
            ->orderBy('created_at')
            ->paginate(min(50, max(1, (int) $request->input('per_page', 25))));

        $items = collect($rows->items())->map(fn (CreatorPostComment $c) => $this->commentToArray($c))->all();

        return $this->ok([
            'items' => $items,
            'meta'  => [
                'current_page' => $rows->currentPage(),
                'per_page'     => $rows->perPage(),
                'total'        => $rows->total(),
                'last_page'    => $rows->lastPage(),
            ],
        ]);
    }

    public function comment(Request $request, string $handle, int $post)
    {
        $viewer = $request->user();
        if (!$viewer) return $this->fail('Sign in to comment.', 401);

        $creator = User::query()->whereRaw('LOWER(handle) = ?', [strtolower(ltrim($handle, '@'))])->first();
        if (!$creator) return $this->notFound();

        $p = CreatorPost::query()->withoutGlobalScope('workspace')
            ->where('user_id', $creator->id)->whereKey($post)->first();
        if (!$p || !$p->published_at) return $this->notFound();

        $data = $request->validate([
            'body'      => 'required|string|max:2000',
            'parent_id' => 'nullable|integer|exists:creator_post_comments,id',
        ]);

        $rateKey = 'cp-comment:' . $viewer->id;
        if (RateLimiter::tooManyAttempts($rateKey, 30)) {
            return $this->fail('You are commenting too quickly. Please slow down.', 429);
        }
        RateLimiter::hit($rateKey, 60);

        $parentId = null;
        if (!empty($data['parent_id'])) {
            $parent = CreatorPostComment::find($data['parent_id']);
            if (!$parent || $parent->post_id !== $p->id) {
                return $this->fail('Invalid parent comment.', 422);
            }
            if ($parent->parent_id) {
                return $this->fail('Replies are limited to one level — reply to the original comment instead.', 422);
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
        $c->load('viewer:id,name,handle,avatar');

        return $this->created(['comment' => $this->commentToArray($c)]);
    }

    private function commentToArray(CreatorPostComment $c): array
    {
        return [
            'id'         => $c->id,
            'parent_id'  => $c->parent_id,
            'body'       => $c->body,
            'created_at' => optional($c->created_at)->toIso8601String(),
            'author'     => $c->viewer ? [
                'id'     => $c->viewer->id,
                'name'   => $c->viewer->name,
                'handle' => $c->viewer->handle,
                'avatar' => $c->viewer->avatar,
            ] : null,
            'replies'    => $c->relationLoaded('replies') ? collect($c->replies)->map(fn ($r) => [
                'id'         => $r->id,
                'parent_id'  => $r->parent_id,
                'body'       => $r->body,
                'created_at' => optional($r->created_at)->toIso8601String(),
                'author'     => $r->viewer ? [
                    'id'     => $r->viewer->id,
                    'name'   => $r->viewer->name,
                    'handle' => $r->viewer->handle,
                    'avatar' => $r->viewer->avatar,
                ] : null,
            ])->all() : [],
        ];
    }

    private function reactionTotalsByPost(array $postIds): array
    {
        if (empty($postIds)) return [];
        $rows = DB::table('creator_post_reactions')
            ->select('post_id', 'reaction', DB::raw('COUNT(*) as c'))
            ->whereIn('post_id', $postIds)
            ->groupBy('post_id', 'reaction')->get();
        $out = [];
        foreach ($rows as $r) $out[(int) $r->post_id][$r->reaction] = (int) $r->c;
        return $out;
    }
}
