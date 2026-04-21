<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProjectController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        $items = Project::where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn ($p) => $this->transform($p))
            ->all();
        return $this->ok(['items' => $items]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color'       => ['nullable', 'string', 'max:20'],
        ]);
        $p = Project::create(array_merge($data, ['user_id' => $request->user()->id]));
        return $this->created(['project' => $this->transform($p)]);
    }

    public function update(Request $request, int $id)
    {
        $p = Project::where('user_id', $request->user()->id)->find($id);
        if (!$p) return $this->notFound('Project not found');
        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'color'       => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);
        $p->fill($data)->save();
        return $this->ok(['project' => $this->transform($p->fresh())]);
    }

    public function destroy(Request $request, int $id)
    {
        $p = Project::where('user_id', $request->user()->id)->find($id);
        if (!$p) return $this->notFound('Project not found');
        $p->delete();
        return $this->noContent();
    }

    protected function transform(Project $p): array
    {
        return [
            'id'          => $p->id,
            'name'        => $p->name,
            'description' => $p->description,
            'color'       => $p->color,
            'created_at'  => optional($p->created_at)->toIso8601String(),
        ];
    }
}
