@php
$reportItems=collect($folderData['resources']??[]);$reportTasks=$reportItems->where('kind','task');$reportRuns=$folderData['runs']??collect();$reportBuzz=$reportItems->where('kind','buzz');$performance=$folderData['peopleData']??['people'=>[],'unassigned'=>0,'unscheduled'=>0,'logged_minutes'=>0,'actions_30d'=>0];$reportNotes=$folderData['notes']??collect();
@endphp
<section class="folder-reports-premium mb-6" aria-label="Project health report">
<div class="folder-panel-head"><div><span class="folder-eyebrow">Folder performance</span><h2>Project health</h2><p class="notes-hint">Current project state and lifetime recorded activity. The link charts below use your selected date range.</p></div><span class="folder-section-badge"><i class="fas fa-chart-line" aria-hidden="true"></i>Folder report</span></div>
<div class="folder-health-stats">
@foreach([
 ['Task progress',$reportTasks->filter(fn($i)=>$i['row']->completed_at)->count().' / '.$reportTasks->count(),'Completed / assigned tasks · current','pages'],
 ['Needs attention',count($folderData['attention']??[]),'Open deadlines, failed reviews, inactive links','visitors'],
 ['Connected resources',$reportItems->count(),'Visible assigned items · current','folders'],
 ['AI review coins',number_format($reportRuns->sum('coins_spent')),'Most recent 50 visible runs','coins']
] as [$label,$value,$detail,$art])
<article class="folder-health-stat"><span>{{ $label }}</span>@include('user.partials.count-card-art',['kind'=>$art,'artId'=>'health-'.$loop->index])<strong>{{ $value }}</strong><small>{{ $detail }}</small></article>
@endforeach
</div>
<div class="folder-health-details">
@foreach([
 ['Work & delivery','fa-check-double',[
 ['Overdue tasks',$reportTasks->filter(fn($i)=>!$i['row']->completed_at && $i['row']->due_date?->isBefore(today()))->count(),'Current open deadlines'],
 ['Unassigned tasks',$performance['unassigned'],'Visible tasks without assignees'],
 ['Unscheduled work',$performance['unscheduled'],'Open tasks without due dates'],
 ['Time logged',round($performance['logged_minutes']/60,1).' h','Recorded time on visible tasks'],
 ['Recorded task actions',$performance['actions_30d'],'Last 30 days']]],
 ['Audience & responses','fa-chart-line',[
 ['Buzz impressions',number_format($reportBuzz->sum(fn($i)=>$i['row']->impressions)),'Lifetime recorded'],
 ['Buzz conversions',number_format($reportBuzz->sum(fn($i)=>$i['row']->conversions)),'Lifetime recorded'],
 ['Form submissions',number_format($reportItems->where('kind','form')->sum(fn($i)=>$i['row']->submissions()->count())),'Lifetime received responses']]],
 ['Campaigns & context','fa-layer-group',[
 ['Studio campaigns',$reportItems->where('kind','campaign')->count(),'Assigned to this folder'],
 ['Budget & Forecast',$reportItems->where('kind','budget')->count(),'Open each forecast for spend and actuals'],
 ['Brand Kits',$reportItems->where('kind','brand')->count(),'Assigned reusable identities'],
 ['AI team',$reportItems->whereIn('kind',['mind','persona','staff','agent','companion'])->count(),'Assigned resources'],
 ['People with access',count($performance['people']),'Active members + owner'],
 ['Pinned notes',$reportNotes->where('pinned',true)->whereNull('completed_at')->count(),'Visible active context']]]
] as [$title,$icon,$metrics])
<section class="notes-card folder-health-detail"><h3><i class="fas {{ $icon }}" aria-hidden="true"></i>{{ $title }}</h3>@foreach($metrics as [$label,$value,$detail])<div class="folder-health-row"><span><strong>{{ $label }}</strong><small>{{ $detail }}</small></span><b>{{ $value }}</b></div>@endforeach</section>
@endforeach
</div>
<div class="folder-health-insights">
<section class="notes-card"><h3>Resource mix</h3><p class="notes-hint">Visible items assigned to this folder.</p>@forelse($reportItems->groupBy('kind') as $kind=>$group)<div class="folder-report-bar"><span>{{ \App\Services\Planner\PlannerResources::typeLabels()[$kind]??ucfirst($kind) }}</span><span class="folder-progress"><span style="width:{{ $reportItems->count()?round($group->count()/$reportItems->count()*100):0 }}%"></span></span><strong>{{ $group->count() }}</strong></div>@empty<p class="folder-report-empty">Assign resources to see their distribution here.</p>@endforelse</section>
<section class="notes-card"><h3>AI review outcomes</h3><p class="notes-hint">Most recent 50 visible review runs.</p>@forelse($reportRuns->groupBy('status') as $status=>$group)<div class="folder-connection-row"><span>{{ ucfirst(str_replace('_',' ',$status)) }}</span><strong>{{ $group->count() }}</strong></div>@empty<div class="folder-report-empty"><i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i><p>No recorded reviews yet. Outcomes and coin costs appear after a review runs.</p></div>@endforelse</section>
</div>
<p class="notes-hint folder-report-disclosure">Campaign spend/revenue and task-to-conversion attribution are not inferred from these counts. Open an assigned Budget & Forecast or form for its native reports.</p>
</section>

<section class="notes-card mb-6"><div class="folder-panel-head"><div><span class="folder-eyebrow">People &amp; delivery</span><h2>Member performance</h2><p class="notes-hint">Assignments and completion are current. Actions cover 30 days; logged time covers visible tasks. Shared tasks count for each assignee.</p></div></div><div class="overflow-x-auto"><table class="folder-performance-table"><thead><tr><th>Person</th><th>Assigned</th><th>Complete</th><th>Overdue</th><th>On time</th><th>Logged</th><th>Actions · 30d</th></tr></thead><tbody>@foreach($performance['people'] as $person)<tr><th><span class="flex items-center gap-2"><img src="{{ $person['avatar_url'] }}" alt="" width="32" height="32" loading="lazy" style="width:32px;height:32px;object-fit:cover;border-radius:50%" onerror="this.hidden=true">{{ $person['name'] }}</span><small>{{ $person['role'] }}</small></th><td>{{ $person['assigned'] }}</td><td>{{ $person['completed'] }}@if($person['assigned'])<small>{{ round($person['completed']/$person['assigned']*100) }}%</small>@endif</td><td>{{ $person['overdue'] }}</td><td>{{ $person['dated_completed'] ? $person['on_time'].' / '.$person['dated_completed'] : '—' }}<small>Completed with due date</small></td><td>{{ round($person['minutes']/60,1) }} h</td><td>{{ $person['actions'] }}</td></tr>@endforeach</tbody></table></div></section>
