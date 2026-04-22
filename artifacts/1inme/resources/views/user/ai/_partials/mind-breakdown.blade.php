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
                <div class="rounded-xl border {{ $contributed ? 'border-white/10 bg-white/[0.02]' : 'border-amber-400/30 bg-amber-400/[0.04]' }} p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            @unless($contributed)
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-amber-400 shrink-0" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 7a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd" />
                                </svg>
                            @endunless
                            <span class="text-sm font-semibold {{ $contributed ? 'text-white/90' : 'text-amber-100' }} truncate">{{ $mu['name'] }}</span>
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
                                <span class="text-amber-300/90 font-medium">no chunks used</span>
                            @endif
                        </div>
                    </div>
                    @unless($contributed)
                        <p class="mt-2 text-xs text-amber-200/80 leading-relaxed">
                            This Mind didn't contribute to the answer, but you were still charged embedding credits for it.
                            Consider unchecking it in the Mind picker next time to save credits, or
                            <a href="{{ route('user.minds.index') }}" class="underline decoration-amber-300/40 hover:decoration-amber-300">manage your Minds</a>
                            to remove it entirely.
                        </p>
                    @endunless
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
