@extends('admin.layouts.app')
@section('title', 'Site Assistant — Analytics')
@section('page-title', 'Site Assistant — Analytics')

@section('content')
@php
    $maxDay = collect($messagesPerDay)->max('count') ?: 1;
    $totalMsgs = collect($messagesPerDay)->sum('count');
    $maxRoute = $topRoutes->max('c') ?: 1;
@endphp
<div class="max-w-7xl space-y-6">
    <div class="text-sm text-white/60"><a href="{{ route('admin.site-assistant.edit') }}" class="hover:text-white">← Back to Site Assistant</a></div>

    <form method="GET" class="flex items-center gap-2">
        <label class="text-xs text-white/50">Window:</label>
        @foreach([7, 14, 30, 60, 90] as $d)
            <a href="?days={{ $d }}"
               class="px-3 py-1.5 rounded-lg text-xs border {{ $days === $d ? 'bg-purple-500 text-white border-purple-400' : 'bg-white/5 text-white/70 border-white/10 hover:bg-white/10' }}">
                {{ $d }}d
            </a>
        @endforeach
    </form>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
        <div class="glass rounded-2xl border border-white/10 p-5 text-center">
            <div class="text-2xl font-semibold text-white">{{ number_format($totalMsgs) }}</div>
            <div class="text-xs text-white/50 mt-1">User messages ({{ $days }}d)</div>
        </div>
        <div class="glass rounded-2xl border border-white/10 p-5 text-center">
            <div class="text-2xl font-semibold text-white">{{ number_format($totalConvs) }}</div>
            <div class="text-xs text-white/50 mt-1">Conversations</div>
        </div>
        <div class="glass rounded-2xl border border-white/10 p-5 text-center">
            <div class="text-2xl font-semibold text-white">
                {{ $deflectionRate === null ? '—' : $deflectionRate.'%' }}
            </div>
            <div class="text-xs text-white/50 mt-1">Deflection rate</div>
            <div class="text-[10px] text-white/30 mt-0.5">resolved without handoff</div>
        </div>
        <div class="glass rounded-2xl border border-white/10 p-5 text-center">
            <div class="text-2xl font-semibold text-white">
                {{ $handedOff > 0 ? number_format($avgTurnsToHandoff, 1) : '—' }}
            </div>
            <div class="text-xs text-white/50 mt-1">Avg turns → handoff</div>
            <div class="text-[10px] text-white/30 mt-0.5">{{ number_format($handedOff) }} handed off</div>
        </div>
        <div class="glass rounded-2xl border border-white/10 p-5 text-center"
             title="Of all partial/failed assistant streams in this window, the share that visitors clicked Retry on. A low retry rate (high abandon rate) usually means a flaky upstream call worth investigating.">
            <div class="text-2xl font-semibold text-white">
                {{ $cutoffRetryRate === null ? '—' : $cutoffRetryRate.'%' }}
            </div>
            <div class="text-xs text-white/50 mt-1">Cut-off retry rate</div>
            <div class="text-[10px] text-white/30 mt-0.5">
                {{ number_format($cutoffRetried) }} retried / {{ number_format($cutoffTotal) }} cut-offs
            </div>
        </div>
    </div>

    <div class="glass rounded-2xl border border-white/10 p-6">
        <div class="flex items-baseline justify-between mb-4">
            <h3 class="font-semibold text-white">Messages per day</h3>
            <span class="text-xs text-white/40">peak {{ number_format($maxDay) }}/day</span>
        </div>
        @if($totalMsgs === 0)
            <p class="text-sm text-white/40 text-center py-8">No assistant traffic in this window yet.</p>
        @else
            <div class="flex items-end gap-1 h-40">
                @foreach($messagesPerDay as $d)
                    @php $h = max(2, (int) round(($d['count'] / $maxDay) * 100)); @endphp
                    <div class="flex-1 flex flex-col items-center justify-end" title="{{ $d['day'] }} — {{ $d['count'] }} msgs">
                        <div class="w-full rounded-t bg-purple-500/70 hover:bg-purple-400 transition-all" style="height: {{ $h }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-between text-[10px] text-white/40 mt-2">
                <span>{{ $messagesPerDay[0]['day'] }}</span>
                <span>{{ $messagesPerDay[count($messagesPerDay)-1]['day'] }}</span>
            </div>
        @endif
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        <div class="glass rounded-2xl border border-white/10 p-6">
            <h3 class="font-semibold text-white mb-4">Top routes</h3>
            @if($topRoutes->isEmpty())
                <p class="text-sm text-white/40">No route data yet.</p>
            @else
                <div class="space-y-2">
                    @foreach($topRoutes as $r)
                        @php $w = max(4, (int) round(($r->c / $maxRoute) * 100)); @endphp
                        <div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-mono text-white/80 truncate pr-3">{{ $r->last_route }}</span>
                                <span class="text-white/50 whitespace-nowrap">{{ $r->c }} convs · {{ (int)$r->turns }} turns</span>
                            </div>
                            <div class="h-1.5 mt-1 rounded-full bg-white/5 overflow-hidden">
                                <div class="h-full bg-purple-500/70" style="width: {{ $w }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="glass rounded-2xl border border-white/10 p-6">
            <div class="flex items-baseline justify-between mb-4">
                <h3 class="font-semibold text-white">Suggestions</h3>
                <span class="text-xs text-white/40">questions that triggered handoff</span>
            </div>
            @if($suggestions->isEmpty())
                <p class="text-sm text-white/40">No handoffs in this window — nothing to learn from yet.</p>
            @else
                <p class="text-xs text-white/40 mb-3">Add page hints or response templates for these to deflect them next time.</p>
                <ol class="space-y-2">
                    @foreach($suggestions as $s)
                        <li class="flex items-start gap-3 text-sm">
                            <span class="shrink-0 inline-flex items-center justify-center min-w-[2rem] h-6 px-2 rounded-md bg-amber-500/15 text-amber-300 text-xs font-semibold">{{ $s['count'] }}×</span>
                            <span class="text-white/80">{{ $s['sample'] }}</span>
                        </li>
                    @endforeach
                </ol>
                <div class="mt-4 flex gap-2">
                    <a href="{{ route('admin.site-assistant.hints') }}" class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-xs text-white">Add a page hint →</a>
                    <a href="{{ route('admin.site-assistant.templates') }}" class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-xs text-white">Add a response template →</a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
