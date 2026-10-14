@php
    $folderItems=collect($resources);
    $folderPlans=$folderItems->where('kind','plan');
    $folderTasks=$folderItems->where('kind','task');
    $folderLinks=$folderItems->where('kind','link');
    $folderDone=$folderTasks->filter(fn($i)=>$i['row']->completed_at)->count();
    $folderColor=preg_match('/^#[0-9a-f]{6}$/i',$project->color??'')?$project->color:'#365ce9';
    $folderTabs=['overview'=>['Overview','fa-th-large'],'links'=>['Links','fa-link'],'plans'=>['Plans','fa-wand-magic-sparkles'],'tasks'=>['Tasks','fa-check-double'],'resources'=>['Resources','fa-layer-group'],'remember'=>['Notes','fa-note-sticky'],'activity'=>['AI activity','fa-bolt'],'reports'=>['Reports','fa-chart-line']];
@endphp
<header class="folder-head" style="--folder-accent:{{ $folderColor }}">
    <div class="folder-heading"><div class="folder-emblem"><i class="fas fa-folder-open" aria-hidden="true"></i></div><div><span class="folder-eyebrow">YOUR FOLDER WORKSPACE</span><h1>{{ $project->name }}</h1><p>{{ $project->description?:'Plan, build and measure your work in one place.' }}</p></div></div>
    <div class="folder-create">
        @canInWorkspace('create')<a class="btn-primary" href="{{ route('user.ai.mind.show',['project_id'=>$project->id]) }}"><i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i> Create AI plan</a>@endcanInWorkspace
        @canInWorkspace('links.create')<a class="form-chip" href="{{ route('user.links.create',['project_id'=>$project->id]) }}"><i class="fas fa-plus" aria-hidden="true"></i> Create link</a>@endcanInWorkspace
        @canInWorkspace('edit')<a class="folder-icon-action" href="{{ route('user.projects.edit',$project) }}" aria-label="Edit folder"><i class="fas fa-sliders" aria-hidden="true"></i></a>@endcanInWorkspace
    </div>
    <div class="folder-facts"><span><strong>{{ $folderItems->count() }}</strong> resources</span><span><strong>{{ $folderLinks->count() }}</strong> links</span><span><strong>{{ $folderPlans->count() }}</strong> plans</span><span><strong>{{ $folderDone }}/{{ $folderTasks->count() }}</strong> tasks complete</span><span><strong>{{ number_format($folderLinks->sum(fn($i)=>$i['row']->total_clicks)) }}</strong> lifetime clicks</span></div>
</header>
<nav class="folder-nav" aria-label="Folder workspace">
    @foreach($folderTabs as $key=>[$label,$icon])<a href="{{ route('user.projects.show',['project'=>$project->id,'tab'=>$key]) }}" @if($activeTab===$key) aria-current="page" @endif><i class="fas {{ $icon }}" aria-hidden="true"></i>{{ $label }}@if(in_array($key,['links','plans','tasks','resources']))<span>{{ match($key){'links'=>$folderLinks->count(),'plans'=>$folderPlans->count(),'tasks'=>$folderTasks->count(),default=>$folderItems->count()} }}</span>@endif</a>@endforeach
</nav>
