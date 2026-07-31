---
name: Service Booking staff/self-service gotchas
description: Testing/extension gotchas for the Service Booking staff, capacity, buffers, and visitor self-service features.
---

- `ServiceBookingRequestService::place()` returns an ARRAY (`['booking'=>..,'checkout_url'=>..]`) since the paid-bookings refactor; older ServiceBookingFlowTest tests still treat it as a model — 8 failures are pre-existing at HEAD (verified via git stash run), not regressions.
- `SlotAvailabilityService::freeSlots($config,$len,$tz,$opts)` does NOT derive capacity from `service_ids` — callers (API/web controllers) compute the min service capacity and pass `capacity` in `$opts`. Direct service calls in tests must pass it too or remaining defaults to 1.
- `service_booking_requests.public_token` is a Postgres uuid column; test fixtures must use `Str::uuid()`, not slug-style strings (insert fails 22P02).
- Staff plan cap: `getPlanFeature('max_service_booking_staff')` (0=hidden, -1=unlimited); users with no plan get the default (null→0), so cap tests need a real `App\Modules\Admin\Models\Plan` with features.
- Staff CRUD create returns 201 (`assertCreated`); "any available" surfaces the best remaining across staff and auto-assigns a free member on book.
- Blocked-dates uniqueness is TWO partial indexes (page-level `staff_id IS NULL` + per-staff), not a plain unique on (booking, date) — a plain unique blocks per-staff days off on already-blocked dates and two members blocking the same date. App-level dupe checks must also be per-staff-scoped.
