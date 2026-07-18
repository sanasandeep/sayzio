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
            'body'         => ['nullable', 'string', 'max:10000'],
            'image'        => ['nullable', 'string', 'max:1024'],
            'scheduled_at' => ['nullable', 'date'],
            'is_pinned'    => ['nullable', 'boolean'],
            // Creator Profile post types (Task #1207). `body` becomes
            // optional once a media payload is supplied so a gallery /
            // video / audio / link card post doesn't have to ship a
            // caption to be valid.
            'post_type'    => ['nullable', 'string', 'in:' . implode(',', CreatorPost::TYPES)],
            'media'        => ['nullable', 'array'],
        ]);

        $scheduledAt = !empty($data['scheduled_at']) ? \Carbon\Carbon::parse($data['scheduled_at']) : null;
        $isFuture = $scheduledAt && $scheduledAt->isFuture();

        $postType = $data['post_type'] ?? CreatorPost::TYPE_TEXT;
        $media    = $this->sanitizeMedia($postType, $data['media'] ?? []);

        if (empty($data['body']) && $postType === CreatorPost::TYPE_TEXT && empty($data['image'])) {
            return $this->fail('A text post must have a body.', 422);
        }

        $p = new CreatorPost([
            'user_id'      => $request->user()->id,
            'title'        => $data['title'] ?? null,
            'body'         => (string) ($data['body'] ?? ''),
            'image'        => $data['image'] ?? null,
            'post_type'    => $postType,
            'media'        => $media,
            'scheduled_at' => $scheduledAt,
            'published_at' => $isFuture ? null : now(),
        ]);
        // workspace_id is not mass-assignable, so set it directly so the post
        // isn't created with workspace_id = null and hidden from the web list.
        $p->workspace_id = $this->activeWorkspaceId($request->user());
        $p->save();

        // posts_count is kept in sync by the CreatorPost model's saved()
        // hook (covers web, API, scheduler, and approval workflow).

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
            'title'     => ['sometimes', 'nullable', 'string', 'max:200'],
            'body'      => ['sometimes', 'string', 'max:10000'],
            'image'     => ['sometimes', 'nullable', 'string', 'max:1024'],
            'post_type' => ['sometimes', 'string', 'in:' . implode(',', CreatorPost::TYPES)],
            'media'     => ['sometimes', 'nullable', 'array'],
        ]);
        if (array_key_exists('media', $data) || array_key_exists('post_type', $data)) {
            $type = $data['post_type'] ?? $p->post_type ?? CreatorPost::TYPE_TEXT;
            $data['media'] = $this->sanitizeMedia($type, $data['media'] ?? []);
            $data['post_type'] = $type;
        }
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
            'id'              => $p->id,
            'title'           => $p->title,
            'body'            => $p->body,
            'image'           => \App\Support\PublicStorageUrl::resolve($p->image),
            'post_type'       => $p->effectiveType(),
            'media'           => is_array($p->media) ? $p->media : null,
            'reactions_count' => (int) ($p->reactions_count ?? 0),
            'comments_count'  => (int) ($p->comments_count ?? 0),
            'scheduled_at'    => optional($p->scheduled_at)->toIso8601String(),
            'published_at'    => optional($p->published_at)->toIso8601String(),
            'pinned_at'       => optional($p->pinned_at)->toIso8601String(),
            'is_pinned'       => $p->isPinned(),
            'is_scheduled'    => $p->isScheduled(),
            'status'          => $p->statusLabel(),
            'created_at'      => optional($p->created_at)->toIso8601String(),
        ];
    }

    /**
     * Trim a `media` payload to the fields each post type knows how to
     * render. Anything else is dropped so we never persist arbitrary
     * client-supplied JSON. Schemas:
     *
     *   gallery: { items: [{url, alt?}, …] }     // up to 10 items
     *   video  : { url, poster?, duration? }
     *   audio  : { url, title?, duration? }
     *   link   : { url, title?, description?, image? }
     *   image  : { url, alt? }                    // for type=image without legacy `image` col
     *   text   : (no media)
     */
    protected function sanitizeMedia(string $type, array $media): ?array
    {
        $clip = fn ($s, $n = 1024) => is_string($s) ? mb_substr(trim($s), 0, $n) : null;
        switch ($type) {
            case CreatorPost::TYPE_GALLERY:
                $items = collect($media['items'] ?? [])
                    ->take(10)
                    ->map(fn ($it) => [
                        'url' => $clip($it['url'] ?? null, 1024),
                        'alt' => $clip($it['alt'] ?? null, 200),
                    ])
                    ->filter(fn ($it) => !empty($it['url']))
                    ->values()->all();
                return $items ? ['items' => $items] : null;
            case CreatorPost::TYPE_VIDEO:
                $url = $clip($media['url'] ?? null);
                if (!$url) return null;
                return array_filter([
                    'url'      => $url,
                    'poster'   => $clip($media['poster'] ?? null),
                    'duration' => isset($media['duration']) ? (int) $media['duration'] : null,
                ], fn ($v) => $v !== null && $v !== '');
            case CreatorPost::TYPE_AUDIO:
                $url = $clip($media['url'] ?? null);
                if (!$url) return null;
                return array_filter([
                    'url'      => $url,
                    'title'    => $clip($media['title'] ?? null, 200),
                    'duration' => isset($media['duration']) ? (int) $media['duration'] : null,
                ], fn ($v) => $v !== null && $v !== '');
            case CreatorPost::TYPE_LINK:
                $url = $clip($media['url'] ?? null);
                if (!$url) return null;
                return array_filter([
                    'url'         => $url,
                    'title'       => $clip($media['title'] ?? null, 200),
                    'description' => $clip($media['description'] ?? null, 500),
                    'image'       => $clip($media['image'] ?? null),
                ], fn ($v) => $v !== null && $v !== '');
            case CreatorPost::TYPE_IMAGE:
                $url = $clip($media['url'] ?? null);
                if (!$url) return null;
                return array_filter([
                    'url' => $url,
                    'alt' => $clip($media['alt'] ?? null, 200),
                ], fn ($v) => $v !== null && $v !== '');
            default:
                return null;
        }
    }
}
