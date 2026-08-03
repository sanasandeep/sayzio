@extends('public.layouts.site')
@section('title', 'Résumé &amp; Portfolio Builder')

@section('content')
@php
    $accent = '#3d6bff';
    $templates = [
        ['name' => 'Minimalist',   'tag' => 'ATS-safe',    'colors' => ['#0f172a', '#3d6bff']],
        ['name' => 'Design Lead',  'tag' => 'Creative',    'colors' => ['#3d6bff', '#e94e8c']],
        ['name' => 'Engineer',     'tag' => 'Tech',        'colors' => ['#1bd4d9', '#3d6bff']],
        ['name' => 'Classic',      'tag' => 'Corporate',   'colors' => ['#0f172a', '#1bd4d9']],
        ['name' => 'Bold',         'tag' => 'Marketing',   'colors' => ['#ff8a3c', '#e94e8c']],
        ['name' => 'Academic',     'tag' => 'Research',    'colors' => ['#16a34a', '#22d3ee']],
        ['name' => 'Photographer', 'tag' => 'Portfolio',   'colors' => ['#e94e8c', '#ff8a3c']],
        ['name' => 'Architect',    'tag' => 'Visual',      'colors' => ['#22d3ee', '#3d6bff']],
    ];
@endphp

<style>
    .rbp-mesh::before {
        content:""; position:absolute; inset:-15%;
        background: radial-gradient(ellipse 75% 75% at 50% 50%, rgba(61,107,255,.08), transparent 78%);
        pointer-events:none;
        animation: rbpMesh 14s ease-in-out infinite alternate;
    }
    @keyframes rbpMesh { 0% { transform: translate3d(0,0,0); } 100% { transform: translate3d(2%,-2%,0) scale(1.05); } }

    /* hero animated paper */
    .rbp-paper {
        position: relative; aspect-ratio: 1 / 1.32; max-width: 360px;
        border-radius: 22px; overflow: hidden;
        background: linear-gradient(180deg,#fff,#f5f7fb); color:#0f172a;
        box-shadow: 0 30px 80px -30px rgba(61,107,255,.55), 0 12px 30px -10px rgba(0,0,0,.55);
        animation: rbpFloat 6s ease-in-out infinite;
    }
    @keyframes rbpFloat { 0%,100% { transform: translateY(0) rotate(-3deg); } 50% { transform: translateY(-10px) rotate(-3deg); } }
    .rbp-paper-head {
        padding: 22px; color:#fff;
        background: linear-gradient(135deg,#3d6bff,#6e61ff,#22d3ee);
    }
    /* Hero art wrapper — inline-block so it shrinks to the paper width and the
       floating status pill can centre itself over the paper. */
    .rbp-hero-art { position: relative; display: inline-block; }

    /* ===== Live "watch it build" sequence (reuses the home Resume section approach) =====
       RESTING / no-JS / reduced-motion: the résumé is already fully assembled and the
       status reads "AI polished". The scatter + sequenced reveal + looping only kick in
       once JS adds `.rb-armed`, which it never does under reduced motion. */
    .rb-build { will-change: transform, opacity; }

    .rb-bar { height: 7px; border-radius: 999px; background: #e5e7eb; overflow: hidden; position: relative; }
    .rb-bar > span {
        position:absolute; left:0; top:0; bottom:0; border-radius: inherit;
        background: linear-gradient(90deg, #3d6bff, #6e61ff, #22d3ee);
        transform-origin: left center; width: var(--rb-w, 70%); transform: scaleX(1);
    }
    @keyframes rbFill { from { transform: scaleX(0); } to { transform: scaleX(1); } }

    .rb-chip {
        display:inline-flex; align-items:center; gap:4px;
        font-size: 10px; font-weight: 700; padding: 4px 8px; border-radius: 999px;
        background: rgba(61,107,255,.10); color: #2342c7; border: 1px solid rgba(61,107,255,.25);
    }

    /* Typed experience line caret — hidden at rest, blinks while writing. */
    .rb-type-caret { display:inline; opacity:0; color:#3d6bff; font-weight:400; }

    /* Floating status beat above the paper. Two stacked spans crossfade:
       "AI writing…" while building → "AI polished" once assembled. */
    .rb-status {
        position: absolute; top: -14px; left: 50%; transform: translateX(-50%);
        z-index: 6; display: inline-grid; pointer-events: none;
    }
    .rb-status-writing, .rb-status-done {
        grid-area: 1 / 1;
        display: inline-flex; align-items: center; gap: 7px; white-space: nowrap;
        padding: 7px 14px; border-radius: 999px;
        font-size: 11px; font-weight: 700; color: #fff;
        background: rgba(15,18,28,.85); backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,.10);
        box-shadow: 0 18px 40px -16px rgba(0,0,0,.7);
        transition: opacity .4s ease;
    }
    .rb-status-done i { color: #1ed760; font-size: 12px; }
    .rb-dots { display: inline-flex; align-items: center; gap: 4px; }
    .rb-dots i {
        width: 6px; height: 6px; border-radius: 50%; background: #1bd4d9; opacity: .6;
        animation: rbDot 1.25s ease-in-out infinite;
    }
    .rb-dots i:nth-child(2) { animation-delay: .18s; }
    .rb-dots i:nth-child(3) { animation-delay: .36s; }
    @keyframes rbDot { 0%,60%,100% { transform: translateY(0); opacity: .4; } 30% { transform: translateY(-3px); opacity: 1; } }

    /* Crossfade: resting (no `.rb-armed`) shows the "AI polished" done chip. */
    .rb-status-writing { opacity: 0; }
    .rb-status-done    { opacity: 1; }
    .rb-armed:not(.rb-built) .rb-status-writing { opacity: 1; }
    .rb-armed:not(.rb-built) .rb-status-done    { opacity: 0; }
    .rb-armed.rb-built .rb-status-writing { opacity: 0; }
    .rb-armed.rb-built .rb-status-done    { opacity: 1; }

    @media (prefers-reduced-motion: no-preference) {
        /* ARMED START STATE — content blocks hidden + scattered, status "writing".
           Driven entirely by JS once it adds `.rb-armed`. */
        .rb-armed .rb-build {
            opacity: 0;
            transform: translate(var(--tx, 0), var(--ty, 18px)) rotate(var(--rot, 0deg)) scale(.97);
            transition: opacity .55s ease, transform .6s cubic-bezier(.34, 1.56, .64, 1);
        }
        .rb-armed .rb-build.is-in { opacity: 1; transform: none; }

        /* Skill bars stay empty while armed, then fill once their block lands. */
        .rb-armed .rb-bar > span { transform: scaleX(0); }
        .rb-armed .rb-build.is-in .rb-bar > span { animation: rbFill 1.1s ease-out forwards; }

        /* Typing caret blinks while writing, vanishes once polished. */
        .rb-armed .rb-type-caret { opacity: 1; animation: rbCaret 1.05s step-end infinite; }
        .rb-armed.rb-built .rb-type-caret { opacity: 0; animation: none; }

        /* Loop reset: gently fade out the assembled page before replaying. */
        .rb-armed.rb-fading .rb-build {
            opacity: 0 !important;
            transform: translateY(-8px) !important;
            transition: opacity .5s ease, transform .5s ease !important;
        }
    }
    @keyframes rbCaret { 0%, 49% { opacity: 1; } 50%, 100% { opacity: 0; } }

    /* Step cards */
    .rbp-step { position: relative; transition: transform .35s ease; }
    .rbp-step:hover { transform: translateY(-6px); }
    .rbp-step-num {
        position:absolute; top:14px; right:18px; font-size: 3rem; font-weight:800; line-height:1; opacity:.14;
        color: var(--rbp-c,#3d6bff);
    }
    .rbp-step-icon {
        width:56px; height:56px; border-radius:18px;
        background: var(--rbp-c,#3d6bff);
        color:#fff; display:flex; align-items:center; justify-content:center;
        box-shadow: 0 12px 28px -10px var(--rbp-c,#3d6bff); position:relative;
    }
    .rbp-step-icon::after {
        content:""; position:absolute; inset:-5px; border-radius:22px;
        border: 2px solid color-mix(in srgb, var(--rbp-c,#3d6bff) 50%, transparent);
        animation: rbpPulse 2.4s ease-in-out infinite; opacity:.3;
    }
    @keyframes rbpPulse { 0%,100% { transform:scale(1); opacity:.25; } 50% { transform:scale(1.08); opacity:.65; } }

    /* Templates */
    .rbp-tpl {
        position: relative; aspect-ratio: 3/4; border-radius: 16px; overflow: hidden;
        background: linear-gradient(180deg,#fff,#eef0f7); color:#0f172a;
        box-shadow: 0 20px 40px -20px rgba(0,0,0,.55);
        transition: transform .35s ease, box-shadow .35s ease;
    }
    .rbp-tpl:hover { transform: translateY(-6px) rotate(-1deg); box-shadow: 0 30px 60px -22px rgba(61,107,255,.5); }
    .rbp-tpl-head { height: 38%; padding: 14px; color:#fff; }
    .rbp-tpl-body { padding: 12px 14px; display:flex; flex-direction:column; gap:6px; }
    .rbp-tpl-body span { height: 5px; border-radius: 3px; background: rgba(15,23,42,.10); }
    .rbp-tpl-body span:nth-child(odd) { width: 80%; }
    .rbp-tpl-body span:nth-child(even) { width: 60%; }
    .rbp-tpl-tag {
        position: absolute; top: 10px; right: 10px;
        font-size: 9px; font-weight: 800; padding: 3px 8px; border-radius: 999px;
        background: rgba(0,0,0,.55); color:#fff; backdrop-filter: blur(6px); letter-spacing: .04em; text-transform: uppercase;
    }

    /* Comparison */
    .rbp-cmp { display:grid; grid-template-columns: 1fr 1fr; gap: 18px; }
    .rbp-cmp .col { padding: 22px; border-radius: 22px; }
    .rbp-cmp .bad  { background: rgba(239,68,68,.05); border:1px solid rgba(239,68,68,.18); }
    .rbp-cmp .good { background: rgba(34,197,94,.06); border:1px solid rgba(34,197,94,.22); position:relative; overflow:hidden; }
    .rbp-cmp .good::before { content:""; position:absolute; inset:-1px; border-radius:inherit; background: rgba(34,197,94,.45); opacity:.18; filter: blur(20px); pointer-events:none; }

    /* Stat counter */
    .rbp-stat { text-align:center; padding: 22px 14px; }
    .rbp-stat .num { font-size: 2.5rem; font-weight: 800; color:#3d6bff; }
    .rbp-stat .lbl { font-size: 11px; text-transform: uppercase; letter-spacing: .15em; color: #9ca3af; margin-top: 4px; }

    /* Light-mode legibility: stat label hardcodes a light gray that drops
       below contrast on the white light-mode background. */
    html.light-mode .rbp-stat .lbl { color: #64748b; }

    @media (max-width: 640px) {
        .rbp-cmp { grid-template-columns: 1fr; }
    }

    /* Reduced motion / no-JS: freeze the ambient + build animations and show the
       fully assembled résumé at rest — no motion. */
    @media (prefers-reduced-motion: reduce) {
        .rbp-mesh::before,
        .rbp-paper,
        .rb-dots i { animation: none !important; }
        .rb-bar > span { transform: scaleX(1) !important; animation: none !important; }
    }
</style>

{{-- ============== HERO ============== --}}
<section id="rbp-hero" class="relative pt-20 pb-20 lg:pt-28 lg:pb-28 overflow-hidden">
    <div class="rbp-mesh absolute inset-0" aria-hidden="true"></div>
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-12 items-center">
        <div data-anim="fade-right">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider border"
                  style="background: {{ $accent }}1a; border-color: {{ $accent }}33; color: {{ $accent }};">
                <i class="fas fa-file-lines text-[10px]"></i> Résumé &amp; Portfolio
            </span>
            <h1 class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.05]">
                Your résumé &amp; portfolio,
                <span class="block grad-text">built in 5 minutes flat.</span>
            </h1>
            <p class="mt-5 text-lg text-gray-400 max-w-xl leading-relaxed">
                Drag-and-drop sections. AI-polished bullet points. 20+ recruiter-tested templates. A public portfolio link at <span class="font-semibold text-white">1inme.com/you/cv</span> &mdash; and a pixel-perfect PDF export when you need to email it.
            </p>
            <div class="mt-7 flex flex-wrap items-center gap-3">
                <a href="/register" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold inline-flex items-center gap-2">
                    <i class="fas fa-rocket text-xs"></i> Start free &mdash; no card
                </a>
                <a href="#templates" class="px-5 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">
                    Browse templates
                </a>
            </div>
            <p class="mt-5 text-xs text-gray-500">
                <i class="fas fa-check text-[10px] mr-1 text-emerald-400"></i>
                Free Forever plan &middot; Unlimited public portfolios &middot; 3 PDF exports/month
            </p>
        </div>
        <div data-anim="fade-left" class="flex justify-center">
            <div class="rbp-hero-art">
                {{-- Status beat: "AI writing…" during the build → "AI polished" once done.
                     Sits at the resting (done) state with no JS / reduced motion. --}}
                <div class="rb-status" aria-hidden="true">
                    <span class="rb-status-writing"><span class="rb-dots"><i></i><i></i><i></i></span> AI writing…</span>
                    <span class="rb-status-done"><i class="fas fa-circle-check"></i> AI polished</span>
                </div>

                <div class="rbp-paper" role="img" aria-label="Résumé preview">
                    <div class="rbp-paper-head rb-build rb-b-head" style="--ty:-26px;--rot:-3deg;">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center font-bold text-white" style="background: #3d6bff;">JS</div>
                            <div>
                                <div class="text-base font-bold leading-tight">Jordan Silva</div>
                                <div class="text-[11px] opacity-90">Full-stack Engineer · Lisbon</div>
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            <span class="text-[10px] font-semibold bg-white/20 px-2 py-0.5 rounded-full">TypeScript</span>
                            <span class="text-[10px] font-semibold bg-white/20 px-2 py-0.5 rounded-full">React</span>
                            <span class="text-[10px] font-semibold bg-white/20 px-2 py-0.5 rounded-full">PostgreSQL</span>
                            <span class="text-[10px] font-semibold bg-white/20 px-2 py-0.5 rounded-full">AWS</span>
                        </div>
                    </div>
                    <div class="px-5 py-4 space-y-4">
                        <div class="rb-build rb-b-exp" style="--tx:-40px;--rot:-2deg;">
                            <div class="flex items-center justify-between mb-1">
                                <div class="text-[11px] font-bold uppercase tracking-wider text-blue-700">Experience</div>
                                <span class="rb-chip"><i class="fas fa-sparkles text-[8px]"></i> AI polished</span>
                            </div>
                            <div class="text-[12px] font-bold text-slate-900">Senior Engineer · Remote</div>
                            <div class="text-[10px] text-slate-500">Acme Inc. &middot; 2022 to Now</div>
                            <div class="text-[10px] text-slate-600 mt-1 leading-snug"><span class="rb-type">Cut p95 latency by 38% by replacing N+1 queries with cursor pagination.</span><span class="rb-type-caret" aria-hidden="true">▍</span></div>
                        </div>
                        <div class="rb-build rb-b-skills" style="--tx:40px;--rot:2deg;">
                            <div class="text-[11px] font-bold uppercase tracking-wider text-blue-700 mb-2">Skills</div>
                            <div class="space-y-2">
                                <div>
                                    <div class="flex justify-between text-[10px] mb-1"><span class="font-semibold text-slate-700">Backend</span><span class="text-slate-500">92%</span></div>
                                    <div class="rb-bar"><span style="--rb-w:92%"></span></div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-[10px] mb-1"><span class="font-semibold text-slate-700">Frontend</span><span class="text-slate-500">85%</span></div>
                                    <div class="rb-bar"><span style="--rb-w:85%; animation-delay:.2s;"></span></div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-[10px] mb-1"><span class="font-semibold text-slate-700">DevOps</span><span class="text-slate-500">70%</span></div>
                                    <div class="rb-bar"><span style="--rb-w:70%; animation-delay:.4s;"></span></div>
                                </div>
                            </div>
                        </div>
                        <div class="rb-build rb-b-port" style="--ty:32px;--rot:3deg;">
                            <div class="text-[11px] font-bold uppercase tracking-wider text-blue-700 mb-1.5">Portfolio</div>
                            <div class="grid grid-cols-3 gap-1.5">
                                <div class="aspect-square rounded-md" style="background:linear-gradient(135deg,#3d6bff,#6e61ff,#22d3ee);"></div>
                                <div class="aspect-square rounded-md" style="background:linear-gradient(135deg,#e94e8c,#ff8a3c);"></div>
                                <div class="aspect-square rounded-md" style="background:linear-gradient(135deg,#22d3ee,#16a34a);"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============== STATS ============== --}}
<section class="py-10 relative overflow-hidden">
    <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach([
            ['5 min',  'Average build time'],
            ['20+',    'Recruiter-tested templates'],
            ['1-tap',  'PDF export'],
            ['100%',   'ATS-friendly'],
        ] as $s)
            <div class="rbp-stat glass rounded-2xl reveal">
                <div class="num">{{ $s[0] }}</div>
                <div class="lbl">{{ $s[1] }}</div>
            </div>
        @endforeach
    </div>
</section>

{{-- ============== HOW IT WORKS ============== --}}
<section class="py-20 lg:py-28 relative overflow-hidden">
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14 max-w-2xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:#1bd4d9">How it works</div>
            <h2 class="reveal rd-1 text-4xl sm:text-5xl font-bold tracking-tight mb-4">
                From blank page to <span class="grad-text">"hire me"</span>.
            </h2>
            <p class="reveal rd-2 text-gray-400">Four steps. No design degree, no template fees, no recruiter rejection.</p>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
            @foreach([
                ['01','fa-user-plus',         '#1bd4d9', 'Tell us about you',     'Paste a LinkedIn URL or fill in 5 quick fields. We pre-fill everything we can.'],
                ['02','fa-wand-magic-sparkles','#3d6bff', 'Let AI polish it',     'AI rewrites bullet points with metrics, action verbs and ATS keywords for your role.'],
                ['03','fa-palette',           '#e94e8c', 'Pick a template',       '20+ recruiter-tested designs. Recolor, reorder, swap fonts &mdash; all live preview.'],
                ['04','fa-share-nodes',       '#ff8a3c', 'Share &amp; export',    'Public link at 1inme.com/you/cv, private link, or pixel-perfect PDF download.'],
            ] as $i => $s)
                <div class="reveal rd-{{ $i + 1 }} rbp-step glass rounded-3xl p-6 text-center" style="--rbp-c: {{ $s[2] }};">
                    <span class="rbp-step-num">{{ $s[0] }}</span>
                    <div class="rbp-step-icon mx-auto mb-4"><i class="fas {{ $s[1] }} text-xl"></i></div>
                    <h3 class="text-lg font-bold mb-1.5">{!! $s[3] !!}</h3>
                    <p class="text-sm text-gray-400 leading-relaxed">{!! $s[4] !!}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============== TEMPLATES ============== --}}
<section id="templates" class="py-20 lg:py-28 relative overflow-hidden">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12 max-w-2xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:#e94e8c">Templates</div>
            <h2 class="reveal rd-1 text-4xl sm:text-5xl font-bold tracking-tight mb-4">
                20+ designs, <span class="grad-text">all recruiter-approved.</span>
            </h2>
            <p class="reveal rd-2 text-gray-400">Every template renders cleanly to PDF and parses correctly through ATS systems like Greenhouse, Lever and Workday.</p>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-5">
            @foreach($templates as $i => $t)
                <div class="reveal rd-{{ ($i % 4) + 1 }} rbp-tpl">
                    <span class="rbp-tpl-tag">{{ $t['tag'] }}</span>
                    <div class="rbp-tpl-head" style="background: linear-gradient(135deg, {{ $t['colors'][0] }}, {{ $t['colors'][1] }});">
                        <div class="text-sm font-bold leading-tight">{{ $t['name'] }}</div>
                        <div class="text-[10px] opacity-80 mt-0.5">Your name here</div>
                    </div>
                    <div class="rbp-tpl-body">
                        <span></span><span></span><span></span><span></span><span></span><span></span>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="text-center mt-10">
            <a href="/register" class="btn-bounce btn-glow inline-flex items-center gap-2 px-7 py-3.5 grad-bar text-white rounded-full text-sm font-bold">
                Try a template free <i class="fas fa-arrow-right text-xs"></i>
            </a>
        </div>
    </div>
</section>

{{-- ============== COMPARISON ============== --}}
<section class="py-20 lg:py-28 relative overflow-hidden">
    <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12 max-w-2xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:#22c55e">Why Sayzio</div>
            <h2 class="reveal rd-1 text-4xl sm:text-5xl font-bold tracking-tight mb-4">
                The old way vs. <span class="grad-text">the Sayzio way.</span>
            </h2>
        </div>
        <div class="rbp-cmp">
            <div class="col bad reveal rd-1">
                <div class="text-[11px] font-bold uppercase tracking-wider text-red-300 mb-3">The old way</div>
                <ul class="space-y-3 text-sm text-gray-300">
                    <li class="flex items-start gap-2.5"><i class="fas fa-times text-red-400 mt-1"></i><span>Wrestle with Word margins for 3 hours</span></li>
                    <li class="flex items-start gap-2.5"><i class="fas fa-times text-red-400 mt-1"></i><span>$45 for a "premium" template that breaks ATS</span></li>
                    <li class="flex items-start gap-2.5"><i class="fas fa-times text-red-400 mt-1"></i><span>Email a static PDF and hope they open it</span></li>
                    <li class="flex items-start gap-2.5"><i class="fas fa-times text-red-400 mt-1"></i><span>Rewrite every bullet point yourself</span></li>
                    <li class="flex items-start gap-2.5"><i class="fas fa-times text-red-400 mt-1"></i><span>No portfolio link &mdash; or one stuck on Behance</span></li>
                </ul>
            </div>
            <div class="col good reveal rd-2">
                <div class="relative text-[11px] font-bold uppercase tracking-wider text-emerald-300 mb-3">The Sayzio way</div>
                <ul class="relative space-y-3 text-sm text-white">
                    <li class="flex items-start gap-2.5"><i class="fas fa-check text-emerald-400 mt-1"></i><span>Drag, drop, done &mdash; live preview, no save button</span></li>
                    <li class="flex items-start gap-2.5"><i class="fas fa-check text-emerald-400 mt-1"></i><span>20+ templates, all free, all ATS-clean</span></li>
                    <li class="flex items-start gap-2.5"><i class="fas fa-check text-emerald-400 mt-1"></i><span>Public link with view analytics &mdash; know who looked</span></li>
                    <li class="flex items-start gap-2.5"><i class="fas fa-check text-emerald-400 mt-1"></i><span>AI polishes every bullet with metrics &amp; keywords</span></li>
                    <li class="flex items-start gap-2.5"><i class="fas fa-check text-emerald-400 mt-1"></i><span>Portfolio &amp; résumé live together at 1inme.com/you</span></li>
                </ul>
            </div>
        </div>
    </div>
</section>

{{-- ============== FAQS ============== --}}
<section class="py-20 lg:py-24 relative overflow-hidden">
    <div class="relative max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:#3d6bff">FAQs</div>
            <h2 class="reveal rd-1 text-3xl sm:text-4xl font-bold tracking-tight">Quick answers.</h2>
        </div>
        <div class="space-y-3" x-data="{ open: 0 }">
            @foreach([
                ['Is it really free?', 'Yes &mdash; the Free Forever plan includes unlimited public portfolios and 3 PDF exports per month. Upgrade only if you need unlimited exports or premium templates.'],
                ['Will my résumé pass ATS systems?', 'Every template is structured so that Greenhouse, Lever, Workday and other ATS parsers read it cleanly. The PDF export uses selectable text and embedded fonts &mdash; no scanned images.'],
                ['Can I import from LinkedIn?', 'Paste your LinkedIn URL and we pre-fill experience, education and skills. You can edit everything before publishing.'],
                ['Who can see my portfolio link?', 'You choose: public (indexed and discoverable), unlisted (link-only) or private (only you). Email and phone can be hidden from public view in one tap.'],
                ['Can I host multiple résumés?', 'Yes &mdash; create a different version for every role. Each gets its own URL slug like 1inme.com/you/cv-design or 1inme.com/you/cv-pm.'],
            ] as $i => $f)
                <div class="reveal rd-{{ ($i % 4) + 1 }} glass rounded-2xl overflow-hidden">
                    <button type="button" @click="open === {{ $i }} ? open = -1 : open = {{ $i }}" class="w-full flex items-center justify-between gap-4 px-5 py-4 text-left">
                        <span class="text-base font-bold text-white">{!! $f[0] !!}</span>
                        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform" :class="open === {{ $i }} ? 'rotate-180' : ''"></i>
                    </button>
                    <div x-show="open === {{ $i }}" x-cloak x-transition.opacity.duration.200ms class="px-5 pb-4 text-sm text-gray-400 leading-relaxed">{!! $f[1] !!}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ============== FINAL CTA ============== --}}
<section class="py-20 lg:py-24 relative overflow-hidden">
    <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="relative rounded-3xl p-10 text-center overflow-hidden" style="background: rgba(61,107,255,.16); border: 1px solid rgba(255,255,255,.08);">
            <div class="absolute inset-[-1px] rounded-[inherit] pointer-events-none" style="background: rgba(61,107,255,.45); opacity:.18; filter: blur(28px);"></div>
            <div class="relative">
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight mb-4">
                    Your next role is one <span class="grad-text">résumé away.</span>
                </h2>
                <p class="text-gray-300 mb-7 max-w-xl mx-auto">Build it free in 5 minutes. Share it as a link or download a perfect PDF. No card, no setup call, no fuss.</p>
                <div class="flex flex-wrap items-center justify-center gap-3">
                    <a href="/register" class="btn-bounce btn-glow inline-flex items-center gap-2 px-7 py-3.5 grad-bar text-white rounded-full text-sm font-bold">
                        Start building &mdash; free <i class="fas fa-arrow-right text-xs"></i>
                    </a>
                    <a href="{{ route('site.pricing') }}" class="inline-flex items-center gap-2 px-5 py-3 rounded-full glass text-white hover:bg-white/10 text-xs font-semibold">See plans</a>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Live "watch it build" sequence for the hero résumé (mirrors the home Resume section). --}}
<script>
(function () {
    var section = document.getElementById('rbp-hero');
    if (!section) return;
    var wrap = section.querySelector('.rbp-hero-art');
    if (!wrap) return;

    var blocks = Array.prototype.slice.call(wrap.querySelectorAll('.rb-build'));
    if (!blocks.length) return;
    var typeEl = wrap.querySelector('.rb-type');
    var fullText = typeEl ? typeEl.textContent : '';

    var reduceMq = window.matchMedia('(prefers-reduced-motion: reduce)');
    var runId = 0;
    var running = false;

    function reveal(cls) {
        var el = wrap.querySelector('.' + cls);
        if (el) el.classList.add('is-in');
    }

    function reset() {
        wrap.classList.remove('rb-built', 'rb-fading');
        for (var i = 0; i < blocks.length; i++) blocks[i].classList.remove('is-in');
        if (typeEl) typeEl.textContent = '';
    }

    function sleep(ms, id) {
        return new Promise(function (resolve, reject) {
            setTimeout(function () { id === runId ? resolve() : reject(); }, ms);
        });
    }

    // Type the AI-written experience line character by character.
    function typeText(id) {
        return new Promise(function (resolve, reject) {
            var i = 0;
            (function step() {
                if (id !== runId) return reject();
                if (typeEl && i <= fullText.length) {
                    typeEl.textContent = fullText.slice(0, i);
                    i++;
                    setTimeout(step, 18 + Math.random() * 30);
                } else {
                    resolve();
                }
            })();
        });
    }

    // One full build cycle: header → experience (types in) → skills (bars fill)
    // → portfolio tiles → "AI polished", hold, fade out, replay.
    function play(id) {
        reset();
        return sleep(420, id)
            .then(function () { reveal('rb-b-head'); return sleep(560, id); })
            .then(function () { reveal('rb-b-exp'); return sleep(300, id); })
            .then(function () { return typeText(id); })
            .then(function () { return sleep(340, id); })
            .then(function () { reveal('rb-b-skills'); return sleep(1150, id); })
            .then(function () { reveal('rb-b-port'); return sleep(680, id); })
            .then(function () { wrap.classList.add('rb-built'); return sleep(3200, id); })
            .then(function () { wrap.classList.add('rb-fading'); return sleep(620, id); })
            .then(function () { reset(); return sleep(420, id); })
            .then(function () { if (id === runId) play(id); })
            .catch(function () {});
    }

    function start() {
        if (reduceMq.matches || running) return;
        running = true;
        runId++;
        wrap.classList.add('rb-armed');
        reset();
        play(runId);
    }

    function stop() {
        running = false;
        runId++;
        reset();
    }

    if (reduceMq.matches) return; // leave fully built, no sequencing

    if (typeof window.IntersectionObserver !== 'function') return; // no IO: leave fully built, never arm/hide

    wrap.classList.add('rb-armed'); // hide blocks until the section is in view

    var io = new IntersectionObserver(function (entries) {
        for (var i = 0; i < entries.length; i++) {
            entries[i].isIntersecting ? start() : stop();
        }
    }, { threshold: 0.25, rootMargin: '0px 0px -10% 0px' });
    io.observe(section);

    var onReduceChange = function (e) {
        if (e.matches) {
            running = false;
            runId++;
            wrap.classList.remove('rb-armed', 'rb-built', 'rb-fading');
            for (var i = 0; i < blocks.length; i++) blocks[i].classList.remove('is-in');
            if (typeEl) typeEl.textContent = fullText;
        }
    };
    if (reduceMq.addEventListener) reduceMq.addEventListener('change', onReduceChange);
    else if (reduceMq.addListener) reduceMq.addListener(onReduceChange);
})();
</script>
@endsection
