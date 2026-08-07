{{--
    Consolidated credibility band — sits directly under the hero so headline
    trust numbers and security signals land above the fold.

    Numbers are pulled from the admin-editable SiteStat source of truth
    (Admin → Site stats), never hard-coded in markup. The count-up uses the
    `.js-stat-count` runtime (honours prefers-reduced-motion); `.reveal`
    entrance is shared. The card itself uses the shared liquid-glass
    treatment (26px blur/saturate, inset top highlight, long soft shadow)
    with a paired html.light-mode variant.
--}}
<style>
    {{-- Consumes the shared --lg-* liquid-glass tokens when theme-styles is
         present; the standalone marketing home doesn't load theme-styles, so
         each var carries the same literal recipe as a fallback, with paired
         html.light-mode overrides for the fallback path. --}}
    .trust-band-card {
        border-radius: var(--lg-radius, 1.5rem);
        background: var(--lg-bg, rgba(255,255,255,0.045));
        border: 1px solid var(--lg-border, rgba(255,255,255,0.10));
        backdrop-filter: var(--lg-blur, blur(26px) saturate(1.4));
        -webkit-backdrop-filter: var(--lg-blur, blur(26px) saturate(1.4));
        box-shadow: var(--lg-shadow, 0 30px 70px -35px rgba(0,0,0,0.7), inset 0 1px 0 rgba(255,255,255,0.07));
    }
    html.light-mode .trust-band-card {
        background: var(--lg-bg, rgba(255,255,255,0.62));
        border-color: var(--lg-border, rgba(15,23,42,0.09));
        box-shadow: var(--lg-shadow, 0 30px 70px -45px rgba(15,23,42,0.35), inset 0 1px 0 rgba(255,255,255,0.85));
    }
    /* Surface-aware sub-elements (icon chips + dividers) — the marketing
       home's generic light-mode overrides handle text, these handle the
       dark-oriented rgba(255,255,255,*) treatments. */
    .trust-band-card .tb-chip { background: rgba(255,255,255,0.05); }
    html.light-mode .trust-band-card .tb-chip { background: rgba(15,23,42,0.06); }
    .trust-band-card .tb-divider { background: rgba(255,255,255,0.10); }
    html.light-mode .trust-band-card .tb-divider { background: rgba(15,23,42,0.10); }
    .trust-band-card .tb-sep { border-top: 1px solid rgba(255,255,255,0.07); }
    html.light-mode .trust-band-card .tb-sep { border-top-color: rgba(15,23,42,0.08); }
</style>
@php
    try {
        $__heroStats = \App\Modules\Admin\Models\SiteStat::cachedActive()->take(4)->values();
    } catch (\Throwable $e) {
        $__heroStats = collect();
    }
@endphp
<section class="py-8 sm:py-10 relative overflow-hidden" aria-label="Sayzio at a glance">
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="reveal trust-band-card px-4 py-5 sm:px-8 sm:py-6">

            {{-- Stats row (shown only when there are admin-seeded stats) --}}
            @if($__heroStats->isNotEmpty())
            <div class="flex flex-col lg:flex-row lg:items-center gap-5 lg:gap-8">
                <div class="flex items-center gap-3 shrink-0">
                    <div class="flex -space-x-2" aria-hidden="true">
                        @foreach(['#3d6bff', '#1bd4d9', '#e94e8c', '#ff8a3c'] as $c)
                            <span class="w-7 h-7 rounded-full border-2 border-white/30"
                                  style="background: {{ $c }};"></span>
                        @endforeach
                    </div>
                    <div class="text-xs sm:text-sm leading-tight">
                        <span class="font-semibold text-white">AI-built pages, trusted worldwide</span>
                        <span class="block text-gray-400">by creators, brands &amp; teams</span>
                    </div>
                </div>

                <div class="hidden lg:block w-px h-12 tb-divider shrink-0"></div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-5 flex-1">
                    @foreach($__heroStats as $i => $stat)
                        @php $target = $stat->numericTarget(); @endphp
                        <div class="reveal rd-{{ ($i % 4) + 1 }} text-center sm:text-left">
                            <div class="text-xl sm:text-2xl font-extrabold tracking-tight grad-text leading-none whitespace-nowrap">
                                <span class="js-stat-count"
                                      data-target="{{ $target !== null ? (int) $target : '' }}"
                                      data-display="{{ $stat->value }}"
                                      data-duration="1600">{{ $target !== null ? '0' : $stat->value }}</span><span class="text-white/80">{{ $stat->suffix }}</span>
                            </div>
                            <div class="mt-1 text-[10px] sm:text-[11px] text-gray-400 uppercase tracking-wider leading-tight">{{ $stat->label }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-5 pt-5 tb-sep"></div>
            @endif

            {{-- Security / reliability signals row — always visible --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @foreach([
                    ['fa-shield-halved', '99.9% uptime',  'Multi-region edge',      'var(--c1)'],
                    ['fa-lock',          'TLS 1.3',        'End-to-end encrypted',   'var(--c2)'],
                    ['fa-user-shield',   'GDPR-ready',     'EU/UK SCCs in place',    'var(--c3)'],
                    ['fa-server',        'Daily backups',  '30-day retention',       'var(--c4)'],
                ] as [$icon, $title, $sub, $color])
                    <div class="flex items-center gap-2.5">
                        <span class="tb-chip w-8 h-8 rounded-xl flex items-center justify-center shrink-0" style="color: {{ $color }};"><i class="fas {{ $icon }} text-xs"></i></span>
                        <div class="min-w-0">
                            <div class="text-xs font-bold text-white leading-tight">{{ $title }}</div>
                            <div class="text-[10px] text-gray-400 leading-tight">{{ $sub }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

        </div>
    </div>
</section>

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
            else el.textContent = display;
        };
        requestAnimationFrame(tick);
    };
    const els = document.querySelectorAll('.js-stat-count');
    if (reduce || !('IntersectionObserver' in window)) {
        els.forEach(el => { el.textContent = el.dataset.display || el.textContent; });
        return;
    }
    const io = new IntersectionObserver((entries) => {
        entries.forEach(en => {
            if (en.isIntersecting) {
                animate(en.target);
                io.unobserve(en.target);
            }
        });
    }, { threshold: 0.4 });
    els.forEach(el => io.observe(el));
})();
</script>
