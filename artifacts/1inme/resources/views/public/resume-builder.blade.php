@extends('public.layouts.site')
@section('title', 'Résumé &amp; Portfolio Builder')

@section('content')
@php
    $accent = '#7c3aed';
    $templates = [
        ['name' => 'Minimalist',   'tag' => 'ATS-safe',    'colors' => ['#0f172a', '#7c3aed']],
        ['name' => 'Design Lead',  'tag' => 'Creative',    'colors' => ['#7c3aed', '#e94e8c']],
        ['name' => 'Engineer',     'tag' => 'Tech',        'colors' => ['#1bd4d9', '#7c3aed']],
        ['name' => 'Classic',      'tag' => 'Corporate',   'colors' => ['#0f172a', '#1bd4d9']],
        ['name' => 'Bold',         'tag' => 'Marketing',   'colors' => ['#ff8a3c', '#e94e8c']],
        ['name' => 'Academic',     'tag' => 'Research',    'colors' => ['#16a34a', '#22d3ee']],
        ['name' => 'Photographer', 'tag' => 'Portfolio',   'colors' => ['#e94e8c', '#ff8a3c']],
        ['name' => 'Architect',    'tag' => 'Visual',      'colors' => ['#22d3ee', '#7c3aed']],
    ];
@endphp

<style>
    .rbp-mesh::before {
        content:""; position:absolute; inset:-15%;
        background: rgba(124,58,237,.06);
        filter: blur(40px); pointer-events:none;
        animation: rbpMesh 14s ease-in-out infinite alternate;
    }
    @keyframes rbpMesh { 0% { transform: translate3d(0,0,0); } 100% { transform: translate3d(2%,-2%,0) scale(1.05); } }

    /* hero animated paper */
    .rbp-paper {
        position: relative; aspect-ratio: 1 / 1.32; max-width: 360px;
        border-radius: 22px; overflow: hidden;
        background: linear-gradient(180deg,#fff,#f5f7fb); color:#0f172a;
        box-shadow: 0 30px 80px -30px rgba(124,58,237,.55), 0 12px 30px -10px rgba(0,0,0,.55);
        animation: rbpFloat 6s ease-in-out infinite;
    }
    @keyframes rbpFloat { 0%,100% { transform: translateY(0) rotate(-3deg); } 50% { transform: translateY(-10px) rotate(-3deg); } }
    .rbp-paper-head {
        padding: 22px; color:#fff;
        background: linear-gradient(135deg,#7c3aed,#a855f7);
    }
    .rbp-bar { height: 7px; border-radius: 999px; background: #e5e7eb; overflow:hidden; position: relative; }
    .rbp-bar > span { position:absolute; inset:0; border-radius:inherit; background: #7c3aed; width: var(--w,70%); transform-origin:left; transform: scaleX(0); animation: rbpFill 2.4s ease-out forwards; }
    @keyframes rbpFill { to { transform: scaleX(1); } }

    /* Step cards */
    .rbp-step { position: relative; transition: transform .35s ease; }
    .rbp-step:hover { transform: translateY(-6px); }
    .rbp-step-num {
        position:absolute; top:14px; right:18px; font-size: 3rem; font-weight:800; line-height:1; opacity:.14;
        color: var(--rbp-c,#7c3aed);
    }
    .rbp-step-icon {
        width:56px; height:56px; border-radius:18px;
        background: var(--rbp-c,#7c3aed);
        color:#fff; display:flex; align-items:center; justify-content:center;
        box-shadow: 0 12px 28px -10px var(--rbp-c,#7c3aed); position:relative;
    }
    .rbp-step-icon::after {
        content:""; position:absolute; inset:-5px; border-radius:22px;
        border: 2px solid color-mix(in srgb, var(--rbp-c,#7c3aed) 50%, transparent);
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
    .rbp-tpl:hover { transform: translateY(-6px) rotate(-1deg); box-shadow: 0 30px 60px -22px rgba(124,58,237,.5); }
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
    .rbp-stat .num { font-size: 2.5rem; font-weight: 800; color:#7c3aed; }
    .rbp-stat .lbl { font-size: 11px; text-transform: uppercase; letter-spacing: .15em; color: #9ca3af; margin-top: 4px; }

    /* Light-mode legibility: stat label hardcodes a light gray that drops
       below contrast on the white light-mode background. */
    html.light-mode .rbp-stat .lbl { color: #64748b; }

    @media (max-width: 640px) {
        .rbp-cmp { grid-template-columns: 1fr; }
    }
</style>

{{-- ============== HERO ============== --}}
<section class="relative pt-20 pb-20 lg:pt-28 lg:pb-28 overflow-hidden">
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
                <a href="/register" class="px-6 py-3 bg-violet-600 hover:bg-violet-700 text-white rounded-full text-sm font-bold inline-flex items-center gap-2">
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
            <div class="rbp-paper">
                <div class="rbp-paper-head">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center font-bold text-white" style="background: #7c3aed;">JS</div>
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
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-wider text-violet-700 mb-1">Experience</div>
                        <div class="text-[12px] font-bold text-slate-900">Senior Engineer · Remote</div>
                        <div class="text-[10px] text-slate-500">Acme Inc. &middot; 2022 — Now</div>
                        <div class="text-[10px] text-slate-600 mt-1">Cut p95 latency by 38% by replacing N+1 queries with cursor pagination.</div>
                    </div>
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-wider text-violet-700 mb-2">Skills</div>
                        <div class="space-y-2">
                            <div>
                                <div class="flex justify-between text-[10px] mb-1"><span class="font-semibold text-slate-700">Backend</span><span class="text-slate-500">92%</span></div>
                                <div class="rbp-bar"><span style="--w:92%"></span></div>
                            </div>
                            <div>
                                <div class="flex justify-between text-[10px] mb-1"><span class="font-semibold text-slate-700">Frontend</span><span class="text-slate-500">85%</span></div>
                                <div class="rbp-bar"><span style="--w:85%; animation-delay:.25s;"></span></div>
                            </div>
                            <div>
                                <div class="flex justify-between text-[10px] mb-1"><span class="font-semibold text-slate-700">DevOps</span><span class="text-slate-500">70%</span></div>
                                <div class="rbp-bar"><span style="--w:70%; animation-delay:.5s;"></span></div>
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
                ['02','fa-wand-magic-sparkles','#7c3aed', 'Let AI polish it',     'AI rewrites bullet points with metrics, action verbs and ATS keywords for your role.'],
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
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:#7c3aed">FAQs</div>
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
        <div class="relative rounded-3xl p-10 text-center overflow-hidden" style="background: rgba(124,58,237,.16); border: 1px solid rgba(255,255,255,.08);">
            <div class="absolute inset-[-1px] rounded-[inherit] pointer-events-none" style="background: rgba(124,58,237,.45); opacity:.18; filter: blur(28px);"></div>
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
@endsection
