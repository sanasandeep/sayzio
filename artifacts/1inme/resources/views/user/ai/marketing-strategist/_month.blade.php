{{-- A single month of the agency-style execution plan. Expects $month (array). --}}
@php
    $month = (array) $month;
    $num   = (int) ($month['month'] ?? 0);
    $lists = [
        'goals'            => ['label' => 'Goals',            'icon' => 'fa-bullseye',        'color' => 'text-emerald-300/70'],
        'deliverables'     => ['label' => 'Deliverables',     'icon' => 'fa-box-open',        'color' => 'text-sky-300/70'],
        'automation_flows' => ['label' => 'Automation flows', 'icon' => 'fa-bolt',            'color' => 'text-amber-300/70'],
        'timeline'         => ['label' => 'Timeline',         'icon' => 'fa-timeline',        'color' => 'text-indigo-300/70'],
    ];
@endphp
<div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-semibold text-white">
            <span class="text-indigo-300">Month {{ $num }}</span>
            @if(!empty($month['theme']))
                <span class="text-white/80">&mdash; {{ $month['theme'] }}</span>
            @endif
        </h3>
        @if(!empty($month['budget']))
            <span class="text-[11px] px-2 py-0.5 rounded-full bg-white/5 text-amber-200/80 shrink-0"><i class="fas fa-coins mr-1"></i>{{ $month['budget'] }}</span>
        @endif
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 mt-3">
        @foreach($lists as $key => $meta)
            @php $items = (array) ($month[$key] ?? []); @endphp
            @if(!empty($items))
                <div>
                    <p class="text-[11px] uppercase tracking-wider text-white/40 font-semibold mb-1.5">
                        <i class="fas {{ $meta['icon'] }} {{ $meta['color'] }} mr-1"></i>{{ $meta['label'] }}
                    </p>
                    <ul class="space-y-1">
                        @foreach($items as $item)
                            <li class="flex items-start gap-2 text-xs text-white/70">
                                <i class="fas fa-check text-white/25 mt-0.5 text-[10px]"></i>
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endforeach
    </div>
</div>
