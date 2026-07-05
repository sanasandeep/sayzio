@extends('public.layouts.site')
@section('title', 'Discover Events')

@php
    $shareTitle = 'Discover Events';
    $shareDescription = 'Find and RSVP to events near you — powered by ' . config('app.name') . '.';
    $hasCustomRange = $dateFrom !== '' || $dateTo !== '';
@endphp

@push('head')
<style>
    /* ── Hero: darkened photographic background ─────────────────────
       Layered so the heading/search bar stay legible over the image in
       BOTH themes — this panel is intentionally always-dark (like a
       poster), independent of the site's light/dark toggle. */
    .events-hero {
        position: relative;
        background-color: #0b0e16;
        background-image:
            linear-gradient(180deg, rgba(6,8,18,0.58) 0%, rgba(6,8,18,0.80) 60%, rgba(6,8,18,0.95) 100%),
            radial-gradient(1200px 400px at 15% -10%, rgba(140,165,255,0.18), transparent 60%),
            url('{{ asset('images/events/events-hero-bg.webp') }}');
        background-size: cover, cover, cover;
        background-position: center, center, center 30%;
        background-repeat: no-repeat, no-repeat, no-repeat;
        border-bottom: 1px solid rgba(255,255,255,0.06);
    }
    /* The global light-mode remap (marketing-anim.css) force-darkens
       .text-white / text-white/NN everywhere it isn't explicitly
       carved out — without this, hero text turns near-invisible dark
       text over the dark photo in light mode. Re-assert white here with
       higher selector specificity so it always wins. */
    html.light-mode .events-hero,
    html.light-mode .events-hero .text-white { color:#fff !important; }
    html.light-mode .events-hero .text-white\/60 { color:rgba(255,255,255,0.6) !important; }
    html.light-mode .events-hero .text-white\/40 { color:rgba(255,255,255,0.4) !important; }
    html.light-mode .events-hero input::placeholder { color:rgba(255,255,255,0.35) !important; }

    .ev-card { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:1rem; }
    .ev-chip { background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.10); color:rgba(255,255,255,0.7); }
    .ev-chip.active, .ev-chip:hover { background:#3d6bff; border-color:#3d6bff; color:#fff; }
    .event-card { transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
    .event-card:hover { transform: translateY(-4px); box-shadow: 0 20px 40px -14px rgba(61,107,255,0.35); border-color: rgba(61,107,255,0.4); }
    .line-clamp-2 { display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    .tier-breakdown.tier-open .tier-chevron { transform: rotate(180deg); }
    .cat-tile-icon {
        position:relative; width:64px; height:64px; flex:0 0 auto;
        border:1.5px solid rgba(255,255,255,0.16); border-radius:0.85rem; background:transparent;
        display:flex; align-items:center; justify-content:center; color:rgba(255,255,255,0.65); font-size:1.2rem;
        transition:border-color .15s ease, color .15s ease, transform .15s ease, background .15s ease;
    }
    .cat-tile-icon:hover { border-color:#3d6bff; color:#fff; transform:translateY(-2px); }
    .cat-tile-icon.active { border-color:#3d6bff; background:rgba(61,107,255,0.16); color:#fff; }
    .cat-tile-icon::after {
        content: attr(data-label);
        position:absolute; bottom:calc(100% + 9px); left:50%; transform:translateX(-50%) translateY(4px);
        background:#0b0e16; color:#fff; font-size:11px; font-weight:600; white-space:nowrap; line-height:1;
        padding:5px 9px; border-radius:6px; border:1px solid rgba(255,255,255,0.12); box-shadow:0 8px 20px -8px rgba(0,0,0,0.6);
        opacity:0; pointer-events:none; transition:opacity .15s ease, transform .15s ease; z-index:20;
    }
    .cat-tile-icon:hover::after, .cat-tile-icon:focus-visible::after { opacity:1; transform:translateX(-50%) translateY(0); }
    .cat-divider { width:1px; align-self:stretch; margin:2px 4px; background:rgba(255,255,255,0.14); }
    .cat-more-btn {
        flex:0 0 auto; width:64px; height:64px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:2px;
        border-radius:0.85rem; border:1.5px dashed rgba(255,255,255,0.22); color:rgba(255,255,255,0.6); font-size:10px; font-weight:700;
        background:transparent; transition:border-color .15s ease, color .15s ease;
    }
    .cat-more-btn:hover { border-color:#3d6bff; color:#fff; }
    @media (prefers-reduced-motion: reduce) {
        .cat-tile-icon, .cat-tile-icon::after, .cat-more-btn { transition:none; }
    }
    #search-map { height:200px; border-radius:0.9rem; }
    [x-cloak] { display:none !important; }

    /* ── Featured slider (redesigned) ──────────────────────────────
       Full-bleed cover art with a scrim + overlaid title/meta, a soft
       fade-in on slide change, and a subtle hover zoom for polish. */
    .hero-slide { display:none; }
    .hero-slide.active { display:block; animation: heroSlideIn .5s ease; }
    @keyframes heroSlideIn { from { opacity:0; transform:scale(1.015); } to { opacity:1; transform:scale(1); } }
    .hero-slide-media { position:relative; aspect-ratio:21/9; overflow:hidden; border-radius:1.25rem; box-shadow:0 24px 48px -18px rgba(0,0,0,0.55); }
    .hero-slide-img { width:100%; height:100%; object-fit:cover; transition:transform .7s ease; }
    .hero-slide:hover .hero-slide-img { transform:scale(1.06); }
    .hero-slide-scrim { position:absolute; inset:0; background:linear-gradient(180deg, rgba(8,10,20,0) 32%, rgba(6,8,16,0.6) 68%, rgba(4,5,12,0.94) 100%); }
    .hero-slide-content { position:absolute; left:0; right:0; bottom:0; padding:1.1rem 1.4rem 1.3rem; }
    .hero-slide-badge { display:inline-flex; align-items:center; font-size:.65rem; font-weight:800; text-transform:uppercase; letter-spacing:.04em; color:#fff; padding:.3rem .7rem; border-radius:999px; background:linear-gradient(135deg,#3d6bff,#6e61ff); box-shadow:0 6px 16px -2px rgba(61,107,255,0.55); margin-bottom:.55rem; }
    .hero-slide-date { font-size:.75rem; color:rgba(255,255,255,0.7); margin-bottom:.25rem; }
    .hero-slide-title { font-size:1.3rem; font-weight:800; color:#fff; line-height:1.28; margin-bottom:.3rem; text-shadow:0 2px 14px rgba(0,0,0,0.5); }
    @media (min-width: 640px) { .hero-slide-title { font-size:1.5rem; } }
    .hero-slide-location { font-size:.75rem; color:rgba(255,255,255,0.6); }
    .hero-dot { width:7px; height:7px; border-radius:999px; background:rgba(255,255,255,0.3); transition:all .25s ease; }
    .hero-dot.active { background:linear-gradient(90deg,#3d6bff,#6e61ff); width:22px; }

    /* ── Light-mode fixes for the rest of the page ──────────────────
       Custom classes below (.ev-card, .ev-chip, chip pills, hashtag
       pills, tier list) aren't Tailwind utilities, so the sitewide
       light-mode remap can't reach them — scope fixes to everything
       BELOW the hero (the hero itself stays a dark photo panel). */
    .chip-pill { background:rgba(255,255,255,0.06); }
    .chip-close { color:rgba(255,255,255,0.3); }
    .chip-close:hover { color:#fff; }
    .link-accent, .tier-toggle-link { color:#8fa8ff; }
    .hashtag-pill { background:rgba(255,255,255,0.06); color:rgba(255,255,255,0.5); }
    .hashtag-pill:hover { color:#fff; }
    .tier-list-box { background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-radius:0.5rem; }
    .tier-list-box > div + div { border-top:1px solid rgba(255,255,255,0.08); }
    .ev-card-footer-divider { border-top:1px solid rgba(255,255,255,0.08); }
    .btn-not-interested { border:1px solid rgba(255,255,255,0.12); color:rgba(255,255,255,0.5); }

    html.light-mode .events-page-body .ev-card { background:#ffffff; border-color:rgba(15,23,42,0.08); box-shadow:0 1px 2px rgba(15,23,42,0.04); }
    html.light-mode .events-page-body .ev-chip { background:rgba(15,23,42,0.05); border-color:rgba(15,23,42,0.12); color:rgba(15,23,42,0.65); }
    html.light-mode .events-page-body .ev-chip.active,
    html.light-mode .events-page-body .ev-chip:hover { background:#3d6bff; border-color:#3d6bff; color:#fff; }
    html.light-mode .events-page-body .chip-pill { background:rgba(15,23,42,0.05); border:1px solid rgba(15,23,42,0.10); }
    html.light-mode .events-page-body .chip-close { color:rgba(15,23,42,0.35); }
    html.light-mode .events-page-body .chip-close:hover { color:#0f172a; }
    html.light-mode .events-page-body .link-accent,
    html.light-mode .events-page-body .tier-toggle-link { color:#2342c7; }
    html.light-mode .events-page-body .hashtag-pill { background:rgba(15,23,42,0.05); color:rgba(15,23,42,0.55); }
    html.light-mode .events-page-body .hashtag-pill:hover { color:#0f172a; }
    html.light-mode .events-page-body .tier-list-box { background:rgba(15,23,42,0.03); border-color:rgba(15,23,42,0.08); }
    html.light-mode .events-page-body .tier-list-box > div + div { border-color:rgba(15,23,42,0.08); }
    html.light-mode .events-page-body .ev-card-footer-divider { border-color:rgba(15,23,42,0.08); }
    html.light-mode .events-page-body .btn-not-interested { border-color:rgba(15,23,42,0.14); color:rgba(15,23,42,0.5); }

    /* Category row (icon tiles) is near-invisible white-on-white in light
       mode — the tiles use bespoke classes, not Tailwind utilities, so the
       sitewide remap can't reach them. */
    html.light-mode .events-page-body .cat-section-label { color:rgba(15,23,42,0.45); }
    html.light-mode .events-page-body .cat-tile-icon { border-color:rgba(15,23,42,0.14); color:rgba(15,23,42,0.55); background:rgba(15,23,42,0.02); }
    html.light-mode .events-page-body .cat-tile-icon:hover { border-color:#3d6bff; color:#3d6bff; background:rgba(61,107,255,0.06); }
    html.light-mode .events-page-body .cat-tile-icon.active { border-color:#3d6bff; background:rgba(61,107,255,0.10); color:#2342c7; }
    html.light-mode .events-page-body .cat-tile-icon::after { background:#0f172a; color:#fff; border-color:rgba(15,23,42,0.12); }
    html.light-mode .events-page-body .cat-divider { background:rgba(15,23,42,0.14); }
    html.light-mode .events-page-body .cat-more-btn { border-color:rgba(15,23,42,0.22); color:rgba(15,23,42,0.5); }
    html.light-mode .events-page-body .cat-more-btn:hover { border-color:#3d6bff; color:#2342c7; }

    /* Currency toggle. */
    .currency-toggle-btn { padding:.35rem .75rem; border-radius:999px; font-size:.75rem; font-weight:700; color:rgba(255,255,255,0.55); background:transparent; transition:background .15s ease, color .15s ease; }
    .currency-toggle-btn.active { background:#3d6bff; color:#fff; }
    html.light-mode .events-page-body .currency-toggle-btn { color:rgba(15,23,42,0.5); }
    html.light-mode .events-page-body .currency-toggle-btn.active { background:#3d6bff; color:#fff; }

    /* Custom date-range panel. */
    .ev-date-range-box { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); }
    .ev-date-range-label { color:rgba(255,255,255,0.4); }
    .ev-date-range-input { background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.14); color:#fff; }
    html.light-mode .events-page-body .ev-date-range-box { background:#ffffff; border-color:rgba(15,23,42,0.10); box-shadow:0 1px 2px rgba(15,23,42,0.04); }
    html.light-mode .events-page-body .ev-date-range-label { color:rgba(15,23,42,0.5); }
    html.light-mode .events-page-body .ev-date-range-input { background:#ffffff; border-color:rgba(15,23,42,0.18); color:#0f172a; }

    /* ── Grid cards (redesigned, single image) ─────────────────────── */
    .ev-card-img { width:100%; height:100%; object-fit:cover; transition:transform .5s ease; }
    .event-card:hover .ev-card-img { transform:scale(1.06); }
    .ev-card-date-chip { background:rgba(11,14,22,0.82); backdrop-filter:blur(4px); }
    .ev-cat-pill { display:inline-flex; align-items:center; gap:.3rem; font-size:.65rem; font-weight:700; padding:.2rem .55rem; border-radius:999px; color:#fff; }
    .ev-price-badge { box-shadow:0 6px 16px -4px rgba(0,0,0,0.35); }
</style>
@endpush

@section('content')
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

            <div x-show="showMap" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" class="mt-3 p-3 rounded-2xl text-left" style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.10);">
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
            @if($priceFilter)<input type="hidden" name="price" value="{{ $priceFilter }}">@endif
            @if($hasCustomRange)
                <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                <input type="hidden" name="date_to" value="{{ $dateTo }}">
            @elseif($dateFilter)
                <input type="hidden" name="date" value="{{ $dateFilter }}">
            @endif
        </form>

        {{-- Hero slider: 2-3 featured upcoming events. --}}
        @if(isset($heroEvents) && $heroEvents->isNotEmpty())
            <div class="mt-9 max-w-3xl mx-auto text-left" x-data="heroSlider({{ $heroEvents->count() }})" x-init="start()">
                @foreach($heroEvents as $hi => $hero)
                    @php
                        $hIcs = $hero->icsData;
                        $hCat = $hero->settings['event_category'] ?? '';
                        $hTiers = $hero->eventTicketTiers->sortBy('price_cents')->values();
                        if ($hTiers->isEmpty()) {
                            $hPriceLabel = 'Free RSVP';
                            $hPriceIsFree = true;
                            $hNativeCurrency = null;
                            $hNativeCents = 0;
                            $hPrefix = '';
                        } else {
                            $hLowest = $hTiers->first();
                            $hHasRange = $hTiers->count() > 1 && (int) $hTiers->last()->price_cents !== (int) $hLowest->price_cents;
                            $hPrefix = $hHasRange ? 'From ' : '';
                            $hPriceLabel = $hPrefix . $hLowest->priceLabel();
                            $hPriceIsFree = $hLowest->isFree() && !$hHasRange;
                            $hNativeCurrency = strtoupper($hLowest->currency);
                            $hNativeCents = (int) $hLowest->price_cents;
                        }
                    @endphp
                    <a href="{{ url('/' . $hero->alias) }}" class="hero-slide {{ $hi === 0 ? 'active' : '' }}" data-slide="{{ $hi }}">
                        <div class="hero-slide-media">
                            @if($hIcs && $hIcs->cover_image_url)
                                <img src="{{ $hIcs->cover_image_url }}" alt="{{ $hero->title }}" class="hero-slide-img">
                            @else
                                <img src="{{ asset('images/events/event-cover-placeholder.svg') }}" alt="{{ $hero->title }}" class="hero-slide-img">
                            @endif
                            <div class="hero-slide-scrim"></div>
                            <span class="hero-slide-price ev-price-badge inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold {{ $hPriceIsFree ? 'bg-emerald-500 text-white' : 'text-white ev-price' }}"
                                  style="position:absolute; top:1rem; right:1rem; {{ $hPriceIsFree ? '' : 'background:rgba(61,107,255,0.92);' }}"
                                  @if(!$hPriceIsFree) data-native-currency="{{ $hNativeCurrency }}" data-native-cents="{{ $hNativeCents }}" data-prefix="{{ $hPrefix }}" @endif>
                                {{ $hPriceLabel }}
                            </span>
                            <div class="hero-slide-content">
                                <span class="hero-slide-badge"><i class="fas fa-star mr-1"></i> Featured</span>
                                @if($hIcs && $hIcs->start_date)
                                    <div class="hero-slide-date"><i class="far fa-clock mr-1"></i>{{ $hIcs->start_date->format('D, M j · g:i A') }}</div>
                                @endif
                                <h3 class="hero-slide-title line-clamp-2">{{ $hero->title }}</h3>
                                @if($hIcs && $hIcs->location)
                                    <div class="hero-slide-location"><i class="fas fa-location-dot mr-1"></i>{{ $hIcs->location }}</div>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
                @if($heroEvents->count() > 1)
                    <div class="flex items-center justify-center gap-1.5 mt-4">
                        @foreach($heroEvents as $hi => $hero)
                            <button type="button" class="hero-dot {{ $hi === 0 ? 'active' : '' }}" @click.prevent="go({{ $hi }})"></button>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>

<div class="events-page-body max-w-6xl mx-auto px-4 py-8">

    {{-- Icon-only category tiles: name shows as a hover tooltip, progressive
         disclosure keeps the row to one line with a "more" toggle for the
         rest (Task #3654). "All events" and "Other" are always visible. --}}
    @php $catFitCount = 9; @endphp
    @if($categories->isNotEmpty() || $hasOtherCategory)
        <div class="mb-6" x-data="{ showMoreCats: false }">
            <div class="cat-section-label text-xs font-bold uppercase tracking-wide text-white/40 mb-3">Browse by category</div>
            <div class="flex flex-wrap items-center gap-2.5">
                <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->except(['category', 'page']), ['category' => ''])) }}"
                   class="cat-tile-icon {{ $category === '' ? 'active' : '' }}"
                   data-label="All events" aria-label="All events">
                    <i class="fas fa-layer-group"></i>
                </a>
                @foreach($categories as $i => $c)
                    <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->except(['category', 'page']), ['category' => $category === $c ? '' : $c])) }}"
                       class="cat-tile-icon {{ $category === $c ? 'active' : '' }}"
                       data-label="{{ $categoryLabels[$c] ?? ucfirst($c) }}" aria-label="{{ $categoryLabels[$c] ?? ucfirst($c) }}"
                       @if($i >= $catFitCount) x-show="showMoreCats" x-cloak @endif>
                        <i class="fas {{ $categoryIcons[$c] ?? 'fa-calendar-star' }}"></i>
                    </a>
                    @if($i === $catFitCount - 1 && $categories->count() > $catFitCount)
                        <div class="cat-divider" aria-hidden="true"></div>
                        <button type="button" @click="showMoreCats = !showMoreCats" class="cat-more-btn">
                            <i class="fas" :class="showMoreCats ? 'fa-chevron-left' : 'fa-chevron-right'"></i>
                            <span x-text="showMoreCats ? 'Less' : 'More'"></span>
                        </button>
                    @endif
                @endforeach
                @if($hasOtherCategory)
                    <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->except(['category', 'page']), ['category' => $category === $otherCategory ? '' : $otherCategory])) }}"
                       class="cat-tile-icon {{ $category === $otherCategory ? 'active' : '' }}"
                       data-label="Other" aria-label="Other">
                        <i class="fas fa-ellipsis"></i>
                    </a>
                @endif
            </div>
        </div>
    @endif

    @php
        $quickDateRanges = ['today' => 'Today', 'weekend' => 'This weekend', 'week' => 'This week', 'month' => 'This month'];
    @endphp

    {{-- Filter bar: online / free / paid + date quick ranges + custom range
         + a display-only currency toggle for ticket prices. --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5" x-data="{ showDateRange: {{ $hasCustomRange ? 'true' : 'false' }} }">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->except(['online', 'page']), $online ? [] : ['online' => 1])) }}"
               class="ev-chip {{ $online ? 'active' : '' }} inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-semibold">
                <i class="fas fa-video"></i> Online events only
            </a>
            <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->except(['price', 'page']), ['price' => $priceFilter === 'free' ? '' : 'free'])) }}"
               class="ev-chip {{ $priceFilter === 'free' ? 'active' : '' }} inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-semibold">
                <i class="fas fa-gift"></i> Free
            </a>
            <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->except(['price', 'page']), ['price' => $priceFilter === 'paid' ? '' : 'paid'])) }}"
               class="ev-chip {{ $priceFilter === 'paid' ? 'active' : '' }} inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-semibold">
                <i class="fas fa-ticket"></i> Paid
            </a>
            <div class="w-px self-stretch mx-0.5" style="background:rgba(255,255,255,0.12);"></div>
            @foreach($quickDateRanges as $key => $label)
                <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->except(['date', 'date_from', 'date_to', 'page']), $dateFilter === $key && !$hasCustomRange ? [] : ['date' => $key])) }}"
                   class="ev-chip {{ ($dateFilter === $key && !$hasCustomRange) ? 'active' : '' }} inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full text-sm font-semibold">
                    {{ $label }}
                </a>
            @endforeach
            <button type="button" @click="showDateRange = !showDateRange"
                    class="ev-chip {{ $hasCustomRange ? 'active' : '' }} inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full text-sm font-semibold">
                <i class="fas fa-calendar-days"></i> Custom range
            </button>
        </div>

        {{-- Currency toggle: display-only conversion, native price is always
             the source of truth (see JS applyCurrency()). --}}
        <div class="inline-flex items-center rounded-full p-1" style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1);">
            <span class="text-[10px] font-bold uppercase tracking-wide text-white/30 px-2">Prices in</span>
            <button type="button" class="currency-toggle-btn" data-currency="USD">USD</button>
            <button type="button" class="currency-toggle-btn" data-currency="INR">INR</button>
        </div>
    </div>

    <div x-show="showDateRange" x-cloak x-transition class="ev-date-range-box mb-5 p-3 rounded-2xl">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            @foreach(['q', 'category', 'tag', 'lat', 'lng'] as $preserve)
                @if(request($preserve))<input type="hidden" name="{{ $preserve }}" value="{{ request($preserve) }}">@endif
            @endforeach
            @if($online)<input type="hidden" name="online" value="1">@endif
            @if($priceFilter)<input type="hidden" name="price" value="{{ $priceFilter }}">@endif
            <div>
                <label class="ev-date-range-label block text-[11px] font-semibold mb-1">From</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="ev-date-range-input text-sm rounded-lg px-2.5 py-1.5">
            </div>
            <div>
                <label class="ev-date-range-label block text-[11px] font-semibold mb-1">To</label>
                <input type="date" name="date_to" value="{{ $dateTo }}" class="ev-date-range-input text-sm rounded-lg px-2.5 py-1.5">
            </div>
            <button type="submit" class="px-4 py-1.5 rounded-lg text-sm font-bold text-white" style="background:#3d6bff;">Apply</button>
            @if($hasCustomRange)
                <a href="{{ url()->current() }}?{{ http_build_query(request()->except(['date_from', 'date_to', 'page'])) }}" class="text-xs font-semibold link-accent hover:underline">Clear range</a>
            @endif
        </form>
    </div>

    {{-- Hashtag row: admin-predefined tags first, backfilled with
         auto-trending tags (deduped) — Task #3654. --}}
    @if($tagRow->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2 mb-6">
            <span class="text-xs font-bold uppercase tracking-wide text-white/40 mr-1"><i class="fas fa-fire mr-1" style="color:#f59e0b;"></i> Trending:</span>
            @foreach($tagRow as $rowTag)
                <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->except('tag', 'page'), ['tag' => $tag === $rowTag ? '' : $rowTag])) }}"
                   class="ev-chip {{ $tag === $rowTag ? 'active' : '' }} inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold">
                    #{{ $rowTag }}
                </a>
            @endforeach
        </div>
    @endif

    {{-- Active filters summary. --}}
    @if($nearMe || $tag || $category || $q || $online || $priceFilter || $dateFilter || $hasCustomRange)
        <div class="flex flex-wrap items-center gap-2 mb-6 text-xs text-white/50">
            <span class="font-semibold text-white/40">Active filters:</span>
            @if($q)
                <span class="chip-pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full">
                    "{{ $q }}"
                    <a href="{{ url()->current() }}?{{ http_build_query(request()->except(['q', 'page'])) }}" class="chip-close">&times;</a>
                </span>
            @endif
            @if($category)
                <span class="chip-pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full">
                    @if($category === $otherCategory)
                        <i class="fas fa-ellipsis"></i> Other
                    @else
                        <i class="fas {{ $categoryIcons[$category] ?? 'fa-calendar-star' }}"></i> {{ $categoryLabels[$category] ?? \App\Modules\User\Support\EventCategories::label($category) }}
                    @endif
                    <a href="{{ url()->current() }}?{{ http_build_query(request()->except(['category', 'page'])) }}" class="chip-close">&times;</a>
                </span>
            @endif
            @if($tag)
                <span class="chip-pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full">
                    #{{ $tag }}
                    <a href="{{ url()->current() }}?{{ http_build_query(request()->except(['tag', 'page'])) }}" class="chip-close">&times;</a>
                </span>
            @endif
            @if($online)
                <span class="chip-pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full">
                    <i class="fas fa-video"></i> Online
                    <a href="{{ url()->current() }}?{{ http_build_query(request()->except(['online', 'page'])) }}" class="chip-close">&times;</a>
                </span>
            @endif
            @if($priceFilter)
                <span class="chip-pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full">
                    <i class="fas {{ $priceFilter === 'free' ? 'fa-gift' : 'fa-ticket' }}"></i> {{ ucfirst($priceFilter) }}
                    <a href="{{ url()->current() }}?{{ http_build_query(request()->except(['price', 'page'])) }}" class="chip-close">&times;</a>
                </span>
            @endif
            @if($hasCustomRange)
                <span class="chip-pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full">
                    <i class="fas fa-calendar-days"></i>
                    {{ $dateFrom ?: '…' }} &rarr; {{ $dateTo ?: '…' }}
                    <a href="{{ url()->current() }}?{{ http_build_query(request()->except(['date_from', 'date_to', 'page'])) }}" class="chip-close">&times;</a>
                </span>
            @elseif($dateFilter)
                <span class="chip-pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full">
                    <i class="fas fa-calendar-days"></i> {{ $quickDateRanges[$dateFilter] ?? ucfirst($dateFilter) }}
                    <a href="{{ url()->current() }}?{{ http_build_query(request()->except(['date', 'page'])) }}" class="chip-close">&times;</a>
                </span>
            @endif
            @if($nearMe)
                <span class="chip-pill inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full">
                    <i class="fas fa-location-arrow"></i> Within {{ $radiusKm }}km
                    <a href="{{ url()->current() }}?{{ http_build_query(request()->except(['lat', 'lng', 'page'])) }}" class="chip-close">&times;</a>
                </span>
            @endif
            <a href="{{ route('events.index') }}" class="link-accent hover:underline font-semibold">Clear all</a>
        </div>
    @endif

    @if($events->count() === 0)
        <div class="text-center py-20 ev-card">
            <i class="fas fa-calendar-xmark text-4xl text-white/20 mb-4"></i>
            <p class="text-white/70 font-semibold">No events found</p>
            <p class="text-white/40 text-sm mt-1">Try a different search, category, or clear your filters.</p>
            @if($nearMe || $tag || $category || $q || $online || $priceFilter || $dateFilter || $hasCustomRange)
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
                        $nativeCurrency = null;
                        $nativeCents = 0;
                        $pricePrefix = '';
                    } else {
                        $lowest = $tiers->first();
                        $hasRange = $tiers->count() > 1 && (int) $tiers->last()->price_cents !== (int) $lowest->price_cents;
                        $pricePrefix = $hasRange ? 'From ' : '';
                        $priceLabel = $pricePrefix . $lowest->priceLabel();
                        $priceIsFree = $lowest->isFree() && !$hasRange;
                        $nativeCurrency = strtoupper($lowest->currency);
                        $nativeCents = (int) $lowest->price_cents;
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
                            <div class="ev-card-date-chip absolute top-3 left-3 z-10 rounded-xl px-2.5 py-1.5 text-center shadow-sm leading-none">
                                <div class="text-[10px] font-bold uppercase" style="color:#8fa8ff;">{{ $ics->start_date->format('M') }}</div>
                                <div class="text-base font-extrabold text-white">{{ $ics->start_date->format('j') }}</div>
                            </div>
                        @endif
                        @if($eventIsOnline)
                            <div class="absolute top-3 {{ $ics && $ics->start_date ? 'left-20' : 'left-3' }} z-10 inline-flex items-center gap-1 text-white rounded-full px-2.5 py-1 text-[10px] font-bold" style="background:rgba(16,185,129,0.85);">
                                <i class="fas fa-video"></i> Online
                            </div>
                        @endif
                        <div class="absolute bottom-3 right-3 z-10">
                            <span class="ev-price-badge inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold {{ $priceIsFree ? 'bg-emerald-500 text-white' : 'text-white ev-price' }}"
                                  style="{{ $priceIsFree ? '' : 'background:rgba(61,107,255,0.9);' }}"
                                  @if(!$priceIsFree) data-native-currency="{{ $nativeCurrency }}" data-native-cents="{{ $nativeCents }}" data-prefix="{{ $pricePrefix }}" @endif>
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
                                    <a href="{{ url()->current() }}?{{ http_build_query(array_merge(request()->except(['tag', 'page']), ['tag' => $ht])) }}"
                                       class="hashtag-pill text-[11px] px-2 py-0.5 rounded-full">#{{ $ht }}</a>
                                @endforeach
                            </div>
                        @endif

                        @if($tiers->count() > 1)
                            <div class="tier-breakdown mt-3">
                                <button type="button" class="tier-toggle tier-toggle-link inline-flex items-center gap-1.5 text-xs font-semibold hover:opacity-80" aria-expanded="false">
                                    <i class="fas fa-tags"></i> {{ $tiers->count() }} ticket tiers
                                    <i class="fas fa-chevron-down text-[10px] transition-transform tier-chevron"></i>
                                </button>
                                <div class="tier-list tier-list-box hidden mt-2">
                                    @foreach($tiers as $tier)
                                        <div class="flex items-center justify-between gap-2 px-2.5 py-1.5 text-xs">
                                            <span class="text-white/50 truncate">{{ $tier->name }}</span>
                                            <span class="font-bold whitespace-nowrap {{ $tier->isFree() ? 'text-emerald-400' : 'text-white ev-price' }}"
                                                  @if(!$tier->isFree()) data-native-currency="{{ strtoupper($tier->currency) }}" data-native-cents="{{ (int) $tier->price_cents }}" data-prefix="" @endif>
                                                {{ $tier->priceLabel() }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="ev-card-footer-divider flex items-center justify-between mt-4 pt-3">
                            <div class="text-xs text-white/40 event-interest-counts" data-role="counts">
                                <i class="fas fa-star text-amber-400 mr-1"></i><span data-role="interested-count">{{ $event->interested_count ?? 0 }}</span> interested
                            </div>
                            <a href="{{ url('/' . $event->alias) }}" class="link-accent text-xs font-bold hover:opacity-80">View event &rarr;</a>
                        </div>

                        <div class="flex items-center gap-2 mt-3 event-interest-widget" data-alias="{{ $event->alias }}">
                            <button type="button" class="btn-interest flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold event-interest-btn" style="border:1px solid rgba(16,185,129,0.3); color:#34d399;" data-status="interested">
                                <i class="fas fa-star"></i> Interested
                            </button>
                            <button type="button" class="btn-interest btn-not-interested flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold event-interest-btn" data-status="not_interested">
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
@endsection

@push('scripts')
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
                    self.$nextTick(function () {
                        // The popup only just became visible (x-show flipped
                        // this tick), so wait one more frame for layout to
                        // settle before measuring/creating the map — Leaflet
                        // reads the container's real width/height at init
                        // time and renders a blank grey box if it's still 0.
                        requestAnimationFrame(function () { self.initMap(); });
                    });
                });
            }
        },
        initMap() {
            if (typeof L === 'undefined' || !this.$refs.map) return;
            if (this.map) {
                setTimeout(() => this.map.invalidateSize(), 60);
                return;
            }
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
            // Popup fade-in transition + font/layout settling can still leave
            // Leaflet's cached size stale; invalidate a couple more times.
            setTimeout(function () { self.map.invalidateSize(); }, 80);
            setTimeout(function () { self.map.invalidateSize(); }, 300);
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
        link.href = '{{ asset('css/vendor/leaflet.min.css') }}';
        document.head.appendChild(link);
    }
    const existing = document.getElementById('mpp-leaflet-js');
    if (existing) {
        if (window.L) { cb(); } else { existing.addEventListener('load', cb); }
        return;
    }
    const s = document.createElement('script');
    s.id = 'mpp-leaflet-js';
    s.src = '{{ asset('js/vendor/leaflet.min.js') }}';
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

// Ticket-price currency toggle (USD/INR). Purely a display conversion —
// the native currency/amount stored on data-native-* is always the source
// of truth; conversion is an approximate fixed rate, not a live FX quote.
const FX_INR_PER_USD = 83;
const CURRENCY_PREF_KEY = 'events_currency_pref';

function formatMoney(currency, cents) {
    const amount = cents / 100;
    const formatted = currency === 'INR'
        ? amount.toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
        : amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return (currency === 'INR' ? '₹' : '$') + formatted;
}

function convertCents(nativeCurrency, nativeCents, targetCurrency) {
    if (nativeCurrency === targetCurrency) return nativeCents;
    if (nativeCurrency === 'USD' && targetCurrency === 'INR') return Math.round(nativeCents * FX_INR_PER_USD);
    if (nativeCurrency === 'INR' && targetCurrency === 'USD') return Math.round(nativeCents / FX_INR_PER_USD);
    // Unsupported native currency (not USD/INR) — leave the price as-is in
    // its own currency rather than guess a conversion.
    return null;
}

function applyCurrency(currency) {
    document.querySelectorAll('.currency-toggle-btn').forEach(function (btn) {
        btn.classList.toggle('active', btn.getAttribute('data-currency') === currency);
    });
    document.querySelectorAll('.ev-price').forEach(function (el) {
        const nativeCurrency = el.getAttribute('data-native-currency');
        const nativeCents = parseInt(el.getAttribute('data-native-cents') || '0', 10);
        const prefix = el.getAttribute('data-prefix') || '';
        if (!nativeCurrency) return;
        const converted = convertCents(nativeCurrency, nativeCents, currency);
        if (converted === null) {
            el.textContent = prefix + formatMoney(nativeCurrency, nativeCents);
            return;
        }
        el.textContent = prefix + (nativeCurrency === currency ? '' : '≈') + formatMoney(currency, converted);
    });
    try { localStorage.setItem(CURRENCY_PREF_KEY, currency); } catch (e) { /* storage unavailable */ }
}

document.querySelectorAll('.currency-toggle-btn').forEach(function (btn) {
    btn.addEventListener('click', function () { applyCurrency(btn.getAttribute('data-currency')); });
});

(function initCurrency() {
    let pref = null;
    try { pref = localStorage.getItem(CURRENCY_PREF_KEY); } catch (e) { /* storage unavailable */ }
    applyCurrency(pref === 'USD' || pref === 'INR' ? pref : '{{ $defaultCurrency }}');
})();
</script>
@endpush
