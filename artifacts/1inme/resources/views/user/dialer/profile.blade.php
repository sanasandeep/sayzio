@extends('user.layouts.app')

@section('title', 'Dialer profile')

@section('content')
@php
    $e164 = $payload['number_e164'] ?? null;
    $bioUrl = $payload['biolink']['url'] ?? null;
    $displayName = $contact?->nameForDisplay() ?? ($matchedUser?->name ?? 'Unknown number');
    $myHandle = optional(auth()->user())->publicHandle();
    $myBioUrl = $myHandle ? url('/' . $myHandle) : null;
@endphp
<div class="max-w-3xl mx-auto" id="dialer-profile"
     data-flag-url="{{ route('user.dialer.flag') }}"
     data-fav-url="{{ route('user.dialer.favorites.store') }}"
     data-log-url="{{ route('user.dialer.log') }}"
     data-callback-url="{{ route('user.dialer.callback.set') }}"
     data-number="{{ $number }}"
     data-number-e164="{{ $e164 }}"
     data-contact-id="{{ $contact?->id }}">
    <a href="{{ route('user.dialer.index') }}" class="inline-flex items-center gap-1 text-xs mb-4" style="color:var(--text-muted);">
        <i class="fas fa-arrow-left"></i> Back to dialer
    </a>

    <div class="card-premium p-6 mb-4">
        <div class="flex items-start gap-4">
            <div class="w-16 h-16 rounded-full flex items-center justify-center text-xl font-bold text-white flex-shrink-0" style="background:linear-gradient(135deg,#7c3aed,#ec4899);">
                @if($contact && $contact->photoUrl())
                    <img src="{{ $contact->photoUrl() }}" class="w-full h-full rounded-full object-cover">
                @elseif($contact)
                    {{ $contact->initials() }}
                @else
                    <i class="fas fa-user"></i>
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <h1 class="text-xl font-bold flex items-center gap-2 flex-wrap" style="color:var(--text-primary);">
                    {{ $displayName }}
                    <span id="badge-spam" class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase @if(!$payload['is_spam']) hidden @endif" style="background:rgba(239,68,68,.15);color:#ef4444;">Spam</span>
                    <span id="badge-blocked" class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase @if(!$payload['is_blocked']) hidden @endif" style="background:rgba(107,114,128,.2);color:#9ca3af;">Blocked</span>
                </h1>
                <div class="text-sm font-mono" style="color:var(--text-muted);">{{ $number }}</div>
                @if(!$contact && $matchedUser)
                    <p class="text-xs mt-1" style="color:#f472b6;"><i class="fas fa-id-badge mr-1"></i> Identified via 1INME biolink</p>
                @elseif($contact && $contact->organization)
                    <p class="text-xs mt-1" style="color:var(--text-muted);">{{ $contact->organization }}</p>
                @endif
            </div>
            <div class="flex flex-col gap-2 flex-shrink-0">
                @if($number)
                    <a href="tel:{{ $number }}" class="px-3 py-1.5 rounded-lg text-xs font-medium text-center" style="background:rgba(34,197,94,.12);color:#22c55e;border:1px solid rgba(34,197,94,.20)">
                        <i class="fas fa-phone mr-1"></i> Call
                    </a>
                @endif
                @if($contact && $contact->emails->isNotEmpty())
                    <a href="mailto:{{ $contact->emails->first()->value }}" class="px-3 py-1.5 rounded-lg text-xs font-medium text-center" style="background:rgba(59,130,246,.12);color:#3b82f6;border:1px solid rgba(59,130,246,.20)">
                        <i class="fas fa-envelope mr-1"></i> Email
                    </a>
                @endif
                <a href="{{ $payload['vcard_url'] }}" class="px-3 py-1.5 rounded-lg text-xs font-medium text-center" style="background:rgba(168,85,247,.12);color:#a855f7;border:1px solid rgba(168,85,247,.20)">
                    <i class="fas fa-address-card mr-1"></i> vCard
                </a>
                @if($contact)
                    <a href="{{ route('user.contacts.edit', $contact) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium text-center" style="background:rgba(255,255,255,.06);color:var(--text-primary);border:1px solid rgba(255,255,255,.10)">
                        <i class="fas fa-pen mr-1"></i> Edit
                    </a>
                    <a href="{{ route('user.contacts.show', $contact) }}" class="px-3 py-1.5 rounded-lg text-[11px] font-medium text-center" style="color:var(--text-muted);">
                        Open contact
                    </a>
                @else
                    <a href="{{ route('user.contacts.create', ['phone' => $number]) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium text-center" style="background:rgba(255,255,255,.06);color:var(--text-primary);border:1px solid rgba(255,255,255,.10)">
                        <i class="fas fa-user-plus mr-1"></i> Save
                    </a>
                @endif
            </div>
        </div>

        {{-- Consistent quick-action bar --}}
        <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 mt-5">
            @if($number)
                <a href="tel:{{ $number }}" onclick="logQuick('called')" class="qa-btn" style="background:rgba(34,197,94,.12);color:#22c55e;border:1px solid rgba(34,197,94,.20)">
                    <i class="fas fa-phone"></i><span>Call</span>
                </a>
                <a href="sms:{{ $number }}" onclick="logQuick('messaged')" class="qa-btn" style="background:rgba(59,130,246,.12);color:#3b82f6;border:1px solid rgba(59,130,246,.20)">
                    <i class="fas fa-comment-sms"></i><span>SMS</span>
                </a>
            @endif
            @if($contact && $contact->emails->isNotEmpty())
                <a href="mailto:{{ $contact->emails->first()->value }}" class="qa-btn" style="background:rgba(99,102,241,.12);color:#818cf8;border:1px solid rgba(99,102,241,.20)">
                    <i class="fas fa-envelope"></i><span>Email</span>
                </a>
            @endif
            <button type="button" onclick="shareMyBiolink()" class="qa-btn" style="background:rgba(236,72,153,.12);color:#f472b6;border:1px solid rgba(236,72,153,.20)">
                <i class="fas fa-share-nodes"></i><span>Share bio</span>
            </button>
            <button type="button" onclick="copyNumber()" class="qa-btn" style="background:rgba(255,255,255,.06);color:var(--text-primary);border:1px solid rgba(255,255,255,.10)">
                <i class="fas fa-copy"></i><span>Copy</span>
            </button>
            @if($contact)
                <a href="{{ route('user.contacts.edit', $contact) }}" class="qa-btn" style="background:rgba(255,255,255,.06);color:var(--text-primary);border:1px solid rgba(255,255,255,.10)">
                    <i class="fas fa-pen"></i><span>Edit</span>
                </a>
            @else
                <a href="{{ route('user.contacts.create', ['phone' => $number]) }}" class="qa-btn" style="background:rgba(255,255,255,.06);color:var(--text-primary);border:1px solid rgba(255,255,255,.10)">
                    <i class="fas fa-user-plus"></i><span>Save</span>
                </a>
            @endif
        </div>

        {{-- Favorite + spam/block toggles --}}
        @if($e164)
        <div class="flex flex-wrap items-center gap-2 mt-3 pt-3" style="border-top:1px solid rgba(255,255,255,.06);">
            <button type="button" id="fav-toggle" onclick="addFavorite()" class="text-[11px] px-2.5 py-1.5 rounded-lg font-medium" style="background:rgba(251,191,36,.12);color:#fbbf24;border:1px solid rgba(251,191,36,.20)">
                <i class="fas fa-star mr-1"></i> <span>{{ $isFavorite ? 'In speed dial' : 'Add to speed dial' }}</span>
            </button>
            <button type="button" id="spam-toggle" onclick="toggleFlag('is_spam')" class="text-[11px] px-2.5 py-1.5 rounded-lg font-medium" style="background:rgba(239,68,68,.10);color:#ef4444;border:1px solid rgba(239,68,68,.20)">
                <i class="fas fa-triangle-exclamation mr-1"></i> <span>{{ $payload['is_spam'] ? 'Unmark spam' : 'Mark spam' }}</span>
            </button>
            <button type="button" id="block-toggle" onclick="toggleFlag('is_blocked')" class="text-[11px] px-2.5 py-1.5 rounded-lg font-medium" style="background:rgba(107,114,128,.15);color:#9ca3af;border:1px solid rgba(107,114,128,.25)">
                <i class="fas fa-ban mr-1"></i> <span>{{ $payload['is_blocked'] ? 'Unblock' : 'Block' }}</span>
            </button>
        </div>
        @endif
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
            <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #ef4444;">
            <i class="fas fa-circle-exclamation mr-1.5"></i> {{ session('error') }}
        </div>
    @endif

    @if($payload['biolink'])
        <div class="card-premium p-6" style="border:1px solid rgba(236,72,153,.30);">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-[10px] font-bold uppercase tracking-wider mb-2" style="color:#f472b6;"><i class="fas fa-link mr-1"></i> 1INME biolink</div>
                    <h2 class="text-lg font-bold mb-1" style="color:var(--text-primary);">{{ $payload['biolink']['name'] }}</h2>
                    <p class="text-sm mb-4" style="color:var(--text-muted);">&commat;{{ $payload['biolink']['handle'] }}</p>
                    @if($payload['biolink']['url'])
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ $payload['biolink']['url'] }}" target="_blank" class="inline-block px-4 py-2 rounded-xl text-sm font-semibold text-white" style="background:linear-gradient(135deg,#7c3aed,#ec4899);">
                                Open biolink <i class="fas fa-external-link-alt ml-1 text-xs"></i>
                            </a>
                            @if($number)
                                <a href="sms:{{ $number }}?body={{ rawurlencode(($contact?->nameForDisplay() ? 'Hey ' . $contact->nameForDisplay() . ', ' : 'Hey, ') . "here's my 1INME page: " . $payload['biolink']['url']) }}"
                                   class="inline-block px-4 py-2 rounded-xl text-sm font-semibold"
                                   style="background:rgba(34,197,94,.12);color:#22c55e;border:1px solid rgba(34,197,94,.20)">
                                    <i class="fas fa-comment-sms mr-1"></i> Text biolink
                                </a>
                                @if($contact)
                                    <form method="POST" action="{{ route('user.contacts.biolink.sms', $contact) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="to" value="{{ $number }}">
                                        <button type="submit" class="px-4 py-2 rounded-xl text-sm font-semibold"
                                                style="background:rgba(124,58,237,.12);color:#a78bfa;border:1px solid rgba(124,58,237,.20)"
                                                title="Send via your configured SMS gateway (desktop fallback)">
                                            <i class="fas fa-paper-plane mr-1"></i> Send via gateway
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    @else
                        <p class="text-xs" style="color:var(--text-faint);">This user hasn't published a biolink yet.</p>
                    @endif
                </div>
                @if($contact)
                    <form method="POST" action="{{ route('user.contacts.biolink.detach', $contact) }}" onsubmit="return window.themedConfirmSubmit(this, {title: 'Detach this biolink?', message: 'It will not auto-attach again on future syncs.', confirmText: 'Detach', confirmIcon: 'fa-link-slash', iconClass: 'fa-link-slash'})">
                        @csrf
                        <button class="px-3 py-1.5 rounded-lg text-xs font-medium" style="background:rgba(239,68,68,.10);color:#ef4444;border:1px solid rgba(239,68,68,.20)">
                            Detach
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @else
        <div class="card-premium p-6 text-center">
            <i class="fas fa-circle-info text-2xl mb-2" style="color:var(--text-faint);"></i>
            <p class="text-sm mb-3" style="color:var(--text-muted);">No 1INME biolink found for this number.</p>
            @if($contact)
                <form method="POST" action="{{ route('user.contacts.biolink.attach', $contact) }}">
                    @csrf
                    <button class="text-xs font-medium" style="color:#a78bfa;">
                        <i class="fas fa-link mr-1"></i> Re-check / re-attach
                    </button>
                </form>
            @endif
        </div>
    @endif

    {{-- Log a call (mini-CRM) --}}
    @if($e164)
    <div class="card-premium p-5 mt-4">
        <h3 class="text-[10px] font-bold uppercase tracking-wider mb-3" style="color:var(--text-faint);">Log this call</h3>
        <div class="flex flex-wrap gap-2 mb-3">
            @foreach(['called'=>'Called','messaged'=>'Messaged','no_answer'=>'No answer','voicemail'=>'Voicemail','wrong_number'=>'Wrong number','completed'=>'Completed'] as $val=>$label)
                <button type="button" class="outcome-chip text-[11px] px-2.5 py-1 rounded-full" data-outcome="{{ $val }}"
                        style="background:rgba(255,255,255,.05);color:var(--text-muted);border:1px solid rgba(255,255,255,.08)">{{ $label }}</button>
            @endforeach
    {{-- Reach via — multi-app calling / messaging chooser --}}
    @php
        $iconFor = fn ($t) => match ($t) {
            'phone' => 'fa-phone', 'sms' => 'fa-comment-sms',
            'whatsapp' => 'fa-brands fa-whatsapp', 'whatsapp_channel' => 'fa-brands fa-whatsapp',
            'telegram' => 'fa-brands fa-telegram', 'facetime_audio' => 'fa-video',
            'facetime_video' => 'fa-video', 'email' => 'fa-envelope',
            default => 'fa-arrow-up-right-from-square',
        };
        $allChannels = array_merge($payload['channels'] ?? [], $payload['manual']['channels'] ?? []);
    @endphp
    @if(!empty($allChannels))
        <div class="card-premium p-5 mt-4">
            <h3 class="text-[10px] font-bold uppercase tracking-wider mb-3" style="color:var(--text-faint);">Reach via</h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                @foreach($allChannels as $ch)
                    <a href="{{ $ch['url'] }}" @if(!\Illuminate\Support\Str::startsWith($ch['url'], ['tel:','sms:','mailto:','facetime'])) target="_blank" rel="noopener" @endif
                       class="flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm font-medium" style="background:rgba(255,255,255,.05);color:var(--text-primary);border:1px solid rgba(255,255,255,.08);">
                        <i class="fas {{ $iconFor($ch['type']) }}" style="color:#a78bfa;width:18px;text-align:center;"></i>
                        <span class="min-w-0">
                            <span class="block truncate">{{ $ch['label'] }}</span>
                            <span class="block text-[11px] truncate" style="color:var(--text-faint);">{{ $ch['value'] }}</span>
                        </span>
                        @if(($ch['source'] ?? '') === 'manual')
                            <span class="ml-auto text-[9px] px-1.5 py-0.5 rounded" style="background:rgba(168,85,247,.15);color:#c084fc;">manual</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Socials (auto-pulled + manual) --}}
    @php $allSocials = array_merge($payload['socials'] ?? [], $payload['manual']['socials'] ?? []); @endphp
    @if(!empty($allSocials))
        <div class="card-premium p-5 mt-4">
            <h3 class="text-[10px] font-bold uppercase tracking-wider mb-3" style="color:var(--text-faint);">Socials</h3>
            <div class="flex flex-wrap gap-2">
                @foreach($allSocials as $s)
                    <a href="{{ $s['url'] }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium" style="background:rgba(255,255,255,.05);color:var(--text-primary);border:1px solid rgba(255,255,255,.08);">
                        <i class="fas fa-globe" style="color:#60a5fa;"></i>
                        {{ $s['label'] }}
                        @if(($s['source'] ?? '') === 'manual')
                            <span class="text-[9px] px-1 py-0.5 rounded" style="background:rgba(168,85,247,.15);color:#c084fc;">manual</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Locations (auto-pulled + manual) --}}
    @php
        $allLocations = $payload['locations'] ?? [];
        if (!empty($payload['manual']['location'])) $allLocations[] = $payload['manual']['location'];
        $hasLocMap = collect($allLocations)->contains(fn ($l) => is_numeric($l['lat'] ?? null) && is_numeric($l['lng'] ?? null));
    @endphp

    {{-- Leaflet assets — shared by the read-only location previews and the manual map picker below. --}}
    @if($hasLocMap || $contact)
        <link rel="stylesheet" href="{{ asset('css/vendor/leaflet.min.css') }}" />
        <script src="{{ asset('js/vendor/leaflet.min.js') }}"></script>
        <style>
            .dialer-loc-map .leaflet-container, .dialer-loc-thumb .leaflet-container { background:#1e2330 !important; font-family:'Space Grotesk', sans-serif; }
            html.light-mode .dialer-loc-map .leaflet-container, html.light-mode .dialer-loc-thumb .leaflet-container { background:#e6e9f0 !important; }
            .dialer-loc-map .leaflet-control-attribution, .dialer-loc-thumb .leaflet-control-attribution { background:rgba(30,35,48,0.85) !important; color:#9ca3af !important; }
            .dialer-loc-map .leaflet-control-attribution a, .dialer-loc-thumb .leaflet-control-attribution a { color:#a78bfa !important; }
            .dialer-loc-map .leaflet-control-zoom a {
                background:#1e2330 !important; color:#fff !important; border-color:rgba(255,255,255,0.15) !important;
            }
            .dialer-loc-map .leaflet-control-zoom a:hover { background:#7c3aed !important; }
            .dialer-loc-marker { width:30px; height:40px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.45)); }
            .dialer-loc-marker svg { width:100%; height:100%; display:block; }
            /* Read-only map preview: non-interactive, taps fall through to open Maps. */
            .dialer-loc-thumb { pointer-events:none; }
        </style>
    @endif

    @if(!empty($allLocations))
        <div class="card-premium p-5 mt-4">
            <h3 class="text-[10px] font-bold uppercase tracking-wider mb-3" style="color:var(--text-faint);">Locations</h3>
            <div class="space-y-3">
                @foreach($allLocations as $loc)
                    @php $hasPt = is_numeric($loc['lat'] ?? null) && is_numeric($loc['lng'] ?? null); @endphp
                    <a href="{{ $loc['maps_url'] }}" target="_blank" rel="noopener"
                       class="block rounded-xl overflow-hidden" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);">
                        @if($hasPt)
                            <div class="dialer-loc-thumb" data-lat="{{ $loc['lat'] }}" data-lng="{{ $loc['lng'] }}"
                                 style="height:140px;width:100%;background:#1e2330;"></div>
                        @endif
                        <span class="flex items-center gap-3 px-3 py-2.5">
                            <i class="fas fa-location-dot" style="color:#f87171;"></i>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium truncate" style="color:var(--text-primary);">{{ $loc['label'] }}</span>
                                @if(!empty($loc['address']))
                                    <span class="block text-[11px] truncate" style="color:var(--text-faint);">{{ $loc['address'] }}</span>
                                @endif
                            </span>
                            @if(($loc['source'] ?? '') === 'manual')
                                <span class="text-[9px] px-1.5 py-0.5 rounded" style="background:rgba(168,85,247,.15);color:#c084fc;">manual</span>
                            @endif
                            <i class="fas fa-arrow-up-right-from-square text-xs" style="color:var(--text-faint);"></i>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
        @if($hasLocMap)
            <script>
                (function () {
                    function initLocThumbs() {
                        if (typeof L === 'undefined') return;
                        var pin = '<svg viewBox="0 0 34 44" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
                            '<defs><linearGradient id="dlt-g" x1="0" y1="0" x2="0" y2="1">' +
                            '<stop offset="0%" stop-color="#a78bfa"/><stop offset="100%" stop-color="#7c3aed"/>' +
                            '</linearGradient></defs>' +
                            '<path d="M17 0C7.6 0 0 7.5 0 16.7c0 11.7 14.6 25.5 16 26.8.6.6 1.5.6 2 0 1.5-1.3 16-15.1 16-26.8C34 7.5 26.4 0 17 0z" fill="url(#dlt-g)" stroke="rgba(255,255,255,0.85)" stroke-width="1.5"/>' +
                            '<circle cx="17" cy="16" r="6" fill="#fff"/></svg>';
                        document.querySelectorAll('.dialer-loc-thumb').forEach(function (el) {
                            if (el.dataset.mapInit) return;
                            var lat = parseFloat(el.dataset.lat), lng = parseFloat(el.dataset.lng);
                            if (!isFinite(lat) || !isFinite(lng)) return;
                            el.dataset.mapInit = '1';
                            var map = L.map(el, {
                                center: [lat, lng], zoom: 15,
                                zoomControl: false, attributionControl: true,
                                dragging: false, touchZoom: false, scrollWheelZoom: false,
                                doubleClickZoom: false, boxZoom: false, keyboard: false, tap: false,
                            });
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                maxZoom: 19,
                                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                            }).addTo(map);
                            var icon = L.divIcon({
                                className: '',
                                html: '<div class="dialer-loc-marker">' + pin + '</div>',
                                iconSize: [30, 40], iconAnchor: [15, 40]
                            });
                            L.marker([lat, lng], { icon: icon, interactive: false, keyboard: false }).addTo(map);
                            setTimeout(function () { map.invalidateSize(); }, 80);
                        });
                    }
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', initLocThumbs);
                    } else {
                        initLocThumbs();
                    }
                })();
            </script>
        @endif
    @endif

    {{-- Manual editor — owner-entered channels / socials / location --}}
    @if($contact)
        {{-- Leaflet assets + shared map styles are loaded once above (the Locations section). --}}
        <style>
            [x-cloak] { display: none !important; }
        </style>
        <div class="card-premium p-5 mt-4" x-data="dialerManual({
                channels: {{ Illuminate\Support\Js::from(collect($payload['manual']['channels'] ?? [])->map(fn($c) => ['type'=>$c['type'],'label'=>$c['label'],'value'=>$c['value']])->values()) }},
                socials: {{ Illuminate\Support\Js::from(collect($payload['manual']['socials'] ?? [])->map(fn($s) => ['platform'=>$s['platform'],'label'=>$s['label'],'url'=>$s['url']])->values()) }},
                location: {{ Illuminate\Support\Js::from($payload['manual']['location'] ? ['label'=>$payload['manual']['location']['label'],'address'=>$payload['manual']['location']['address'],'lat'=>$payload['manual']['location']['lat'],'lng'=>$payload['manual']['location']['lng']] : ['label'=>'','address'=>'','lat'=>'','lng'=>'']) }},
             })">
            <form method="POST" action="{{ route('user.dialer.manual') }}">
                @csrf
                <input type="hidden" name="contact_id" value="{{ $contact->id }}">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-[10px] font-bold uppercase tracking-wider" style="color:var(--text-faint);">Manual additions</h3>
                    <button type="submit" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white" style="background:linear-gradient(135deg,#7c3aed,#ec4899);">
                        <i class="fas fa-save mr-1"></i> Save
                    </button>
                </div>

                {{-- Channels --}}
                <div class="mb-4">
                    <div class="text-xs font-semibold mb-2" style="color:var(--text-muted);">Channels</div>
                    <template x-for="(c, i) in channels" :key="'c'+i">
                        <div class="flex gap-2 mb-2">
                            <select :name="`channels[${i}][type]`" x-model="c.type" class="px-2 py-1.5 rounded-lg text-xs" style="background:rgba(255,255,255,.05);color:var(--text-primary);border:1px solid rgba(255,255,255,.10);">
                                <option value="phone">Phone</option>
                                <option value="sms">SMS</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="telegram">Telegram</option>
                                <option value="facetime_audio">FaceTime Audio</option>
                                <option value="facetime_video">FaceTime</option>
                                <option value="email">Email</option>
                                <option value="custom">Custom link</option>
                            </select>
                            <input :name="`channels[${i}][value]`" x-model="c.value" placeholder="Number / URL / handle" class="flex-1 px-2 py-1.5 rounded-lg text-xs" style="background:rgba(255,255,255,.05);color:var(--text-primary);border:1px solid rgba(255,255,255,.10);">
                            <button type="button" @click="channels.splice(i,1)" class="px-2 rounded-lg" style="color:#ef4444;"><i class="fas fa-trash text-xs"></i></button>
                        </div>
                    </template>
                    <button type="button" @click="channels.push({type:'phone',label:'',value:''})" class="text-xs font-medium" style="color:#a78bfa;"><i class="fas fa-plus mr-1"></i> Add channel</button>
                </div>

                {{-- Socials --}}
                <div class="mb-4">
                    <div class="text-xs font-semibold mb-2" style="color:var(--text-muted);">Socials</div>
                    <template x-for="(s, i) in socials" :key="'s'+i">
                        <div class="flex gap-2 mb-2">
                            <input :name="`socials[${i}][platform]`" x-model="s.platform" placeholder="Platform" class="w-28 px-2 py-1.5 rounded-lg text-xs" style="background:rgba(255,255,255,.05);color:var(--text-primary);border:1px solid rgba(255,255,255,.10);">
                            <input :name="`socials[${i}][url]`" x-model="s.url" placeholder="https://…" class="flex-1 px-2 py-1.5 rounded-lg text-xs" style="background:rgba(255,255,255,.05);color:var(--text-primary);border:1px solid rgba(255,255,255,.10);">
                            <button type="button" @click="socials.splice(i,1)" class="px-2 rounded-lg" style="color:#ef4444;"><i class="fas fa-trash text-xs"></i></button>
                        </div>
                    </template>
                    <button type="button" @click="socials.push({platform:'',label:'',url:''})" class="text-xs font-medium" style="color:#a78bfa;"><i class="fas fa-plus mr-1"></i> Add social</button>
                </div>

                {{-- Location --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-xs font-semibold" style="color:var(--text-muted);">Location</div>
                        <button type="button" @click="toggleMap()" class="text-[11px] font-medium" style="color:#a78bfa;">
                            <i class="fas fa-map-location-dot mr-1"></i> <span x-text="showMap ? 'Hide map' : 'Pick on map'"></span>
                        </button>
                    </div>

                    <div x-show="showMap" x-cloak class="mb-3">
                        <div class="flex gap-2 mb-2">
                            <input x-model="searchQuery" @keydown.enter.prevent="searchAddress()" type="text" placeholder="Search a place or address…" class="flex-1 px-2 py-1.5 rounded-lg text-xs" style="background:rgba(255,255,255,.05);color:var(--text-primary);border:1px solid rgba(255,255,255,.10);">
                            <button type="button" @click="searchAddress()" class="px-3 py-1.5 rounded-lg text-xs font-medium" style="background:rgba(124,58,237,.12);color:#a78bfa;border:1px solid rgba(124,58,237,.20)">
                                <i class="fas fa-magnifying-glass"></i>
                            </button>
                        </div>
                        <div x-ref="map" class="dialer-loc-map" style="height:260px;border-radius:12px;overflow:hidden;border:1px solid rgba(255,255,255,.10);background:#1e2330;"></div>
                        <p class="text-[11px] mt-1.5" style="color:var(--text-faint);">
                            <i class="fas fa-circle-info mr-1"></i> Tap the map or drag the pin — we'll fill in the address and coordinates for you.
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <input name="location[label]" x-model="location.label" placeholder="Label (e.g. Office)" class="px-2 py-1.5 rounded-lg text-xs" style="background:rgba(255,255,255,.05);color:var(--text-primary);border:1px solid rgba(255,255,255,.10);">
                        <input name="location[address]" x-model="location.address" placeholder="Address" class="px-2 py-1.5 rounded-lg text-xs" style="background:rgba(255,255,255,.05);color:var(--text-primary);border:1px solid rgba(255,255,255,.10);">
                        <input name="location[lat]" x-model="location.lat" @input="syncMapFromInputs()" placeholder="Latitude (optional)" class="px-2 py-1.5 rounded-lg text-xs" style="background:rgba(255,255,255,.05);color:var(--text-primary);border:1px solid rgba(255,255,255,.10);">
                        <input name="location[lng]" x-model="location.lng" @input="syncMapFromInputs()" placeholder="Longitude (optional)" class="px-2 py-1.5 rounded-lg text-xs" style="background:rgba(255,255,255,.05);color:var(--text-primary);border:1px solid rgba(255,255,255,.10);">
                    </div>

                    {{-- Live preview of the pending point (before saving) --}}
                    <div x-show="hasPreviewPoint()" x-cloak class="mt-3">
                        <div x-ref="preview" class="dialer-loc-thumb" style="height:150px;border-radius:12px;overflow:hidden;border:1px solid rgba(255,255,255,.10);background:#1e2330;"></div>
                        <p class="text-[11px] mt-1.5" style="color:var(--text-faint);">
                            <i class="fas fa-eye mr-1"></i> Preview of the point you'll save — updates as you adjust the coordinates.
                        </p>
                    </div>
                </div>
            </form>
        </div>
        <script>
            const DIALER_PIN_SVG = '<svg viewBox="0 0 34 44" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
                '<defs><linearGradient id="dlm-g" x1="0" y1="0" x2="0" y2="1">' +
                '<stop offset="0%" stop-color="#a78bfa"/><stop offset="100%" stop-color="#7c3aed"/>' +
                '</linearGradient></defs>' +
                '<path d="M17 0C7.6 0 0 7.5 0 16.7c0 11.7 14.6 25.5 16 26.8.6.6 1.5.6 2 0 1.5-1.3 16-15.1 16-26.8C34 7.5 26.4 0 17 0z" fill="url(#dlm-g)" stroke="rgba(255,255,255,0.85)" stroke-width="1.5"/>' +
                '<circle cx="17" cy="16" r="6" fill="#fff"/>' +
                '<text x="17" y="19.5" text-anchor="middle" font-family="Space Grotesk, sans-serif" font-size="8" font-weight="700" fill="#7c3aed">1</text>' +
                '</svg>';

            function dialerManual(initial) {
                return {
                    channels: initial.channels || [],
                    socials: initial.socials || [],
                    location: initial.location || {label:'',address:'',lat:'',lng:''},
                    showMap: false,
                    searchQuery: '',
                    map: null,
                    marker: null,
                    previewMap: null,
                    previewMarker: null,
                    _suppressMapSync: false,
                    _geoTimer: null,

                    init() {
                        this.$nextTick(() => { if (this.hasPreviewPoint()) this.initPreview(); });
                        this.$watch('location.lat', () => this.refreshPreview());
                        this.$watch('location.lng', () => this.refreshPreview());
                    },

                    toggleMap() {
                        this.showMap = !this.showMap;
                        if (this.showMap) this.$nextTick(() => this.initMap());
                    },

                    _coord(v) { var n = parseFloat(v); return isFinite(n) ? n : null; },

                    hasPreviewPoint() {
                        return this._coord(this.location.lat) !== null && this._coord(this.location.lng) !== null;
                    },

                    initPreview() {
                        if (typeof L === 'undefined' || !this.$refs.preview) return;
                        var lat = this._coord(this.location.lat), lng = this._coord(this.location.lng);
                        if (lat === null || lng === null) return;
                        if (this.previewMap) { this.updatePreview(); return; }

                        var map = L.map(this.$refs.preview, {
                            center: [lat, lng], zoom: 15,
                            zoomControl: false, attributionControl: true,
                            dragging: false, touchZoom: false, scrollWheelZoom: false,
                            doubleClickZoom: false, boxZoom: false, keyboard: false, tap: false,
                        });
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                        }).addTo(map);
                        var icon = L.divIcon({
                            className: '',
                            html: '<div class="dialer-loc-marker">' + DIALER_PIN_SVG + '</div>',
                            iconSize: [30, 40], iconAnchor: [15, 40]
                        });
                        this.previewMarker = L.marker([lat, lng], { icon: icon, interactive: false, keyboard: false }).addTo(map);
                        this.previewMap = map;
                        setTimeout(() => map.invalidateSize(), 80);
                    },

                    updatePreview() {
                        var lat = this._coord(this.location.lat), lng = this._coord(this.location.lng);
                        if (lat === null || lng === null || !this.previewMap) return;
                        this.previewMarker.setLatLng([lat, lng]);
                        this.previewMap.setView([lat, lng], this.previewMap.getZoom(), { animate: false });
                        setTimeout(() => this.previewMap.invalidateSize(), 60);
                    },

                    refreshPreview() {
                        if (!this.hasPreviewPoint()) return;
                        if (this.previewMap) this.updatePreview();
                        else this.$nextTick(() => this.initPreview());
                    },

                    initMap() {
                        if (typeof L === 'undefined' || !this.$refs.map) return;
                        if (this.map) { setTimeout(() => this.map.invalidateSize(), 60); return; }

                        var lat = this._coord(this.location.lat);
                        var lng = this._coord(this.location.lng);
                        var hasPoint = lat !== null && lng !== null;
                        var center = hasPoint ? [lat, lng] : [20, 0];
                        var zoom = hasPoint ? 15 : 2;

                        var map = L.map(this.$refs.map, { center: center, zoom: zoom, scrollWheelZoom: true, zoomControl: true });
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                        }).addTo(map);

                        var icon = L.divIcon({
                            className: '',
                            html: '<div class="dialer-loc-marker">' + DIALER_PIN_SVG + '</div>',
                            iconSize: [30, 40], iconAnchor: [15, 40]
                        });

                        var marker = L.marker(center, { icon: icon, draggable: true }).addTo(map);
                        if (!hasPoint) marker.setOpacity(0);

                        marker.on('dragend', () => {
                            var p = marker.getLatLng();
                            this.applyPoint(p.lat, p.lng, false);
                        });
                        map.on('click', (e) => {
                            marker.setLatLng(e.latlng);
                            marker.setOpacity(1);
                            this.applyPoint(e.latlng.lat, e.latlng.lng, false);
                        });

                        this.map = map;
                        this.marker = marker;
                        setTimeout(() => map.invalidateSize(), 80);
                    },

                    applyPoint(lat, lng, recenter) {
                        this._suppressMapSync = true;
                        this.location.lat = (Math.round(lat * 1e6) / 1e6).toString();
                        this.location.lng = (Math.round(lng * 1e6) / 1e6).toString();
                        this._suppressMapSync = false;
                        if (recenter && this.map) this.map.setView([lat, lng], Math.max(this.map.getZoom(), 15), { animate: false });
                        this.reverseGeocode(lat, lng);
                    },

                    syncMapFromInputs() {
                        if (this._suppressMapSync || !this.map || !this.marker) return;
                        var lat = this._coord(this.location.lat);
                        var lng = this._coord(this.location.lng);
                        if (lat === null || lng === null) return;
                        this.marker.setLatLng([lat, lng]);
                        this.marker.setOpacity(1);
                        this.map.setView([lat, lng], Math.max(this.map.getZoom(), 13), { animate: false });
                    },

                    reverseGeocode(lat, lng) {
                        clearTimeout(this._geoTimer);
                        this._geoTimer = setTimeout(() => {
                            fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + lat + '&lon=' + lng, { headers: { 'Accept': 'application/json' } })
                                .then(r => r.ok ? r.json() : null)
                                .then(d => { if (d && d.display_name) this.location.address = d.display_name; })
                                .catch(() => {});
                        }, 250);
                    },

                    searchAddress() {
                        var q = (this.searchQuery || '').trim();
                        if (!q) return;
                        fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
                            .then(r => r.ok ? r.json() : null)
                            .then(d => {
                                if (!d || !d.length) { if (window.showToast) window.showToast('No matching place found'); return; }
                                var lat = parseFloat(d[0].lat), lng = parseFloat(d[0].lon);
                                this._suppressMapSync = true;
                                this.location.lat = (Math.round(lat * 1e6) / 1e6).toString();
                                this.location.lng = (Math.round(lng * 1e6) / 1e6).toString();
                                if (d[0].display_name) this.location.address = d[0].display_name;
                                this._suppressMapSync = false;
                                if (this.marker) { this.marker.setLatLng([lat, lng]); this.marker.setOpacity(1); }
                                if (this.map) this.map.setView([lat, lng], 15, { animate: false });
                            })
                            .catch(() => {});
                    },
                };
            }
        </script>
    @endif

    @if(!empty($recent) && $recent->isNotEmpty())
        <div class="card-premium p-5 mt-4">
            <h3 class="text-[10px] font-bold uppercase tracking-wider mb-3" style="color:var(--text-faint);">Recent activity</h3>
            <div class="space-y-2">
                @foreach($recent as $r)
                    <div class="flex items-center justify-between py-1.5" style="border-top: 1px solid rgba(255,255,255,.06);">
                        <div class="text-xs font-mono" style="color:var(--text-primary);">{{ $r->number_e164 }}</div>
                        <div class="text-[11px]" style="color:var(--text-faint);">{{ $r->looked_up_at->diffForHumans() }}</div>
                    </div>
                @endforeach
            </div>
        </div>
        <input id="log-tag" type="text" placeholder="Tag (e.g. lead, family, vendor)" maxlength="50"
               class="w-full px-3 py-2 rounded-xl text-sm mb-2" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
        <textarea id="log-note" rows="2" placeholder="Note about this call…" maxlength="2000"
               class="w-full px-3 py-2 rounded-xl text-sm mb-2" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);"></textarea>
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <input id="cb-at" type="datetime-local" class="px-2 py-1.5 rounded-lg text-xs" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                <button type="button" onclick="setCallback()" class="text-[11px] px-2.5 py-1.5 rounded-lg font-medium" style="background:rgba(124,58,237,.12);color:#a78bfa;border:1px solid rgba(124,58,237,.20)">
                    <i class="fas fa-bell mr-1"></i> Remind me
                </button>
            </div>
            <button type="button" onclick="saveLog()" class="text-xs px-4 py-2 rounded-xl font-semibold text-white" style="background:linear-gradient(135deg,#7c3aed,#ec4899);">Save log</button>
        </div>
        @if($callback)
            <div class="mt-3 text-[11px] flex items-center gap-1.5" style="color:#a78bfa;">
                <i class="fas fa-bell"></i> Call-back reminder set for {{ \Illuminate\Support\Carbon::parse($callback['callback_at'])->diffForHumans() }}
            </div>
        @endif
    </div>
    @endif

    {{-- Recent activity --}}
    <div class="card-premium p-5 mt-4" id="activity-card" @if(empty($recent)) style="display:none" @endif>
        <h3 class="text-[10px] font-bold uppercase tracking-wider mb-3" style="color:var(--text-faint);">Recent activity</h3>
        <div class="space-y-2" id="activity-list">
            @foreach($recent as $r)
                <div class="py-1.5" style="border-top: 1px solid rgba(255,255,255,.06);">
                    <div class="flex items-center justify-between">
                        <div class="text-xs font-medium" style="color:var(--text-primary);">
                            {{ $r['outcome'] ? str_replace('_',' ',$r['outcome']) : 'Lookup' }}
                            @if($r['tag'])<span class="ml-1 px-1.5 rounded-full text-[10px]" style="background:rgba(255,255,255,.06);color:var(--text-muted);">{{ $r['tag'] }}</span>@endif
                        </div>
                        <div class="text-[11px]" style="color:var(--text-faint);">{{ $r['at_human'] }}</div>
                    </div>
                    @if($r['note'])<div class="text-[11px] mt-0.5" style="color:var(--text-muted);">{{ $r['note'] }}</div>@endif
                </div>
            @endforeach
        </div>
    </div>
</div>

<style>
.qa-btn { display:flex; flex-direction:column; align-items:center; gap:3px; padding:10px 4px; border-radius:12px; font-size:11px; font-weight:600; text-align:center; }
.qa-btn i { font-size:14px; }
.outcome-chip.active { background:rgba(124,58,237,.18) !important; color:#a78bfa !important; border-color:rgba(124,58,237,.35) !important; }
</style>

<script>
const dp = document.getElementById('dialer-profile');
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
const NUM = dp.dataset.number || '';
const NUM_E164 = dp.dataset.numberE164 || '';
const CONTACT_ID = dp.dataset.contactId || null;
const MY_BIO_URL = @json($myBioUrl);
let selectedOutcome = null;

function toast(msg) {
    if (window.showToast) { window.showToast(msg); return; }
    console.log(msg);
}

async function post(url, body, method = 'POST') {
    const r = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF },
        body: body ? JSON.stringify(body) : undefined,
    });
    return { ok: r.ok, data: await r.json().catch(() => null) };
}

function copyNumber() {
    if (!NUM) return;
    navigator.clipboard?.writeText(NUM).then(() => toast('Number copied'));
}

function shareMyBiolink() {
    const url = MY_BIO_URL;
    if (!url) { toast('Publish a biolink first'); return; }
    if (navigator.share) { navigator.share({ url }).catch(() => {}); }
    else { navigator.clipboard?.writeText(url).then(() => toast('Your biolink copied')); }
    if (NUM) logQuick('messaged');
}

async function addFavorite() {
    const { ok } = await post(dp.dataset.favUrl, { number: NUM_E164 || NUM, contact_id: CONTACT_ID });
    if (ok) {
        const btn = document.getElementById('fav-toggle');
        btn.querySelector('span').textContent = 'In speed dial';
        toast('Added to speed dial');
    }
}

async function toggleFlag(field) {
    const badge = field === 'is_spam' ? document.getElementById('badge-spam') : document.getElementById('badge-blocked');
    const on = badge.classList.contains('hidden'); // about to turn on
    const { ok, data } = await post(dp.dataset.flagUrl, { number: NUM_E164 || NUM, [field]: on });
    if (!ok || !data?.data) return;
    const spam = data.data.is_spam, blocked = data.data.is_blocked;
    document.getElementById('badge-spam').classList.toggle('hidden', !spam);
    document.getElementById('badge-blocked').classList.toggle('hidden', !blocked);
    document.querySelector('#spam-toggle span').textContent = spam ? 'Unmark spam' : 'Mark spam';
    document.querySelector('#block-toggle span').textContent = blocked ? 'Unblock' : 'Block';
}

document.querySelectorAll('.outcome-chip').forEach(c => {
    c.addEventListener('click', () => {
        document.querySelectorAll('.outcome-chip').forEach(x => x.classList.remove('active'));
        if (selectedOutcome === c.dataset.outcome) { selectedOutcome = null; return; }
        c.classList.add('active');
        selectedOutcome = c.dataset.outcome;
    });
});

function logQuick(outcome) {
    // Fire-and-forget quick log from the action bar.
    post(dp.dataset.logUrl, { number: NUM_E164 || NUM, contact_id: CONTACT_ID, outcome });
}

async function saveLog() {
    const note = document.getElementById('log-note').value.trim();
    const tag = document.getElementById('log-tag').value.trim();
    const { ok } = await post(dp.dataset.logUrl, {
        number: NUM_E164 || NUM, contact_id: CONTACT_ID,
        outcome: selectedOutcome, note: note || null, tag: tag || null,
    });
    if (ok) { toast('Call logged'); location.reload(); }
}

async function setCallback() {
    const at = document.getElementById('cb-at').value;
    if (!at) { toast('Pick a date & time'); return; }
    const note = document.getElementById('log-note').value.trim();
    const { ok, data } = await post(dp.dataset.callbackUrl, {
        number: NUM_E164 || NUM, contact_id: CONTACT_ID, callback_at: at, note: note || null,
    });
    if (ok) { toast('Reminder set'); location.reload(); }
    else if (data?.error) toast(data.error.message || 'Could not set reminder');
}
</script>
@endsection
