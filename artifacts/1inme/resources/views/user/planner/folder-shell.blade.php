@php
    $folderItems=collect($resources);
    $folderPlans=$folderItems->where('kind','plan');
    $folderTasks=$folderItems->where('kind','task');
    $folderLinks=$folderItems->where('kind','link');
    $folderDone=$folderTasks->filter(fn($i)=>$i['row']->completed_at)->count();
    $folderColor=preg_match('/^#[0-9a-f]{6}$/i',$project->color??'')?$project->color:'#365ce9';
    $folderTabs=['overview'=>['Overview','fa-th-large'],'resources'=>['Resources','fa-layer-group'],'work'=>['Work','fa-check-double'],'reports'=>['Reports','fa-chart-line']];
    $navTab=match($activeTab){'plans','tasks','remember','activity','work'=>'work','links','team','marketing','budget','brands'=>'resources',default=>$activeTab};
@endphp
@php
$folderActions=[];
if(auth()->user()->canInWorkspace(app('current_workspace'),'ai.view')) {
$folderActions[]=['label'=>'Create campaign','url'=>route('user.marketing-studio.create',['project_id'=>$project->id]),'icon'=>'fa-bullhorn','class'=>'btn-primary'];
$folderActions[]=['label'=>'Create forecast','url'=>route('user.marketing-plan.create',['project_id'=>$project->id]),'icon'=>'fa-wallet','class'=>'btn-ghost'];
}
$folderActions[]=['label'=>'Assign existing','url'=>route('user.projects.show',[$project,'tab'=>'resources']).'#assign-resources','icon'=>'fa-layer-group','class'=>'btn-ghost'];
if(auth()->user()->canInWorkspace(app('current_workspace'),'create'))$folderActions[]=['label'=>'Create Planner plan','url'=>route('user.ai.mind.show',['project_id'=>$project->id]),'icon'=>'fa-wand-magic-sparkles','class'=>'btn-primary'];
if(auth()->user()->canInWorkspace(app('current_workspace'),'links.create'))$folderActions[]=['label'=>'Create Brand Kit','url'=>route('user.brand-kits.create'),'icon'=>'fa-palette','class'=>'btn-ghost'];
if(auth()->user()->canInWorkspace(app('current_workspace'),'links.create'))$folderActions[]=['label'=>'Create link','url'=>route('user.links.create',['project_id'=>$project->id]),'icon'=>'fa-plus','class'=>'btn-ghost'];
if(auth()->user()->canInWorkspace(app('current_workspace'),'edit'))$folderActions[]=['label'=>'Folder settings','url'=>route('user.projects.edit',$project),'icon'=>'fa-sliders','class'=>'btn-ghost'];
@endphp
<div class="folder-colored-header" style="--folder-color:{{ $folderColor }}">
@include('user.partials.page-hero',['title'=>$project->name,'subtitle'=>$project->description?:'Content, knowledge and work in one connected project.','icon'=>'fa-folder-open','emblemIcon'=>'fa-folder-open','favicon'=>null,'url'=>null,'back'=>route('user.dashboard').'#folders','actions'=>[],'chips'=>[['text'=>'Folder workspace','icon'=>'fa-folder'],['text'=>$folderItems->count().' resources','icon'=>'fa-layer-group'],['text'=>$folderLinks->count().' links','icon'=>'fa-link']]])

</div>
<div class="folder-quick-actions">
<details class="folder-menu"><summary class="form-chip">+ Create</summary><div class="folder-menu-items">@foreach($folderActions as $action)@if(str_starts_with($action['label'],'Create'))<a href="{{ $action['url'] }}"><i class="fas {{ $action['icon'] }}" aria-hidden="true"></i>{{ $action['label'] }}</a>@endif
@endforeach</div></details>
<a class="form-chip" href="{{ route('user.projects.show',[$project,'tab'=>'resources']) }}#assign-resources">Assign existing</a>
<details class="folder-menu"><summary class="form-chip" aria-label="Folder options">⋯</summary><div class="folder-menu-items">@foreach($folderActions as $action)@if($action['label']==='Folder settings')<a href="{{ $action['url'] }}">Folder settings</a>@endif
@endforeach<a href="{{ route('user.projects.show',[$project,'tab'=>'overview']).'#project-map' }}">Map & exports</a></div></details>
</div>
<nav class="folder-nav folder-simple-nav" aria-label="Folder workspace">
@foreach($folderTabs as $key=>[$label,$icon])<a href="{{ route('user.projects.show',['project'=>$project->id,'tab'=>$key]) }}" @if($navTab===$key) aria-current="page" @endif><i class="fas {{ $icon }}" aria-hidden="true"></i>{{ $label }}</a>@endforeach
</nav>
<span class="sr-only">Project section</span>
