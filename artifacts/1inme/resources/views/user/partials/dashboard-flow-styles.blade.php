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
.dflow-rail-base{position:absolute;left:14px;right:14px;top:50%;height:2px;margin-top:-1px;background:var(--border-glass);border-radius:2px;pointer-events:none}
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
