---
name: Event ticket tier capacity alerts
description: How the "tier is 90%+ full / sold out" proactive owner alert is wired and kept idempotent
---

Proactive alert fires the moment a paid ticket tier crosses 90% or sells out, so the owner can add capacity before missing sales (complements the AI Mind events snapshot which only surfaces the same flags reactively).

- Trigger point: `MonetizationCheckout::issueEventTicket()`, right after `$tier->increment('sold_count', ...)`. That covers every sale path (free + paid + preview) because they all funnel through issueEventTicket. The tier's denormalized `sold_count` (not EventCheckinProgress' summed-quantity figure) is the signal — it's the value being bumped there and matches `remainingCapacity()`/`isSoldOut()`.
- Idempotency: two nullable stamp columns on `event_ticket_tiers` — `capacity_alerted_near_at` / `capacity_alerted_full_at`. `EventTierCapacityAlerter` claims each threshold with an atomic conditional UPDATE (`whereNull(...)->update(...)`) so concurrent sales can't double-send. Sold-out claim stamps BOTH columns so a jump straight to sold-out never emits a redundant 90% alert afterward.

**Why:** "once per threshold per tier, no spam" — a naive read-then-write on a hot sale path races.
**How to apply:** raising a tier's capacity (web `EventTicketTierController::update` + API `EventTicketApiController::updateTier`) clears both stamps so a re-fill re-alerts; both update paths must keep that reset in lockstep. Null-capacity tiers are unbounded and never trip (`capacityFilledRatio()` returns null).

- Surfaces: in-app `UserNotification` (types `event.tier_near_full` / `event.tier_sold_out`, created directly like `event.ticket_sold`, not via NotificationService catalog) + email registry key `ticketing.tier_capacity` (inline, category `events`). Messages are counts-only, no attendee PII. Notification payload uses the canonical `url` key (resolveTargetUrl only reads url/target_url/fix_url; the older `event.ticket_sold` uses `link` which doesn't resolve).
