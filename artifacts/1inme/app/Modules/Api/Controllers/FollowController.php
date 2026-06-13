<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Api\Resources\UserResource;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class FollowController extends Controller
{
    use ApiResponses;

    public function follow(Request $request, int $userId)
    {
        $viewer = $request->user();
        if ((int) $viewer->id === $userId) {
            return $this->fail('You cannot follow yourself', 422, 'self_follow');
        }

        $creator = User::find($userId);
        if (!$creator) return $this->notFound('User not found');
        if (!($creator->allow_followers ?? true)) {
            return $this->forbidden('This user does not accept followers');
        }

        $created = false;
        DB::transaction(function () use ($viewer, $creator, &$created) {
            $exists = Follow::where('follower_id', $viewer->id)->where('creator_id', $creator->id)->exists();
            if (!$exists) {
                $f = new Follow([
                    'follower_id' => $viewer->id,
                    'creator_id'  => $creator->id,
                    'created_at'  => now(),
                ]);
                $f->workspace_id = $this->activeWorkspaceId($viewer);
                $f->save();
                $creator->increment('followers_count');
                $created = true;
            }
        });

        // Paid DMs (Task #1210): only fire welcome rules on the
        // very first follow, not on a duplicate "follow" call.
        if ($created) {
            try {
                app(\App\Services\Dm\DmDispatcher::class)->triggerNewFollower($creator, $viewer);
            } catch (\Throwable $e) { /* welcome rules must never block follow */ }
        }

        return $this->ok([
            'following'       => true,
            'created'         => $created,
            'followers_count' => (int) $creator->fresh()->followers_count,
        ], $created ? 201 : 200);
    }

    public function unfollow(Request $request, int $userId)
    {
        $viewer = $request->user();
        $creator = User::find($userId);
        if (!$creator) return $this->notFound('User not found');

        DB::transaction(function () use ($viewer, $creator) {
            $deleted = Follow::where('follower_id', $viewer->id)->where('creator_id', $creator->id)->delete();
            if ($deleted > 0 && (int) $creator->followers_count > 0) {
                $creator->decrement('followers_count');
            }
        });

        return $this->ok([
            'following'       => false,
            'followers_count' => (int) $creator->fresh()->followers_count,
        ]);
    }

    public function following(Request $request)
    {
        $viewer = $request->user();
        $page = User::whereIn('id', Follow::where('follower_id', $viewer->id)->select('creator_id'))
            ->orderBy('name')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 20))));

        return $this->ok([
            'items' => collect($page->items())->map(fn ($u) => UserResource::toArray($u))->all(),
            'meta'  => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }

    public function followers(Request $request)
    {
        $viewer = $request->user();
        $page = User::whereIn('id', Follow::where('creator_id', $viewer->id)->select('follower_id'))
            ->orderBy('name')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 20))));

        return $this->ok([
            'items' => collect($page->items())->map(fn ($u) => UserResource::toArray($u))->all(),
            'meta'  => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }
}
