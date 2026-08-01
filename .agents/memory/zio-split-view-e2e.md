---
name: Zio split-view Electron e2e
description: Gotchas for the Website+Website split-view harness and pane-dim CSS
---
- Validation `e2e-zio-split-view` wraps the split-view harness; the wrapper owns the better-sqlite3 Electron-ABI swap and restores the node ABI on exit.
- Pane focus is timing-fragile: evaluating JS inside a pane OR loadURL on the view that holds window focus re-fires its webContents 'focus' listener and flips focusedPane. Never assert "default focus"; pin via tabs.focusPane in a poll loop until the 'Address bar · Left pane' tag appears.
- To test dim re-application on dom-ready, navigate the UNFOCUSED second pane via tabs.navigatePane IPC, then re-pin primary in a loop (the load can steal focus after a one-shot re-pin).
- Dim detection: pane windows appear in app.windows(); dim = getComputedStyle(html,'::before') position:fixed + rgba(0,0,0,0.25); re-resolve the window handle on every poll (handles go stale).
- Sibling harness run-modes.cjs tours every OTHER TabModeSwitcher mode (entry/exit + omnibox routing); run-validation.sh runs both. The Zio chat input is a `textarea` (placeholder "Ask about this page…"), NOT an `<input>` — selector mismatch times out silently. Only browser+browser gets focus frames/dim; divider renders for any two-native/right-files split, never for +zio.
- Session restore of a split: rebuilding via setTabMode+navigatePane makes the second pane's initial load/attach steal window focus, flipping focusedPane to 'second' — so the toolbar shows the second-pane URL after restart. Snap focus back to primary on the second pane's once('did-stop-loading') (focusedPane isn't persisted; primary is the expected default).
- **Why** refreshPaneDim uses UNIQUE pending sentinels: a shared 'pending' let overlapping insertCSS resolutions adopt each other's slot — one stored a key for a torn-down document while the other removed the live overlay, leaving a pane permanently undimmed.
- Close-while-detached leak: webContents.close() is a polite window.close(); a detached companion view whose document never committed (blank second pane) or is mid-load (dashboard pane) ignores it and leaks an orphaned window. closeTab uses close() + a 250ms force-destroy fallback. run-modes.cjs asserts app.windows() returns to baseline after closing a detached browser+browser and dashboard+browser tab.

## Window-count baseline race in run-modes teardown
The close-teardown sections assert `app.windows()` returns to a pre-captured
baseline. The earlier mode tour lazily creates the tab's detached
dashboardView (kept alive by design); its remote sayzio.app page target can
register with Playwright MINUTES late, so a baseline captured too early makes
every `windowCount() === baseline` check fail by exactly one, with the extra
window at `sayzio.app/user/login`. Diagnose by the leaked URL: if it isn't a
pane of the closed tab (hasX/hasY false), it's the baseline race, not an app
leak. Fix in-harness: settle-wait (window URL set unchanged ~5s, 60s cap)
before snapshotting the baseline.
