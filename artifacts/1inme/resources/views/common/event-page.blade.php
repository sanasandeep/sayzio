@extends('public.layouts.site')

@php
    $ics = $link->icsData;
    $eventCategory = ($link->settings ?? [])['event_category'] ?? '';
    $isOnline = !empty(($link->settings ?? [])['is_online']);
    $rsvpEnabled = !empty(($link->settings ?? [])['rsvp_enabled']);
    $hasTicketTiers = isset($tiers) && $tiers->isNotEmpty();
    $categoryGradient = \App\Modules\User\Support\EventCategories::gradient($eventCategory ?: '');
    $hasPin = !$isOnline && $ics && $ics->latitude !== null && $ics->longitude !== null;
    $metaDescription = \Illuminate\Support\Str::limit($ics->description ?? $link->title, 180);

    $shareTitle = $link->title;
    $shareDescription = $metaDescription;
    $shareImage = $ics->cover_image_url ?? null;

    // This page has its own cover-image hero — suppress the layout's
    // cross-page "Discover Events" promo band so it doesn't stack a second,
    // competing hero above this one (Task #3668).
    request()->attributes->set('suppress_events_hero_band', true);
@endphp

@section('title', $link->title)

@push('head')
<style>
    .ev-card { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:1.25rem; }
    .ev-accent-bg { background:#3d6bff; }
    .ev-accent-text { color:#8fa8ff; }
    .ev-chip { background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.08); color:#fff; }
    .ev-hero { background: {{ $categoryGradient }}; }
    .ev-tier { border:1.5px solid rgba(255,255,255,0.10); border-radius:0.9rem; transition:border-color .15s ease, background .15s ease; }
    .ev-tier:has(input:checked) { border-color:#3d6bff; background:rgba(61,107,255,0.10); }
    .ev-tier input:disabled { opacity:.4; }
    .ev-input { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12); color:#e8eaf0; }
    .ev-input:focus { outline:none; border-color:#3d6bff; box-shadow:0 0 0 3px rgba(61,107,255,0.25); }
    .ev-label { color: rgba(255,255,255,0.5); }
    .ev-muted { color: rgba(255,255,255,0.6); }
    .ev-muted-lite { color: rgba(255,255,255,0.4); }
    .ev-desc { color: rgba(255,255,255,0.7); }
    .ev-price-free { color: #34d399; }
    #ev-map { height:260px; border-radius:0.9rem; }
    footer.ev-footer { color: rgba(255,255,255,0.25); }

    html.light-mode .ev-card { background:#ffffff; border-color:rgba(15,23,42,0.08); box-shadow:0 1px 3px rgba(15,23,42,0.06); }
    html.light-mode .ev-accent-text { color:#3d6bff; }
    html.light-mode .ev-chip { background:rgba(15,23,42,0.05); border-color:rgba(15,23,42,0.08); color:#111827; }
    html.light-mode .ev-tier { border-color:rgba(15,23,42,0.12); }
    html.light-mode .ev-tier:has(input:checked) { border-color:#3d6bff; background:rgba(61,107,255,0.06); }
    html.light-mode .ev-input { background:#f8fafc; border-color:rgba(15,23,42,0.14); color:#111827; }
    html.light-mode .ev-label { color: rgba(15,23,42,0.55); }
    html.light-mode .ev-muted { color: rgba(15,23,42,0.6); }
    html.light-mode .ev-muted-lite { color: rgba(15,23,42,0.4); }
    html.light-mode .ev-desc { color: rgba(15,23,42,0.72); }
    html.light-mode .ev-price-free { color: #059669; }
    html.light-mode h1.ev-title { color:#0f172a; }
    html.light-mode footer.ev-footer { color: rgba(15,23,42,0.4); }

    /* Scoped overrides so the shared (Bootstrap-styled) event-rich-content
       partial — reused by the light RSVP page — reads correctly on this
       marketing theme, in both dark and light mode. Do not restyle the
       partial itself; it must stay visually correct on rsvp-form.blade.php too. */
    .ev-rich { color: #e8eaf0; }
    .ev-rich .badge.bg-light { background: rgba(255,255,255,0.06) !important; border-color: rgba(255,255,255,0.12) !important; color: #b9c2e0 !important; }
    .ev-rich .text-dark { color: #e8eaf0 !important; }
    .ev-rich .text-muted { color: rgba(232,234,240,0.55) !important; }
    .ev-rich .border { border-color: rgba(255,255,255,0.10) !important; }
    .ev-rich a.border, .ev-rich a.border:hover { background: rgba(255,255,255,0.03); transition: background .15s ease, border-color .15s ease; }
    .ev-rich a.border:hover { background: rgba(255,255,255,0.06); border-color: rgba(61,107,255,0.4) !important; }
    .ev-rich .btn-outline-success { color:#34d399; border-color: rgba(52,211,153,0.45); background:transparent; }
    .ev-rich .btn-outline-success:hover { background: rgba(52,211,153,0.12); color:#34d399; }
    .ev-rich .btn-outline-secondary { color: rgba(232,234,240,0.65); border-color: rgba(255,255,255,0.18); background:transparent; }
    .ev-rich .btn-outline-secondary:hover { background: rgba(255,255,255,0.06); color:#e8eaf0; }
    .ev-rich .fw-semibold { font-weight: 600; }
    .ev-rich .border.rounded-3 { border-radius: 0.75rem !important; }
    .ev-rich .row.g-2 { display: flex; flex-wrap: wrap; align-items: flex-start; margin: 0 -0.25rem; }
    .ev-rich .row.g-2 > [class^="col-"] { padding: 0 0.25rem; margin-bottom: 0.5rem; box-sizing: border-box; align-self: flex-start; }
    .ev-rich .row.g-2 > .col-6 { width: 50%; }
    .ev-rich .row.g-2 > .col-4 { width: 33.3333%; }
    .ev-rich .h-100 { height: auto !important; }

    html.light-mode .ev-rich { color: #111827; }
    html.light-mode .ev-rich .badge.bg-light { background: rgba(15,23,42,0.05) !important; border-color: rgba(15,23,42,0.1) !important; color: #475569 !important; }
    html.light-mode .ev-rich .text-dark { color: #111827 !important; }
    html.light-mode .ev-rich .text-muted { color: rgba(15,23,42,0.55) !important; }
    html.light-mode .ev-rich .border { border-color: rgba(15,23,42,0.1) !important; }
    html.light-mode .ev-rich a.border, html.light-mode .ev-rich a.border:hover { background: rgba(15,23,42,0.02); }
    html.light-mode .ev-rich a.border:hover { background: rgba(15,23,42,0.05); border-color: rgba(61,107,255,0.4) !important; }
    html.light-mode .ev-rich .btn-outline-secondary { color: rgba(15,23,42,0.65); border-color: rgba(15,23,42,0.18); }
    html.light-mode .ev-rich .btn-outline-secondary:hover { background: rgba(15,23,42,0.06); color:#111827; }
</style>
@endpush

@section('content')
<section class="relative pb-20">
    <div class="mesh-bg"></div>
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

        @if(session('success'))
            <div class="mb-5 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
                <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div class="mb-5 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #ef4444;">
                <i class="fas fa-exclamation-circle mr-1.5"></i> {{ $errors->first() }}
            </div>
        @endif

        <div class="ev-card overflow-hidden">
            <div class="relative">
                @if($ics && $ics->cover_image_url)
                    <img src="{{ $ics->cover_image_url }}" alt="{{ $link->title }}" class="w-full h-64 sm:h-80 lg:h-[26rem] object-cover">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent"></div>
                @else
                    <img src="{{ asset('images/events/event-cover-placeholder.svg') }}" alt="{{ $link->title }}" class="w-full h-48 sm:h-56 object-cover">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent"></div>
                @endif

                <div class="absolute top-4 left-4 flex flex-wrap gap-2">
                    @if($eventCategory)
                        <span class="ev-chip text-xs font-semibold px-3 py-1.5 rounded-full backdrop-blur">
                            <i class="fas {{ \App\Modules\User\Support\EventCategories::icon($eventCategory) }} mr-1"></i>
                            {{ \App\Modules\User\Support\EventCategories::label($eventCategory) }}
                        </span>
                    @endif
                    @if($isOnline)
                        <span class="text-xs font-semibold px-3 py-1.5 rounded-full backdrop-blur text-white" style="background: rgba(16,185,129,0.25); border:1px solid rgba(16,185,129,0.4);">
                            <i class="fas fa-video mr-1"></i> Online
                        </span>
                    @endif
                </div>
            </div>

            <div class="p-6 sm:p-8 lg:p-10">
                <div class="grid lg:grid-cols-3 gap-8 lg:gap-10">
                    {{-- Main content column --}}
                    <div class="lg:col-span-2">
                        <h1 class="ev-title text-2xl sm:text-3xl lg:text-4xl font-extrabold leading-tight">{{ $link->title }}</h1>

                        @if($ics && $ics->start_date)
                            <div class="mt-3 flex items-center gap-2 text-sm ev-muted">
                                <i class="far fa-clock ev-accent-text"></i>
                                {{ $ics->start_date->setTimezone(new \DateTimeZone($ics->timezone ?: 'UTC'))->format('D, M j Y · g:i A') }}
                            </div>
                        @endif
                        @if($ics && $ics->location)
                            <div class="mt-1.5 flex items-center gap-2 text-sm ev-muted">
                                <i class="fas fa-location-dot ev-accent-text"></i> {{ $ics->location }}
                            </div>
                        @endif

                        @if($ics && $ics->description)
                            <p class="mt-4 text-sm ev-desc whitespace-pre-line leading-relaxed">{{ $ics->description }}</p>
                        @endif

                        {{-- Cover/gallery/info sections, hashtags, Interested widget, similar/host events --}}
                        <div class="ev-rich mt-5">
                            @include('common.partials.event-rich-content', ['link' => $link, 'similarEvents' => $similarEvents ?? collect(), 'sameHostEvents' => $sameHostEvents ?? collect(), 'interestCounts' => $interestCounts ?? []])
                        </div>

                        @if($hasPin)
                            <div class="mt-6">
                                <div id="ev-map" data-lat="{{ $ics->latitude }}" data-lng="{{ $ics->longitude }}" data-label="{{ $link->title }}"></div>
                            </div>
                        @endif
                    </div>

                    {{-- Sticky CTA column --}}
                    <div class="lg:col-span-1">
                        <div class="lg:sticky lg:top-24">
                            @if($hasTicketTiers)
                                <form method="POST" action="{{ route('redirect.event.buy', $link->alias) }}" id="ticket-form" class="ev-card p-5">
                                    @csrf
                                    <h2 class="text-sm font-bold uppercase tracking-wide ev-label mb-3">Get tickets</h2>
                                    <div class="space-y-2.5">
                                        @foreach($tiers as $tier)
                                            <label class="ev-tier flex items-start justify-between gap-3 p-3.5 cursor-pointer">
                                                <div class="flex items-start gap-3">
                                                    <input type="radio" name="tier_id" value="{{ $tier->id }}" required class="mt-1"
                                                           @checked($loop->first) @disabled($tier->isSoldOut() || !$tier->isOnSale())>
                                                    <div>
                                                        <div class="font-semibold text-sm flex items-center gap-2">
                                                            {{ $tier->name }}
                                                            @if($tier->isSoldOut())<span class="text-[10px] font-bold px-2 py-0.5 rounded-full ev-chip">SOLD OUT</span>@endif
                                                        </div>
                                                        @if($tier->description)<div class="text-xs ev-muted-lite mt-0.5">{{ $tier->description }}</div>@endif
                                                        @if($tier->remainingCapacity() !== null)
                                                            <div class="text-xs ev-muted-lite mt-0.5">{{ $tier->remainingCapacity() }} remaining</div>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="font-bold text-sm whitespace-nowrap {{ $tier->isFree() ? 'ev-price-free' : '' }}">{{ $tier->priceLabel() }}</div>
                                            </label>
                                        @endforeach
                                    </div>

                                    <div class="grid grid-cols-2 gap-3 mt-4">
                                        <div>
                                            <label class="block text-xs font-semibold ev-label mb-1">Quantity</label>
                                            <input type="number" name="quantity" value="1" min="1" max="20" class="ev-input w-full rounded-lg px-3 py-2 text-sm">
                                        </div>
                                    </div>
                                    <div class="grid sm:grid-cols-2 gap-3 mt-3">
                                        <div>
                                            <label class="block text-xs font-semibold ev-label mb-1">Full name</label>
                                            <input type="text" name="name" required value="{{ old('name') }}" class="ev-input w-full rounded-lg px-3 py-2 text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold ev-label mb-1">Email</label>
                                            <input type="email" name="email" required value="{{ old('email') }}" class="ev-input w-full rounded-lg px-3 py-2 text-sm">
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <label class="block text-xs font-semibold ev-label mb-1">Phone (optional)</label>
                                        <input type="text" name="phone" value="{{ old('phone') }}" class="ev-input w-full rounded-lg px-3 py-2 text-sm">
                                    </div>

                                    <button type="submit" class="ev-accent-bg w-full mt-4 py-3 rounded-xl text-sm font-bold text-white hover:opacity-90 transition">
                                        <i class="fas fa-ticket-alt mr-1.5"></i> Get tickets
                                    </button>
                                </form>
                            @elseif($rsvpEnabled)
                                <div class="ev-card p-5">
                                    <h2 class="text-sm font-bold uppercase tracking-wide ev-label mb-3">Join this event</h2>
                                    <a href="{{ route('redirect.rsvp.form', $link->alias) }}"
                                       class="ev-accent-bg w-full flex items-center justify-center gap-2 py-3 rounded-xl text-sm font-bold text-white hover:opacity-90 transition">
                                        <i class="fas fa-calendar-check"></i> RSVP now
                                    </a>
                                </div>
                            @else
                                <div class="ev-card p-5 text-center">
                                    <p class="text-sm ev-muted-lite py-2">This event doesn't require a ticket or RSVP.</p>
                                </div>
                            @endif

                            <div class="flex flex-wrap items-center justify-center gap-3 mt-4 text-sm">
                                <a href="{{ url('/' . $link->alias . '?ics=1') }}" class="inline-flex items-center gap-1.5 ev-chip px-4 py-2 rounded-xl hover:opacity-80">
                                    <i class="fas fa-calendar-plus"></i> Add to calendar
                                </a>
                                <a href="{{ auth('web')->check() ? route('user.links.create') : (route('user.login') . '?redirect=' . urlencode(route('user.links.create'))) }}"
                                   class="inline-flex items-center gap-1.5 ev-chip px-4 py-2 rounded-xl hover:opacity-80">
                                    <i class="fas fa-plus"></i> Create your own event
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <footer class="ev-footer text-center mt-8 text-xs">
            Powered by <a href="{{ url('/') }}" class="ev-accent-text hover:underline">{{ config('app.name') }}</a>
        </footer>
    </div>
</section>
@endsection

@if($hasPin)
@push('scripts')
<script>
(function () {
    function ensureLeaflet(cb) {
        if (window.L) { cb(); return; }
        if (!document.getElementById('mpp-leaflet-css')) {
            var link = document.createElement('link');
            link.id = 'mpp-leaflet-css';
            link.rel = 'stylesheet';
            link.href = '/css/vendor/leaflet.min.css';
            document.head.appendChild(link);
        }
        var existing = document.getElementById('mpp-leaflet-js');
        if (existing) { existing.addEventListener('load', cb); return; }
        var s = document.createElement('script');
        s.id = 'mpp-leaflet-js';
        s.src = '/js/vendor/leaflet.min.js';
        s.onload = cb;
        document.head.appendChild(s);
    }
    var el = document.getElementById('ev-map');
    if (!el) return;
    var lat = parseFloat(el.dataset.lat), lng = parseFloat(el.dataset.lng);
    if (!isFinite(lat) || !isFinite(lng)) return;
    ensureLeaflet(function () {
        var map = L.map(el, { center: [lat, lng], zoom: 15, scrollWheelZoom: false });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(map);
        var icon = L.divIcon({
            className: '',
            html: '<div style="width:30px;height:40px;"><svg viewBox="0 0 34 44" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="evmap-g" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#90acff"/><stop offset="100%" stop-color="#3d6bff"/></linearGradient></defs><path d="M17 0C7.6 0 0 7.5 0 16.7c0 11.7 14.6 25.5 16 26.8.6.6 1.5.6 2 0 1.5-1.3 16-15.1 16-26.8C34 7.5 26.4 0 17 0z" fill="url(#evmap-g)" stroke="rgba(255,255,255,0.85)" stroke-width="1.5"/></svg></div>',
            iconSize: [30, 40],
            iconAnchor: [15, 40],
        });
        L.marker([lat, lng], { icon: icon }).addTo(map);
        setTimeout(function () { map.invalidateSize(); }, 80);
    });
})();
</script>
@endpush
@endif
