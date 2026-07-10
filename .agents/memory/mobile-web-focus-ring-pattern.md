---
name: Mobile web keyboard focus ring pattern
description: How the on-brand :focus-visible keyboard focus ring is applied to React Native Web controls, and which surfaces carry it in lockstep.
---

# Mobile web keyboard focus ring

React Native Web renders Pressables as `<div>`s with NO default focus outline, so
sighted keyboard users can't see which control has focus. The fix is an on-brand
`:focus-visible` ring applied web-only. The treatment now lives in ONE shared module,
`artifacts/1inme-mobile/lib/webFocusRing.ts` — do NOT re-inline it per surface:

- `useWebFocusRing(config, ringColor)` — call once per surface. Installs the one-time
  stylesheet (`[data-<marker>]:focus-visible { outline: 2px solid var(--<var>, #fallback) }`),
  keeps the CSS var tracking `colors.primary`, and returns the marker props.
- `focusRingMarkerProps(config)` — `{ dataSet: { <key>: "true" } }` on web, `null` on
  native; spread onto every focusable Pressable.
- Presets `TAB_BAR_FOCUS_RING` (marker `data-tabbar-tab`) and `DRAWER_FOCUS_RING`
  (marker `data-drawer-focusable`). Add a new `WebFocusRingConfig` preset for a new surface.

**Why:** the ring must be keyboard-only (`:focus-visible`, never on tap) and must not
touch native rendering, so the props are null on native and the styling is pure web CSS.
Duplicating it inline per surface let keyboard a11y drift silently.

**Drift guard:** `scripts/check-focus-ring-shared.mjs` (npm `test:focus-ring-guard`,
wired into `test:unit` → the `mobile-unit` workflow) fails if any file under
`components/` inlines a `:focus-visible` stylesheet / focus-ring `dataSet` marker /
`--*-focus-ring` var (comment-stripped) WITHOUT importing `webFocusRing`. Correct usage
(spreading helper props) produces none of those literals, so sanctioned surfaces pass.

**Shared helper (preferred for NEW surfaces, as of Jul 2026):**
`artifacts/1inme-mobile/hooks/useWebFocusRing.ts` generalizes the pattern with a single
marker `data-focus-ring`:
- `WEB_FOCUS_RING_PROPS` = `{ dataSet: { focusRing: "true" } }` on web / `null` on native
  — spread onto any focusable Pressable/TextInput (spreading `null` in JSX is valid).
- `useWebFocusRing()` injects the one-time `<style id="app-focus-visible-style">`
  (`[data-focus-ring]:focus-visible { outline: 2px solid var(--app-focus-ring,#3d6bff); outline-offset:2px }`
  + a `:focus{outline:none}` reset) and sets `--app-focus-ring` to `colors.primary`.
  Mounted ONCE in `app/_layout.tsx` `RootLayoutNav()` (inside ThemeProvider). No
  border-radius override — the outline follows each control's own radius.
- Because it lives on the shared primitives `components/Button.tsx` +
  `components/TextField.tsx`, MOST buttons/fields across auth + editors get the ring for
  free. Only RAW `<Pressable>`s need the explicit `{...WEB_FOCUS_RING_PROPS}` spread:
  `app/(auth)/index.tsx` (channel tabs, social buttons, info links),
  `components/SettingsForm.tsx` (choice segments), and the block editors
  `app/links/[id]/blocks/index.tsx` + `blocks/[blockId].tsx` (every Pressable).

**Legacy per-marker surfaces (own injected stylesheet + marker each):**
- `components/FloatingTabBar.tsx` (marker `tabbar-tab`) — original.
- `components/DrawerSidebar.tsx` (marker `drawer-focusable`) — ALL focusable drawer
  Pressables. Forgetting one silently drops its ring.

**e2e gates:** `test-tabbar-legibility-e2e.mjs`, `test-drawer-focus-ring-e2e.mjs`, and
`test-form-focus-ring-e2e.mjs` (auth form field + tab button, workflow
`e2e-mobile-form-focus-ring`) each assert keyboard focus paints a ring + pointer press
leaves none, in light + dark.

**Running the focus-ring e2e locally:** the throwaway Expo web server boot is too slow
on the constrained box (>7 min, harness SKIPs per its best-effort contract). To actually
verify, warm the expo workflow, `curl` `/` until 200, then run with
`APP_URL="https://$REPLIT_EXPO_DEV_DOMAIN/"` — explicit APP_URL makes it fail hard
instead of skip.
