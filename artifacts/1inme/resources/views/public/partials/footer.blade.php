<footer class="bg-[#08020f] text-white pt-16 pb-8 border-t border-white/5">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid md:grid-cols-6 gap-8 mb-12">
            <div class="md:col-span-2">
                <a href="{{ route('home') }}" class="inline-flex items-center" aria-label="Sayzio home">
                    @include('common.partials.brand-logo', ['height' => 'h-9'])
                </a>
                <p class="text-sm text-gray-500 mt-3 leading-relaxed max-w-sm">The all-in-one link platform: build a drag-and-drop Link in Bio, share it everywhere, and grow with live analytics and a built-in Performance Coach.</p>
                <div class="mt-5">
                    <div class="text-xs font-bold text-gray-300 uppercase tracking-wider mb-3">Get the app</div>
                    @include('public.partials.store-buttons')
                </div>
            </div>
            <div>
                <h4 class="text-xs font-bold text-gray-300 uppercase tracking-wider mb-4">Product</h4>
                <ul class="space-y-2.5">
                    <li><a href="{{ route('home') }}#features" class="text-sm text-gray-500 hover:text-white">Features</a></li>
                    <li><a href="{{ route('home') }}#how-it-works" class="text-sm text-gray-500 hover:text-white">How it works</a></li>
                    <li><a href="{{ route('site.workspace-team') }}" class="text-sm text-gray-500 hover:text-white">Workspace &amp; Team</a></li>
                    <li><a href="{{ route('site.ai-dashboard') }}" class="text-sm text-gray-500 hover:text-white">AI Dashboard</a></li>
                    <li><a href="{{ route('site.pricing') }}" class="text-sm text-gray-500 hover:text-white">Pricing</a></li>
                    <li><a href="{{ route('site.download') }}" class="text-sm text-gray-500 hover:text-white">Download browser</a></li>
                    <li><a href="{{ route('site.api-docs') }}" class="text-sm text-gray-500 hover:text-white">API</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-xs font-bold text-gray-300 uppercase tracking-wider mb-4">Solutions</h4>
                <ul class="space-y-2.5">
                    <li><a href="{{ route('site.services') }}" class="text-sm text-gray-500 hover:text-white">Use cases</a></li>
                    <li><a href="{{ route('site.discovery') }}" class="text-sm text-gray-500 hover:text-white">Discover creators</a></li>
                    <li><a href="{{ route('site.creators-feed') }}" class="text-sm text-gray-500 hover:text-white">Creators feed</a></li>
                    <li><a href="{{ route('site.buzz') }}" class="text-sm text-gray-500 hover:text-white">Buzz</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-xs font-bold text-gray-300 uppercase tracking-wider mb-4">Company</h4>
                <ul class="space-y-2.5">
                    <li><a href="{{ route('site.about') }}" class="text-sm text-gray-500 hover:text-white">About</a></li>
                    <li><a href="{{ route('site.contact') }}" class="text-sm text-gray-500 hover:text-white">Contact</a></li>
                    <li><a href="{{ route('site.faqs') }}" class="text-sm text-gray-500 hover:text-white">FAQs</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-xs font-bold text-gray-300 uppercase tracking-wider mb-4">Legal</h4>
                <ul class="space-y-2.5">
                    <li><a href="{{ route('site.terms') }}" class="text-sm text-gray-500 hover:text-white">Terms</a></li>
                    <li><a href="{{ route('site.privacy') }}" class="text-sm text-gray-500 hover:text-white">Privacy</a></li>
                    <li><a href="{{ route('site.refunds') }}" class="text-sm text-gray-500 hover:text-white">Refunds</a></li>
                    <li><a href="{{ route('site.cookies') }}" class="text-sm text-gray-500 hover:text-white">Cookies</a></li>
                    <li><a href="{{ route('site.gdpr') }}" class="text-sm text-gray-500 hover:text-white">GDPR</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-white/5 pt-6 pb-6">
            @include('common.partials.social-row')
        </div>
        <div class="border-t border-white/5 pt-5 pb-5">
            @include('common.partials.shortcut-hint')
        </div>
        <div class="border-t border-white/5 pt-6 flex flex-col sm:flex-row justify-between items-center gap-3">
            <p class="text-sm text-gray-600">&copy; {{ date('Y') }} Sayzio. All rights reserved.</p>
            {{-- Currency switch — footer is the single control point for public pages.
                 Dispatches a window event so any in-page pricing section reacts
                 instantly without a page reload. --}}
            @php
                [$__footerCur, $__footerSrc] = (function(){
                    try {
                        [$c, $s] = \App\Services\PricingResolver::resolve(auth()->user() ?: null);
                        return [$c, $s];
                    } catch (\Throwable $e) {
                        return ['USD', \App\Services\PricingResolver::SOURCE_GEO];
                    }
                })();
                $__footerLocked  = $__footerSrc === \App\Services\PricingResolver::SOURCE_USER_COUNTRY;
                $__footerAutodet = $__footerSrc === \App\Services\PricingResolver::SOURCE_GEO;
            @endphp
            <div x-data="{ currency: '{{ $__footerCur }}' }"
                 class="flex items-center gap-2 text-xs text-gray-600"
                 role="group" aria-label="Display currency">
                @if($__footerLocked)
                    {{-- Country-locked: show fixed currency + hint to change billing country --}}
                    <span class="text-gray-600">{{ $__footerCur === 'INR' ? '₹ INR' : '$ USD' }}</span>
                    <span class="text-white/10">·</span>
                    <span class="text-gray-600">
                        Set by your billing country:
                        <a href="{{ route('user.profile.edit') }}" class="text-gray-500 hover:text-white transition-colors">change</a>
                    </span>
                @else
                    <span class="text-gray-600 hidden sm:inline">Currency:</span>
                    <div class="inline-flex rounded-full border border-white/10 bg-white/[0.03] overflow-hidden" role="tablist" aria-label="Choose display currency">
                        <button type="button" role="tab"
                                :aria-selected="currency === 'USD'"
                                :class="currency === 'USD' ? 'bg-white/15 text-gray-200' : 'text-gray-500 hover:text-gray-300'"
                                @click="
                                    if (currency !== 'USD') {
                                        currency = 'USD';
                                        window.dispatchEvent(new CustomEvent('inme-currency', { detail: { c: 'USD' } }));
                                        try {
                                            const fd = new FormData();
                                            fd.append('currency', 'USD');
                                            fd.append('_token', document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '');
                                            fetch('{{ route('upgrade.public.switch-currency') }}', { method:'POST', body:fd, credentials:'same-origin', keepalive:true, headers:{'X-Requested-With':'XMLHttpRequest'} });
                                        } catch(e){}
                                    }
                                "
                                class="px-2.5 py-1 text-[11px] font-medium transition-colors motion-reduce:transition-none"
                                aria-label="Show prices in US dollars">$ USD</button>
                        <button type="button" role="tab"
                                :aria-selected="currency === 'INR'"
                                :class="currency === 'INR' ? 'bg-white/15 text-gray-200' : 'text-gray-500 hover:text-gray-300'"
                                @click="
                                    if (currency !== 'INR') {
                                        currency = 'INR';
                                        window.dispatchEvent(new CustomEvent('inme-currency', { detail: { c: 'INR' } }));
                                        try {
                                            const fd = new FormData();
                                            fd.append('currency', 'INR');
                                            fd.append('_token', document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '');
                                            fetch('{{ route('upgrade.public.switch-currency') }}', { method:'POST', body:fd, credentials:'same-origin', keepalive:true, headers:{'X-Requested-With':'XMLHttpRequest'} });
                                        } catch(e){}
                                    }
                                "
                                class="px-2.5 py-1 text-[11px] font-medium transition-colors motion-reduce:transition-none"
                                aria-label="Show prices in Indian rupees">₹ INR</button>
                    </div>
                    @if($__footerAutodet)
                        <span class="text-gray-700 hidden sm:inline" aria-live="polite">auto-detected; switch anytime</span>
                    @endif
                @endif
            </div>
            <div class="flex items-center gap-3 text-xs text-gray-600">
                @php
                    $__ccCfgHome = \App\Modules\Common\Support\CookieConsentConfig::shouldRender('site')
                        ? \App\Modules\Common\Support\CookieConsentConfig::get() : null;
                @endphp
                @if($__ccCfgHome)
                    @php
                        $__ccCopyHome = \App\Modules\Common\Support\CookieConsentConfig::copyFor($__ccCfgHome);
                        $__ccPolicyHome = $__ccCopyHome['policy_link_url'] ?? '/cookies';
                        $__ccReopenHome = $__ccCopyHome['reopen_link_label'] ?? 'Cookie preferences';
                    @endphp
                    <a href="{{ $__ccPolicyHome }}"
                       class="cc-footer-link text-gray-500 hover:text-white"
                       aria-label="{{ $__ccReopenHome }}"
                       onclick="if(window.openCookiePreferences){return window.openCookiePreferences(event);}">
                        {{ $__ccReopenHome }}
                    </a>
                    <span class="text-white/10">·</span>
                @endif
                <p>One Platform. Endless Conversations.</p>
            </div>
        </div>
    </div>
</footer>
