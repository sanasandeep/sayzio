---
name: Mobile event create bridges to canonical ics type
description: Why mobile posts type "event" but the canonical event link type is "ics", and the lockstep surfaces that bridge them.
---

The mobile "Event invite" create screen posts `POST /api/v1/links` with
apiType `"event"` + a loose `settings.event {start,end,location}` blob. The
canonical event link type is `"ics"` with a companion `IcsData` row — the
public event page + RSVP flow (`RedirectController::handleEventTicketingPage`,
route match `'ics'`) render from `ics_data`, NOT from `settings.event`. A bare
`event` link renders nothing.

**Rule:** an event created via REST must be stored as `type='ics'` AND get an
`IcsData` row, or it won't resolve as an event page. Bridging is lockstep
across:
- `Api\LinkController::store()` — accepts `event`|`ics`, remaps `event`→`ics`,
  and builds the `IcsData` row (`createEventData()`); also accepts `ics`
  directly so mobile *duplicate* (re-posts the stored type) keeps working.
- mobile `lib/linkKinds.ts` — `KINDS_BY_API['ics']` is aliased to the calendar
  meta, else the edit screen + link lists show a stored event as "Short link".

**Why:** web (`IcsLinkController`) and mobile create events through totally
different request shapes; only the `ics` type + `IcsData` is shared truth.

**How to apply:** `IcsData.event_name/start_date/end_date` are NOT NULL — every
create branch must resolve concrete values (event_name←title||'Event',
end←start+1h when missing/invalid, timezone←'UTC'). A plain `ics` link with no
ticket tiers still shows the event page (RSVP on by default unless
`settings.rsvp_disabled`); `?ics=1` is the raw .ics download.
Do NOT change the web `IcsLinkController` path.
