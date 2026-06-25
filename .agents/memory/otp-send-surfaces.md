---
name: OTP code-send surfaces
description: Every place 1INME issues a one-time code, so cross-cutting OTP changes hit them all.
---

# OTP code-send surfaces

Any cross-cutting change to OTP behaviour (revealing it, throttling, message
copy, new channel) must be threaded through ALL of these — they do NOT share a
single send funnel:

- Web (1inme Laravel, session-flash based):
  - `User/Controllers/AuthController` → `register()`, `sendOtp()`, `resendOtp()` (login send + resend)
  - `User/Controllers/AccountMergeController::challenge()` (merge proof)
  - `User/Controllers/LinkedIdentifierController::start()` (add email/phone)
  - `Common/Controllers/ViewerAuthController::sendOtp()` (public viewer login — JSON, Alpine modal)
- API (Sanctum, `ApiResponses::ok()`/`created()` → `{data:{...}}`):
  - `Api/Controllers/OtpController` → `send()` (resend reuses it) + `register()`
  - `Api/Controllers/AccountMergeController::challenge()`
- Mobile (Expo, `apiFetch` inline types, no codegen for these):
  - `contexts/AuthContext.tsx::sendOtp` (login + resend on `app/(auth)/verify.tsx`)
  - `app/account-merge.tsx` (`lib/api/accountMerge.ts::mergeChallenge`)

**Account-enumeration guard:** the no-user / account-hiding branches generate
NO code. Any "reveal the code" feature must pass `null` there (helper
`AuthMethods::demoRevealMessage(?string $code)` returns null for null code) so a
hidden account never surfaces a code.

**Why:** the literal "cover all code-sending flows" requirement is easy to
half-do; there is no central interceptor, so each controller/screen is its own
edit.
