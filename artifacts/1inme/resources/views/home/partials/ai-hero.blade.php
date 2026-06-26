{{--
    AI builder spotlight — a lighter SUPPORTING section (not a hero). The page is
    led by the Zio orbital hero (home.partials.hero); this band zooms in on the
    one AI capability that hero only mentions in passing: the prompt → full page
    builder, shown with a live "Generating" demo. Demoted from a full-height hero
    to avoid two stacked heroes at the top of the page.

    Reuses the homepage's existing design system only (glass, reveal, rd-*,
    grad-text, grad-bar, btn-bounce, btn-glow, float-*, --c1..--c5) so dark/light
    modes, animations and reduced-motion handling all carry over for free. CTA
    keeps the same open-auth + trackMarketingEvent pattern and the #ai-suite anchor.
--}}
<section class="relative py-16 lg:py-24 overflow-hidden" aria-labelledby="ai-hero-h">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-10 xl:px-12">
        <div class="grid grid-cols-1 gap-y-12 lg:grid-cols-[1.05fr_1fr] lg:gap-x-12 xl:gap-x-16 lg:items-center">
            {{-- Copy column --}}
            <div class="text-center lg:text-left lg:max-w-[600px]">
                <div class="reveal inline-flex items-center gap-2 px-4 py-1.5 glass rounded-full text-xs font-semibold mb-6">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full rounded-full" style="background:var(--c2)"></span>
                        <span class="ring-pulse" style="inset:0;background:var(--c2);"></span>
                    </span>
                    <span class="grad-text">AI builder · page in seconds</span>
                </div>

                <h2 id="ai-hero-h" class="reveal rd-1 text-3xl sm:text-4xl lg:text-5xl font-bold leading-[1.1] tracking-tight mb-6">
                    <span>Your AI builds </span>
                    <span class="relative inline-block">
                        <span class="grad-text">the whole page.</span>
                        <svg class="absolute -bottom-3 left-0 w-full" height="14" viewBox="0 0 220 14" preserveAspectRatio="none" aria-hidden="true">
                            <path class="draw-line" d="M2 9 Q 60 2, 110 8 T 218 6" stroke="url(#ai-hero-underline)" stroke-width="5" fill="none" stroke-linecap="round"/>
                            <defs><linearGradient id="ai-hero-underline"><stop offset="0%" stop-color="#3d6bff"/><stop offset="100%" stop-color="#1bd4d9"/></linearGradient></defs>
                        </svg>
                    </span>
                </h2>

                <p class="reveal rd-2 text-lg sm:text-xl text-gray-400 max-w-xl mx-auto lg:mx-0 mb-8 leading-relaxed">
                    Describe your idea in a sentence and <strong class="text-white">your AI</strong> builds the whole Link in Bio — it writes the copy, picks a matching theme and lays out every block in seconds. Everything stays <strong class="text-white">fully editable</strong>, so you tweak any block and publish the moment it looks right. No templates to wrestle, no design skills needed.
                </p>

                <div class="reveal rd-3 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-5 justify-center lg:justify-start">
                    <button type="button" onclick="window.trackMarketingEvent && window.trackMarketingEvent('landing_home_cta','ai_hero'); window.dispatchEvent(new CustomEvent('open-auth',{detail:{tab:'register'}}))" class="btn-bounce btn-glow inline-flex items-center justify-center gap-2 px-8 py-4 grad-bar text-white rounded-full text-base font-bold whitespace-nowrap shrink-0">
                        Build mine with AI <i class="fas fa-arrow-right text-sm"></i>
                    </button>
                    <a href="#ai-suite" class="inline-flex items-center justify-center gap-1.5 text-sm font-semibold text-gray-300 hover:text-white">
                        See the AI in action <i class="fas fa-arrow-right text-[11px]"></i>
                    </a>
                </div>

                <div class="reveal rd-4 flex flex-wrap items-center gap-x-6 gap-y-3 mt-10 justify-center lg:justify-start text-sm">
                    <span class="flex items-center gap-2 text-gray-400">
                        <i class="fas fa-keyboard text-[13px]" style="color:var(--c2)"></i>
                        <span class="font-bold text-white">One prompt</span>
                        <span class="text-gray-500">to a full page</span>
                    </span>
                    <span class="flex items-center gap-2 text-gray-400">
                        <i class="fas fa-palette text-[13px]" style="color:var(--c1)"></i>
                        <span class="font-bold text-white">Theme &amp; copy</span>
                        <span class="text-gray-500">auto-matched</span>
                    </span>
                    <span class="flex items-center gap-2 text-gray-400">
                        <i class="fas fa-sliders text-[13px]" style="color:var(--c5)"></i>
                        <span class="font-bold text-white">Every block</span>
                        <span class="text-gray-500">fully editable</span>
                    </span>
                </div>
            </div>

            {{-- Visual column: prompt → biolink blocks fly in and assemble a page --}}
            <div class="reveal rd-2 relative w-full max-w-[480px] mx-auto lg:justify-self-end">
                <div class="float-c">
                    <div class="glass rounded-3xl p-4 sm:p-5 relative overflow-hidden border border-white/10">
                        <div class="absolute -top-16 -right-16 w-48 h-48 rounded-full opacity-25" style="background:var(--c2)"></div>
                        <div class="absolute -bottom-20 -left-16 w-52 h-52 rounded-full opacity-20" style="background:var(--c1)"></div>

                        {{-- Prompt bar (the trigger) --}}
                        <div class="relative">
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-[11px] font-bold uppercase tracking-[.2em]" style="color:var(--c2)">
                                    <i class="fas fa-wand-magic-sparkles"></i> AI builder
                                </div>
                                {{-- Generating → built beat --}}
                                <div class="aih-status">
                                    <span class="aih-status-gen inline-flex items-center gap-1.5 px-3 py-1 grad-bar text-white rounded-full text-[11px] font-bold"><i class="fas fa-bolt"></i> Generating</span>
                                    <span class="aih-status-built inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold" style="background:#1ed76022;color:#1ed760"><i class="fas fa-check"></i> Page built</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 rounded-2xl bg-white/[.05] border border-white/10 px-4 py-3">
                                <i class="fas fa-keyboard text-sm text-gray-400 shrink-0"></i>
                                <span class="text-[13px] sm:text-sm text-gray-200">"A link page for my coffee brand with shop, menu &amp; reviews"</span>
                            </div>
                        </div>

                        {{-- Assembly stage: each block flies in from a different direction and snaps into place --}}
                        <div class="aih-stage relative mt-4 rounded-2xl bg-[#0a0a14] border border-white/5 p-3.5 sm:p-4">
                            <div class="aih-page">
                                {{-- Profile block (in from top) --}}
                                <div class="aih-block aih-profile" style="--d:60ms;--tx:-22px;--ty:-120px;--rot:-5deg;">
                                    <img src="{{ asset('images/marketing/ai-hero/avatar.webp') }}" alt="" loading="lazy" decoding="async" class="aih-avatar">
                                    <div class="aih-prof-meta">
                                        <div class="aih-name">Daybreak Coffee <i class="fas fa-circle-check aih-verified" aria-hidden="true"></i></div>
                                        <div class="aih-tag">Roasted fresh · shipped daily</div>
                                    </div>
                                </div>

                                {{-- Link cards (in from alternating sides) --}}
                                <div class="aih-block aih-link" style="--d:220ms;--tx:-180px;--ty:8px;--rot:-7deg;">
                                    <span class="aih-link-ico" style="background:color-mix(in srgb,var(--c2) 16%,transparent);color:var(--c2)"><i class="fas fa-store"></i></span>
                                    <span class="aih-link-label">Shop the beans</span>
                                    <i class="fas fa-arrow-right aih-link-arrow" aria-hidden="true"></i>
                                </div>
                                <div class="aih-block aih-link" style="--d:340ms;--tx:185px;--ty:8px;--rot:7deg;">
                                    <span class="aih-link-ico" style="background:color-mix(in srgb,var(--c1) 16%,transparent);color:var(--c1)"><i class="fas fa-book-open"></i></span>
                                    <span class="aih-link-label">See the menu</span>
                                    <i class="fas fa-arrow-right aih-link-arrow" aria-hidden="true"></i>
                                </div>
                                <div class="aih-block aih-link" style="--d:460ms;--tx:-170px;--ty:8px;--rot:-5deg;">
                                    <span class="aih-link-ico" style="background:color-mix(in srgb,var(--c5) 18%,transparent);color:var(--c5)"><i class="fas fa-star"></i></span>
                                    <span class="aih-link-label">Read reviews</span>
                                    <span class="aih-link-rating"><i class="fas fa-star"></i> 4.9</span>
                                </div>

                                {{-- Projects / gallery block (in from bottom) --}}
                                <div class="aih-block aih-gallery" style="--d:580ms;--tx:0;--ty:140px;--rot:3deg;">
                                    <img src="{{ asset('images/marketing/ai-hero/gallery-latte.webp') }}" alt="" loading="lazy" decoding="async" class="aih-shot">
                                    <img src="{{ asset('images/marketing/ai-hero/gallery-beans.webp') }}" alt="" loading="lazy" decoding="async" class="aih-shot">
                                    <img src="{{ asset('images/marketing/ai-hero/gallery-pastry.webp') }}" alt="" loading="lazy" decoding="async" class="aih-shot">
                                </div>

                                {{-- Contacts row (in from right) --}}
                                <div class="aih-block aih-contacts" style="--d:700ms;--tx:175px;--ty:24px;--rot:6deg;">
                                    <span class="aih-contact"><i class="fas fa-phone"></i> Call</span>
                                    <span class="aih-contact"><i class="fas fa-envelope"></i> Email</span>
                                    <span class="aih-contact"><i class="fas fa-location-dot"></i> Visit</span>
                                </div>

                                {{-- Social icons row (in from bottom) --}}
                                <div class="aih-block aih-socials" style="--d:820ms;--tx:0;--ty:110px;--rot:-4deg;">
                                    <span class="aih-soc"><i class="fab fa-instagram"></i></span>
                                    <span class="aih-soc"><i class="fab fa-tiktok"></i></span>
                                    <span class="aih-soc"><i class="fab fa-x-twitter"></i></span>
                                    <span class="aih-soc"><i class="fab fa-youtube"></i></span>
                                    <span class="aih-soc"><i class="fab fa-spotify"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Floating "page built" chip (desktop only) --}}
                    <div class="float-b hidden lg:block absolute -right-5 -bottom-4 glass rounded-2xl px-4 py-3 border border-white/10" style="animation-delay:-2s">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl flex items-center justify-center grad-bar"><i class="fas fa-check text-white text-xs"></i></div>
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-wider text-gray-400">AI builder</div>
                                <div class="text-xs font-bold text-white">Page built in 18s</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* ===== AI hero — biolink blocks fly in and assemble a page ===== */
        /* Master cycle: all blocks share the same duration so they loop in
           sync; per-block --d staggers the entry into a cascade, and --tx/--ty
           /--rot set each block's scattered start offset. */
        .aih-stage { overflow: hidden; }
        .aih-page { display: flex; flex-direction: column; gap: 9px; }

        .aih-block {
            opacity: 0;
            transform-origin: center;
            will-change: transform, opacity;
            animation: aihAssemble 9s cubic-bezier(.34, 1.56, .64, 1) infinite;
            animation-delay: var(--d, 0ms);
        }
        @keyframes aihAssemble {
            0%   { opacity: 0; transform: translate(var(--tx, 0), var(--ty, 36px)) rotate(var(--rot, 0deg)) scale(.86); }
            14%  { opacity: 1; transform: translate(0, 0) rotate(0) scale(1); }
            86%  { opacity: 1; transform: translate(0, 0) rotate(0) scale(1); }
            100% { opacity: 0; transform: translate(var(--tx, 0), var(--ty, 36px)) rotate(var(--rot, 0deg)) scale(.86); }
        }

        /* Profile block */
        .aih-profile {
            display: flex; align-items: center; gap: 11px;
            padding: 11px 12px; border-radius: 16px;
            background: linear-gradient(135deg, rgba(255,255,255,.12), rgba(255,255,255,.03));
            border: 1px solid rgba(255,255,255,.10);
        }
        .aih-avatar {
            width: 46px; height: 46px; border-radius: 50%;
            object-fit: cover; flex-shrink: 0;
            border: 2px solid rgba(255,255,255,.30);
        }
        .aih-prof-meta { min-width: 0; }
        .aih-name {
            font-size: 14px; font-weight: 700; color: #fff; line-height: 1.15;
            display: flex; align-items: center; gap: 5px;
        }
        .aih-verified { color: var(--c2); font-size: 12px; }
        .aih-tag { font-size: 11px; color: rgba(255,255,255,.62); margin-top: 2px; }

        /* Link cards */
        .aih-link {
            display: flex; align-items: center; gap: 11px;
            padding: 9px 11px; border-radius: 13px;
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.07);
        }
        .aih-link-ico {
            width: 30px; height: 30px; border-radius: 9px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 12px;
        }
        .aih-link-label { font-size: 13px; font-weight: 600; color: #fff; flex: 1; min-width: 0; }
        .aih-link-arrow { color: rgba(255,255,255,.40); font-size: 11px; }
        .aih-link-rating { font-size: 11px; font-weight: 700; color: var(--c5); white-space: nowrap; }
        .aih-link-rating i { font-size: 9px; margin-right: 1px; }

        /* Projects / gallery block */
        .aih-gallery { display: grid; grid-template-columns: repeat(3, 1fr); gap: 7px; }
        .aih-shot {
            width: 100%; aspect-ratio: 1 / 1; object-fit: cover;
            border-radius: 11px; border: 1px solid rgba(255,255,255,.08);
        }

        /* Contacts row */
        .aih-contacts { display: flex; gap: 7px; }
        .aih-contact {
            flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 5px;
            padding: 7px 4px; border-radius: 11px;
            background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.07);
            font-size: 11px; font-weight: 600; color: rgba(255,255,255,.85);
        }
        .aih-contact i { color: var(--c1); font-size: 11px; }

        /* Social icons row */
        .aih-socials { display: flex; gap: 9px; justify-content: center; padding-top: 1px; }
        .aih-soc {
            width: 30px; height: 30px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.08);
            color: rgba(255,255,255,.82); font-size: 13px;
        }

        /* Generating → built status crossfade (synced to the 9s cycle) */
        .aih-status { position: relative; display: inline-grid; }
        .aih-status-gen, .aih-status-built { grid-area: 1 / 1; }
        .aih-status-gen   { animation: aihStatusGen   9s ease-in-out infinite; }
        .aih-status-built { animation: aihStatusBuilt 9s ease-in-out infinite; opacity: 0; }
        @keyframes aihStatusGen   { 0%, 5% { opacity: 1; } 17%, 100% { opacity: 0; } }
        @keyframes aihStatusBuilt { 0%, 13% { opacity: 0; } 21%, 93% { opacity: 1; } 100% { opacity: 0; } }

        /* Small phones: tighten spacing + scale type so the page stays inside
           the column, legible and free of horizontal overflow. */
        @media (max-width: 420px) {
            .aih-page { gap: 7px; }
            .aih-avatar { width: 40px; height: 40px; }
            .aih-name { font-size: 13px; }
            .aih-link { padding: 8px 10px; }
            .aih-link-label { font-size: 12px; }
            .aih-gallery { gap: 6px; }
            .aih-contact { font-size: 10px; }
            .aih-soc { width: 27px; height: 27px; font-size: 12px; }
        }

        /* Reduced motion: skip the fly-in entirely; show the assembled page
           and the "Page built" state immediately. */
        @media (prefers-reduced-motion: reduce) {
            .aih-block {
                animation: none !important;
                opacity: 1 !important;
                transform: none !important;
            }
            .aih-status-gen { display: none; }
            .aih-status-built { animation: none !important; opacity: 1 !important; }
        }
    </style>
</section>
