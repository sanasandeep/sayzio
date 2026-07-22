---
name: Alpine reactivity misses hasOwnProperty/key-addition
description: Adding a NEW key to a reactive object via plain assignment is invisible to effects that only checked hasOwnProperty — reassign the whole object.
---

Alpine's bundled @vue/reactivity does not track `Object.prototype.hasOwnProperty.call(obj, key)` (it goes through the untracked getOwnPropertyDescriptor path). An effect whose only dependency on a key is a hasOwnProperty check will NOT re-run when that key is later ADDED by `obj[key] = ...`.

**Symptom:** model data mutates correctly (visible via `Alpine.$data`), but x-for / bound DOM stays stale — only on the FIRST mutation that creates the override key. Later mutations (splice/push on the now-existing array) react fine, so add/remove tests can pass while first-touch reorder silently doesn't render.

**Why:** the fallback branch (`hasOwnProperty ? overrides[key] : systemDefault`) never performed a tracked `get` on the missing key, so key-addition triggers nothing.

**How to apply:** when creating a brand-new key on reactive data, reassign the container: `this.obj = { ...this.obj, [key]: value }`. Seen in the admin Block Defaults list editor (`ensureListOverride`). Also: Playwright can't do native HTML5 drag via mouse — dispatch DragEvents with a shared `new DataTransfer()` in page.evaluate to exercise Alpine @dragstart/@dragover/@drop handlers.
