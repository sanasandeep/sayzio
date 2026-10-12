{{--
    One dimension card: a donut, a filter pill row, and the chart actions.

    The per-link stats page had this markup three times over for browsers,
    operating systems and devices, and twice more in spirit for traffic source
    and channel -- identical but for an accent colour, an icon, a column name
    and a canvas id. The folder report needs the same five cards, so rather
    than a sixth, seventh and eighth copy they all come from here.

    The canvas carries its own labels and values as data attributes and is
    drawn by `dimension-charts`, so a page gains a card by including this
    partial and nothing else: no per-card JavaScript to forget.

    Expects:
      rows        collection of {<column>, count}
      column      property on each row holding the label
      filterKey   query-string key this card filters by
      filterValue currently applied value, if any
      title/icon/slug/canvasId
      accent      [from, to] gradient stops
      url         fn (array $params): string  -- merges into the current query
      labels      optional map of raw value => display label
      footer      chart-export footer line
--}}
@php
    $__rows = $rows ?? collect();
    $__names = ['macos' => 'macOS', 'ios' => 'iOS', 'windows' => 'Windows', 'android' => 'Android', 'linux' => 'Linux', 'chrome' => 'Chrome', 'safari' => 'Safari', 'edge' => 'Edge', 'firefox' => 'Firefox', 'mobile_app' => 'Mobile app', 'web' => 'Web', 'desktop' => 'Desktop', 'mobile' => 'Mobile', 'tablet' => 'Tablet', 'unknown' => 'Unknown'];
    $__labelFor = fn ($v) => ($labels[$v] ?? $__names[strtolower((string) $v)] ?? ucfirst(str_replace('_', ' ', (string) $v)));
    $__accentFrom = $accent[0] ?? '#6366f1';
    $__accentTo   = $accent[1] ?? '#818cf8';
@endphp
<div class="section-card"
     style="--sc-accent: linear-gradient(90deg,{{ $__accentFrom }},{{ $__accentTo }}); --sc-glow: {{ $glow ?? 'rgba(99,102,241,0.35)' }}; --sc-color: {{ $tint ?? '#a5b4fc' }}; --sc-border: {{ $borderTint ?? 'rgba(99,102,241,0.3)' }};">
    <div class="section-head">
        <div class="section-title"><div class="section-icon"><i class="fas {{ $icon }}"></i></div> {{ $title }}</div>
        <div class="flex items-center gap-2 flex-wrap">
            @if(!empty($filterValue))
                <a href="{{ $url([$filterKey => null]) }}" class="section-pill" title="Clear {{ Str::lower($title) }} filter">
                    <i class="fas fa-times mr-1"></i>{{ $__labelFor($filterValue) }}
                </a>
            @endif
            @if($__rows->isNotEmpty())
                @include('user.links.partials.chart-actions', [
                    'target' => $canvasId,
                    'title'  => $title,
                    'slug'   => $slug,
                    'footer' => $footer ?? '',
                ])
            @endif
        </div>
    </div>

    @if($__rows->isEmpty())
        <p class="text-sm text-center py-8" style="color: var(--text-faint);">No data</p>
    @else
        <div style="height: 240px;">
            <canvas id="{{ $canvasId }}"
                    data-dimension-chart
                    data-labels="{{ json_encode($__rows->pluck($column)->map($__labelFor)->values()) }}"
                    data-values="{{ json_encode($__rows->pluck('count')->values()) }}"></canvas>
        </div>
        <div class="flex flex-wrap items-center gap-2 mt-3">
            <a href="{{ $url([$filterKey => null]) }}" class="pill {{ empty($filterValue) ? 'pill-active' : '' }}">All</a>
            @foreach($__rows->take(8) as $row)
                <a href="{{ $url([$filterKey => $row->{$column}]) }}"
                   class="pill {{ (string) ($filterValue ?? '') === (string) $row->{$column} ? 'pill-active' : '' }}">
                    {{ $__labelFor($row->{$column}) }} <span class="ml-1 opacity-60">({{ number_format($row->count) }})</span>
                </a>
            @endforeach
        </div>
    @endif
</div>
