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
