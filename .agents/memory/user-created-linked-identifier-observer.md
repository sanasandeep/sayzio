---
name: User::created auto-mirrors email/mobile into linked_identifiers
description: Why seeding a fixture user with a mobile must NOT manually insert its own primary phone linked_identifier row.
---

# User::created mirrors email/mobile into linked_identifiers

When a `User` is first created (the `booted()::created` observer), 1inme automatically
mirrors the `email` and `mobile` columns into `linked_identifiers`:
- an `email` row, `verified_at` set, **`is_primary = true`** (the account's single primary),
- a `phone` row (only if `mobile` is set), `verified_at = now()`, **`is_primary = false`**
  (email already took primary), stored under `LinkedIdentifier::normalize('phone', mobile)`.

**Rule:** a test/seed fixture that sets `$u->mobile` already gets a verified phone
linked-identifier for free. Do NOT `updateOrInsert`/insert your own phone row with
`is_primary = true`.

**Why:** the table has a `linked_identifiers_one_primary_per_user` unique constraint
(one primary per user). A manual insert matched on the RAW `+…` value misses the
observer's NORMALIZED row, so it INSERTs a *second* primary → 23505 unique violation.
In a Playwright spec this throws inside `beforeAll`, surfacing as a `(0ms)` test
failure whose real cause is hidden (execFileSync only shows "Command failed").

**How to apply:** to guarantee a fixture's mobile is verified, idempotently UPDATE the
observer-created row instead of inserting — `DB::table('linked_identifiers')
->where('user_id',$u->id)->where('kind','phone')->update(['verified_at'=>now()])` —
never touching `is_primary`. `resolveUserByIdentifier` matches on verified + normalized
value regardless of primary, so this is enough for OTP mobile-login fixtures.
