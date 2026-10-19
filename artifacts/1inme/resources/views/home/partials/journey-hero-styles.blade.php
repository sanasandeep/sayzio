<style>
.sj-hero{--sj-ink:#edf2ff;--sj-muted:#a9b4ce;--sj-surface:#131b30;--sj-border:#ffffff19;position:relative;isolation:isolate;overflow:hidden;padding:135px 0 0;color:var(--sj-ink)}html.light-mode .sj-hero{--sj-ink:#17213a;--sj-muted:#637089;--sj-surface:#fff;--sj-border:#17213a15}
.sj-grid-bg{position:absolute;inset:0;z-index:-1;background:radial-gradient(ellipse at 85% 30%,#7665ef12,transparent 55%),linear-gradient(#8c9bb007 1px,transparent 1px),linear-gradient(90deg,#8c9bb007 1px,transparent 1px);background-size:auto,90px 90px,90px 90px;mask-image:linear-gradient(#000,transparent)}
.sj-layout{max-width:1440px;padding:0 48px 64px;margin:auto;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.12fr);gap:68px;align-items:center}.sj-copy{padding:24px 0}.sj-eyebrow{display:inline-flex;gap:9px;align-items:center;color:#9eabff;font-size:12px;font-weight:650;letter-spacing:.03em;border:1px solid var(--sj-border);border-radius:30px;padding:9px 13px;margin-bottom:24px}html.light-mode .sj-eyebrow{color:#5360d7}
.sj-copy h1{font-size:clamp(40px,4.05vw,60px);font-weight:800;letter-spacing:-.045em;line-height:1.09;margin:0 0 24px;max-width:620px;color:var(--sj-ink)}.sj-subheading{font-size:18px;line-height:1.75;max-width:520px;color:var(--sj-muted);margin:0 0 30px}.sj-subheading strong{color:var(--sj-ink)!important;font-weight:650}.sj-ctas{display:flex;gap:12px;align-items:center;flex-wrap:wrap}.sj-primary,.sj-secondary{display:inline-flex;align-items:center;justify-content:center;gap:10px;padding:15px 20px;border-radius:14px;font-weight:700;font-size:14px;min-height:52px;transition:transform .2s,box-shadow .2s}.sj-primary{background:linear-gradient(120deg,#4358ef,#7740dd);color:#fff;box-shadow:0 8px 22px #5260ef22}.sj-secondary{background:var(--sj-surface);border:1px solid var(--sj-border);color:var(--sj-ink)}.sj-primary:hover,.sj-secondary:hover{transform:translateY(-2px)}.sj-features{font-size:12px;color:var(--sj-muted);line-height:2;margin-top:23px}.sj-features span{padding:0 5px}.sj-proof{display:flex;gap:10px 18px;flex-wrap:wrap;font-size:11px;color:var(--sj-muted);margin-top:22px}.sj-proof>span{display:inline-flex;gap:5px;align-items:center}.sj-proof i{display:inline-block;width:5px;height:5px;background:#20bf98;border-radius:50%;margin-right:2px}.sj-proof b{color:var(--sj-ink);font-weight:650}
.sj-board{border:1px solid var(--sj-border);border-radius:26px;background:var(--sj-surface);box-shadow:0 30px 80px #25235418;position:relative;scroll-margin-top:100px;overflow:hidden}.sj-board-head{padding:18px 20px;display:flex;justify-content:space-between;align-items:center;gap:10px;border-bottom:1px solid var(--sj-border);font-size:12px;font-weight:650}.sj-board-head i{color:#7a85f3;margin-right:7px}.sj-demo-label{font-size:9px;font-weight:500;color:var(--sj-muted);border:1px solid var(--sj-border);border-radius:20px;padding:4px 7px;white-space:nowrap}
.sj-stages{list-style:none;margin:0;padding:14px;display:grid;gap:7px;position:relative}.sj-stages:before{content:'';position:absolute;top:32px;bottom:32px;left:37px;width:1px;background:linear-gradient(#5865ef55,#0ea5b755,#db8d2755)}.sj-stage{position:relative}.sj-stage-button{display:flex;align-items:center;gap:11px;width:100%;text-align:left;border:1px solid transparent;border-radius:13px;padding:10px 11px;position:relative;color:var(--sj-ink);background:var(--sj-surface);min-height:58px;transition:background .4s,border-color .4s,box-shadow .4s}.sj-stage-button:hover{background:color-mix(in srgb,var(--stage-color) 5%,var(--sj-surface))}.sj-stage.is-active .sj-stage-button{background:color-mix(in srgb,var(--stage-color) 8%,var(--sj-surface));border-color:color-mix(in srgb,var(--stage-color) 35%,transparent);box-shadow:0 3px 14px color-mix(in srgb,var(--stage-color) 10%,transparent)}.sj-step-number{width:22px;height:22px;display:grid;place-items:center;flex:none;border:1px solid var(--sj-border);border-radius:50%;font-size:10px;color:var(--sj-muted);background:var(--sj-surface);z-index:1}.sj-stage.is-active .sj-step-number{background:var(--stage-color);border-color:var(--stage-color);color:white;box-shadow:0 0 0 4px color-mix(in srgb,var(--stage-color) 10%,transparent)}.sj-stage-icon{width:28px;color:var(--stage-color);font-size:19px;text-align:center;flex:none}.sj-stage-copy{flex:1;min-width:0}.sj-stage-copy strong{display:block;font-size:12px;font-weight:700}.sj-stage-copy small{display:block;font-size:9px;color:var(--sj-muted);margin-top:3px}.sj-mini-card{border-left:1px solid var(--sj-border);padding-left:12px;width:46%;min-width:0}.sj-mini-card b{display:block;font-size:10px;font-weight:600;line-height:1.4}.sj-mini-card small{display:block;font-size:9px;color:var(--sj-muted);margin-top:3px;line-height:1.4}
.sj-explanation{margin:0 20px;padding:12px 0;border-top:1px solid var(--sj-border);min-height:56px;color:var(--sj-muted);font-size:11px;line-height:1.6}.sj-explanation p{margin:0}.sj-explanation [hidden]{display:none!important}.sj-board-foot{padding:5px 20px 12px;display:flex;align-items:center;justify-content:space-between}.sj-zio{display:flex;align-items:center;gap:10px;min-height:42px}.sj-zio .zio-face{position:relative!important;inset:auto!important;width:44px!important;height:44px!important;--size:105px}.sj-zio strong{display:block;font-size:11px}.sj-zio small{display:block;font-size:10px;color:var(--sj-muted);margin-top:3px}.sj-playback{width:30px;height:30px;border-radius:50%;border:1px solid var(--sj-border);color:var(--sj-muted);font-size:10px;display:grid;place-items:center}.sj-demo-note{text-align:center;color:var(--sj-muted);font-size:9px;line-height:1.5;margin:0;padding:0 15px 13px}.sj-stage-button:focus-visible,.sj-playback:focus-visible,.sj-ctas a:focus-visible{outline:2px solid #818cf8;outline-offset:3px}
@media(max-width:1150px){.sj-layout{gap:30px;padding-inline:28px}.sj-copy h1{font-size:44px}.sj-subheading{font-size:16px}.sj-stage-button{gap:7px;padding:10px 8px}.sj-stage-copy strong{font-size:11px}.sj-mini-card b{font-size:9px}.sj-mini-card{padding-left:8px}.sj-stages:before{left:33px}}
@media(max-width:850px){.sj-layout{grid-template-columns:1fr;max-width:700px;gap:30px}.sj-copy{text-align:center}.sj-copy h1,.sj-subheading{margin-left:auto;margin-right:auto}.sj-ctas,.sj-proof{justify-content:center}.sj-copy h1{font-size:46px}.sj-copy{padding:0}.sj-hero{padding-top:115px}.sj-board{width:100%}}
@media(max-width:480px){.sj-layout{padding:0 18px 35px}.sj-copy h1{font-size:38px}.sj-subheading{font-size:15px;line-height:1.7}.sj-eyebrow{font-size:11px}.sj-ctas{gap:10px}.sj-primary,.sj-secondary{width:100%;font-size:13px;min-height:48px;padding:12px 16px}.sj-board-head{padding:16px 13px;font-size:10px}.sj-demo-label{font-size:8px}.sj-stages{padding:10px 7px;gap:5px}.sj-stage-button{gap:6px;min-height:66px;padding:10px 7px}.sj-stage-icon{width:21px;font-size:15px}.sj-step-number{width:19px;height:19px;font-size:9px}.sj-stage-copy strong{font-size:10px}.sj-stage-copy small{font-size:8px}.sj-mini-card{width:45%;padding-left:7px}.sj-mini-card small{font-size:8px}.sj-stages:before{left:24px}.sj-board{border-radius:20px}.sj-explanation{margin-inline:14px;min-height:68px}.sj-board-foot{padding-inline:14px}.sj-proof{font-size:10px}}
@media(prefers-reduced-motion:reduce){.sj-hero *{animation:none!important;transition:none!important;scroll-behavior:auto!important}}
</style>
<style>
#hero .sj-stage-button{background:var(--sj-surface)!important;color:var(--sj-ink)!important}#hero .sj-stage-button:hover{background:color-mix(in srgb,var(--stage-color) 5%,var(--sj-surface))!important}#hero .sj-stage.is-active .sj-stage-button{background:color-mix(in srgb,var(--stage-color) 9%,var(--sj-surface))!important}.sj-copy h1 .grad-text{background:linear-gradient(110deg,#5367f5,#9655df);background-clip:text;-webkit-background-clip:text;color:transparent;-webkit-text-fill-color:transparent}.sj-stage-copy strong{font-size:13px}.sj-stage-copy small{font-size:10px}.sj-mini-card b{font-size:11px}.sj-mini-card small{font-size:10px}.sj-stage.is-active:after{content:'';position:absolute;left:22px;top:24px;width:4px;height:15px;border-radius:4px;background:var(--stage-color);box-shadow:0 0 10px var(--stage-color);pointer-events:none;animation:sj-travel 3.2s linear infinite}@keyframes sj-travel{0%{transform:translateY(0);opacity:0}20%{opacity:.8}85%{opacity:.8}100%{transform:translateY(52px);opacity:0}}
@media(max-width:480px){.sj-stage-copy strong{font-size:11px}.sj-stage-copy small{font-size:9px}.sj-mini-card b{font-size:10px}.sj-mini-card small{font-size:9px}.sj-stage.is-active:after{left:15px}}
@media(prefers-reduced-motion:reduce){.sj-stage.is-active:after{display:none}}
</style>
<style>.sj-stage.is-active:after{animation-play-state:paused}.sj-board[data-playing] .sj-stage.is-active:after{animation-play-state:running}</style>

<style>
/* Gentle character acting, scoped to the small journey mascot. */
.sj-zio .zio-face{--fw:44px;animation:sj-zio-float 6s ease-in-out infinite!important;transform-origin:50% 85%}
.sj-zio .zio-lid{animation:sj-zio-blink 6.7s ease-in-out infinite!important}.sj-zio .zio-lid--r{animation-delay:.04s!important}
.sj-zio .zio-mouth-gate{left:39%;top:40.7%;width:11%;height:4%;animation:sj-zio-smile 8.6s ease-in-out infinite!important}
.sj-zio .zio-mouth{height:140%;animation:none!important;background:#271044}.sj-zio .zio-tongue{background:linear-gradient(#ff719b,#ef456f);height:70%;bottom:-10%;animation:sj-zio-tongue 8.6s ease-in-out infinite!important}
.sj-zio .zio-ant--l{animation:sj-antenna-left 5s ease-in-out infinite!important}.sj-zio .zio-ant--r{animation:sj-antenna-right 5.8s ease-in-out infinite!important}
@keyframes sj-zio-blink{0%,47%,52%,89%,94%,100%{clip-path:inset(0 0 100% 0)}49%,50%,91%,92%{clip-path:inset(0)}}
@keyframes sj-zio-smile{0%,68%,85%,100%{opacity:0}72%,81%{opacity:1}}
@keyframes sj-zio-tongue{0%,69%,86%,100%{transform:translateY(80%)}74%,79%{transform:translateY(0)}82%{transform:translateY(25%)}}
@keyframes sj-zio-float{0%,100%{transform:translateY(0) rotate(-2deg)}50%{transform:translateY(-3px) rotate(2deg)}}
@keyframes sj-antenna-left{0%,100%{transform:rotate(-3deg)}50%{transform:rotate(5deg)}}
@keyframes sj-antenna-right{0%,100%{transform:rotate(3deg)}50%{transform:rotate(-4deg)}}
.sj-stage-icon{transition:transform .4s}.sj-board[data-playing] .sj-stage.is-active .sj-stage-icon{animation:sj-icon-greet 3.2s ease-in-out infinite}.sj-board[data-playing] .sj-stage.is-active .sj-mini-card{animation:sj-card-arrive .6s ease-out both}
@keyframes sj-icon-greet{0%,70%,100%{transform:translateY(0) rotate(0)}25%{transform:translateY(-2px) rotate(-7deg)}40%{transform:translateY(-2px) rotate(5deg)}}
@keyframes sj-card-arrive{from{opacity:.5;transform:translateX(5px)}to{opacity:1;transform:translateX(0)}}
.sj-board:not([data-playing]) .sj-zio .zio-face,.sj-board:not([data-playing]) .sj-zio .zio-face *{animation-play-state:paused!important}
@media(prefers-reduced-motion:reduce){#hero .sj-zio .zio-face,#hero .sj-zio .zio-face *,#hero .sj-stage-icon,#hero .sj-mini-card{animation:none!important;transform:none!important}#hero .sj-zio .zio-mouth-gate{opacity:0!important}#hero .sj-zio .zio-lid{clip-path:inset(0 0 100% 0)!important}}
</style>
<style>
/* Match the page rails and keep rounded cards clear of the section divider. */
.sj-layout{max-width:1280px;padding:0 40px 88px;gap:42px}.sj-copy h1{font-size:clamp(38px,3.6vw,54px)}
@media(max-width:1150px){.sj-layout{padding-inline:36px;gap:28px}.sj-copy h1{font-size:42px}}
@media(max-width:850px){.sj-layout{max-width:700px;padding:0 30px 64px;gap:30px}}
@media(max-width:480px){.sj-layout{padding:0 24px 52px}.sj-copy h1{font-size:36px}}
</style>

<style>
/* ===================== The hero scene =====================
 * A picture instead of a panel: three surfaces from the real product,
 * floating over soft colour, wired together by the folder map's own
 * dashed rails. Nothing here is a screenshot -- the surfaces are CSS, so
 * they stay sharp, animate, and never drift out of date.
 *
 * Geometry rule for the whole scene: the stage has a fixed 600x520 ratio,
 * every surface is positioned in PERCENTAGES of that stage, and the rails
 * are drawn in a viewBox of exactly 600x520. One unit is one unit, so
 * nothing is measured at runtime and nothing can drift -- the mistake that
 * put the folder map's own wires up to 39px off their cards.
 */
#sayzio-journey.sj-board--scene{background:transparent;border:0;box-shadow:none;overflow:visible}
#sayzio-journey.sj-board--scene .sj-board-head{border-bottom:0;padding:4px 2px 10px}
#sayzio-journey.sj-board--scene .sj-board-foot{padding:6px 2px 0;justify-content:flex-end}
#sayzio-journey.sj-board--scene .sj-demo-note{text-align:right;padding:4px 2px 0;opacity:.75}
#sayzio-journey.sj-board--scene .sj-playback{opacity:.6}

.sj-scene{position:relative;width:100%;aspect-ratio:600/520;isolation:isolate;overflow:visible}
.sj-scene *{box-sizing:border-box}

.sj-blob{position:absolute;border-radius:50%;filter:blur(46px);opacity:.34;pointer-events:none;z-index:0}
.sj-blob.b1{left:-8%;top:2%;width:52%;height:48%;background:radial-gradient(circle,#6a7bff,transparent 68%);animation:sj-drift1 17s ease-in-out infinite}
.sj-blob.b2{right:-10%;top:18%;width:56%;height:52%;background:radial-gradient(circle,#b06ae0,transparent 68%);animation:sj-drift2 21s ease-in-out infinite}
.sj-blob.b3{left:18%;bottom:-6%;width:54%;height:46%;background:radial-gradient(circle,#2fc3b4,transparent 68%);animation:sj-drift1 24s ease-in-out infinite reverse}
@keyframes sj-drift1{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(5%,-4%) scale(1.07)}}
@keyframes sj-drift2{0%,100%{transform:translate(0,0) scale(1)}50%{transform:translate(-5%,5%) scale(1.05)}}
.sj-dots{position:absolute;inset:0;z-index:0;pointer-events:none;opacity:.34;
  background-image:radial-gradient(circle,color-mix(in srgb,var(--sj-ink) 14%,transparent) 1px,transparent 1px);background-size:19px 19px;
  mask-image:radial-gradient(ellipse at 50% 45%,#000 35%,transparent 72%)}

.sj-rails{position:absolute;inset:0;z-index:2;pointer-events:none;overflow:visible}
.sj-rails path{fill:none;stroke-width:1.9;stroke-linecap:round;stroke-dasharray:4.5 7;opacity:.75;animation:sj-flowline 2.4s linear infinite}
@keyframes sj-flowline{to{stroke-dashoffset:-23}}

.sj-surface,.sj-mini{position:absolute;z-index:3;background:#fff;box-shadow:0 20px 44px #1b225c17,0 0 0 1px #1b225c0c;
  animation:sj-float 7s ease-in-out infinite}
.sj-surface{border-radius:16px}
.sj-mini{border-radius:13px;padding:9px 10px}
@keyframes sj-float{0%,100%{translate:0 0}50%{translate:0 -7px}}

/* --- folder map fragment ---
 * Six stations threaded onto one dashed rail, with a pulse walking down them
 * in order: the folder handing a job along its own flow.
 *
 * The walk is six copies of one 6.6s keyframe, each row delayed by its own
 * --si index -- no absolute px arithmetic that drifts when the padding
 * changes at a breakpoint, and no script. Note what is deliberately NOT here:
 * `!important`. The reduced-motion block below switches the whole scene off
 * with `animation:none!important`, and an important declaration beats a
 * normal one whatever the specificity -- so leaving these normal is what
 * makes the stilled scene actually still. Zio's layer learned that the hard
 * way; see the note there.
 */
/* --mp is the card's own padding, and the rail's offset is derived from it:
 * the icon tiles sit at padding + 1px row border + 7px row padding + half a
 * 22px tile, so the rail centres on them at --mp + 19px whatever --mp is.
 * Hard-coding 29px worked until the breakpoint changed the padding to 10 and
 * left the wire 1.25px off its tiles -- the same class of mistake that put
 * the folder map's own wires 39px off their cards in #444. Derive, don't
 * re-measure. */
.sj-map{--mp:11px;left:1%;top:4%;width:47%;padding:var(--mp);transform:rotate(-1.6deg);animation-delay:-1.2s}
.sj-map .mh{font-size:7.5px;font-weight:700;color:#8b94ad;letter-spacing:.06em;text-transform:uppercase;margin-bottom:7px;display:flex;align-items:center;gap:4px}
.sj-map .rail{position:absolute;left:calc(var(--mp) + 18.25px);top:calc(var(--mp) + 20px);bottom:calc(var(--mp) + 3px);width:1.5px;z-index:0;pointer-events:none;
  background-image:linear-gradient(#c9d1e8 0 4px,transparent 4px 10px);background-size:1.5px 10px;
  animation:sj-rail-flow 1.1s linear infinite}
@keyframes sj-rail-flow{to{background-position-y:10px}}
.sj-map .stn{position:relative;z-index:1;display:grid;grid-template-columns:22px 1fr auto;align-items:center;gap:7px;
  border:1px solid #e7eaf3;border-radius:9px;padding:5px 7px;margin-bottom:5px;background:#fff;min-height:33px;
  animation:sj-stn-lit 6.6s ease-in-out infinite;animation-delay:calc(var(--si) * 1.1s)}
.sj-map .stn:last-child{margin-bottom:0}
.sj-map .stn i{width:22px;height:22px;border-radius:7px;display:grid;place-items:center;font-size:9px;color:#fff;
  background:var(--sc);animation:sj-stn-tile 6.6s ease-in-out infinite;animation-delay:calc(var(--si) * 1.1s)}
.sj-map .stn b{font-size:8px;font-weight:700;color:#5a6480;letter-spacing:.05em;text-transform:uppercase;
  min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sj-map .stn s{font-size:13px;font-weight:800;color:#1b2342;text-decoration:none;line-height:1;
  font-variant-numeric:tabular-nums;animation:sj-stn-count 6.6s ease-in-out infinite;animation-delay:calc(var(--si) * 1.1s)}
.sj-map .stn.dim{border-style:dashed;opacity:.55}
.sj-map .stn.dim i{background:#e6e9f2;color:#aeb5c9}
@keyframes sj-stn-lit{0%{border-color:#e7eaf3}4%,12%{border-color:var(--sc)}18%,100%{border-color:#e7eaf3}}
@keyframes sj-stn-tile{0%,18%,100%{transform:scale(1)}5%,12%{transform:scale(1.14)}}
@keyframes sj-stn-count{0%,18%,100%{color:#1b2342}4%,13%{color:var(--sc)}}

/* --- biolink page on a phone --- */
.sj-phone{left:72%;top:0;width:26%;aspect-ratio:150/310;padding:7px;border-radius:26px;transform:rotate(1.8deg);
  background:linear-gradient(160deg,#20264a,#121733);box-shadow:0 24px 50px #141a3a33,0 0 0 1px #ffffff14 inset;animation-delay:-3.4s}
.sj-phone .scr{display:block;width:100%;height:100%;border-radius:20px;overflow:hidden;position:relative;
  background:linear-gradient(170deg,#5a63e8,#8a52d8 52%,#2fb4a8)}
.sj-phone .scr:after{content:'';position:absolute;inset:0;pointer-events:none;
  background:linear-gradient(105deg,transparent 38%,#ffffff38 48%,transparent 58%);transform:translateX(-120%);
  animation:sj-sheen 7s ease-in-out infinite}
@keyframes sj-sheen{0%,62%{transform:translateX(-120%)}80%,100%{transform:translateX(120%)}}
.sj-phone .scr{padding:13% 8.5% 7%;display:flex;flex-direction:column;align-items:center}
.sj-phone .av{width:34%;aspect-ratio:1;border-radius:50%;background:#ffffff2e;border:1.5px solid #ffffff85;margin:0 auto;
  display:grid;place-items:center;font-size:11px;font-weight:800;color:#fff;line-height:1}
.sj-phone .nm{display:flex;align-items:center;justify-content:center;gap:3px;margin-top:8px;font-size:8px;font-weight:750;color:#fff;white-space:nowrap;letter-spacing:-.01em}
.sj-phone .nm i{font-size:6.5px;color:#9ff3e0}
.sj-phone .bi{display:block;margin-top:3px;font-size:6px;color:#ffffffc4;text-align:center;line-height:1.4}
.sj-phone .lnk{width:100%;margin-top:7px;height:20px;border-radius:10px;background:#fff;display:flex;align-items:center;gap:5px;
  padding:0 9px;font-size:6.6px;font-weight:700;color:#262f58;box-shadow:0 2px 6px #0000001f}
.sj-phone .lnk i{font-size:6.5px;color:#5a63e8}
.sj-phone .lnk.ghost{background:#ffffff2b;border:1px solid #ffffff5c;color:#fff}
.sj-phone .lnk.ghost i{color:#fff}
.sj-phone .soc{display:flex;gap:6px;margin-top:10px}
.sj-phone .soc span{width:14px;height:14px;border-radius:50%;background:#ffffff38;border:1px solid #ffffff5c;display:grid;place-items:center;font-size:6px;color:#fff}
.sj-phone .pw{display:block;margin-top:9px;font-size:5px;color:#ffffff8f;letter-spacing:.04em}

/* --- the paid moment, and the week's clicks --- */
.sj-toast{left:49%;top:1%;width:21%;display:flex;align-items:center;gap:7px;transform:rotate(-1deg);
  animation:sj-float 7.6s ease-in-out infinite,sj-toast-land 12s ease-in-out infinite;animation-delay:-1.1s,0s}
@keyframes sj-toast-land{0%,4%{opacity:0;transform:translateY(-10px) rotate(-1deg) scale(.95)}
  10%,74%{opacity:1;transform:translateY(0) rotate(-1deg) scale(1)}82%,100%{opacity:0;transform:translateY(-6px) rotate(-1deg)}}
.sj-toast .tk{width:17px;height:17px;border-radius:50%;background:#159b82;color:#fff;display:grid;place-items:center;font-size:7px;flex:none;box-shadow:0 0 0 4px #159b8222}
.sj-toast b{font-size:8.5px;font-weight:720;color:#1b2342;display:block;line-height:1.25}
.sj-toast small{font-size:7px;color:#99a1b8;display:block}
/* The folder card is six rows tall now, so this clears its lower-right
 * corner instead of sitting over it. */
.sj-anal{left:50%;top:45%;width:21%;transform:rotate(1deg);animation-delay:-2.6s}
.sj-anal .lb{font-size:6.5px;font-weight:750;letter-spacing:.07em;text-transform:uppercase;color:#99a1b8;display:block}
.sj-bars{display:flex;align-items:flex-end;gap:3px;height:34px;margin-top:7px}
.sj-bars i{flex:1;min-width:4px;border-radius:3px 3px 1px 1px;background:linear-gradient(#6a7bff,#9a6ae8);transform-origin:bottom;animation:sj-bar 3.2s ease-in-out infinite}
.sj-bars i:nth-child(1){height:52%}.sj-bars i:nth-child(2){height:74%;animation-delay:.25s}
.sj-bars i:nth-child(3){height:46%;animation-delay:.5s}.sj-bars i:nth-child(4){height:88%;animation-delay:.75s}
.sj-bars i:nth-child(5){height:66%;animation-delay:1s}
.sj-bars i:nth-child(6){height:100%;animation-delay:1.25s;background:linear-gradient(#2fc3b4,#59d6b2)}
@keyframes sj-bar{0%,100%{transform:scaleY(.82)}50%{transform:scaleY(1)}}

/* --- the customer record --- */
.sj-rec2{left:4%;bottom:1%;width:60%;padding:12px 13px;transform:rotate(-.8deg);animation-delay:-5.1s}
.sj-rec2 .rh{display:flex;align-items:center;gap:8px}
.sj-rec2 .ava{width:28px;height:28px;border-radius:9px;background:linear-gradient(135deg,#4358ef,#7740dd);color:#fff;display:grid;place-items:center;font-size:9px;font-weight:800;flex:none;box-shadow:0 4px 11px #4358ef3d}
.sj-rec2 .rn{font-size:12px;font-weight:760;color:#1b2342;letter-spacing:-.01em;display:block}
.sj-rec2 .rs{font-size:8px;color:#8b94ad;display:block;margin-top:1px}
.sj-rec2 .cnt{margin-left:auto;font-size:7.5px;font-weight:700;color:#8b94ad;text-align:right;letter-spacing:.04em}
.sj-rec2 .cnt b{display:block;font-size:15px;color:#4358ef;line-height:1}
.sj-rec2 .chips{display:flex;flex-wrap:wrap;gap:4px;margin-top:9px}
.sj-rec2 .chips span{display:inline-flex;align-items:center;gap:4px;font-size:8px;font-weight:650;color:#2a3252;border:1px solid #dfe3f0;border-radius:99px;padding:3px 7px 3px 4px;background:#fbfcff}
.sj-rec2 .chips span i{width:12px;height:12px;border-radius:4px;display:grid;place-items:center;font-size:5.5px;color:#fff}
.sj-rec2 .chips span.pend{border-style:dashed;color:#aeb5c9;background:transparent}
.sj-rec2 .chips span.pend i{background:#e6e9f2;color:#aeb5c9}
.sj-rec2 .chips span:nth-child(5){animation:sj-chip-pulse 12s ease-in-out infinite}
@keyframes sj-chip-pulse{0%,54%{box-shadow:0 0 0 0 #159b8200}60%{box-shadow:0 0 0 5px #159b8224}68%,100%{box-shadow:0 0 0 0 #159b8200}}

/* --- the two of them talking --- */
.sj-bubble,.sj-zbub{position:absolute;z-index:4;margin:0;font-weight:650;box-shadow:0 12px 26px #141a3a30}
.sj-bubble{left:60%;top:62%;background:#1b2342;color:#fff;border-radius:13px 13px 13px 4px;padding:7px 11px;font-size:9px;
  transform:rotate(-1deg);animation:sj-float 8.4s ease-in-out infinite,sj-say 9s ease-in-out infinite;animation-delay:-3s,0s}
.sj-zbub{right:15%;bottom:25%;background:#fff;color:#2a3252;border-radius:11px 11px 4px 11px;padding:6px 9px;font-size:8px;
  box-shadow:0 10px 24px #1b225c22,0 0 0 1px #1b225c0e;animation:sj-say 9s ease-in-out infinite;animation-delay:4.5s}
@keyframes sj-say{0%,8%{opacity:0;transform:translateY(8px) scale(.94)}14%,72%{opacity:1;transform:none}80%,100%{opacity:0;transform:translateY(-6px) scale(.98)}}

/* ===================== Zio's 12s performance =====================
 * Every beat shares one 12s clock so they land in a deliberate order:
 * idle, double-blink, antennae perk when the record's pulse reaches him,
 * then the tongue. The lids clip downward -- inset(0) is shut.
 */
.sj-zio-stage{position:absolute;right:-1%;bottom:-2%;width:19%;z-index:4}
.sj-zio-stage .zio-face{position:relative!important;inset:auto!important;width:100%!important;height:auto!important;aspect-ratio:1;
  animation:sj-zio-idle 12s ease-in-out infinite!important;transform-origin:50% 88%}
.sj-zio-stage .zio-lid{animation:sj-zio-blinks 12s linear infinite!important}
.sj-zio-stage .zio-lid--r{animation-delay:.05s!important}
/* The pivot is where the antenna meets the head, measured off the alpha
 * channel of zio-antenna-l/r.png rather than guessed: left base at
 * 36.5% 26.4% of its own box, right at 68% 30.3%. The shipped values were
 * `70% 100%` and `30% 100%` -- the BOTTOM EDGE of a 116px box, and left and
 * right swapped. So each antenna swung about a point several pixels clear of
 * his head and below it, which hinged the root out from under the skull and
 * read as a crop. Sana twice: "Antenas arnt cropped properly", then "still
 * cropping of antinas from Zio head wasnt good. fix the position". The angle
 * was never the problem; cutting it to 6 degrees only made a wrong pivot
 * less visible. With the real bases the full swing is clean. */
.sj-zio-stage .zio-ant--l{animation:sj-zio-ant-l 12s ease-in-out infinite!important;transform-origin:36.5% 26.4%}
.sj-zio-stage .zio-ant--r{animation:sj-zio-ant-r 12s ease-in-out infinite!important;transform-origin:68% 30.3%}
.sj-zio-stage .zio-mouth-gate{left:39%;top:40.7%;width:11%;height:4%;animation:sj-zio-mouth 12s ease-in-out infinite!important}
.sj-zio-stage .zio-mouth{height:140%;animation:none!important;background:#2a1046}
.sj-zio-stage .zio-tongue{background:linear-gradient(#ff719b,#ef456f);height:72%;bottom:-10%;border-radius:40% 40% 55% 55%;
  animation:sj-zio-tongue 12s ease-in-out infinite!important}
@keyframes sj-zio-idle{0%,100%{translate:0 0;rotate:-1.5deg}18%{translate:0 -5px;rotate:1deg}
  40%{translate:0 -2px;rotate:-3deg}52%{translate:0 -7px;rotate:2.5deg}70%{translate:0 -3px;rotate:-1deg}86%{translate:0 -6px;rotate:1.5deg}}
@keyframes sj-zio-blinks{0%,20%,23.5%,26%,29.5%,61%,64.5%,100%{clip-path:inset(0 0 100% 0)}21%,23%,27%,29%,62%,64%{clip-path:inset(0)}}
/* zio-body.webp carries no painted antennae, only a small root nub at each
 * base, so with the pivots above the overlays can take a real swing without
 * anything showing through: -13deg to +12deg, the perk arriving at 40-51%
 * when the record's own pulse reaches him. */
@keyframes sj-zio-ant-l{0%,100%{rotate:-4deg}30%{rotate:6deg}40%{rotate:-13deg}45%{rotate:9deg}50%{rotate:-11deg}72%{rotate:3deg}}
@keyframes sj-zio-ant-r{0%,100%{rotate:4deg}28%{rotate:-6deg}41%{rotate:12deg}46%{rotate:-9deg}51%{rotate:11deg}74%{rotate:-3deg}}
@keyframes sj-zio-mouth{0%,62%{opacity:0}66%,80%{opacity:1}84%,100%{opacity:0}}
@keyframes sj-zio-tongue{0%,63%{transform:translateY(88%) rotate(0)}69%,76%{transform:translateY(0) rotate(-3deg)}80%{transform:translateY(28%)}85%,100%{transform:translateY(88%)}}
.sj-zio-spark{position:absolute;right:8%;bottom:34%;z-index:6;color:#ffb44d;pointer-events:none;line-height:0;
  animation:sj-zio-spark 12s ease-in-out infinite}
@keyframes sj-zio-spark{0%,34%{opacity:0;transform:scale(.3) rotate(0)}40%,46%{opacity:1;transform:scale(1) rotate(30deg)}52%,100%{opacity:0;transform:scale(.4) rotate(60deg)}}

/* The pause button stops the whole scene, not just one layer -- including
 * the phone's sheen, which lives on a pseudo-element. `.sj-scene *` does not
 * match pseudo-elements, so without the third line the sweep keeps running
 * after the visitor presses pause. */
#sayzio-journey.sj-board--scene:not([data-playing]) .sj-scene *,
#sayzio-journey.sj-board--scene:not([data-playing]) .sj-scene .scr:after,
#sayzio-journey.sj-board--scene:not([data-playing]) .sj-scene{animation-play-state:paused!important}

@media(max-width:850px){.sj-scene{aspect-ratio:600/560}.sj-map{width:52%}.sj-rec2{width:66%}}
/* ===== Phone: the scene stops being a composition and becomes a stack =====
 *
 * Every surface above is PLACED in percentages of the stage, but the type and
 * row heights INSIDE them are fixed px. Shrink the stage to a phone and the
 * contents keep their size while the box does not, so the surfaces grow into
 * each other. Measured at a 426px viewport, the shipped version already had
 * six pairs of surfaces overlapping -- the map over the toast, the phone over
 * the analytics card, the record over Zio's speech bubble -- and nudging the
 * percentages only moves which pair collides. A six-row folder card makes it
 * worse, so this is the fix rather than another nudge.
 *
 * Below 560px the stage becomes a two-column grid in normal flow: the folder
 * card full width, the phone beside the paid toast and the week's clicks,
 * then the record, then Zio. The rails are hidden -- they are drawn for a
 * 600x520 stage and mean nothing once the surfaces are stacked -- and the
 * float/land/say animations are stopped, because they were composed to drift
 * in free space, not inside a grid cell. The blobs stay: they are absolute,
 * so they keep the colour behind the stack. Measured again after: 0 overlaps,
 * 0 horizontal overflow, nothing clipped.
 */
@media(max-width:560px){
  .sj-scene{aspect-ratio:auto;height:auto;display:grid;grid-template-columns:42% 1fr;gap:9px;
    align-content:start;padding:2px 0 4px}
  .sj-rails{display:none}
  .sj-scene>.sj-surface,.sj-scene>.sj-mini,.sj-scene>.sj-bubble,.sj-scene>.sj-zbub,.sj-scene>.sj-zio-stage{
    position:static;inset:auto;width:auto;transform:none;animation:none;opacity:1}
  .sj-map{--mp:10px;grid-column:1/-1}
  .sj-scene>.sj-phone{grid-column:1;grid-row:span 2;aspect-ratio:150/290;max-width:140px;align-self:start}
  .sj-toast,.sj-anal{align-self:start}
  .sj-scene>.sj-bubble{grid-column:1/-1;justify-self:start;font-size:8px}
  .sj-rec2{grid-column:1/-1;padding:10px}
  .sj-scene>.sj-zbub{grid-column:1;align-self:end;justify-self:start;margin-bottom:14px;font-size:7.5px}
  .sj-scene>.sj-zio-stage{grid-column:2;justify-self:end;width:108px}
  .sj-rec2 .chips span{font-size:7.5px}}
/* Both sides of this carry `!important`, so SPECIFICITY decides, and
 * `.sj-scene *` is (0,1,0) while `.sj-zio-stage .zio-face` is (0,2,0). The
 * universal selector lost, and six of Zio's animations kept running for a
 * reader who had asked the whole page to hold still -- shipped, and green,
 * because the test next to it asserted that the rule TEXT existed rather
 * than that it won. Hence the second selector list: one entry per element
 * the Zio block names, at matching specificity. Add a rule up there and it
 * needs a line down here too. */
@media(prefers-reduced-motion:reduce){
  .sj-scene *,.sj-scene{animation:none!important;transition:none!important;transform:none!important}
  .sj-zio-stage .zio-face,.sj-zio-stage .zio-face *,.sj-zio-stage .zio-lid,
  .sj-zio-stage .zio-ant--l,.sj-zio-stage .zio-ant--r,
  .sj-zio-stage .zio-mouth-gate,.sj-zio-stage .zio-mouth,.sj-zio-stage .zio-tongue,
  .sj-zio-stage .sj-zio-spark{animation:none!important;transform:none!important}
  /* Same blind spot, different cause: `.sj-scene *` matches elements, and the
   * phone's sheen is a pseudo-element, so it swept straight through the
   * stilled scene. Found by counting getAnimations() after applying this
   * block, not by reading it. */
  .sj-phone .scr:after{animation:none!important;transform:translateX(-120%)!important}
  .sj-blob{opacity:.22}
  .sj-bubble,.sj-zbub,.sj-toast{opacity:1!important}
  .sj-zio-stage .zio-lid{clip-path:inset(0 0 100% 0)!important}
  .sj-zio-stage .zio-mouth-gate{opacity:0!important}
  .sj-zio-spark{opacity:0!important}}
</style>
