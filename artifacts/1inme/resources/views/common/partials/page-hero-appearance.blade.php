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
    $plateBg     = $isDark ? '#181824' : '#ffffff';
    $plateBorder = $isDark ? 'rgba(255,255,255,.09)' : 'rgba(15,23,42,.07)';
    $plateShadow = $isDark
        ? '0 14px 34px -20px rgba(0,0,0,.75)'
        : '0 12px 30px -18px rgba(7,20,55,.38)';

    $wash = sprintf(
        'radial-gradient(130%% 190%% at 0%% 0%%, %s 0%%, %s 36%%, %s 64%%, rgba(255,255,255,0) 84%%)',
        $heroTint($hues[0], $isDark ? .22 : .22),
        $heroTint($hues[1], $isDark ? .13 : .13),
        $heroTint($hues[0], $isDark ? .04 : .04)
    );
@endphp
    /* {{ $heroOpt['label'] }} - {{ $heroTheme }} */
    {{-- No border-color here. `html.aurora .page-hero` (among others) sets
         `border: 1px solid transparent !important`, so a border-color rule at
         this specificity is dead on arrival - it computed rgba(0,0,0,0) on
         every page. The band/plate contrast carries the edge instead. --}}
    {!! $heroSel !!} .page-hero {
        padding: {{ $rings ? '14px 14px 14px 150px' : '14px' }};
        background-color: {{ $bandBg }};
    }
    {!! $heroSel !!} .page-hero > .page-hero-deco {
        display: block;
        position: absolute;
        inset: 0;
        overflow: hidden;
        border-radius: inherit;
        pointer-events: none;
@if($rings)
        background-image: {!! $heroRingStack($hues, $isDark ? .40 : .34) !!}, {!! $wash !!};
        -webkit-mask-image: linear-gradient(to right, #000 0%, rgba(0,0,0,.6) 34%, transparent 62%);
        mask-image: linear-gradient(to right, #000 0%, rgba(0,0,0,.6) 34%, transparent 62%);
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
        border: 1px solid {{ $plateBorder }};
        border-radius: 14px;
        padding: 18px 22px;
        box-shadow: {{ $plateShadow }};
    }
    {!! $heroSel !!} .page-hero { --hero-grid-line: {{ $heroTint($lead, $isDark ? .10 : .07) }}; }
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
@media (max-width: 900px) {
    html[data-hero-style] .page-hero { padding: 14px; }
}
</style>
