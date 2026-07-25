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
