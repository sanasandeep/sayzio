{{-- Events running now or starting within the day, as host or as guest.
     Renders nothing at all when there are none: an empty state here would
     be a permanent band of furniture above the numbers people come for. --}}
{{-- Block form only in this file, deliberately. Blade lifts raw PHP
     blocks out before it compiles directives, and the match is
     non-greedy, so a one-line parenthesised PHP directive earlier in a
     file pairs with the first block terminator below it and swallows
     everything between -- the opening conditional of this partial
     included. The page then fails to parse at its last line. --}}
@php
    $eventsSoon = collect($eventsSoon ?? []);
    $limit = \App\Modules\User\Support\EventsHappeningSoon::LIST_LIMIT;
    $shown = $eventsSoon->take($limit);
    $extra = $eventsSoon->count() - $shown->count();
    $tz = \App\Support\PlatformTimezone::forUser(auth()->user());
@endphp
@if($eventsSoon->isNotEmpty())
<style>
    .evs { display: flex; flex-direction: column; gap: 10px; margin-bottom: 18px; }
    .evs-row { display: flex; align-items: stretch; gap: 20px; flex-wrap: wrap;
               border: 1px solid var(--ov-border, rgba(148,163,184,0.22)); border-radius: 16px;
               padding: 16px 20px; background: var(--ov-panel-bg, rgba(255,255,255,0.02)); }
    .evs-when { min-width: 132px; display: flex; flex-direction: column; gap: 6px; }
    .evs-flag { display: inline-flex; align-items: center; gap: 7px; align-self: flex-start;
                border-radius: 999px; padding: 4px 11px; font-size: 10px; font-weight: 800;
                letter-spacing: 0.07em; text-transform: uppercase; }
    .evs-flag-live { background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.34); color: #f87171; }
    .evs-flag-soon { background: rgba(245,158,11,0.12); border: 1px solid rgba(245,158,11,0.34); color: #fbbf24; }
    .evs-flag-guest { background: rgba(13,148,136,0.12); border: 1px solid rgba(13,148,136,0.36); color: #2dd4bf; }
    .evs-dot { width: 6px; height: 6px; border-radius: 999px; background: currentColor; }
    .evs-dot-live { animation: evsBreath 2.4s ease-in-out infinite; }
    @keyframes evsBreath { 0%, 100% { opacity: 1; } 50% { opacity: 0.35; } }
    @media (prefers-reduced-motion: reduce) { .evs-dot-live { animation: none; } }
    .evs-count { font-size: 20px; font-weight: 700; letter-spacing: -0.015em; font-variant-numeric: tabular-nums; }
    .evs-body { flex: 1 1 260px; min-width: 0; display: flex; flex-direction: column; justify-content: center; gap: 4px; }
    .evs-title { margin: 0; font-size: 16px; font-weight: 650; letter-spacing: -0.01em; }
    .evs-act { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .evs-btn { display: inline-flex; align-items: center; justify-content: center; min-height: 40px;
               padding: 10px 16px; border-radius: 11px; font-size: 13px; font-weight: 650;
               text-decoration: none; background: #3d6bff; color: #fff; }
    .evs-btn-quiet { background: transparent; color: inherit; border: 1px solid var(--ov-border, rgba(148,163,184,0.26)); font-weight: 600; }
    .evs-more { font-size: 13px; }
</style>
<section class="evs" aria-label="Events happening now or soon">
    @foreach($shown as $row)
        @php
            $link = $row['link'];
            $ics = $row['ics'];
            $isHost = $row['role'] === 'host';
            $start = $ics->start_date ? $ics->start_date->copy()->setTimezone($tz) : null;
        @endphp
        <article class="evs-row">
            <div class="evs-when">
                @if($row['live'])
                    <span class="evs-flag evs-flag-live"><span class="evs-dot evs-dot-live"></span>Live now</span>
                @elseif($isHost)
                    <span class="evs-flag evs-flag-soon"><span class="evs-dot"></span>Starts soon</span>
                @else
                    <span class="evs-flag evs-flag-guest"><span class="evs-dot"></span>You are going</span>
                @endif
                <span class="evs-count">{{ $start ? $start->diffForHumans(['parts' => 1, 'short' => false]) : 'Scheduled' }}</span>
                <span class="ov-muted">{{ $start ? $start->format('g:i A') : '' }}</span>
            </div>

            <div class="evs-body">
                <h2 class="evs-title">{{ $ics->event_name ?: $link->title }}</h2>
                <p class="ov-muted">
                    {{ $ics->location ?: 'Online' }}@unless($isHost) · hosted by {{ $link->user->name ?? 'the organiser' }}@endunless
                </p>
            </div>

            <div class="evs-act">
                @if($isHost)
                    <a class="evs-btn" href="{{ route('user.links.ics.tickets', $link) }}">Check people in</a>
                    <a class="evs-btn evs-btn-quiet" href="{{ route('user.links.ics.edit', $link) }}">Manage</a>
                @else
                    <a class="evs-btn evs-btn-quiet" href="{{ url('/' . $link->alias) }}">Open event</a>
                @endif
            </div>
        </article>
    @endforeach

    @if($extra > 0)
        <p class="ov-muted evs-more">And {{ $extra }} {{ \Illuminate\Support\Str::plural('other event', $extra) }} in the next 24 hours.</p>
    @endif
</section>
@endif
