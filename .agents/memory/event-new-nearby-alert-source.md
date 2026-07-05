---
name: event.new_nearby is a real server-side notification
description: The "new event near you" alert already exists server-side (preference-gated + proximity-gated); mobile clients should consume it, not reimplement it.
---

`event.new_nearby` is a full server-side feature: `SendNewEventAlerts` (scheduled command) does the Haversine proximity check against a user's saved `event_alert_latitude/longitude/radius_km` and `event_alerts_enabled`/`event_alert_frequency`, and `NotificationService` gates delivery on the user's own `event.new_nearby` notification preference (in_app/email/push) before writing a `user_notifications` row. It surfaces through the existing generic `GET /api/v1/notifications` feed alongside every other notification type.

**Decision:** a mobile client wanting "new nearby event" alerts should poll/read the existing notifications feed and filter for `type === "event.new_nearby"`, not re-derive its own distance/preference logic on-device from scratch.

**Why:** re-implementing proximity + preference checks client-side duplicates business logic, drifts from the server's radius/frequency settings, and — critically — ignores the user's actual opt-out preference (a purely local poll fires regardless of what the user configured on the backend).

**How to apply:** there is currently no mobile-facing endpoint to *set* `event_alert_latitude/longitude/radius_km/frequency` (only web settings), so a client can consume these alerts but can't yet let the user configure the alert radius/location from the app — that gap needs a small backend endpoint addition if a client ever needs to expose that control.
