---
name: IcsLinkController::update settings-only backfill
description: why a partial PUT to the ics link update route needs field backfill before validation
---

`IcsLinkController::update()`'s `validateRequest()` requires `event_name`,
`start_date`, `end_date`, `timezone` even though several UI surfaces
(the ticket-page "hide from directory" mini-toggle, the events-directory
"make discoverable" nudge) intentionally submit only 1-2 settings keys via
the same PUT route.

**Why:** these were latent partial-submit forms that would have hit a 422 on
the missing required fields; reusing the single update/settings mutation
path (rather than adding a parallel endpoint) only works if the controller
backfills the omitted core fields from the existing `Link`/`IcsData` before
validating.

**How to apply:** in `update()`, when `event_name` is absent from the
request, merge in `event_name`/`description`/`location`/`organizer(_email)`/
`start_date`/`end_date`/`timezone`/`url` from `$link`/`$link->icsData` before
calling `validateRequest()`. Any new settings-only mini-form against this
route must rely on that backfill, not add required-field workarounds.
