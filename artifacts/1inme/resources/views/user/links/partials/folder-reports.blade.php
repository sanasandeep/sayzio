{{--
    The combined project report.

    Same components as the per-link stats page, because it is the same
    question asked of more than one link: section cards, the shared
    click-heatmap, dimension donuts, chart-actions on every chart. The first
    version of this file drew its own bars and its own heatmap, which is how
    a codebase ends up with three of everything.
--}}
@php
    $r = $report;
    $q = request()->except(['page', 'export']);
    $exportUrl = fn (string $s) => route('user.links.index', array_merge($q, ['view' => 'reports', 'export' => $s]));
    $buildUrl = fn (array $params) => route('user.links.index', array_merge(
        array_filter(array_merge($q, $params), fn ($v) => $v !== null && $v !== ''),
        ['view' => 'reports'],
    ));
    $rangeUrl = fn (int $d) => route('user.links.index', array_merge($q, [
        'view' => 'reports',
        'from' => now($r['timezone'])->subDays($d - 1)->toDateString(),
        'to'   => now($r['timezone'])->toDateString(),
    ]));
    $days = (int) round($r['from']->diffInDays($r['to'])) + 1;
    $chartFooter = $r['from']->format('j M Y').' – '.$r['to']->format('j M Y').' · '.$r['timezone'];
    $scopeName = $folder?->name ?? 'All links';
@endphp


<script src="{{ asset('js/vendor/chart.umd.min.js') }}"></script>

{{-- ===================== FILTERS ===================== --}}
<form data-list-filters data-date-filters class="section-card mb-6" method="GET" action="{{ route('user.links.index') }}">
    @foreach(Arr::except($q, ['from', 'to', 'bots', 'view', 'group']) as $k => $v)
        @if(is_array($v))
            @foreach($v as $vv)<input type="hidden" name="{{ $k }}[]" value="{{ $vv }}">@endforeach
        @else
            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endif
    @endforeach
    <input type="hidden" name="view" value="reports">
    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label for="rp-from" class="block text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">From</label>
            <input id="rp-from" type="date" name="from" value="{{ $r['from']->toDateString() }}" class="px-3 py-2 rounded-lg text-sm" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
        </div>
        <div>
            <label for="rp-to" class="block text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">To</label>
            <input id="rp-to" type="date" name="to" value="{{ $r['to']->toDateString() }}" class="px-3 py-2 rounded-lg text-sm" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
        </div>
        <div>
            <label for="rp-bots" class="block text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">Traffic</label>
            <select id="rp-bots" name="bots" class="report-select" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                <option value="0" @selected(! $r['bots'])>People only</option>
                <option value="1" @selected($r['bots'])>Include bots</option>
            </select>
        </div>
        <div>
            <label for="rp-group" class="block text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">Group by</label>
            <select id="rp-group" name="group" class="report-select">
                @foreach(['day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'year' => 'Year'] as $value => $label)
                    <option value="{{ $value }}" @selected($r['groupBy'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-primary text-xs py-2 px-4">Apply</button>
        <div class="flex flex-wrap items-center gap-2">
            @foreach(\App\Modules\User\Support\LinkReport::PRESETS as $p)
                <a href="{{ $rangeUrl($p) }}" class="pill {{ $days === $p ? 'pill-active' : '' }}">{{ $p === 365 ? '1 year' : $p.'d' }}</a>
            @endforeach
        </div>
        <a class="pill" href="{{ $exportUrl('all') }}"><i class="fas fa-file-csv mr-1"></i>Export everything</a>
    </div>
</form>

{{-- ===================== HEADLINE ===================== --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 mb-7">
    @foreach([
        ['Clicks', number_format($r['totals']['clicks']), $days.' days'],
        ['Visitors', number_format($r['totals']['visitors']), 'Distinct addresses'],
        ['Sessions', number_format($r['sessions']['sessions']), 'avg '.$r['sessions']['avgSeconds'].'s on page'],
        ['Bounce rate', $r['sessions']['bounceRate'].'%', 'Under 5 seconds'],
        ['Links with traffic', number_format($r['totals']['links']), 'Of '.number_format(count($r['links'])).' in view'],
        ['Bot hits filtered', number_format($r['botTotal']), 'Excluded from totals'],
    ] as [$k, $v, $sub])
        @php
            $folderCardColors = ['#3b82f6','#8b5cf6','#0d9488','#f59e0b','#ec4899','#0891b2'];
            $folderCardTones = ['#123c85','#512b89','#075953','#804210','#831843','#164e63'];
            $folderCardKinds = ['clicks','visitors','sessions','sessions','links','bot'];
        @endphp
        <article class="folder-kpi" style="--ov-color:{{ $folderCardColors[$loop->index] }};--ov-tone:{{ $folderCardTones[$loop->index] }};">
            <h2 class="folder-kpi-label">{{ $k }}</h2>
            @include('user.partials.count-card-art',['kind'=>$folderCardKinds[$loop->index],'artId'=>'folder-stat-'.$loop->index])
            <p class="folder-kpi-value" @if(strlen($v) > 7) style="font-size:28px" @endif>{{ $v }}</p>
            <p class="folder-kpi-sub">{{ $sub }}</p>
        </article>
    @endforeach
</div>

@include('user.links.partials.ai-growth')

{{-- ===================== CLICKS OVER TIME ===================== --}}
<div class="section-card mb-7" style="--sc-accent: linear-gradient(90deg,#3d6bff,#6366f1);">
    <div class="section-head">
        <div class="section-title"><div class="section-icon"><i class="fas fa-chart-line"></i></div> Clicks Over Time <span class="text-xs font-normal" style="color: var(--text-muted);">({{ ucfirst($r['groupBy']) }})</span></div>
        <div class="flex items-center gap-2 flex-wrap">
            <span class="section-pill">{{ $chartFooter }}</span>
            @include('user.links.partials.chart-actions', ['target' => 'folderClicksChart', 'title' => 'Clicks Over Time — '.$scopeName, 'slug' => 'clicks-over-time', 'footer' => $chartFooter])
            <a class="pill" href="{{ $exportUrl('daily') }}">CSV</a>
        </div>
    </div>
    @if($r['totals']['clicks'] === 0 && $r['botTotal'] === 0)
        <p class="text-sm text-center py-10" style="color: var(--text-faint);">No clicks in this range.</p>
    @else
        <div style="height: 300px;">
            <canvas id="folderClicksChart"
                    data-series-chart
                    data-labels="{{ json_encode($r['series']->pluck('bucket')) }}"
                    data-clicks="{{ json_encode($r['series']->pluck('count')) }}"
                    data-unique="{{ json_encode($r['series']->pluck('unique_count')) }}"
                    data-bots="{{ json_encode($r['series']->pluck('bucket')->map(fn ($b) => (int) ($r['botSeries'][$b] ?? 0))) }}"></canvas>
        </div>
    @endif
</div>

{{-- ===================== WHEN PEOPLE CLICK ===================== --}}
<div class="section-card mb-7" style="--sc-accent: linear-gradient(90deg,#8b5cf6,#a78bfa);">
    <div class="section-head">
        <div class="section-title"><div class="section-icon"><i class="fas fa-clock"></i></div> When People Click <span class="text-xs font-normal" style="color: var(--text-muted);">(2-hour blocks)</span></div>
        <div class="flex items-center gap-2 flex-wrap">
            @include('user.links.partials.chart-actions', ['target' => 'folder-heat-grid', 'title' => 'When People Click — '.$scopeName, 'slug' => 'when-people-click', 'footer' => $chartFooter])
            <a class="pill" href="{{ $exportUrl('heatmap') }}">CSV</a>
        </div>
    </div>
    @include('user.partials.click-heatmap', [
        'hmId'        => 'folder-heat',
        'hmRows'      => $r['heat']['rows'],
        'hmPeak'      => $r['heat']['peak'],
        'hmPeakLabel' => $r['heat']['peakLabel'],
    ])
</div>

{{-- ===================== BROWSER / OS / DEVICE ===================== --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-7">
    @include('user.links.partials.dimension-card', [
        'rows' => $r['dimensions']['browser'], 'column' => 'browser', 'filterKey' => 'browser',
        'filterValue' => request('browser'), 'title' => 'Browsers', 'icon' => 'fa-globe',
        'slug' => 'browsers', 'canvasId' => 'folderBrowserChart', 'url' => $buildUrl,
        'accent' => ['#6366f1', '#818cf8'], 'footer' => $chartFooter,
    ])
    @include('user.links.partials.dimension-card', [
        'rows' => $r['dimensions']['os'], 'column' => 'os', 'filterKey' => 'os',
        'filterValue' => request('os'), 'title' => 'Operating Systems', 'icon' => 'fa-laptop',
        'slug' => 'operating-systems', 'canvasId' => 'folderOsChart', 'url' => $buildUrl,
        'accent' => ['#10b981', '#34d399'], 'glow' => 'rgba(16,185,129,0.35)', 'tint' => '#6ee7b7',
        'borderTint' => 'rgba(16,185,129,0.3)', 'footer' => $chartFooter,
    ])
    @include('user.links.partials.dimension-card', [
        'rows' => $r['dimensions']['device_type'], 'column' => 'device_type', 'filterKey' => 'device',
        'filterValue' => request('device'), 'title' => 'Devices', 'icon' => 'fa-mobile-alt',
        'slug' => 'devices', 'canvasId' => 'folderDeviceChart', 'url' => $buildUrl,
        'accent' => ['#f59e0b', '#fbbf24'], 'glow' => 'rgba(245,158,11,0.35)', 'tint' => '#fcd34d',
        'borderTint' => 'rgba(245,158,11,0.3)', 'footer' => $chartFooter,
    ])
</div>

{{-- ===================== SOURCE / CHANNEL ===================== --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-7">
    @include('user.links.partials.dimension-card', [
        'rows' => $r['dimensions']['source'], 'column' => 'source', 'filterKey' => 'source',
        'filterValue' => request('source'), 'title' => 'Traffic Source', 'icon' => 'fa-mobile-screen',
        'slug' => 'traffic-source', 'canvasId' => 'folderSourceChart', 'url' => $buildUrl,
        'accent' => ['#06b6d4', '#22d3ee'], 'glow' => 'rgba(6,182,212,0.35)', 'tint' => '#67e8f9',
        'borderTint' => 'rgba(6,182,212,0.3)', 'footer' => $chartFooter,
        'labels' => ['mobile_app' => 'Mobile app', 'web' => 'Web', 'unknown' => 'Unknown'],
    ])
    @include('user.links.partials.dimension-card', [
        'rows' => $r['dimensions']['channel'], 'column' => 'channel', 'filterKey' => 'channel',
        'filterValue' => request('channel'), 'title' => 'Channel', 'icon' => 'fa-layer-group',
        'slug' => 'channel', 'canvasId' => 'folderChannelChart', 'url' => $buildUrl,
        'accent' => ['#ec4899', '#f472b6'], 'glow' => 'rgba(236,72,153,0.35)', 'tint' => '#f9a8d4',
        'borderTint' => 'rgba(236,72,153,0.3)', 'footer' => $chartFooter,
    ])
</div>

{{-- ===================== PLACES / REFERRERS / CAMPAIGNS ===================== --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-7">
    @foreach([
        ['countries', 'Top Countries', 'fa-flag', 'country_code'],
        ['cities', 'Top Cities', 'fa-city', 'city'],
        ['referrers', 'Top Referrers', 'fa-link', 'referrer'],
        ['languages', 'Languages', 'fa-language', 'language'],
    ] as [$key, $label, $icon, $column])
        @php $rows = $r['dimensions'][$column]; @endphp
        <div class="section-card">
            <div class="section-head">
                <div class="section-title"><div class="section-icon"><i class="fas {{ $icon }}"></i></div> {{ $label }}</div>
                <a class="pill" href="{{ $exportUrl($key) }}">CSV</a>
            </div>
            @if($rows->isEmpty())
                <p class="text-sm text-center py-8" style="color: var(--text-faint);">No data</p>
            @else
                @php $max = max(1, (int) $rows->max('count')); @endphp
                @foreach($rows->take(10) as $row)
                    <div class="py-2" style="border-bottom: 1px solid var(--border-glass);">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span style="color: var(--text-primary); overflow-wrap: anywhere;">{{ $row->{$column} }}</span>
                            <span class="text-xs whitespace-nowrap" style="color: var(--text-muted); font-variant-numeric: tabular-nums;">{{ number_format($row->count) }}</span>
                        </div>
                        <div style="height:4px;border-radius:4px;background:var(--bg-glass-input);margin-top:6px;overflow:hidden;">
                            <span style="display:block;height:100%;width:{{ max(2, round($row->count / $max * 100)) }}%;background:var(--accent,#6366f1);border-radius:4px;"></span>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    @endforeach
</div>

{{-- ===================== EVERY LINK ===================== --}}
<div class="section-card mb-7">
    <div class="section-head">
        <div class="section-title"><div class="section-icon"><i class="fas fa-list"></i></div> Every link in this view</div>
        <a class="pill" href="{{ $exportUrl('links') }}">CSV</a>
    </div>
    <p class="text-xs mb-3" style="color: var(--text-muted);">Links with no clicks in the range are listed too &mdash; that is half the question.</p>
    @if(count($r['links']) === 0)
        <p class="text-sm text-center py-8" style="color: var(--text-faint);">No links match these filters.</p>
    @else
        <div style="overflow-x:auto;">
        <table class="w-full text-sm" style="border-collapse: collapse;">
            <thead>
                <tr>
                    @foreach(['Link', 'Type', 'Status', 'Clicks', 'Visitors', 'Last click'] as $i => $h)
                        <th class="text-[10px] uppercase tracking-wider pb-2 pr-3 {{ $i >= 3 ? 'text-right' : 'text-left' }}" style="color: var(--text-faint);">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($r['links'] as $row)
                    <tr style="border-top: 1px solid var(--border-glass);">
                        <td class="py-2.5 pr-3">
                            <a href="{{ route('user.links.show', $row['link']->id) }}" class="font-semibold" style="color: var(--text-primary);">{{ $row['link']->title ?: $row['link']->alias }}</a>
                            <div class="text-xs" style="color: var(--text-muted); overflow-wrap: anywhere;">{{ $row['link']->alias }}</div>
                        </td>
                        <td class="py-2.5 pr-3 text-xs" style="color: var(--text-muted);">{{ Str::headline($row['link']->type ?? '') }}</td>
                        <td class="py-2.5 pr-3 text-xs" style="color: var(--text-muted);">{{ $row['link']->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="py-2.5 pr-3 text-right" style="font-variant-numeric: tabular-nums;">{{ number_format($row['clicks']) }}</td>
                        <td class="py-2.5 pr-3 text-right" style="font-variant-numeric: tabular-nums;">{{ number_format($row['visitors']) }}</td>
                        <td class="py-2.5 text-right text-xs" style="color: var(--text-muted); white-space: nowrap;">{{ $row['lastClick'] ? \App\Support\PlatformTimezone::format($row['lastClick'], 'j M, g:ia', false, $r['timezone']) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
</div>

@include('user.links.partials.dimension-charts')

<script>
(() => {
    const el = document.getElementById('folderClicksChart');
    if (!el || !window.Chart) return;
    const read = (k) => { try { return JSON.parse(el.dataset[k] || '[]'); } catch (e) { return []; } };
    const light = document.documentElement.classList.contains('light-mode');
    const tick = light ? 'rgba(15,23,42,0.75)' : 'rgba(255,255,255,0.7)';
    const grid = light ? 'rgba(0,0,0,0.06)' : 'rgba(255,255,255,0.05)';
    const ctx = el.getContext('2d');
    const g1 = ctx.createLinearGradient(0,0,0,300);
    g1.addColorStop(0, 'rgba(61,107,255,0.45)'); g1.addColorStop(1, 'rgba(61,107,255,0.02)');
    const g2 = ctx.createLinearGradient(0,0,0,300);
    g2.addColorStop(0, 'rgba(16,185,129,0.30)'); g2.addColorStop(1, 'rgba(16,185,129,0.02)');
    new Chart(el, {
        type: 'line',
        data: {
            labels: read('labels'),
            datasets: [
                { label: 'Total Clicks', data: read('clicks'), borderColor: '#5c83ff', backgroundColor: g1, fill: true, tension: 0.4, borderWidth: 2.5, pointRadius: 0 },
                { label: 'Unique IPs', data: read('unique'), borderColor: '#34d399', backgroundColor: g2, fill: true, tension: 0.4, borderWidth: 2, pointRadius: 0 },
                { label: 'Filtered bots (excluded from totals)', data: read('bots'), borderColor: '#f97316', borderDash: [5, 4], fill: false, tension: 0.4, borderWidth: 2, pointRadius: 0 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: { legend: { labels: { color: tick, usePointStyle: true, padding: 16, font: { size: 11, weight: '600' } } } },
            scales: {
                x: { grid: { color: grid, drawTicks: false }, ticks: { color: tick, font: { size: 10 } }, border: { display: false } },
                y: { grid: { color: grid, drawTicks: false }, ticks: { color: tick, font: { size: 10 } }, beginAtZero: true, border: { display: false } }
            }
        }
    });
})();
</script>

@include('user.links.partials.chart-export')
