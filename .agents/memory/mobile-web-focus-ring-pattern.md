---
name: Mobile web keyboard focus ring pattern
description: How the on-brand :focus-visible keyboard focus ring is applied to React Native Web controls, and which surfaces carry it in lockstep.
---

# Mobile web keyboard focus ring

React Native Web renders Pressables as `<div>`s with NO default focus outline, so
sighted keyboard users can't see which control has focus. The fix is an on-brand
`:focus-visible` ring applied web-only:

- A module-level `*_FOCUS_RING_PROPS` that is `{ dataSet: { <marker>: "true" } }` on
  web and `null` on native (spread onto every focusable Pressable).
- A one-time injected stylesheet: `[data-<marker>]:focus-visible { outline: 2px solid
  var(--<marker>-focus-ring, #<fallback>); ... }`.
- A CSS var (`--<marker>-focus-ring`) set to `colors.primary` from a useEffect so the
  ring tracks the active theme.

**Why:** the ring must be keyboard-only (`:focus-visible`, never on tap) and must not
touch native rendering, so the props are null on native and the styling is pure web CSS.

**How to apply / lockstep surfaces (as of Jul 2026):**
- `artifacts/1inme-mobile/components/FloatingTabBar.tsx` (marker `tabbar-tab`) — original.
- `artifacts/1inme-mobile/components/DrawerSidebar.tsx` (marker `drawer-focusable`) —
  spread onto ALL focusable drawer Pressables (nav items, close, sign out, workspace
  switcher + dropdown rows, theme toggles). Forgetting one silently drops its ring.
- Each surface has a sibling e2e (`test-tabbar-legibility-e2e.mjs`,
  `test-drawer-focus-ring-e2e.mjs`) asserting keyboard focus paints a ring + pointer
  press leaves none, in light + dark.

**Running the focus-ring e2e locally:** the throwaway Expo web server boot is too slow
on the constrained box (>7 min, harness SKIPs per its best-effort contract). To actually
verify, warm the expo workflow, `curl` `/` until 200, then run with
`APP_URL="https://$REPLIT_EXPO_DEV_DOMAIN/"` — explicit APP_URL makes it fail hard
instead of skip.
