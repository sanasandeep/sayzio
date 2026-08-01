---
name: Zio Browser ad blocker (ghostery engine)
description: Integration pattern and gotchas for the EasyList/EasyPrivacy ad blocker in Zio Browser
---

- The ad blocker (@ghostery/adblocker) owns MATCHING only; the single per-session `onBeforeRequest` dispatcher lives in tracker-blocker and consults it. Never install a second onBeforeRequest listener — Electron replaces the previous one silently.
- **Why:** two listeners on one session means the last-registered wins and the other's blocking silently stops.
- Serialized-engine cache in userData must be keyed by the library's `ENGINE_VERSION` (binary format changes across versions); parse ~750ms vs deserialize ~20ms, so cache matters.
- Private windows get NO webRequest hooks by default — `createPrivateWindow` must call `installTrackerHooks(privateSession)` explicitly; per-site override resolvers must return null for non-persistent sessions so private windows follow global toggles only.
- Never block `mainFrame` requests via filter lists — cancelling a top-level navigation blanks the page instead of removing an ad.
- Cosmetic (element-hiding) CSS is injected on `dom-ready` via a global `app.on('web-contents-created')` hook; http(s) documents only, best-effort.
