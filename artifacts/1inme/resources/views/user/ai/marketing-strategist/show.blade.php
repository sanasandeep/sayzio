@extends('user.layouts.app')
@section('title', $strategy->title)

@php $plan = (array) ($strategy->strategy ?? []); @endphp

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">
    @include('user.ai._partials.header', [
        'kicker'   => 'AI · Marketing Strategist',
        'title'    => $strategy->title,
        'subtitle' => $strategy->goalSummary(200),
        'balance'  => $balance,
    ])

    @if(session('status'))
        <div class="rounded-xl border border-emerald-500/25 bg-emerald-500/[0.08] text-emerald-200 text-sm px-4 py-3 mb-4"><i class="fas fa-check-circle mr-1.5"></i>{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-xl border border-red-500/25 bg-red-500/[0.08] text-red-200 text-sm px-4 py-3 mb-4"><i class="fas fa-triangle-exclamation mr-1.5"></i>{{ session('error') }}</div>
    @endif

    <div class="flex flex-wrap items-center gap-2 mb-6">
        <a href="{{ route('user.ai.marketing-strategist.index') }}"
           class="px-3 py-1.5 rounded-lg bg-white/5 text-white/70 hover:bg-white/10 text-xs">
            <i class="fas fa-arrow-left mr-1"></i> All strategies
        </a>

        {{-- Download split-button: free tiers + premium AI PDF (costs coins) --}}
        <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
            <button type="button" @click="open = !open"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/5 text-white/70 hover:bg-white/10 text-xs">
                <i class="fas fa-download"></i> Download <i class="fas fa-chevron-down text-[9px] opacity-70"></i>
            </button>
            <div x-show="open" x-cloak x-transition.opacity
                 class="absolute left-0 mt-1.5 w-72 z-30 rounded-xl border border-white/10 bg-slate-900/95 backdrop-blur-xl shadow-xl shadow-black/40 p-1.5">
                <a href="{{ route('user.ai.marketing-strategist.export', $strategy->id) }}"
                   class="flex items-start gap-2.5 px-2.5 py-2 rounded-lg hover:bg-white/[0.06] transition">
                    <i class="fas fa-file-lines text-white/50 mt-0.5 w-4 text-center"></i>
                    <span class="min-w-0"><span class="block text-xs text-white font-medium">Markdown <span class="text-emerald-300/80 font-normal">· free</span></span><span class="block text-[11px] text-white/45">Plain text, great for editing.</span></span>
                </a>
                <a href="{{ route('user.ai.marketing-strategist.export', $strategy->id) }}?format=pdf"
                   class="flex items-start gap-2.5 px-2.5 py-2 rounded-lg hover:bg-white/[0.06] transition">
                    <i class="fas fa-file-pdf text-white/50 mt-0.5 w-4 text-center"></i>
                    <span class="min-w-0"><span class="block text-xs text-white font-medium">Rich PDF <span class="text-emerald-300/80 font-normal">· free</span></span><span class="block text-[11px] text-white/45">Branded, print-ready report.</span></span>
                </a>
                <a href="{{ route('user.ai.marketing-strategist.export', $strategy->id) }}?format=csv"
                   class="flex items-start gap-2.5 px-2.5 py-2 rounded-lg hover:bg-white/[0.06] transition">
                    <i class="fas fa-file-csv text-white/50 mt-0.5 w-4 text-center"></i>
                    <span class="min-w-0"><span class="block text-xs text-white font-medium">CSV <span class="text-emerald-300/80 font-normal">· free</span></span><span class="block text-[11px] text-white/45">Scores, forecast &amp; actions as data.</span></span>
                </a>
                <div class="border-t border-white/10 my-1"></div>
                <form method="POST" action="{{ route('user.ai.marketing-strategist.report', $strategy->id) }}"
                      onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').innerHTML='&lt;i class=\'fas fa-circle-notch fa-spin\'&gt;&lt;/i&gt; Generating…';">
                    @csrf
                    <button type="submit" class="w-full flex items-start gap-2.5 px-2.5 py-2 rounded-lg hover:bg-blue-500/[0.12] transition text-left">
                        <i class="fas fa-wand-magic-sparkles text-blue-300 mt-0.5 w-4 text-center"></i>
                        <span class="min-w-0"><span class="block text-xs text-white font-medium">Premium AI PDF <span class="text-amber-300/90 font-normal">· costs coins</span></span><span class="block text-[11px] text-white/45">Fresh AI executive summary on top of the report.</span></span>
                    </button>
                </form>
            </div>
        </div>

        {{-- Share link --}}
        <div x-data="msShare({ shareUrl: @js($strategy->isShared() ? route('public.ai-report', $strategy->share_token) : ''), shareEndpoint: @js(route('user.ai.marketing-strategist.share', $strategy->id)), unshareEndpoint: @js(route('user.ai.marketing-strategist.unshare', $strategy->id)) })"
             class="flex items-center gap-2">
            <button type="button" @click="toggle()" :disabled="busy"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs transition disabled:opacity-60"
                    :class="url ? 'bg-emerald-500/15 text-emerald-200 hover:bg-emerald-500/25' : 'bg-white/5 text-white/70 hover:bg-white/10'">
                <i class="fas" :class="busy ? 'fa-circle-notch fa-spin' : (url ? 'fa-link' : 'fa-share-nodes')"></i>
                <span x-text="url ? 'Sharing on' : 'Share link'"></span>
            </button>
            <template x-if="url">
                <button type="button" @click="copy()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/5 text-white/70 hover:bg-white/10 text-xs">
                    <i class="fas" :class="copied ? 'fa-check text-emerald-300' : 'fa-copy'"></i>
                    <span x-text="copied ? 'Copied' : 'Copy'"></span>
                </button>
            </template>
        </div>

        <form method="POST" action="{{ route('user.ai.marketing-strategist.destroy', $strategy->id) }}"
              onsubmit="return confirm('Delete this strategy? This cannot be undone.');" class="ml-auto">
            @csrf @method('DELETE')
            <button class="px-3 py-1.5 rounded-lg bg-red-500/10 text-red-300 hover:bg-red-500/20 text-xs">
                <i class="fas fa-trash mr-1"></i> Delete
            </button>
        </form>
    </div>

    {{-- Summary --}}
    @if(!empty($plan['summary']))
        <div class="rounded-2xl border border-blue-500/20 bg-blue-500/[0.06] p-5 mb-6">
            <p class="text-sm text-blue-100/90 leading-relaxed">{{ $plan['summary'] }}</p>
        </div>
    @endif

    @php
        $scorecard  = (array) ($strategy->scorecard ?? []);
        $diagnosis  = (array) ($strategy->diagnosis['narrative'] ?? []);
        $forecast   = (array) ($strategy->forecast ?? []);
        $bands      = (array) ($forecast['scenarios'] ?? $forecast['bands'] ?? []);
        $competitor = (array) ($strategy->competitor_analysis ?? []);
        $outcome    = (array) ($strategy->outcome ?? []);
        $scoreHistory = $strategy->exists ? $strategy->scores()->get() : collect();
        $axisMeta = [
            'reach'       => ['label' => 'Reach',       'icon' => 'fa-signal',       'color' => 'sky'],
            'engagement'  => ['label' => 'Engagement',  'icon' => 'fa-heart',        'color' => 'rose'],
            'conversion'  => ['label' => 'Conversion',  'icon' => 'fa-bullseye',     'color' => 'emerald'],
            'consistency' => ['label' => 'Consistency', 'icon' => 'fa-calendar-check','color' => 'amber'],
        ];
        $ring = function (int $v) {
            $v = max(0, min(100, $v));
            $tone = $v >= 70 ? '#34d399' : ($v >= 40 ? '#fbbf24' : '#f87171');
            return 'background:conic-gradient(' . $tone . ' ' . ($v * 3.6) . 'deg, rgba(255,255,255,0.08) 0deg);';
        };
    @endphp

    {{-- Marketing scorecard (free, PHP-only, re-scorable) --}}
    @if($scorecard)
        <section class="mb-8 rounded-2xl border border-white/10 bg-white/[0.03] p-5">
            <div class="flex items-start justify-between gap-3 mb-4 flex-wrap">
                <div>
                    <h2 class="text-white font-semibold"><i class="fas fa-heart-pulse text-emerald-300 mr-1"></i> Marketing health</h2>
                    <p class="text-xs text-white/45 mt-0.5">Scored 0–100 from your own data. Re-score any time — it's free.</p>
                </div>
                <form method="POST" action="{{ route('user.ai.marketing-strategist.rescore', $strategy->id) }}">
                    @csrf
                    <button class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/5 text-white/70 hover:bg-white/10 text-xs">
                        <i class="fas fa-rotate"></i> Re-score
                    </button>
                </form>
            </div>

            <div class="flex flex-col sm:flex-row items-center gap-5">
                <div class="shrink-0 grid place-items-center h-28 w-28 rounded-full" style="{{ $ring((int) ($scorecard['overall'] ?? 0)) }}">
                    <div class="grid place-items-center h-[92px] w-[92px] rounded-full bg-slate-900">
                        <span class="text-3xl font-bold text-white leading-none">{{ (int) ($scorecard['overall'] ?? 0) }}</span>
                        <span class="text-[10px] text-white/40 mt-0.5">/ 100</span>
                    </div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 flex-1 w-full">
                    @foreach($axisMeta as $k => $meta)
                        @php $val = (int) ($scorecard[$k] ?? 0); @endphp
                        <div class="rounded-xl border border-white/10 bg-white/[0.02] p-3">
                            <div class="flex items-center gap-1.5 text-[11px] text-white/50 mb-1.5"><i class="fas {{ $meta['icon'] }} text-{{ $meta['color'] }}-300"></i> {{ $meta['label'] }}</div>
                            <div class="text-xl font-bold text-white leading-none">{{ $val }}</div>
                            <div class="mt-2 h-1.5 rounded-full bg-white/10 overflow-hidden">
                                <div class="h-full rounded-full bg-{{ $meta['color'] }}-400" style="width: {{ max(0, min(100, $val)) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            @if(!empty($scorecard['reasons']))
                <ul class="mt-4 space-y-1.5">
                    @foreach((array) $scorecard['reasons'] as $reason)
                        <li class="flex items-start gap-2 text-xs text-white/60"><i class="fas fa-circle-info text-blue-300/70 mt-0.5 text-[10px]"></i> {{ $reason }}</li>
                    @endforeach
                </ul>
            @endif

            @if($scoreHistory->count() > 1)
                @php
                    $vals = $scoreHistory->map(fn ($s) => (int) $s->overall)->all();
                    $n = count($vals); $mn = min($vals); $mx = max($vals); $rng = max(1, $mx - $mn);
                    $pts = [];
                    foreach ($vals as $i => $v) {
                        $x = $n > 1 ? round($i / ($n - 1) * 100, 2) : 0;
                        $y = round(30 - (($v - $mn) / $rng) * 26 - 2, 2);
                        $pts[] = $x . ',' . $y;
                    }
                @endphp
                <div class="mt-4 pt-4 border-t border-white/[0.07]">
                    <div class="flex items-center justify-between text-[11px] text-white/45 mb-2"><span><i class="fas fa-chart-line mr-1"></i> Overall score over time</span><span>{{ $mn }}–{{ $mx }}</span></div>
                    <svg viewBox="0 0 100 30" preserveAspectRatio="none" class="w-full h-16">
                        <polyline points="{{ implode(' ', $pts) }}" fill="none" stroke="#60a5fa" stroke-width="1.2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
                    </svg>
                </div>
            @endif
        </section>
    @endif

    {{-- Diagnosis (grounded narrative) --}}
    @if($diagnosis)
        <section class="mb-8 rounded-2xl border border-white/10 bg-white/[0.03] p-5">
            <h2 class="text-white font-semibold mb-3"><i class="fas fa-stethoscope text-indigo-300 mr-1"></i> Diagnosis</h2>
            <ul class="space-y-2">
                @foreach($diagnosis as $line)
                    <li class="flex items-start gap-2 text-sm text-white/70"><i class="fas fa-angle-right text-white/30 mt-1 text-xs"></i> {{ $line }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Forecast (three scenarios) --}}
    @if($bands)
        @php $fMetric = (string) ($forecast['metric'] ?? $strategy->goal_metric ?? 'clicks'); @endphp
        <section class="mb-8 rounded-2xl border border-white/10 bg-white/[0.03] p-5">
            <h2 class="text-white font-semibold mb-1"><i class="fas fa-chart-simple text-sky-300 mr-1"></i> Forecast <span class="text-white/40 font-normal text-sm">— {{ $fMetric }}</span></h2>
            <p class="text-xs text-white/45 mb-4">Projected outcomes across three scenarios if you follow the plan.</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                @php
                    $bandTone = ['pessimistic' => 'rose', 'realistic' => 'sky', 'optimistic' => 'emerald'];
                @endphp
                @foreach($bands as $name => $band)
                    @php
                        $band = (array) $band;
                        $label = is_string($name) ? ucfirst($name) : ucfirst((string) ($band['label'] ?? 'Scenario'));
                        $val = (int) ($band['value'] ?? $band['projected'] ?? 0);
                        $delta = (int) ($band['delta_pct'] ?? 0);
                        $tone = $bandTone[strtolower((string) $name)] ?? 'sky';
                    @endphp
                    <div class="rounded-xl border border-{{ $tone }}-400/25 bg-{{ $tone }}-500/[0.06] p-4 text-center">
                        <div class="text-[11px] uppercase tracking-wide text-{{ $tone }}-200/80 font-semibold">{{ $label }}</div>
                        <div class="text-2xl font-bold text-white mt-1.5 leading-none">{{ number_format($val) }}</div>
                        @if($delta)
                            <div class="text-[11px] mt-1.5 {{ $delta >= 0 ? 'text-emerald-300' : 'text-rose-300' }}">{{ $delta > 0 ? '+' : '' }}{{ $delta }}% vs now</div>
                        @endif
                    </div>
                @endforeach
            </div>
            @if(!empty($forecast['narrative']))
                <p class="text-xs text-white/55 mt-3 leading-relaxed">{{ $forecast['narrative'] }}</p>
            @endif
        </section>
    @endif

    {{-- Suggestions --}}
    @if($suggestions->isNotEmpty())
        <section class="mb-8">
            <h2 class="text-white font-semibold mb-3"><i class="fas fa-bolt text-amber-300 mr-1"></i> One-click actions</h2>
            <ul class="space-y-2" id="ms-suggestions">
                @foreach($suggestions as $sug)
                    <li class="rounded-xl border border-white/10 bg-white/[0.03] p-4" data-suggestion="{{ $sug->id }}">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-blue-500/15 text-blue-200">{{ $sug->typeLabel() }}</span>
                                    <span class="text-sm text-white font-medium truncate">{{ $sug->title }}</span>
                                </div>
                                @if($sug->description)
                                    <p class="text-xs text-white/50 mt-1">{{ $sug->description }}</p>
                                @endif
                                <p class="text-xs mt-2 ms-feedback" data-feedback></p>
                            </div>
                            <div class="shrink-0 flex items-center gap-2" data-actions>
                                @if($sug->status === \App\Modules\User\Models\MarketingStrategySuggestion::STATUS_APPLIED)
                                    <span class="text-xs text-emerald-300"><i class="fas fa-check mr-1"></i>Applied</span>
                                @elseif($sug->status === \App\Modules\User\Models\MarketingStrategySuggestion::STATUS_DISMISSED)
                                    <span class="text-xs text-white/40">Dismissed</span>
                                @else
                                    <button type="button" data-apply
                                            data-confirm="Apply &quot;{{ $sug->title }}&quot;? This makes a real change to your account ({{ strtolower($sug->typeLabel()) }})."
                                            class="px-3 py-1.5 rounded-lg bg-blue-600 text-white text-xs font-semibold hover:bg-blue-700">
                                        Apply
                                    </button>
                                    <button type="button" data-dismiss
                                            class="px-3 py-1.5 rounded-lg bg-white/5 text-white/60 text-xs hover:bg-white/10">
                                        Dismiss
                                    </button>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Plays --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        @php
            $renderPlays = function ($plays, $paid) {
                return $plays;
            };
        @endphp
        <section>
            <h2 class="text-white font-semibold mb-3"><i class="fas fa-seedling text-emerald-300 mr-1"></i> Organic plan</h2>
            @forelse((array) ($plan['organic'] ?? []) as $play)
                @include('user.ai.marketing-strategist._play', ['play' => $play, 'paid' => false])
            @empty
                <p class="text-sm text-white/40">No organic plays generated.</p>
            @endforelse
        </section>
        <section>
            <h2 class="text-white font-semibold mb-3"><i class="fas fa-rocket text-sky-300 mr-1"></i> Paid plan</h2>
            @forelse((array) ($plan['paid'] ?? []) as $play)
                @include('user.ai.marketing-strategist._play', ['play' => $play, 'paid' => true])
            @empty
                <p class="text-sm text-white/40">No paid plays generated.</p>
            @endforelse
        </section>
    </div>

    {{-- Execution plan (multi-month, agency-style) --}}
    @php
        $exec       = (array) ($plan['execution_plan'] ?? []);
        $execMonths = (array) ($exec['months'] ?? []);
        $execPhases = (array) ($exec['phases'] ?? []);
        $execPeriod = (int) ($exec['period_months'] ?? count($execMonths));
    @endphp
    @if($exec && (!empty($execMonths) || !empty($exec['overview']) || !empty($execPhases)))
        <section class="mb-8">
            <div class="flex flex-wrap items-center gap-2 mb-3">
                <h2 class="text-white font-semibold"><i class="fas fa-calendar-days text-indigo-300 mr-1"></i> Execution plan</h2>
                @if($execPeriod > 0)
                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-indigo-500/15 text-indigo-200 border border-indigo-400/25">{{ $execPeriod }} {{ \Illuminate\Support\Str::plural('month', $execPeriod) }}</span>
                @endif
            </div>

            @if(!empty($exec['overview']))
                <p class="text-sm text-white/60 mb-3 rounded-2xl border border-white/10 bg-white/[0.03] p-4">{{ $exec['overview'] }}</p>
            @endif

            @if(!empty($execPhases))
                <div class="flex flex-wrap gap-2 mb-4">
                    @foreach($execPhases as $ph)
                        <span class="text-[11px] px-3 py-1 rounded-full bg-white/5 text-white/60 border border-white/10">{{ $ph }}</span>
                    @endforeach
                </div>
            @endif

            <div class="space-y-3">
                @foreach($execMonths as $month)
                    @include('user.ai.marketing-strategist._month', ['month' => $month])
                @endforeach
            </div>
        </section>
    @endif

    {{-- KPIs --}}
    @if(!empty($plan['kpis']))
        <section class="mb-8 rounded-2xl border border-white/10 bg-white/[0.03] p-5">
            <h2 class="text-white font-semibold mb-3"><i class="fas fa-chart-line text-blue-300 mr-1"></i> KPIs to watch</h2>
            <ul class="flex flex-wrap gap-2">
                @foreach((array) $plan['kpis'] as $kpi)
                    <li class="text-xs px-3 py-1.5 rounded-full bg-white/5 text-white/70">{{ $kpi }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Competitor landscape (depth 5) --}}
    @if($competitor)
        <section class="mb-8 rounded-2xl border border-white/10 bg-white/[0.03] p-5">
            <h2 class="text-white font-semibold mb-3"><i class="fas fa-chess text-indigo-300 mr-1"></i> Competitor landscape</h2>
            @if(!empty($competitor['summary']))
                <p class="text-sm text-white/70 mb-4 leading-relaxed">{{ $competitor['summary'] }}</p>
            @endif
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                @foreach(['positioning' => ['Positioning', 'fa-map-pin', 'sky'], 'gaps' => ['Gaps to exploit', 'fa-door-open', 'emerald'], 'moves' => ['Recommended moves', 'fa-arrow-trend-up', 'amber']] as $k => $meta)
                    @php $items = (array) ($competitor[$k] ?? []); @endphp
                    @if($items)
                        <div class="rounded-xl border border-white/10 bg-white/[0.02] p-4">
                            <div class="flex items-center gap-1.5 text-xs text-{{ $meta[2] }}-200 font-semibold mb-2.5"><i class="fas {{ $meta[1] }}"></i> {{ $meta[0] }}</div>
                            <ul class="space-y-1.5">
                                @foreach($items as $it)
                                    <li class="flex items-start gap-2 text-xs text-white/60"><i class="fas fa-angle-right text-white/25 mt-0.5 text-[10px]"></i> {{ $it }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach
            </div>
        </section>
    @endif

    {{-- Outcome tracking (free, PHP-only) --}}
    <section class="mb-8 rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <div class="flex items-start justify-between gap-3 mb-3 flex-wrap">
            <div>
                <h2 class="text-white font-semibold"><i class="fas fa-flag-checkered text-emerald-300 mr-1"></i> Did it work?</h2>
                <p class="text-xs text-white/45 mt-0.5">Track how your goal metric moved since this plan. Free to refresh.</p>
            </div>
            <form method="POST" action="{{ route('user.ai.marketing-strategist.outcome', $strategy->id) }}">
                @csrf
                <button class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/5 text-white/70 hover:bg-white/10 text-xs">
                    <i class="fas fa-rotate"></i> Refresh outcome
                </button>
            </form>
        </div>
        @if($outcome)
            @php
                $oDelta = (int) ($outcome['delta_pct'] ?? 0);
                $oMetric = (string) ($outcome['goal_metric'] ?? $strategy->goal_metric ?? 'clicks');
                $oVerdict = (string) ($outcome['verdict'] ?? 'measured');
                $vTone = $oDelta > 0 ? 'emerald' : ($oDelta < 0 ? 'rose' : 'white');
            @endphp
            <div class="flex flex-wrap items-center gap-4">
                <div class="text-center">
                    <div class="text-3xl font-bold {{ $oDelta > 0 ? 'text-emerald-300' : ($oDelta < 0 ? 'text-rose-300' : 'text-white') }} leading-none">{{ $oDelta > 0 ? '+' : '' }}{{ $oDelta }}%</div>
                    <div class="text-[11px] text-white/45 mt-1 capitalize">{{ $oVerdict }}</div>
                </div>
                <div class="text-sm text-white/60">
                    <span class="capitalize text-white/80">{{ $oMetric }}</span> moved from
                    <span class="text-white font-medium">{{ number_format((int) ($outcome['baseline_value'] ?? 0)) }}</span> to
                    <span class="text-white font-medium">{{ number_format((int) ($outcome['current_value'] ?? 0)) }}</span>
                    over {{ (int) ($outcome['window_days'] ?? 0) }} days.
                </div>
            </div>
        @else
            <p class="text-sm text-white/40">No outcome measured yet. Apply a suggestion or two, let some activity accrue, then refresh.</p>
        @endif
    </section>

    {{-- Chat refine --}}
    <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <h2 class="text-white font-semibold mb-1"><i class="fas fa-comments text-blue-300 mr-1"></i> Refine with the strategist</h2>
        <p class="text-xs text-white/50 mb-4">Ask follow-up questions or request changes. Replies are metered from your coin wallet.</p>

        <div id="ms-chat" class="space-y-3 mb-4 max-h-[50vh] overflow-y-auto">
            @foreach($messages as $m)
                <div class="flex {{ $m->role === 'user' ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-[85%] rounded-2xl px-4 py-2.5 text-sm whitespace-pre-wrap
                                {{ $m->role === 'user' ? 'bg-blue-600 text-white' : 'bg-white/[0.06] text-white/90' }}">
                        {{ $m->content }}
                    </div>
                </div>
            @endforeach
        </div>

        <form id="ms-chat-form" class="flex items-end gap-2">
            <textarea id="ms-chat-input" rows="1" maxlength="4000" required
                      placeholder="e.g. Make the paid plan cheaper, or focus organic on TikTok."
                      class="flex-1 bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm placeholder-white/30 resize-none focus:ring-blue-500 focus:border-blue-500"></textarea>
            <button type="submit" id="ms-chat-send"
                    class="px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 disabled:opacity-60">
                Send
            </button>
        </form>
    </section>
</div>

<script>
// Share-link toggle for the report (mint / revoke / copy). Registered globally
// so Alpine's x-data can resolve it on init.
window.msShare = function (opts) {
    return {
        url: opts.shareUrl || '',
        busy: false,
        copied: false,
        async toggle() {
            if (this.busy) return;
            this.busy = true;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            try {
                if (this.url) {
                    const res = await fetch(opts.unshareEndpoint, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    });
                    if (res.ok) { this.url = ''; this.copied = false; }
                } else {
                    const res = await fetch(opts.shareEndpoint, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    });
                    const data = await res.json();
                    if (res.ok && data.url) this.url = data.url;
                }
            } catch (e) { /* leave state unchanged on failure */ }
            finally { this.busy = false; }
        },
        async copy() {
            try {
                await navigator.clipboard.writeText(this.url);
                this.copied = true;
                setTimeout(() => { this.copied = false; }, 1800);
            } catch (e) {
                window.prompt('Copy this link:', this.url);
            }
        },
    };
};

(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // ── Suggestions: apply / dismiss ──────────────────────────────
    const applyUrl = function (id) {
        return @json(url('/user/ai/marketing-strategist/suggestions')) + '/' + id + '/apply';
    };
    const dismissUrl = function (id) {
        return @json(url('/user/ai/marketing-strategist/suggestions')) + '/' + id + '/dismiss';
    };

    document.getElementById('ms-suggestions')?.addEventListener('click', async function (e) {
        const applyBtn = e.target.closest('[data-apply]');
        const dismissBtn = e.target.closest('[data-dismiss]');
        if (!applyBtn && !dismissBtn) return;

        const li = e.target.closest('[data-suggestion]');
        const id = li?.getAttribute('data-suggestion');
        const actions = li?.querySelector('[data-actions]');
        const feedback = li?.querySelector('[data-feedback]');
        if (!id) return;

        // Applying performs a real, state-changing action — confirm first.
        if (applyBtn) {
            const msg = applyBtn.getAttribute('data-confirm')
                || 'Apply this suggestion? This makes a real change to your account.';
            if (!window.confirm(msg)) return;
        }

        const btn = applyBtn || dismissBtn;
        btn.disabled = true;
        const original = btn.textContent;
        btn.textContent = applyBtn ? 'Applying…' : 'Dismissing…';

        try {
            const res = await fetch(applyBtn ? applyUrl(id) : dismissUrl(id), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: applyBtn ? JSON.stringify({ confirm: true }) : undefined,
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data?.error?.message || 'Action failed.');

            if (applyBtn) {
                let html = '<span class="text-emerald-300"><i class="fas fa-check mr-1"></i>Applied</span>';
                if (data.url) html += ' <a href="' + data.url + '" class="text-blue-300 underline ml-2">Open</a>';
                actions.innerHTML = html;
                if (feedback) feedback.innerHTML = '<span class="text-emerald-300/80">' + (data.message || '') + '</span>';
            } else {
                actions.innerHTML = '<span class="text-xs text-white/40">Dismissed</span>';
            }
        } catch (err) {
            btn.disabled = false;
            btn.textContent = original;
            if (feedback) feedback.innerHTML = '<span class="text-red-300">' + (err.message || 'Failed') + '</span>';
        }
    });

    // ── Chat refine (SSE stream) ──────────────────────────────────
    const chat  = document.getElementById('ms-chat');
    const form  = document.getElementById('ms-chat-form');
    const input = document.getElementById('ms-chat-input');
    const send  = document.getElementById('ms-chat-send');
    const streamUrl = @json(route('user.ai.marketing-strategist.chat', $strategy->id));

    const bubble = function (role, text) {
        const wrap = document.createElement('div');
        wrap.className = 'flex ' + (role === 'user' ? 'justify-end' : 'justify-start');
        const inner = document.createElement('div');
        inner.className = 'max-w-[85%] rounded-2xl px-4 py-2.5 text-sm whitespace-pre-wrap ' +
            (role === 'user' ? 'bg-blue-600 text-white' : 'bg-white/[0.06] text-white/90');
        inner.textContent = text;
        wrap.appendChild(inner);
        chat.appendChild(wrap);
        chat.scrollTop = chat.scrollHeight;
        return inner;
    };

    form?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const msg = (input.value || '').trim();
        if (!msg) return;

        bubble('user', msg);
        input.value = '';
        send.disabled = true;
        const out = bubble('assistant', '…');
        let acc = '';

        try {
            const res = await fetch(streamUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/event-stream',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'message=' + encodeURIComponent(msg),
            });

            if (!res.ok || !res.body) {
                let m = 'The strategist could not reply right now.';
                try { const j = await res.json(); m = j?.error?.message || m; } catch (_) {}
                out.textContent = m;
                send.disabled = false;
                return;
            }

            const reader = res.body.getReader();
            const decoder = new TextDecoder();
            let buf = '';

            while (true) {
                const { value, done } = await reader.read();
                if (done) break;
                buf += decoder.decode(value, { stream: true });
                const frames = buf.split('\n\n');
                buf = frames.pop() || '';
                for (const frame of frames) {
                    const evMatch = frame.match(/^event: (.+)$/m);
                    const dataMatch = frame.match(/^data: (.+)$/m);
                    if (!dataMatch) continue;
                    const ev = evMatch ? evMatch[1] : 'message';
                    let payload = {};
                    try { payload = JSON.parse(dataMatch[1]); } catch (_) {}

                    if (ev === 'token') {
                        if (acc === '') out.textContent = '';
                        acc += payload.delta || '';
                        out.textContent = acc;
                        chat.scrollTop = chat.scrollHeight;
                    } else if (ev === 'error') {
                        out.textContent = payload.message || 'Something went wrong.';
                    } else if (ev === 'done') {
                        if (payload.message?.content) out.textContent = payload.message.content;
                    }
                }
            }
        } catch (err) {
            out.textContent = 'Connection lost. Please try again.';
        } finally {
            send.disabled = false;
        }
    });
})();
</script>
@endsection
