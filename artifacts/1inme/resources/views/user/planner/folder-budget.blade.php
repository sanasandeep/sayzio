<section class="notes-card"><div class="folder-panel-head"><div><h2>Budget & Forecast</h2><p class="notes-hint">Each forecast keeps its own period, currency and assumptions. Actuals are workspace proxies, not measured campaign attribution.</p></div>@if(auth()->user()->canInWorkspace(app('current_workspace'),'ai.view'))<a class="form-chip" href="{{ route('user.marketing-plan.create',['project_id'=>$project->id]) }}">Create forecast →</a>@endif</div>
@forelse(collect($resources)->where('kind','budget') as $item)

@php $projection=app(\App\Services\BudgetForecast::class)->calculate($item['row']->payload ?? []); @endphp
<a class="folder-resource-row" href="{{ $item['url'] }}"><span class="folder-mini-icon"><i class="fas fa-wallet" aria-hidden="true"></i></span><span class="folder-mini-copy"><strong>{{ $item['label'] }}</strong><small>Modelled cost: {{ number_format($projection['totals']['spend'],2) }} INR · {{ $projection['period'] }} months · forecast assumptions. Open for recorded actuals and reports.</small></span><span>Open →</span></a>
@empty<div class="folder-empty"><p>No forecasts connected yet. Create one here or assign an existing forecast under Resources.</p><a class="form-chip" href="{{ route('user.projects.show',[$project,'tab'=>'resources']) }}#assign-resources">Assign existing</a></div>@endforelse
</section>
