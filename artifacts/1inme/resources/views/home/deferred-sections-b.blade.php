{{-- Compact home design: the classic page's best sections, trimmed & combined.
     Keeps all animations/images. Drops: marquee, audience, Share/Domains, deeper
     showcases, workspace, extra AI demos, Buzz, compare matrix, blog carousel. --}}
{{-- ============================ "1IN.ME is Sayzio" BRAND SECTION ============================ --}}
@include('home.partials.brand-sayzio')
{{-- ============================ CREDIBILITY BAND (near-hero trust numbers) ============================ --}}
@include('public.partials.marketing-trust-band')

{{-- ============================ WHAT YOU CAN CREATE (LINK TYPES) ============================ --}}
@include('home.partials.create-showcase')


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
</div>

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
                    <a href="#features" class="btn-bounce inline-flex items-center justify-center gap-2 px-8 py-4 glass-2 text-white rounded-full text-base font-bold whitespace-nowrap">
                        See features
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
