---
name: sayzio-dialer-standalone sync contract
description: Editing shared mobile files trips the dialer-standalone sync/typecheck gates; how to sync and what pre-existing drift lurks
---

# sayzio-dialer-standalone sync contract

`sayzio-dialer-standalone/` is a verbatim transplant of `artifacts/1inme-mobile/`
trimmed to the dialer surface. It is NOT a workspace package (own node_modules,
own npm). A manifest (`sayzio-dialer-standalone/sync-manifest.json`) maps every
transplanted file with a `relation`:
- `identical` — must stay byte-identical to the main-app source; sync = `cp`.
- `adapted` — intentionally differs (`note` says how); manifest records the
  main-app source sha256 at last sync.
- `standaloneOnly` — no main-app counterpart (e.g. the dialer/contacts screens
  the main app deleted).

**Rule:** editing ANY mobile file that the manifest mirrors (AuthContext,
lib/secure, verify.tsx, shared components, etc.) fails the `dialer-sync`,
`dialer-typecheck`, and `scripts-tests` validation gates until you re-sync.

**How to apply (the SYNC.md procedure):**
1. `pnpm --filter @workspace/scripts run check:dialer-sync` lists drift.
2. For `identical` drift: `cp` the main-app file over.
3. For `adapted` drift: diff, apply the relevant change preserving the
   intentional difference.
4. New mobile components used by synced files need their own copy + a manifest
   entry (relation `identical`).
5. `cd sayzio-dialer-standalone && npx tsc --noEmit` (or the `dialer-typecheck`
   script).
6. Re-baseline: `pnpm --filter @workspace/scripts run check:dialer-sync:accept`.

**Gotcha — the standalone lags behind prior admin-removal.** When you finally
`cp` a current AuthContext over, pre-existing un-synced drift surfaces as NEW
typecheck errors, because the old standalone was internally consistent with the
old API. Seen: standalone `contexts/AuthContext.tsx` importing removed
`getImpersonator`/`setImpersonator`; `app/(auth)/index.tsx` calling the old
`demoLogin(role)` (now 0-arg) behind a "Demo as admin" button. Fixing these to
match the main app (admin removed) is part of a clean sync. Related:
[mobile-admin-removal-lockstep.md](mobile-admin-removal-lockstep.md).
