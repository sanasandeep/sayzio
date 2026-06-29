---
name: Banned-name enforcement on @handle surfaces
description: All the places a profile @handle is chosen must run NotBannedName, not just link aliases.
---

A profile `@handle` is a reserved public slug (`/@handle`) exactly like a link
alias, so the admin banned-names list must be enforced on every handle-setting
surface — not just the Create Link alias path.

The handle-setting surfaces (keep `NotBannedName` on all of them in lockstep):
- API registration — `Api\Controllers\AuthController::register`
- API profile update — `Api\Controllers\ProfileController::update`
- web profile editor update — `User\Controllers\ProfileController::update`
- web handle claim — `User\Controllers\CreatorProfileController::claimHandle`

**Why:** the web profile editor and claim endpoints already ran the rule, but the
two API surfaces did not, letting a non-privileged user register or PATCH their way
to a reserved handle (a real reservation leak). Web registration does NOT take a
handle (set later), and the OTP/social account-creation paths don't accept a handle
either — so only the four surfaces above matter.

**How to apply:** `NotBannedName` reads `Auth::user()` for the `user.banned_names.bypass`
bypass; on `auth:sanctum` routes the Authenticate middleware promotes the sanctum
user to the default guard, so the bypass resolves there too. At registration there is
no authenticated user, so no bypass applies (correct — a fresh account can't bypass).
