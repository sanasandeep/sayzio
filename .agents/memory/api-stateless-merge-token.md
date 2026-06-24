---
name: Stateless account-merge over Sanctum API
description: How the mobile/API account-merge flow carries the secondary-account binding without a session.
---

The web `/user/merge` flow (User module AccountMergeController) stashes the
proven secondary account id in **session keys** between challenge → preview →
confirm. The Sanctum Bearer API has no session, so the API mirror (Api module
AccountMergeController, routes `account/merge/{challenge,verify,preview,confirm}`)
carries that binding in a short-lived **APP_KEY-encrypted "merge token"**
(`Crypt::encryptString(json_encode(['p'=>primaryId,'s'=>secondaryId,'exp'=>...]))`,
15-min TTL).

**Rule:** every step that consumes the token must re-check `token.p === auth user id`
(403 on mismatch) and re-load the secondary fresh (404 if gone). Do NOT trust the
token's secondary blindly — after a successful merge the secondary is deleted, so a
replayed confirm naturally 404s (no double-merge guard needed).

**Why:** a leaked/replayed token must not let another signed-in account drive a merge,
and the stateless flow has no server-side "this challenge belongs to user X" record
other than what's inside the token.

**How to apply:** reuse the shared `AccountMergeService::preview()/merge()` for the
actual data move (same code the web flow uses — already battle-tested); the API
controller is only a thin stateless wrapper (OTP challenge/verify via OtpService with
guard `web`/purpose `login`, token round-trip, and array-shaping the preview). Admin
(role-holding) and self-merge are refused at both verify and resolveToken.
