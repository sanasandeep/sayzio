# Link in bio runtime audit, 7 October 2026

## Executed checks

- Inspected all 114 canonical block admin previews on the authenticated live site, reading each preview iframe's rendered HTML text. This checks HTML rendering only: it does not prove every interaction, image, external embed or configuration works.
- Six previews displayed the fallback error: card, grid, grid_auto, insider, fan_leaderboard, roadmap.
- Buy Me a Coffee, Patreon and Ko-fi displayed a literal Blade username expression. TikTok's follow URL had the same escaping error in source.
- Poll admin preview produced ReferenceError messages for biolinkPoll and its dependent state.
- Verified the live profile preview now contains location, website, verified badge, social links and CTA.
- Standalone Chromium regression audit passed: 20 text designs on two backgrounds at 320, 375 and 768 pixels, preserved text and links, no horizontal overflow, reduced-motion behavior. Mocked poll success, rejected vote and future result deadline passed with no JavaScript exceptions.
- Blade JSON attribute guard, Alpine line-comment guard and git diff whitespace check passed.

## Fixes in this change

- Shared renderer initializes absent global theme state; admin preview uses an in-memory preview block and link id zero so containers and community route URLs can render without querying persisted preview children.
- Admin preview includes the shared poll/countdown runtime and text-motion runtime used by public pages.
- Correct Blade handle interpolation for three tip providers and TikTok URL.
- Serialize editable form button labels into JavaScript, including apostrophes, for contact, email, phone and tip capture.
- Scope vCard handlers by block id and serialize contact fields safely, preventing multiple cards from downloading the last card's data.
- Add nested block id/type markers used by live preview targeting.
- Update obsolete placement tests after root and child rendering were unified; update the default-style reset test to require removal of stored style.

## Added checks that have not run locally

BiolinkAllTypesRenderingTest covers every registered type with seeded and empty settings at root and nested positions (139 types, 556 rendering cases), plus targeted handle, vCard and editable label checks. It renders Blade directly without the preview controller's fallback exception handler, so a renderer failure fails the test instead of appearing as successful fallback HTML.

PHP, Composer and vendor dependencies are unavailable in this workspace. These PHP checks require CI; they are not reported as passed.

## Outstanding verification and issues

The merged main Tests workflow 37657337832 is failing. Its annotations include obsolete placement/reset assertions addressed here, alongside numerous other failures. This patch does not claim to resolve the whole suite.

RSS Feed still renders a feed heading/URL rather than feed items; YouTube Feed also requires further functional implementation/verification. External media with missing demo IDs/accounts cannot be judged from empty iframe text. Valid configured provider integrations, actual submissions, checkout, uploads, save/reload persistence, and all settings permutations have not been exhaustively tested. No production forms, purchases or data changes were submitted during this audit.

The fixes require review, merge and deployment before live verification. Do not equate the admin HTML inventory or source dispatch coverage with a complete end-to-end pass.
