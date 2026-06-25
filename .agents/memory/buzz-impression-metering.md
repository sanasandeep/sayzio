---
name: Buzz impression metering
description: How per-plan monthly Buzz (social-proof) view caps are metered and enforced
---

# Buzz impression metering (`max_buzz_impressions`)

Per-plan monthly cap on Buzz (Social Proof) notification **views**. -1 = unlimited.

- **Counter is period-scoped, NOT the cumulative column.** `social_proofs.impressions`
  never resets and must not be used for gating. Gating reads `buzz_impression_counters`
  (one row per user+calendar-month), mirroring `api_usage_counters`.
- **Single chokepoint:** `BuzzImpressionMeter` (app/Services). `servingPaused()` is the
  gate; `record()` is the atomic upsert-increment called on each public impression.
- **Enforced in `SocialProofPublicController::config()`** — when the owner is over cap it
  returns an empty `notifications` array, so both external embeds and biolink-block widgets
  (which both fetch config) stop serving. `track()` meters impressions only.
- **Backfill safety:** allowance defaults to -1 (unlimited) when the plan key is missing, so
  un-reseeded plans never silently cut creators off.
  **Why:** existing plan rows aren't updated by the overlay seeder.
- **Bypass:** super-admins with `user.plan_limits.bypass` get PHP_INT_MAX allowance via
  `getPlanFeature`, so they never pause — expected.
