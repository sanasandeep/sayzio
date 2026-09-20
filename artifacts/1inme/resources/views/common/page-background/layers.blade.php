{{--
    The background layer elements. Every one carries .bg-layer, which is
    what the `body > *:not(.bg-layer)` z-index rule keys off, and what the
    fingerprint test walks to record the rendered background.

    Expects $pb -- \App\Modules\User\Support\PageBackground::resolve().
    Belongs as early in <body> as possible, before the page content.
--}}
@if($pb['hasLayer'])
<div class="bg-page-fixed bg-layer" aria-hidden="true"></div>
@endif

@if($pb['tilesActive'])
<div class="bg-tiles bg-layer" aria-hidden="true">
    @foreach($pb['tiles'] as $__tile)
        <span style="background: {{ $__tile['css'] }}; grid-column: span {{ $__tile['col'] }}; grid-row: span {{ $__tile['row'] }};"></span>
    @endforeach
</div>
@endif

@if($pb['tornActive'])
<div class="bg-torn-backdrop bg-layer" aria-hidden="true"></div>
<div class="bg-torn-paper bg-layer" aria-hidden="true">
    @foreach($pb['tornSheets'] as $__i => $__sheet)
        <span class="torn-sheet-{{ $__i }}"></span>
    @endforeach
</div>
@endif

@if($pb['type'] === 'slideshow' && count($pb['slideshowImages']) > 0)
<div class="bg-slideshow bg-layer">
    @foreach($pb['slideshowImages'] as $si => $sImg)
    <img src="{{ $sImg }}" alt="" loading="eager" class="{{ $si === 0 ? 'active' : '' }}">
    @endforeach
</div>
@endif

@if($pb['type'] === 'video')
<div class="bg-video-wrap bg-layer">
    <video autoplay muted loop playsinline @if($pb['fallbackImage']) poster="{{ $pb['fallbackImage'] }}" @endif>
        @if($pb['videoFile'])
        <source src="{{ $pb['videoFile'] }}" type="{{ str_ends_with(strtolower($pb['videoFile']), '.webm') ? 'video/webm' : 'video/mp4' }}">
        @endif
        @if($pb['videoUrl'])
        <source src="{{ $pb['videoUrl'] }}" type="{{ str_ends_with(strtolower($pb['videoUrl']), '.webm') ? 'video/webm' : 'video/mp4' }}">
        @endif
    </video>
</div>
@endif

@if($pb['type'] === 'template' && $pb['template'])
<div class="bg-template bg-layer bg-template-{{ $pb['template']->slug }}" style="position:{{ $pb['fixed'] ? 'fixed' : 'absolute' }};inset:0;z-index:0;overflow:hidden;"></div>
@endif
