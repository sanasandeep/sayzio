<?php

namespace App\Services\Planner;

use App\Modules\User\Models\PlannerNote;
use App\Modules\User\Models\PlannerRun;
use App\Modules\User\Models\Project;
use App\Modules\User\Models\TaskActivity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FolderWorkspace
{
    public function data($project, $user): array
    {
        $resources = app(PlannerResources::class)->folder($project, $user);
        $items = collect($resources);
        $tasks = $items->where('kind', 'task');
        (new Collection($tasks->pluck('row')->all()))->load(['assignees:id,name', 'subtasks', 'board', 'column']);
        $notes = PlannerNote::where('project_id', $project->id)->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('shared', true))->orderByDesc('pinned')->latest()->get();
        $unassignedNotes = PlannerNote::whereNull('project_id')->whereNull('plan_id')->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('shared', true))->latest()->limit(20)->get();
        $runs = PlannerRun::whereIn('plan_id', $items->where('kind', 'plan')->pluck('row.id'))->latest()->limit(50)->get();
        $visible = $items->mapWithKeys(fn ($i) => [$i['kind'].':'.$i['row']->id => $i]);
        $relations = DB::table('folder_relationships')->where('workspace_id', $project->workspace_id)->where('project_id', $project->id)->get()->filter(fn ($r) => isset($visible[$r->source_kind.':'.$r->source_id],$visible[$r->target_kind.':'.$r->target_id]));
        $timeline = $items->map(fn ($i) => ['date' => $i['row']->updated_at, 'title' => $i['label'], 'detail' => ucfirst($i['kind']).' updated', 'url' => $i['url']]);
        foreach ($runs as $run) {
            $timeline->push(['date' => $run->created_at, 'title' => 'AI review: '.str_replace('_', ' ', $run->status), 'detail' => $run->message, 'url' => route('user.ai.mind.show', ['record' => $run->plan_id])]);
        }
        foreach ($notes as $note) {
            $timeline->push(['date' => $note->updated_at, 'title' => 'Note '.($note->completed_at ? 'completed' : 'saved'), 'detail' => mb_substr($note->body, 0, 120), 'url' => route('user.projects.show', ['project' => $project->id, 'tab' => 'remember'])]);
        }
        $taskItems = $tasks->keyBy(fn ($i) => $i['row']->id);
        foreach (TaskActivity::whereIn('card_id', $tasks->pluck('row.id'))->latest('created_at')->limit(30)->get() as $activity) {
            $task = $taskItems[$activity->card_id];
            $timeline->push(['date' => $activity->created_at, 'title' => $task['label'], 'detail' => 'Task '.str_replace('_', ' ', $activity->type), 'url' => $task['url']]);
        }
        $attention = [];
        foreach ($tasks->filter(fn ($i) => ! $i['row']->completed_at && $i['row']->due_date?->isBefore(today())) as $i) {
            $attention[] = ['label' => 'Overdue: '.$i['label'], 'url' => $i['url']];
        }
        foreach ($runs->unique('plan_id')->where('status', 'failed') as $run) {
            $attention[] = ['label' => 'AI review needs attention', 'url' => route('user.ai.mind.show', ['record' => $run->plan_id])];
        }
        foreach ($items->where('kind', 'link')->filter(fn ($i) => ! $i['row']->is_active) as $i) {
            $attention[] = ['label' => 'Inactive content: '.$i['label'], 'url' => $i['url']];
        }

        return compact('resources', 'notes', 'runs', 'relations', 'attention', 'unassignedNotes') + ['timeline' => $timeline->sortByDesc('date')->take(30)->values(), 'choices' => app(PlannerResources::class)->choices($user), 'boards' => app(PlannerResources::class)->query('board', $user)->whereNull('archived_at')->get(), 'members' => app('current_workspace')->members()->with('user')->get(), 'destinations' => Project::where('user_id', workspace_owner_id())->where('id', '!=', $project->id)->get()];
    }
}
