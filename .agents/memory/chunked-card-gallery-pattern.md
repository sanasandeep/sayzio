---
name: Chunked card-gallery pattern (template picker)
description: How huge Blade card grids stay under 1MB — first-chunk SSR + Alpine chunk streaming; gotchas found while building it.
---

Pattern (reference: user/links/templates picker):
- Server renders only the first PICKER_CHUNK cards; a JSON chunk endpoint returns `{html, next_offset, total}` and Alpine `loadRest()` insertAdjacentHTML-appends until `next_offset` is null, then sets `data-all-loaded` on the grid (e2e waits on that attribute).
- Search/category chips keep filtering the FULL library via a light column-only index passed alongside (no snapshot/preview columns).
- Cards injected inside the x-data tree are auto-initialized by Alpine — no manual Alpine.initTree needed.

**Why:** rendering ~400 full cards inline was ~5.3MB HTML and a 20-30s render; per-card DB lookups (plan-rank pluck per card) multiplied over distant RDS.

**How to apply:** memoize any per-card lookup (e.g. plan ranks) once per request; measure with authenticated curl — demo-login POST logs into the DEMO_LOGIN_EMAIL account (the Gmail demo-login account (see demo-login config)), NOT the legacy 1inme demo address (see demo-login config), so seed fixtures for that user or you get 403 from workspace.can middleware. Baseline first: in dev over distant RDS even /user/links takes ~40s warm, so compare against a baseline page before chasing "slow" numbers.
