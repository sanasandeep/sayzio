{{--
    Full-width CTA card, Stripe's shape.

    A pale card with everything readable stacked on the LEFT, and a single
    bold gradient form running off the RIGHT edge. That asymmetry is the
    whole idea: the eye lands on the colour, then travels left into the
    words, and the card stops being a rectangle of text with a button in it.

    It replaces the previous treatment, which was a glass card with two big
    blurred colour discs floating behind the copy. Those discs read as haze
    rather than shape, put coloured light directly behind the text, and were
    the same ambient wash being taken off the rest of the page. The ribbon
    here is the opposite: hard-edged, confined to its own side of the card,
    and never underneath a word.

    Props (all optional except heading):
      $eyebrow   string   small uppercase line above the heading
      $heading   string   the headline (HTML allowed; keep it plain)
      $body      string   one supporting sentence
      $primary   array    ['label','href'] or ['label','onclick']
      $secondary array    ['label','href']
      $ribbon    string   'cool' (blue to cyan) | 'warm' (magenta to amber)
      $side      string   'right' (default) | 'left' -- which edge the ribbon
                          bleeds off. The copy takes the other side, so this
                          mirrors the card rather than just moving the artwork.
      $id        string   id for the wrapper, when the section needs an anchor

    The ribbon is an inline SVG rather than skewed divs so the bands stay
    crisp at any width and the whole form can be given one gradient; it is
    aria-hidden because it says nothing the copy does not.
--}}
@php
    $ctaEyebrow   = $eyebrow   ?? null;
    $ctaHeading   = $heading   ?? '';
    $ctaBody      = $body      ?? null;
    $ctaPrimary   = $primary   ?? null;
    $ctaSecondary = $secondary ?? null;
    $ctaRibbon    = ($ribbon ?? 'cool') === 'warm' ? 'warm' : 'cool';
    // Which edge the ribbon bleeds off. The copy always takes the other side,
    // so this flips the card's whole composition rather than just the artwork.
    $ctaSide      = ($side ?? 'right') === 'left' ? 'left' : 'right';
    // Two cards can sit on one page, and each needs its own gradient ids or
    // the second one silently reuses the first one's stops.
    $ctaUid = 'cta' . substr(md5($ctaHeading . $ctaRibbon . uniqid('', true)), 0, 8);
@endphp

@once
<style>
    /* ===== Stripe-shape CTA card (xcta-) =====
       Prefixed `xcta-` rather than the obvious `cc-`: `cc-` is already the
       cookie-consent banner's prefix, and surfaces.blade.php sets padding and
       background on `.cc-card` and `.cc-btn` by name. The first draft of this
       component used it, inherited that padding -- which is why the headline
       ran under the ribbon -- and was restyling the cookie banner at the same
       time. Check a prefix before claiming it. */
    .xcta-card {
        position: relative;
        overflow: hidden;
        border-radius: 18px;
        /* Pale, not glass: the ribbon supplies the contrast, so the card
           itself steps back. Both grounds are literals with a light-mode
           override below rather than theme tokens, because this card has to
           read the same on the white page and the near-black one. */
        background: #12151F;
        border: 1px solid rgba(255,255,255,.10);
        padding: 40px;
        /* Room beside the copy for the ribbon so a long headline never runs
           under it. Collapses on narrow screens, where the ribbon becomes a
           strip along the top instead. */
        padding-right: min(46%, 520px);
    }
    html.light-mode .xcta-card {
        background: #F6F7FB;
        border-color: #E6E8F2;
    }

    /* Mirrored variant. The ribbon takes the left edge and the copy moves
       right, so two cards on one page are not the same composition twice. */
    .xcta-card--left { padding-right: 40px; padding-left: min(46%, 520px); }

    /* ---------- the lattice under the copy ----------
       The hero's ground is a faint grid on the page's own `--grid` cell, and
       everything that sits on it -- the navbar, the tiles, now the marquee --
       lands on that cell. These cards were the one large surface on the page
       with nothing behind the words at all, which is why they read as panels
       dropped onto the page rather than as part of it.

       Same variable, same 50% phase, so the card's lines are the page's lines
       carried underneath it. It is masked to fade out well before the ribbon:
       the grid is a ground for the copy, and running it under the gradient
       would be two textures fighting in the same place. */
    .xcta-grid {
        position: absolute; inset: 0; pointer-events: none;
        --xcta-line: rgba(15,23,42,.055);
        background-image:
            linear-gradient(to right,  var(--xcta-line) 1px, transparent 1px),
            linear-gradient(to bottom, var(--xcta-line) 1px, transparent 1px);
        background-size: var(--grid, 80px) var(--grid, 80px);
        background-position: 50% 0;
        -webkit-mask-image: linear-gradient(to right, #000 0%, #000 42%, transparent 68%);
                mask-image: linear-gradient(to right, #000 0%, #000 42%, transparent 68%);
    }
    html:not(.light-mode) .xcta-grid { --xcta-line: rgba(255,255,255,.055); }
    .xcta-card--left .xcta-grid {
        -webkit-mask-image: linear-gradient(to left, #000 0%, #000 42%, transparent 68%);
                mask-image: linear-gradient(to left, #000 0%, #000 42%, transparent 68%);
    }

    .xcta-eyebrow {
        font-size: 11px; font-weight: 700; letter-spacing: .2em;
        text-transform: uppercase; color: #9098AB; margin: 0 0 12px;
    }
    html.light-mode .xcta-eyebrow { color: #6B7280; }

    .xcta-h {
        margin: 0;
        font-size: clamp(26px, 3.4vw, 40px);
        font-weight: 800; line-height: 1.1; letter-spacing: -.03em;
        text-wrap: balance;
        color: #fff;
    }
    html.light-mode .xcta-h { color: #0F172A; }

    .xcta-body {
        margin: 14px 0 0; max-width: 52ch;
        font-size: 15.5px; line-height: 1.62; color: #A8B0C6;
    }
    html.light-mode .xcta-body { color: #4E5680; }

    .xcta-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 26px; }

    .xcta-btn {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 12px 22px; border-radius: 10px;
        font-size: 14.5px; font-weight: 700; white-space: nowrap;
        transition: background .2s ease, color .2s ease, border-color .2s ease;
    }
    .xcta-btn-primary { background: #3E3AE0; color: #fff; border: 1px solid transparent; }
    .xcta-btn-primary:hover { background: #322FC4; color: #fff; }
    .xcta-btn-ghost {
        background: transparent; color: #C7CEE4;
        border: 1px solid rgba(255,255,255,.16);
    }
    .xcta-btn-ghost:hover { color: #fff; border-color: rgba(255,255,255,.34); }
    html.light-mode .xcta-btn-ghost { color: #2A3350; border-color: #D9DDEC; }
    html.light-mode .xcta-btn-ghost:hover { color: #0F172A; border-color: #B9C0DA; }

    /* ---------- the ribbon ----------
       Anchored to the right edge and deliberately WIDER than the space it is
       given, so it runs off the card instead of sitting inside it like a
       picture. `overflow:hidden` on the card is what turns that overflow
       into a bleed. */
    .xcta-ribbon {
        position: absolute; top: -12%; right: -8%;
        width: min(52%, 620px); height: 124%;
        pointer-events: none;
    }
    /* Mirrored rather than redrawn: the SVG's slant is asymmetric, so a ribbon
       moved to the left edge without flipping would lean the wrong way and the
       card would look like a mistake rather than a mirror. */
    .xcta-card--left .xcta-ribbon { right: auto; left: -8%; transform: scaleX(-1); }
    .xcta-ribbon svg { width: 100%; height: 100%; display: block; }

    /* The copy sits above the ribbon in the stacking order AND is capped in
       width. The cap is belt-and-braces next to the card's padding-right: a
       padding can be overridden by a page-level rule reaching in by class
       (which is exactly what happened once already), and a headline sliding
       under the gradient is the one failure that has to not happen. */
    .xcta-copy { position: relative; z-index: 1; max-width: 54ch; }

    @media (max-width: 900px) {
        .xcta-card,
        .xcta-card--left { padding: 32px 28px; padding-top: 96px; }
        /* On a phone the card is a single column, so the ribbon moves to a
           band across the top: still the same form, still bleeding off an
           edge, but no longer competing with the copy for width. Both
           variants land in the same place -- at this width the mirror has
           nothing left to mirror. */
        .xcta-ribbon,
        .xcta-card--left .xcta-ribbon {
            top: -30%; right: -10%; left: auto;
            width: 78%; height: 150px; transform: none;
        }
        /* The grid runs the full width here, since the copy does too. */
        .xcta-grid,
        .xcta-card--left .xcta-grid {
            -webkit-mask-image: linear-gradient(to bottom, transparent 0%, #000 30%);
                    mask-image: linear-gradient(to bottom, transparent 0%, #000 30%);
        }
    }
</style>
@endonce

<div class="xcta-card{{ $ctaSide === 'left' ? ' xcta-card--left' : '' }}"@if(isset($id)) id="{{ $id }}"@endif>
    <span class="xcta-grid" aria-hidden="true"></span>
    <div class="xcta-ribbon" aria-hidden="true">
        <svg viewBox="0 0 400 460" preserveAspectRatio="xMidYMid slice" role="presentation" focusable="false">
            <defs>
                <linearGradient id="{{ $ctaUid }}-a" x1="0" y1="0" x2="1" y2="1">
                    @if($ctaRibbon === 'warm')
                        <stop offset="0"   stop-color="#FF8A3C"/>
                        <stop offset=".55" stop-color="#E94E8C"/>
                        <stop offset="1"   stop-color="#7C5CFF"/>
                    @else
                        <stop offset="0"   stop-color="#3E3AE0"/>
                        <stop offset=".55" stop-color="#3D6BFF"/>
                        <stop offset="1"   stop-color="#1BD4D9"/>
                    @endif
                </linearGradient>
                <linearGradient id="{{ $ctaUid }}-b" x1="0" y1="1" x2="1" y2="0">
                    @if($ctaRibbon === 'warm')
                        <stop offset="0" stop-color="#FFC845"/>
                        <stop offset="1" stop-color="#FF8A3C"/>
                    @else
                        <stop offset="0" stop-color="#1BD4D9"/>
                        <stop offset="1" stop-color="#6F9BFF"/>
                    @endif
                </linearGradient>
            </defs>
            {{-- Three slanted bands at the same angle: one solid form, one
                 lighter companion, one thin accent. Parallel edges are what
                 make it read as a ribbon rather than as three shapes. --}}
            <polygon fill="url(#{{ $ctaUid }}-a)" points="150,-40 400,-40 400,500 -10,500"/>
            <polygon fill="url(#{{ $ctaUid }}-b)" opacity=".55" points="250,-40 400,-40 400,300 95,500 -10,500"/>
            <polygon fill="#fff" opacity=".14" points="316,-40 356,-40 0,500 -40,500"/>
        </svg>
    </div>

    <div class="xcta-copy">
        @if($ctaEyebrow)
            <p class="xcta-eyebrow">{{ $ctaEyebrow }}</p>
        @endif

        <h2 class="xcta-h">{!! $ctaHeading !!}</h2>

        @if($ctaBody)
            <p class="xcta-body">{!! $ctaBody !!}</p>
        @endif

        @if($ctaPrimary || $ctaSecondary)
            <div class="xcta-actions">
                @if($ctaPrimary)
                    @if(!empty($ctaPrimary['onclick']))
                        <button type="button" class="xcta-btn xcta-btn-primary" onclick="{!! $ctaPrimary['onclick'] !!}">
                            {{ $ctaPrimary['label'] }} <i class="fas fa-arrow-right text-xs" aria-hidden="true"></i>
                        </button>
                    @else
                        <a href="{{ $ctaPrimary['href'] ?? '#' }}" class="xcta-btn xcta-btn-primary">
                            {{ $ctaPrimary['label'] }} <i class="fas fa-arrow-right text-xs" aria-hidden="true"></i>
                        </a>
                    @endif
                @endif

                @if($ctaSecondary)
                    <a href="{{ $ctaSecondary['href'] ?? '#' }}" class="xcta-btn xcta-btn-ghost">
                        {{ $ctaSecondary['label'] }}
                    </a>
                @endif
            </div>
        @endif
    </div>
</div>
