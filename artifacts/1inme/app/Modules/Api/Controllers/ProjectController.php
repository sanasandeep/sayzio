<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProjectController extends Controller
{
    use ApiResponses;

    /**
     * Folders are workspace-scoped and owned by the workspace OWNER on the
     * web dashboard (workspace_owner()->projects() under the BelongsToWorkspace
     * scope). The stateless API has no current_workspace binding, so mirror
     * that scoping explicitly: resolve the caller's active workspace, then
     * filter by the owner's user_id + workspace_id. Keeps the desktop
     * browser's folder list identical to the web folders desk, including for
     * team members acting inside a shared workspace.
     */
    protected function scopedProjects(Request $request)
    {
        $ws      = $this->activeWorkspace($request->user());
        $ownerId = $ws?->owner_user_id ?? $request->user()->id;
        return Project::withoutGlobalScope('workspace')
            ->where('user_id', $ownerId)
            ->when($ws, fn ($q) => $q->where('workspace_id', $ws->id));
    }

    public function index(Request $request)
    {
        $items = $this->scopedProjects($request)
            ->withCount('links')
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
        // Mirror the web dashboard: folders are created under the workspace
        // OWNER's account (workspace_owner()->projects()->create()).
        $ws = $this->activeWorkspace($request->user());
        $p  = new Project(array_merge($data, ['user_id' => $ws?->owner_user_id ?? $request->user()->id]));
        $p->workspace_id = $ws?->id;
        $p->save();
        return $this->created(['project' => $this->transform($p)]);
    }

    public function update(Request $request, int $id)
    {
        $p = $this->scopedProjects($request)->find($id);
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
        $p = $this->scopedProjects($request)->find($id);
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
            // Present when loaded via withCount (index); computed lazily
            // otherwise (store/update return paths) so clients always get it.
            'links_count' => (int) ($p->links_count ?? $p->links()->count()),
            'created_at'  => optional($p->created_at)->toIso8601String(),
        ];
    }
}
