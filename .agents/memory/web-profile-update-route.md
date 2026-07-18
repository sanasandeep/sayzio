---
name: Web profile-update route vs redirect trap
description: Which URL actually hits ProfileController::update, and why PUT /user/profile silently no-ops in tests.
---

The real web profile-save route is `route('user.profile.update')` =
**PUT `/user/settings/profile`** (registered under the `settings/profile`
prefix group). `ProfileController::update` only runs there.

**Trap:** `/user/profile` is a legacy `Route::redirect('profile',
'user/settings/profile')`. Laravel's `Route::redirect` registers with
`any()`, so it answers **every** verb — a PUT to `/user/profile` returns a
302 to `user/settings/profile` and NEVER reaches the controller (no
success/error flash, no validation errors).

**Why this bites tests:** a feature test that does
`$this->put('/user/profile', ...)` passes *vacuously* — the request is
swallowed by the redirect, so "nothing changed" assertions (e.g. "field
stayed the same") pass without exercising any code. Symptom seen while
debugging: `status=302 loc=user/settings/profile`, name unchanged, no
session errors.

**How to apply:** always target `route('user.profile.update')` (or the
literal `/user/settings/profile`) in web profile tests, never
`/user/profile`. Any pre-existing spec still using `/user/profile` PUT is
suspect.
