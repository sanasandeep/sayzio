---
name: /info route list triplication (mobile)
description: The mobile /info route list is duplicated across three places that silently drift; which guard covers which pair.
---

# /info route list triplication (1inme-mobile)

The set of internal `/info/*` routes (About / Help / Privacy / Terms) is listed
independently in THREE places, and they have drifted before (a stale `/info/nfc`
lived in the footer + e2e fixture long after the screen was removed):

1. `app/(auth)/index.tsx` — the **login** screen footer links.
2. `app/onboarding.tsx` — the `INFO_LINKS` array (onboarding footer).
3. `scripts/test-info-pages-e2e.mjs` — the `PAGES` e2e fixture that drives each
   route in a real browser.

**Guards:**
- `test:login-footer-links` (test-login-footer-links.mjs) — ties the LOGIN
  footer links to real screen files + navigation targets.
- `test:onboarding-footer-links-sync` (test-onboarding-footer-links-sync.mjs) —
  ties `INFO_LINKS` internal routes to the `PAGES` e2e fixture, both directions.

**Gap:** nothing yet ties the login footer route set to the onboarding
`INFO_LINKS` set, so those two can still diverge from each other.

**Why:** reading source (not a hardcoded third copy) is the convention — each
guard parses the real shipped literals so it exercises exactly what ships.

**How to apply:** adding/removing an `/info` route means editing all three lists
in lockstep; external (`kind:"external"`, e.g. Website) links are intentionally
excluded from the e2e-fixture guard since the harness only drives in-app routes.
