@extends('admin.layouts.app')
@section('title', 'Spam Rule Stats')
@section('page-title', 'Spam Rule Stats')

@section('content')
<div class="max-w-5xl">

    <div class="glass rounded-2xl border border-white/10 p-6 mb-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h2 class="text-lg font-semibold text-white/90">Platform-wide spam rule activity</h2>
                <p class="text-xs text-white/50 mt-1 max-w-2xl">
                    Aggregated counts across every account. Use this to spot which built-in keywords
                    are universally too aggressive (candidates for removal from the default list)
                    versus the ones doing useful work.
                </p>
            </div>
            <form method="GET" action="{{ route('admin.spam-rules.index') }}" class="flex items-center gap-2">
                <label class="text-[10px] uppercase font-bold tracking-wider text-white/50">Window</label>
                <select name="days" onchange="this.form.submit()"
                        class="bg-black/30 border border-white/15 rounded-lg px-2.5 py-1.5 text-sm text-white">
                    @foreach($allowedWindows as $w)
                        <option value="{{ $w }}" @selected($days === $w)>Last {{ $w }} days</option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="mt-5 grid grid-cols-2 sm:grid-cols-4 gap-3">
            @foreach([
                'blocked_keyword' => ['Blocked keyword', 'fa-key',          '#a855f7'],
                'too_many_links'  => ['Too many links',  'fa-link',         '#0ea5e9'],
                'rate_limit'      => ['Rate limit',      'fa-gauge-high',   '#f59e0b'],
                'honeypot'        => ['Honeypot',        'fa-spider',       '#ef4444'],
            ] as $code => $meta)
                <div class="rounded-xl px-4 py-3 border border-white/10 bg-white/[0.02]">
                    <div class="text-[10px] uppercase font-bold tracking-wider text-white/50">
                        <i class="fas {{ $meta[1] }} mr-1" style="color: {{ $meta[2] }};"></i>{{ $meta[0] }}
                    </div>
                    <div class="text-2xl font-bold text-white mt-1">{{ number_format($ruleHits[$code]) }}</div>
                    @if($totalRuleHits > 0)
                        <div class="text-[10px] text-white/40 mt-0.5">
                            {{ number_format($ruleHits[$code] / $totalRuleHits * 100, 1) }}% of all hits
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        @if($totalRuleHits === 0)
            <div class="mt-4 text-sm text-white/50 italic">
                No spam was flagged across any account in this window.
            </div>
        @else
            <div class="mt-3 text-xs text-white/40">
                {{ number_format($totalRuleHits) }} total {{ \Illuminate\Support\Str::plural('hit', $totalRuleHits) }}
                across form submissions and Link in Bio subscribers in the last {{ $days }} days.
            </div>
        @endif
    </div>

    <div class="glass rounded-2xl border border-white/10 p-6 mb-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: rgba(168,85,247,0.15);">
                <i class="fas fa-key text-purple-300"></i>
            </div>
            <div>
                <h3 class="text-base font-semibold text-white/90">Built-in keywords</h3>
                <p class="text-xs text-white/50">
                    Hits per default keyword from <code class="text-white/70">SpamChecker::BLOCKED_KEYWORDS</code>.
                    Zero-hit keywords across this window are strong candidates for removal.
                </p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[10px] uppercase font-bold tracking-wider text-white/50 border-b border-white/10">
                        <th class="py-2 pr-4">Keyword</th>
                        <th class="py-2 pr-4 text-right">Hits</th>
                        <th class="py-2 pl-4 w-1/2">Share</th>
                    </tr>
                </thead>
                <tbody>
                    @php $maxDefault = max(array_map(fn($r) => $r['count'], $defaultKeywordRows) ?: [0]); @endphp
                    @foreach($defaultKeywordRows as $row)
                        <tr class="border-b border-white/5">
                            <td class="py-2 pr-4 font-mono text-white/80">{{ $row['keyword'] }}</td>
                            <td class="py-2 pr-4 text-right font-semibold {{ $row['count'] === 0 ? 'text-white/30' : 'text-white' }}">
                                {{ number_format($row['count']) }}
                            </td>
                            <td class="py-2 pl-4">
                                <div class="h-2 bg-white/5 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full"
                                         style="width: {{ $maxDefault > 0 ? ($row['count'] / $maxDefault * 100) : 0 }}%; background: linear-gradient(90deg,#7c3aed,#a855f7);"></div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="glass rounded-2xl border border-white/10 p-6">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: rgba(245,158,11,0.15);">
                <i class="fas fa-user-pen text-amber-300"></i>
            </div>
            <div>
                <h3 class="text-base font-semibold text-white/90">Custom keywords (added by creators)</h3>
                <p class="text-xs text-white/50">
                    Keywords added by individual creators that fired in this window. Useful for spotting common
                    additions that might deserve a place in the platform default list.
                </p>
            </div>
        </div>

        @if(empty($customKeywordHits))
            <div class="text-sm text-white/50 italic">No custom-added keywords fired in this window.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[10px] uppercase font-bold tracking-wider text-white/50 border-b border-white/10">
                            <th class="py-2 pr-4">Keyword</th>
                            <th class="py-2 pr-4 text-right">Hits</th>
                            <th class="py-2 pl-4 w-1/2">Share</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $maxCustom = max($customKeywordHits ?: [0]); @endphp
                        @foreach($customKeywordHits as $kw => $count)
                            <tr class="border-b border-white/5">
                                <td class="py-2 pr-4 font-mono text-white/80">{{ $kw }}</td>
                                <td class="py-2 pr-4 text-right font-semibold text-white">{{ number_format($count) }}</td>
                                <td class="py-2 pl-4">
                                    <div class="h-2 bg-white/5 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full"
                                             style="width: {{ $maxCustom > 0 ? ($count / $maxCustom * 100) : 0 }}%; background: linear-gradient(90deg,#d97706,#f59e0b);"></div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
