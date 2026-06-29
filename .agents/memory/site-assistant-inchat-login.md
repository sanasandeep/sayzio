---
name: Site assistant in-chat passwordless login
description: How Zio Bot's in-chat OTP login/signup works across the blade + marketing-React widgets, and the token gotcha.
---

# In-chat passwordless login for the Zio Bot assistant

The assistant can sign anonymous visitors in/up WITHOUT leaving the chat panel,
on BOTH front-ends, via two endpoints under the already-CORS/CSRF-exempt
`assistant/*` group: `auth/send-code` (throttle:otp-send) and
`auth/verify-code` (throttle:otp-verify). Login == signup: the first successful
verification of an unknown identifier creates the account server-side.

**Why the two front-ends diverge on auth carrier:**
- Same-origin blade widget → `verifyCode` does `Auth::login` + session
  regenerate (cookies work). Sends NO `issue_token`.
- Cross-origin marketing React widget → no cookies cross the origin, so it
  sends `issue_token:true` and gets back a Sanctum bearer token it replays as
  `Authorization: Bearer` on EVERY `/assistant/*` call (incl. bootstrap).

**Token gotcha (marketing widget):** the pre-existing `sa_visitor_token_v1`
localStorage key holds the anonymous *visitor_token* (conversation continuity,
sent in the body) — it is NOT an auth token. The bearer auth token MUST live
under a separate key (`sa_auth_token_v1`); conflating them breaks both. After
login, persist the bearer key, null the cached bootstrap promise, and re-boot
so the chat unlocks in place.

**How to apply (any cross-cutting change here):** keep the request/response
field names lockstep between the blade `showLoginGate()` form and the React
`LoginGate` component. Server resolves the caller via a `currentUser()` helper
= `$request->user() ?: $request->user('sanctum')` (Laravel 11 auto-registers
the sanctum guard). Respect: registration-pause (no code/no account when paused
+ unknown identifier), enumeration guard (generic success on send-code
regardless of account existence), honeypot (`website`) + time-trap
(`elapsed_ms` via `QuickContactService::tooFastSubmission`), and bounce
2FA-enrolled accounts to the full login page (2FA in chat is out of scope).
Bootstrap exposes `email_otp_enabled`/`mobile_login_enabled` so the front-ends
show/hide the phone tab and fall back to the full-page CTA when both are off.
