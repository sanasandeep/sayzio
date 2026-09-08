{{--
    Flat surfaces.

    No design change: same page, same sections, same layout, same colours.
    This removes two effects that were layered over all of it.

      1. Liquid glass. Every backdrop blur is off, and the surfaces that
         relied on it get a solid background and a hairline border instead,
         so a card is a card rather than a translucent pane over whatever
         happens to be behind it.

      2. The decorative gradients. The drifting auras and the blurred blobs
         behind cards are gone, the gradient washes on card surfaces are
         flat, and the feature ticker is one colour rather than a five stop
         rainbow.

    Brand marks, product mockups and the primary call-to-action buttons keep
    their gradients on purpose: those read as the product's identity rather
    than as decoration. Say the word and they can go flat too.

    Scope. This was written from an audit of the live page, which found 54
    elements still running a backdrop blur and 242 carrying a gradient
    background, so the rules aim at what is actually there rather than at
    the sections someone happened to look at.
--}}
<style>
:root{
  --fs-card:#17162A;
  --fs-rule:rgba(255,255,255,.10);
  --fs-rule-2:rgba(255,255,255,.18);
  --fs-ground:#11101E;
  --fs-shadow:0 22px 50px -34px rgba(0,0,0,.75);
  --fs-ink-2:#A9B0D0;
}
html.light-mode{
  --fs-card:#FFFFFF;
  --fs-rule:#E6E8F2;
  --fs-rule-2:#D2D6E6;
  --fs-ground:#F7F8FC;
  --fs-shadow:0 20px 44px -34px rgba(11,16,51,.28);
  --fs-ink-2:#4E5680;
}

/* ---------- 1. no liquid glass ---------- */
*,*::before,*::after{backdrop-filter:none !important;-webkit-backdrop-filter:none !important}

:is(.glass,.glass-2,.trust-band-card,.bs-card,.bs-pillar,.buzz-card,.prem-feat,.zio-node-btn,.geo-ticker){
  background-image:none !important;
  background-color:var(--fs-card) !important;
  border:1px solid var(--fs-rule) !important;
  box-shadow:var(--fs-shadow) !important;
}
:is(.glass,.glass-2):hover{border-color:var(--fs-rule-2) !important}

/* The inner highlight and wash those cards drew on their own pseudo
   elements went with the blur; without it they read as smudges. */
:is(.glass,.glass-2,.trust-band-card,.bs-card,.bs-pillar,.buzz-card,.prem-feat,.zio-node-btn)::before,
:is(.glass,.glass-2,.trust-band-card,.bs-card,.bs-pillar,.buzz-card,.prem-feat,.zio-node-btn)::after{
  background-image:none !important;
}

/* ---------- 2. fewer gradients ---------- */

/* Drifting auras, mascot halo, and the blurred circles behind cards. */
:is(.aurora,.zio-glow,.zio-mascot-halo,.aud-blob,.lt-blob,.ai-zone-aura,.aisx-blob){display:none !important}
.absolute[class*="blur-"]{display:none !important}
.absolute.rounded-full[class*="opacity-"]{display:none !important}

/* The feature ticker was a five stop rainbow. */
.grad-bar{background-image:none !important;background-color:#3E3AE0 !important}

/* Product mock surfaces sit on the page ground rather than a wash. */
:is(.lt-mock-zone,.bb-screen,.rb-paper,.geo-map){
  background-image:none !important;background-color:var(--fs-ground) !important}

/* ---------- 3. light mode contrast ----------
   Not part of the flattening, and not caused by it: several newer
   components hardcode rgba(255,255,255,...) text instead of taking a
   colour token, so in light mode they turn white on white. The link type
   chips lose every label. This is a real bug on the site today, with no
   skin involved; the durable fix is a light-mode pairing inside each
   component, and this holds the line until then. */
html.light-mode :is(.lt-chip,.lt-chip span,.lt-rail span,.lt-pane-desc,.lt-pane-title,
  .lt-pane-badge,.lt-chip-new,.ms-item-text,.dc-dialchan-label,.dc-digit,
  .aisx-card-desc,.th-pill){color:var(--fs-ink-2) !important}
html.light-mode :is(.lt-chip.is-active,.lt-chip[aria-selected="true"],.aisx-card-title){color:#0B1033 !important}
</style>
