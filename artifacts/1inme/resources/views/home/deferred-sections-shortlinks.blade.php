{{-- "Short links" design: keyword focus = URL shortener, QR code generator. --}}
@include('home.partials.seo-intro', [
    'anchorId' => 'ai-zone',
    'eyebrow' => 'URL Shortener & QR Codes',
    'heading' => 'Free <span class="grad-text">URL shortener</span> with branded links & QR code generator',
    'lead' => 'Shorten long URLs into branded short links on your own domain, generate QR codes, and track every click with real-time link analytics.',
    'chips' => [['Shorten a link', '/register'], ['QR code generator', '/register'], ['Pricing', '/pricing']],
])
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
