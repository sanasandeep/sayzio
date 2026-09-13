{{--
    The marketing site's ribbon, brought into the product.

    Three slanted bands at one angle, on one gradient, anchored to an edge and
    drawn WIDER than the space they are given so they bleed off the card
    instead of sitting inside it like a picture. The card's overflow:hidden is
    what turns that overflow into a bleed, so a host card must have it.

    This is deliberately not the earlier treatment, which was a soft gradient
    washed across the whole top of the card. A wash puts colour behind the
    words and reads as haze; a ribbon is a hard-edged shape confined to its
    own side, and never underneath anything anyone has to read.

    It is the same geometry as home/partials/cta-card.blade.php -- same three
    polygons, same stops -- rather than a lookalike, so the product and the
    landing page cannot drift apart. It is aria-hidden because it says nothing
    the copy does not.

    It renders two layers, in the order the landing page uses them: a faint
    square lattice under the copy, and the ribbon bleeding off the far edge.
    The lattice is the same device as the CTA card's -- the page's own grid
    cell carried under the card, so the card reads as part of the page rather
    than a panel dropped on it -- and it is masked to stop well before the
    ribbon, because a grid running under a gradient is two textures fighting
    over the same space.

    Usage: put it as the first child of a card that is position:relative and
    overflow:hidden, then give the card's content wrapper `.cribbon-copy`
    (or any positioned element) so the words sit above it.

      @include('common.partials.card-ribbon')
      @include('common.partials.card-ribbon', ['tone' => 'warm'])

    Props:
      $tone  'cool' (blue into cyan, default) | 'warm' (amber into violet)
--}}
@php
    $cribbonTone = ($tone ?? 'cool') === 'warm' ? 'warm' : 'cool';
    // Which edge it bleeds off. The copy takes the other side, so this flips
    // the card's whole composition rather than just moving the artwork; the
    // SVG's slant is asymmetric, so the left variant is mirrored, not moved.
    $cribbonSide = ($side ?? 'right') === 'left' ? 'left' : 'right';
    // Two ribbons can share a page and each needs its own gradient ids, or
    // the second silently reuses the first one's stops.
    $cribbonUid  = 'cr'.substr(md5($cribbonTone.uniqid('', true)), 0, 8);
@endphp

@once
<style>
    /* A corner sweep, not a wall. The first pass gave it 42% of the card and
       full height, which on a hero whose right half is a chart buried the
       chart and put white label text on a blue field. Anchored to the bottom
       corner and masked back along its own angle, it reads as the marketing
       hero's ribbon does -- something passing through the corner -- and the
       top of the card, where headings and eyebrows live, stays clear. */
    .cribbon {
        position: absolute;
        bottom: -12%;
        right: -7%;
        width: min(26%, 320px);
        height: 82%;
        pointer-events: none;
        z-index: 0;
        -webkit-mask-image: linear-gradient(20deg, #000 0%, #000 52%, transparent 88%);
                mask-image: linear-gradient(20deg, #000 0%, #000 52%, transparent 88%);
    }
    .cribbon--left {
        right: auto; left: -7%; transform: scaleX(-1);
    }
    .cribbon svg { width: 100%; height: 100%; display: block; }

    /* ---------- the lattice under the copy ---------- */
    .cribbon-grid {
        position: absolute; inset: 0; pointer-events: none; z-index: 0;
        --cribbon-line: rgba(15, 23, 42, .05);
        background-image:
            linear-gradient(to right,  var(--cribbon-line) 1px, transparent 1px),
            linear-gradient(to bottom, var(--cribbon-line) 1px, transparent 1px);
        background-size: 46px 46px;
        background-position: 50% 0;
        -webkit-mask-image: linear-gradient(to right, #000 0%, #000 40%, transparent 66%);
                mask-image: linear-gradient(to right, #000 0%, #000 40%, transparent 66%);
    }
    html:not(.light-mode) .cribbon-grid { --cribbon-line: rgba(255, 255, 255, .055); }
    .cribbon-grid--left {
        -webkit-mask-image: linear-gradient(to left, #000 0%, #000 40%, transparent 66%);
                mask-image: linear-gradient(to left, #000 0%, #000 40%, transparent 66%);
    }

    /* Anything that has to be read sits above both layers. No width cap
       here: this class goes on whatever wrapper a page already has, and on
       several of them that wrapper is the card's own grid -- capping it would
       collapse the layout rather than protect the copy. Keeping text clear of
       the ribbon is the host's job, and every card this is used on already
       has its content in a left column. */
    .cribbon-copy { position: relative; z-index: 1; }

    @media (max-width: 900px) {
        /* On a phone the card is one column, so the ribbon becomes a band
           across the top: same form, still bleeding off an edge, no longer
           competing with the copy for width. */
        .cribbon,
        .cribbon--left {
            bottom: auto; top: -20%; right: -12%; left: auto;
            width: 62%; height: 150px; transform: none;
        }
        /* The copy runs the full width here, so the lattice does too. */
        .cribbon-grid,
        .cribbon-grid--left {
            -webkit-mask-image: linear-gradient(to bottom, transparent 0%, #000 28%);
                    mask-image: linear-gradient(to bottom, transparent 0%, #000 28%);
        }
    }
</style>
@endonce

<span class="cribbon-grid{{ $cribbonSide === 'left' ? ' cribbon-grid--left' : '' }}" aria-hidden="true"></span>
<span class="cribbon{{ $cribbonSide === 'left' ? ' cribbon--left' : '' }}" aria-hidden="true">
    <svg viewBox="0 0 400 460" preserveAspectRatio="xMidYMid slice" role="presentation" focusable="false">
        <defs>
            <linearGradient id="{{ $cribbonUid }}-a" x1="0" y1="0" x2="1" y2="1">
                @if($cribbonTone === 'warm')
                    <stop offset="0"   stop-color="#FF8A3C"/>
                    <stop offset=".55" stop-color="#E94E8C"/>
                    <stop offset="1"   stop-color="#7C5CFF"/>
                @else
                    <stop offset="0"   stop-color="#3E3AE0"/>
                    <stop offset=".55" stop-color="#3D6BFF"/>
                    <stop offset="1"   stop-color="#1BD4D9"/>
                @endif
            </linearGradient>
            <linearGradient id="{{ $cribbonUid }}-b" x1="0" y1="1" x2="1" y2="0">
                @if($cribbonTone === 'warm')
                    <stop offset="0" stop-color="#FFC845"/>
                    <stop offset="1" stop-color="#FF8A3C"/>
                @else
                    <stop offset="0" stop-color="#1BD4D9"/>
                    <stop offset="1" stop-color="#6F9BFF"/>
                @endif
            </linearGradient>
        </defs>
        <polygon fill="url(#{{ $cribbonUid }}-a)" points="150,-40 400,-40 400,500 -10,500"/>
        <polygon fill="url(#{{ $cribbonUid }}-b)" opacity=".55" points="250,-40 400,-40 400,300 95,500 -10,500"/>
        <polygon fill="#fff" opacity=".14" points="316,-40 356,-40 0,500 -40,500"/>
    </svg>
</span>
