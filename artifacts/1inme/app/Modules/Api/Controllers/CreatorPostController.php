<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Services\WorkspaceActivityRecorder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CreatorPostController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        CreatorPost::publishDuePosts($request->user()->id);

        $page = CreatorPost::where('user_id', $request->user()->id)
            ->orderByDesc('pinned_at')
            ->orderByDesc('created_at')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 25))));
        return $this->ok([
            'items' => collect($page->items())->map(fn ($p) => $this->transform($p))->all(),
            'meta'  => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'        => ['nullable', 'string', 'max:200'],
            'body'         => ['required', 'string', 'max:10000'],
            'image'        => ['nullable', 'string', 'max:1024'],
            'scheduled_at' => ['nullable', 'date'],
            'is_pinned'    => ['nullable', 'boolean'],
        ]);

        $scheduledAt = !empty($data['scheduled_at']) ? \Carbon\Carbon::parse($data['scheduled_at']) : null;
        $isFuture = $scheduledAt && $scheduledAt->isFuture();

        $p = CreatorPost::create([
            'user_id'      => $request->user()->id,
            'title'        => $data['title'] ?? null,
            'body'         => $data['body'],
            'image'        => $data['image'] ?? null,
            'scheduled_at' => $scheduledAt,
            'published_at' => $isFuture ? null : now(),
        ]);

        if (!empty($data['is_pinned']) && !$isFuture) {
            CreatorPost::query()
                ->where('user_id', $request->user()->id)
                ->whereNotNull('pinned_at')
                ->where('id', '!=', $p->id)
                ->update(['pinned_at' => null]);
            $p->forceFill(['pinned_at' => now()])->save();
        }

        WorkspaceActivityRecorder::record(
            null, 'post.publish', 'post', $p->id,
            $p->title ?: mb_substr((string) $p->body, 0, 60),
            route('user.posts.index'),
            ['scheduled' => $isFuture],
        );

        return $this->created(['post' => $this->transform($p->fresh())]);
    }

    public function update(Request $request, int $id)
    {
        $p = CreatorPost::where('user_id', $request->user()->id)->find($id);
        if (!$p) return $this->notFound('Post not found');
        $data = $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:200'],
            'body'  => ['sometimes', 'string', 'max:10000'],
            'image' => ['sometimes', 'nullable', 'string', 'max:1024'],
        ]);
        $p->fill($data)->save();
        WorkspaceActivityRecorder::record(
            null, 'post.update', 'post', $p->id,
            $p->title ?: mb_substr((string) $p->body, 0, 60),
            route('user.posts.index'),
            ['fields' => array_keys($data)],
        );
        return $this->ok(['post' => $this->transform($p->fresh())]);
    }

    public function destroy(Request $request, int $id)
    {
        $p = CreatorPost::where('user_id', $request->user()->id)->find($id);
        if (!$p) return $this->notFound('Post not found');
        $label = $p->title ?: mb_substr((string) $p->body, 0, 60);
        $postId = $p->id;
        $p->delete();
        WorkspaceActivityRecorder::record(null, 'post.delete', 'post', $postId, $label, route('user.posts.index'));
        return $this->noContent();
    }

    public function pin(Request $request, int $id)
    {
        $p = CreatorPost::where('user_id', $request->user()->id)->find($id);
        if (!$p) return $this->notFound('Post not found');
        if (!$p->isPublished()) return $this->fail('Only published posts can be pinned', 422);
        CreatorPost::query()
            ->where('user_id', $request->user()->id)
            ->whereNotNull('pinned_at')
            ->where('id', '!=', $p->id)
            ->update(['pinned_at' => null]);
        $p->forceFill(['pinned_at' => now()])->save();
        WorkspaceActivityRecorder::record(null, 'post.pin', 'post', $p->id,
            $p->title ?: mb_substr((string) $p->body, 0, 60), route('user.posts.index'));
        return $this->ok(['post' => $this->transform($p->fresh())]);
    }

    public function unpin(Request $request, int $id)
    {
        $p = CreatorPost::where('user_id', $request->user()->id)->find($id);
        if (!$p) return $this->notFound('Post not found');
        $p->forceFill(['pinned_at' => null])->save();
        WorkspaceActivityRecorder::record(null, 'post.unpin', 'post', $p->id,
            $p->title ?: mb_substr((string) $p->body, 0, 60), route('user.posts.index'));
        return $this->ok(['post' => $this->transform($p->fresh())]);
    }

    protected function transform(CreatorPost $p): array
    {
        return [
            'id'           => $p->id,
            'title'        => $p->title,
            'body'         => $p->body,
            'image'        => $p->image,
            'scheduled_at' => optional($p->scheduled_at)->toIso8601String(),
            'published_at' => optional($p->published_at)->toIso8601String(),
            'pinned_at'    => optional($p->pinned_at)->toIso8601String(),
            'is_pinned'    => $p->isPinned(),
            'is_scheduled' => $p->isScheduled(),
            'status'       => $p->statusLabel(),
            'created_at'   => optional($p->created_at)->toIso8601String(),
        ];
    }
}
