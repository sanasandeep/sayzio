---
name: Expo Router typed-route regeneration
description: Why router.push("/new-screen") fails typecheck right after adding a screen, and how to fix it.
---

In `artifacts/1inme-mobile`, `router.push("/some-route")` is typed against the
generated `.expo/types/router.d.ts`. When you add a brand-new screen file under
`app/`, typecheck fails with "Argument of type '"/your-route"' is not assignable"
until those types are regenerated.

**Why:** the typed-routes file is produced by the Expo dev server (the expo-router
plugin), not by `tsc`. A fresh screen file won't appear in the union of valid
hrefs until the dev server rewrites that `.d.ts`.

**How to apply:** after adding a screen, `restart_workflow "artifacts/1inme-mobile: expo"`
and wait a few seconds; the dev server rewrites `.expo/types/router.d.ts` to
include the new route, then `pnpm --filter @workspace/1inme-mobile run typecheck`
passes. Don't reach for `as any`/`as Href` casts — regenerating is the clean fix.
