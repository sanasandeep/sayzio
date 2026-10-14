@extends('user.layouts.app')
@section('title', $project->name.' · Folder workspace')
@section('content')
@include('user.planner.styles')
@php
 $plans=collect($resources)->where('kind','plan');$tasks=collect($resources)->where('kind','task');$links=collect($resources)->where('kind','link');
 $done=$tasks->filter(fn($item)=>$item['row']->completed_at)->count();
 $coins=$runs->sum('coins_spent');
 $canAssignFolder=((int)$project->created_by_user_id===(int)auth()->id() || \App\Modules\User\Services\WorkspacePermissions::userCan('projects.edit_others')) && \App\Modules\User\Services\WorkspacePermissions::userCan('projects.edit') && \App\Modules\User\Services\WorkspacePermissions::userCan('planner.edit');
@endphp
@php $activeTab=in_array(request('tab'),['overview','plans','tasks','team','marketing','resources','remember','activity'])?request('tab'):'overview'; @endphp
<div class="notes-studio folder-workspace" x-data="{tab:@js($activeTab),resourceKinds:[],query:''}">
@include('user.planner.folder-shell')
<div class="notes-stack" x-show="tab==='overview'">
@include('user.vault.clients.folder-projects')
@include('user.planner.folder-map')
@if(count($attention))<section class="notes-card"><h2>Needs attention</h2><div class="notes-actions">@foreach(array_slice($attention,0,6) as $issue)<a class="form-chip" href="{{ $issue['url'] }}"><i class="fas fa-circle-exclamation text-amber-500"></i>{{ $issue['label'] }}</a>@endforeach</div></section>@endif
    <div class="notes-metrics">
        <div class="notes-metric"><i class="fas fa-link" aria-hidden="true"></i>Link engagement<strong>{{ number_format($links->sum(fn($i)=>$i['row']->total_clicks)) }}</strong><small>Lifetime clicks across {{ $links->count() }} links</small></div>
        <div class="notes-metric"><i class="fas fa-check-double" aria-hidden="true"></i>Work completed<strong>{{ $done }} / {{ $tasks->count() }}</strong><small>{{ $plans->count() }} plans · {{ $tasks->count()-$done }} tasks remaining</small></div>
        <div class="notes-metric"><i class="fas fa-layer-group" aria-hidden="true"></i>Connected resources<strong>{{ count($resources) }}</strong><small>{{ collect($resources)->pluck('kind')->unique()->count() }} resource types in this folder</small></div>
    </div>
    <div class="folder-overview-grid">
        <section class="notes-card"><div class="folder-panel-head"><h2>Content & performance</h2><a href="{{ route('user.projects.show',['project'=>$project->id,'tab'=>'links']) }}">Manage links →</a></div>
            @forelse($links->sortByDesc(fn($i)=>$i['row']->total_clicks)->take(5) as $item)
            <a class="folder-mini-row" href="{{ $item['url'] }}"><span class="folder-mini-icon"><i class="fas fa-link" aria-hidden="true"></i></span><span class="folder-mini-copy"><strong>{{ $item['label'] }}</strong><small>{{ ucfirst(str_replace('_',' ',$item['row']->type)) }} · {{ $item['row']->is_active?'Active':'Inactive' }}</small></span><span class="folder-mini-stat">{{ number_format($item['row']->total_clicks) }}<small>clicks</small></span></a>
            @empty<div class="folder-empty"><p>Add links and pages to connect your content with the work in this folder.</p><a class="form-chip" href="{{ route('user.projects.show',['project'=>$project->id,'tab'=>'resources']) }}">+ Add existing resources</a></div>@endforelse
        </section>
        <section class="notes-card"><div class="folder-panel-head"><h2>Next actions</h2><a href="{{ route('user.projects.show',['project'=>$project->id,'tab'=>'tasks']) }}">View tasks →</a></div>
            @forelse($tasks->filter(fn($i)=>!$i['row']->completed_at)->take(4) as $item)<a class="folder-mini-row" href="{{ $item['url'] }}"><span class="folder-mini-icon"><i class="fas fa-check" aria-hidden="true"></i></span><span class="folder-mini-copy"><strong>{{ $item['label'] }}</strong><small>{{ $item['row']->due_date?->format('d M Y')??'No due date' }} · {{ $item['row']->priority }} priority</small><span class="folder-progress"><span style="width:{{ max(0,min(100,(int)$item['row']->progress)) }}%"></span></span></span></a>@empty<div class="folder-empty"><span class="folder-mini-icon"><i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i></span><p>Turn your campaign notes into a plan, then create tasks alongside your content.</p>@canInWorkspace('create')<a class="form-chip" href="{{ route('user.ai.mind.show',['project_id'=>$project->id]) }}">Create your first AI plan →</a>@endcanInWorkspace</div>@endforelse
        </section>
        <section class="notes-card"><div class="folder-panel-head"><h2>Plans & AI work</h2><a href="{{ route('user.projects.show',['project'=>$project->id,'tab'=>'plans']) }}">View plans →</a></div>@forelse($plans->take(3) as $item)<a class="folder-mini-row" href="{{ $item['url'] }}"><span class="folder-mini-icon"><i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i></span><span class="folder-mini-copy"><strong>{{ $item['label'] }}</strong><small>{{ $item['row']->automation_enabled?'Scheduled reviews enabled':'Manual planning' }}</small></span></a>@empty<p class="notes-hint">Plans created for this folder stay connected to its tasks and resources.</p>@endforelse
            @if($runs->isNotEmpty())<p class="notes-hint mt-4">Latest review: {{ str_replace('_',' ',$runs->first()->status) }} · {{ $runs->first()->created_at->diffForHumans() }}</p>@endif
        </section>
        <section class="notes-card"><div class="folder-panel-head"><h2>Notes to remember</h2><a href="{{ route('user.projects.show',['project'=>$project->id,'tab'=>'remember']) }}">Manage notes →</a></div>@forelse($notes->whereNull('completed_at')->take(2) as $note)<div class="folder-mini-row"><span class="folder-mini-icon"><i class="fas fa-note-sticky" aria-hidden="true"></i></span><span class="folder-mini-copy"><strong>{{ \Illuminate\Support\Str::limit($note->body,180) }}</strong><small>{{ $note->shared?'Shared with workspace':'Private to you' }}</small></span></div>@empty<p class="notes-hint">Keep campaign goals, decisions and reminders beside the work they relate to.</p>@endforelse</section>
    </div>
</div>
@foreach(['plans'=>'plan'] as $view=>$type)<div class="notes-stack" x-show="tab==='{{ $view }}'" x-cloak>@forelse(collect($resources)->where('kind',$type) as $item)<a href="{{ $item['url'] }}" class="notes-card"><h2>{{ $item['label'] }}</h2><p class="notes-hint">{{ $type==='plan'?($item['row']->automation_enabled?'Background work enabled':'Open plan and next actions'):($item['row']->completed_at?'Done':$item['row']->progress.'% complete') }}</p></a>@empty<div class="notes-card notes-hint">No {{ $view }} in this folder yet. Add existing items under Resources or create an AI plan.</div>@endforelse</div>@endforeach
<div class="notes-stack" x-show="tab==='resources'" x-cloak>
<div class="notes-card folder-resource-panel">
    @php
        $resourceCounts = collect($resources)->countBy('kind')->all();
        $resourceOptions = collect(\App\Services\Planner\PlannerResources::typeLabels())->sortByDesc(fn($label,$kind)=>$resourceCounts[$kind]??0)->all();
        $filterResources = array_map(fn($item)=>['kind'=>$item['kind'],'label'=>mb_strtolower($item['label'])], $resources);
    @endphp
    <div class="folder-panel-head"><div><span class="folder-eyebrow">Your connected workspace</span><h2>All folder resources</h2><p class="notes-hint">{{ count($resources) }} resources · combine types to find the right work.</p></div><span class="folder-section-badge"><i class="fas fa-layer-group" aria-hidden="true"></i>{{ count($resourceCounts) }} types</span></div>
    <div class="folder-resource-toolbar"><label class="folder-search"><i class="fas fa-search" aria-hidden="true"></i><input x-model="query" placeholder="Search resources…" aria-label="Search folder resources"></label>@include('user.planner.multi-filter',['model'=>'resourceKinds','options'=>$resourceOptions,'counts'=>$resourceCounts,'filterLabel'=>'Resource types'])</div>
    <div class="folder-selected-chips" x-show="resourceKinds.length"><template x-for="type in resourceKinds" :key="type"><button type="button" class="form-chip" @click="resourceKinds=resourceKinds.filter(v=>v!==type)"><span x-text="type"></span><span aria-hidden="true">×</span></button></template><button type="button" class="folder-clear-link" @click="resourceKinds=[];query=''">Reset filters</button></div>
    <div class="folder-resource-list">
        @foreach($resources as $item)
        <a href="{{ $item['url'] }}" class="folder-resource-row" x-show="(!resourceKinds.length||resourceKinds.includes(@js($item['kind'])))&&@js(mb_strtolower($item['label'])).includes(query.trim().toLowerCase())">
            <span class="folder-resource-icon">@if($item['kind']==='link' && \Illuminate\Support\Str::startsWith((string)$item['row']->favicon,['https://','http://','/']))<img src="{{ $item['row']->favicon }}" alt="" width="30" height="30" loading="lazy">@else<i class="fas {{ ['link'=>'fa-link','form'=>'fa-clipboard-list','qr'=>'fa-qrcode','file'=>'fa-file','calendar'=>'fa-calendar','board'=>'fa-table-columns','task'=>'fa-check','plan'=>'fa-wand-magic-sparkles','mind'=>'fa-brain','persona'=>'fa-user','buzz'=>'fa-bullhorn'][$item['kind']]??'fa-layer-group' }}" aria-hidden="true"></i>@endif</span>
            <span class="folder-resource-copy"><span class="folder-resource-type">{{ $resourceOptions[$item['kind']] }}</span><strong>{{ $item['label'] }}</strong>@if($item['kind']==='link')<small>{{ ucfirst($item['row']->type) }} · {{ $item['row']->is_active?'Active':'Inactive' }} · {{ number_format($item['row']->total_clicks) }} clicks</small>@else<small>Connected to {{ $project->name }}</small>@endif</span><span class="folder-resource-open">Open <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></span>
        </a>
        @endforeach
        <div class="folder-filter-empty" x-show="!@js($filterResources).some(item=>(!resourceKinds.length||resourceKinds.includes(item.kind))&&item.label.includes(query.trim().toLowerCase()))"><i class="fas fa-search" aria-hidden="true"></i><strong>No matching resources</strong><p>Choose other types or clear your search.</p><button type="button" class="form-chip" @click="resourceKinds=[];query=''">Clear filters</button></div>
    </div>
</div>
@include('user.planner.folder-connections')
@if($canAssignFolder)<div class="notes-card"><h2>+ Add / move existing items</h2><p class="notes-hint mb-3">Select multiple resources. Moving them changes their folder association; the resource itself is kept.</p><form method="POST" action="{{ route('user.ai.planner.attach',$project) }}">@csrf
@include('user.planner.chooser',['choices'=>$choices,'fieldName'=>'items[]','selected'=>[],'label'=>'Add / move items','limit'=>50])<button class="btn-primary px-4 py-2 mt-3">Move selected to folder</button></form></div>@endif</div>
<div x-show="tab==='tasks'" x-cloak>@include('user.planner.folder-tasks')</div>
<div x-show="tab==='remember'" x-cloak>@include('user.planner.folder-notes')</div>
<div x-show="tab==='team'" x-cloak>@include('user.planner.folder-team')</div>
<div x-show="tab==='marketing'" x-cloak>@include('user.planner.folder-marketing')</div>
<div x-show="tab==='activity'" x-cloak>@include('user.planner.folder-timeline')</div>

</div>
@endsection
