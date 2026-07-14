---
name: OTP e2e on shared RDS — prefix prunes and the fixed dev code
description: Two traps for Browser OTP specs: parallel task envs deleting shared-prefix fixtures mid-flight, and the non-production fixed '123456' OTP code making "wrong code" assertions coincidentally pass/fail.
---

## 1. Age-gate every prefix-wide prune

Parallel task environments run the same/sibling OTP Browser specs against the shared RDS at the same time. A `beforeAll` that deletes `users`/`otps` rows by shared email/mobile PREFIX will delete a concurrent twin's freshly seeded fixtures mid-flight (symptom: "no OTP issued, otps_any=[]", users vanishing minutes after creation).

**How to apply:** prune only stale rows — `->where('created_at', '<', now()->subHours(2))` — plus exact-match deletes for THIS run's unique identifiers. afterAll cleanup stays run-scoped. Also beware sibling specs toggling GLOBAL AppSettings (e.g. allowed dialling codes) mid-run; retries usually absorb it.

## 2. Non-production OtpService issues the SAME code ('123456') to everyone

Any assertion of the form "old/foreign code must be rejected" silently breaks: the "stale" code equals the victim's fresh code, and verify happily logs in (spec sees a dashboard navigation instead of the invalid-code error).

**How to apply:** before submitting the supposedly-invalid code, burn (mark `used`) the outstanding otps rows for ALL identifiers involved — not just the abandoned one — then re-issue via the verify screen's Resend button (`form[action$="/user/resend-otp"]`) for the legitimate login step.
