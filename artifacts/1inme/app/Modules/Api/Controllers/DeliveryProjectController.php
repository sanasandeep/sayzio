<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\DeliveryProject;
use App\Modules\User\Models\DeliveryProjectTask;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceMember;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Task #3564 — Sanctum Bearer-token parity for the web Delivery Projects
 * surfaces (see {@see \App\Modules\User\Controllers\DeliveryProjectController}).
 *
 *   GET    /api/v1/delivery-projects                       list projects
 *   GET    /api/v1/delivery-projects/{id}                  project + tasks + members
 *   POST   /api/v1/delivery-projects/{id}/tasks            add a task
 *   PATCH  /api/v1/delivery-projects/tasks/{task}          update a task
 *   DELETE /api/v1/delivery-projects/tasks/{task}          delete a task
 *
 * The Sanctum API path never runs SetActiveWorkspace, so the
 * {@see BelongsToWorkspace} global scope is inactive here; we resolve
 * projects explicitly across the caller's accessible workspaces (reads need
 * `tasks.view`, mutations need `tasks.edit`), matching the web feature.
 */
class DeliveryProjectController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        $user = $request->user();
        $wsIds = $this->workspaceIds($user, 'tasks.view');
        if (empty($wsIds)) return $this->ok(['items' => []]);

        $projects = DeliveryProject::query()
            ->withoutGlobalScope('workspace')
            ->whereIn('workspace_id', $wsIds)
            ->with('creator:id,name')
            ->withCount('tasks')
            ->withCount(['tasks as done_tasks_count' => fn ($q) => $q->where('status', DeliveryProjectTask::STATUS_DONE)])
            ->orderByDesc('id')
            ->get()
            ->map(fn (DeliveryProject $p) => $this->projectArray($p))
            ->all();

        return $this->ok(['items' => $projects]);
    }

    public function show(Request $request, int $id)
    {
        $project = $this->findProject($request, $id, 'tasks.view');
        if (!$project) return $this->notFound('Project not found');

        $project->load(['tasks.assignee:id,name,avatar', 'creator:id,name', 'clientUser:id,name']);

        return $this->ok([
            'project'  => $this->projectArray($project, withTasks: true),
            'members'  => $this->workspaceMembers($project->workspace_id),
            'statuses' => DeliveryProjectTask::STATUSES,
            'share_url' => route('delivery-project.share', $project->share_token),
        ]);
    }

    public function storeTask(Request $request, int $id)
    {
        $project = $this->findProject($request, $id, 'tasks.edit');
        if (!$project) return $this->notFound('Project not found');

        $data = $request->validate([
            'title'            => 'required|string|max:200',
            'assignee_user_id' => 'nullable|integer',
            'start_date'       => 'nullable|date',
            'due_date'         => 'nullable|date',
        ]);

        $position = (int) ($project->tasks()->max('position') ?? 0) + 1;

        $task = $project->tasks()->create([
            'workspace_id'     => $project->workspace_id,
            'title'            => $data['title'],
            'status'           => DeliveryProjectTask::STATUS_TODO,
            'assignee_user_id' => $this->validAssignee($project, $data['assignee_user_id'] ?? null),
            'start_date'       => $data['start_date'] ?? null,
            'due_date'         => $data['due_date'] ?? null,
            'progress'         => 0,
            'position'         => $position,
        ]);

        return $this->created(['task' => $this->taskArray($task)]);
    }

    public function updateTask(Request $request, int $task)
    {
        $model = $this->findTask($request, $task, 'tasks.edit');
        if (!$model) return $this->notFound('Task not found');

        $data = $request->validate([
            'title'            => 'sometimes|string|max:200',
            'status'           => 'sometimes|in:todo,in_progress,done',
            'progress'         => 'sometimes|integer|min:0|max:100',
            'assignee_user_id' => 'sometimes|nullable|integer',
            'start_date'       => 'sometimes|nullable|date',
            'due_date'         => 'sometimes|nullable|date',
        ]);

        if (array_key_exists('title', $data))      $model->title = $data['title'];
        if (array_key_exists('start_date', $data)) $model->start_date = $data['start_date'];
        if (array_key_exists('due_date', $data))   $model->due_date = $data['due_date'];
        if (array_key_exists('assignee_user_id', $data)) {
            $model->assignee_user_id = $this->validAssignee($model->project, $data['assignee_user_id']);
        }
        if (array_key_exists('status', $data) || array_key_exists('progress', $data)) {
            $model->syncStatusProgress($data['status'] ?? null, $data['progress'] ?? null);
        }

        $model->save();

        return $this->ok(['task' => $this->taskArray($model->fresh('assignee'))]);
    }

    public function destroyTask(Request $request, int $task)
    {
        $model = $this->findTask($request, $task, 'tasks.edit');
        if (!$model) return $this->notFound('Task not found');

        $model->delete();

        return $this->ok(['deleted' => true]);
    }

    // ----- Helpers ----------------------------------------------------------

    /** @return int[] IDs of workspaces where the caller holds $permission. */
    private function workspaceIds($user, string $permission): array
    {
        if (!$user) return [];
        return $user->accessibleWorkspaces()
            ->filter(fn ($ws) => $user->canInWorkspace($ws, $permission))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function findProject(Request $request, int $id, string $permission): ?DeliveryProject
    {
        $wsIds = $this->workspaceIds($request->user(), $permission);
        if (empty($wsIds)) return null;

        return DeliveryProject::query()
            ->withoutGlobalScope('workspace')
            ->whereIn('workspace_id', $wsIds)
            ->find($id);
    }

    private function findTask(Request $request, int $id, string $permission): ?DeliveryProjectTask
    {
        $wsIds = $this->workspaceIds($request->user(), $permission);
        if (empty($wsIds)) return null;

        return DeliveryProjectTask::query()
            ->withoutGlobalScope('workspace')
            ->whereIn('workspace_id', $wsIds)
            ->with('project')
            ->find($id);
    }

    /** Return the user id only if they are an active member of the workspace. */
    private function validAssignee(?DeliveryProject $project, $userId): ?int
    {
        $userId = $userId ? (int) $userId : null;
        if (!$userId || !$project) return null;

        $ws = Workspace::find($project->workspace_id);
        $isMember = $ws && (
            (int) $ws->owner_user_id === $userId
            || $ws->members()->where('user_id', $userId)->whereNull('suspended_at')->exists()
        );

        return $isMember ? $userId : null;
    }

    /** @return array<int, array{id:int,user_id:int,name:?string,avatar:?string}> */
    private function workspaceMembers(?int $workspaceId): array
    {
        $ws = $workspaceId ? Workspace::find($workspaceId) : null;
        if (!$ws) return [];

        return $ws->members()
            ->whereNull('suspended_at')
            ->with('user:id,name,avatar')
            ->get()
            ->map(fn (WorkspaceMember $m) => [
                'id'      => $m->id,
                'user_id' => (int) $m->user_id,
                'name'    => $m->user?->name,
                'avatar'  => $m->user?->avatar,
            ])
            ->filter(fn ($m) => $m['name'] !== null)
            ->values()
            ->all();
    }

    /** @return array<string,mixed> */
    private function projectArray(DeliveryProject $p, bool $withTasks = false): array
    {
        $done = $p->done_tasks_count ?? null;
        $total = $p->tasks_count ?? null;

        $out = [
            'id'            => $p->id,
            'title'         => $p->title,
            'description'   => $p->description,
            'status'        => $p->status,
            'status_label'  => $p->statusLabel(),
            'source_label'  => $p->sourceLabel(),
            'client_name'   => $p->client_name,
            'client_email'  => $p->client_email,
            'progress'      => $withTasks ? $p->progressPercent() : ($total ? (int) round(($done / max(1, $total)) * 100) : 0),
            'tasks_count'   => $total !== null ? (int) $total : $p->tasks()->count(),
            'done_tasks_count' => $done !== null ? (int) $done : null,
            'created_by'    => optional($p->creator)->name,
            'completed_at'  => optional($p->completed_at)->toIso8601String(),
            'warranty_expires_at'    => optional($p->warranty_expires_at)->toDateString(),
            'warranty_reminder_days' => $p->warranty_reminder_days !== null ? (int) $p->warranty_reminder_days : null,
            'warranty_active'  => $p->warrantyActive(),
            'warranty_expired' => $p->warrantyExpired(),
        ];

        if ($withTasks) {
            $out['tasks'] = $p->tasks->map(fn (DeliveryProjectTask $t) => $this->taskArray($t))->all();
        }

        return $out;
    }

    /** @return array<string,mixed> */
    private function taskArray(DeliveryProjectTask $task): array
    {
        return [
            'id'               => $task->id,
            'title'            => $task->title,
            'status'           => $task->status,
            'status_label'     => $task->statusLabel(),
            'progress'         => (int) $task->progress,
            'assignee_user_id' => $task->assignee_user_id ? (int) $task->assignee_user_id : null,
            'assignee_name'    => optional($task->assignee)->name,
            'start_date'       => optional($task->start_date)->toDateString(),
            'due_date'         => optional($task->due_date)->toDateString(),
            'position'         => (int) $task->position,
        ];
    }
}
