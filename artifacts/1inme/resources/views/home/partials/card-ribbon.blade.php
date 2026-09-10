{{--
    The CTA card's gradient ribbon, cut down to fit a feature card.

    Sana asked for one card in each section to carry "background gradient just
    similar to the CTA section". Reusing the shape rather than inventing a
    second one is the point: the page already says "this is the thing to look
    at" with a hard-edged gradient bleeding off an edge, and a card that
    borrows that shape is understood immediately.

    What changes is the SIZE and the EDGE. On the full-width CTA card the
    ribbon takes half the card and the copy has the other half. A feature card
    is a third of a row and its copy needs all of it, so this is a corner wedge
    off the top-right: the same parallel bands at the same angle, small enough
    to be an accent. The card's own icon sits top-LEFT, so the two never meet.

    Props:
      $variant string  'cool' (default, blue to cyan) | 'warm' (amber to magenta)

    Each instance needs its own gradient ids -- two on one page and the second
    silently paints with the first one's stops, which is a bug that only shows
    up once someone adds a second card.
--}}
@php
    $crVariant = ($variant ?? 'cool') === 'warm' ? 'warm' : 'cool';
    $crUid = 'cr' . substr(md5($crVariant . uniqid('', true)), 0, 8);
@endphp

@once
<style>
    .card-ribbon {
        /* z-index -1, not 0.
           Within a stacking context the element's own background paints
           first, then NEGATIVE z-index descendants, and only then the
           in-flow text. A positioned child at z-index 0 paints ABOVE that
           text, which is why the first version had to lift every sibling
           with `position: relative; z-index: 1`. That rule then overrode the
           absolute positioning of anything that already had some -- the
           How-it-works step number dropped into the flow and pushed the whole
           card down. Sitting the ribbon behind the content instead needs no
           rule on the siblings at all. */
        position: absolute; top: 0; right: 0; z-index: -1;
        /* Sized to clear the copy. The card's icon sits top-left and occupies
           the first ~84px; nothing else reaches the right-hand side until the
           eyebrow. At 58%/62% the wedge's lower tip came down behind the
           headline -- legible, because the copy is lifted above it, but the
           CTA card's rule is that the gradient is never underneath a word,
           and an accent that breaks it is just a stain. */
        width: min(52%, 226px); height: min(30%, 112px);
        pointer-events: none;
        /* Fades into the card rather than stopping at a hard horizontal line:
           the ribbon's own edges are meant to be the sharp ones, and a wedge
           that simply ends looks clipped rather than bled. */
        -webkit-mask-image: linear-gradient(215deg, #000 24%, transparent 78%);
                mask-image: linear-gradient(215deg, #000 24%, transparent 78%);
    }
    .card-ribbon svg { width: 100%; height: 100%; display: block; }
    /* The host has three jobs, and it cannot be assumed to be doing any of
       them already: some of these cards carry `relative overflow-hidden` and
       some do not. Without `position` the wedge anchors to the nearest
       positioned ancestor -- the SECTION -- and at z-index -1 it then painted
       behind the whole band, appearing as a stray gradient floating beside
       the heading with nothing in the card at all. Without `overflow` it
       would spill past the rounded corner, and without `isolation` the -1
       could slide behind an ancestor's background rather than the card's. */
    .card-ribbon-host { position: relative; overflow: hidden; isolation: isolate; }
</style>
@endonce

<span class="card-ribbon" aria-hidden="true">
    <svg viewBox="0 0 260 130" preserveAspectRatio="xMaxYMin slice" role="presentation" focusable="false">
        <defs>
            <linearGradient id="{{ $crUid }}-a" x1="0" y1="1" x2="1" y2="0">
                @if($crVariant === 'warm')
                    <stop offset="0"   stop-color="#E94E8C"/>
                    <stop offset=".55" stop-color="#FF8A3C"/>
                    <stop offset="1"   stop-color="#FFC845"/>
                @else
                    <stop offset="0"   stop-color="#3E3AE0"/>
                    <stop offset=".55" stop-color="#3D6BFF"/>
                    <stop offset="1"   stop-color="#1BD4D9"/>
                @endif
            </linearGradient>
        </defs>
        {{-- Two bands at one angle, the second narrower and lighter. Parallel
             edges are what make it read as a ribbon rather than two shapes. --}}
        <polygon fill="url(#{{ $crUid }}-a)" points="70,-10 280,-10 280,150"/>
        <polygon fill="#fff" opacity=".16" points="128,-10 168,-10 280,92 280,140"/>
    </svg>
</span>
