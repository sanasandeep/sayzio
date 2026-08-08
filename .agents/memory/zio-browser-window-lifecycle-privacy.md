---
name: Zio Browser window lifecycle & privacy guards
description: Electron logout/window churn vs window-all-closed quit; private-window guard rule for persistent IPC handlers.
---

## Logout / replace-all-windows churn
Rule: when replacing all windows (logout, profile reset), create the NEW window BEFORE destroying old ones.
**Why:** `app.on('window-all-closed')` unconditionally quits on Windows/Linux; a zero-window moment during churn races into `app.quit()`.
**How to apply:** any handler that destroys every BrowserWindow and reopens one — snapshot `getAllWindows()` first, `createWindow()`, then destroy the snapshot.

## Private windows vs persistent state
Rule: EVERY IPC handler that reads or writes durable state (DB prefs, named sessions, history, extensions) must start with a `senderIsPrivate(event)` guard.
**Why:** private/incognito windows share the same ipcMain handlers; forgetting the guard silently persists private-mode data (found by review on the sessions:* handlers).
**How to apply:** copy the guard pattern used by the existing handlers in ipc-handlers.ts; list-type handlers return `[]`, mutators return `false`.

## Playwright Electron e2e window-handle staleness
- Playwright handles to Zio Browser windows can go stale after new windows open: `isClosed()` returns false yet clicks fail "Target page closed". Re-acquire via a liveness probe (attempt a real click, retry loop), don't trust isClosed().
- `window.close()` from a renderer tears down more than its own window — leave extra e2e windows open instead.
- To find a freshly opened window, snapshot `app.windows()` BEFORE the trigger and pass that array as the exclude list; old `page` object identities may not match the surviving windows.
- The omnibox treats `data:` input as a search query (no `//`), so `tabs.navigate(id, 'data:...')` lands on a search page — use a real https URL when a non-newtab URL is needed (e.g. Create's canShorten).
- Create button is auth-gated; seed `window.zio.auth.storeToken(...)` then open a NEW window via `window.zio.window.openNew()` (page.reload() breaks the tab registry). New windows show the mode picker — click the exact-match "Browser" card first.

## Startup restore prompt (Aug 2026)
`startup_mode` values are `ask` (DEFAULT since v0.3.14) / `continue` / `newtab`. The 'ask' prompt in ready-to-show and the crash-recovery prompt share module flag `startupRestorePromptShown` so a launch never prompts twice; release guard: push-to-main builds auto-publish on a detected version bump, so a manual release dispatch after a bump fails the "existing release" guard — that failure is benign.
