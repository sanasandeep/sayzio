{{--
    Precision skin.

    Not a new page. This restyles the home page that already exists: same
    sections, same copy, same testimonials, same interactive demos, in the
    same order. Only the look changes, and it changes as a stylesheet, so
    nothing in the content partials is touched and switching the design back
    in Marketing Settings restores today's look exactly.

    THEME. Dark is the base, because that is what this site is and what the
    components were built for. html.light-mode is the site's own light switch
    and it repaints the same tokens. Every rule below reads a token, so the
    theme toggle works in both directions. An earlier version defined the
    light palette unconditionally, which forced a white ground onto dark
    components whenever a visitor had chosen dark: a white page wearing dark
    cards. That is what this structure prevents.
--}}
<link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
/* ---------- palette: dark base ---------- */
html.szskin{
  --sz-bg:#0A0A14; --sz-bg2:#11101E; --sz-card:#17162A;
  --sz-rule:rgba(255,255,255,.10); --sz-rule2:rgba(255,255,255,.20);
  --sz-ink:#F3F5FF; --sz-ink2:#A9B0D0; --sz-ink3:#7C84AC;
  --sz-accent:#7B8CFF; --sz-indigo:#7B8CFF; --sz-teal:#2AD4D4; --sz-amber:#F5B133;
  --sz-emerald:#3BDCA2; --sz-coral:#FF8B69; --sz-rose:#FF6D93; --sz-plum:#C182FF;
  --sz-lime:#E5FF6B; --sz-block:#3E3AE0;
  --sz-shadow:0 26px 60px -34px rgba(0,0,0,.8);
}
/* ---------- palette: light ---------- */
html.szskin.light-mode{
  --sz-bg:#FFFFFF; --sz-bg2:#F7F8FC; --sz-card:#FFFFFF;
  --sz-rule:#E6E8F2; --sz-rule2:#D2D6E6;
  --sz-ink:#0B1033; --sz-ink2:#4E5680; --sz-ink3:#8B91B0;
  --sz-accent:#3858F8; --sz-indigo:#4F46E5; --sz-teal:#0EA5A5; --sz-amber:#E08A00;
  --sz-emerald:#10B981; --sz-coral:#F1603C; --sz-rose:#E23D6E; --sz-plum:#9333EA;
  --sz-shadow:0 22px 50px -34px rgba(11,16,51,.30);
}
html.szskin,html.szskin body{background:var(--sz-bg)}
html.szskin body{color:var(--sz-ink);font-family:'Instrument Sans','Space Grotesk',sans-serif}
html.szskin .aurora{opacity:.35}
html.szskin.light-mode .aurora{display:none}

/* ---------- one content column ----------
   The page mixes max-w-6xl and max-w-7xl containers. Pinning one width and
   drawing the rules on the container itself is what makes every section line
   up with every other; fixed lines down the viewport could only ever agree
   with one of the two widths and cut through the text of the other. */
html.szskin section > div.mx-auto{
  max-width:1240px;
  border-left:1px solid var(--sz-rule);border-right:1px solid var(--sz-rule);
  padding-left:clamp(20px,3vw,40px);padding-right:clamp(20px,3vw,40px)
}

/* ---------- one vertical rhythm ----------
   The page shipped section padding of 40, 80, 96, 112 and 128px and header
   margins of 48, 56 and 64. One value each. */
html.szskin section{padding-top:clamp(52px,5.4vw,80px);padding-bottom:clamp(52px,5.4vw,80px)}
html.szskin section > div > div.text-center{margin-bottom:clamp(26px,2.8vw,40px)}
html.szskin section:nth-of-type(even){background:var(--sz-bg2)}
html.szskin section + section{border-top:1px solid var(--sz-rule)}

/* ---------- typography ---------- */
html.szskin h1,html.szskin h2,html.szskin h3,html.szskin h4{letter-spacing:-.032em;color:var(--sz-ink)}
html.szskin .grad-text{background:none;-webkit-text-fill-color:currentColor;color:var(--sz-accent)}
html.szskin :is(.text-gray-100,.text-gray-200,.text-gray-300,.text-gray-400,.text-gray-500){color:var(--sz-ink2)}

/* ---------- section headers: left, ruled, colour-coded ----------
   Every section opens with the same centred stack. Left-aligned, with the
   measure capped and a coloured rule under the heading, eight sections stop
   looking like one section repeated eight times. */
html.szskin section > div > div.text-center{text-align:left;margin-left:0;margin-right:0;max-width:none;display:grid;gap:11px}
@media (min-width:900px){
  html.szskin section > div > div.text-center{grid-template-columns:1.12fr .88fr;align-items:end;column-gap:44px}
  html.szskin section > div > div.text-center > h2{grid-column:1}
  html.szskin section > div > div.text-center > p{grid-column:2;grid-row:2;margin:0}
  html.szskin section > div > div.text-center > div:first-child{grid-column:1/-1}
}
/* The eyebrow is a pill in the markup. Spanning the grid made it a
   full-width bar, so it shrinks to its own content instead. */
html.szskin section > div > div.text-center > div:first-child{display:flex;align-items:center;gap:9px;font-size:11.5px;letter-spacing:.14em;margin-bottom:0;width:max-content;max-width:100%;justify-self:start}
html.szskin section > div > div.text-center > div:first-child::before{content:"";width:7px;height:7px;border-radius:2px;background:currentColor;flex:none}
html.szskin section > div > div.text-center > div:first-child::after{content:"";width:90px;flex:none;height:1px;background:var(--sz-rule)}
html.szskin section > div > div.text-center > h2{position:relative;padding-bottom:14px;margin-bottom:0;text-wrap:balance}
html.szskin section > div > div.text-center > h2::after{content:"";position:absolute;left:0;bottom:0;width:56px;height:3px;border-radius:3px;background:var(--sz-accent)}
html.szskin section:nth-of-type(3n+1) > div > div.text-center > h2::after{background:var(--sz-indigo)}
html.szskin section:nth-of-type(3n+2) > div > div.text-center > h2::after{background:var(--sz-amber)}
html.szskin section:nth-of-type(3n)   > div > div.text-center > h2::after{background:var(--sz-teal)}

/* ---------- one card treatment ----------
   Radius is set only where the page asked for a card radius. An earlier
   version set it on .glass itself, which reshaped the pill-shaped eyebrow
   into a full-width rectangle, because that pill is a .glass too. */
html.szskin :is(.glass,.glass-2,.trust-band-card){
  background:var(--sz-card);border:1px solid var(--sz-rule);
  backdrop-filter:none;-webkit-backdrop-filter:none;box-shadow:var(--sz-shadow)
}
html.szskin :is(.glass,.glass-2):hover{border-color:var(--sz-rule2)}
html.szskin :is(.rounded-3xl,.rounded-2xl):not(.rounded-full){border-radius:16px}
html.szskin .rounded-full{border-radius:9999px}
html.szskin :is(.border-white\/10,.border-white\/5){border-color:var(--sz-rule)}
html.szskin :is(.bg-white\/5,.bg-white\/\[0\.04\],.bg-white\/\[0\.02\]){background:var(--sz-bg2)}

/* ---------- components built for the dark page ----------
   Several newer components hardcode rgba(255,255,255,...) text instead of
   taking a token, so on a light ground they vanish: an audit of the live
   page found around 250 such elements, and the link type chips had lost
   every label. Only light mode needs the correction; in dark they are right
   as they are. The real fix belongs in each component's own light-mode
   pairing, since this affects the site's existing light mode too. */
html.szskin.light-mode :is(.lt-chip,.lt-chip span,.lt-rail span,.lt-pane-desc,
  .lt-pane-title,.lt-pane-badge,.lt-chip-new,.ms-item-text,.dc-dialchan-label,
  .dc-digit,.aisx-card-desc,.th-pill){color:var(--sz-ink2)}
html.szskin.light-mode :is(.lt-chip.is-active,.lt-chip[aria-selected="true"],.aisx-card-title,.lt-pane-title){color:var(--sz-ink)}
html.szskin.light-mode [style*="color:#fff"],
html.szskin.light-mode [style*="color: #fff"],
html.szskin.light-mode [style*="color:rgba(255,255,255"]{color:var(--sz-ink2) !important}

/* ---------- flat, everywhere ----------
   No liquid glass and no decorative gradients. Rather than chase this
   section by section, the page was audited in the browser: 54 elements were
   still running a backdrop blur and 242 carried a gradient background. The
   rules below are aimed at what that audit actually found, so the treatment
   is the same in every section instead of only the ones someone looked at.

   Small brand marks and the product mockups keep their own colour: they are
   content, not chrome. What goes is the blur, the glow, the drifting blobs
   and the gradient washes on surfaces and buttons. */
html.szskin *,html.szskin *::before,html.szskin *::after{
  backdrop-filter:none !important;-webkit-backdrop-filter:none !important}

/* Decorative auras, halos and the blurred circles behind cards. */
html.szskin :is(.aurora,.zio-glow,.zio-mascot-halo,.aud-blob,.lt-blob,.ai-zone-aura,.aisx-blob,.bs-glyph){display:none !important}
html.szskin .absolute[class*="blur-"]{display:none !important}
html.szskin .absolute.rounded-full[class*="opacity-"]{display:none !important}

/* One surface treatment for every card-like thing on the page. */
html.szskin :is(.glass,.glass-2,.trust-band-card,.bs-card,.bs-pillar,.buzz-card,.prem-feat,.zio-node-btn,.geo-ticker){
  background-image:none !important;background-color:var(--sz-card) !important;
  border:1px solid var(--sz-rule) !important;box-shadow:var(--sz-shadow) !important}
html.szskin :is(.glass,.glass-2,.trust-band-card,.bs-card,.bs-pillar,.buzz-card,.prem-feat,.zio-node-btn)::before,
html.szskin :is(.glass,.glass-2,.trust-band-card,.bs-card,.bs-pillar,.buzz-card,.prem-feat,.zio-node-btn)::after{
  background-image:none !important}

/* Buttons: one solid accent, no gradient and no glow. */
html.szskin :is(.btn-glow,.btn-bounce,.btn-cta,.ai-gen-btn,.mf-btn,.bb-btn){
  background-image:none !important;background-color:var(--sz-accent) !important;color:#FFFFFF !important;
  box-shadow:none !important;border-radius:11px !important}

/* The feature ticker was a five stop rainbow; it is one flat colour now. */
html.szskin .grad-bar{background-image:none !important;background-color:var(--sz-block) !important}

/* Product mock surfaces sit on the page ground rather than their own wash. */
html.szskin :is(.lt-mock-zone,.bb-screen,.rb-paper,.geo-map){background-image:none !important;background-color:var(--sz-bg2) !important}

/* ---------- two flat colour blocks ----------
   The same indigo in both themes, on the two sections that most need to
   interrupt the scroll. Both are short and mostly headings and buttons,
   which is what survives being put on a colour. The testimonial wall is
   deliberately left alone: twenty cards of body copy is where contrast
   goes wrong. */
html.szskin :is(section#how-it-works,section#cta-final){background:var(--sz-block);border-top-color:rgba(255,255,255,.14)}
html.szskin :is(section#how-it-works,section#cta-final) :is(h1,h2,h3,h4,p,span,li,a,strong,em,div){color:#FFFFFF !important}
html.szskin :is(section#how-it-works,section#cta-final) .grad-text{color:var(--sz-lime) !important}
html.szskin :is(section#how-it-works,section#cta-final) h2::after{background:var(--sz-lime)}
html.szskin :is(section#how-it-works,section#cta-final) :is(.glass,.glass-2){background:rgba(255,255,255,.09);border-color:rgba(255,255,255,.18);box-shadow:none}
html.szskin :is(section#how-it-works,section#cta-final) > div.mx-auto{border-left-color:rgba(255,255,255,.16);border-right-color:rgba(255,255,255,.16)}
html.szskin :is(section#how-it-works,section#cta-final) > div > div.text-center > div:first-child::after{background:rgba(255,255,255,.30)}
</style>
