---
name: RSVP silent account provisioning
description: How free-event RSVP submission provisions a lightweight Sayzio account, and the guard/test gotchas involved.
---

Free RSVP submission (`RedirectController::rsvpSubmit`) mirrors the paid-ticket guest flow (`EventTicketPublicController::buy`): `User::firstOrCreate(['email' => ...], [...])` with a random bcrypt password, default plan via `Plan::defaultPlan()`, and `ensureDefaultWorkspace()` only when `wasRecentlyCreated` is true (so existing accounts are never touched — no password/plan/profile overwrite). Wrapped in try/catch so provisioning failures never block the RSVP.

**Why:** RSVP-ers should land as real free-plan users without any extra step, but an RSVP must never risk clobbering an existing account or ever fail the visitor-facing flow.

**How to apply:** gate on `!$request->user()` (skip entirely if already signed in) and only when the RSVP has an email. The `/{alias}/rsvp` POST route is a plain **web (session) guard** route, not sanctum — a bearer token does NOT count as "signed in" here; feature tests must use `actingAs($user, 'web')`, not a Sanctum token, to simulate a logged-in visitor. Any test that creates a second `User` after binding `current_workspace`/`workspace_owner` (via the common `makeUser()` helper pattern) must `app()->forgetInstance('current_workspace')` + `workspace_owner` before hitting the public alias route, or the public GET/POST 404s (see isolated-env workspace-scope leak note).
