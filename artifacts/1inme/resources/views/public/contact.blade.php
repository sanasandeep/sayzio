@extends('public.layouts.site')
@section('content')
@php
    $sections = $page->visibleSections();
    $extraArr = is_array($extra ?? null) ? $extra : [];

    $address = trim((string)($extraArr['address'] ?? ''));
    $email   = trim((string)($extraArr['email']   ?? ''));
    $phone   = trim((string)($extraArr['phone']   ?? ''));
    $hours   = trim((string)($extraArr['hours']   ?? ''));
    $social  = (array)($extraArr['social']        ?? []);
    $map     = (array)($extraArr['map']           ?? []);
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

    // ------------------------------------------------------------------
    // Editable copy/imagery groups (task #762). Every leaf has a literal
    // fallback so the page keeps rendering identically even when the
    // admin blanks a field or the entire $extra column is empty.
    // ------------------------------------------------------------------
    $hero          = is_array($extraArr['hero']           ?? null) ? $extraArr['hero']           : [];
    $floatingCard  = is_array($hero['floating_card']      ?? null) ? $hero['floating_card']      : [];
    $officeImageIn = is_array($extraArr['office_image']   ?? null) ? $extraArr['office_image']   : [];
    $formCfg       = is_array($extraArr['form']           ?? null) ? $extraArr['form']           : [];

    $or = function ($v, $fallback) {
        $v = is_string($v) ? trim($v) : $v;
        return ($v === '' || $v === null) ? $fallback : $v;
    };

    // Hero defaults (single source of truth for the literal fallbacks).
    $heroBadgeLabel        = $or($hero['badge_label']        ?? '', 'Contact');
    $heroBadgeIcon         = $or($hero['badge_icon']         ?? '', 'fa-envelope');
    $heroAvailabilityText  = trim((string) ($hero['availability_text'] ?? 'Replies within 1 business day'));
    $heroAvailabilityIcon  = trim((string) ($hero['availability_icon'] ?? ''));
    $heroLanguages         = trim((string) ($hero['languages']         ?? 'EN · हिन्दी'));
    $heroSideImage         = $or($hero['side_image']         ?? '', asset('images/marketing/contact/hero.png'));
    $heroSideImageAlt      = $or($hero['side_image_alt']     ?? '', 'The Sayzio support team');
    $floatingCardTitle     = trim((string) ($floatingCard['title']    ?? 'Friendly humans'));
    $floatingCardSubtitle  = trim((string) ($floatingCard['subtitle'] ?? 'Behind every reply'));
    $floatingCardIcon      = $or($floatingCard['icon']        ?? '', 'fa-headset');
    $showFloatingCard      = ($floatingCardTitle !== '' || $floatingCardSubtitle !== '');

    // "Contact details" heading (blank to hide).
    $detailsHeading = trim((string) ($extraArr['details_heading'] ?? 'Contact details'));

    // Feature cards — if the admin saved an explicitly empty array, hide
    // the entire row; if the key is absent, fall back to the three defaults.
    $defaultFeatureCards = [
        ['icon' => 'fa-bolt',      'title' => 'Fast replies',  'desc' => 'Most messages get a real human reply within a few hours.'],
        ['icon' => 'fa-handshake', 'title' => 'Partnerships',  'desc' => 'Press, integrations, agencies — pitch us, we read every one.'],
        ['icon' => 'fa-lightbulb', 'title' => 'Feature ideas', 'desc' => 'Tell us what to build next — your name is on the changelog.'],
    ];
    if (array_key_exists('feature_cards', $extraArr) && is_array($extraArr['feature_cards'])) {
        $featureCards = array_values($extraArr['feature_cards']);
    } else {
        $featureCards = $defaultFeatureCards;
    }

    // Office image next to the form.
    $officeImageUrl = $or($officeImageIn['url'] ?? '', asset('images/marketing/contact/office.png'));
    $officeImageAlt = $or($officeImageIn['alt'] ?? '', 'Our office');

    // Form copy.
    $formHeading             = $or($formCfg['heading']             ?? '', 'Send us a message');
    $formIntro               = trim((string) ($formCfg['intro']    ?? ''));
    $formNameLabel           = $or($formCfg['name_label']          ?? '', 'Your name');
    $formNamePlaceholder     = trim((string) ($formCfg['name_placeholder']    ?? ''));
    $formEmailLabel          = $or($formCfg['email_label']         ?? '', 'Email');
    $formEmailPlaceholder    = trim((string) ($formCfg['email_placeholder']   ?? ''));
    $formSubjectLabel        = $or($formCfg['subject_label']       ?? '', 'Subject');
    $formSubjectPlaceholder  = trim((string) ($formCfg['subject_placeholder'] ?? ''));
    $formMessageLabel        = $or($formCfg['message_label']       ?? '', 'Message');
    $formMessagePlaceholder  = trim((string) ($formCfg['message_placeholder'] ?? ''));
    $formSubmitLabel         = $or($formCfg['submit_label']        ?? '', 'Send message');
@endphp

<section class="relative pt-20 pb-12 lg:pt-28 lg:pb-16 overflow-hidden">
    <div class="mesh-bg"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-10 items-center">
        <div data-anim="fade-right">
            @if($heroBadgeLabel !== '')
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-400/20 text-xs text-blue-300 uppercase tracking-wider font-semibold">
                    @if($heroBadgeIcon !== '')<i class="fas {{ $heroBadgeIcon }} text-[10px]"></i>@endif {{ $heroBadgeLabel }}
                </span>
            @endif
            <h1 class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.05]">{{ $page->title }}</h1>
            @if($page->meta_description)
                <p class="mt-5 text-lg text-gray-400 max-w-xl leading-relaxed">{{ $page->meta_description }}</p>
            @endif
            @if($heroAvailabilityText !== '' || $heroLanguages !== '')
                <div class="mt-7 flex flex-wrap items-center gap-6 text-sm" data-anim="fade-up" data-stagger>
                    @if($heroAvailabilityText !== '')
                        <div class="flex items-center gap-2">
                            @if($heroAvailabilityIcon !== '')
                                <i class="fas {{ $heroAvailabilityIcon }} text-emerald-400"></i>
                            @else
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 pulse-dot text-emerald-400/40"></span>
                            @endif
                            <span class="text-gray-200 font-medium">{{ $heroAvailabilityText }}</span>
                        </div>
                    @endif
                    @if($heroLanguages !== '')
                        <div class="text-gray-400"><i class="fas fa-language text-blue-300 mr-1"></i> {{ $heroLanguages }}</div>
                    @endif
                </div>
            @endif
        </div>
        <div data-anim="fade-left" data-tilt="5" class="relative">
            <div class="img-frame img-tilt aspect-[16/10]">
                <img src="{{ $heroSideImage }}" alt="{{ $heroSideImageAlt }}">
            </div>
            @if($showFloatingCard)
                <div class="absolute -bottom-5 -right-5 bg-[#11101c] border border-white/10 rounded-2xl p-3 pr-4 flex items-center gap-3 shadow-2xl float-y">
                    @if($floatingCardIcon !== '')<i class="fas {{ $floatingCardIcon }} text-blue-400 text-lg"></i>@endif
                    <div class="text-xs">
                        @if($floatingCardTitle !== '')<div class="font-semibold text-white">{{ $floatingCardTitle }}</div>@endif
                        @if($floatingCardSubtitle !== '')<div class="text-gray-400">{{ $floatingCardSubtitle }}</div>@endif
                    </div>
                </div>
            @endif
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
            @if($detailsHeading !== '')<h2 class="text-lg font-bold text-white">{{ $detailsHeading }}</h2>@endif
            @if($address !== '')
                <div>
                    <div class="text-[11px] uppercase tracking-wider text-gray-400 mb-1"><i class="fas fa-location-dot mr-1.5"></i>Address</div>
                    <div class="text-sm text-gray-200 leading-relaxed">{!! nl2br(e($address)) !!}</div>
                </div>
            @endif
            @if($email !== '')
                <div>
                    <div class="text-[11px] uppercase tracking-wider text-gray-400 mb-1"><i class="fas fa-envelope mr-1.5"></i>Email</div>
                    <a href="mailto:{{ $email }}" class="text-sm text-blue-300 hover:text-blue-200 break-all">{{ $email }}</a>
                </div>
            @endif
            @if($phone !== '')
                <div>
                    <div class="text-[11px] uppercase tracking-wider text-gray-400 mb-1"><i class="fas fa-phone mr-1.5"></i>Phone</div>
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="text-sm text-blue-300 hover:text-blue-200">{{ $phone }}</a>
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
                                   class="w-9 h-9 rounded-lg bg-white/5 hover:bg-blue-600 border border-white/10 flex items-center justify-center text-gray-300 hover:text-white transition">
                                    <i class="fab {{ $icon }}"></i>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
        <div class="map-card overflow-hidden flex flex-col" data-anim="fade-up">
            <span class="map-card__glow" aria-hidden="true"></span>
            <div class="map-card__viewport flex-1 min-h-[320px] w-full bg-white/5">
                <span class="map-card__corner map-card__corner--tl" aria-hidden="true"></span>
                <span class="map-card__corner map-card__corner--tr" aria-hidden="true"></span>
                <span class="map-card__corner map-card__corner--bl" aria-hidden="true"></span>
                <span class="map-card__corner map-card__corner--br" aria-hidden="true"></span>
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
            <div class="map-card__footer px-4 py-3 flex items-center justify-between text-xs text-gray-300">
                <span class="inline-flex items-center gap-1.5">
                    <i class="fas fa-location-dot text-blue-300/80 text-[11px]"></i>
                    {{ $mapLabel ?: 'Find us on OpenStreetMap' }}
                </span>
                <a href="{{ $mapLargerUrl }}" target="_blank" rel="noopener" class="map-card__footer-link font-semibold">
                    View larger map <i class="fas fa-up-right-from-square map-card__footer-arrow text-[10px]"></i>
                </a>
            </div>
        </div>
    </div>
</section>

@if(!empty($featureCards))
<section class="pb-12">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 grid md:grid-cols-3 gap-5" data-anim="fade-up" data-stagger>
        @foreach($featureCards as $card)
            @php
                $cIcon  = trim((string)($card['icon']  ?? ''));
                $cTitle = trim((string)($card['title'] ?? ''));
                $cDesc  = trim((string)($card['desc']  ?? ''));
            @endphp
            <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5 hover:border-blue-400/40 transition">
                <div class="w-10 h-10 rounded-lg bg-blue-500/15 border border-blue-400/30 flex items-center justify-center text-blue-300 mb-3"><i class="fas {{ $cIcon !== '' ? $cIcon : 'fa-circle-dot' }}"></i></div>
                @if($cTitle !== '')<div class="text-sm font-bold text-white">{{ $cTitle }}</div>@endif
                @if($cDesc !== '')<div class="text-xs text-gray-400 mt-1">{{ $cDesc }}</div>@endif
            </div>
        @endforeach
    </div>
</section>
@endif

<section class="pb-20">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 grid md:grid-cols-[1fr_1fr] gap-6 items-start">
        @if($officeImageUrl !== '')
            <div class="img-frame aspect-[4/3] hidden md:block" data-anim="fade-right" data-tilt="4">
                <img src="{{ $officeImageUrl }}" alt="{{ $officeImageAlt }}">
            </div>
        @else
            {{-- Keep the 2-col grid even if the image is intentionally blank. --}}
            <div class="hidden md:block"></div>
        @endif
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
            @if($formHeading !== '')<h2 class="text-lg font-bold text-white">{{ $formHeading }}</h2>@endif
            @if($formIntro !== '')<p class="text-sm text-gray-400 leading-relaxed -mt-1">{{ $formIntro }}</p>@endif
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    @if($formNameLabel !== '')<label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400">{{ $formNameLabel }}</label>@endif
                    <input type="text" name="name" required value="{{ old('name') }}" @if($formNamePlaceholder !== '') placeholder="{{ $formNamePlaceholder }}" @endif class="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                </div>
                <div>
                    @if($formEmailLabel !== '')<label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400">{{ $formEmailLabel }}</label>@endif
                    <input type="email" name="email" required value="{{ old('email') }}" @if($formEmailPlaceholder !== '') placeholder="{{ $formEmailPlaceholder }}" @endif class="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                </div>
            </div>
            @auth
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400">Topic</label>
                <select name="topic" class="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
                    <option value="" class="bg-slate-900" {{ old('topic') === 'badge_request' ? '' : 'selected' }}>General enquiry</option>
                    <option value="badge_request" class="bg-slate-900" {{ old('topic') === 'badge_request' ? 'selected' : '' }}>Badge request</option>
                </select>
                <p class="text-[11px] mt-1 text-gray-500">Pick “Badge request” to ask our team for an account badge — it goes straight to the review queue.</p>
            </div>
            @endauth
            <div>
                @if($formSubjectLabel !== '')<label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400">{{ $formSubjectLabel }}</label>@endif
                <input type="text" name="subject" required value="{{ old('subject') }}" @if($formSubjectPlaceholder !== '') placeholder="{{ $formSubjectPlaceholder }}" @endif class="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
            </div>
            <div>
                @if($formMessageLabel !== '')<label class="block text-xs font-semibold uppercase tracking-wider mb-1.5 text-gray-400">{{ $formMessageLabel }}</label>@endif
                <textarea name="message" required rows="6" @if($formMessagePlaceholder !== '') placeholder="{{ $formMessagePlaceholder }}" @endif class="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white">{{ old('message') }}</textarea>
            </div>
            <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm font-bold text-white transition">
                {{ $formSubmitLabel !== '' ? $formSubmitLabel : 'Send message' }}
            </button>
        </form>
        </div>
    </div>
</section>

<section class="pb-10">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8" data-anim="fade-up">
        <a href="{{ route('site.pricing') }}#custom-plan-request"
           class="group flex items-center gap-4 p-5 rounded-2xl bg-white/[0.025] border border-white/10 hover:border-blue-400/30 transition-all">
            <div class="w-10 h-10 shrink-0 rounded-xl flex items-center justify-center"
                 style="background:rgba(61,107,255,0.15);border:1px solid rgba(61,107,255,0.3);">
                <i class="fas fa-gem text-blue-400"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-bold text-white">Enquire about a custom plan</div>
                <div class="text-xs text-gray-400 mt-0.5">Need more links, extra storage, or a bespoke feature set? Tell us your requirements and we'll put together a tailored offer.</div>
            </div>
            <i class="fas fa-arrow-right text-blue-400/50 group-hover:text-blue-300 transition text-xs shrink-0"></i>
        </a>
    </div>
</section>

<section class="pb-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8" data-anim="fade-up">
        <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-6 sm:p-8">
            <div class="flex items-center gap-2 text-[11px] uppercase tracking-wider text-emerald-300 font-semibold mb-2">
                <i class="fas fa-user-shield"></i> Your privacy
            </div>
            <h2 class="text-xl font-bold text-white">Manage your personal data</h2>
            <p class="text-sm text-gray-400 mt-1.5 max-w-2xl leading-relaxed">
                Under data-protection laws such as the GDPR and CCPA, you can ask us to permanently delete your
                account or send you a full copy of the data we hold about you. We verify every request before acting on it.
            </p>
            <div class="mt-5 grid sm:grid-cols-2 gap-4">
                <a href="{{ route('privacy.request', ['type' => 'export']) }}"
                   class="group flex items-start gap-3 p-4 rounded-xl bg-white/[0.02] border border-white/10 hover:border-blue-400/40 transition">
                    <div class="w-10 h-10 shrink-0 rounded-lg bg-blue-500/15 border border-blue-400/30 flex items-center justify-center text-blue-300"><i class="fas fa-download"></i></div>
                    <div>
                        <div class="text-sm font-bold text-white">Export my data</div>
                        <div class="text-xs text-gray-400 mt-0.5">Get a downloadable archive of your account and files.</div>
                    </div>
                </a>
                <a href="{{ route('privacy.request', ['type' => 'deletion']) }}"
                   class="group flex items-start gap-3 p-4 rounded-xl bg-white/[0.02] border border-white/10 hover:border-red-400/40 transition">
                    <div class="w-10 h-10 shrink-0 rounded-lg bg-red-500/15 border border-red-400/30 flex items-center justify-center text-red-300"><i class="fas fa-user-slash"></i></div>
                    <div>
                        <div class="text-sm font-bold text-white">Delete my account</div>
                        <div class="text-xs text-gray-400 mt-0.5">Permanently remove your account and personal data.</div>
                    </div>
                </a>
            </div>
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
{{-- Leaflet is self-hosted (public/css/vendor + public/js/vendor) to avoid the
     brittle CDN Subresource-Integrity mismatch that previously blocked the map. --}}
<link rel="stylesheet" href="{{ asset('css/vendor/leaflet.min.css') }}" />
<style>
    /* ---- Map card shell ---------------------------------------------------- */
    .map-card {
        position: relative;
        border-radius: 1.25rem;
        background: rgba(61,107,255,0.06);
        border: 1px solid rgba(144,172,255,0.30);
        box-shadow:
            0 1px 0 rgba(255,255,255,0.06) inset,
            0 24px 60px -30px rgba(61,107,255,0.55),
            0 18px 50px -28px rgba(0,0,0,0.7);
        transition: transform .45s cubic-bezier(.2,.7,.2,1), box-shadow .45s cubic-bezier(.2,.7,.2,1);
    }
    .map-card:hover {
        transform: translateY(-4px);
        box-shadow:
            0 1px 0 rgba(255,255,255,0.08) inset,
            0 30px 70px -28px rgba(61,107,255,0.7),
            0 22px 60px -26px rgba(0,0,0,0.75);
    }
    .map-card__glow {
        position:absolute; inset:-1px; border-radius:inherit; pointer-events:none;
        background: rgba(144,172,255,0.10);
        opacity:0; transition: opacity .45s ease;
    }
    .map-card:hover .map-card__glow { opacity:1; }
    .map-card__viewport {
        position:relative; overflow:hidden;
        border-radius: 1.1rem 1.1rem 0 0;
    }
    /* Sheen sweep that crosses the map once on hover. */
    .map-card__viewport::after {
        content:""; position:absolute; top:0; bottom:0; left:-60%; width:45%;
        background: rgba(255,255,255,0.06);
        transform: skewX(-18deg); pointer-events:none; z-index:500; opacity:0;
    }
    .map-card:hover .map-card__viewport::after {
        animation: map-sheen 1.1s ease forwards;
    }
    @keyframes map-sheen {
        0%   { left:-60%; opacity:0; }
        15%  { opacity:1; }
        100% { left:130%; opacity:0; }
    }
    /* Corner gradient accents framing the live map. */
    .map-card__corner {
        position:absolute; width:30px; height:30px; z-index:500; pointer-events:none;
        border-color: rgba(144,172,255,0.65); border-style:solid; border-width:0;
    }
    .map-card__corner--tl { top:10px; left:10px; border-top-width:2px; border-left-width:2px; border-top-left-radius:8px; }
    .map-card__corner--tr { top:10px; right:10px; border-top-width:2px; border-right-width:2px; border-top-right-radius:8px; }
    .map-card__corner--bl { bottom:10px; left:10px; border-bottom-width:2px; border-left-width:2px; border-bottom-left-radius:8px; }
    .map-card__corner--br { bottom:10px; right:10px; border-bottom-width:2px; border-right-width:2px; border-bottom-right-radius:8px; }
    .map-card__footer {
        position:relative;
        background: rgba(61,107,255,0.05);
        border-top: 1px solid rgba(255,255,255,0.08);
    }
    .map-card__footer-link {
        display:inline-flex; align-items:center; gap:.35rem;
        color:#bccfff; transition: color .2s ease, transform .2s ease;
    }
    .map-card__footer-link:hover { color:#dbe4ff; }
    .map-card__footer-link:hover .map-card__footer-arrow { transform: translate(2px,-2px); }
    .map-card__footer-arrow { transition: transform .2s ease; }

    /* ---- Leaflet theming --------------------------------------------------- */
    #contact-map { background:#1e2330; }
    .leaflet-container { background:#1e2330 !important; font-family:'Space Grotesk', sans-serif; }
    /* In light mode the loading backdrop should read light too. */
    html.light-mode #contact-map { background:#e6e9f0; }
    html.light-mode .leaflet-container { background:#e6e9f0 !important; }
    /* Tile theming follows the active site theme (toggling .light-mode on <html>).
       Dark mode: invert + hue-rotate the light OSM raster so it reads as a dark map.
       Light mode: keep tiles light with a subtle brand-leaning tint. */
    .map-card .leaflet-tile-pane {
        filter: invert(1) hue-rotate(180deg) brightness(0.94) contrast(0.9) saturate(0.85);
        transition: filter .4s ease;
    }
    html.light-mode .map-card .leaflet-tile-pane {
        filter: saturate(0.92) brightness(0.98) contrast(1.02);
    }
    .leaflet-control-attribution { background:rgba(30,35,48,0.85) !important; color:#9ca3af !important; }
    .leaflet-control-attribution a { color:#90acff !important; }
    .leaflet-control-zoom { border:none !important; box-shadow:0 8px 24px -12px rgba(0,0,0,0.8) !important; }
    .leaflet-control-zoom a {
        background:rgba(30,35,48,0.92) !important; color:#fff !important;
        border-color:rgba(255,255,255,0.12) !important;
        backdrop-filter: blur(8px); transition: background .2s ease, color .2s ease;
    }
    .leaflet-control-zoom a:first-child { border-top-left-radius:10px; border-top-right-radius:10px; }
    .leaflet-control-zoom a:last-child { border-bottom-left-radius:10px; border-bottom-right-radius:10px; }
    .leaflet-control-zoom a:hover { background:#3d6bff !important; }
    .brand-marker {
        width:34px; height:44px; position:relative;
        filter: drop-shadow(0 4px 6px rgba(0,0,0,0.45));
        animation: brand-marker-drop .55s cubic-bezier(.2,1.3,.4,1) both;
    }
    .brand-marker svg { width:100%; height:100%; display:block; }
    .brand-marker .pulse {
        position:absolute; left:50%; bottom:-4px; width:14px; height:14px;
        margin-left:-7px; border-radius:9999px;
        background:rgba(61,107,255,0.55);
        animation: brand-marker-pulse 1.8s ease-out infinite;
    }
    .brand-marker .pulse.pulse--delay { animation-delay:.9s; }
    @keyframes brand-marker-pulse {
        0% { transform:scale(0.6); opacity:0.9; }
        100% { transform:scale(2.6); opacity:0; }
    }
    @keyframes brand-marker-drop {
        0% { transform: translateY(-18px) scale(0.7); opacity:0; }
        100% { transform: translateY(0) scale(1); opacity:1; }
    }
    .brand-popup .leaflet-popup-content-wrapper {
        background:#1e2230; color:#fff;
        border:1px solid rgba(144,172,255,0.30);
        border-radius:14px; box-shadow:0 18px 40px -20px rgba(61,107,255,0.7);
    }
    .brand-popup .leaflet-popup-tip { background:#1c2030; border:1px solid rgba(144,172,255,0.30); }
    .brand-popup .leaflet-popup-content { margin:11px 15px; font-size:13px; line-height:1.45; }
    .brand-popup .leaflet-popup-content .bp-eyebrow {
        display:flex; align-items:center; gap:.4rem; font-size:10px; letter-spacing:.08em;
        text-transform:uppercase; color:#90acff; margin-bottom:3px; font-weight:600;
    }
    .brand-popup a.leaflet-popup-close-button { color:#9ca3af !important; }
    .brand-popup a.leaflet-popup-close-button:hover { color:#fff !important; }

    @media (prefers-reduced-motion: reduce) {
        .map-card, .map-card:hover { transition:none; transform:none; }
        .map-card__viewport::after,
        .map-card:hover .map-card__viewport::after { animation:none; opacity:0; }
        .brand-marker { animation:none; }
        .brand-marker .pulse { animation:none; opacity:0; }
        .map-card__footer-link, .map-card__footer-arrow { transition:none; }
        .map-card .leaflet-tile-pane { transition:none; }
    }
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/vendor/leaflet.min.js') }}" defer></script>
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
            '<stop offset="0%" stop-color="#90acff"/><stop offset="100%" stop-color="#3d6bff"/>' +
            '</linearGradient></defs>' +
            '<path d="M17 0C7.6 0 0 7.5 0 16.7c0 11.7 14.6 25.5 16 26.8.6.6 1.5.6 2 0 1.5-1.3 16-15.1 16-26.8C34 7.5 26.4 0 17 0z" fill="url(#bm-g)" stroke="rgba(255,255,255,0.85)" stroke-width="1.5"/>' +
            '<circle cx="17" cy="16" r="6" fill="#fff"/>' +
            '<text x="17" y="19.5" text-anchor="middle" font-family="Space Grotesk, sans-serif" font-size="8" font-weight="700" fill="#3d6bff">1</text>' +
            '</svg>';

        var icon = L.divIcon({
            className: '',
            html: '<div class="brand-marker"><span class="pulse"></span><span class="pulse pulse--delay"></span>' + pinSvg + '</div>',
            iconSize: [34, 44],
            iconAnchor: [17, 44],
            popupAnchor: [0, -42]
        });

        var marker = L.marker([lat, lng], { icon: icon, title: label || 'Sayzio' }).addTo(map);
        if (label) {
            var safeLabel = label.replace(/[<>&]/g, function(c){
                return {'<':'&lt;','>':'&gt;','&':'&amp;'}[c];
            });
            marker.bindPopup(
                '<span class="bp-eyebrow"><i class="fas fa-location-dot"></i> Sayzio</span>' +
                '<strong>' + safeLabel + '</strong>',
                { className: 'brand-popup' }
            ).openPopup();
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
