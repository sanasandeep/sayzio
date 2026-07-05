<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $ics = $link->icsData;
        $eventCategory = ($link->settings ?? [])['event_category'] ?? '';
        $isOnline = !empty(($link->settings ?? [])['is_online']);
        $rsvpEnabled = !empty(($link->settings ?? [])['rsvp_enabled']);
        $hasTicketTiers = isset($tiers) && $tiers->isNotEmpty();
        $categoryGradient = \App\Modules\User\Support\EventCategories::gradient($eventCategory ?: '');
        $hasPin = !$isOnline && $ics && $ics->latitude !== null && $ics->longitude !== null;
        $metaDescription = \Illuminate\Support\Str::limit($ics->description ?? $link->title, 180);
    @endphp
    <title>{{ $link->title }} — {{ config('app.name') }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <meta property="og:title" content="{{ $link->title }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:type" content="website">
    @if($ics && $ics->cover_image_url)<meta property="og:image" content="{{ $ics->cover_image_url }}">@endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/vendor/fontawesome-free-6.5.1/css/all.min.css') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family:'Space Grotesk',sans-serif; background:#0b0e16; color:#e8eaf0; min-height:100vh; }
        .ev-card { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:1.25rem; }
        .ev-accent-bg { background:#3d6bff; }
        .ev-accent-text { color:#8fa8ff; }
        .ev-chip { background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.08); }
        .ev-hero { background: {{ $categoryGradient }}; }
        .ev-tier { border:1.5px solid rgba(255,255,255,0.10); border-radius:0.9rem; transition:border-color .15s ease, background .15s ease; }
        .ev-tier:has(input:checked) { border-color:#3d6bff; background:rgba(61,107,255,0.10); }
        .ev-tier input:disabled { opacity:.4; }
        .ev-input { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12); color:#e8eaf0; }
        .ev-input:focus { outline:none; border-color:#3d6bff; box-shadow:0 0 0 3px rgba(61,107,255,0.25); }
        [x-cloak] { display:none !important; }
        #ev-map { height:220px; border-radius:0.9rem; }

        /* Scoped overrides so the shared (Bootstrap-styled) event-rich-content
           partial — reused by the light RSVP page — reads correctly on this
           dark glass theme. Do not restyle the partial itself; it must stay
           visually correct on rsvp-form.blade.php too. */
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
    </style>
</head>
<body>
<div class="max-w-2xl mx-auto px-4 py-8 sm:py-10">

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

    <div class="ev-card overflow-hidden mb-6">
        <div class="relative">
            @if($ics && $ics->cover_image_url)
                <img src="{{ $ics->cover_image_url }}" alt="{{ $link->title }}" class="w-full h-56 sm:h-72 object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent"></div>
            @else
                <img src="{{ asset('images/events/event-cover-placeholder.svg') }}" alt="{{ $link->title }}" class="w-full h-40 sm:h-48 object-cover">
                <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent"></div>
            @endif

            <div class="absolute top-4 left-4 flex flex-wrap gap-2">
                @if($eventCategory)
                    <span class="ev-chip text-white text-xs font-semibold px-3 py-1.5 rounded-full backdrop-blur">
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

        <div class="p-6 sm:p-7">
            <h1 class="text-2xl sm:text-3xl font-extrabold leading-tight">{{ $link->title }}</h1>

            @if($ics && $ics->start_date)
                <div class="mt-3 flex items-center gap-2 text-sm text-white/60">
                    <i class="far fa-clock ev-accent-text"></i>
                    {{ $ics->start_date->setTimezone(new \DateTimeZone($ics->timezone ?: 'UTC'))->format('D, M j Y · g:i A') }}
                </div>
            @endif
            @if($ics && $ics->location)
                <div class="mt-1.5 flex items-center gap-2 text-sm text-white/60">
                    <i class="fas fa-location-dot ev-accent-text"></i> {{ $ics->location }}
                </div>
            @endif

            @if($ics && $ics->description)
                <p class="mt-4 text-sm text-white/70 whitespace-pre-line">{{ $ics->description }}</p>
            @endif

            {{-- Cover/gallery/info sections, hashtags, Interested widget, similar/host events --}}
            <div class="ev-rich mt-4">
                @include('common.partials.event-rich-content', ['link' => $link, 'similarEvents' => $similarEvents ?? collect(), 'sameHostEvents' => $sameHostEvents ?? collect(), 'interestCounts' => $interestCounts ?? []])
            </div>

            @if($hasPin)
                <div class="mt-5">
                    <div id="ev-map" data-lat="{{ $ics->latitude }}" data-lng="{{ $ics->longitude }}" data-label="{{ $link->title }}"></div>
                </div>
            @endif

            {{-- ── CTAs ──────────────────────────────────────────── --}}
            <div class="mt-7 pt-6 border-t border-white/10">
                @if($hasTicketTiers)
                    <form method="POST" action="{{ route('redirect.event.buy', $link->alias) }}" id="ticket-form">
                        @csrf
                        <h2 class="text-sm font-bold uppercase tracking-wide text-white/50 mb-3">Get tickets</h2>
                        <div class="space-y-2.5">
                            @foreach($tiers as $tier)
                                <label class="ev-tier flex items-start justify-between gap-3 p-3.5 cursor-pointer">
                                    <div class="flex items-start gap-3">
                                        <input type="radio" name="tier_id" value="{{ $tier->id }}" required class="mt-1"
                                               @checked($loop->first) @disabled($tier->isSoldOut() || !$tier->isOnSale())>
                                        <div>
                                            <div class="font-semibold text-sm flex items-center gap-2">
                                                {{ $tier->name }}
                                                @if($tier->isSoldOut())<span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-white/10 text-white/50">SOLD OUT</span>@endif
                                            </div>
                                            @if($tier->description)<div class="text-xs text-white/45 mt-0.5">{{ $tier->description }}</div>@endif
                                            @if($tier->remainingCapacity() !== null)
                                                <div class="text-xs text-white/35 mt-0.5">{{ $tier->remainingCapacity() }} remaining</div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="font-bold text-sm whitespace-nowrap {{ $tier->isFree() ? 'text-emerald-400' : 'text-white' }}">{{ $tier->priceLabel() }}</div>
                                </label>
                            @endforeach
                        </div>

                        <div class="grid grid-cols-2 gap-3 mt-4">
                            <div>
                                <label class="block text-xs font-semibold text-white/50 mb-1">Quantity</label>
                                <input type="number" name="quantity" value="1" min="1" max="20" class="ev-input w-full rounded-lg px-3 py-2 text-sm">
                            </div>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-3 mt-3">
                            <div>
                                <label class="block text-xs font-semibold text-white/50 mb-1">Full name</label>
                                <input type="text" name="name" required value="{{ old('name') }}" class="ev-input w-full rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-white/50 mb-1">Email</label>
                                <input type="email" name="email" required value="{{ old('email') }}" class="ev-input w-full rounded-lg px-3 py-2 text-sm">
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="block text-xs font-semibold text-white/50 mb-1">Phone (optional)</label>
                            <input type="text" name="phone" value="{{ old('phone') }}" class="ev-input w-full rounded-lg px-3 py-2 text-sm">
                        </div>

                        <button type="submit" class="ev-accent-bg w-full mt-4 py-3 rounded-xl text-sm font-bold text-white hover:opacity-90 transition">
                            <i class="fas fa-ticket-alt mr-1.5"></i> Get tickets
                        </button>
                    </form>
                @elseif($rsvpEnabled)
                    <h2 class="text-sm font-bold uppercase tracking-wide text-white/50 mb-3">Join this event</h2>
                    <a href="{{ route('redirect.rsvp.form', $link->alias) }}"
                       class="ev-accent-bg w-full flex items-center justify-center gap-2 py-3 rounded-xl text-sm font-bold text-white hover:opacity-90 transition">
                        <i class="fas fa-calendar-check"></i> RSVP now
                    </a>
                @else
                    <p class="text-sm text-white/40 text-center py-2">This event doesn't require a ticket or RSVP.</p>
                @endif

                <div class="flex flex-wrap items-center justify-center gap-4 mt-4 text-sm">
                    <a href="{{ url('/' . $link->alias . '?ics=1') }}" class="inline-flex items-center gap-1.5 ev-chip px-4 py-2 rounded-xl text-white/70 hover:text-white">
                        <i class="fas fa-calendar-plus"></i> Add to calendar
                    </a>
                    <a href="{{ auth('web')->check() ? route('user.links.create') : (route('user.login') . '?redirect=' . urlencode(route('user.links.create'))) }}"
                       class="inline-flex items-center gap-1.5 ev-chip px-4 py-2 rounded-xl text-white/70 hover:text-white">
                        <i class="fas fa-plus"></i> Create your own event
                    </a>
                </div>
            </div>
        </div>
    </div>

    <footer class="text-center mt-8 text-xs text-white/25">
        Powered by <a href="{{ url('/') }}" class="ev-accent-text hover:underline">{{ config('app.name') }}</a>
    </footer>
</div>

@if($hasPin)
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
@endif
</body>
</html>
