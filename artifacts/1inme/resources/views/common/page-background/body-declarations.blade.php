{{--
    The background declarations that belong INSIDE the page's `body { }`
    rule, for the cases that paint the body directly rather than using a
    dedicated layer.

    Expects $pb -- \App\Modules\User\Support\PageBackground::resolve().

    Emitted as declarations only (no selector, no braces) so the caller
    keeps its own body rule with its font, color and layout declarations.
--}}
background-color: {{ $pb['fallbackColor'] }};
@if(!$pb['hasLayer'] && !$pb['tornActive'])
    @if($pb['type'] === 'color')
        background-color: {{ $pb['color'] }};
    @elseif($pb['type'] === 'gradient')
        background: {{ $pb['gradient'] }};
    @elseif($pb['type'] === 'preset' && $pb['presetCss'])
        {{-- Always terminate the inlined preset CSS: many catalog entries have no
             trailing semicolon, and without one the following declaration
             (min-height) glues onto the preset's last declaration, silently
             invalidating both in the browser. --}}
        {!! rtrim($pb['presetCss'], "; \t\n\r") !!};
        {{-- User chose "Scroll": neutralize any attachment hardcoded in catalog CSS. --}}
        background-attachment: scroll !important;
    @elseif($pb['type'] === 'preset')
        background-color: {{ $pb['fallbackColor'] }};
    @elseif(($pb['type'] === 'mesh' || $pb['type'] === 'pattern') && $pb['presetCss'])
        {{-- Mesh/Pattern (Task #6204): catalog CSS resolved by key. --}}
        {!! rtrim($pb['presetCss'], "; \t\n\r") !!};
        background-attachment: scroll !important;
    @elseif($pb['type'] === 'mesh' || $pb['type'] === 'pattern' || $pb['type'] === 'tiles')
        background-color: {{ $pb['fallbackColor'] }};
    @elseif($pb['type'] === 'image' && $pb['image'])
        background: {{ $pb['fallbackColor'] }} url('{{ $pb['image'] }}') center/cover no-repeat scroll;
    @elseif($pb['type'] === 'slideshow' || $pb['type'] === 'video' || $pb['type'] === 'template')
        background-color: {{ $pb['fallbackColor'] }};
        @if($pb['fallbackImage'])
            background-image: url('{{ $pb['fallbackImage'] }}');
            background-size: cover;
            background-position: center;
        @endif
    @endif
@endif
