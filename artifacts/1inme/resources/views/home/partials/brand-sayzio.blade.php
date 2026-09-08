{{--
    "1IN.ME is Sayzio" banner.

    This used to be a full-height section: a giant typographic backdrop, two
    large cards joined by an animated energy line, a circular "is" pill and a
    four-card pillar grid, around 850px tall to carry one sentence of meaning.
    It is a banner, so it is a banner now: one strip, one line of brand, one
    line of copy and four small proof points, about 230px.

    Nothing that mattered was dropped. The two card descriptions are combined
    into the single sentence they always added up to, and the four pillars
    keep their words and their colours at a size that suits them.
--}}
<style>
    /* ===== "1IN.ME is Sayzio" banner (bs- = brand-sayzio) ===== */
    .bs-banner {
        display: flex; flex-wrap: wrap; align-items: stretch; gap: 0;
        border-radius: 16px;
        background: rgba(255,255,255,.04);
        border: 1px solid rgba(255,255,255,.10);
    }
    .bs-banner > * { padding: 18px 22px; }
    html.light-mode .bs-banner { background: #F7F8FC; border-color: #E6E8F2; }

    .bs-lockup {
        display: flex; align-items: center; gap: 9px; flex: none; margin: 0;
        align-self: center;
        font-size: 21px; font-weight: 800; letter-spacing: -.02em; line-height: 1;
    }
    /* Sized by height, not into a square box. The 1IN.ME mark is 256x199 and
       the Zio icon is 256x256, so a shared square box rendered the first one
       26 wide by 20 tall and it read as the smaller of the two. Matching
       their heights is what makes them look like a pair. */
    .bs-lockup img { height: 24px; width: auto; object-fit: contain; display: block; }
    .bs-is-word { font-style: italic; font-weight: 600; opacity: .45; font-size: .8em; padding: 0 4px; }

    /* Each brand word needs the plate the other mode would otherwise swallow:
       white text needs a dark plate in light mode, black text needs a light
       plate in dark mode. */
    .bs-word { display: inline-block; line-height: 1; padding: .06em .22em; border-radius: .32em; }
    .bs-word--id { color: #fff; background: #140a22; }
    .bs-word--zio { color: #0a0a12; background: #fff; }
    html.light-mode .bs-word--zio { background: none; padding: 0; }

    /* Capped so the sentence sets in two comfortable lines instead of
       stretching 840px to the far edge of the banner. */
    .bs-copy {
        flex: 1 1 340px; min-width: 0; max-width: 62ch; margin: 0; align-self: center;
        font-size: 14px; line-height: 1.5; color: #9aa1c8;
        border-left: 1px solid rgba(255,255,255,.10);
    }
    html.light-mode .bs-copy { color: #4E5680; border-left-color: #E6E8F2; }
    @media (max-width: 900px) {
        .bs-copy { border-left: 0; border-top: 1px solid rgba(255,255,255,.10); }
        html.light-mode .bs-copy { border-top-color: #E6E8F2; }
    }

    .bs-pillars {
        display: flex; flex-wrap: wrap; gap: 8px; width: 100%;
        border-top: 1px solid rgba(255,255,255,.10);
    }
    html.light-mode .bs-pillars { border-top-color: #E6E8F2; }
    .bs-pillar {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 7px 12px; border-radius: 9999px;
        font-size: 12.5px; font-weight: 700; color: #fff;
        border: 1px solid rgba(255,255,255,.10);
    }
    html.light-mode .bs-pillar { border-color: #E6E8F2; color: #0B1033; }
    .bs-pillar i { font-size: 11px; }
    .bs-pillar span { font-weight: 500; opacity: .6; }

    @media (max-width: 720px) {
        .bs-banner > * { padding: 16px 18px; }
        .bs-lockup { font-size: 19px; }
    }
</style>

<section class="bs-section relative py-10 lg:py-12" aria-labelledby="bs-h">
    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-10 xl:px-12">
        <div class="bs-banner" data-anim="fade-up">
            <h2 id="bs-h" class="bs-lockup">
                <img src="{{ asset('branding/1inme-mark.png') }}" alt="" width="52" height="40" decoding="async">
                <span class="bs-word bs-word--id">1IN.ME</span>
                <span class="bs-is-word">is</span>
                <img src="{{ asset('branding/sayzio-card-icon.png') }}" alt="" width="52" height="52" decoding="async">
                <span class="bs-word bs-word--zio">Sayzio</span>
            </h2>

            <p class="bs-copy">One link for everything you share, with the engine behind it: analytics, AI, automation and rock-solid delivery at any scale.</p>

            <div class="bs-pillars">
                @foreach([
                    ['fa-rocket',        '#3d6bff', 'Built for',      'Performance'],
                    ['fa-shield-halved', '#1bd4d9', 'Engineered for', 'Reliability'],
                    ['fa-cubes',         '#e94e8c', 'Designed for',   'Scalability'],
                    ['fa-lightbulb',     '#ff8a3c', 'Driven by',      'Innovation'],
                ] as $p)
                    <span class="bs-pillar">
                        <i class="fas {{ $p[0] }}" style="color:{{ $p[1] }}" aria-hidden="true"></i>
                        <span>{{ $p[2] }}</span>{{ $p[3] }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>
</section>
