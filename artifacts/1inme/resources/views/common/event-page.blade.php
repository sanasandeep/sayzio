@extends('public.layouts.site')

@php
    $ics = $link->icsData;
    $eventCategory = ($link->settings ?? [])['event_category'] ?? '';
    $isOnline = !empty(($link->settings ?? [])['is_online']);
    $rsvpEnabled = $rsvpAvailable ?? !empty(($link->settings ?? [])['rsvp_enabled']);
    $eventCancelled = $link->isEventCancelled();
    $hasTicketTiers = isset($tiers) && $tiers->isNotEmpty();
    $categoryGradient = \App\Modules\User\Support\EventCategories::gradient($eventCategory ?: '');
    $hasPin = !$isOnline && $ics && $ics->latitude !== null && $ics->longitude !== null;
    $directionsUrl = $hasPin
        ? 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($ics->latitude . ',' . $ics->longitude)
        : null;
    $metaDescription = \Illuminate\Support\Str::limit($ics->description ?? $link->title, 180);

    // Guest-local time + add-to-calendar data (Google deep link expects UTC
    // dates in the compact "Ymd\THis\Z" form). $eventTz is the organizer's
    // timezone; the small inline JS below compares it against the viewer's
    // own Intl timezone and only shows the "in your timezone" line when they
    // differ. The .ics download reuses the existing `?ics=1` endpoint, which
    // already emits per-event VEVENTs with a proper DTSTART;TZID (IcsData::toIcs).
    $eventTz = $ics && $ics->timezone ? $ics->timezone : 'UTC';
    $googleCalUrl = null;
    $startIso = null;
    $endIso = null;
    if ($ics && $ics->start_date) {
        $startTz = $ics->start_date->copy()->setTimezone(new \DateTimeZone($eventTz));
        $endTz = ($ics->end_date ?: $ics->start_date->copy()->addHour())->copy()->setTimezone(new \DateTimeZone($eventTz));
        $startIso = $startTz->toIso8601String();
        $endIso = $endTz->toIso8601String();
        $gStart = $startTz->copy()->setTimezone('UTC')->format('Ymd\THis\Z');
        $gEnd = $endTz->copy()->setTimezone('UTC')->format('Ymd\THis\Z');
        $googleCalUrl = 'https://calendar.google.com/calendar/render?' . http_build_query([
            'action'   => 'TEMPLATE',
            'text'     => $link->title,
            'dates'    => $gStart . '/' . $gEnd,
            'ctz'      => $eventTz,
            'details'  => (string) ($ics->description ?? ''),
            'location' => (string) ($ics->location ?? ''),
        ]);
    }

    // Host/organizer card is rendered in the right column below (Task #3731);
    // compute it here so it's available outside event-rich-content, which is
    // told to skip its own copy via `hideHostCard`.
    $host = $link->relationLoaded('user') ? $link->user : $link->user()->first();
    $organizer = $host ? $host->organizerProfile() : null;

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
    .ev-card { background:rgba(255,255,255,0.045); border:1px solid rgba(255,255,255,0.09); border-radius:1.5rem; box-shadow:0 24px 60px -30px rgba(0,0,0,0.65); }
    .ev-card-soft { background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07); border-radius:1.1rem; }
    .ev-accent-bg { background:#3d6bff; }
    .ev-accent-text { color:#8fa8ff; }
    .ev-accent-icon-badge { background:rgba(61,107,255,0.16); border:1px solid rgba(61,107,255,0.3); color:#8fa8ff; }
    .ev-chip { background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.08); color:#fff; }
    .ev-meta-chip { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.09); color:rgba(255,255,255,0.82); border-radius:0.75rem; }
    .ev-hero { background: {{ $categoryGradient }}; }
    .ev-tier { border:1.5px solid rgba(255,255,255,0.10); border-radius:0.9rem; transition:border-color .15s ease, background .15s ease; }
    .ev-tier:has(input:checked) { border-color:#3d6bff; background:rgba(61,107,255,0.10); }
    .ev-tier input:disabled { opacity:.4; }
    .ev-input { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12); color:#e8eaf0; }
    .ev-input:focus { outline:none; border-color:#3d6bff; box-shadow:0 0 0 3px rgba(61,107,255,0.25); }
    .ev-label { color: rgba(255,255,255,0.5); }
    .ev-section-label { color:#8fa8ff; }
    .ev-strong { color:#fff; }
    .ev-muted { color: rgba(255,255,255,0.6); }
    .ev-muted-lite { color: rgba(255,255,255,0.4); }
    .ev-desc { color: rgba(255,255,255,0.7); }
    .ev-price-free { color: #34d399; }
    .ev-cta-btn { box-shadow:0 14px 30px -10px rgba(61,107,255,0.55); transition:transform .15s ease, box-shadow .15s ease, opacity .15s ease; }
    .ev-cta-btn:hover { transform:translateY(-1px); box-shadow:0 18px 36px -10px rgba(61,107,255,0.65); }
    .ev-host-avatar-ring { padding:2.5px; background:linear-gradient(135deg,#3d6bff,#8fa8ff); border-radius:9999px; }
    #ev-map { height:260px; border-radius:0.9rem; }
    footer.ev-footer { color: rgba(255,255,255,0.25); }

    html.light-mode .ev-card { background:#ffffff; border-color:rgba(15,23,42,0.08); box-shadow:0 12px 36px -20px rgba(15,23,42,0.18); }
    html.light-mode .ev-card-soft { background:#f8fafc; border-color:rgba(15,23,42,0.08); }
    html.light-mode .ev-accent-text { color:#3d6bff; }
    html.light-mode .ev-accent-icon-badge { background:rgba(61,107,255,0.1); border-color:rgba(61,107,255,0.25); color:#3d6bff; }
    html.light-mode .ev-chip { background:rgba(15,23,42,0.05); border-color:rgba(15,23,42,0.08); color:#111827; }
    html.light-mode .ev-meta-chip { background:#f8fafc; border-color:rgba(15,23,42,0.09); color:#334155; }
    html.light-mode .ev-tier { border-color:rgba(15,23,42,0.12); }
    html.light-mode .ev-tier:has(input:checked) { border-color:#3d6bff; background:rgba(61,107,255,0.06); }
    html.light-mode .ev-input { background:#f8fafc; border-color:rgba(15,23,42,0.14); color:#111827; }
    html.light-mode .ev-label { color: rgba(15,23,42,0.55); }
    html.light-mode .ev-section-label { color:#3d6bff; }
    html.light-mode .ev-strong { color:#0f172a; }
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

    /* Structural fallbacks for the Bootstrap utility classes the shared
       event partials rely on — this marketing layout is Tailwind-only, so
       without these the recommendation cards collapse (images at natural
       size, no 2-up grid, unstyled buttons). Scoped to .ev-rich; the RSVP
       page loads real Bootstrap and is unaffected. */
    .ev-rich .d-block { display:block; }
    .ev-rich .d-flex { display:flex; }
    .ev-rich .flex-column { flex-direction:column; }
    .ev-rich .align-items-center { align-items:center; }
    .ev-rich .justify-content-between { justify-content:space-between; }
    .ev-rich .flex-grow-1 { flex-grow:1; }
    .ev-rich .flex-shrink-0 { flex-shrink:0; }
    .ev-rich .min-width-0 { min-width:0; }
    .ev-rich .flex-1 { flex:1 1 0%; min-width:0; }
    .ev-rich .gap-2 { gap:.5rem; }
    .ev-rich .gap-3 { gap:.85rem; }
    .ev-rich .text-decoration-none { text-decoration:none; }
    .ev-rich .text-truncate { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .ev-rich .text-uppercase { text-transform:uppercase; }
    .ev-rich .small { font-size:.85rem; }
    .ev-rich .badge { display:inline-block; line-height:1.1; }
    .ev-rich .rounded-2 { border-radius:.5rem; }
    .ev-rich .rounded-3 { border-radius:.75rem; }
    .ev-rich .me-1 { margin-right:.3rem; }
    .ev-rich .ms-1 { margin-left:.3rem; }
    .ev-rich .m-2 { margin:.5rem; }
    .ev-rich .w-100 { width:100%; }
    .ev-rich .position-relative { position:relative; }
    .ev-rich .position-absolute { position:absolute; }
    .ev-rich .top-0 { top:0; }
    .ev-rich .end-0 { right:0; }
    .ev-rich .row { display:flex; flex-wrap:wrap; }
    .ev-rich .row.g-3 { margin:0 -.5rem; }
    .ev-rich .row.g-3 > [class^="col-"] { padding:0 .5rem; margin-bottom:1rem; box-sizing:border-box; }
    .ev-rich .col-6 { width:50%; }
    .ev-rich .col-4 { width:33.3333%; }
    .ev-rich .ratio { position:relative; width:100%; }
    .ev-rich .ratio.ratio-16x9::before { content:""; display:block; padding-top:56.25%; }
    .ev-rich .ratio > img, .ev-rich .ratio > video, .ev-rich .ratio > iframe { position:absolute; inset:0; width:100% !important; height:100% !important; }
    .ev-rich .ev-rec-card-list-thumb img { width:100%; height:100% !important; object-fit:cover; }

    /* Inline RSVP form (shared rsvp-form-fields partial) — Bootstrap form
       fallbacks scoped to .ev-rsvp. Dark theme first, light-mode paired
       below with the other .ev-rich overrides. */
    .ev-rsvp { color:#e8eaf0; }
    .ev-rsvp .mb-1 { margin-bottom:.25rem; }
    .ev-rsvp .mb-3 { margin-bottom:1rem; }
    .ev-rsvp .p-2 { padding:.5rem; }
    .ev-rsvp .py-1 { padding-top:.25rem; padding-bottom:.25rem; }
    .ev-rsvp .py-2 { padding-top:.55rem; padding-bottom:.55rem; }
    .ev-rsvp .flex-wrap { flex-wrap:wrap; }
    .ev-rsvp .gap-1 { gap:.25rem; }
    .ev-rsvp .rounded { border-radius:.5rem; }
    .ev-rsvp .form-label { display:block; font-weight:600; font-size:.8rem; color:rgba(232,234,240,0.7); margin-bottom:.35rem; }
    .ev-rsvp .form-control, .ev-rsvp .form-select {
        display:block; width:100%; box-sizing:border-box; font-size:.875rem; line-height:1.4;
        padding:.55rem .75rem; border-radius:.6rem; border:1px solid rgba(255,255,255,0.14);
        background:rgba(255,255,255,0.05); color:#e8eaf0; outline:none; transition:border-color .15s, box-shadow .15s;
    }
    .ev-rsvp .form-control::placeholder { color:rgba(232,234,240,0.35); }
    .ev-rsvp .form-control:focus, .ev-rsvp .form-select:focus { border-color:rgba(61,107,255,0.6); box-shadow:0 0 0 3px rgba(61,107,255,0.18); }
    .ev-rsvp select.form-select option { color:#111827; }
    .ev-rsvp textarea.form-control { resize:vertical; min-height:3.2rem; }
    .ev-rsvp .response-pill { cursor:pointer; border:2px solid rgba(255,255,255,0.12); border-radius:14px; padding:12px 6px; text-align:center; font-weight:600; transition:all .15s; background:rgba(255,255,255,0.04); color:#e8eaf0; }
    .ev-rsvp .response-pill input { display:none; }
    .ev-rsvp .response-pill.is-yes:has(input:checked) { border-color:#10b981; background:rgba(16,185,129,0.15); color:#34d399; }
    .ev-rsvp .response-pill.is-maybe:has(input:checked) { border-color:#f59e0b; background:rgba(245,158,11,0.15); color:#fbbf24; }
    .ev-rsvp .response-pill.is-no:has(input:checked) { border-color:rgba(255,255,255,0.25); background:rgba(255,255,255,0.06); color:#94a3b8; }
    .ev-rsvp .btn.btn-purple { display:inline-flex; align-items:center; justify-content:center; gap:.4rem; border:none; cursor:pointer; font-size:.875rem; border-radius:.75rem; transition:opacity .15s; background:#3d6bff !important; color:#fff !important; padding-top:.7rem; padding-bottom:.7rem; }
    .ev-rsvp .btn.btn-purple:hover { opacity:.9; }
    .ev-rsvp .alert { border-radius:.6rem; padding:.6rem .8rem; margin-bottom:1rem; font-size:.82rem; }
    .ev-rsvp .alert-danger { background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.25); color:#f87171; }
    .ev-rsvp .alert-warning, .ev-rsvp .badge.bg-warning { background:rgba(245,158,11,0.12); border:1px solid rgba(245,158,11,0.3); color:#fbbf24; }
    .ev-rsvp .badge.bg-warning { display:inline-block; border-radius:.4rem; padding:.15rem .45rem; font-size:.7rem; }
    .ev-rsvp .badge.bg-danger { display:inline-block; border-radius:.4rem; padding:.15rem .45rem; font-size:.7rem; background:rgba(239,68,68,0.15); border:1px solid rgba(239,68,68,0.3); color:#f87171; }
    .ev-rsvp .border.rounded.p-2 { background:rgba(255,255,255,0.03) !important; }
    .ev-rich .btn { display:inline-flex; align-items:center; gap:.3rem; font-size:.8rem; font-weight:600; padding:.45rem .9rem; border-radius:.6rem; border:1px solid transparent; cursor:pointer; background:transparent; transition:background .15s ease, color .15s ease, border-color .15s ease; }
    @media (max-width: 560px) {
        .ev-rich .row.g-3 > .col-6 { width:100%; }
    }

    /* Task #3794 — recommendation cards (event-page-recommendations.blade.php)
       repaint their theme-agnostic surface for this dark page. */
    .ev-rich .ev-rec-heading { color: rgba(232,234,240,0.85); }
    .ev-rich .ev-rec-card { background: rgba(255,255,255,0.035); border-color: rgba(255,255,255,0.10) !important; box-shadow: 0 2px 8px rgba(0,0,0,0.25); }
    .ev-rich a:hover > .ev-rec-card { border-color: rgba(61,107,255,0.45) !important; box-shadow: 0 18px 36px rgba(0,0,0,0.4), 0 0 0 1px rgba(61,107,255,0.25); background: rgba(255,255,255,0.05); }
    .ev-rich .ev-rec-badge-free { background: rgba(52,211,153,0.16) !important; color: #34d399 !important; border-color: rgba(52,211,153,0.35); }
    .ev-rich .ev-rec-badge-paid { background: rgba(255,255,255,0.12) !important; color: #e8eaf0 !important; border-color: rgba(255,255,255,0.18); }

    html.light-mode .ev-rich { color: #111827; }
    html.light-mode .ev-rich .ev-rec-heading { color: rgba(15,23,42,0.75); }
    html.light-mode .ev-rich .ev-rec-card { background: #ffffff; border-color: rgba(15,23,42,0.08) !important; box-shadow: 0 1px 3px rgba(15,23,42,0.06); }
    html.light-mode .ev-rich a:hover > .ev-rec-card { border-color: rgba(61,107,255,0.35) !important; box-shadow: 0 16px 32px rgba(15,23,42,0.14), 0 0 0 1px rgba(61,107,255,0.15); }
    html.light-mode .ev-rich .ev-rec-badge-free { background: rgba(5,150,105,0.1) !important; color: #059669 !important; border-color: rgba(5,150,105,0.25); }
    html.light-mode .ev-rich .ev-rec-badge-paid { background: rgba(15,23,42,0.05) !important; color: #334155 !important; border-color: rgba(15,23,42,0.12); }
    html.light-mode .ev-rich .badge.bg-light { background: rgba(15,23,42,0.05) !important; border-color: rgba(15,23,42,0.1) !important; color: #475569 !important; }
    html.light-mode .ev-rich .text-dark { color: #111827 !important; }
    html.light-mode .ev-rich .text-muted { color: rgba(15,23,42,0.55) !important; }
    html.light-mode .ev-rich .border { border-color: rgba(15,23,42,0.1) !important; }
    html.light-mode .ev-rich a.border, html.light-mode .ev-rich a.border:hover { background: rgba(15,23,42,0.02); }
    html.light-mode .ev-rich a.border:hover { background: rgba(15,23,42,0.05); border-color: rgba(61,107,255,0.4) !important; }
    html.light-mode .ev-rich .btn-outline-secondary { color: rgba(15,23,42,0.65); border-color: rgba(15,23,42,0.18); }
    html.light-mode .ev-rich .btn-outline-secondary:hover { background: rgba(15,23,42,0.06); color:#111827; }
    html.light-mode .ev-rich .btn-outline-success { color:#059669; border-color: rgba(5,150,105,0.5); }
    html.light-mode .ev-rich .btn-outline-success:hover { background: rgba(5,150,105,0.1); color:#047857; }

    /* Light-mode pairing for the inline RSVP form */
    html.light-mode .ev-rsvp { color:#111827; }
    html.light-mode .ev-rsvp .form-label { color:rgba(15,23,42,0.6); }
    html.light-mode .ev-rsvp .form-control, html.light-mode .ev-rsvp .form-select { background:#f8fafc; border-color:rgba(15,23,42,0.14); color:#111827; }
    html.light-mode .ev-rsvp .form-control::placeholder { color:rgba(15,23,42,0.35); }
    html.light-mode .ev-rsvp .form-control:focus, html.light-mode .ev-rsvp .form-select:focus { border-color:rgba(61,107,255,0.6); box-shadow:0 0 0 3px rgba(61,107,255,0.15); background:#fff; }
    html.light-mode .ev-rsvp .response-pill { border-color:#e5e7eb; background:#fff; color:#111827; }
    html.light-mode .ev-rsvp .response-pill.is-yes:has(input:checked) { border-color:#10b981; background:#ecfdf5; color:#047857; }
    html.light-mode .ev-rsvp .response-pill.is-maybe:has(input:checked) { border-color:#f59e0b; background:#fffbeb; color:#b45309; }
    html.light-mode .ev-rsvp .response-pill.is-no:has(input:checked) { border-color:#9ca3af; background:#f3f4f6; color:#374151; }
    html.light-mode .ev-rsvp .alert-danger { background:rgba(239,68,68,0.06); border-color:rgba(239,68,68,0.25); color:#b91c1c; }
    html.light-mode .ev-rsvp .alert-warning, html.light-mode .ev-rsvp .badge.bg-warning { background:#fffbeb; border-color:rgba(245,158,11,0.35); color:#b45309; }
    html.light-mode .ev-rsvp .badge.bg-danger { background:rgba(239,68,68,0.08); border-color:rgba(239,68,68,0.3); color:#b91c1c; }
    html.light-mode .ev-rsvp .border.rounded.p-2 { background:#fafafa !important; }

    /* Light-mode overrides for the "10x your connections" tips and the
       "Perfect pairings" cross-promo — both partials bake their colors as
       inline styles for the dark theme they're included with here, so
       override with !important instead of restyling the shared partials
       (which are also reused elsewhere with their own theme prop). This
       mirrors the .ev-rich pattern above and also applies live when the
       theme is toggled client-side without a reload (Task #3731). */
    html.light-mode .ev-connection-tips { color: #111827 !important; }
    html.light-mode .ev-connection-tips > div:first-child > span:first-child { color: #3d6bff !important; }
    html.light-mode .ev-connection-tips > div:first-child > p { color: rgba(17,24,39,.62) !important; }
    html.light-mode .ev-connection-tip-card { background: linear-gradient(180deg, rgba(61,107,255,.05), rgba(255,255,255,0)) !important; border-color: rgba(61,107,255,.16) !important; }
    html.light-mode .ev-connection-tip-card > span:first-child { background: rgba(61,107,255,.1) !important; }
    html.light-mode .ev-connection-tip-card > span:first-child i { color: #3d6bff !important; }
    html.light-mode .ev-connection-tip-card > span:nth-child(3) { color: rgba(17,24,39,.62) !important; }
    html.light-mode .ev-connection-tip-card > span:last-child { color: #3d6bff !important; }

    html.light-mode .ltp-pairings { color: #111827 !important; }
    html.light-mode .ltp-pairings > div > a { background: #ffffff !important; border-color: rgba(0,0,0,.08) !important; }
    html.light-mode .ltp-pairings > div > a > span:first-child { background: rgba(61,107,255,.1) !important; }
    html.light-mode .ltp-pairings > div > a > span:first-child i { color: #3d6bff !important; }
    html.light-mode .ltp-pairings > div > a > span:last-child > span:nth-child(2) { color: rgba(17,24,39,.62) !important; }
    html.light-mode .ltp-pairings > div > a > span:last-child > span:nth-child(3) { color: #3d6bff !important; }
    html.light-mode .ltp-pairings > p { color: rgba(17,24,39,.62) !important; }
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

        @if($eventCancelled)
            <div class="mb-5 px-4 py-4 rounded-xl text-sm font-medium flex items-start gap-3"
                 style="background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.35); color: #ef4444;">
                <i class="fas fa-ban mt-0.5"></i>
                <div>
                    <div class="font-bold text-base">This event has been cancelled</div>
                    <div class="mt-1" style="color: rgba(239,68,68,0.9);">
                        The organizer has called off this event. RSVPs and ticket sales are closed.
                        @if($hasTicketTiers)
                            If you purchased a ticket, please contact the organizer about a refund.
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="ev-card overflow-hidden">
            <div class="p-6 sm:p-8 lg:p-10">
                <div class="grid lg:grid-cols-3 gap-8 lg:gap-10">
                    {{-- Main content column --}}
                    <div class="lg:col-span-2">
                        {{-- Task #3800: cover image + core event details (title/date/
                             location/description) sit side-by-side in this inner grid,
                             stacking on small screens. --}}
                        <div class="grid sm:grid-cols-2 gap-5 sm:gap-6 items-start">
                            <div class="relative rounded-2xl overflow-hidden">
                                @if($ics && $ics->cover_image_url)
                                    <img src="{{ $ics->cover_image_url }}" alt="{{ $link->title }}" class="w-full h-56 sm:h-full sm:min-h-[16rem] object-cover">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent"></div>
                                @else
                                    <img src="{{ asset('images/events/event-cover-placeholder.svg') }}" alt="{{ $link->title }}" class="w-full h-48 sm:h-full sm:min-h-[16rem] object-cover">
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

                            <div>
                                <h1 class="ev-title text-2xl sm:text-3xl lg:text-4xl font-extrabold leading-tight tracking-tight">{{ $link->title }}</h1>

                                <div class="mt-4 flex flex-wrap gap-2">
                                    @if($ics && $ics->start_date)
                                        <div class="ev-meta-chip inline-flex items-center gap-2 text-sm font-medium px-3 py-1.5">
                                            <i class="far fa-clock ev-accent-text"></i>
                                            {{ $ics->start_date->setTimezone(new \DateTimeZone($eventTz))->format('D, M j Y · g:i A') }}
                                        </div>
                                        {{-- Guest-local time (Task): rendered client-side from the
                                             ISO8601 start emitted below; hidden entirely when the
                                             viewer's timezone matches the event timezone. --}}
                                        <div id="ev-local-time" class="ev-meta-chip inline-flex items-center gap-2 text-sm font-medium px-3 py-1.5"
                                             data-start="{{ $startIso }}" data-event-tz="{{ $eventTz }}" hidden>
                                            <i class="far fa-user-clock ev-accent-text"></i>
                                            <span data-local-label></span>
                                        </div>
                                    @endif
                                    @if($ics && $ics->location)
                                        <div class="ev-meta-chip inline-flex items-center gap-2 text-sm font-medium px-3 py-1.5">
                                            <i class="fas fa-location-dot ev-accent-text"></i> {{ $ics->location }}
                                        </div>
                                    @endif
                                </div>

                                @if($ics && $ics->description)
                                    <p class="mt-4 text-sm ev-desc whitespace-pre-line leading-relaxed">{{ $ics->description }}</p>
                                @endif
                            </div>
                        </div>

                        {{-- Cover/gallery/info sections, hashtags, Interested widget, similar/host events.
                             The host/organizer card is rendered separately in the right column
                             below, so it isn't duplicated here (Task #3731). --}}
                        <div class="ev-rich mt-5">
                            @include('common.partials.event-rich-content', ['link' => $link, 'similarEvents' => $similarEvents ?? collect(), 'sameHostEvents' => $sameHostEvents ?? collect(), 'interestCounts' => $interestCounts ?? [], 'hideHostCard' => true])
                        </div>
                    </div>

                    {{-- Sticky CTA column --}}
                    <div class="lg:col-span-1">
                        <div class="lg:sticky lg:top-24">
                            @if($eventCancelled)
                                <div class="ev-card p-5 text-center">
                                    <span class="ev-accent-icon-badge w-10 h-10 rounded-lg inline-flex items-center justify-center mb-3" style="background: rgba(239,68,68,0.16); border-color: rgba(239,68,68,0.3); color:#ef4444;">
                                        <i class="fas fa-ban"></i>
                                    </span>
                                    <p class="text-sm ev-strong font-semibold">Event cancelled</p>
                                    <p class="text-xs ev-muted mt-1">RSVPs and ticket sales are closed for this event.</p>
                                </div>
                            @elseif($hasTicketTiers)
                                <form method="POST" action="{{ route('redirect.event.buy', $link->alias) }}" id="ticket-form" class="ev-card p-5">
                                    @csrf
                                    <div class="flex items-center gap-2.5 mb-4">
                                        <span class="ev-accent-icon-badge w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"><i class="fas fa-ticket-alt text-sm"></i></span>
                                        <h2 class="text-sm font-bold uppercase tracking-wide ev-section-label">Get tickets</h2>
                                    </div>
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

                                    <button type="submit" class="ev-accent-bg ev-cta-btn w-full mt-4 py-3 rounded-xl text-sm font-bold text-white hover:opacity-90 transition">
                                        <i class="fas fa-ticket-alt mr-1.5"></i> Get tickets
                                    </button>
                                </form>
                            @elseif($rsvpEnabled)
                                <div class="ev-card p-5">
                                    <div class="flex items-center gap-2.5 mb-4">
                                        <span class="ev-accent-icon-badge w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"><i class="fas fa-calendar-check text-sm"></i></span>
                                        <h2 class="text-sm font-bold uppercase tracking-wide ev-section-label">RSVP</h2>
                                    </div>
                                    @if(session()->get('rsvp_submitted_' . $link->id) && !session('success'))
                                        <div class="mb-3 px-3 py-2 rounded-lg text-xs font-medium" style="background: rgba(61,107,255,0.08); border: 1px solid rgba(61,107,255,0.2); color: #6b93ff;">
                                            <i class="fas fa-check-circle mr-1"></i> You've already responded. Submit again to update your RSVP.
                                        </div>
                                    @endif
                                    {{-- Same shared form partial the standalone /rsvp page uses,
                                         embedded inline (Task: RSVP on the event page itself).
                                         .ev-rsvp supplies Tailwind-layout fallbacks for the
                                         partial's Bootstrap form classes; never restyle the
                                         partial itself — the RSVP page still uses it too. --}}
                                    <div class="ev-rich ev-rsvp">
                                        @include('common.partials.rsvp-form-fields', ['link' => $link, 'action' => route('redirect.rsvp.submit', $link->alias), 'sourceTag' => 'event_page'])
                                    </div>
                                </div>
                            @else
                                <div class="ev-card p-5 text-center">
                                    <p class="text-sm ev-muted-lite py-2">RSVPs are closed for this event.</p>
                                </div>
                            @endif

                            @if($ics && $ics->start_date)
                                <div class="flex flex-wrap items-center justify-center gap-3 mt-4 text-sm">
                                    @if($googleCalUrl)
                                        <a href="{{ $googleCalUrl }}" target="_blank" rel="noopener"
                                           class="inline-flex items-center gap-1.5 ev-chip px-4 py-2 rounded-xl hover:opacity-80 transition">
                                            <i class="fab fa-google"></i> Google Calendar
                                        </a>
                                    @endif
                                    <a href="{{ url('/' . $link->alias . '?ics=1') }}"
                                       class="inline-flex items-center gap-1.5 ev-chip px-4 py-2 rounded-xl hover:opacity-80 transition">
                                        <i class="fas fa-calendar-plus"></i> .ics download
                                    </a>
                                </div>
                            @endif
                            <div class="flex flex-wrap items-center justify-center gap-3 mt-4 text-sm">
                                <a href="{{ auth('web')->check() ? route('user.links.create') : (route('user.login') . '?redirect=' . urlencode(route('user.links.create'))) }}"
                                   class="inline-flex items-center gap-1.5 ev-chip px-4 py-2 rounded-xl hover:opacity-80 transition">
                                    <i class="fas fa-plus"></i> Create your own event
                                </a>
                            </div>

                            @if($host)
                                <div class="ev-rich mt-4">
                                    <div class="flex items-center gap-2 mb-2 px-1">
                                        <i class="fas fa-user-tie ev-accent-text text-xs"></i>
                                        <span class="text-[11px] font-bold uppercase tracking-wide ev-section-label">Organizer</span>
                                    </div>
                                    @include('common.partials.event-host-card', ['host' => $host, 'organizer' => $organizer])
                                </div>
                            @endif

                            @if($hasPin)
                                <div class="ev-card p-4 mt-4">
                                    <div class="flex items-center gap-2 mb-3 px-1">
                                        <i class="fas fa-location-dot ev-accent-text text-xs"></i>
                                        <span class="text-[11px] font-bold uppercase tracking-wide ev-section-label">Location</span>
                                    </div>
                                    <div id="ev-map" data-lat="{{ $ics->latitude }}" data-lng="{{ $ics->longitude }}" data-label="{{ $link->title }}"></div>
                                    <a href="{{ $directionsUrl }}" target="_blank" rel="noopener"
                                       class="mt-3 inline-flex items-center justify-center gap-2 w-full ev-chip px-4 py-2.5 rounded-xl hover:opacity-80 font-medium transition">
                                        <i class="fas fa-diamond-turn-right ev-accent-text"></i> Get directions
                                    </a>
                                </div>
                            @endif

                            @auth('web')
                                {{-- Task #5052 — "My swaps": the signed-in viewer's own
                                     pending/accepted contact-swap requests at this event,
                                     with a Withdraw button on pending ones they sent. --}}
                                <div class="ev-card p-4 mt-4" id="ev-my-swaps" hidden
                                     data-list-url="{{ route('user.event-swaps.index', $link->alias) }}"
                                     data-cancel-url="{{ url('/user/contact-exchanges') }}"
                                     data-csrf="{{ csrf_token() }}">
                                    <div class="flex items-center gap-2 mb-3 px-1">
                                        <i class="fas fa-right-left ev-accent-text text-xs"></i>
                                        <span class="text-[11px] font-bold uppercase tracking-wide ev-section-label">My swaps</span>
                                    </div>
                                    <div id="ev-my-swaps-list" class="space-y-2"></div>
                                </div>
                            @endauth
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @include('common.partials.event-connection-tips', ['compact' => true, 'theme' => 'dark', 'surface' => 'event'])

        @include('common.partials.link-type-pairings', ['pairingType' => 'ics', 'theme' => 'dark'])

        <footer class="ev-footer text-center mt-8 text-xs">
            Powered by <a href="{{ url('/') }}" class="ev-accent-text hover:underline">{{ config('app.name') }}</a>
        </footer>
    </div>
</section>
@endsection

@push('scripts')
<script>
// Guest-local event time: render the start in the viewer's own timezone via
// Intl.DateTimeFormat, but only when it differs from the organizer's timezone
// (otherwise the organizer line already covers it). Vanilla JS to match this
// page's conventions; theme-agnostic since it reuses the .ev-meta-chip styling.
(function () {
    var el = document.getElementById('ev-local-time');
    if (!el) return;
    var iso = el.dataset.start;
    var eventTz = el.dataset.eventTz;
    if (!iso) return;
    try {
        var viewerTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (viewerTz && eventTz && viewerTz === eventTz) return;
        var d = new Date(iso);
        if (isNaN(d.getTime())) return;
        var fmt = new Intl.DateTimeFormat(undefined, {
            weekday: 'short', month: 'short', day: 'numeric', year: 'numeric',
            hour: 'numeric', minute: '2-digit', timeZoneName: 'short',
        });
        el.querySelector('[data-local-label]').textContent = 'Your time: ' + fmt.format(d);
        el.hidden = false;
    } catch (e) { /* Intl unsupported — leave the line hidden */ }
})();
</script>
@endpush

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
    });
})();
</script>
@endpush
@endif

@auth('web')
@push('scripts')
<script>
// Task #5052 — "My swaps" panel: fetch the viewer's pending/accepted swap
// requests for this event and allow withdrawing pending ones they sent.
// The card stays hidden until at least one swap exists.
(function () {
    var card = document.getElementById('ev-my-swaps');
    if (!card) return;
    var listEl = document.getElementById('ev-my-swaps-list');
    var listUrl = card.dataset.listUrl;
    var cancelBase = card.dataset.cancelUrl;
    var csrf = card.dataset.csrf;

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function fmtDate(iso) {
        if (!iso) return '';
        try {
            return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
        } catch (e) { return ''; }
    }

    function render(items) {
        if (!items.length) { card.hidden = true; return; }
        card.hidden = false;
        listEl.innerHTML = items.map(function (it) {
            var o = it.other || {};
            var name = esc(o.name || 'Attendee');
            var avatar = o.avatar_url
                ? '<img src="' + esc(o.avatar_url) + '" alt="" class="w-8 h-8 rounded-full object-cover flex-shrink-0">'
                : '<div class="w-8 h-8 rounded-full ev-chip flex items-center justify-center text-xs font-bold flex-shrink-0">' + esc((o.name || '?')[0]).toUpperCase() + '</div>';
            var status;
            if (it.status === 'accepted') {
                status = '<span class="text-[11px] ev-accent-text font-semibold"><i class="fas fa-check mr-1"></i>Accepted' + (it.accepted_at ? ' ' + esc(fmtDate(it.accepted_at)) : '') + '</span>';
            } else {
                status = '<span class="text-[11px] ev-muted-lite">' + (it.sent_by_me ? 'Pending (sent by you)' : 'Pending (awaiting you)') + '</span>';
            }
            var action = it.can_cancel
                ? '<button type="button" data-cancel-id="' + it.exchange_id + '" class="ev-chip text-[11px] font-semibold px-2.5 py-1.5 rounded-lg hover:opacity-80 transition flex-shrink-0">Withdraw</button>'
                : '';
            return '<div class="ev-card-soft flex items-center gap-2.5 p-2.5" data-swap-row="' + it.exchange_id + '">'
                + avatar
                + '<div class="min-w-0 flex-1"><div class="text-sm font-semibold ev-strong truncate">' + name + (o.handle ? ' <span class="ev-muted-lite font-normal">@' + esc(o.handle) + '</span>' : '') + '</div>' + status + '</div>'
                + action
                + '</div>';
        }).join('');
    }

    listEl.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-cancel-id]');
        if (!btn) return;
        var id = btn.getAttribute('data-cancel-id');
        btn.disabled = true;
        fetch(cancelBase + '/' + id + '/cancel', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            credentials: 'same-origin',
        }).then(function (r) {
            if (!r.ok) throw new Error('cancel failed');
            var row = listEl.querySelector('[data-swap-row="' + id + '"]');
            if (row) row.remove();
            if (!listEl.children.length) card.hidden = true;
        }).catch(function () {
            btn.disabled = false;
            alert('Could not withdraw the request. Please try again.');
        });
    });

    fetch(listUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : { items: [] }; })
        .then(function (data) { render((data && data.items) || []); })
        .catch(function () { /* leave hidden */ });
})();
</script>
@endpush
@endauth
