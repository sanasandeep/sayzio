@extends('admin.layouts.app')
@section('title', 'Site Assistant: Analytics')
@section('page-title', 'Site Assistant, Analytics')

@section('content')
@php
    $maxDay = collect($messagesPerDay)->max('count') ?: 1;
    $totalMsgs = collect($messagesPerDay)->sum('count');
    $maxRoute = $topRoutes->max('c') ?: 1;
@endphp
<div class="max-w-7xl space-y-6">
    <div class="text-sm text-white/60 ak-muted"><a href="{{ route('admin.site-assistant.edit') }}" class="hover:text-white">← Back to Site Assistant</a></div>

    <form method="GET" class="flex items-center gap-2">
        <label class="text-xs text-white/50 ak-muted">Window:</label>
        @foreach([7, 14, 30, 60, 90] as $d)
            <a href="?days={{ $d }}"
               class="px-3 py-1.5 rounded-lg text-xs border {{ $days === $d ? 'bg-indigo-500 text-white border-indigo-400' : 'bg-white/5 text-white/70 border-white/10 hover:bg-white/10 ak-muted' }}">
                {{ $d }}d
            </a>
        @endforeach
    </form>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
        <div class="glass rounded-2xl border border-white/10 p-5 text-center">
            <div class="text-2xl font-semibold text-white ak-strong">{{ number_format($totalMsgs) }}</div>
            <div class="text-xs text-white/50 mt-1 ak-muted">User messages ({{ $days }}d)</div>
        </div>
        <div class="glass rounded-2xl border border-white/10 p-5 text-center">
            <div class="text-2xl font-semibold text-white ak-strong">{{ number_format($totalConvs) }}</div>
            <div class="text-xs text-white/50 mt-1 ak-muted">Conversations</div>
        </div>
        <div class="glass rounded-2xl border border-white/10 p-5 text-center">
            <div class="text-2xl font-semibold text-white ak-strong">
                {{ $deflectionRate === null ? '-' : $deflectionRate.'%' }}
            </div>
            <div class="text-xs text-white/50 mt-1 ak-muted">Deflection rate</div>
            <div class="text-[10px] text-white/30 mt-0.5 ak-note">resolved without handoff</div>
        </div>
        <div class="glass rounded-2xl border border-white/10 p-5 text-center">
            <div class="text-2xl font-semibold text-white ak-strong">
                {{ $handedOff > 0 ? number_format($avgTurnsToHandoff, 1) : '—' }}
            </div>
            <div class="text-xs text-white/50 mt-1 ak-muted">Avg turns → handoff</div>
            <div class="text-[10px] text-white/30 mt-0.5 ak-note">{{ number_format($handedOff) }} handed off</div>
        </div>
        <div class="glass rounded-2xl border border-white/10 p-5 text-center"
             title="Of all partial/failed assistant streams in this window, the share that visitors clicked Retry on. A low retry rate (high abandon rate) usually means a flaky upstream call worth investigating.">
            <div class="text-2xl font-semibold text-white ak-strong">
                {{ $cutoffRetryRate === null ? '-' : $cutoffRetryRate.'%' }}
            </div>
            <div class="text-xs text-white/50 mt-1 ak-muted">Cut-off retry rate</div>
            <div class="text-[10px] text-white/30 mt-0.5 ak-note">
                {{ number_format($cutoffRetried) }} retried / {{ number_format($cutoffTotal) }} cut-offs
            </div>
        </div>
        <div class="glass rounded-2xl border border-white/10 p-5 text-center"
             title="Clicks on the low-balance CTA shown above the chat input (Top up / See plans). Use this to tell whether the hint actually moves visitors toward a top-up or pricing page.">
            <div class="text-2xl font-semibold text-white ak-strong">{{ number_format($lbClicksTotal) }}</div>
            <div class="text-xs text-white/50 mt-1 ak-muted">Low-balance CTA clicks</div>
            <div class="text-[10px] text-white/30 mt-0.5 ak-note">
                {{ number_format($lbClicksBySurface['app']) }} app · {{ number_format($lbClicksBySurface['marketing']) }} marketing
            </div>
        </div>
    </div>

    <div class="glass rounded-2xl border border-white/10 p-6">
        <div class="flex items-baseline justify-between mb-4 gap-3 flex-wrap">
            <h3 class="font-semibold text-white ak-strong">Recent cut-off alerts</h3>
            <div class="flex items-center gap-3">
                <span class="text-xs text-white/40 ak-note">
                    {{ $recentAlerts->count() }} shown
                    @if($acknowledgedCount > 0)
                        · {{ $acknowledgedCount }} acknowledged
                    @endif
                </span>
                @if($acknowledgedCount > 0)
                    @if($showAcknowledged)
                        <a href="{{ route('admin.site-assistant.analytics', ['days' => $days]) }}"
                           class="px-2.5 py-1 rounded-md text-[11px] bg-white/10 hover:bg-white/15 border border-white/10 text-white/70 ak-strong">
                            Hide acknowledged
                        </a>
                    @else
                        <a href="{{ route('admin.site-assistant.analytics', ['days' => $days, 'show_ack' => 1]) }}"
                           class="px-2.5 py-1 rounded-md text-[11px] bg-white/5 hover:bg-white/10 border border-white/10 text-white/60 ak-muted">
                            Show acknowledged
                        </a>
                    @endif
                @endif
            </div>
        </div>
        @if($recentAlerts->isEmpty())
            <p class="text-sm text-white/40 ak-note">
                @if($acknowledgedCount > 0 && !$showAcknowledged)
                    No unacknowledged cut-off alerts. Nice work, toggle "Show acknowledged" to review past incidents.
                @else
                    No cut-off alerts have been dispatched yet.
                @endif
            </p>
        @else
            <ul class="divide-y divide-white/5 text-sm">
                @foreach($recentAlerts as $a)
                    @php $ack = $a->acknowledged_at !== null; @endphp
                    <li class="py-2 flex items-center justify-between gap-4 {{ $ack ? 'opacity-50' : '' }}">
                        <div class="min-w-0">
                            <div class="text-white/80 ak-strong">
                                <span class="font-semibold {{ $ack ? 'text-white/60 line-through ak-muted' : 'text-rose-300 ak-red' }}">{{ $a->abandon_rate }}% abandon</span>
                                <span class="text-white/40 ak-note">· threshold {{ $a->threshold }}%</span>
                                @if($ack)
                                    <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] bg-emerald-500/15 text-emerald-300 border border-emerald-400/20 ak-green">
                                        Acknowledged
                                    </span>
                                @endif
                            </div>
                            <div class="text-[11px] text-white/40 mt-0.5 ak-note">
                                {{ number_format($a->total) }} cut-offs · {{ number_format($a->retried) }} retried · {{ $a->window_hours }}h window
                                @if($ack)
                                    · by {{ optional($a->acknowledger)->name ?? optional($a->acknowledger)->email ?? 'unknown' }}
                                    <span title="{{ optional($a->acknowledged_at)->toDayDateTimeString() }}">
                                        {{ optional($a->acknowledged_at)->diffForHumans() }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <div class="text-right text-xs text-white/50 whitespace-nowrap ak-muted"
                                 title="{{ optional($a->dispatched_at)->toDayDateTimeString() }}">
                                {{ optional($a->dispatched_at)->diffForHumans() }}
                            </div>
                            <form method="POST"
                                  action="{{ route('admin.site-assistant.alerts.acknowledge', $a) }}"
                                  class="m-0">
                                @csrf
                                <input type="hidden" name="days" value="{{ $days }}">
                                @if($showAcknowledged)
                                    <input type="hidden" name="show_ack" value="1">
                                @endif
                                <button type="submit"
                                        class="px-2.5 py-1 rounded-md text-[11px] border whitespace-nowrap
                                               {{ $ack
                                                  ? 'bg-white/5 hover:bg-white/10 border-white/10 text-white/60 ak-muted'
                                                  : 'bg-emerald-500/15 hover:bg-emerald-500/25 border-emerald-400/30 text-emerald-200 ak-green' }}">
                                    {{ $ack ? 'Dismiss' : 'Acknowledge' }}
                                </button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="glass rounded-2xl border border-white/10 p-6">
        <div class="flex items-baseline justify-between mb-4">
            <h3 class="font-semibold text-white ak-strong">Messages per day</h3>
            <span class="text-xs text-white/40 ak-note">peak {{ number_format($maxDay) }}/day</span>
        </div>
        @if($totalMsgs === 0)
            <p class="text-sm text-white/40 text-center py-8 ak-note">No assistant traffic in this window yet.</p>
        @else
            <div class="flex items-end gap-1 h-40">
                @foreach($messagesPerDay as $d)
                    @php $h = max(2, (int) round(($d['count'] / $maxDay) * 100)); @endphp
                    <div class="flex-1 flex flex-col items-center justify-end" title="{{ $d['day'] }}, {{ $d['count'] }} msgs">
                        <div class="w-full rounded-t bg-indigo-500/70 hover:bg-indigo-400 transition-all" style="height: {{ $h }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-between text-[10px] text-white/40 mt-2 ak-note">
                <span>{{ $messagesPerDay[0]['day'] }}</span>
                <span>{{ $messagesPerDay[count($messagesPerDay)-1]['day'] }}</span>
            </div>
        @endif
    </div>

    @php
        $maxModelCutoff = $cutoffByModel->max('cutoffs') ?: 1;
        $maxRouteCutoff = $cutoffByRoute->max('cutoffs') ?: 1;
    @endphp
    @if($cutoffTotal > 0)
        <div class="grid lg:grid-cols-2 gap-6">
            <div class="glass rounded-2xl border border-white/10 p-6">
                <div class="flex items-baseline justify-between mb-4">
                    <h3 class="font-semibold text-white ak-strong">Cut-offs by model</h3>
                    <span class="text-xs text-white/40 ak-note">retry rate per row</span>
                </div>
                @if($cutoffByModel->isEmpty())
                    <p class="text-sm text-white/40 ak-note">No model metadata on the cut-offs in this window.</p>
                @else
                    <table class="w-full text-xs">
                        <thead class="text-white/40 ak-note">
                            <tr>
                                <th class="text-left font-normal pb-2">Model</th>
                                <th class="text-right font-normal pb-2">Cut-offs</th>
                                <th class="text-right font-normal pb-2">Retried</th>
                                <th class="text-right font-normal pb-2">Retry rate</th>
                            </tr>
                        </thead>
                        <tbody class="text-white/80 ak-strong">
                            @foreach($cutoffByModel as $row)
                                @php
                                    $w = max(4, (int) round(($row['cutoffs'] / $maxModelCutoff) * 100));
                                    $href = route('admin.site-assistant.conversations', [
                                        'cutoffs' => 1,
                                        'model'   => $row['label'],
                                        'days'    => $days,
                                    ]);
                                @endphp
                                <tr class="cutoff-row border-t border-white/5 hover:bg-white/5 cursor-pointer group"
                                    data-href="{{ $href }}"
                                    title="View cut-off transcripts for {{ $row['label'] }}">
                                    <td class="py-2 pr-3">
                                        <a href="{{ $href }}" class="block font-mono truncate text-white group-hover:text-indigo-300 ak-strong">{{ $row['label'] }}</a>
                                        <div class="h-1 mt-1 rounded-full bg-white/5 overflow-hidden">
                                            <div class="h-full bg-rose-500/70" style="width: {{ $w }}%"></div>
                                        </div>
                                    </td>
                                    <td class="py-2 text-right whitespace-nowrap">{{ number_format($row['cutoffs']) }}</td>
                                    <td class="py-2 text-right whitespace-nowrap text-white/50 ak-muted">{{ number_format($row['retried']) }}</td>
                                    <td class="py-2 text-right whitespace-nowrap">{{ $row['rate'] === null ? '-' : $row['rate'].'%' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="glass rounded-2xl border border-white/10 p-6">
                <div class="flex items-baseline justify-between mb-4">
                    <h3 class="font-semibold text-white ak-strong">Cut-offs by route</h3>
                    <span class="text-xs text-white/40 ak-note">retry rate per row</span>
                </div>
                @if($cutoffByRoute->isEmpty())
                    <p class="text-sm text-white/40 ak-note">No route data on the cut-offs in this window.</p>
                @else
                    <table class="w-full text-xs">
                        <thead class="text-white/40 ak-note">
                            <tr>
                                <th class="text-left font-normal pb-2">Route</th>
                                <th class="text-right font-normal pb-2">Cut-offs</th>
                                <th class="text-right font-normal pb-2">Retried</th>
                                <th class="text-right font-normal pb-2">Retry rate</th>
                            </tr>
                        </thead>
                        <tbody class="text-white/80 ak-strong">
                            @foreach($cutoffByRoute as $row)
                                @php
                                    $w = max(4, (int) round(($row['cutoffs'] / $maxRouteCutoff) * 100));
                                    $href = route('admin.site-assistant.conversations', [
                                        'cutoffs' => 1,
                                        'route'   => $row['label'],
                                        'days'    => $days,
                                    ]);
                                @endphp
                                <tr class="cutoff-row border-t border-white/5 hover:bg-white/5 cursor-pointer group"
                                    data-href="{{ $href }}"
                                    title="View cut-off transcripts for {{ $row['label'] }}">
                                    <td class="py-2 pr-3">
                                        <a href="{{ $href }}" class="block font-mono truncate text-white group-hover:text-indigo-300 ak-strong">{{ $row['label'] }}</a>
                                        <div class="h-1 mt-1 rounded-full bg-white/5 overflow-hidden">
                                            <div class="h-full bg-rose-500/70" style="width: {{ $w }}%"></div>
                                        </div>
                                    </td>
                                    <td class="py-2 text-right whitespace-nowrap">{{ number_format($row['cutoffs']) }}</td>
                                    <td class="py-2 text-right whitespace-nowrap text-white/50 ak-muted">{{ number_format($row['retried']) }}</td>
                                    <td class="py-2 text-right whitespace-nowrap">{{ $row['rate'] === null ? '-' : $row['rate'].'%' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
        {{-- Delegated row click: anywhere in the row navigates to the
             pre-filtered transcripts (the inner anchor still works for
             keyboard / middle-click). Skips clicks that originated on
             the anchor so we don't double-fire. --}}
        <script>
            document.querySelectorAll('.cutoff-row').forEach(function (tr) {
                tr.addEventListener('click', function (e) {
                    if (e.target.closest('a')) return;
                    var href = tr.getAttribute('data-href');
                    if (href) window.location = href;
                });
            });
        </script>
    @endif

    <div class="grid lg:grid-cols-2 gap-6">
        <div class="glass rounded-2xl border border-white/10 p-6">
            <h3 class="font-semibold text-white mb-4 ak-strong">Top routes</h3>
            @if($topRoutes->isEmpty())
                <p class="text-sm text-white/40 ak-note">No route data yet.</p>
            @else
                <div class="space-y-2">
                    @foreach($topRoutes as $r)
                        @php $w = max(4, (int) round(($r->c / $maxRoute) * 100)); @endphp
                        <div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-mono text-white/80 truncate pr-3 ak-strong">{{ $r->last_route }}</span>
                                <span class="text-white/50 whitespace-nowrap ak-muted">{{ $r->c }} convs · {{ (int)$r->turns }} turns</span>
                            </div>
                            <div class="h-1.5 mt-1 rounded-full bg-white/5 overflow-hidden">
                                <div class="h-full bg-indigo-500/70" style="width: {{ $w }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="glass rounded-2xl border border-white/10 p-6">
            <div class="flex items-baseline justify-between mb-4">
                <h3 class="font-semibold text-white ak-strong">Suggestions</h3>
                <span class="text-xs text-white/40 ak-note">questions that triggered handoff</span>
            </div>
            @if($suggestions->isEmpty())
                <p class="text-sm text-white/40 ak-note">No handoffs in this window, nothing to learn from yet.</p>
            @else
                <p class="text-xs text-white/40 mb-3 ak-note">Add page hints or response templates for these to deflect them next time.</p>
                <ol class="space-y-2">
                    @foreach($suggestions as $s)
                        <li class="flex items-start gap-3 text-sm">
                            <span class="shrink-0 inline-flex items-center justify-center min-w-[2rem] h-6 px-2 rounded-md bg-amber-500/15 text-amber-300 text-xs font-semibold ak-amber">{{ $s['count'] }}×</span>
                            <span class="text-white/80 ak-strong">{{ $s['sample'] }}</span>
                        </li>
                    @endforeach
                </ol>
                <div class="mt-4 flex gap-2">
                    <a href="{{ route('admin.site-assistant.hints') }}" class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-xs text-white ak-strong">Add a page hint →</a>
                    <a href="{{ route('admin.site-assistant.templates') }}" class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-xs text-white ak-strong">Add a response template →</a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
