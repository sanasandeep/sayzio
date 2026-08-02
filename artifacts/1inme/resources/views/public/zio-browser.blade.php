@extends('public.layouts.site')
@section('title', 'Zio Browser')

@section('content')
{{-- Standalone Zio Browser product page (/browser). Download CTAs come from
     ProductDownloadLinks::browser() — admin-managed Mac / Windows URLs with
     automatic fallback to the latest published GitHub release (cached). --}}
<style>
    /* Scoped zbp-* classes with explicit colors inside the always-dark
       browser-window mockup so light-mode remaps can't darken them. */
    .zbp-window {
        position: relative; width: 100%; max-width: 560px; margin: 0 auto;
        border-radius: 18px; overflow: hidden;
        background: linear-gradient(180deg, #10182f 0%, #0a0e1c 100%);
        border: 1px solid rgba(255,255,255,.1);
        box-shadow: 0 40px 90px -30px rgba(61,107,255,.5), 0 14px 34px -12px rgba(0,0,0,.7);
    }
    .zbp-chrome { display:flex; align-items:center; gap:10px; padding: 11px 14px; background: rgba(255,255,255,.04); border-bottom:1px solid rgba(255,255,255,.07); }
    .zbp-dots { display:flex; gap:6px; }
    .zbp-dot { width:10px; height:10px; border-radius:999px; }
    .zbp-tabs { display:flex; gap:6px; min-width:0; flex:1; }
    .zbp-tab {
        display:inline-flex; align-items:center; gap:6px; min-width:0;
        padding: 4px 12px; border-radius: 8px; font-size: 10.5px; font-weight:700; color:#cbd5e1;
        background: rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.08); white-space:nowrap;
    }
    .zbp-tab.on { background: rgba(61,107,255,.2); border-color: rgba(61,107,255,.5); color:#fff; }
    .zbp-tab i { font-size: 9px; color:#7a9eff; }
    .zbp-urlbar {
        display:flex; align-items:center; gap:8px; margin: 10px 14px;
        padding: 8px 13px; border-radius: 10px; font-size: 11px; color:#a8b3cf;
        background: rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.09);
        font-variant-numeric: tabular-nums;
    }
    .zbp-urlbar i { color:#34d399; font-size:10px; }
    .zbp-body { padding: 6px 14px 16px; display:grid; grid-template-columns: 1fr 1fr; gap:10px; }
    .zbp-card { border-radius: 12px; padding: 12px; background: rgba(255,255,255,.045); border:1px solid rgba(255,255,255,.08); color:#fff; }
    .zbp-card .t { font-size: 10px; font-weight:800; color:#8f9bb8; text-transform:uppercase; letter-spacing:.08em; }
    .zbp-card .v { margin-top:4px; font-size: 17px; font-weight:800; color:#fff; }
    .zbp-bar { height: 5px; border-radius: 999px; background: rgba(255,255,255,.08); margin-top: 8px; overflow:hidden; }
    .zbp-bar > span { display:block; height:100%; border-radius:999px; background: linear-gradient(90deg, #3d6bff, #6e61ff, #22d3ee); }
    /* Floating profile chip */
    .zbp-profile {
        position:absolute; left:-18px; bottom:14%; z-index:6;
        display:flex; align-items:center; gap:9px; padding: 9px 14px 9px 10px;
        border-radius: 14px; background: rgba(14,20,38,.96);
        border:1px solid rgba(61,107,255,.35); box-shadow: 0 24px 60px -18px rgba(61,107,255,.6);
        color:#fff;
    }
    @media (max-width: 640px) { .zbp-profile { left: 4px; } }
    .zbp-profile .ava { width:30px; height:30px; border-radius:10px; background: linear-gradient(135deg, #3d6bff, #6e61ff, #22d3ee); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:12px; color:#fff; }
    .zbp-profile .n { font-size:11.5px; font-weight:800; color:#fff; }
    .zbp-profile .s { font-size:9.5px; color:#a8b3cf; font-weight:600; }
    @media (prefers-reduced-motion: no-preference) {
        .zbp-window { animation: zbpFloat 7s ease-in-out infinite; }
        @keyframes zbpFloat { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
        .zbp-profile { animation: zbpChip 7s ease-in-out infinite; }
        @keyframes zbpChip { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }
    }
    .zbp-mesh::before {
        content:""; position:absolute; inset:-15%;
        background: rgba(61,107,255,.06); filter: blur(40px); pointer-events:none;
    }
    .zbp-faq-card { background: rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.09); }
    html.light-mode .zbp-faq-card { background:#ffffff; border-color: rgba(15,23,42,.1); }
    /* Real-photo treatment (light-mode paired) */
    .zbp-photo { width:100%; height:auto; border-radius:20px; border:1px solid rgba(255,255,255,.1); box-shadow: 0 30px 70px -30px rgba(61,107,255,.4); }
    html.light-mode .zbp-photo { border-color: rgba(15,23,42,.12); box-shadow: 0 30px 70px -30px rgba(61,107,255,.28); }
</style>

{{-- ============ Hero ============ --}}
<section class="relative overflow-hidden zbp-mesh pt-16 sm:pt-24 pb-16 sm:pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-12 lg:gap-10 items-center relative">
        <div data-anim="fade-right">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider"
                  style="background: rgba(61,107,255,.14); color:#6d92ff; border:1px solid rgba(61,107,255,.35);">
                <i class="fas fa-window-maximize text-[10px]"></i> Zio Browser, for desktop
            </span>
            <h1 class="mt-4 text-4xl sm:text-5xl font-bold leading-[1.08] text-white">
                A browser built for <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#3d6bff] via-[#6e61ff] to-[#22d3ee]">people who ship</span>
            </h1>
            <p class="mt-5 text-lg text-gray-400 leading-relaxed max-w-xl">
                Zio Browser is the SayZio desktop browser for Mac, Windows and Linux: private,
                fully isolated profiles, a built-in device lab for testing your pages,
                distraction-free browsing and one-click access to your links and analytics.
            </p>
            <div class="mt-8 flex flex-wrap items-center gap-3">
                @if($cta['mac'] !== '')
                    <a href="{{ $cta['mac'] }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-2.5 px-6 py-3 rounded-full text-sm font-bold text-white bg-[#3d6bff] hover:bg-[#2342c7] transition-colors">
                        <i class="fab fa-apple"></i> Download for Mac
                    </a>
                @endif
                @if($cta['windows'] !== '')
                    <a href="{{ $cta['windows'] }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-2.5 px-6 py-3 rounded-full text-sm font-bold border transition-colors {{ $cta['mac'] !== '' ? 'text-gray-200 border-white/20 hover:border-[#3d6bff]/60 hover:text-white' : 'text-white bg-[#3d6bff] border-transparent hover:bg-[#2342c7]' }}">
                        <i class="fab fa-windows"></i> Download for Windows
                    </a>
                @endif
                @if(($cta['linux_appimage'] ?? '') !== '' || ($cta['linux_deb'] ?? '') !== '')
                    <a href="{{ ($cta['linux_appimage'] ?? '') !== '' ? $cta['linux_appimage'] : $cta['linux_deb'] }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-2.5 px-6 py-3 rounded-full text-sm font-bold border transition-colors {{ ($cta['mac'] !== '' || $cta['windows'] !== '') ? 'text-gray-200 border-white/20 hover:border-[#3d6bff]/60 hover:text-white' : 'text-white bg-[#3d6bff] border-transparent hover:bg-[#2342c7]' }}">
                        <i class="fab fa-linux"></i> Download for Linux
                    </a>
                @endif
                @if($cta['mac'] === '' && $cta['windows'] === '' && ($cta['linux_appimage'] ?? '') === '' && ($cta['linux_deb'] ?? '') === '')
                    <a href="{{ route('site.download') }}"
                       class="inline-flex items-center gap-2.5 px-6 py-3 rounded-full text-sm font-bold text-white bg-[#3d6bff] hover:bg-[#2342c7] transition-colors">
                        <i class="fas fa-download"></i> Go to downloads
                    </a>
                @endif
            </div>
            <p class="mt-4 text-xs text-gray-500">
                <i class="fas fa-shield-halved mr-1"></i> Free download
                @if($cta['version'] !== '') · Latest version {{ $cta['version'] }} @endif
                · macOS (Intel &amp; Apple Silicon), Windows and Linux (AppImage &amp; .deb) ·
                <a href="{{ route('site.download') }}" class="underline hover:text-gray-300">all installers</a>
            </p>
        </div>
        <div data-anim="fade-left" class="relative">
            <div class="zbp-window" role="img" aria-label="Zio Browser window showing tabs, a secure URL bar and a links dashboard">
                <div class="zbp-chrome">
                    <span class="zbp-dots" aria-hidden="true">
                        <span class="zbp-dot" style="background:#f87171;"></span>
                        <span class="zbp-dot" style="background:#fbbf24;"></span>
                        <span class="zbp-dot" style="background:#34d399;"></span>
                    </span>
                    <span class="zbp-tabs" aria-hidden="true">
                        <span class="zbp-tab on"><i class="fas fa-link"></i> My links</span>
                        <span class="zbp-tab"><i class="fas fa-chart-line"></i> Analytics</span>
                        <span class="zbp-tab hidden sm:inline-flex"><i class="fas fa-mobile-screen"></i> Device lab</span>
                    </span>
                </div>
                <div class="zbp-urlbar" aria-hidden="true"><i class="fas fa-lock"></i> sayz.io/user/links</div>
                <div class="zbp-body" aria-hidden="true">
                    <div class="zbp-card">
                        <span class="t">Clicks today</span>
                        <span class="v block">2,418</span>
                        <span class="zbp-bar"><span style="width:76%;"></span></span>
                    </div>
                    <div class="zbp-card">
                        <span class="t">Live visitors</span>
                        <span class="v block">37</span>
                        <span class="zbp-bar"><span style="width:42%;"></span></span>
                    </div>
                    <div class="zbp-card" style="grid-column: span 2;">
                        <span class="t">Top link</span>
                        <span class="v block" style="font-size:13px;">sayz.io/@studio · Launch page</span>
                        <span class="zbp-bar"><span style="width:88%;"></span></span>
                    </div>
                </div>
                <div class="zbp-profile" aria-hidden="true">
                    <span class="ava">W</span>
                    <span>
                        <span class="n block">Work profile</span>
                        <span class="s block">Fully isolated session</span>
                    </span>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============ In real life (photo band) ============ --}}
<section class="py-16 sm:py-20 relative">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-10 lg:gap-14 items-center">
        <div data-anim="fade-right">
            <img src="{{ asset('images/marketing/browser/hero-desktop.webp') }}"
                 alt="A maker working in Zio Browser on a large desktop monitor"
                 loading="lazy" decoding="async" class="zbp-photo">
        </div>
        <div data-anim="fade-left">
            <span class="text-xs font-bold uppercase tracking-wider text-blue-300">A desktop browser with a job</span>
            <h2 class="mt-3 text-3xl sm:text-4xl font-bold text-white">Built for the people who build pages</h2>
            <p class="mt-4 text-gray-400 leading-relaxed">
                Zio Browser is a full desktop browser (tabs, bookmarks, history, downloads) designed
                around one workflow: making, checking and sharing your Sayzio pages. Your account rides
                along, so opening your dashboard, your biolinks or a follower's page never starts with a
                login screen.
            </p>
            <p class="mt-4 text-gray-400 leading-relaxed">
                Type a handle and it resolves straight to the Sayzio profile. Open the device lab and see
                the same page as a phone, a tablet and a desktop side by side. When you're done, close the
                window knowing private sessions left nothing behind.
            </p>
            <ul class="mt-6 space-y-3">
                @foreach([
                    'Signed-in session bridges to your Sayzio account across windows',
                    'Per-profile isolation keeps clients, brands and personal browsing apart',
                    'Private windows carry a hard wall, no history, no leftover storage',
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
            <h2 class="text-3xl sm:text-4xl font-bold text-white">More than tabs</h2>
            <p class="mt-3 text-gray-400">The tools creators and teams actually use, built into the browser.</p>
        </div>
        <div class="mt-12 grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach([
                ['fa-user-shield', 'Private profiles', 'Every profile gets its own cookies, logins and history, switch between work, personal and client accounts without leaks.'],
                ['fa-mobile-screen', 'Built-in device lab', 'Preview any page across phone, tablet and desktop viewports side by side, perfect for checking your Link in Bio.'],
                ['fa-bolt', 'Fast & focused', 'A clean, distraction-free window with none of the toolbar clutter. Your pages, your work, nothing else.'],
                ['fa-link', 'Sayzio built in', 'Jump to your links, biolinks, QR codes and analytics from anywhere with one click, no bookmarks needed.'],
                ['fa-eye-slash', 'True private windows', 'Private windows keep zero traces and are walled off from every profile, by design, not by setting.'],
                ['fa-rotate', 'Auto-updates', 'New releases install themselves quietly in the background, so you\'re always on the latest version.'],
                ['fa-shield-halved', 'Built-in ad blocker', 'Block ads and trackers out of the box, tune the strength, and keep per-site allow and block lists for the pages you trust.'],
                ['fa-folder-open', 'My Files at hand', 'Browse your Sayzio storage from a sidebar pane: folders, drag-and-drop uploads and your quota, without opening the dashboard.'],
                ['fa-note-sticky', 'Notes on every site', 'Your Sayzio notes ride along: a badge shows how many notes you have for the site you\'re on, synced with web and mobile and available offline.'],
                ['fa-phone', 'Dialpad built in', 'A T9 dialpad panel hands off to the Zio Dialer app so a number found on any page becomes a call in two clicks.'],
                ['fa-file-lines', 'Files open in place', 'Text, Markdown, JSON and CSV files open in clean built-in viewers instead of piling up in your downloads folder.'],
                ['fa-wand-magic-sparkles', 'Create from anywhere', 'The Create popover includes quick tiles for the newest page types: AI Chat, Paid Page, Text Page, Restaurant Menu, Store and Booking.'],
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

{{-- ============ Photo gallery: made for makers ============ --}}
<section class="py-16 sm:py-20 relative">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-2xl mx-auto" data-anim="fade-up">
            <h2 class="text-3xl sm:text-4xl font-bold text-white">Three ways makers use it every day</h2>
            <p class="mt-3 text-gray-400">The browser earns its place by removing the little frictions between "edit" and "looks right everywhere."</p>
        </div>
        <div class="mt-12 grid md:grid-cols-3 gap-6">
            @foreach([
                ['images/marketing/browser/device-lab.webp', 'A designer comparing the same page on laptop, tablet and phone',
                 'Check every screen at once', 'The built-in device lab renders your page at real phone, tablet and desktop sizes side by side, no resizing windows, no guessing how a block wraps on mobile.'],
                ['images/marketing/browser/profiles.webp', 'A freelancer switching between browser profiles at a cafe table',
                 'One window per client', 'Profiles keep logins, cookies and history fully separated. Manage a client\'s workspace in one profile and your own brand in another without ever logging out.'],
                ['images/marketing/browser/focus.webp', 'A minimalist night-time workspace with a single clean page open',
                 'Stay in the flow', 'A quiet, chrome-light interface and handle-aware address bar keep the path from "idea" to "published page" short, type the handle, see the page, make the change.'],
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
            <h2 class="text-3xl sm:text-4xl font-bold text-white">Zio Browser, answered</h2>
        </div>
        <div class="mt-10 space-y-3" x-data="{ open: null }">
            @foreach([
                ['Which platforms are supported?', 'macOS (both Intel and Apple Silicon builds), Windows, and Linux (x64, as a portable AppImage or a .deb installer for Ubuntu/Debian). Grab the right installer above or from the downloads page.'],
                ['Is it free?', 'Yes, Zio Browser is completely free to download and use. A Sayzio account unlocks the built-in link and analytics shortcuts.'],
                ['How are profiles different from Chrome profiles?', 'Each Zio profile is a fully isolated session: separate cookies, storage and logins with zero sharing between them, so one browser can safely hold many identities.'],
                ['What is the device lab?', 'A built-in preview mode that renders any URL across common phone, tablet and desktop viewports at once, ideal for testing biolinks and landing pages before you share them.'],
                ['Does it sync with my Sayzio account?', 'Sign in once and your links, pages and analytics are one click away in every window. Your browsing data itself stays on your machine.'],
            ] as $i => [$q, $a])
                <div class="zbp-faq-card rounded-2xl overflow-hidden" data-anim="fade-up">
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
            <h2 class="text-3xl sm:text-4xl font-bold text-white">Browse like it's your workspace</h2>
            <p class="mt-3 text-gray-400 max-w-xl mx-auto">Free for Mac, Windows and Linux. Installed in seconds.</p>
            <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                @if($cta['mac'] !== '')
                    <a href="{{ $cta['mac'] }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-2.5 px-6 py-3 rounded-full text-sm font-bold text-white bg-[#3d6bff] hover:bg-[#2342c7] transition-colors">
                        <i class="fab fa-apple"></i> Download for Mac
                    </a>
                @endif
                @if($cta['windows'] !== '')
                    <a href="{{ $cta['windows'] }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-2.5 px-6 py-3 rounded-full text-sm font-bold text-gray-200 border border-white/20 hover:border-[#3d6bff]/60 hover:text-white transition-colors">
                        <i class="fab fa-windows"></i> Download for Windows
                    </a>
                @endif
                @if(($cta['linux_appimage'] ?? '') !== '' || ($cta['linux_deb'] ?? '') !== '')
                    <a href="{{ ($cta['linux_appimage'] ?? '') !== '' ? $cta['linux_appimage'] : $cta['linux_deb'] }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-2.5 px-6 py-3 rounded-full text-sm font-bold text-gray-200 border border-white/20 hover:border-[#3d6bff]/60 hover:text-white transition-colors">
                        <i class="fab fa-linux"></i> Download for Linux
                    </a>
                @endif
                <a href="{{ route('site.download') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-full text-sm font-bold text-gray-300 hover:text-white transition-colors">
                    All installers <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>
        </div>
    </div>
</section>
@endsection
