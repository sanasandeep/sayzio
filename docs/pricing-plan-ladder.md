# Public pricing configuration

The approved five-plan ladder is defined by `PlansAndAddonsSeeder::publicPlanLadder()`.
Prices are unchanged. Public plan cards are grouped into Link in Bio and the wider
platform. Team and developer cards are available through `/pricing?all=1`; the
comparison table continues to include all public plans. Unknown active public plans
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
