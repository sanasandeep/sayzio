<?php

namespace App\Services;

use App\Modules\User\Models\Calendar;
use App\Modules\User\Models\DeliveryProject;
use App\Modules\User\Models\DeliveryProjectTask;
use App\Modules\User\Models\Project;
use App\Modules\User\Services\WorkspacePermissions as WP;
use App\Services\Clients\ClientTracker;
use App\Services\Planner\PlannerResources;
use App\Support\FilterValues;
use Illuminate\Support\Facades\DB;

class CalendarContextFilters
{
    public function options(): array
    {
        return ['folders' => WP::userCan('projects.view') ? Project::orderBy('name')->get() : collect(), 'clients' => WP::userCan('vault.view') ? app(ClientTracker::class)->clients() : collect(), 'assignees' => WP::userCan('tasks.view') ? app('current_workspace')->members()->with('user:id,name')->get()->pluck('user')->push(app('current_workspace')->owner)->filter()->unique('id') : collect(), 'projects' => WP::userCan('delivery-projects.view') && WP::userCan('tasks.view') ? DeliveryProject::orderBy('title')->get() : collect()];
    }

    public function apply($query, $request): void
    {
        $options = $this->options();
        $folders = FilterValues::ids($request, 'folder');
        $clients = FilterValues::ids($request, 'client');
        $projects = FilterValues::ids($request, 'project');
        $assignees = FilterValues::ids($request, 'assignee');
        if (! $folders && ! $clients && ! $projects && ! $assignees) {
            return;
        }
        $delivery = DeliveryProject::query();
        if (! WP::userCan('delivery-projects.view') || ! WP::userCan('tasks.view')) {
            $delivery->whereRaw('1=0');
        }
        if ($folders) {
            $delivery->whereIn('folder_id', $options['folders']->pluck('id')->intersect($folders));
        }
        if ($clients) {
            $delivery->whereIn('vault_client_id', $options['clients']->pluck('id')->intersect($clients));
        }
        if ($projects) {
            $delivery->whereIn('id', $options['projects']->pluck('id')->intersect($projects));
        }
        $deliveryIds = $delivery->pluck('id');
        $calendarIds = Calendar::whereIn('delivery_project_id', $deliveryIds)->pluck('id');
        if ($folders && ! $clients && ! $projects && WP::userCan('links.view')) {
            $calendarIds = $calendarIds->merge(Calendar::whereHas('link', fn ($q) => $q->whereIn('project_id', $options['folders']->pluck('id')->intersect($folders)))->pluck('id'));
        }
        $eventIds = collect();
        if ($folders && ! $clients && ! $projects) {
            foreach ($options['folders']->whereIn('id', $folders) as $folder) {
                foreach (app(PlannerResources::class)->folder($folder, auth()->user()) as $r) {
                    if ($r['kind'] === 'calendar') {
                        $calendarIds->push($r['row']->id);
                    }if ($r['kind'] === 'task' && $r['row']->calendar_event_id) {
                        $eventIds->push($r['row']->calendar_event_id);
                    }if ($r['kind'] === 'board') {
                        $eventIds = $eventIds->merge(app(PlannerResources::class)->query('task', auth()->user())->where('board_id', $r['row']->id)->pluck('calendar_event_id'));
                    }
                }
            }
        }
        if ($clients || $projects) {
            $taskIds = DB::table('client_tracking_items')->where('workspace_id', app('current_workspace')->id)->whereIn('delivery_project_id', $deliveryIds)->where('kind', 'task')->pluck('resource_id');
            if (WP::userCan('tasks.view')) {
                $eventIds = $eventIds->merge(app(PlannerResources::class)->query('task', auth()->user())->whereIn('id', $taskIds)->pluck('calendar_event_id'));
            }
        }
        if ($folders || $clients || $projects) {
            $query->where(fn ($q) => $q->whereIn('calendar_id', $calendarIds->unique())->orWhereIn('id', $eventIds->filter()->unique()));
        }
        if ($assignees) {
            $ids = $options['assignees']->pluck('id')->intersect($assignees);
            $tasks = WP::userCan('tasks.view') ? app(PlannerResources::class)->query('task', auth()->user())->whereHas('assignees', fn ($q) => $q->whereIn('users.id', $ids))->pluck('calendar_event_id') : collect();
            $deliveryTasks = WP::userCan('tasks.view') && WP::userCan('delivery-projects.view') ? DeliveryProjectTask::whereHas('project')->whereIn('assignee_user_id', $ids)->pluck('calendar_event_id') : collect();
            $query->whereIn('id', $tasks->merge($deliveryTasks)->filter()->unique());
        }
    }
}
