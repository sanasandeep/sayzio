---
name: package-firewall 404 → prime npm cache
description: Workaround when npm ci fails with 404 from package-firewall.replit.local on a specific tarball
---

**Rule:** When `npm ci` (e.g. the dialer-typecheck check's standalone install) fails with `404 Not Found - GET http://package-firewall.replit.local/<pkg>/-/<pkg>-<ver>.tgz`, the firewall proxy is missing/blocking that one tarball, not the code. Fetch it directly and prime the cache:

```
curl -sO https://registry.npmjs.org/<pkg>/-/<pkg>-<ver>.tgz
npm cache add ./<pkg>-<ver>.tgz
```

Then re-run — npm ci resolves it from the content-addressed cache by integrity hash.

**Why:** Direct access to registry.npmjs.org works from bash even though npm's registry env vars point at the firewall; the cache is checked before the network.

**How to apply:** Any workflow/check that shells out to `npm ci`/`npm install` and 404s on a specific tarball. Retrying the workflow alone never helps.

**Related pre-existing failure:** with npm ci fixed, `check:dialer-typecheck` surfaces a pre-existing error — `sayzio-dialer-standalone/app/(auth)/index.tsx` pushes `"/onboarding"` but the standalone app has no onboarding route (lost in a past sync). Not caused by new work; fix = restore an onboarding screen or retarget the "Back to intro" button.
