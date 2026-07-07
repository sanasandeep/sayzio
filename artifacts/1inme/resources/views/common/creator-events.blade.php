@extends('public.layouts.site')
@section('title', $creator->name . ' — Events')

@php
    $shareTitle = $creator->name . ' — Events';
    $shareDescription = 'Upcoming events from ' . $creator->name . ' (@' . $creator->handle . ') on ' . config('app.name') . '.';
    $shareImage = $creator->avatar ?? null;
@endphp

{{-- Standalone creator events listing at /@{handle}/events (Task #3666).
     Mirrors the /events directory's card styling/theming but scoped to
     one host and without the search/filter controls — those live only
     on the directory. --}}
@push('head')
<style>
    .ev-card { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:1.25rem; box-shadow:0 16px 40px -26px rgba(0,0,0,0.6); }
    .event-card { transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
    .event-card:hover { transform: translateY(-4px); box-shadow: 0 22px 44px -14px rgba(61,107,255,0.4); border-color: rgba(61,107,255,0.4); }
    .ev-card-date-chip { backdrop-filter: blur(6px); }
    .ev-price-badge { backdrop-filter: blur(6px); }
    .ev-card-img { width:100%; height:100%; object-fit:cover; }
    .line-clamp-2 { display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    .ev-cat-pill { display:inline-flex; align-items:center; gap:.25rem; color:#fff; font-size:10px; font-weight:700; padding:.2rem .5rem; border-radius:999px; }
    .hashtag-pill { background:rgba(255,255,255,0.06); color:rgba(255,255,255,0.5); }
    .hashtag-pill:hover { color:#fff; }
    .link-accent { color:#8fa8ff; }
    .ev-card-footer-divider { border-top:1px solid rgba(255,255,255,0.08); }

    html.light-mode .events-page-body .ev-card { background:#ffffff; border-color:rgba(15,23,42,0.08); box-shadow:0 1px 2px rgba(15,23,42,0.04); }
    html.light-mode .events-page-body .hashtag-pill { background:rgba(15,23,42,0.05); color:rgba(15,23,42,0.55); }
    html.light-mode .events-page-body .hashtag-pill:hover { color:#0f172a; }
    html.light-mode .events-page-body .link-accent { color:#2342c7; }
    html.light-mode .events-page-body .ev-card-footer-divider { border-color:rgba(15,23,42,0.08); }
    html.light-mode .events-page-body .ev-price-badge.text-white { color:#fff !important; }
    html.light-mode .events-page-body .ev-online-pill.text-white { color:#fff !important; }

    /* Theme-aware date chip over the card cover image: the dark glass chip
       becomes a white glass chip with dark day / deep-blue month in light
       mode. !important beats the chip's inline dark background. */
    html.light-mode .events-page-body .ev-card-date-chip { background:rgba(255,255,255,0.92) !important; }
    html.light-mode .events-page-body .ev-card-date-chip [style*="color:#8fa8ff"] { color:#2342c7 !important; }
    html.light-mode .events-page-body .ev-card-date-chip .text-white { color:#0f172a !important; }
</style>
@endpush

@section('content')
<div class="events-page-body max-w-6xl mx-auto px-4 py-8">

    <div class="flex items-center gap-3 mb-6">
        @if($creator->avatar)
            <img src="{{ $creator->avatar }}" alt="" class="w-12 h-12 rounded-xl object-cover">
        @else
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-fuchsia-500 text-white flex items-center justify-center font-extrabold text-sm">
                {{ $creator->getInitials() }}
            </div>
        @endif
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white">{{ $creator->name }}'s events</h1>
            <a href="{{ route('creator-profile.show', $creator->handle) }}" class="link-accent text-sm hover:underline">&#64;{{ $creator->handle }}</a>
        </div>
    </div>

    {{-- Organizer header (Task #3699) — account-wide organizer profile,
         same details shown on the public event detail page. Only renders
         when the creator has actually filled it in. --}}
    @if($organizer['filled'])
        <div class="ev-card p-4 sm:p-5 mb-6">
            <div class="flex items-start gap-3">
                @if($organizer['logo'])
                    <img src="{{ $organizer['logo'] }}" alt="" class="w-14 h-14 rounded-xl object-cover flex-shrink-0">
                @else
                    <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-blue-500 to-fuchsia-500 text-white flex items-center justify-center font-extrabold text-sm flex-shrink-0">
                        {{ $creator->getInitials() }}
                    </div>
                @endif
                <div class="min-w-0">
                    <p class="text-white/40 text-[11px] uppercase tracking-wider font-semibold">Organizer</p>
                    <p class="text-white font-bold">{{ $organizer['name'] ?: $creator->name }}</p>
                    @if($organizer['description'])
                        <p class="text-white/60 text-sm mt-1">{{ $organizer['description'] }}</p>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap gap-x-4 gap-y-1 mt-3 text-sm text-white/70">
                @if($organizer['website'])
                    <a href="{{ $organizer['website'] }}" target="_blank" rel="noopener" class="link-accent hover:underline">
                        <i class="fas fa-globe mr-1"></i>Website
                    </a>
                @endif
                @if($organizer['contact_email'])
                    <a href="mailto:{{ $organizer['contact_email'] }}" class="link-accent hover:underline">
                        <i class="fas fa-envelope mr-1"></i>{{ $organizer['contact_name'] ?: $organizer['contact_email'] }}
                    </a>
                @elseif($organizer['contact_name'])
                    <span><i class="fas fa-user-tie mr-1"></i>{{ $organizer['contact_name'] }}</span>
                @endif
                @if($organizer['contact_phone'])
                    <a href="tel:{{ $organizer['contact_phone'] }}" class="link-accent hover:underline">
                        <i class="fas fa-phone mr-1"></i>{{ $organizer['contact_phone'] }}
                    </a>
                @endif
                @if($organizer['address'])
                    <span><i class="fas fa-location-dot mr-1"></i>{{ $organizer['address'] }}</span>
                @endif
            </div>

            @if(!empty($organizer['socials']))
                <div class="flex flex-wrap gap-2 mt-3">
                    @foreach($organizer['socials'] as $platform => $value)
                        @php
                            $isUrl = str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
                            $isEmail = $platform === 'email';
                            $href = $isEmail ? ('mailto:' . $value) : ($isUrl ? $value : ('https://' . ltrim($value, '@')));
                        @endphp
                        <a href="{{ $href }}" target="_blank" rel="noopener" class="hashtag-pill text-xs px-2.5 py-1 rounded-full">
                            {{ ucfirst($platform) }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    @if($events->count() === 0)
        <div class="text-center py-20 ev-card">
            <i class="fas fa-calendar-xmark text-4xl text-white/20 mb-4"></i>
            <p class="text-white/70 font-semibold">No upcoming events</p>
            <p class="text-white/40 text-sm mt-1">{{ $creator->name }} hasn't announced any events yet.</p>
            <a href="{{ route('creator-profile.show', $creator->handle) }}" class="inline-block mt-4 px-4 py-2 rounded-lg text-white text-sm font-semibold" style="background:#3d6bff;">Back to profile</a>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($events as $event)
                @php
                    $ics = $event->icsData;
                    $cover = $ics->cover_image_url ?? null;
                    $eventCategory = $event->settings['event_category'] ?? '';
                    $eventIsOnline = !empty($event->settings['is_online'] ?? false);
                    $catIcon = $eventCategory !== ''
                        ? \App\Modules\User\Support\EventCategories::icon($eventCategory)
                        : 'fa-calendar-star';
                    $catGradient = \App\Modules\User\Support\EventCategories::gradient($eventCategory);

                    $tiers = $event->eventTicketTiers->sortBy('price_cents')->values();
                    if ($tiers->isEmpty()) {
                        $priceLabel = 'Free RSVP';
                        $priceIsFree = true;
                    } else {
                        $lowest = $tiers->first();
                        $hasRange = $tiers->count() > 1 && (int) $tiers->last()->price_cents !== (int) $lowest->price_cents;
                        $pricePrefix = $hasRange ? 'From ' : '';
                        $priceLabel = $pricePrefix . $lowest->priceLabel();
                        $priceIsFree = $lowest->isFree() && !$hasRange;
                    }
                @endphp
                <div class="event-card ev-card overflow-hidden">
                    <a href="{{ url('/' . $event->alias) }}" class="block relative aspect-[16/10] overflow-hidden">
                        @if($cover)
                            <img src="{{ $cover }}" alt="{{ $event->title }}" loading="lazy" class="ev-card-img">
                        @else
                            <img src="{{ asset('images/events/event-cover-placeholder.svg') }}" alt="{{ $event->title }}" loading="lazy" class="ev-card-img">
                        @endif
                        @if($ics && $ics->start_date)
                            <div class="ev-card-date-chip absolute top-3 left-3 z-10 rounded-xl px-2.5 py-1.5 text-center shadow-sm leading-none" style="background:rgba(11,14,22,0.8);">
                                <div class="text-[10px] font-bold uppercase" style="color:#8fa8ff;">{{ $ics->start_date->format('M') }}</div>
                                <div class="text-base font-extrabold text-white">{{ $ics->start_date->format('j') }}</div>
                            </div>
                        @endif
                        @if($eventIsOnline)
                            <div class="ev-online-pill absolute top-3 {{ $ics && $ics->start_date ? 'left-20' : 'left-3' }} z-10 inline-flex items-center gap-1 text-white rounded-full px-2.5 py-1 text-[10px] font-bold" style="background:rgba(16,185,129,0.85);">
                                <i class="fas fa-video"></i> Online
                            </div>
                        @endif
                        <div class="absolute bottom-3 right-3 z-10">
                            <span class="ev-price-badge inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold {{ $priceIsFree ? 'bg-emerald-500 text-white' : 'text-white' }}"
                                  style="{{ $priceIsFree ? '' : 'background:rgba(61,107,255,0.9);' }}">
                                {{ $priceLabel }}
                            </span>
                        </div>
                    </a>

                    <div class="p-4">
                        <div class="flex items-center gap-2 text-xs text-white/40 mb-1.5">
                            @if($ics && $ics->start_date)
                                <span><i class="far fa-clock mr-1"></i>{{ $ics->start_date->format('D, M j · g:i A') }}</span>
                            @endif
                            @if($eventCategory !== '')
                                <span class="ev-cat-pill" style="background:{{ $catGradient }};">
                                    <i class="fas {{ $catIcon }}"></i> {{ \App\Modules\User\Support\EventCategories::label($eventCategory) }}
                                </span>
                            @endif
                        </div>

                        <a href="{{ url('/' . $event->alias) }}" class="block">
                            <h2 class="font-bold text-white leading-snug line-clamp-2 hover:opacity-80">{{ $event->title }}</h2>
                        </a>

                        @if($ics && $ics->location)
                            <div class="text-xs text-white/40 mt-1.5"><i class="fas fa-location-dot mr-1"></i>{{ $ics->location }}</div>
                        @endif

                        @if($ics && $ics->hashtagList())
                            <div class="flex flex-wrap gap-1 mt-2">
                                @foreach(array_slice($ics->hashtagList(), 0, 4) as $ht)
                                    <span class="hashtag-pill text-[11px] px-2 py-0.5 rounded-full">#{{ $ht }}</span>
                                @endforeach
                            </div>
                        @endif

                        <div class="ev-card-footer-divider flex items-center justify-end mt-4 pt-3">
                            <a href="{{ url('/' . $event->alias) }}" class="link-accent text-xs font-bold hover:opacity-80">View event &rarr;</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-8">{{ $events->links() }}</div>
    @endif

    <div class="text-center mt-10">
        <a href="{{ route('events.index') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-sm font-bold text-white hover:opacity-90" style="background:#3d6bff;">
            <i class="fas fa-calendar-days"></i> Browse all events
        </a>
    </div>
</div>
@endsection
