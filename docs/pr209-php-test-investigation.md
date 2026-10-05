# PR #209 PHP test investigation

Examined [PHP job 111879898623](https://github.com/sanasandeep/sayzio/actions/runs/37344557141/job/111879898623), which tested commit `e478c918911221b98e3e36db27ee747d9714e70d`. All six shards ran: 6,711 tests, 169 failures and 26 errors. The preceding main commit (`f1e2b403a969`) also failed: 168 failures and 25 errors. A failed suite does not establish that deployment failed or that every listed failure was introduced by this PR.

## Confirmed corrections

- `WhatIsQuotedIsWhatIsChargedTest::test_store_bulk_pricing_is_quoted_and_saved`: PostgreSQL rejected the fixture with SQLSTATE 23502 because `store_products.category_id` is required. Create a category and attach the test product. The bulk-pricing assertions remain unchanged.
- `TheCartSurvivesAReloadTest::test_the_old_price_is_kept_only_to_notice_it_changed`: an exact source assertion still expected `it.price` directly, although restoration now selects regular or bulk pricing from the live catalog before adding extras. Update the assertion to the current `base` formula. Node execution confirmed regular/bulk/zero bulk prices, extras and ignoring the stored price.
- `ACustomerCanJumpDownALongMenuTest::test_navigation_colours_survive_saving_reloading_and_public_rendering`: already failed before #209. Its settings request returned 422 because required `mode` and `currency` were absent. Include both in valid and invalid-colour requests so the test reaches the intended colour validation.

## Remaining findings

Comparing the failing test names with the preceding main run identifies three additional new names: `DialerCallerIdReachabilityScaleTest::test_favorites_hide_a_suspended_or_blocking_creators_biolink_badge`, `TheGuestLearnsTheOrderWentThroughTest::test_the_confirmation_panel_has_the_box_it_fills`, and `TheKitchenScreenShowsWhatIsWaitingTest::test_a_table_shows_its_oldest_open_ticket_not_its_newest`. Each fails with SQLSTATE 25P01 (`SAVEPOINT can only be used in transaction blocks`) while creating a user through the linked-identifier model callback. This suggests a test transaction/state issue; the underlying cause has not been established and is not corrected here.

The broader failures span unrelated billing, AI, marketing, routing and other features. They require separate diagnosis. No production pricing or ordering code is changed by this correction.

## Validation limits

`git diff --check` and Node cart-restoration checks passed. PHP is not installed locally, so the corrected PHPUnit tests must run in CI before claiming verification. Useful focused rerun:

```sh
cd artifacts/1inme
php artisan test --filter='WhatIsQuotedIsWhatIsChargedTest|TheCartSurvivesAReloadTest|ACustomerCanJumpDownALongMenuTest'
```

Run against the isolated test database defined by CI, then run the full sharded suite. Do not disable failed tests or weaken production validation to make CI pass.
