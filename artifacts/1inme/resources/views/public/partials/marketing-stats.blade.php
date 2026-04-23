@php
    try {
        $__stats = \App\Modules\Admin\Models\SiteStat::active()->ordered()->get();
    } catch (\Throwable $e) {
        $__stats = collect();
    }
@endphp
@if($__stats->count())
<section class="py-14 lg:py-20 relative overflow-hidden">
    <div class="absolute inset-0 -z-10 opacity-60" style="background:
        radial-gradient(40rem 24rem at 15% 0%, rgba(124,58,237,.18), transparent 60%),
        radial-gradient(40rem 24rem at 85% 100%, rgba(27,212,217,.16), transparent 60%);"></div>

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

            <div class="relative grid gap-6 sm:gap-8" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                @foreach($__stats as $i => $stat)
                    @php
                        $target = $stat->numericTarget();
                        $hasNumeric = $target !== null && str_contains($stat->value, (string) (int) $target) ? true : ($target !== null);
                    @endphp
                    <div class="reveal rd-{{ ($i%4)+1 }} text-center group">
                        <div class="relative inline-flex items-center justify-center mb-4">
                            <div class="absolute inset-0 rounded-2xl blur-xl opacity-60 group-hover:opacity-90 transition" style="background: {{ $stat->color }};"></div>
                            <div class="relative w-14 h-14 rounded-2xl flex items-center justify-center border border-white/15"
                                 style="background: linear-gradient(135deg, {{ $stat->color }}, rgba(124,58,237,.85)); box-shadow: 0 12px 36px -12px {{ $stat->color }};">
                                <i class="fas {{ $stat->icon }} text-white text-lg"></i>
                            </div>
                        </div>
                        <div class="text-3xl sm:text-4xl font-extrabold tracking-tight grad-text leading-none">
                            <span class="js-stat-count"
                                  data-target="{{ $target !== null ? (int) $target : '' }}"
                                  data-display="{{ $stat->value }}"
                                  data-duration="1600">{{ $hasNumeric ? '0' : $stat->value }}</span><span class="text-white/80">{{ $stat->suffix }}</span>
                        </div>
                        <div class="mt-2 text-xs sm:text-sm text-gray-400 uppercase tracking-wider">{{ $stat->label }}</div>
                        <div class="mx-auto mt-3 h-1 w-16 rounded-full" style="background: linear-gradient(90deg, {{ $stat->color }}, transparent);"></div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <script>
    (function(){
        if (window.__inmeStatsCountupBound) return;
        window.__inmeStatsCountupBound = true;
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
        document.querySelectorAll('.js-stat-count').forEach(el => io.observe(el));
    })();
    </script>
</section>
@endif
