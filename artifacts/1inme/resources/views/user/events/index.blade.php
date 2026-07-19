@extends('user.layouts.app')

@section('title', 'Events')

@push('head')
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
<style>
/* FullCalendar theming to match the rest of the app */
#bz-cal{ --fc-border-color: rgba(255,255,255,.08); --fc-page-bg-color: transparent;
         --fc-neutral-bg-color: rgba(255,255,255,.02); --fc-list-event-hover-bg-color: rgba(61,107,255,.12);
         --fc-today-bg-color: rgba(61,107,255,.10); --fc-event-bg-color:#3d6bff; --fc-event-border-color:#3d6bff;
         --fc-event-text-color:#fff; --fc-button-bg-color:rgba(255,255,255,.06);
         --fc-button-border-color:rgba(255,255,255,.10); --fc-button-text-color:var(--text-primary);
         --fc-button-active-bg-color:#3d6bff; --fc-button-active-border-color:#3d6bff;
         --fc-button-hover-bg-color:rgba(61,107,255,.20); --fc-button-hover-border-color:rgba(61,107,255,.30);
         --fc-now-indicator-color:#ec4899; }
#bz-cal .fc-toolbar-title{ color:var(--text-primary); font-weight:700; font-size:1.05rem }
#bz-cal .fc-col-header-cell-cushion,
#bz-cal .fc-daygrid-day-number,
#bz-cal .fc-list-day-cushion,
#bz-cal .fc-timegrid-slot-label-cushion,
#bz-cal .fc-list-event-time,
#bz-cal .fc-list-event-title{ color:var(--text-primary); text-decoration:none }
#bz-cal .fc-day-other .fc-daygrid-day-number{ color:var(--text-faint) }
#bz-cal .fc-list-day-cushion{ background:rgba(61,107,255,.08) !important }
#bz-cal .fc-button{ text-transform:capitalize; font-weight:600; font-size:.78rem; padding:.4rem .8rem; border-radius:.6rem }
#bz-cal .fc-event{ border-radius:6px; padding:1px 4px; font-weight:600; font-size:.72rem; cursor:pointer }
#bz-cal .fc-list-empty{ background:transparent; color:var(--text-muted) }
html.light-mode #bz-cal{
    --fc-border-color:#e2e8f0;
    --fc-page-bg-color:#fff;
    --fc-neutral-bg-color:#f8fafc;
    --fc-button-bg-color:#fff;
    --fc-button-border-color:#e2e8f0;
    --fc-button-text-color:#0f172a;
    --fc-button-hover-bg-color:rgba(61,107,255,.10);
    --fc-button-hover-border-color:rgba(61,107,255,.30);
    --fc-button-active-bg-color:#3d6bff;
    --fc-button-active-border-color:#3d6bff;
    --fc-list-event-hover-bg-color:rgba(61,107,255,.06);
    --fc-today-bg-color:rgba(61,107,255,.06);
    --fc-event-text-color:#fff;
}
html.light-mode #bz-cal .fc-toolbar-title,
html.light-mode #bz-cal .fc-col-header-cell-cushion,
html.light-mode #bz-cal .fc-daygrid-day-number,
html.light-mode #bz-cal .fc-list-day-cushion,
html.light-mode #bz-cal .fc-list-day-cushion *,
html.light-mode #bz-cal .fc-timegrid-slot-label-cushion,
html.light-mode #bz-cal .fc-timegrid-axis-cushion,
html.light-mode #bz-cal .fc-list-event-time,
html.light-mode #bz-cal .fc-list-event-title,
html.light-mode #bz-cal .fc-list-event-title a{ color:#0f172a }
html.light-mode #bz-cal .fc-day-other .fc-daygrid-day-number{ color:#94a3b8 }
html.light-mode #bz-cal .fc-button.fc-button-active,
html.light-mode #bz-cal .fc-button.fc-button-active:focus{ color:#fff !important }
html.light-mode #bz-cal .fc-list-day-cushion{ background:rgba(61,107,255,.06) !important }
html.light-mode #bz-cal .fc-list,
html.light-mode #bz-cal .fc-scrollgrid,
html.light-mode #bz-cal table{ background:#fff }
html.light-mode #bz-cal .fc-list-empty{ color:#64748b }
</style>
@endpush

@section('content')
@include('user.partials._plan_lock', ['feature' => 'events', 'kind' => 'flag', 'label' => 'Events'])
<div class="max-w-7xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'Events',
        'subtitle' => 'Every event you\'ve created, switch between Month, Week, Day and List views.',
        'icon' => 'fa-calendar-day',
        'chips' => [
            ['icon' => 'fa-calendar text-blue-400', 'text' => $totalEvents . ' total event' . ($totalEvents === 1 ? '' : 's')],
            ['icon' => 'fa-clock text-pink-400', 'text' => $upcoming->count() . ' upcoming'],
        ],
        'actions' => [
            ['label' => 'New event', 'url' => route('user.links.create') . '?type=ics', 'icon' => 'fa-plus', 'class' => 'btn-primary'],
        ],
    ])

    <div class="grid grid-cols-1 xl:grid-cols-4 gap-6">
        {{-- Calendar --}}
        <div class="xl:col-span-3">
            <div class="card-premium p-4">
                <div id="bz-cal"></div>
            </div>
            <div class="mt-3 flex flex-wrap gap-3 text-xs" style="color: var(--text-muted);">
                <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded" style="background:#3d6bff"></span> Primary event</span>
                <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded" style="background:#ec4899"></span> Extra schedule</span>
                <span class="ml-auto"><i class="fas fa-info-circle mr-1 opacity-60"></i> Click any event to open it</span>
            </div>
        </div>

        {{-- Upcoming sidebar --}}
        <div class="xl:col-span-1">
            <div class="card-premium p-5">
                <h3 class="text-base font-bold mb-4" style="color: var(--text-primary);">
                    <i class="fas fa-clock text-pink-400 mr-1.5"></i> Upcoming
                </h3>
                @if($upcoming->isEmpty())
                    <div class="text-center py-8">
                        <div class="w-14 h-14 mx-auto rounded-2xl flex items-center justify-center mb-3" style="background: linear-gradient(135deg, rgba(236,72,153,0.18), rgba(92,131,255,0.18));">
                            <i class="fas fa-calendar-plus text-xl text-blue-400"></i>
                        </div>
                        <p class="text-sm font-semibold mb-1" style="color: var(--text-primary);">No upcoming events</p>
                        <p class="text-xs mb-4" style="color: var(--text-muted);">Create your first Event link to see it here.</p>
                        <a href="{{ route('user.links.create') }}?type=ics" class="btn-primary inline-flex items-center gap-1.5 text-xs px-4 py-2">
                            <i class="fas fa-plus text-[10px]"></i> New event
                        </a>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($upcoming as $u)
                            @php
                                $start = $u->start_date;
                                $end   = $u->end_date;
                            @endphp
                            <a href="{{ route('user.links.show', $u->link) }}" class="block p-3 rounded-xl transition" style="background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.06)" onmouseover="this.style.background='rgba(61,107,255,.10)'; this.style.borderColor='rgba(61,107,255,.30)'" onmouseout="this.style.background='rgba(255,255,255,.04)'; this.style.borderColor='rgba(255,255,255,.06)'">
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0 w-12 text-center rounded-lg py-1.5" style="background:rgba(61,107,255,.15); border:1px solid rgba(61,107,255,.25)">
                                        <div class="text-[9px] uppercase font-bold tracking-wider" style="color:#90acff">{{ $start?->format('M') }}</div>
                                        <div class="text-base font-bold leading-none mt-0.5" style="color:#fff">{{ $start?->format('d') }}</div>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="font-semibold text-sm truncate" style="color: var(--text-primary);">{{ $u->event_name ?: $u->link->title }}</div>
                                        <div class="text-xs mt-0.5" style="color: var(--text-muted);">
                                            @if($u->all_day)
                                                <i class="far fa-calendar mr-1"></i> All day
                                            @else
                                                <i class="far fa-clock mr-1"></i>
                                                {{ $start?->format('D, M j · g:i A') }}
                                                @if($end && $end->format('Y-m-d') === $start?->format('Y-m-d')) – {{ $end->format('g:i A') }}@endif
                                            @endif
                                        </div>
                                        @if($u->location)
                                            <div class="text-xs mt-1 truncate" style="color: var(--text-faint);">
                                                <i class="fas fa-map-marker-alt mr-1"></i> {{ $u->location }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('bz-cal');
    if (!el || !window.FullCalendar) return;

    var cal = new FullCalendar.Calendar(el, {
        initialView: 'dayGridMonth',
        height: 'auto',
        firstDay: 1,
        nowIndicator: true,
        navLinks: true,
        dayMaxEvents: 3,
        headerToolbar: {
            left:   'prev,next today',
            center: 'title',
            right:  'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
        },
        buttonText: {
            today: 'Today', month: 'Month', week: 'Week', day: 'Day', list: 'List',
        },
        events: {
            url: '{{ route('user.events.feed') }}',
            failure: function () { console.warn('Failed to load events feed'); },
        },
        eventDidMount: function (info) {
            var loc = info.event.extendedProps.location;
            var desc = info.event.extendedProps.description;
            var bits = [];
            if (loc)  bits.push('📍 ' + loc);
            if (desc) bits.push(String(desc).slice(0, 140));
            if (bits.length) info.el.title = info.event.title + '\n' + bits.join('\n');
        },
    });
    cal.render();
});
</script>
@endsection
