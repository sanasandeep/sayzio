---
name: AI gate page vs plan allowlists
description: How the shared user.ai.disabled gate page interacts with per-feature plan allowlists, and the debugging path for "enable button does nothing" in prod
---

**Rule:** Some AI surfaces (Voice Assistant) have THREE gates: master `ai.enabled`, a per-feature toggle (`ai.voice.enabled`), and a per-plan allowlist (`ai.voice.enabled_plans`, empty = all plans). An allowlist containing a nonexistent plan slug silently blocks every user. The shared gate view's admin branch must reflect the *actual* blocking gate — the controller passes `planGate` when the allowlist is the blocker, and the one-click enable POST (`user.ai.enable`) accepts `feature=voice` (flip toggle) or `feature=voice-plans` (clear allowlist).

**Why:** July 2026 incident: prod had `ai.voice.enabled_plans=["voice-only-tier"]` (no such plan), engine+toggle already on. Admin saw "AI features are currently turned off / Enable AI now"; the button flipped already-on switches, showing a success banner while nothing changed.

**How to apply:** When an admin reports an enable/settings button "works but reverts on reload", first read the real prod values before touching code. The deployed app reads AWS RDS (host from `PROD_DATABASE_URL`, other creds shared with dev `.env`) — the Replit production replica (`executeSql environment:"production"`) is a different, mostly empty DB and proves nothing here. Also check whether a plan allowlist references a plan slug that no longer exists.
