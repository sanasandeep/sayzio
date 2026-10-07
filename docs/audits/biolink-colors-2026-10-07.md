# Block color audit

The 139-type source checklist shares one root/child renderer. Color review covered the shared style form, effective style resolution, live color patching and inline page-ink usage across dedicated and inline block renderers.

Confirmed fixes:

- Inner renderers now receive effective block text color instead of always receiving page text color. Container children resolve their own override on entry.
- Text-color changes request the existing full preview re-render so baked inline colors and opacity are recomputed. Clearing a color follows the same route. Wrapper-only patching could acknowledge success while text retained its previous color.
- Replaced 89 hex-only opacity suffixes with CSS color mixing across 20 content renderers and the shared inline renderer. Short hex, RGB and named page/block colors now produce valid opacity declarations.
- Restored Text controls for YouTube feed, Tidal, Mixcloud, Anchor FM, Kick, Rumble and VK fallback blocks, which render their own labels or links. Provider-controlled embed interiors remain outside the shared text controls.

Validation: Chromium regression confirmed text-color edits return handled=false (the editor re-render path) while label/alignment edits still work. Blade/Alpine guards and diff check pass. Added PHP render tests for block ink priority and non-hex opacity; PHP/vendor are unavailable locally, so their execution is pending CI.

Limits: this is source verification plus isolated browser testing, not an authenticated live test of every design and provider embed. Existing badge/CTA content color controls and design-specific colors can have their own precedence; a universal recoloring of all decorative/provider colors is not claimed.
