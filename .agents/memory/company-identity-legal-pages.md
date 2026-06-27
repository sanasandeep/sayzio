---
name: Company identity + legal-page seed versioning
description: How the admin-editable company identity flows into legal pages, and the multi-snapshot seed-version refresh rule for policies.
---

# Company identity & legal-page disclosures

`CompanyIdentity` (Common/Support) is the single source for the operating
company's legal identity (legal name, registered address, jurisdiction
city/state/country, general/legal/privacy-DPO emails, website URL). Each field
is one `AppSetting` key; blanks fall back to code defaults (Hyderabad, Telangana,
India; emails derived from website domain → `contact_recipient_email`).
`tokens()`/`substitute()` replace `{{company_*}}`, `{{jurisdiction*}}`,
`{{app_name}}` tokens in policy section bodies/intros at render time
(`public/policy.blade.php`) and feed the footer.

## Policy seed-version refresh rule (the non-obvious part)

`SitePagesContent::policyDefaults()` is the **current** (V2) default set with
tokens + AWS/AI/tracking-pixel disclosures. Prior frozen snapshots live in
`policyDefaultsV1()` and `richDefaults()`, surfaced together via
`policyPreviousDefaults()` (keyed by slug → list of {title,meta,intro,sections}).

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
