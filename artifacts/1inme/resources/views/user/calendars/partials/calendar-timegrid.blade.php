{{-- Week / Day time-grid view — events positioned by start/end time, with a
     dedicated all-day row. `$view` is either 'week' or 'day'. --}}
@php
    use Illuminate\Support\Carbon;

    $hourPx      = 52;          // height of one hour row in px
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
        $etz   = $ev->timezone ?: ($ev->calendar?->effectiveTimezone() ?? 'UTC');
        $start = $ev->start_at?->timezone($etz);
        if (!$start) { continue; }
        $key = $start->format('Y-m-d');
        if (!array_key_exists($key, $timed)) { continue; }

        if ($ev->all_day) {
            $allDay[$key][] = ['ev' => $ev];
            continue;
        }

        $end      = $ev->end_at ? $ev->end_at->timezone($etz) : $start->copy()->addHour();
        $startMin = $start->hour * 60 + $start->minute;
        $endMin   = max($startMin + 30, $end->hour * 60 + $end->minute + ($end->day !== $start->day ? 1440 : 0));
        $endMin   = min($endMin, 1440);
        $timed[$key][] = [
            'ev'    => $ev,
            'start' => $start,
            'end'   => $end,
            'top'   => round($startMin / 60 * $hourPx, 1),
            'h'     => round(max(24, ($endMin - $startMin) / 60 * $hourPx), 1),
        ];
    }

    $today     = Carbon::now($userTz)->format('Y-m-d');
    $colClass  = $view === 'day' ? 'grid-cols-1' : 'grid-cols-7';
    $nowMinute = Carbon::now($userTz)->hour * 60 + Carbon::now($userTz)->minute;
    $nowTop    = round($nowMinute / 60 * $hourPx, 1);
    $isCurrentPeriod = in_array($today, $dayKeys, true);
@endphp

<div class="glass rounded-2xl overflow-hidden">
    <div class="{{ $view === 'day' ? '' : 'overflow-x-auto' }}">
        <div class="{{ $view === 'day' ? '' : 'min-w-[760px]' }}">

            {{-- Day column headers --}}
            <div class="flex border-b border-white/[0.07] bg-white/[0.02]">
                <div class="w-14 flex-shrink-0"></div>
                <div class="grid {{ $colClass }} flex-1">
                    @foreach($days as $d)
                        @php
                            $isToday  = $d->format('Y-m-d') === $today;
                            $isWeekend = $d->dayOfWeek === Carbon::SUNDAY || $d->dayOfWeek === Carbon::SATURDAY;
                        @endphp
                        <a href="{{ $linkTo(['view' => 'day', 'date' => $d->format('Y-m-d')]) }}"
                           class="text-center py-3 group border-l border-white/[0.06] first:border-l-0
                               {{ $isToday ? 'bg-blue-600/10' : ($isWeekend ? 'bg-white/[0.015]' : '') }}">
                            <div class="text-[10px] uppercase tracking-widest font-semibold
                                {{ $isToday ? 'text-blue-400' : ($isWeekend ? 'text-white/25' : 'text-white/35') }}">
                                {{ $d->isoFormat('ddd') }}
                            </div>
                            <div class="mt-0.5">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold transition
                                    {{ $isToday ? 'bg-blue-600 text-white shadow-sm shadow-blue-700/40' : ($isWeekend ? 'text-white/30 group-hover:bg-white/8' : 'text-white/70 group-hover:bg-white/10 group-hover:text-white') }}">
                                    {{ $d->day }}
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- All-day row --}}
            @php $hasAllDay = collect($allDay)->contains(fn ($x) => count($x) > 0); @endphp
            @if($hasAllDay)
            <div class="flex border-b border-white/[0.07] bg-white/[0.015]">
                <div class="w-14 flex-shrink-0 flex items-center justify-end pr-3">
                    <span class="text-[9px] uppercase tracking-widest text-white/25 leading-none text-right">All<br>day</span>
                </div>
                <div class="grid {{ $colClass }} flex-1 gap-px py-1.5 px-0.5">
                    @foreach($dayKeys as $key)
                        <div class="flex flex-col gap-0.5 px-0.5">
                            @foreach($allDay[$key] as $row)
                                @php $ev = $row['ev']; $accent = $ev->calendar?->accent_color ?: '#3d6bff'; $editorUrl = $ev->calendar?->link_id ? route('user.calendars.editor', $ev->calendar->link_id) : '#'; @endphp
                                <a href="{{ $editorUrl }}"
                                   @if($editorUrl === '#') onclick="return false;" @endif
                                   class="block px-2 py-1 rounded-md text-[11px] leading-tight truncate hover:brightness-125 transition"
                                   style="background: {{ $accent }}28; border-left: 2px solid {{ $accent }}cc;"
                                   title="{{ $ev->title }}">
                                    <span style="color: {{ $accent }}ee;">{{ $ev->title }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Time grid --}}
            <div class="flex max-h-[38rem] overflow-y-auto" id="cal-timegrid-scroll">
                {{-- Hour gutter --}}
                <div class="w-14 flex-shrink-0 relative select-none" style="height: {{ $totalHeight }}px;">
                    @for($h = 0; $h < 24; $h++)
                        <div class="absolute right-3 text-[10px] text-white/25 tabular-nums font-medium"
                             style="top: {{ $h * $hourPx + 2 }}px; transform: none;">
                            @if($h === 0) 12 AM @elseif($h < 12) {{ $h }} AM @elseif($h === 12) 12 PM @else {{ $h - 12 }} PM @endif
                        </div>
                    @endfor
                </div>

                {{-- Day columns --}}
                <div class="grid {{ $colClass }} flex-1 relative border-l border-white/[0.06]">
                    @foreach($dayKeys as $dayIdx => $key)
                        @php $isToday = $key === $today; $isWeekend = $days[$dayIdx]->dayOfWeek === Carbon::SUNDAY || $days[$dayIdx]->dayOfWeek === Carbon::SATURDAY; @endphp
                        <div class="relative border-l border-white/[0.05] first:border-l-0
                            {{ $isToday ? 'bg-blue-600/[0.04]' : ($isWeekend ? 'bg-black/[0.06]' : '') }}"
                             style="height: {{ $totalHeight }}px;">

                            {{-- Hour lines --}}
                            @for($h = 0; $h < 24; $h++)
                                <div class="absolute left-0 right-0 border-t {{ $h % 6 === 0 ? 'border-white/[0.09]' : 'border-white/[0.04]' }}"
                                     style="top: {{ $h * $hourPx }}px;"></div>
                                {{-- Half-hour dotted tick --}}
                                <div class="absolute left-0 right-0 border-t border-dashed border-white/[0.025]"
                                     style="top: {{ ($h + 0.5) * $hourPx }}px;"></div>
                            @endfor

                            {{-- Current-time indicator --}}
                            @if($isToday && $isCurrentPeriod)
                                <div class="absolute left-0 right-0 z-20 flex items-center" style="top: {{ $nowTop }}px;">
                                    <span class="w-2 h-2 rounded-full bg-rose-500 flex-shrink-0 -ml-1"></span>
                                    <div class="flex-1 border-t border-rose-500/70"></div>
                                </div>
                            @endif

                            {{-- Timed events --}}
                            @foreach($timed[$key] as $row)
                                @php $ev = $row['ev']; $accent = $ev->calendar?->accent_color ?: '#3d6bff'; $editorUrl = $ev->calendar?->link_id ? route('user.calendars.editor', $ev->calendar->link_id) : '#'; @endphp
                                <a href="{{ $editorUrl }}"
                                   @if($editorUrl === '#') onclick="return false;" @endif
                                   class="absolute left-1 right-1 rounded-lg px-2 py-1 overflow-hidden hover:brightness-125 hover:z-10 transition group"
                                   style="top: {{ $row['top'] }}px; height: {{ $row['h'] }}px; background: {{ $accent }}2a; border-left: 2px solid {{ $accent }}cc;"
                                   title="{{ $ev->title }}{{ $ev->calendar ? ' · '.$ev->calendar->title : '' }}">
                                    <div class="text-[11px] font-semibold leading-tight truncate" style="color: {{ $accent }}ee;">{{ $ev->title }}</div>
                                    @if($row['h'] >= 32)
                                    <div class="text-[10px] text-white/45 tabular-nums leading-tight mt-0.5">
                                        {{ $row['start']->format('g:i A') }}@if($ev->end_at) – {{ $row['end']->format('g:i A') }}@endif
                                    </div>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Scroll to current time on page load --}}
<script>
(function () {
    var el = document.getElementById('cal-timegrid-scroll');
    if (!el) { return; }
    var nowTop = {{ $isCurrentPeriod ? $nowTop : ($hourPx * 8) }};
    el.scrollTop = Math.max(0, nowTop - el.clientHeight / 3);
})();
</script>

@if($gridEvents->isEmpty())
    <p class="text-center text-xs text-white/40 mt-3">No events in this {{ $view }}. Use the arrows to browse, or switch to Agenda.</p>
@endif
