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
<style>
    /* ft- = footer */
    .ft {
        border-top: 1px solid var(--fs-rule, rgba(255,255,255,.14));
        background: var(--fs-page, #000);
    }

    .ft-h {
        font-size: 11px; font-weight: 700; letter-spacing: .14em;
        text-transform: uppercase;
        color: #8B93AD;
        margin: 0 0 14px;
    }
    html.light-mode .ft-h { color: #6B7280; }

    /* The fix for the blue wall: state the link colour, and beat the
       page-level anchor rule with a class of the same weight declared
       later. Muted at rest, full ink on hover -- the same relationship the
       nav uses, so the footer feels like part of the same site. */
    .ft-link {
        display: inline-block;
        font-size: 14px; line-height: 1.5;
        color: #A0A8C0 !important;
        transition: color .15s ease;
    }
    .ft-link:hover { color: #FFFFFF !important; }
    html.light-mode .ft-link { color: #55607F !important; }
    html.light-mode .ft-link:hover { color: #0F172A !important; }

    .ft-col ul { display: flex; flex-direction: column; gap: 10px; margin: 0; padding: 0; list-style: none; }

    /* The column grid is plain CSS rather than a Tailwind arbitrary value
       (`lg:grid-cols-[1.6fr_1fr_1fr_1fr_1fr]`). Tailwind is compiled at
       build time here, so a class nobody has built yet simply does not
       exist at runtime -- the first version of this footer silently fell
       back to two columns because of exactly that. */
    .ft-grid { display: grid; gap: 40px; grid-template-columns: 1fr; }
    @media (min-width: 640px)  { .ft-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (min-width: 1024px) { .ft-grid { grid-template-columns: 1.7fr repeat(4, 1fr); gap: 32px; } }

    .ft-blurb {
        margin: 14px 0 0; max-width: 34ch;
        font-size: 14px; line-height: 1.65;
        color: #98A0B8;
    }
    html.light-mode .ft-blurb { color: #55607F; }

    /* Bottom bar: everything that is about the site rather than in it. */
    .ft-bar {
        border-top: 1px solid var(--fs-rule, rgba(255,255,255,.14));
        display: flex; flex-wrap: wrap; align-items: center; gap: 10px 18px;
        padding-top: 22px;
    }
    .ft-bar-sep { opacity: .28; user-select: none; }
    .ft-meta { font-size: 12.5px; color: #7E869E; }
    html.light-mode .ft-meta { color: #6B7280; }

    /* Pushes the right-hand group away from the left one at width, and lets
       it wrap underneath instead of squeezing on a phone. */
    .ft-bar-spacer { flex: 1 1 24px; }

    .ft-cur {
        display: inline-flex; align-items: center;
        border-radius: 999px; overflow: hidden;
        border: 1px solid var(--fs-rule, rgba(255,255,255,.14));
    }
    .ft-cur button {
        padding: 5px 12px; font-size: 11.5px; font-weight: 700;
        color: #8B93AD; transition: background .15s ease, color .15s ease;
    }
    .ft-cur button[aria-selected="true"] { background: rgba(255,255,255,.12); color: #F2F5FF; }
    html.light-mode .ft-cur button { color: #6B7280; }
    html.light-mode .ft-cur button[aria-selected="true"] { background: #EDEFF7; color: #0F172A; }

    @media (max-width: 640px) {
        .ft-bar { flex-direction: column; align-items: flex-start; }
        .ft-bar-spacer { display: none; }
    }
</style>

<footer class="ft glass-footer pt-16 pb-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Brand column plus four even link columns. --}}
        <div class="ft-grid mb-12">
            <div>
                <a href="{{ route('home') }}" class="inline-flex items-center" aria-label="Sayzio home">
                    @include('common.partials.brand-logo', ['height' => 'h-9'])
                </a>
                <p class="ft-blurb">The all-in-one link platform: build a drag-and-drop Link in Bio, share it everywhere, and grow with live analytics and a built-in Performance Coach.</p>

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
