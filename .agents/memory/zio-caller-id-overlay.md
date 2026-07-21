---
name: Zio Dialer caller-ID overlay (Android)
description: Truecaller-style incoming-call alert — native-only pipeline, why JS can't be involved, permission/role gotchas.
---
The incoming-call alert must be fully native (CallScreeningService + WindowManager overlay + SharedPreferences directory) because the JS runtime is usually dead when a call rings. JS only manages permissions/toggle and pushes a `[{n,name,photo,org}]` directory snapshot after each contact sync.

**Why:** CallScreeningService binds without launching the app; any RN bridge dependency would silently no-op on cold rings.

**How to apply:** any new at-ring feature (spam badge, call logging) goes into the Kotlin side reading CallerIdStore; JS enriches the directory snapshot, never handles the ring. Two grants are needed and are checked independently: SYSTEM_ALERT_WINDOW (settings page, re-check on AppState foreground) and RoleManager.ROLE_CALL_SCREENING (startActivityForResult + OnActivityResult in the expo module — resolve the promise by re-checking isRoleHeld, the resultCode is unreliable). Always respond to onScreenCall with an empty allow response FIRST, before any lookup work.
