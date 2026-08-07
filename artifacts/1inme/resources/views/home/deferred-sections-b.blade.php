{{-- ==========================================================================
     Homepage Variant B ("Signal") — deferred below-the-fold fragment.
     Fetched by the loader in home-b.blade.php. Same data contract as the
     classic fragment ($plans, $currency, $linkTypes, …) but a compact,
     calmer layout: trust band, dense feature grid, a short how-it-works
     row, the shared pricing teaser, and one closing CTA.
     ========================================================================== --}}

{{-- ============================ TRUST BAND ============================ --}}
@include('public.partials.marketing-trust-band')

{{-- ============================ ZIO PITCH ============================ --}}
<section id="zio" class="py-14 lg:py-20">
    <div class="hb-shell">
        <div class="hb-panel p-7 sm:p-10 grid lg:grid-cols-[auto_1fr] gap-6 lg:gap-10 items-center">
            <div class="w-16 h-16 rounded-2xl grad-bar flex items-center justify-center text-white text-2xl shrink-0 mx-auto lg:mx-0">
                <i class="fas fa-robot" aria-hidden="true"></i>
            </div>
            <div class="text-center lg:text-left">
                <div class="hb-eyebrow mb-2">Meet Zio</div>
                <h2 class="text-2xl sm:text-3xl font-bold tracking-tight mb-3">
                    The AI that <span class="grad-text">works your link</span> while you work.
                </h2>
                <p class="text-sm sm:text-base text-gray-400 leading-relaxed max-w-3xl mx-auto lg:mx-0">
                    Zio builds your pages from a sentence, replies to visitors in your voice, answers your
                    calls with the built-in dialer, and coaches you with live analytics. One assistant across
                    every Sayzio tool — included on the free plan.
                </p>
            </div>
        </div>
    </div>
</section>

{{-- ============================ FEATURE GRID ============================ --}}
<section id="features" class="py-14 lg:py-20" aria-labelledby="features-h">
    <div class="hb-shell">
        <div class="max-w-2xl mb-10">
            <div class="reveal hb-eyebrow mb-2">Everything included</div>
            <h2 id="features-h" class="reveal text-3xl sm:text-4xl font-bold tracking-tight mb-3">
                One toolkit for <span class="grad-text">creators, brands &amp; networking pros.</span>
            </h2>
            <p class="reveal text-gray-400 text-sm sm:text-base">Build it with AI or drag-and-drop — then share it everywhere and watch it grow.</p>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach([
                ['fa-wand-magic-sparkles', 'AI page builder', 'Describe your page and Zio stacks the blocks: socials, shop, video, forms, tips.'],
                ['fa-link', 'Branded short links', 'Clean, trackable links on your own domain — with click analytics built in.'],
                ['fa-qrcode', 'Dynamic QR codes', 'Print once, retarget forever. Swap the destination without reprinting.'],
                ['fa-comment-dots', 'Zio answers visitors', 'Your page talks back: questions answered in your voice, 24/7.'],
                ['fa-phone', 'Built-in dialer', 'Calls, contacts and voicemail — Zio can even pick up for you.'],
                ['fa-chart-line', 'Live analytics &amp; coach', 'A real-time visitor map plus an AI coach that turns numbers into next steps.'],
            ] as $i => $f)
                <article class="reveal hb-panel p-5 flex flex-col">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4 text-white" style="background:#3d6bff">
                        <i class="fas {{ $f[0] }} text-sm" aria-hidden="true"></i>
                    </div>
                    <h3 class="text-base font-bold mb-1.5">{!! $f[1] !!}</h3>
                    <p class="text-sm text-gray-400 leading-relaxed">{!! $f[2] !!}</p>
                </article>
            @endforeach
        </div>
        <div class="mt-8 text-center">
            <a href="{{ route('site.features') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-blue-300 hover:text-blue-200">
                See every feature <i class="fas fa-arrow-right text-[10px]" aria-hidden="true"></i>
            </a>
        </div>
    </div>
</section>

{{-- ============================ HOW IT WORKS (compact) ============================ --}}
<section id="how-it-works" class="py-14 lg:py-20" aria-labelledby="hiw-h">
    <div class="hb-shell">
        <div class="max-w-2xl mb-10">
            <div class="reveal hb-eyebrow mb-2">How it works</div>
            <h2 id="hiw-h" class="reveal text-3xl sm:text-4xl font-bold tracking-tight">
                Live in <span class="grad-text">under 2 minutes.</span>
            </h2>
        </div>
        <ol class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach([
                ['1', 'Sign up free', 'Email or one-tap Google. Pick your handle.'],
                ['2', 'Build with AI', 'Zio drafts your page; drag blocks to tweak it.'],
                ['3', 'Share everywhere', 'One link, short links and a dynamic QR.'],
                ['4', 'Watch it grow', 'Live analytics + an AI coach with next steps.'],
            ] as $s)
                <li class="reveal hb-panel p-5">
                    <div class="text-2xl font-bold mb-2" style="color:#7ea0ff">{{ $s[0] }}</div>
                    <h3 class="text-base font-bold mb-1">{{ $s[1] }}</h3>
                    <p class="text-sm text-gray-400 leading-relaxed">{{ $s[2] }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>

{{-- ============================ PRICING TEASER (shared partial) ============================ --}}
@include('home.partials.pricing')

{{-- ============================ FINAL CTA ============================ --}}
<section id="cta-final" class="py-14 lg:py-20">
    <div class="hb-shell">
        <div class="reveal hb-panel p-8 sm:p-12 text-center relative overflow-hidden">
            <div class="absolute -top-24 -right-20 w-72 h-72 rounded-full opacity-20 blur-3xl" style="background: var(--c2);" aria-hidden="true"></div>
            <div class="relative">
                <div class="hb-eyebrow mb-3">Ready when you are</div>
                <h2 class="text-3xl sm:text-4xl font-bold leading-tight mb-4">
                    Your audience is <span class="grad-text">already searching for you.</span>
                </h2>
                <p class="text-sm sm:text-base text-gray-400 max-w-xl mx-auto mb-7">
                    Let Zio build the page. Share the link. Watch them show up — live on a map.
                </p>
                <div class="flex flex-wrap justify-center gap-3">
                    <button type="button"
                            onclick="window.trackMarketingEvent && window.trackMarketingEvent('landing_home_cta','final_cta_b'); window.dispatchEvent(new CustomEvent('open-auth',{detail:{tab:'register'}}))"
                            class="btn-bounce inline-flex items-center gap-2 px-8 py-4 grad-bar text-white rounded-full text-base font-bold">
                        Sign up free <i class="fas fa-arrow-right text-xs" aria-hidden="true"></i>
                    </button>
                    <a href="{{ route('site.pricing') }}"
                       class="btn-bounce inline-flex items-center gap-2 px-8 py-4 hb-panel text-white rounded-full text-base font-bold">
                        See pricing
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
