---
name: Onboarding intro slides dual-copy lockstep
description: The mobile intro carousel copy lives in two places that must stay identical
---

The onboarding intro carousel ships the SAME slides from two sources, and which
one a user sees depends on seeding state:

- `artifacts/1inme-mobile/app/onboarding.tsx` → `FALLBACK_SLIDES` — bundled copy
  shown on fresh install / offline / before the admin-seeding migration runs.
- `artifacts/1inme/database/seeders/OnboardingSlidesSeeder.php` → `$slides` —
  canonical seeded copy shown once the admin has seeded.

**Rule:** slug, category, title, body AND sort_order must match per slug across
both. Edit both in lockstep.

**Why:** if they drift, the same user sees different intro wording depending on
whether seeding has run yet (historically "single biolink" vs "single Link in
Bio", "menu, hours" vs "menu, opening hours").

**How to apply:** the guard `artifacts/1inme-mobile/scripts/test-onboarding-slides-sync.mjs`
(package script `test:onboarding-slides-sync`, in the mobile `test:unit` chain)
parses both real sources and fails on any per-slide drift including body copy.
