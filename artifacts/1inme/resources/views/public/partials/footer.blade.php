<footer class="border-t border-white/10 bg-[#161b26] mt-16">
    {{-- 3-way Subscribe block: Email newsletter, WhatsApp Channel, WhatsApp DM. --}}
    <div id="subscribe">
        @include('public.partials.subscribe-block-footer')
    </div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14 text-sm">
        {{-- Brand block (full width above the link grid) --}}
        <div class="mb-10 max-w-md">
            @include('common.partials.brand-logo', ['height' => 'h-8'])
            <p class="mt-3 text-gray-500 text-xs leading-relaxed">Your link, your page, your audience — all in one place. Build, share &amp; grow with the all-in-one creator stack.</p>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-8">
        {{-- Product --}}
        <div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-gray-300 mb-4">Product</div>
            <ul class="space-y-2.5 text-gray-400">
                <li><a href="{{ route('site.features') }}" class="hover:text-white transition-colors">Features</a></li>
                <li><a href="{{ route('site.how-it-works') }}" class="hover:text-white transition-colors">How it works</a></li>
                <li><a href="{{ route('site.analytics') }}" class="hover:text-white transition-colors">Analytics</a></li>
                <li><a href="{{ route('site.audience') }}" class="hover:text-white transition-colors">Audience</a></li>
                <li><a href="{{ route('site.integrations') }}" class="hover:text-white transition-colors">Integrations</a></li>
                <li><a href="{{ route('site.features') }}#cat-scheduling" class="hover:text-white transition-colors">Scheduling</a></li>
                <li><a href="{{ route('site.workspace-team') }}" class="hover:text-white transition-colors">Workspace &amp; Team</a></li>
                <li><a href="{{ route('site.premium-features') }}" class="hover:text-white transition-colors">Premium features</a></li>
            </ul>
        </div>
        {{-- AI Suite (5) --}}
        <div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-gray-300 mb-4">AI Suite</div>
            <ul class="space-y-2.5 text-gray-400">
                <li><a href="{{ route('site.ai-chatbot') }}" class="hover:text-white transition-colors">AI Chatbot</a></li>
                <li><a href="{{ route('site.ai-agent') }}" class="hover:text-white transition-colors">AI Agent</a></li>
                <li><a href="{{ route('site.ai-widget') }}" class="hover:text-white transition-colors">AI Widget</a></li>
                <li><a href="{{ route('site.ai-voice-assistant') }}" class="hover:text-white transition-colors">AI Voice Assistant</a></li>
                <li><a href="{{ route('site.resume-builder') }}" class="hover:text-white transition-colors">Résumé &amp; Portfolio</a></li>
            </ul>
        </div>
        {{-- Solutions (7) --}}
        <div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-gray-300 mb-4">Solutions</div>
            <ul class="space-y-2.5 text-gray-400">
                <li><a href="{{ route('site.services') }}" class="hover:text-white transition-colors">Use cases</a></li>
                <li><a href="{{ route('site.compare.index') }}" class="hover:text-white transition-colors">Compare 1INME</a></li>
                <li><a href="{{ route('site.pricing') }}" class="hover:text-white transition-colors">Pricing</a></li>
                <li><a href="{{ route('site.pricing', ['view' => 'coins']) }}" class="hover:text-white transition-colors">Coin packages</a></li>
                <li><a href="{{ route('site.discovery') }}" class="hover:text-white transition-colors">Discover creators</a></li>
                <li><a href="{{ route('site.creators-feed') }}" class="hover:text-white transition-colors">Creators feed</a></li>
                <li><a href="{{ route('site.features') }}#cat-events" class="hover:text-white transition-colors">Events &amp; RSVPs</a></li>
                <li><a href="{{ route('site.features') }}#cat-referrals" class="hover:text-white transition-colors">Referral program</a></li>
            </ul>
            <div class="text-[11px] font-bold uppercase tracking-wider text-gray-300 mt-6 mb-4">1INME for…</div>
            <ul class="space-y-2.5 text-gray-400">
                @foreach(\App\Modules\Common\Support\SitePagesContent::useCaseMeta() as $__fucSlug => $__fucMeta)
                    <li><a href="{{ route('site.use-case', $__fucSlug) }}" class="hover:text-white transition-colors">{{ $__fucMeta['eyebrow'] }}</a></li>
                @endforeach
            </ul>
        </div>
        {{-- Resources --}}
        <div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-gray-300 mb-4">Resources</div>
            <ul class="space-y-2.5 text-gray-400">
                <li><a href="{{ route('site.blogs.index') }}" class="hover:text-white transition-colors">Blog</a></li>
                <li><a href="{{ route('site.faqs') }}" class="hover:text-white transition-colors">FAQs</a></li>
                <li><a href="{{ route('site.features') }}#cat-templates" class="hover:text-white transition-colors">Templates</a></li>
                <li><a href="{{ route('site.api-docs') }}" class="hover:text-white transition-colors">Help &amp; API docs</a></li>
                <li><a href="#subscribe" class="hover:text-white transition-colors">Newsletter sign-up</a></li>
            </ul>
        </div>
        {{-- Company (5) --}}
        <div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-gray-300 mb-4">Company</div>
            <ul class="space-y-2.5 text-gray-400">
                <li><a href="{{ route('site.about') }}" class="hover:text-white transition-colors">About</a></li>
                <li><a href="{{ route('site.buzz') }}" class="hover:text-white transition-colors">Buzz</a></li>
                <li><a href="{{ route('site.contact') }}" class="hover:text-white transition-colors">Contact</a></li>
                @auth
                    <li><a href="{{ \Illuminate\Support\Facades\Route::has('site.dashboard') ? route('site.dashboard') : '/dashboard' }}" class="hover:text-white transition-colors">Dashboard</a></li>
                @else
                    <li><a href="{{ route('login.page') }}" class="hover:text-white transition-colors">Login</a></li>
                @endauth
            </ul>
        </div>
        {{-- Legal (5) --}}
        <div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-gray-300 mb-4">Legal</div>
            <ul class="space-y-2.5 text-gray-400">
                <li><a href="{{ route('site.terms') }}" class="hover:text-white transition-colors">Terms &amp; Conditions</a></li>
                <li><a href="{{ route('site.privacy') }}" class="hover:text-white transition-colors">Privacy</a></li>
                <li><a href="{{ route('site.refunds') }}" class="hover:text-white transition-colors">Refunds</a></li>
                <li><a href="{{ route('site.cookies') }}" class="hover:text-white transition-colors">Cookies</a></li>
                <li><a href="{{ route('site.gdpr') }}" class="hover:text-white transition-colors">GDPR</a></li>
            </ul>
        </div>
        </div>{{-- /.grid --}}
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
