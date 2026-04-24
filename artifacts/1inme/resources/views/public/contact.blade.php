@extends('public.layouts.site')
@section('content')
@php
    $sections = $page->visibleSections();
    $address = trim((string)($extra['address'] ?? ''));
    $email   = trim((string)($extra['email']   ?? ''));
    $phone   = trim((string)($extra['phone']   ?? ''));
    $hours   = trim((string)($extra['hours']   ?? ''));
    $social  = (array)($extra['social']        ?? []);
    $map     = (array)($extra['map']           ?? []);
    $lat     = (float)($map['lat']  ?? 17.3850);
    $lng     = (float)($map['lng']  ?? 78.4867);
    $zoom    = (int)  ($map['zoom'] ?? 12);
    $mapLabel= trim((string)($map['label'] ?? ''));
    // Approximate bbox from lat/lng/zoom for OpenStreetMap embed iframe.
    // Span shrinks roughly by half per zoom level. zoom 12 ~ 0.18° lat span.
    $latSpan = 360.0 / pow(2, $zoom);
    $lngSpan = $latSpan / max(0.01, cos(deg2rad($lat)));
    $minLng = $lng - $lngSpan / 2;
    $maxLng = $lng + $lngSpan / 2;
    $minLat = $lat - $latSpan / 2;
    $maxLat = $lat + $latSpan / 2;
    $bbox = sprintf('%F,%F,%F,%F', $minLng, $minLat, $maxLng, $maxLat);
    $marker = sprintf('%F,%F', $lat, $lng);
    $mapEmbedUrl = 'https://www.openstreetmap.org/export/embed.html?bbox=' . urlencode($bbox) . '&layer=mapnik&marker=' . urlencode($marker);
    $mapLargerUrl = sprintf('https://www.openstreetmap.org/?mlat=%F&mlon=%F#map=%d/%F/%F', $lat, $lng, $zoom, $lat, $lng);
    $mapTitle = $mapLabel ?: 'our location';

    $socialIcons = [
        'twitter'   => ['fa-x-twitter', 'X (Twitter)'],
        'instagram' => ['fa-instagram', 'Instagram'],
        'linkedin'  => ['fa-linkedin',  'LinkedIn'],
        'youtube'   => ['fa-youtube',   'YouTube'],
        'facebook'  => ['fa-facebook',  'Facebook'],
    ];
@endphp

<section class="relative pt-20 pb-12 lg:pt-28 lg:pb-16 overflow-hidden">
    <div class="mesh-bg"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-10 items-center">
        <div data-anim="fade-right">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-violet-500/10 border border-violet-400/20 text-xs text-violet-300 uppercase tracking-wider font-semibold">
                <i class="fas fa-envelope text-[10px]"></i> Contact
            </span>
            <h1 class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.05]">{{ $page->title }}</h1>
            @if($page->meta_description)
                <p class="mt-5 text-lg text-gray-400 max-w-xl leading-relaxed">{{ $page->meta_description }}</p>
            @endif
            <div class="mt-7 flex flex-wrap items-center gap-6 text-sm" data-anim="fade-up" data-stagger>
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 pulse-dot text-emerald-400/40"></span>
                    <span class="text-gray-200 font-medium">Replies within 1 business day</span>
                </div>
                <div class="text-gray-400"><i class="fas fa-language text-violet-300 mr-1"></i> EN · हिन्दी</div>
            </div>
        </div>
        <div data-anim="fade-left" data-tilt="5" class="relative">
            <div class="img-frame img-tilt aspect-[16/10]">
                <img src="{{ asset('images/marketing/contact/hero.png') }}" alt="The 1INME support team">
            </div>
            <div class="absolute -bottom-5 -right-5 bg-[#11101c] border border-white/10 rounded-2xl p-3 pr-4 flex items-center gap-3 shadow-2xl float-y">
                <i class="fas fa-headset text-violet-400 text-lg"></i>
                <div class="text-xs"><div class="font-semibold text-white">Friendly humans</div><div class="text-gray-400">Behind every reply</div></div>
            </div>
        </div>
    </div>
</section>

<section class="pb-8">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        @foreach($sections as $section)
            <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-6 mb-6">
                @if(!empty($section['heading']))<h2 class="text-lg font-bold mb-2">{{ $section['heading'] }}</h2>@endif
                <div class="text-gray-300 text-sm leading-relaxed">{!! nl2br(e($section['body'] ?? '')) !!}</div>
            </div>
        @endforeach
    </div>
</section>

<section class="pb-12">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 grid md:grid-cols-2 gap-6" data-anim="fade-up" data-stagger>
        <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-6 space-y-5">
            <h2 class="text-lg font-bold text-white">Contact details</h2>
            @if($address !== '')
                <div>
                    <div class="text-[11px] uppercase tracking-wider text-gray-400 mb-1"><i class="fas fa-location-dot mr-1.5"></i>Address</div>
                    <div class="text-sm text-gray-200 leading-relaxed">{!! nl2br(e($address)) !!}</div>
                </div>
            @endif
            @if($email !== '')
                <div>
                    <div class="text-[11px] uppercase tracking-wider text-gray-400 mb-1"><i class="fas fa-envelope mr-1.5"></i>Email</div>
                    <a href="mailto:{{ $email }}" class="text-sm text-violet-300 hover:text-violet-200 break-all">{{ $email }}</a>
                </div>
            @endif
            @if($phone !== '')
                <div>
                    <div class="text-[11px] uppercase tracking-wider text-gray-400 mb-1"><i class="fas fa-phone mr-1.5"></i>Phone</div>
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="text-sm text-violet-300 hover:text-violet-200">{{ $phone }}</a>
                </div>
            @endif
            @if($hours !== '')
                <div>
                    <div class="text-[11px] uppercase tracking-wider text-gray-400 mb-1"><i class="far fa-clock mr-1.5"></i>Hours</div>
                    <div class="text-sm text-gray-200 leading-relaxed">{!! nl2br(e($hours)) !!}</div>
                </div>
            @endif
            @php $hasSocial = false; foreach($social as $u){ if(trim((string)$u)!==''){ $hasSocial = true; break; } } @endphp
            @if($hasSocial)
                <div>
                    <div class="text-[11px] uppercase tracking-wider text-gray-400 mb-2">Follow us</div>
                    <div class="flex gap-3">
                        @foreach($socialIcons as $key => [$icon, $label])
                            @php $url = trim((string)($social[$key] ?? '')); @endphp
                            @if($url !== '')
                                <a href="{{ $url }}" target="_blank" rel="noopener" title="{{ $label }}"
                                   class="w-9 h-9 rounded-lg bg-white/5 hover:bg-violet-600 border border-white/10 flex items-center justify-center text-gray-300 hover:text-white transition">
                                    <i class="fab {{ $icon }}"></i>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
        <div class="bg-white/[0.03] border border-white/10 rounded-2xl overflow-hidden flex flex-col">
            <div class="aspect-[4/3] w-full bg-white/5 relative">
                <div id="contact-map"
                     role="region"
                     aria-label="Map of {{ $mapTitle }}"
                     data-lat="{{ $lat }}"
                     data-lng="{{ $lng }}"
                     data-zoom="{{ $zoom }}"
                     data-label="{{ $mapLabel }}"
                     style="width:100%; height:100%;">
                    <noscript>
                        <iframe
                            src="{{ $mapEmbedUrl }}"
                            title="Map of {{ $mapTitle }}"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            style="border:0; width:100%; height:100%;"
                            allowfullscreen></iframe>
                    </noscript>
                </div>
            </div>
            <div class="px-4 py-3 flex items-center justify-between text-xs text-gray-400">
                <span>{{ $mapLabel ?: 'Find us on OpenStreetMap' }}</span>
                <a href="{{ $mapLargerUrl }}" target="_blank" rel="noopener" class="text-violet-300 hover:text-violet-200">
                    View larger map <i class="fas fa-up-right-from-square ml-1 text-[10px]"></i>
                </a>
            </div>
        </div>
    </div>
</section>

<section class="pb-12">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 grid md:grid-cols-3 gap-5" data-anim="fade-up" data-stagger>
        <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5 hover:border-violet-400/40 transition">
            <div class="w-10 h-10 rounded-lg bg-violet-500/15 border border-violet-400/30 flex items-center justify-center text-violet-300 mb-3"><i class="fas fa-bolt"></i></div>
            <div class="text-sm font-bold text-white">Fast replies</div><div class="text-xs text-gray-400 mt-1">Most messages get a real human reply within a few hours.</div>
        </div>
        <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5 hover:border-violet-400/40 transition">
            <div class="w-10 h-10 rounded-lg bg-violet-500/15 border border-violet-400/30 flex items-center justify-center text-violet-300 mb-3"><i class="fas fa-handshake"></i></div>
            <div class="text-sm font-bold text-white">Partnerships</div><div class="text-xs text-gray-400 mt-1">Press, integrations, agencies — pitch us, we read every one.</div>
        </div>
        <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5 hover:border-violet-400/40 transition">
            <div class="w-10 h-10 rounded-lg bg-violet-500/15 border border-violet-400/30 flex items-center justify-center text-violet-300 mb-3"><i class="fas fa-lightbulb"></i></div>
            <div class="text-sm font-bold text-white">Feature ideas</div><div class="text-xs text-gray-400 mt-1">Tell us what to build next — your name is on the changelog.</div>
        </div>
    </div>
</section>

<section class="pb-20">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 grid md:grid-cols-[1fr_1fr] gap-6 items-start">
        <div class="img-frame aspect-[4/3] hidden md:block" data-anim="fade-right" data-tilt="4">
            <img src="{{ asset('images/marketing/contact/office.png') }}" alt="Our office">
        </div>
        <div data-anim="fade-left">
        @if(session('success'))
            <div class="rounded-xl px-4 py-3 text-sm mb-4" style="background:rgba(34,197,94,0.10); border:1px solid rgba(34,197,94,0.30); color:#86efac;">
                {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div class="rounded-xl px-4 py-3 text-sm mb-4 bg-red-500/10 border border-red-500/30 text-red-300">
                @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('site.contact.submit') }}" class="space-y-4 bg-white/[0.03] border border-white/10 rounded-2xl p-6">
            @csrf
            {{-- honeypot --}}
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
            <h2 class="text-lg font-bold text-white">Send us a message</h2>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400">Your name</label>
                    <input type="text" name="name" required value="{{ old('name') }}" class="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400">Email</label>
                    <input type="email" name="email" required value="{{ old('email') }}" class="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400">Subject</label>
                <input type="text" name="subject" required value="{{ old('subject') }}" class="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400">Message</label>
                <textarea name="message" required rows="6" class="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white">{{ old('message') }}</textarea>
            </div>
            <button type="submit" class="w-full py-3 bg-violet-600 hover:bg-violet-700 rounded-lg text-sm font-bold text-white transition">
                Send message
            </button>
        </form>
        </div>
    </div>
</section>

@include('public.partials.subscribe-block', [
    'heading' => 'Or reach us on the channel that suits you.',
    'subtext' => 'Subscribe by email, follow our WhatsApp Channel for short broadcasts, or DM us for a 1:1 conversation.',
    'source'  => 'contact',
])
@endsection

@push('head')
<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css"
      integrity="sha512-h9FcoyWjHcOcmEVkxOfTLnmZFWIH0iZhZT1H2TbOq55xssQGEJHEaIm+PgoUaZbRvQTNTluNOEfb1ZRy6D3BOw=="
      crossorigin="anonymous" referrerpolicy="no-referrer" />
<style>
    #contact-map { background:#1e2330; }
    .leaflet-container { background:#1e2330 !important; font-family:'Space Grotesk', sans-serif; }
    .leaflet-control-attribution { background:rgba(30,35,48,0.85) !important; color:#9ca3af !important; }
    .leaflet-control-attribution a { color:#a78bfa !important; }
    .leaflet-control-zoom a {
        background:#1e2330 !important; color:#fff !important; border-color:rgba(255,255,255,0.15) !important;
    }
    .leaflet-control-zoom a:hover { background:#7c3aed !important; }
    .brand-marker {
        width:34px; height:44px; position:relative;
        filter: drop-shadow(0 4px 6px rgba(0,0,0,0.45));
    }
    .brand-marker svg { width:100%; height:100%; display:block; }
    .brand-marker .pulse {
        position:absolute; left:50%; bottom:-4px; width:14px; height:14px;
        margin-left:-7px; border-radius:9999px;
        background:rgba(124,58,237,0.55);
        animation: brand-marker-pulse 1.8s ease-out infinite;
    }
    @keyframes brand-marker-pulse {
        0% { transform:scale(0.6); opacity:0.9; }
        100% { transform:scale(2.2); opacity:0; }
    }
    .brand-popup .leaflet-popup-content-wrapper {
        background:#1e2330; color:#fff; border:1px solid rgba(255,255,255,0.1);
        border-radius:12px;
    }
    .brand-popup .leaflet-popup-tip { background:#1e2330; border:1px solid rgba(255,255,255,0.1); }
    .brand-popup .leaflet-popup-content { margin:10px 14px; font-size:13px; }
</style>
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"
        integrity="sha512-BB3hKbKWOc9Ez/TAwyWxNXeoV9c1v6FIeYiBieIWkpLjauysF18NzgR1MBNBXf8/KABdlkX68nAhlwcDFLGPCQ=="
        crossorigin="anonymous" referrerpolicy="no-referrer" defer></script>
<script>
(function(){
    function init(){
        var el = document.getElementById('contact-map');
        if (!el || typeof L === 'undefined') return;
        var lat = parseFloat(el.dataset.lat);
        var lng = parseFloat(el.dataset.lng);
        var zoom = parseInt(el.dataset.zoom, 10) || 12;
        var label = el.dataset.label || '';
        if (!isFinite(lat) || !isFinite(lng)) return;

        var map = L.map(el, {
            center: [lat, lng],
            zoom: zoom,
            scrollWheelZoom: false,
            zoomControl: true
        });
        map.on('click', function(){ map.scrollWheelZoom.enable(); });
        map.on('mouseout', function(){ map.scrollWheelZoom.disable(); });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        var pinSvg = '<svg viewBox="0 0 34 44" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
            '<defs><linearGradient id="bm-g" x1="0" y1="0" x2="0" y2="1">' +
            '<stop offset="0%" stop-color="#a78bfa"/><stop offset="100%" stop-color="#7c3aed"/>' +
            '</linearGradient></defs>' +
            '<path d="M17 0C7.6 0 0 7.5 0 16.7c0 11.7 14.6 25.5 16 26.8.6.6 1.5.6 2 0 1.5-1.3 16-15.1 16-26.8C34 7.5 26.4 0 17 0z" fill="url(#bm-g)" stroke="rgba(255,255,255,0.85)" stroke-width="1.5"/>' +
            '<circle cx="17" cy="16" r="6" fill="#fff"/>' +
            '<text x="17" y="19.5" text-anchor="middle" font-family="Space Grotesk, sans-serif" font-size="8" font-weight="700" fill="#7c3aed">1</text>' +
            '</svg>';

        var icon = L.divIcon({
            className: '',
            html: '<div class="brand-marker"><span class="pulse"></span>' + pinSvg + '</div>',
            iconSize: [34, 44],
            iconAnchor: [17, 44],
            popupAnchor: [0, -40]
        });

        var marker = L.marker([lat, lng], { icon: icon, title: label || '1INME' }).addTo(map);
        if (label) {
            marker.bindPopup('<strong>' + label.replace(/[<>&]/g, function(c){
                return {'<':'&lt;','>':'&gt;','&':'&amp;'}[c];
            }) + '</strong>', { className: 'brand-popup' });
        }

        setTimeout(function(){ map.invalidateSize(); }, 100);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
@endpush
