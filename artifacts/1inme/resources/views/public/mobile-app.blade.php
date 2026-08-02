@extends('public.layouts.site')
@section('title', 'Sayzio Mobile App')

@section('content')
{{-- Standalone mobile-app marketing page (/app). Store CTAs render via the
     shared store-buttons partial (admin-managed Play / App Store URLs with a
     "coming soon" modal fallback); a direct-APK link from
     ProductDownloadLinks::app() is added when available. --}}
<style>
    /* Scoped map-* classes with explicit colors inside the always-dark phone
       mockup so light-mode remaps can't darken them. */
    .map-phone {
        position: relative; width: 270px; max-width: 80vw; margin: 0 auto;
        aspect-ratio: 270 / 552; border-radius: 40px; padding: 11px;
        background: linear-gradient(160deg, #10182f, #080a14);
        box-shadow: 0 40px 90px -30px rgba(61,107,255,.55), 0 14px 34px -12px rgba(0,0,0,.7), inset 0 0 0 1.5px rgba(255,255,255,.08);
    }
    .map-screen { position:absolute; inset:11px; border-radius:31px; overflow:hidden; background: linear-gradient(180deg, #0e1426 0%, #090a12 100%); display:flex; flex-direction:column; color:#fff; }
    .map-notch { position:absolute; top:8px; left:50%; transform:translateX(-50%); width:78px; height:18px; border-radius:999px; background:#04060c; z-index:5; }
    .map-status { display:flex; align-items:center; justify-content:space-between; padding:11px 18px 0; font-size:10px; font-weight:700; color:#8f9bb8; letter-spacing:.04em; }
    .map-head { padding: 16px 18px 8px; }
    .map-head .h { font-size:15px; font-weight:800; color:#fff; }
    .map-head .s { font-size:10px; color:#8f9bb8; font-weight:600; margin-top:2px; }
    .map-stats { display:grid; grid-template-columns:1fr 1fr; gap:8px; padding: 6px 16px; }
    .map-stat { border-radius: 12px; padding: 10px 12px; background: rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.08); }
    .map-stat .t { font-size:8.5px; font-weight:800; color:#8f9bb8; text-transform:uppercase; letter-spacing:.08em; }
    .map-stat .v { font-size:16px; font-weight:800; color:#fff; margin-top:2px; }
    .map-links { padding: 8px 16px; display:flex; flex-direction:column; gap:7px; }
    .map-link { display:flex; align-items:center; gap:9px; padding: 9px 11px; border-radius: 12px; background: rgba(255,255,255,.045); border:1px solid rgba(255,255,255,.08); }
    .map-link .ic { width:28px; height:28px; border-radius:9px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:11px; flex-shrink:0; }
    .map-link .n { font-size:11px; font-weight:700; color:#fff; }
    .map-link .m { font-size:9px; color:#8f9bb8; font-weight:600; }
    .map-link .c { margin-left:auto; font-size:10px; font-weight:800; color:#34d399; }
    .map-tabbar { margin-top:auto; display:flex; justify-content:space-around; padding: 10px 12px 14px; border-top:1px solid rgba(255,255,255,.07); }
    .map-tabbar i { font-size:14px; color:#5b6885; }
    .map-tabbar i.on { color:#3d6bff; }
    @media (prefers-reduced-motion: no-preference) {
        .map-phone { animation: mapFloat 6.5s ease-in-out infinite; }
        @keyframes mapFloat { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-12px); } }
    }
    .map-mesh::before {
        content:""; position:absolute; inset:-15%;
        background: rgba(61,107,255,.06); filter: blur(40px); pointer-events:none;
    }
    .map-faq-card { background: rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.09); }
    html.light-mode .map-faq-card { background:#ffffff; border-color: rgba(15,23,42,.1); }
    /* Real-photo treatment (light-mode paired) */
    .map-photo { width:100%; height:auto; border-radius:20px; border:1px solid rgba(255,255,255,.1); box-shadow: 0 30px 70px -30px rgba(61,107,255,.4); }
    html.light-mode .map-photo { border-color: rgba(15,23,42,.12); box-shadow: 0 30px 70px -30px rgba(61,107,255,.28); }
</style>

{{-- ============ Hero ============ --}}
<section class="relative overflow-hidden map-mesh pt-16 sm:pt-24 pb-16 sm:pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-12 lg:gap-8 items-center relative">
        <div data-anim="fade-right">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider"
                  style="background: rgba(61,107,255,.14); color:#6d92ff; border:1px solid rgba(61,107,255,.35);">
                <i class="fas fa-mobile-screen-button text-[10px]"></i> Sayzio mobile app
            </span>
            <h1 class="mt-4 text-4xl sm:text-5xl font-bold leading-[1.08] text-white">
                Your whole link business, <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#3d6bff] via-[#6e61ff] to-[#22d3ee]">in your pocket</span>
            </h1>
            <p class="mt-5 text-lg text-gray-400 leading-relaxed max-w-xl">
                Edit your Link in Bio, spin up short links and QR codes, answer your audience,
                and watch clicks roll in live, the full Sayzio experience, rebuilt for
                Android and iPhone.
            </p>
            <div class="mt-8">
                @include('public.partials.store-buttons')
            </div>
            @if(($cta['apk'] ?? '') !== '')
                <p class="mt-4 text-xs text-gray-500">
                    On Android without Play access?
                    <a href="{{ $cta['apk'] }}" class="underline hover:text-gray-300">Download the APK directly</a>.
                </p>
            @endif
        </div>
        <div data-anim="fade-left" class="relative flex justify-center">
            <div class="map-phone">
                <div class="map-notch" aria-hidden="true"></div>
                <div class="map-screen" role="img" aria-label="Sayzio mobile app dashboard with live stats and a links list">
                    <div class="map-status"><span>9:41</span><span><i class="fas fa-signal"></i> <i class="fas fa-wifi"></i> <i class="fas fa-battery-three-quarters"></i></span></div>
                    <div class="map-head">
                        <span class="h block">Good morning, Alex</span>
                        <span class="s block">sayz.io/@alexmakes</span>
                    </div>
                    <div class="map-stats" aria-hidden="true">
                        <div class="map-stat"><span class="t block">Clicks today</span><span class="v block">1,204</span></div>
                        <div class="map-stat"><span class="t block">Live now</span><span class="v block">23</span></div>
                    </div>
                    <div class="map-links" aria-hidden="true">
                        <div class="map-link">
                            <span class="ic" style="background: linear-gradient(135deg, #3d6bff, #6e61ff, #22d3ee);"><i class="fas fa-id-badge"></i></span>
                            <span><span class="n block">Link in Bio</span><span class="m block">@alexmakes</span></span>
                            <span class="c">+312</span>
                        </div>
                        <div class="map-link">
                            <span class="ic" style="background: linear-gradient(135deg, #16a34a, #22c55e);"><i class="fas fa-qrcode"></i></span>
                            <span><span class="n block">Menu QR</span><span class="m block">Table 4 · scans</span></span>
                            <span class="c">+96</span>
                        </div>
                        <div class="map-link">
                            <span class="ic" style="background: linear-gradient(135deg, #f59e0b, #f97316);"><i class="fas fa-link"></i></span>
                            <span><span class="n block">Launch link</span><span class="m block">sayz.io/drop</span></span>
                            <span class="c">+788</span>
                        </div>
                    </div>
                    <div class="map-tabbar" aria-hidden="true">
                        <i class="fas fa-house on"></i>
                        <i class="fas fa-link"></i>
                        <i class="fas fa-qrcode"></i>
                        <i class="fas fa-chart-line"></i>
                        <i class="fas fa-user"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============ In real life (photo band) ============ --}}
<section class="py-16 sm:py-20 relative">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-10 lg:gap-14 items-center">
        <div data-anim="fade-right">
            <img src="{{ asset('images/marketing/app/hero-creator.webp') }}"
                 alt="A creator in a cafe checking her Sayzio analytics on her phone"
                 loading="lazy" decoding="async" class="map-photo">
        </div>
        <div data-anim="fade-left">
            <span class="text-xs font-bold uppercase tracking-wider text-blue-300">Your links, off the laptop</span>
            <h2 class="mt-3 text-3xl sm:text-4xl font-bold text-white">Run your whole page from a coffee break</h2>
            <p class="mt-4 text-gray-400 leading-relaxed">
                Your audience doesn't wait for you to get back to a desk, and with the Sayzio app you
                don't have to. Edit any block on your biolink, swap a headline, publish a new link or
                pause an old one, and watch the change go live before your coffee cools.
            </p>
            <p class="mt-4 text-gray-400 leading-relaxed">
                The same account, the same pages, the same analytics as the web dashboard, the app talks
                to your account through the Sayzio API, so everything you do on your phone is instantly
                reflected everywhere else.
            </p>
            <ul class="mt-6 space-y-3">
                @foreach([
                    'Full biolink editor with drag-to-reorder blocks and inline editing',
                    'Live click, scan and visitor analytics the moment they happen',
                    'Push notifications for new followers, orders, form entries and reviews',
                ] as $point)
                    <li class="flex items-start gap-3 text-sm text-gray-300">
                        <i class="fas fa-circle-check text-blue-400 mt-0.5"></i> {{ $point }}
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</section>

{{-- ============ Feature grid ============ --}}
<section class="py-16 sm:py-20 relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto" data-anim="fade-up">
            <h2 class="text-3xl sm:text-4xl font-bold text-white">Not a companion app. The real thing.</h2>
            <p class="mt-3 text-gray-400">Full parity with the web, create, edit, moderate and measure anywhere.</p>
        </div>
        <div class="mt-12 grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach([
                ['fa-id-badge', 'Edit your Link in Bio', 'Rearrange blocks, swap images and restyle your page with a live preview, changes publish instantly.'],
                ['fa-link', 'Links & QR codes', 'Create short links and fully styled QR codes on the go, with the same domains and designs as the web.'],
                ['fa-chart-line', 'Live analytics', 'Watch clicks, scans, cities and referrers update in real time, with the same charts you know from the dashboard.'],
                ['fa-inbox', 'Inbox & audience', 'Reply to messages, review followers and subscribers, and moderate reviews without opening a laptop.'],
                ['fa-bell', 'Smart notifications', 'Order requests, new subscribers, milestone alerts, pushed the moment they happen, tuned per channel.'],
                ['fa-wand-magic-sparkles', 'AI on the go', 'Generate pages, tailor your résumé or draft replies with the same AI tools as the web app.'],
            ] as [$icon, $title, $desc])
                <div class="glass rounded-2xl p-6" data-anim="fade-up">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-500/10 text-blue-300">
                        <i class="fas {{ $icon }}"></i>
                    </span>
                    <h3 class="mt-4 text-base font-bold text-white">{{ $title }}</h3>
                    <p class="mt-1.5 text-sm text-gray-400 leading-relaxed">{{ $desc }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============ Photo gallery: a day with the app ============ --}}
<section class="py-16 sm:py-20 relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto" data-anim="fade-up">
            <h2 class="text-3xl sm:text-4xl font-bold text-white">A day with Sayzio in your pocket</h2>
            <p class="mt-3 text-gray-400">Wherever the day takes you, your links, pages and people come along.</p>
        </div>
        <div class="mt-12 grid md:grid-cols-3 gap-6">
            @foreach([
                ['images/marketing/app/analytics-hand.webp', 'A hand holding a phone showing live click and scan analytics',
                 'Morning: check the numbers', 'Open the app to live clicks, QR scans and visitor trends from the last 24 hours, the same analytics engine as the web dashboard, down to referrers and locations.'],
                ['images/marketing/app/restaurant.webp', 'A cafe owner at the counter with a QR table tent and the Sayzio app',
                 'Midday: run the business', 'Restaurant and store owners watch orders arrive in real time, move them through preparing and served, and pause ordering with one tap when the rush hits.'],
                ['images/marketing/app/inbox-couch.webp', 'A man on a couch in the evening replying to his audience from his phone',
                 'Evening: answer your people', 'New followers, messages, form entries and reviews land in the app with push notifications, reply from the couch instead of catching up at midnight.'],
            ] as [$img, $alt, $title, $desc])
                <div class="glass rounded-2xl overflow-hidden" data-anim="fade-up">
                    <img src="{{ asset($img) }}" alt="{{ $alt }}" loading="lazy" decoding="async" class="w-full aspect-[4/3] object-cover">
                    <div class="p-6">
                        <h3 class="text-base font-bold text-white">{{ $title }}</h3>
                        <p class="mt-1.5 text-sm text-gray-400 leading-relaxed">{{ $desc }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============ FAQs ============ --}}
<section class="py-16 sm:py-20">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center" data-anim="fade-up">
            <h2 class="text-3xl sm:text-4xl font-bold text-white">The app, answered</h2>
        </div>
        <div class="mt-10 space-y-3" x-data="{ open: null }">
            @foreach([
                ['Is the app free?', 'Yes, download and sign in with your existing Sayzio account. Your plan works identically on mobile and web.'],
                ['Android and iPhone?', 'Both. The app ships for Android (Google Play, plus a direct APK) and iPhone via the App Store.'],
                ['Does it do everything the website does?', 'Nearly everything, editing pages, links, QR codes, analytics, inbox, orders and settings all have full mobile parity. A few admin-only tools remain web-first.'],
                ['Will my edits sync?', 'Instantly. The app talks to the same account as the web dashboard, so a change on one appears on the other in seconds.'],
                ['Is Zio Dialer included?', 'Zio Dialer is a separate Android app focused on calling. The Sayzio app includes contacts and the universal finder; install both for the full experience.'],
            ] as $i => [$q, $a])
                <div class="map-faq-card rounded-2xl overflow-hidden" data-anim="fade-up">
                    <button type="button" @click="open === {{ $i }} ? open = null : open = {{ $i }}"
                            :aria-expanded="open === {{ $i }}"
                            class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left">
                        <span class="text-sm font-semibold text-white">{{ $q }}</span>
                        <i class="fas fa-chevron-down text-xs text-gray-500 transition-transform" :class="open === {{ $i }} ? 'rotate-180' : ''"></i>
                    </button>
                    <div x-show="open === {{ $i }}" x-collapse x-cloak>
                        <p class="px-5 pb-4 text-sm text-gray-400 leading-relaxed">{{ $a }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============ Bottom CTA ============ --}}
<section class="py-16 sm:py-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grad-border rounded-3xl p-8 sm:p-12 text-center relative overflow-hidden" data-anim="fade-up">
            <h2 class="text-3xl sm:text-4xl font-bold text-white">Take Sayzio with you</h2>
            <p class="mt-3 text-gray-400 max-w-xl mx-auto">Free on Android and iPhone. Your account, everywhere.</p>
            <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                @include('public.partials.store-buttons')
            </div>
        </div>
    </div>
</section>
@endsection
