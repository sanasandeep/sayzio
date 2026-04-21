{{--
    Shared auto-rotating info slider for auth screens (login page, register page, public auth modal).
    Props:
      - variant: 'page' (large, used on user/auth/login.blade.php and register.blade.php)
                 or 'modal' (compact, used inside the public auth modal)
--}}
@php
    $variant = $variant ?? 'page';
    $slides = [
        [
            'icon' => 'fas fa-link',
            'color_text' => 'text-violet-300',
            'color_bg' => 'rgba(124,58,237,0.15)',
            'color_border' => 'rgba(124,58,237,0.25)',
            'headline' => 'Drag-and-drop biolinks',
            'description' => 'Stack 99+ blocks — videos, forms, maps, products — into a stunning Link in Bio page in minutes.',
        ],
        [
            'icon' => 'fas fa-chart-line',
            'color_text' => 'text-cyan-300',
            'color_bg' => 'rgba(6,182,212,0.15)',
            'color_border' => 'rgba(6,182,212,0.25)',
            'headline' => 'Real-time analytics',
            'description' => 'Watch clicks, devices, locations and referrers stream in live as visitors arrive.',
        ],
        [
            'icon' => 'fas fa-bolt',
            'color_text' => 'text-pink-300',
            'color_bg' => 'rgba(236,72,153,0.15)',
            'color_border' => 'rgba(236,72,153,0.25)',
            'headline' => 'Performance Coach',
            'description' => 'Get one-click fixes for whatever is slowing your links and pages down.',
        ],
        [
            'icon' => 'fas fa-qrcode',
            'color_text' => 'text-emerald-300',
            'color_bg' => 'rgba(16,185,129,0.15)',
            'color_border' => 'rgba(16,185,129,0.25)',
            'headline' => 'Short links & QR codes',
            'description' => 'Branded, dynamic and repointable — perfect for campaigns, packaging and print.',
        ],
    ];
@endphp

<div
    x-data="authSlider({{ count($slides) }})"
    x-init="init()"
    @mouseenter="pause()"
    @mouseleave="resume()"
    @keydown.arrow-left.prevent="prev()"
    @keydown.arrow-right.prevent="next()"
    tabindex="0"
    role="region"
    aria-roledescription="carousel"
    aria-label="1INME features"
    class="auth-slider relative w-full focus:outline-none {{ $variant === 'modal' ? '' : 'max-w-md' }}"
>
    <div class="relative overflow-hidden {{ $variant === 'modal' ? 'min-h-[280px]' : 'min-h-[260px]' }}">
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
                aria-label="Slide {{ $i + 1 }} of {{ count($slides) }}"
                class="{{ $variant === 'modal' ? '' : '' }}"
            >
                <div
                    class="{{ $variant === 'modal' ? 'w-14 h-14' : 'w-16 h-16' }} rounded-2xl flex items-center justify-center mb-5"
                    style="background: {{ $slide['color_bg'] }}; border: 1px solid {{ $slide['color_border'] }};"
                >
                    <i class="{{ $slide['icon'] }} {{ $slide['color_text'] }} {{ $variant === 'modal' ? 'text-xl' : 'text-2xl' }}"></i>
                </div>
                <h3 class="{{ $variant === 'modal' ? 'text-lg' : 'text-2xl' }} font-bold mb-3 leading-tight"
                    style="{{ $variant === 'modal' ? 'color:#fff;' : 'color: var(--text-primary);' }}">
                    {{ $slide['headline'] }}
                </h3>
                <p class="{{ $variant === 'modal' ? 'text-xs' : 'text-sm' }} leading-relaxed"
                   style="{{ $variant === 'modal' ? 'color:#9ca3af;' : 'color: var(--text-dimmed);' }}">
                    {{ $slide['description'] }}
                </p>
            </div>
        @endforeach
    </div>

    <div class="flex items-center justify-between mt-6">
        <div class="flex items-center gap-2" aria-label="Choose slide">
            @foreach($slides as $i => $slide)
                <button
                    type="button"
                    @click="goTo({{ $i }})"
                    :aria-current="active === {{ $i }} ? 'true' : 'false'"
                    aria-label="Go to slide {{ $i + 1 }}"
                    :class="active === {{ $i }} ? 'w-6 bg-violet-400' : 'w-2 bg-white/25 hover:bg-white/40'"
                    class="h-2 rounded-full transition-all duration-300"
                ></button>
            @endforeach
        </div>
        <div class="flex items-center gap-1.5">
            <button
                type="button"
                @click="prev()"
                aria-label="Previous slide"
                class="w-8 h-8 rounded-full flex items-center justify-center text-white/60 hover:text-white hover:bg-white/10 transition"
            >
                <i class="fas fa-chevron-left text-[11px]"></i>
            </button>
            <button
                type="button"
                @click="next()"
                aria-label="Next slide"
                class="w-8 h-8 rounded-full flex items-center justify-center text-white/60 hover:text-white hover:bg-white/10 transition"
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
                        }, 6000);
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
