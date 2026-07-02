{{-- Month grid view — a full month, events placed on their dates, accent-coded. --}}
@php
    use Illuminate\Support\Carbon;

    // Group the windowed events by their own-timezone local date.
    $byDate = [];
    foreach ($gridEvents as $ev) {
        $etz = $ev->timezone ?: ($ev->calendar?->effectiveTimezone() ?? 'UTC');
        $local = $ev->start_at?->timezone($etz);
        if (!$local) { continue; }
        $byDate[$local->format('Y-m-d')][] = ['ev' => $ev, 'local' => $local];
    }

    $monthStart = $focusDate->copy()->startOfMonth();
    $gridStart  = $monthStart->copy()->startOfWeek(Carbon::SUNDAY);
    $gridEnd    = $focusDate->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
    $today      = Carbon::now($userTz)->format('Y-m-d');
    $maxPerCell = 3;

    $weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
@endphp

<div class="glass rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <div class="min-w-[640px]">
            {{-- Weekday header --}}
            <div class="grid grid-cols-7 border-b border-white/[0.07]">
                @foreach($weekdays as $i => $wd)
                    <div class="text-[11px] font-semibold uppercase tracking-widest text-white/35 text-center py-3
                        {{ $i === 0 || $i === 6 ? 'text-white/20' : '' }}">
                        {{ $wd }}
                    </div>
                @endforeach
            </div>

            {{-- Day cells --}}
            <div class="grid grid-cols-7 divide-x divide-y divide-white/[0.06]">
                @php $cursor = $gridStart->copy(); @endphp
                @while($cursor <= $gridEnd)
                    @php
                        $key      = $cursor->format('Y-m-d');
                        $inMonth  = $cursor->month === $focusDate->month;
                        $isToday  = $key === $today;
                        $isWeekend = $cursor->dayOfWeek === Carbon::SUNDAY || $cursor->dayOfWeek === Carbon::SATURDAY;
                        $dayEvents = $byDate[$key] ?? [];
                        $dayUrl   = $linkTo(['view' => 'day', 'date' => $key]);
                    @endphp
                    <div class="min-h-[7.5rem] p-2 flex flex-col gap-1
                        {{ $inMonth ? ($isWeekend ? 'bg-white/[0.015]' : 'bg-transparent') : 'bg-black/10' }}
                        {{ $isToday ? 'ring-1 ring-inset ring-blue-500/30' : '' }}">

                        {{-- Day number --}}
                        <a href="{{ $dayUrl }}"
                           class="self-start text-xs font-bold w-6 h-6 inline-flex items-center justify-center rounded-full transition
                               {{ $isToday
                                    ? 'bg-blue-600 text-white shadow-sm shadow-blue-700/40'
                                    : ($inMonth ? 'text-white/65 hover:bg-white/10 hover:text-white' : 'text-white/20 hover:bg-white/8 hover:text-white/40') }}">
                            {{ $cursor->day }}
                        </a>

                        {{-- Events --}}
                        <div class="flex flex-col gap-px mt-0.5">
                            @foreach(array_slice($dayEvents, 0, $maxPerCell) as $row)
                                @php
                                    $ev        = $row['ev'];
                                    $accent    = $ev->calendar?->accent_color ?: '#3d6bff';
                                    $editorUrl = $ev->calendar?->link_id ? route('user.calendars.editor', $ev->calendar->link_id) : '#';
                                @endphp
                                <a href="{{ $editorUrl }}"
                                   @if($editorUrl === '#') onclick="return false;" @endif
                                   class="group flex items-center gap-1 px-1.5 py-[3px] rounded-md text-[11px] leading-tight truncate hover:brightness-125 transition"
                                   style="background: {{ $accent }}22; border-left: 2px solid {{ $accent }}cc;"
                                   title="{{ $ev->title }}{{ $ev->calendar ? ' · '.$ev->calendar->title : '' }}">
                                    @unless($ev->all_day)
                                        <span class="text-white/40 tabular-nums shrink-0">{{ $row['local']->format('g:i') }}</span>
                                    @endunless
                                    <span class="truncate" style="color: {{ $accent }}ee;">{{ $ev->title }}</span>
                                </a>
                            @endforeach
                            @if(count($dayEvents) > $maxPerCell)
                                <a href="{{ $dayUrl }}" class="text-[11px] text-white/35 hover:text-white/70 px-1.5 py-px transition">
                                    +{{ count($dayEvents) - $maxPerCell }} more
                                </a>
                            @endif
                        </div>
                    </div>
                    @php $cursor->addDay(); @endphp
                @endwhile
            </div>
        </div>
    </div>
</div>

@if($gridEvents->isEmpty())
    <p class="text-center text-xs text-white/40 mt-3">No events this month. Use the arrows to browse other months, or switch to Agenda.</p>
@endif
