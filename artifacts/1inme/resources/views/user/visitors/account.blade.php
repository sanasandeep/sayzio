@extends('user.layouts.app')
@section('title', 'Visitors')

@section('content')
@php
    $qs = request()->query();
    $buildUrl = fn($overrides = []) => route('user.visitors.index') . '?' . http_build_query(array_filter(array_merge($qs, $overrides), fn($v) => $v !== null));
@endphp

@push('styles')
<style>
    .period-bar {
        background: var(--bg-glass);
        border: 1px solid var(--border-glass);
        border-radius: 18px;
        padding: 10px 14px;
        backdrop-filter: blur(20px);
    }
    .pill {
        padding: 7px 13px;
        border-radius: 11px;
        font-size: 11px;
        font-weight: 600;
        transition: all .2s ease;
        color: var(--text-muted);
        white-space: nowrap;
    }
    .pill:hover { background: var(--bg-glass-hover); color: var(--text-primary); transform: translateY(-1px); }
    .pill-active {
        background: linear-gradient(135deg, #3d6bff, #5c83ff);
        color: #fff !important;
        box-shadow: 0 6px 18px rgba(61,107,255,0.4);
    }
    .view-toggle {
        display: inline-flex;
        border: 1px solid var(--border-glass);
        border-radius: 10px;
        overflow: hidden;
    }
    .view-toggle button {
        padding: 6px 11px;
        font-size: 11px;
        font-weight: 600;
        color: var(--text-muted);
        background: var(--bg-glass);
    }
    .view-toggle button.active {
        background: linear-gradient(135deg, #3d6bff, #5c83ff);
        color: #fff;
    }
    .type-select {
        background: var(--bg-glass-input, var(--bg-glass));
        border: 1px solid var(--border-glass);
        color: var(--text-primary);
        border-radius: 11px;
        padding: 8px 12px;
        font-size: 12px;
        font-weight: 600;
    }
    .type-select option { color: #0f172a; }
</style>
@endpush

@include('user.partials.page-hero', [
    'title'    => 'Visitors',
    'icon'     => 'fa-users',
    'chips'    => [
        ['icon' => 'fa-calendar', 'text' => $startDate->format('M d') . ' – ' . $endDate->format('M d, Y')],
        ['icon' => 'fa-filter', 'text' => $typeFilter === 'all' ? 'All link types' : \App\Modules\User\Models\Link::typeLabel($typeFilter)],
    ],
    'actions'  => [
        workspace_owner()?->getPlanFeature('analytics_export', true)
            ? ['label' => 'Export CSV', 'url' => route('user.visitors.export', request()->query()), 'icon' => 'fa-download', 'class' => 'btn-primary']
            : ['label' => 'Export CSV', 'url' => route('user.upgrade'), 'icon' => 'fa-lock', 'class' => 'btn-ghost', 'title' => 'Exporting visitor data is a paid feature. Upgrade your plan to download CSV exports.'],
        ['label' => 'Stats home', 'url' => route('user.stats.index'), 'icon' => 'fa-chart-line', 'class' => 'btn-ghost'],
    ],
])

@if(!$hasLinks)
    <div class="rounded-2xl border p-8 text-center" style="background: var(--bg-card); border-color: var(--border-soft);">
        <i class="fas fa-link text-3xl mb-3" style="color: var(--text-faint);"></i>
        <p class="font-semibold" style="color: var(--text-primary);">No links yet</p>
        <p class="text-sm mt-1" style="color: var(--text-muted);">Create a link, biolink, or QR code to start collecting visitor analytics.</p>
        <a href="{{ route('user.links.create') }}" class="btn-primary inline-flex items-center gap-2 mt-4 text-sm px-4 py-2"><i class="fas fa-plus"></i> Create a link</a>
    </div>
@else
    {{-- ===================== FILTERS ===================== --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        @include('user.partials.visitor-range-control', ['buildUrl' => $buildUrl, 'period' => $period, 'startDate' => $startDate, 'endDate' => $endDate])
        <form method="GET" class="flex items-center gap-2">
            @foreach(request()->except(['type']) as $k => $v)
                @continue(is_array($v))
                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endforeach
            <label class="text-[10px] uppercase tracking-wider font-bold" style="color: var(--text-faint);"><i class="fas fa-shapes text-blue-400"></i> Type</label>
            <select name="type" onchange="this.form.submit()" class="type-select">
                <option value="all" {{ $typeFilter === 'all' ? 'selected' : '' }}>All types</option>
                @foreach($availableTypes as $t)
                    <option value="{{ $t }}" {{ $typeFilter === $t ? 'selected' : '' }}>{{ \App\Modules\User\Models\Link::typeLabel($t) }}</option>
                @endforeach
            </select>
        </form>
    </div>

    {{-- ===================== KPI CARDS ===================== --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
        <div class="rounded-xl p-4 border" style="background: var(--bg-card); border-color: var(--border-soft); box-shadow: var(--card-shadow);">
            <p class="text-xs uppercase tracking-wide" style="color: var(--text-faint);">Total visitors</p>
            <p class="text-2xl font-extrabold mt-1" style="color: var(--text-primary);">{{ number_format($totalVisitors) }}</p>
        </div>
        <div class="rounded-xl p-4 border" style="background: var(--bg-card); border-color: var(--border-soft); box-shadow: var(--card-shadow);">
            <p class="text-xs uppercase tracking-wide" style="color: var(--text-faint);">New</p>
            <p class="text-2xl font-extrabold mt-1 text-emerald-600">{{ number_format($newCount) }}</p>
        </div>
        <div class="rounded-xl p-4 border" style="background: var(--bg-card); border-color: var(--border-soft); box-shadow: var(--card-shadow);">
            <p class="text-xs uppercase tracking-wide" style="color: var(--text-faint);">Returning</p>
            <p class="text-2xl font-extrabold mt-1 text-blue-600">{{ number_format($returningCount) }}</p>
        </div>
    </div>

    {{-- ===================== TREND CHART ===================== --}}
    <div class="rounded-2xl border p-5 mb-6" style="background: var(--bg-card); border-color: var(--border-soft); box-shadow: var(--card-shadow);">
        <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
            <h2 class="font-bold" style="color: var(--text-primary);">Visitor trend</h2>
            <div class="view-toggle" data-chart-toggle="trendChart">
                <button type="button" class="active" data-view="line">Line</button>
                <button type="button" data-view="bar">Bar</button>
                <button type="button" data-view="area">Area</button>
            </div>
        </div>
        @if($dailySeries->isEmpty())
            <p class="text-sm py-8 text-center" style="color: var(--text-muted);">No visitor activity in this range yet.</p>
        @else
            <div style="height: 260px;"><canvas id="trendChart"></canvas></div>
        @endif
    </div>

    {{-- ===================== BREAKDOWNS ===================== --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="rounded-2xl border p-5" style="background: var(--bg-card); border-color: var(--border-soft); box-shadow: var(--card-shadow);">
            <h2 class="font-bold mb-3" style="color: var(--text-primary);">Visitors by link type</h2>
            @if($typeBreakdown->isEmpty())
                <p class="text-sm py-8 text-center" style="color: var(--text-muted);">No data in this range.</p>
            @else
                <div style="height: 240px;"><canvas id="typeChart"></canvas></div>
            @endif
        </div>
        <div class="rounded-2xl border p-5" style="background: var(--bg-card); border-color: var(--border-soft); box-shadow: var(--card-shadow);">
            <h2 class="font-bold mb-3" style="color: var(--text-primary);">Visitors by source</h2>
            @if($sourceBreakdown->isEmpty())
                <p class="text-sm py-8 text-center" style="color: var(--text-muted);">No data in this range.</p>
            @else
                <div style="height: 240px;"><canvas id="sourceChart"></canvas></div>
            @endif
        </div>
    </div>

    <script src="{{ asset('js/vendor/chart.umd.min.js') }}"></script>
    @vite(['resources/js/analytics-charts.js'])
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const labels = @json($dailySeries->pluck('d'));
            const newSeries = @json($dailySeries->pluck('new'));
            const returningSeries = @json($dailySeries->pluck('returning'));

            let trendChart = null;
            if (labels.length) {
                trendChart = AnalyticsCharts.createTrendChart('trendChart', labels, [
                    { label: 'New', data: newSeries, color: '#10b981' },
                    { label: 'Returning', data: returningSeries, color: '#3d6bff' },
                ]);
            }

            const toggle = document.querySelector('[data-chart-toggle="trendChart"]');
            if (toggle && trendChart) {
                toggle.querySelectorAll('button').forEach((btn) => {
                    btn.addEventListener('click', () => {
                        toggle.querySelectorAll('button').forEach((b) => b.classList.remove('active'));
                        btn.classList.add('active');
                        AnalyticsCharts.setTrendView(trendChart, btn.dataset.view);
                    });
                });
            }

            const typeLabels = @json($typeBreakdown->pluck('label'));
            const typeData = @json($typeBreakdown->pluck('n'));
            if (typeLabels.length) {
                AnalyticsCharts.createBreakdownChart('typeChart', typeLabels, typeData, { type: 'doughnut' });
            }

            const sourceLabels = @json($sourceBreakdown->pluck('src'));
            const sourceData = @json($sourceBreakdown->pluck('n'));
            if (sourceLabels.length) {
                AnalyticsCharts.createBreakdownChart('sourceChart', sourceLabels, sourceData, { type: 'bar' });
            }
        });
    </script>
@endif
@endsection
