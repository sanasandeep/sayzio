{{--
    Proof band — the one section under the hero that says who Sayzio is and
    how much of it there is.

    This used to be two adjacent sections doing halves of the same job: the
    "1IN.ME is Sayzio" banner (formerly home/partials/brand-sayzio.blade.php,
    deleted with this change) and a
    separate stats/security card. They sat one after the other on the same
    page, each in its own bordered box, so the brand line and the numbers that
    prove it read as unrelated. They are one statement, so they are one
    section now: lockup, headline, the numbers, and the reliability signals
    underneath.

    Layout follows stripe.com's "The backbone of global commerce" band — a
    centred claim over a single hairline-divided row of large figures — which
    is what the boxed two-card version was reaching for.

    Numbers come from the admin-editable SiteStat source of truth
    (Admin → Site stats), never hard-coded in markup, and animate through the
    shared `.js-stat-count` runtime at the bottom of this file. The `.reveal`
    entrance is the page-wide one (home.blade.php), which adds `.visible` as
    each element scrolls in; the rule/number flourishes here hang off that
    same class rather than a second observer.

    The four "Built for Performance / Reliability / Scalability / Innovation"
    pillars from the old banner are deliberately not carried over: they were a
    second four-item trust row directly above this one's four-item security
    row, saying something vaguer than "99.9% uptime". One row of concrete
    signals beats two rows where one is adjectives.
--}}
<style>
    /* ===== Proof band (pb- = proof band) ===== */
    .pb-band {
        --pb-ink:      #ffffff;
        --pb-muted:    #9aa1c8;
        --pb-faint:    #6f77a3;
        --pb-rule:     rgba(255,255,255,.12);
        --pb-rule-lit: rgba(255,255,255,.28);
        --pb-chip:     rgba(255,255,255,.05);
        --pb-ground:   transparent;
        position: relative;
        background: var(--pb-ground);
    }
    html.light-mode .pb-band {
        --pb-ink:      #0B1033;
        --pb-muted:    #4E5680;
        --pb-faint:    #757CA6;
        --pb-rule:     #E4E7F4;
        --pb-rule-lit: #C3CAE8;
        --pb-chip:     rgba(15,23,42,.05);
        /* Stripe's band lifts off the page on a barely-there tint rather than
           a border. Same trick: the section reads as its own surface without
           drawing a box around itself. */
        --pb-ground:   linear-gradient(180deg, rgba(246,248,255,0) 0%, #F6F8FF 12%, #F6F8FF 88%, rgba(246,248,255,0) 100%);
    }

    /* ---- Brand lockup (the eyebrow) ---- */
    .pb-lockup {
        display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        margin: 0 auto; font-size: 17px; font-weight: 800;
        letter-spacing: -.02em; line-height: 1; color: var(--pb-ink);
    }
    /* Sized by height, not into a square box: the 1IN.ME mark is 256x199 and
       the Zio icon is 256x256, so a shared square box renders the first one
       visibly smaller. Matching heights is what makes them read as a pair. */
    .pb-lockup img { height: 20px; width: auto; object-fit: contain; display: block; }
    .pb-is { font-style: italic; font-weight: 600; opacity: .45; font-size: .8em; padding: 0 2px; }
    /* Both brand words are plain text in the page's own ink. They used to sit
       on plates -- white-on-black for 1IN.ME, black-on-white for Sayzio --
       which is how each brand's logo is locked up on its own, but side by side
       in a sentence the plates read as two badges rather than as one line
       saying that one brand IS the other. The marks beside each word already
       carry the colour; the words only have to be readable. */
    .pb-word { display: inline-block; line-height: 1; color: var(--pb-ink); }

    /* ---- Claim ---- */
    .pb-head {
        margin: 22px auto 0;
        /* Wide enough that the claim sets in TWO lines on a desktop width,
           the way Stripe's does. At 16ch it broke into three ("The engine /
           behind every / link you share"), which reads as a stack rather than
           a statement. `text-wrap: balance` then evens the two lines out. */
        max-width: 19ch;
        font-size: clamp(30px, 5.2vw, 54px);
        font-weight: 800; line-height: 1.04; letter-spacing: -.035em;
        text-wrap: balance; color: var(--pb-ink);
    }
    .pb-sub {
        margin: 16px auto 0; max-width: 56ch;
        font-size: 15px; line-height: 1.6; color: var(--pb-muted);
        text-wrap: pretty;
    }

    /* ---- Figures ---- */
    .pb-figures {
        margin-top: 46px;
        display: grid; grid-template-columns: repeat(2, minmax(0,1fr));
        border-top: 1px solid var(--pb-rule);
    }
    @media (min-width: 720px) { .pb-figures { grid-template-columns: repeat(4, minmax(0,1fr)); } }

    .pb-fig { padding: 30px 18px 4px; position: relative; text-align: center; }
    @media (min-width: 720px) { .pb-fig { text-align: left; padding-inline: 26px; } }
    .pb-fig:first-child { padding-inline-start: 0; }
    .pb-fig:last-child  { padding-inline-end: 0; }
    /* Column dividers as an inset pseudo-rule rather than border-left, so the
       first column in each row never carries one and the line stops short of
       the band's own top rule. */
    .pb-fig::before {
        content: ""; position: absolute; left: 0; top: 26px; bottom: 10px; width: 1px;
        background: var(--pb-rule);
    }
    .pb-fig:nth-child(odd)::before { content: none; }
    @media (min-width: 720px) {
        .pb-fig:nth-child(odd)::before { content: ""; }
        .pb-fig:first-child::before { content: none; }
    }

    .pb-num {
        font-size: clamp(30px, 4.4vw, 50px);
        font-weight: 800; line-height: 1; letter-spacing: -.04em;
        color: var(--pb-ink);
        /* The figures line up in columns and count upward, so the digits have
           to hold their own width or every column jitters as it animates. */
        font-variant-numeric: tabular-nums;
        font-feature-settings: "tnum" 1;
        white-space: nowrap;
    }
    /* Stripe keeps one figure in full ink and lets the rest recede. Same idea,
       but the lead figure carries the brand gradient instead of just weight. */
    .pb-fig:first-child .pb-num {
        /* Holds the brand blue across most of the figure and only resolves to
           cyan at the tail. An even blue→cyan ramp landed the midpoint on the
           widest digits, so the whole number read cyan and sat lighter than
           the three ink figures beside it. */
        background: linear-gradient(96deg, #2f52d8, #3d6bff 34%, #1bd4d9 88%, #22d3ee);
        -webkit-background-clip: text; background-clip: text;
        -webkit-text-fill-color: transparent; color: transparent;
    }
    .pb-fig:not(:first-child) .pb-num { color: var(--pb-ink); opacity: .82; }
    .pb-suffix { opacity: .5; font-weight: 700; }
    .pb-fig:first-child .pb-suffix { -webkit-text-fill-color: currentColor; color: #1bd4d9; opacity: .9; }

    .pb-label {
        margin-top: 10px; font-size: 11px; font-weight: 600;
        line-height: 1.35; color: var(--pb-faint);
        text-transform: uppercase; letter-spacing: .12em;
    }

    /* ---- Reliability strip (quiet by design: the figures are the point) ---- */
    .pb-signals {
        margin-top: 38px; padding-top: 20px;
        border-top: 1px solid var(--pb-rule);
        display: flex; flex-wrap: wrap; justify-content: center;
        gap: 10px 26px;
    }
    .pb-signal {
        display: inline-flex; align-items: baseline; gap: 7px;
        font-size: 12.5px; color: var(--pb-faint);
    }
    .pb-signal i { font-size: 10px; opacity: .75; }
    .pb-signal b { font-weight: 700; color: var(--pb-muted); }

    /* ---- Motion ----
       Three small things, each tied to the shared `.reveal` observer rather
       than a second one: the top rule draws out from the centre, a light
       passes across it once, and each figure lifts as its column arrives.
       All of it is decoration on content that is already readable at rest. */
    .pb-figures { position: relative; }
    .pb-figures::after {
        content: ""; position: absolute; left: 0; right: 0; top: -1px; height: 1px;
        background: linear-gradient(90deg, transparent, var(--pb-rule-lit), transparent);
        transform: scaleX(0); transform-origin: 50% 50%;
        transition: transform 1.1s cubic-bezier(.16,1,.3,1) .15s;
    }
    .reveal.visible .pb-figures::after { transform: scaleX(1); }

    .pb-num, .pb-label { transition: opacity .6s ease, transform .6s cubic-bezier(.16,1,.3,1); }
    html.js .reveal.reveal-ready .pb-num,
    html.js .reveal.reveal-ready .pb-label { opacity: 0; transform: translateY(12px); }
    html.js .reveal.reveal-ready.visible .pb-num,
    html.js .reveal.reveal-ready.visible .pb-label { opacity: 1; transform: none; }
    html.js .reveal.reveal-ready.visible .pb-fig:nth-child(1) .pb-num { transition-delay: .10s }
    html.js .reveal.reveal-ready.visible .pb-fig:nth-child(2) .pb-num { transition-delay: .18s }
    html.js .reveal.reveal-ready.visible .pb-fig:nth-child(3) .pb-num { transition-delay: .26s }
    html.js .reveal.reveal-ready.visible .pb-fig:nth-child(4) .pb-num { transition-delay: .34s }
    html.js .reveal.reveal-ready.visible .pb-fig:nth-child(1) .pb-label { transition-delay: .16s }
    html.js .reveal.reveal-ready.visible .pb-fig:nth-child(2) .pb-label { transition-delay: .24s }
    html.js .reveal.reveal-ready.visible .pb-fig:nth-child(3) .pb-label { transition-delay: .32s }
    html.js .reveal.reveal-ready.visible .pb-fig:nth-child(4) .pb-label { transition-delay: .40s }

    /* The lead figure's gradient drifts, very slowly, so the band has one
       living element without anything moving in the reader's way. */
    .pb-fig:first-child .pb-num {
        background-size: 220% 100%;
        animation: pbDrift 9s ease-in-out infinite;
    }
    @keyframes pbDrift { 0%,100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }

    @media (prefers-reduced-motion: reduce) {
        .pb-figures::after { transform: scaleX(1) !important; transition: none !important; }
        .pb-num, .pb-label {
            opacity: 1 !important; transform: none !important;
            transition: none !important; animation: none !important;
        }
        .pb-fig:first-child .pb-num { animation: none !important; }
    }

    @media (max-width: 719px) {
        .pb-fig { padding-inline: 12px; }
        .pb-signals { gap: 8px 18px; }
    }
</style>

@php
    try {
        $__heroStats = \App\Modules\Admin\Models\SiteStat::cachedActive()->take(4)->values();
    } catch (\Throwable $e) {
        $__heroStats = collect();
    }
@endphp

<section class="pb-band relative py-14 sm:py-20" aria-labelledby="pb-h">
    <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="reveal text-center">

            {{-- Brand lockup. The heading element is the claim below, not this
                 line, so the marks stay decorative and the alt text stays empty. --}}
            <p class="pb-lockup">
                <img src="{{ asset('branding/1inme-mark.png') }}" alt="" width="52" height="40" decoding="async">
                <span class="pb-word">1IN.ME</span>
                <span class="pb-is">is</span>
                <img src="{{ asset('branding/sayzio-card-icon.png') }}" alt="" width="52" height="52" decoding="async">
                <span class="pb-word">Sayzio</span>
            </p>

            <h2 id="pb-h" class="pb-head">The engine behind every link you share</h2>

            {{-- No em dash in this line: the em-dash copy guard bans U+2014
                 from user-visible marketing copy, and this partial lives under
                 resources/views/public/, which the guard scans. A colon does
                 the same work here. --}}
            <p class="pb-sub">One link for everything you share, with the engine behind it: analytics, AI, automation and rock-solid delivery at any scale.</p>

            @if($__heroStats->isNotEmpty())
                <div class="pb-figures">
                    @foreach($__heroStats as $stat)
                        @php $target = $stat->numericTarget(); @endphp
                        <div class="pb-fig">
                            <div class="pb-num">
                                {{-- data-target/data-display/.js-stat-count are the
                                     contract the count-up runtime below reads; the
                                     rendered text is the final value so the figure is
                                     correct with JS off. --}}
                                <span class="js-stat-count"
                                      data-target="{{ $target !== null ? (int) $target : '' }}"
                                      data-display="{{ $stat->value }}"
                                      data-duration="1800">{{ $target !== null ? '0' : $stat->value }}</span><span class="pb-suffix">{{ $stat->suffix }}</span>
                            </div>
                            <div class="pb-label">{{ $stat->label }}</div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="pb-signals">
                @foreach([
                    ['fa-shield-halved', '99.9% uptime',  'multi-region edge'],
                    ['fa-lock',          'TLS 1.3',       'end-to-end encrypted'],
                    ['fa-user-shield',   'GDPR-ready',    'EU/UK SCCs in place'],
                    ['fa-server',        'Daily backups', '30-day retention'],
                ] as [$icon, $title, $sub])
                    <span class="pb-signal">
                        <i class="fas {{ $icon }}" aria-hidden="true"></i>
                        <b>{{ $title }}</b>{{ $sub }}
                    </span>
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
