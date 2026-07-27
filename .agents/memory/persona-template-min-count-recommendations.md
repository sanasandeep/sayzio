---
name: Persona template top-up counts recommendations, not slugs
description: Why some personas end up with fewer than MIN_PER_PERSONA persona-* rows after seeding — starter templates' recommended_personas count toward the minimum.
---

The persona page-template seeder tops each persona up to `MIN_PER_PERSONA`, but it counts **recommendations across ALL active templates** (`recommended_personas` arrays), not `persona-<slug>-*` slug rows.

**Why:** Starter templates also carry `recommended_personas`. A persona recommended by a starter template already has 1 credit, so the seeder stops one persona blueprint short (e.g. 9 `persona-chef-*` rows + `starter-restaurant-menu` recommending chef = 10). This is by design, not data loss.

**How to apply:** When auditing seeded template counts, compare against recommendation totals, not slug-prefix counts. Also: `templates:refresh-persona-seed` over the distant RDS takes >2 min — run it via a registered validation workflow (`setValidationCommand` then `restart_workflow`), never a plain bash call (killed at 120s) or nohup (reaped when the bash call ends).
