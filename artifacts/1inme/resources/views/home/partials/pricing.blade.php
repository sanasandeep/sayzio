{{-- ============================ PRICING ============================ --}}
@include('home.partials.pricing-style')
{{-- The pricing band, rebuilt against a Stripe reference.

     The shape of it: the colour lives in the ground rather than in the cards,
     the two plans are a light/dark pair rather than white against saturated
     blue, each card is a copy block over value cells separated by hairlines
     rather than a grid of bordered chips, and the three side errands are one
     floating pill instead of a button and two grey links in separate rows.

     Everything that decides WHAT is shown -- the plan collections, the
     currency switcher, the tax overlay, the annual maths, the "starting from"
     figure -- is unchanged from the previous version and still comes from the
     controller's cached payload. Only the presentation is new. --}}
<section id="pricing" class="pr-band sec-ground py-24 lg:py-32 relative overflow-hidden"
    @inme-currency.window="currency = $event.detail.c"
    x-data="{
        billing: 'monthly',
        currency: '{{ $currency ?? 'USD' }}',
        switchCurrency(c){
            if (this.currency === c) return;
            this.currency = c;
            /* Persist the choice (session + cookie + profile) in the
               background — the UI has already re-rendered, so we never block. */
            const url = '{{ route('upgrade.public.switch-currency') }}';
            const token = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';
            const data = new FormData();
            data.append('currency', c);
            data.append('_token', token);
            try {
                fetch(url, {
                    method: 'POST',
                    body: data,
                    credentials: 'same-origin',
                    keepalive: true,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
            } catch (e) { /* swallow — UX must not depend on persistence */ }
        },
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
    <div class="relative pr-wide mx-auto px-4 sm:px-6 lg:px-8">

        <div class="pr-head">
            <div class="reveal pr-eyebrow">Pricing</div>
            <h2 class="reveal rd-1 pr-title">Start free. Pay when it pays you back.</h2>
            <p class="reveal rd-2 pr-sub">Two plans on this page and the full grid on the next one. No card to begin, no expiry, and nothing you build is held hostage if you stop.</p>
        </div>

        <div class="reveal rd-2 flex justify-center mt-9 mb-12">
            <div class="pr-toggle" role="tablist" aria-label="Billing cadence">
                <button type="button" role="tab" :aria-selected="billing === 'monthly'" @click="billing = 'monthly'">Monthly</button>
                <button type="button" role="tab" :aria-selected="billing === 'annual'" @click="billing = 'annual'">
                    Annual <span class="pr-save">2 months free</span>
                </button>
            </div>
        </div>

        @php
            $freePlans = collect($plans)->filter(fn($p) => !empty($p['is_free']))->values();
            $paidPlans = collect($plans)->reject(fn($p) => !empty($p['is_free']))->values();
            // "Starting from" is the minimum across the WHOLE public
            // catalogue, which the controller computes and passes in.
            //
            // It cannot be derived from $plans: that collection holds two
            // cards by design, the free plan and the POPULAR one, so
            // sorting it for the cheapest paid entry just returned the
            // popular plan. The homepage was advertising Rs 1,389/mo as a
            // starting price on a catalogue that starts at Rs 167/mo.
            //
            // Falls back to the old behaviour only when the key is absent
            // (a cached payload written before this change).
            $cheapestPaid = $cheapestPaidPlan
                ?? $paidPlans->sortBy(fn($p) => (int) ($p['monthly']['amount_minor'] ?? PHP_INT_MAX))->first();
            $premiumHighlights = [
                ['fa-infinity',      'Unlimited links & Link in Bio pages'],
                ['fa-chart-line',    'Advanced analytics & A/B tests'],
                ['fa-users',         'Team seats & roles'],
                ['fa-globe',         'Custom domains'],
                ['fa-robot',         'AI Coach + AI replies'],
                ['fa-shield-halved', 'Priority support'],
            ];
        @endphp

        <div class="pr-pair">
            @foreach($freePlans as $i => $plan)
                @php $f = $plan['features']; @endphp
                <div class="reveal rd-{{ $i + 1 }} pr-card">
                    <div class="pr-name">{{ $plan['name'] }}</div>

                    <div class="pr-price">
                        Free<span class="per">forever</span>
                    </div>
                    <div class="pr-price-note">No card, no trial clock, no expiry.</div>
                    <p class="pr-blurb">Everything you need to put one link in your bio and find out what people actually do with it.</p>

                    {{-- The four numbers that are the free plan: what you get,
                         from the plan record rather than from a list here, so
                         an admin raising a limit changes this card too. --}}
                    <div class="pr-cells">
                        @foreach([
                            'max_links'        => ['fa-link', 'links'],
                            'max_biolinks'     => ['fa-id-card', 'Link in Bio pages'],
                            'storage_limit_mb' => ['fa-database', 'MB storage'],
                            'contacts_max'     => ['fa-address-book', 'contacts'],
                        ] as $key => $meta)
                            @if(isset($f[$key]))
                                @php
                                    $__n = (int) $f[$key];
                                    // "1 Link in Bio pages" is the kind of thing
                                    // a reader trusts a little less afterwards.
                                    $__label = ($__n === 1 && str_ends_with($meta[1], 's') && ! str_ends_with($meta[1], 'ss'))
                                        ? substr($meta[1], 0, -1)
                                        : $meta[1];
                                @endphp
                                <div class="pr-cell">
                                    <i class="fas {{ $meta[0] }}" aria-hidden="true"></i>
                                    <span><b>{{ $__n === -1 ? 'Unlimited' : number_format($__n) }}</b> {{ $__n === -1 ? $meta[1] : $__label }}</span>
                                </div>
                            @endif
                        @endforeach
                        <div class="pr-cell">
                            <i class="fas fa-chart-simple" aria-hidden="true"></i>
                            <span>Click and scan analytics</span>
                            <span class="pr-cell-note">Included</span>
                        </div>
                        <div class="pr-cell">
                            <i class="fas fa-qrcode" aria-hidden="true"></i>
                            <span>Dynamic QR codes</span>
                            <span class="pr-cell-note">Included</span>
                        </div>
                    </div>

                    <div class="pr-foot">
                        @guest
                            <button type="button" @click="trackMarketingEvent('plan_free'); $dispatch('open-auth', { tab: 'register' })" class="pr-cta">
                                Get started free <i class="fas fa-arrow-right text-xs"></i>
                            </button>
                        @else
                            {{-- Signed in: skip the signup modal, go to the dashboard. --}}
                            <a href="{{ route('user.dashboard') }}" class="pr-cta">
                                Go to your dashboard <i class="fas fa-arrow-right text-xs"></i>
                            </a>
                        @endguest
                        <div class="pr-note">Free forever · nothing to cancel</div>
                    </div>
                </div>
            @endforeach

            {{-- The premium card. Dark in both themes -- that pairing is the
                 whole point of the layout, and `card-lit` is the page's
                 existing class for a surface that does not turn white when the
                 page does. --}}
            <div class="reveal rd-2 pr-card pr-card--dark card-lit">
                <span class="pr-flag">Most popular</span>

                <div class="pr-name">Premium</div>

                @if($cheapestPaid)
                    @php
                        // Annual = 10x monthly (i.e. 2 months free) per the FAQ
                        // promise. Pure UI estimate; checkout still controls
                        // the actual cadence.
                        $monthlyMinor  = (int) ($cheapestPaid['monthly']['amount_minor'] ?? 0);
                        $currencyCode  = (string) ($cheapestPaid['monthly']['currency'] ?? 'USD');
                        $annualEquiv   = \App\Services\PricingResolver::money((int) round($monthlyMinor * 10 / 12), $currencyCode);
                        $annualTotal   = \App\Services\PricingResolver::money($monthlyMinor * 10, $currencyCode);
                    @endphp
                    <div class="pr-price" x-data='{ cheapest: @json($cheapestPaid['prices'] ?? []) }'>
                        <span x-show="billing === 'monthly'"
                              x-text="(cheapest[currency] && cheapest[currency].monthly && cheapest[currency].monthly.formatted) || '{{ $cheapestPaid['monthly']['formatted'] }}'">{{ $cheapestPaid['monthly']['formatted'] }}</span>
                        <span x-show="billing === 'annual'" x-cloak>{{ $annualEquiv }}</span>
                        <span class="per">/mo, from</span>
                    </div>
                    <div class="pr-price-note" x-show="billing === 'monthly'">
                        + taxes as applicable, shown at checkout.
                    </div>
                    <div class="pr-price-note" x-show="billing === 'annual'" x-cloak>
                        <span class="was">{{ $cheapestPaid['monthly']['formatted'] }}/mo</span>
                        billed yearly at {{ $annualTotal }}.
                    </div>
                @else
                    <div class="pr-price">Premium<span class="per">plans</span></div>
                @endif

                <p class="pr-blurb">Everything in Free, plus the tools you grow into. Six plans on the next page; this is where they start.</p>

                <div class="pr-cells">
                    @foreach($premiumHighlights as $h)
                        <div class="pr-cell">
                            <i class="fas {{ $h[0] }}" aria-hidden="true"></i>
                            <span>{{ $h[1] }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="pr-foot">
                    {{-- The card is dark and its text is forced white in light
                         mode; this button has a white ground of its own, so it
                         has to be excluded from that or it is white on white.
                         `surface-lit-keep` is the exclusion, and .pr-cta below
                         then sets the navy that matches the card rather than
                         the brand blue the older `card-lit-cta` escape forces. --}}
                    <a href="{{ route('site.pricing') }}" @click="trackMarketingEvent('plan_paid')" class="surface-lit-keep pr-cta">
                        Explore premium plans <i class="fas fa-arrow-right text-xs"></i>
                    </a>
                    <div class="pr-note">Cancel any time · keep everything you made</div>
                </div>
            </div>
        </div>

        {{-- The way out of this section is /pricing, and the two side errands
             ride alongside it rather than under it in their own grey row. --}}
        <div class="reveal pr-anchors">
            <a href="{{ route('site.pricing') }}" @click="trackMarketingEvent('pricing')">
                <i class="fas fa-tags" aria-hidden="true"></i> Compare every plan
            </a>
            <a href="{{ route('site.pricing', ['view' => 'coins']) }}" @click="trackMarketingEvent('coins')">
                <i class="fas fa-coins" aria-hidden="true"></i> Coin packages
            </a>
            <a href="{{ route('site.pricing') }}#custom-plan-request">
                <i class="fas fa-gem" aria-hidden="true"></i> Need a custom plan?
            </a>
        </div>

        <div class="reveal pr-trust">
            <span><i class="fas fa-shield-halved" aria-hidden="true"></i> Cancel any time</span>
            <span><i class="fas fa-receipt" aria-hidden="true"></i> Tax-inclusive invoices</span>
            <span><i class="fas fa-arrow-right-arrow-left" aria-hidden="true"></i> Export your data whenever</span>
        </div>
    </div>
</section>
