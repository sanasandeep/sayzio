---
name: WhatsApp/mobile-only signup accounts
description: Constraints hit by email-less (mobile-only) OTP signup and its e2e oracle
---
- `users.email` was NOT NULL until a July 2026 additive migration relaxed it; any env behind on migrations makes WhatsApp signup (email=null) die with 23502. Signup mints the user at SEND time in `AuthController::sendOtp` (intent=signup).
- **Oracle trap:** counting "users created" by summing `users.mobile` matches + `linked_identifiers` phone matches double-counts — the `User::created` observer mirrors mobile into linked_identifiers, so ONE account = 2 rows. Count DISTINCT user ids across both paths.
- Slug-style uniqueness helpers that probe `exists(base-N)` one query at a time are O(collisions) round-trips per signup; every account creates a same-named default record ("My Tasks" board), so on a busy shared DB one signup issued hundreds of sequential queries (minutes over remote RDS). Fetch all colliding slugs in ONE query (`slug = base OR LIKE base-%`) and compute the suffix locally. **How to apply:** audit any `while exists()` slug loop on rows minted per-user. The single-query max-suffix lookup is now centralized in `App\Support\UniqueSuffix::resolve()` — all deterministic base-N slug helpers delegate to it; only random-token do/while loops (negligible collision odds) remain, which is fine.
