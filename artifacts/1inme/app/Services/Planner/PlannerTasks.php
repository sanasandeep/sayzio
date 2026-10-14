<?php

namespace App\Services\Planner;

use App\Modules\User\Models\NoteActionPlan;
use App\Modules\User\Models\Project;
use App\Modules\User\Models\TaskActivity;
use App\Modules\User\Models\TaskBoard;
use App\Modules\User\Models\TaskCard;
use App\Modules\User\Models\TaskColumn;
use App\Modules\User\Models\TaskSubtask;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;

class PlannerTasks
{
    public function key(array $task): string
    {
        return hash('sha256', ($task['title'] ?? '').'|'.($task['details'] ?? ''));
    }

    public function create(NoteActionPlan $plan, User $actor, array $indices, ?int $boardId = null, ?string $due = null, ?int $assignee = null): TaskBoard
    {
        abort_unless($actor->canInWorkspace(app('current_workspace'), 'tasks.create'), 403, 'You cannot create tasks in this workspace.');
        return DB::transaction(function () use ($plan, $actor, $indices, $boardId, $due, $assignee) {
            $plan = NoteActionPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            $actions = $plan->actions ?? [];
            $boardId ??= $actions['task_board'] ?? null;
            $board = $boardId ? app(PlannerResources::class)->get('board', $boardId, $actor) : null;
            if (! $board) {
                $features = $actor->plan?->features ?? [];
                abort_if(! $actor->hasPermission('user.plan_limits.bypass') && array_key_exists('tasks', $features) && ! $features['tasks'], 422, 'Task boards are unavailable on this plan.');
                $cap = $actor->getPlanFeature('max_task_boards', -1);
                abort_if($cap >= 0 && TaskBoard::whereNull('archived_at')->count() >= $cap, 422, 'Task board limit reached. Select an existing board.');
                $ws = app('current_workspace');
                $board = TaskBoard::create(['name' => mb_substr($plan->title, 0, 120), 'scope' => $ws->is_personal && $ws->owner_user_id === $actor->id && ! $plan->shared ? 'personal' : 'team', 'owner_user_id' => $ws->is_personal && $ws->owner_user_id === $actor->id && ! $plan->shared ? $actor->id : null, 'created_by_user_id' => $actor->id, 'workspace_id' => $ws->id]);
                foreach (['To do', 'In progress', 'Done'] as $i => $name) {
                    TaskColumn::create(['board_id' => $board->id, 'name' => $name, 'position' => $i, 'is_done' => $i === 2, 'workspace_id' => $ws->id]);
                }
            }
            abort_if($board->archived_at, 422, 'Select an active task board.');
            $column = $board->columns()->first();
            abort_unless($column, 422, 'Add a column to this board first.');
            foreach (array_unique($indices) as $index) {
                $task = $plan->result['tasks'][$index] ?? null;
                if (! $task) {
                    continue;
                }$key = $this->key($task);
                $existing = $actions['task_links'][$key] ?? null;
                if ($existing && TaskCard::whereKey($existing)->where('board_id', $board->id)->exists()) {
                    continue;
                }
                $card = TaskCard::create(['workspace_id' => $board->workspace_id, 'board_id' => $board->id, 'column_id' => $column->id, 'created_by_user_id' => $actor->id, 'title' => $task['title'], 'description' => $task['details'], 'priority' => $task['priority'] === 'medium' ? 'normal' : $task['priority'], 'position' => ($column->cards()->max('position') ?? 0) + 1, 'due_date' => $due]);
                foreach (array_slice($task['checklist'] ?? [], 0, 15) as $i => $title) {
                    TaskSubtask::create(['card_id' => $card->id, 'title' => $title, 'position' => $i, 'completed' => false]);
                }
                if ($assignee) {
                    $card->assignees()->attach($assignee);
                }
                TaskActivity::log($card->id, $actor->id, 'created', ['source' => 'AI Planner', 'plan_id' => $plan->id]);
                $actions['task_links'][$key] = $card->id;
                if ($plan->project_id) {
                    $project = Project::find($plan->project_id);
                    if ($project) {
                        app(PlannerResources::class)->attach($project, 'task', $card->id, $actor);
                    }
                }
            }
            $actions['task_board'] = $board->id;
            $plan->update(['actions' => $actions]);
            if ($plan->project_id && ($project = Project::find($plan->project_id))) {
                app(PlannerResources::class)->attach($project, 'board', $board->id, $actor);
            }

            return $board;
        });
    }
}
