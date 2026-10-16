# Public pricing configuration

The approved five-plan ladder is defined by `PlansAndAddonsSeeder::publicPlanLadder()`.
Prices are unchanged. The default page shows Free, Bio (the Bio Lite billing plan), Creator and Professional as compact tiles.
The comparison table uses those same four plans. All plans, grouped by Bio,
Platform, Teams and Developers, are available through `/pricing?all=1`. Unknown active public plans
remain visible in a More plans group.

| Plan | Bio pages | Short links | Custom domains | Seats | Monthly coins |
| --- | ---: | ---: | ---: | ---: | ---: |
| Starter | 1 | 10 | 0 | 1 | 0 |
| Bio Lite | 3 | 100 | 0 | 1 | 100 |
| Bio Plus | 10 | 500 | 1 | 1 | 300 |
| Creator | 25 | 2,000 | 2 | 3 | 800 |
| Professional | 100 | Unlimited | 5 | 10 | 2,500 |

Bio Plus does not include teams. The domain parent switch is enabled in all five
plans; the child permission and domain limit determine which plans can add domains.
Fresh seed definitions and the alignment migration use the same matrix. The
migration preserves prices, unrelated feature keys and the other five plan rows.
Known legacy Bio descriptions are corrected without replacing custom admin copy.

## Verify after deployment or an admin edit

Run `php artisan plans:audit-public` from the Laravel app. This reads the actual saved
plan rows, reports missing plans, unexpected limits and missing pricing groups, and
returns a nonzero status for differences. It does not modify plans, subscribers or
prices. Tests compare the code matrix; they cannot validate the production database.
Subscriber counts require a separate authenticated check and are not fixed counts.

Targeted tests: `vendor/bin/phpunit --filter 'PlanLadder|PlanGroup|Pricing'` against an
isolated test database. The homepage tests cover the current compact pricing teaser,
not the retired two-card pricing rail. The obsolete `fix/plan-ladder` branch is not
part of this release.

## Session matcher

Curate with Zio is an instant rules-based matcher, not a generative AI call. It
compares requested pages, seats, domains, paid forms and API access with the current
public catalogue and ranks matching options by the selected currency/cadence price.
It labels missing requirements when no exact match exists. Results expand the full
catalogue and comparison on the same page. Answers and expansion state use only
`sessionStorage` under `sayzio.pricing.matcher.v1`; reset removes that key. No
preference is written to an account, cookie, localStorage or database. Existing
site currency/cadence preferences are separate and retain their normal behavior.
