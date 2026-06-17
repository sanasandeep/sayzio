@extends('user.layouts.app')

@section('title', 'Stats')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <div class="flex flex-wrap items-end justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900">Stats home</h1>
            <p class="text-sm text-slate-500">{{ $start->format('M j, Y') }} – {{ $end->format('M j, Y') }} · {{ $rangeLabel }}</p>
        </div>
        <form method="GET" class="flex gap-2">
            <select name="range" onchange="this.form.submit()" class="px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm">
                @foreach($ranges as $key => $r)
                    <option value="{{ $key }}" {{ $range === $key ? 'selected' : '' }}>{{ $r['label'] }}</option>
                @endforeach
            </select>
            <a href="{{ route('user.stats.export', ['range' => $range]) }}" class="px-3 py-2 rounded-lg bg-slate-900 text-white text-sm font-semibold hover:bg-slate-800">
                <i class="fas fa-download mr-1"></i> CSV
            </a>
        </form>
    </div>

    {{-- KPI tiles --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        @php
            $tiles = [
                ['label' => 'Followers',         'value' => number_format($kpis['followers_total']),    'delta' => '+' . number_format($kpis['followers_new']),     'icon' => 'fa-user-plus',    'tint' => 'violet'],
                ['label' => 'Posts published',   'value' => number_format($kpis['posts_published']),    'delta' => $rangeLabel,                                     'icon' => 'fa-file-lines',   'tint' => 'sky'],
                ['label' => 'Engagement',        'value' => number_format($kpis['reactions'] + $kpis['comments']), 'delta' => $kpis['reactions'] . ' reactions · ' . $kpis['comments'] . ' comments', 'icon' => 'fa-heart', 'tint' => 'rose'],
                ['label' => 'Subscribers',       'value' => number_format($kpis['subscribers_active']), 'delta' => '+' . $kpis['subscribers_new'] . ' new',         'icon' => 'fa-crown',        'tint' => 'amber'],
            ];
            $tints = [
                'violet' => 'bg-violet-50 text-violet-700',
                'sky'    => 'bg-sky-50 text-sky-700',
                'rose'   => 'bg-rose-50 text-rose-700',
                'amber'  => 'bg-amber-50 text-amber-700',
            ];
        @endphp
        @foreach($tiles as $t)
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center {{ $tints[$t['tint']] }}"><i class="fas {{ $t['icon'] }}"></i></span>
                    <span class="text-[11px] uppercase tracking-wider text-slate-500">{{ $t['label'] }}</span>
                </div>
                <div class="mt-3 text-2xl font-extrabold text-slate-900">{{ $t['value'] }}</div>
                <div class="text-xs text-slate-500">{{ $t['delta'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Earnings strip --}}
    <div class="bg-gradient-to-br from-violet-600 to-fuchsia-600 text-white rounded-xl p-5 mb-6 flex items-center justify-between">
        <div>
            <div class="text-[11px] uppercase tracking-wider opacity-80">Unlock revenue · {{ $rangeLabel }}</div>
            <div class="text-3xl font-extrabold mt-1">${{ number_format($kpis['unlock_revenue_cents']/100, 2) }}</div>
        </div>
        <a href="{{ route('user.monetization.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white/15 hover:bg-white/25 text-sm font-semibold">
            Open Monetization <i class="fas fa-arrow-right"></i>
        </a>
    </div>

    {{-- Daily series chart (Chart.js via CDN). --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5 mb-6">
        <h2 class="text-sm font-bold text-slate-900 mb-3">Daily activity</h2>
        <canvas id="statsChart" height="80"></canvas>
    </div>

    {{-- Top posts --}}
    <div class="bg-white rounded-xl border border-slate-200 p-5">
        <h2 class="text-sm font-bold text-slate-900 mb-3">Top posts in this range</h2>
        @if($topPosts->count() === 0)
            <p class="text-sm text-slate-500">No posts published in this range yet.</p>
        @else
            <table class="w-full text-sm">
                <thead><tr class="text-left text-[11px] uppercase tracking-wider text-slate-500">
                    <th class="py-2">Post</th><th>Reactions</th><th>Comments</th><th>Published</th>
                </tr></thead>
                <tbody>
                @foreach($topPosts as $p)
                    <tr class="border-t border-slate-100">
                        <td class="py-2 pr-4 font-semibold text-slate-900">{{ \Illuminate\Support\Str::limit($p->title ?: $p->body, 80) }}</td>
                        <td>{{ number_format((int) $p->reactions_count) }}</td>
                        <td>{{ number_format((int) $p->comments_count) }}</td>
                        <td class="text-slate-500">{{ optional($p->published_at)->format('M j, Y') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    const labels   = @json(array_keys($audienceSeries));
    const audience = @json(array_values($audienceSeries));
    const content  = @json(array_values($contentSeries));
    // This page renders on always-light (bg-white) cards, so the chart is
    // tuned for a light surface: dark slate axis labels and a faint grid that
    // stay legible on white regardless of the app's light/dark theme.
    const statsTickColor = 'rgba(15,23,42,0.7)';
    const statsGridColor = 'rgba(0,0,0,0.07)';
    new Chart(document.getElementById('statsChart'), {
        type: 'line',
        data: {
            labels,
            datasets: [
                { label: 'New followers',  data: audience, borderColor: '#7c3aed', backgroundColor: 'rgba(124,58,237,.15)', tension: .3, fill: true },
                { label: 'Posts published',data: content,  borderColor: '#0ea5e9', backgroundColor: 'rgba(14,165,233,.15)', tension: .3, fill: true },
            ]
        },
        options: {
            plugins: {
                legend: { position: 'bottom', labels: { color: statsTickColor } },
                tooltip: {
                    backgroundColor: 'rgba(255,255,255,0.98)',
                    titleColor: statsTickColor, bodyColor: statsTickColor,
                    borderColor: 'rgba(124,58,237,0.35)', borderWidth: 1,
                    padding: 10, cornerRadius: 10,
                },
            },
            scales: {
                x: { grid: { color: statsGridColor }, ticks: { color: statsTickColor }, border: { display: false } },
                y: { beginAtZero: true, grid: { color: statsGridColor }, ticks: { color: statsTickColor, precision: 0 }, border: { display: false } },
            },
        }
    });
</script>
@endsection
