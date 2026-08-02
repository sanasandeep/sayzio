---
name: Mega-menu panels must be near-opaque + page dim
description: Marketing nav dropdown legibility — why translucent glass fails and the hero paints through even opaque panels.
---

Rule: nav dropdown/mega-menu surfaces (`.glass-dropdown`) must use near-opaque fills (~0.96-0.99), never the decorative liquid-glass low-alpha recipe — menus are functional overlays over arbitrary content.

**Why (two separate failure modes):**
1. Low-alpha glass fill lets page content bleed through the panel (unreadable menu — user-reported).
2. Even with a 0.99-opaque panel at correct z-order (fixed z-50 nav > z-10 sections), the home hero copy (animated reveal/gradient text) still paints ~15% THROUGH the panel — a compositing quirk. No CSS on the panel/nav fixes it (z bumps, isolation, disabling backdrop-filter/animations all fail); a minimal reduced test renders correctly, so it's triggered by something in the hero subtree.

**How to apply:** the fix is the `mega-menu-open` html class (toggled by the header Alpine `x-effect` on `openMenu`) + CSS dimming `body > section, main.mkt-site-main` to opacity .15 while a mega menu is open. This both kills the paint-through and gives standard focus UX. Don't remove either half; don't re-thin the dropdown fill.

Debug tip: `elementsFromPoint` hit-testing and computed styles can all look correct while pixels disagree — verify menus with actual screenshots (headful xvfb renders the same as headless here, so headless captures of this bug are trustworthy).
