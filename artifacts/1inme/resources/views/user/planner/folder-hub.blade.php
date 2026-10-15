@php
$hubGroups=[
 ['Links & pages',['link'],'links','fa-link','Manage pages and their lifetime engagement.'],
 ['Campaigns',['campaign','strategy','studio','buzz'],'marketing','fa-bullhorn','Studio campaigns, strategies and created materials.'],
 ['Budget & Forecast',['budget'],'budget','fa-wallet','Saved forecasts and their cost assumptions.'],
 ['Brand Kits',['brand'],'brands','fa-palette','Reusable identities assigned to this project.'],
 ['Planner plans',['plan'],'plans','fa-wand-magic-sparkles','Plans connected to execution.'],
 ['Tasks',['task'],'tasks','fa-check-double','Assignments, progress and deadlines.'],
 ['AI Minds & team',['mind','persona','agent','staff','companion'],'team','fa-brain','Knowledge, personas, Agents, AI Staff and Channels.'],
 ['Files & resources',['form','qr','file','calendar','board'],'resources','fa-layer-group','Files, references, forms, QR codes and calendars.'],
];
@endphp
<section class="notes-card"><div class="folder-panel-head"><div><h2>Your project workspace</h2><p class="notes-hint">Open a category to work with its assigned items. Counts include only resources you can access.</p></div></div>
<div class="folder-card-grid">@foreach($hubGroups as [$label,$kinds,$section,$icon,$description])<a class="folder-resource-row" href="{{ route('user.projects.show',[$project,'tab'=>$section]) }}"><span class="folder-mini-icon"><i class="fas {{ $icon }}" aria-hidden="true"></i></span><span class="folder-mini-copy"><strong>{{ $label }} · {{ collect($resources)->whereIn('kind',$kinds)->count() }}</strong><small>{{ $description }}</small></span><span aria-hidden="true">→</span></a>@endforeach</div>
<p class="notes-hint mt-4">Suggested workflow: Budget & Forecast → Planner → Studio campaign → Materials → Results. Open Resources to save specific item connections.</p>
</section>
