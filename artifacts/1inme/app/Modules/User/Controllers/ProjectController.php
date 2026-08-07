<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Project;
use Illuminate\Http\Request;

/**
 * "Folders" — the user-facing name for link projects. Routes, models and the
 * `projects` table keep their original names (wire tokens stay stable); only
 * display copy says "folder".
 */
class ProjectController extends Controller
{
    /** Preset folder color palette (hex) shared by create/edit UIs. */
    public const COLORS = [
        '#3b82f6', '#8b5cf6', '#ec4899', '#ef4444', '#f97316',
        '#eab308', '#22c55e', '#14b8a6', '#64748b',
    ];

    public function index(Request $request)
    {
        // The standalone Folders page is retired — folders now live on the
        // dashboard desk. Keep the route for old bookmarks/deep links.
        return redirect(route('user.dashboard') . '#folders');
    }

    /** @deprecated Standalone folders page retired; kept for reference. */
    private function legacyIndex(Request $request)
    {
        $sort = $request->get('sort', 'created');

        // "name" defaults to ascending, everything else to descending.
        if ($request->filled('dir')) {
            $dir = $request->get('dir') === 'asc' ? 'asc' : 'desc';
        } else {
            $dir = $sort === 'name' ? 'asc' : 'desc';
        }

        $query = workspace_owner()->projects()
            ->withCount([
                'links',
                'links as active_links_count' => fn ($q) => $q->where('is_active', true),
            ])
            ->withSum('links as total_clicks_sum', 'total_clicks');

        match ($sort) {
            'name'     => $query->orderBy('name', $dir),
            'links'    => $query->orderBy('links_count', $dir),
            'active'   => $query->orderBy('active_links_count', $dir),
            'clicks'   => $query->orderBy('total_clicks_sum', $dir),
            'modified' => $query->orderBy('updated_at', $dir),
            default    => $query->orderBy('created_at', $dir),
        };

        $projects = $query->paginate(24)->withQueryString();

        return view('user.projects.index', [
            'projects' => $projects,
            'sort'     => $sort,
            'dir'      => $dir,
            'colors'   => self::COLORS,
        ]);
    }

    public function create()
    {
        return view('user.projects.create', ['colors' => self::COLORS]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string|max:1000',
        ]);

        $validated['color'] = $this->normalizeColor($validated['color'] ?? null);

        $project = workspace_owner()->projects()->create($validated);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'project' => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'color' => $project->color,
                    'url' => route('user.links.index', ['project_id' => $project->id]),
                ],
            ]);
        }

        return redirect()->route('user.projects.index')
            ->with('success', 'Folder created successfully.');
    }

    public function show(Request $request, Project $project)
    {
        $this->authorizeOwner($project);

        // Folders open the full My Links experience filtered to the folder so
        // every link action (stats, edit, view, share…) is available.
        return redirect()->route('user.links.index', ['project_id' => $project->id]);
    }

    public function edit(Request $request, Project $project)
    {
        $this->authorizeOwner($project);

        return view('user.projects.edit', ['project' => $project, 'colors' => self::COLORS]);
    }

    public function update(Request $request, Project $project)
    {
        $this->authorizeOwner($project);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string|max:1000',
        ]);

        $validated['color'] = $this->normalizeColor($validated['color'] ?? $project->color);

        $project->update($validated);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('user.projects.index')
            ->with('success', 'Folder updated successfully.');
    }

    public function destroy(Request $request, Project $project)
    {
        $this->authorizeOwner($project);

        $project->delete();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('user.projects.index')
            ->with('success', 'Folder deleted. Its links were kept and are now unfiled.');
    }

    /**
     * Move a link into a folder (or out of any folder with project_id=null).
     * Used by the drag-and-drop + "Move to folder" menus on My Links.
     */
    public function moveLink(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner()->id, 403);

        $validated = $request->validate([
            'project_id' => 'nullable|integer',
        ]);

        $projectId = $validated['project_id'] ?? null;

        if ($projectId !== null) {
            $project = workspace_owner()->projects()->whereKey($projectId)->first();
            abort_if(! $project, 404);
        }

        $link->update(['project_id' => $projectId]);

        return response()->json(['success' => true, 'project_id' => $projectId]);
    }

    protected function normalizeColor(?string $color): string
    {
        $color = strtolower(trim((string) $color));

        return preg_match('/^#[0-9a-f]{6}$/', $color) ? $color : self::COLORS[0];
    }

    protected function authorizeOwner(Project $project): void
    {
        abort_if($project->user_id !== workspace_owner()->id, 403);
    }
}
