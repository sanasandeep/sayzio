{{-- The combined report for whatever the filters resolve to: the whole
     account when no folder is chosen, one project when it is. Server
     rendered with no JavaScript, so it works under the inline-script guard
     and prints correctly. --}}
@php
    $r = $report;
    $q = request()->except(['page', 'export']);
    $exportUrl = fn (string $section) => route('user.links.index', array_merge($q, ['view' => 'reports', 'export' => $section]));
    $rangeUrl = fn (int $days) => route('user.links.index', array_merge($q, [
        'view' => 'reports',
        'from' => now($r['timezone'])->subDays($days - 1)->toDateString(),
        'to'   => now($r['timezone'])->toDateString(),
    ]));
    $activeDays = (int) round($r['from']->diffInDays($r['to'])) + 1;
    $peak = $r['heatmap']['peak'];
@endphp

<style>
.rp-filters{display:flex;flex-wrap:wrap;align-items:flex-end;gap:12px;margin-bottom:22px}
.rp-filters label{display:block;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:var(--text-faint);font-weight:700;margin-bottom:5px}
.rp-filters input[type=date]{padding:9px 12px;border-radius:10px;border:1px solid var(--border-glass);background:var(--bg-glass-input);color:var(--text-primary);font:inherit}
.rp-presets{display:flex;gap:6px;flex-wrap:wrap}
.rp-presets a{padding:9px 14px;border-radius:10px;border:1px solid var(--border-glass);color:var(--text-primary);text-decoration:none;font-size:13px}
.rp-presets a[aria-current=true]{background:var(--accent);color:#fff;border-color:transparent}
.rp-head{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin-bottom:24px}
.rp-stat{background:var(--bg-card);border:1px solid var(--border-glass);border-radius:16px;padding:18px}
.rp-stat .k{margin:0;font-size:10px;letter-spacing:.14em;text-transform:uppercase;font-weight:700;color:var(--text-faint)}
.rp-stat .v{margin:8px 0 0;font-size:27px;font-weight:750;letter-spacing:-.035em;font-variant-numeric:tabular-nums;color:var(--text-primary)}
.rp-stat .s{margin:4px 0 0;font-size:12px;color:var(--text-muted)}
.rp-section{background:var(--bg-card);border:1px solid var(--border-glass);border-radius:18px;padding:22px;margin-bottom:20px}
.rp-section h3{font-size:15px;font-weight:700;margin:0}
.rp-line{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap}
.rp-export{font-size:12px;color:var(--text-muted);text-decoration:none;border:1px solid var(--border-glass);border-radius:8px;padding:5px 10px;white-space:nowrap}
.rp-export:hover{color:var(--text-primary)}
.rp-heat{overflow-x:auto}
.rp-heat table{border-collapse:separate;border-spacing:3px;margin-top:14px}
.rp-heat th{font-size:10px;font-weight:600;color:var(--text-faint);padding:0 2px;font-variant-numeric:tabular-nums}
.rp-heat th[scope=row]{text-align:right;padding-right:6px}
.rp-heat td{width:17px;height:17px;border-radius:4px;background:color-mix(in srgb,var(--accent,#6366f1) calc(var(--i,0) * 1%),transparent);outline:1px solid var(--border-glass);outline-offset:-1px}
.rp-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px}
.rp-rows{margin-top:12px}
.rp-row{padding:9px 0;border-bottom:1px solid var(--border-glass);font-size:13px}
.rp-row:last-child{border:0}
.rp-bar{height:5px;border-radius:6px;background:var(--bg-glass-input);margin-top:6px;overflow:hidden}
.rp-bar span{display:block;height:100%;background:var(--accent,#6366f1);border-radius:6px}
.rp-table{width:100%;border-collapse:collapse;margin-top:14px;font-size:13px}
.rp-table th{text-align:left;font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:var(--text-faint);padding:0 10px 10px 0}
.rp-table td{padding:11px 10px 11px 0;border-top:1px solid var(--border-glass);vertical-align:top}
.rp-table td.n{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
.rp-muted{color:var(--text-muted);font-size:12px}
.rp-empty{padding:30px 10px;text-align:center;color:var(--text-muted)}
@media(max-width:1000px){.rp-head{grid-template-columns:repeat(2,minmax(0,1fr))}.rp-grid{grid-template-columns:1fr}}
@media(max-width:560px){.rp-head{grid-template-columns:1fr}}
</style>

<form class="rp-filters" method="GET" action="{{ route('user.links.index') }}">
    @foreach(Arr::except($q, ['from', 'to', 'bots', 'view']) as $k => $v)
        @if(is_array($v))
            @foreach($v as $vv)<input type="hidden" name="{{ $k }}[]" value="{{ $vv }}">@endforeach
        @else
            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endif
    @endforeach
    <input type="hidden" name="view" value="reports">
    <div>
        <label for="rp-from">From</label>
        <input id="rp-from" type="date" name="from" value="{{ $r['from']->toDateString() }}">
    </div>
    <div>
        <label for="rp-to">To</label>
        <input id="rp-to" type="date" name="to" value="{{ $r['to']->toDateString() }}">
    </div>
    <div>
        <label for="rp-bots">Traffic</label>
        <select id="rp-bots" name="bots" style="padding:9px 12px;border-radius:10px;border:1px solid var(--border-glass);background:var(--bg-glass-input);color:var(--text-primary);font:inherit">
            <option value="0" @selected(! $r['bots'])>People only</option>
            <option value="1" @selected($r['bots'])>Include bots</option>
        </select>
    </div>
    <button type="submit" class="btn-primary text-xs py-2">Apply</button>
    <div class="rp-presets">
        @foreach(\App\Modules\User\Support\LinkReport::PRESETS as $preset)
            <a href="{{ $rangeUrl($preset) }}" @if($activeDays === $preset) aria-current="true" @endif>{{ $preset === 365 ? '1 year' : $preset.' days' }}</a>
        @endforeach
    </div>
    <a class="rp-export" href="{{ $exportUrl('all') }}">Export everything (CSV)</a>
</form>

<div class="rp-head">
    <div class="rp-stat"><p class="k">Clicks</p><p class="v">{{ number_format($r['totals']['clicks']) }}</p><p class="s">{{ $activeDays }} {{ Str::plural('day', $activeDays) }}</p></div>
    <div class="rp-stat"><p class="k">Visitors</p><p class="v">{{ number_format($r['totals']['visitors']) }}</p><p class="s">Distinct addresses</p></div>
    <div class="rp-stat"><p class="k">Links with traffic</p><p class="v">{{ number_format($r['totals']['links']) }}</p><p class="s">Of {{ number_format(count($r['links'])) }} in view</p></div>
    <div class="rp-stat"><p class="k">Average per day</p><p class="v">{{ number_format($r['totals']['perDay'], 1) }}</p><p class="s">Across the range</p></div>
    <div class="rp-stat">
        <p class="k">Busiest day</p>
        <p class="v" style="font-size:19px">{{ $r['totals']['busiest'] ? \Illuminate\Support\Carbon::parse($r['totals']['busiest'])->format('j M') : '—' }}</p>
        <p class="s">{{ $r['totals']['busiest'] ? number_format($r['daily'][$r['totals']['busiest']]).' clicks' : 'No clicks yet' }}</p>
    </div>
</div>

<section class="rp-section">
    <div class="rp-line">
        <h3>Clicks per day</h3>
        <a class="rp-export" href="{{ $exportUrl('daily') }}">CSV</a>
    </div>
    @php $dailyMax = max(1, max($r['daily'] ?: [0])); @endphp
    @if($r['totals']['clicks'] > 0)
        <div class="ov-bars" role="img" aria-label="Clicks per day from {{ $r['from']->toDateString() }} to {{ $r['to']->toDateString() }}. Busiest day {{ number_format($dailyMax) }} clicks.">
            @foreach($r['daily'] as $day => $count)
                <div class="ov-bar" style="height: {{ $count > 0 ? max(3, round($count / $dailyMax * 100)) : 1 }}%" title="{{ $day }}: {{ number_format($count) }}"></div>
            @endforeach
        </div>
        <p class="rp-muted">{{ $r['from']->format('j M Y') }} &ndash; {{ $r['to']->format('j M Y') }} &middot; times shown in {{ $r['timezone'] }}</p>
    @else
        <p class="rp-empty">No clicks in this range.</p>
    @endif
</section>

<section class="rp-section">
    <div class="rp-line">
        <h3>When people click</h3>
        <a class="rp-export" href="{{ $exportUrl('heatmap') }}">CSV</a>
    </div>
    <p class="rp-muted">Weekday and hour of day, in {{ $r['timezone'] }}.
        @if($peak)Busiest is {{ \App\Modules\User\Support\LinkReport::DAYS[$peak['day']] }} at {{ \Illuminate\Support\Carbon::createFromTime($peak['hour'])->format('ga') }}.@endif
    </p>
    @if($r['heatmap']['max'] > 0)
    <div class="rp-heat">
        <table>
            <caption class="sr-only">Clicks by weekday and hour</caption>
            <thead>
                <tr><th></th>@for($h = 0; $h < 24; $h++)<th scope="col">{{ $h % 3 === 0 ? $h : '' }}</th>@endfor</tr>
            </thead>
            <tbody>
                @foreach($r['heatmap']['grid'] as $day => $hours)
                <tr>
                    <th scope="row">{{ \App\Modules\User\Support\LinkReport::DAYS[$day] }}</th>
                    @foreach($hours as $hour => $count)
                        <td style="--i: {{ $count > 0 ? max(8, round($count / $r['heatmap']['max'] * 100)) : 0 }}"
                            title="{{ \App\Modules\User\Support\LinkReport::DAYS[$day] }} {{ $hour }}:00 — {{ number_format($count) }} {{ Str::plural('click', $count) }}"></td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
        <p class="rp-empty">No clicks in this range.</p>
    @endif
</section>

<div class="rp-grid">
    @foreach($r['breakdowns'] as $key => $breakdown)
    <section class="rp-section">
        <div class="rp-line">
            <h3>{{ $breakdown['label'] }}</h3>
            <a class="rp-export" href="{{ $exportUrl($key) }}">CSV</a>
        </div>
        @if($breakdown['rows']->isEmpty())
            <p class="rp-empty">Nothing yet.</p>
        @else
        <div class="rp-rows">
            @foreach($breakdown['rows'] as $row)
            <div class="rp-row">
                <div class="rp-line"><span>{{ $row['label'] }}</span><span class="rp-muted">{{ number_format($row['clicks']) }} &middot; {{ $row['share'] }}%</span></div>
                <div class="rp-bar"><span style="width: {{ max(2, $row['share']) }}%"></span></div>
            </div>
            @endforeach
        </div>
        @endif
    </section>
    @endforeach

    <section class="rp-section">
        <div class="rp-line">
            <h3>UTM campaigns</h3>
            <a class="rp-export" href="{{ $exportUrl('campaigns') }}">CSV</a>
        </div>
        @if($r['campaigns']->isEmpty())
            <p class="rp-empty">No tagged campaigns in this range.</p>
        @else
        <div class="rp-rows">
            @foreach($r['campaigns'] as $row)
            <div class="rp-row">
                <div class="rp-line"><span>{{ $row['label'] }}</span><span class="rp-muted">{{ number_format($row['clicks']) }} &middot; {{ $row['share'] }}%</span></div>
                <div class="rp-bar"><span style="width: {{ max(2, $row['share']) }}%"></span></div>
            </div>
            @endforeach
        </div>
        @endif
    </section>
</div>

<section class="rp-section">
    <div class="rp-line">
        <h3>Every link in this view</h3>
        <a class="rp-export" href="{{ $exportUrl('links') }}">CSV</a>
    </div>
    <p class="rp-muted">Links with no clicks in the range are listed too &mdash; that is half the question.</p>
    @if(count($r['links']) === 0)
        <p class="rp-empty">No links match these filters.</p>
    @else
    <table class="rp-table">
        <thead>
            <tr>
                <th>Link</th><th>Type</th><th>Status</th>
                <th class="n" style="text-align:right">Clicks</th>
                <th class="n" style="text-align:right">Visitors</th>
                <th class="n" style="text-align:right">Last click</th>
            </tr>
        </thead>
        <tbody>
            @foreach($r['links'] as $row)
            <tr>
                <td>
                    <a href="{{ route('user.links.edit', $row['link']->id) }}" style="color:var(--text-primary);font-weight:600">{{ $row['link']->title ?: $row['link']->alias }}</a>
                    <div class="rp-muted">{{ $row['link']->alias }}</div>
                </td>
                <td class="rp-muted">{{ Str::headline($row['link']->type ?? '') }}</td>
                <td class="rp-muted">{{ $row['link']->is_active ? 'Active' : 'Inactive' }}</td>
                <td class="n">{{ number_format($row['clicks']) }}</td>
                <td class="n">{{ number_format($row['visitors']) }}</td>
                <td class="n rp-muted">{{ $row['lastClick'] ? \App\Support\PlatformTimezone::format($row['lastClick'], 'j M, g:ia', false, $r['timezone']) : '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</section>
