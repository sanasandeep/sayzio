{{--
    Reusable "Discover Events" promo band — the same blurred-photo, always-dark
    hero treatment as the /events directory page's hero, slimmed down into a
    compact 2-3 slide featured-events strip + a CTA into the full directory.
    Data ($heroBandEvents) is supplied by EventsHeroBandComposer (composed on
    THIS view), so any page can @include it with no controller wiring.

    Intentionally scoped with its own `ehb-` prefixed classes (rather than
    reusing events-directory.blade.php's .events-hero/.hero-slide names) so
    the two never collide if ever rendered on the same page.
--}}
@if(isset($heroBandEvents) && $heroBandEvents->isNotEmpty())
    {{-- Synchronous body-class hook: added before any layout paint so the
         companion CSS rule (.has-ehb-band .mkt-site-main) takes effect without
         a flash. document.body is safe here — the opening <body> tag is already
         in the parsed DOM by the time this partial renders. --}}
    <script>document.body.classList.add('has-ehb-band');</script>

    {{-- Inlined (not @push('head')) because this partial is included after
         the layout already flushes @stack('head'); a pushed style here would
         silently never render. --}}
    <style>
        .ehb-band {
            position: relative;
            background-color: #0b0e16;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            overflow: hidden;
            /* Pull the band flush with the top of the viewport so it sits
               behind the floating nav, eliminating the body-background gap that
               appears because this band lives outside <main> and therefore
               misses .mkt-site-main's own pull-up.  Compensate with top
               padding so the band's content still clears the nav. */
            margin-top: calc(-1 * var(--mkt-nav-h));
            padding-top: calc(var(--mkt-nav-h) + 2rem);    /* base ≈ py-8 top */
        }
        @media (min-width: 640px) {
            .ehb-band { padding-top: calc(var(--mkt-nav-h) + 2.5rem); } /* base ≈ py-10 top */
        }
        /* When the band is present it occupies the visual role that
           .mkt-site-main's negative margin normally fills, so cancel <main>'s
           pull-up to prevent it from sliding up into the band. */
        .has-ehb-band .mkt-site-main { margin-top: 0; }
        .ehb-band::before {
            content: '';
            position: absolute; inset: -20px;
            background-image: url('{{ asset('images/events/events-hero-bg.webp') }}');
            background-size: cover;
            background-position: center 35%;
            background-repeat: no-repeat;
            filter: blur(3px) saturate(1.05);
            transform: scale(1.06);
            z-index: 0;
        }
        .ehb-band::after {
            content: '';
            position: absolute; inset: 0;
            background:
                linear-gradient(180deg, rgba(6,8,18,0.80) 0%, rgba(6,8,18,0.90) 55%, rgba(6,8,18,0.97) 100%),
                radial-gradient(1200px 400px at 15% -10%, rgba(140,165,255,0.16), transparent 60%);
            z-index: 1;
        }
        .ehb-band > * { position: relative; z-index: 2; }
        /* Theme-aware band: the dark gradient above is the dark-mode look;
           in light mode the same blurred photo sits under a LIGHT gradient
           and the sitewide light-mode remap darkens the Tailwind text-white
           utilities inside the band. Only labels on saturated fills (CTA,
           price badge) re-assert white below. */
        html.light-mode .ehb-band {
            background-color:#eef2f9;
            border-bottom-color: rgba(15,23,42,0.08);
        }
        html.light-mode .ehb-band::before { filter: blur(3px) saturate(1.02) brightness(1.08); }
        html.light-mode .ehb-band::after {
            background:
                linear-gradient(180deg, rgba(244,247,252,0.86) 0%, rgba(246,249,253,0.92) 55%, rgba(248,250,252,0.985) 100%),
                radial-gradient(1200px 400px at 15% -10%, rgba(61,107,255,0.10), transparent 60%);
        }
        html.light-mode .ehb-cta { color:#fff !important; }
        html.light-mode .ehb-band .ehb-price-badge { color:#fff !important; }

        .ehb-slide { display:none; }
        .ehb-slide.active { display:block; animation: ehbSlideIn .5s ease; }
        @keyframes ehbSlideIn { from { opacity:0; transform:scale(1.015); } to { opacity:1; transform:scale(1); } }
        .ehb-slide-media { position:relative; aspect-ratio:21/9; overflow:hidden; border-radius:1.1rem; box-shadow:0 20px 40px -16px rgba(0,0,0,0.55); }
        .ehb-slide-img { width:100%; height:100%; object-fit:cover; transition:transform .7s ease; }
        .ehb-slide:hover .ehb-slide-img { transform:scale(1.06); }
        .ehb-slide-scrim { position:absolute; inset:0; background:linear-gradient(180deg, rgba(8,10,20,0) 32%, rgba(6,8,16,0.6) 68%, rgba(4,5,12,0.94) 100%); }
        .ehb-slide-content { position:absolute; left:0; right:0; bottom:0; padding:.9rem 1.2rem 1.1rem; }
        .ehb-slide-badge { display:inline-flex; align-items:center; font-size:.62rem; font-weight:800; text-transform:uppercase; letter-spacing:.04em; color:#fff; padding:.28rem .65rem; border-radius:999px; background:linear-gradient(135deg,#3d6bff,#6e61ff); box-shadow:0 6px 16px -2px rgba(61,107,255,0.55); margin-bottom:.5rem; }
        .ehb-slide-date { font-size:.72rem; color:rgba(255,255,255,0.7); margin-bottom:.2rem; }
        .ehb-slide-title { font-size:1.1rem; font-weight:800; color:#fff; line-height:1.28; text-shadow:0 2px 14px rgba(0,0,0,0.5); }
        @media (min-width: 640px) { .ehb-slide-title { font-size:1.25rem; } }
        .ehb-dot { width:6px; height:6px; border-radius:999px; background:rgba(255,255,255,0.3); transition:all .25s ease; }
        .ehb-dot.active { background:linear-gradient(90deg,#3d6bff,#6e61ff); width:18px; }
        .ehb-cta { background:#3d6bff; }
        .ehb-cta:hover { background:#2342c7; }
        .ehb-price-badge { box-shadow:0 6px 16px -4px rgba(0,0,0,0.35); }
        /* Light-mode variants of the card scrim + the bespoke overlay text
           it carries (not Tailwind utilities, so the sitewide remap can't
           reach them): a white gradient with dark text/date. The "Featured"
           badge keeps its white label on the blue-violet gradient pill. */
        html.light-mode .ehb-slide-media { box-shadow:0 20px 40px -16px rgba(15,23,42,0.25); }
        html.light-mode .ehb-slide-scrim { background:linear-gradient(180deg, rgba(248,250,252,0) 32%, rgba(248,250,252,0.66) 68%, rgba(255,255,255,0.94) 100%); }
        html.light-mode .ehb-slide-date { color:rgba(15,23,42,0.6); }
        html.light-mode .ehb-slide-title { color:#0f172a; text-shadow:0 2px 14px rgba(255,255,255,0.6); }
        html.light-mode .ehb-dot { background:rgba(15,23,42,0.25); }
    </style>

    <div class="ehb-band text-white px-4 py-8 sm:py-10">
        <div class="max-w-5xl mx-auto">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-5">
                <div>
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-blue-300">
                        <i class="fas fa-calendar-star"></i> Discover Events
                    </span>
                    <h2 class="mt-1 text-xl sm:text-2xl font-extrabold tracking-tight">Happening soon on {{ config('app.name') }}</h2>
                </div>
                <a href="{{ route('events.index') }}" class="ehb-cta inline-flex items-center justify-center gap-1.5 self-start sm:self-auto px-5 py-2.5 rounded-full text-sm font-bold text-white whitespace-nowrap">
                    Browse all events <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>

            @php
                // Tailwind's build-time class scanner needs literal class
                // names in the source — an interpolated `sm:grid-cols-{{ n }}`
                // would never be generated, so map the count to a full string.
                $ehbGridColsClass = match (min(3, $heroBandEvents->count())) {
                    1 => 'sm:grid-cols-1',
                    2 => 'sm:grid-cols-2',
                    default => 'sm:grid-cols-3',
                };
            @endphp
            <div x-data="ehbHeroSlider({{ $heroBandEvents->count() }})" x-init="start()" class="grid grid-cols-1 {{ $ehbGridColsClass }} gap-4">
                @foreach($heroBandEvents as $hi => $hero)
                    @php
                        $hIcs = $hero->icsData;
                        $hTiers = $hero->eventTicketTiers->sortBy('price_cents')->values();
                        if ($hTiers->isEmpty()) {
                            $hPriceLabel = 'Free RSVP';
                            $hPriceIsFree = true;
                        } else {
                            $hLowest = $hTiers->first();
                            $hHasRange = $hTiers->count() > 1 && (int) $hTiers->last()->price_cents !== (int) $hLowest->price_cents;
                            $hPriceLabel = ($hHasRange ? 'From ' : '') . $hLowest->priceLabel();
                            $hPriceIsFree = $hLowest->isFree() && !$hHasRange;
                        }
                    @endphp
                    <a href="{{ url('/' . $hero->alias) }}" class="ehb-slide {{ $hi === 0 ? 'active' : '' }} sm:!block" data-slide="{{ $hi }}">
                        <div class="ehb-slide-media">
                            @if($hIcs && $hIcs->cover_image_url)
                                <img src="{{ $hIcs->cover_image_url }}" alt="{{ $hero->title }}" class="ehb-slide-img">
                            @else
                                <img src="{{ asset('images/events/event-cover-placeholder.svg') }}" alt="{{ $hero->title }}" class="ehb-slide-img">
                            @endif
                            <div class="ehb-slide-scrim"></div>
                            <span class="ehb-price-badge inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold {{ $hPriceIsFree ? 'bg-emerald-500 text-white' : 'text-white' }}"
                                  style="position:absolute; top:.75rem; right:.75rem; {{ $hPriceIsFree ? '' : 'background:rgba(61,107,255,0.92);' }}">
                                {{ $hPriceLabel }}
                            </span>
                            <div class="ehb-slide-content">
                                <span class="ehb-slide-badge"><i class="fas fa-star mr-1"></i> Featured</span>
                                @if($hIcs && $hIcs->start_date)
                                    <div class="ehb-slide-date"><i class="far fa-clock mr-1"></i>{{ $hIcs->start_date->format('D, M j · g:i A') }}</div>
                                @endif
                                <h3 class="ehb-slide-title line-clamp-2">{{ $hero->title }}</h3>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
            @if($heroBandEvents->count() > 1)
                <div class="flex items-center justify-center gap-1.5 mt-4 sm:hidden">
                    @foreach($heroBandEvents as $hi => $hero)
                        <button type="button" class="ehb-dot {{ $hi === 0 ? 'active' : '' }}" @click.prevent="go({{ $hi }})"></button>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        function ehbHeroSlider(count) {
            return {
                idx: 0,
                count: count,
                timer: null,
                start() {
                    // Desktop shows all slides side-by-side (grid); the
                    // rotating single-card view is mobile-only, matching the
                    // sm:!block override on .ehb-slide.
                    if (this.count <= 1 || window.innerWidth >= 640) return;
                    this.timer = setInterval(() => this.go((this.idx + 1) % this.count), 5000);
                },
                go(i) {
                    this.idx = i;
                    this.$el.querySelectorAll('.ehb-slide').forEach((el, k) => el.classList.toggle('active', k === i));
                    this.$el.querySelectorAll('.ehb-dot').forEach((el, k) => el.classList.toggle('active', k === i));
                    if (this.timer) { clearInterval(this.timer); this.start(); }
                },
            };
        }
    </script>
    @endpush
@endif
