@extends('public.layouts.site')
@section('title', 'Zio Extension')

@section('content')
{{-- Standalone Zio Extension product page (/extension). Store links come from
     ProductDownloadLinks::extension() — admin-managed per-store URLs from the
     shared ExtensionStoreLinks catalog; stores without a URL open the shared
     "coming soon" affordance (disabled pill), never a dead link. --}}
<style>
    /* Scoped zxp-* classes with explicit colors inside the always-dark popup
       mockup so light-mode remaps can't darken them. */
    .zxp-popup {
        position: relative; width: 320px; max-width: 88vw; margin: 0 auto;
        border-radius: 18px; overflow: hidden;
        background: linear-gradient(180deg, #10182f 0%, #0a0e1c 100%);
        border: 1px solid rgba(255,255,255,.1);
        box-shadow: 0 40px 90px -30px rgba(61,107,255,.5), 0 14px 34px -12px rgba(0,0,0,.7);
        color:#fff;
    }
    .zxp-pophead { display:flex; align-items:center; gap:9px; padding: 13px 16px; border-bottom:1px solid rgba(255,255,255,.07); }
    .zxp-logo { width:26px; height:26px; border-radius:8px; background: linear-gradient(135deg, #3d6bff, #6e61ff, #22d3ee); display:flex; align-items:center; justify-content:center; color:#fff; font-size:12px; }
    .zxp-pophead .t { font-size:12px; font-weight:800; color:#fff; }
    .zxp-pophead .s { font-size:9.5px; color:#8f9bb8; font-weight:600; }
    .zxp-row { padding: 13px 16px; }
    .zxp-label { font-size:9.5px; font-weight:800; color:#8f9bb8; text-transform:uppercase; letter-spacing:.08em; }
    .zxp-short {
        margin-top:7px; display:flex; align-items:center; justify-content:space-between; gap:8px;
        padding: 9px 12px; border-radius: 10px;
        background: rgba(61,107,255,.14); border:1px solid rgba(61,107,255,.4);
        font-size: 12px; font-weight:700; color:#dbe4ff; font-variant-numeric: tabular-nums;
    }
    .zxp-short i { color:#7a9eff; font-size:11px; }
    .zxp-actions { display:grid; grid-template-columns:1fr 1fr; gap:8px; padding: 0 16px 16px; }
    .zxp-act {
        display:flex; align-items:center; justify-content:center; gap:7px;
        padding: 9px 0; border-radius: 10px; font-size: 11px; font-weight:700; color:#fff;
        background: rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1);
    }
    .zxp-act.primary { background: linear-gradient(135deg, #3d6bff, #6e61ff, #22d3ee); border:none; }
    .zxp-qr {
        position:absolute; right:-26px; bottom:-20px; z-index:6;
        width: 92px; height: 92px; border-radius: 16px; padding: 10px;
        background:#fff; box-shadow: 0 22px 50px -16px rgba(61,107,255,.6);
        display:flex; align-items:center; justify-content:center;
    }
    @media (max-width: 640px) { .zxp-qr { right: -4px; } }
    .zxp-qr i { font-size: 56px; color:#0f172a; }
    @media (prefers-reduced-motion: no-preference) {
        .zxp-popup { animation: zxpFloat 6.5s ease-in-out infinite; }
        @keyframes zxpFloat { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
        .zxp-qr { animation: zxpQr 6.5s ease-in-out infinite; }
        @keyframes zxpQr { 0%,100% { transform: translateY(0) rotate(3deg); } 50% { transform: translateY(-7px) rotate(3deg); } }
    }
    .zxp-mesh::before {
        content:""; position:absolute; inset:-15%;
        background: rgba(61,107,255,.06); filter: blur(40px); pointer-events:none;
    }
    /* Store pills (light-mode paired) */
    .zxp-store {
        display:inline-flex; align-items:center; gap:10px;
        padding: 10px 18px 10px 14px; border-radius: 12px;
        background:#0d0f17; border:1px solid rgba(255,255,255,.18); color:#fff;
        transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
    }
    .zxp-store:hover { transform: translateY(-2px); border-color: rgba(61,107,255,.55); box-shadow: 0 12px 28px -12px rgba(61,107,255,.55); }
    .zxp-store i { font-size: 19px; }
    .zxp-store .k { font-size: 9px; letter-spacing:.08em; text-transform:uppercase; color: rgba(255,255,255,.6); font-weight:600; display:block; }
    .zxp-store .n { font-size: 13.5px; font-weight:700; color:#fff; display:block; }
    .zxp-store.soon { opacity:.55; cursor:default; }
    .zxp-store.soon:hover { transform:none; border-color: rgba(255,255,255,.18); box-shadow:none; }
    html.light-mode .zxp-store { background:#111827; border-color: rgba(17,24,39,.9); }
    .zxp-faq-card { background: rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.09); }
    html.light-mode .zxp-faq-card { background:#ffffff; border-color: rgba(15,23,42,.1); }
    /* Real-photo treatment (light-mode paired) */
    .zxp-photo { width:100%; height:auto; border-radius:20px; border:1px solid rgba(255,255,255,.1); box-shadow: 0 30px 70px -30px rgba(61,107,255,.4); }
    html.light-mode .zxp-photo { border-color: rgba(15,23,42,.12); box-shadow: 0 30px 70px -30px rgba(61,107,255,.28); }
    .zxp-step-num { display:inline-flex; height:40px; width:40px; align-items:center; justify-content:center; border-radius:999px; font-size:14px; font-weight:800; color:#fff; background: linear-gradient(135deg, #3d6bff, #6e61ff, #22d3ee); }
</style>

@php
    // Store pills: label + icon per catalog key; unlinked stores render as
    // disabled "coming soon" pills so the lineup stays visible.
    $storeIcons = [
        'chrome'  => 'fab fa-chrome',
        'edge'    => 'fab fa-edge',
        'firefox' => 'fab fa-firefox-browser',
        'opera'   => 'fab fa-opera',
        'safari'  => 'fab fa-safari',
        'brave'   => 'fas fa-shield-halved',
    ];
    $storePills = collect($stores)->map(function ($s) use ($storeIcons) {
        return [
            'label' => $s['label'] ?? ucfirst((string) ($s['key'] ?? 'store')),
            'url'   => trim((string) ($s['url'] ?? '')),
            'icon'  => $storeIcons[$s['key'] ?? ''] ?? 'fas fa-puzzle-piece',
        ];
    });
    $liveStores = $storePills->where('url', '!=', '');
@endphp

{{-- ============ Hero ============ --}}
<section class="relative overflow-hidden zxp-mesh pt-16 sm:pt-24 pb-16 sm:pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-12 lg:gap-8 items-center relative">
        <div data-anim="fade-right">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider"
                  style="background: rgba(61,107,255,.14); color:#6d92ff; border:1px solid rgba(61,107,255,.35);">
                <i class="fas fa-puzzle-piece text-[10px]"></i> Zio Extension
            </span>
            <h1 class="mt-4 text-4xl sm:text-5xl font-bold leading-[1.08] text-white">
                Shorten any page <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#3d6bff] via-[#6e61ff] to-[#22d3ee]">without leaving it</span>
            </h1>
            <p class="mt-5 text-lg text-gray-400 leading-relaxed max-w-xl">
                One click in your toolbar turns the page you're on into a branded short link,
                a QR code, or a saved link in your Sayzio account — complete with UTM tags
                and instant copy to clipboard.
            </p>
            <div class="mt-8 flex flex-wrap items-center gap-3">
                @forelse($liveStores as $pill)
                    <a href="{{ $pill['url'] }}" target="_blank" rel="noopener noreferrer" class="zxp-store" aria-label="Add to {{ $pill['label'] }}">
                        <i class="{{ $pill['icon'] }}"></i>
                        <span><span class="k">Add to</span><span class="n">{{ $pill['label'] }}</span></span>
                    </a>
                @empty
                    @foreach($storePills->take(3) as $pill)
                        <span class="zxp-store soon" aria-label="{{ $pill['label'] }} (coming soon)">
                            <i class="{{ $pill['icon'] }}"></i>
                            <span><span class="k">Coming soon</span><span class="n">{{ $pill['label'] }}</span></span>
                        </span>
                    @endforeach
                @endforelse
            </div>
            <p class="mt-4 text-xs text-gray-500"><i class="fas fa-shield-halved mr-1"></i> Free · Works with your existing Sayzio account</p>
        </div>
        <div data-anim="fade-left" class="relative flex justify-center lg:pr-6 pb-8">
            <div class="zxp-popup" role="img" aria-label="Zio Extension popup shortening the current page with copy and QR actions">
                <div class="zxp-pophead">
                    <span class="zxp-logo"><i class="fas fa-link"></i></span>
                    <span class="min-w-0">
                        <span class="t block">Zio Extension</span>
                        <span class="s block">Signed in as @studio</span>
                    </span>
                </div>
                <div class="zxp-row" aria-hidden="true">
                    <span class="zxp-label">This page, shortened</span>
                    <span class="zxp-short"><span class="truncate">sayz.io/launch-day</span> <i class="fas fa-copy"></i></span>
                </div>
                <div class="zxp-actions" aria-hidden="true">
                    <span class="zxp-act primary"><i class="fas fa-qrcode"></i> QR code</span>
                    <span class="zxp-act"><i class="fas fa-floppy-disk"></i> Save link</span>
                    <span class="zxp-act"><i class="fas fa-tags"></i> UTM tags</span>
                    <span class="zxp-act"><i class="fas fa-chart-line"></i> Stats</span>
                </div>
                <div class="zxp-qr" aria-hidden="true"><i class="fas fa-qrcode"></i></div>
            </div>
        </div>
    </div>
</section>

{{-- ============ In real life (photo band) ============ --}}
<section class="py-16 sm:py-20 relative">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-10 lg:gap-14 items-center">
        <div data-anim="fade-right">
            <img src="{{ asset('images/marketing/extension/hero-desk.webp') }}"
                 alt="A marketer at a desk shortening the page they're reading with the Zio Extension"
                 loading="lazy" decoding="async" class="zxp-photo">
        </div>
        <div data-anim="fade-left">
            <span class="text-xs font-bold uppercase tracking-wider text-blue-300">Built for the middle of your day</span>
            <h2 class="mt-3 text-3xl sm:text-4xl font-bold text-white">Share the moment you find it</h2>
            <p class="mt-4 text-gray-400 leading-relaxed">
                The best link to share is the page you're already reading — a product you love, an article
                worth passing on, your own landing page fresh off a deploy. Zio Extension lives in your
                toolbar so that moment never gets lost to a tab-switch: one click and the page becomes a
                clean, branded short link with your default domain, already copied to your clipboard.
            </p>
            <p class="mt-4 text-gray-400 leading-relaxed">
                Because every link is created inside your Sayzio account, it arrives with click analytics,
                smart-routing options and A/B testing already attached — the extension even has a
                "Shorten as A/B test" mode for trying two destinations from a single short link.
            </p>
            <ul class="mt-6 space-y-3">
                @foreach([
                    'Links land in your dashboard instantly, tracked from the very first click',
                    'Works with every verified custom domain on your account',
                    'Feeds Backlinks Radar, so you can see where your links get re-shared',
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
            <h2 class="text-3xl sm:text-4xl font-bold text-white">Your toolbar, upgraded</h2>
            <p class="mt-3 text-gray-400">Everything the Sayzio dashboard does to a URL — right where you found it.</p>
        </div>
        <div class="mt-12 grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach([
                ['fa-bolt', 'One-click shorten', 'Open the popup and the current page is already shortened with your default domain. Copy and go.'],
                ['fa-qrcode', 'Instant QR codes', 'Generate a scannable QR code for the page you\'re on — download it or open it in QR Studio for full styling.'],
                ['fa-floppy-disk', 'Save to your account', 'Every link you create lands in your Sayzio dashboard with full click analytics from the first visitor.'],
                ['fa-tags', 'UTM builder', 'Add campaign, source and medium tags before shortening so your analytics stay clean without spreadsheets.'],
                ['fa-globe', 'Branded domains', 'Shorten onto any of your verified custom domains, or the shared Sayzio domains — pick per link.'],
                ['fa-chart-line', 'Stats at a glance', 'See clicks for links you\'ve already made on the site you\'re browsing, straight from the popup.'],
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

{{-- ============ How it works (photo steps) ============ --}}
<section class="py-16 sm:py-20 relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto" data-anim="fade-up">
            <h2 class="text-3xl sm:text-4xl font-bold text-white">From page to campaign in three clicks</h2>
            <p class="mt-3 text-gray-400">A complete link workflow that never opens a new tab.</p>
        </div>
        <div class="mt-12 grid md:grid-cols-3 gap-6">
            @foreach([
                ['images/marketing/extension/workflow.webp', 'Hands at a laptop with the extension popup open on the current page',
                 '1', 'Click the icon', 'Open the popup on any page and it\'s already shortened onto your default domain. Copy it, or keep going for more.'],
                ['images/marketing/extension/campaign.webp', 'A campaign-planning desk with notes, laptop and phone laid out',
                 '2', 'Tag the campaign', 'Add UTM source, medium and campaign right in the popup — no spreadsheets, no URL-builder tools, no typos in your analytics.'],
                ['images/marketing/extension/qr-share.webp', 'Two colleagues sharing a QR code from a laptop screen',
                 '3', 'Share it anywhere', 'Copy the short link, or generate a scannable QR code on the spot — then open it in QR Studio for full branding when you need print quality.'],
            ] as [$img, $alt, $step, $title, $desc])
                <div class="glass rounded-2xl overflow-hidden" data-anim="fade-up">
                    <img src="{{ asset($img) }}" alt="{{ $alt }}" loading="lazy" decoding="async" class="w-full aspect-[4/3] object-cover">
                    <div class="p-6">
                        <span class="zxp-step-num">{{ $step }}</span>
                        <h3 class="mt-4 text-base font-bold text-white">{{ $title }}</h3>
                        <p class="mt-1.5 text-sm text-gray-400 leading-relaxed">{{ $desc }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============ Use cases ============ --}}
<section class="py-16 sm:py-20 relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto" data-anim="fade-up">
            <h2 class="text-3xl sm:text-4xl font-bold text-white">Who reaches for it every day</h2>
        </div>
        <div class="mt-12 grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
            @foreach([
                ['fa-bullhorn', 'Marketers', 'Tag every share with UTM parameters as you browse, and run quick A/B tests on landing pages without leaving the article you found them in.'],
                ['fa-pen-nib', 'Creators', 'Turn anything you recommend into a branded link on your own domain, so every share builds your name — and every click shows up in your stats.'],
                ['fa-briefcase', 'Sales & founders', 'Save prospect pages and case studies to your account as you research, then send trackable links and see exactly when they get opened.'],
                ['fa-users', 'Teams', 'Everyone shortens onto the same verified company domains, so links stay on-brand no matter who shares them.'],
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

{{-- ============ FAQs ============ --}}
<section class="py-16 sm:py-20">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center" data-anim="fade-up">
            <h2 class="text-3xl sm:text-4xl font-bold text-white">Zio Extension, answered</h2>
        </div>
        <div class="mt-10 space-y-3" x-data="{ open: null }">
            @foreach([
                ['Which browsers are supported?', 'Chrome and every Chromium-based browser (Edge, Brave, Opera), plus Firefox. Grab it from your browser\'s store above.'],
                ['Is it free?', 'Yes. The extension itself is free — links you create count toward your Sayzio plan exactly like links made on the website.'],
                ['Do I need a Sayzio account?', 'Yes — sign in once inside the popup and every shortened link is saved to your account with analytics.'],
                ['Can it read my browsing history?', 'No. The extension only sees the tab you invoke it on, at the moment you click the icon. It never tracks browsing in the background.'],
                ['Can I use my own domain?', 'Absolutely. Any custom domain you\'ve verified in Sayzio shows up in the popup\'s domain picker.'],
            ] as $i => [$q, $a])
                <div class="zxp-faq-card rounded-2xl overflow-hidden" data-anim="fade-up">
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
            <h2 class="text-3xl sm:text-4xl font-bold text-white">Add it in ten seconds</h2>
            <p class="mt-3 text-gray-400 max-w-xl mx-auto">Then never paste a URL into a shortener again.</p>
            <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                @foreach($liveStores as $pill)
                    <a href="{{ $pill['url'] }}" target="_blank" rel="noopener noreferrer" class="zxp-store" aria-label="Add to {{ $pill['label'] }}">
                        <i class="{{ $pill['icon'] }}"></i>
                        <span><span class="k">Add to</span><span class="n">{{ $pill['label'] }}</span></span>
                    </a>
                @endforeach
                @if($liveStores->isEmpty())
                    <a href="{{ route('register') }}" class="inline-flex items-center gap-2.5 px-6 py-3 rounded-full text-sm font-bold text-white bg-[#3d6bff] hover:bg-[#2342c7] transition-colors">
                        Start on the web free <i class="fas fa-arrow-right text-xs"></i>
                    </a>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection
