@php
    $folderItems=collect($resources);
    $folderPlans=$folderItems->where('kind','plan');
    $folderTasks=$folderItems->where('kind','task');
    $folderLinks=$folderItems->where('kind','link');
    $folderDone=$folderTasks->filter(fn($i)=>$i['row']->completed_at)->count();
    $folderColor=preg_match('/^#[0-9a-f]{6}$/i',$project->color??'')?$project->color:'#365ce9';
    $folderTabs=['overview'=>['Overview','fa-th-large'],'links'=>['Links','fa-link'],'plans'=>['Planner plans','fa-wand-magic-sparkles'],'tasks'=>['Tasks','fa-check-double'],'team'=>['AI team','fa-users-gear'],'marketing'=>['Campaigns','fa-bullhorn'],'budget'=>['Budget & Forecast','fa-wallet'],'brands'=>['Brand Kits','fa-palette'],'resources'=>['Resources','fa-layer-group'],'remember'=>['Notes','fa-note-sticky'],'activity'=>['AI activity','fa-bolt'],'reports'=>['Reports','fa-chart-line']];
@endphp
@php
$folderActions=[];
if(auth()->user()->canInWorkspace(app('current_workspace'),'ai.view')) {
$folderActions[]=['label'=>'Create campaign','url'=>route('user.marketing-studio.create',['project_id'=>$project->id]),'icon'=>'fa-bullhorn','class'=>'btn-primary'];
$folderActions[]=['label'=>'Create forecast','url'=>route('user.marketing-plan.create',['project_id'=>$project->id]),'icon'=>'fa-wallet','class'=>'btn-ghost'];
}
$folderActions[]=['label'=>'Assign existing','url'=>route('user.projects.show',[$project,'tab'=>'resources']).'#assign-resources','icon'=>'fa-layer-group','class'=>'btn-ghost'];
if(auth()->user()->canInWorkspace(app('current_workspace'),'create'))$folderActions[]=['label'=>'Create Planner plan','url'=>route('user.ai.mind.show',['project_id'=>$project->id]),'icon'=>'fa-wand-magic-sparkles','class'=>'btn-primary'];
if(auth()->user()->canInWorkspace(app('current_workspace'),'links.create'))$folderActions[]=['label'=>'Create link','url'=>route('user.links.create',['project_id'=>$project->id]),'icon'=>'fa-plus','class'=>'btn-ghost'];
if(auth()->user()->canInWorkspace(app('current_workspace'),'edit'))$folderActions[]=['label'=>'Folder settings','url'=>route('user.projects.edit',$project),'icon'=>'fa-sliders','class'=>'btn-ghost'];
@endphp
@include('user.partials.page-hero',['title'=>$project->name,'subtitle'=>$project->description?:'Content, knowledge and work in one connected project.','icon'=>'fa-folder-open','emblemIcon'=>'fa-folder-open','favicon'=>null,'url'=>null,'back'=>route('user.dashboard').'#folders','actions'=>$folderActions,'chips'=>[['text'=>'Folder workspace','icon'=>'fa-folder'],['text'=>$folderItems->count().' resources','icon'=>'fa-layer-group'],['text'=>$folderLinks->count().' links','icon'=>'fa-link']]])
<div class="folder-tab-picker"><label for="folder-section">Project section</label><select id="folder-section" class="theme-input" onchange="window.location.assign(this.value)">@foreach($folderTabs as $key=>[$label,$icon])<option value="{{ route('user.projects.show',['project'=>$project->id,'tab'=>$key]) }}" @selected($activeTab===$key)>{{ $label }}</option>@endforeach</select></div>
<nav class="folder-nav" aria-label="Folder workspace" x-data @resize.window.debounce.100ms="$nextTick(()=>{const active=$el.querySelector('[aria-current=page]');if(active)$el.scrollLeft=active.offsetLeft-12})" x-init="$nextTick(()=>{const active=$el.querySelector('[aria-current=page]');if(active)$el.scrollLeft=active.offsetLeft-12})">
    @foreach($folderTabs as $key=>[$label,$icon])<a href="{{ route('user.projects.show',['project'=>$project->id,'tab'=>$key]) }}" @if($activeTab===$key) aria-current="page" @endif><i class="fas {{ $icon }}" aria-hidden="true"></i>{{ $label }}@if(in_array($key,['links','plans','tasks','resources']))<span>{{ match($key){'links'=>$folderLinks->count(),'plans'=>$folderPlans->count(),'tasks'=>$folderTasks->count(),default=>$folderItems->count()} }}</span>@endif</a>@endforeach
</nav>
