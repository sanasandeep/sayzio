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

    $socialIcons = [
        'twitter'   => ['fa-x-twitter', 'X (Twitter)'],
        'instagram' => ['fa-instagram', 'Instagram'],
        'linkedin'  => ['fa-linkedin',  'LinkedIn'],
        'youtube'   => ['fa-youtube',   'YouTube'],
        'facebook'  => ['fa-facebook',  'Facebook'],
    ];
@endphp

<section class="pt-16 pb-8 lg:pt-24 text-center">
    <div class="max-w-3xl mx-auto px-4">
        <h1 class="text-4xl sm:text-5xl font-bold">{{ $page->title }}</h1>
        @if($page->meta_description)
            <p class="mt-4 text-lg text-gray-400">{{ $page->meta_description }}</p>
        @endif
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
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 grid md:grid-cols-2 gap-6">
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
            <div class="aspect-[4/3] w-full bg-white/5">
                <iframe
                    src="{{ $mapEmbedUrl }}"
                    title="Map of {{ $mapLabel ?: 'our location' }}"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    style="border:0; width:100%; height:100%;"
                    allowfullscreen></iframe>
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

<section class="pb-20">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
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
            <button type="submit" class="w-full py-3 bg-violet-600 hover:bg-violet-700 rounded-lg text-sm font-bold text-white">
                Send message
            </button>
        </form>
    </div>
</section>
@endsection
