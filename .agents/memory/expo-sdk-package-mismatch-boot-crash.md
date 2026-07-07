---
name: Expo SDK package-version mismatch crashes whole app at boot
description: A single expo-* package from the wrong SDK line crashes the entire Expo web app at boot; e2e symptom is "login screen never appears".
---

The rule: every `expo-*` package must come from the SDK line matching the installed `expo` package. The canonical versions are in `node_modules/expo/bundledNativeModules.json`; pin to those (e.g. SDK 54 → `expo-camera ~17.0.10`, NOT `^57.x` which is the SDK 55 unified-versioning line).

**Why:** expo-router eagerly evaluates every route module at boot. One screen importing a mismatched package (e.g. `useCameraPermissions` from expo-camera 57 on expo 54 → `createPermissionHook is not a function`) throws at module scope and takes down the WHOLE app — Metro serves fine, the bundle loads, but the app renders only the redbox error page.

**How to apply:** when mobile e2e gates fail with "app mounted" but the login-screen text never appears (while Metro/bundle warm succeeds), don't assume timeout flake — drive a headless page at the Expo server and dump `document.body.innerText` + console errors; a bundler/redbox error page names the offending file. Also note: several sibling packages may carry 55.x versions without crashing (only eagerly-imported ones blow up), so fix minimally — pin only what crashes unless asked otherwise.
