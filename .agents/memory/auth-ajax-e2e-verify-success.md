---
name: AJAX auth e2e — OTP verify success path
description: Why a good-OTP-verify e2e must log into an existing user and assert on status+navigation, not the JSON body
---

# AJAX auth flows e2e (auth-ajax.js) — the good-verify success path

Covers the Browser spec that exercises `public/js/auth-ajax.js` (password login,
OTP send/verify, register referral, admin forgot/reset). Error paths are easy;
the OTP verify GOOD-code (success) leg has three traps, all env-driven by the
single-worker `php -S` ephemeral e2e server + distant RDS.

## 1. A good OTP verify for a FRESH email runs the signup pipeline and stalls
`AuthController::verifyOtp` success path branches on `resolveUserByIdentifier`:
- existing user -> `Auth::login` (fast, same path password login uses)
- unknown email -> `createOtpSignupUser` (new account creation pipeline)

On the single-worker e2e server the signup pipeline does not respond within 120s
(the verify POST just hangs). **Fix:** seed the OTP email as an EXISTING active
user in the fixture seeder (name/status=active/email_verified_at/referral_code/
plan_id) so the good verify is a fast OTP *login*. Signup itself is covered by
the sibling `otp-signup-*` specs — don't re-test it here.
**Why:** the spec tests AJAX verify *mechanics* (inline error + no reload + CSRF
rotation + success redirect), not the account-creation pipeline.

## 2. Don't assert on the success response BODY — it races the redirect
On success auth-ajax.js reads `res.json()` then immediately sets
`window.location.href = data.redirect`. That navigation evicts the response body
before Playwright's `response.json()` can read it, so the body parses as `{}`
(`ok` undefined). Assert on `response.status()` (200 — from the status line, no
body read) + `waitForURL` off `/user/verify-otp`. Both are reliable; the body is
not.

## 3. Fire the verify via the browser's OWN fetch, not route.fetch
A second connection (route.fetch / a fresh request) deadlocks the single-thread
`php -S` server while the navigated verify page still holds its connection.
Drive the real auth-ajax.js listener instead: in `page.evaluate`, set the form
`novalidate`, set `[name="code"].value`, call `form.requestSubmit()`.

OtpService in non-production issues a fixed `123456` and MAX_ATTEMPTS=5, so one
wrong guess leaves the correct code valid for the immediately following good
verify.
