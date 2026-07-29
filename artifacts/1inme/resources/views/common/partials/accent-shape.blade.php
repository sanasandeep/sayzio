{{-- Shared decorative accent shape (AccentShapeCatalog). Params:
     $shape (catalog key), $color, optional $w/$h (px), $posStyle (inline
     positioning CSS), $accClass (extra classes, defaults z-10). Renders
     nothing for unknown shapes so callers can pass stored tokens as-is. --}}
@php
    $__acc = \App\Modules\User\Support\AccentShapeCatalog::SHAPES[$shape ?? ''] ?? null;
@endphp
@if($__acc)
    <svg class="absolute pointer-events-none {{ $accClass ?? 'z-10' }}" aria-hidden="true"
         viewBox="{{ $__acc['viewBox'] }}"
         width="{{ $w ?? $__acc['w'] }}" height="{{ $h ?? $__acc['h'] }}"
         style="{{ $posStyle ?? '' }}"
         @if($__acc['mode'] === 'fill')
             fill="{{ e($color ?? '#3f4e63') }}"
         @else
             fill="none" stroke="{{ e($color ?? '#3f4e63') }}" stroke-width="{{ $__acc['stroke_width'] ?? 5 }}"
             @if(!empty($__acc['linecap'])) stroke-linecap="{{ $__acc['linecap'] }}" @endif
         @endif
    >{!! $__acc['body'] !!}</svg>
@endif
