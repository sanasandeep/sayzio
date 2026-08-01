---
name: Zio Browser startup window-swap race
description: Deferred callbacks bound to a window can fire after the startup mode-pick destroys/recreates it — guard with isDestroyed().
---

The Zio Browser startup mode pick can destroy the first main window and create a replacement while the old window's queued callbacks (notably `ready-to-show` session restore) are still pending. Running restore against the destroyed window crashes main with `TypeError: Object has been destroyed` at `this.win.getContentSize()` in `layoutActiveTab` — an intermittent e2e killer (harness hangs until watchdog; run 1's clean-close snapshot never persists).

**Why:** `win.once('ready-to-show', ...)` callbacks stay queued after `win.destroy()`; addChildView/removeChildView are try/catch-wrapped so the crash only surfaces at the first unguarded window API call.

**How to apply:** guard deferred window work with `win.isDestroyed()` — bail out of the ready-to-show restore and at the top of `layoutActiveTab`. When an e2e "Object has been destroyed" stack points into layout code, suspect the WINDOW, not a view: map the dist line number to source (`awk NR==` on dist) before hunting for view destroyers. The replacement window runs its own restore, so bailing is safe.
