{{--
    Shared auto-rotating "use case" slider for auth screens.
    Now full-bleed: each slide fills the container with a real photo
    background, an overlay, a brand logo (modal only), a category tag,
    a headline and 3-4 bullet points showing how that audience uses 1INME.

    Props:
      - variant: 'page'  (used on user/auth/login.blade.php and register.blade.php)
                 'modal' (used inside the public auth modal — includes brand logo overlay)
--}}
@php
    $variant = $variant ?? 'page';
    $slides = [
        [
            'image'    => asset('images/auth-slider/photo-creators.png'),
            'tag'      => 'For content creators',
            'headline' => 'Turn followers into a fan economy.',
            'icon'     => 'fas fa-camera',
            'accent'   => '#a78bfa',
            'bullets'  => [
                'One bio link for TikTok, Reels and YouTube',
                'Drop merch, presets and digital products',
                'Collect tips and paid DMs',
                'See which posts drive the most clicks',
            ],
        ],
        [
            'image'    => asset('images/auth-slider/photo-musicians.png'),
            'tag'      => 'For musicians & artists',
            'headline' => 'Every release. One smart link.',
            'icon'     => 'fas fa-music',
            'accent'   => '#f472b6',
            'bullets'  => [
                'Auto-route fans to Spotify, Apple, YouTube',
                'Sell vinyl, merch and tour tickets',
                'Capture emails for the next drop',
                'Track plays, streams and pre-saves live',
            ],
        ],
        [
            'image'    => asset('images/auth-slider/photo-shops.png'),
            'tag'      => 'For small shops & makers',
            'headline' => 'Sell from your bio in one tap.',
            'icon'     => 'fas fa-store',
            'accent'   => '#fb923c',
            'bullets'  => [
                'Showcase your shop, lookbook & reviews',
                'Branded short links for packaging & ads',
                'Dynamic QR codes for stickers and flyers',
                'See which post drove the order',
            ],
        ],
        [
            'image'    => asset('images/auth-slider/photo-educators.png'),
            'tag'      => 'For coaches & educators',
            'headline' => 'Book sessions. Sell courses. Repeat.',
            'icon'     => 'fas fa-graduation-cap',
            'accent'   => '#34d399',
            'bullets'  => [
                'Embed Calendly, Stripe and lesson links',
                'Drop free lead magnets behind email opt-ins',
                'Run waitlists and early-bird offers',
                'Coach: AI suggestions to lift conversions',
            ],
        ],
        [
            'image'    => asset('images/auth-slider/photo-podcasters.png'),
            'tag'      => 'For podcasters & streamers',
            'headline' => 'Grow on every platform at once.',
            'icon'     => 'fas fa-microphone-lines',
            'accent'   => '#22d3ee',
            'bullets'  => [
                'Smart link routes to Spotify, Apple, Overcast',
                'Direct fans to Patreon, Discord, Twitch',
                'Promote latest episode automatically',
                'See where new listeners come from',
            ],
        ],
        [
            'image'    => asset('images/auth-slider/photo-agencies.png'),
            'tag'      => 'For agencies & teams',
            'headline' => 'Run links across every client.',
            'icon'     => 'fas fa-people-group',
            'accent'   => '#818cf8',
            'bullets'  => [
                'Workspaces with owner, admin, editor, viewer roles',
                'White-label biolinks on your own domain',
                'Bulk-create short links from CSV',
                'Audit logs and exportable analytics',
            ],
        ],
    ];
    $count = count($slides);

    // Variant tuning
    $isModal   = $variant === 'modal';
    $minHeight = $isModal ? 'min-h-[520px]' : 'min-h-screen';
    $padX      = $isModal ? 'px-7'  : 'px-10 xl:px-14';
    $padBottom = $isModal ? 'pb-20' : 'pb-24';
    $padTop    = $isModal ? 'pt-7'  : 'pt-7';
    $headSize  = $isModal ? 'text-2xl' : 'text-4xl';
    $rounding  = $isModal ? 'rounded-l-2xl' : 'rounded-none';
@endphp

<div
    x-data="authSlider({{ $count }})"
    x-init="init()"
    @mouseenter="pause()"
    @mouseleave="resume()"
    @focusin="pause()"
    @focusout="resume()"
    @keydown.arrow-left.prevent="prev()"
    @keydown.arrow-right.prevent="next()"
    @keydown.space.prevent="toggle()"
    tabindex="0"
    role="region"
    aria-roledescription="carousel"
    aria-label="See how creators, brands and teams use 1INME"
    class="auth-slider relative w-full h-full focus:outline-none"
>
    <div class="relative w-full h-full overflow-hidden {{ $rounding }} {{ $minHeight }}">
        @foreach($slides as $i => $slide)
            <div
                x-show="active === {{ $i }}"
                {{ $i === 0 ? '' : 'x-cloak' }}
                x-transition:enter="transition ease-out duration-700"
                x-transition:enter-start="opacity-0 scale-[1.04]"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-400 absolute inset-0"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-[0.98]"
                role="group"
                aria-roledescription="slide"
                aria-label="Slide {{ $i + 1 }} of {{ $count }}: {{ $slide['headline'] }}"
                class="absolute inset-0"
            >
                {{-- Real photograph background --}}
                <div
                    aria-hidden="true"
                    class="absolute inset-0 bg-center bg-cover"
                    style="background-image: url('{{ $slide['image'] }}'); animation: authSliderKenburns 14s ease-in-out infinite alternate;"
                ></div>
                {{-- Multi-stop dark gradient overlay so text is always legible --}}
                <div
                    aria-hidden="true"
                    class="absolute inset-0"
                    style="background:
                        linear-gradient(180deg, rgba(8,4,18,0.40) 0%, rgba(8,4,18,0.10) 35%, rgba(8,4,18,0.55) 65%, rgba(8,4,18,0.92) 100%),
                        linear-gradient(110deg, rgba(0,0,0,0.45) 0%, rgba(0,0,0,0) 55%);"
                ></div>
                {{-- Tinted accent corner glow --}}
                <div
                    aria-hidden="true"
                    class="absolute -top-10 -left-10 w-56 h-56 rounded-full opacity-50 pointer-events-none"
                    style="background: radial-gradient(closest-side, {{ $slide['accent'] }}55, transparent 70%); filter: blur(36px);"
                ></div>

                {{-- Top brand row (modal only) --}}
                @if($isModal)
                <div class="absolute top-0 inset-x-0 {{ $padX }} {{ $padTop }} z-10">
                    @include('common.partials.brand-logo', ['height' => 'h-9'])
                </div>
                @endif

                {{-- Bottom-anchored content --}}
                <div class="absolute bottom-0 inset-x-0 {{ $padX }} {{ $padBottom }} z-10">
                    {{-- Category tag pill --}}
                    <div class="inline-flex items-center gap-2 mb-4 px-3 py-1.5 rounded-full text-[11px] font-semibold uppercase tracking-wider"
                         style="background: {{ $slide['accent'] }}26; border: 1px solid {{ $slide['accent'] }}66; color: #fff; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);">
                        <i class="{{ $slide['icon'] }}" style="color: {{ $slide['accent'] }};"></i>
                        <span>{{ $slide['tag'] }}</span>
                    </div>
                    <h3 class="{{ $headSize }} font-bold mb-4 leading-tight"
                        style="color:#ffffff !important; text-shadow: 0 2px 18px rgba(0,0,0,0.65);">
                        {{ $slide['headline'] }}
                    </h3>
                    <ul class="space-y-1.5 text-sm">
                        @foreach($slide['bullets'] as $b)
                            <li class="flex items-start gap-2.5"
                                style="color: rgba(255,255,255,0.92) !important; text-shadow: 0 1px 8px rgba(0,0,0,0.55);">
                                <span class="mt-[7px] inline-block w-1.5 h-1.5 rounded-full flex-shrink-0"
                                      style="background: {{ $slide['accent'] }}; box-shadow: 0 0 10px {{ $slide['accent'] }}99;"></span>
                                <span>{{ $b }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endforeach

        {{-- Bottom-overlaid controls (dots + prev/next) --}}
        <div class="absolute bottom-3 inset-x-0 {{ $padX }} z-20 flex items-center justify-between pointer-events-none">
            <div class="flex items-center gap-1.5 pointer-events-auto" aria-label="Choose slide">
                @foreach($slides as $i => $slide)
                    <button
                        type="button"
                        @click="goTo({{ $i }})"
                        :aria-current="active === {{ $i }} ? 'true' : 'false'"
                        aria-label="Go to slide {{ $i + 1 }}: {{ $slide['headline'] }}"
                        :class="active === {{ $i }} ? 'w-6 bg-white' : 'w-2 bg-white/40 hover:bg-white/70'"
                        class="h-1.5 rounded-full transition-all duration-300"
                    ></button>
                @endforeach
            </div>
            <div class="flex items-center gap-1.5 pointer-events-auto">
                <button type="button" @click="prev()" aria-label="Previous slide"
                        class="w-8 h-8 rounded-full flex items-center justify-center text-white/85 hover:text-white bg-white/10 hover:bg-white/20 backdrop-blur-md transition">
                    <i class="fas fa-chevron-left text-[11px]"></i>
                </button>
                <button type="button" @click="next()" aria-label="Next slide"
                        class="w-8 h-8 rounded-full flex items-center justify-center text-white/85 hover:text-white bg-white/10 hover:bg-white/20 backdrop-blur-md transition">
                    <i class="fas fa-chevron-right text-[11px]"></i>
                </button>
            </div>
        </div>
    </div>
</div>

@once
        <style>
            @keyframes authSliderKenburns {
                0%   { transform: scale(1.00) translate3d(0,0,0); }
                100% { transform: scale(1.08) translate3d(-1.5%, -1.5%, 0); }
            }
            @media (prefers-reduced-motion: reduce) {
                .auth-slider [style*="authSliderKenburns"] { animation: none !important; }
            }
        </style>
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('authSlider', (count) => ({
                    active: 0,
                    count: count,
                    timer: null,
                    paused: false,
                    reducedMotion: false,
                    init() {
                        try {
                            this.reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                        } catch (_) {}
                        if (!this.reducedMotion) this.start();
                    },
                    start() {
                        this.stop();
                        this.timer = setInterval(() => {
                            if (!this.paused) this.next();
                        }, 7000);
                    },
                    stop() { if (this.timer) { clearInterval(this.timer); this.timer = null; } },
                    pause() { this.paused = true; },
                    resume() { this.paused = false; },
                    toggle() { this.paused = !this.paused; },
                    next() { this.active = (this.active + 1) % this.count; },
                    prev() { this.active = (this.active - 1 + this.count) % this.count; },
                    goTo(i) { this.active = i; },
                }));
            });
        </script>
@endonce
