---
name: Stats CSV export gating
description: Which CSV exports the analytics_export plan feature gates, including the creator-stats dashboard export.
---

# Stats CSV export gating (`analytics_export`)

All stats/analytics CSV exports are gated behind the `analytics_export`
PremiumFeatures key ("Stats CSV export"). Surfaces:

- Web link clicks export, follower/subscriber list export, slide-deck
  analytics CSV (always gated).
- Web **creator-stats dashboard export** (`/user/stats/export`,
  `CreatorStatsController::export`) — was originally FREE; deliberately
  brought under the gate so the paywall is consistent across all stats
  exports.
- Mobile Stats screen "Export CSV" button (`artifacts/1inme-mobile/app/stats.tsx`)
  — gated via the `capabilities.analytics_export` flag on the self user
  payload (`UserResource::toArray(..., self:true)`, returned by
  `/api/v1/auth/me` and `/api/v1/profile`).

**Why:** the creator-stats export being free was an inconsistency; the
owner chose to gate everything rather than un-gate the others. Do NOT
"fix" the creator-stats gate by removing it thinking it's an oversight.

**How to apply:** any new stats/analytics CSV export must check
`workspace_owner()?->getPlanFeature('analytics_export', true)` server-side
(default-true fallback) and hide/disable the control with upgrade
messaging client-side. Mobile reads the capability, never re-derives it.

Note: `/api/v1/stats` (the data feed the mobile Stats screen tries to
fetch) is NOT implemented — the screen is a stub that errors, so the
gated button is currently unreachable until that endpoint exists.
