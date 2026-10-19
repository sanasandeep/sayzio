<style>
/* "How your Sayzio fits together" -- the account-wide companion to the folder
   Flow tab. Colours, eyebrows and icons are the Flow tab's own, so the two
   screens describe the product the same way. Everything here is built on the
   dashboard's theme tokens (--bg-card, --border-glass, --text-*) so it follows
   whatever theme the shell is in rather than carrying its own palette. */

.dflow{--df:#3b82f6;--df-stop:34px;margin-bottom:24px}
.dflow-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;flex-wrap:wrap}
.dflow-head-copy{min-width:0;flex:1 1 320px}
.dflow-head h2{margin:0 0 6px}
.dflow-score{flex:0 0 auto;display:flex;flex-direction:column;align-items:flex-end;gap:2px;margin:0;line-height:1.1}
.dflow-score strong{font-size:26px;font-weight:750;letter-spacing:-.04em;font-variant-numeric:tabular-nums;color:var(--text-primary)}
.dflow-score span{font-size:11.5px}

/* The rail. The line sits at the vertical middle of the row and every dot is
   centred in its own stop, so the line passes through the dot centres at any
   label length -- rather than a hardcoded offset that is right at one font
   size and five pixels out at the next. */
.dflow-rail{position:relative;display:flex;align-items:center;gap:6px;margin:20px 0 0;padding:10px 2px;overflow-x:auto;scrollbar-width:none}
.dflow-rail::-webkit-scrollbar{display:none}
.dflow-stop{position:relative;z-index:1;flex:1 1 0;min-width:92px;display:flex;align-items:center;gap:9px;padding:6px 12px 6px 6px;border:1px solid transparent;border-radius:999px;background:var(--bg-card);color:var(--text-secondary);font:inherit;font-size:12.5px;font-weight:650;cursor:pointer;white-space:nowrap;transition:background .18s ease,border-color .18s ease,color .18s ease}
.dflow-stop-dot{flex:0 0 auto;display:grid;place-items:center;width:var(--df-stop);height:var(--df-stop);border-radius:50%;background:color-mix(in srgb,var(--df) 12%,var(--bg-card));border:2px solid color-mix(in srgb,var(--df) 35%,transparent);color:var(--df);font-size:12px;font-weight:750;font-variant-numeric:tabular-nums;transition:background .18s ease,border-color .18s ease,color .18s ease}
.dflow-stop-dot i{font-size:11px}
.dflow-stop:hover{border-color:color-mix(in srgb,var(--df) 30%,transparent)}
.dflow-stop[aria-selected="true"]{border-color:color-mix(in srgb,var(--df) 42%,transparent);background:color-mix(in srgb,var(--df) 8%,var(--bg-card));color:var(--text-primary)}
.dflow-stop[aria-selected="true"] .dflow-stop-dot{background:var(--df);border-color:var(--df);color:#fff}
.dflow-stop:focus-visible{outline:2px solid var(--df);outline-offset:3px}

.dflow-panel{padding-top:18px}
.dflow-panel-title{margin:0 0 14px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--df)}

.dflow-stations{display:grid;grid-template-columns:repeat(auto-fit,minmax(168px,1fr));gap:10px}
.dflow-station{display:flex;align-items:center;gap:11px;min-width:0;padding:13px 14px;border:1px solid var(--border-glass);border-radius:14px;background:var(--bg-card);color:var(--text-primary);text-decoration:none;transition:border-color .18s ease,background .18s ease,transform .18s ease}
.dflow-station:hover{border-color:color-mix(in srgb,var(--df) 45%,transparent);background:color-mix(in srgb,var(--df) 5%,var(--bg-card));transform:translateY(-2px)}
.dflow-station:focus-visible{outline:2px solid var(--df);outline-offset:3px}
.dflow-station.is-inert{cursor:default}
.dflow-station.is-inert:hover{border-color:var(--border-glass);background:var(--bg-card);transform:none}
.dflow-station-icon{flex:0 0 auto;display:grid;place-items:center;width:38px;height:38px;border-radius:11px;background:color-mix(in srgb,var(--df) 10%,transparent);color:var(--df)}
.dflow-station-icon svg{width:20px;height:20px}
.dflow-station-copy{display:flex;flex-direction:column;gap:2px;min-width:0}
.dflow-station-copy strong{font-size:13px;font-weight:650;letter-spacing:-.01em;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dflow-station-count{font-size:15px;font-weight:750;letter-spacing:-.02em;font-variant-numeric:tabular-nums;color:var(--df)}
.dflow-station-cta{font-size:11.5px;font-weight:600;color:var(--text-muted)}
/* A station in use is the one worth spotting at a glance, so the accent edge
   goes there -- not on every card, which would flatten the distinction the
   panel exists to draw. */
.dflow-station.is-on{border-color:color-mix(in srgb,var(--df) 28%,var(--border-glass))}
.dflow-station.is-on .dflow-station-icon{background:color-mix(in srgb,var(--df) 16%,transparent)}

/* Placeholder shown while the fragment is on its way. Sized to the real panel
   so the page does not jump when it lands. */
.dflow-skeleton{margin-bottom:24px;min-height:188px;display:flex;flex-direction:column;justify-content:center;gap:10px}
.dflow-skeleton p{margin:0}
.dflow-skeleton-bar{height:10px;border-radius:999px;background:var(--bg-glass-input,rgba(125,135,165,.14));animation:dflow-pulse 1.4s ease-in-out infinite}
.dflow-skeleton-bar:nth-child(2){width:46%}
.dflow-skeleton-bar:nth-child(3){width:72%}
@keyframes dflow-pulse{0%,100%{opacity:.45}50%{opacity:.9}}

@media (max-width:640px){
  .dflow{--df-stop:30px}
  .dflow-head{gap:12px}
  .dflow-score{flex-direction:row;align-items:baseline;gap:7px}
  .dflow-score strong{font-size:21px}
  /* The rail scrolls rather than wrapping: a wrapped rail would put the line
     through the first row only and leave the second row floating beside it. */
  .dflow-rail{gap:4px}
  .dflow-stop{flex:0 0 auto;min-width:0;font-size:12px}
  .dflow-stations{grid-template-columns:1fr}
}

@media (prefers-reduced-motion:reduce){
  .dflow-stop,.dflow-station,.dflow-stop-dot{transition:none}
  .dflow-station:hover{transform:none}
  .dflow-skeleton-bar{animation:none}
}
</style>

<style>
/* ---------------------------------------------------------------------------
   Arrows between the stages, and one piece of motion per station icon.

   The stages are a sequence -- Prepare, Create, Serve, Reach, Act -- so the
   gaps between them carry a direction, and a plain line said nothing about it.
   Each gap is now a dash run with a chevron head.

   One wave, not four loops. Every arrow shares a period and takes a staggered
   delay from its position, so what crosses the rail reads as a single gesture
   travelling left to right. Four independent loops on a page somebody opens
   every working day is a twitch, not a design.

   Arrows up to the open stage take that stage's colour, so the rail shows how
   far along you are as well as which way it runs; the tab script keeps that in
   step with the selection.
--------------------------------------------------------------------------- */
.dflow-arrow{flex:0 0 auto;display:flex;align-items:center;width:28px;height:12px;color:var(--border-strong,#cbd4e6);pointer-events:none;transition:color .25s ease}
.dflow-arrow svg{width:100%;height:100%;overflow:visible}
.dflow-arrow path{fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round}
.dflow-arrow .dfa-line{stroke-width:1.6;stroke-dasharray:3 4;animation:dfa-run 2.6s linear infinite;animation-delay:var(--dfa-delay,0s)}
.dflow-arrow .dfa-head{stroke-width:1.8}
.dflow-arrow.is-passed{color:color-mix(in srgb,var(--df) 62%,transparent)}
@keyframes dfa-run{to{stroke-dashoffset:-14}}

/* --- station icons ------------------------------------------------------
   Motion on hover and keyboard focus only, never ambient: nineteen looping
   icons would turn a working surface into a Christmas tree. This is the same
   trigger the folder artwork on this page already uses.

   Each icon moves as the thing it draws would: the target's rings close on the
   bullseye, the megaphone's arcs leave the horn, the robot blinks. Children are
   addressed by position because the glyphs are shared constants on the folder
   Flow tab -- adding classes to them there to style them here would be the
   wrong way round. Transform and opacity only: no stroke-dash drawing, which
   needs a path length CSS cannot know and half-draws the ones it guesses wrong.
------------------------------------------------------------------------- */
.dflow-station-icon svg > *{transform-box:fill-box;transform-origin:center}
.dflow-station-icon svg{overflow:visible}

@keyframes df-pop{0%{transform:scale(.3);opacity:0}60%{transform:scale(1.18);opacity:1}100%{transform:scale(1);opacity:1}}
@keyframes df-rise{from{transform:translateY(5px);opacity:0}to{transform:translateY(0);opacity:1}}
@keyframes df-drop{from{transform:translateY(-6px);opacity:0}to{transform:translateY(0);opacity:1}}
@keyframes df-blink{0%,100%{transform:scaleY(1)}45%,55%{transform:scaleY(.08)}}
@keyframes df-ripple{0%{transform:scale(.5);opacity:0}35%{opacity:1}100%{transform:scale(1.32);opacity:0}}
@keyframes df-spin{to{transform:rotate(360deg)}}
@keyframes df-wiggle{0%,100%{transform:rotate(0)}30%{transform:rotate(-10deg)}65%{transform:rotate(7deg)}}
@keyframes df-wipe{from{transform:scaleX(0)}to{transform:scaleX(1)}}
@keyframes df-lock{0%{transform:scale(1.45);opacity:.25}100%{transform:scale(1);opacity:1}}
@keyframes df-nudge-l{0%,100%{transform:translateX(0)}50%{transform:translateX(-2.6px)}}
@keyframes df-nudge-r{0%,100%{transform:translateX(0)}50%{transform:translateX(2.6px)}}
@keyframes df-slide-in{from{transform:translateX(7px);opacity:0}to{transform:translateX(0);opacity:1}}
@keyframes df-tilt{0%,100%{transform:rotate(0)}40%{transform:rotate(-12deg)}}
@keyframes df-settle{0%{transform:translateY(-3px)}60%{transform:translateY(1px)}100%{transform:translateY(0)}}

.dflow-station:is(:hover,:focus-visible) .dflow-station-icon svg > *{animation-fill-mode:both}

/* Prepare ---------------------------------------------------------------- */
/* file: the folded corner lifts, then the written lines arrive */
.dflow-station:is(:hover,:focus-visible) [data-kind=file] svg>:nth-child(2){animation:df-tilt .5s ease}
.dflow-station:is(:hover,:focus-visible) [data-kind=file] svg>:nth-child(3){animation:df-slide-in .45s ease .1s}
/* mind: the four satellites fire outward in turn, then the links fade up */
.dflow-station:is(:hover,:focus-visible) [data-kind=mind] svg>:nth-child(2){animation:df-pop .42s ease}
.dflow-station:is(:hover,:focus-visible) [data-kind=mind] svg>:nth-child(3){animation:df-pop .42s ease .07s}
.dflow-station:is(:hover,:focus-visible) [data-kind=mind] svg>:nth-child(4){animation:df-pop .42s ease .14s}
.dflow-station:is(:hover,:focus-visible) [data-kind=mind] svg>:nth-child(5){animation:df-pop .42s ease .21s}
.dflow-station:is(:hover,:focus-visible) [data-kind=mind] svg>:nth-child(6){animation:df-lock .5s ease .1s}
/* plan: the two ticks get checked off */
.dflow-station:is(:hover,:focus-visible) [data-kind=plan] svg>:nth-child(3){animation:df-pop .5s ease}
.dflow-station:is(:hover,:focus-visible) [data-kind=plan] svg>:nth-child(2){animation:df-drop .4s ease}
/* budget: the level in the cylinder settles */
.dflow-station:is(:hover,:focus-visible) [data-kind=budget] svg>:nth-child(1){animation:df-settle .5s ease}
.dflow-station:is(:hover,:focus-visible) [data-kind=budget] svg>:nth-child(3){animation:df-settle .5s ease .12s}
/* brand: three swatches land one after another */
.dflow-station:is(:hover,:focus-visible) [data-kind=brand] svg>:nth-child(2){animation:df-pop .4s ease}
.dflow-station:is(:hover,:focus-visible) [data-kind=brand] svg>:nth-child(3){animation:df-pop .4s ease .09s}
.dflow-station:is(:hover,:focus-visible) [data-kind=brand] svg>:nth-child(4){animation:df-pop .4s ease .18s}

/* Create ----------------------------------------------------------------- */
/* link: the two halves of the chain pull apart and close */
.dflow-station:is(:hover,:focus-visible) [data-kind=link] svg>:nth-child(1){animation:df-nudge-r .5s ease}
.dflow-station:is(:hover,:focus-visible) [data-kind=link] svg>:nth-child(2){animation:df-nudge-l .5s ease}
/* form: the fields fill in from the left */
.dflow-station:is(:hover,:focus-visible) [data-kind=form] svg>:nth-child(2){transform-origin:left center;animation:df-wipe .45s ease}
/* qr: the three finder squares register in sequence, then the data */
.dflow-station:is(:hover,:focus-visible) [data-kind=qr] svg>:nth-child(1){animation:df-pop .34s ease}
.dflow-station:is(:hover,:focus-visible) [data-kind=qr] svg>:nth-child(2){animation:df-pop .34s ease .08s}
.dflow-station:is(:hover,:focus-visible) [data-kind=qr] svg>:nth-child(3){animation:df-pop .34s ease .16s}
.dflow-station:is(:hover,:focus-visible) [data-kind=qr] svg>:nth-child(4){animation:df-pop .34s ease .24s}
/* calendar: two bookings drop onto the grid */
.dflow-station:is(:hover,:focus-visible) [data-kind=calendar] svg>:nth-child(3){animation:df-drop .42s ease}
.dflow-station:is(:hover,:focus-visible) [data-kind=calendar] svg>:nth-child(4){animation:df-drop .42s ease .1s}

/* Serve ------------------------------------------------------------------ */
/* companion: the bubble leans in and the spark turns */
.dflow-station:is(:hover,:focus-visible) [data-kind=companion] svg>:nth-child(1){animation:df-nudge-l .5s ease}
.dflow-station:is(:hover,:focus-visible) [data-kind=companion] svg>:nth-child(2){animation:df-spin .7s ease}
/* agent: it blinks */
.dflow-station:is(:hover,:focus-visible) [data-kind=agent] svg>:nth-child(3){animation:df-blink .6s ease}
.dflow-station:is(:hover,:focus-visible) [data-kind=agent] svg>:nth-child(4){animation:df-blink .6s ease .05s}
.dflow-station:is(:hover,:focus-visible) [data-kind=agent] svg>:nth-child(2){animation:df-wiggle .6s ease}
/* staff: the second pair of hands arrives */
.dflow-station:is(:hover,:focus-visible) [data-kind=staff] svg>:nth-child(3){animation:df-slide-in .45s ease}
.dflow-station:is(:hover,:focus-visible) [data-kind=staff] svg>:nth-child(4){animation:df-slide-in .45s ease .06s}
/* persona: the face lands, then the details next to it */
.dflow-station:is(:hover,:focus-visible) [data-kind=persona] svg>:nth-child(2){animation:df-pop .4s ease}
.dflow-station:is(:hover,:focus-visible) [data-kind=persona] svg>:nth-child(4){transform-origin:left center;animation:df-wipe .4s ease .12s}

/* Reach ------------------------------------------------------------------ */
/* campaign: the horn turns and the sound leaves it */
.dflow-station:is(:hover,:focus-visible) [data-kind=campaign] svg>:nth-child(1){animation:df-tilt .5s ease}
.dflow-station:is(:hover,:focus-visible) [data-kind=campaign] svg>:nth-child(2){animation:df-ripple .75s ease-out}
/* studio: the star turns over */
.dflow-station:is(:hover,:focus-visible) [data-kind=studio] svg>:nth-child(1){animation:df-spin .8s cubic-bezier(.4,0,.2,1)}
/* buzz: the signal goes out in rings */
.dflow-station:is(:hover,:focus-visible) [data-kind=buzz] svg>:nth-child(2){animation:df-ripple .7s ease-out}
.dflow-station:is(:hover,:focus-visible) [data-kind=buzz] svg>:nth-child(3){animation:df-ripple .7s ease-out .15s}
/* strategy: the sights close on the bullseye */
.dflow-station:is(:hover,:focus-visible) [data-kind=strategy] svg>:nth-child(1){animation:df-lock .5s ease}
.dflow-station:is(:hover,:focus-visible) [data-kind=strategy] svg>:nth-child(2){animation:df-lock .5s ease .09s}
.dflow-station:is(:hover,:focus-visible) [data-kind=strategy] svg>:nth-child(3){animation:df-pop .4s ease .2s}

/* Act -------------------------------------------------------------------- */
/* task: it gets ticked */
.dflow-station:is(:hover,:focus-visible) [data-kind=task] svg>:nth-child(2){animation:df-pop .45s ease}
.dflow-station:is(:hover,:focus-visible) [data-kind=task] svg>:nth-child(1){animation:df-lock .45s ease}
/* board: the columns fill up */
.dflow-station:is(:hover,:focus-visible) [data-kind=board] svg>:nth-child(2){animation:df-rise .42s ease}
.dflow-station:is(:hover,:focus-visible) [data-kind=board] svg>:nth-child(3){animation:df-rise .42s ease .1s}

/* The tile lifts a touch further than before so the icon has somewhere to
   move into rather than animating inside a static box. */
.dflow-station:is(:hover,:focus-visible) .dflow-station-icon{background:color-mix(in srgb,var(--df) 18%,transparent)}

@media (prefers-reduced-motion:reduce){
  /* Plainly written, no !important, so this genuinely switches the motion off
     rather than losing to the rules above on specificity. */
  .dflow-arrow .dfa-line{animation:none}
  .dflow-arrow{transition:none}
  .dflow-station:is(:hover,:focus-visible) .dflow-station-icon svg > *{animation:none}
}
</style>
