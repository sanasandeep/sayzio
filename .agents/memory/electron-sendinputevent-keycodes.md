---
name: Electron sendInputEvent key codes
description: sendInputEvent needs accelerator-style keyCodes, not DOM KeyboardEvent.key names
---

Electron's `webContents.sendInputEvent` expects accelerator-style key codes: `Left`, `Right`, `Up`, `Down`, `Return`, `Backspace`, `Tab`. DOM `KeyboardEvent.key` names like `ArrowLeft` or `Enter` are silently ignored — the event fires but the page sees nothing.

**Why:** the Zio Browser virtual keyboard originally forwarded DOM key names straight into `keyCode`; arrows were dead until mapped.

**How to apply:** any key-injection helper must translate DOM names → accelerator codes before calling sendInputEvent; `Enter`/`Tab` also need a `char` event (`\u000d` / `\u0009`) between keyDown/keyUp to actually insert.
