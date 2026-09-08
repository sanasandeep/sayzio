{{--
    Schematic skin: the marketing art direction.

    Every rule is scoped under body.skin-schematic so the existing site styles
    (Tailwind preflight, app.css, the glass theme) are untouched on every other
    page. Tokens follow the site's own light/dark convention: dark is the
    default, html.light-mode swaps the palette, so the header's existing theme
    toggle keeps working with no extra wiring.

    No em dashes anywhere in this file: the em-dash copy guard scans marketing
    Blade views and treats U+2014 as a failure.
--}}
@push('head')
@if(request()->routeIs('site.schematic.*'))
<meta name="robots" content="noindex, nofollow">
@endif
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@100..125,400..900&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
@verbatim
<style>
body.skin-schematic{
  --paper:#0B0D13; --surface:#11141C; --surface-2:#171B25;
  --ink:#EDEFF5; --ink-2:#A5ACBC; --ink-3:#6E7686;
  --rule:#232836; --rule-strong:#333A4B;
  --violet:#A084FF; --violet-soft:rgba(160,132,255,.12); --cyan:#4CD8E6;
  --shadow:0 1px 0 rgba(255,255,255,.03), 0 18px 40px -24px rgba(0,0,0,.9);
  --sch-display:"Archivo","Helvetica Neue",Arial,sans-serif;
  --sch-body:"IBM Plex Sans","Segoe UI",Helvetica,Arial,sans-serif;
  --sch-mono:"IBM Plex Mono",ui-monospace,SFMono-Regular,Menlo,monospace;
  --sch-maxw:1160px;
  --sch-gutter:clamp(20px,4vw,56px);

  background:var(--paper);
  color:var(--ink);
  font-family:var(--sch-body);
  font-size:16px;
  line-height:1.55;
  -webkit-font-smoothing:antialiased;
}
html.light-mode body.skin-schematic{
  --paper:#F4F5F8; --surface:#FFFFFF; --surface-2:#ECEEF3;
  --ink:#0C0F16; --ink-2:#3E4553; --ink-3:#787F8F;
  --rule:#D6DAE3; --rule-strong:#B7BDCB;
  --violet:#4B2AE0; --violet-soft:rgba(75,42,224,.09); --cyan:#0C8996;
  --shadow:0 1px 0 rgba(12,15,22,.04), 0 12px 32px -20px rgba(12,15,22,.35);
}

.skin-schematic a{color:inherit}
.skin-schematic :focus-visible{outline:2px solid var(--violet);outline-offset:3px;border-radius:2px}
.skin-schematic .sch-sr{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}

/* ---------- primitives ---------- */
.skin-schematic .sch-wrap{max-width:var(--sch-maxw);margin:0 auto;padding-left:var(--sch-gutter);padding-right:var(--sch-gutter)}
.skin-schematic .sch-disp{font-family:var(--sch-display);font-variation-settings:"wdth" 118;font-weight:700;letter-spacing:-.022em;line-height:.98;text-wrap:balance;margin:0}
.skin-schematic .sch-h1{font-size:clamp(35px,5vw,63px)}
.skin-schematic .sch-h2{font-size:clamp(27px,3.5vw,44px)}
.skin-schematic .sch-h3{font-size:clamp(19px,2vw,25px);font-variation-settings:"wdth" 110;letter-spacing:-.014em}
.skin-schematic .sch-mono{font-family:var(--sch-mono);font-size:11px;letter-spacing:.13em;text-transform:uppercase;font-weight:500}
.skin-schematic .sch-route{display:flex;align-items:center;gap:11px;color:var(--ink-3);margin:0}
.skin-schematic .sch-route .slug{color:var(--violet);font-weight:600;text-transform:none;letter-spacing:.04em}
.skin-schematic .sch-route::after{content:"";flex:1;height:1px;background:var(--rule)}
.skin-schematic .sch-lede{font-size:clamp(16px,1.5vw,18.5px);color:var(--ink-2);max-width:60ch;margin:0}
.skin-schematic .sch-tnum{font-variant-numeric:tabular-nums}

.skin-schematic .sch-section{border-top:1px solid var(--rule);padding-top:clamp(52px,6.5vw,92px);padding-bottom:clamp(52px,6.5vw,92px)}
.skin-schematic .sch-head{display:grid;grid-template-columns:1fr;gap:18px;margin-bottom:clamp(26px,4vw,46px)}
@media (min-width:900px){
  .skin-schematic .sch-head{grid-template-columns:150px 1fr;gap:32px;align-items:start}
  .skin-schematic .sch-head .sch-route{padding-top:9px}
}

.skin-schematic .sch-btn{font-family:var(--sch-body);font-size:14px;font-weight:500;padding:9px 16px;border-radius:2px;text-decoration:none;border:1px solid var(--ink);background:var(--ink);color:var(--paper);transition:transform .12s ease;display:inline-flex;align-items:center;gap:8px;cursor:pointer}
.skin-schematic .sch-btn:hover{transform:translateY(-1px);color:var(--paper)}
.skin-schematic .sch-btn.ghost{background:transparent;color:var(--ink);border-color:var(--rule-strong)}
.skin-schematic .sch-btn.ghost:hover{border-color:var(--ink);color:var(--ink)}
.skin-schematic .sch-btn.lg{padding:13px 22px;font-size:15px}

/* ---------- header / footer ---------- */
.skin-schematic .sch-nav{position:sticky;top:0;z-index:40;background:var(--paper);border-bottom:1px solid var(--rule)}
.skin-schematic .sch-nav-in{display:flex;align-items:center;gap:22px;height:62px}
.skin-schematic .sch-brand{display:flex;align-items:baseline;gap:9px;text-decoration:none;flex:none}
.skin-schematic .sch-brand b{font-family:var(--sch-display);font-variation-settings:"wdth" 125;font-weight:800;font-size:19px;letter-spacing:.01em}
.skin-schematic .sch-brand span{font-family:var(--sch-mono);font-size:10px;letter-spacing:.18em;color:var(--ink-3);text-transform:uppercase}
.skin-schematic .sch-nav-links{display:none;gap:4px;margin-left:auto}
@media (min-width:820px){.skin-schematic .sch-nav-links{display:flex}}
.skin-schematic .sch-nav-links a{font-family:var(--sch-mono);font-size:11px;letter-spacing:.1em;border-bottom:1px solid transparent;color:var(--ink-3);padding:6px 10px;text-decoration:none}
.skin-schematic .sch-nav-links a:hover{color:var(--ink)}
.skin-schematic .sch-nav-links a[aria-current="page"]{color:var(--ink);border-bottom-color:var(--violet)}
.skin-schematic .sch-nav-act{display:flex;align-items:center;gap:9px;margin-left:auto}
@media (min-width:820px){.skin-schematic .sch-nav-act{margin-left:20px}}
.skin-schematic .sch-toggle{font-family:var(--sch-mono);font-size:10px;letter-spacing:.12em;text-transform:uppercase;border:1px solid var(--rule-strong);background:transparent;color:var(--ink-2);border-radius:2px;padding:8px 11px;cursor:pointer;display:inline-flex;align-items:center;gap:7px}
.skin-schematic .sch-toggle:hover{color:var(--ink);border-color:var(--ink)}
.skin-schematic .sch-toggle i{width:7px;height:7px;border-radius:50%;background:var(--violet);display:block}
.skin-schematic .sch-mobnav{display:flex;gap:6px;overflow-x:auto;padding:9px var(--sch-gutter);border-bottom:1px solid var(--rule)}
@media (min-width:820px){.skin-schematic .sch-mobnav{display:none}}
.skin-schematic .sch-mobnav a{font-family:var(--sch-mono);font-size:10.5px;letter-spacing:.1em;border:1px solid var(--rule);border-radius:2px;color:var(--ink-3);padding:6px 11px;white-space:nowrap;text-decoration:none}
.skin-schematic .sch-mobnav a[aria-current="page"]{color:var(--ink);border-color:var(--ink)}

.skin-schematic .sch-footer{border-top:1px solid var(--rule);padding-top:34px;padding-bottom:42px;color:var(--ink-3);margin-top:auto}
.skin-schematic .sch-foot-grid{display:grid;gap:26px}
@media (min-width:760px){.skin-schematic .sch-foot-grid{grid-template-columns:1.4fr repeat(3,1fr)}}
.skin-schematic .sch-foot-grid h4{margin:0 0 12px;font-family:var(--sch-mono);font-size:10.5px;letter-spacing:.16em;text-transform:uppercase;color:var(--ink-3);font-weight:500}
.skin-schematic .sch-foot-grid ul{list-style:none;margin:0;padding:0;display:grid;gap:8px}
.skin-schematic .sch-foot-grid a{font-size:13.5px;color:var(--ink-2);text-decoration:none}
.skin-schematic .sch-foot-grid a:hover{color:var(--violet)}
.skin-schematic .sch-colophon{display:flex;flex-wrap:wrap;gap:14px;justify-content:space-between;margin-top:32px;padding-top:18px;border-top:1px solid var(--rule);font-family:var(--sch-mono);font-size:10.5px;letter-spacing:.1em;text-transform:uppercase}

/* ---------- hero ---------- */
.skin-schematic .sch-hero{padding-top:clamp(44px,5.5vw,76px);padding-bottom:clamp(32px,4vw,52px)}
.skin-schematic .sch-hero-grid{display:grid;gap:clamp(30px,4vw,54px)}
@media (min-width:980px){.skin-schematic .sch-hero-grid{grid-template-columns:minmax(0,1fr) minmax(0,1.02fr);align-items:center}}
.skin-schematic .sch-quiet{color:var(--ink-3)}
.skin-schematic .sch-cta-row{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}
.skin-schematic .sch-note{margin-top:20px;color:var(--ink-3);font-family:var(--sch-mono);font-size:11px;letter-spacing:.1em;text-transform:uppercase}
.skin-schematic .sch-diagram{position:relative;border:1px solid var(--rule);background:var(--surface);border-radius:3px;box-shadow:var(--shadow);overflow:hidden}
.skin-schematic .sch-diagram-bar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:9px 12px;border-bottom:1px solid var(--rule);color:var(--ink-3)}
.skin-schematic .sch-diagram canvas{display:block;width:100%;height:auto}

/* ---------- ledger / proof ---------- */
.skin-schematic .sch-proof{display:grid;gap:28px;align-items:end}
@media (min-width:860px){.skin-schematic .sch-proof{grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);gap:56px}}
.skin-schematic .sch-figure{font-family:var(--sch-display);font-variation-settings:"wdth" 112;font-weight:700;font-size:clamp(50px,7.6vw,92px);line-height:.86;letter-spacing:-.03em}
.skin-schematic .sch-figure small{display:block;font-family:var(--sch-mono);font-size:11px;font-weight:500;letter-spacing:.15em;text-transform:uppercase;color:var(--ink-3);margin-top:14px;line-height:1.45}
.skin-schematic .sch-ledger{width:100%;border-collapse:collapse}
.skin-schematic .sch-ledger td{padding:11px 0;border-top:1px solid var(--rule);font-size:14px;color:var(--ink-2);vertical-align:baseline}
.skin-schematic .sch-ledger tr:first-child td{border-top:0}
.skin-schematic .sch-ledger .v{font-family:var(--sch-mono);font-weight:600;color:var(--ink);text-align:right;white-space:nowrap}
.skin-schematic .sch-ledger .bar{width:32%;padding-left:18px}
.skin-schematic .sch-ledger .bar i{display:block;height:2px;background:var(--violet);opacity:.5}

/* ---------- catalogue ---------- */
.skin-schematic .sch-lead{display:grid;gap:clamp(24px,3vw,44px);margin-bottom:clamp(32px,4vw,52px)}
@media (min-width:900px){.skin-schematic .sch-lead{grid-template-columns:minmax(0,1fr) 300px;align-items:center}}
.skin-schematic .sch-spec{border:1px solid var(--rule);background:var(--surface);border-radius:3px;padding:18px;box-shadow:var(--shadow)}
.skin-schematic .sch-spec svg{display:block;width:100%;height:auto}
.skin-schematic .sch-kicker{font-family:var(--sch-mono);font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:var(--violet);margin:0 0 12px}
.skin-schematic .sch-index{display:grid}
@media (min-width:820px){.skin-schematic .sch-index{grid-template-columns:1fr 1fr;column-gap:clamp(28px,4vw,64px)}}
.skin-schematic .sch-row{display:grid;grid-template-columns:32px 1fr auto;gap:14px;align-items:baseline;padding:14px 0 13px;border-top:1px solid var(--rule);text-decoration:none;transition:padding-left .16s ease}
.skin-schematic .sch-row:hover{padding-left:8px}
.skin-schematic .sch-row:hover .name{color:var(--violet)}
.skin-schematic .sch-row .n{font-family:var(--sch-mono);font-size:11px;color:var(--ink-3);font-variant-numeric:tabular-nums}
.skin-schematic .sch-row .name{font-weight:600;font-size:15.5px;letter-spacing:-.005em;transition:color .16s ease}
.skin-schematic .sch-row .desc{display:block;font-size:13.5px;color:var(--ink-3);margin-top:3px;line-height:1.5}
.skin-schematic .sch-row .go{font-family:var(--sch-mono);font-size:11px;color:var(--ink-3);opacity:0;transition:opacity .16s ease}
.skin-schematic .sch-row:hover .go{opacity:1;color:var(--violet)}

/* ---------- features page ---------- */
.skin-schematic .sch-feat{display:grid;gap:clamp(28px,4vw,54px)}
@media (min-width:960px){.skin-schematic .sch-feat{grid-template-columns:216px minmax(0,1fr);align-items:start}}
.skin-schematic .sch-margin-index{position:sticky;top:82px;display:none}
@media (min-width:960px){.skin-schematic .sch-margin-index{display:block}}
.skin-schematic .sch-margin-index h4{margin:0 0 12px;font-family:var(--sch-mono);font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:var(--ink-3);font-weight:500}
.skin-schematic .sch-margin-index a{display:block;font-size:13.5px;color:var(--ink-3);text-decoration:none;padding:6px 0 6px 12px;border-left:1px solid var(--rule)}
.skin-schematic .sch-margin-index a:hover{color:var(--ink);border-left-color:var(--violet)}
.skin-schematic .sch-cap{border-top:1px solid var(--rule);padding-top:26px;padding-bottom:26px}
.skin-schematic .sch-cap:first-of-type{border-top:0;padding-top:0}
.skin-schematic .sch-cap h3{margin:0 0 6px}
.skin-schematic .sch-cap p{margin:0;color:var(--ink-2);max-width:62ch;font-size:15px}
.skin-schematic .sch-chips{display:flex;flex-wrap:wrap;gap:7px;margin:14px 0 0;padding:0;list-style:none}
.skin-schematic .sch-chips li{font-family:var(--sch-mono);font-size:10.5px;letter-spacing:.06em;color:var(--ink-3);border:1px solid var(--rule);border-radius:2px;padding:5px 9px}

/* ---------- pricing ---------- */
.skin-schematic .sch-cycle{display:inline-flex;border:1px solid var(--rule-strong);border-radius:2px;overflow:hidden}
.skin-schematic .sch-cycle button{font-family:var(--sch-mono);font-size:10.5px;letter-spacing:.14em;text-transform:uppercase;padding:9px 15px;border:0;background:transparent;color:var(--ink-3);cursor:pointer}
.skin-schematic .sch-cycle button[aria-pressed="true"]{background:var(--ink);color:var(--paper)}
.skin-schematic .sch-cycle-note{font-family:var(--sch-mono);font-size:10.5px;letter-spacing:.1em;text-transform:uppercase;color:var(--cyan);margin-left:14px}
.skin-schematic .sch-scroll{overflow-x:auto}
.skin-schematic .sch-ladder{width:100%;border-collapse:collapse}
.skin-schematic .sch-ladder caption{text-align:left;font-family:var(--sch-mono);font-size:10.5px;letter-spacing:.14em;text-transform:uppercase;color:var(--ink-3);padding-bottom:10px}
.skin-schematic .sch-ladder th{font-family:var(--sch-mono);font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:var(--ink-3);font-weight:500;text-align:left;padding:0 12px 10px;border-bottom:1px solid var(--rule-strong)}
.skin-schematic .sch-ladder th.num,.skin-schematic .sch-ladder td.num{text-align:right;font-variant-numeric:tabular-nums}
.skin-schematic .sch-ladder td{padding:15px 12px;border-bottom:1px solid var(--rule);vertical-align:baseline}
.skin-schematic .sch-ladder tbody tr:hover td{background:var(--surface)}
.skin-schematic .sch-ladder .pname{font-family:var(--sch-display);font-variation-settings:"wdth" 112;font-weight:700;font-size:17.5px;letter-spacing:-.012em;white-space:nowrap}
.skin-schematic .sch-ladder .pfor{display:block;font-size:13.5px;color:var(--ink-3);margin-top:4px;line-height:1.5;max-width:46ch}
.skin-schematic .sch-ladder .amt{font-family:var(--sch-display);font-variation-settings:"wdth" 106;font-weight:700;font-size:20px;letter-spacing:-.02em;white-space:nowrap}
.skin-schematic .sch-ladder .per{font-family:var(--sch-mono);font-size:10.5px;color:var(--ink-3);display:block;margin-top:4px;white-space:nowrap}
.skin-schematic .sch-ladder tr.pick td{background:var(--violet-soft)}
.skin-schematic .sch-ladder tr.pick td:first-child{box-shadow:inset 3px 0 0 var(--violet)}
.skin-schematic .sch-flag{display:inline-block;font-family:var(--sch-mono);font-size:9.5px;letter-spacing:.14em;text-transform:uppercase;color:var(--violet);border:1px solid currentColor;border-radius:2px;padding:2px 6px;margin-left:10px;vertical-align:2px}
.skin-schematic .sch-ladder .act a{font-family:var(--sch-mono);font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-3);text-decoration:none;white-space:nowrap}
.skin-schematic .sch-ladder tr:hover .act a{color:var(--violet)}
@media (max-width:760px){
  .skin-schematic .sch-ladder .pfor{display:none}
  .skin-schematic .sch-ladder td,.skin-schematic .sch-ladder th{padding-left:8px;padding-right:8px}
}
.skin-schematic .sch-matrix{width:100%;border-collapse:collapse;min-width:560px}
.skin-schematic .sch-matrix th,.skin-schematic .sch-matrix td{padding:12px 14px;border-top:1px solid var(--rule);text-align:right;font-family:var(--sch-mono);font-size:12.5px;color:var(--ink-2);font-variant-numeric:tabular-nums}
.skin-schematic .sch-matrix thead th{border-top:0;border-bottom:1px solid var(--rule-strong);font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:var(--ink-3);font-weight:500}
.skin-schematic .sch-matrix th[scope="row"]{text-align:left;font-family:var(--sch-body);font-size:14px;color:var(--ink);font-weight:500;width:40%}
.skin-schematic .sch-matrix .col-pick{background:var(--violet-soft);color:var(--ink)}
.skin-schematic .sch-matrix .no{color:var(--ink-3);opacity:.5}

.skin-schematic .sch-coins{display:grid;gap:0}
@media (min-width:760px){.skin-schematic .sch-coins{grid-template-columns:repeat(4,1fr);column-gap:1px;background:var(--rule)}}
.skin-schematic .sch-coin{background:var(--paper);padding:20px 18px 20px 0}
@media (min-width:760px){.skin-schematic .sch-coin{padding-left:18px}.skin-schematic .sch-coin:first-child{padding-left:0}}
.skin-schematic .sch-coin b{display:block;font-family:var(--sch-display);font-variation-settings:"wdth" 108;font-weight:700;font-size:26px;letter-spacing:-.02em;font-variant-numeric:tabular-nums}
.skin-schematic .sch-coin span{display:block;font-family:var(--sch-mono);font-size:10.5px;letter-spacing:.12em;text-transform:uppercase;color:var(--ink-3);margin-top:6px}
.skin-schematic .sch-coin em{display:block;font-style:normal;font-size:14px;color:var(--ink-2);margin-top:10px}

.skin-schematic .sch-faq{border-top:1px solid var(--rule)}
.skin-schematic .sch-faq details{border-bottom:1px solid var(--rule)}
.skin-schematic .sch-faq summary{cursor:pointer;list-style:none;padding:16px 0;font-weight:600;font-size:15.5px;display:flex;justify-content:space-between;gap:16px}
.skin-schematic .sch-faq summary::-webkit-details-marker{display:none}
.skin-schematic .sch-faq summary::after{content:"+";font-family:var(--sch-mono);color:var(--violet)}
.skin-schematic .sch-faq details[open] summary::after{content:"\2013"}
.skin-schematic .sch-faq p{margin:0 0 18px;color:var(--ink-2);max-width:64ch;font-size:15px}

/* ---------- about ---------- */
.skin-schematic .sch-about{display:grid;gap:clamp(30px,4vw,60px)}
@media (min-width:900px){.skin-schematic .sch-about{grid-template-columns:minmax(0,1fr) minmax(0,.82fr)}}
.skin-schematic .sch-prose p{color:var(--ink-2);max-width:58ch;margin:0 0 18px}
.skin-schematic .sch-prose p:last-child{margin-bottom:0}
.skin-schematic .sch-prose p:first-child{color:var(--ink);font-size:18px}
.skin-schematic .sch-timeline{list-style:none;margin:0;padding:0}
.skin-schematic .sch-timeline li{display:grid;grid-template-columns:62px 1fr;gap:18px;padding:15px 0;border-top:1px solid var(--rule)}
.skin-schematic .sch-timeline li:first-child{border-top:0;padding-top:0}
.skin-schematic .sch-timeline time{font-family:var(--sch-mono);font-size:12px;color:var(--violet);font-weight:600;font-variant-numeric:tabular-nums}
.skin-schematic .sch-timeline b{display:block;font-size:15px;font-weight:600}
.skin-schematic .sch-timeline span{font-size:13.5px;color:var(--ink-3)}
.skin-schematic .sch-panel{border:1px solid var(--rule);background:var(--surface);border-radius:3px;padding:20px;box-shadow:var(--shadow)}
.skin-schematic .sch-dots{display:grid;grid-template-columns:repeat(28,1fr);gap:6px;margin-top:14px}
.skin-schematic .sch-dots i{display:block;aspect-ratio:1;border-radius:50%;background:var(--rule-strong);opacity:.5}
.skin-schematic .sch-dots i.on{background:var(--violet);opacity:1}
.skin-schematic .sch-principles{display:grid;gap:0}
@media (min-width:820px){.skin-schematic .sch-principles{grid-template-columns:1fr 1fr;column-gap:clamp(28px,4vw,64px)}}
.skin-schematic .sch-principle{border-top:1px solid var(--rule);padding:18px 0}
.skin-schematic .sch-principle b{display:block;font-family:var(--sch-display);font-variation-settings:"wdth" 110;font-weight:700;font-size:17px;letter-spacing:-.012em;margin-bottom:5px}
.skin-schematic .sch-principle p{margin:0;color:var(--ink-3);font-size:14.5px;max-width:46ch}

.skin-schematic .sch-cta{display:grid;gap:22px;align-items:center}
@media (min-width:820px){.skin-schematic .sch-cta{grid-template-columns:1fr auto}}

@media (prefers-reduced-motion: reduce){
  .skin-schematic *{animation:none !important;transition:none !important}
}
</style>
@endverbatim
@endpush
