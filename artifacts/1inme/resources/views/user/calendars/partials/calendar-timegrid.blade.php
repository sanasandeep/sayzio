{{-- Week / Day time-grid view — events positioned by start/end time, with a
     dedicated all-day row. `$view` is either 'week' or 'day'. --}}
@php
    use Illuminate\Support\Carbon;

    $hourPx = 48;          // height of one hour row
    $dayTop = 0;           // grid starts at 00:00
    $totalHeight = 24 * $hourPx;

    if ($view === 'day') {
        $days = [$focusDate->copy()];
    } else {
        $weekStart = $focusDate->copy()->startOfWeek(Carbon::SUNDAY);
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = $weekStart->copy()->addDays($i);
        }
    }
    $dayKeys = collect($days)->map(fn ($d) => $d->format('Y-m-d'))->all();

    // Bucket events into the day columns by their own-tz local date.
    $timed  = array_fill_keys($dayKeys, []);
    $allDay = array_fill_keys($dayKeys, []);
    foreach ($gridEvents as $ev) {
        $etz = $ev->timezone ?: ($ev->calendar?->effectiveTimezone() ?? 'UTC');
        $start = $ev->start_at?->timezone($etz);
        if (!$start) { continue; }
        $key = $start->format('Y-m-d');
        if (!array_key_exists($key, $timed)) { continue; }

        if ($ev->all_day) {
            $allDay[$key][] = ['ev' => $ev];
            continue;
        }

        $end = $ev->end_at ? $ev->end_at->timezone($etz) : $start->copy()->addHour();
        $startMin = $start->hour * 60 + $start->minute;
        $endMin   = max($startMin + 30, $end->hour * 60 + $end->minute + ($end->day !== $start->day ? 1440 : 0));
        $endMin   = min($endMin, 1440);
        $timed[$key][] = [
            'ev'    => $ev,
            'start' => $start,
            'end'   => $end,
            'top'   => round($startMin / 60 * $hourPx, 1),
            'h'     => round(max(22, ($endMin - $startMin) / 60 * $hourPx), 1),
        ];
    }

    $today = Carbon::now($userTz)->format('Y-m-d');
    $colClass = $view === 'day' ? 'grid-cols-1' : 'grid-cols-7';
@endphp

<div class="glass rounded-2xl p-3 sm:p-4 overflow-x-auto">
    <div class="{{ $view === 'day' ? '' : 'min-w-[760px]' }}">
        {{-- Day column headers --}}
        <div class="flex border-b border-white/10 pb-2 mb-2">
            <div class="w-14 flex-shrink-0"></div>
            <div class="grid {{ $colClass }} flex-1 gap-px">
                @foreach($days as $d)
                    @php $isToday = $d->format('Y-m-d') === $today; @endphp
                    <a href="{{ $linkTo(['view' => 'day', 'date' => $d->format('Y-m-d')]) }}" class="text-center group">
                        <div class="text-[11px] uppercase tracking-wide text-white/40">{{ $d->isoFormat('ddd') }}</div>
                        <div class="text-sm font-semibold {{ $isToday ? 'text-white' : 'text-white/70' }} mt-0.5">
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-full {{ $isToday ? 'bg-blue-600 text-white' : 'group-hover:bg-white/10' }}">{{ $d->day }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- All-day row --}}
        @php $hasAllDay = collect($allDay)->contains(fn ($x) => count($x) > 0); @endphp
        @if($hasAllDay)
        <div class="flex border-b border-white/10 pb-2 mb-2">
            <div class="w-14 flex-shrink-0 text-[10px] uppercase tracking-wide text-white/30 pt-1">All day</div>
            <div class="grid {{ $colClass }} flex-1 gap-px">
                @foreach($dayKeys as $key)
                    <div class="flex flex-col gap-1 px-0.5">
                        @foreach($allDay[$key] as $row)
                            @php $ev = $row['ev']; $accent = $ev->calendar?->accent_color ?: '#3d6bff'; $editorUrl = $ev->calendar?->link_id ? route('user.calendars.editor', $ev->calendar->link_id) : '#'; @endphp
                            <a href="{{ $editorUrl }}"
                               @if($editorUrl === '#') onclick="return false;" @endif
                               class="block px-1.5 py-1 rounded-md text-[11px] leading-tight truncate hover:brightness-125"
                               style="background: {{ $accent }}26; border-left: 3px solid {{ $accent }};"
                               title="{{ $ev->title }}">
                                <span class="text-white/90">{{ $ev->title }}</span>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Time grid --}}
        <div class="flex max-h-[34rem] overflow-y-auto">
            {{-- hour gutter --}}
            <div class="w-14 flex-shrink-0 relative" style="height: {{ $totalHeight }}px;">
                @for($h = 0; $h < 24; $h++)
                    <div class="absolute right-2 -translate-y-1/2 text-[10px] text-white/30 tabular-nums" style="top: {{ $h * $hourPx }}px;">
                        @if($h === 0) 12 AM @elseif($h < 12) {{ $h }} AM @elseif($h === 12) 12 PM @else {{ $h - 12 }} PM @endif
                    </div>
                @endfor
            </div>

            {{-- day columns --}}
            <div class="grid {{ $colClass }} flex-1 gap-px">
                @foreach($dayKeys as $key)
                    <div class="relative" style="height: {{ $totalHeight }}px;">
                        {{-- hour lines --}}
                        @for($h = 0; $h < 24; $h++)
                            <div class="absolute left-0 right-0 border-t border-white/5" style="top: {{ $h * $hourPx }}px;"></div>
                        @endfor

                        @foreach($timed[$key] as $row)
                            @php $ev = $row['ev']; $accent = $ev->calendar?->accent_color ?: '#3d6bff'; $editorUrl = $ev->calendar?->link_id ? route('user.calendars.editor', $ev->calendar->link_id) : '#'; @endphp
                            <a href="{{ $editorUrl }}"
                               @if($editorUrl === '#') onclick="return false;" @endif
                               class="absolute left-0.5 right-0.5 rounded-md px-1.5 py-0.5 overflow-hidden hover:brightness-125 hover:z-10"
                               style="top: {{ $row['top'] }}px; height: {{ $row['h'] }}px; background: {{ $accent }}33; border-left: 3px solid {{ $accent }};"
                               title="{{ $ev->title }} — {{ $ev->calendar?->title }}">
                                <div class="text-[11px] font-medium text-white/90 leading-tight truncate">{{ $ev->title }}</div>
                                <div class="text-[10px] text-white/50 tabular-nums leading-tight">
                                    {{ $row['start']->format('g:i A') }}@if($ev->end_at) – {{ $row['end']->format('g:i A') }}@endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

@if($gridEvents->isEmpty())
    <p class="text-center text-xs text-white/40 mt-3">No events in this {{ $view }}. Use the arrows to browse, or switch to Agenda.</p>
@endif
