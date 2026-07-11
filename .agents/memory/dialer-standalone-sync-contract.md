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

**Gotcha — the standalone has NO `react-native-reanimated`.** It deliberately
avoids that dependency (its InfoPage is the plain static version for this exact
reason). So a new main-app shared component built on reanimated (AnimatedBlob
decorative background, ScrollReveal, etc.) CANNOT be `cp`'d into the standalone —
`dialer-typecheck` fails with `Cannot find module 'react-native-reanimated'`.
When a synced `adapted` file (e.g. `app/(auth)/index.tsx`) starts pulling in a
reanimated component, the correct sync is to OMIT that decorative layer from the
standalone copy, extend the manifest entry's `note` to document the omission,
and re-baseline the entry's `sourceSha256` to the current main-app source (do
NOT run the global `:accept`, which would also mask unrelated pre-existing drift
in about.tsx/InfoPage.tsx/siteContent.ts/verify.tsx).

**Scoping — only fix YOUR drift.** `check:dialer-sync` surfaces cascading
pre-existing drift from earlier tasks (verify.tsx, about.tsx, InfoPage.tsx ~360
diff lines, siteContent.ts) that predates your change and is out of scope to
port. Resolve only the entry your commit touched; leave the rest and (if the
gate stays red purely on pre-existing drift) skip that validation with a reason.
