<style>
.ch-trust-dot{display:inline-block;width:6px;height:6px;border-radius:50%;background:#18bd88;margin-right:7px;vertical-align:middle}

.ch-section,.ch-trust{--ch-text:#edf0ff;--ch-muted:#abb5cf;--ch-panel:#141b30;--ch-soft:#1c2540;--ch-border:#ffffff19;--ch-accent:#aaa3ff;color:var(--ch-text);box-sizing:border-box}html.light-mode .ch-section,html.light-mode .ch-trust{--ch-text:#141c31;--ch-muted:#63708b;--ch-panel:#fff;--ch-soft:#f5f6fc;--ch-border:#18284816;--ch-accent:#5845d8}
.ch-section{max-width:1280px;margin:0 auto;padding:64px 40px}
.ch-trust{max-width:1200px;margin:0 auto;padding:24px 0;display:flex;justify-content:center;gap:32px;flex-wrap:wrap;border-bottom:1px solid var(--ch-border);font-size:13px;color:var(--ch-muted)}
.ch-trust b{color:var(--ch-text)}
.ch-heading{max-width:730px;margin:0 auto 30px;text-align:center}
.ch-eyebrow{font-size:11px;font-weight:750;letter-spacing:.13em;color:var(--ch-accent)}
.ch-section h2{font-size:clamp(28px,3.2vw,42px);line-height:1.14;letter-spacing:-.035em;font-weight:750;margin:12px 0 16px;color:var(--ch-text)}
.ch-section p{font-size:15px;line-height:1.7;color:var(--ch-muted);margin:0}
.ch-demo{border:1px solid var(--ch-border);border-radius:24px;overflow:hidden;background:var(--ch-panel);box-shadow:0 20px 60px #20185409}
.ch-tabs{display:flex;gap:8px;justify-content:center;padding:16px;border-bottom:1px solid var(--ch-border);background:var(--ch-soft)}
.ch-tabs button{display:flex;align-items:center;justify-content:center;gap:9px;background:transparent;border:1px solid transparent;border-radius:12px;padding:11px 20px;color:var(--ch-muted);font-size:14px;font-weight:650;cursor:pointer;transition:background .2s,color .2s,box-shadow .2s}
.ch-tabs button[aria-selected=true]{background:var(--ch-panel);color:var(--ch-accent);border-color:var(--ch-border);box-shadow:0 3px 12px #2720550b}
.ch-tabs button:focus-visible,.ch-section a:focus-visible,.ch-section summary:focus-visible{outline:3px solid var(--ch-accent);outline-offset:3px}
.ch-panel{display:grid;grid-template-columns:1fr 1.1fr;align-items:center;gap:48px;padding:38px 44px;}
.ch-panel[hidden]{display:none!important}
.ch-panel-copy h3{font-size:26px;font-weight:700;letter-spacing:-.025em;line-height:1.2;margin:18px 0 14px;color:var(--ch-text)}
.ch-chip{display:inline-block;color:var(--ch-accent);font-size:12px;font-weight:650}
.ch-panel-copy a,.ch-link{display:inline-flex;gap:12px;align-items:center;color:var(--ch-accent);font-weight:650;font-size:14px;margin-top:22px;text-decoration:none}
.ch-product{background:var(--ch-soft);border:1px solid var(--ch-border);border-radius:17px;padding:18px;box-shadow:0 16px 32px #20185408}
.ch-product-top{display:flex;justify-content:space-between;gap:12px;align-items:center;font-size:12px;font-weight:650;margin-bottom:18px}
.ch-product-top i{color:var(--ch-accent);margin-right:5px}
.ch-product-top small,.ch-muted{color:var(--ch-muted);font-size:11px;line-height:1.6}
.ch-record{background:var(--ch-panel);border:1px solid var(--ch-border);border-radius:12px;padding:20px}
.ch-status{font-size:11px;display:inline-block;color:var(--ch-accent);font-weight:650;border-radius:20px;padding:4px 9px;background:var(--ch-soft)}
.ch-person{display:flex;gap:12px;align-items:center;margin:18px 0}
.ch-avatar{flex-shrink:0;width:42px;height:42px;display:grid;place-items:center;border-radius:14px;background:linear-gradient(145deg,#6e65ed,#ac5dc8);color:#fff;font-weight:750;font-size:13px}
.ch-record h4{font-size:16px;font-weight:700;margin:0 0 4px;color:var(--ch-text)}
.ch-record p{font-size:12px}
.ch-record-line{font-size:12px;color:var(--ch-muted);display:flex;align-items:center;gap:8px}
.ch-record-line i{color:#19aa86}
.ch-next{border-top:1px solid var(--ch-border);margin-top:17px;padding-top:14px;display:grid;gap:5px}
.ch-next small{font-size:9px;letter-spacing:.1em;color:var(--ch-muted)}
.ch-next strong{font-size:12px;color:var(--ch-text)}
.ch-path{display:flex;justify-content:center;align-items:center;gap:10px;margin-top:16px;font-size:10px;color:var(--ch-muted)}
.ch-path b{color:var(--ch-accent)}
.ch-demo .ch-caption{font-size:11px;padding:0 24px 20px;text-align:center}
.ch-ai-scene{background:radial-gradient(ellipse at 50% 30%,#8668ef16,transparent 75%);padding:14px 0}
.ch-context{font-size:11px;color:var(--ch-muted);text-align:center;margin-bottom:20px}
.ch-context i{color:var(--ch-accent);margin-right:6px}
.ch-prompt{padding:14px 18px;margin-left:30px;background:var(--ch-soft);border:1px solid var(--ch-border);border-radius:15px 15px 3px 15px;font-size:13px}
.ch-answer{display:flex;gap:12px;padding:22px;margin:15px 20px 0 0;border:1px solid var(--ch-border);border-radius:15px;background:var(--ch-panel);box-shadow:0 12px 30px #2018540c}
.ch-spark{color:var(--ch-accent);font-size:24px}
.ch-answer strong{font-size:15px;color:var(--ch-text)}
.ch-answer p{font-size:12px;margin:8px 0 14px}
.ch-panel-copy .ch-muted{display:block;margin-top:20px}
.ch-pricing,.ch-faq{display:grid;grid-template-columns:1fr 1.3fr;gap:60px;align-items:center}
.ch-prices{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px}
.ch-prices article{background:var(--ch-panel);padding:26px;border:1px solid var(--ch-border);border-radius:18px}
.ch-prices article>span{color:var(--ch-muted);font-size:13px}
.ch-prices h3{font-size:28px;margin:18px 0 12px;font-weight:750;letter-spacing:-.03em}
.ch-prices h3 small{font-size:12px;font-weight:400;letter-spacing:0;color:var(--ch-muted)}
.ch-prices p{font-size:12px}
/* The plan limits under each price. Rendered from the same features the
   /pricing cards read, so the list length varies by plan -- hence a list
   rather than three fixed rows. */
.ch-plan-limits{list-style:none;margin:12px 0 0;padding:0;display:grid;gap:7px}
.ch-plan-limits li{font-size:12px;color:var(--ch-muted);display:flex;align-items:center;gap:8px;line-height:1.45}
.ch-plan-limits li::before{content:'';flex:none;width:5px;height:5px;border-radius:50%;background:currentColor;opacity:.55}
.ch-faq{align-items:start;border-top:1px solid var(--ch-border)}
.ch-faq details{border-bottom:1px solid var(--ch-border);padding:18px 0}
.ch-faq details:first-child{padding-top:0}
.ch-faq summary{display:flex;justify-content:space-between;gap:14px;cursor:pointer;font-size:14px;font-weight:650;list-style:none}
.ch-faq summary::-webkit-details-marker{display:none}
.ch-faq summary span{color:var(--ch-accent)}
.ch-faq details[open] summary span{transform:rotate(45deg)}
.ch-faq details p{font-size:13px;padding:14px 20px 0 0}
.ch-final{text-align:center;padding-top:48px;padding-bottom:72px;background:radial-gradient(ellipse at 50% 70%,#8463ed10,transparent 65%)}
.ch-button{display:inline-flex;align-items:center;gap:20px;margin-top:24px;background:linear-gradient(110deg,#4655ee,#7845dd);color:white!important;border-radius:13px;padding:15px 24px;font-weight:700;font-size:14px;text-decoration:none;box-shadow:0 8px 24px #6550df23;transition:transform .2s,box-shadow .2s}
.ch-button:hover{transform:translateY(-2px);box-shadow:0 12px 26px #6550df35}
@media(max-width:760px){.ch-section{padding:44px 24px}
.ch-trust{margin:0 24px;gap:12px 20px;padding:20px 0;font-size:11px}
.ch-panel{grid-template-columns:1fr;gap:24px;padding:24px}
.ch-panel-copy h3{font-size:23px}
.ch-tabs{padding:10px;gap:3px}
.ch-tabs button{flex:1;gap:6px;padding:10px 5px;font-size:12px}
.ch-tabs i{display:none}
.ch-pricing,.ch-faq{grid-template-columns:1fr;gap:26px}
.ch-path{gap:7px}
.ch-product{padding:12px}
.ch-record{padding:16px}
.ch-product-top small{font-size:9px}
.ch-heading{margin-bottom:24px}
.ch-final{padding-bottom:48px}}

/* ===== The journey rail (#customer-workflow) =====================
   The tablist is the route. Stops sit on one dashed line with a pulse
   running it left to right, so the row reads as a journey rather than a
   button bar -- which is what the section is actually describing. Keeps
   full tablist semantics; only the paint changed. */
.ch-journey .ch-rail{--rail-pad:22px;--stop-dot:38px;position:relative;display:grid;grid-auto-flow:column;grid-auto-columns:1fr;gap:4px;padding:var(--rail-pad) 18px 18px;border-bottom:1px solid var(--ch-border);background:var(--ch-soft)}
/* The line has to pass through the middle of the stops. Derived from the
   rail's own padding and dot size rather than a magic top: hardcoding it
   put the line 5px under the dots, and the breakpoint below changes both
   numbers -- so a constant would have been wrong twice. */
.ch-rail-art{position:absolute;left:8%;right:8%;top:calc(var(--rail-pad) + var(--stop-dot)/2 - 12px);width:84%;height:24px;overflow:visible;pointer-events:none}
.ch-rail-base{stroke:var(--ch-border);stroke-width:2;stroke-linecap:round}
.ch-rail-flow{stroke:var(--ch-accent);stroke-width:2;stroke-linecap:round;opacity:.3;stroke-dasharray:6 10;animation:ch-rail-flow 1.4s linear infinite}
@keyframes ch-rail-flow{to{stroke-dashoffset:-16}}
.ch-rail-pulse{fill:var(--ch-accent);opacity:.9;animation:ch-rail-pulse 5.5s ease-in-out infinite}
@keyframes ch-rail-pulse{0%{transform:translateX(8px);opacity:0}8%{opacity:.9}92%{opacity:.9}100%{transform:translateX(992px);opacity:0}}
.ch-stop{position:relative;z-index:1;display:flex;flex-direction:column;align-items:center;gap:9px;background:transparent;border:0;padding:0;cursor:pointer;color:var(--ch-muted);font-size:13px;font-weight:650}
.ch-stop-dot{width:var(--stop-dot);height:var(--stop-dot);border-radius:50%;display:grid;place-items:center;font-size:14px;background:var(--ch-panel);border:2px solid var(--ch-border);color:var(--ch-muted);transition:border-color .25s,color .25s,transform .25s,box-shadow .25s}
.ch-stop-label{transition:color .25s}
.ch-stop:hover .ch-stop-dot{border-color:var(--ch-accent);color:var(--ch-accent)}
.ch-stop[aria-selected=true] .ch-stop-dot{border-color:var(--ch-accent);background:var(--ch-accent);color:#fff;transform:scale(1.1);box-shadow:0 0 0 6px color-mix(in srgb,var(--ch-accent) 14%,transparent)}
.ch-stop[aria-selected=true] .ch-stop-label{color:var(--ch-accent)}
.ch-stop:focus-visible{outline:3px solid var(--ch-accent);outline-offset:4px;border-radius:12px}

/* What the record is carrying by this stage. Earlier stages stay on the
   card, ticked, so the history accumulates instead of swapping. */
.ch-trail{display:flex;flex-wrap:wrap;gap:6px;margin-top:14px}
.ch-trail-chip{display:inline-flex;align-items:center;gap:5px;font-size:10.5px;font-weight:650;padding:4px 9px;border-radius:9999px;border:1px solid var(--ch-border);color:var(--ch-muted)}
.ch-trail-chip i{font-size:8px;color:#19aa86}
.ch-trail-chip.is-now{border-color:var(--ch-accent);color:var(--ch-accent);background:color-mix(in srgb,var(--ch-accent) 10%,transparent)}

/* ===== The converging diagram (#connected-ai) ====================
   Two inputs, two curves, one answer -- a Y, because the rail above is a
   line and two identical rectangles back to back was half of why this
   page read flat. */
/* The merge needs more room than the default 1fr/1.1fr panel split gives
   it -- three columns inside one of them. */
.ch-converge .ch-panel{grid-template-columns:.88fr 1.32fr;gap:36px}
.ch-converge .ch-merge{display:grid;grid-template-columns:1fr 92px 1.15fr;align-items:center;gap:0}
.ch-feeds{display:grid;gap:16px}
.ch-feed{background:var(--ch-panel);border:1px solid var(--ch-border);border-radius:14px;padding:13px 15px;display:grid;gap:5px;box-shadow:0 10px 24px #20185409}
.ch-feed-name{font-size:12.5px;font-weight:700;color:var(--ch-text)}
.ch-feed-detail{font-size:11px;color:var(--ch-muted)}
.ch-sw-row{display:flex;align-items:center;gap:5px;margin-top:4px}
.ch-sw-row i{width:15px;height:15px;border-radius:5px;flex:none}
.ch-sw-row b{font-size:12px;font-weight:800;color:var(--ch-text);margin-left:3px}
.ch-sw-row--sm i{width:11px;height:11px;border-radius:4px}
.ch-feed-chips{display:flex;gap:5px;margin-top:4px}
.ch-feed-chips i{height:9px;border-radius:5px;background:color-mix(in srgb,var(--ch-accent) 28%,transparent)}
.ch-feed-chips i:nth-child(1){width:38%}.ch-feed-chips i:nth-child(2){width:26%}.ch-feed-chips i:nth-child(3){width:18%}
.ch-feed-lines{display:grid;gap:5px;margin-top:5px}
.ch-feed-lines i{height:6px;border-radius:3px;background:var(--ch-border)}
.ch-feed-lines i:nth-child(1){width:80%}.ch-feed-lines i:nth-child(2){width:55%}
.ch-merge-art{width:92px;height:190px;overflow:visible}
.ch-wire{fill:none;stroke:var(--ch-border);stroke-width:2}
.ch-wire-run{stroke:var(--ch-accent);stroke-width:2;stroke-linecap:round;stroke-dasharray:14 240;animation:ch-wire-run 3.2s ease-in-out infinite}
.ch-wire-run--b{animation-delay:1.6s}
@keyframes ch-wire-run{0%{stroke-dashoffset:254;opacity:0}10%{opacity:1}85%{opacity:1}100%{stroke-dashoffset:0;opacity:0}}
.ch-out{background:var(--ch-panel);border:1px solid var(--ch-border);border-radius:16px;padding:20px;display:grid;gap:9px;box-shadow:0 16px 36px #2018540f}
.ch-out-top{display:flex;align-items:center;gap:9px;font-size:14.5px;font-weight:700;color:var(--ch-text);line-height:1.3}
.ch-out p{font-size:12px}
.ch-out .ch-status{justify-self:start;margin-top:3px}
.ch-spark{color:var(--ch-accent);font-size:15px;flex:none}

@media(max-width:760px){
.ch-journey .ch-rail{--rail-pad:16px;--stop-dot:30px;padding:var(--rail-pad) 10px 12px;gap:2px}
.ch-rail-art{display:none}
.ch-stop{font-size:11px;gap:6px}
.ch-stop-dot{font-size:12px}
.ch-converge .ch-merge{grid-template-columns:1fr;gap:12px}
.ch-merge-art{width:100%;height:46px;transform:rotate(90deg);justify-self:center}
.ch-feeds{gap:10px}}

@media(prefers-reduced-motion:reduce){.ch-panel{animation:none}
.ch-tabs button,.ch-button{transition:none}
.ch-button:hover{transform:none}
/* Every moving part added for the rail and the converging wires. These are
   plain CSS animations with no !important and no deep selectors precisely
   so this block can switch them off; the wires use CSS rather than SMIL
   for the same reason -- an <animateMotion> would have kept running here. */
.ch-rail-flow,.ch-rail-pulse,.ch-wire-run{animation:none}
.ch-rail-pulse{opacity:0}
.ch-wire-run{stroke-dashoffset:0;stroke-dasharray:none;opacity:.55}
.ch-stop-dot,.ch-stop-label{transition:none}}
</style>
