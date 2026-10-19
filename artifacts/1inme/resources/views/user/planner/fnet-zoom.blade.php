{{-- Viewport controls shared by all three map tabs.

     The boards outgrew their container: at 19 stations the Flow map runs past
     the right edge and the only way to see the far end was a horizontal
     scrollbar. These controls let the whole picture be sized to taste, panned
     by dragging, and thrown onto the whole screen -- the same bar on every tab
     so the three boards behave identically. --}}
<div class="fnet-zoombar" role="group" aria-label="Board view controls">
  <button class="fnet-zbtn" type="button" data-fnet-fit title="Fit the whole board">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 9V5a1 1 0 0 1 1-1h4M15 4h4a1 1 0 0 1 1 1v4M20 15v4a1 1 0 0 1-1 1h-4M9 20H5a1 1 0 0 1-1-1v-4"/></svg>
    <span>Fit</span>
  </button>
  <button class="fnet-zbtn is-icon" type="button" data-fnet-out aria-label="Zoom out">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14"/></svg>
  </button>
  <input class="fnet-zrange" type="range" min="40" max="250" step="5" value="100" data-fnet-slider aria-label="Zoom level">
  <button class="fnet-zbtn is-icon" type="button" data-fnet-in aria-label="Zoom in">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
  </button>
  <output class="fnet-zpct" data-fnet-pct>100%</output>
  <button class="fnet-zbtn" type="button" data-fnet-full>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v3M16 3h3a2 2 0 0 1 2 2v3M21 16v3a2 2 0 0 1-2 2h-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
    <span data-fnet-fulltext>Fullscreen</span>
  </button>
</div>
