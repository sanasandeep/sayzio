<?php

namespace App\Services\Planner;

use App\Modules\User\Models\AiCompanion;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\MarketingStrategy;
use App\Modules\User\Models\MarketingPlanCalc;
use App\Modules\User\Models\SocialProof;
use App\Modules\User\Models\AiPersona;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\AiStaff;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\BrandStudioKit;
use App\Modules\User\Models\Calendar;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\NoteActionPlan;
use App\Modules\User\Models\ProjectItem;
use App\Modules\User\Models\QrCode;
use App\Modules\User\Models\TaskBoard;
use App\Modules\User\Models\TaskCard;
use App\Modules\User\Models\UserFile;
use Illuminate\Support\Facades\Route;

class PlannerResources
{
    public const TYPES = ['campaign' => \App\Modules\User\Models\MarketingCampaign::class, 'link' => Link::class, 'form' => Form::class, 'qr' => QrCode::class, 'file' => UserFile::class, 'calendar' => Calendar::class, 'board' => TaskBoard::class, 'task' => TaskCard::class, 'plan' => NoteActionPlan::class, 'mind' => AiMind::class, 'persona' => AiPersona::class, 'brand' => BrandKit::class, 'staff' => AiStaff::class, 'agent' => AiPersonaAgent::class, 'companion' => AiCompanion::class, 'studio' => BrandStudioKit::class, 'strategy' => MarketingStrategy::class, 'budget' => MarketingPlanCalc::class, 'buzz' => SocialProof::class];

    public static function typeLabels(): array
    {
        $labels = ['campaign'=>'Campaigns','link'=>'Links','form'=>'Forms','qr'=>'QR codes','file'=>'Files','calendar'=>'Calendars','board'=>'Task boards','task'=>'Tasks','plan'=>'Planner plans','mind'=>'AI Minds','persona'=>'Personas','brand'=>'Brand kits','staff'=>'AI Staff','agent'=>'Agents','companion'=>'Channels','studio'=>'Studio materials','strategy'=>'Strategies','budget'=>'Budget & Forecast','buzz'=>'Buzz campaigns'];
        return collect(array_keys(self::TYPES))->mapWithKeys(fn($kind)=>[$kind=>$labels[$kind]??ucfirst($kind)])->all();
    }

    public function query(string $kind, $user)
    {
        abort_unless(isset(self::TYPES[$kind]), 422, 'Unsupported resource type.');
        $q = self::TYPES[$kind]::query();
        $permission = match($kind){'task','board'=>'tasks.view','link'=>'links.view','qr'=>'qr.view','calendar'=>'calendar.view','buzz'=>'social-proofs.view','form'=>'forms.view','file'=>'files.view','plan'=>'planner.view','mind'=>'minds.view','persona'=>'ai-personas.view','staff','agent','companion'=>'ai-agents.view',default=>'ai.view'};
        if($permission && !$user->canInWorkspace(app('current_workspace'),$permission)) return $q->whereRaw('1=0');
        if (in_array($kind, ['campaign', 'strategy', 'budget'])) {
            return $q->where('user_id', $user->id)->where('workspace_id', app('current_workspace')->id);
        }
        if ($kind === 'calendar') {
            return $q->whereHas('link', fn ($q) => $q->where('user_id', workspace_owner_id()));
        }
        if ($kind === 'board') {
            return $q->where(fn ($q) => $q->where('scope', '!=', 'personal')->orWhere('owner_user_id', $user->id));
        }
        if ($kind === 'task') {
            return $q->whereHas('board', fn ($q) => $q->where('scope', '!=', 'personal')->orWhere('owner_user_id', $user->id));
        }
        if ($kind === 'plan') {
            return $q->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('shared', true));
        }
        if (in_array($kind, ['mind', 'persona', 'brand', 'staff', 'file', 'agent', 'companion', 'studio'])) {
            return $q->where('user_id', $user->id);
        }

        return $q->where('user_id', workspace_owner_id());
    }

    public function get(string $kind, int $id, $user)
    {
        return $this->query($kind, $user)->findOrFail($id);
    }

    public function label($row): string
    {
        return (string) ($row->title ?? $row->name ?? $row->original_name ?? ('Item '.$row->id));
    }

    public function url(string $kind, $row): string
    {
        $routes = ['campaign'=>'user.marketing-studio.show','link' => 'user.links.show', 'form' => 'user.forms.show', 'qr' => 'user.qr-codes.edit', 'file' => 'user.files.index', 'calendar' => 'user.calendars.show', 'board' => 'user.tasks.show', 'task' => 'user.tasks.show', 'mind' => 'user.minds.edit', 'persona' => 'user.ai.persona.show', 'brand' => 'user.brand-kits.show', 'staff' => 'user.ai.staff.show', 'agent' => 'user.ai-personas.edit', 'companion' => 'user.ai-companions.edit', 'studio' => 'user.brand-studio.show', 'strategy' => 'user.ai.marketing-strategist.show', 'budget' => 'user.marketing-plan.edit', 'buzz' => 'user.social-proofs.edit'];
        if ($kind === 'plan') {
            return route('user.ai.mind.show', ['record' => $row->id]);
        }
        $route = $routes[$kind] ?? 'user.links.index';
        if (! Route::has($route)) {
            return route('user.links.index');
        }

        return route($route, $kind === 'persona' ? [] : ($kind === 'file' ? ['planner_resource' => 'file:'.$row->id] : ($kind === 'task' ? $row->board_id : ($kind === 'calendar' ? $row->link_id : $row->id))));
    }

    public function attach($project, string $kind, int $id, $user): void
    {
        $row = $this->get($kind, $id, $user);
        if (in_array($kind, ['link', 'form', 'qr', 'plan'])) {
            $row->update(['project_id' => $project->id]);
        }
        if ($kind === 'campaign') $row->update(['project_id' => $project->id]);
        if ($kind === 'budget') $row->update(['payload' => array_replace($row->payload ?? [], ['project_id' => $project->id])]);
        ProjectItem::updateOrCreate(['workspace_id' => $project->workspace_id, 'kind' => $kind, 'resource_id' => $id], ['project_id' => $project->id, 'user_id' => $user->id]);
    }

    public function choices($user): array
    {
        $items = [];
        foreach (array_keys(self::TYPES) as $kind) {
            foreach ($this->query($kind, $user)->latest('id')->limit(60)->get() as $row) {
                $items[] = ['kind' => $kind, 'id' => $row->id, 'label' => $this->label($row), 'url' => $this->url($kind, $row)];
            }
        }

        return $items;
    }

    public function folder($project, $user): array
    {
        $result = [];
        $seen = [];
        foreach (['link', 'form', 'qr', 'plan', 'campaign', 'budget'] as $kind) {
            foreach ($this->query($kind, $user)->where($kind === 'budget' ? 'payload->project_id' : 'project_id', $project->id)->get() as $row) {
                $seen[$kind.':'.$row->id] = true;
                $result[] = ['kind' => $kind, 'row' => $row, 'label' => $this->label($row), 'url' => $this->url($kind, $row)];
            }
        }
        foreach (ProjectItem::where('project_id', $project->id)->get() as $item) {
            if (isset($seen[$item->kind.':'.$item->resource_id])) {
                continue;
            }$row = isset(self::TYPES[$item->kind]) ? $this->query($item->kind, $user)->find($item->resource_id) : null;
            if ($row && (!in_array($item->kind, ['link','form','qr','plan','campaign','budget']) || ($item->kind === 'budget' && !array_key_exists('project_id',$row->payload ?? [])) || (int)($item->kind === 'budget' ? ($row->payload['project_id'] ?? 0) : $row->project_id) === (int)$project->id)) {
                $result[] = ['kind' => $item->kind, 'row' => $row, 'label' => $this->label($row), 'url' => $this->url($item->kind, $row)];
            }
        }

        $boardIds=collect($result)->where('kind','board')->pluck('row.id');
        $seen=collect($result)->mapWithKeys(fn($item)=>[$item['kind'].':'.$item['row']->id=>true])->all();
        $elsewhere=ProjectItem::where('kind','task')->where('project_id','!=',$project->id)->pluck('resource_id');
        foreach($this->query('task',$user)->whereIn('board_id',$boardIds)->whereNotIn('id',$elsewhere)->whereNull('archived_at')->get() as $task) {
            if(isset($seen['task:'.$task->id])) continue;
            $result[]=['kind'=>'task','row'=>$task,'label'=>$this->label($task),'url'=>$this->url('task',$task)];
        }

        return $result;
    }
}
