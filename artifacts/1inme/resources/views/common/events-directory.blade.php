<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Discover Events — {{ config('app.name') }}</title>
    <meta name="description" content="Find and RSVP to events near you — powered by {{ config('app.name') }}.">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/vendor/fontawesome-free-6.5.1/css/all.min.css') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family:'Space Grotesk',sans-serif; background:#0b0e16; color:#e8eaf0; min-height:100vh; }
        .events-hero {
            background:
                radial-gradient(1200px 400px at 15% -10%, rgba(255,255,255,0.10), transparent 60%),
                linear-gradient(135deg, #0b0e16 0%, #14204a 55%, #2342c7 130%);
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .ev-card { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:1rem; }
        .ev-chip { background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.10); color:rgba(255,255,255,0.7); }
        .ev-chip.active, .ev-chip:hover { background:#3d6bff; border-color:#3d6bff; color:#fff; }
        .event-card { transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
        .event-card:hover { transform: translateY(-4px); box-shadow: 0 20px 40px -14px rgba(61,107,255,0.35); border-color: rgba(61,107,255,0.4); }
        .line-clamp-2 { display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
        .tier-breakdown.tier-open .tier-chevron { transform: rotate(180deg); }
        .cat-tile { position:relative; border-radius:1rem; overflow:hidden; min-height:96px; transition:transform .15s ease; }
        .cat-tile:hover { transform: translateY(-3px); }
        .cat-tile.active { outline:2px solid #fff; outline-offset:-2px; }
        .hero-slide { display:none; }
        .hero-slide.active { display:block; }
        .hero-dot { width:7px; height:7px; border-radius:999px; background:rgba(255,255,255,0.3); }
        .hero-dot.active { background:#fff; width:20px; border-radius:999px; }
        #search-map { height:200px; border-radius:0.9rem; }
        [x-cloak] { display:none !important; }
    </style>
</head>
<body>

{{-- ── Hero: search + slider of featured events ─────────────────── --}}
<div class="events-hero text-white px-4 py-12 sm:py-16">
    <div class="max-w-5xl mx-auto text-center">
        <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight">Discover Events</h1>
        <p class="mt-2 text-white/60 text-sm sm:text-base max-w-xl mx-auto">Find happenings near you, from meetups to festivals — powered by {{ config('app.name') }}.</p>

        <form method="GET" class="mt-7 max-w-3xl mx-auto" x-data="eventSearchMap({{ $lat ? "'".$lat."'" : 'null' }}, {{ $lng ? "'".$lng."'" : 'null' }})">
            <div class="flex flex-col sm:flex-row gap-2 rounded-2xl p-2" style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12);">
                <div class="flex-1 flex items-center gap-2 px-3">
                    <i class="fas fa-magnifying-glass text-white/40"></i>
                    <input type="text" name="q" value="{{ $q }}" placeholder="Search events, locations, or #hashtags"
                           class="w-full py-2.5 bg-transparent text-white text-sm focus:outline-none placeholder:text-white/35">
                </div>
                <button type="button" id="near-me-btn"
                        class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-semibold ev-chip">
                    <i class="fas fa-location-arrow"></i> Near me
                </button>
                <button type="button" @click="toggleMap()"
                        class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-semibold ev-chip">
                    <i class="fas fa-map-location-dot"></i> Map search
                </button>
                <button type="submit"
                        class="px-6 py-2.5 rounded-xl text-sm font-bold text-white" style="background:#3d6bff;">
                    Search
                </button>
            </div>

            <div x-show="showMap" x-cloak x-transition class="mt-3 p-3 rounded-2xl text-left" style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.10);">
                <p class="text-xs text-white/50 mb-2"><i class="fas fa-hand-pointer mr-1"></i> Drag the pin or click the map to search a location.</p>
                <div id="search-map" x-ref="map"></div>
                <div class="flex items-center justify-between mt-2 text-xs text-white/50">
                    <span x-text="address || 'No location selected'" class="truncate max-w-[70%]"></span>
                    <button type="button" @click="applyToForm()" class="px-3 py-1.5 rounded-lg font-semibold text-white" style="background:#3d6bff;">Use this location</button>
                </div>
            </div>

            <input type="hidden" name="lat" x-ref="latInput" value="{{ $lat }}">
            <input type="hidden" name="lng" x-ref="lngInput" value="{{ $lng }}">
            <input type="hidden" name="category" value="{{ $category }}">
            <input type="hidden" name="tag" value="{{ $tag }}">
            @if($online)<input type="hidden" name="online" value="1">@endif
        </form>

        {{-- Hero slider: 2-3 featured upcoming events. --}}
        @if(isset($heroEvents) && $heroEvents->isNotEmpty())
            <div class="mt-9 max-w-3xl mx-auto text-left" x-data="heroSlider({{ $heroEvents->count() }})" x-init="start()">
                @foreach($heroEvents as $hi => $hero)
                    @php $hIcs = $hero->icsData; $hCat = $hero->settings['event_category'] ?? ''; @endphp
                    <a href="{{ url('/' . $hero->alias) }}" class="hero-slide {{ $hi === 0 ? 'active' : '' }}" data-slide="{{ $hi }}">
                        <div class="ev-card flex flex-col sm:flex-row items-stretch overflow-hidden hover:border-white/20">
                            <div class="sm:w-2/5 h-40 sm:h-auto relative">
                                @if($hIcs && $hIcs->cover_image_url)
                                    <img src="{{ $hIcs->cover_image_url }}" alt="{{ $hero->title }}" class="w-full h-full object-cover">
                                @else
                                    <img src="{{ asset('images/events/event-cover-placeholder.svg') }}" alt="{{ $hero->title }}" class="w-full h-full object-cover">
                                @endif
                                <span class="absolute top-3 left-3 text-[10px] font-bold uppercase px-2.5 py-1 rounded-full text-white" style="background:#3d6bff;">Featured</span>
                            </div>
                            <div class="p-5 flex-1">
                                @if($hIcs && $hIcs->start_date)
                                    <div class="text-xs text-white/40 mb-1"><i class="far fa-clock mr-1"></i>{{ $hIcs->start_date->format('D, M j · g:i A') }}</div>
                                @endif
                                <h3 class="font-bold text-lg text-white leading-snug line-clamp-2">{{ $hero->title }}</h3>
                                @if($hIcs && $hIcs->location)
                                    <div class="text-xs text-white/40 mt-1.5"><i class="fas fa-location-dot mr-1"></i>{{ $hIcs->location }}</div>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
                @if($heroEvents->count() > 1)
                    <div class="flex items-center justify-center gap-1.5 mt-3">
                        @foreach($heroEvents as $hi => $hero)
                            <button type="button" class="hero-dot {{ $hi === 0 ? 'active' : '' }}" @click.prevent="go({{ $hi }})"></button>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>

<div class="max-w-6xl mx-auto px-4 py-8">

    {{-- Gradient category tiles. --}}
    @if($categories->isNotEmpty() || $hasOtherCategory)
        <div class="mb-6">
            <div class="text-xs font-bold uppercase tracking-wide text-white/40 mb-3">Browse by category</div>
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
                <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->except(['category', 'page']), ['category' => ''])) }}"
                   class="cat-tile {{ $category === '' ? 'active' : '' }} flex flex-col items-center justify-center gap-1.5 text-white text-sm font-semibold p-4"
                   style="background: linear-gradient(135deg, #334155 0%, #1e293b 100%);">
                    <i class="fas fa-layer-group text-lg"></i> All events
                </a>
                @foreach($categories as $c)
                    <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->except(['category', 'page']), ['category' => $category === $c ? '' : $c])) }}"
                       class="cat-tile {{ $category === $c ? 'active' : '' }} flex flex-col items-center justify-center gap-1.5 text-white text-sm font-semibold p-4 text-center"
                       style="background: {{ $categoryColors[$c] ?? \App\Modules\User\Support\EventCategories::gradient($c) }};">
                        <i class="fas {{ $categoryIcons[$c] ?? 'fa-calendar-star' }} text-lg"></i> {{ $categoryLabels[$c] ?? ucfirst($c) }}
                    </a>
                @endforeach
                @if($hasOtherCategory)
                    <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->except(['category', 'page']), ['category' => $category === $otherCategory ? '' : $otherCategory])) }}"
                       class="cat-tile {{ $category === $otherCategory ? 'active' : '' }} flex flex-col items-center justify-center gap-1.5 text-white text-sm font-semibold p-4"
                       style="background: linear-gradient(135deg, #3d6bff 0%, #1e293b 100%);">
                        <i class="fas fa-ellipsis text-lg"></i> Other
                    </a>
                @endif
            </div>
        </div>
    @endif

    {{-- Online filter toggle. --}}
    <div class="flex flex-wrap items-center gap-2 mb-5">
        <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->except(['online', 'page']), $online ? [] : ['online' => 1])) }}"
           class="ev-chip {{ $online ? 'active' : '' }} inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-semibold">
            <i class="fas fa-video"></i> Online events only
        </a>
    </div>

    {{-- Trending hashtag row (recency-weighted). --}}
    @if($trendingTags->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2 mb-6">
            <span class="text-xs font-bold uppercase tracking-wide text-white/40 mr-1"><i class="fas fa-fire mr-1" style="color:#f59e0b;"></i> Trending:</span>
            @foreach($trendingTags as $trendingTag)
                <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->except('tag', 'page'), ['tag' => $tag === $trendingTag ? '' : $trendingTag])) }}"
                   class="ev-chip {{ $tag === $trendingTag ? 'active' : '' }} inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold">
                    #{{ $trendingTag }}
                </a>
            @endforeach
        </div>
    @endif

    {{-- Active filters summary. --}}
    @if($nearMe || $tag || $category || $q || $online)
        <div class="flex flex-wrap items-center gap-2 mb-6 text-xs text-white/50">
            <span class="font-semibold text-white/40">Active filters:</span>
            @if($q)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full" style="background:rgba(255,255,255,0.06);">
                    "{{ $q }}"
                    <a href="{{ url()->current() }}?{{ http_build_query(request()->except(['q', 'page'])) }}" class="text-white/30 hover:text-white">&times;</a>
                </span>
            @endif
            @if($category)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full" style="background:rgba(255,255,255,0.06);">
                    @if($category === $otherCategory)
                        <i class="fas fa-ellipsis"></i> Other
                    @else
                        <i class="fas {{ $categoryIcons[$category] ?? 'fa-calendar-star' }}"></i> {{ $categoryLabels[$category] ?? \App\Modules\User\Support\EventCategories::label($category) }}
                    @endif
                    <a href="{{ url()->current() }}?{{ http_build_query(request()->except(['category', 'page'])) }}" class="text-white/30 hover:text-white">&times;</a>
                </span>
            @endif
            @if($tag)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full" style="background:rgba(255,255,255,0.06);">
                    #{{ $tag }}
                    <a href="{{ url()->current() }}?{{ http_build_query(request()->except(['tag', 'page'])) }}" class="text-white/30 hover:text-white">&times;</a>
                </span>
            @endif
            @if($online)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full" style="background:rgba(255,255,255,0.06);">
                    <i class="fas fa-video"></i> Online
                    <a href="{{ url()->current() }}?{{ http_build_query(request()->except(['online', 'page'])) }}" class="text-white/30 hover:text-white">&times;</a>
                </span>
            @endif
            @if($nearMe)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full" style="background:rgba(255,255,255,0.06);">
                    <i class="fas fa-location-arrow"></i> Within {{ $radiusKm }}km
                    <a href="{{ url()->current() }}?{{ http_build_query(request()->except(['lat', 'lng', 'page'])) }}" class="text-white/30 hover:text-white">&times;</a>
                </span>
            @endif
            <a href="{{ route('events.index') }}" class="hover:underline font-semibold" style="color:#8fa8ff;">Clear all</a>
        </div>
    @endif

    @if($events->count() === 0)
        <div class="text-center py-20 ev-card">
            <i class="fas fa-calendar-xmark text-4xl text-white/20 mb-4"></i>
            <p class="text-white/70 font-semibold">No events found</p>
            <p class="text-white/40 text-sm mt-1">Try a different search, category, or clear your filters.</p>
            @if($nearMe || $tag || $category || $q || $online)
                <a href="{{ route('events.index') }}" class="inline-block mt-4 px-4 py-2 rounded-lg text-white text-sm font-semibold" style="background:#3d6bff;">Clear filters</a>
            @endif
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
                        $priceLabel = ($hasRange ? 'From ' : '') . $lowest->priceLabel();
                        $priceIsFree = $lowest->isFree() && !$hasRange;
                    }

                    // Gallery preview: cover first, then distinct gallery
                    // images. Capped so the hover cycle stays lightweight.
                    $gallery = array_values(array_filter(
                        array_map('trim', (array) ($ics?->gallery ?? [])),
                        fn ($u) => $u !== ''
                    ));
                    $previewImages = [];
                    if ($cover) $previewImages[] = $cover;
                    foreach ($gallery as $g) {
                        if ($g !== $cover) $previewImages[] = $g;
                    }
                    $previewImages = array_slice(array_values(array_unique($previewImages)), 0, 5);
                    $photoCount = count($previewImages);
                @endphp
                <div class="event-card ev-card overflow-hidden">
                    <a href="{{ url('/' . $event->alias) }}"
                       class="block relative aspect-[16/10] overflow-hidden {{ $photoCount > 1 ? 'event-media' : '' }}">
                        @if($photoCount > 0)
                            @foreach($previewImages as $pi => $imgUrl)
                                <img src="{{ $imgUrl }}" alt="{{ $event->title }}" loading="lazy" data-idx="{{ $pi }}"
                                     class="event-media-img absolute inset-0 w-full h-full object-cover transition-opacity duration-500 {{ $pi === 0 ? 'opacity-100' : 'opacity-0' }}">
                            @endforeach
                        @else
                            <img src="{{ asset('images/events/event-cover-placeholder.svg') }}" alt="{{ $event->title }}" loading="lazy" class="w-full h-full object-cover">
                        @endif
                        @if($ics && $ics->start_date)
                            <div class="absolute top-3 left-3 z-10 rounded-xl px-2.5 py-1.5 text-center shadow-sm leading-none" style="background:rgba(11,14,22,0.85); backdrop-filter:blur(4px);">
                                <div class="text-[10px] font-bold uppercase" style="color:#8fa8ff;">{{ $ics->start_date->format('M') }}</div>
                                <div class="text-base font-extrabold text-white">{{ $ics->start_date->format('j') }}</div>
                            </div>
                        @endif
                        @if($eventIsOnline)
                            <div class="absolute top-3 {{ $ics && $ics->start_date ? 'left-20' : 'left-3' }} z-10 inline-flex items-center gap-1 text-white rounded-full px-2.5 py-1 text-[10px] font-bold" style="background:rgba(16,185,129,0.85);">
                                <i class="fas fa-video"></i> Online
                            </div>
                        @endif
                        @if($photoCount > 1)
                            <div class="absolute top-3 right-3 z-10 inline-flex items-center gap-1 bg-black/55 text-white rounded-full px-2 py-1 text-[11px] font-semibold backdrop-blur-sm shadow-sm">
                                <i class="fas fa-images"></i> {{ $photoCount }} photos
                            </div>
                            <div class="event-media-dots absolute bottom-3 left-3 z-10 flex items-center gap-1">
                                @foreach($previewImages as $pi => $imgUrl)
                                    <span class="event-media-dot h-1.5 w-1.5 rounded-full {{ $pi === 0 ? 'bg-white' : 'bg-white/60' }}" data-dot="{{ $pi }}"></span>
                                @endforeach
                            </div>
                        @endif
                        <div class="absolute bottom-3 right-3 z-10">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold shadow-sm {{ $priceIsFree ? 'bg-emerald-500 text-white' : 'text-white' }}" style="{{ $priceIsFree ? '' : 'background:rgba(61,107,255,0.9);' }}">
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
                                <span class="text-white/20">&bull;</span>
                                <span><i class="fas {{ $catIcon }} mr-1"></i>{{ \App\Modules\User\Support\EventCategories::label($eventCategory) }}</span>
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
                                    <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->except(['tag', 'page']), ['tag' => $ht])) }}"
                                       class="text-[11px] px-2 py-0.5 rounded-full text-white/50 hover:text-white" style="background:rgba(255,255,255,0.06);">#{{ $ht }}</a>
                                @endforeach
                            </div>
                        @endif

                        @if($tiers->count() > 1)
                            <div class="tier-breakdown mt-3">
                                <button type="button" class="tier-toggle inline-flex items-center gap-1.5 text-xs font-semibold hover:opacity-80" style="color:#8fa8ff;" aria-expanded="false">
                                    <i class="fas fa-tags"></i> {{ $tiers->count() }} ticket tiers
                                    <i class="fas fa-chevron-down text-[10px] transition-transform tier-chevron"></i>
                                </button>
                                <div class="tier-list hidden mt-2 rounded-lg divide-y divide-white/8" style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08);">
                                    @foreach($tiers as $tier)
                                        <div class="flex items-center justify-between gap-2 px-2.5 py-1.5 text-xs">
                                            <span class="text-white/50 truncate">{{ $tier->name }}</span>
                                            <span class="font-bold whitespace-nowrap {{ $tier->isFree() ? 'text-emerald-400' : 'text-white' }}">{{ $tier->priceLabel() }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="flex items-center justify-between mt-4 pt-3 border-t border-white/8">
                            <div class="text-xs text-white/40 event-interest-counts" data-role="counts">
                                <i class="fas fa-star text-amber-400 mr-1"></i><span data-role="interested-count">{{ $event->interested_count ?? 0 }}</span> interested
                            </div>
                            <a href="{{ url('/' . $event->alias) }}" class="text-xs font-bold hover:opacity-80" style="color:#8fa8ff;">View event &rarr;</a>
                        </div>

                        <div class="flex items-center gap-2 mt-3 event-interest-widget" data-alias="{{ $event->alias }}">
                            <button type="button" class="btn-interest flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold event-interest-btn" style="border:1px solid rgba(16,185,129,0.3); color:#34d399;" data-status="interested">
                                <i class="fas fa-star"></i> Interested
                            </button>
                            <button type="button" class="btn-interest flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold event-interest-btn" style="border:1px solid rgba(255,255,255,0.12); color:rgba(255,255,255,0.5);" data-status="not_interested">
                                <i class="fas fa-xmark"></i> Not interested
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-8">{{ $events->links() }}</div>
    @endif

    <div class="text-center mt-10">
        <a href="{{ auth('web')->check() ? route('user.links.create') : (route('user.login') . '?redirect=' . urlencode(route('user.links.create'))) }}"
           class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-sm font-bold text-white hover:opacity-90" style="background:#3d6bff;">
            <i class="fas fa-plus"></i> Create your own event
        </a>
    </div>
</div>

<script>
function heroSlider(count) {
    return {
        idx: 0,
        count: count,
        timer: null,
        start() {
            if (this.count <= 1) return;
            this.timer = setInterval(() => this.go((this.idx + 1) % this.count), 5000);
        },
        go(i) {
            this.idx = i;
            document.querySelectorAll('.hero-slide').forEach((el, k) => el.classList.toggle('active', k === i));
            document.querySelectorAll('.hero-dot').forEach((el, k) => el.classList.toggle('active', k === i));
            if (this.timer) { clearInterval(this.timer); this.start(); }
        },
    };
}

function eventSearchMap(initLat, initLng) {
    return {
        showMap: false,
        address: '',
        map: null,
        marker: null,
        lat: initLat,
        lng: initLng,
        toggleMap() {
            this.showMap = !this.showMap;
            if (this.showMap) {
                const self = this;
                ensureLeafletForSearch(function () {
                    self.$nextTick(function () { self.initMap(); });
                });
            }
        },
        initMap() {
            if (typeof L === 'undefined' || !this.$refs.map || this.map) return;
            const hasPoint = this.lat && this.lng;
            const center = hasPoint ? [parseFloat(this.lat), parseFloat(this.lng)] : [20, 0];
            const zoom = hasPoint ? 12 : 2;
            this.map = L.map(this.$refs.map, { center: center, zoom: zoom, scrollWheelZoom: false });
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors',
            }).addTo(this.map);
            const icon = L.divIcon({
                className: '',
                html: '<div style="width:26px;height:34px;"><svg viewBox="0 0 34 44" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="sm-g" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#90acff"/><stop offset="100%" stop-color="#3d6bff"/></linearGradient></defs><path d="M17 0C7.6 0 0 7.5 0 16.7c0 11.7 14.6 25.5 16 26.8.6.6 1.5.6 2 0 1.5-1.3 16-15.1 16-26.8C34 7.5 26.4 0 17 0z" fill="url(#sm-g)" stroke="rgba(255,255,255,0.85)" stroke-width="1.5"/></svg></div>',
                iconSize: [26, 34],
                iconAnchor: [13, 34],
            });
            this.marker = L.marker(center, { icon: icon, draggable: true }).addTo(this.map);
            if (!hasPoint) this.marker.setOpacity(0);
            const self = this;
            this.marker.on('dragend', function () {
                const p = self.marker.getLatLng();
                self.setPoint(p.lat, p.lng);
            });
            this.map.on('click', function (e) {
                self.marker.setLatLng(e.latlng);
                self.marker.setOpacity(1);
                self.setPoint(e.latlng.lat, e.latlng.lng);
            });
            setTimeout(function () { self.map.invalidateSize(); }, 80);
        },
        setPoint(lat, lng) {
            this.lat = String(Math.round(lat * 1e6) / 1e6);
            this.lng = String(Math.round(lng * 1e6) / 1e6);
            const self = this;
            fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + lat + '&lon=' + lng, { headers: { Accept: 'application/json' } })
                .then(r => r.ok ? r.json() : null)
                .then(d => { if (d && d.display_name) self.address = d.display_name; })
                .catch(() => {});
        },
        applyToForm() {
            if (!this.lat || !this.lng) return;
            this.$refs.latInput.value = this.lat;
            this.$refs.lngInput.value = this.lng;
            this.$refs.latInput.closest('form').submit();
        },
    };
}

function ensureLeafletForSearch(cb) {
    if (window.L) { cb(); return; }
    if (!document.getElementById('mpp-leaflet-css')) {
        const link = document.createElement('link');
        link.id = 'mpp-leaflet-css';
        link.rel = 'stylesheet';
        link.href = '/css/vendor/leaflet.min.css';
        document.head.appendChild(link);
    }
    const existing = document.getElementById('mpp-leaflet-js');
    if (existing) { existing.addEventListener('load', cb); return; }
    const s = document.createElement('script');
    s.id = 'mpp-leaflet-js';
    s.src = '/js/vendor/leaflet.min.js';
    s.onload = cb;
    document.head.appendChild(s);
}

document.getElementById('near-me-btn')?.addEventListener('click', function () {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(function (pos) {
        const form = document.querySelector('form');
        form.lat.value = pos.coords.latitude;
        form.lng.value = pos.coords.longitude;
        form.submit();
    });
});

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
document.querySelectorAll('.event-interest-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const widget = btn.closest('.event-interest-widget');
        const alias = widget.getAttribute('data-alias');
        const status = btn.getAttribute('data-status');
        const siblingButtons = widget.querySelectorAll('.event-interest-btn');
        siblingButtons.forEach(function (b) { b.disabled = true; });

        fetch('/' + alias + '/interest', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ status: status }),
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !data.success) return;
                siblingButtons.forEach(function (b) {
                    b.classList.toggle('active', b.getAttribute('data-status') === data.status);
                });
                const counts = widget.closest('.event-card').querySelector('[data-role="interested-count"]');
                if (counts && data.counts) counts.textContent = data.counts.interested;
            })
            .catch(function () { /* silent — one-tap signal is best-effort */ })
            .finally(function () {
                siblingButtons.forEach(function (b) { b.disabled = false; });
            });
    });
});

// Ticket-tier breakdown: reveal the full name+price list inline without
// leaving the directory. Default collapsed state keeps the "From $X" summary.
document.querySelectorAll('.tier-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const wrap = btn.closest('.tier-breakdown');
        const list = wrap.querySelector('.tier-list');
        const open = list.classList.toggle('hidden') === false;
        wrap.classList.toggle('tier-open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
});

// Lightweight hover gallery preview for cards with multiple photos.
document.querySelectorAll('.event-media').forEach(function (media) {
    const imgs = media.querySelectorAll('.event-media-img');
    const dots = media.querySelectorAll('.event-media-dot');
    if (imgs.length < 2) return;
    let idx = 0;
    let timer = null;

    function show(next) {
        imgs[idx].classList.replace('opacity-100', 'opacity-0');
        if (dots[idx]) dots[idx].classList.replace('bg-white', 'bg-white/60');
        idx = next;
        imgs[idx].classList.replace('opacity-0', 'opacity-100');
        if (dots[idx]) dots[idx].classList.replace('bg-white/60', 'bg-white');
    }

    media.addEventListener('mouseenter', function () {
        if (timer) return;
        timer = setInterval(function () { show((idx + 1) % imgs.length); }, 1100);
    });
    media.addEventListener('mouseleave', function () {
        clearInterval(timer);
        timer = null;
        if (idx !== 0) show(0);
    });
});
</script>
</body>
</html>
