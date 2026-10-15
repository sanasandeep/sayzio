@php
$mapGroups=[['label'=>'Links & pages','kinds'=>['link'],'tab'=>'links','icon'=>'fa-link'],['label'=>'AI Minds','kinds'=>['mind'],'tab'=>'team','icon'=>'fa-brain'],['label'=>'Personas','kinds'=>['persona'],'tab'=>'team','icon'=>'fa-user'],['label'=>'Agents & Staff','kinds'=>['agent','staff','companion'],'tab'=>'team','icon'=>'fa-users-gear'],['label'=>'Planner plans','kinds'=>['plan'],'tab'=>'plans','icon'=>'fa-wand-magic-sparkles'],['label'=>'Tasks','kinds'=>['task'],'tab'=>'tasks','icon'=>'fa-check-double'],['label'=>'Files, kits & budgets','kinds'=>['form','qr','file','calendar','board','brand','budget'],'tab'=>'resources','icon'=>'fa-layer-group'],['label'=>'Campaigns','kinds'=>['campaign','strategy','buzz','studio'],'tab'=>'marketing','icon'=>'fa-bullhorn']];
$chartExports=[];foreach(['hub','flow','people'] as $chartMode) $chartExports[$chartMode]=\App\Services\Planner\FolderChartExport::render($chartMode,$project->name,$resources,$peopleData['people']);
@endphp
<details class="notes-card"><summary class="folder-panel-head" style="cursor:pointer">Map view · explore connections</summary>
<section class="folder-map" x-data="folderChart(@js($chartExports))">
<div class="folder-panel-head"><div><h2>Your project, connected</h2><p class="notes-hint">Explore what is assigned and how the work can flow.</p></div><div class="folder-view-switch"><button @click="mode='hub'" :aria-pressed="mode==='hub'">Hub</button><button @click="mode='flow'" :aria-pressed="mode==='flow'">Flow</button><button @click="mode='people'" :aria-pressed="mode==='people'">People / Process</button></div></div>
<div class="notes-actions folder-export-actions"><button class="form-chip" type="button" @click="download('png')" :disabled="exporting">Export PNG</button><button class="form-chip" type="button" @click="download('svg')" :disabled="exporting">Export SVG</button><span class="notes-hint" role="status" x-text="exportError"></span></div>
<div x-show="mode==='hub'" class="folder-hub">
<svg class="folder-map-lines" viewBox="0 0 1000 330" preserveAspectRatio="none" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="1.5">@foreach([120,370,630,880] as $x)<path d="M500 165 Q{{ $x }} 165 {{ $x }} 45"/><path d="M500 165 Q{{ $x }} 165 {{ $x }} 285"/>@endforeach</g><circle cx="500" cy="165" r="48" fill="none" stroke="currentColor" stroke-dasharray="3 8" class="map-orbit"/></svg>
@foreach($mapGroups as $i=>$group)<a class="folder-map-node map-node-{{ $i }}" href="{{ route('user.projects.show',['project'=>$project->id,'tab'=>$group['tab']]) }}"><span class="folder-mini-icon"><i class="fas {{ $group['icon'] }}" aria-hidden="true"></i></span><span><strong>{{ $group['label'] }}</strong><small>{{ collect($resources)->whereIn('kind',$group['kinds'])->count() }} assigned</small></span></a>@endforeach
<div class="folder-map-center"><i class="fas fa-folder-open" aria-hidden="true"></i><strong>{{ $project->name }}</strong><small>Project hub</small></div>
</div>
<div x-show="mode==='flow'" x-cloak><div class="folder-flow">
@foreach([['Prepare',['file','mind','plan','budget','brand'],'resources','Knowledge & plans'],['Create',['link','form','qr'],'links','Content & forms'],['Reach',['campaign','studio','buzz','strategy'],'marketing','Marketing & audience'],['Act',['task'],'tasks','Tasks & outcomes']] as [$stage,$types,$tab,$detail])<a href="{{ route('user.projects.show',['project'=>$project->id,'tab'=>$tab]) }}" class="folder-flow-node"><span class="folder-eyebrow">{{ $stage }}</span><strong>{{ $detail }}</strong><span>{{ collect($resources)->whereIn('kind',$types)->count() }} assigned items</span><svg viewBox="0 0 100 16" height="16" aria-hidden="true"><path d="M0 8 H90 M84 2 L92 8 L84 14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-dasharray="5 4"/></svg></a>@endforeach
</div><p class="notes-hint mt-4"><span class="folder-suggestion-badge">Suggested workflow</span> This is a possible process, not measured attribution. Recorded item-to-item connections can be managed under Resources. Click a stage to work on it.</p><div class="notes-actions mt-3"><span class="notes-priority">Observed activity</span><span class="notes-hint">{{ number_format(collect($resources)->where('kind','link')->sum(fn($i)=>$i['row']->total_clicks)) }} lifetime link clicks · {{ number_format(collect($resources)->where('kind','buzz')->sum(fn($i)=>$i['row']->impressions)) }} Buzz impressions</span></div></div>
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
</section></details>

<script>
function folderChart(exports) {
    return { mode:'hub', exporting:false, exportError:'', async download(format) {
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
