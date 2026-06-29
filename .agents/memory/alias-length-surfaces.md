---
name: Custom-URL (link alias) length enforcement surfaces
description: Where the per-plan alias min/max length is resolved and the surfaces that historically bypassed it.
---

## Single source of truth
`User::getAliasLengthLimits()` resolves `{min,max}` from the per-plan `min_alias_length` / `max_alias_length` features (admin-editable). The live availability checker `AliasAvailability::check()` and every submit path must go through it so inline feedback matches server enforcement.

**Why:** the minimum is a per-plan perk — free/entry plans get the LARGEST minimum, paid tiers step down. Hardcoding a number anywhere breaks that and desyncs the checker from validation.

## Surfaces that did NOT use the helper (fixed; re-check when touching aliases)
- `Api\LinkController` store / update / storeSmart / storeAb — alias rules had `max:80` + regex but **no `min` at all**; REST/mobile clients could create sub-minimum aliases. Each now prepends `'min:' . $owner->getAliasLengthLimits()['min']`.
- `User\LinkController::updateAlias` (edit primary alias) and `LinkAliasController::store` (add extra alias) hardcoded `min:3, max:60`. Min now resolves via the helper; **max left at 60 on purpose** (changing the max scheme is out of scope; these allow 60 vs the plan cap of 50 — pre-existing).

Already correct (helper-based): web LinkController create rules, File/Vcf/Ics link controllers, BiolinkWizard (web + API), BulkBiolink. CalendarController step 2 only renders the form; the calendar link is actually created through the standard LinkController create flow.

## Per-plan minimum scheme & backfill
Seeded minimums: free=4, creator/professional=3, business/agency/developer=2, enterprise-api=1. Central fallback (helper) and admin plan-form default are both 4.
The overlay seeder never overwrites an existing feature key, so existing rows are backfilled by a one-time data migration that moves a plan only if its current `min_alias_length` is unset or still on the OLD global default (3) — admin-customized values are preserved.
