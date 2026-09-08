{{--
    Precision skin.

    Not a new page. This restyles the home page that already exists: same
    eleven sections, same copy, same testimonials, same interactive demos,
    in the same order. Only the look changes.

    It is a stylesheet rather than a rewrite on purpose. Nothing in the
    content partials is touched, so the skin cannot break the page's
    behaviour, and switching the design back in Marketing Settings restores
    today's look exactly.

    What it changes:
      * A light ground with a ruled content column, instead of dark glass.
      * Section headers left-aligned with a coloured eyebrow and rule,
        instead of eight identical centred blocks down the page.
      * Flat cards with hairline borders, instead of blurred glass.
      * Alternating section grounds, so the page has a rhythm.
--}}
<link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
/* ---------- palette, sampled from the logo ---------- */
html.szskin{
  --sz-bg:#FFFFFF; --sz-bg2:#F7F8FC; --sz-rule:#E6E8F2; --sz-rule2:#D2D6E6;
  --sz-ink:#0B1033; --sz-ink2:#4E5680; --sz-ink3:#8B91B0;
  --sz-blue:#3858F8; --sz-violet:#7040F8; --sz-indigo:#4F46E5; --sz-teal:#0EA5A5;
  --sz-emerald:#10B981; --sz-amber:#E08A00; --sz-coral:#F1603C; --sz-rose:#E23D6E; --sz-plum:#9333EA;
  --sz-col:1180px;
}
html.szskin, html.szskin body{background:var(--sz-bg)}
html.szskin body{color:var(--sz-ink);font-family:'Instrument Sans','Space Grotesk',sans-serif}

/* The aurora glow belongs to the dark page. */
html.szskin .aurora{display:none}

/* ---------- the ruled content column ----------
   The rules sit on each section's own container rather than on two fixed
   lines down the viewport. The page mixes max-w-6xl and max-w-7xl
   containers, so fixed lines could only ever align with one of them and cut
   through the text of the other. Pinning one width here and drawing the
   rules on the container makes every section line up with every other. */
html.szskin section > div.mx-auto{
  max-width:1240px;
  border-left:1px solid var(--sz-rule);border-right:1px solid var(--sz-rule);
  padding-left:clamp(20px,3vw,40px);padding-right:clamp(20px,3vw,40px)
}

/* ---------- one vertical rhythm ----------
   The page shipped with section padding of 40, 80, 96, 112 and 128px and
   header margins of 48, 56 and 64. That inconsistency is most of what read
   as "the spacing is off". One value each, everywhere. */
html.szskin section{padding-top:clamp(52px,5.4vw,80px);padding-bottom:clamp(52px,5.4vw,80px)}
html.szskin section > div > div.text-center{margin-bottom:clamp(26px,2.8vw,40px)}

/* ---------- typography ---------- */
html.szskin h1,html.szskin h2,html.szskin h3,html.szskin h4{letter-spacing:-.032em;color:var(--sz-ink)}
html.szskin .grad-text{background:none;-webkit-text-fill-color:currentColor;color:var(--sz-blue)}
html.szskin .text-gray-400,html.szskin .text-gray-300,html.szskin .text-gray-500,
html.szskin .text-gray-200,html.szskin .text-gray-100{color:var(--sz-ink2)}
html.szskin .text-white{color:var(--sz-ink)}

/* ---------- section headers: left, ruled, colour-coded ----------
   Every section on this page opens with the same centred stack. Left-align
   it, cap the measure, and give the heading the coloured rule that carries
   the section's accent, so eight sections stop looking like one section
   repeated eight times. */
html.szskin section > div > div.text-center{
  text-align:left;margin-left:0;margin-right:0;max-width:none;
  display:grid;gap:11px;
}
@media (min-width:900px){
  html.szskin section > div > div.text-center{grid-template-columns:1.12fr .88fr;align-items:end;column-gap:44px}
  html.szskin section > div > div.text-center > h2{grid-column:1}
  html.szskin section > div > div.text-center > p{grid-column:2;grid-row:2;margin:0}
  html.szskin section > div > div.text-center > div:first-child{grid-column:1/-1}
}
html.szskin section > div > div.text-center > div:first-child{
  display:flex;align-items:center;gap:9px;font-size:11.5px;letter-spacing:.14em;margin-bottom:0
}
html.szskin section > div > div.text-center > div:first-child::before{
  content:"";width:7px;height:7px;border-radius:2px;background:currentColor;flex:none
}
html.szskin section > div > div.text-center > div:first-child::after{
  content:"";flex:1;height:1px;background:var(--sz-rule);max-width:120px
}
html.szskin section > div > div.text-center > h2{
  position:relative;padding-bottom:14px;margin-bottom:0;text-wrap:balance
}
html.szskin section > div > div.text-center > h2::after{
  content:"";position:absolute;left:0;bottom:0;width:56px;height:3px;border-radius:3px;background:var(--sz-blue)
}
/* The accent rotates down the page so no two neighbours share a colour. */
html.szskin section:nth-of-type(3n+1) > div > .text-center.mx-auto > h2::after{background:var(--sz-indigo)}
html.szskin section:nth-of-type(3n+2) > div > .text-center.mx-auto > h2::after{background:var(--sz-amber)}
html.szskin section:nth-of-type(3n)   > div > .text-center.mx-auto > h2::after{background:var(--sz-teal)}

/* ---------- components built for the dark page ----------
   Several newer components hardcode rgba(255,255,255,...) text instead of
   taking a token, so they vanish on a light ground. An audit of the live
   page found around 250 such elements. These are the named offenders; the
   underlying gap is in each component's own light-mode pairing and is worth
   fixing there, since it affects the site's existing light mode too. */
html.szskin :is(.lt-chip,.lt-chip span,.lt-rail span,.lt-pane-desc,.lt-pane-title,
  .lt-pane-badge,.lt-chip-new,.ms-item-text,.dc-dialchan-label,.dc-digit,
  .aisx-card-desc,.th-pill,.bs-word){color:var(--sz-ink2)}
html.szskin :is(.lt-chip.is-active,.lt-chip[aria-selected="true"],.aisx-card-title,.lt-pane-title){color:var(--sz-ink)}
html.szskin [style*="color:#fff"],
html.szskin [style*="color: #fff"],
html.szskin [style*="color:rgba(255,255,255"]{color:var(--sz-ink2) !important}

/* ---------- cards: flat and ruled, not blurred glass ---------- */
html.szskin .glass,html.szskin .glass-2,html.szskin .trust-band-card{
  background:var(--sz-bg);border:1px solid var(--sz-rule);
  backdrop-filter:none;-webkit-backdrop-filter:none;
  box-shadow:0 20px 44px -34px rgba(11,16,51,.30);border-radius:16px
}
html.szskin .glass:hover,html.szskin .glass-2:hover{border-color:var(--sz-rule2)}
html.szskin .rounded-3xl{border-radius:16px}
html.szskin .rounded-2xl{border-radius:13px}
html.szskin .border-white\/10,html.szskin .border-white\/5{border-color:var(--sz-rule)}
html.szskin .bg-white\/5,html.szskin .bg-white\/\[0\.04\],html.szskin .bg-white\/\[0\.02\]{background:var(--sz-bg2)}
html.szskin .divide-white\/10 > * + *{border-color:var(--sz-rule)}

/* ---------- section rhythm ----------
   Alternate grounds, full bleed, so the page is not one continuous surface.
   This is the change that stops it reading as eight of the same block. */
html.szskin section:nth-of-type(even){background:var(--sz-bg2)}
html.szskin section{position:relative}
html.szskin section + section{border-top:1px solid var(--sz-rule)}

/* ---------- the two flat colour blocks ----------
   Indigo from the mockup, on the two sections that most need to interrupt
   the scroll: the four-step explainer and the closing call to action. Both
   are short and mostly headings and buttons, which is what survives being
   put on a colour. The testimonial wall is deliberately left on the light
   ground: twenty cards of body copy is exactly where contrast goes wrong,
   because the page's light-mode rules turn its greys dark. */
html.szskin section#how-it-works,
html.szskin section#cta-final{background:#3E3AE0;border-top-color:rgba(255,255,255,.14)}
html.szskin section#how-it-works :is(h1,h2,h3,h4,p,span,li,a,strong,em,div),
html.szskin section#cta-final :is(h1,h2,h3,h4,p,span,li,a,strong,em,div){color:#FFFFFF !important}
html.szskin section#how-it-works .grad-text,
html.szskin section#cta-final .grad-text{color:#E5FF6B !important}
html.szskin section#how-it-works h2::after,
html.szskin section#cta-final h2::after{background:#E5FF6B}
html.szskin section#how-it-works .glass,
html.szskin section#how-it-works .glass-2,
html.szskin section#cta-final .glass,
html.szskin section#cta-final .glass-2{
  background:rgba(255,255,255,.09);border-color:rgba(255,255,255,.18);box-shadow:none
}
html.szskin section#how-it-works > div > div.text-center > div:first-child::after,
html.szskin section#cta-final > div > div.text-center > div:first-child::after{background:rgba(255,255,255,.30)}

/* ---------- buttons ---------- */
html.szskin .btn-primary,html.szskin .cta-primary{border-radius:11px}

/* ---------- respect the toggle ----------
   A visitor who explicitly picks dark still gets the dark page; the skin
   only decides what the page opens as. */
</style>
