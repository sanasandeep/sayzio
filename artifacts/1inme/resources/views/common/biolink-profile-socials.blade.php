{{--
    Profile-card social-icon row (Task #1740).

    Shared by the identity-design layouts that surface a socials row
    (glass, gradient, minimal dark, social profile). Renders a centred,
    wrapping row of circular icon chips.

    Expected vars:
      $psocials    array of ['name' => slug, 'url' => href]
      $socialIcons the brand-icon map from biolink.blade.php
      $accent      accent colour for the chip
      $chip        chip style: glass | solid | plain | accent_outline
--}}
@php $chip = $chip ?? 'glass'; @endphp
@if(!empty($psocials))
<div class="flex flex-wrap justify-center gap-2.5 mt-4">
    @foreach($psocials as $soc)
        @php
            $sn   = $soc['name'] ?? '';
            $def  = $socialIcons[$sn] ?? ['fas fa-link', $accent];
            $href = $soc['url'] ?? '';
        @endphp
        <a href="{{ $href ?: '#' }}" @if($href) target="_blank" rel="noopener" @endif
           class="w-9 h-9 rounded-full flex items-center justify-center text-sm transition hover:scale-110"
           aria-label="{{ ucfirst($sn ?: 'link') }}"
           style="@if($chip === 'solid')background:{{ $accent }};color:#fff;@elseif($chip === 'plain')color:{{ $def[1] }};@elseif($chip === 'accent_outline')border:1.5px solid {{ $accent }}40;color:{{ $accent }};@else background:rgba(255,255,255,0.10);border:1px solid rgba(255,255,255,0.20);color:{{ $accent }};@endif">
            <i class="{{ $def[0] }}"></i>
        </a>
    @endforeach
</div>
@endif
