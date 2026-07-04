---
name: Standalone repo cold npm install
description: sayzio-dialer-standalone has no committed lockfile/node_modules; first install in a fresh session needs care.
---

`sayzio-dialer-standalone/` (unregistered artifact, sole home for the dialer/contacts mobile UI — see dialer-standalone-sync.md) does not ship `node_modules` and may not have a lockfile committed. In a fresh session, `npx tsc --noEmit` will fail with `Cannot find module` errors that look like real regressions but are just missing deps.

Run `npm install` first. A single `npm install` invocation can partially fail with `ENOTEMPTY` on a nested package's build directory (e.g. `node_modules/@tanstack/react-query/build`) if a prior partial/interrupted install left a stale directory. Don't restart the whole install from scratch — `rm -rf` just that one offending subpath and re-run `npm install`; it completes normally on the retry.

**How to apply:** before typechecking or trusting any error output from this repo, confirm `node_modules` exists and `npm install` exited cleanly. Pre-existing errors about missing `@tanstack/react-query`, `expo-router`, or `expo-notifications` type declarations are environment gaps affecting the whole app, not something a small feature change introduced — verify by checking whether the same error appears on files you didn't touch.
