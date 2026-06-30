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

<div class="glass rounded-2xl p-3 sm:p-4 overflow-x-auto">
    <div class="min-w-[640px]">
        {{-- weekday header --}}
        <div class="grid grid-cols-7 mb-2">
            @foreach($weekdays as $wd)
                <div class="text-[11px] font-semibold uppercase tracking-wide text-white/40 text-center py-1">{{ $wd }}</div>
            @endforeach
        </div>

        <div class="grid grid-cols-7 gap-px rounded-xl overflow-hidden bg-white/5">
            @php $cursor = $gridStart->copy(); @endphp
            @while($cursor <= $gridEnd)
                @php
                    $key = $cursor->format('Y-m-d');
                    $inMonth = $cursor->month === $focusDate->month;
                    $isToday = $key === $today;
                    $dayEvents = $byDate[$key] ?? [];
                    $dayUrl = $linkTo(['view' => 'day', 'date' => $key]);
                @endphp
                <div class="min-h-[7rem] p-1.5 flex flex-col gap-1 {{ $inMonth ? 'bg-white/[0.02]' : 'bg-transparent' }}">
                    <a href="{{ $dayUrl }}" class="self-start text-xs font-semibold w-6 h-6 inline-flex items-center justify-center rounded-full
                        {{ $isToday ? 'bg-blue-600 text-white' : ($inMonth ? 'text-white/70 hover:bg-white/10' : 'text-white/25 hover:bg-white/10') }}">
                        {{ $cursor->day }}
                    </a>
                    <div class="flex flex-col gap-1">
                        @foreach(array_slice($dayEvents, 0, $maxPerCell) as $row)
                            @php
                                $ev = $row['ev'];
                                $accent = $ev->calendar?->accent_color ?: '#3d6bff';
                                $editorUrl = $ev->calendar?->link_id ? route('user.calendars.editor', $ev->calendar->link_id) : '#';
                            @endphp
                            <a href="{{ $editorUrl }}"
                               @if($editorUrl === '#') onclick="return false;" @endif
                               class="group flex items-center gap-1.5 px-1.5 py-1 rounded-md text-[11px] leading-tight truncate hover:brightness-125"
                               style="background: {{ $accent }}26; border-left: 3px solid {{ $accent }};"
                               title="{{ $ev->title }} — {{ $ev->calendar?->title }}">
                                @unless($ev->all_day)
                                    <span class="text-white/50 tabular-nums">{{ $row['local']->format('g:i') }}</span>
                                @endunless
                                <span class="text-white/90 truncate">{{ $ev->title }}</span>
                            </a>
                        @endforeach
                        @if(count($dayEvents) > $maxPerCell)
                            <a href="{{ $dayUrl }}" class="text-[11px] text-white/40 hover:text-white px-1.5">+{{ count($dayEvents) - $maxPerCell }} more</a>
                        @endif
                    </div>
                </div>
                @php $cursor->addDay(); @endphp
            @endwhile
        </div>
    </div>
</div>

@if($gridEvents->isEmpty())
    <p class="text-center text-xs text-white/40 mt-3">No events this month. Use the arrows to browse other months, or switch to Agenda.</p>
@endif
