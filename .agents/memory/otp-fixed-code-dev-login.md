---
name: OTP fixed dev code + curl login flow
description: How to smoke-test 1inme login via curl when only OTP is enabled (password login off), and the CSRF pitfalls involved.
---

`AuthMethods::emailPasswordEnabled()` can be `false` site-wide (OTP-only mode) — posting to `user.login.submit` with a correct password then just redirects back to `/user/login` with no visible error, which looks like a bug but isn't. Check `AuthMethods::emailPasswordEnabled()`/`emailOtpEnabled()` before assuming credentials or app logic are wrong.

`OtpService::generate()` uses a **fixed code `123456`** whenever `app()->environment('production')` is false — no need to read the `otps` table or logs to smoke-test login in dev.

Curl login flow gotchas:
- `POST /user/send-otp` requires `identifier` **and** `type` (e.g. `type=email`), not just `identifier`, or it 422s.
- The send-otp response is a redirect to `/user/verify-otp`; the CSRF token must be re-fetched from that page (a fresh `GET /user/verify-otp` with the same cookie jar) before posting the code — reusing the token from the original login-page GET gives a 419.
- A successful `POST /user/verify-otp` 302s to `/user/dashboard`, which itself may 302 further to `/user/onboarding/whatsapp` if `onboarded_at` is unset — that's expected for freshly-seeded accounts, not a failure.

**Why:** saved after burning several round-trips diagnosing a "login silently fails" symptom that was actually password-login being disabled + otp params/CSRF-refresh requirements, while testing a new demo account end-to-end via curl (no browser).
