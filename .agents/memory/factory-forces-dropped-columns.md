---
name: User factory forces dropped/renamed columns + default drift
description: When replacing hand-rolled User::create() test helpers with User::factory(), the unguarded factory re-surfaces two silent behavioral gaps mass-assignment used to hide.
---

Replacing `makeUser()` helpers that did `User::create([...])` with `User::factory()->create([...])` re-exposes two things `User::create()` silently hid. Both bite at scale (100s of helpers) and look like "the factory is broken."

## 1. Factory forces attributes that are NOT real columns
`User::create()` drops any key not in `$fillable`. The `role` key was passed by ~37 helpers as cosmetic (`role => 'user'`), but `role` is not `$fillable` AND the `users.role` column was dropped (roles moved to the `user_roles` pivot). `create()` dropped it silently; the factory forces every passed attribute into the INSERT → `column "role" does not exist` (54+ errors).

**Fix:** strip the dead attribute in the factory's `configure()->afterMaking()` before persist (mirrors the old silent-drop). Do NOT strip by `$fillable` (factories legitimately set non-fillable real columns like `created_at`); strip only the known dead key(s) so genuine typos still error loudly.

**How to find them all:** static-scan every `User::factory(...)` call site for the keys it forwards and diff against the live `users` column list. Any forwarded key that isn't a real column is a regression. `role` was the ONLY one suite-wide.

**Now guarded automatically:** `scripts/check-factory-columns.php` (composer `check:factory-columns`, validation `factory-columns`) token-scans every `User::factory(...)` chain's inline-array keys and fails if any key is neither a real `users` column (derived DB-free via `SchemaManifest::build()`, replays migrations) nor an intentionally-stripped key. The strip list is the single source of truth `UserDatabaseFactory::DROPPED_LEGACY_ATTRIBUTES` (currently `['role']`), consumed by BOTH the factory's `afterMaking` strip and the guard's allowlist. **When you drop/rename another `users` column that test call sites still pass, add it to that const** (or the guard fails). Variable/spread args (`->create($attrs)`) are reported as "unresolved" but never fail — their keys live at the caller, out of a per-call-site scan's scope.

## 2. Default-value drift (nullable-no-DB-default columns)
`users.email_verified_at` and `onboarded_at` are nullable with NO DB default (→ null). Most old helpers never set them, so old test users were **unverified + not-onboarded**. A factory that defaults them to `now()` makes new users **verified + onboarded** — a real behavioral drift toward the "happy" state.

This is usually harmless (onboarding-gate/verification tests explicitly `forceFill([... => null])` or set the state), but verify: static-sweep tests that assert on the null/unverified/not-onboarded state and confirm they either override the default or still pass. Provide `unverified()` / `notOnboarded()` factory states for the ones that need it.

## Proving remaining failures are pre-existing, not refactor-caused
`git show HEAD~1:<path>` is read-only (allowed under the no-branch-change rule). Reconstruct the pre-refactor test file to a NEW temp path with the class renamed (`<Name>H1DIFF`, PSR-4 maps `Tests\Feature\X`→`tests/Feature/X.php`, so filename must match the renamed class), run it, delete it — no mutation of tracked files. If it fails identically, the failure predates the refactor.

**Why:** a test-only refactor can only regress via (1) forced columns or (2) default drift — there is no third class. Cover (1) with the static column scan and (2) with the assertion sweep; everything else is env (missing composer deps, AI engine off, schema drift) and reproduces on HEAD~1.
