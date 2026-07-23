---
name: applyRuntimeConfig caches admin values for the process lifetime
description: Clearing an admin-stored credential in the same PHP process that booted with it set does not un-configure the feature; config('services.*') keeps the boot-time copy.
---

The rule: `PlatformServiceSettings::applyRuntimeConfig()` runs at boot and copies admin-stored values into `config('services.*')`. If you later `set...(null)` inside that same process (tinker, queue worker, long test), the `config()` fallback in the getter still returns the boot-time value, so `...Configured()` stays true.

**Why:** A browser spec's "feature hidden when unconfigured" check kept skipping — the tinker process that cleared the Google CSE admin keys had been booted while they were set, so `googleCseConfigured()` still read the stale runtime config and looked like an env fallback existed.

**How to apply:** After nulling admin values inside a live process, also clear the runtime copy (`config(['services.x.key' => null, ...])`) before asserting configured-state — or check from a fresh process. Web requests are unaffected (each request re-boots).
