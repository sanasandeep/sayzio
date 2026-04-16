{{-- ===================== PERFORMANCE COACH ===================== --}}
@if(!empty($performance))
@php
    $p = $performance;
    $sevMap = [
        'critical' => ['bg' => 'rgba(239,68,68,0.12)',  'border' => 'rgba(239,68,68,0.35)',  'color' => '#fca5a5', 'icon' => 'fa-triangle-exclamation'],
        'warning'  => ['bg' => 'rgba(245,158,11,0.12)', 'border' => 'rgba(245,158,11,0.35)', 'color' => '#fcd34d', 'icon' => 'fa-circle-exclamation'],
        'tip'      => ['bg' => 'rgba(59,130,246,0.12)', 'border' => 'rgba(59,130,246,0.35)', 'color' => '#93c5fd', 'icon' => 'fa-lightbulb'],
        'win'      => ['bg' => 'rgba(16,185,129,0.12)', 'border' => 'rgba(16,185,129,0.35)', 'color' => '#6ee7b7', 'icon' => 'fa-circle-check'],
    ];
    $deltaPct = $p['delta_pct'] ?? null;
    $deltaUp  = $deltaPct !== null && $deltaPct > 0.001;
    $deltaDown = $deltaPct !== null && $deltaPct < -0.001;

    // Gauge ring color based on score band.
    $score = $p['score'];
    $gradeColor = match(true) {
        $score === null      => '#9ca3af',
        $score >= 90         => '#34d399',
        $score >= 75         => '#6ee7b7',
        $score >= 60         => '#fbbf24',
        $score >= 40         => '#fb923c',
        default              => '#f87171',
    };
    $gaugeDeg = $score === null ? 0 : round(($score / 100) * 360);

    // Sparkline: last 30 daily score snapshots (written by coach:snapshot-scores).
    // Only render when we have at least 2 points — a single dot isn't a trend.
    $history = $performanceHistory ?? [];
    $sparkPoints = [];
    $sparkDelta  = null;
    if (is_array($history) && count($history) >= 2) {
        $w = 140; $h = 36; $pad = 2;
        $n = count($history);
        $stepX = $n > 1 ? ($w - 2 * $pad) / ($n - 1) : 0;
        foreach ($history as $i => $row) {
            $s = max(0, min(100, (int) ($row['score'] ?? 0)));
            $x = $pad + $stepX * $i;
            $y = $pad + (1 - $s / 100) * ($h - 2 * $pad);
            $sparkPoints[] = round($x, 2) . ',' . round($y, 2);
        }
        $first = (int) ($history[0]['score'] ?? 0);
        $last  = (int) ($history[count($history) - 1]['score'] ?? 0);
        $sparkDelta = $last - $first;
    }
@endphp

<div class="glass rounded-2xl p-4 md:p-5 mb-3 perf-coach"
     style="--pc-ring: {{ $gradeColor }}; --pc-gauge-deg: {{ $gaugeDeg }}deg;">
    <div class="flex flex-col lg:flex-row gap-5">

        {{-- LEFT: Score gauge / grade / trend --}}
        <div class="flex items-center gap-4 lg:w-[320px] lg:flex-shrink-0 lg:border-r lg:pr-5"
             style="border-color: var(--border-glass);">
            <div class="pc-gauge">
                <div class="pc-gauge-inner">
                    @if($score !== null)
                        <div class="pc-score">{{ $score }}</div>
                        <div class="pc-grade" style="color: {{ $gradeColor }};">{{ $p['grade'] }}</div>
                    @else
                        <div class="pc-score" style="font-size: 1.1rem; line-height:1;">—</div>
                        <div class="pc-grade" style="color: {{ $gradeColor }}; font-size:.7rem;">NEW</div>
                    @endif
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-[10px] uppercase tracking-wider font-bold" style="color: var(--text-faint);">
                    <i class="fas fa-wand-magic-sparkles text-purple-400"></i> {{ $p['headline'] }}
                </div>
                <div class="text-lg font-semibold mt-0.5" style="color: var(--text-primary);">{{ $p['label'] }}</div>
                @if(!empty($sparkPoints))
                    @php
                        $sparkColor = $sparkDelta > 0 ? '#6ee7b7' : ($sparkDelta < 0 ? '#fca5a5' : '#cbd5e1');
                        $sparkFill  = $sparkDelta > 0 ? 'rgba(16,185,129,0.18)' : ($sparkDelta < 0 ? 'rgba(239,68,68,0.15)' : 'rgba(148,163,184,0.15)');
                        $areaPath   = 'M ' . $sparkPoints[0] . ' L ' . implode(' ', array_slice($sparkPoints, 1))
                                    . ' L 138,34 L 2,34 Z';
                        $linePath   = 'M ' . $sparkPoints[0] . ' L ' . implode(' ', array_slice($sparkPoints, 1));
                        $sparkTitle = count($history) . '-day score trend';
                        if ($sparkDelta !== null) {
                            $sign = $sparkDelta > 0 ? '+' : '';
                            $sparkTitle .= " ({$sign}{$sparkDelta} pts)";
                        }
                    @endphp
                    <div class="pc-spark mt-2" title="{{ $sparkTitle }}" aria-label="{{ $sparkTitle }}">
                        <svg viewBox="0 0 140 36" width="140" height="36" preserveAspectRatio="none" style="display:block;">
                            <path d="{{ $areaPath }}" fill="{{ $sparkFill }}" stroke="none" />
                            <path d="{{ $linePath }}" fill="none" stroke="{{ $sparkColor }}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            @php $last = end($sparkPoints); @endphp
                            <circle cx="{{ explode(',', $last)[0] }}" cy="{{ explode(',', $last)[1] }}" r="2" fill="{{ $sparkColor }}" />
                        </svg>
                        <span class="pc-spark-label" style="color: {{ $sparkColor }};">
                            @if($sparkDelta > 0)
                                +{{ $sparkDelta }} pts
                            @elseif($sparkDelta < 0)
                                {{ $sparkDelta }} pts
                            @else
                                flat
                            @endif
                            <span style="color: var(--text-faint); font-weight: 500;">· {{ count($history) }}d</span>
                        </span>
                    </div>
                @endif
                <div class="mt-2">
                    @if($deltaUp)
                        <span class="pc-trend" style="background:rgba(16,185,129,0.15); color:#6ee7b7; border-color:rgba(16,185,129,0.3);">
                            <i class="fas fa-arrow-trend-up text-[9px]"></i> {{ $p['delta_label'] }}
                        </span>
                    @elseif($deltaDown)
                        <span class="pc-trend" style="background:rgba(239,68,68,0.15); color:#fca5a5; border-color:rgba(239,68,68,0.3);">
                            <i class="fas fa-arrow-trend-down text-[9px]"></i> {{ $p['delta_label'] }}
                        </span>
                    @else
                        <span class="pc-trend" style="background:rgba(148,163,184,0.15); color:#cbd5e1; border-color:rgba(148,163,184,0.3);">
                            <i class="fas fa-minus text-[9px]"></i> {{ $p['delta_label'] }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- RIGHT: Insights --}}
        <div class="flex-1 min-w-0">
            @if(empty($p['insights']))
                <div class="text-sm py-6 text-center" style="color: var(--text-faint);">
                    <i class="fas fa-check-circle text-emerald-400 mr-1"></i>
                    No recommendations — all metrics look healthy.
                </div>
            @else
                <div class="flex flex-col gap-2">
                    @foreach($p['insights'] as $ins)
                        @php $s = $sevMap[$ins['severity']] ?? $sevMap['tip']; @endphp
                        <div class="pc-insight"
                             style="background: {{ $s['bg'] }}; border-color: {{ $s['border'] }};">
                            <div class="pc-insight-icon" style="color: {{ $s['color'] }};">
                                <i class="fas {{ $ins['icon'] ?? $s['icon'] }}"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-semibold leading-tight" style="color: var(--text-primary);">{{ $ins['headline'] }}</div>
                                <div class="text-[12px] mt-0.5" style="color: var(--text-faint);">{{ $ins['reason'] }}</div>
                            </div>
                            @if(!empty($ins['action']) && !empty($ins['action_label']) && !empty($link) && isset($ins['action']['type']))
                                @php $act = $ins['action']; @endphp
                                <form action="{{ route('user.links.coach-action', $link) }}" method="POST" class="pc-insight-action-form"
                                      @if(!empty($act['confirm'])) onsubmit="return confirm(@json($act['confirm']));" @endif>
                                    @csrf
                                    <input type="hidden" name="action_type" value="{{ $act['type'] }}">
                                    @if(!empty($act['block_id']))
                                        <input type="hidden" name="block_id" value="{{ (int) $act['block_id'] }}">
                                    @endif
                                    @if(!empty($act['block_ids']) && is_array($act['block_ids']))
                                        @foreach($act['block_ids'] as $bid)
                                            <input type="hidden" name="block_ids[]" value="{{ (int) $bid }}">
                                        @endforeach
                                    @endif
                                    <button type="submit" class="pc-insight-cta" style="color: {{ $s['color'] }}; border-color: {{ $s['border'] }};">
                                        <i class="fas fa-bolt text-[9px] mr-1"></i>{{ $ins['action_label'] }}
                                    </button>
                                </form>
                            @elseif(!empty($ins['action_url']) && !empty($ins['action_label']))
                                <a href="{{ $ins['action_url'] }}" class="pc-insight-cta" style="color: {{ $s['color'] }}; border-color: {{ $s['border'] }};">
                                    {{ $ins['action_label'] }} <i class="fas fa-arrow-right text-[9px] ml-1"></i>
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

<style>
    .perf-coach .pc-gauge {
        width: 92px; height: 92px; border-radius: 50%; flex-shrink: 0;
        background:
            conic-gradient(var(--pc-ring) var(--pc-gauge-deg), rgba(148,163,184,0.18) 0);
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 0 20px -6px var(--pc-ring);
        position: relative;
    }
    .perf-coach .pc-gauge::before {
        content: ''; position: absolute; inset: 5px; border-radius: 50%;
        background: var(--bg-secondary, rgba(15,15,25,0.85));
        backdrop-filter: blur(10px);
    }
    .perf-coach .pc-gauge-inner {
        position: relative; z-index: 1; text-align: center; line-height: 1;
    }
    .perf-coach .pc-score {
        font-size: 1.75rem; font-weight: 800; color: var(--text-primary, #fff);
        letter-spacing: -0.02em;
    }
    .perf-coach .pc-grade {
        font-size: .7rem; font-weight: 700; letter-spacing: .12em; margin-top: 2px;
    }
    .perf-coach .pc-trend {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600;
        border: 1px solid; white-space: nowrap;
    }
    .perf-coach .pc-insight {
        display: flex; align-items: center; gap: 12px;
        padding: 10px 12px; border-radius: 12px;
        border: 1px solid;
        transition: transform .15s ease;
    }
    .perf-coach .pc-insight:hover { transform: translateY(-1px); }
    .perf-coach .pc-insight-icon {
        width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
        border-radius: 8px; background: rgba(255,255,255,0.05); font-size: 14px; flex-shrink: 0;
    }
    .perf-coach .pc-insight-cta {
        display: inline-flex; align-items: center;
        padding: 5px 10px; border-radius: 999px;
        border: 1px solid;
        font-size: 11px; font-weight: 600; white-space: nowrap;
        background: rgba(255,255,255,0.03);
        transition: background .15s ease;
    }
    .perf-coach .pc-insight-cta:hover { background: rgba(255,255,255,0.08); }
    .perf-coach .pc-spark {
        display: flex; align-items: center; gap: 8px;
    }
    .perf-coach .pc-spark svg {
        flex-shrink: 0;
        filter: drop-shadow(0 1px 2px rgba(0,0,0,0.25));
    }
    .perf-coach .pc-spark-label {
        font-size: 10px; font-weight: 700; letter-spacing: .04em;
        text-transform: uppercase; white-space: nowrap;
    }
    @media (max-width: 1024px) {
        .perf-coach .pc-insight-cta { display: none; }
    }
</style>
@endif
