<?php

namespace App\Services\Planner;

use App\Modules\User\Models\TaskActivity;
use App\Modules\User\Models\TaskTimeEntry;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceRoleDirectory;

/** Recorded work over the caller's visible resources; never infers live presence. */
class FolderPeople
{
    public function data($project, $resources): array
    {
        $ws = Workspace::findOrFail($project->workspace_id);
        $names = WorkspaceRoleDirectory::names($ws);
        $roster = collect();
        if ($ws->owner) {
            $roster->push(['user' => $ws->owner, 'role' => 'Owner']);
        }
        foreach ($ws->members()->with('user')->get() as $member) {
            if (! $member->user || $member->isSuspended() || ! $member->can('projects.view')) {
                continue;
            }
            if ((int) $project->created_by_user_id !== (int) $member->user_id && ! $member->can('projects.view_others')) {
                continue;
            }
            if ((int) $member->user_id !== (int) $ws->owner_user_id) {
                $roster->push(['user' => $member->user, 'role' => $names[$member->role] ?? $member->role]);
            }
        }
        $tasks = collect($resources)->where('kind', 'task')->pluck('row');
        $actions = TaskActivity::whereIn('card_id', $tasks->pluck('id'))->where('created_at', '>=', now()->subDays(30))->get();
        $time = TaskTimeEntry::whereIn('card_id', $tasks->pluck('id'))->where(fn ($q) => $q->where('source', '!=', 'timer')->orWhereNotNull('ended_at'))->get();
        $people = $roster->map(function ($entry) use ($tasks, $actions, $time) {
            $user = $entry['user'];
            $assigned = $tasks->filter(fn ($task) => $task->assignees->contains('id', $user->id));
            $completed = $assigned->filter(fn ($task) => $task->completed_at);
            $dated = $completed->filter(fn ($task) => $task->due_date);
            $onTime = $dated->filter(fn ($task) => $task->completed_at->lte($task->due_date->copy()->endOfDay()))->count();
            $events = $actions->where('user_id', $user->id);

            return ['id' => $user->id, 'name' => $user->name, 'avatar_url' => $user->resolveAvatarUrl(), 'role' => $entry['role'], 'assigned' => $assigned->count(), 'completed' => $completed->count(), 'open' => $assigned->count() - $completed->count(), 'overdue' => $assigned->filter(fn ($task) => ! $task->completed_at && $task->due_date?->isBefore(today()))->count(), 'on_time' => $onTime, 'dated_completed' => $dated->count(), 'minutes' => (int) $time->where('user_id', $user->id)->sum('minutes'), 'actions' => $events->count(), 'last_action' => $events->max('created_at'), 'tasks' => $assigned->map(fn ($task) => ['title' => $task->title, 'completed' => (bool) $task->completed_at, 'due' => $task->due_date?->format('d M')])->values()->all()];
        })->values()->all();

        return ['people' => $people, 'unassigned' => $tasks->filter(fn ($task) => $task->assignees->isEmpty())->count(), 'unscheduled' => $tasks->filter(fn ($task) => ! $task->completed_at && ! $task->due_date)->count(), 'logged_minutes' => (int) $time->sum('minutes'), 'actions_30d' => $actions->count()];
    }
}
