---
name: AI Marketing Strategist report surfaces
description: Where the deepened analysis (scorecard/diagnosis/forecast/competitor/outcome) is rendered and the lockstep gotchas.
---

# AI Marketing Strategist — analysis rendering lockstep

The deepened analysis blocks live on `MarketingStrategy` JSON columns
(`scorecard`, `diagnosis`, `forecast`, `competitor_analysis`, `outcome`,
`baseline`) and are rendered in TWO independent places that must agree on shape:

1. `MarketingStrategistService::toHtml()` — the canonical branded HTML used for
   the **Rich/Premium PDF download AND the public share page**
   (`PublicMarketingReportController` reuses `toHtml`, self-contained inline CSS,
   no Vite manifest so it survives outages).
2. `resources/views/user/ai/marketing-strategist/show.blade.php` — the in-app
   dashboard (interactive, inline SVG charts only, no CDN).

**Rule:** a new analysis section must be added to BOTH `toHtml` and the show
blade or it silently won't appear in downloads/shares (or vice-versa). The
multi-month `execution_plan` block is even wider lockstep — FIVE surfaces:
`toHtml`, `toMarkdown`, `toCsv` (all in the service) + `show.blade.php` +
its `_month.blade.php` partial. All read the same normalized shape
(`{overview, period_months, phases[], months[{month,theme,budget,goals[],deliverables[],automation_flows[],timeline[]}]}`)
from `normalizeExecutionPlan()`; missing/invalid JSON normalizes to null and
every surface guards on empty.

**Why:** the PDF/public report is NOT generated from the blade — it's a separate
string builder. The two representations drift easily and must be kept in sync.

## Branded PDF logo fetch (SSRF)
`MarketingStrategistService::embedImage()` inlines a Brand Kit logo as a base64
data URI (dompdf remote images stay off). Remote http(s) fetches MUST keep TLS
verification ON, follow NO redirects, and pass `remoteImageFetchAllowed()` —
which resolves the host and rejects any private/reserved/link-local IP (SSRF).
**Why:** logo URLs are user-controlled; disabling TLS or allowing internal
hosts is a security regression a code review will block.

## Project profiles (multi-project)
`MarketingProfile` is now MULTIPLE named projects per (user, workspace), not one.
The New Strategy form picker (`profile_id` select + `profile_choice`/`new_profile_name`)
resolves to a profile id in `resolveStoreProfileId()` and passes it as
`generate()`'s 7th arg. Inline-create (`profile_choice='new'`) seeds a profile
from the form's goal/audience/budget/currency/main_offer. Legacy `.profile`
route now redirects to `.projects.index`; CRUD lives under
`.projects.{index,create,store,edit,update,destroy}`.

## Non-obvious shape facts
- `forecast` accepts EITHER `scenarios` or `bands` (fallback) keyed
  name→{value||projected, delta_pct, label}. Support both.
- `scorecard['reasons']` is sometimes a KEYED assoc array (no-data path in
  `MarketingScorecardService::score`, has_data=false) and sometimes a flat list;
  both must iterate as values.
- `refreshOutcome`/`MarketingOutcomeService::evaluate` returns **null unless a
  `baseline` (with `value`) exists** — controller then soft-errors via
  `back()->with('error')` and writes nothing. Tests must seed `baseline`.
- `recomputeScore` always yields a scorecard (overall 0 for no-data), never null
  unless `diagnose()` throws.

## Download tiers (split-button)
Markdown / Rich PDF / CSV are FREE (no coins; `export()` with `?format=`).
Premium AI PDF (`report()`) is metered via `generatePremiumReport` which throws
on empty/failed AI (chat call is OUTSIDE its try, auto-refunds inside) →
controller catches `\Throwable` → redirect back with error.

## Depth slider
Builder posts `parameters[depth]` (1-5), `parameters[goal_metric]`,
`parameters[horizon_days]`; validated as `parameters.*` in `validateBuilder`.
`MarketingStrategy::depth()` clamps 1-5 from `parameters['depth']`.
