{{--
    Site footer.

    Rebuilt because the old one had three problems that were all visible at
    a glance on the white page:

      1. Every link rendered in link-blue. The markup coloured them with
         Tailwind greys, but the marketing page's light-mode sheet repaints
         anchors, so the whole footer came out as a wall of blue text with
         near-invisible grey headings above it.
      2. The columns were wildly uneven -- ten items under Product, three
         under Company -- so the block read as one long list and three
         stubs. The Zio apps now have a column of their own, which is what
         they always were.
      3. Legal had a whole column to itself, and the space between the links
         and the bottom row was dead. Legal moves into the bottom bar, where
         it is conventional and where it fills a row that was empty.

    Colours are set explicitly here, in both directions, rather than
    inherited: this is the one block on the page where inheriting was the
    bug. Everything the old footer offered is still here -- store buttons,
    social row, keyboard shortcuts, currency switch, cookie preferences --
    just placed where it belongs.
--}}
<link rel="stylesheet" href="{{ asset('css/marketing/marketing-footer-815e32f750b0.css') }}">

<footer class="ft glass-footer pt-16 pb-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Brand column plus four even link columns. --}}
        <div class="ft-grid mb-12">
            <div>
                <a href="{{ route('home') }}" class="inline-flex items-center" aria-label="Sayzio home">
                    @include('common.partials.brand-logo', ['height' => 'h-9', 'loading' => 'lazy'])
                </a>
                {{-- "The all-in-one link platform" was true and a category
                     behind: the product now answers visitors, picks up calls,
                     chases leads and reads the numbers back. A footer blurb is
                     where a reader checks what the thing IS, so it names the
                     category the product is actually in. Colon, not a dash:
                     this file is under resources/views/public/, which the
                     em-dash copy guard scans. --}}
                <p class="ft-blurb">The AI-powered digital presence platform: build your Link in Bio, smart links, QR codes and AI pages, then let Zio engage your visitors, capture leads and help you grow.</p>

                <div class="mt-6">
                    <p class="ft-h">Get the app</p>
                    @include('public.partials.store-buttons')
                </div>

                {{-- Social moved up beside the brand. On its own row it was a
                     line of icons floating in the middle of an empty band. --}}
                <div class="mt-6 ft-social">
                    @include('common.partials.social-row')
                </div>
            </div>

            <div class="ft-col">
                <p class="ft-h">Product</p>
                <ul>
                    <li><a href="{{ route('home') }}#features" class="ft-link">Features</a></li>
                    <li><a href="{{ route('home') }}#how-it-works" class="ft-link">How it works</a></li>
                    <li><a href="{{ route('site.pricing') }}" class="ft-link">Pricing</a></li>
                    <li><a href="{{ route('site.workspace-team') }}" class="ft-link">Workspace &amp; Team</a></li>
                    <li><a href="{{ route('site.ai-dashboard') }}" class="ft-link">AI Dashboard</a></li>
                    <li><a href="{{ route('site.api-docs') }}" class="ft-link">API</a></li>
                </ul>
            </div>

            <div class="ft-col">
                <p class="ft-h">Apps</p>
                <ul>
                    <li><a href="{{ route('site.mobile-app') }}" class="ft-link">Mobile app</a></li>
                    <li><a href="{{ route('site.zio-dialer') }}" class="ft-link">Zio Dialer</a></li>
                    <li><a href="{{ route('site.zio-browser') }}" class="ft-link">Zio Browser</a></li>
                    <li><a href="{{ route('site.zio-extension') }}" class="ft-link">Browser extension</a></li>
                </ul>
            </div>

            <div class="ft-col">
                <p class="ft-h">Solutions</p>
                <ul>
                    <li><a href="{{ route('site.services') }}" class="ft-link">Use cases</a></li>
                    <li><a href="{{ route('site.discovery') }}" class="ft-link">Discover creators</a></li>
                    <li><a href="{{ route('site.creators-feed') }}" class="ft-link">Creators feed</a></li>
                    <li><a href="{{ route('site.newsroom') }}" class="ft-link">Newsroom</a></li>
                </ul>
            </div>

            <div class="ft-col">
                <p class="ft-h">Company</p>
                <ul>
                    <li><a href="{{ route('site.about') }}" class="ft-link">About</a></li>
                    <li><a href="{{ route('site.contact') }}" class="ft-link">Contact</a></li>
                    <li><a href="{{ route('site.faqs') }}" class="ft-link">FAQs</a></li>
                </ul>
            </div>
        </div>

        {{-- Legal, on one line rather than in a column of its own. --}}
        <div class="ft-bar" style="border-top: 0; padding-top: 0; margin-bottom: 22px;">
            <a href="{{ route('site.terms') }}" class="ft-link" style="font-size:13px">Terms</a>
            <a href="{{ route('site.privacy') }}" class="ft-link" style="font-size:13px">Privacy</a>
            <a href="{{ route('site.refunds') }}" class="ft-link" style="font-size:13px">Refunds</a>
            <a href="{{ route('site.cookies') }}" class="ft-link" style="font-size:13px">Cookies</a>
            <a href="{{ route('site.gdpr') }}" class="ft-link" style="font-size:13px">GDPR</a>
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
                   class="cc-footer-link ft-link" style="font-size:13px"
                   aria-label="{{ $__ccReopenHome }}"
                   onclick="if(window.openCookiePreferences){return window.openCookiePreferences(event);}">
                    {{ $__ccReopenHome }}
                </a>
            @endif

            <span class="ft-bar-spacer" aria-hidden="true"></span>

            @include('common.partials.shortcut-hint')
        </div>

        {{-- Copyright, currency, tagline. --}}
        <div class="ft-bar">
            <p class="ft-meta">&copy; {{ date('Y') }} Sayzio. All rights reserved.</p>

            <span class="ft-bar-spacer" aria-hidden="true"></span>

            {{-- Currency switch. The footer is the single control point for
                 public pages: it dispatches a window event so any in-page
                 pricing section re-renders instantly, with no page reload.

                 PricingResolver has no `resolve()`. It never has.

                 This block used to call one inside a try/catch, so every
                 render threw "Call to undefined method", the catch swallowed
                 it, and the footer fell back to ['USD', SOURCE_GEO] every
                 single time. That is why the switcher looked broken: a
                 visitor could click INR, the POST would persist correctly
                 and the event would re-render the prices, and then the next
                 page load would show USD selected again -- because the
                 control's own state came from a fallback, not from the
                 resolver. An Indian visitor resolving to INR saw a footer
                 insisting on USD, and the country-locked branch below could
                 never render at all.

                 The real API is two calls. They are made outside a catch-all
                 on purpose: a missing method here should fail loudly in
                 testing rather than quietly serve everyone the wrong
                 currency, which is precisely what happened. --}}
            @php
                // `auth()->user()` is not necessarily a customer. An admin
                // signed into the admin guard is browsing the public site as
                // an Admin model, and `currencyForUser()` is typed `?User`,
                // so handing it one is a TypeError -- which is what took the
                // whole page down the moment the catch-all above was removed
                // (PublicPagesAdminGuardTest covers exactly that visitor).
                //
                // Narrowing to a real User is the right answer rather than
                // catching again: the currency question is "what does this
                // CUSTOMER pay in", and an admin session is not a customer,
                // so it should fall through to session, cookie and geo like
                // any anonymous visitor.
                $__footerAuth    = auth()->user();
                $__footerUser    = $__footerAuth instanceof \App\Modules\User\Models\User ? $__footerAuth : null;
                $__footerCur     = \App\Services\PricingResolver::currencyForUser($__footerUser);
                $__footerSrc     = \App\Services\PricingResolver::currencySourceForUser($__footerUser);
                $__footerLocked  = $__footerSrc === \App\Services\PricingResolver::SOURCE_USER_COUNTRY;
                $__footerAutodet = $__footerSrc === \App\Services\PricingResolver::SOURCE_GEO;
            @endphp
            <div x-data="{ currency: '{{ $__footerCur }}' }"
                 class="flex items-center gap-2.5 ft-meta"
                 role="group" aria-label="Display currency">
                @if($__footerLocked)
                    {{-- Country-locked: a fixed label and a way to change it,
                         not a switcher that would disagree with billing. --}}
                    <span>{{ $__footerCur === 'INR' ? '₹ INR' : '$ USD' }}</span>
                    <span class="ft-bar-sep">·</span>
                    <span>
                        Set by your billing country:
                        <a href="{{ route('user.profile.edit') }}" class="ft-link" style="font-size:12.5px">change</a>
                    </span>
                @else
                    <span class="hidden sm:inline">Currency</span>
                    <div class="ft-cur" role="tablist" aria-label="Choose display currency">
                        <button type="button" role="tab"
                                :aria-selected="currency === 'USD'"
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
                                aria-label="Show prices in US dollars">$ USD</button>
                        <button type="button" role="tab"
                                :aria-selected="currency === 'INR'"
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
                                aria-label="Show prices in Indian rupees">₹ INR</button>
                    </div>
                    @if($__footerAutodet)
                        <span class="hidden sm:inline" aria-live="polite">auto-detected; switch anytime</span>
                    @endif
                @endif
            </div>

            <span class="ft-bar-sep hidden sm:inline">·</span>
            <p class="ft-meta">One Platform. Endless Conversations.</p>
        </div>
    </div>
</footer>
