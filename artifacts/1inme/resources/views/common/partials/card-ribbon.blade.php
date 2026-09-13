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
    .cribbon {
        position: absolute;
        top: -14%;
        right: -6%;
        width: min(42%, 460px);
        height: 128%;
        pointer-events: none;
        z-index: 0;
    }
    .cribbon--left { right: auto; left: -6%; transform: scaleX(-1); }
    .cribbon svg { width: 100%; height: 100%; display: block; }

    /* Anything that has to be read sits above the ribbon and is capped well
       clear of it. The cap is belt-and-braces beside the card's own padding:
       a padding can be overridden by a page rule reaching in by class, and a
       heading sliding under the gradient is the one failure that must not
       happen. */
    .cribbon-copy { position: relative; z-index: 1; max-width: 58ch; }

    @media (max-width: 900px) {
        /* On a phone the card is one column, so the ribbon becomes a band
           across the top: same form, still bleeding off an edge, no longer
           competing with the copy for width. */
        .cribbon,
        .cribbon--left {
            top: -24%; right: -10%; left: auto;
            width: 74%; height: 132px; transform: none;
        }
    }
</style>
@endonce

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
