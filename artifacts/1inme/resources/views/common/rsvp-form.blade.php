@php
    $ics = $link->icsData;
    $isOnline = !empty(($link->settings ?? [])['is_online']);
    $hasPin = !$isOnline && $ics && $ics->latitude !== null && $ics->longitude !== null;
    $directionsUrl = $hasPin
        ? 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($ics->latitude . ',' . $ics->longitude)
        : null;
@endphp
<!DOCTYPE html>
<html lang="en" class="{{ request()->cookie('1inme_theme') === 'light' ? 'light-mode' : '' }}">
<head>
    @include('common.partials.toolbar-theme-color')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>RSVP: {{ $link->title }}</title>
    @include('common.partials.theme-bootstrap')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    @include('common.partials.fontawesome')
    @if($hasPin)
        <link rel="stylesheet" href="{{ asset('css/vendor/leaflet.min.css') }}">
    @endif
    <style>
        /* ── Dark base (default) ── */
        body { background:#0f0f1a; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
        .rsvp-card { max-width:520px; width:100%; border-radius:18px; border:none; box-shadow:0 12px 40px rgba(0,0,0,0.55); overflow:hidden; }
        .rsvp-header { background:linear-gradient(135deg,#3d6bff 0%,#6e61ff 100%); color:#fff; padding:28px; }
        .rsvp-body { padding:28px; background:#16171f; }
        .response-pill { cursor:pointer; border:2px solid rgba(255,255,255,0.12); border-radius:14px; padding:14px; text-align:center; font-weight:600; transition:all .15s; background:rgba(255,255,255,0.04); color:#e2e8f0; }
        .response-pill input { display:none; }
        .response-pill.is-yes:has(input:checked) { border-color:#10b981; background:rgba(16,185,129,0.15); color:#34d399; }
        .response-pill.is-maybe:has(input:checked) { border-color:#f59e0b; background:rgba(245,158,11,0.15); color:#fbbf24; }
        .response-pill.is-no:has(input:checked) { border-color:rgba(255,255,255,0.25); background:rgba(255,255,255,0.06); color:#94a3b8; }
        .btn-purple { background:#3d6bff; color:#fff; border:none; }
        .btn-purple:hover { background:#2342c7; color:#fff; }

        /* Bootstrap form/alert overrides for dark — scoped to .rsvp-body */
        .rsvp-body .form-control,
        .rsvp-body .form-select { background:rgba(255,255,255,0.06); border-color:rgba(255,255,255,0.12); color:#e2e8f0; }
        .rsvp-body .form-control::placeholder { color:rgba(255,255,255,0.35); }
        .rsvp-body .form-control:focus,
        .rsvp-body .form-select:focus { background:rgba(255,255,255,0.09); border-color:rgba(61,107,255,0.6); color:#e2e8f0; box-shadow:0 0 0 0.25rem rgba(61,107,255,0.2); }
        .rsvp-body .form-label { color:#94a3b8; }
        .rsvp-body .alert-success { background:rgba(16,185,129,0.15); border-color:rgba(16,185,129,0.3); color:#34d399; }
        .rsvp-body .alert-info { background:rgba(59,130,246,0.15); border-color:rgba(59,130,246,0.3); color:#93c5fd; }
        .rsvp-body .text-muted { color:#94a3b8 !important; }
        .rsvp-body .btn-outline-primary { color:#7d9bff; border-color:rgba(61,107,255,0.5); background:transparent; }
        .rsvp-body .btn-outline-primary:hover { background:rgba(61,107,255,0.15); color:#7d9bff; border-color:#3d6bff; }
        .rsvp-body .btn-outline-danger { color:#fb7185; border-color:rgba(251,113,133,0.4); background:transparent; }
        .rsvp-body .btn-outline-danger:hover { background:rgba(251,113,133,0.12); color:#fb7185; border-color:#fb7185; }

        /* Map border */
        #ev-map { height:220px; border-radius:12px; border:1px solid rgba(255,255,255,0.12); }

        /* ── Scoped dark overrides for the event-rich-content partial ──
           Never edit common/partials/event-rich-content.blade.php; restyle
           it for the dark page via this .rsvp-rich wrapper (same pattern as
           .ev-rich in event-page.blade.php). */
        .rsvp-rich { color: #e8eaf0; }
        .rsvp-rich .badge.bg-light { background: rgba(255,255,255,0.06) !important; border-color: rgba(255,255,255,0.12) !important; color: #b9c2e0 !important; }
        .rsvp-rich .text-dark { color: #e8eaf0 !important; }
        .rsvp-rich .text-muted { color: rgba(232,234,240,0.55) !important; }
        .rsvp-rich .border { border-color: rgba(255,255,255,0.10) !important; }
        .rsvp-rich a.border:hover { border-color: rgba(61,107,255,0.4) !important; }
        .rsvp-rich .btn-outline-success { color:#34d399; border-color: rgba(52,211,153,0.45); background:transparent; }
        .rsvp-rich .btn-outline-success:hover { background: rgba(52,211,153,0.12); color:#34d399; }
        .rsvp-rich .btn-outline-secondary { color: rgba(232,234,240,0.65); border-color: rgba(255,255,255,0.18); background:transparent; }
        .rsvp-rich .btn-outline-secondary:hover { background: rgba(255,255,255,0.06); color:#e8eaf0; }
        .rsvp-rich .ev-rec-heading { color: rgba(232,234,240,0.85); }
        .rsvp-rich .ev-rec-card { border-color: rgba(255,255,255,0.10) !important; }
        .rsvp-rich .ev-rec-badge-free { color: #34d399 !important; border-color: rgba(52,211,153,0.35); }
        .rsvp-rich .ev-rec-badge-paid { color: #e8eaf0 !important; border-color: rgba(255,255,255,0.18); }

        /* ── Light-mode overrides — preserve today's light look ── */
        html.light-mode body { background:#f5f3ff; }
        html.light-mode .rsvp-body { background:#fff; }
        html.light-mode .response-pill { border-color:#e5e7eb; background:#fff; color:inherit; }
        html.light-mode .response-pill.is-yes:has(input:checked) { border-color:#10b981; background:#ecfdf5; color:#047857; }
        html.light-mode .response-pill.is-maybe:has(input:checked) { border-color:#f59e0b; background:#fffbeb; color:#b45309; }
        html.light-mode .response-pill.is-no:has(input:checked) { border-color:#9ca3af; background:#f3f4f6; color:#374151; }
        html.light-mode .rsvp-body .form-control,
        html.light-mode .rsvp-body .form-select { background:#fff; border-color:#dee2e6; color:inherit; }
        html.light-mode .rsvp-body .form-control::placeholder { color:#6c757d; }
        html.light-mode .rsvp-body .form-control:focus,
        html.light-mode .rsvp-body .form-select:focus { background:#fff; border-color:#86b7fe; color:inherit; box-shadow:0 0 0 0.25rem rgba(13,110,253,0.25); }
        html.light-mode .rsvp-body .form-label { color:inherit; }
        html.light-mode .rsvp-body .alert-success { background:#d1e7dd; border-color:#badbcc; color:#0f5132; }
        html.light-mode .rsvp-body .alert-info { background:#cff4fc; border-color:#b6effb; color:#055160; }
        html.light-mode .rsvp-body .text-muted { color:#6c757d !important; }
        html.light-mode .rsvp-body .btn-outline-primary { color:#0d6efd; border-color:#0d6efd; background:transparent; }
        html.light-mode .rsvp-body .btn-outline-primary:hover { background:#0d6efd; color:#fff; border-color:#0d6efd; }
        html.light-mode .rsvp-body .btn-outline-danger { color:#dc3545; border-color:#dc3545; background:transparent; }
        html.light-mode .rsvp-body .btn-outline-danger:hover { background:#dc3545; color:#fff; border-color:#dc3545; }
        html.light-mode #ev-map { border-color:#e5e7eb; }

        /* event-rich-content light-mode overrides */
        html.light-mode .rsvp-rich { color: #111827; }
        html.light-mode .rsvp-rich .badge.bg-light { background: rgba(15,23,42,0.05) !important; border-color: rgba(15,23,42,0.1) !important; color: #475569 !important; }
        html.light-mode .rsvp-rich .text-dark { color: #111827 !important; }
        html.light-mode .rsvp-rich .text-muted { color: rgba(15,23,42,0.55) !important; }
        html.light-mode .rsvp-rich .border { border-color: rgba(15,23,42,0.1) !important; }
        html.light-mode .rsvp-rich a.border:hover { border-color: rgba(61,107,255,0.4) !important; }
        html.light-mode .rsvp-rich .btn-outline-success { color:#059669; border-color: rgba(5,150,105,0.5); }
        html.light-mode .rsvp-rich .btn-outline-success:hover { background: rgba(5,150,105,0.1); color:#047857; }
        html.light-mode .rsvp-rich .btn-outline-secondary { color: rgba(15,23,42,0.65); border-color: rgba(15,23,42,0.18); }
        html.light-mode .rsvp-rich .btn-outline-secondary:hover { background: rgba(15,23,42,0.06); color:#111827; }
        html.light-mode .rsvp-rich .ev-rec-heading { color: rgba(15,23,42,0.75); }
        html.light-mode .rsvp-rich .ev-rec-card { border-color: rgba(15,23,42,0.08) !important; }
        html.light-mode .rsvp-rich .ev-rec-badge-free { color: #059669 !important; border-color: rgba(5,150,105,0.25); }
        html.light-mode .rsvp-rich .ev-rec-badge-paid { color: #334155 !important; border-color: rgba(15,23,42,0.12); }
    </style>
</head>
<body>
<div class="card rsvp-card">
    <div class="rsvp-header">
        <div class="small opacity-75 mb-1"><i class="fas fa-calendar-alt me-1"></i> You're invited to</div>
        <h1 class="h3 fw-bold mb-2">{{ $link->title }}</h1>
        @if($link->icsData && $link->icsData->start_date)
            <div class="small">
                <i class="far fa-clock me-1"></i>
                {{ $link->icsData->start_date->setTimezone(new \DateTimeZone($link->icsData->timezone ?: 'UTC'))->format('D, M j Y · g:i A') }}
                @if($link->icsData->location) · <i class="fas fa-map-marker-alt ms-1 me-1"></i>{{ $link->icsData->location }}@endif
            </div>
        @endif
    </div>

    <div class="rsvp-body">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($submitted && !session('success'))
            <div class="alert alert-info">
                <i class="fas fa-check-circle me-1"></i> You've already responded. Submit again to update your RSVP.
            </div>
        @endif

        <div class="rsvp-rich">
            @include('common.partials.event-rich-content', ['link' => $link, 'similarEvents' => $similarEvents ?? collect(), 'sameHostEvents' => $sameHostEvents ?? collect(), 'interestCounts' => $interestCounts ?? []])
        </div>

        @if($hasPin)
            <div class="mb-3">
                <div id="ev-map" data-lat="{{ $ics->latitude }}" data-lng="{{ $ics->longitude }}" data-label="{{ $link->title }}"></div>
                <a href="{{ $directionsUrl }}" target="_blank" rel="noopener"
                   class="btn btn-outline-primary btn-sm w-100 mt-2 d-inline-flex align-items-center justify-content-center gap-2">
                    <i class="fas fa-diamond-turn-right"></i> Get directions
                </a>
            </div>
        @endif

        @include('common.partials.rsvp-form-fields', ['link' => $link, 'action' => route('redirect.rsvp.submit', $link->alias), 'sourceTag' => 'event_page'])

        <div class="text-center mt-3 small text-muted">
            <a href="{{ url('/' . $link->alias) }}" class="text-muted text-decoration-none">
                <i class="fas fa-download me-1"></i> Download .ics file
            </a>
        </div>
    </div>
</div>
@if($hasPin)
<script src="{{ asset('js/vendor/leaflet.min.js') }}"></script>
<script>
(function () {
    var el = document.getElementById('ev-map');
    if (!el || !window.L) return;
    var lat = parseFloat(el.dataset.lat), lng = parseFloat(el.dataset.lng);
    if (!isFinite(lat) || !isFinite(lng)) return;
    var map = L.map(el, { center: [lat, lng], zoom: 15, scrollWheelZoom: false });
    var DARK_URL = 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png';
    var LIGHT_URL = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
    var DARK_ATTR = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com">CARTO</a>';
    var LIGHT_ATTR = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
    var isDark = !document.documentElement.classList.contains('light-mode');
    var tiles = L.tileLayer(isDark ? DARK_URL : LIGHT_URL, {
        maxZoom: 19,
        attribution: isDark ? DARK_ATTR : LIGHT_ATTR,
    }).addTo(map);
    window.addEventListener('1inme:theme-change', function (e) {
        var dark = e.detail && e.detail.theme === 'dark';
        tiles.setUrl(dark ? DARK_URL : LIGHT_URL);
        tiles.options.attribution = dark ? DARK_ATTR : LIGHT_ATTR;
        if (map.attributionControl) {
            map.attributionControl.removeAttribution(dark ? LIGHT_ATTR : DARK_ATTR);
            map.attributionControl.addAttribution(dark ? DARK_ATTR : LIGHT_ATTR);
        }
    });
    var icon = L.divIcon({
        className: '',
        html: '<div style="width:30px;height:40px;"><svg viewBox="0 0 34 44" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="evmap-g" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#90acff"/><stop offset="100%" stop-color="#3d6bff"/></linearGradient></defs><path d="M17 0C7.6 0 0 7.5 0 16.7c0 11.7 14.6 25.5 16 26.8.6.6 1.5.6 2 0 1.5-1.3 16-15.1 16-26.8C34 7.5 26.4 0 17 0z" fill="url(#evmap-g)" stroke="rgba(255,255,255,0.85)" stroke-width="1.5"/></svg></div>',
        iconSize: [30, 40],
        iconAnchor: [15, 40],
    });
    L.marker([lat, lng], { icon: icon }).addTo(map);
    setTimeout(function () { map.invalidateSize(); }, 80);
})();
</script>
@endif
</body>
</html>
