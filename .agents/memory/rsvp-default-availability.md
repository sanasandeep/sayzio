---
name: RSVP default availability + organizer card
description: How free-event RSVP availability is gated, and where the organizer-card / same-host-events pattern lives.
---

`RedirectController::isRsvpAvailable(Link, ?Collection $tiers)` is the single source of truth for
whether an `ics` event's public page offers RSVP + QR check-in. False for ticketed events
(`ticketing_enabled` or active tiers exist); otherwise true unless the organizer explicitly set
`settings['rsvp_disabled']`.

**Why:** RSVP used to require a separate `rsvp_enabled` toggle. That field now controls only the
"customize RSVP form" sub-panel (capacity/deadline/questions/waitlist) — availability and
customization are orthogonal booleans, and free events should accept RSVPs by default.

**How to apply:** any surface deciding "can this event take an RSVP" must call
`isRsvpAvailable()` — never read `rsvp_enabled` directly for that purpose. Badge gating and RSVP
deadline checks are independent and still run after this gate. The opt-out toggle only exists on
the edit flow, not the create flow — new events default to RSVP-available.

`RedirectController::sameHostEvents(Link, $limit=4)` is the shared "more from this host" query
(upcoming first, backfilled with recent past events) — used by both the web event page and the
mobile API event shape, so a host without a public handle still gets a populated organizer card
and events list instead of an empty section.
