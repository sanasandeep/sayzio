{{-- "Growth" short design: keyword focus = link analytics, click tracking. --}}
@include('home.partials.seo-intro', [
    'eyebrow' => 'Link Analytics & Growth',
    'heading' => 'Track every click with real-time <span class="grad-text">link analytics</span>',
    'lead' => 'See clicks, countries, devices and referrers for your short links and bio link page. Click tracking, UTM insights and audience growth tools in one dashboard.',
    'chips' => [['Start tracking free', '/register'], ['Analytics features', '/features'], ['Pricing', '/pricing']],
])
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
