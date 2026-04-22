@php
    $mindsUsed = $result['minds_used'] ?? [];
    $citations = $result['citations'] ?? [];
    $citationsByMind = [];
    foreach ($citations as $i => $c) {
        $citationsByMind[(int) ($c['mind_id'] ?? 0)][] = ['index' => $i + 1, 'cite' => $c];
    }
    $unattached = $citationsByMind[0] ?? [];
@endphp

@if(!empty($mindsUsed))
    <div class="mt-4 pt-4 border-t border-white/10">
        <p class="text-xs uppercase tracking-wider text-white/40 mb-2">Grounded in</p>
        <div class="space-y-3">
            @foreach($mindsUsed as $mu)
                @php
                    $mid    = (int) ($mu['id'] ?? 0);
                    $chunks = (int) ($mu['chunks_used'] ?? 0);
                    $top    = (float) ($mu['top_score'] ?? 0.0);
                    $cites  = $citationsByMind[$mid] ?? [];
                    $contributed = $chunks > 0;
                @endphp
                <div class="rounded-xl border border-white/10 bg-white/[0.02] p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="text-sm font-semibold text-white/90 truncate">{{ $mu['name'] }}</span>
                            @if(!empty($mu['is_platform']))
                                <span class="text-[10px] uppercase tracking-wider px-1.5 py-0.5 rounded bg-white/5 border border-white/10 text-white/60">platform</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-3 text-xs text-white/60">
                            @if($contributed)
                                <span>{{ $chunks }} {{ \Illuminate\Support\Str::plural('chunk', $chunks) }}</span>
                                <span>·</span>
                                <span>best match {{ number_format($top * 100, 1) }}%</span>
                            @else
                                <span class="text-white/40">no chunks used</span>
                            @endif
                        </div>
                    </div>
                    @if(!empty($cites))
                        <ul class="mt-2 space-y-1 text-xs text-white/70">
                            @foreach($cites as $row)
                                @php $c = $row['cite']; @endphp
                                <li>
                                    <span class="text-white/40">[{{ $row['index'] }}]</span>
                                    <span class="text-white/90">{{ $c['title'] }}</span>
                                    <span class="text-white/40">· {{ $c['type'] }}</span>
                                    <span class="text-white/40">· match {{ number_format($c['score'] * 100, 1) }}%</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach

            @if(!empty($unattached))
                <div class="rounded-xl border border-white/10 bg-white/[0.02] p-3">
                    <p class="text-xs uppercase tracking-wider text-white/40 mb-2">Other sources</p>
                    <ul class="space-y-1 text-xs text-white/70">
                        @foreach($unattached as $row)
                            @php $c = $row['cite']; @endphp
                            <li>
                                <span class="text-white/40">[{{ $row['index'] }}]</span>
                                <span class="text-white/90">{{ $c['title'] }}</span>
                                <span class="text-white/40">· {{ $c['type'] }}</span>
                                <span class="text-white/40">· match {{ number_format($c['score'] * 100, 1) }}%</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
@elseif(!empty($citations))
    <div class="mt-4">
        <p class="text-xs uppercase tracking-wider text-white/40 mb-2">Sources</p>
        <ul class="space-y-1 text-xs text-white/70">
            @foreach($citations as $i => $c)
                <li>
                    <span class="text-white/40">[{{ $i + 1 }}]</span>
                    <span class="text-white/90">{{ $c['title'] }}</span>
                    <span class="text-white/40">· {{ $c['type'] }}</span>
                    <span class="text-white/40">· match {{ number_format($c['score'] * 100, 1) }}%</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
