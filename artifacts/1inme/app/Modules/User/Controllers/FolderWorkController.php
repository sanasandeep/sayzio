<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\PlannerNote;
use App\Modules\User\Models\Project;
use App\Modules\User\Models\ProjectItem;
use App\Modules\User\Models\TaskActivity;
use App\Modules\User\Models\TaskBoard;
use App\Modules\User\Models\TaskCard;
use App\Modules\User\Models\TaskColumn;
use App\Modules\User\Models\TaskSubtask;
use App\Modules\User\Models\User;
use App\Services\Planner\PlannerResources;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FolderWorkController extends Controller
{
    private function owner(Project $project): void
    {
        abort_unless($project->user_id === workspace_owner_id(), 404);
    }

    public function task(Request $r, Project $project)
    {
        $this->owner($project);
        abort_unless($r->user()->canInWorkspace(app('current_workspace'), 'tasks.create'), 403);
        $v = $r->validate(['title' => 'required|string|max:200', 'description' => 'nullable|string|max:8000', 'priority' => 'required|in:low,normal,high,urgent', 'due_date' => 'nullable|date', 'board_id' => 'nullable|integer', 'assignee_id' => 'nullable|integer', 'checklist' => 'nullable|string|max:3000', 'related' => 'nullable|regex:/^[a-z]+:[1-9][0-9]*$/']);
        if (! empty($v['assignee_id'])) {
            abort_unless(User::findOrFail($v['assignee_id'])->belongsToWorkspace(app('current_workspace')), 422);
        }
        if (! empty($v['related'])) {
            [$kind,$id] = explode(':', $v['related']);
            app(PlannerResources::class)->get($kind, (int) $id, $r->user());
            $assigned = collect(app(PlannerResources::class)->folder($project, $r->user()))->contains(fn ($i) => $i['kind'] === $kind && $i['row']->id === (int) $id);
            abort_unless($assigned, 422, 'Choose a related resource already assigned to this folder.');
        }
        DB::transaction(function () use ($r, $project, $v) {
            $board = ! empty($v['board_id']) ? app(PlannerResources::class)->get('board', $v['board_id'], $r->user()) : null;
            if (! $board) {
                $features = $r->user()->plan?->features ?? [];
                abort_if(! $r->user()->hasPermission('user.plan_limits.bypass') && isset($features['tasks']) && ! $features['tasks'], 422, 'Task boards are unavailable on this plan.');
                $cap = $r->user()->getPlanFeature('max_task_boards', -1);
                abort_if(! $r->user()->hasPermission('user.plan_limits.bypass') && $cap >= 0 && app(PlannerResources::class)->query('board', $r->user())->whereNull('archived_at')->count() >= $cap, 422, 'Task board limit reached. Choose an existing board.');
                $board = TaskBoard::create(['workspace_id' => $project->workspace_id, 'name' => mb_substr($project->name.' tasks', 0, 120), 'scope' => 'team', 'created_by_user_id' => $r->user()->id]);
                foreach (['To do', 'In progress', 'Done'] as $i => $name) {
                    TaskColumn::create(['workspace_id' => $project->workspace_id, 'board_id' => $board->id, 'name' => $name, 'position' => $i, 'is_done' => $i === 2]);
                }
            }
            abort_if($board->archived_at, 422, 'Select an active board.');
            $column = $board->columns()->where('is_done', false)->first();
            abort_unless($column, 422, 'Add an open column to the board.');
            $card = TaskCard::create(['workspace_id' => $project->workspace_id, 'board_id' => $board->id, 'column_id' => $column->id, 'created_by_user_id' => $r->user()->id, 'title' => $v['title'], 'description' => $v['description'] ?? null, 'priority' => $v['priority'], 'due_date' => $v['due_date'] ?? null, 'position' => ($column->cards()->max('position') ?? 0) + 1]);
            if (! empty($v['assignee_id'])) {
                $card->assignees()->attach($v['assignee_id']);
            }
            foreach (array_slice(array_filter(array_map('trim', explode("\n", $v['checklist'] ?? ''))), 0, 20) as $i => $title) {
                TaskSubtask::create(['card_id' => $card->id, 'title' => mb_substr($title, 0, 200), 'position' => $i, 'completed' => false]);
            }
            app(PlannerResources::class)->attach($project, 'task', $card->id, $r->user());
            app(PlannerResources::class)->attach($project, 'board', $board->id, $r->user());
            TaskActivity::log($card->id, $r->user()->id, 'created', ['source' => 'Folder workspace']);
            if (! empty($v['related'])) {
                [$kind,$id] = explode(':', $v['related']);
                app(PlannerResources::class)->attach($project, $kind, (int) $id, $r->user());
                $this->saveRelation($project, $r, 'task', $card->id, $kind, (int) $id, 'Supports');
            }
        });

        return redirect()->route('user.projects.show', ['project' => $project->id, 'tab' => 'tasks'])->with('success', 'Task created.');
    }

    public function complete(Request $r, Project $project, TaskCard $card)
    {
        $this->owner($project);
        abort_unless(ProjectItem::where('project_id', $project->id)->where('kind', 'task')->where('resource_id', $card->id)->exists(), 404);
        app(PlannerResources::class)->get('task', $card->id, $r->user());
        abort_unless($r->user()->canInWorkspace(app('current_workspace'), 'tasks.edit'), 403);
        $v = $r->validate(['action' => 'required|in:complete,reopen']);
        $column = $card->board->columns()->where('is_done', $v['action'] === 'complete')->first();
        abort_unless($column, 422, 'Add a matching board column first.');
        $card->update(['column_id' => $column->id, 'completed_at' => $v['action'] === 'complete' ? now() : null, 'progress' => $v['action'] === 'complete' ? 100 : 0]);
        TaskActivity::log($card->id, $r->user()->id, $v['action']);

        return back()->with('success', 'Task updated.');
    }

    private function saveRelation($project, $r, $sk, $si, $tk, $ti, $label): void
    {
        DB::table('folder_relationships')->updateOrInsert(['project_id' => $project->id, 'source_kind' => $sk, 'source_id' => $si, 'target_kind' => $tk, 'target_id' => $ti], ['workspace_id' => $project->workspace_id, 'user_id' => $r->user()->id, 'label' => $label, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function relationship(Request $r, Project $project)
    {
        $this->owner($project);
        $v = $r->validate(['source' => 'required|regex:/^[a-z]+:[1-9][0-9]*$/', 'target' => 'required|different:source|regex:/^[a-z]+:[1-9][0-9]*$/', 'label' => 'required|string|max:120']);
        foreach (['source', 'target'] as $side) {
            [$kind,$id] = explode(':', $v[$side]);
            app(PlannerResources::class)->get($kind, (int) $id, $r->user());
            abort_unless(collect(app(PlannerResources::class)->folder($project,$r->user()))->contains(fn($item)=>$item['kind']===$kind && (int)$item['row']->id===(int)$id), 422, 'Connect items assigned to this folder.');
        }
        [$sk,$si] = explode(':', $v['source']);
        [$tk,$ti] = explode(':', $v['target']);
        $this->saveRelation($project, $r, $sk, (int) $si, $tk, (int) $ti, $v['label']);

        return back()->with('success', 'Connection saved.');
    }

    public function disconnect(Request $r, Project $project, int $relationship)
    {
        $this->owner($project);
        DB::table('folder_relationships')->where('workspace_id', $project->workspace_id)->where('project_id', $project->id)->where('id', $relationship)->delete();

        return back()->with('success', 'Connection removed. Resources were kept.');
    }

    public function move(Request $r, Project $project)
    {
        $this->owner($project);
        $v = $r->validate(['destination' => 'required|integer|different:project']);
        $target = Project::where('user_id', workspace_owner_id())->where('id', '!=', $project->id)->findOrFail($v['destination']);
        DB::transaction(function () use ($project, $target) {
            foreach (['link', 'form', 'qr', 'plan', 'campaign'] as $kind) {
                $class = PlannerResources::TYPES[$kind];
                $class::where('project_id', $project->id)->update(['project_id' => $target->id]);
            }
            foreach (\App\Modules\User\Models\MarketingPlanCalc::where('payload->project_id',$project->id)->get() as $forecast) {
                $forecast->update(['payload'=>array_replace($forecast->payload ?? [], ['project_id'=>$target->id])]);
            }
            ProjectItem::where('project_id', $project->id)->update(['project_id' => $target->id]);
            PlannerNote::where('project_id', $project->id)->update(['project_id' => $target->id]);
            $relations = DB::table('folder_relationships')->where('workspace_id', $project->workspace_id)->where('project_id', $project->id)->get();
            foreach ($relations as $relation) {
                $attrs = (array) $relation;
                unset($attrs['id']);
                $attrs['project_id'] = $target->id;
                DB::table('folder_relationships')->insertOrIgnore($attrs);
            }
            DB::table('folder_relationships')->where('workspace_id', $project->workspace_id)->where('project_id', $project->id)->delete();
        });

        return redirect()->route('user.projects.show', $target)->with('success', 'Contents moved. Both folders were kept.');
    }
}
