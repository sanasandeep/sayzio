{{-- ============================ "1IN.ME is Sayzio" BRAND SECTION ============================ --}}
@include('home.partials.brand-sayzio')
{{-- ============================ MARQUEE STRIP ============================ --}}
@php $__skipMarquee = false; @endphp
<div class="grad-bar py-4 overflow-hidden border-y border-white/10" aria-hidden="true">
    <div class="flex whitespace-nowrap marquee">
        @for($i = 0; $i < 2; $i++)
        <span class="inline-flex items-center gap-8 mx-4">
            @foreach([
                ['fa-grip-vertical','Drag &amp; Drop Editor'],
                ['fa-globe','Live Geo Heatmap'],
                ['fa-bolt','Performance Coach'],
                ['fa-link','Short Links'],
                ['fa-qrcode','Dynamic QR Codes'],
                ['fa-users','Follower System'],
                ['fa-wpforms','Form Builder'],
                ['fa-bullhorn','Social Proof'],
                ['fa-address-book','Contacts Sync'],
                ['fa-phone','Built-in Dialer'],
            ] as $item)
                <span class="text-sm font-bold uppercase tracking-wider flex items-center gap-2 text-white"><i class="fas {{ $item[0] }}"></i>{!! $item[1] !!}</span>
                <span class="text-xl text-white/70">★</span>
            @endforeach
        </span>
        @endfor
    </div>
</div>

{{-- ============================ CREDIBILITY BAND (near-hero trust numbers) ============================ --}}
@include('public.partials.marketing-trust-band')

{{-- ============================ WHAT YOU CAN CREATE (LINK TYPES) ============================ --}}
@include('home.partials.create-showcase')


{{-- ==================== ZONE · WHO IT'S FOR & HOW IT WORKS ==================== --}}
{{-- ============================ AUDIENCE (CREATORS / BUSINESSES / NETWORKING) ============================ --}}
@php
    $__audiences = [
        [
            'eyebrow' => 'Creators',
            'title'   => 'Turn followers into fans &mdash; and income.',
            'desc'    => 'One link for every drop, with tips, products, DMs, scheduled posts and an AI coach to keep you growing.',
            'icon'    => 'fa-microphone-lines',
            'color'   => '#e94e8c',
            'cta'     => 'Build my creator page',
        ],
        [
            'eyebrow' => 'Businesses',
            'title'   => 'A landing page, storefront &amp; CRM in one.',
            'desc'    => 'Branded short links, QR codes for packaging &amp; print, custom domains, forms and team workspaces.',
            'icon'    => 'fa-store',
            'color'   => '#1bd4d9',
            'cta'     => 'Start my business page',
        ],
        [
            'eyebrow' => 'Networking pros',
            'title'   => 'Your digital business card &mdash; and then some.',
            'desc'    => 'Tap-to-share NFC tags, dynamic QR codes, instant DMs and a live visitor map of who&rsquo;s engaging.',
            'icon'    => 'fa-id-badge',
            'color'   => '#ff8a3c',
            'cta'     => 'Make my smart card',
        ],
    ];
@endphp
<section id="audience" class="py-20 lg:py-28 relative overflow-hidden" aria-labelledby="audience-h">
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12 max-w-2xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c3)">Built for you</div>
            <h2 id="audience-h" class="reveal rd-1 text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight mb-4">
                Built for <span class="grad-text">creators, brands &amp; networking pros.</span>
            </h2>
            <p class="reveal rd-2 text-gray-400">Pick the one that fits you &mdash; the same AI-powered, all-in-one toolkit powers all three.</p>
        </div>

        <div class="grid md:grid-cols-3 gap-5">
            @foreach($__audiences as $i => $a)
                <article class="audience-card reveal rd-{{ $i + 1 }} glass rounded-3xl p-7 tilt relative overflow-hidden flex flex-col">
                    <div class="aud-blob absolute -top-16 -right-16 w-48 h-48 rounded-full opacity-25" style="background:{{ $a['color'] }};animation-delay:{{ $i * 1.2 }}s;"></div>
                    <div class="aud-icon relative w-14 h-14 rounded-2xl flex items-center justify-center mb-5" style="background: {{ $a['color'] }}; box-shadow: 0 12px 30px -10px {{ $a['color'] }};animation-delay:{{ $i * 0.4 }}s;">
                        <i class="fas {{ $a['icon'] }} text-xl text-white" style="animation-delay:{{ $i * 0.5 }}s;"></i>
                    </div>
                    <div class="relative text-[11px] font-bold uppercase tracking-wider mb-2" style="color: {{ $a['color'] }};">{{ $a['eyebrow'] }}</div>
                    <h3 class="relative text-xl font-bold mb-3 leading-snug">{!! $a['title'] !!}</h3>
                    <p class="relative text-sm text-gray-400 leading-relaxed mb-6 flex-1">{!! $a['desc'] !!}</p>
                    <button type="button" onclick="window.trackMarketingEvent && window.trackMarketingEvent('landing_home_cta','audience'); window.dispatchEvent(new CustomEvent('open-auth',{detail:{tab:'register'}}))" class="relative btn-bounce inline-flex items-center justify-center gap-2 px-5 py-2.5 grad-bar text-white rounded-full text-sm font-bold self-start">
                        {{ $a['cta'] }} <i class="aud-arrow fas fa-arrow-right text-[10px]" style="animation-delay:{{ $i * 0.3 }}s;"></i>
                    </button>
                </article>
            @endforeach
        </div>
    </div>
</section>

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

{{-- ==================== ZONE · WHAT YOU CAN BUILD ==================== --}}
{{-- ============================ 1 · BUILD ============================ --}}
<section id="features" class="py-24 lg:py-32 relative overflow-hidden">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c1)">Build</div>
            <h2 class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                A whole website,<br><span class="grad-text">AI-built or drag-and-drop.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">Describe it and your AI stacks the blocks &mdash; or build by hand: text, images, video, audio, files, embeds and forms in multi-column layouts. Pick a theme. Go live.</p>
        </div>

        {{-- AI builder prompt showcase (typewriter loop, balances the drag loop below) --}}
        <div class="reveal rd-3 ai-prompt-card glass max-w-3xl mx-auto mb-8">
            <div class="ai-prompt-head">
                <span class="ai-badge"><i class="fas fa-wand-magic-sparkles"></i> AI builder</span>
                <span class="ai-status" id="ai-prompt-status"><span class="ai-status-dot"></span><span id="ai-prompt-status-text">Ready</span></span>
            </div>
            <div class="ai-prompt-input">
                <i class="fas fa-wand-magic-sparkles ai-spark" aria-hidden="true"></i>
                <div class="ai-typed" aria-hidden="true"><span id="ai-typed-text"></span><span class="ai-caret"></span></div>
                <button type="button" class="ai-gen-btn" onclick="window.trackMarketingEvent && window.trackMarketingEvent('landing_home_cta','ai_builder'); window.dispatchEvent(new CustomEvent('open-auth',{detail:{tab:'register'}}))">Generate <i class="fas fa-arrow-right"></i></button>
            </div>
            <div class="ai-build-chips" id="ai-build-chips" aria-hidden="true">
                <span class="ai-build-hint">AI stacks your blocks:</span>
                <span class="ai-chip"><i class="fas fa-user" style="color:var(--c1)"></i>Profile</span>
                <span class="ai-chip"><i class="fas fa-image" style="color:var(--c2)"></i>Hero</span>
                <span class="ai-chip"><i class="fas fa-share-nodes" style="color:var(--c3)"></i>Socials</span>
                <span class="ai-chip"><i class="fas fa-store" style="color:var(--c4)"></i>Shop</span>
                <span class="ai-chip"><i class="fas fa-wpforms" style="color:var(--c5)"></i>Form</span>
            </div>
        </div>

        <div class="grid lg:grid-cols-12 gap-6">
            <div class="reveal rd-1 lg:col-span-7 glass rounded-3xl p-7 tilt">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wider mb-1" style="color:var(--c2)">Drag-and-drop Link in Bio editor</div>
                        <h3 class="text-xl font-bold">Reorder blocks. Build columns. Ship.</h3>
                    </div>
                    <span class="hidden sm:inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold text-white" style="background:rgba(61,107,255,.25);color:#bccfff"><i class="fas fa-grip-vertical"></i> Drag</span>
                </div>
                <div class="grid grid-cols-12 gap-3">
                    <div class="col-span-12 sm:col-span-7 bg-[#0a0a14] feat-preview rounded-2xl p-3 border border-white/5">
                        <div class="build-list space-y-2">
                            {{-- 1. Hero image --}}
                            <div class="build-row lift" data-bl-style="image">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-ic" style="background:rgba(27,212,217,.2)"><i class="fas fa-image" style="color:var(--c1)"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">Hero image</div><div class="bl-sub">1200×630 · WEBP</div></div>
                                <div class="bl-thumb" style="background:linear-gradient(135deg,#1bd4d9,#3d6bff)"></div>
                            </div>
                            {{-- 2. Free Templates link --}}
                            <div class="build-row lift" data-bl-style="link">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-ic" style="background:rgba(61,107,255,.25)"><i class="fas fa-link" style="color:var(--c2)"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">Free Templates</div><div class="bl-sub font-mono">jane.co/templates</div></div>
                                <span class="bl-chip">Link</span>
                            </div>
                            {{-- 3. Shop Merch (selected/live) --}}
                            <div class="build-row is-selected lift" data-bl-style="shop">
                                <i class="fas fa-grip-vertical bl-grip" style="color:var(--c3)"></i>
                                <div class="bl-ic" style="background:rgba(255,138,60,.25)"><i class="fas fa-store" style="color:var(--c4)"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">Shop Merch</div><div class="bl-sub">22 products · From $ 19</div></div>
                                <span class="bl-live"><span class="dot"></span>Live</span>
                            </div>
                            {{-- 4. YouTube embed --}}
                            <div class="build-row lift" data-bl-style="video">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-ic" style="background:rgba(255,0,51,.2)"><i class="fab fa-youtube" style="color:#ff0033"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">Latest video</div><div class="bl-sub">Embed · 4:20</div></div>
                                <div class="bl-thumb-dark"><i class="fas fa-play"></i></div>
                            </div>
                            {{-- 5. Spotify audio --}}
                            <div class="build-row lift" data-bl-style="audio">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-ic" style="background:rgba(30,215,96,.2)"><i class="fab fa-spotify" style="color:#1ed760"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">Latest track</div><div class="bl-sub">Saltwater · 3:42</div></div>
                                <div class="bl-eq" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
                            </div>
                            {{-- 6. Gallery --}}
                            <div class="build-row lift" data-bl-style="gallery">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-ic" style="background:rgba(233,78,140,.2)"><i class="fas fa-images" style="color:var(--c3)"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">Photo gallery</div><div class="bl-sub">12 photos · Masonry</div></div>
                                <div class="bl-mini-grid"><i></i><i></i><i></i><i></i></div>
                            </div>
                            {{-- 7. Newsletter form --}}
                            <div class="build-row lift" data-bl-style="form">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-ic" style="background:rgba(255,200,69,.25)"><i class="fas fa-wpforms" style="color:var(--c5)"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">Newsletter form</div><div class="bl-sub">Email + name · 1.2k subs</div></div>
                                <span class="bl-chip">Form</span>
                            </div>
                            {{-- 8. Calendar / booking --}}
                            <div class="build-row lift" data-bl-style="calendar">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-ic" style="background:rgba(14,165,233,.2)"><i class="fas fa-calendar-check" style="color:#0ea5e9"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">Book a call</div><div class="bl-sub">30 min · Calendly</div></div>
                                <div class="bl-date"><span class="mo">JUN</span><span class="da">14</span></div>
                            </div>
                            {{-- 9. Tip jar / payments --}}
                            <div class="build-row lift" data-bl-style="tip">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-ic" style="background:rgba(236,72,153,.2)"><i class="fas fa-hand-holding-heart" style="color:#ec4899"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">Tip jar</div><div class="bl-sub">$ 3 / $ 5 / Custom</div></div>
                                <span class="bl-chip">Pay</span>
                            </div>
                            {{-- 10. Socials row --}}
                            <div class="build-row lift" data-bl-style="socials">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-ic" style="background:rgba(61,107,255,.2)"><i class="fas fa-share-nodes" style="color:#bccfff"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">Socials row</div><div class="bl-sub">IG · TikTok · YT · X</div></div>
                                <div class="bl-socials">
                                    <i class="fab fa-instagram" style="color:#e94e8c"></i>
                                    <i class="fab fa-tiktok"></i>
                                    <i class="fab fa-youtube" style="color:#ff0033"></i>
                                </div>
                            </div>
                            {{-- 11. Map / location --}}
                            <div class="build-row lift" data-bl-style="map">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-ic" style="background:rgba(34,211,238,.2)"><i class="fas fa-location-dot" style="color:#22d3ee"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">Studio location</div><div class="bl-sub">Berlin · 12 Mitte St.</div></div>
                                <div class="bl-map" aria-hidden="true"></div>
                            </div>
                            {{-- 12. Countdown --}}
                            <div class="build-row lift" data-bl-style="countdown">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-ic" style="background:rgba(251,191,36,.2)"><i class="fas fa-hourglass-half" style="color:#fbbf24"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">Drop countdown</div><div class="bl-sub">Spring capsule · 3d 14h</div></div>
                                <div class="bl-count"><span>03</span>:<span>14</span>:<span>22</span></div>
                            </div>
                            {{-- 13. FAQ --}}
                            <div class="build-row lift" data-bl-style="faq">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-ic" style="background:rgba(92,131,255,.2)"><i class="fas fa-circle-question" style="color:#90acff"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">FAQ</div><div class="bl-sub">6 questions · Accordion</div></div>
                                <i class="fas fa-chevron-down bl-chev"></i>
                            </div>
                            {{-- 14. File download --}}
                            <div class="build-row lift" data-bl-style="file">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-ic" style="background:rgba(59,130,246,.2)"><i class="fas fa-file-lines" style="color:#60a5fa"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">Media kit · PDF</div><div class="bl-sub">2.4 MB · 12 pages</div></div>
                                <span class="bl-chip">PDF</span>
                            </div>
                            {{-- 15. Testimonials --}}
                            <div class="build-row lift" data-bl-style="quote">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-ic" style="background:rgba(16,185,129,.2)"><i class="fas fa-quote-right" style="color:#10b981"></i></div>
                                <div class="flex-1 min-w-0"><div class="bl-title">Testimonials</div><div class="bl-sub">4.9★ · 140 reviews</div></div>
                                <div class="bl-stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                            </div>
                            {{-- 16. Two-column row --}}
                            <div class="build-row is-columns lift" data-bl-style="cols">
                                <i class="fas fa-grip-vertical bl-grip"></i>
                                <div class="bl-col"><div class="bl-ic sm" style="background:rgba(27,212,217,.2)"><i class="fas fa-download" style="color:var(--c1)"></i></div><div class="bl-col-t">Download</div></div>
                                <div class="bl-col"><div class="bl-ic sm" style="background:rgba(255,200,69,.25)"><i class="fas fa-envelope" style="color:var(--c5)"></i></div><div class="bl-col-t">Contact</div></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-span-12 sm:col-span-5 bg-[#0a0a14] feat-preview rounded-2xl p-3 border border-white/5 flex items-center justify-center">
                        <div class="bb-phone">
                            <div class="bb-screen">
                                <div class="bb-notch" aria-hidden="true"></div>
                                <div class="bb-scroll">
                                  <div class="bb-phone-inner">
                                    {{-- Profile header --}}
                                    <div class="bb-prof">
                                        <div class="bb-av">JD</div>
                                        <div class="bb-h">@jane</div>
                                        <div class="bb-t">Creator · Berlin</div>
                                        <div class="bb-soc"><i class="fab fa-instagram"></i><i class="fab fa-tiktok"></i><i class="fab fa-youtube"></i><i class="fab fa-x-twitter"></i></div>
                                    </div>
                                    {{-- Hero image --}}
                                    <div class="bb-hero" aria-hidden="true"></div>
                                    {{-- Link --}}
                                    <div class="bb-btn">Templates</div>
                                    {{-- Shop (selected highlight) --}}
                                    <div class="bb-btn is-accent"><i class="fas fa-store"></i>Shop Merch<span class="bb-live"><span class="dot"></span>Live</span></div>
                                    {{-- Video embed --}}
                                    <div class="bb-video"><i class="fas fa-play"></i><span>Latest video · 4:20</span></div>
                                    {{-- Audio --}}
                                    <div class="bb-audio">
                                        <div class="ico"><i class="fab fa-spotify"></i></div>
                                        <div class="meta"><div class="tt">Saltwater</div><div class="ss">3:42</div></div>
                                        <div class="eq"><i></i><i></i><i></i><i></i></div>
                                    </div>
                                    {{-- Gallery 3-up --}}
                                    <div class="bb-gal"><i></i><i></i><i></i></div>
                                    {{-- Newsletter form --}}
                                    <div class="bb-form">
                                        <div class="fi">Email</div>
                                        <div class="fb">Subscribe</div>
                                    </div>
                                    {{-- Calendar --}}
                                    <div class="bb-cal">
                                        <div class="dt"><span class="mo">JUN</span><span class="da">14</span></div>
                                        <div class="mt"><div class="tt">Book a 30-min call</div><div class="ss">Calendly · Free intro</div></div>
                                    </div>
                                    {{-- Tip jar --}}
                                    <div class="bb-tip"><span>Tip jar</span><span class="amts">$3<i>·</i>$5<i>·</i>$10</span></div>
                                    {{-- Map --}}
                                    <div class="bb-map"><i class="fas fa-location-dot"></i>Berlin · 12 Mitte St.</div>
                                    {{-- Countdown --}}
                                    <div class="bb-count"><span>03</span><i>:</i><span>14</span><i>:</i><span>22</span></div>
                                    {{-- Two-column --}}
                                    <div class="bb-2col"><div><i class="fas fa-download"></i>Press kit</div><div><i class="fas fa-envelope"></i>Contact</div></div>
                                    {{-- FAQ --}}
                                    <div class="bb-faq"><span>FAQ</span><i class="fas fa-chevron-down"></i></div>
                                    {{-- Testimonials --}}
                                    <div class="bb-quote">"Best tool I've used all year" <div><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div></div>
                                    {{-- Socials --}}
                                    <div class="bb-socials"><i class="fab fa-instagram"></i><i class="fab fa-tiktok"></i><i class="fab fa-youtube"></i><i class="fab fa-x-twitter"></i><i class="fab fa-spotify"></i></div>
                                    <div class="bb-foot">1inme.co/@jane</div>
                                  </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="reveal rd-2 lg:col-span-5 grid grid-cols-1 gap-6 auto-rows-fr">
                {{-- Themes & design controls --}}
                <div class="glass rounded-3xl p-6 lift relative overflow-hidden flex flex-col">
                    <div class="absolute -top-8 -right-8 w-32 h-32 rounded-full opacity-25" style="background:var(--c1)"></div>
                    <div class="relative flex flex-col flex-1">
                        <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-4" style="background:rgba(27,212,217,.2)"><i class="fas fa-palette text-xl" style="color:var(--c1)"></i></div>
                        <h3 class="text-lg font-bold mb-1.5">Themes &amp; design controls</h3>
                        <p class="text-sm text-gray-400 mb-5">Pick from beautiful presets, then fine-tune fonts, colours and layouts to match your vibe.</p>

                        <div class="mt-auto space-y-3">
                            {{-- Active theme label --}}
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">Theme</span>
                                <span id="th-active-label" class="text-[10px] font-bold px-2 py-0.5 rounded-full border" style="background:rgba(27,212,217,.12);border-color:rgba(27,212,217,.35);color:var(--c1)">Aurora</span>
                            </div>
                            {{-- Theme preset swatches --}}
                            <div class="flex items-center gap-2">
                                <span class="th-swatch" data-th-name="Aurora" data-th-c1="#1bd4d9" data-th-c2="#3d6bff" style="background:linear-gradient(135deg,#1bd4d9,#3d6bff)" title="Aurora"></span>
                                <span class="th-swatch" data-th-name="Sunset" data-th-c1="#e94e8c" data-th-c2="#ff8a3c" style="background:linear-gradient(135deg,#e94e8c,#ff8a3c)" title="Sunset"></span>
                                <span class="th-swatch" data-th-name="Noir"   data-th-c1="#6b7280" data-th-c2="#3f3f46" style="background:linear-gradient(135deg,#0e0e10,#3f3f46);border:1px solid rgba(255,255,255,.15)" title="Noir"></span>
                                <span class="th-swatch" data-th-name="Sand"   data-th-c1="#f59e0b" data-th-c2="#fef3c7" style="background:linear-gradient(135deg,#fef3c7,#f59e0b)" title="Sand"></span>
                                <span class="th-swatch" data-th-name="Mint"   data-th-c1="#10b981" data-th-c2="#a7f3d0" style="background:linear-gradient(135deg,#a7f3d0,#10b981)" title="Mint"></span>
                                <span class="th-swatch" data-th-name="Sky"    data-th-c1="#3b82f6" data-th-c2="#dbeafe" style="background:linear-gradient(135deg,#dbeafe,#3b82f6)" title="Sky"></span>
                                <span class="text-[11px] font-bold text-gray-500 ml-1">+24</span>
                            </div>
                            {{-- Theme preview bar --}}
                            <div class="th-preview-bar">
                                <div id="th-preview-fill" class="th-preview-bar-fill" style="width:100%;background:linear-gradient(90deg,#1bd4d9,#3d6bff)"></div>
                            </div>
                            {{-- Font + radius row --}}
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="th-pill" style="font-family:'Inter',sans-serif">Aa Inter</span>
                                <span class="th-pill" style="font-family:Georgia,serif">Aa Serif</span>
                                <span class="th-pill" style="font-family:'Courier New',monospace">Aa Mono</span>
                                <span class="th-pill th-pill--accent"><i class="fas fa-circle-half-stroke text-[9px]"></i> Round</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Mobile-first by default --}}
                <div class="glass rounded-3xl p-6 lift relative overflow-hidden flex flex-col">
                    <div class="absolute -top-8 -right-8 w-32 h-32 rounded-full opacity-25" style="background:var(--c3)"></div>
                    <div class="relative flex flex-col flex-1">
                        <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-4" style="background:rgba(233,78,140,.2)"><i class="fas fa-mobile-screen text-xl" style="color:var(--c3)"></i></div>
                        <h3 class="text-lg font-bold mb-1.5">Mobile-first by default</h3>
                        <p class="text-sm text-gray-400 mb-5">Every theme looks razor-sharp on small screens — that’s where your audience actually is.</p>

                        <div class="mt-auto mf-mock" aria-hidden="true">
                            {{-- Tiny phone mockup --}}
                            <div class="mf-phone">
                                <div class="mf-notch"></div>
                                <div class="mf-avatar"></div>
                                <div class="mf-name"></div>
                                <div class="mf-handle"></div>
                                <div class="mf-btn" style="animation-delay:0s"></div>
                                <div class="mf-btn" style="width:78%;animation-delay:.28s"></div>
                                <div class="mf-btn" style="width:62%;animation-delay:.56s"></div>
                            </div>
                            {{-- Stats --}}
                            <div class="mf-stats">
                                <div>
                                    <strong>92%</strong><span>mobile traffic</span>
                                    <div class="mf-stat-bar mt-1.5" style="background:linear-gradient(90deg,var(--c1),var(--c2));width:92%;animation-delay:.1s"></div>
                                </div>
                                <div>
                                    <strong>&lt;1.2s</strong><span>load time</span>
                                    <div class="mf-stat-bar mt-1.5" style="background:linear-gradient(90deg,var(--c3),var(--c4));width:82%;animation-delay:.35s"></div>
                                </div>
                                <div>
                                    <strong>100</strong><span>Lighthouse</span>
                                    <div class="mf-stat-bar mt-1.5" style="background:linear-gradient(90deg,#10b981,#1bd4d9);width:100%;animation-delay:.6s"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>                </div>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    'use strict';
    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ─── Builder drag-reorder loop ─── */
    var buildList = document.querySelector('.build-list');
    if (buildList && !reducedMotion) {
        var busy = false;

        function pickRow(rows, exclude) {
            var candidates = rows.filter(function (r) { return r !== exclude; });
            return candidates[Math.floor(Math.random() * candidates.length)];
        }

        function runDrag() {
            if (busy) return;
            var rows = Array.from(buildList.querySelectorAll('.build-row'));
            if (rows.length < 4) return;

            /* Pick src from middle of list (avoid first/last) */
            var srcIdx = 1 + Math.floor(Math.random() * (rows.length - 3));
            var src = rows[srcIdx];

            /* Pick target at least 2 slots away */
            var tgtIdx;
            var tries = 0;
            do {
                tgtIdx = Math.floor(Math.random() * rows.length);
                tries++;
            } while (Math.abs(tgtIdx - srcIdx) < 2 && tries < 20);
            var tgt = rows[tgtIdx];

            busy = true;

            /* Phase 1: lift */
            src.classList.add('bl-dragging');

            setTimeout(function () {
                /* Phase 2: ghost + drop indicator */
                src.classList.remove('bl-dragging');
                src.classList.add('bl-ghost');

                var indicator = document.createElement('div');
                indicator.className = 'bl-drop-indicator';
                var insertRef = tgtIdx > srcIdx ? tgt.nextSibling : tgt;
                buildList.insertBefore(indicator, insertRef);

                setTimeout(function () {
                    /* Phase 3: move + drop */
                    indicator.remove();
                    src.classList.remove('bl-ghost');

                    var moveBefore = tgtIdx > srcIdx ? tgt.nextSibling : tgt;
                    buildList.insertBefore(src, moveBefore);
                    src.classList.add('bl-dropping');

                    setTimeout(function () {
                        src.classList.remove('bl-dropping');
                        busy = false;
                    }, 450);
                }, 650);
            }, 550);
        }

        /* Start after 1.8s, then every 4.2s */
        setTimeout(function () {
            runDrag();
            setInterval(runDrag, 4200);
        }, 1800);
    }

    /* ─── Theme swatch cycling ─── */
    var swatches = Array.from(document.querySelectorAll('.th-swatch[data-th-name]'));
    var thLabel = document.getElementById('th-active-label');
    var thFill  = document.getElementById('th-preview-fill');
    if (swatches.length && !reducedMotion) {
        var thIdx = 0;
        function cycleSwatch() {
            swatches.forEach(function (s) { s.classList.remove('is-active'); });
            var active = swatches[thIdx];
            active.classList.add('is-active');
            var name = active.dataset.thName || '';
            var c1   = active.dataset.thC1 || '#1bd4d9';
            var c2   = active.dataset.thC2 || '#3d6bff';
            if (thLabel) {
                thLabel.textContent = name;
                thLabel.style.background     = 'rgba(' + hexToRgb(c1) + ',.14)';
                thLabel.style.borderColor    = 'rgba(' + hexToRgb(c1) + ',.4)';
                thLabel.style.color          = c1;
            }
            if (thFill) {
                thFill.style.background = 'linear-gradient(90deg,' + c1 + ',' + c2 + ')';
                /* animate width: collapse then expand */
                thFill.style.transition = 'none';
                thFill.style.width = '0%';
                requestAnimationFrame(function () {
                    requestAnimationFrame(function () {
                        thFill.style.transition = 'width .55s cubic-bezier(.16,1,.3,1)';
                        thFill.style.width = '100%';
                    });
                });
            }
            thIdx = (thIdx + 1) % swatches.length;
        }
        cycleSwatch();
        setInterval(cycleSwatch, 2000);
    }

    function hexToRgb(hex) {
        var r = parseInt(hex.slice(1,3),16);
        var g = parseInt(hex.slice(3,5),16);
        var b = parseInt(hex.slice(5,7),16);
        return r + ',' + g + ',' + b;
    }

    /* ─── AI builder prompt typewriter loop ─── */
    var aiTyped   = document.getElementById('ai-typed-text');
    var aiStatus  = document.getElementById('ai-prompt-status');
    var aiStatusT = document.getElementById('ai-prompt-status-text');
    var aiChips   = document.getElementById('ai-build-chips');
    if (aiTyped) {
        var aiPrompts = [
            'Build a page for a Berlin techno artist with tour dates & merch',
            'Create a link-in-bio for a vegan fitness coach',
            'Make a landing page for my coffee shop with a menu & map',
            'Design a portfolio for a freelance photographer',
            'Set up a link-in-bio for a podcast with episodes & socials'
        ];
        var aiCard  = aiTyped.closest('.ai-prompt-card');
        var chipEls = aiChips ? Array.prototype.slice.call(aiChips.querySelectorAll('.ai-chip')) : [];

        function aiSetStatus(text, building) {
            if (aiStatusT) aiStatusT.textContent = text;
            if (aiStatus) aiStatus.classList.toggle('is-building', !!building);
            if (aiCard) aiCard.classList.toggle('is-building', !!building);
        }

        if (reducedMotion) {
            /* Static, fully-typed state — no motion */
            aiTyped.textContent = aiPrompts[0];
            aiSetStatus('Page built', false);
            chipEls.forEach(function (c) { c.classList.add('is-in'); });
        } else {
            var aiIdx = 0;
            function clearChips() { chipEls.forEach(function (c) { c.classList.remove('is-in'); }); }

            function typePrompt() {
                var text = aiPrompts[aiIdx];
                aiTyped.textContent = '';
                clearChips();
                aiSetStatus('Ready', false);
                var i = 0;
                (function typeChar() {
                    if (i <= text.length) {
                        aiTyped.textContent = text.slice(0, i);
                        i++;
                        setTimeout(typeChar, 32 + Math.random() * 34);
                    } else {
                        /* Done typing → "build" the page */
                        setTimeout(buildPhase, 480);
                    }
                })();
            }

            function buildPhase() {
                aiSetStatus('Building…', true);
                chipEls.forEach(function (c, n) {
                    setTimeout(function () { c.classList.add('is-in'); }, 140 + n * 200);
                });
                var settle = 140 + chipEls.length * 200 + 500;
                setTimeout(function () {
                    aiSetStatus('Page built', false);
                    /* Hold the finished state, then move on */
                    setTimeout(function () {
                        aiIdx = (aiIdx + 1) % aiPrompts.length;
                        typePrompt();
                    }, 2000);
                }, settle);
            }

            setTimeout(typePrompt, 900);
        }
    }

    /* ─── mf-stat-bar: re-trigger animation on intersection ─── */
    var statBars = document.querySelectorAll('.mf-stat-bar');
    if (statBars.length && 'IntersectionObserver' in window && !reducedMotion) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) {
                    e.target.style.animation = 'none';
                    requestAnimationFrame(function () {
                        e.target.style.animation = '';
                    });
                    io.unobserve(e.target);
                }
            });
        }, { threshold: 0.5 });
        statBars.forEach(function (b) { io.observe(b); });
    }
})();
</script>

{{-- ============================ 2 · SHARE ============================ --}}
<section id="share" class="py-24 lg:py-32 relative overflow-hidden">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c3)">Share</div>
            <h2 class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                Share your Sayzio<br><span class="grad-text">anywhere you like.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">Branded short links and dynamic QR codes you can repoint at any time. Add your link to bios, posters, business cards, packaging — anywhere. Save links from any browser tab with the Zio Extension, or share straight from any mobile app into Sayzio.</p>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6 glass-ambient-wash">
            {{-- 1 · Branded short links --}}
            <div class="reveal rd-1 glass rounded-3xl p-7 tilt share-card">
                <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full opacity-30" style="background:var(--c1)"></div>
                <div class="relative">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-4" style="background:rgba(27,212,217,.2)"><i class="fas fa-link text-xl" style="color:var(--c1)"></i></div>
                    <h3 class="text-xl font-bold mb-2">Branded short links</h3>
                    <p class="text-sm text-gray-400 mb-5">Custom slugs, UTM-ready, click tracking. Looks like you, not a random shortener.</p>
                    <div class="sl-pill">
                        <i class="fas fa-link text-[10px]" style="color:var(--c1)"></i>
                        <span class="host">1inme.co/</span><span class="slug">spring-drop</span>
                    </div>
                    <div class="sl-counter">
                        <span><span class="num">1,284</span> clicks today</span>
                        <span class="sl-spark" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i></span>
                    </div>
                </div>
            </div>

            {{-- 2 · Custom domain (NEW) --}}
            <div class="reveal rd-2 glass rounded-3xl p-7 tilt share-card">
                <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full opacity-30" style="background:var(--c2)"></div>
                <div class="relative">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-4" style="background:rgba(61,107,255,.22)"><i class="fas fa-globe text-xl" style="color:var(--c2)"></i></div>
                    <h3 class="text-xl font-bold mb-2">Custom domain</h3>
                    <p class="text-sm text-gray-400 mb-5">Bring your own domain like <span class="text-white">links.yourbrand.com</span> — auto-SSL, zero DNS headaches.</p>
                    <div class="cd-stage">
                        <div class="cd-bar">
                            <span class="lock"><i class="fas fa-lock"></i></span>
                            <span class="sub">https://</span><span class="brand">links.</span><span class="brand">yourbrand</span><span class="tld">.com</span><span class="path">/launch</span>
                        </div>
                        <div class="cd-rows" aria-hidden="true">
                            <div class="cd-rec">
                                <span class="ty">CNAME</span>
                                <span class="val">links → cname.1inme.co</span>
                                <span class="ok"><i class="fas fa-circle-check"></i></span>
                            </div>
                            <div class="cd-rec">
                                <span class="ty">TXT</span>
                                <span class="val">_1inme-verify=ok-91a2</span>
                                <span class="ok"><i class="fas fa-circle-check"></i></span>
                            </div>
                            <div class="cd-rec">
                                <span class="ty">SSL</span>
                                <span class="val">Let's Encrypt · auto-renew</span>
                                <span class="ok"><i class="fas fa-circle-check"></i></span>
                            </div>
                        </div>
                        <span class="cd-status"><span class="pulse"></span>Live · secured</span>
                    </div>
                </div>
            </div>

            {{-- 3 · Dynamic QR codes --}}
            <div class="reveal rd-3 glass rounded-3xl p-7 tilt share-card">
                <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full opacity-30" style="background:var(--c3)"></div>
                <div class="relative">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-4" style="background:rgba(233,78,140,.2)"><i class="fas fa-qrcode text-xl" style="color:var(--c3)"></i></div>
                    <h3 class="text-xl font-bold mb-2">Dynamic QR codes</h3>
                    <p class="text-sm text-gray-400 mb-5">Print once, redirect forever. Change the destination without reprinting.</p>
                    <div class="qr-stage qr-stage--left" aria-hidden="true">
                        <span class="qr-corner tl"></span>
                        <span class="qr-corner tr"></span>
                        <span class="qr-corner bl"></span>
                        <span class="qr-corner br"></span>
                        <span class="qr-scans-pill">+128 scans · today</span>
                        @php
                            $qrSize = 29;
                            $qrGrid = array_fill(0, $qrSize, array_fill(0, $qrSize, 0));
                            $qrFinder = function (&$g, $ox, $oy) {
                                for ($i = 0; $i < 7; $i++) {
                                    for ($j = 0; $j < 7; $j++) {
                                        $on = ($i === 0 || $i === 6 || $j === 0 || $j === 6)
                                            || ($i >= 2 && $i <= 4 && $j >= 2 && $j <= 4);
                                        $g[$oy + $i][$ox + $j] = $on ? 1 : 0;
                                    }
                                }
                            };
                            $qrFinder($qrGrid, 0, 0);
                            $qrFinder($qrGrid, 22, 0);
                            $qrFinder($qrGrid, 0, 22);
                            for ($i = 0; $i < 5; $i++) {
                                for ($j = 0; $j < 5; $j++) {
                                    $on = ($i === 0 || $i === 4 || $j === 0 || $j === 4) || ($i === 2 && $j === 2);
                                    $qrGrid[20 + $i][20 + $j] = $on ? 1 : 0;
                                }
                            }
                            for ($i = 8; $i <= 20; $i++) {
                                $qrGrid[6][$i] = ($i % 2 === 0) ? 1 : 0;
                                $qrGrid[$i][6] = ($i % 2 === 0) ? 1 : 0;
                            }
                            $qrReserved = function ($x, $y) {
                                if ($x < 8 && $y < 8) return true;
                                if ($x >= 22 && $y < 8) return true;
                                if ($x < 8 && $y >= 22) return true;
                                if ($x >= 20 && $x < 25 && $y >= 20 && $y < 25) return true;
                                if ($x === 6 || $y === 6) return true;
                                return false;
                            };
                            mt_srand(20251128);
                            for ($y = 0; $y < $qrSize; $y++) {
                                for ($x = 0; $x < $qrSize; $x++) {
                                    if (!$qrReserved($x, $y)) {
                                        $qrGrid[$y][$x] = (mt_rand(0, 100) < 47) ? 1 : 0;
                                    }
                                }
                            }
                            for ($y = 12; $y <= 16; $y++) {
                                for ($x = 12; $x <= 16; $x++) {
                                    $qrGrid[$y][$x] = 0;
                                }
                            }
                        @endphp
                        <svg class="qr-svg" viewBox="0 0 29 29" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
                            <defs>
                                <linearGradient id="qrLogoGrad" x1="0" y1="0" x2="1" y2="1">
                                    <stop offset="0" stop-color="#e94e8c"/>
                                    <stop offset="1" stop-color="#3d6bff"/>
                                </linearGradient>
                            </defs>
                            @for ($y = 0; $y < $qrSize; $y++)
                                @for ($x = 0; $x < $qrSize; $x++)
                                    @if ($qrGrid[$y][$x])
                                        <rect x="{{ $x }}" y="{{ $y }}" width="1.04" height="1.04" rx="0.18" ry="0.18" fill="#0e0e10"/>
                                    @endif
                                @endfor
                            @endfor
                            <rect x="11.4" y="11.4" width="6.2" height="6.2" rx="1.3" ry="1.3" fill="#fff"/>
                            <rect x="12.1" y="12.1" width="4.8" height="4.8" rx="1" ry="1" fill="url(#qrLogoGrad)"/>
                            <text x="14.5" y="15.95" text-anchor="middle" font-family="Inter,system-ui,-apple-system,sans-serif" font-weight="900" font-size="3.2" fill="#fff">1</text>
                        </svg>
                    </div>
                </div>
            </div>

            {{-- 4 · Channel-ready --}}
            <div class="reveal rd-4 glass rounded-3xl p-7 tilt share-card">
                <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full opacity-30" style="background:var(--c4)"></div>
                <div class="relative">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-4" style="background:rgba(255,138,60,.2)"><i class="fas fa-share-nodes text-xl" style="color:var(--c4)"></i></div>
                    <h3 class="text-xl font-bold mb-2">Channel-ready</h3>
                    <p class="text-sm text-gray-400 mb-5">Pre-made share cards for every channel. Pixels, UTM and OG ready out of the box.</p>
                    <div class="ch-grid">
                        @foreach(['fa-instagram'=>'#e94e8c','fa-tiktok'=>'#1bd4d9','fa-youtube'=>'#e94e8c','fa-x-twitter'=>'#3d6bff','fa-linkedin'=>'#1bd4d9','fa-facebook'=>'#3d6bff'] as $ic => $col)
                            <span class="ch-icon" style="color:{{ $col }}"><i class="fab {{ $ic }}"></i></span>
                        @endforeach
                    </div>
                    <div class="ch-tags" aria-hidden="true">
                        <span>OG</span><span>Pixels</span><span>UTM</span><span>UTM-A/B</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ============================ DOMAINS & URL ALIASES ============================ --}}
<section id="domains" class="py-24 lg:py-32 relative overflow-hidden" aria-labelledby="domains-h">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c2)">Domains &amp; URL aliases</div>
            <h2 id="domains-h" class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                Pick a domain that fits.<br><span class="grad-text">Or bring your own.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">
                Launch on one of our branded shared domains, connect your own custom domain, or give any link a memorable slug — with multiple aliases pointing at the same AI-built page.
            </p>
        </div>

        <div class="grid md:grid-cols-3 gap-6 glass-ambient-wash">
            {{-- 1 · Multiple global domains --}}
            <div class="reveal rd-1 glass rounded-3xl p-7 tilt relative overflow-hidden">
                <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full opacity-30" style="background:var(--c1)"></div>
                <div class="relative">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-4" style="background:rgba(27,212,217,.2)"><i class="fas fa-layer-group text-xl" style="color:var(--c1)"></i></div>
                    <h3 class="text-xl font-bold mb-2">Multiple global domains</h3>
                    <p class="text-sm text-gray-400 mb-5">Choose from our branded shared domains at sign-up — no DNS setup required.</p>
                    <div class="flex flex-wrap gap-2" aria-hidden="true">
                        @foreach(($showcaseDomains ?? \App\Modules\User\Models\Domain::SHOWCASE_FALLBACK) as $__dom)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border border-white/10 bg-white/5 text-gray-200">
                                <i class="fas fa-globe text-[10px]" style="color:var(--c1)"></i> {{ $__dom }}
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- 2 · Bring your own domain --}}
            <div class="reveal rd-2 glass rounded-3xl p-7 tilt relative overflow-hidden">
                <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full opacity-30" style="background:var(--c2)"></div>
                <div class="relative">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-4" style="background:rgba(61,107,255,.22)"><i class="fas fa-globe text-xl" style="color:var(--c2)"></i></div>
                    <h3 class="text-xl font-bold mb-2">Bring your own domain</h3>
                    <p class="text-sm text-gray-400 mb-5">Connect a personal or brand domain like <span class="text-white">links.yourbrand.com</span> and verify it with a single CNAME record.</p>
                    <div class="space-y-2" aria-hidden="true">
                        <div class="flex items-center justify-between gap-2 px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-xs">
                            <span class="font-mono text-gray-400">CNAME</span>
                            <span class="font-mono text-gray-200 truncate">links → cname.1in.me</span>
                            <span style="color:var(--c1)"><i class="fas fa-circle-check"></i></span>
                        </div>
                        <div class="flex items-center justify-between gap-2 px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-xs">
                            <span class="font-mono text-gray-400">Status</span>
                            <span class="font-mono text-gray-200">Verified · auto-SSL</span>
                            <span style="color:var(--c1)"><i class="fas fa-circle-check"></i></span>
                        </div>
                    </div>
                    <p class="mt-4 text-[11px] text-gray-500"><i class="fas fa-crown text-[10px] mr-1" style="color:var(--c5)"></i> Custom domains are a paid-plan feature.</p>
                </div>
            </div>

            {{-- 3 · Custom URL aliases --}}
            <div class="reveal rd-3 glass rounded-3xl p-7 tilt relative overflow-hidden">
                <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full opacity-30" style="background:var(--c3)"></div>
                <div class="relative">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-4" style="background:rgba(233,78,140,.2)"><i class="fas fa-tags text-xl" style="color:var(--c3)"></i></div>
                    <h3 class="text-xl font-bold mb-2">Custom URL aliases</h3>
                    <p class="text-sm text-gray-400 mb-5">Pick a memorable primary slug, then add extra aliases that all open the same page — no redirects.</p>
                    <div class="space-y-2" aria-hidden="true">
                        <div class="flex items-center gap-2 px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-xs font-mono">
                            <i class="fas fa-star text-[10px]" style="color:var(--c5)"></i>
                            <span class="text-gray-400">1in.me/</span><span class="text-white">spring-drop</span>
                        </div>
                        <div class="flex items-center gap-2 px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-xs font-mono">
                            <i class="fas fa-link text-[10px]" style="color:var(--c3)"></i>
                            <span class="text-gray-400">1in.me/</span><span class="text-gray-200">sale</span>
                        </div>
                        <div class="flex items-center gap-2 px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-xs font-mono">
                            <i class="fas fa-link text-[10px]" style="color:var(--c3)"></i>
                            <span class="text-gray-400">1in.me/</span><span class="text-gray-200">drop24</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="reveal rd-4 mt-10 text-center">
            <a href="{{ route('site.domains') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-full text-sm font-bold text-white btn-bounce btn-glow grad-bar">
                Explore domains &amp; aliases <i class="fas fa-arrow-right text-xs"></i>
            </a>
        </div>
    </div>
</section>

{{-- ============================ 3 · GROW ============================ --}}
<section class="py-24 lg:py-32 relative overflow-hidden">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c5)">Grow</div>
            <h2 class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                Live analytics with<br><span class="grad-text">an AI growth coach.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">See visitors land on a world map, watch click trends per block, and let your AI Performance Coach suggest one-click fixes. AI Audience Insights even estimates who's visiting — students, professionals, businesses or creators — so you can tune every page to its crowd.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            {{-- Live geo card --}}
            <div class="reveal rd-1 lg:col-span-7 glass rounded-3xl p-7 tilt">
                <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wider mb-1" style="color:var(--c1)">Live geo heatmap</div>
                        <h3 class="text-xl font-bold">247 visitors right now in 14 countries</h3>
                    </div>
                    <span class="flex items-center gap-1.5 text-xs font-bold px-3 py-1 rounded-full" style="background:rgba(27,212,217,.15);color:var(--c1)"><span class="w-1.5 h-1.5 rounded-full pulse-dot" style="background:var(--c1)"></span>LIVE</span>
                </div>

                <div class="geo-map">
                    <div class="grid"></div>
                    {{-- Continents (simplified silhouettes) --}}
                    <svg class="continents" viewBox="0 0 320 180" preserveAspectRatio="none" aria-hidden="true">
                        {{-- North America --}}
                        <path d="M22,42 L48,32 L72,38 L80,52 L72,70 L86,82 L78,98 L60,108 L42,102 L30,86 L22,68 Z"/>
                        {{-- South America --}}
                        <path d="M82,108 L96,108 L102,124 L96,148 L86,160 L78,148 L76,128 Z"/>
                        {{-- Europe --}}
                        <path d="M138,38 L160,34 L172,42 L168,56 L156,62 L142,58 L134,48 Z"/>
                        {{-- Africa --}}
                        <path d="M150,68 L172,66 L184,82 L186,108 L172,132 L158,134 L144,118 L142,92 Z"/>
                        {{-- Asia --}}
                        <path d="M178,30 L228,26 L264,38 L274,56 L256,72 L228,72 L196,68 L182,52 Z"/>
                        {{-- SE Asia --}}
                        <path d="M242,78 L260,74 L266,86 L256,98 L246,94 Z"/>
                        {{-- Australia --}}
                        <path d="M256,118 L284,116 L292,128 L282,140 L262,138 L256,128 Z"/>
                    </svg>

                    {{-- Animated streams between visitor pins --}}
                    <svg class="absolute inset-0 w-full h-full" viewBox="0 0 320 180" preserveAspectRatio="none" aria-hidden="true">
                        <path class="geo-stream" stroke="#1bd4d9" d="M58,68 Q120,40 156,52"/>
                        <path class="geo-stream" stroke="#e94e8c" d="M156,52 Q210,30 252,52" style="animation-delay:-.5s"/>
                        <path class="geo-stream" stroke="#ffc845" d="M168,98 Q220,108 274,124" style="animation-delay:-1s"/>
                        <path class="geo-stream" stroke="#3d6bff" d="M58,68 Q70,90 88,112" style="animation-delay:-.7s"/>
                    </svg>

                    {{-- Sweeping meridian line --}}
                    <span class="meridian" aria-hidden="true"></span>

                    {{-- Live ticker overlay --}}
                    <div class="geo-ticker" aria-hidden="true">
                        <span class="live">Live</span>
                        <div class="feed">
                            <div>👤 <em>Sara</em> · Tokyo · clicked <em>/spring-drop</em></div>
                            <div>👤 <em>Liam</em> · London · scanned <em>QR · merch</em></div>
                            <div>👤 <em>Amara</em> · Lagos · followed <em>@jamie</em></div>
                            <div>👤 <em>Diego</em> · Mexico City · viewed <em>Link in Bio</em></div>
                        </div>
                    </div>

                    {{-- Pulsing visitor pins --}}
                    @foreach([
                        ['18%','38%','#1bd4d9'],
                        ['48%','29%','#e94e8c'],
                        ['76%','29%','#ffc845'],
                        ['28%','62%','#ff8a3c'],
                        ['83%','72%','#3d6bff'],
                        ['52%','58%','#1bd4d9'],
                        ['58%','78%','#e94e8c'],
                    ] as $i => $p)
                        <span class="geo-pin" style="left:{{ $p[0] }};top:{{ $p[1] }};--c:{{ $p[2] }}; animation-delay:{{ -$i*0.4 }}s">
                            <span class="ring r1" style="animation-delay:{{ -$i*0.4 }}s"></span>
                            <span class="ring r2"></span>
                            <span class="ring r3"></span>
                            <span class="core"></span>
                        </span>
                    @endforeach
                </div>

                {{-- Stat trio with animated bars --}}
                <div class="grid grid-cols-3 gap-4 mt-5">
                    <div class="geo-stat">
                        <div class="num">38.4k</div>
                        <div class="text-[10px] uppercase tracking-wider text-gray-500 mt-0.5">7-day visits</div>
                        <div class="bar" style="--to:78%"></div>
                    </div>
                    <div class="geo-stat">
                        <div class="num">9.1k</div>
                        <div class="text-[10px] uppercase tracking-wider text-gray-500 mt-0.5">QR scans</div>
                        <div class="bar" style="--to:60%"></div>
                    </div>
                    <div class="geo-stat">
                        <div class="num">2.4k</div>
                        <div class="text-[10px] uppercase tracking-wider text-gray-500 mt-0.5">New followers</div>
                        <div class="bar" style="--to:42%"></div>
                    </div>
                </div>

                {{-- Country flags marquee --}}
                <div class="geo-flags" aria-hidden="true">
                    <div class="marquee">
                        @foreach([
                            ['🇺🇸','US','58'],['🇬🇧','UK','41'],['🇯🇵','JP','37'],['🇩🇪','DE','29'],
                            ['🇫🇷','FR','24'],['🇧🇷','BR','22'],['🇮🇳','IN','19'],['🇨🇦','CA','14'],
                            ['🇲🇽','MX','11'],['🇦🇺','AU','9'],['🇳🇬','NG','7'],['🇰🇷','KR','6'],
                            ['🇪🇸','ES','5'],['🇮🇹','IT','4'],
                        ] as $f)
                            <span class="geo-flag"><span class="em">{{ $f[0] }}</span>{{ $f[1] }}<span class="n">{{ $f[2] }}</span></span>
                        @endforeach
                        @foreach([
                            ['🇺🇸','US','58'],['🇬🇧','UK','41'],['🇯🇵','JP','37'],['🇩🇪','DE','29'],
                            ['🇫🇷','FR','24'],['🇧🇷','BR','22'],['🇮🇳','IN','19'],['🇨🇦','CA','14'],
                            ['🇲🇽','MX','11'],['🇦🇺','AU','9'],['🇳🇬','NG','7'],['🇰🇷','KR','6'],
                            ['🇪🇸','ES','5'],['🇮🇹','IT','4'],
                        ] as $f)
                            <span class="geo-flag"><span class="em">{{ $f[0] }}</span>{{ $f[1] }}<span class="n">{{ $f[2] }}</span></span>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Coach card --}}
            <div class="reveal rd-2 lg:col-span-5 rounded-3xl p-7 tilt relative overflow-hidden text-white" style="background: #3d6bff;">
                <div class="absolute -top-12 -right-12 w-48 h-48 rounded-full bg-white/10"></div>
                <div class="absolute -bottom-16 -left-16 w-56 h-56 rounded-full bg-white/5"></div>
                <div class="relative">
                    <div class="text-xs font-bold uppercase tracking-wider mb-1 text-white/80">Performance Coach</div>
                    <h3 class="text-2xl font-bold mb-4">Health score <span class="font-extrabold">87</span> <span class="text-white/70 text-base font-normal">/ 100</span></h3>

                    <div class="coach-ring">
                        <span class="glow" aria-hidden="true"></span>
                        <svg viewBox="0 0 100 100">
                            <circle class="track" cx="50" cy="50" r="40" fill="none" stroke-width="9"/>
                            <circle class="fill"  cx="50" cy="50" r="40" fill="none" stroke-width="9"/>
                        </svg>
                        <div class="num">
                            <span class="big">87</span>
                            <span class="lbl">Health</span>
                        </div>
                    </div>

                    <div class="coach-analyzing" aria-hidden="true">
                        <i class="fas fa-wand-magic-sparkles"></i>
                        <span>Coach is analyzing</span>
                        <span class="dots"><span></span><span></span><span></span></span>
                    </div>

                    <div class="space-y-2.5">
                        <div class="coach-tip">
                            <span class="ic"><i class="fas fa-arrows-rotate"></i></span>
                            <div class="body">
                                <b>Swap your top block.</b> “Free Templates” CTR
                                <small>
                                    <span class="spark dn" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
                                    <span>−12% · last 7d</span>
                                </small>
                            </div>
                            <a href="#" class="cta">Try fix</a>
                        </div>
                        <div class="coach-tip">
                            <span class="ic"><i class="fas fa-star"></i></span>
                            <div class="body">
                                <b>Add social proof.</b> Pages with reviews convert
                                <small>
                                    <span class="spark up" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
                                    <span>1.7× higher</span>
                                </small>
                            </div>
                            <a href="#" class="cta">Add now</a>
                        </div>
                        <div class="coach-tip">
                            <span class="ic"><i class="fas fa-flask"></i></span>
                            <div class="body">
                                <b>A/B test the hero.</b> 2 variants, auto-pick winner
                                <small>
                                    <span class="spark up" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
                                    <span>+8% projected lift</span>
                                </small>
                            </div>
                            <a href="#" class="cta">Run test</a>
                        </div>
                        <div class="coach-tip">
                            <span class="ic"><i class="fas fa-chart-pie"></i></span>
                            <div class="body">
                                <b>Know your audience.</b> AI estimates visitor types
                                <small>
                                    <span class="spark up" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
                                    <span>62% professionals</span>
                                </small>
                            </div>
                            <a href="#" class="cta">See insights</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ==================== ZONE · DEEPER FEATURE SHOWCASE ==================== --}}
@include('home.partials.resume')
@include('home.partials.dialer-contacts')
@include('home.partials.forms')
@include('home.partials.notifications')
{{-- ============================ WORKSPACE & TEAM ============================ --}}
<section id="workspace-team" class="py-24 lg:py-32 relative overflow-hidden">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c1)">Workspace &amp; Team</div>
            <h2 class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                Run Sayzio with <span class="grad-text">your whole team.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">
                Multiple workspaces, real teammates with real roles, fine-grained permissions and per-workspace billing — with shared AI tools built for agencies, founders and busy creators.
            </p>
        </div>

        <div class="grid lg:grid-cols-2 gap-10 items-center">
            <div class="reveal rd-2">
                <div class="grid sm:grid-cols-2 gap-4 glass-ambient-wash">
                    @foreach([
                        ['fa-layer-group','#1bd4d9','Multiple workspaces','One per brand, client or side project — fully isolated.'],
                        ['fa-user-plus','#3d6bff','Invite teammates','Add members by email. They get their own login.'],
                        ['fa-user-shield','#e94e8c','Roles &amp; permissions','Owner, Admin, Editor, Viewer — locked down where it counts.'],
                        ['fa-credit-card','#ff8a3c','Billing per workspace','Separate plans &amp; invoices for each workspace.'],
                    ] as $i => $f)
                        <div class="reveal rd-{{ $i+1 }} glass rounded-2xl p-5 lift">
                            <div class="w-11 h-11 rounded-xl flex items-center justify-center mb-3" style="background: {{ $f[1] }}; box-shadow: 0 12px 30px -12px {{ $f[1] }};">
                                <i class="fas {{ $f[0] }} text-white"></i>
                            </div>
                            <h3 class="text-base font-bold mb-1">{!! $f[2] !!}</h3>
                            <p class="text-xs text-gray-400 leading-relaxed">{!! $f[3] !!}</p>
                        </div>
                    @endforeach
                </div>
                <div class="reveal rd-5 mt-8">
                    <a href="{{ route('site.workspace-team') }}" class="btn-bounce btn-glow inline-flex items-center justify-center gap-2 px-7 py-3.5 grad-bar text-white rounded-full text-sm font-bold">
                        Explore Workspace &amp; Team <i class="fas fa-arrow-right text-xs"></i>
                    </a>
                </div>
            </div>

            <div class="reveal rd-3">
                <div class="glass rounded-3xl p-6 sm:p-8 tilt relative overflow-hidden ws-card">
                    <div class="absolute -top-16 -right-16 w-56 h-56 rounded-full opacity-30" style="background:var(--c2);"></div>
                    <div class="absolute -bottom-20 -left-20 w-64 h-64 rounded-full opacity-20" style="background:var(--c2);"></div>

                    {{-- Live cursors floating across the panel --}}
                    <div class="ws-cursor" aria-hidden="true">
                        <svg viewBox="0 0 16 16" fill="none"><path d="M2 2 L14 8 L8 9.5 L6.5 14 Z" fill="#90acff" stroke="#fff" stroke-width="1"/></svg>
                        <span class="lbl">Jane</span>
                    </div>
                    <div class="ws-cursor c2" aria-hidden="true">
                        <svg viewBox="0 0 16 16" fill="none"><path d="M2 2 L14 8 L8 9.5 L6.5 14 Z" fill="#22d3ee" stroke="#fff" stroke-width="1"/></svg>
                        <span class="lbl">Marco</span>
                    </div>

                    <div class="relative">
                        {{-- Header --}}
                        <div class="flex items-center justify-between mb-5">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl grad-bar flex items-center justify-center text-white font-bold">A</div>
                                <div>
                                    <div class="text-sm font-bold">Acme Studio</div>
                                    <div class="text-[11px] text-gray-400 flex items-center gap-1.5">
                                        <span class="relative flex h-1.5 w-1.5">
                                            <span class="absolute inline-flex h-full w-full rounded-full opacity-75 animate-ping" style="background:#22c55e"></span>
                                            <span class="relative inline-flex rounded-full h-1.5 w-1.5" style="background:#22c55e"></span>
                                        </span>
                                        Pro workspace · 6 members · 5 online
                                    </div>
                                </div>
                            </div>
                            <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full" style="background:rgba(27,212,217,.15); color:var(--c1);">Active</span>
                        </div>

                        {{-- Live activity rows --}}
                        @php
                            $teammates = [
                                [
                                    'name' => 'Jane Doe',
                                    'role' => 'Owner',
                                    'avatar' => '/images/hero-roles/role_designer.jpg',
                                    'badge_class' => 'ws-b-edit',
                                    'badge' => 'Editing',
                                    'task_icon' => 'fa-pen',
                                    'task' => 'Spring Campaign page',
                                    'extra' => 'typing',
                                    'delay' => '0s',
                                ],
                                [
                                    'name' => 'Marco Perez',
                                    'role' => 'Admin',
                                    'avatar' => '/images/hero-roles/role_developer.jpg',
                                    'badge_class' => 'ws-b-up',
                                    'badge' => 'Uploading',
                                    'task_icon' => 'fa-cloud-arrow-up',
                                    'task' => '12 assets · Brand kit',
                                    'extra' => 'progress',
                                    'delay' => '.12s',
                                ],
                                [
                                    'name' => 'Aisha Khan',
                                    'role' => 'Editor',
                                    'avatar' => '/images/hero-roles/role_writer.jpg',
                                    'badge_class' => 'ws-b-comment',
                                    'badge' => 'Commenting',
                                    'task_icon' => 'fa-comment-dots',
                                    'task' => 'on “Hero copy v3”',
                                    'extra' => null,
                                    'delay' => '.24s',
                                ],
                                [
                                    'name' => 'Devon Smith',
                                    'role' => 'Editor',
                                    'avatar' => '/images/hero-roles/role_business.jpg',
                                    'badge_class' => 'ws-b-ok',
                                    'badge' => 'Approved',
                                    'task_icon' => 'fa-circle-check',
                                    'task' => 'Q1 Report · just now',
                                    'extra' => null,
                                    'delay' => '.36s',
                                ],
                                [
                                    'name' => 'Priya Nair',
                                    'role' => 'Viewer',
                                    'avatar' => '/images/hero-roles/role_photographer.jpg',
                                    'badge_class' => 'ws-b-view',
                                    'badge' => 'Viewing',
                                    'task_icon' => 'fa-chart-line',
                                    'task' => 'Analytics dashboard',
                                    'extra' => null,
                                    'delay' => '.48s',
                                ],
                            ];
                        @endphp
                        <div class="space-y-2.5">
                            @foreach($teammates as $m)
                                <div class="ws-row" style="animation-delay: {{ $m['delay'] }}">
                                    <div class="ws-avatar is-online">
                                        <img src="{{ $m['avatar'] }}" alt="{{ $m['name'] }}" loading="lazy">
                                    </div>
                                    <div class="ws-meta">
                                        <div class="ws-name">
                                            {{ $m['name'] }}
                                            <span class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">· {{ $m['role'] }}</span>
                                        </div>
                                        <div class="ws-task">
                                            <i class="fas {{ $m['task_icon'] }}"></i>
                                            <span class="truncate">{{ $m['task'] }}</span>
                                            @if($m['extra'] === 'typing')
                                                <span class="ws-typing" aria-hidden="true"><span></span><span></span><span></span></span>
                                            @endif
                                        </div>
                                        @if($m['extra'] === 'progress')
                                            <div class="ws-prog" aria-hidden="true"><div class="bar"></div></div>
                                        @endif
                                    </div>
                                    <span class="ws-badge {{ $m['badge_class'] }}">
                                        <span class="dot"></span>{{ $m['badge'] }}
                                    </span>
                                </div>
                            @endforeach
                        </div>

                        {{-- Footer: online stack + actions --}}
                        <div class="mt-5 flex items-center justify-between gap-3 flex-wrap">
                            <div class="flex items-center gap-3">
                                <div class="ws-online-stack" aria-label="Online now">
                                    @foreach($teammates as $m)
                                        <div class="av"><img src="{{ $m['avatar'] }}" alt="" loading="lazy"></div>
                                    @endforeach
                                </div>
                                <span class="text-[11px] text-gray-400">Collaborating live</span>
                            </div>
                            <div class="flex items-center gap-4 text-[11px] text-gray-400">
                                <span><i class="fas fa-arrow-right-arrow-left mr-1.5"></i>Switch</span>
                                <span><i class="fas fa-receipt mr-1.5"></i>Billing</span>
                            </div>
                        </div>
                    </div>
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
                <a href="#ai-marketing-strategist" class="ai-zone-chip"><i class="fas fa-chart-line"></i> AI Marketing Strategist</a>
                <a href="#whatsapp-agent" class="ai-zone-chip"><i class="fab fa-whatsapp"></i> WhatsApp Agent</a>
                <a href="#ai-dashboard" class="ai-zone-chip"><i class="fas fa-gauge-high"></i> AI Dashboard</a>
            </div>
        </div>
    </section>

    @include('home.partials.ai-hero')
    @include('home.partials.ai-suite')
    @include('home.partials.ai-marketing-strategist')
    @include('home.partials.whatsapp-agent')
    @include('home.partials.ai-dashboard')
</div>

{{-- ============================ BUZZ ============================ --}}
<section id="buzz" class="py-24 lg:py-32 relative overflow-hidden">
    <div class="absolute inset-0 -z-10" style="background:rgba(61,107,255,.06);"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14 max-w-3xl mx-auto">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c3)">Buzz</div>
            <h2 class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">
                Your AI grows it. <span class="grad-text">Buzz shows it.</span>
            </h2>
            <p class="reveal rd-2 text-lg text-gray-400">
                While your AI builds and tunes the page, Buzz keeps the momentum visible — live signups, visits and purchases pop up right on your Link in Bio so visitors see the room is busy and act.
            </p>
        </div>

        <div class="grid lg:grid-cols-2 gap-10 items-center">
            <div class="reveal rd-3 order-2 lg:order-1">
                <div class="relative glass rounded-3xl p-6 sm:p-8 tilt overflow-hidden" style="min-height: 360px;">
                    <div class="absolute -bottom-20 -left-20 w-64 h-64 rounded-full opacity-30" style="background:var(--c2);"></div>
                    <div class="relative">
                        <div class="flex items-center justify-between mb-4">
                            <div class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Live on your Link in Bio</div>
                            <span class="flex items-center gap-1.5 text-[10px] font-bold px-2.5 py-1 rounded-full" style="background:rgba(74,222,128,.15);color:#4ade80">
                                <span class="w-1.5 h-1.5 rounded-full pulse-dot" style="background:#4ade80"></span>7 events · last min
                            </span>
                        </div>

                        <div class="buzz-feed">
                            {{-- 1 · NEW FOLLOW with real avatar --}}
                            <div class="buzz-card fresh">
                                <span class="fresh-tag">✨ Just now</span>
                                <div class="bz-follow">
                                    <div class="bz-avatar">
                                        <img src="/images/hero-roles/role_designer-200.jpg" alt="Sara" loading="lazy" decoding="async" width="40" height="40">
                                        <span class="on" aria-hidden="true"></span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="name">Sara from Berlin</div>
                                        <div class="meta"><i class="fas fa-user-plus text-[9px] mr-1" style="color:var(--c1)"></i>just followed you · 12s ago</div>
                                    </div>
                                    <a href="#" class="btn">Follow back</a>
                                </div>
                            </div>

                            {{-- 2 · PURCHASE with product thumb + price --}}
                            <div class="buzz-card">
                                <div class="bz-buy">
                                    <div class="bz-thumb">
                                        <img src="/images/hero-roles/thumb_design-320.jpg" alt="Lightroom Pack" loading="lazy" decoding="async" width="64" height="64">
                                        <span class="tag">Preset</span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="product">🛒 Lightroom Pack · Vol II</div>
                                        <div class="who">bought by <b class="text-white">@nora.cph</b> · 42s ago</div>
                                    </div>
                                    <span class="price"><span class="d"></span>+$24.00</span>
                                </div>
                            </div>

                            {{-- 3 · LIVE VIEWERS with bar --}}
                            <div class="buzz-card">
                                <div class="bz-views">
                                    <div class="ic"><i class="fas fa-eye"></i></div>
                                    <div class="min-w-0 w-full">
                                        <div class="row">
                                            <span><b>🇳🇬 Lagos</b> &amp; 5 cities viewing now</span>
                                            <span class="num">+18</span>
                                        </div>
                                        <div class="track"><div class="fill"></div></div>
                                    </div>
                                </div>
                            </div>

                            {{-- 4 · TIP with spinning coin --}}
                            <div class="buzz-card">
                                <div class="bz-tip">
                                    <div class="bz-coin">$</div>
                                    <div class="min-w-0">
                                        <div class="who"><b>@yuki.draws</b> sent you a tip</div>
                                        <div class="msg">“Loved your latest pack — keep going!”</div>
                                    </div>
                                    <div class="amt">$5<small>.00</small></div>
                                </div>
                            </div>

                            {{-- 5 · FORM submission --}}
                            <div class="buzz-card">
                                <div class="bz-form">
                                    <div class="ic"><i class="fas fa-envelope-open-text"></i></div>
                                    <div class="min-w-0">
                                        <div class="who">Marco from Madrid · contact form</div>
                                        <div class="subj">“Hi! Available for a wedding shoot in June?”</div>
                                    </div>
                                    <span class="pri">High</span>
                                </div>
                            </div>

                            {{-- 6 · QR scan with sparkline --}}
                            <div class="buzz-card">
                                <div class="bz-qr">
                                    <div class="ic"><i class="fas fa-qrcode"></i></div>
                                    <div class="min-w-0">
                                        <div class="label">QR · Studio poster scanned</div>
                                        <div class="meta">+127 scans today · peak 4:20 pm</div>
                                    </div>
                                    <span class="spark" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
                                </div>
                            </div>

                            {{-- 7 · GOAL hit (full-width progress) --}}
                            <div class="buzz-card bz-goal">
                                <div class="top">
                                    <div class="trophy"><i class="fas fa-trophy text-sm"></i></div>
                                    <div class="title">🎉 Monthly goal hit · 1,000 followers</div>
                                    <div class="pct">100%</div>
                                </div>
                                <div class="track">
                                    <div class="fill"></div>
                                    <span class="conf" aria-hidden="true">🎊</span>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-4 text-[10px] text-gray-500 uppercase tracking-wider font-semibold">
                            <i class="fas fa-circle-down mr-1 opacity-60"></i> 12 more events today
                        </div>
                    </div>
                </div>
            </div>

            <div class="reveal rd-2 order-1 lg:order-2">
                <div class="grid sm:grid-cols-2 gap-4 glass-ambient-wash">
                    @foreach([
                        ['fa-bolt','#ffc845','Real-time activity','Live signups, visits, purchases &amp; form fills.'],
                        ['fa-toggle-on','#1bd4d9','Zero setup','Already integrated with your Link in Bio — flip it on.'],
                        ['fa-sliders','#e94e8c','Pick what shows','Choose events &amp; priorities; hide the rest.'],
                        ['fa-user-secret','#3d6bff','Privacy-first','Names masked, locations coarse, dismissible.'],
                    ] as $i => $f)
                        <div class="reveal rd-{{ $i+1 }} glass rounded-2xl p-5 lift">
                            <div class="w-11 h-11 rounded-xl flex items-center justify-center mb-3" style="background: {{ $f[1] }}; box-shadow: 0 12px 30px -12px {{ $f[1] }};">
                                <i class="fas {{ $f[0] }} text-white"></i>
                            </div>
                            <h3 class="text-base font-bold mb-1">{!! $f[2] !!}</h3>
                            <p class="text-xs text-gray-400 leading-relaxed">{!! $f[3] !!}</p>
                        </div>
                    @endforeach
                </div>
                <div class="reveal rd-5 mt-8">
                    <a href="{{ route('site.buzz') }}" class="btn-bounce btn-glow inline-flex items-center justify-center gap-2 px-7 py-3.5 grad-bar text-white rounded-full text-sm font-bold">
                        See how Buzz works <i class="fas fa-arrow-right text-xs"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ==================== ZONE · PROOF ==================== --}}
{{-- ============================ TESTIMONIAL MARQUEE ============================ --}}
<section id="proof" class="py-20 lg:py-24 relative overflow-hidden">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c5)">Social proof</div>
            <h2 class="reveal rd-1 text-3xl sm:text-4xl lg:text-5xl font-bold">Built with AI, <span class="grad-text">loved by creators.</span></h2>
        </div>
    </div>

    @php
        try {
            $__allReviews    = \App\Modules\Admin\Models\Testimonial::cachedActive();
            $__topReviews    = $__allReviews->where('row', 'top')->values();
            $__bottomReviews = $__allReviews->where('row', 'bottom')->values();
        } catch (\Throwable $e) {
            $__topReviews = collect();
            $__bottomReviews = collect();
        }
    @endphp

    @if($__topReviews->isNotEmpty())
        <div class="overflow-hidden mb-4" style="mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent);">
            <div class="flex whitespace-nowrap marquee">
                @for($i = 0; $i < 2; $i++)
                    @foreach($__topReviews as $r)
                        <div class="inline-block w-[340px] sm:w-[400px] mx-3 align-top">
                            <div class="glass rounded-3xl p-6 lift">
                                <div class="flex text-base mb-3" style="color:var(--c5)">
                                    @for($s = 0; $s < $r->rating; $s++)<i class="fas fa-star {{ $s ? 'ml-0.5' : '' }}"></i>@endfor
                                </div>
                                <p class="text-sm text-gray-200 mb-4 whitespace-normal">&ldquo;{{ $r->quote }}&rdquo;</p>
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold text-white" style="background: linear-gradient(135deg, {{ $r->accent_color }}, var(--c2));">{{ $r->initial() }}</div>
                                    <div>
                                        <div class="text-sm font-bold">{{ $r->author_name }}</div>
                                        <div class="text-[11px] text-gray-500">{{ $r->author_role }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endfor
            </div>
        </div>
    @endif

    @if($__bottomReviews->isNotEmpty())
        <div class="overflow-hidden" style="mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent);">
            <div class="flex whitespace-nowrap marquee-rev">
                @for($i = 0; $i < 2; $i++)
                    @foreach($__bottomReviews as $r)
                        <div class="inline-block w-[340px] sm:w-[400px] mx-3 align-top">
                            <div class="glass rounded-3xl p-6 lift">
                                <div class="flex text-base mb-3" style="color:var(--c5)">
                                    @for($s = 0; $s < $r->rating; $s++)<i class="fas fa-star {{ $s ? 'ml-0.5' : '' }}"></i>@endfor
                                </div>
                                <p class="text-sm text-gray-200 mb-4 whitespace-normal">&ldquo;{{ $r->quote }}&rdquo;</p>
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold text-white" style="background: linear-gradient(135deg, {{ $r->accent_color }}, var(--c2));">{{ $r->initial() }}</div>
                                    <div>
                                        <div class="text-sm font-bold">{{ $r->author_name }}</div>
                                        <div class="text-[11px] text-gray-500">{{ $r->author_role }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endfor
            </div>
        </div>
    @endif
</section>

{{-- ==================== ZONE · COMPARE & PRICING ==================== --}}
{{-- ============================ HOW WE COMPARE ============================ --}}
@include('public.partials._compare', ['teaser' => true, 'eyebrowOverride' => 'How we compare'])
@php
    // Legacy inline arrays kept commented out — replaced by shared partial above.
    /*
    $__cmpCompetitors = [
        ['key' => 'ours',     'name' => 'Sayzio',         'badge' => 'Better deal',           'isOurs' => true],
        ['key' => 'linktree', 'name' => 'Linktree',      'badge' => 'Half the cost',         'isOurs' => false],
        ['key' => 'bitly',    'name' => 'Bitly',         'badge' => 'More features included', 'isOurs' => false],
        ['key' => 'beacons',  'name' => 'Beacons',       'badge' => 'Up to 1/10th the price','isOurs' => false],
    ];
    // 10 features. Order chosen to front-load Sayzio-only wins.
    $__cmpFeatures = [
        ['Link in Bio pages',             ['ours' => true, 'linktree' => true,  'bitly' => true,  'beacons' => true]],
        ['Branded short links',       ['ours' => true, 'linktree' => false, 'bitly' => true,  'beacons' => false]],
        ['Dynamic QR codes',          ['ours' => true, 'linktree' => true,  'bitly' => true,  'beacons' => true]],
        ['Built-in analytics',        ['ours' => true, 'linktree' => true,  'bitly' => true,  'beacons' => true]],
        ['Live visitor map',          ['ours' => true, 'linktree' => false, 'bitly' => false, 'beacons' => false]],
        ['Performance coach',         ['ours' => true, 'linktree' => false, 'bitly' => false, 'beacons' => false]],
        ['Team workspaces',           ['ours' => true, 'linktree' => true,  'bitly' => true,  'beacons' => false]],
        ['Direct messaging',          ['ours' => true, 'linktree' => false, 'bitly' => false, 'beacons' => false]],
        ['Scheduled posts',           ['ours' => true, 'linktree' => false, 'bitly' => false, 'beacons' => true]],
        ['Custom domains',            ['ours' => true, 'linktree' => true,  'bitly' => true,  'beacons' => true]],
    ];
    */
@endphp
@if(false)
<section id="compare-legacy" class="py-20 lg:py-28 relative overflow-hidden">
    <div class="mesh-bg" aria-hidden="true"></div>
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12 max-w-2xl mx-auto">
            <div data-anim="fade-up" class="text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c4)">How we compare</div>
            <h2 data-anim="fade-up" class="text-4xl sm:text-5xl font-bold tracking-tight mb-4">
                More features. <span class="grad-text">Better deal.</span>
            </h2>
            <p data-anim="fade-up" class="text-gray-400">See how Sayzio stacks up against the link-in-bio tools you already know — most charge extra for AI; here it's built in and free.</p>
        </div>

        {{-- ===== Desktop / tablet matrix ===== --}}
        <div data-anim="fade-up" class="hidden md:block cmp-wrap">
            <div class="grad-border rounded-3xl overflow-hidden relative">
                {{-- Highlighted column band overlays the Sayzio column (col 2 of 5: feature col + 4 brand cols) --}}
                <div class="cmp-ours-band" style="left: 40%; width: calc(60% / 4);"></div>

                {{-- Header --}}
                <div class="grid items-center px-4 sm:px-6 py-5 bg-white/[.03] text-xs font-bold uppercase tracking-wider text-gray-400 relative z-[1]"
                     style="grid-template-columns: 40% repeat(4, 1fr);">
                    <div>Feature</div>
                    @foreach($__cmpCompetitors as $c)
                        <div class="text-center">
                            @if($c['isOurs'])
                                <span class="cmp-brand-ours text-xs">
                                    <i class="fas fa-bolt"></i> {{ $c['name'] }}
                                </span>
                            @else
                                <span class="text-gray-300 text-sm normal-case tracking-normal font-semibold">{{ $c['name'] }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Rows --}}
                <div class="cmp-stagger" data-anim="fade">
                    @foreach($__cmpFeatures as $row)
                        @php [$label, $support] = $row; @endphp
                        <div class="cmp-row grid items-center px-4 sm:px-6 py-4 border-t border-white/5 text-sm"
                             style="grid-template-columns: 40% repeat(4, 1fr);">
                            <div class="text-gray-200 font-medium">{{ $label }}</div>
                            @foreach($__cmpCompetitors as $c)
                                <div class="text-center">
                                    @if($support[$c['key']])
                                        <span class="cmp-mark {{ $c['isOurs'] ? 'cmp-mark-yes-ours' : 'cmp-mark-yes' }}" aria-label="Included">
                                            <svg class="cmp-draw" width="{{ $c['isOurs'] ? 18 : 14 }}" height="{{ $c['isOurs'] ? 18 : 14 }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <path d="M5 12.5l4.5 4.5L19 7"/>
                                            </svg>
                                        </span>
                                    @else
                                        <span class="cmp-mark cmp-mark-no" aria-label="Not included">
                                            <svg class="cmp-draw" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true">
                                                <path d="M6 12h12"/>
                                            </svg>
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach

                    {{-- Footer badges row --}}
                    <div class="cmp-row grid items-center px-4 sm:px-6 py-5 border-t border-white/10 bg-white/[.02]"
                         style="grid-template-columns: 40% repeat(4, 1fr);">
                        <div class="text-xs font-bold uppercase tracking-wider text-gray-400">The bottom line</div>
                        @foreach($__cmpCompetitors as $c)
                            <div class="text-center">
                                <span class="cmp-badge {{ $c['isOurs'] ? 'cmp-badge-ours' : '' }}">
                                    @if($c['isOurs'])<i class="fas fa-star text-[10px]"></i>@endif
                                    {{ $c['badge'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Mobile stacked cards ===== --}}
        <div class="md:hidden space-y-4" data-anim="fade">
            @foreach($__cmpCompetitors as $c)
                <div data-anim="fade-up" class="cmp-card {{ $c['isOurs'] ? 'cmp-card-ours' : '' }}">
                    <div class="flex items-center justify-between gap-3 mb-4">
                        <div class="flex items-center gap-2">
                            @if($c['isOurs'])
                                <span class="cmp-brand-ours text-xs"><i class="fas fa-bolt"></i> {{ $c['name'] }}</span>
                            @else
                                <span class="text-base font-bold text-gray-100">{{ $c['name'] }}</span>
                            @endif
                        </div>
                        <span class="cmp-badge {{ $c['isOurs'] ? 'cmp-badge-ours' : '' }}">
                            @if($c['isOurs'])<i class="fas fa-star text-[10px]"></i>@endif
                            {{ $c['badge'] }}
                        </span>
                    </div>
                    <ul class="cmp-stagger space-y-2.5" data-anim="fade">
                        @foreach($__cmpFeatures as $row)
                            @php [$label, $support] = $row; $on = $support[$c['key']]; @endphp
                            <li class="cmp-row flex items-center gap-3 text-sm">
                                @if($on)
                                    <span class="cmp-mark {{ $c['isOurs'] ? 'cmp-mark-yes-ours' : 'cmp-mark-yes' }}" style="width:24px;height:24px;">
                                        <svg class="cmp-draw" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M5 12.5l4.5 4.5L19 7"/>
                                        </svg>
                                    </span>
                                @else
                                    <span class="cmp-mark cmp-mark-no" style="width:24px;height:24px;">
                                        <svg class="cmp-draw" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true">
                                            <path d="M6 12h12"/>
                                        </svg>
                                    </span>
                                @endif
                                <span class="{{ $on ? 'text-gray-100' : 'text-gray-500 line-through' }}">{{ $label }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        <p data-anim="fade-up" class="text-center text-xs text-gray-500 mt-6">Comparison reflects publicly listed feature sets at the time of writing. We never quote a competitor's price.</p>
    </div>
</section>
@endif

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

{{-- ============================ FEATURED POSTS CAROUSEL ============================ --}}
@if(!empty($featuredBlogPosts) && $featuredBlogPosts->count())
<section id="blog-featured" class="pt-14 pb-12 lg:pt-16 lg:pb-14 relative overflow-hidden">
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-end justify-between mb-10 gap-6 flex-wrap">
            <div>
                <div class="reveal text-xs font-bold uppercase tracking-[.2em] mb-3" style="color:var(--c4)">From the blog</div>
                <h2 class="reveal rd-1 text-4xl sm:text-5xl font-bold tracking-tight mb-3">Featured <span class="grad-text">stories.</span></h2>
                <p class="reveal rd-2 text-gray-400 max-w-xl">AI playbooks, product news and creator deep-dives — fresh from the Sayzio team.</p>
            </div>
            <a href="{{ route('site.blogs.index') }}" class="hidden sm:inline-flex items-center gap-2 text-sm font-semibold text-blue-300 hover:text-blue-200 transition">
                Browse all posts
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
            </a>
        </div>

        {{-- Snap-scroll carousel on mobile, 3-up grid from md+. --}}
        <div class="-mx-4 sm:mx-0 px-4 sm:px-0 flex sm:grid sm:grid-cols-2 md:grid-cols-3 gap-6 glass-ambient-wash overflow-x-auto sm:overflow-visible snap-x snap-mandatory scroll-smooth pb-4 sm:pb-0 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
            @foreach($featuredBlogPosts as $post)
                <a href="{{ route('site.blogs.show', $post->slug) }}"
                   class="group shrink-0 w-[85%] sm:w-auto snap-start block bg-white/[0.03] border border-white/10 rounded-2xl overflow-hidden hover:border-blue-500/40 transition reveal rd-{{ $loop->iteration + 1 }}">
                    @if($post->cover_image)
                        <div class="aspect-[16/9] bg-white/5 overflow-hidden">
                            <img src="{{ \App\Support\PublicStorageUrl::resolve($post->cover_image) }}" alt="" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                        </div>
                    @else
                        <div class="aspect-[16/9]" style="background:rgba(61,107,255,.18);"></div>
                    @endif
                    <div class="p-6">
                        @if($post->category)
                            <span class="inline-block text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full mb-3" style="background: {{ $post->category->color ? $post->category->color . '22' : 'rgba(61,107,255,.15)' }}; color: {{ $post->category->color ?: '#90acff' }};">{{ $post->category->name }}</span>
                        @endif
                        <h3 class="text-lg font-semibold text-white group-hover:text-blue-200 transition">{{ $post->title }}</h3>
                        @if($post->excerpt)
                            <p class="mt-2 text-sm text-gray-400 line-clamp-3">{{ $post->excerpt }}</p>
                        @endif
                        <div class="mt-4 flex items-center gap-2 text-xs text-white/50">
                            <span>{{ optional($post->published_at)->format('M j, Y') }}</span>
                            <span>·</span>
                            <span>{{ $post->reading_time_min }} min read</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-8 sm:hidden text-center">
            <a href="{{ route('site.blogs.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-blue-300 hover:text-blue-200 transition">
                Browse all posts
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>
</section>
@endif
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
                    <a href="#features" class="btn-bounce inline-flex items-center justify-center gap-2 px-8 py-4 glass-2 text-white rounded-full text-base font-bold whitespace-nowrap">
                        See features
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
