---
name: Cookie consent backdrop vs floating launcher clicks
description: Why a floating corner widget must out-stack the consent host (not just dodge its card) to stay clickable.
---

# Consent backdrop swallows floating-launcher clicks

The site-assistant launcher (and any fixed bottom-corner widget) shares the page
with the cookie-consent host (`.cc-host`, z-index ~2.1B). The consent host is
`pointer-events:none` except its card/backdrop. In **modal/takeover** layouts it
renders a full-screen `.cc-backdrop` (`position:absolute; inset:0;
pointer-events:auto`) that blankets the entire viewport — including the corner —
even though the centered card never physically overlaps the corner.

**Rule:** two independent defences are needed, and the stacking one is the easy
one to forget:
1. Physical nudge — move the launcher's `bottom` offset up so it clears a
   bottom-corner consent card (handles corner/pill/banner/inline).
2. Stacking lift — raise the launcher's `z-index` above the consent host
   whenever `.cc-host` exists, so the button pokes through a modal/takeover
   backdrop. Clear the inline z-index when the host is gone so nothing hovers
   over the rest of the UI afterward.

**Why:** the launcher self-lift logic originally only adjusted the bottom offset
and explicitly skipped modal/takeover ("centered card doesn't overlap the
corner"), which ignored the full-screen backdrop and made the launcher
unclickable for fresh visitors who see a modal consent banner.

**How to apply:** keep the wrapper `pointer-events:none` and only the button
`pointer-events:auto`, so raising z-index lets the button receive clicks while
the backdrop still blocks the rest of the page (consent behaviour unchanged).

**Gotcha when testing blade changes:** Laravel serves compiled views from
`storage/framework/views`. If a blade edit doesn't seem to take effect in the
browser, run `php artisan view:clear` before re-testing.
