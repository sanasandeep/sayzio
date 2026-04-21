<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\CreatorPost;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class CreatorPostController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        $page = CreatorPost::where('user_id', $request->user()->id)
            ->orderByDesc('id')
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
            'title'   => ['nullable', 'string', 'max:200'],
            'body'    => ['required', 'string', 'max:10000'],
            'media'   => ['nullable', 'array'],
            'visibility' => ['nullable', Rule::in(['public', 'followers', 'subscribers'])],
        ]);
        $p = CreatorPost::create(array_merge($data, [
            'user_id'    => $request->user()->id,
            'visibility' => $data['visibility'] ?? 'public',
        ]));
        return $this->created(['post' => $this->transform($p)]);
    }

    public function update(Request $request, int $id)
    {
        $p = CreatorPost::where('user_id', $request->user()->id)->find($id);
        if (!$p) return $this->notFound('Post not found');
        $data = $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:200'],
            'body'  => ['sometimes', 'string', 'max:10000'],
            'media' => ['sometimes', 'nullable', 'array'],
            'visibility' => ['sometimes', Rule::in(['public', 'followers', 'subscribers'])],
        ]);
        $p->fill($data)->save();
        return $this->ok(['post' => $this->transform($p->fresh())]);
    }

    public function destroy(Request $request, int $id)
    {
        $p = CreatorPost::where('user_id', $request->user()->id)->find($id);
        if (!$p) return $this->notFound('Post not found');
        $p->delete();
        return $this->noContent();
    }

    protected function transform(CreatorPost $p): array
    {
        return [
            'id'         => $p->id,
            'title'      => $p->title,
            'body'       => $p->body,
            'media'      => $p->media,
            'visibility' => $p->visibility ?? 'public',
            'created_at' => optional($p->created_at)->toIso8601String(),
        ];
    }
}
