---
name: Zio Browser private-window IPC guard
description: Every durable-state IPC handler in zio-browser main must guard private windows
---

Rule: any ipcMain.handle that reads or writes durable on-disk state (bookmarks, history, passwords, reading list, prefs mutations, etc.) must start with `if (senderIsPrivate(event)) return <safe default>` (false / [] / null no-op).

**Why:** private windows must never touch persisted profile data; wiring a new UI surface to an existing handler set (e.g. the bookmark star) can silently reopen the boundary — code review flagged exactly this when bookmarks:add/remove/is-bookmarked/all/search lacked guards.

**How to apply:** when exposing or wiring any preload API in the renderer, check the matching main handlers for the guard; also hide/disable the UI affordance in private windows (`isPrivate` prop in ChromeBar) so the safe defaults aren't confusing.
