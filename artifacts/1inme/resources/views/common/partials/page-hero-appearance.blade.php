{{--
    Page-hero appearance. One admin choice (PageHeroStyle), applied to every
    page that renders user.partials.page-hero.

    Everything paints onto .page-hero-deco - the wrapper that already sits
    inside the hero for clipped decoration. It is inset:0, overflow:hidden and
    border-radius:inherit, so it clips correctly WITHOUT putting overflow on
    .page-hero, which is deliberately unclipped ("dropdown menu cuts", 2026-10-05).

    The wrapper is hidden by default and holds a card-ribbon, so a style turns
    it back on and hides its children, using it purely as a paint surface.

    ::before is left alone - it carries the 46px grid lattice. Each colourway
    only re-tints --hero-grid-line so the existing lattice picks up the colour.
--}}
@php
    $heroStyles  = \App\Modules\Common\Support\PageHeroStyle::options();
    $heroTint    = [\App\Modules\Common\Support\PageHeroStyle::class, 'tint'];

    // Ring radii / widths for the arc layout, outermost last.
    $heroRings = [[58, 2.4], [84, 2.2], [110, 2.0], [136, 1.8], [162, 1.6], [188, 1.5]];

    $heroRingStack = static function (array $hues) use ($heroRings): string {
        $layers = [];
        foreach ($heroRings as $i => [$r, $w]) {
            $hue = $hues[$i % count($hues)];
            $layers[] = sprintf(
                'radial-gradient(circle at 100%% 0, transparent %1$spx, %2$s %1$spx, %2$s %3$spx, transparent %3$spx)',
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
    $hues   = $heroOpt[$heroTheme];
    $isDark = $heroTheme === 'dark';
@endphp
    /* {{ $heroOpt['label'] }} - {{ $heroTheme }} */
    {{ $heroSel }} .page-hero > .page-hero-deco { display: block; }
    {{ $heroSel }} .page-hero > .page-hero-deco > * { display: none; }
    {{ $heroSel }} .page-hero { --hero-grid-line: {{ $heroTint($hues[0], $isDark ? .10 : .07) }}; }
@if($heroOpt['layout'] === 'rings')
    {{ $heroSel }} .page-hero > .page-hero-deco {
        background-image: {!! $heroRingStack(array_map(fn ($h) => $heroTint($h, $isDark ? .34 : .26), $hues)) !!};
        -webkit-mask-image: linear-gradient(to left, #000, transparent 72%);
        mask-image: linear-gradient(to left, #000, transparent 72%);
    }
@else
    {{ $heroSel }} .page-hero > .page-hero-deco {
        background-image: linear-gradient(90deg,
            {{ $heroTint($hues[0], $isDark ? .20 : .13) }} 0 25%,
            {{ $heroTint($hues[1], $isDark ? .20 : .13) }} 25% 50%,
            {{ $heroTint($hues[2], $isDark ? .20 : .13) }} 50% 75%,
            {{ $heroTint($hues[3], $isDark ? .20 : .13) }} 75% 100%);
        -webkit-mask-image: linear-gradient(to left, #000, transparent 88%);
        mask-image: linear-gradient(to left, #000, transparent 88%);
    }
@endif
@endforeach
@endforeach
</style>
