{{-- "Creators" short design: keyword focus = link in bio for creators, bio link page. --}}
@include('home.partials.seo-intro', [
    'anchorId' => 'ai-zone',
    'eyebrow' => 'Link in Bio for Creators',
    'heading' => 'One <span class="grad-text">bio link page</span> for everything you create',
    'lead' => 'Build a link in bio page for Instagram, TikTok and YouTube: sell products, collect subscribers and share every link from one creator page, free to start.',
    'chips' => [['Create your bio link', '/register', 'fas fa-at'], ['Sell products', '/features', 'fas fa-bag-shopping'], ['Grow subscribers', '/features', 'fas fa-users']],
    'floats' => ['fab fa-instagram', 'fab fa-tiktok', 'fab fa-youtube', 'fas fa-heart'],
])
@include('home.partials.audience-benefits', [
    'title' => 'Built for the way <span class="grad-text">creators</span> actually work',
    'sub' => 'Your bio link is your storefront. Make it earn its place.',
    'items' => [
        ['fas fa-link', 'One link, every platform', 'Instagram, TikTok, YouTube, X: keep one link in every bio and update what is behind it anytime, without touching your profiles.'],
        ['fas fa-bag-shopping', 'Sell without a store', 'Sell digital products, take tips and offer paid pages right from your bio link. No separate shop, no code.'],
        ['fas fa-users', 'Own your audience', 'Turn followers into subscribers you can actually reach: collect emails and WhatsApp subscribers from your page.'],
        ['fas fa-clapperboard', 'Show your latest work', 'Feature your newest video, drop or post front and center, so fans always land on your freshest content.'],
        ['fas fa-chart-simple', 'Know what converts', 'See which links your fans tap, where they come from and what they ignore, so you post more of what works.'],
        ['fas fa-gem', 'Look premium everywhere', 'Designer templates and smooth animations that look expensive on every phone, without hiring a designer.'],
    ],
    'pills' => ['Free to start', 'Custom themes', 'Gated content for fans', 'QR code for events'],
])
{{-- ============================ CREDIBILITY BAND (near-hero trust numbers) ============================ --}}
@include('public.partials.marketing-trust-band')

{{-- ============================ WHAT YOU CAN CREATE (LINK TYPES) ============================ --}}
@include('home.partials.create-showcase')



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
                    <a href="/features" class="btn-bounce inline-flex items-center justify-center gap-2 px-8 py-4 glass-2 text-white rounded-full text-base font-bold whitespace-nowrap">
                        See features
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
