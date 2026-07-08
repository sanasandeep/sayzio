---
name: Mobile inline block editing shares the route editor
description: The mobile blocks list expands BlockSettingsEditor inline; screen and inline modes must stay in lockstep.
---

The mobile biolink editor's block settings are ONE component (`BlockSettingsEditor`, exported from the `[blockId]` route file) rendered two ways: the full-screen route (default export wrapper) and inline, expanded beneath a row in the blocks list.

**Why:** mirrors the web editor's inline/expand pattern; a fork of the editor would drift between surfaces.

**How to apply:**
- Never nest a ScrollView in inline mode — the blocks list already scrolls; inline renders a plain View.
- Save must call `onDone` (collapse) in inline mode, `router.back()` only on the screen path.
- One block open at a time (`expandedId`); creating a block auto-expands it, deleting the expanded block must collapse it.
- Card children ride the same flat `order.map` row, so inline behaviour covers them for free.
- Wiring locked by `scripts/test-inline-block-edit.mjs` (in `test:unit`).
