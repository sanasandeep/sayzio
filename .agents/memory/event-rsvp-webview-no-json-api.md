---
name: Free event RSVP has no JSON API — embed the web form
description: How mobile apps do in-app RSVP for the free/non-ticketed event flow when only a session-based web form exists.
---

The free RSVP flow (Yes/No/Maybe, plus-ones, custom questions, capacity/waitlist)
lives entirely server-side in the public session/CSRF form at `/{alias}/rsvp`
(`RedirectController::rsvpForm`/`rsvpSubmit`). There is no JSON API endpoint for
it — only ticketed events have an API purchase flow.

**Decision:** rather than building a new backend endpoint (out of scope for
client-only mobile tasks) or duplicating the form's validation/business logic
client-side, embed the existing web page in a WebView (same pattern as a
map-picker WebView) so the visitor never leaves the app. Detect success by
watching `onNavigationStateChange` for the redirect to `/rsvp/manage/...`
(the form's own success target) rather than parsing response bodies.

**Why:** keeps a single source of truth for RSVP business rules (capacity,
waitlist, custom questions) and avoids a backend change for a client-scoped task.

**How to apply:** reuse this pattern (WebView + navigation-URL success sniff)
for any other visitor-facing flow that's session/CSRF-form-only with no JSON
API, before reaching for a new backend endpoint.
