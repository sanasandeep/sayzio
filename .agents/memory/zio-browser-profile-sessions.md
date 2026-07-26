---
name: Zio Browser profile partition sessions
description: Tabs run in per-profile partition sessions, not defaultSession — privacy hooks and data operations must cover all of them
---

Zio Browser tabs are created in `session.fromPartition('persist:zio-profile-<id>')`, never `session.defaultSession`.

**Why:** Anything wired only to `defaultSession` (webRequest hooks for DNT/3p-cookie/tracker blocking, `clearStorageData`, cookie reads/clears, cache size) silently misses real tab traffic and data. An architect review caught a whole settings feature set built against `defaultSession` that compiled and passed tests but did nothing for actual tabs.

**How to apply:**
- Data clear/count/forget IPC handlers must iterate all persistent sessions: helper `allDataSessions()` in `ipc-handlers.ts` = defaultSession + every profile partition from `listProfiles()`.
- webRequest hook installers use a `WeakSet<Session>` guard (`installPrivacyHooks`/`installTrackerHooks`) and are called at startup for the active profile, on `profiles:switch`, and on `profiles:warm-session`.
- New privileged IPC handlers must gate with `senderIsPrivate(event)` (private windows must not read/mutate durable state) — mirror existing handlers.
- Destructive host-suffix matching (forget-site) needs host validation (`isValidForgetHost`): reject single labels and bare public suffixes or `endsWith('.'+target)` wipes unrelated domains.
