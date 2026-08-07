---
name: Zio chrome theme vs website color scheme
description: nativeTheme.themeSource is global to all renderers; chrome theming must never touch it
---

Electron's `nativeTheme.themeSource` changes the `prefers-color-scheme` signal EVERY renderer sees — websites AND the chrome UI. So:

- The chrome "Appearance" setting resolves in the renderer (light-mode class toggle) and must never assign `themeSource`; only the "Website appearance" setting owns it (see `src/main/theme.ts`).
- **Why:** setting themeSource for chrome dark mode silently forced dark rendering on Gmail etc., diverging from Safari on a light-mode Mac.
- **How to apply:** any new theming feature that needs the REAL OS scheme while an override is active must use the flip-probe in `theme.ts` (`systemPrefersDark()`), which temporarily sets themeSource='system' with a re-entrancy guard — assigning themeSource fires `updated` events, so the bridge that broadcasts `theme:system-changed` must ignore probe-triggered events or it loops.
- Chrome "System" mode can't use the renderer's own matchMedia (it sees the overridden signal); it asks main via `theme:get-system` and listens on `theme:system-changed`.
