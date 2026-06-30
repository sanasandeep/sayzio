{{-- Agenda / list view — the original flat event feed. --}}
<div class="space-y-3">
    @forelse($events as $event)
        @php $tz = $event->timezone ?: ($event->calendar?->effectiveTimezone() ?? 'UTC'); @endphp
        <div class="glass rounded-2xl p-5 flex items-start gap-4">
            <div class="flex flex-col items-center justify-center w-14 flex-shrink-0 rounded-xl py-2" style="background: {{ ($event->calendar?->accent_color ?: '#3d6bff') }}1a;">
                <span class="text-[10px] uppercase tracking-wide text-white/50">{{ $event->start_at?->timezone($tz)->format('M') }}</span>
                <span class="text-xl font-bold text-white leading-none">{{ $event->start_at?->timezone($tz)->format('j') }}</span>
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <h3 class="font-semibold text-white truncate">{{ $event->title }}</h3>
                    @if($event->all_day)<span class="text-[10px] px-2 py-0.5 rounded-full bg-white/10 text-white/60">All day</span>@endif
                </div>
                <p class="text-xs text-white/40 mt-1">
                    <i class="far fa-clock mr-1"></i>
                    {{ $event->start_at?->timezone($tz)->format('D, g:i A') }}
                    @if($event->end_at) &ndash; {{ $event->end_at->timezone($tz)->format('g:i A') }} @endif
                    @if($event->calendar)
                        <span class="text-white/25">&middot; {{ $event->calendar->title }}</span>
                    @endif
                </p>
                @if($event->location)<p class="text-xs text-white/40 mt-0.5"><i class="fas fa-location-dot mr-1"></i>{{ $event->location }}</p>@endif
                @if(!empty($event->hashtags))
                    <div class="flex flex-wrap gap-1 mt-2">
                        @foreach($event->hashtags as $tag)
                            <a href="{{ $linkTo(['tag' => $tag]) }}" class="text-[11px] px-2 py-0.5 rounded-full bg-blue-500/10 text-blue-300 hover:bg-blue-500/20">#{{ $tag }}</a>
                        @endforeach
                    </div>
                @endif
            </div>
            @if($event->payment_url)
                <a href="{{ $event->payment_url }}" target="_blank" rel="noopener" class="flex-shrink-0 px-3 py-1.5 rounded-lg text-xs font-medium border border-white/10 text-white/70 hover:text-white hover:border-white/30">
                    <i class="fas fa-ticket mr-1"></i> Tickets
                </a>
            @endif
        </div>
    @empty
        <div class="glass rounded-2xl p-10 text-center text-white/40">
            <i class="far fa-calendar text-3xl mb-3 block"></i>
            No events match. Follow a public calendar or create your own.
        </div>
    @endforelse
</div>

@if($events->hasPages())
    <div>{{ $events->links() }}</div>
@endif
