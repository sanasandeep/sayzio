@php
    try {
        $__stats = \App\Modules\Admin\Models\SiteStat::active()->ordered()->get();
    } catch (\Throwable $e) {
        $__stats = collect();
    }
@endphp
@if($__stats->count())
<section class="py-14 lg:py-20 relative overflow-hidden">
    <div class="absolute inset-0 -z-10 opacity-60" style="background: rgba(124,58,237,.06);"></div>

    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10">
            <div class="reveal text-xs font-bold uppercase tracking-[.22em] mb-3" style="color:var(--c2)">By the numbers</div>
            <h2 class="reveal rd-1 text-3xl sm:text-4xl md:text-5xl font-bold tracking-tight">
                Trusted at <span class="grad-text">scale.</span>
            </h2>
            <p class="reveal rd-2 text-gray-400 max-w-xl mx-auto mt-3 text-sm sm:text-base">Real numbers from real creators, brands and teams using 1INME every day.</p>
        </div>

        <div class="reveal glass rounded-3xl p-6 sm:p-10 relative overflow-hidden border border-white/10">
            <div class="absolute -top-24 -right-24 w-80 h-80 rounded-full opacity-25 blur-3xl" style="background: var(--c2);"></div>
            <div class="absolute -bottom-24 -left-24 w-80 h-80 rounded-full opacity-20 blur-3xl" style="background: var(--c4);"></div>

            @php $__count = $__stats->count(); @endphp
            <div class="relative -mx-2 sm:mx-0">
                <div class="flex md:grid gap-3 sm:gap-5 md:gap-6 overflow-x-auto md:overflow-visible snap-x snap-mandatory scroll-smooth px-2 sm:px-0 pb-2 md:pb-0 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden"
                     style="grid-template-columns: repeat({{ max(1, $__count) }}, minmax(0, 1fr));">
                    @foreach($__stats as $i => $stat)
                        @php
                            $target = $stat->numericTarget();
                            $hasNumeric = $target !== null;
                        @endphp
                        <div class="reveal rd-{{ ($i%4)+1 }} text-center group shrink-0 snap-center w-[42%] xs:w-[36%] sm:w-[28%] md:w-auto">
                            <div class="relative inline-flex items-center justify-center mb-2 sm:mb-3 md:mb-4">
                                <div class="absolute inset-0 rounded-xl sm:rounded-2xl blur-xl opacity-60 group-hover:opacity-90 transition" style="background: {{ $stat->color }};"></div>
                                <div class="relative w-10 h-10 sm:w-12 sm:h-12 md:w-14 md:h-14 rounded-xl sm:rounded-2xl flex items-center justify-center border border-white/15"
                                     style="background: {{ $stat->color }}; box-shadow: 0 12px 36px -12px {{ $stat->color }};">
                                    <i class="fas {{ $stat->icon }} text-white text-sm sm:text-base md:text-lg"></i>
                                </div>
                            </div>
                            <div class="text-lg sm:text-2xl md:text-3xl lg:text-4xl font-extrabold tracking-tight grad-text leading-none whitespace-nowrap">
                                <span class="js-stat-count"
                                      data-target="{{ $target !== null ? (int) $target : '' }}"
                                      data-display="{{ $stat->value }}"
                                      data-duration="1600">{{ $hasNumeric ? '0' : $stat->value }}</span><span class="text-white/80">{{ $stat->suffix }}</span>
                            </div>
                            <div class="mt-1.5 sm:mt-2 text-[10px] sm:text-xs md:text-sm text-gray-400 uppercase tracking-wider leading-tight">{{ $stat->label }}</div>
                            <div class="mx-auto mt-2 sm:mt-3 h-1 w-10 sm:w-14 md:w-16 rounded-full" style="background: {{ $stat->color }};"></div>
                        </div>
                    @endforeach
                </div>
                <div class="md:hidden flex justify-center gap-1.5 mt-3" aria-hidden="true">
                    @for($i = 0; $i < min(5, $__count); $i++)
                        <span class="w-1.5 h-1.5 rounded-full bg-white/25"></span>
                    @endfor
                </div>
            </div>
        </div>
    </div>

    <script>
    (function(){
        if (window.__inmeStatsCountupBound) return;
        window.__inmeStatsCountupBound = true;
        const reduce = window.matchMedia &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const fmt = (n) => n.toLocaleString('en-IN');
        const animate = (el) => {
            const target = parseInt(el.dataset.target || '', 10);
            const display = el.dataset.display || '';
            if (!Number.isFinite(target) || target <= 0) { el.textContent = display; return; }
            const dur = parseInt(el.dataset.duration || '1500', 10);
            const start = performance.now();
            const tick = (now) => {
                const t = Math.min(1, (now - start) / dur);
                const eased = 1 - Math.pow(1 - t, 3);
                const cur = Math.round(target * eased);
                el.textContent = fmt(cur);
                if (t < 1) requestAnimationFrame(tick);
                else el.textContent = display; // snap to admin-typed format (preserves commas/decimals)
            };
            requestAnimationFrame(tick);
        };
        const io = new IntersectionObserver((entries) => {
            entries.forEach(en => {
                if (en.isIntersecting) {
                    animate(en.target);
                    io.unobserve(en.target);
                }
            });
        }, { threshold: 0.4 });
        const els = document.querySelectorAll('.js-stat-count');
        if (reduce || !('IntersectionObserver' in window)) {
            els.forEach(el => { el.textContent = el.dataset.display || el.textContent; });
            return;
        }
        els.forEach(el => io.observe(el));
    })();
    </script>
</section>
@endif
