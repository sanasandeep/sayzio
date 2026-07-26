---
name: Zio Browser chrome-overlay for dropdowns
description: How renderer dropdown menus avoid being occluded by native WebContentsViews, and the ref-count balance rules
---

Native WebContentsViews (tab views AND the dashboard view) sit ABOVE the renderer DOM, so any chrome dropdown menu extending into their region gets its clicks swallowed. Hiding tabs alone is NOT enough in dashboard/split modes — the dashboard view must be detached too.

**Rule:** dropdowns use the main-process chrome-overlay API (`setChromeOverlay(open)`): open → detach all native views; close → re-apply current mode to restore. Main ref-counts holds and restores only at count 0.

**Balance rule (v0.1.20 lesson):** every acquire must be released EXACTLY once — no more, no less. Two failure modes caused "all buttons dead / dropdown buggy" bugs:
- Skipping the release because a mode pick "already restores views" via setMode: setMode reattaches views but does NOT decrement the count → permanent leak → every later menu close fails to reattach views.
- `setChromeOverlay(boolState)` in an effect body plus a conditional cleanup releases TWICE per close → steals the overlay from other open holders, reattaching views over their menus.

**How to apply:** use the wasOpen/held-ref pattern (acquire on true edge, release once on false edge, release on unmount if held). Releasing after a pick is safe: main clamps count at 0 and setMode is idempotent. Never raw `tabs.hideAll`.

**v0.1.21 additions:** shared `useChromeOverlay(active)` renderer hook implements the balance pattern once — use it, never hand-roll acquire/release. Docked Ask Zio panel: the browser-mode right-strip reserve is gated on `docked && visible`; renderer syncs visibility via `window:set-zio-panel-visible` IPC, and the docked default is TRUE in three lockstep places (ipc get handler, mode-store initial, WindowModeManager ctor default) — flip all or first-paint disagrees with stored state.
