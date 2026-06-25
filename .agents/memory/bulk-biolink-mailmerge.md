---
name: Bulk biolink mail-merge
description: Scope + gating decisions for bulk-create biolink pages from a sheet
---

# Bulk biolink mail-merge (sheet → many pages)

Deterministic `{{token}}` substitution (NO AI). Intentionally mirrors the bulk
short-link wizard's shape: Step1 input → Step2 preview-grid (edit/skip per row) →
Step3 store.

**Scope guardrails (a reviewer invented extra requirements here — don't add them):**
the spec's blueprint sources are exactly an active gallery template OR an existing
user biolink-family page; intake is exactly paste / CSV / .xlsx. The bulk-URL flow
being mirrored has NO inline "build a fresh template" mode and NO add/remove-row
table authoring — so neither belongs in this feature. `{{title}}`/`{{handle}}`
already resolve because alias/title are injected into the substitution values.

**Why (gating decision):** a biolink batch must enforce BOTH `max_links` AND
`max_biolinks` plan caps for the whole batch up-front — the single-link
CheckPlanLimit middleware is the wrong tool. Block-type gate runs over ALL blocks
(incl. card children), including the `product`→`ecommerce` feature check, before any
row is created. Locked-template check compares `plan_tier` vs the user's plan by
`Plan.sort_order` (same basis as `scopeAvailableForPlan`); the UI shows all active
templates but disables/rejects locked ones rather than filtering them out.

**How to apply:** entry points must mirror bulk-URL — both the links *create* hub
AND the links *index* (hero actions + empty-state). Pages are written through
`TemplateService::applyPageToLink` so block trees / image URLs get re-sanitized for
free. PHP 8.4: pass the `$escape` arg explicitly to `str_getcsv`/`fgetcsv` or the
deprecation CI guard fails.
