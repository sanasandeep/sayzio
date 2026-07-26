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

Shared `useChromeOverlay(active)` renderer hook implements the balance pattern once — use it, never hand-roll acquire/release. Docked Ask Zio panel: the browser-mode right-strip reserve is gated on `docked && visible`; renderer syncs visibility via IPC, and the docked default must agree in every lockstep place (ipc get handler, mode-store initial, WindowModeManager ctor default) or first-paint disagrees with stored state.

**Detach alone is not enough — suppress re-attach too.** Acquiring the overlay detaches views, but ANY later layout pass (window resize, tab events, panel-bounds IPC) re-attaches the active tab's native views and covers the open DOM panel, swallowing its clicks (broke Settings tabs entirely). TabManager holds an `overlaySuppressed` flag: the layout/attach path early-returns while an overlay hold is active; the manager clears it only when the ref count hits 0, right before re-applying the mode layout. Any new code path that attaches native views must respect this flag.
