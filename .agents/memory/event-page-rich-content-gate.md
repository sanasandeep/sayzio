---
name: Event pages rich content gate
description: Why plain ics links download instead of showing the rich event page, and how to smoke-test rich content safely.
---

The `/{alias}` route for an `ics`-type link only renders the rich event page (cover image, gallery, info sections, hashtags, Interested/Not-interested buttons, similar & same-host recommendations) when `settings['ticketing_enabled']` is true, via `RedirectController::handleEventTicketingPage`. A separate `/{alias}/rsvp` route (`RedirectController::rsvpForm`) renders similar rich content when `settings['rsvp_enabled']` is true. Any ics link with neither flag set just serves a raw `.ics` calendar file download (`handleIcsDownload`) — this is original pre-existing routing behavior, not a bug to fix.

**Why:** Rich event content sections were added to the event page/rsvp-form templates without changing which links are eligible to see them — ticketing/RSVP opt-in remained the gate. Confirmed via live smoke test: flipping `ticketing_enabled=true` + populating hashtags/cover image/gallery/info sections on a demo event immediately surfaced all rich sections with no errors; a plain event without either flag returned the bare ICS text.

**How to apply:** When smoke-testing or debugging "why doesn't my event show hashtags/gallery/etc.", first check `link.settings['ticketing_enabled']` and `icsData/settings['rsvp_enabled']` before assuming a rendering bug. `User::create()`/`Link::create()` in tinker hang in this environment (see other memory), but `->save()` on an existing Link/IcsData row works fine for quick temporary smoke-test writes — always revert them afterward (`settings` unset, array fields back to `[]`).
