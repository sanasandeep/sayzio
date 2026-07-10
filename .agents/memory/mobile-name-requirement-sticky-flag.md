---
name: Mobile mandatory-name requirement is a sticky client flag
description: Why the mobile "needs display name" prompt uses a persisted client flag instead of reading it from the server
---

# Mobile mandatory-name requirement is a sticky client flag

The `needs_name` signal (a brand-new account still owes a display name) is
returned by the API **only** on the first response after auto-creation
(OTP verify / social login). It rides in the user object and is persisted
incidentally with the stored user.

**Key constraint:** `GET /auth/me` (`AuthController::me` →
`UserResource::toArray(..., self:true)`) does NOT include `needs_name`. So a
plain `refresh()` returns a user without the field. Any code that derived the
requirement from `user.needs_name` would therefore lose it after the first
refresh.

**Why not just check for an empty name:** the OTP controller seeds a
placeholder name (`Str::before(identifier,'@') ?: 'Creator'`) on auto-create,
so an empty/blank `display_name` is NOT a reliable "needs name" signal.

**How to apply:** On mobile the requirement is a dedicated sticky flag
(`getNeedsName`/`setNeedsName` in `lib/secure.ts`, surfaced as
`isNameRequired` on `useAuth()`), set in `applySession` from
`user.needs_name`, and cleared ONLY when the name is actually saved
(`clearNameRequirement`) or on `signOut` — never resurrected from the server.
Decouple `isNameRequired` from the cached user so `refresh()` can't clobber it.
The web side is guarded by `RequiresNameMiddleware` (web guard), which does NOT
cover the mobile Sanctum/token path — that's why the mobile resilience exists.
