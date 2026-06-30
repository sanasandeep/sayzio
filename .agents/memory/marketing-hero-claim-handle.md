---
name: Marketing hero "claim your link" → register handle handoff
description: How a handle typed on the Laravel marketing home is carried through signup and reserved as the new user's @handle.
---

The homepage hero (Laravel `artifacts/1inme`) has a "claim your link" control
that carries a desired handle into the existing register flow. Wiring spans
several lockstep surfaces — change them together or the handle silently drops:

- **Hero form** (`home/partials/hero.blade.php`) — a scoped-CSS glass pill
  (`.zio-claim*`, no new Tailwind utilities so it renders in build-less isolated
  envs). Submit calls `window.zioClaimSubmit`, which fires
  `trackMarketingEvent('landing_home_cta','hero_claim')` and dispatches the
  SAME `open-auth` CustomEvent the other hero CTAs use, now with
  `detail.handle`. Brand prefix is `PlatformHosts::PLATFORM_DOMAINS[0]`.
- **Header Alpine** (`public/partials/header.blade.php`) — the `$useModal`
  x-data holds `authHandle`; the `@open-auth.window` listener reads
  `$event.detail.handle`. The auth modal lives INSIDE this x-data scope.
- **Auth modal** (`public/partials/auth-modal.blade.php`) register form — hidden
  `<input name="desired_handle" :value="authHandle">` + an x-show "Claiming @x"
  banner.
- **Standalone register page** carries it via `?handle=` query →
  `AuthController::showRegister` passes `$prefilledHandle` → hidden field.
- **`AuthController::register`** calls `applyClaimedHandle()` AFTER `User::create`.
- **OTP/WhatsApp sign-UP path** (`Api\OtpController::register`, mobile) honors a
  `desired_handle` after create. NOTE the *web* WhatsApp/OTP variant
  (`AuthController::sendOtp`/`verifyOtp`) is LOGIN-ONLY — it never creates an
  account for an unknown identifier, so it can't drop a claimed handle; the web
  claim only ever rides the register form.

**Shared validation:** all sign-up surfaces now apply the handle through
`App\Modules\Common\Support\ClaimedHandle::apply(User,?string)` (the canonical
rules below live there) — keep new claim surfaces on that helper so they can't
drift.

**Why graceful, not validated-at-form:** `applyClaimedHandle` re-validates with
the canonical claim rules (mirror `CreatorProfileController::claimHandle`:
`min:3,max:30, regex /^[a-z0-9_]+$/i, Rule::unique('users','handle'), NotBannedName`)
and on ANY failure silently skips, so signup never dead-ends on a taken/banned
handle — the user just picks one later. Empty handle opens registration normally.

**OTP-as-signup paths are out of scope for the claimed handle (by design):**
the web bare OTP path (`user.otp.send`→`user.otp.verify`) never CREATES an
account for an unknown identifier (no code issued; verify returns "User not
found"), and the mobile/API OTP signup (`POST /api/v1/auth/otp/register`) has
no handle field and ignores one if sent. Pinned by
`ClaimedHandleOtpSignupTest`.

**Claimed-handle feature-test gotcha:** `register()` lowercases the stored
email, but `users.email` is plain varchar (case-sensitive). Generate test
emails with a LOWERCASE local part — `Str::random` emits uppercase, so a
`User::where('email', $payload['email'])` lookup misses the new row and every
"account must exist" assertion fails on real Postgres (masked on stale helium
by the 25P02 cascade).

**Verifying in isolated env:** the marketing home 500s on pre-existing schema
drift (HomeController queries `plans.is_internal`, `site_assistant_page_hints`,
etc. missing until the migration backlog drains) — unrelated to hero edits.
Render the hero in isolation instead: `view('home.partials.hero')->render()` in
a bootstrap script (it's self-contained — only needs `PlatformHosts`, no DB).
The auth-modal can't render standalone (needs `$errors` from
`ShareErrorsFromSession` middleware); verify it by `compileString` + `php -l`.
