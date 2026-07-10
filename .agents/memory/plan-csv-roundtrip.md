---
name: Plan CSV export/import round-trip
description: The admin /admin/plans spreadsheet export and import share one schema; keep them in lockstep.
---

# Plan CSV export/import round-trip

`PlanCsvSchema` (app/Modules/Admin/Support) is the single source of truth for the
admin pricing-plans spreadsheet: it defines each column's header, its per-type
export formatter (`export` closure), and its import parse+validate (`parseCell`).
Both `PlanController::export()` and the importer build from `columns()`.

**Why:** export and import must round-trip exactly — the importer diffs a parsed
cell's `canonical` string against `export($plan)` to decide "changed vs unchanged".
If you change how a value is FORMATTED on export without matching the import
canonical form (or vice-versa), every cell reads as a phantom change or silently
never matches.

**How to apply:** add/adjust a column in ONE place (a descriptor in `columns()`),
never in the controller. Import matches plans by the `Slug` column
(`matchHeader()`), unknown slugs are skipped as error rows (never created),
required=Name, blank cell = "leave unchanged". Commit re-parses from the hidden
raw CSV (never trusts a client change set). Prices are minor units; feature keys
are MERGED into the existing features blob so omitted columns are preserved.
