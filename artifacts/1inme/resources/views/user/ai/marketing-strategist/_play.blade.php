{{-- A single organic/paid marketing play card. Expects $play (array) + $paid (bool). --}}
@php $play = (array) $play; @endphp
<div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 mb-3">
    <div class="flex items-start justify-between gap-3">
        <h3 class="text-sm font-semibold text-white">{{ $play['title'] ?? 'Play' }}</h3>
        @if(!empty($play['channel']))
            <span class="text-[11px] px-2 py-0.5 rounded-full bg-white/5 text-white/50 shrink-0">{{ $play['channel'] }}</span>
        @endif
    </div>

    @if(!empty($play['budget_hint']))
        <p class="text-[11px] text-amber-200/80 mt-1"><i class="fas fa-coins mr-1"></i>{{ $play['budget_hint'] }}</p>
    @endif

    @if(!empty($play['rationale']))
        <p class="text-xs text-white/50 mt-2">{{ $play['rationale'] }}</p>
    @endif

    @if(!empty($play['steps']))
        <ul class="mt-3 space-y-1.5">
            @foreach((array) $play['steps'] as $step)
                <li class="flex items-start gap-2 text-xs text-white/70">
                    <i class="fas fa-check text-emerald-300/70 mt-0.5"></i>
                    <span>{{ $step }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    @if(!empty($play['sayzio_features']))
        <div class="flex flex-wrap gap-1.5 mt-3">
            @foreach((array) $play['sayzio_features'] as $feature)
                <span class="text-[10px] px-2 py-0.5 rounded-full bg-blue-500/10 text-blue-200/80">{{ $feature }}</span>
            @endforeach
        </div>
    @endif
</div>
