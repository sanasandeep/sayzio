@php
$mapGroups=[
 ['label'=>'Links & pages','kinds'=>['link'],'tab'=>'links','icon'=>'fa-link','art'=>'links'],
 ['label'=>'Campaigns','kinds'=>['campaign','strategy','studio','buzz'],'tab'=>'marketing','icon'=>'fa-bullhorn','art'=>'pages'],
 ['label'=>'Budget & Forecast','kinds'=>['budget'],'tab'=>'budget','icon'=>'fa-wallet','art'=>'coins'],
 ['label'=>'Brand Kits','kinds'=>['brand'],'tab'=>'brands','icon'=>'fa-palette','art'=>'folders'],
 ['label'=>'Planner plans','kinds'=>['plan'],'tab'=>'plans','icon'=>'fa-wand-magic-sparkles','art'=>'pages'],
 ['label'=>'Tasks','kinds'=>['task'],'tab'=>'tasks','icon'=>'fa-check-double','art'=>'pages'],
 ['label'=>'AI Minds & team','kinds'=>['mind','persona','agent','staff','companion'],'tab'=>'team','icon'=>'fa-brain','art'=>'bot'],
 ['label'=>'Resources','kinds'=>['form','qr','file','calendar','board'],'tab'=>'resources','icon'=>'fa-layer-group','art'=>'folders'],
];
$mapColor=preg_match('/^#[0-9a-f]{6}$/i',$project->color??'')?$project->color:'#365ce9';
$chartExports=[];foreach(['hub','flow','people'] as $chartMode) $chartExports[$chartMode]=\App\Services\Planner\FolderChartExport::render($chartMode,$project->name,$resources,$peopleData['people'],$mapColor);
@endphp
<section id="project-map" class="notes-card folder-map visual-folder-map" style="--folder-color:{{ $mapColor }};--ov-tone:{{ $mapColor }}" x-data="folderChart(@js($chartExports))" @keydown.escape.window="closePanel()">
<div class="folder-panel-head"><div><h2>Map view · explore connections</h2><p class="notes-hint">Select a node to explore its items, assign resources or create something new.</p></div><div class="folder-view-switch"><button @click="mode='hub'" :aria-pressed="mode==='hub'">Hub</button><button @click="mode='flow'" :aria-pressed="mode==='flow'">Flow</button><button @click="mode='people'" :aria-pressed="mode==='people'">People / Process</button></div></div>
<div class="notes-actions folder-export-actions"><button class="form-chip" type="button" @click="download('png')" :disabled="exporting">Export PNG</button><button class="form-chip" type="button" @click="download('svg')" :disabled="exporting">Export SVG</button><span class="notes-hint" role="status" x-text="exportError"></span></div>
<div x-show="mode==='hub'" class="folder-hub visual-hub">
<svg class="folder-map-lines" viewBox="0 0 1000 480" preserveAspectRatio="none" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="1.8">@foreach([125,375,625,875] as $x)<path d="M500 240 Q{{ $x }} 240 {{ $x }} 90"/><path d="M500 240 Q{{ $x }} 240 {{ $x }} 390"/>@endforeach</g><circle cx="500" cy="240" r="55" fill="none" stroke="currentColor" stroke-dasharray="3 9"/></svg>
@foreach($mapGroups as $i=>$group)
@php $nodeItems=collect($resources)->whereIn('kind',$group['kinds']); @endphp
<button type="button" class="folder-map-node map-node-{{ $i }} visual-map-node {{ $nodeItems->isEmpty()?'is-empty':'' }}" @click="openPanel({{ $i }},$event.currentTarget)" :aria-expanded="selected==={{ $i }}" aria-controls="folder-map-panel">
<span class="map-node-art">@include('user.partials.count-card-art',['kind'=>$group['art'],'artId'=>'map-art-'.$i])</span><span class="map-node-copy"><strong>{{ $group['label'] }}</strong><small>{{ $nodeItems->count() }} assigned</small>
@if($nodeItems->isEmpty())<span class="map-node-add">+ Assign</span>@else<span class="map-node-preview">{{ \Illuminate\Support\Str::limit($nodeItems->first()['label'],27) }}</span>@endif
@if($group['tab']==='tasks' && $nodeItems->isNotEmpty())<span class="folder-progress"><span style="width:{{ round($nodeItems->filter(fn($v)=>$v['row']->completed_at)->count()/$nodeItems->count()*100) }}%"></span></span>@endif
@if($group['tab']==='brands' && $nodeItems->isNotEmpty())<span class="map-brand-swatches">@foreach(collect($nodeItems->first()['row']->palette())->only(['primary','secondary','accent']) as $swatch)@if(is_string($swatch) && preg_match('/^#[0-9a-f]{6}$/i',$swatch))<i style="background:{{ $swatch }}"></i>@endif
@endforeach</span>@endif
@if($group['tab']==='links' && $nodeItems->isNotEmpty())@foreach($nodeItems->take(3) as $preview)@if(\Illuminate\Support\Str::startsWith((string)$preview['row']->favicon,['https://','http://','/']))<img class="map-node-thumb" src="{{ $preview['row']->favicon }}" alt="" loading="lazy">@endif
@endforeach
@endif
</span></button>
@endforeach
<div class="folder-map-center"><i class="fas fa-folder-open" aria-hidden="true"></i><strong>{{ $project->name }}</strong><small>{{ count($resources) }} connected resources</small></div>
</div>
<div class="map-panel-backdrop" x-show="selected!==null" x-cloak @click.self="closePanel()">
<aside id="folder-map-panel" class="map-detail-panel notes-card" role="dialog" aria-modal="true" aria-label="Assigned folder items" tabindex="-1" x-ref="panel" @keydown.tab="trapTab($event)">
@foreach($mapGroups as $i=>$group)
@php $panelItems=collect($resources)->whereIn('kind',$group['kinds']); @endphp
<div class="map-panel-section" x-show="selected==={{ $i }}">
<header class="map-panel-header"><span class="map-panel-emblem"><i class="fas {{ $group['icon'] }}" aria-hidden="true"></i></span><div class="map-panel-heading"><h2>{{ $group['label'] }}</h2><p>{{ $panelItems->count() }} assigned · {{ $project->name }}</p></div><button class="map-panel-close" type="button" @click="closePanel()" aria-label="Close assigned items"><i class="fas fa-xmark" aria-hidden="true"></i></button></header>
<div class="map-panel-body">
@forelse($panelItems as $item)
<a class="map-item-card" href="{{ $item['url'] }}"><span class="map-item-icon"><i class="fas {{ $group['icon'] }}" aria-hidden="true"></i></span><span class="map-item-copy"><strong>{{ $item['label'] }}</strong><small>{{ \App\Services\Planner\PlannerResources::typeLabels()[$item['kind']] }}</small></span><span class="map-item-open">Open <i class="fas fa-arrow-right" aria-hidden="true"></i></span></a>
@empty<div class="map-panel-empty"><i class="fas {{ $group['icon'] }}" aria-hidden="true"></i><strong>Nothing assigned here yet.</strong><p>Choose an existing item or create something new for this folder.</p></div>
@endforelse
</div>
<footer class="map-panel-footer"><a class="form-chip" href="{{ route('user.projects.show',[$project,'tab'=>$group['tab']]) }}">View all →</a><a class="form-chip" href="{{ route('user.projects.show',[$project,'tab'=>'resources']) }}#assign-resources">Assign existing</a>
@php $createLabel=match($group['tab']){'marketing'=>'Create campaign','budget'=>'Create forecast','plans'=>'Create Planner plan','links'=>'Create link','brands'=>'Create Brand Kit',default=>null}; @endphp
@foreach($folderActions??[] as $action)
@if($action['label']===$createLabel)<a class="form-chip map-panel-primary" href="{{ $action['url'] }}">{{ $createLabel }}</a>@endif
@endforeach
</footer></div>
@endforeach
</aside></div>
<div x-show="mode==='flow'" x-cloak>
@php $flowStages=\App\Services\Planner\FolderFlowStages::all(); $flowKeys=array_keys($flowStages); $flowEdges=\App\Services\Planner\FolderFlowEdges::from($relations); $flowItems=collect($resources); $flowOffRail=\App\Services\Planner\FolderFlowEdges::offRailCount($flowEdges); @endphp
<div class="folder-flow">
@foreach($flowStages as $stageKey => $stage)@php $nextStage=$flowKeys[$loop->index+1]??null; $recorded=$nextStage?\App\Services\Planner\FolderFlowEdges::recordedBetween($flowEdges,$stageKey,$nextStage):0; $insideCount=$flowEdges['inside'][$stageKey]??0; @endphp<a href="{{ route('user.projects.show',['project'=>$project->id,'tab'=>$stage['tab']]) }}" class="folder-flow-node"><span class="folder-eyebrow">{{ $stage['eyebrow'] }}</span><strong>{{ $stage['title'] }}</strong><span class="folder-flow-count">{{ $flowItems->whereIn('kind',$stage['kinds'])->count() }} assigned items</span>@if($insideCount)<span class="folder-flow-inside">{{ $insideCount }} connected to each other here</span>@endif
@if($nextStage)<svg class="folder-flow-arrow {{ $recorded ? 'is-recorded' : 'is-suggested' }}" viewBox="0 0 100 16" height="16" aria-hidden="true"><path d="M0 8 H90 M84 2 L92 8 L84 14" fill="none" stroke="currentColor" stroke-width="1.5"/></svg><span class="folder-flow-edge-label">{{ $recorded ? $recorded.' recorded connection'.($recorded===1?'':'s').' to '.$flowStages[$nextStage]['eyebrow'] : 'Nothing recorded into '.$flowStages[$nextStage]['eyebrow'] }}</span>@endif</a>@endforeach
</div>
<div class="folder-connection-summary"><span><span class="map-legend-solid"></span> Saved item-to-item connections</span><span><span class="map-legend-dashed"></span> Suggested order only</span></div>
<p class="notes-hint mt-4"><span class="folder-suggestion-badge">Suggested order</span> The stages are a possible process, not measured attribution. A solid connector is real: that many item-to-item connections were saved between those two stages under Resources. A dashed one means none were saved there — not that nothing happened.</p>
@if($flowOffRail)<p class="notes-hint mt-2">{{ $flowOffRail }} saved connection{{ $flowOffRail===1?'':'s' }} run between stages this rail does not join ({{ collect($flowEdges['offRail'])->take(3)->map(fn($e)=>$flowStages[$e['from']]['eyebrow'].' → '.$flowStages[$e['to']]['eyebrow'].' ×'.$e['count'])->implode(', ') }}). They are counted here but not drawn above; manage them under Resources.</p>@endif
@if($flowEdges['unplaced'])<p class="notes-hint mt-2">{{ $flowEdges['unplaced'] }} saved connection{{ $flowEdges['unplaced']===1?'':'s' }} point at an item kind that no longer has a stage, so they are counted but not drawn.</p>@endif
<div class="notes-actions mt-3"><span class="notes-priority">Observed activity</span><span class="notes-hint">{{ number_format(collect($resources)->where('kind','link')->sum(fn($i)=>$i['row']->total_clicks)) }} lifetime link clicks · {{ number_format(collect($resources)->where('kind','buzz')->sum(fn($i)=>$i['row']->impressions)) }} Buzz impressions</span></div></div>
<div x-show="mode==='people'" x-cloak>
    <p class="notes-hint mb-4">Who has access, what they are assigned, and their recorded task activity. Shared tasks count for every assignee; this view does not indicate live presence.</p>
    <div class="folder-card-grid">@foreach($peopleData['people'] as $person)<article class="folder-person-card"><div class="folder-person-heading"><span class="folder-person-avatar" x-data="{photoFailed:false}"><span x-show="photoFailed" x-cloak>{{ mb_substr($person['name'],0,1) }}</span><img src="{{ $person['avatar_url'] }}" alt="{{ $person['name'] }}" loading="lazy" x-show="!photoFailed" x-on:error="photoFailed=true" style="width:100%;height:100%;object-fit:cover;border-radius:inherit"></span><div><strong>{{ $person['name'] }}</strong><small>{{ $person['role'] }}</small></div></div><div class="folder-person-stats"><span><strong>{{ $person['open'] }}</strong>Open tasks</span><span><strong>{{ $person['completed'] }}</strong>Completed</span><span><strong>{{ $person['actions'] }}</strong>Actions · 30d</span></div><div class="folder-person-process"><span>Assigned {{ $person['assigned'] }}</span><i class="fas fa-arrow-right" aria-hidden="true"></i><span>Open {{ $person['open'] }}</span><i class="fas fa-arrow-right" aria-hidden="true"></i><span>Done {{ $person['completed'] }}</span></div>@forelse(array_slice($person['tasks'],0,4) as $personTask)<div class="folder-mini-row"><i class="fas {{ $personTask['completed']?'fa-circle-check':'fa-circle' }}" aria-hidden="true"></i><span>{{ $personTask['title'] }}</span><small>{{ $personTask['completed']?'Done':($personTask['due']??'No due date') }}</small></div>@empty<p class="notes-hint mt-3">No visible task assignments yet.</p>@endforelse</article>@endforeach</div>
</div>
<div class="folder-connection-summary"><span><span class="map-legend-solid"></span> Assigned project resources</span><span><span class="map-legend-dashed"></span> Suggested paths</span></div>
@if($relations->isNotEmpty())
@php $mapItems=collect($resources)->keyBy(fn($i)=>$i['kind'].':'.$i['row']->id); @endphp
<div class="folder-recorded-pairs">@foreach($relations->take(3) as $pair)<a href="{{ route('user.projects.show',['project'=>$project->id,'tab'=>'resources']) }}"><strong>{{ $mapItems[$pair->source_kind.':'.$pair->source_id]['label'] }}</strong> {{ $pair->label }} → <strong>{{ $mapItems[$pair->target_kind.':'.$pair->target_id]['label'] }}</strong></a>@endforeach</div><p class="notes-hint mt-2">{{ $relations->count() }} recorded item connections · manage under Resources.</p>
@endif
@php $eligible=collect($resources)->whereIn('kind',['link','form','task','plan','mind','staff','strategy'])->values(); @endphp
@if($eligible->count()>1 && $relations->isEmpty())<p class="notes-hint mt-3">Suggestion: connect a task or plan to the content it supports. Review and save it in Resources; no association is created automatically.</p>@endif
@foreach(collect($resources)->where('kind','plan')->take(2) as $suggestionPlan)
@if(!empty($suggestionPlan['row']->result['tasks']))<div class="folder-suggested-task"><span class="folder-suggestion-badge">AI plan suggestion · review first</span><strong>{{ $suggestionPlan['row']->result['tasks'][0]['title'] }}</strong><a href="{{ $suggestionPlan['url'] }}" class="notes-hint">Review in {{ $suggestionPlan['label'] }} →</a></div>@endif
@endforeach
</section>

<script>
function folderChart(exports) {
    return { mode:'hub', selected:null, trigger:null, openPanel(index,trigger){this.selected=index;this.trigger=trigger;this.previousOverflow=document.body.style.overflow;document.body.style.overflow='hidden';this.$nextTick(()=>this.$refs.panel.focus())}, closePanel(){if(this.selected===null)return;this.selected=null;document.body.style.overflow=this.previousOverflow||'';this.trigger?.focus()}, trapTab(event){const items=[...this.$refs.panel.querySelectorAll('a,button,[tabindex]')].filter(el=>el.getClientRects().length&&!el.disabled);const first=items[0],last=items.at(-1);if(!first){event.preventDefault();return}if(event.shiftKey&&(document.activeElement===first||document.activeElement===this.$refs.panel)){event.preventDefault();last.focus()}else if(!event.shiftKey&&(document.activeElement===last||document.activeElement===this.$refs.panel)){event.preventDefault();first.focus()}}, exporting:false, exportError:'', async download(format) {
        this.exporting=true; this.exportError=''; let sourceUrl;
        try {
            const svg=exports[this.mode]; let blob=new Blob([svg],{type:'image/svg+xml;charset=utf-8'});
            if(format==='png') {
                sourceUrl=URL.createObjectURL(blob); const img=new Image();
                await new Promise((resolve,reject)=>{img.onload=resolve;img.onerror=reject;img.src=sourceUrl;});
                const canvas=document.createElement('canvas'); canvas.width=img.naturalWidth*2;canvas.height=img.naturalHeight*2;
                canvas.getContext('2d').drawImage(img,0,0,canvas.width,canvas.height);
                blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/png'));
                if(!blob) throw new Error('Export failed');
            }
            const url=URL.createObjectURL(blob), a=document.createElement('a'); a.href=url;a.download='project-'+this.mode+'.'+format;a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);
        } catch(error) {this.exportError='Image export failed. Try the SVG download.';} finally {if(sourceUrl)URL.revokeObjectURL(sourceUrl);this.exporting=false;}
    }};
}
</script>
