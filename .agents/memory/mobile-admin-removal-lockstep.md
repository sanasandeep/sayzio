---
name: Mobile admin-removal lockstep surfaces
description: What breaks (beyond typecheck) when you strip admin from the 1inme-mobile Expo app
---

Removing admin from `artifacts/1inme-mobile` is NOT just deleting `app/admin/*` + `lib/api/admin.ts` and friends. Typecheck stays green while several **runtime test + validation** surfaces still reference the deleted admin behavior and only fail under the validation workflows:

- `scripts/test-push-action.mjs` asserts push routing; the `expected_columns_missing` type used to deep-link to `/admin` — after removal it falls through to `/notifications`, so the assertion must change too. The live mapping lives in `lib/push.ts` `decidePushAction`.
- `app/notifications.tsx` `nativeTarget()` had a `/admin/cron-jobs` → `/admin/scheduled-jobs` remap that must be deleted (no native admin screen to route to).
- `scripts/test-auth-flow.mjs` pins the demo-login wiring; collapsing the two demo buttons to one no-arg `demoLogin()` breaks its regex assertion.
- Orphaned admin test scripts must be deleted AND removed from the `test:unit` chain in `package.json`: `test-scheduled-jobs-client.mjs` (reads deleted `lib/api/scheduledJobs.ts`), `test-pairings-admin-e2e.mjs` (drives the deleted `app/admin/link-type-pairings.tsx`). `test:unit` is a long `&&`-chain — miss one ref and the whole suite dies mid-run.
- The `e2e-mobile-pairings` **validation workflow** tests the deleted admin Perfect Pairings screen; retire it via `clearValidationCommand({name:"e2e-mobile-pairings"})`.
- `test-stats-*` scripts are USER-facing stats, not admin — leave them.

**Why:** these live in `.mjs` source-extraction harnesses + registered validation workflows, invisible to `tsc`. The MarkTaskCompleteWorkflow runs the validation workflows, so they must all be reconciled or task-complete fails.

**How to apply:** after deleting admin mobile code, grep the whole mobile app (incl. `scripts/` and `docs/`) for `api/admin`, `app/admin/`, `getAdminContext`, `ImpersonationBanner`, `/admin`, `mail-settings`, `schema-health`, `cron-jobs`, `scheduledJobs`, and reconcile every hit — including package.json scripts and `.replit` validation workflows. Backend Laravel API + its feature tests (e.g. MobileLinkTypePairingsApiTest) stay untouched.
