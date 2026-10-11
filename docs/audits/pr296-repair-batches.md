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
