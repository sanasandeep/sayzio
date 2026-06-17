{{-- ===================== PERFORMANCE COACH ===================== --}}
@if(!empty($performance))
@php
    $p = $performance;
    $pcPresets = \App\Modules\User\Services\LinkPerformanceCoach::availablePresets();
    $pcPreset  = \App\Modules\User\Services\LinkPerformanceCoach::resolvePreset($link);
    $pcEffective = \App\Modules\User\Services\LinkPerformanceCoach::resolveConfig($link);
    $sevMap = [
        'critical' => ['bg' => 'rgba(239,68,68,0.12)',  'border' => 'rgba(239,68,68,0.35)',  'color' => '#fca5a5', 'icon' => 'fa-triangle-exclamation'],
        'warning'  => ['bg' => 'rgba(245,158,11,0.12)', 'border' => 'rgba(245,158,11,0.35)', 'color' => '#fcd34d', 'icon' => 'fa-circle-exclamation'],
        'tip'      => ['bg' => 'rgba(59,130,246,0.12)', 'border' => 'rgba(59,130,246,0.35)', 'color' => '#c4b5fd', 'icon' => 'fa-lightbulb'],
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

    // Component breakdown for the most recent snapshot — tells users *why*
    // their score sits where it does. Components are normalized 0-1 values
    // written by `coach:snapshot-scores` into `components_json`.
    $latestComponents = null;
    if (is_array($history) && count($history) > 0) {
        $lastRow = $history[count($history) - 1];
        if (!empty($lastRow['components']) && is_array($lastRow['components'])) {
            $latestComponents = $lastRow['components'];
        }
    }
    $componentMeta = [
        'ctr'        => ['label' => 'CTR',        'color' => '#a78bfa'],
        'bounce'     => ['label' => 'Bounce',     'color' => '#f472b6'],
        'engagement' => ['label' => 'Engagement', 'color' => '#a78bfa'],
        'momentum'   => ['label' => 'Momentum',   'color' => '#34d399'],
        'diversity'  => ['label' => 'Diversity',  'color' => '#fbbf24'],
        'activity'   => ['label' => 'Activity',   'color' => '#f87171'],
    ];
    $weakestKey = null;
    $weakestVal = null;
    if ($latestComponents) {
        foreach ($componentMeta as $k => $_m) {
            if (!array_key_exists($k, $latestComponents)) continue;
            $v = (float) $latestComponents[$k];
            if ($weakestVal === null || $v < $weakestVal) {
                $weakestVal = $v;
                $weakestKey = $k;
            }
        }
    }
@endphp

<div class="glass rounded-2xl p-4 md:p-5 mb-3 perf-coach"
     style="--pc-ring: {{ $gradeColor }}; --pc-gauge-deg: {{ $gaugeDeg }}deg;"
     x-data="{
        pcSettingsOpen: false,
        pcPreset: @js($pcPreset),
        pcPresets: @js(collect($pcPresets)->mapWithKeys(fn($v,$k)=>[$k=>$v['values']])->all()),
        pcLabels: @js(array_merge(collect($pcPresets)->mapWithKeys(fn($v,$k)=>[$k=>$v['label']])->all(), ['custom' => 'Custom'])),
        pcValues: @js(collect($pcEffective)->only(\App\Modules\User\Services\LinkPerformanceCoach::TUNABLE_KEYS)->all()),
        applyPreset(p) {
            this.pcPreset = p;
            if (p !== 'custom' && this.pcPresets[p]) {
                this.pcValues = { ...this.pcPresets[p] };
            }
        },
        onEdit() { if (this.pcPreset !== 'custom') this.pcPreset = 'custom'; }
     }">
    <button type="button"
            @click="pcSettingsOpen = !pcSettingsOpen"
            class="pc-settings-btn"
            :aria-expanded="pcSettingsOpen.toString()"
            title="Tune what counts as 'healthy' for this page">
        <i class="fas" :class="pcSettingsOpen ? 'fa-xmark' : 'fa-sliders'"></i>
        <span class="pc-settings-btn-label" x-text="pcLabels[pcPreset] || 'Creator'"></span>
    </button>
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
                    <i class="fas fa-wand-magic-sparkles text-violet-400"></i> {{ $p['headline'] }}
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
                    <div class="pc-spark mt-2" aria-label="{{ $sparkTitle }}">
                        <svg viewBox="0 0 140 36" width="140" height="36" preserveAspectRatio="none" style="display:block; overflow: visible;" role="img" aria-label="{{ $sparkTitle }}">
                            <title>{{ $sparkTitle }}</title>
                            <path d="{{ $areaPath }}" fill="{{ $sparkFill }}" stroke="none" />
                            <path d="{{ $linePath }}" fill="none" stroke="{{ $sparkColor }}" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            @php $lastPoint = end($sparkPoints); @endphp
                            <circle cx="{{ explode(',', $lastPoint)[0] }}" cy="{{ explode(',', $lastPoint)[1] }}" r="2" fill="{{ $sparkColor }}" />
                            {{-- Per-day hoverable / focusable points with date + score tooltips. --}}
                            @foreach($sparkPoints as $i => $pt)
                                @php
                                    [$cx, $cy] = explode(',', $pt);
                                    $row = $history[$i] ?? [];
                                    $rawDate = $row['date'] ?? null;
                                    try {
                                        $dateLabel = $rawDate ? \Carbon\Carbon::parse($rawDate)->format('M j, Y') : '';
                                    } catch (\Throwable $e) {
                                        $dateLabel = (string) $rawDate;
                                    }
                                    $pointScore = (int) ($row['score'] ?? 0);
                                    $pointTitle = trim($dateLabel . ($dateLabel !== '' ? ' — ' : '') . $pointScore . ' pts');
                                @endphp
                                <circle class="pc-spark-point"
                                        cx="{{ $cx }}" cy="{{ $cy }}" r="6"
                                        fill="transparent"
                                        tabindex="0"
                                        role="img"
                                        aria-label="{{ $pointTitle }}">
                                    <title>{{ $pointTitle }}</title>
                                </circle>
                            @endforeach
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
                @if($latestComponents)
                    @php
                        $weakestLabel = $weakestKey ? $componentMeta[$weakestKey]['label'] : null;
                        $weakestPct   = $weakestVal !== null ? (int) round($weakestVal * 100) : null;
                    @endphp
                    <div class="pc-breakdown mt-2" aria-label="Score factor breakdown">
                        @foreach($componentMeta as $key => $meta)
                            @php
                                $val = isset($latestComponents[$key]) ? max(0.0, min(1.0, (float) $latestComponents[$key])) : null;
                                $pct = $val === null ? 0 : (int) round($val * 100);
                                $isWeakest = $key === $weakestKey;
                                $title = $meta['label'] . ': ' . ($val === null ? 'n/a' : $pct . '%')
                                       . ($isWeakest ? ' — weakest factor' : '');
                            @endphp
                            <div class="pc-bd-col {{ $isWeakest ? 'pc-bd-weak' : '' }}"
                                 title="{{ $title }}" aria-label="{{ $title }}">
                                <div class="pc-bd-track">
                                    <div class="pc-bd-fill"
                                         style="height: {{ $pct }}%; background: {{ $meta['color'] }};
                                                box-shadow: 0 0 6px -1px {{ $meta['color'] }};"></div>
                                </div>
                                <div class="pc-bd-label">{{ strtoupper(substr($meta['label'], 0, 3)) }}</div>
                            </div>
                        @endforeach
                    </div>
                    @if($weakestLabel !== null && $weakestPct !== null)
                        <div class="pc-bd-caption" style="color: var(--text-faint);">
                            <i class="fas fa-arrow-down-wide-short text-[9px] mr-1" style="color:#fca5a5;"></i>
                            Weakest: <span style="color: var(--text-primary); font-weight: 600;">{{ $weakestLabel }}</span>
                            <span style="color: var(--text-faint);">· {{ $weakestPct }}%</span>
                        </div>
                    @endif
                @endif
                <div class="mt-2">
                    @if($deltaUp)
                        <span class="pc-trend text-emerald-300" style="background:rgba(16,185,129,0.15); border-color:rgba(16,185,129,0.3);">
                            <i class="fas fa-arrow-trend-up text-[9px]"></i> {{ $p['delta_label'] }}
                        </span>
                    @elseif($deltaDown)
                        <span class="pc-trend text-red-300" style="background:rgba(239,68,68,0.15); border-color:rgba(239,68,68,0.3);">
                            <i class="fas fa-arrow-trend-down text-[9px]"></i> {{ $p['delta_label'] }}
                        </span>
                    @else
                        <span class="pc-trend text-slate-300" style="background:rgba(148,163,184,0.15); border-color:rgba(148,163,184,0.3);">
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
                                @if(!empty($ins['threshold']) && !empty($ins['threshold']['key']))
                                    @php $th = $ins['threshold']; @endphp
                                    <button type="button"
                                            class="pc-threshold-chip"
                                            style="color: {{ $s['color'] }}; border-color: {{ $s['border'] }}; background: {{ $s['bg'] }};"
                                            title="Click to tune this threshold"
                                            @click="pcSettingsOpen = true; $nextTick(() => { const el = $root.querySelector('[name=&quot;overrides[{{ $th['key'] }}]&quot;]'); if (el) { el.scrollIntoView({behavior:'smooth', block:'center'}); el.focus(); el.select && el.select(); } })">
                                        <i class="fas fa-sliders text-[9px] mr-1"></i>
                                        <span class="pc-threshold-chip-rule">{{ $th['threshold_label'] }}</span>
                                        <span class="pc-threshold-chip-sep">·</span>
                                        <span class="pc-threshold-chip-actual">{{ $th['actual_label'] }}</span>
                                    </button>
                                @endif
                            </div>
                            @if(!empty($ins['action']) && !empty($ins['action_label']) && !empty($link) && isset($ins['action']['type']))
                                @php $act = $ins['action']; @endphp
                                <form action="{{ route('user.links.coach-action', $link) }}" method="POST" class="pc-insight-action-form"
                                      @if(!empty($act['confirm'])) onsubmit="return window.themedConfirmSubmit(this, {title: 'Apply this fix?', message: @js($act['confirm']), confirmText: 'Apply', confirmIcon: 'fa-wand-magic-sparkles', iconClass: 'fa-wand-magic-sparkles'})" @endif>
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

    {{-- Inline settings drawer: preset picker + custom thresholds --}}
    <div x-show="pcSettingsOpen" x-cloak x-transition class="pc-settings">
        <form method="POST" action="{{ route('user.links.performance-coach.settings', $link) }}" class="pc-settings-form">
            @csrf
            <div class="pc-settings-head">
                <div>
                    <div class="pc-settings-title"><i class="fas fa-sliders mr-1.5"></i> Tune what counts as healthy</div>
                    <div class="pc-settings-sub">Pick a preset or fine-tune each threshold. Settings are saved per link.</div>
                </div>
            </div>

            <div class="pc-preset-grid">
                @foreach($pcPresets as $key => $meta)
                    <label class="pc-preset-card" :class="pcPreset === '{{ $key }}' ? 'is-active' : ''"
                           @click.prevent="applyPreset('{{ $key }}')">
                        <div class="pc-preset-top">
                            <input type="radio" name="preset" value="{{ $key }}" :checked="pcPreset === '{{ $key }}'" class="accent-purple-400">
                            <span class="pc-preset-label">{{ $meta['label'] }}</span>
                        </div>
                        <div class="pc-preset-desc">{{ $meta['description'] }}</div>
                    </label>
                @endforeach
                <label class="pc-preset-card" :class="pcPreset === 'custom' ? 'is-active' : ''"
                       @click.prevent="applyPreset('custom')">
                    <div class="pc-preset-top">
                        <input type="radio" name="preset" value="custom" :checked="pcPreset === 'custom'" class="accent-purple-400">
                        <span class="pc-preset-label">Custom</span>
                    </div>
                    <div class="pc-preset-desc">Hand-tune each threshold below.</div>
                </label>
            </div>

            <div class="pc-field-grid">
                {{-- CTR thresholds --}}
                <div class="pc-field-group">
                    <div class="pc-field-group-title">Click-through rate (% of visitors who click a block)</div>
                    <div class="pc-field-row">
                        <label>Critical below<input type="number" step="0.01" min="0" max="1" name="overrides[ctr_critical]" :value="(+pcValues.ctr_critical).toFixed(2)" @input="pcValues.ctr_critical=$event.target.value; onEdit()"></label>
                        <label>Warning below<input type="number" step="0.01" min="0" max="1" name="overrides[ctr_warning]" :value="(+pcValues.ctr_warning).toFixed(2)" @input="pcValues.ctr_warning=$event.target.value; onEdit()"></label>
                        <label>Excellent at<input type="number" step="0.01" min="0" max="1" name="overrides[ctr_excellent]" :value="(+pcValues.ctr_excellent).toFixed(2)" @input="pcValues.ctr_excellent=$event.target.value; onEdit()"></label>
                    </div>
                </div>
                {{-- Bounce thresholds --}}
                <div class="pc-field-group">
                    <div class="pc-field-group-title">Bounce rate (%)</div>
                    <div class="pc-field-row">
                        <label>Critical above<input type="number" step="1" min="0" max="100" name="overrides[bounce_critical]" :value="Math.round(pcValues.bounce_critical)" @input="pcValues.bounce_critical=$event.target.value; onEdit()"></label>
                        <label>Warning above<input type="number" step="1" min="0" max="100" name="overrides[bounce_warning]" :value="Math.round(pcValues.bounce_warning)" @input="pcValues.bounce_warning=$event.target.value; onEdit()"></label>
                        <label>Excellent below<input type="number" step="1" min="0" max="100" name="overrides[bounce_excellent]" :value="Math.round(pcValues.bounce_excellent)" @input="pcValues.bounce_excellent=$event.target.value; onEdit()"></label>
                    </div>
                </div>
                {{-- Engagement thresholds --}}
                <div class="pc-field-group">
                    <div class="pc-field-group-title">Avg. session length (seconds)</div>
                    <div class="pc-field-row">
                        <label>Low below<input type="number" step="1" min="1" max="600" name="overrides[engagement_low_seconds]" :value="Math.round(pcValues.engagement_low_seconds)" @input="pcValues.engagement_low_seconds=$event.target.value; onEdit()"></label>
                        <label>Excellent at<input type="number" step="1" min="1" max="600" name="overrides[engagement_excellent_seconds]" :value="Math.round(pcValues.engagement_excellent_seconds)" @input="pcValues.engagement_excellent_seconds=$event.target.value; onEdit()"></label>
                    </div>
                </div>
                {{-- Momentum thresholds --}}
                <div class="pc-field-group">
                    <div class="pc-field-group-title">Momentum vs previous period</div>
                    <div class="pc-field-row">
                        <label>Critical drop<input type="number" step="0.05" min="-1" max="0" name="overrides[momentum_drop_critical]" :value="(+pcValues.momentum_drop_critical).toFixed(2)" @input="pcValues.momentum_drop_critical=$event.target.value; onEdit()"></label>
                        <label>Warning drop<input type="number" step="0.05" min="-1" max="0" name="overrides[momentum_drop_warning]" :value="(+pcValues.momentum_drop_warning).toFixed(2)" @input="pcValues.momentum_drop_warning=$event.target.value; onEdit()"></label>
                        <label>Win at<input type="number" step="0.05" min="0" max="5" name="overrides[momentum_win_threshold]" :value="(+pcValues.momentum_win_threshold).toFixed(2)" @input="pcValues.momentum_win_threshold=$event.target.value; onEdit()"></label>
                    </div>
                </div>
            </div>

            <div class="pc-settings-foot">
                <button type="button" @click="pcSettingsOpen = false" class="pc-btn pc-btn-ghost">Cancel</button>
                <button type="submit" class="pc-btn pc-btn-save"><i class="fas fa-check mr-1"></i> Save thresholds</button>
            </div>
        </form>
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
        background: var(--bg-card);
    }
    .perf-coach .pc-gauge-inner {
        position: relative; z-index: 1; text-align: center; line-height: 1;
    }
    .perf-coach .pc-score {
        font-size: 1.75rem; font-weight: 800; color: var(--text-primary);
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
        border-radius: 8px; background: var(--bg-glass-hover); font-size: 14px; flex-shrink: 0;
    }
    .perf-coach .pc-insight-cta {
        display: inline-flex; align-items: center;
        padding: 5px 10px; border-radius: 999px;
        border: 1px solid;
        font-size: 11px; font-weight: 600; white-space: nowrap;
        background: var(--bg-glass);
        transition: background .15s ease;
    }
    .perf-coach .pc-insight-cta:hover { background: var(--bg-glass-hover); }
    .perf-coach .pc-threshold-chip {
        display: inline-flex; align-items: center; gap: 4px;
        margin-top: 6px;
        padding: 3px 8px; border-radius: 999px;
        border: 1px solid;
        font-size: 10px; font-weight: 600;
        letter-spacing: .02em;
        cursor: pointer; max-width: 100%;
        transition: filter .15s ease, transform .15s ease;
    }
    .perf-coach .pc-threshold-chip:hover { filter: brightness(1.15); transform: translateY(-1px); }
    .perf-coach .pc-threshold-chip-rule { text-transform: lowercase; }
    .perf-coach .pc-threshold-chip-sep { opacity: .5; margin: 0 2px; }
    .perf-coach .pc-threshold-chip-actual { opacity: .85; }
    .perf-coach .pc-spark {
        display: flex; align-items: center; gap: 8px;
    }
    .perf-coach .pc-spark svg {
        flex-shrink: 0;
        filter: drop-shadow(0 1px 2px rgba(0,0,0,0.25));
    }
    .perf-coach .pc-spark-point {
        cursor: pointer;
        transition: fill .12s ease, stroke .12s ease;
        outline: none;
    }
    .perf-coach .pc-spark-point:hover,
    .perf-coach .pc-spark-point:focus-visible {
        fill: var(--text-primary);
        stroke: var(--bg-body);
        stroke-width: 1;
    }
    .perf-coach .pc-spark-label {
        font-size: 10px; font-weight: 700; letter-spacing: .04em;
        text-transform: uppercase; white-space: nowrap;
    }
    .perf-coach .pc-breakdown {
        display: flex; align-items: flex-end; gap: 4px;
        padding: 4px 2px 2px;
    }
    .perf-coach .pc-bd-col {
        flex: 1 1 0; min-width: 0;
        display: flex; flex-direction: column; align-items: center; gap: 3px;
        cursor: help;
    }
    .perf-coach .pc-bd-track {
        width: 100%; height: 28px;
        background: var(--bg-glass-hover);
        border-radius: 3px; overflow: hidden;
        display: flex; align-items: flex-end;
        position: relative;
    }
    .perf-coach .pc-bd-fill {
        width: 100%; min-height: 2px;
        border-radius: 3px;
        transition: height .3s ease;
    }
    .perf-coach .pc-bd-label {
        font-size: 8.5px; font-weight: 700; letter-spacing: .05em;
        color: var(--text-faint);
    }
    .perf-coach .pc-bd-weak .pc-bd-track {
        outline: 1px solid rgba(252,165,165,0.55);
        outline-offset: 1px;
    }
    .perf-coach .pc-bd-weak .pc-bd-label {
        color: #fca5a5;
    }
    .perf-coach .pc-bd-caption {
        margin-top: 4px;
        font-size: 10px; letter-spacing: .02em;
    }
    @media (max-width: 1024px) {
        .perf-coach .pc-insight-cta { display: none; }
    }

    /* Settings drawer */
    .perf-coach { position: relative; }
    .perf-coach .pc-settings-btn {
        position: absolute; top: 10px; right: 10px; z-index: 2;
        display: inline-flex; align-items: center; gap: 6px;
        padding: 4px 10px; border-radius: 999px;
        border: 1px solid var(--border-glass);
        background: var(--bg-glass-hover);
        color: var(--text-muted);
        font-size: 11px; font-weight: 600;
        transition: background .15s ease, color .15s ease;
    }
    .perf-coach .pc-settings-btn:hover { background: var(--bg-glass-light); color: var(--text-primary); }
    .perf-coach .pc-settings-btn-label { white-space: nowrap; }
    .perf-coach .pc-settings {
        margin-top: 16px; padding-top: 14px;
        border-top: 1px solid var(--border-glass, rgba(148,163,184,0.2));
    }
    .perf-coach .pc-settings-head { margin-bottom: 10px; }
    .perf-coach .pc-settings-title {
        font-size: 13px; font-weight: 700; color: var(--text-primary);
    }
    .perf-coach .pc-settings-sub {
        font-size: 11px; color: var(--text-faint, #94a3b8); margin-top: 2px;
    }
    .perf-coach .pc-preset-grid {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 8px; margin-bottom: 14px;
    }
    .perf-coach .pc-preset-card {
        border: 1px solid var(--border-glass, rgba(148,163,184,0.25));
        border-radius: 10px; padding: 10px 12px;
        cursor: pointer; background: var(--bg-glass);
        transition: border-color .15s ease, background .15s ease;
    }
    .perf-coach .pc-preset-card:hover { background: var(--bg-glass-hover); }
    .perf-coach .pc-preset-card.is-active {
        border-color: rgba(139,92,246,0.6); background: var(--c-primary-soft);
    }
    .perf-coach .pc-preset-top {
        display: flex; align-items: center; gap: 8px; margin-bottom: 4px;
    }
    .perf-coach .pc-preset-label {
        font-size: 13px; font-weight: 700; color: var(--text-primary);
    }
    .perf-coach .pc-preset-desc {
        font-size: 11px; color: var(--text-faint, #94a3b8); line-height: 1.3;
    }
    .perf-coach .pc-field-grid {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 12px 16px;
    }
    .perf-coach .pc-field-group {
        background: var(--bg-glass);
        border: 1px solid var(--border-glass, rgba(148,163,184,0.18));
        border-radius: 10px; padding: 10px 12px;
    }
    .perf-coach .pc-field-group-title {
        font-size: 11px; font-weight: 600; color: var(--text-faint, #94a3b8);
        margin-bottom: 8px; letter-spacing: .02em;
    }
    .perf-coach .pc-field-row {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(84px, 1fr));
        gap: 8px;
    }
    .perf-coach .pc-field-row label {
        display: flex; flex-direction: column; gap: 3px;
        font-size: 10px; color: var(--text-faint, #94a3b8);
        font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
    }
    .perf-coach .pc-field-row input[type="number"] {
        width: 100%;
        padding: 5px 8px; border-radius: 6px;
        border: 1px solid var(--border-glass, rgba(148,163,184,0.25));
        background: var(--bg-glass-input); color: var(--text-primary);
        font-size: 13px; font-weight: 600; text-transform: none; letter-spacing: 0;
    }
    .perf-coach .pc-field-row input[type="number"]:focus {
        outline: none; border-color: var(--accent);
        box-shadow: 0 0 0 2px var(--accent-glow);
    }
    .perf-coach .pc-settings-foot {
        display: flex; justify-content: flex-end; gap: 8px; margin-top: 14px;
    }
    .perf-coach .pc-btn {
        display: inline-flex; align-items: center;
        padding: 7px 14px; border-radius: 8px;
        font-size: 12px; font-weight: 600;
        border: 1px solid transparent; cursor: pointer;
        transition: background .15s ease, border-color .15s ease;
    }
    .perf-coach .pc-btn-ghost {
        background: transparent; color: var(--text-faint, #94a3b8);
        border-color: var(--border-glass, rgba(148,163,184,0.25));
    }
    .perf-coach .pc-btn-ghost:hover { background: var(--bg-glass-hover); color: var(--text-primary); }
    .perf-coach .pc-btn-save {
        background: linear-gradient(135deg, rgba(139,92,246,0.9), rgba(99,102,241,0.9));
        /* White text intentional: button bg is the dark violet/indigo accent gradient in both light & dark modes. */
        color: #fff; border-color: rgba(139,92,246,0.6);
    }
    .perf-coach .pc-btn-save:hover { filter: brightness(1.1); }
    [x-cloak] { display: none !important; }
</style>
@endif
