{{-- "Business" short design: keyword focus = digital business card, business page. --}}
@include('home.partials.seo-intro', [
    'anchorId' => 'ai-zone',
    'eyebrow' => 'For Small Business',
    'heading' => 'Your <span class="grad-text">digital business card</span> and mini-site in minutes',
    'lead' => 'Create a digital business card, lead capture forms and a business landing page on your own custom domain, with an AI receptionist that answers customers for you.',
    'chips' => [['Digital business card', '/register', 'fas fa-id-card'], ['Custom domains', '/features', 'fas fa-globe'], ['Lead forms', '/features', 'fas fa-inbox']],
    'floats' => ['fas fa-briefcase', 'fas fa-id-card', 'fas fa-phone', 'fas fa-envelope-open-text'],
])
@include('home.partials.audience-benefits', [
    'title' => 'Look bigger. <span class="grad-text">Miss nothing.</span>',
    'sub' => 'For shops, clinics, agencies, freelancers and every business that lives on referrals.',
    'items' => [
        ['fas fa-id-card', 'A card they can\'t lose', 'Your digital business card lives at one link: tap, save, call. Update your number once and everyone has the new one.'],
        ['fas fa-store', 'A mini-site in minutes', 'Services, opening hours, location, reviews and booking, on your own domain, without paying for a website project.'],
        ['fas fa-inbox', 'Never miss a lead', 'Enquiry and booking forms go straight to your inbox and dashboard, so weekend visitors become Monday customers.'],
        ['fas fa-phone-volume', 'Calls answered for you', 'The AI receptionist picks up when you are with a customer, takes the message and lets you call back.'],
        ['fas fa-qrcode', 'One QR on everything', 'Put one QR code on your storefront, van, packaging and cards. Change where it points anytime, reprint nothing.'],
        ['fas fa-user-group', 'Your whole team, one place', 'Give staff their own cards and pages under one business workspace, with one bill and one brand.'],
    ],
    'pills' => ['Own domain', 'Lead forms', 'Google-review link', 'Works without an app'],
])
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


@include('home.partials.resume')
@include('home.partials.dialer-contacts')
@include('home.partials.forms')

@include('home.partials.pricing')

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
