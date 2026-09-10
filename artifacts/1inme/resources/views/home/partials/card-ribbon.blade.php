{{--
    The corner accent one card in each section carries.

    It started as one shape stamped four times, in one colour, which is what
    made it read as a template rather than as an accent -- four cards on four
    different bands wearing the same sticker. It is four shapes and four
    palettes now.

    What they have in common is the grammar, and that is deliberate: every one
    is anchored to the top-right corner, bleeds off both edges it touches, and
    fades out towards the copy rather than stopping at a line. That is the same
    gesture the CTA card makes at full width, which is where this came from. A
    reader should recognise the family without recognising the stamp.

    The card's own icon sits top-LEFT in every one of these layouts, so nothing
    here ever meets it.

    Props:
      $shape   'wedge' | 'arc' | 'steps' | 'beam'     (default 'wedge')
      $variant 'indigo' | 'teal' | 'violet' | 'rose'  (default 'indigo')

    'cool' and 'warm' are still accepted for the variant and map to indigo and
    rose, so an old call site keeps working rather than silently rendering
    nothing.

    Each instance needs its own gradient ids -- two on one page and the second
    silently paints with the first one's stops, which is a bug that only shows
    up once someone adds a second card.
--}}
@php
    $crShapes = ['wedge', 'arc', 'steps', 'beam'];
    $crShape = in_array($shape ?? 'wedge', $crShapes, true) ? ($shape ?? 'wedge') : 'wedge';

    $crPalettes = [
        // Deep to bright, always ending on the lighter end, so every one of
        // them reads as light coming into the corner rather than as a block of
        // colour sitting in it.
        'indigo' => ['#3E3AE0', '#3D6BFF', '#1BD4D9'],
        'teal'   => ['#0E7490', '#1BD4D9', '#A5F3FC'],
        'violet' => ['#5B21B6', '#8B5CF6', '#C4B5FD'],
        // The one warm-leaning accent on the page, and deliberately the deep
        // end of it: the batch that cut the icon colours cut orange entirely,
        // and a magenta that starts at #A21C68 is a long way from the amber
        // this used to offer.
        'rose'   => ['#A21C68', '#E94E8C', '#F9A8D4'],
    ];

    $crVariant = $variant ?? 'indigo';
    // Back-compat for the first version's two names.
    $crVariant = ['cool' => 'indigo', 'warm' => 'rose'][$crVariant] ?? $crVariant;
    $crStops = $crPalettes[$crVariant] ?? $crPalettes['indigo'];

    $crUid = 'cr' . substr(md5($crShape . $crVariant . uniqid('', true)), 0, 8);
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
           the ribbon's own edges are meant to be the sharp ones, and a shape
           that simply ends looks clipped rather than bled. Each shape sets the
           angle its own geometry wants. */
        --cr-fade: 215deg;
        -webkit-mask-image: linear-gradient(var(--cr-fade), #000 24%, transparent 78%);
                mask-image: linear-gradient(var(--cr-fade), #000 24%, transparent 78%);
    }
    /* The arc sweeps around the corner rather than across it, so it wants a
       radial fade centred on that corner; a linear one cuts a chord through
       the rings. The steps stack downwards and want a steeper angle so the
       lowest one is the faintest. */
    .card-ribbon--arc {
        -webkit-mask-image: radial-gradient(120% 130% at 100% 0%, #000 34%, transparent 82%);
                mask-image: radial-gradient(120% 130% at 100% 0%, #000 34%, transparent 82%);
    }
    .card-ribbon--steps { --cr-fade: 200deg; }
    .card-ribbon--beam  { --cr-fade: 232deg; width: min(62%, 268px); height: min(26%, 96px); }
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

<span class="card-ribbon card-ribbon--{{ $crShape }}" aria-hidden="true">
    <svg viewBox="0 0 260 130" preserveAspectRatio="xMaxYMin slice" role="presentation" focusable="false">
        <defs>
            <linearGradient id="{{ $crUid }}-a" x1="0" y1="1" x2="1" y2="0">
                <stop offset="0"   stop-color="{{ $crStops[0] }}"/>
                <stop offset=".55" stop-color="{{ $crStops[1] }}"/>
                <stop offset="1"   stop-color="{{ $crStops[2] }}"/>
            </linearGradient>
        </defs>

        @switch($crShape)
            @case('arc')
                {{-- Three concentric quarter-rings around the corner. Drawn as
                     stroked arcs rather than filled shapes so the gaps between
                     them are card, not paint: the corner stays light. --}}
                <g fill="none" stroke="url(#{{ $crUid }}-a)" stroke-linecap="butt">
                    <path d="M 260 4 A 126 126 0 0 0 134 130" stroke-width="26"/>
                    <path d="M 260 52 A 78 78 0 0 0 182 130" stroke-width="16" opacity=".72"/>
                    <path d="M 260 92 A 38 38 0 0 0 222 130" stroke-width="9" opacity=".5"/>
                </g>
                @break

            @case('steps')
                {{-- A staircase climbing into the corner: four columns, each
                     taller than the last, the way a rising bar chart does.
                     Squared ends and equal gaps -- the rhythm is the shape. --}}
                <g fill="url(#{{ $crUid }}-a)">
                    <rect x="128" y="86" width="24" height="60" rx="3" opacity=".45"/>
                    <rect x="162" y="58" width="24" height="88" rx="3" opacity=".62"/>
                    <rect x="196" y="30" width="24" height="116" rx="3" opacity=".82"/>
                    <rect x="230" y="-6" width="34" height="152" rx="3"/>
                </g>
                @break

            @case('beam')
                {{-- One wide band across the corner at a shallow angle, with a
                     thin parallel line ahead of it. Reads as a beam of light
                     entering the card rather than a block filling it. --}}
                <g>
                    <polygon fill="url(#{{ $crUid }}-a)" points="86,-10 200,-10 280,58 280,132"/>
                    <polygon fill="url(#{{ $crUid }}-a)" opacity=".42" points="34,-10 56,-10 280,150 280,150 240,150"/>
                    <polygon fill="#fff" opacity=".14" points="150,-10 174,-10 280,86 280,112"/>
                </g>
                @break

            @default
                {{-- Two bands at one angle, the second narrower and lighter.
                     Parallel edges are what make it read as a ribbon rather
                     than two shapes. --}}
                <polygon fill="url(#{{ $crUid }}-a)" points="70,-10 280,-10 280,150"/>
                <polygon fill="#fff" opacity=".16" points="128,-10 168,-10 280,92 280,140"/>
        @endswitch
    </svg>
</span>
