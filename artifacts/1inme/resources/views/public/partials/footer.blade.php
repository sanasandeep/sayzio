<footer class="border-t border-white/10 bg-[#161b26] mt-16">
    {{-- 3-way Subscribe block: Email newsletter, WhatsApp Channel, WhatsApp DM. --}}
    @include('public.partials.subscribe-block-footer')
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12 grid grid-cols-2 md:grid-cols-5 gap-8 text-sm justify-items-center text-center">
        <div class="col-span-2 md:col-span-1 flex flex-col items-center">
            @include('common.partials.brand-logo', ['height' => 'h-8'])
            <p class="mt-3 text-gray-500 text-xs leading-relaxed max-w-[16rem]">Your link, your page, your audience — all in one place.</p>
        </div>
        <div>
            <div class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">Product</div>
            <ul class="space-y-2 text-gray-400">
                <li><a href="{{ route('site.features') }}" class="hover:text-white">Features</a></li>
                <li><a href="{{ route('site.how-it-works') }}" class="hover:text-white">How it works</a></li>
                <li><a href="{{ route('site.workspace-team') }}" class="hover:text-white">Workspace &amp; Team</a></li>
                <li><a href="{{ route('site.pricing') }}" class="hover:text-white">Pricing</a></li>
                <li><a href="{{ route('site.pricing', ['view' => 'coins']) }}" class="hover:text-white">Coin packages</a></li>
                <li><a href="{{ route('site.premium-features') }}" class="hover:text-white">Premium features</a></li>
                <li><a href="{{ route('site.api-docs') }}" class="hover:text-white">API</a></li>
                <li><a href="{{ route('site.ai-chatbot') }}" class="hover:text-white">AI Chatbot</a></li>
                <li><a href="{{ route('site.ai-agent') }}" class="hover:text-white">AI Agent</a></li>
                <li><a href="{{ route('site.ai-widget') }}" class="hover:text-white">AI Widget</a></li>
                <li><a href="{{ route('site.ai-voice-assistant') }}" class="hover:text-white">AI Voice Assistant</a></li>
                <li><a href="{{ route('site.resume-builder') }}" class="hover:text-white">Résumé &amp; Portfolio</a></li>
            </ul>
        </div>
        <div>
            <div class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">Solutions</div>
            <ul class="space-y-2 text-gray-400">
                <li><a href="{{ route('site.services') }}" class="hover:text-white">Use cases</a></li>
                <li><a href="{{ route('site.discovery') }}" class="hover:text-white">Discover creators</a></li>
                <li><a href="{{ route('site.creators-feed') }}" class="hover:text-white">Creators feed</a></li>
                <li><a href="{{ route('site.buzz') }}" class="hover:text-white">Buzz</a></li>
            </ul>
        </div>
        <div>
            <div class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">Company</div>
            <ul class="space-y-2 text-gray-400">
                <li><a href="{{ route('site.about') }}" class="hover:text-white">About</a></li>
                <li><a href="{{ route('site.contact') }}" class="hover:text-white">Contact</a></li>
                <li><a href="{{ route('site.faqs') }}" class="hover:text-white">FAQs</a></li>
                <li><a href="{{ route('login.page') }}" class="hover:text-white">Login</a></li>
                <li><a href="{{ route('register.page') }}" class="hover:text-white">Register</a></li>
            </ul>
        </div>
        <div>
            <div class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">Legal</div>
            <ul class="space-y-2 text-gray-400">
                <li><a href="{{ route('site.terms') }}" class="hover:text-white">Terms &amp; Conditions</a></li>
                <li><a href="{{ route('site.privacy') }}" class="hover:text-white">Privacy</a></li>
                <li><a href="{{ route('site.refunds') }}" class="hover:text-white">Refunds</a></li>
                <li><a href="{{ route('site.cookies') }}" class="hover:text-white">Cookies</a></li>
                <li><a href="{{ route('site.gdpr') }}" class="hover:text-white">GDPR</a></li>
            </ul>
        </div>
    </div>
    @php
        $__socialNetworks = \App\Modules\Common\Support\SitePagesContent::socialNetworks();
        $__socialLinks = [];
        foreach ($__socialNetworks as $__sKey => $__sMeta) {
            $__sUrl = trim((string) \App\Modules\Admin\Models\AppSetting::get($__sKey, ''));
            if ($__sUrl !== '') {
                $__socialLinks[] = ['url' => $__sUrl, 'label' => $__sMeta['label'], 'icon' => $__sMeta['icon']];
            }
        }
    @endphp
    @if(!empty($__socialLinks))
        <div class="border-t border-white/5 py-5">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-wrap items-center justify-center gap-3">
                @foreach($__socialLinks as $__sLink)
                    <a href="{{ $__sLink['url'] }}"
                       target="_blank"
                       rel="noopener noreferrer nofollow"
                       aria-label="{{ $__sLink['label'] }}"
                       title="{{ $__sLink['label'] }}"
                       class="w-9 h-9 rounded-full flex items-center justify-center bg-white/5 border border-white/10 text-gray-300 hover:text-white hover:bg-white/10 transition">
                        <i class="fa-brands {{ $__sLink['icon'] }} text-sm"></i>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
    <div class="border-t border-white/5 py-4">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            @include('common.partials.shortcut-hint')
        </div>
    </div>
    <div class="border-t border-white/5 py-5 text-center text-xs text-gray-500">
        © {{ date('Y') }} {{ config('app.name', '1INME') }}. All rights reserved.
        @php
            $__ccCfg = \App\Modules\Common\Support\CookieConsentConfig::shouldRender('site')
                ? \App\Modules\Common\Support\CookieConsentConfig::get() : null;
        @endphp
        @if($__ccCfg)
            @php
                $__ccCopy = \App\Modules\Common\Support\CookieConsentConfig::copyFor($__ccCfg);
                $__ccPolicy = $__ccCopy['policy_link_url'] ?? '/cookies';
                $__ccReopen = $__ccCopy['reopen_link_label'] ?? 'Cookie preferences';
            @endphp
            <span class="mx-2 text-white/20">·</span>
            <a href="{{ $__ccPolicy }}"
               class="cc-footer-link text-gray-400 hover:text-white"
               aria-label="{{ $__ccReopen }}"
               onclick="if(window.openCookiePreferences){return window.openCookiePreferences(event);}">
                {{ $__ccReopen }}
            </a>
        @endif
    </div>
</footer>
