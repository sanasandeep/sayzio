<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Project;
use App\Modules\User\Models\ProjectItem;
use App\Services\Planner\PlannerResources;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FolderExplorerController extends Controller
{
    private const NATIVE = ['link', 'form', 'qr', 'plan', 'campaign'];

    private function folder($id): ?Project
    {
        return $id ? workspace_owner()->projects()->findOrFail($id) : null;
    }

    private function folders()
    {
        return workspace_owner()->projects()->orderBy('name')->get(['id', 'name', 'color', 'created_by_user_id'])->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'color' => $p->color ?: '#3b82f6', 'url' => route('user.projects.show', $p), 'writable' => $this->canEditFolder($p)]);
    }

    private function canEditFolder(Project $folder): bool
    {
        $user = auth()->user();

        return $user->canInWorkspace(app('current_workspace'), 'projects.edit') && ((int) $folder->created_by_user_id === (int) $user->id || $user->canInWorkspace(app('current_workspace'), 'projects.edit_others'));
    }

    public function index(Request $request)
    {
        $request->validate(['folder' => 'nullable|integer|min:1']);
        $selected = $this->folder($request->query('folder'));

        return view('user.projects.explorer', ['folders' => $this->folders(), 'selected' => $selected]);
    }

    private function location(string $kind, $row): ?int
    {
        if (in_array($kind, self::NATIVE)) {
            return $row->project_id ? (int) $row->project_id : null;
        }
        if ($kind === 'budget') {
            return ! empty($row->payload['project_id']) ? (int) $row->payload['project_id'] : null;
        }
        $id = ProjectItem::where('kind', $kind)->where('resource_id', $row->id)->value('project_id');
        if (! $id && $kind === 'task') {
            $id = ProjectItem::where('kind', 'board')->where('resource_id', $row->board_id)->value('project_id');
        }

        return $id ? (int) $id : null;
    }

    public function contents(Request $request, PlannerResources $resources)
    {
        $v = $request->validate(['folder' => 'nullable|integer|min:1', 'q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1', 'kind' => 'nullable|string|in:'.implode(',', array_keys(PlannerResources::TYPES)), 'link_type' => 'nullable|string|max:80', 'sort' => 'nullable|in:name,name_desc,newest,oldest']);
        $folder = $this->folder($v['folder'] ?? null);
        if ($folder) {
            $items = collect($resources->folder($folder, $request->user()));
        } else {
            $items = collect();
            foreach (array_keys(PlannerResources::TYPES) as $kind) {
                $query = $resources->query($kind, $request->user());
                if (in_array($kind, self::NATIVE)) {
                    $query->whereNull('project_id');
                } elseif ($kind === 'budget') {
                    $query->whereNull('payload->project_id');
                } else {
                    $query->whereNotIn('id', ProjectItem::where('kind', $kind)->select('resource_id'));
                    if ($kind === 'task') {
                        $query->whereNotIn('board_id', ProjectItem::where('kind', 'board')->select('resource_id'))->whereNull('archived_at');
                    }
                }
                foreach ($query->get() as $row) {
                    $items->push(['kind' => $kind, 'row' => $row, 'label' => $resources->label($row), 'url' => $resources->url($kind, $row)]);
                }
            }
        }
        $q = mb_strtolower($v['q'] ?? '');
        $items = $items->filter(fn ($item) => (! $q || str_contains(mb_strtolower($item['label']), $q))
            && (empty($v['kind']) || $item['kind'] === $v['kind'])
            && (empty($v['link_type']) || ($item['kind'] === 'link' && $item['row']->type === $v['link_type'])));
        $sort = $v['sort'] ?? 'name';
        $items = match ($sort) {
            'name_desc' => $items->sortByDesc('label', SORT_NATURAL | SORT_FLAG_CASE),
            'newest' => $items->sortByDesc(fn ($item) => $item['row']->created_at?->getTimestamp() ?? 0),
            'oldest' => $items->sortBy(fn ($item) => $item['row']->created_at?->getTimestamp() ?? 0),
            default => $items->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE),
        };
        $items = $items->values();
        $total = $items->count();
        $page = $v['page'] ?? 1;
        $items = $items->slice(($page - 1) * 100, 100);
        $tasks = $items->where('kind', 'task');
        $directTasks = ProjectItem::where('kind', 'task')->whereIn('resource_id', $tasks->pluck('row.id'))->pluck('resource_id')->flip();
        $assignedBoards = ProjectItem::where('kind', 'board')->whereIn('resource_id', $tasks->pluck('row.board_id'))->pluck('resource_id')->flip();
        $items = $items->map(function ($item) use ($request, $directTasks, $assignedBoards, $folder) {
            $permission = preg_replace('/\.view$/', '.edit', PlannerResources::permissionFor($item['kind']));
            $inherited = $item['kind'] === 'task' && ! isset($directTasks[$item['row']->id]) && isset($assignedBoards[$item['row']->board_id]);

            return ['key' => $item['kind'].':'.$item['row']->id, 'kind' => $item['kind'], 'name' => $item['label'], 'url' => $item['url'], 'movable' => (! $folder || $this->canEditFolder($folder)) && $request->user()->canInWorkspace(app('current_workspace'), 'projects.edit') && $request->user()->canInWorkspace(app('current_workspace'), $permission), 'inherited' => (bool) $inherited];
        })->values();

        return response()->json(['folder' => $folder ? ['id' => $folder->id, 'name' => $folder->name, 'color' => $folder->color, 'url' => route('user.projects.show', $folder)] : null, 'items' => $items, 'total' => $total, 'page' => $page, 'has_more' => $page * 100 < $total, 'folders' => $this->folders()]);
    }

    public function move(Request $request, PlannerResources $resources)
    {
        $v = $request->validate(['items' => 'required|array|min:1|max:50', 'items.*' => ['required', 'string', 'distinct', 'regex:/^[a-z]+:[1-9][0-9]*$/'], 'destination' => 'present|nullable|integer|min:1', 'source' => 'present|nullable|integer|min:1']);
        abort_unless($request->user()->canInWorkspace(app('current_workspace'), 'projects.edit'), 403);
        $source = $this->folder($v['source']);
        $target = $this->folder($v['destination']);
        foreach (array_filter([$source, $target]) as $p) {
            abort_unless((int) $p->created_by_user_id === (int) $request->user()->id || $request->user()->canInWorkspace(app('current_workspace'), 'projects.edit_others'), 403);
        }
        DB::transaction(function () use ($v, $resources, $request, $target) {
            $rows = [];
            foreach ($v['items'] as $key) {
                [$kind,$id] = explode(':', $key);
                $row = $resources->get($kind, (int) $id, $request->user());
                $permission = preg_replace('/\.view$/', '.edit', PlannerResources::permissionFor($kind));
                abort_unless($request->user()->canInWorkspace(app('current_workspace'), $permission), 403);
                $row = $row->newQuery()->whereKey($row->id)->lockForUpdate()->firstOrFail();
                abort_unless($this->location($kind, $row) === ($v['source'] ? (int) $v['source'] : null), 409, 'An item moved since this folder was opened. Refresh and try again.');
                $inherited = $kind === 'task' && ! ProjectItem::where('kind', 'task')->where('resource_id', $row->id)->exists() && $this->location($kind, $row);
                abort_if($inherited && ! $target && ! in_array('board:'.$row->board_id, $v['items'], true), 422, 'This task follows its board folder. Move its board out, or move the task to a different folder.');
                $rows[] = [$kind, $row];
            }
            foreach ($rows as [$kind,$row]) {
                if ($target) {
                    $resources->attach($target, $kind, $row->id, $request->user());
                } else {
                    if (in_array($kind, self::NATIVE)) {
                        $row->update(['project_id' => null]);
                    }
                    if ($kind === 'budget') {
                        $row->update(['payload' => array_replace($row->payload ?? [], ['project_id' => null])]);
                    }
                    ProjectItem::where('kind', $kind)->where('resource_id', $row->id)->delete();
                }
            }
        });

        return response()->json(['ok' => true]);
    }
}
