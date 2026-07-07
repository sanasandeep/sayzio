<!DOCTYPE html>
<html lang="en" class="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $calendar->title }} &middot; {{ config('app.name') }}</title>
<meta name="description" content="{{ \Illuminate\Support\Str::limit($calendar->description ?: ('Follow ' . $calendar->title . ' on ' . config('app.name')), 180) }}">
<meta property="og:title" content="{{ $calendar->title }}">
<meta property="og:description" content="{{ \Illuminate\Support\Str::limit($calendar->description ?: ('Follow ' . $calendar->title), 180) }}">
<meta property="og:type" content="website">
@vite(['resources/css/app.css', 'resources/js/app.js'])
@include('common.partials.fontawesome')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root{ --cal-accent: {{ $calendar->accent_color ?: '#3d6bff' }}; }
    body{ font-family:'Space Grotesk',sans-serif; background:#0b0e16; color:#e8eaf0; min-height:100vh; }
    .cal-card{ background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:1rem; }
    .cal-accent-bg{ background:var(--cal-accent); }
    .cal-accent-text{ color:var(--cal-accent); }
    .cal-chip{ background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.08); }
    .cal-chip.is-active{ background:var(--cal-accent); border-color:var(--cal-accent); color:#fff; }
    [x-cloak]{ display:none !important; }
</style>
</head>
<body>
<div class="max-w-2xl mx-auto px-4 py-10">

    {{-- ── Header ────────────────────────────────────────────── --}}
    <header class="text-center mb-8" x-data="calFollow()">
        <div class="w-16 h-16 mx-auto rounded-2xl flex items-center justify-center text-2xl text-white cal-accent-bg mb-4">
            <i class="far fa-calendar-days"></i>
        </div>
        <h1 class="text-3xl font-bold">{{ $calendar->title }}</h1>
        @if($calendar->description)
            <p class="text-white/50 mt-2 max-w-md mx-auto">{{ $calendar->description }}</p>
        @endif
        <p class="text-xs text-white/30 mt-2"><span x-text="followers">{{ (int) $calendar->followers_count }}</span> follower(s)</p>

        <div class="flex items-center justify-center gap-2 mt-5 flex-wrap">
            @if($isOwner)
                <a href="{{ route('user.calendars.editor', $link->id) }}" class="px-5 py-2.5 rounded-xl text-sm font-semibold cal-accent-bg text-white">
                    <i class="fas fa-pen mr-1"></i> Manage
                </a>
            @elseif($viewer)
                <button type="button" @click="toggle()" :disabled="busy"
                        class="px-6 py-2.5 rounded-xl text-sm font-semibold transition"
                        :class="following ? 'border border-white/15 text-white/70' : 'cal-accent-bg text-white'">
                    <span x-show="!following"><i class="fas fa-plus mr-1"></i> Follow</span>
                    <span x-show="following" x-cloak><i class="fas fa-check mr-1"></i> Following</span>
                </button>
            @else
                <a href="{{ route('login') }}?redirect={{ urlencode(request()->fullUrl()) }}" class="px-6 py-2.5 rounded-xl text-sm font-semibold cal-accent-bg text-white">
                    <i class="fas fa-plus mr-1"></i> Sign in to follow
                </a>
            @endif

            <a href="{{ $googleUrl }}" target="_blank" rel="noopener" class="px-4 py-2.5 rounded-xl text-sm font-medium cal-chip text-white/70 hover:text-white">
                <i class="fab fa-google mr-1"></i> Google
            </a>
            <a href="{{ $icsUrl }}" class="px-4 py-2.5 rounded-xl text-sm font-medium cal-chip text-white/70 hover:text-white">
                <i class="fas fa-rss mr-1"></i> Subscribe (.ics)
            </a>
        </div>
    </header>

    {{-- ── Filters ───────────────────────────────────────────── --}}
    <form method="GET" class="mb-5">
        <div class="flex flex-wrap gap-2 mb-3">
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Search events…"
                   class="flex-1 min-w-[180px] border border-white/10 rounded-xl px-3 py-2.5 text-sm bg-white/5 focus:ring-2 focus:ring-white/20">
            <input type="text" name="location" value="{{ $filters['location'] ?? '' }}" placeholder="Location…"
                   class="flex-1 min-w-[140px] border border-white/10 rounded-xl px-3 py-2.5 text-sm bg-white/5 focus:ring-2 focus:ring-white/20">
            @if($filters['past'])<input type="hidden" name="past" value="1">@endif
            @if(!empty($filters['tag']))<input type="hidden" name="tag" value="{{ $filters['tag'] }}">@endif
            <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold cal-accent-bg text-white">Search</button>
        </div>
        <div class="flex flex-wrap items-center gap-2 mb-3 text-xs text-white/50">
            <label class="flex items-center gap-1.5">From
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"
                       class="border border-white/10 rounded-lg px-2 py-1.5 text-xs bg-white/5 focus:ring-2 focus:ring-white/20 text-white/80">
            </label>
            <label class="flex items-center gap-1.5">To
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"
                       class="border border-white/10 rounded-lg px-2 py-1.5 text-xs bg-white/5 focus:ring-2 focus:ring-white/20 text-white/80">
            </label>
            @if(!empty($filters['from']) || !empty($filters['to']) || !empty($filters['location']))
                <a href="{{ url()->current() }}{{ $filters['past'] ? '?past=1' : '' }}" class="text-white/40 hover:text-white/60">Clear filters</a>
            @endif
        </div>
        @if(!empty($allTags))
            <div class="flex flex-wrap gap-1.5">
                <a href="{{ url()->current() }}{{ $filters['past'] ? '?past=1' : '' }}" class="cal-chip {{ !$filters['tag'] ? 'is-active' : '' }} text-[11px] px-2.5 py-1 rounded-full text-white/70">All</a>
                @foreach($allTags as $tag)
                    <a href="{{ url()->current() }}?tag={{ urlencode($tag) }}{{ $filters['past'] ? '&past=1' : '' }}"
                       class="cal-chip {{ $filters['tag'] === $tag ? 'is-active' : '' }} text-[11px] px-2.5 py-1 rounded-full text-white/70">#{{ $tag }}</a>
                @endforeach
            </div>
        @endif
        <div class="mt-2 text-right">
            @if($filters['past'])
                <a href="{{ url()->current() }}{{ $filters['tag'] ? '?tag=' . urlencode($filters['tag']) : '' }}" class="text-[11px] text-white/40 hover:text-white/60">Hide past events</a>
            @else
                <a href="{{ url()->current() }}?past=1{{ $filters['tag'] ? '&tag=' . urlencode($filters['tag']) : '' }}" class="text-[11px] text-white/40 hover:text-white/60">Show past events</a>
            @endif
        </div>
    </form>

    {{-- ── Events ────────────────────────────────────────────── --}}
    <div class="space-y-3">
        @forelse($events as $event)
            @php $tz = $event->timezone ?: $calendar->effectiveTimezone(); @endphp
            <div class="cal-card p-5 flex items-start gap-4">
                <div class="flex flex-col items-center justify-center w-14 flex-shrink-0 rounded-xl py-2" style="background: var(--cal-accent);">
                    <span class="text-[10px] uppercase tracking-wide text-white/80">{{ $event->start_at?->timezone($tz)->format('M') }}</span>
                    <span class="text-xl font-bold text-white leading-none">{{ $event->start_at?->timezone($tz)->format('j') }}</span>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="font-semibold text-white truncate">{{ $event->title }}</h3>
                        @if($event->all_day)<span class="text-[10px] px-2 py-0.5 rounded-full bg-white/10 text-white/60">All day</span>@endif
                    </div>
                    <p class="text-xs text-white/40 mt-1">
                        <i class="far fa-clock mr-1"></i>
                        {{ $event->start_at?->timezone($tz)->format('D, M j, g:i A') }}
                        @if($event->end_at) &ndash; {{ $event->end_at->timezone($tz)->format('g:i A') }} @endif
                        <span class="text-white/25">({{ $tz }})</span>
                    </p>
                    @if($event->description)<p class="text-sm text-white/55 mt-2">{{ $event->description }}</p>@endif
                    @if($event->location)
                        <p class="text-xs text-white/40 mt-1.5">
                            <i class="fas fa-location-dot mr-1"></i>
                            @if($event->lat && $event->lng)
                                <a href="https://www.google.com/maps/search/?api=1&query={{ $event->lat }},{{ $event->lng }}" target="_blank" rel="noopener" class="hover:underline cal-accent-text">{{ $event->location }}</a>
                            @else
                                {{ $event->location }}
                            @endif
                        </p>
                    @endif
                    @if(!empty($event->hashtags))
                        <div class="flex flex-wrap gap-1 mt-2">
                            @foreach($event->hashtags as $tag)
                                <a href="{{ url()->current() }}?tag={{ urlencode($tag) }}" class="text-[11px] px-2 py-0.5 rounded-full bg-white/8 text-white/60 hover:text-white">#{{ $tag }}</a>
                            @endforeach
                        </div>
                    @endif
                    @if($event->payment_url)
                        <a href="{{ $event->payment_url }}" target="_blank" rel="noopener" class="inline-flex items-center mt-3 px-4 py-1.5 rounded-lg text-xs font-semibold cal-accent-bg text-white">
                            <i class="fas fa-ticket mr-1"></i> Get tickets
                        </a>
                    @endif
                </div>
            </div>
        @empty
            <div class="cal-card p-10 text-center text-white/40">
                <i class="far fa-calendar text-3xl mb-3 block"></i>
                No {{ $filters['past'] ? '' : 'upcoming ' }}events yet.
            </div>
        @endforelse
    </div>

    <footer class="text-center mt-10 text-xs text-white/25">
        Powered by <a href="{{ url('/') }}" class="cal-accent-text hover:underline">{{ config('app.name') }}</a>
    </footer>
</div>

<script>
function calFollow(){
    return {
        following: {{ $isFollowing ? 'true' : 'false' }},
        followers: {{ (int) $calendar->followers_count }},
        busy: false,
        async toggle(){
            if (this.busy) return;
            this.busy = true;
            try {
                const res = await fetch('{{ route('public.calendars.follow', $calendar->id) }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                });
                if (res.status === 401) { window.location = '{{ route('login') }}?redirect=' + encodeURIComponent(window.location.href); return; }
                const data = await res.json();
                if (data.success) {
                    this.following = data.following;
                    this.followers = data.followers_count;
                }
            } catch (e) { /* swallow */ }
            this.busy = false;
        },
    };
}
</script>
</body>
</html>
