{{--
    Page-hero appearance. One admin choice (PageHeroStyle), applied to every
    page that renders user.partials.page-hero.

    The look is a DOUBLE CARD, matching the design boards:

      .page-hero            the outer band - carries the colour wash and,
                            on the rings layout, a wide left inset so the
                            arcs have room
      .page-hero-deco       the decoration layer: rings + wash, or colour
                            fields. Already in the markup, already clipped.
      .page-hero > .hero-row  the inner plate the content sits on

    No markup change: the partial already wraps all its content in .hero-row
    and already carries .page-hero-deco. Nothing here clips .page-hero, which
    is deliberately unclipped ("dropdown menu cuts", 2026-10-05).

    Two things learned the hard way, both verified in the browser:

    1. The deco must assert position:absolute;inset:0 itself. It declares them,
       but `.page-hero > *` resets position to relative at equal specificity and
       later in the cascade, collapsing it to zero height - the styles then
       render and paint into nothing.
    2. The wash goes on the deco, NOT on .page-hero. A theme rule wins the
       `background` shorthand on the hero and layers linear-gradient(#fff,#fff)
       over anything set as background-image there.

    ::before is left alone - it carries the 46px grid lattice.
--}}
@php
    $heroStyles = \App\Modules\Common\Support\PageHeroStyle::options();
    $heroTint   = [\App\Modules\Common\Support\PageHeroStyle::class, 'tint'];
    $heroOn     = [\App\Modules\Common\Support\PageHeroStyle::class, 'onLead'];

    // Ring radii / widths, outermost last. The arcs are hairlines: at 2.4px
    // the innermost read as a drawn band rather than a trace (Sana, live).
    $heroRings = [[58, 1.4], [86, 1.3], [114, 1.2], [142, 1.1], [170, 1.0], [198, 1.0]];

    $heroRingStack = static function (array $hues, float $alpha) use ($heroRings, $heroTint): string {
        $layers = [];
        foreach ($heroRings as $i => [$r, $w]) {
            $hue = $heroTint($hues[$i % count($hues)], $alpha);
            $layers[] = sprintf(
                'radial-gradient(circle at 0 0, transparent %1$spx, %2$s %1$spx, %2$s %3$spx, transparent %3$spx)',
                $r, $hue, $r + $w
            );
        }
        return implode(', ', $layers);
    };
@endphp
<style>
@foreach($heroStyles as $heroKey => $heroOpt)
@foreach([['light', 'html.light-mode[data-hero-style="' . $heroKey . '"]'], ['dark', 'html:not(.light-mode)[data-hero-style="' . $heroKey . '"]']] as [$heroTheme, $heroSel])
@php
    // {!! !!} below, not {{ }}: the selector carries double quotes and escaping
    // them to &quot; invalidates every rule in this sheet.
    $hues   = $heroOpt[$heroTheme];
    $isDark = $heroTheme === 'dark';
    $lead   = $hues[0];
    $onLead = $heroOn($lead);
    $rings  = $heroOpt['layout'] === 'rings';

    // The band and the plate are two surfaces. Without this the theme paints
    // .page-hero #ffffff in light and rgb(16,15,23) in dark, against a plate of
    // #ffffff / rgb(18,18,26) - the same colour and two levels apart - so the
    // card melted into the band and only the gutters showed any decoration.
    $bandBg      = $isDark
        ? \App\Modules\Common\Support\PageHeroStyle::mix($lead, '#07070c', .95)
        : \App\Modules\Common\Support\PageHeroStyle::mix($lead, '#f1f3f9', .95);
    // The plate is GLASS, not an opaque slab: the wash behind it reads through
    // it, which is the whole point of putting a wash there. It can be glass now
    // only because the band has a colour of its own - when both were #ffffff,
    // a translucent plate was invisible.
    //
    // The values are literal rather than --lg-bg / --lg-blur: html.aurora sets
    // --lg-blur to none and --lg-bg to an opaque #100f17, so the tokens would
    // quietly give no glass at all on the themes most pages actually run.
    $plateBg     = $isDark ? 'rgba(24,24,36,.62)' : 'rgba(255,255,255,.72)';
    $plateBlur   = 'blur(22px) saturate(1.5)';
    $plateBorder = $isDark ? 'rgba(255,255,255,.10)' : 'rgba(255,255,255,.75)';
    $plateShadow = $isDark
        ? '0 14px 34px -20px rgba(0,0,0,.75)'
        : '0 12px 30px -18px rgba(7,20,55,.38)';

    // Two blooms, not one. A single top-left bloom died around 60% of the width
    // and the right-hand end of the band was flat - "right side gradient is
    // missing... i want it visible" (Sana). The second bloom comes in from the
    // right edge so the colour reads at both ends.
    $k = $isDark ? 1.15 : 1.0;
    $wash = implode(', ', [
        sprintf(
            'radial-gradient(110%% 190%% at 0%% 0%%, %s 0%%, %s 30%%, %s 58%%, rgba(255,255,255,0) 78%%)',
            $heroTint($hues[0], .26 * $k),
            $heroTint($hues[1], .15 * $k),
            $heroTint($hues[0], .05 * $k)
        ),
        sprintf(
            'radial-gradient(70%% 230%% at 100%% 60%%, %s 0%%, %s 30%%, %s 62%%, rgba(255,255,255,0) 82%%)',
            $heroTint($hues[2], .30 * $k),
            $heroTint($hues[1], .18 * $k),
            $heroTint($hues[2], .06 * $k)
        ),
    ]);
@endphp
    {{-- Generated hero option and theme. --}}
    {{-- No border-color here. `html.aurora .page-hero` (among others) sets
         `border: 1px solid transparent !important`, so a border-color rule at
         this specificity is dead on arrival - it computed rgba(0,0,0,0) on
         every page. The band/plate contrast carries the edge instead. --}}
    {!! $heroSel !!} .page-hero {
        padding: {{ $rings ? '18px 22px 18px 150px' : '18px 22px' }};
        background-color: {{ $bandBg }};
    }
    {{-- The narrow-screen padding has to be emitted HERE, carrying the
         same $heroSel, because specificity decides this and the old
         global override lost it silently.

              this rule   html.light-mode[data-hero-style="..."] .page-hero   (0,3,1)
              the old one html[data-hero-style] .page-hero                    (0,2,1)

         So `@media (max-width: 900px)` never applied, and on a 360px
         phone the hero kept a 150px left inset -- leaving about 190px for
         the title, the chips and the buttons, which is why the subtitle
         wrapped one word per line. The inset exists to clear the rings,
         and the rings are decoration: on a small screen the content wins. --}}
    @media (max-width: 900px) {
        {!! $heroSel !!} .page-hero { padding: 14px; }
    }
    {!! $heroSel !!} .page-hero > .page-hero-deco {
        display: block;
        position: absolute;
        inset: 0;
        overflow: hidden;
        border-radius: inherit;
        pointer-events: none;
@if($rings)
        {{-- No mask. It faded the decoration to nothing by 62% of the width,
             which is exactly what left the right-hand end flat. The arcs are
             drawn from the top-left corner and stop at r=199px on their own,
             so nothing needs masking away. --}}
        background-image: {!! $heroRingStack($hues, $isDark ? .40 : .34) !!}, {!! $wash !!};
@else
        background-image: linear-gradient(90deg,
            {{ $heroTint($hues[0], $isDark ? .32 : .26) }} 0 25%,
            {{ $heroTint($hues[1], $isDark ? .32 : .26) }} 25% 50%,
            {{ $heroTint($hues[2], $isDark ? .32 : .26) }} 50% 75%,
            {{ $heroTint($hues[3], $isDark ? .32 : .26) }} 75% 100%);
@endif
    }
    {!! $heroSel !!} .page-hero > .page-hero-deco > * { display: none; }
    {!! $heroSel !!} .page-hero > .hero-row {
        background: {{ $plateBg }};
        -webkit-backdrop-filter: {{ $plateBlur }};
        backdrop-filter: {{ $plateBlur }};
        border: 1px solid {{ $plateBorder }};
        border-radius: 14px;
        padding: 18px 22px;
        box-shadow: {{ $plateShadow }};
    }
    {{-- The 46px lattice on ::before is switched off wherever a style is
         chosen. The arcs are the decoration now, and two geometries over one
         another read as a grid of boxes behind the arcs - "checkbox behind
         arcs" (Sana, live). ::before itself is left alone; only its colour
         goes, so nothing here has to touch that rule. --}}
    {!! $heroSel !!} .page-hero { --hero-grid-line: transparent; }
    {!! $heroSel !!} .page-hero .hero-emblem.has-letter {
        background: {{ $lead }};
        border-color: transparent;
    }
    {!! $heroSel !!} .page-hero .hero-emblem.has-letter .hero-emblem-letter,
    {!! $heroSel !!} .page-hero .hero-emblem.has-letter i { color: {{ $onLead }}; }
    {!! $heroSel !!} .page-hero .btn-primary {
        background: {{ $lead }};
        border-color: transparent;
        color: {{ $onLead }};
    }
@endforeach
@endforeach
</style>
