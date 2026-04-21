<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * CRUD for the blocks (sections) that make up an authenticated user's
 * biolink page. Public viewing of blocks is via BiolinkController@show.
 */
class BiolinkBlockController extends Controller
{
    use ApiResponses;

    public function index(Request $request, int $linkId)
    {
        $link = $this->ownedLink($request, $linkId);
        if (!$link) return $this->notFound('Biolink not found');

        $items = BiolinkBlock::where('link_id', $link->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($b) => $this->transform($b))
            ->all();
        return $this->ok(['items' => $items]);
    }

    public function store(Request $request, int $linkId)
    {
        $link = $this->ownedLink($request, $linkId);
        if (!$link) return $this->notFound('Biolink not found');

        $data = $request->validate([
            'type'       => ['required', 'string', 'max:60'],
            'sort_order' => ['nullable', 'integer'],
            'parent_id'  => ['nullable', 'integer'],
            'is_active'  => ['nullable', 'boolean'],
            'settings'   => ['nullable', 'array'],
        ]);
        $sort = $data['sort_order'] ?? ((int) BiolinkBlock::where('link_id', $link->id)->max('sort_order') + 1);
        $b = BiolinkBlock::create([
            'link_id'    => $link->id,
            'type'       => $data['type'],
            'sort_order' => $sort,
            'parent_id'  => $data['parent_id'] ?? null,
            'is_active'  => $data['is_active'] ?? true,
            'settings'   => $data['settings'] ?? [],
        ]);
        return $this->created(['block' => $this->transform($b)]);
    }

    public function update(Request $request, int $linkId, int $id)
    {
        $link = $this->ownedLink($request, $linkId);
        if (!$link) return $this->notFound('Biolink not found');

        $b = BiolinkBlock::where('link_id', $link->id)->find($id);
        if (!$b) return $this->notFound('Block not found');

        $data = $request->validate([
            'type'       => ['sometimes', 'string', 'max:60'],
            'sort_order' => ['sometimes', 'integer'],
            'parent_id'  => ['sometimes', 'nullable', 'integer'],
            'is_active'  => ['sometimes', 'boolean'],
            'settings'   => ['sometimes', 'array'],
        ]);
        $b->fill($data)->save();
        return $this->ok(['block' => $this->transform($b->fresh())]);
    }

    public function destroy(Request $request, int $linkId, int $id)
    {
        $link = $this->ownedLink($request, $linkId);
        if (!$link) return $this->notFound('Biolink not found');

        $b = BiolinkBlock::where('link_id', $link->id)->find($id);
        if (!$b) return $this->notFound('Block not found');
        $b->delete();
        return $this->noContent();
    }

    public function reorder(Request $request, int $linkId)
    {
        $link = $this->ownedLink($request, $linkId);
        if (!$link) return $this->notFound('Biolink not found');

        $data = $request->validate([
            'order'        => ['required', 'array', 'min:1'],
            'order.*'      => ['integer'],
        ]);
        foreach ($data['order'] as $i => $blockId) {
            BiolinkBlock::where('link_id', $link->id)->where('id', $blockId)->update(['sort_order' => $i]);
        }
        return $this->ok(['reordered' => true]);
    }

    protected function ownedLink(Request $request, int $id): ?Link
    {
        return Link::where('user_id', $request->user()->id)
            ->where('type', 'biolink')
            ->find($id);
    }

    protected function transform(BiolinkBlock $b): array
    {
        return [
            'id'         => $b->id,
            'link_id'    => $b->link_id,
            'type'       => $b->type,
            'sort_order' => $b->sort_order,
            'parent_id'  => $b->parent_id,
            'is_active'  => (bool) $b->is_active,
            'settings'   => $b->settings,
            'created_at' => optional($b->created_at)->toIso8601String(),
            'updated_at' => optional($b->updated_at)->toIso8601String(),
        ];
    }
}
