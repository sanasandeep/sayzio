{{-- ============================ PRICING ============================ --}}
<section id="pricing" class="py-20 lg:py-24 relative overflow-hidden"
    x-data="{
        billing: 'monthly',
        trackMarketingEvent(target){
            const url = '{{ route('marketing-events.track') }}';
            const data = new FormData();
            data.append('source', 'landing_pricing_teaser');
            data.append('target', target);
            try {
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(url, data);
                } else {
                    fetch(url, { method: 'POST', body: data, keepalive: true, credentials: 'same-origin' });
                }
            } catch (e) { /* fire-and-forget */ }
        }
    }">
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12 max-w-3xl mx-auto">
            <div class="reveal inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[.2em] mb-3 px-3 py-1 rounded-full" style="color:var(--c1); background: rgba(124,58,237,0.10);">
                <span class="inline-block w-1.5 h-1.5 rounded-full" style="background:var(--c1)"></span>
                Pricing
            </div>
            <h2 class="reveal rd-1 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight mb-5">Simple, <span class="grad-text">transparent pricing.</span></h2>
            <p class="reveal rd-2 text-lg text-gray-400">Start free. Upgrade only when you outgrow it.</p>
        </div>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mb-8">
            @include('public.pricing._currency_badge', [
                'currency'       => $currency ?? 'USD',
                'currencySource' => $currencySource ?? \App\Services\PricingResolver::SOURCE_GEO,
                'user'           => $user ?? auth()->user(),
                'switchRoute'    => 'upgrade.public.switch-currency',
                'compact'        => true,
            ])

            {{-- Monthly / Annual billing toggle --}}
            <div class="inline-flex items-center gap-1 p-1 rounded-full glass border border-white/10" role="tablist" aria-label="Billing cadence">
                <button type="button" role="tab" :aria-selected="billing === 'monthly'" @click="billing = 'monthly'"
                        :class="billing === 'monthly' ? 'grad-bar text-white shadow-lg shadow-[#7c3aed]/30' : 'text-gray-300 hover:text-white'"
                        class="px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider transition-all">
                    Monthly
                </button>
                <button type="button" role="tab" :aria-selected="billing === 'annual'" @click="billing = 'annual'"
                        :class="billing === 'annual' ? 'grad-bar text-white shadow-lg shadow-[#7c3aed]/30' : 'text-gray-300 hover:text-white'"
                        class="px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider transition-all flex items-center gap-1.5">
                    Annual
                    <span class="px-1.5 py-0.5 rounded-full text-[9px] font-extrabold bg-emerald-400/20 text-emerald-300 border border-emerald-400/40">Save 2 months</span>
                </button>
            </div>
        </div>

        @php
            $freePlans = collect($plans)->filter(fn($p) => !empty($p['is_free']))->values();
            $paidPlans = collect($plans)->reject(fn($p) => !empty($p['is_free']))->values();
            $cheapestPaid = $paidPlans->sortBy(fn($p) => (int) ($p['monthly']['amount_minor'] ?? PHP_INT_MAX))->first();
            $premiumHighlights = [
                ['fa-infinity',          'Unlimited links & bio pages'],
                ['fa-chart-line',        'Advanced analytics & A/B tests'],
                ['fa-users',             'Team seats & roles'],
                ['fa-globe',             'Custom domains'],
                ['fa-robot',             'AI Coach + AI replies'],
                ['fa-shield-halved',     'Priority support'],
            ];
        @endphp
        <div class="grid md:grid-cols-2 gap-6 max-w-4xl mx-auto">
            @foreach($freePlans as $i => $plan)
                @php $featured = false; $f = $plan['features']; @endphp
                <div class="reveal rd-{{ $i + 1 }} lift group relative rounded-3xl p-8 transition-all duration-300 hover:-translate-y-1 glass hover:shadow-xl hover:shadow-[#7c3aed]/10 overflow-hidden" style="border: 1px solid rgba(255,255,255,0.08);">
                    {{-- Animated background blobs --}}
                    <div class="absolute -top-24 -right-24 w-72 h-72 rounded-full opacity-25 blur-3xl pointer-events-none" style="background: radial-gradient(circle, var(--c2), transparent 70%); animation: floatA 9s ease-in-out infinite;"></div>
                    <div class="absolute -bottom-24 -left-24 w-72 h-72 rounded-full opacity-20 blur-3xl pointer-events-none" style="background: radial-gradient(circle, var(--c4), transparent 70%); animation: floatB 11s ease-in-out infinite;"></div>
                    {{-- Sparkles --}}
                    <span class="free-spark" style="top:14%;left:82%; animation-delay:0s"></span>
                    <span class="free-spark" style="top:46%;left:6%;  animation-delay:1.4s"></span>
                    <span class="free-spark" style="top:70%;left:88%; animation-delay:.7s"></span>

                    <div class="relative">
                    <div class="text-xs font-bold uppercase tracking-wider mb-3 text-gray-400 flex items-center gap-2">
                        <span class="inline-flex w-5 h-5 rounded-full grad-bar items-center justify-center"><i class="fas fa-gift text-[8px] text-white"></i></span>
                        {{ $plan['name'] }}
                    </div>

                    @if($plan['is_free'])
                        <div class="mb-4 flex items-center gap-4 flex-wrap">
                            <div class="free-pill-wrap relative inline-flex">
                                {{-- Pulsing glow halo --}}
                                <span class="absolute -inset-2 rounded-3xl opacity-40 blur-xl pointer-events-none" style="background: linear-gradient(135deg, var(--c1), var(--c2), var(--c3), var(--c4)); animation: pulseDot 2.4s ease-in-out infinite;"></span>
                                {{-- The actual pill --}}
                                <span class="relative inline-flex items-center px-5 py-2 rounded-2xl text-3xl sm:text-4xl font-extrabold tracking-tight text-white" style="background: linear-gradient(135deg, var(--c2), var(--c3) 50%, var(--c4)); letter-spacing: 0.05em;">
                                    FREE
                                    <i class="fas fa-sparkles ml-1.5 text-xs" style="animation: wiggle 2s ease-in-out infinite;"></i>
                                </span>
                            </div>
                            <div class="leading-tight">
                                <div class="text-[10px] uppercase tracking-wider font-bold flex items-center gap-1.5" style="color:#4ade80">
                                    <span class="relative flex w-2 h-2"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-400"></span></span>
                                    Forever
                                </div>
                                <div class="text-[11px] text-gray-400 mt-0.5">No card. No expiry.</div>
                            </div>
                        </div>
                    @else
                        @php
                            // Annual = 10× monthly (i.e. 2 months free) per the
                            // FAQ promise. Pure UI estimate; checkout still
                            // controls the actual cadence.
                            $monthlyMinor = (int) ($plan['monthly']['amount_minor'] ?? 0);
                            $currencyCode = (string) ($plan['monthly']['currency'] ?? 'USD');
                            $annualEquivMonthlyMinor = (int) round($monthlyMinor * 10 / 12);
                            $annualTotalMinor = $monthlyMinor * 10;
                            $annualEquivPretty = \App\Services\PricingResolver::money($annualEquivMonthlyMinor, $currencyCode);
                            $annualTotalPretty = \App\Services\PricingResolver::money($annualTotalMinor, $currencyCode);
                        @endphp
                        <div class="text-[11px] uppercase tracking-wider font-semibold {{ $featured ? 'text-white/70' : 'text-gray-400' }} mb-1">Starts at</div>
                        <div x-show="billing === 'monthly'" class="text-5xl font-bold mb-1 text-white leading-none">
                            {{ $plan['monthly']['formatted'] }}<span class="text-lg font-medium {{ $featured ? 'text-white/60' : 'text-gray-500' }}">/mo</span>
                        </div>
                        <div x-show="billing === 'annual'" x-cloak class="mb-1 leading-none">
                            <div class="text-5xl font-bold text-white flex items-baseline gap-2">
                                {{ $annualEquivPretty }}<span class="text-lg font-medium {{ $featured ? 'text-white/60' : 'text-gray-500' }}">/mo</span>
                            </div>
                            <div class="mt-1.5 flex items-center gap-2 text-[11px] {{ $featured ? 'text-white/80' : 'text-gray-400' }}">
                                <span class="line-through opacity-70">{{ $plan['monthly']['formatted'] }}/mo</span>
                                <span class="px-1.5 py-0.5 rounded-full text-[9px] font-extrabold bg-emerald-400/25 text-emerald-200 border border-emerald-400/40">2 months free</span>
                            </div>
                            <div class="text-[11px] mt-1 {{ $featured ? 'text-white/70' : 'text-gray-500' }}">Billed yearly · {{ $annualTotalPretty }}/yr</div>
                        </div>
                        @if(!empty($plan['tax']))
                            @foreach($plan['tax']['tax_breakdown'] as $line)
                                <div class="text-[11px] {{ $featured ? 'text-white/70' : 'text-gray-500' }}">+ {{ $line['label'] }} {{ \App\Services\PricingResolver::money((int) $line['amount_minor'], $plan['monthly']['currency']) }}</div>
                            @endforeach
                            <div class="text-[11px] font-semibold {{ $featured ? 'text-white' : 'text-gray-300' }} mb-1">Total {{ \App\Services\PricingResolver::money((int) $plan['tax']['grand_total_minor'], $plan['monthly']['currency']) }}/mo</div>
                            @if(!empty($plan['tax']['reverse_charge_note']))
                                <div class="text-[10px] italic {{ $featured ? 'text-white/60' : 'text-gray-500' }} mb-1">{{ $plan['tax']['reverse_charge_note'] }}</div>
                            @endif
                        @else
                            <div class="text-[11px] {{ $featured ? 'text-white/60' : 'text-gray-500' }} mb-1">+ taxes as applicable (shown at checkout)</div>
                        @endif
                    @endif
                    @if(!$plan['is_free'] && !empty($plan['description']))
                        <div class="text-sm mb-5 text-gray-400">{{ $plan['description'] }}</div>
                    @endif

                    {{-- Feature blocks (richer than plain bullets) --}}
                    <div class="space-y-2 mb-5">
                        @foreach(['max_links' => ['fa-link', 'links'], 'max_biolinks' => ['fa-id-card', 'bio pages'], 'storage_limit_mb' => ['fa-database', 'MB storage'], 'contacts_max' => ['fa-address-book', 'contacts']] as $key => $meta)
                            @if(isset($f[$key]))
                                <div class="free-row flex items-center gap-3 p-2.5 rounded-xl bg-white/[.04] border border-white/5 hover:border-white/15 hover:bg-white/[.06] transition group/row">
                                    <span class="w-9 h-9 rounded-lg flex items-center justify-center grad-bar shrink-0 group-hover/row:scale-110 transition" style="box-shadow: 0 8px 20px -8px rgba(124,58,237,.6);">
                                        <i class="fas {{ $meta[0] }} text-white text-[12px]"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-bold text-white leading-tight">{{ (int) $f[$key] === -1 ? 'Unlimited' : number_format((int) $f[$key]) }} {{ $meta[1] }}</div>
                                        <div class="text-[10px] text-gray-500 uppercase tracking-wider">Included on Free</div>
                                    </div>
                                    <i class="fas fa-check text-xs" style="color:var(--c1)"></i>
                                </div>
                            @endif
                        @endforeach
                    </div>

                    {{-- 0% reassurance strip --}}
                    <div class="grid grid-cols-3 gap-2 mb-6 px-3 py-3 rounded-xl bg-emerald-500/[.06] border border-emerald-500/15">
                        @foreach([['0%', 'Card'], ['0%', 'Trial'], ['100%', 'Yours']] as $z)
                            <div class="text-center">
                                <div class="text-base font-extrabold text-emerald-300 leading-none">{{ $z[0] }}</div>
                                <div class="text-[9px] uppercase tracking-wider text-emerald-200/70 mt-0.5">{{ $z[1] }}</div>
                            </div>
                        @endforeach
                    </div>

                    <button type="button" @click="trackMarketingEvent('plan_free'); $dispatch('open-auth', { tab: 'register' })" class="btn-bounce block w-full py-3.5 text-center rounded-full text-sm font-bold transition-transform group-hover:scale-[1.02] grad-bar text-white">
                        Get started free <i class="fas fa-arrow-right text-xs ml-1"></i>
                    </button>
                    </div>{{-- /.relative --}}
                </div>
            @endforeach

            {{-- Premium promo card. Outer wrapper isolates the badge so the inner
                 link can use overflow-hidden for blob effects without clipping it. --}}
            <div class="relative reveal rd-2 md:scale-[1.03]">
                {{-- Floating badge --}}
                <div class="absolute -top-3.5 left-1/2 -translate-x-1/2 z-20 pointer-events-none">
                    <div class="px-4 py-1.5 bg-white text-[#7c3aed] text-[11px] font-extrabold rounded-full uppercase tracking-wider shadow-lg flex items-center gap-1.5" style="box-shadow: 0 8px 24px -8px rgba(124,58,237,.6), 0 0 0 4px rgba(255,255,255,.08);">
                        <i class="fas fa-crown text-[10px]" style="animation: wiggle 2.4s ease-in-out infinite; transform-origin: 50% 80%;"></i>
                        Premium
                    </div>
                </div>

                <a href="{{ route('site.pricing') }}"
                   @click="trackMarketingEvent('plan_paid')"
                   class="lift group relative block rounded-3xl p-8 pt-9 text-white shadow-2xl shadow-[#7c3aed]/40 hover:shadow-[#7c3aed]/60 transition-all duration-300 hover:-translate-y-1 overflow-hidden"
                   style="background: linear-gradient(150deg, var(--c2), var(--c3) 60%, var(--c4));">
                    {{-- Ambient blobs --}}
                    <div class="absolute -top-16 -right-16 w-56 h-56 rounded-full bg-white/15 blur-3xl pointer-events-none" style="animation: floatA 10s ease-in-out infinite;"></div>
                    <div class="absolute -bottom-16 -left-16 w-56 h-56 rounded-full bg-white/10 blur-3xl pointer-events-none" style="animation: floatB 12s ease-in-out infinite;"></div>
                    {{-- Diagonal shimmer sweep --}}
                    <div class="absolute inset-0 prem-shimmer pointer-events-none"></div>
                    {{-- Sparkles --}}
                    <span class="prem-spark" style="top:18%;left:88%; animation-delay:0s"></span>
                    <span class="prem-spark" style="top:50%;left:6%;  animation-delay:1.1s"></span>
                    <span class="prem-spark" style="top:78%;left:84%; animation-delay:.6s"></span>

                    <div class="relative">
                        <div class="text-xs font-bold uppercase tracking-wider text-white/80 mb-3">Premium features</div>
                        <h3 class="text-2xl sm:text-3xl font-extrabold leading-tight mb-2">
                            Built for serious <span class="relative inline-block">creators &amp; teams.<span class="absolute left-0 right-0 -bottom-1 h-[3px] rounded-full bg-white/40"></span></span>
                        </h3>
                        <p class="text-sm text-white/80 mb-6">Everything in Free, plus the tools you grow into.</p>

                        {{-- Each feature is its own animated block --}}
                        <div class="grid grid-cols-2 gap-2 mb-6">
                            @foreach($premiumHighlights as $hi => $h)
                                <div class="prem-feat relative rounded-xl p-3 bg-white/[.12] backdrop-blur-sm border border-white/15 hover:bg-white/[.22] hover:-translate-y-0.5 transition-all duration-200" style="animation-delay: {{ $hi * 90 }}ms">
                                    <span class="absolute top-1.5 right-2 text-[8px] font-bold text-white/45 tracking-wider">0{{ $hi + 1 }}</span>
                                    <div class="flex items-start gap-2">
                                        <span class="prem-feat-ico w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center shrink-0 transition">
                                            <i class="fas {{ $h[0] }} text-[13px]"></i>
                                        </span>
                                        <span class="text-[12px] font-semibold leading-snug pt-1">{{ $h[1] }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if($cheapestPaid)
                            <div class="flex items-center justify-between gap-3 mb-5 p-3 rounded-xl bg-white/15 backdrop-blur-sm border border-white/20">
                                <div class="leading-tight">
                                    <div class="text-[10px] uppercase tracking-wider font-bold text-white/70">Plans starting from</div>
                                    <div class="flex items-baseline gap-1">
                                        <span class="text-2xl font-extrabold">{{ $cheapestPaid['monthly']['formatted'] }}</span>
                                        <span class="text-xs text-white/70">/mo</span>
                                    </div>
                                </div>
                                <span class="text-[10px] px-2 py-1 rounded-full bg-emerald-300/30 text-emerald-50 font-bold uppercase tracking-wider whitespace-nowrap">Cancel anytime</span>
                            </div>
                        @endif

                        <span class="btn-bounce inline-flex items-center justify-center gap-2 w-full py-3.5 text-center rounded-full text-sm font-bold bg-white text-[#7c3aed] hover:bg-gray-100 transition-transform group-hover:scale-[1.02]">
                            Explore premium plans <i class="fas fa-arrow-right text-xs transition-transform group-hover:translate-x-1"></i>
                        </span>
                    </div>
                </a>
            </div>
        </div>

        {{-- Pricing trust strip — sits directly under the cards as a slim reassurance row --}}
        <div class="reveal mt-8 max-w-4xl mx-auto flex flex-wrap items-center justify-center gap-x-6 gap-y-3 text-xs sm:text-sm text-gray-300">
            @foreach([
                ['fa-shield-halved', 'Cancel any time'],
                ['fa-receipt', 'Tax-inclusive invoices'],
            ] as $t)
                <span class="inline-flex items-center gap-2"><i class="fas {{ $t[0] }} text-[11px]" style="color:var(--c1)"></i>{{ $t[1] }}</span>
            @endforeach
        </div>

        {{-- Slim "more pricing details" link row — replaces the previous oversized drill-down card. --}}
        <div class="reveal mt-6 max-w-4xl mx-auto flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-sm text-gray-400">
            <span class="text-gray-500">More pricing details:</span>
            <a href="{{ route('site.pricing') }}" @click="trackMarketingEvent('pricing')" class="inline-flex items-center gap-1.5 text-violet-300 hover:text-violet-200 font-semibold transition">
                <i class="fas fa-tags text-[11px]"></i> Compare all plans
            </a>
            <span class="text-gray-700">·</span>
            <a href="{{ route('site.pricing', ['view' => 'coins']) }}" @click="trackMarketingEvent('coins')" class="inline-flex items-center gap-1.5 text-violet-300 hover:text-violet-200 font-semibold transition">
                <i class="fas fa-coins text-[11px] text-amber-400"></i> Coin packages
            </a>
            <span class="text-gray-700">·</span>
            <a href="{{ route('site.premium-features') }}" @click="trackMarketingEvent('premium_features')" class="inline-flex items-center gap-1.5 text-violet-300 hover:text-violet-200 font-semibold transition">
                <i class="fas fa-star text-[11px] text-amber-300"></i> Premium features
            </a>
        </div>
    </div>
</section>

