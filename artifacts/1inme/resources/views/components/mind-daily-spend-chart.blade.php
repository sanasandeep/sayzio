@props([
    'days' => [],
    'height' => 'h-24',
])
@php
    $days = is_array($days) ? $days : [];
    $count = count($days);
    $maxTotal = 0;
    foreach ($days as $d) {
        $t = (int) ($d['ingest'] ?? 0) + (int) ($d['query'] ?? 0);
        if ($t > $maxTotal) $maxTotal = $t;
    }
    $hasAny = $maxTotal > 0;
    $denom  = max(1, $maxTotal);
@endphp
<div {{ $attributes->merge(['class' => 'mt-4']) }}>
    @if($count === 0)
        <p class="text-[11px] text-white/40">No data.</p>
    @else
        <div class="flex items-end gap-px {{ $height }}" role="img"
             aria-label="Daily AI credit spend, last {{ $count }} days, ingestion vs questions stacked">
            @foreach($days as $d)
                @php
                    $ingest = (int) ($d['ingest'] ?? 0);
                    $query  = (int) ($d['query']  ?? 0);
                    $tot    = $ingest + $query;
                    $iH     = ($ingest / $denom) * 100;
                    $qH     = ($query  / $denom) * 100;
                    $date   = \Illuminate\Support\Carbon::parse($d['date'])->format('M j, Y');
                    $title  = $tot > 0
                        ? $date.' — '.number_format($tot).' credits ('.number_format($ingest).' ingest, '.number_format($query).' questions)'
                        : $date.' — no spend';
                @endphp
                <div class="flex-1 h-full flex flex-col justify-end min-w-0" title="{{ $title }}">
                    @if($query > 0)
                        <div class="bg-blue-400/70 hover:bg-blue-300" style="height: {{ $qH }}%"></div>
                    @endif
                    @if($ingest > 0)
                        <div class="bg-cyan-400/70 hover:bg-cyan-300" style="height: {{ $iH }}%"></div>
                    @endif
                    @if($tot === 0)
                        <div class="bg-white/5" style="height: 1px"></div>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="flex items-center justify-between mt-2 text-[10px] text-white/40">
            <span>{{ \Illuminate\Support\Carbon::parse($days[0]['date'])->format('M j') }}</span>
            <span class="flex items-center gap-3">
                <span class="flex items-center gap-1"><span class="w-2 h-2 bg-cyan-400/70 inline-block rounded-sm"></span> Ingestion</span>
                <span class="flex items-center gap-1"><span class="w-2 h-2 bg-blue-400/70 inline-block rounded-sm"></span> Questions</span>
            </span>
            <span>{{ \Illuminate\Support\Carbon::parse($days[$count - 1]['date'])->format('M j') }}</span>
        </div>
        @unless($hasAny)
            <p class="text-[11px] text-white/40 mt-1">No spend in this window.</p>
        @endunless
    @endif
</div>
