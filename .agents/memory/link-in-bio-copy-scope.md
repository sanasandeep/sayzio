---
name: "Link in Bio" copy standardization scope boundary
description: What counts as in-scope "visible user-facing wording" vs out-of-scope identifiers when standardizing biolink→Link in Bio copy across 1inme artifacts.
---

# "Link in Bio" wording standardization — scope boundary

Product wording is "Link in Bio" (plurals meaning pages → "Link in Bio pages"). When asked to standardize visible copy across the 4 artifacts (Laravel `1inme`, `1inme-com`, `1inme-mobile`, `1inme-deck`), the judgment that matters is **visible copy vs identifier**.

**IN SCOPE (change):** flash messages (`->with('success'/'error', ...)`), API error messages returned in the `{error:{message}}` envelope (e.g. `notFound('Biolink not found')` — they surface in mobile UI; CardScan precedent), moderation/notification descriptions shown in UI, admin-UI strings, blade text nodes / `@section('title',...)` / visible attributes (title=, placeholder=, option text), marketing content classes (ComparisonContent, SitePagesContent, MarketingSeo titles+descriptions), and **display-label array keys** (e.g. ComparisonContent group-label keys that are iterated as section headings — verify nothing looks them up as a literal first).

**OUT OF SCOPE (leave):** class/method/var/route/view names, array keys & enum values (`'biolink'`, `type=>'biolink'`, `max_biolinks`, settings paths like `settings['biolink']`, app-binding keys), comments (`//`, `/* */`, `{{-- --}}`), `utm_medium` value `'biolink'`, generated filenames, **MarketingSeo `keywords` meta** (deliberate SEO search terms — leaving them avoids SEO regression), **artisan `$signature`/`$description`** (CLI-only — but admin-UI CronJobsInspector descriptions ARE in scope), AI/model-facing prompts & function-calling tool descriptions (per AI policy), and developer docs (api.md, features.md, knowledge-base.md, api-docs blade/tsx).

**Why:** "visible user-facing wording" = what a user/admin reads in the product. CLI text and SEO keyword soup are technical surfaces; identifiers/keys break behavior if touched.

**How to apply:** drive edits with a curated explicit `[from,to]` script that asserts each match is non-zero (catches stale targets); then re-grep each artifact and confirm every remaining hit falls in an OUT-OF-SCOPE bucket. No automated regression guard exists — copy will drift again unless one is added.
