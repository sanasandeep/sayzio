{{-- "AI builder" short design: keyword focus = AI link in bio, AI page builder. --}}
@include('home.partials.seo-intro', [
    'eyebrow' => 'AI Link in Bio Builder',
    'heading' => 'The <span class="grad-text">AI link in bio</span> builder that does the work for you',
    'lead' => 'Sayzio is a free AI page builder: describe your page and Zio builds your link in bio, bio link page, short links and QR codes in seconds, then answers visitors 24/7.',
    'chips' => [['AI page builder', '/register'], ['Free link in bio', '/pricing'], ['See features', '/features']],
])
{{-- ============================ HOW IT WORKS (upgraded) ============================ --}}
<style>
    .hiw-track { position: relative; }
    @media (min-width: 1024px) {
        .hiw-track::before {
            content: ""; position: absolute; left: 8%; right: 8%; top: 56px; height: 2px;
            background: rgba(61,107,255,.45);
            opacity: .55; pointer-events: none;
        }
    }
    .hiw-step { position: relative; transition: transform .35s ease, box-shadow .35s ease; }
    .hiw-step:hover { transform: translateY(-6px); box-shadow: 0 30px 60px -30px rgba(61,107,255,.55); }
    .hiw-icon-wrap { position: relative; width: 64px; height: 64px; border-radius: 22px; display:flex; align-items:center; justify-content:center; margin: 0 auto 1rem; box-shadow: 0 14px 30px -12px var(--hiw-color, #3d6bff); }
    .hiw-icon-wrap::after {
        content: ""; position: absolute; inset: -6px; border-radius: 26px;
        border: 2px solid color-mix(in srgb, var(--hiw-color, #3d6bff) 50%, transparent);
        opacity: .35; animation: hiwPulse 2.4s ease-in-out infinite;
    }
    .hiw-step:hover .hiw-icon-wrap::after { opacity: .8; }
    @keyframes hiwPulse { 0%,100% { transform: scale(1); opacity: .25; } 50% { transform: scale(1.08); opacity: .65; } }
    .hiw-num { position: absolute; top: 14px; right: 18px; font-size: 3.25rem; font-weight: 800; line-height: 1;
        color: var(--hiw-color, #3d6bff); opacity: .14;
    }
    .hiw-time {
        display:inline-flex; align-items:center; gap:6px;
        padding: 4px 10px; border-radius: 9999px; font-size: 11px; font-weight: 700;
        background: rgba(34,197,94,.12); color: #4ade80; border: 1px solid rgba(34,197,94,.25);
        margin-bottom: 10px; letter-spacing: .04em;
    }
    .hiw-time i { font-size: 9px; }
    .hiw-cta-wrap {
        position: relative; padding: 1.75rem; border-radius: 1.75rem; overflow: hidden;
        background: rgba(61,107,255,.12);
        border: 1px solid rgba(255,255,255,.08);
    }
    .hiw-cta-wrap::before {
        content:""; position:absolute; inset:-1px; border-radius:inherit; pointer-events:none;
        background: #3d6bff;
        opacity:.18; filter: blur(20px);
    }
</style>
<section id="how-it-works" class="py-20 lg:py-28 relative overflow-hidden">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c2)">How it works</div>
            <h2 class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-4">
                Live in <span class="grad-text">under 2 minutes.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">Tell your AI what you want and it builds the page. Four tiny steps from "I have an idea" to "share my link" &mdash; no card, no setup call, no fuss.</p>
        </div>

        <div class="hiw-track grid sm:grid-cols-2 lg:grid-cols-4 gap-5 max-w-6xl mx-auto">
            @foreach([
                ['01','0:15','Sign up free','Email or one-tap Google. Pick your handle and you\'re in.','fa-user-plus','#1bd4d9'],
                ['02','0:45','Build with AI','Let AI draft it, then drag-and-drop blocks for socials, music, shop, video.','fa-grip-vertical','#3d6bff'],
                ['03','1:30','Share it everywhere','One link, branded short links and a dynamic QR for offline.','fa-share-nodes','#e94e8c'],
                ['04','2:00','Watch it grow','Live analytics + an AI Coach that turns numbers into actions.','fa-chart-line','#ff8a3c'],
            ] as $i => $s)
                <div class="reveal rd-{{ ($i % 4)+1 }} hiw-step glass rounded-3xl p-6 text-center" style="--hiw-color: {{ $s[5] }}">
                    <span class="hiw-num">{{ $s[0] }}</span>
                    <div class="hiw-icon-wrap" style="background: {{ $s[5] }};"><i class="fas {{ $s[4] }} text-xl text-white"></i></div>
                    <span class="hiw-time"><i class="fas fa-stopwatch"></i>{{ $s[1] }}</span>
                    <h3 class="text-lg font-bold mb-1.5">{!! $s[2] !!}</h3>
                    <p class="text-sm text-gray-400 leading-relaxed">{!! $s[3] !!}</p>
                </div>
            @endforeach
        </div>

        <div class="reveal rd-4 mt-12 max-w-3xl mx-auto">
            <div class="hiw-cta-wrap relative flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="relative text-center sm:text-left">
                    <div class="text-[11px] font-bold uppercase tracking-[.2em] mb-1" style="color:var(--c1)">Ready when you are</div>
                    <div class="text-lg sm:text-xl font-bold text-white">Start free — no card needed.</div>
                    <div class="text-xs text-gray-400 mt-0.5">Free Forever plan · Upgrade only when you outgrow it.</div>
                </div>
                <div class="relative flex flex-wrap items-center gap-3 shrink-0">
                    <button type="button" onclick="window.trackMarketingEvent && window.trackMarketingEvent('landing_home_cta','how_it_works'); window.dispatchEvent(new CustomEvent('open-auth',{detail:{tab:'register'}}))" class="btn-bounce btn-glow inline-flex items-center gap-2 px-7 py-3.5 grad-bar text-white rounded-full text-sm font-bold">
                        Start free — no card <i class="fas fa-arrow-right text-xs"></i>
                    </button>
                    <a href="{{ route('site.how-it-works') }}" class="inline-flex items-center gap-2 px-5 py-3 rounded-full glass text-white hover:bg-white/10 text-xs font-semibold transition-colors">
                        Walk me through it <i class="fas fa-route text-[10px]"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>


{{-- ==================== ZONE · THE SAYZIO AI TEAM ==================== --}}
<style>
    /* Shared wash + intro that binds every AI section into one continuous zone. */
    .ai-zone { position: relative; isolation: isolate; }
    .ai-zone-wash {
        position: absolute; inset: 0; z-index: -2; pointer-events: none;
        background:
            radial-gradient(ellipse 92% 52% at 50% 0%, rgba(61,107,255,.16), transparent 62%),
            radial-gradient(ellipse 65% 45% at 12% 34%, rgba(27,212,217,.10), transparent 55%),
            radial-gradient(ellipse 70% 50% at 88% 68%, rgba(92,131,255,.12), transparent 55%),
            linear-gradient(180deg, transparent, rgba(61,107,255,.045) 16%, rgba(61,107,255,.045) 84%, transparent);
    }
    html.light-mode .ai-zone-wash {
        background:
            radial-gradient(ellipse 92% 52% at 50% 0%, rgba(61,107,255,.08), transparent 62%),
            radial-gradient(ellipse 70% 52% at 88% 68%, rgba(92,131,255,.06), transparent 55%),
            linear-gradient(180deg, transparent, rgba(61,107,255,.03) 16%, rgba(61,107,255,.03) 84%, transparent);
    }
    /* Pre-softened radial gradients instead of a live filter: blur() so
       low-end/mobile GPUs never composite a continuously-animated blur
       (matches the aurora background fix). Sized up slightly so the
       gradient falloff reads like the old 120px blur halo. */
    .ai-zone-aura {
        position: absolute; z-index: -1; width: 720px; height: 720px; border-radius: 9999px;
        opacity: .18; pointer-events: none; will-change: transform;
    }
    .ai-zone-aura-a { top: 4%; left: -9%; background: radial-gradient(closest-side, #3d6bff 0%, rgba(61,107,255,.45) 45%, transparent 72%); animation: aiZoneA 28s ease-in-out infinite; }
    .ai-zone-aura-b { bottom: 6%; right: -7%; background: radial-gradient(closest-side, #1bd4d9 0%, rgba(27,212,217,.45) 45%, transparent 72%); opacity: .13; animation: aiZoneB 34s ease-in-out infinite; }
    @keyframes aiZoneA { 0%,100% { transform: translate3d(0,0,0) scale(1); } 50% { transform: translate3d(70px,50px,0) scale(1.12); } }
    @keyframes aiZoneB { 0%,100% { transform: translate3d(0,0,0) scale(1); } 50% { transform: translate3d(-60px,-46px,0) scale(1.1); } }
    html.light-mode .ai-zone-aura { opacity: .08; }

    .ai-zone-pill {
        color: #90acff; background: rgba(61,107,255,.1); border: 1px solid rgba(61,107,255,.25);
    }
    html.light-mode .ai-zone-pill { color: #3d6bff; background: rgba(61,107,255,.08); }
    .ai-zone-pill-dot {
        width: 7px; height: 7px; border-radius: 9999px; background: #3d6bff;
        box-shadow: 0 0 0 0 rgba(61,107,255,.55); animation: aiZonePulse 2.2s ease-out infinite;
    }
    @keyframes aiZonePulse { 0% { box-shadow: 0 0 0 0 rgba(61,107,255,.5); } 70% { box-shadow: 0 0 0 8px rgba(61,107,255,0); } 100% { box-shadow: 0 0 0 0 rgba(61,107,255,0); } }

    .ai-zone-chip {
        display: inline-flex; align-items: center; gap: 7px; font-size: .8rem; font-weight: 700;
        padding: 8px 15px; border-radius: 9999px; color: #cbd5e1;
        background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.1);
        transition: color .25s ease, border-color .25s ease, background .25s ease, transform .25s ease;
    }
    .ai-zone-chip:hover { color: #fff; border-color: rgba(61,107,255,.5); background: rgba(61,107,255,.12); transform: translateY(-2px); }
    html.light-mode .ai-zone-chip { color: #475569; background: #fff; border-color: rgba(15,23,42,.1); }
    html.light-mode .ai-zone-chip:hover { color: #0f172a; background: rgba(61,107,255,.08); }
    .ai-zone-chip i { color: #3d6bff; font-size: .72rem; }

    @media (prefers-reduced-motion: reduce) {
        .ai-zone-aura, .ai-zone-pill-dot { animation: none !important; }
    }
</style>
<div id="ai-zone" class="ai-zone relative overflow-hidden">
    <div class="ai-zone-wash" aria-hidden="true"></div>
    <div class="ai-zone-aura ai-zone-aura-a" aria-hidden="true"></div>
    <div class="ai-zone-aura ai-zone-aura-b" aria-hidden="true"></div>

    {{-- Shared zone intro --}}
    <section class="relative pt-24 lg:pt-32 pb-2" aria-labelledby="ai-zone-h">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="reveal inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-xs font-bold uppercase tracking-[.2em] ai-zone-pill">
                <span class="ai-zone-pill-dot" aria-hidden="true"></span> Meet Zio
            </div>
            <h2 id="ai-zone-h" class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mt-5 mb-5">
                One AI that <span class="grad-text">builds and runs it all.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">
                From your Link in Bio to your phone line, Sayzio ships a whole crew of AI coworkers — a page
                builder, a chatbot, an agent, an embeddable widget, a voice receptionist, a marketing
                strategist and a WhatsApp teammate. One login, all grounded in your real data.
            </p>
            <div class="reveal rd-3 mt-7 flex flex-wrap items-center justify-center gap-2.5">
                <a href="#ai-suite" class="ai-zone-chip"><i class="fas fa-robot"></i> Chatbot &amp; Agent</a>
                <a href="/features" class="ai-zone-chip"><i class="fas fa-chart-line"></i> AI Marketing Strategist</a>
                <a href="/features" class="ai-zone-chip"><i class="fab fa-whatsapp"></i> WhatsApp Agent</a>
                <a href="/features" class="ai-zone-chip"><i class="fas fa-gauge-high"></i> AI Dashboard</a>
            </div>
        </div>
    </section>

    @include('home.partials.ai-hero')
    @include('home.partials.ai-suite')
</div>


@include('home.partials.pricing')
{{-- ==================== ZONE · ANSWERS, TRUST & FINAL CTA ==================== --}}
{{-- ============================ FAQ (homepage — searchable, chip-filtered) ============================ --}}
@php
    $__homeFaqGroups = \App\Modules\Common\Support\SitePagesContent::homepageFaqs();
    $__homeFaqHighlights = \App\Modules\Common\Support\SitePagesContent::homepageFaqHighlights();
    $__faqNode = \App\Modules\Common\Support\MarketingSchema::faqPage($__homeFaqHighlights);
@endphp
@if($__faqNode)
<script type="application/ld+json">{!! json_encode(\App\Modules\Common\Support\MarketingSchema::graph([$__faqNode]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endif
<section id="faq" class="pt-16 pb-10 lg:pt-20 lg:pb-12 relative overflow-hidden">
    <div class="relative max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-8">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c4)">FAQ</div>
            <h2 class="reveal rd-1 text-3xl sm:text-4xl font-bold tracking-tight mb-2">Questions? <span class="grad-text">Answered.</span></h2>
            <p class="reveal rd-2 text-sm text-gray-400">How the AI builder, coach and the rest actually work — a quick highlight reel; the full searchable library lives on the FAQ page.</p>
        </div>

        <div class="reveal rd-3 space-y-3">
            @foreach($__homeFaqHighlights as $f)
                <details class="faq-item glass rounded-2xl px-5 py-4 hover:bg-white/[.06] transition-colors">
                    <summary class="flex items-center justify-between gap-4 cursor-pointer">
                        <span class="font-semibold text-sm sm:text-base pr-4">{{ $f['q'] }}</span>
                        <span class="faq-icon w-6 h-6 rounded-full grad-bar text-white flex items-center justify-center font-bold flex-shrink-0">
                            <i class="fas fa-plus text-[10px]"></i>
                        </span>
                    </summary>
                    <p class="mt-3 text-sm text-gray-300 leading-relaxed">{{ $f['a'] }}</p>
                </details>
            @endforeach
        </div>

        <div class="reveal rd-4 mt-6 text-center">
            <a href="{{ route('site.faqs') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-blue-300 hover:text-blue-200 transition">
                Browse all answers <i class="fas fa-arrow-right text-xs"></i>
            </a>
        </div>
    </div>
</section>


{{-- ============================ FINAL CTA ============================ --}}
{{-- Visually distinct from the gradient hero blocks above: a single asymmetric
     glass card with a left-aligned headline + right-aligned action, so the
     closing run reads as "cards → trust strip → links → one final CTA". --}}
<section id="cta-final" class="py-16 lg:py-20 relative overflow-hidden">
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="reveal glass rounded-[2rem] p-8 sm:p-12 relative overflow-hidden border border-white/10">
            <div class="absolute -top-24 -right-20 w-80 h-80 rounded-full opacity-30 blur-3xl" style="background: var(--c2);"></div>
            <div class="absolute -bottom-24 -left-20 w-80 h-80 rounded-full opacity-25 blur-3xl" style="background: var(--c4);"></div>

            <div class="relative grid lg:grid-cols-[1fr_auto] gap-8 lg:gap-10 items-center">
                <div class="text-center lg:text-left">
                    <div class="text-[11px] font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c5)">Ready when you are</div>
                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold leading-tight">
                        Your audience is <span class="grad-text">already searching for you.</span>
                    </h2>
                    <p class="text-base text-gray-400 mt-4 max-w-xl mx-auto lg:mx-0">
                        Let your AI build the page. Share the link. Watch them show up — live on a map.
                    </p>
                </div>
                <div class="flex flex-col sm:flex-row lg:flex-col gap-3 shrink-0 items-stretch sm:justify-center lg:items-stretch">
                    <button type="button" onclick="window.trackMarketingEvent && window.trackMarketingEvent('landing_home_cta','final_cta'); window.dispatchEvent(new CustomEvent('open-auth',{detail:{tab:'register'}}))" class="btn-bounce btn-glow inline-flex items-center justify-center gap-2 px-8 py-4 grad-bar text-white rounded-full text-base font-bold whitespace-nowrap">
                        Sign up free <i class="fas fa-arrow-right text-xs"></i>
                    </button>
                    <a href="/features" class="btn-bounce inline-flex items-center justify-center gap-2 px-8 py-4 glass-2 text-white rounded-full text-base font-bold whitespace-nowrap">
                        See features
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
