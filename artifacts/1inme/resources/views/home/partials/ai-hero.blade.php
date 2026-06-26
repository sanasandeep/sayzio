{{--
    AI-first lead hero — sits at the very top of the homepage, above the classic
    "I am a [role]" hero. Frames Sayzio around its built-in AI: type a prompt and
    the AI builds the page, writes the copy, answers visitors and coaches growth.

    Reuses the homepage's existing design system only (glass, reveal, rd-*,
    grad-text, grad-bar, btn-bounce, btn-glow, float-*, --c1..--c5) so dark/light
    modes, animations and reduced-motion handling all carry over for free. CTAs
    follow the same open-auth + trackMarketingEvent pattern as the classic hero.
--}}
<section class="relative pt-28 pb-16 lg:pt-44 lg:pb-20 xl:pt-52 overflow-hidden" aria-labelledby="ai-hero-h">
    {{-- Drifting confetti (matches the classic hero's playful motion) --}}
    <div class="confetti drift-a" style="left:12%; bottom:-22vh;"><div class="w-3 h-3 rounded-sm" style="background:var(--c2)"></div></div>
    <div class="confetti drift-b" style="left:84%; bottom:-28vh; animation-delay:-5s"><div class="w-2 h-6 rounded-full" style="background:var(--c1)"></div></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-10 xl:px-12">
        <div class="grid grid-cols-1 gap-y-12 lg:grid-cols-[1.05fr_1fr] lg:gap-x-12 xl:gap-x-16 lg:items-center">
            {{-- Copy column --}}
            <div class="text-center lg:text-left lg:max-w-[600px]">
                <div class="reveal inline-flex items-center gap-2 px-4 py-1.5 glass rounded-full text-xs font-semibold mb-8">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full rounded-full" style="background:var(--c2)"></span>
                        <span class="ring-pulse" style="inset:0;background:var(--c2);"></span>
                    </span>
                    <span class="grad-text">AI-first link platform · Builder · Coach · Chatbot · Voice</span>
                </div>

                <h1 id="ai-hero-h" class="reveal rd-1 text-5xl sm:text-6xl lg:text-7xl font-bold leading-[1.05] tracking-tight mb-8">
                    <span class="block">Your AI builds</span>
                    <span class="relative inline-block">
                        <span class="grad-text">the whole page.</span>
                        <svg class="absolute -bottom-3 left-0 w-full" height="14" viewBox="0 0 220 14" preserveAspectRatio="none" aria-hidden="true">
                            <path class="draw-line" d="M2 9 Q 60 2, 110 8 T 218 6" stroke="url(#ai-hero-underline)" stroke-width="5" fill="none" stroke-linecap="round"/>
                            <defs><linearGradient id="ai-hero-underline"><stop offset="0%" stop-color="#3d6bff"/><stop offset="100%" stop-color="#1bd4d9"/></linearGradient></defs>
                        </svg>
                    </span>
                </h1>

                <p class="reveal rd-2 text-lg sm:text-xl text-gray-400 max-w-xl mx-auto lg:mx-0 mb-10 leading-relaxed">
                    Describe your idea and <strong class="text-white">your AI</strong> builds the Link in Bio, writes the copy, picks the theme and lays out every block. Then it keeps working — an <strong class="text-white">AI chatbot</strong> answering visitors, a <strong class="text-white">voice assistant</strong> taking calls and a <strong class="text-white">Performance Coach</strong> turning your numbers into next steps — <strong class="text-white">free forever</strong>, no card required.
                </p>

                <div class="reveal rd-3 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-5 justify-center lg:justify-start">
                    <button type="button" onclick="window.trackMarketingEvent && window.trackMarketingEvent('landing_home_cta','ai_hero'); window.dispatchEvent(new CustomEvent('open-auth',{detail:{tab:'register'}}))" class="btn-bounce btn-glow inline-flex items-center justify-center gap-2 px-8 py-4 grad-bar text-white rounded-full text-base font-bold whitespace-nowrap shrink-0">
                        Build mine with AI <i class="fas fa-arrow-right text-sm"></i>
                    </button>
                    <a href="#ai-suite" class="inline-flex items-center justify-center gap-1.5 text-sm font-semibold text-gray-300 hover:text-white">
                        See the AI in action <i class="fas fa-arrow-right text-[11px]"></i>
                    </a>
                </div>

                <div class="reveal rd-4 flex flex-wrap items-center gap-x-6 gap-y-3 mt-12 justify-center lg:justify-start text-sm">
                    <span class="flex items-center gap-2 text-gray-400">
                        <i class="fas fa-wand-magic-sparkles text-[13px]" style="color:var(--c2)"></i>
                        <span class="font-bold text-white">AI builder</span>
                        <span class="text-gray-500">page in seconds</span>
                    </span>
                    <span class="flex items-center gap-2 text-gray-400">
                        <i class="fas fa-comments text-[13px]" style="color:var(--c1)"></i>
                        <span class="font-bold text-white">AI chatbot</span>
                        <span class="text-gray-500">always on</span>
                    </span>
                    <span class="flex items-center gap-2 text-gray-400">
                        <i class="fas fa-bolt text-[13px]" style="color:var(--c5)"></i>
                        <span class="font-bold text-white">AI coach</span>
                        <span class="text-gray-500">grows you</span>
                    </span>
                </div>
            </div>

            {{-- Visual column: prompt → AI-generated page --}}
            <div class="reveal rd-2 relative w-full max-w-[520px] mx-auto lg:justify-self-end">
                <div class="float-c">
                    <div class="glass rounded-3xl p-5 sm:p-6 relative overflow-hidden border border-white/10">
                        <div class="absolute -top-16 -right-16 w-48 h-48 rounded-full opacity-25" style="background:var(--c2)"></div>
                        <div class="absolute -bottom-20 -left-16 w-52 h-52 rounded-full opacity-20" style="background:var(--c1)"></div>

                        {{-- Prompt bar --}}
                        <div class="relative">
                            <div class="text-[11px] font-bold uppercase tracking-[.2em] mb-2" style="color:var(--c2)">
                                <i class="fas fa-wand-magic-sparkles"></i> AI builder
                            </div>
                            <div class="flex items-center gap-3 rounded-2xl bg-white/[.05] border border-white/10 px-4 py-3">
                                <i class="fas fa-keyboard text-sm text-gray-400"></i>
                                <span class="text-sm text-gray-200">"A link page for my coffee brand with shop, menu &amp; reviews"</span>
                            </div>
                            <div class="flex items-center justify-end mt-3">
                                <span class="inline-flex items-center gap-2 px-4 py-2 grad-bar text-white rounded-full text-xs font-bold">
                                    <i class="fas fa-bolt"></i> Generating
                                </span>
                            </div>
                        </div>

                        {{-- Generated blocks preview --}}
                        <div class="relative mt-5 rounded-2xl bg-[#0a0a14] border border-white/5 p-4">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-11 h-11 rounded-2xl flex items-center justify-center grad-bar shrink-0"><i class="fas fa-mug-hot text-white"></i></div>
                                <div class="min-w-0">
                                    <div class="text-sm font-bold text-white leading-tight">Daybreak Coffee</div>
                                    <div class="text-[11px] text-gray-400">Roasted fresh, shipped daily</div>
                                </div>
                            </div>
                            <div class="space-y-2.5">
                                @foreach([
                                    ['fa-store','Shop the beans','var(--c2)'],
                                    ['fa-book-open','See the menu','var(--c1)'],
                                    ['fa-star','Read reviews','var(--c5)'],
                                ] as $g)
                                    <div class="flex items-center gap-3 rounded-xl bg-white/[.04] border border-white/5 px-3 py-2.5">
                                        <span class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background:{{ $g[2] }}1f;color:{{ $g[2] }}"><i class="fas {{ $g[0] }} text-xs"></i></span>
                                        <span class="text-[13px] font-semibold text-white">{{ $g[1] }}</span>
                                        <i class="fas fa-check ml-auto text-[10px]" style="color:#1ed760"></i>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Floating AI coach chip (desktop only) --}}
                    <div class="float-b hidden lg:block absolute -right-5 -bottom-4 glass rounded-2xl px-4 py-3 border border-white/10" style="animation-delay:-2s">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl flex items-center justify-center grad-bar"><i class="fas fa-bolt text-white text-xs"></i></div>
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-wider text-gray-400">AI Coach</div>
                                <div class="text-xs font-bold text-white">Add a QR — reach +18%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
