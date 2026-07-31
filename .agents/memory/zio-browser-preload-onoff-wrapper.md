---
name: Zio Browser preload on/off wrapper map
description: contextBridge event API must keep a per-channel listener→wrapper map or off() never unsubscribes
---

The preload `on()` wraps every renderer listener to strip the IPC event arg. If `off()` calls `ipcRenderer.removeListener(channel, originalListener)`, references never match and cleanup silently no-ops — handlers stack on every effect re-run (duplicate event firing).

**Why:** caught by architect review after adding `settings:open`/`bookmarks:changed` listeners with cleanup in effects; typecheck and tests stay green.

**How to apply:** keep `Map<channel, Map<listener, wrapper>>` in the preload; `on()` registers the wrapper and records it, `off()` looks up and removes that exact wrapper. Any new `window.zio.on` usage inside a React effect relies on this.
