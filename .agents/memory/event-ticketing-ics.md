---
name: Event ticketing on the ics link type
description: Paid ticket tiers/QR/check-in built on top of the free-RSVP `ics` calendar link type — where the surfaces live and naming gotchas.
---

The `ics` link type (mobile kind label `"calendar"`) grew paid ticketing: `EventTicketTier`/`EventTicket` models, `MonetizationCheckout`'s `event_ticket` kind (0% platform fee, reuses the existing creator-payout system), a public `/events` directory + `/{alias}` event page, owner tier CRUD + QR check-in scanner (web), and full `/api/v1` mobile parity (`EventTicketApiController`) + mobile screens under `app/events/*`.

**Why paid and free RSVP coexist safely:** ticketing is opt-in per event (`ticketing_enabled` / `tiers` presence); the original free-RSVP flow is untouched code paths.

**How to apply / gotchas:**
- The owner check-in endpoint path is `/api/v1/links/{link}/event-checkin`, NOT under `/events/{id}/checkin/*` — it lives with the other per-link owner actions, not the public events namespace. Don't guess the path; grep `routes/api.php` before wiring a client.
- Mobile ticketing entry point is gated on `meta.kind === "calendar"` (not `"ics"` or `"event"`) in `app/links/[id]/edit.tsx`'s kind mapping (`lib/linkKinds.ts`).
- Adding new Expo Router screens (e.g. `app/events/...`) breaks `router.push()` typecheck until `.expo/types/router.d.ts` is regenerated. If no expo dev workflow is currently running, a one-off `timeout 25 npx expo start --no-dev --offline --port <free-port>` inside the mobile package regenerates the types file well enough for `tsc --noEmit` to pass — no need for a live full dev server.
- `composer check:mobile-docs-parity` only tracks *link/block type* documentation drift, not fine-grained sub-features like ticketing; a new sub-feature on an already-documented link type won't show as drift.
