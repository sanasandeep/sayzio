@php
    $marketing=collect($resources)->whereIn('kind',['campaign','strategy','buzz','studio']);
    $buzz=$marketing->where('kind','buzz');
    $marketingOptions=['campaign'=>'Studio campaigns','strategy'=>'Strategies','buzz'=>'Buzz campaigns','studio'=>'Studio materials'];
    $marketingChoices=array_values(array_filter($choices,fn($choice)=>isset($marketingOptions[$choice['kind']])));
    $marketingSearch=$marketing->map(fn($item)=>['kind'=>$item['kind'],'label'=>mb_strtolower($item['label'])])->values()->all();
@endphp
<div class="notes-stack folder-marketing" x-data="{marketingKinds:[],marketingQuery:'',assignMarketing:false}">
    <div class="folder-panel-head"><div><span class="folder-eyebrow">Plan · create · reach · measure</span><h2 class="text-xl font-bold">Campaigns</h2><p class="notes-hint">Your strategies, budgets, brand assets and campaigns, connected to {{ $project->name }}.</p></div>@if($canAssignFolder)<button type="button" class="btn-primary px-4 py-2" @click="assignMarketing=!assignMarketing" :aria-expanded="assignMarketing"><i class="fas fa-plus" aria-hidden="true"></i> Assign resources</button>@endif</div>
    @if($canAssignFolder)
    <form method="post" action="{{ route('user.ai.planner.attach',$project) }}" x-show="assignMarketing" x-cloak class="notes-card folder-assignment-panel">@csrf
        <div class="folder-panel-head"><div><h2>Connect marketing resources</h2><p class="notes-hint">Choose several existing items. Moving an item changes its folder association.</p></div><button type="button" class="form-chip" @click="assignMarketing=false">Close</button></div>
        @include('user.planner.chooser',['choices'=>$marketingChoices,'fieldName'=>'items[]','selected'=>[],'label'=>'Marketing resources','limit'=>50])
        <button class="btn-primary px-4 py-2 mt-4">Assign selected resources</button>
    </form>
    @endif
    <div class="notes-metrics folder-marketing-metrics">
        <div class="notes-metric"><span>Strategies</span><strong>{{ $marketing->where('kind','strategy')->count() }}</strong><small>Connected campaign plans</small>@include('user.forms._stat-art',['art'=>'folder-strategy'])</div>
        <div class="notes-metric"><span>Buzz impressions</span><strong>{{ number_format($buzz->sum(fn($i)=>$i['row']->impressions)) }}</strong><small>Lifetime recorded impressions</small>@include('user.forms._stat-art',['art'=>'people'])</div>
        <div class="notes-metric"><span>Buzz conversions</span><strong>{{ number_format($buzz->sum(fn($i)=>$i['row']->conversions)) }}</strong><small>Lifetime recorded conversions</small>@include('user.forms._stat-art',['art'=>'check'])</div>
    </div>
    @if($marketing->isNotEmpty())
        <section class="notes-card">
            <div class="folder-panel-head"><div><h2>Campaign workspace</h2><p class="notes-hint">{{ $marketing->count() }} connected resources</p></div></div>
            <div class="folder-resource-toolbar"><label class="folder-search"><i class="fas fa-search" aria-hidden="true"></i><input x-model="marketingQuery" placeholder="Search marketing resources…" aria-label="Search marketing resources"></label>@include('user.planner.multi-filter',['model'=>'marketingKinds','options'=>$marketingOptions,'counts'=>$marketing->countBy('kind')->all(),'filterLabel'=>'Marketing types'])</div>
            <div class="folder-card-grid">
            @foreach($marketing as $item)
                <article class="folder-marketing-card" x-show="(!marketingKinds.length||marketingKinds.includes(@js($item['kind'])))&&@js(mb_strtolower($item['label'])).includes(marketingQuery.trim().toLowerCase())">
                    <div class="folder-panel-head"><span class="folder-mini-icon"><i class="fas {{ ['campaign'=>'fa-bullhorn','strategy'=>'fa-bullseye','budget'=>'fa-wallet','buzz'=>'fa-bullhorn','brand'=>'fa-palette','studio'=>'fa-wand-magic-sparkles'][$item['kind']] }}" aria-hidden="true"></i></span><span class="folder-resource-type">{{ $marketingOptions[$item['kind']] }}</span></div><h3>{{ $item['label'] }}</h3>
                    @if($item['kind']==='buzz')<p class="notes-hint">{{ $item['row']->is_active?'Active campaign':'Inactive campaign' }}</p><div class="folder-campaign-results"><span><strong>{{ number_format($item['row']->impressions) }}</strong>Impressions</span><span><strong>{{ number_format($item['row']->clicks) }}</strong>Clicks</span><span><strong>{{ number_format($item['row']->conversions) }}</strong>Conversions</span></div>
                    @elseif($item['kind']==='campaign')<p class="notes-hint">{{ count($item['row']->studio_ids ?? []) }} Studio material sets · {{ $item['row']->plan_id ? 'Planner connected' : 'No Planner connection' }}</p>
                    @elseif($item['kind']==='strategy')<p class="notes-hint">{{ $item['row']->goal }} · {{ ucfirst($item['row']->status) }}</p>
                    @else<p class="notes-hint">{{ $item['kind']==='budget'?'Review campaign costs and estimates.':'Keep your campaign’s identity and creative assets together.' }}</p>@endif
                    <a class="folder-marketing-open" href="{{ $item['url'] }}">Open resource <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                </article>
            @endforeach
            </div>
            <div class="folder-filter-empty" x-show="!@js($marketingSearch).some(item=>(!marketingKinds.length||marketingKinds.includes(item.kind))&&item.label.includes(marketingQuery.trim().toLowerCase()))"><strong>No matching marketing resources</strong><button class="form-chip" type="button" @click="marketingKinds=[];marketingQuery=''">Clear filters</button></div>
        </section>
    @else
        <section class="notes-card folder-campaign-empty"><span class="folder-campaign-orbit"><i class="fas fa-bullhorn" aria-hidden="true"></i></span><div><span class="folder-eyebrow">One connected campaign workspace</span><h2>Connect your campaign work</h2><p class="notes-hint">Bring strategies, budgets, brand kits and Buzz campaigns into this folder. Your recorded metrics will appear here as resources are assigned.</p><div class="folder-campaign-flow"><span>Strategy</span><i class="fas fa-arrow-right" aria-hidden="true"></i><span>Brand assets</span><i class="fas fa-arrow-right" aria-hidden="true"></i><span>Campaign</span><i class="fas fa-arrow-right" aria-hidden="true"></i><span>Results</span></div>@if($canAssignFolder)<button type="button" class="form-chip" @click="assignMarketing=true">Choose marketing resources</button>@endif</div></section>
    @endif
    <section class="notes-card"><div class="folder-panel-head"><div><h2>Start a marketing workflow</h2><p class="notes-hint">Choose your next step. Each tool keeps its usual permissions and AI estimate.</p></div></div><div class="folder-workflow-grid">
        <a href="{{ route('user.ai.mind.show',['project_id'=>$project->id,'purpose'=>'campaign']) }}"><span class="folder-mini-icon"><i class="fas fa-wand-magic-sparkles"></i></span><strong>Plan this campaign</strong><p>Turn your ideas into a plan you can review and connect to tasks.</p><span>Start planning →</span></a>
        <a href="{{ route('user.marketing-studio.create',['project_id'=>$project->id]) }}"><span class="folder-mini-icon"><i class="fas fa-bullseye"></i></span><strong>Marketing Studio</strong><p>Develop the audience, positioning and direction for your campaign.</p><span>Create campaign →</span></a>
        <a href="{{ route('user.marketing-plan.create',['project_id'=>$project->id]) }}"><span class="folder-mini-icon"><i class="fas fa-wallet"></i></span><strong>Budget & Forecast</strong><p>Estimate your campaign spend before committing to the next step.</p><span>Review budget →</span></a>
    </div></section>
</div>
