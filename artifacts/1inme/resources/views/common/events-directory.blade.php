<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Discover Events — {{ config('app.name') }}</title>
    <meta name="description" content="Find and RSVP to events near you — powered by {{ config('app.name') }}.">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/vendor/fontawesome-free-6.5.1/css/all.min.css') }}">
    <style>
        .events-hero {
            background:
                radial-gradient(1200px 400px at 15% -10%, rgba(255,255,255,0.18), transparent 60%),
                linear-gradient(135deg, #2f4fe0 0%, #3d6bff 55%, #6e61ff 100%);
        }
        .cover-fallback {
            background: linear-gradient(135deg, #3d6bff 0%, #6e61ff 55%, #a855f7 130%);
        }
        .event-card { transition: transform .18s ease, box-shadow .18s ease; }
        .event-card:hover { transform: translateY(-4px); box-shadow: 0 20px 40px -12px rgba(61,107,255,0.28); }
        .cat-pill.active { background:#3d6bff; color:#fff; border-color:#3d6bff; }
        .tag-chip.active { background:#3d6bff; color:#fff; border-color:#3d6bff; }
        .btn-interest.active { background:#3d6bff; color:#fff; border-color:#3d6bff; }
        .line-clamp-2 { display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
        .tier-breakdown.tier-open .tier-chevron { transform: rotate(180deg); }
    </style>
</head>
<body class="bg-gradient-to-b from-slate-50 via-white to-slate-50 min-h-screen">

<div class="events-hero text-center text-white px-4 py-14 sm:py-16">
    <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight">Discover Events</h1>
    <p class="mt-2 text-white/80 text-sm sm:text-base max-w-xl mx-auto">Find happenings near you, from meetups to festivals — powered by {{ config('app.name') }}.</p>

    <form method="GET" class="mt-7 max-w-3xl mx-auto">
        <div class="flex flex-col sm:flex-row gap-2 bg-white rounded-2xl p-2 shadow-xl">
            <div class="flex-1 flex items-center gap-2 px-3">
                <i class="fas fa-magnifying-glass text-slate-400"></i>
                <input type="text" name="q" value="{{ $q }}" placeholder="Search events, locations, or #hashtags"
                       class="w-full py-2.5 text-slate-800 text-sm focus:outline-none placeholder:text-slate-400">
            </div>
            <button type="button" id="near-me-btn"
                    class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 transition-colors">
                <i class="fas fa-location-arrow"></i> Near me
            </button>
            <button type="submit"
                    class="px-6 py-2.5 rounded-xl text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 transition-colors">
                Search
            </button>
        </div>
        <input type="hidden" name="lat" value="{{ $lat }}">
        <input type="hidden" name="lng" value="{{ $lng }}">
        <input type="hidden" name="category" value="{{ $category }}">
        <input type="hidden" name="tag" value="{{ $tag }}">
    </form>
</div>

<div class="max-w-6xl mx-auto px-4 py-8">

    {{-- Category browse row (Eventbrite-style). --}}
    @if($categories->isNotEmpty() || $hasOtherCategory)
        <div class="flex gap-2 overflow-x-auto pb-2 -mx-1 px-1 mb-5 snap-x">
            <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->except(['category', 'page']), ['category' => ''])) }}"
               class="cat-pill snap-start flex-shrink-0 inline-flex items-center gap-2 px-4 py-2 rounded-full border border-slate-200 bg-white text-sm font-semibold text-slate-700 hover:border-blue-300 {{ $category === '' ? 'active' : '' }}">
                <i class="fas fa-layer-group"></i> All events
            </a>
            @foreach($categories as $c)
                <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->except(['category', 'page']), ['category' => $category === $c ? '' : $c])) }}"
                   class="cat-pill snap-start flex-shrink-0 inline-flex items-center gap-2 px-4 py-2 rounded-full border border-slate-200 bg-white text-sm font-semibold text-slate-700 hover:border-blue-300 {{ $category === $c ? 'active' : '' }}">
                    <i class="fas {{ $categoryIcons[$c] ?? 'fa-calendar-star' }}"></i> {{ $categoryLabels[$c] ?? ucfirst($c) }}
                </a>
            @endforeach
            @if($hasOtherCategory)
                <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->except(['category', 'page']), ['category' => $category === $otherCategory ? '' : $otherCategory])) }}"
                   class="cat-pill snap-start flex-shrink-0 inline-flex items-center gap-2 px-4 py-2 rounded-full border border-slate-200 bg-white text-sm font-semibold text-slate-700 hover:border-blue-300 {{ $category === $otherCategory ? 'active' : '' }}">
                    <i class="fas fa-ellipsis"></i> Other
                </a>
            @endif
        </div>
    @endif

    {{-- Popular hashtag filter chips. --}}
    @if($popularTags->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <span class="text-xs font-semibold text-slate-500 mr-1">Popular:</span>
            @foreach($popularTags as $popularTag)
                <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->except('tag', 'page'), ['tag' => $tag === $popularTag ? '' : $popularTag])) }}"
                   class="tag-chip {{ $tag === $popularTag ? 'active' : '' }} inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border border-slate-200 bg-white text-slate-600 hover:border-blue-300">
                    #{{ $popularTag }}
                </a>
            @endforeach
        </div>
    @endif

    {{-- Active filters summary. --}}
    @if($nearMe || $tag || $category || $q)
        <div class="flex flex-wrap items-center gap-2 mb-6 text-xs text-slate-600">
            <span class="font-semibold text-slate-500">Active filters:</span>
            @if($q)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-100">
                    "{{ $q }}"
                    <a href="{{ url()->current() }}?{{ http_build_query(request()->except(['q', 'page'])) }}" class="text-slate-400 hover:text-slate-700">&times;</a>
                </span>
            @endif
            @if($category)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-100">
                    @if($category === $otherCategory)
                        <i class="fas fa-ellipsis"></i> Other
                    @else
                        <i class="fas {{ $categoryIcons[$category] ?? 'fa-calendar-star' }}"></i> {{ $categoryLabels[$category] ?? \App\Modules\User\Support\EventCategories::label($category) }}
                    @endif
                    <a href="{{ url()->current() }}?{{ http_build_query(request()->except(['category', 'page'])) }}" class="text-slate-400 hover:text-slate-700">&times;</a>
                </span>
            @endif
            @if($tag)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-100">
                    #{{ $tag }}
                    <a href="{{ url()->current() }}?{{ http_build_query(request()->except(['tag', 'page'])) }}" class="text-slate-400 hover:text-slate-700">&times;</a>
                </span>
            @endif
            @if($nearMe)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-100">
                    <i class="fas fa-location-arrow"></i> Within {{ $radiusKm }}km
                    <a href="{{ url()->current() }}?{{ http_build_query(request()->except(['lat', 'lng', 'page'])) }}" class="text-slate-400 hover:text-slate-700">&times;</a>
                </span>
            @endif
            <a href="{{ route('events.index') }}" class="text-blue-600 hover:underline font-semibold">Clear all</a>
        </div>
    @endif

    @if($events->count() === 0)
        <div class="text-center py-20 bg-white rounded-3xl border border-slate-200">
            <i class="fas fa-calendar-xmark text-4xl text-slate-300 mb-4"></i>
            <p class="text-slate-700 font-semibold">No events found</p>
            <p class="text-slate-500 text-sm mt-1">Try a different search, category, or clear your filters.</p>
            @if($nearMe || $tag || $category || $q)
                <a href="{{ route('events.index') }}" class="inline-block mt-4 px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">Clear filters</a>
            @endif
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($events as $event)
                @php
                    $ics = $event->icsData;
                    $cover = $ics->cover_image_url ?? null;
                    $eventCategory = $event->settings['event_category'] ?? '';
                    $catIcon = $eventCategory !== ''
                        ? \App\Modules\User\Support\EventCategories::icon($eventCategory)
                        : 'fa-calendar-star';

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
                <div class="event-card bg-white rounded-2xl border border-slate-200 overflow-hidden">
                    <a href="{{ url('/' . $event->alias) }}"
                       class="block relative aspect-[16/10] overflow-hidden {{ $photoCount > 1 ? 'event-media' : '' }}">
                        @if($photoCount > 0)
                            @foreach($previewImages as $pi => $imgUrl)
                                <img src="{{ $imgUrl }}" alt="{{ $event->title }}" loading="lazy" data-idx="{{ $pi }}"
                                     class="event-media-img absolute inset-0 w-full h-full object-cover transition-opacity duration-500 {{ $pi === 0 ? 'opacity-100' : 'opacity-0' }}">
                            @endforeach
                        @else
                            <div class="cover-fallback w-full h-full flex items-center justify-center">
                                <i class="fas {{ $catIcon }} text-white/70 text-4xl"></i>
                            </div>
                        @endif
                        @if($ics && $ics->start_date)
                            <div class="absolute top-3 left-3 z-10 bg-white/95 backdrop-blur rounded-xl px-2.5 py-1.5 text-center shadow-sm leading-none">
                                <div class="text-[10px] font-bold uppercase text-blue-600">{{ $ics->start_date->format('M') }}</div>
                                <div class="text-base font-extrabold text-slate-900">{{ $ics->start_date->format('j') }}</div>
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
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold shadow-sm {{ $priceIsFree ? 'bg-emerald-500 text-white' : 'bg-white/95 text-slate-900' }}">
                                {{ $priceLabel }}
                            </span>
                        </div>
                    </a>

                    <div class="p-4">
                        <div class="flex items-center gap-2 text-xs text-slate-500 mb-1.5">
                            @if($ics && $ics->start_date)
                                <span><i class="far fa-clock mr-1"></i>{{ $ics->start_date->format('D, M j · g:i A') }}</span>
                            @endif
                            @if(!empty($event->settings['event_category'] ?? null))
                                <span class="text-slate-300">&bull;</span>
                                <span><i class="fas {{ $catIcon }} mr-1"></i>{{ \App\Modules\User\Support\EventCategories::label($event->settings['event_category']) }}</span>
                            @endif
                        </div>

                        <a href="{{ url('/' . $event->alias) }}" class="block">
                            <h2 class="font-bold text-slate-900 leading-snug line-clamp-2 hover:text-blue-700">{{ $event->title }}</h2>
                        </a>

                        @if($ics && $ics->location)
                            <div class="text-xs text-slate-500 mt-1.5"><i class="fas fa-location-dot mr-1"></i>{{ $ics->location }}</div>
                        @endif

                        @if($ics && $ics->hashtagList())
                            <div class="flex flex-wrap gap-1 mt-2">
                                @foreach(array_slice($ics->hashtagList(), 0, 4) as $ht)
                                    <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->except(['tag', 'page']), ['tag' => $ht])) }}"
                                       class="text-[11px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 hover:bg-blue-50 hover:text-blue-700">#{{ $ht }}</a>
                                @endforeach
                            </div>
                        @endif

                        @if($tiers->count() > 1)
                            <div class="tier-breakdown mt-3">
                                <button type="button" class="tier-toggle inline-flex items-center gap-1.5 text-xs font-semibold text-blue-600 hover:text-blue-700" aria-expanded="false">
                                    <i class="fas fa-tags"></i> {{ $tiers->count() }} ticket tiers
                                    <i class="fas fa-chevron-down text-[10px] transition-transform tier-chevron"></i>
                                </button>
                                <div class="tier-list hidden mt-2 rounded-lg border border-slate-100 bg-slate-50/70 divide-y divide-slate-100">
                                    @foreach($tiers as $tier)
                                        <div class="flex items-center justify-between gap-2 px-2.5 py-1.5 text-xs">
                                            <span class="text-slate-600 truncate">{{ $tier->name }}</span>
                                            <span class="font-bold whitespace-nowrap {{ $tier->isFree() ? 'text-emerald-600' : 'text-slate-900' }}">{{ $tier->priceLabel() }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="flex items-center justify-between mt-4 pt-3 border-t border-slate-100">
                            <div class="text-xs text-slate-500 event-interest-counts" data-role="counts">
                                <i class="fas fa-star text-amber-400 mr-1"></i><span data-role="interested-count">{{ $event->interested_count ?? 0 }}</span> interested
                            </div>
                            <a href="{{ url('/' . $event->alias) }}" class="text-xs font-bold text-blue-600 hover:text-blue-700">View event &rarr;</a>
                        </div>

                        <div class="flex items-center gap-2 mt-3 event-interest-widget" data-alias="{{ $event->alias }}">
                            <button type="button" class="btn-interest flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold border border-emerald-200 text-emerald-700 hover:bg-emerald-50 event-interest-btn" data-status="interested">
                                <i class="fas fa-star"></i> Interested
                            </button>
                            <button type="button" class="btn-interest flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold border border-slate-200 text-slate-600 hover:bg-slate-50 event-interest-btn" data-status="not_interested">
                                <i class="fas fa-xmark"></i> Not interested
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-8">{{ $events->links() }}</div>
    @endif
</div>

<script>
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
