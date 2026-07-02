---
name: Standalone dialer sync
description: sayzio-dialer-standalone is a manifest-tracked transplant of the main mobile app; how to keep it in sync.
---

`sayzio-dialer-standalone/` is a deliberate verbatim-copy (no shared package — it must stay liftable out of the monorepo) of the dialer surface of `artifacts/1inme-mobile/`.

**The main app has since REMOVED its Dialer/Contacts/Caller ID surfaces entirely** (screens + `lib/api/dialer.ts`/`contacts.ts`); the standalone is now their sole home and those files are tracked as `standaloneOnly` in the manifest. What still syncs is the shared foundation: auth screens, components, contexts, hooks, shared `lib/` modules.

**Rule:** any change to those shared main-app files must be re-applied to the standalone copy, then re-baselined. Post-auth redirects in the standalone target `/(tabs)/dialer` (its tab group has no `index`), so `(auth)/_layout.tsx` + `(auth)/verify.tsx` are `adapted`, not `identical`.

**How to apply:**
- `pnpm --filter @workspace/scripts run check:dialer-sync` detects drift via `sayzio-dialer-standalone/sync-manifest.json` (relations: `identical` = must be byte-identical, copy over; `adapted` = intentional diff, source-hash-tracked, diff & hand-apply; `standaloneOnly`).
- After syncing: `check:dialer-sync:accept` re-baselines the recorded source hashes (identical entries are verified, never auto-accepted).
- Procedure + restructured-file mapping (`app/dialer.tsx`→`app/(tabs)/dialer.tsx` etc.) documented in `sayzio-dialer-standalone/SYNC.md`.

**Why:** drift already happened once (main app grew Google-sync throttle/cooldown the standalone lacked); a hash-baseline manifest catches it mechanically. Not a validation gate on purpose — it would fail unrelated tasks touching shared mobile files; run it on demand when mobile dialer surfaces change.
