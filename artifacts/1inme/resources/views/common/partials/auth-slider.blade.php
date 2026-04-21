{{--
    Shared auto-rotating info slider for auth screens (login page, register page, public auth modal).
    Props:
      - variant: 'page' (large, used on user/auth/login.blade.php and register.blade.php)
                 or 'modal' (compact, used inside the public auth modal)
--}}
@php
    $variant = $variant ?? 'page';
    $slides = [
        // 5 product feature slides
        [
            'icon' => 'fas fa-link',
            'color_text' => 'text-violet-300',
            'color_bg' => 'rgba(124,58,237,0.18)',
            'color_border' => 'rgba(124,58,237,0.35)',
            'image' => asset('images/auth-slider/biolinks.svg'),
            'overlay' => 'linear-gradient(135deg, rgba(15,10,30,0.55) 0%, rgba(15,10,30,0.85) 100%)',
            'headline' => 'Drag-and-drop biolinks',
            'description' => 'Stack 99+ blocks — videos, forms, maps, products — into a stunning Link in Bio page in minutes.',
        ],
        [
            'icon' => 'fas fa-chart-line',
            'color_text' => 'text-cyan-300',
            'color_bg' => 'rgba(6,182,212,0.18)',
            'color_border' => 'rgba(6,182,212,0.35)',
            'image' => asset('images/auth-slider/analytics.svg'),
            'overlay' => 'linear-gradient(135deg, rgba(8,20,30,0.55) 0%, rgba(8,20,30,0.88) 100%)',
            'headline' => 'Real-time analytics',
            'description' => 'Watch clicks, devices, locations and referrers stream in live as visitors arrive.',
        ],
        [
            'icon' => 'fas fa-bolt',
            'color_text' => 'text-pink-300',
            'color_bg' => 'rgba(236,72,153,0.18)',
            'color_border' => 'rgba(236,72,153,0.35)',
            'image' => asset('images/auth-slider/coach.svg'),
            'overlay' => 'linear-gradient(135deg, rgba(28,8,22,0.55) 0%, rgba(28,8,22,0.88) 100%)',
            'headline' => 'Performance Coach',
            'description' => 'Get one-click fixes for whatever is slowing your links and pages down.',
        ],
        [
            'icon' => 'fas fa-qrcode',
            'color_text' => 'text-emerald-300',
            'color_bg' => 'rgba(16,185,129,0.18)',
            'color_border' => 'rgba(16,185,129,0.35)',
            'image' => asset('images/auth-slider/qrcodes.svg'),
            'overlay' => 'linear-gradient(135deg, rgba(6,28,22,0.55) 0%, rgba(6,28,22,0.88) 100%)',
            'headline' => 'Short links & QR codes',
            'description' => 'Branded, dynamic and repointable — perfect for campaigns, packaging and print.',
        ],
        [
            'icon' => 'fas fa-users',
            'color_text' => 'text-indigo-300',
            'color_bg' => 'rgba(99,102,241,0.18)',
            'color_border' => 'rgba(99,102,241,0.35)',
            'image' => asset('images/auth-slider/team.svg'),
            'overlay' => 'linear-gradient(135deg, rgba(14,12,40,0.55) 0%, rgba(14,12,40,0.88) 100%)',
            'headline' => 'Team workspaces',
            'description' => 'Invite teammates with owner, admin, editor or viewer roles — every action is logged.',
        ],
        // 3 informational slides
        [
            'icon' => 'fas fa-heart',
            'color_text' => 'text-fuchsia-300',
            'color_bg' => 'rgba(217,70,239,0.18)',
            'color_border' => 'rgba(217,70,239,0.35)',
            'image' => asset('images/auth-slider/trusted.svg'),
            'overlay' => 'linear-gradient(135deg, rgba(20,10,35,0.55) 0%, rgba(20,10,35,0.88) 100%)',
            'headline' => 'Trusted by 10,000+ creators',
            'description' => 'Solo creators, growing brands and agencies all run their links and bio pages on 1INME.',
        ],
        [
            'icon' => 'fas fa-mobile-screen',
            'color_text' => 'text-sky-300',
            'color_bg' => 'rgba(14,165,233,0.18)',
            'color_border' => 'rgba(14,165,233,0.35)',
            'image' => asset('images/auth-slider/devices.svg'),
            'overlay' => 'linear-gradient(135deg, rgba(8,15,30,0.55) 0%, rgba(8,15,30,0.88) 100%)',
            'headline' => 'Works on web + mobile',
            'description' => 'Build, edit and track from any device. Your bio page looks gorgeous on every screen.',
        ],
        [
            'icon' => 'fas fa-shield-halved',
            'color_text' => 'text-emerald-300',
            'color_bg' => 'rgba(16,185,129,0.18)',
            'color_border' => 'rgba(16,185,129,0.35)',
            'image' => asset('images/auth-slider/secure.svg'),
            'overlay' => 'linear-gradient(135deg, rgba(8,24,20,0.55) 0%, rgba(8,24,20,0.88) 100%)',
            'headline' => 'GDPR-ready & secure',
            'description' => 'Encrypted in transit, role-based access, audit logs and privacy controls baked in.',
        ],
    ];
    $count = count($slides);
@endphp

<div
    x-data="authSlider({{ $count }})"
    x-init="init()"
    @mouseenter="pause()"
    @mouseleave="resume()"
    @keydown.arrow-left.prevent="prev()"
    @keydown.arrow-right.prevent="next()"
    tabindex="0"
    role="region"
    aria-roledescription="carousel"
    aria-label="1INME features and highlights"
    class="auth-slider relative w-full focus:outline-none {{ $variant === 'modal' ? '' : 'max-w-md' }}"
>
    <div class="relative overflow-hidden rounded-2xl {{ $variant === 'modal' ? 'min-h-[300px]' : 'min-h-[300px]' }}">
        @foreach($slides as $i => $slide)
            <div
                x-show="active === {{ $i }}"
                {{ $i === 0 ? '' : 'x-cloak' }}
                x-transition:enter="transition ease-out duration-500"
                x-transition:enter-start="opacity-0 translate-x-4"
                x-transition:enter-end="opacity-100 translate-x-0"
                x-transition:leave="transition ease-in duration-300 absolute inset-0"
                x-transition:leave-start="opacity-100 translate-x-0"
                x-transition:leave-end="opacity-0 -translate-x-4"
                role="group"
                aria-roledescription="slide"
                aria-label="Slide {{ $i + 1 }} of {{ $count }}: {{ $slide['headline'] }}"
                class="relative overflow-hidden rounded-2xl {{ $variant === 'modal' ? 'min-h-[300px]' : 'min-h-[300px]' }}"
            >
                {{-- Background image layer --}}
                <div
                    aria-hidden="true"
                    class="absolute inset-0 bg-center bg-cover"
                    style="background-image: url('{{ $slide['image'] }}');"
                ></div>
                {{-- Gradient overlay for text legibility --}}
                <div
                    aria-hidden="true"
                    class="absolute inset-0"
                    style="background: {{ $slide['overlay'] }};"
                ></div>

                {{-- Content --}}
                <div class="relative {{ $variant === 'modal' ? 'p-5' : 'p-6' }}">
                    <div
                        class="{{ $variant === 'modal' ? 'w-12 h-12' : 'w-14 h-14' }} rounded-2xl flex items-center justify-center mb-4 backdrop-blur-sm"
                        style="background: {{ $slide['color_bg'] }}; border: 1px solid {{ $slide['color_border'] }};"
                    >
                        <i class="{{ $slide['icon'] }} {{ $slide['color_text'] }} {{ $variant === 'modal' ? 'text-lg' : 'text-xl' }}"></i>
                    </div>
                    <h3 class="{{ $variant === 'modal' ? 'text-lg' : 'text-2xl' }} font-bold mb-3 leading-tight"
                        style="color:#ffffff !important; text-shadow: 0 1px 12px rgba(0,0,0,0.6);">
                        {{ $slide['headline'] }}
                    </h3>
                    <p class="{{ $variant === 'modal' ? 'text-xs' : 'text-sm' }} leading-relaxed"
                       style="color:rgba(255,255,255,0.88) !important; text-shadow: 0 1px 8px rgba(0,0,0,0.55);">
                        {{ $slide['description'] }}
                    </p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="flex items-center justify-between mt-5">
        <div class="flex items-center gap-1.5 flex-wrap" aria-label="Choose slide">
            @foreach($slides as $i => $slide)
                <button
                    type="button"
                    @click="goTo({{ $i }})"
                    :aria-current="active === {{ $i }} ? 'true' : 'false'"
                    aria-label="Go to slide {{ $i + 1 }}: {{ $slide['headline'] }}"
                    :class="active === {{ $i }} ? 'w-5 bg-violet-400' : 'w-2 bg-white/25 hover:bg-white/40'"
                    class="h-2 rounded-full transition-all duration-300"
                ></button>
            @endforeach
        </div>
        <div class="flex items-center gap-1.5">
            <button
                type="button"
                @click="prev()"
                aria-label="Previous slide"
                class="w-8 h-8 rounded-full flex items-center justify-center text-white/70 hover:text-white hover:bg-white/10 transition"
            >
                <i class="fas fa-chevron-left text-[11px]"></i>
            </button>
            <button
                type="button"
                @click="next()"
                aria-label="Next slide"
                class="w-8 h-8 rounded-full flex items-center justify-center text-white/70 hover:text-white hover:bg-white/10 transition"
            >
                <i class="fas fa-chevron-right text-[11px]"></i>
            </button>
        </div>
    </div>
</div>

@once
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
                    next() { this.active = (this.active + 1) % this.count; },
                    prev() { this.active = (this.active - 1 + this.count) % this.count; },
                    goTo(i) { this.active = i; },
                }));
            });
        </script>
@endonce
