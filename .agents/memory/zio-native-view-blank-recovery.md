---
name: Zio Browser blank-pane recovery
description: Why a native pane can go permanently blank below a working toolbar, and the recovery pattern
---

- A blank content area under a healthy toolbar is a NATIVE WebContentsView problem, never React: chrome DOM and native panes are separate processes. Two causes match the symptom: the pane's renderer process died (no recovery existed before v0.3.13), or the chrome-overlay suppression left views detached.
- **Recovery pattern (v0.3.13):** every native view wires `render-process-gone` → reload + relayout, capped at 3 reloads/minute; the window `focus` event re-runs `layoutActiveTab()` as an idempotent failsafe (it no-ops while an overlay legitimately holds views detached).
- **Overlay rule:** any recovery/relayout path must respect overlay suppression — the dashboard-view recovery only re-applies the mode when `overlayCount === 0`, or a crash during an open menu would reattach the view over the menu and swallow its clicks.
- **How to verify visually:** Playwright `page.screenshot()` on the chrome window NEVER shows native child views — it lies blank. Use `wc.capturePage()` for a single view's paint, and `xwd -root | magick xwd:- out.png` under xvfb for the true composited window.
- **Debug harness:** boot dist build under xvfb with Playwright `_electron`, seed signed-in state via tests/e2e-sync-plan-gate/seed-zio-db.cjs (node-ABI better-sqlite3 for seeding, Electron-ABI swap for the app run); a real prod Sanctum token can be minted/removed directly in prod RDS `personal_access_tokens` (token column = sha256 of the `id|plain` right half).
- TabManager unit tests pass a minimal window stub — guard any new `win.on(...)` in the constructor with `typeof win.on === 'function'`.
