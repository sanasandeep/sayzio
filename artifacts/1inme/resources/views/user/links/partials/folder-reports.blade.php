<section class="ov-wrap">
<div class="ov-head"><div><h2>Performance</h2><p class="ov-muted">Reports use the same filters as your links. Bot traffic is excluded.</p></div>
<form method="GET" action="{{ route('user.links.index') }}">
@foreach(request()->except('page', 'days', 'view') as $key => $value)
@foreach((array) $value as $entry)
@if(is_scalar($entry))<input type="hidden" name="{{ is_array($value) ? $key . '[]' : $key }}" value="{{ $entry }}">@endif
@endforeach
@endforeach
<input type="hidden" name="view" value="reports"><label for="report-days">Period</label>
<select id="report-days" name="days" class="theme-input">
@foreach([7,30,90] as $days)<option value="{{ $days }}" @selected((int) request('days',7) === $days)>Last {{ $days }} days</option>@endforeach
</select><button class="btn-ghost" type="submit">Apply</button></form></div>
<p class="ov-muted mb-4"><a href="{{ route('user.links.index', array_merge(request()->except('page'), ['view' => 'reports', 'export' => 'daily'])) }}">Export daily totals CSV →</a></p>
<div class="ov-grid">
<div class="ov-panel ov-count"><span>Clicks in period</span><strong>{{ number_format($report['total']) }}</strong><p class="ov-muted">Last {{ $report['days'] }} days, within plan retention</p></div>
<div class="ov-panel ov-count" style="--ov-color:#0d9488"><span>Active links</span><strong>{{ number_format($summary['active']) }}</strong><p class="ov-muted">{{ $summary['total'] }} selected links</p></div>
<div class="ov-panel ov-count" style="--ov-color:#8b5cf6"><span>Lifetime clicks</span><strong>{{ number_format($summary['clicks']) }}</strong><p class="ov-muted">Saved lifetime counters for selected links</p></div>
</div>
@if($report['total'] === 0)
<div class="ov-panel ov-empty"><h2>No traffic recorded in this period</h2><p>Share your links or choose a longer period. Reports will appear as visits arrive.</p></div>
@else
<div class="ov-panel"><h2>Daily clicks</h2><p class="ov-muted">{{ $report['start']->format('M j, Y') }} to {{ now()->format('M j, Y') }}</p>
<div class="ov-bars" role="img" aria-label="Daily clicks. Expand daily totals for details.">
@foreach($report['series'] as $day => $count)<div class="ov-bar" title="{{ $day }}: {{ $count }} clicks" style="height: {{ max(1, round($count / max(1, max($report['series'])) * 100)) }}%"></div>@endforeach
</div><details><summary>View daily totals</summary><table class="w-full"><thead><tr><th>Date</th><th>Clicks</th></tr></thead><tbody>@foreach($report['series'] as $day => $count)<tr><td>{{ $day }}</td><td>{{ $count }}</td></tr>@endforeach</tbody></table></details></div>
<div class="ov-report-grid">
@foreach(['sources'=>['Traffic sources','referrer'], 'countries'=>['Countries','country_code'], 'devices'=>['Devices','device_type']] as $key => $meta)
<section class="ov-panel"><h2>{{ $meta[0] }}</h2>
@foreach($report[$key] as $row)<div class="ov-line ov-breakdown"><span>{{ $row->{$meta[1]} ?: ($key === 'sources' ? 'Direct / unknown' : 'Unknown') }}</span><strong>{{ number_format($row->count) }}</strong></div>@endforeach
</section>
@endforeach
</div>
<section class="ov-panel"><h2>Top links in this period</h2>
@foreach($report['topCounts'] as $id => $count)
@php
$topLink = $report['topLinks']->get($id);
@endphp
@if($topLink)<div class="ov-recent"><a href="{{ route('user.links.show', $topLink) }}">{{ $topLink->title ?: $topLink->alias }}</a><strong>{{ $count }} clicks</strong></div>@endif
@endforeach
</section>
@endif
</section>
