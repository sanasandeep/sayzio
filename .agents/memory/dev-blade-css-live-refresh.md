---
name: 1inme dev live CSS refresh on blade edits
description: How the 1inme Laravel dev server rebuilds Tailwind CSS when blade files change mid-session, and why the compiled-view clear must live in the run command
---

The 1inme dev service does NOT run a Vite dev server / HMR. `php artisan serve`
only serves the pre-built `public/build` assets. To make Blade edits refresh the
CSS live mid-session, `[services.development].run` (artifact.toml) runs
`vite build --watch` (npm script `build:watch`) concurrently with `artisan serve`
via the `concurrently` dev dependency (`--kill-others` ties their lifecycles).
Tailwind v4 registers every `@source` glob (incl. `../**/*.blade.php`) as a
watched file, so saving a blade triggers an incremental rebuild and new utilities
appear automatically.

**Why the compiled-blade-cache clear is in the run command, not a vite plugin:**
`resources/css/app.css` has `@source '../../storage/framework/views/*.php'`, so
Tailwind scans Laravel's compiled-blade cache. Two hard facts (both verified
empirically) make an in-process vite plugin useless for this:
1. Tailwind v4's vite plugin enumerates/scans `@source` files at config-resolve
   time — BEFORE the `buildStart` hook runs — so clearing in `buildStart` is too
   late (proved: buildStart deleted the file, token still emitted).
2. Tailwind's watch-mode candidate set is ADDITIVE — a token scanned once
   survives every later rebuild until the watcher process restarts (proved:
   removing a class from raw blade left it in the CSS).
So the only reliable fix is `rm -f storage/framework/views/*.php` in the run
command BEFORE the vite process launches. Laravel lazily recompiles views on the
next request, and from then on the cache only ever holds current-source tokens,
so it can never feed STALE tokens back. (Removed/renamed classes still linger as
dead CSS until a watcher restart — inherent additive limitation, not the compiled
cache; the rendered page is still correct because it uses the new class.)

**Gotchas:**
- `vite build --watch` empties `public/build` only on its FIRST build; incremental
  rebuilds leave orphaned hashed `app-*.css` files. Harmless (gitignored, @vite
  reads the manifest), cleaned on restart. When grep-checking the served CSS,
  read the manifest-referenced file, not every `*.css`.
- `php artisan serve` cold boot over the distant RDS is slow; DevStartupProbe
  covers the "/" readiness probe (see artifact.toml notes).
