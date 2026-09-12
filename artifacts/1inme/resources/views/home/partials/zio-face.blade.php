{{-- Zio's animated face.

     Zio appears twice on the homepage: large in the hero, and small at the
     centre of the "Zio is not eight tools" hub. Both are the same character
     doing the same thing, so both build him from the same markup rather than
     one being a live mascot and the other a flat PNG of his head.

     Three images, not one. The artwork ships cut into a body and two antennae
     (branding/zio-body.png, zio-antenna-l.png, zio-antenna-r.png), all on the
     same 512x512 canvas so they stack at inset:0 with nothing to line up by
     hand. The cut was made along the head's own outline, so each antenna is
     whole and the body keeps the little stubs where they join -- which is what
     lets them sway from their bases without coming loose.

     Everything else -- the lids, the mouth, the tongue -- is CSS positioned in
     PERCENTAGES of the artwork, so it stays registered at every size. The
     numbers come from the pixels of the 758px master: left iris x 170-292,
     y 173-291; right iris x 409-542, y 201-323; the painted smile x 303-378,
     y 313-334.

     The mouth sits ON TOP of the painted smile rather than replacing it:
     between lines it is hidden and the original smile is Zio's resting face;
     while he is speaking it opens over it and the tongue shows.

     PARAMETERS

     $size   Width of the whole artwork canvas. `.zio-face` takes 0.42 of it,
             so this is the same knob the hero's --size already was. Omit it to
             inherit, which is what the hero does: `.zio-orbit` sets --size for
             that whole box and setting it again here would only be a second
             place to keep in step.

     $mouth  Whether to render the mouth. It is driven by a 4s loop written to
             match the hero's speech bubbles, line for line. Where there are no
             bubbles there is no line being spoken, and a mouth chattering at
             nothing is worse than a resting smile -- so the hub passes false
             and keeps the painted smile from the artwork.

     $alt    Alt text. Empty (and the caller marks the block aria-hidden) where
             the same thing is already said in the copy beside it.

     The CSS for all of this lives in the hero partial's <style> block, which
     is on the page before this is used in either place. That is a real
     coupling, so HomepageZioIsAliveInBothPlacesTest asserts the rules are
     present in the rendered page rather than leaving it to be noticed the day
     Zio quietly stops blinking. --}}
@php
    $zioSize  = $size ?? null;
    $zioMouth = $mouth ?? true;
    $zioAlt   = $alt ?? '';
@endphp
<div class="zio-face"@if($zioSize) style="--size: {{ $zioSize }};"@endif>
    <img src="{{ asset('branding/zio-body.png') }}"
         alt="{{ $zioAlt }}" class="zio-mascot"
         width="220" height="220" loading="{{ $loading ?? 'eager' }}" decoding="async">
    <span class="zio-ant zio-ant--l"></span>
    <span class="zio-ant zio-ant--r"></span>
    <span class="zio-lid zio-lid--l"></span>
    <span class="zio-lid zio-lid--r"></span>
    @if($zioMouth)
        <span class="zio-mouth-gate"><span class="zio-mouth"><i class="zio-tongue"></i></span></span>
    @endif
</div>
