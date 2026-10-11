# PR296 repair batches

## Batch 1: fixtures and current contracts

Workspace ownership, fake upload storage, wizard answers, admin price/compare markup, release snapshots, buffered analytics writes, onboarding fixtures, billing approval and strategist demo slot counts. CI script: `composer test:repair-fixtures`.

## Batch 2: runtime and rendering

API threshold closure capture; reserved aliases; site page prefills; unchecked brand-kit sections; omitted updates body; centralized test email delivery; business taxonomy; AI resource accounting; duplicate flash messages; catalog Blade rendering; missing standalone view error bag. CI script: `composer test:repair-runtime`.

## Batch 3: unresolved isolation and transaction failures

Pending controlled reproduction against main with the same test ordering and database state. Full-suite failure counts change as file-size-based shard allocation changes; count differences alone are not evidence of fixes. Updates follower notification fixtures, critical alerts, payment/webhook, and other remaining failures require individual root-cause analysis.

## Validation

Dashboard previously passed 24 tests/120 assertions; rollback regression passed 1 test/7 assertions. Local Blade PHP, JSON attribute, Alpine comment, copy, JSON parsing and whitespace checks pass for the batch changes. This branch still needs CI runtime verification. No PHP/Docker is available locally. Required checks remain enabled. No production deployment has occurred.

## CI result on c3e9ab884

Dashboard: 24 tests, 120 assertions passed. Fixture batch: 153 tests, 1513 assertions passed. Runtime batch: 63 tests, 406 assertions, one failing route inventory expectation for sitemap-creators.xml. Full suite did not execute because Composer stopped after runtime batch failure. All three dedicated sitemap routes are declared before the alias catch-all and use dotted names disallowed by alias validation; added them to the existing explicit static-route allowlist. Reverification pending.

## CI result on aef630898

Dashboard 24/120, fixtures 153/1513, runtime 64/368 and rollback 1/7 all passed. Full suite ran and reported 114 distinct failing cases. CriticalEventAlertsTest expected four notification arguments; production sends five including the category. Updated the three mocks to assert the appropriate payment/renewal/job category explicitly and added a focused alert batch for CI. Remaining full-suite failures are not resolved.

## CI result on 9412cfa9d

Dashboard, fixture, runtime, critical alert (5 tests/10 assertions) and rollback batches passed. Full run reported 112 distinct failing cases. PaidGatewayDowngradeBillingFlowTest lost a subscription row during renewal; AiMindFeatureAdapterEventLookupTest encountered SAVEPOINT outside an active PostgreSQL transaction during user creation. Added an isolated two-class batch before the full suite to distinguish independent bugs from earlier test-order contamination. No speculative transaction reset or weakened assertion was added.

## CI isolated result on b9260c8c7

The two-class isolation batch ran 11 tests. The missing subscription and inactive PostgreSQL transaction errors did not recur. One owner-scope assertion failed because the not-found response echoes the search query; that is caller-provided input, not another owner's event data. Updated it to assert the exact not-found response and absence of location, tier, attendee identity and sales count. Test-order contamination remains suspected, not proven or fixed.
