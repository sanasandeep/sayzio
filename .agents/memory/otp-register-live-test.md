---
name: OTP registration live-testing gotchas
description: How to drive the guest register->OTP verify flow via curl/tinker when SMTP creds are invalid in this env
---

The dev environment's SMTP credentials are invalid (auth fails against the real
mail server), so `POST /user/register` is slow (~30-40s) while it tries and
fails to send the OTP email before falling through — plan for a long curl
`--max-time` (60s+) rather than assuming the endpoint hangs forever or is broken.

The OTP itself is fetched from `DB::table('otps')` (not an Eloquent model —
there's no `OtpCode` model), keyed by `identifier` (email/phone). In this
dev/seed environment it resolves to a fixed test code `123456`.

The verify step is `POST /user/verify-otp` with field name **`code`**, not
`otp` — posting `otp=...` silently redirects back to the same page with no
error surfaced in the response body.

To confirm a post-login/post-register redirect target (e.g. `url.intended`
stashed in session) end-to-end without guessing at decrypting session files,
just complete the real request chain (register -> fetch OTP from DB -> verify)
and read the final `Location` header — far simpler than manually decrypting
Laravel's encrypted session cookie/file.
