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

            {{-- Visual column: prompt → AI-generated page --}}
            <div class="reveal rd-2 relative w-full max-w-[520px] mx-auto lg:justify-self-end">
                <div class="float-c aibd" id="ai-builder-demo">
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
                                <span class="text-sm text-gray-200">"<span class="aibd-prompt">A link page for my coffee brand with shop, menu &amp; reviews</span><span class="aibd-caret" aria-hidden="true">▍</span>"</span>
                            </div>
                            <div class="flex items-center justify-end mt-3">
                                <span class="aibd-status px-4 py-2 grad-bar text-white rounded-full text-xs font-bold" aria-live="polite">
                                    <span class="aibd-status-gen inline-flex items-center gap-2" aria-hidden="true"><i class="fas fa-bolt"></i> Generating</span>
                                    <span class="aibd-status-done inline-flex items-center gap-2"><i class="fas fa-check"></i> Built</span>
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
                                    <div class="aibd-block flex items-center gap-3 rounded-xl bg-white/[.04] border border-white/5 px-3 py-2.5">
                                        <span class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background:{{ $g[2] }}1f;color:{{ $g[2] }}"><i class="fas {{ $g[0] }} text-xs"></i></span>
                                        <span class="text-[13px] font-semibold text-white">{{ $g[1] }}</span>
                                        <i class="fas fa-check ml-auto text-[10px]" style="color:#1ed760"></i>
                                    </div>
                                @endforeach
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
</section>

{{--
    AI builder demo animation — on scroll-into-view the prompt sentence types
    out, the "Generating" pill cross-fades to "Built" and the three result
    blocks reveal one-by-one. Pure opacity/transform (GPU-cheap), runs once.
    The resting CSS state IS the finished state, so no-JS and
    prefers-reduced-motion users see the page already "built" (frozen final).
    The start/animated state only applies once JS adds `.aibd-armed`, which it
    never does under reduced motion.
--}}
<style>
    .aibd .aibd-status { display: inline-grid; }
    .aibd .aibd-status > span { grid-area: 1 / 1; transition: opacity .4s ease; }
    /* Resting / final state (also no-JS + reduced motion): "Built", no caret, blocks shown */
    .aibd .aibd-status-gen { opacity: 0; }
    .aibd .aibd-status-done { opacity: 1; }
    .aibd .aibd-caret { opacity: 0; color: var(--c2); font-weight: 400; }
    .aibd .aibd-block { transition: opacity .55s ease, transform .55s cubic-bezier(.16,1,.3,1); }

    @media (prefers-reduced-motion: no-preference) {
        /* Start state — only active while JS drives the sequence forward */
        .aibd.aibd-armed .aibd-status-gen { opacity: 1; }
        .aibd.aibd-armed .aibd-status-done { opacity: 0; }
        .aibd.aibd-armed .aibd-caret { opacity: 1; animation: aibdCaret 1.05s step-end infinite; }
        .aibd.aibd-armed .aibd-block { opacity: 0; transform: translateY(12px); }

        /* Status flip: Generating -> Built */
        .aibd.aibd-armed.aibd-built .aibd-status-gen { opacity: 0; }
        .aibd.aibd-armed.aibd-built .aibd-status-done { opacity: 1; }
        .aibd.aibd-armed.aibd-built .aibd-caret { opacity: 0; animation: none; }

        /* Sequential block reveal (JS adds .is-in per block) */
        .aibd.aibd-armed .aibd-block.is-in { opacity: 1; transform: none; }
    }
    @keyframes aibdCaret { 0%, 49% { opacity: 1; } 50%, 100% { opacity: 0; } }
</style>
<script>
(function () {
    if (window.__aibdInit) return;
    window.__aibdInit = true;

    function init() {
        var root = document.getElementById('ai-builder-demo');
        if (!root) return;

        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reduce) return; // leave at resting/final state

        var promptEl = root.querySelector('.aibd-prompt');
        var blocks = root.querySelectorAll('.aibd-block');
        var genEl = root.querySelector('.aibd-status-gen');
        var doneEl = root.querySelector('.aibd-status-done');
        var fullText = promptEl ? promptEl.textContent.trim() : '';

        function exposeStatus(building) {
            if (genEl) genEl.setAttribute('aria-hidden', building ? 'false' : 'true');
            if (doneEl) doneEl.setAttribute('aria-hidden', building ? 'true' : 'false');
        }

        // Arm immediately (before paint where possible) to avoid a flash of the
        // finished state, then clear the prompt ready to type.
        root.classList.add('aibd-armed');
        exposeStatus(true); // start state shows "Generating"
        if (promptEl) promptEl.textContent = '';

        var played = false;
        function play() {
            if (played) return;
            played = true;

            var i = 0;
            function type() {
                if (promptEl && i <= fullText.length) {
                    promptEl.textContent = fullText.slice(0, i);
                    i++;
                    setTimeout(type, 26 + Math.random() * 36);
                } else {
                    setTimeout(finish, 380);
                }
            }
            function finish() {
                root.classList.add('aibd-built'); // flip Generating -> Built
                exposeStatus(false); // now announce "Built"
                blocks.forEach(function (b, idx) {
                    setTimeout(function () { b.classList.add('is-in'); }, 260 + idx * 170);
                });
            }
            type();
        }

        if ('IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    if (e.isIntersecting) { play(); io.disconnect(); }
                });
            }, { threshold: 0.35 });
            io.observe(root);
        } else {
            play();
        }
    }

    if (document.readyState !== 'loading') init();
    else document.addEventListener('DOMContentLoaded', init);
})();
</script>
