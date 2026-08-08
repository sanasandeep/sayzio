---
name: Zio startup keychain block (white window)
description: macOS keychain prompts at boot can hold the whole chrome white; safeStorage must never run before first paint
---

- Any synchronous `safeStorage` read (auth-store retrieveToken via seedSayzioWebSession) placed before the chrome `loadURL` blocks the main process while macOS shows its keychain password prompt → fully white window (failsafe shows the frame at 6s but chrome never painted). Fixed in v0.3.15 by deferring the session seed to ready-to-show/failsafe (+400ms) via `seedWebSessionOnceVisible`.
- **Why:** keychain prompts are modal to the requesting process; ad-hoc-signed builds (no CSC_LINK) re-prompt after EVERY update because the signature changes, so "Always Allow" never sticks across versions.
- Users always see TWO prompts on macOS: Chromium's own "Zio Browser Safe Storage" cookie-encryption key access + our safeStorage token read. Both are inherent to unsigned builds; the only real fix is Developer ID signing (CSC_LINK path in electron-builder.config.cjs already supports it).
- **How to apply:** never call safeStorage/keychain (or any modal-capable sync API) in createWindow before `loadURL`; defer to after `win.show()`.
