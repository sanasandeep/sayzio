{{-- Agenda / list view — the original flat event feed, modernized. --}}
@php
    use Illuminate\Support\Carbon;
    $today = Carbon::now($userTz ?? 'UTC')->format('Y-m-d');
    $prevDateKey = null;
@endphp

<div class="space-y-1.5">
    @forelse($events as $event)
        @php
            $tz        = $event->timezone ?: ($event->calendar?->effectiveTimezone() ?? 'UTC');
            $accent    = $event->calendar?->accent_color ?: '#3d6bff';
            $dateKey   = $event->start_at?->timezone($tz)->format('Y-m-d');
            $isToday   = $dateKey === $today;
            $isPast    = $dateKey && $dateKey < $today;
            $showDivider = $dateKey !== $prevDateKey;
            $prevDateKey = $dateKey;
        @endphp

        {{-- Date group divider --}}
        @if($showDivider && $dateKey)
            <div class="flex items-center gap-3 pt-3 first:pt-0 pb-1 px-1">
                <span class="text-[11px] font-semibold uppercase tracking-widest
                    {{ $isToday ? 'text-blue-400' : ($isPast ? 'text-white/25' : 'text-white/40') }}">
                    @if($isToday)
                        Today · {{ $event->start_at->timezone($tz)->isoFormat('MMMM D, YYYY') }}
                    @else
                        {{ $event->start_at->timezone($tz)->isoFormat('dddd, MMMM D, YYYY') }}
                    @endif
                </span>
                <div class="flex-1 h-px bg-white/[0.06]"></div>
            </div>
        @endif

        {{-- Event card --}}
        <div class="group glass rounded-2xl p-4 flex items-start gap-4 transition hover:bg-white/[0.04]
            {{ $isPast ? 'opacity-60' : '' }}">

            {{-- Accent date badge --}}
            <div class="flex flex-col items-center justify-center w-12 flex-shrink-0 rounded-xl py-2.5 gap-0.5"
                 style="background: {{ $accent }}18; border: 1px solid {{ $accent }}30;">
                <span class="text-[9px] uppercase tracking-wider font-semibold" style="color: {{ $accent }}99;">
                    {{ $event->start_at?->timezone($tz)->format('M') }}
                </span>
                <span class="text-[22px] font-extrabold leading-none" style="color: {{ $accent }}ee;">
                    {{ $event->start_at?->timezone($tz)->format('j') }}
                </span>
            </div>

            {{-- Content --}}
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <h3 class="font-semibold text-white/90 group-hover:text-white transition truncate">{{ $event->title }}</h3>
                    @if($event->all_day)
                        <span class="text-[10px] px-2 py-0.5 rounded-full border border-white/10 text-white/50">All day</span>
                    @endif
                    @if($isToday)
                        <span class="text-[10px] px-2 py-0.5 rounded-full bg-blue-500/15 text-blue-300 font-medium">Today</span>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-1.5">
                    @unless($event->all_day)
                        <span class="text-xs text-white/45 flex items-center gap-1">
                            <i class="far fa-clock text-[10px]"></i>
                            {{ $event->start_at?->timezone($tz)->format('g:i A') }}
                            @if($event->end_at) &ndash; {{ $event->end_at->timezone($tz)->format('g:i A') }} @endif
                        </span>
                    @endunless
                    @if($event->calendar)
                        <span class="text-xs flex items-center gap-1.5 text-white/35">
                            <span class="w-2 h-2 rounded-full flex-shrink-0" style="background: {{ $accent }};"></span>
                            {{ $event->calendar->title }}
                        </span>
                    @endif
                    @if($event->location)
                        <span class="text-xs text-white/40 flex items-center gap-1">
                            <i class="fas fa-location-dot text-[10px]"></i>{{ $event->location }}
                        </span>
                    @endif
                </div>

                @if(!empty($event->hashtags))
                    <div class="flex flex-wrap gap-1 mt-2">
                        @foreach($event->hashtags as $tag)
                            <a href="{{ $linkTo(['tag' => $tag]) }}"
                               class="text-[11px] px-2 py-0.5 rounded-full bg-blue-500/10 text-blue-300/80 hover:bg-blue-500/20 hover:text-blue-300 transition">
                                #{{ $tag }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Ticket CTA --}}
            @if($event->payment_url)
                <a href="{{ $event->payment_url }}" target="_blank" rel="noopener"
                   class="flex-shrink-0 px-3 py-1.5 rounded-lg text-xs font-medium border border-white/10 text-white/60 hover:text-white hover:border-white/30 transition">
                    <i class="fas fa-ticket mr-1"></i> Tickets
                </a>
            @endif
        </div>
    @empty
        <div class="glass rounded-2xl p-12 text-center">
            <i class="far fa-calendar-xmark text-3xl mb-3 block text-white/20"></i>
            <p class="text-white/40 text-sm">No events match your filters.</p>
            <p class="text-white/25 text-xs mt-1">Follow a public calendar or create your own to get started.</p>
        </div>
    @endforelse
</div>

@if($events->hasPages())
    <div class="mt-4">{{ $events->links() }}</div>
@endif
