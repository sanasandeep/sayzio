<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\CreatorPostComment;
use App\Modules\User\Models\CreatorPostReaction;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
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
            'reactions_catalog' => CreatorPostReaction::REACTIONS,
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

        $items = collect($page->items())->map(function (CreatorPost $p) use ($totals, $mine) {
            return [
                'id'              => $p->id,
                'post_type'       => $p->effectiveType(),
                'title'           => $p->title,
                'body'            => $p->body,
                'image'           => $p->image,
                'media'           => is_array($p->media) ? $p->media : null,
                'is_pinned'       => $p->isPinned(),
                'published_at'    => optional($p->published_at)->toIso8601String(),
                'reactions_count' => (int) $p->reactions_count,
                'comments_count'  => (int) $p->comments_count,
                'reaction_totals' => $totals[$p->id] ?? new \stdClass(),
                'my_reaction'     => $mine[$p->id] ?? null,
            ];
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
