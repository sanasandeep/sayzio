{{--
    Shared date-range control for visitor analytics (Task #3812): the
    today/7d/30d/90d/year/all preset pills PLUS a custom start/end date
    picker, all honoring the plan's stats-retention clamp server-side.

    Expects:
      - $buildUrl: closure(array $overrides = []): string — merges overrides
        into the current query string (period filter callers already define
        this; account page defines its own that also preserves `type`).
      - $period, $startDate, $endDate: current resolved window.
--}}
@php
    $isCustom = ($period ?? '30d') === 'custom';
@endphp
<div class="period-bar mb-6">
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-[10px] uppercase tracking-wider font-bold mr-1" style="color: var(--text-faint);"><i class="fas fa-clock text-blue-400"></i> Period</span>
        @foreach(\App\Modules\User\Support\AnalyticsRangeResolver::PRESETS as $k => $lbl)
            <a href="{{ $buildUrl(['period' => $k, 'start' => null, 'end' => null]) }}" class="pill {{ !$isCustom && ($period ?? '30d') === $k ? 'pill-active' : '' }}">{{ $lbl }}</a>
        @endforeach
        <form method="GET" class="flex items-center gap-1.5 flex-wrap" data-custom-range-form>
            @foreach(request()->except(['period', 'start', 'end']) as $k => $v)
                @if(is_array($v))
                    @foreach($v as $vv)
                        <input type="hidden" name="{{ $k }}[]" value="{{ $vv }}">
                    @endforeach
                @else
                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                @endif
            @endforeach
            <input type="hidden" name="period" value="custom">
            <input type="date" name="start" value="{{ $startDate->format('Y-m-d') }}" class="range-date-input" max="{{ now()->format('Y-m-d') }}">
            <span class="text-xs" style="color: var(--text-faint);">–</span>
            <input type="date" name="end" value="{{ $endDate->format('Y-m-d') }}" class="range-date-input" max="{{ now()->format('Y-m-d') }}">
            <button type="submit" class="pill {{ $isCustom ? 'pill-active' : '' }}"><i class="fas fa-calendar-week text-[9px] mr-1"></i> Custom</button>
        </form>
    </div>
</div>

@once
    @push('styles')
    <style>
        .range-date-input {
            background: var(--bg-glass-input, var(--bg-glass));
            border: 1px solid var(--border-glass);
            color: var(--text-primary);
            border-radius: 9px;
            padding: 6px 8px;
            font-size: 11px;
            font-weight: 600;
            color-scheme: light dark;
        }
        .range-date-input::-webkit-calendar-picker-indicator { opacity: .65; }
    </style>
    @endpush
@endonce
