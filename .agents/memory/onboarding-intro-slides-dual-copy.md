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
GOTCHA: that guard's PHP parser keys off the literal marker `$slides = [` in the
seeder — keep that exact literal if you refactor the seeder, or the guard breaks.

**Third (live-DB) copy the static guard can't see:** the seeded rows are
editable in the admin UI, and the edited DB row (not the baked-in copy) is what
the app serves once seeded. So an admin edit can silently diverge from BOTH
baked-in copies. Detection has to be runtime, comparing the live row's wording
against the seeder defaults — the seeder is the single source of truth for both
seeding and drift detection. Surfaced to admins as a per-slide state
(default/customized/custom) + a warning banner on the admin slides page.
