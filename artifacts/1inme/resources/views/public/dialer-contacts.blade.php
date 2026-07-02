@extends('public.layouts.site')
@section('title', 'Smart Dialer &amp; Contacts')

@section('content')
@php
    $accent = '#3d6bff';
@endphp

<style>
    .dcp-mesh::before {
        content:""; position:absolute; inset:-15%;
        background: rgba(61,107,255,.06);
        filter: blur(40px); pointer-events:none;
        animation: dcpMesh 15s ease-in-out infinite alternate;
    }
    @keyframes dcpMesh { 0% { transform: translate3d(0,0,0); } 100% { transform: translate3d(-2%,2%,0) scale(1.05); } }

    /* Hero phone */
    .dcp-phone {
        position: relative; width: 290px; max-width: 82vw; margin: 0 auto;
        aspect-ratio: 290 / 590; border-radius: 42px; padding: 12px;
        background: linear-gradient(160deg, #1b1030, #0c0718);
        box-shadow: 0 40px 90px -30px rgba(61,107,255,.55), 0 14px 34px -12px rgba(0,0,0,.7), inset 0 0 0 1.5px rgba(255,255,255,.08);
        animation: dcpFloat 6.5s ease-in-out infinite;
    }
    @keyframes dcpFloat { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-12px); } }
    .dcp-screen { position:absolute; inset:12px; border-radius:32px; overflow:hidden; background: linear-gradient(180deg,#14091f,#0a0a14); display:flex; flex-direction:column; }
    .dcp-notch { position:absolute; top:8px; left:50%; transform:translateX(-50%); width:84px; height:20px; border-radius:999px; background:#05030a; z-index:5; }
    .dcp-callerid {
        margin: 30px 14px 0; border-radius:18px; padding:14px;
        background: linear-gradient(135deg, rgba(61,107,255,.22), rgba(27,212,217,.14));
        border:1px solid rgba(255,255,255,.12); box-shadow: 0 18px 40px -20px rgba(61,107,255,.6);
    }
    .dcp-avatar { position:relative; overflow:hidden; width:46px; height:46px; border-radius:14px; flex-shrink:0; background:linear-gradient(135deg,#3d6bff,#1bd4d9); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:800; font-size:17px; }
    .dcp-avatar img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; border-radius:inherit; }
    .dcp-chan { width:34px; height:34px; border-radius:11px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:13px; flex-shrink:0; }
    .dcp-keys { margin:16px 22px 20px; display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }
    .dcp-key { aspect-ratio:1; border-radius:999px; background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.08); display:flex; flex-direction:column; align-items:center; justify-content:center; color:#fff; line-height:1; }
    .dcp-key .d { font-size:18px; font-weight:700; }
    .dcp-key .l { font-size:7px; letter-spacing:.12em; color:rgba(255,255,255,.45); margin-top:2px; }
    .dcp-call { margin:0 22px 18px; height:50px; border-radius:999px; background:linear-gradient(135deg,#16a34a,#22c55e); display:flex; align-items:center; justify-content:center; gap:8px; color:#fff; font-weight:700; font-size:14px; box-shadow:0 14px 30px -12px rgba(34,197,94,.8); }

    /* Everything grid cards */
    .dcp-card { transition: transform .35s ease, box-shadow .35s ease, border-color .35s ease; }
    .dcp-card:hover { transform: translateY(-6px); box-shadow: 0 30px 60px -26px rgba(61,107,255,.5); border-color: rgba(61,107,255,.4); }
    .dcp-card-icon { width:46px; height:46px; border-radius:14px; display:flex; align-items:center; justify-content:center; color:#fff; box-shadow:0 12px 28px -10px var(--dcp-c,#3d6bff); background:var(--dcp-c,#3d6bff); }

    /* Channel chips row */
    .dcp-chchip { display:inline-flex; align-items:center; gap:8px; padding:10px 16px; border-radius:999px; font-size:13px; font-weight:700; color:#fff; }

    /* Stat block */
    .dcp-stat { text-align:center; padding:22px 14px; }
    .dcp-stat .num { font-size:2.5rem; font-weight:800; color:#3d6bff; }
    .dcp-stat .lbl { font-size:11px; text-transform:uppercase; letter-spacing:.15em; color:#9ca3af; margin-top:4px; }
    html.light-mode .dcp-stat .lbl { color:#64748b; }

    /* Sync diagram */
    .dcp-sync-node { border-radius:20px; padding:20px; text-align:center; }
    .dcp-sync-arrows i { animation: dcpArrow 2.4s ease-in-out infinite; }
    @keyframes dcpArrow { 0%,100% { opacity:.4; transform: translateX(0); } 50% { opacity:1; transform: translateX(4px); } }

    @media (prefers-reduced-motion: reduce) {
        .dcp-mesh::before, .dcp-phone, .dcp-sync-arrows i { animation: none !important; }
    }

    /* Light mode: the phone screen stays a dark display, so pin its text back
       to light colors (the global html.light-mode remap darkens .text-white /
       .text-gray-300 / accent-400 utilities, which would go dark-on-dark here). */
    html.light-mode .dcp-phone { background: linear-gradient(160deg, #101827, #060b16); }
    html.light-mode .dcp-screen .text-white { color: #ffffff !important; }
    html.light-mode .dcp-screen .text-gray-300 { color: #cbd5e1 !important; }
    html.light-mode .dcp-screen [class*="text-emerald-4"] { color: #34d399 !important; }
</style>

{{-- ============== HERO ============== --}}
<section id="dcp-hero" class="relative pt-20 pb-20 lg:pt-28 lg:pb-28 overflow-hidden">
    <div class="dcp-mesh absolute inset-0" aria-hidden="true"></div>
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-12 items-center">
        <div data-anim="fade-right">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider border"
                  style="background: {{ $accent }}1a; border-color: {{ $accent }}33; color: {{ $accent }};">
                <i class="fas fa-phone-volume text-[10px]"></i> Dialer &amp; Contacts
            </span>
            <h1 class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.05]">
                Every number becomes
                <span class="block grad-text">a real connection.</span>
            </h1>
            <p class="mt-5 text-lg text-gray-400 max-w-xl leading-relaxed">
                A smart T9 dialer, one-tap call / SMS / WhatsApp channels, two-way Google Contacts sync, an AI business-card scanner and phone-to-biolink caller&nbsp;ID &mdash; your entire address book, supercharged inside Sayzio.
            </p>
            <div class="mt-7 flex flex-wrap items-center gap-3">
                @auth
                    <a href="{{ route('user.dashboard') }}" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold inline-flex items-center gap-2">
                        <i class="fas fa-rocket text-xs"></i> Open your dashboard
                    </a>
                @else
                    <a href="{{ route('register.page') }}" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold inline-flex items-center gap-2">
                        <i class="fas fa-rocket text-xs"></i> Start free &mdash; no card
                    </a>
                @endauth
                <a href="#everything" class="px-5 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">
                    See everything it does
                </a>
            </div>
            <p class="mt-5 text-xs text-gray-500">
                <i class="fas fa-check text-[10px] mr-1 text-emerald-400"></i>
                Free Forever plan &middot; Two-way Google sync &middot; Works on web &amp; mobile
            </p>
        </div>
        <div data-anim="fade-left" class="flex justify-center relative">
            <div class="dcp-phone" role="img" aria-label="Sayzio dialer resolving a phone number into a profile">
                <div class="dcp-screen">
                    <div class="dcp-notch" aria-hidden="true"></div>
                    <div class="dcp-callerid">
                        <div class="flex items-center gap-3">
                            <div class="dcp-avatar">AR<img src="{{ asset('images/marketing/contact-aisha.jpg') }}" alt="" loading="lazy" onerror="this.remove()"></div>
                            <div class="min-w-0">
                                <div class="text-sm font-bold text-white leading-tight flex items-center gap-1.5">
                                    Aisha Rahman <i class="fas fa-circle-check text-[11px]" style="color:#3d6bff;"></i>
                                </div>
                                <div class="text-[11px] text-gray-300">@aisha &middot; on Sayzio</div>
                            </div>
                        </div>
                        <div class="text-[11px] text-gray-300 mt-2.5 flex items-center gap-1.5">
                            <i class="fas fa-phone text-[9px] text-emerald-400"></i> +1 (415) 555-0182
                        </div>
                        <div class="flex items-center gap-2 mt-3">
                            <div class="dcp-chan" style="background:#22c55e;" title="Call"><i class="fas fa-phone"></i></div>
                            <div class="dcp-chan" style="background:#3d6bff;" title="SMS"><i class="fas fa-comment-sms"></i></div>
                            <div class="dcp-chan" style="background:#25d366;" title="WhatsApp"><i class="fab fa-whatsapp"></i></div>
                            <div class="dcp-chan" style="background:#229ed9;" title="Telegram"><i class="fab fa-telegram"></i></div>
                            <div class="dcp-chan ml-auto" style="background:rgba(255,255,255,.12);" title="Open biolink"><i class="fas fa-link text-[11px]"></i></div>
                        </div>
                    </div>
                    <div class="dcp-keys mt-auto" aria-hidden="true">
                        @foreach([['1',''],['2','ABC'],['3','DEF'],['4','GHI'],['5','JKL'],['6','MNO'],['7','PQRS'],['8','TUV'],['9','WXYZ'],['*',''],['0','+'],['#','']] as $k)
                            <div class="dcp-key"><span class="d">{{ $k[0] }}</span>@if($k[1] !== '')<span class="l">{{ $k[1] }}</span>@endif</div>
                        @endforeach
                    </div>
                    <div class="dcp-call" aria-hidden="true"><i class="fas fa-phone"></i> Call</div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============== STATS ============== --}}
<section class="py-10 relative overflow-hidden">
    <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach([
            ['6',    'Quick channels'],
            ['2-way','Google Contacts sync'],
            ['T9',   'Smart name search'],
            ['AI',   'Business-card scan'],
        ] as $s)
            <div class="dcp-stat glass rounded-2xl reveal">
                <div class="num">{{ $s[0] }}</div>
                <div class="lbl">{{ $s[1] }}</div>
            </div>
        @endforeach
    </div>
</section>

{{-- ============== EVERYTHING GRID ============== --}}
<section id="everything" class="py-20 lg:py-28 relative overflow-hidden">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14 max-w-2xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:#1bd4d9">Everything in one place</div>
            <h2 class="reveal rd-1 text-4xl sm:text-5xl font-bold tracking-tight mb-4">
                A dialer and address book <span class="grad-text">that actually works for you.</span>
            </h2>
            <p class="reveal rd-2 text-gray-400">From the first keypress to a shareable vCard &mdash; here's the full toolkit.</p>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach([
                ['fa-phone-volume', '#1bd4d9', 'Smart T9 dialer &amp; keypad',   'Tap out a name on the number pad and T9 surfaces the right contact instantly. Flip to a full alphanumeric keyboard whenever you prefer &mdash; both feed the same search.'],
                ['fa-comments',     '#3d6bff', 'Quick channels',                 'Reach anyone through Call, SMS, WhatsApp, Telegram, Signal or Viber &mdash; one tap opens the right app with the number pre-filled.'],
                ['fa-star',         '#e94e8c', 'Speed dial',                     'Pin the people you reach most to a speed-dial row so they are always a single tap away.'],
                ['fa-clock-rotate-left', '#ff8a3c', 'Smart history &amp; frequent', 'Recents and a frequently-contacted list are built automatically, so the right person is always near the top.'],
                ['fa-magnifying-glass', '#22c55e', 'Universal finder',           'One search spans your contacts, people on Sayzio, your links &amp; biolinks and your workspaces &mdash; grouped by category with a clear action for each result.'],
                ['fa-clipboard-list', '#22d3ee', 'Call logging &amp; reminders', 'Log call outcomes and set callback reminders so follow-ups never slip through the cracks.'],
                ['fa-ban',          '#ef4444', 'Spam &amp; block controls',      'Flag and block unwanted numbers to keep your dialer and caller ID clean.'],
                ['fa-rotate',       '#14b8a6', 'Two-way Google Contacts sync',   'Contacts stay in lockstep with Google via the People API &mdash; edits made anywhere flow both directions, incrementally and on a schedule.'],
                ['fa-address-card', '#0ea5e9', 'Phone &rarr; biolink resolution', 'Incoming and saved numbers resolve to rich Sayzio profiles, silently attaching the matching biolink (with detach memory).'],
                ['fa-id-badge',     '#f59e0b', 'Rich identity profiles',         'Every contact holds multiple numbers, emails, addresses, socials and organisations &mdash; not just a single field.'],
                ['fa-file-import',  '#16a34a', 'Bulk CSV / vCard import',        'Import large lists via a parse &rarr; preview &rarr; confirm flow, skipping rows before you commit, with big files processed in the background.'],
                ['fa-id-card',      '#ec4899', 'AI business-card scanner',       'Snap a business card or brochure and AI extracts names, numbers, emails and socials straight into a clean new contact.'],
            ] as $i => $f)
                <div class="reveal rd-{{ ($i % 3) + 1 }} dcp-card glass rounded-3xl p-6">
                    <div class="dcp-card-icon mb-4" style="--dcp-c: {{ $f[1] }};"><i class="fas {{ $f[0] }} text-lg"></i></div>
                    <h3 class="text-lg font-bold mb-1.5">{!! $f[2] !!}</h3>
                    <p class="text-sm text-gray-400 leading-relaxed">{!! $f[3] !!}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============== QUICK CHANNELS ============== --}}
<section class="py-20 lg:py-28 relative overflow-hidden">
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
        <div data-anim="fade-right">
            <div class="text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:#3d6bff">One tap, right app</div>
            <h2 class="text-4xl sm:text-5xl font-bold tracking-tight mb-5">
                Reach anyone, <span class="grad-text">however they prefer.</span>
            </h2>
            <p class="text-lg text-gray-400 leading-relaxed mb-7">
                Every contact carries the channels they actually use. Skip the copy-paste dance &mdash; tap once and Sayzio opens the right app with the number ready to go.
            </p>
            <div class="flex flex-wrap gap-3">
                @foreach([
                    ['fa-phone','#22c55e','Call'],
                    ['fa-comment-sms','#3d6bff','SMS'],
                    ['fab fa-whatsapp','#25d366','WhatsApp'],
                    ['fab fa-telegram','#229ed9','Telegram'],
                    ['fas fa-shield-halved','#3a76f0','Signal'],
                    ['fas fa-phone-volume','#7360f2','Viber'],
                ] as $c)
                    <span class="dcp-chchip glass" style="border:1px solid {{ $c[1] }}55;">
                        <i class="{{ str_starts_with($c[0],'fab') || str_starts_with($c[0],'fas') ? $c[0] : 'fas '.$c[0] }}" style="color:{{ $c[1] }};"></i> {{ $c[2] }}
                    </span>
                @endforeach
            </div>
        </div>
        <div data-anim="fade-left">
            <div class="glass rounded-3xl p-6 sm:p-8 relative overflow-hidden">
                <div class="absolute -top-16 -right-16 w-56 h-56 rounded-full opacity-25" style="background:#3d6bff;"></div>
                <div class="relative space-y-3">
                    @foreach([
                        ['MK','Maria Kovac','+385 91 555 0110','#e94e8c'],
                        ['JT','James Tanaka','+81 90 5555 0143','#1bd4d9'],
                        ['LO','Lola Okafor','+234 802 555 0178','#ff8a3c'],
                    ] as $r)
                        <div class="flex items-center gap-3 p-3 rounded-2xl" style="background:rgba(255,255,255,.05);">
                            <div class="w-11 h-11 rounded-xl flex items-center justify-center text-white font-bold flex-shrink-0" style="background:linear-gradient(135deg,{{ $r[3] }},#3d6bff);">{{ $r[0] }}</div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-bold truncate">{{ $r[1] }}</div>
                                <div class="text-[11px] text-gray-400">{{ $r[2] }}</div>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs" style="background:#22c55e;"><i class="fas fa-phone"></i></span>
                                <span class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs" style="background:#25d366;"><i class="fab fa-whatsapp"></i></span>
                                <span class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs" style="background:#3d6bff;"><i class="fas fa-comment-sms"></i></span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============== GOOGLE SYNC ============== --}}
<section id="sync" class="py-20 lg:py-28 relative overflow-hidden">
    <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12 max-w-2xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:#e94e8c">Always in sync</div>
            <h2 class="reveal rd-1 text-4xl sm:text-5xl font-bold tracking-tight mb-4">
                Two-way Google Contacts sync, <span class="grad-text">on autopilot.</span>
            </h2>
            <p class="reveal rd-2 text-gray-400">Add a contact on your phone, edit one in Sayzio &mdash; both stay identical. Changes flow both ways, incrementally, on a schedule and the moment you make them.</p>
        </div>
        <div class="grid sm:grid-cols-[1fr_auto_1fr] gap-5 items-center">
            <div class="dcp-sync-node glass reveal">
                <div class="w-12 h-12 rounded-2xl mx-auto mb-3 flex items-center justify-center text-white text-xl" style="background:#3d6bff;"><i class="fab fa-google"></i></div>
                <div class="font-bold">Google Contacts</div>
                <div class="text-xs text-gray-400 mt-1">Your existing address book</div>
            </div>
            <div class="dcp-sync-arrows reveal rd-1 flex sm:flex-col items-center justify-center gap-2 text-2xl" style="color:#1bd4d9;" aria-hidden="true">
                <i class="fas fa-arrow-right sm:rotate-0"></i>
                <i class="fas fa-arrow-left sm:rotate-0"></i>
            </div>
            <div class="dcp-sync-node glass reveal rd-2">
                <div class="w-12 h-12 rounded-2xl mx-auto mb-3 flex items-center justify-center text-white text-xl grad-bar"><i class="fas fa-address-book"></i></div>
                <div class="font-bold">Sayzio Contacts</div>
                <div class="text-xs text-gray-400 mt-1">Dialer, caller ID &amp; profiles</div>
            </div>
        </div>
        <div class="grid sm:grid-cols-3 gap-4 mt-10">
            @foreach([
                ['fa-bolt','Instant on edit','Create or edit a contact and the change pushes immediately.'],
                ['fa-arrows-rotate','Scheduled sweep','A background sync reconciles both sides every 30 minutes.'],
                ['fa-code-branch','Incremental &amp; safe','Only changes move across, with tombstone-safe deletes.'],
            ] as $i => $f)
                <div class="reveal rd-{{ $i+1 }} glass rounded-2xl p-5">
                    <i class="fas {{ $f[0] }} text-lg mb-2" style="color:#1bd4d9;"></i>
                    <div class="font-bold text-sm mb-1">{!! $f[1] !!}</div>
                    <div class="text-xs text-gray-400 leading-relaxed">{!! $f[2] !!}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============== IMPORT & SCAN ============== --}}
<section class="py-20 lg:py-28 relative overflow-hidden">
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
        <div data-anim="fade-right">
            <div class="glass rounded-3xl p-6 sm:p-8 relative overflow-hidden">
                <div class="absolute -bottom-16 -left-16 w-56 h-56 rounded-full opacity-20" style="background:#ff8a3c;"></div>
                <div class="relative">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-11 h-11 rounded-xl flex items-center justify-center text-white" style="background:#ec4899;"><i class="fas fa-id-card"></i></div>
                        <div>
                            <div class="font-bold text-sm">Business card scanned</div>
                            <div class="text-[11px] text-gray-400">AI extracted 6 fields &middot; 1.4s</div>
                        </div>
                        <span class="ml-auto text-[10px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full" style="background:rgba(34,197,94,.15); color:#22c55e;">Ready</span>
                    </div>
                    <div class="space-y-2">
                        @foreach([
                            ['fa-user','Priya Nair'],
                            ['fa-briefcase','Head of Growth · Nimbus Labs'],
                            ['fa-phone','+91 98765 43210'],
                            ['fa-envelope','priya@nimbuslabs.io'],
                            ['fa-globe','nimbuslabs.io'],
                        ] as $row)
                            <div class="flex items-center gap-3 text-sm p-2.5 rounded-xl" style="background:rgba(255,255,255,.05);">
                                <i class="fas {{ $row[0] }} text-xs w-4 text-center" style="color:#1bd4d9;"></i>
                                <span class="text-gray-200">{{ $row[1] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div data-anim="fade-left">
            <div class="text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:#ff8a3c">Get contacts in fast</div>
            <h2 class="text-4xl sm:text-5xl font-bold tracking-tight mb-5">
                Import a spreadsheet <span class="grad-text">or just snap a card.</span>
            </h2>
            <p class="text-lg text-gray-400 leading-relaxed mb-6">
                Bring in a whole list from CSV or vCard with a safe parse &rarr; preview &rarr; confirm flow &mdash; skip the rows you don't want before anything is saved, and let big files finish in the background. Or point your camera at a business card and let AI do the typing.
            </p>
            <ul class="space-y-3">
                @foreach([
                    'Preview every row and skip duplicates before committing',
                    'Large imports run as a background job with live progress',
                    'AI card &amp; brochure scanning fills names, numbers, emails &amp; socials',
                    'Export any contact as a shareable vCard in one tap',
                ] as $li)
                    <li class="flex items-start gap-3 text-sm text-gray-300">
                        <i class="fas fa-circle-check mt-0.5" style="color:#22c55e;"></i>
                        <span>{!! $li !!}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</section>

{{-- ============== CLOSING CTA ============== --}}
<section class="py-20 lg:py-28 relative overflow-hidden">
    <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grad-border rounded-3xl p-8 sm:p-12 text-center relative overflow-hidden" data-anim="fade-up">
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight mb-4">
                Turn your contacts into <span class="grad-text">conversations.</span>
            </h2>
            <p class="text-gray-400 max-w-2xl mx-auto mb-8">
                Bring your address book to Sayzio and get a smart dialer, quick channels, Google sync and phone-to-profile caller ID &mdash; free to start.
            </p>
            <div class="flex flex-wrap items-center justify-center gap-3">
                @auth
                    <a href="{{ route('user.dashboard') }}" class="px-7 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold">Go to your dashboard</a>
                @else
                    <a href="{{ route('register.page') }}" class="px-7 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold">Get started free</a>
                @endauth
                <a href="{{ route('site.pricing') }}" class="px-7 py-3 rounded-full glass text-white hover:bg-white/10 text-sm font-semibold">See pricing</a>
            </div>
        </div>
    </div>
</section>
@endsection
