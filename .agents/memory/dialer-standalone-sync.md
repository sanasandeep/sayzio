---
name: Standalone dialer sync
description: sayzio-dialer-standalone is a manifest-tracked transplant of the main mobile app; how to keep it in sync.
---

`sayzio-dialer-standalone/` is a deliberate verbatim-copy (no shared package — it must stay liftable out of the monorepo) of the dialer surface of `artifacts/1inme-mobile/`.

**Rule:** any change to the main app's dialer / contacts / caller-id / search screens or their `lib/api` clients must be re-applied to the standalone copy, then re-baselined.

**How to apply:**
- `pnpm --filter @workspace/scripts run check:dialer-sync` detects drift via `sayzio-dialer-standalone/sync-manifest.json` (relations: `identical` = must be byte-identical, copy over; `adapted` = intentional diff, source-hash-tracked, diff & hand-apply; `standaloneOnly`).
- After syncing: `check:dialer-sync:accept` re-baselines the recorded source hashes (identical entries are verified, never auto-accepted).
- Procedure + restructured-file mapping (`app/dialer.tsx`→`app/(tabs)/dialer.tsx` etc.) documented in `sayzio-dialer-standalone/SYNC.md`.

**Why:** drift already happened once (main app grew Google-sync throttle/cooldown the standalone lacked); a hash-baseline manifest catches it mechanically. Not a validation gate on purpose — it would fail unrelated tasks touching shared mobile files; run it on demand when mobile dialer surfaces change.
