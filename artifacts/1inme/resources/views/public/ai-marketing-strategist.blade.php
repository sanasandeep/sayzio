@extends('public.layouts.site')
@section('title', 'AI Marketing Strategist')

@section('content')
@php
    use Illuminate\Support\Js;

    $accent = '#3d6bff';

    // Pre-written strategy examples the hero "types out" live. Each one mirrors
    // the real MarketingStrategistService output shape — a goal, a short
    // summary, an organic + paid plan whose plays name concrete Sayzio
    // features, and the KPIs to watch. Kept here (not live AI) so the page
    // never makes an API call at load. Lines carry a `k` (kind) used purely
    // for colour: head | sub | organic | paid | kpi | dim | plain.
    $examples = [
        [
            'goal' => 'Grow my email + WhatsApp subscribers from my Link in Bio',
            'lines' => [
                ['k' => 'head', 't' => 'Turn bio traffic into owned subscribers'],
                ['k' => 'dim',  't' => 'Convert the visitors you already get, then bring the warm ones back with a tiny paid budget.'],
                ['k' => 'plain','t' => ''],
                ['k' => 'sub',  't' => 'ORGANIC'],
                ['k' => 'organic','t' => '1. Link in Bio — add a "Free guide" subscribe block above the fold'],
                ['k' => 'dim',  't' => '   → captures email + WhatsApp straight into your Subscribers list'],
                ['k' => 'organic','t' => '2. Creator Feed — schedule 3 posts a week teasing the guide'],
                ['k' => 'organic','t' => '3. QR Code — a print-ready code on every offline touchpoint'],
                ['k' => 'plain','t' => ''],
                ['k' => 'sub',  't' => 'PAID  ·  $5–10/day'],
                ['k' => 'paid', 't' => '1. Meta Ads — retarget visitors via your connected Facebook pixel'],
                ['k' => 'paid', 't' => '2. Send every click to the same lead-magnet page'],
                ['k' => 'plain','t' => ''],
                ['k' => 'kpi',  't' => 'KPIs → subscriber growth · click-through · cost per lead'],
            ],
        ],
        [
            'goal' => 'Get more bookings for my studio this month',
            'lines' => [
                ['k' => 'head', 't' => 'Fill the calendar from a single link'],
                ['k' => 'dim',  't' => 'Make booking the easiest action on your page, and back it with proof.'],
                ['k' => 'plain','t' => ''],
                ['k' => 'sub',  't' => 'ORGANIC'],
                ['k' => 'organic','t' => '1. Link in Bio — pin a Calendar booking block to the very top'],
                ['k' => 'organic','t' => '2. Reviews Wall — surface your 5-star reviews to build trust'],
                ['k' => 'organic','t' => '3. Digital Card — share a vCard so referrals save you instantly'],
                ['k' => 'plain','t' => ''],
                ['k' => 'sub',  't' => 'PAID  ·  $8–15/day'],
                ['k' => 'paid', 't' => '1. Google Ads — point local searches at your booking page'],
                ['k' => 'paid', 't' => '2. Track every booking with your GA4 pixel on the link'],
                ['k' => 'plain','t' => ''],
                ['k' => 'kpi',  't' => 'KPIs → bookings · profile views · returning visitors'],
            ],
        ],
        [
            'goal' => 'Launch my new product drop',
            'lines' => [
                ['k' => 'head', 't' => 'Build hype, then convert on launch day'],
                ['k' => 'dim',  't' => 'Collect a waitlist first so launch day starts with demand, not silence.'],
                ['k' => 'plain','t' => ''],
                ['k' => 'sub',  't' => 'ORGANIC'],
                ['k' => 'organic','t' => '1. Link in Bio — a countdown + waitlist Form block'],
                ['k' => 'organic','t' => '2. Creator Feed — a daily teaser post to your followers'],
                ['k' => 'organic','t' => '3. Buzz — live social-proof notifications on new sign-ups'],
                ['k' => 'plain','t' => ''],
                ['k' => 'sub',  't' => 'PAID  ·  $10–20/day'],
                ['k' => 'paid', 't' => '1. TikTok Ads — a short demo to the waitlist (TikTok pixel attached)'],
                ['k' => 'paid', 't' => '2. Email the waitlist the moment you go live'],
                ['k' => 'plain','t' => ''],
                ['k' => 'kpi',  't' => 'KPIs → waitlist size · launch-day clicks · conversion rate'],
            ],
        ],
    ];

    // The real data sources the strategist reads from (mirrors
    // MarketingStrategistService::SOURCES) — used in the "grounded in your
    // data" section so visitors see it works from their own account, not
    // generic templates.
    $sources = [
        ['icon' => 'fa-link',        'label' => 'Links & types',       'desc' => 'Your links, their types and lifetime clicks.'],
        ['icon' => 'fa-chart-line',  'label' => 'Analytics',           'desc' => 'Recent click trends and device split.'],
        ['icon' => 'fa-users',       'label' => 'Followers & subs',    'desc' => 'Audience size and how it is growing.'],
        ['icon' => 'fa-bullseye',    'label' => 'Tracking pixels',     'desc' => 'The ad pixels you already have connected.'],
        ['icon' => 'fa-brain',       'label' => 'AI Minds',            'desc' => 'Your knowledge bases, by name.'],
        ['icon' => 'fa-palette',     'label' => 'Brand Kits',          'desc' => 'Your palette, voice and taglines.'],
        ['icon' => 'fa-user-astronaut','label' => 'AI Personas',       'desc' => 'Your saved AI persona agents.'],
        ['icon' => 'fa-comments',    'label' => 'AI Companions',       'desc' => 'Your published AI chat companions.'],
    ];

    $steps = [
        ['n' => '1', 'icon' => 'fa-bullseye',          'title' => 'Set your goal',        'body' => 'Tell the strategist what you want — more subscribers, more bookings, a product launch — and tune a few parameters.'],
        ['n' => '2', 'icon' => 'fa-database',          'title' => 'Pick your data',       'body' => 'Toggle which of your own account signals it reads. Everything is grounded in real data, never invented metrics or URLs.'],
        ['n' => '3', 'icon' => 'fa-wand-magic-sparkles','title' => 'Get a plan you can run','body' => 'It returns an organic + paid plan built around real Sayzio features, plus one-click actions you can apply on the spot.'],
    ];

    $benefits = [
        ['icon' => 'fa-seedling',   'title' => 'Grounded in your data',  'body' => 'Every recommendation is built from your real links, analytics and audience — no generic fluff, no made-up numbers.'],
        ['icon' => 'fa-scale-balanced','title' => 'Organic + paid, together','body' => 'You get both a free-to-run organic plan and a low-budget paid plan, each play naming the exact Sayzio feature it uses.'],
        ['icon' => 'fa-bolt',       'title' => 'One-click to apply',     'body' => 'The plan ships with applyable suggestions — create a link, add a block, attach a pixel, draft a post — done inside Sayzio.'],
        ['icon' => 'fa-comments',   'title' => 'Refine by chat',         'body' => 'Not quite right? Chat with the strategist to sharpen the plan, swap channels or tighten the budget — it stays grounded.'],
        ['icon' => 'fa-file-arrow-down','title' => 'Export & keep',       'body' => 'Save every strategy, export it to Markdown, and revisit your past plans whenever you want to run them again.'],
        ['icon' => 'fa-shield-halved','title' => 'Private by design',     'body' => 'It only reads the sources you toggle on, and only ever sees a compact, PII-free snapshot of your own account.'],
    ];

    $useCases = [
        ['icon' => 'fa-star',          'title' => 'Creators',  'body' => 'Turn bio visitors into subscribers and followers, then bring them back with scheduled posts.'],
        ['icon' => 'fa-store',         'title' => 'Local business', 'body' => 'Drive bookings and calls from one link, backed by reviews and conversion tracking.'],
        ['icon' => 'fa-bag-shopping',  'title' => 'Sellers',   'body' => 'Build a waitlist, launch a drop, and retarget warm shoppers with a small ad budget.'],
        ['icon' => 'fa-briefcase',     'title' => 'Agencies & teams', 'body' => 'Spin up grounded, channel-specific plans for each client in minutes, not meetings.'],
    ];

    $faqs = [
        ['q' => 'Does it use my real account data?', 'a' => 'Yes. You choose which sources to include — links, analytics, audience, pixels, Brand Kits and more — and the strategist builds the plan from a compact, PII-free snapshot of just those. It never invents metrics, follower counts or URLs.'],
        ['q' => 'What do I actually get back?', 'a' => 'A structured strategy: a short summary, an organic plan and a paid plan where every play names the Sayzio feature it uses, the KPIs to watch, and a short list of one-click actions you can apply right away.'],
        ['q' => 'Can I change the plan after it is generated?', 'a' => 'Absolutely. You can chat with the strategist to refine it — swap channels, tighten the budget, go deeper on a play — and it stays grounded in your data and the original plan.'],
        ['q' => 'Which plans include the AI Marketing Strategist?', 'a' => 'It is included on every paid Sayzio plan. Generation is metered against your plan allowance, and any overage is covered by your coin wallet so you are never cut off mid-plan.'],
        ['q' => 'Will it post or spend money on its own?', 'a' => 'No. It only proposes a plan and one-click actions. Nothing is created, posted or spent until you choose to apply it.'],
    ];
@endphp

<style>
    /* ===== AI Marketing Strategist — typewriter terminal ===== */
    .ms-term {
        position: relative;
        border-radius: 20px;
        overflow: hidden;
        border: 1px solid rgba(255,255,255,.10);
        background: linear-gradient(180deg, rgba(13,12,24,.92), rgba(10,10,20,.96));
        box-shadow: 0 40px 90px -40px rgba(61,107,255,.55), 0 14px 36px -14px rgba(0,0,0,.6);
    }
    html.light-mode .ms-term {
        background: linear-gradient(180deg, #0f1326, #0c1020);
        border-color: rgba(15,23,42,.18);
    }
    .ms-term-bar {
        display: flex; align-items: center; gap: 8px;
        padding: 12px 16px;
        border-bottom: 1px solid rgba(255,255,255,.07);
        background: rgba(255,255,255,.02);
    }
    .ms-dot { width: 11px; height: 11px; border-radius: 9999px; display: inline-block; }
    .ms-dot:nth-child(1) { background: #ff5f57; }
    .ms-dot:nth-child(2) { background: #febc2e; }
    .ms-dot:nth-child(3) { background: #28c840; }
    .ms-term-title {
        margin-left: 8px; font-size: 12px; font-weight: 600; letter-spacing: .02em;
        color: #9ca3af; display: inline-flex; align-items: center; gap: 7px;
    }
    .ms-term-title .ms-live {
        width: 7px; height: 7px; border-radius: 9999px; background: #28c840;
        box-shadow: 0 0 0 0 rgba(40,200,64,.6);
        animation: msPulse 2.2s ease-out infinite;
    }
    @keyframes msPulse {
        0%   { box-shadow: 0 0 0 0 rgba(40,200,64,.55); }
        70%  { box-shadow: 0 0 0 7px rgba(40,200,64,0); }
        100% { box-shadow: 0 0 0 0 rgba(40,200,64,0); }
    }
    .ms-term-body {
        padding: 18px 20px 22px;
        font-family: 'Space Grotesk', ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 13.5px; line-height: 1.62;
        min-height: 430px;
        color: #e5e7eb;
    }
    @media (min-width: 1024px) { .ms-term-body { min-height: 470px; } }
    .ms-goal-row {
        display: flex; flex-wrap: wrap; align-items: baseline; gap: 8px;
        padding-bottom: 14px; margin-bottom: 14px;
        border-bottom: 1px dashed rgba(255,255,255,.10);
    }
    .ms-goal-label {
        font-size: 10px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase;
        color: #fff; background: {{ $accent }};
        padding: 3px 8px; border-radius: 7px; flex: 0 0 auto;
    }
    .ms-goal-text { color: #cbd5e1; font-weight: 500; }
    .ms-out { margin: 0; white-space: pre-wrap; word-break: break-word; }
    .ms-line { min-height: 1.62em; }
    .ms-k-head    { color: #fff; font-weight: 700; font-size: 15px; }
    .ms-k-head::before { content: '■'; color: {{ $accent }}; margin-right: 8px; }
    .ms-k-sub     { color: {{ $accent }}; font-weight: 700; letter-spacing: .12em; font-size: 11px; margin-top: 2px; }
    .ms-k-organic { color: #d6e0ff; }
    .ms-k-paid    { color: #ffe2c7; }
    .ms-k-kpi     { color: #9ff0c4; font-weight: 600; }
    .ms-k-dim     { color: #8b93a7; }
    .ms-k-plain   { color: #e5e7eb; }
    .ms-caret {
        display: inline-block; width: 8px; height: 1.05em;
        background: {{ $accent }}; margin-left: 2px;
        vertical-align: text-bottom; border-radius: 1px;
        animation: msBlink 1s steps(1) infinite;
    }
    @keyframes msBlink { 50% { opacity: 0; } }

    /* Floating accent badge on the hero art */
    .ms-badge {
        position: absolute; background: #11101c; border: 1px solid rgba(255,255,255,.10);
        border-radius: 16px; padding: 12px 14px; display: flex; align-items: center; gap: 10px;
        box-shadow: 0 20px 50px -20px rgba(0,0,0,.7);
    }
    html.light-mode .ms-badge { background: #fff; border-color: rgba(15,23,42,.10); }

    @media (prefers-reduced-motion: reduce) {
        .ms-caret { display: none !important; }
        .ms-term-title .ms-live { animation: none !important; }
    }
</style>

{{-- ─────────────  HERO  ───────────── --}}
<section class="relative pt-20 pb-16 lg:pt-28 lg:pb-20 overflow-hidden">
    <div class="mesh-bg"></div>
    <div class="absolute inset-0 grid-bg opacity-50 pointer-events-none"></div>
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-[1fr_1.05fr] gap-10 lg:gap-14 items-center">
            <div data-anim="fade-right">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider border"
                      style="background: {{ $accent }}1a; border-color: {{ $accent }}33; color: {{ $accent }};">
                    <i class="fas fa-wand-magic-sparkles text-[10px]"></i> AI Marketing Strategist
                </span>
                <h1 class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.05]">
                    Your marketing plan,
                    <span class="block grad-text">written from your own data.</span>
                </h1>
                <p class="mt-5 text-lg text-gray-400 max-w-xl leading-relaxed">
                    Tell it your goal. The AI Marketing Strategist reads your real links, analytics and audience, then types out a practical organic + paid plan built around the Sayzio features you already have — with one-click actions you can apply instantly.
                </p>
                <div class="mt-7 flex flex-wrap items-center gap-3">
                    <a href="/register" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold inline-flex items-center gap-2">
                        <i class="fas fa-rocket text-xs"></i> Build my strategy free
                    </a>
                    <a href="{{ route('site.pricing') }}" class="px-5 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">
                        See plans
                    </a>
                </div>
                <p class="mt-5 text-xs text-gray-500">
                    <i class="fas fa-lock-open text-[10px] mr-1" style="color: {{ $accent }};"></i>
                    Included on every paid plan · grounded in your data, never invented metrics.
                </p>
            </div>

            {{-- Typewriter centerpiece --}}
            <div data-anim="fade-left" class="relative">
                <div class="ms-term" id="msTerm" role="img"
                     aria-label="A live demo of the AI Marketing Strategist typing out a marketing strategy from a creator's goal.">
                    <div class="ms-term-bar">
                        <span class="ms-dot" aria-hidden="true"></span>
                        <span class="ms-dot" aria-hidden="true"></span>
                        <span class="ms-dot" aria-hidden="true"></span>
                        <span class="ms-term-title"><span class="ms-live" aria-hidden="true"></span> Sayzio · Marketing Strategist</span>
                    </div>
                    <div class="ms-term-body">
                        <div class="ms-goal-row">
                            <span class="ms-goal-label">Goal</span>
                            <span class="ms-goal-text" id="msGoal"></span>
                        </div>
                        <div class="ms-out" id="msOut"></div>
                    </div>
                </div>
                <div class="ms-badge float-y" style="bottom: -18px; left: -14px;">
                    <span class="w-9 h-9 rounded-xl flex items-center justify-center text-white" style="background: {{ $accent }};">
                        <i class="fas fa-bolt text-sm"></i>
                    </span>
                    <span>
                        <span class="block text-sm font-semibold text-white">One-click to apply</span>
                        <span class="block text-xs text-gray-400">Right inside Sayzio</span>
                    </span>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ─────────────  HOW IT WORKS  ───────────── --}}
<section class="relative pb-20">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12" data-anim="fade-up">
            <div class="text-xs font-bold uppercase tracking-[.2em] mb-3" style="color: {{ $accent }};">How it works</div>
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">From a goal to a plan in <span class="grad-text">three steps</span>.</h2>
        </div>
        <div class="grid md:grid-cols-3 gap-5" data-anim="fade-up" data-stagger>
            @foreach($steps as $s)
                <article class="glass rounded-3xl p-7 lift relative overflow-hidden">
                    <div class="absolute -top-12 -right-12 w-40 h-40 rounded-full opacity-20" style="background: {{ $accent }};"></div>
                    <div class="relative flex items-center gap-3 mb-4">
                        <span class="w-11 h-11 rounded-2xl flex items-center justify-center text-white" style="background: {{ $accent }}; box-shadow: 0 12px 30px -12px {{ $accent }};">
                            <i class="fas {{ $s['icon'] }}"></i>
                        </span>
                        <span class="text-xs font-bold uppercase tracking-widest text-gray-500">Step {{ $s['n'] }}</span>
                    </div>
                    <h3 class="relative text-xl font-bold mb-2 leading-snug">{{ $s['title'] }}</h3>
                    <p class="relative text-sm text-gray-300 leading-relaxed">{{ $s['body'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ─────────────  GROUNDED IN YOUR DATA  ───────────── --}}
<section class="relative pb-24">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grad-border rounded-3xl p-8 sm:p-10 relative overflow-hidden" data-anim="fade-up">
            <div class="mesh-bg opacity-40"></div>
            <div class="relative">
                <div class="max-w-2xl">
                    <div class="text-xs font-bold uppercase tracking-[.2em] mb-3" style="color: {{ $accent }};">Grounded in your data</div>
                    <h2 class="text-2xl sm:text-3xl font-bold tracking-tight">It reads <span class="grad-text">your account</span>, not a generic template.</h2>
                    <p class="mt-3 text-sm text-gray-300 leading-relaxed">Pick exactly which signals the strategist may use. It only ever sees a compact, PII-free snapshot of the sources you toggle on — and builds the whole plan from there.</p>
                </div>
                <div class="mt-8 grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    @foreach($sources as $src)
                        <div class="rounded-2xl p-5 bg-white/[0.03] border border-white/10">
                            <span class="w-10 h-10 rounded-xl flex items-center justify-center mb-3" style="background: {{ $accent }}1f; color: {{ $accent }};">
                                <i class="fas {{ $src['icon'] }}"></i>
                            </span>
                            <div class="text-sm font-bold text-white">{{ $src['label'] }}</div>
                            <div class="text-xs text-gray-400 mt-1 leading-snug">{{ $src['desc'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ─────────────  BENEFITS  ───────────── --}}
<section class="relative pb-24">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12" data-anim="fade-up">
            <div class="text-xs font-bold uppercase tracking-[.2em] mb-3" style="color: {{ $accent }};">Why creators use it</div>
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">A strategist that actually <span class="grad-text">knows your account</span>.</h2>
        </div>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5" data-anim="fade-up" data-stagger>
            @foreach($benefits as $b)
                <article class="glass rounded-3xl p-7 lift">
                    <div class="w-11 h-11 rounded-2xl flex items-center justify-center mb-4 text-white" style="background: {{ $accent }}; box-shadow: 0 12px 30px -12px {{ $accent }};">
                        <i class="fas {{ $b['icon'] }}"></i>
                    </div>
                    <h3 class="text-lg font-bold mb-2 leading-snug">{{ $b['title'] }}</h3>
                    <p class="text-sm text-gray-300 leading-relaxed">{{ $b['body'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ─────────────  USE CASES  ───────────── --}}
<section class="relative pb-24">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12" data-anim="fade-up">
            <div class="text-xs font-bold uppercase tracking-[.2em] mb-3" style="color: {{ $accent }};">Made for the way you grow</div>
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">One strategist, <span class="grad-text">every goal</span>.</h2>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4" data-anim="fade-up" data-stagger>
            @foreach($useCases as $u)
                <article class="rounded-2xl p-6 bg-white/[0.03] border border-white/10 lift">
                    <span class="w-10 h-10 rounded-xl flex items-center justify-center mb-3 text-white" style="background: {{ $accent }};">
                        <i class="fas {{ $u['icon'] }}"></i>
                    </span>
                    <h3 class="text-base font-bold mb-1.5">{{ $u['title'] }}</h3>
                    <p class="text-sm text-gray-400 leading-relaxed">{{ $u['body'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- ─────────────  FAQ  ───────────── --}}
<section class="pb-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10" data-anim="fade-up">
            <div class="text-xs font-bold uppercase tracking-[.2em] mb-3" style="color: {{ $accent }};">FAQ</div>
            <h3 class="text-2xl sm:text-3xl font-bold tracking-tight">Common questions about the <span class="grad-text">Marketing Strategist</span>.</h3>
        </div>
        <div class="space-y-3" data-anim="fade-up" data-stagger>
            @foreach($faqs as $faq)
                <details class="group glass rounded-2xl p-5 open:pb-6">
                    <summary class="flex items-center justify-between gap-4 cursor-pointer list-none">
                        <span class="text-base font-semibold text-white">{{ $faq['q'] }}</span>
                        <span class="shrink-0 w-7 h-7 rounded-full border border-white/15 flex items-center justify-center text-gray-300 group-open:rotate-45 transition">
                            <i class="fas fa-plus text-[10px]"></i>
                        </span>
                    </summary>
                    <p class="mt-3 text-sm text-gray-400 leading-relaxed">{{ $faq['a'] }}</p>
                </details>
            @endforeach
        </div>
    </div>
</section>

{{-- ─────────────  CTA BAND  ───────────── --}}
<section class="pb-24">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grad-border rounded-3xl p-8 sm:p-12 text-center relative overflow-hidden" data-anim="fade-up">
            <div class="mesh-bg opacity-50"></div>
            <div class="relative">
                <h3 class="text-3xl sm:text-4xl font-bold tracking-tight">Stop guessing. <span class="grad-text">Get the plan.</span></h3>
                <p class="mt-4 text-gray-300 max-w-2xl mx-auto">Create your free Sayzio, point the AI Marketing Strategist at your account, and watch it write a plan you can actually run — organic and paid, grounded in your data.</p>
                <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                    <a href="/register" class="px-7 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-full text-sm font-bold">Build my strategy free</a>
                    <a href="{{ route('site.features') }}" class="px-6 py-3 rounded-full text-sm font-medium text-gray-200 border border-white/15 hover:bg-white/5">See all features</a>
                </div>
                <p class="mt-5 text-xs text-gray-500">Included on every paid plan · metered against your allowance, overage covered by coins.</p>
            </div>
        </div>
    </div>
</section>

@include('public.partials.subscribe-block', [
    'heading' => 'New AI features, the moment they ship.',
    'subtext' => 'Pick how you want to hear from us — email, WhatsApp Channel, or DM. Once-a-month notes on the AI suite, no fluff.',
    'source'  => 'ai-marketing-strategist',
])

<script>
(function () {
    if (window.__msTypewriterInit) return;
    window.__msTypewriterInit = true;

    var EXAMPLES = {!! Js::from($examples) !!};

    function init() {
        var goalEl = document.getElementById('msGoal');
        var outEl  = document.getElementById('msOut');
        if (!goalEl || !outEl || !EXAMPLES.length) return;

        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        // Reduced motion / no-JS-animation path: render the first example in
        // full, statically, with no caret and no looping.
        if (reduce) {
            renderStatic(EXAMPLES[0]);
            return;
        }

        var caret = document.createElement('span');
        caret.className = 'ms-caret';

        var ex = 0;
        runExample(EXAMPLES[ex]);

        function runExample(example) {
            goalEl.textContent = '';
            outEl.innerHTML = '';
            goalEl.appendChild(caret);

            typeInto(goalEl, example.goal, function () {
                typeLines(example.lines, 0, function () {
                    // Hold the finished plan, then cycle to the next example.
                    window.setTimeout(function () {
                        ex = (ex + 1) % EXAMPLES.length;
                        runExample(EXAMPLES[ex]);
                    }, 4200);
                });
            });
        }

        function typeLines(lines, i, done) {
            if (i >= lines.length) { done(); return; }
            var line = lines[i];
            var div = document.createElement('div');
            div.className = 'ms-line ms-k-' + (line.k || 'plain');
            outEl.appendChild(div);
            div.appendChild(caret);

            if (line.t === '') {
                // Blank spacer line — brief beat, no typing.
                window.setTimeout(function () { typeLines(lines, i + 1, done); }, 90);
                return;
            }

            typeInto(div, line.t, function () {
                var pause = (line.k === 'head' || line.k === 'sub') ? 230 : 70;
                window.setTimeout(function () { typeLines(lines, i + 1, done); }, pause);
            });
        }

        function typeInto(el, text, done) {
            var buf = '';
            var idx = 0;
            (function step() {
                if (idx >= text.length) {
                    el.removeChild(caret);
                    done();
                    return;
                }
                buf += text.charAt(idx++);
                el.textContent = buf;
                el.appendChild(caret);
                // A touch of jitter so it reads like real typing, faster for spaces.
                var ch = text.charAt(idx - 1);
                var delay = ch === ' ' ? 14 : (16 + Math.random() * 26);
                window.setTimeout(step, delay);
            })();
        }

        function renderStatic(example) {
            goalEl.textContent = example.goal;
            outEl.innerHTML = '';
            example.lines.forEach(function (line) {
                var div = document.createElement('div');
                div.className = 'ms-line ms-k-' + (line.k || 'plain');
                div.textContent = line.t;
                outEl.appendChild(div);
            });
        }
    }

    if (document.readyState !== 'loading') init();
    else document.addEventListener('DOMContentLoaded', init);
})();
</script>
@endsection
