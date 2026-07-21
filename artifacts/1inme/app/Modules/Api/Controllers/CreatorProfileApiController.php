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

        return $this->ok([
            'profile'             => $this->profilePayload($creator, $viewer, $isOwner, $isFollowing),
            'reactions_catalog'   => CreatorPostReaction::REACTIONS,
            'tiers'               => $this->tiersPayload($creator),
            'viewer_subscription' => $this->viewerSubscriptionPayload($creator, $viewer, $isOwner),
        ]);
    }

    /**
     * Task #5480 — mint a short-lived signed URL for the owner's live
     * profile preview (/@handle?cp_preview=1). The mobile app has no web
     * session, so the public page accepts a valid (relative) signature as
     * proof of ownership instead — only the authenticated owner can mint
     * one here. Returned as a RELATIVE path so the app prepends its own
     * base URL (hosts differ between dev proxy / production domains).
     */
    public function previewUrl(Request $request)
    {
        $user = $request->user();
        if (!$user || empty($user->handle)) {
            return $this->error('Claim a handle first to preview your profile.', 'no_handle', 422);
        }

        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'creator-profile.show',
            now()->addMinutes(30),
            ['handle' => $user->handle, 'cp_preview' => 1],
            absolute: false,
        );

        return $this->ok([
            'url'        => $url,
            'expires_in' => 30 * 60,
        ]);
    }

    /**
     * Public Paid Page (Task #1208 / #1649). Resolves a `paid_page` link by
     * its alias and returns the same creator/profile/tier payload as the
     * /@handle surface PLUS the chosen design template (decomposed into
     * mobile-friendly tokens) and the page-level public/gated state. The
     * Expo app renders this natively so the bold per-link design matches
     * the web `public/paid-page.blade.php` renderer. Reuses the existing
     * handle-keyed posts/react/comment endpoints for the feed itself.
     */
    public function paidPageShow(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if (!$link || $link->type !== Link::TYPE_PAID_PAGE || !$link->is_active) {
            return $this->notFound('Page not found');
        }

        $creator = User::find($link->user_id);
        if (!$creator) return $this->notFound('Page not found');

        $viewer  = $request->user();
        $isOwner = $viewer && (int) $viewer->id === (int) $creator->id;

        if ($gate = $this->enforcePaidPageVisibility($link, $viewer, $isOwner)) {
            return $gate;
        }

        $isFollowing = $viewer && !$isOwner
            ? Follow::where('follower_id', $viewer->id)->where('creator_id', $creator->id)->exists()
            : false;

        $template = \App\Modules\User\Support\PaidPageTemplates::applyCustomBackground(
            \App\Modules\User\Support\PaidPageTemplates::get($link->settings['paid_page']['template'] ?? null),
            $link->settings['paid_page'] ?? []
        );

        return $this->ok([
            'page' => [
                'alias'       => $link->alias,
                'handle'      => $creator->handle,
                'title'       => $link->title ?: $creator->name,
                'description' => $link->seo_description ?: $creator->tagline,
                'visibility'  => $link->visibility ?? 'public',
                'is_owner'    => $isOwner,
            ],
            'template'            => \App\Modules\User\Support\PaidPageTemplates::mobileTokens($template),
            'profile'             => $this->profilePayload($creator, $viewer, $isOwner, $isFollowing),
            'reactions_catalog'   => CreatorPostReaction::REACTIONS,
            'tiers'               => $this->tiersPayload($creator),
            'viewer_subscription' => $this->viewerSubscriptionPayload($creator, $viewer, $isOwner),
        ]);
    }

    /**
     * Posts feed for a Paid Page, resolved by link alias. Unlike the
     * handle-keyed feed() this does NOT require the creator to have a
     * published profile — the Paid Page link itself is the publication
     * surface — but it does honour the page-level visibility gate.
     */
    public function paidPageFeed(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if (!$link || $link->type !== Link::TYPE_PAID_PAGE || !$link->is_active) {
            return $this->notFound('Page not found');
        }

        $creator = User::find($link->user_id);
        if (!$creator) return $this->notFound('Page not found');

        $viewer  = $request->user();
        $isOwner = $viewer && (int) $viewer->id === (int) $creator->id;

        if ($gate = $this->enforcePaidPageVisibility($link, $viewer, $isOwner)) {
            return $gate;
        }

        return $this->buildFeedResponse($request, $creator, $viewer);
    }

    /**
     * Page-level visibility gate for a Paid Page link. Mirrors
     * RedirectController::enforceVisibility for the registered / followers /
     * subscribers tiers (the editor only sets public/registered today, but
     * we honour the full set to stay in lockstep with the web column).
     * Returns a JSON error response when gated, or null to proceed.
     */
    private function enforcePaidPageVisibility(Link $link, ?User $viewer, bool $isOwner)
    {
        $vis = $link->visibility ?? 'public';
        if ($vis === 'public' || $isOwner) return null;

        $viewerId = $viewer?->id;

        if ($vis === 'registered') {
            if (!$viewerId) {
                return $this->fail('Sign in to view this page.', 401, 'gated_registered');
            }
            return null;
        }

        if ($vis === 'followers') {
            $ok = $viewerId && Follow::where('follower_id', $viewerId)
                ->where('creator_id', $link->user_id)->exists();
            if (!$ok) {
                return $this->fail('Follow this creator to view this page.', 403, 'gated_followers');
            }
            return null;
        }

        if ($vis === 'subscribers') {
            $ok = $viewerId && CreatorSubscription::query()
                ->where('fan_user_id', $viewerId)
                ->where('creator_user_id', $link->user_id)
                ->whereIn('status', [
                    CreatorSubscription::STATUS_ACTIVE,
                    CreatorSubscription::STATUS_TRIALING,
                    CreatorSubscription::STATUS_PAST_DUE,
                ])->exists();
            if (!$ok) {
                return $this->fail('Subscribe to view this page.', 403, 'gated_subscribers');
            }
        }

        return null;
    }

    /**
     * Shared creator/profile payload used by both the handle-keyed show()
     * and the alias-keyed paidPageShow().
     *
     * @return array<string,mixed>
     */
    private function profilePayload(User $creator, ?User $viewer, bool $isOwner, bool $isFollowing): array
    {
        $primaryBiolink = Link::where('user_id', $creator->id)
            ->whereIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)->where('is_active', true)
            ->orderBy('id')->first();

        $sections        = $creator->profileSectionVisibility();
        $showcase        = $creator->resolvedProfileShowcase();
        $featuredLinks   = $this->apiResolveFeaturedLinks($creator, $showcase, $sections);
        $showcaseCards   = $this->apiResolveShowcaseCards($creator, $showcase, $sections);
        $totalPublicLinks = Link::query()
            ->withoutGlobalScope('workspace')
            ->where('user_id', $creator->id)
            ->where('is_active', true)
            ->where('visibility', 'public')
            ->count();

        return [
            'id'                  => $creator->id,
            'handle'              => $creator->handle,
            'name'                => $creator->name,
            'avatar'              => \App\Support\PublicStorageUrl::resolve($creator->creatorAvatarRaw()),
            'cover_image'         => \App\Support\PublicStorageUrl::resolve($creator->cover_image),
            'tagline'             => $creator->tagline,
            'bio'                 => $creator->bio,
            'location'            => $creator->location,
            'niche_tags'          => is_array($creator->niche_tags) ? $creator->niche_tags : [],
            'socials'             => is_array($creator->socials) ? $creator->socials : [],
            'sections'            => $sections,
            'profile_published'   => (bool) $creator->profile_published,
            'followers_count'     => (int) $creator->followers_count,
            'posts_count'         => (int) $creator->posts_count,
            'total_public_links'  => (int) $totalPublicLinks,
            'is_following'        => $isFollowing,
            'is_owner'            => $isOwner,
            'created_at'          => $creator->created_at?->toIso8601String(),
            'biolink_url'         => $primaryBiolink ? url('/' . $primaryBiolink->alias) : null,
            'theme_color'         => $creator->profile_theme_color ?: null,
            'showcase'            => [
                'show_link_stats'      => (bool) ($showcase['show_link_stats'] ?? false),
                'featured_links_style' => $showcase['featured_links_style'] ?? 'classic',
                'highlights'           => $showcase['highlights'],
                'cta'                  => $showcase['cta'],
            ],
            'featured_links'      => $featuredLinks,
            'showcase_cards'      => $showcaseCards,
        ];
    }

    /**
     * Resolve featured links for the API payload — public visibility only,
     * preserving the owner-defined order.
     *
     * @return array<int, array<string,mixed>>
     */
    private function apiResolveFeaturedLinks(User $creator, array $showcase, array $sectionsVisible): array
    {
        if (empty($sectionsVisible['featured_links'])) return [];
        $rawItems = is_array($showcase['featured_links'] ?? null) ? $showcase['featured_links'] : [];
        // Keep only enabled entries and extract IDs in owner-defined order.
        $ids = array_values(array_filter(array_map(function ($item) {
            if (!is_array($item)) return 0;
            return ($item['enabled'] ?? true) ? (int) ($item['id'] ?? 0) : 0;
        }, $rawItems)));
        if (empty($ids)) return [];

        $links = Link::query()
            ->withoutGlobalScope('workspace')
            ->where('user_id', $creator->id)
            ->where('is_active', true)
            ->where('visibility', 'public')
            ->whereIn('id', $ids)
            ->get(['id', 'title', 'alias', 'type'])
            ->keyBy('id');

        $ordered = [];
        foreach ($ids as $id) {
            if (!isset($links[$id])) continue;
            $l = $links[$id];
            $ordered[] = [
                'id'     => $l->id,
                'title'  => $l->title,
                'alias'  => $l->alias,
                'type'   => $l->type,
                'url'    => url('/' . $l->alias),
                'clicks' => ($showcase['show_link_stats'] ?? false) ? ((int) $l->clicks_count) : null,
            ];
        }
        return $ordered;
    }

    /**
     * Resolve showcase cards for the API payload.
     *
     * @return array<int, array<string,mixed>>
     */
    private function apiResolveShowcaseCards(User $creator, array $showcase, array $sectionsVisible): array
    {
        if (empty($sectionsVisible['showcase'])) return [];
        $items = is_array($showcase['showcase_items'] ?? null) ? $showcase['showcase_items'] : [];
        if (empty($items)) return [];

        $linkIds = array_values(array_unique(array_column($items, 'link_id')));
        $links = Link::query()
            ->withoutGlobalScope('workspace')
            ->where('user_id', $creator->id)
            ->where('is_active', true)
            ->where('visibility', 'public')
            ->whereIn('id', $linkIds)
            ->get(['id', 'title', 'alias', 'type'])
            ->keyBy('id');

        $cards = [];
        foreach ($items as $item) {
            $linkId = (int) ($item['link_id'] ?? 0);
            $type   = (string) ($item['type'] ?? '');
            $l      = $links[$linkId] ?? null;
            if (!$l) continue;
            $cards[] = [
                'type'  => $type,
                'id'    => $l->id,
                'title' => $l->title,
                'alias' => $l->alias,
                'url'   => url('/' . $l->alias),
            ];
        }
        return $cards;
    }

    /**
     * Lightweight mini-summary for the hover-card popover (mobile parity
     * of GET /@{handle}/mini on the web).  Public; no auth required.
     */
    public function mini(Request $request, string $handle)
    {
        $handle  = ltrim($handle, '@');
        $creator = User::query()
            ->whereRaw('LOWER(handle) = ?', [strtolower($handle)])
            ->first();

        if (!$creator || !$creator->profile_published) {
            return $this->notFound('Creator not found');
        }

        $isVerified = method_exists($creator, 'isVerified') ? $creator->isVerified() : !empty($creator->email_verified_at);

        return $this->ok([
            'handle'          => $creator->handle,
            'name'            => $creator->name,
            'avatar'          => \App\Support\PublicStorageUrl::resolve($creator->creatorAvatarRaw()),
            'tagline'         => $creator->tagline,
            'followers_count' => (int) $creator->followers_count,
            'is_verified'     => $isVerified,
            'theme_color'     => $creator->profile_theme_color ?: null,
            'profile_url'     => route('creator-profile.show', $creator->handle),
            'profile_published' => true,
        ]);
    }

    /**
     * Active tiers for the Subscribe sheet. Mirrors the shape exposed by
     * the web profile controller.
     */
    private function tiersPayload(User $creator)
    {
        return SubscriptionTier::query()
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
    }

    /** The viewer's own current subscription to this creator, if any. */
    private function viewerSubscriptionPayload(User $creator, ?User $viewer, bool $isOwner): ?array
    {
        if (!$viewer || $isOwner) return null;

        $sub = CreatorSubscription::query()
            ->where('fan_user_id', $viewer->id)
            ->where('creator_user_id', $creator->id)
            ->whereIn('status', [
                CreatorSubscription::STATUS_ACTIVE,
                CreatorSubscription::STATUS_TRIALING,
                CreatorSubscription::STATUS_PAST_DUE,
            ])
            ->first();
        if (!$sub) return null;

        $tier = SubscriptionTier::find($sub->tier_id);
        return [
            'id'                   => $sub->id,
            'tier_id'              => $sub->tier_id,
            'tier_name'            => $tier?->name,
            'tier_badge'           => $tier?->badge,
            'status'               => $sub->status,
            'status_label'         => $sub->statusLabel(),
            'billing_cycle'        => $sub->billing_cycle,
            'price_cents'          => (int) $sub->price_cents,
            'currency'             => $sub->currency,
            'current_period_end'   => optional($sub->current_period_end)->toIso8601String(),
            'cancel_at_period_end' => (bool) $sub->cancel_at_period_end,
            'is_current'           => $sub->isCurrent(),
        ];
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

        return $this->buildFeedResponse($request, $creator, $viewer);
    }

    /**
     * Shared paginated feed builder used by both the handle-keyed feed()
     * and the alias-keyed paidPageFeed(). Applies the same per-post
     * paywall masking so a locked post never leaks its body/media URLs.
     */
    private function buildFeedResponse(Request $request, User $creator, ?User $viewer)
    {
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
                'image'           => \App\Support\PublicStorageUrl::resolve($p->image),
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
                'avatar' => \App\Support\PublicStorageUrl::resolve($c->viewer->avatar),
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
                    'avatar' => \App\Support\PublicStorageUrl::resolve($r->viewer->avatar),
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
