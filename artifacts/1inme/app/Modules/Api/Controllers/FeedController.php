<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\FeedEvent;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class FeedController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        return $this->paginate($request, $this->buildQuery($request));
    }

    public function byCreator(Request $request, string $handle)
    {
        $creator = \App\Modules\User\Models\CreatorProfile::ownerUserForHandle($handle);
        if (!$creator) return $this->notFound('Creator not found');
        return $this->paginate($request, $this->buildQuery($request, $creator->id));
    }

    protected function buildQuery(Request $request, ?int $creatorId = null)
    {
        $viewer = $request->user();
        $q = FeedEvent::query();

        if ($creatorId) {
            $q->where('user_id', $creatorId);
        }

        $followingIds = $viewer
            ? Follow::where('follower_id', $viewer->id)->pluck('creator_id')->all()
            : [];
        $subscribedToIds = $viewer
            ? Subscriber::where('email', $viewer->email)->where('status', 'active')->pluck('user_id')->unique()->values()->all()
            : [];

        $q->where(function ($w) use ($viewer, $followingIds, $subscribedToIds) {
            $w->where('visibility', 'public');

            if ($viewer) {
                $w->orWhere('visibility', 'registered');

                if (!empty($followingIds)) {
                    $w->orWhere(function ($x) use ($followingIds) {
                        $x->where('visibility', 'followers')->whereIn('user_id', $followingIds);
                    });
                }
                if (!empty($subscribedToIds)) {
                    $w->orWhere(function ($x) use ($subscribedToIds) {
                        $x->where('visibility', 'subscribers')->whereIn('user_id', $subscribedToIds);
                    });
                }

                $w->orWhere('user_id', $viewer->id);
            }
        });

        return $q->orderByDesc('occurred_at');
    }

    protected function paginate(Request $request, $q)
    {
        $page = $q->paginate(min(100, max(1, (int) $request->input('per_page', 20))));

        $items = collect($page->items())->map(function (FeedEvent $e) {
            $u = $e->user;
            return [
                'id'          => $e->id,
                'type'        => $e->type,
                'visibility'  => $e->visibility ?? 'public',
                'occurred_at' => optional($e->occurred_at)->toIso8601String(),
                'data'        => $e->data,
                'creator'     => $u ? [
                    'id'     => $u->id,
                    'name'   => $u->name,
                    'handle' => $u->handle,
                    'avatar' => \App\Support\PublicStorageUrl::resolve($u->avatar),
                ] : null,
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
}
