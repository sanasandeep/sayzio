---
name: Company identity + legal-page seed versioning
description: How the admin-editable company identity flows into legal pages, and the multi-snapshot seed-version refresh rule for policies.
---

# Company identity & legal-page disclosures

`CompanyIdentity` (Common/Support) is the single source for the operating
company's legal identity (legal name, registered address, jurisdiction
city/state/country, general/legal/privacy-DPO/grievance emails, website URL).
Each field is one `AppSetting` key; blanks fall back to code defaults (Hyderabad,
Telangana, India; email local-parts are fixed — general→`support`, `legal`,
`privacy`, `grievance` — on `emailDomain()` which falls back to `sayzio.app`).
`grievance@` is the India IT-Rules / DPDP-Act Grievance Officer mailbox.
`tokens()`/`substitute()` replace `{{company_*}}`, `{{jurisdiction*}}`,
`{{app_name}}` tokens in policy section bodies/intros at render time
(`public/policy.blade.php`) and feed the footer.

## Policy seed-version refresh rule (the non-obvious part)

`SitePagesContent::policyDefaults()` is the **current** (V3) default set: deep
Terms/Privacy/Refunds/GDPR copy, all contacts token-driven onto the dedicated
mailboxes, plus grievance-officer sections. Prior frozen snapshots live in
`richDefaults()`, `policyDefaultsV1()` and `policyDefaultsV2()`, surfaced together
via `policyPreviousDefaults()` (keyed by slug → list of {title,meta,intro,sections}).
Cookies V3 is byte-identical to V2 (its emails are already token-driven).

**Why multiple snapshots:** an untouched page may match *any* earlier shipped
default, not just the immediately previous one. So both the seeder and the
refresh migration use `sectionsMatchAnyPrevious($current, $prevSectionSets)`:
- match → replace wholesale + re-stamp `last_updated_at` to today (untouched)
- no match → `mergeMissingSections` (admin edited → only append new sections by
  stable id, never clobber)

**How to apply:** when you change policy defaults again, snapshot the outgoing
set into a new frozen `policyDefaultsV{n}()`, add it to `policyPreviousDefaults()`,
and add a content-only refresh migration mirroring the seeder loop. Never seed
`AppSetting` rows for identity — defaults stay in code until an admin overrides.

A `policyDefaultsV{n}()` snapshot may be **slug-partial**: if only one page
changed in a generation, freeze only that page's outgoing entry (V3 is
privacy-only). Unchanged pages are already covered by earlier snapshots +
current defaults, and `policyPreviousDefaults()` is keyed per slug so partial
sets compose fine.

## Per-section refresh fallback (July 2026)
Shared-DB policy rows can drift from EVERY frozen snapshot (e.g. an em-dash copy sweep changed defaults without freezing a new generation), so whole-page "untouched" detection fails and body edits to existing sections never land via append-missing. Fix pattern (see 2029_07_22_000001 migration): a SECTION_REFRESH map replaces a single section's body only when that section's current body verbatim-matches ANY previous default for the same id — safe on admin-edited pages because a touched section never matches. Appended sections on drifted pages keep their new-generation heading number (e.g. "10." after "24."), accepted per prior generations; pristine installs still get wholesale replacement with correct ordering/renumbering.
