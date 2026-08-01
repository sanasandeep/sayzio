---
name: Zio split-view Electron e2e
description: Gotchas for the Website+Website split-view harness and pane-dim CSS
---
- Validation `e2e-zio-split-view` wraps the split-view harness; the wrapper owns the better-sqlite3 Electron-ABI swap and restores the node ABI on exit.
- Pane focus is timing-fragile: evaluating JS inside a pane OR loadURL on the view that holds window focus re-fires its webContents 'focus' listener and flips focusedPane. Never assert "default focus"; pin via tabs.focusPane in a poll loop until the 'Address bar · Left pane' tag appears.
- To test dim re-application on dom-ready, navigate the UNFOCUSED second pane via tabs.navigatePane IPC, then re-pin primary in a loop (the load can steal focus after a one-shot re-pin).
- Dim detection: pane windows appear in app.windows(); dim = getComputedStyle(html,'::before') position:fixed + rgba(0,0,0,0.25); re-resolve the window handle on every poll (handles go stale).
- Sibling harness run-modes.cjs tours every OTHER TabModeSwitcher mode (entry/exit + omnibox routing); run-validation.sh runs both. The Zio chat input is a `textarea` (placeholder "Ask about this page…"), NOT an `<input>` — selector mismatch times out silently. Only browser+browser gets focus frames/dim; divider renders for any two-native/right-files split, never for +zio.
- **Why** refreshPaneDim uses UNIQUE pending sentinels: a shared 'pending' let overlapping insertCSS resolutions adopt each other's slot — one stored a key for a torn-down document while the other removed the live overlay, leaving a pane permanently undimmed.
