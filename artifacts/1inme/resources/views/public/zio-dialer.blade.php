@extends('public.layouts.site')
@section('title', 'Zio Dialer')

@section('content')
{{-- Standalone Zio Dialer product page (/dialer). Download CTAs come from
     ProductDownloadLinks::dialer() — admin-managed Play Store / APK URLs with
     automatic fallback to the live APK manager release. Buttons whose URL is
     unresolvable are hidden (never render dead links). --}}
<style>
    /* Scoped zdp-* classes with explicit colors inside the always-dark phone
       mockups so the global html.light-mode remap can never darken them. */
    .zdp-phone {
        position: relative; width: 270px; max-width: 80vw; margin: 0 auto;
        aspect-ratio: 270 / 552; border-radius: 40px; padding: 11px;
        background: linear-gradient(160deg, #10182f, #080a14);
        box-shadow: 0 40px 90px -30px rgba(61,107,255,.55), 0 14px 34px -12px rgba(0,0,0,.7), inset 0 0 0 1.5px rgba(255,255,255,.08);
    }
    .zdp-screen { position:absolute; inset:11px; border-radius:31px; overflow:hidden; background: linear-gradient(180deg, #0e1426 0%, #090a12 100%); display:flex; flex-direction:column; color:#fff; }
    .zdp-notch { position:absolute; top:8px; left:50%; transform:translateX(-50%); width:78px; height:18px; border-radius:999px; background:#04060c; z-index:5; }
    .zdp-status { display:flex; align-items:center; justify-content:space-between; padding:11px 18px 0; font-size:10px; font-weight:700; color:#8f9bb8; letter-spacing:.04em; }
    .zdp-num { margin: auto 18px 4px; text-align:center; font-size:22px; font-weight:600; color:#fff; letter-spacing:.06em; font-variant-numeric: tabular-nums; min-height:30px; }
    .zdp-chip {
        display:inline-flex; align-items:center; gap:6px; margin: 0 auto 6px;
        padding:4px 11px; border-radius:999px;
        background: rgba(61,107,255,.16); border:1px solid rgba(61,107,255,.4);
        font-size:10px; font-weight:700; color:#dbe4ff;
    }
    .zdp-chip i { color:#7a9eff; font-size:9px; }
    .zdp-keys { margin:10px 20px 12px; display:grid; grid-template-columns:repeat(3,1fr); gap:9px; }
    .zdp-key { aspect-ratio:1; border-radius:999px; background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.08); display:flex; flex-direction:column; align-items:center; justify-content:center; color:#fff; line-height:1; }
    .zdp-key .d { font-size:17px; font-weight:700; }
    .zdp-key .l { font-size:6.5px; letter-spacing:.12em; color:rgba(255,255,255,.45); margin-top:2px; }
    .zdp-call {
        margin: 0 20px 16px; height:44px; border-radius:999px;
        background: linear-gradient(135deg, #16a34a, #22c55e);
        box-shadow: 0 12px 26px -12px rgba(34,197,94,.8);
        display:flex; align-items:center; justify-content:center; gap:7px;
        color:#fff; font-weight:700; font-size:12.5px;
    }
    /* Caller-ID overlay card floating next to the phone */
    .zdp-cid {
        position:absolute; right:-24px; top:22%; width:210px; z-index:6;
        border-radius:18px; padding:14px;
        background: rgba(14,20,38,.96); border:1px solid rgba(61,107,255,.35);
        box-shadow: 0 24px 60px -18px rgba(61,107,255,.6);
        color:#fff;
    }
    @media (max-width: 480px) { .zdp-cid { right: -6px; width: 185px; } }
    .zdp-cid-name { font-size:13px; font-weight:800; color:#fff; display:flex; align-items:center; gap:5px; }
    .zdp-cid-name i { color:#3d6bff; font-size:11px; }
    .zdp-cid-sub { font-size:10px; color:#a8b3cf; font-weight:600; margin-top:2px; }
    .zdp-cid-ava {
        width:38px; height:38px; border-radius:13px; flex-shrink:0;
        background: linear-gradient(135deg, #3d6bff, #6e61ff, #22d3ee);
        display:flex; align-items:center; justify-content:center;
        color:#fff; font-weight:800; font-size:14px;
    }
    .zdp-cid-chans { display:flex; gap:6px; margin-top:10px; }
    .zdp-cid-chan { flex:1; height:26px; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:10px; }
    @media (prefers-reduced-motion: no-preference) {
        .zdp-phone { animation: zdpFloat 6.5s ease-in-out infinite; }
        @keyframes zdpFloat { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-12px); } }
        .zdp-cid { animation: zdpCid 6.5s ease-in-out infinite; }
        @keyframes zdpCid { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
    }
    .zdp-mesh::before {
        content:""; position:absolute; inset:-15%;
        background: rgba(61,107,255,.06); filter: blur(40px); pointer-events:none;
    }
    /* Feature cards keep the shared glass treatment; light-mode pairing for
       the few explicit surfaces on this page. */
    .zdp-faq-card { background: rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.09); }
    html.light-mode .zdp-faq-card { background:#ffffff; border-color: rgba(15,23,42,.1); }
    /* Real-photo treatment (light-mode paired) */
    .zdp-photo { width:100%; height:auto; border-radius:20px; border:1px solid rgba(255,255,255,.1); box-shadow: 0 30px 70px -30px rgba(61,107,255,.4); }
    html.light-mode .zdp-photo { border-color: rgba(15,23,42,.12); box-shadow: 0 30px 70px -30px rgba(61,107,255,.28); }
</style>

{{-- ============ Hero ============ --}}
<section class="relative overflow-hidden zdp-mesh pt-16 sm:pt-24 pb-16 sm:pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-12 lg:gap-8 items-center relative">
        <div data-anim="fade-right">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider"
                  style="background: rgba(61,107,255,.14); color:#6d92ff; border:1px solid rgba(61,107,255,.35);">
                <i class="fas fa-phone text-[10px]"></i> Zio Dialer, for Android
            </span>
            <h1 class="mt-4 text-4xl sm:text-5xl font-bold leading-[1.08] text-white">
                The dialer that knows <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#3d6bff] via-[#6e61ff] to-[#22d3ee]">who's calling</span>
            </h1>
            <p class="mt-5 text-lg text-gray-400 leading-relaxed max-w-xl">
                Zio Dialer replaces your stock phone app with T9 smart search, a caller-ID overlay
                powered by Sayzio profiles, quick call / SMS / WhatsApp channels and two-way
                Google Contacts sync, all in one beautiful, fast dialer.
            </p>
            <div class="mt-8 flex flex-wrap items-center gap-3">
                @if($cta['play'] !== '')
                    <a href="{{ $cta['play'] }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-2.5 px-6 py-3 rounded-full text-sm font-bold text-white bg-[#3d6bff] hover:bg-[#2342c7] transition-colors">
                        <i class="fab fa-google-play"></i> Get it on Google Play
                    </a>
                @endif
                @if($cta['apk'] !== '')
                    <a href="{{ $cta['apk'] }}" @if(!str_starts_with($cta['apk'], url('/'))) target="_blank" rel="noopener noreferrer" @endif
                       class="inline-flex items-center gap-2.5 px-6 py-3 rounded-full text-sm font-bold border transition-colors {{ $cta['play'] !== '' ? 'text-gray-200 border-white/20 hover:border-[#3d6bff]/60 hover:text-white' : 'text-white bg-[#3d6bff] border-transparent hover:bg-[#2342c7]' }}">
                        <i class="fab fa-android"></i> Download APK
                    </a>
                @endif
                @if($cta['play'] === '' && $cta['apk'] === '')
                    <button type="button"
                            onclick="window.dispatchEvent(new CustomEvent('open-store-coming-soon',{detail:{store:'play'}}))"
                            class="inline-flex items-center gap-2.5 px-6 py-3 rounded-full text-sm font-bold text-white bg-[#3d6bff] hover:bg-[#2342c7] transition-colors">
                        <i class="fab fa-google-play"></i> Coming soon, notify me
                    </button>
                @endif
            </div>
            <p class="mt-4 text-xs text-gray-500"><i class="fas fa-shield-halved mr-1"></i> Free download · Android 10+ · No ads in the dialer</p>
        </div>
        <div data-anim="fade-left" class="relative flex justify-center lg:pr-10">
            <div class="zdp-phone">
                <div class="zdp-notch" aria-hidden="true"></div>
                <div class="zdp-screen" role="img" aria-label="Zio Dialer keypad with a T9 match chip showing a contact">
                    <div class="zdp-status"><span>9:41</span><span><i class="fas fa-signal"></i> <i class="fas fa-wifi"></i> <i class="fas fa-battery-three-quarters"></i></span></div>
                    <div class="zdp-num">54 5</div>
                    <span class="zdp-chip"><i class="fas fa-user"></i> Lily K · @lilycreates</span>
                    <div class="zdp-keys" aria-hidden="true">
                        <div class="zdp-key"><span class="d">1</span><span class="l">&nbsp;</span></div>
                        <div class="zdp-key"><span class="d">2</span><span class="l">ABC</span></div>
                        <div class="zdp-key"><span class="d">3</span><span class="l">DEF</span></div>
                        <div class="zdp-key"><span class="d">4</span><span class="l">GHI</span></div>
                        <div class="zdp-key" style="background:rgba(61,107,255,.3); border-color:rgba(61,107,255,.55);"><span class="d">5</span><span class="l">JKL</span></div>
                        <div class="zdp-key"><span class="d">6</span><span class="l">MNO</span></div>
                        <div class="zdp-key"><span class="d">7</span><span class="l">PQRS</span></div>
                        <div class="zdp-key"><span class="d">8</span><span class="l">TUV</span></div>
                        <div class="zdp-key"><span class="d">9</span><span class="l">WXYZ</span></div>
                    </div>
                    <div class="zdp-call"><i class="fas fa-phone"></i> Call</div>
                </div>
                <div class="zdp-cid" role="img" aria-label="Caller ID card resolving a number to a Sayzio profile">
                    <div class="flex items-center gap-3">
                        <span class="zdp-cid-ava">LK</span>
                        <span class="min-w-0">
                            <span class="zdp-cid-name">Lily K <i class="fas fa-circle-check"></i></span>
                            <span class="zdp-cid-sub block">sayz.io/@lilycreates</span>
                        </span>
                    </div>
                    <div class="zdp-cid-chans" aria-hidden="true">
                        <span class="zdp-cid-chan" style="background:rgba(34,197,94,.85);"><i class="fas fa-phone"></i></span>
                        <span class="zdp-cid-chan" style="background:rgba(61,107,255,.85);"><i class="fas fa-comment-sms"></i></span>
                        <span class="zdp-cid-chan" style="background:rgba(37,211,102,.85);"><i class="fab fa-whatsapp"></i></span>
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
            <img src="{{ asset('images/marketing/dialer/hero-call.webp') }}"
                 alt="A small-business owner taking a call in her shop with Zio Dialer"
                 loading="lazy" decoding="async" class="zdp-photo">
        </div>
        <div data-anim="fade-left">
            <span class="text-xs font-bold uppercase tracking-wider text-blue-300">Every call, in context</span>
            <h2 class="mt-3 text-3xl sm:text-4xl font-bold text-white">Know who's calling before you say hello</h2>
            <p class="mt-4 text-gray-400 leading-relaxed">
                When a customer calls, the difference between "Hello?" and "Hi Priya, how did the order
                work out?" is everything. Zio Dialer looks up the number against your Sayzio contacts and
                the wider Sayzio network the moment your phone rings, so the name, photo and context are
                on screen before you pick up.
            </p>
            <p class="mt-4 text-gray-400 leading-relaxed">
                It's a real replacement for your Android phone app (dial pad, call log, favourites) with
                your Sayzio address book behind it. Numbers that belong to Sayzio members resolve to their
                biolink automatically, so a phone number becomes a whole profile.
            </p>
            <ul class="mt-6 space-y-3">
                @foreach([
                    'Caller ID overlay works natively at ring time, even before the app opens',
                    'T9 smart dialing searches names, businesses and handles as you type digits',
                    'Calls stay on your carrier, Sayzio never routes or records your calls',
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
            <h2 class="text-3xl sm:text-4xl font-bold text-white">Everything a dialer should have been</h2>
            <p class="mt-3 text-gray-400">Fast on the basics, smart everywhere else.</p>
        </div>
        <div class="mt-12 grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach([
                ['fa-keyboard', 'T9 smart search', 'Type 5-4-5 and instantly match names, handles, biolinks, even people you follow on Sayzio. Toggle to a full keyboard any time.'],
                ['fa-id-badge', 'Caller ID overlay', 'Incoming numbers resolve to full Sayzio profiles, so you see the name, photo and verified badge before you pick up.'],
                ['fa-comments', 'Quick channels', 'Call, SMS or WhatsApp any contact from one row, the dialer remembers each person\'s preferred channel.'],
                ['fa-rotate', 'Google Contacts sync', 'Two-way, incremental sync with Google Contacts keeps your address book identical everywhere, automatically.'],
                ['fa-magnifying-glass', 'Universal finder', 'One search across contacts, people, your links, followed creators and workspaces, grouped and ranked.'],
                ['fa-link', 'Phone → biolink', 'Numbers silently attach to matching Sayzio pages, so a phone number becomes a full mini-website in your contacts.'],
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

{{-- ============ How it works ============ --}}
<section class="py-16 sm:py-20 relative">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto" data-anim="fade-up">
            <h2 class="text-3xl sm:text-4xl font-bold text-white">Set up in under a minute</h2>
        </div>
        <div class="mt-12 grid sm:grid-cols-3 gap-5">
            @foreach([
                ['1', 'Install & set default', 'Download Zio Dialer and set it as your default phone app, Android asks with one tap.'],
                ['2', 'Connect your accounts', 'Sign in with Sayzio and optionally link Google Contacts for automatic two-way sync.'],
                ['3', 'Dial smarter', 'T9 search, caller ID and quick channels start working immediately, no configuration needed.'],
            ] as [$step, $title, $desc])
                <div class="glass rounded-2xl p-6 text-center" data-anim="fade-up">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-full text-sm font-bold text-white" style="background: linear-gradient(135deg, #3d6bff, #6e61ff, #22d3ee);">{{ $step }}</span>
                    <h3 class="mt-4 text-base font-bold text-white">{{ $title }}</h3>
                    <p class="mt-1.5 text-sm text-gray-400 leading-relaxed">{{ $desc }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============ Photo gallery: a dialer that works like you do ============ --}}
<section class="py-16 sm:py-20 relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto" data-anim="fade-up">
            <h2 class="text-3xl sm:text-4xl font-bold text-white">A phone app that works like you do</h2>
            <p class="mt-3 text-gray-400">From the first digit to the follow-up message, everything stays connected to your Sayzio account.</p>
        </div>
        <div class="mt-12 grid md:grid-cols-3 gap-6">
            @foreach([
                ['images/marketing/dialer/keypad-hand.webp', 'A thumb dialing on the Zio Dialer keypad',
                 'Find anyone with a few taps', 'The universal finder searches your contacts, followed creators, your own links and Sayzio profiles at once, grouped results, T9 digits or full keyboard, your choice.'],
                ['images/marketing/dialer/caller-id.webp', 'A shop owner answering a call with a rich caller card on screen',
                 'Rich caller cards at ring time', 'Incoming numbers resolve against your address book and the Sayzio network, so a bare number turns into a name, a photo and a biolink you can open right after the call.'],
                ['images/marketing/dialer/sync-desk.webp', 'A phone and laptop side by side with matching synced contact lists',
                 'Two-way Google Contacts sync', 'Link Google Contacts once and Zio Dialer keeps both address books in step automatically, edits on either side flow across in the background, roughly every half hour.'],
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
        <p class="mt-8 text-center text-sm text-gray-400 max-w-2xl mx-auto" data-anim="fade-up">
            Privacy first: Zio Dialer only ever launches standard <span class="text-gray-300 font-semibold">tel:</span> and
            <span class="text-gray-300 font-semibold">mailto:</span> actions, your call log and messages never leave your device.
        </p>
    </div>
</section>

{{-- ============ FAQs ============ --}}
<section class="py-16 sm:py-20">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center" data-anim="fade-up">
            <h2 class="text-3xl sm:text-4xl font-bold text-white">Zio Dialer, answered</h2>
        </div>
        <div class="mt-10 space-y-3" x-data="{ open: null }">
            @foreach([
                ['Is Zio Dialer free?', 'Yes. The dialer, T9 search, caller ID and quick channels are free. Some Sayzio-side features (like advanced contact caps) follow your Sayzio plan.'],
                ['Does it work on iPhone?', 'Not yet, iOS doesn\'t allow full replacement dialers. iPhone users get the same contacts, universal finder and caller lookups inside the Sayzio mobile app.'],
                ['Do I need a Sayzio account?', 'You can dial and search contacts without one, but caller-ID profiles, phone-to-biolink matching and sync across devices need a free Sayzio account.'],
                ['What data does caller ID use?', 'Lookups match the incoming number against your own contacts and public Sayzio profiles that listed that number. Nothing is uploaded from your call log.'],
                ['How does Google Contacts sync work?', 'Connect once and Zio Dialer syncs incrementally in both directions roughly every 30 minutes, edits on either side show up on the other.'],
            ] as $i => [$q, $a])
                <div class="zdp-faq-card rounded-2xl overflow-hidden" data-anim="fade-up">
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
            <h2 class="text-3xl sm:text-4xl font-bold text-white">Ready to upgrade your phone app?</h2>
            <p class="mt-3 text-gray-400 max-w-xl mx-auto">Install Zio Dialer and every call starts with context.</p>
            <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                @if($cta['play'] !== '')
                    <a href="{{ $cta['play'] }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-2.5 px-6 py-3 rounded-full text-sm font-bold text-white bg-[#3d6bff] hover:bg-[#2342c7] transition-colors">
                        <i class="fab fa-google-play"></i> Get it on Google Play
                    </a>
                @endif
                @if($cta['apk'] !== '')
                    <a href="{{ $cta['apk'] }}" @if(!str_starts_with($cta['apk'], url('/'))) target="_blank" rel="noopener noreferrer" @endif
                       class="inline-flex items-center gap-2.5 px-6 py-3 rounded-full text-sm font-bold text-gray-200 border border-white/20 hover:border-[#3d6bff]/60 hover:text-white transition-colors">
                        <i class="fab fa-android"></i> Download APK
                    </a>
                @endif
                @if($cta['play'] === '' && $cta['apk'] === '')
                    <button type="button"
                            onclick="window.dispatchEvent(new CustomEvent('open-store-coming-soon',{detail:{store:'play'}}))"
                            class="inline-flex items-center gap-2.5 px-6 py-3 rounded-full text-sm font-bold text-white bg-[#3d6bff] hover:bg-[#2342c7] transition-colors">
                        <i class="fab fa-google-play"></i> Coming soon, notify me
                    </button>
                @endif
                <a href="{{ route('site.dialer-contacts') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-full text-sm font-bold text-gray-300 hover:text-white transition-colors">
                    Explore Dialer &amp; Contacts <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>
        </div>
    </div>
</section>
@include('public.partials.store-buttons-modal-only', [])
@endsection
