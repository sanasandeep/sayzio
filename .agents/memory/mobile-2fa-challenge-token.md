---
name: Mobile 2FA challenge token
description: Stateless TOTP step for mobile API logins — challenge_token contract and lockstep surfaces
---

TOTP-enrolled accounts no longer dead-end on mobile: every API login path (OTP verify, password, social — both `SocialAuthController::exchange` AND the native-Google catch in mobile `(auth)/index.tsx`, plus `oauth-callback.tsx`) returns 403 `totp_required` with `details.challenge_token`, a Crypt(APP_KEY)-encrypted `{p:'2fa_login',uid,exp +10min}` minted by `TwoFactorChallengeController`. `POST /api/v1/auth/2fa/challenge/verify` (and `/auth/2fa/backup-codes/verify`, same action) accepts a TOTP code or single-use recovery code and issues the Sanctum token.

**Why:** stateless token avoids server-side pending-login state; recovery-code consumption mirrors web `TwoFactorController::verifyChallenge` so single-use semantics can't drift.

**How to apply:**
- Adding a NEW login path? It must catch `totp_required` and route to the verify screen with `challenge_token` — the native-Google catch was the one everyone forgets (it bypasses oauth-callback).
- Rate limiting: the challenge endpoints use the dedicated `twofactor-verify` limiter (keyed sha1(challenge_token)+IP). Do NOT reuse `otp-verify` — it keys on an `identifier` input this endpoint doesn't have, collapsing to one shared global bucket.
- Errors: wrong code → 400 `invalid_2fa_code`; bad/expired token → 410 `challenge_expired`; recovery codes are single-use.
