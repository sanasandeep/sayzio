---
name: Master override password
description: Admin-set master password that logs into any account across web/API/admin; the timing + lockstep rules for changing it.
---

# Master override password

A super-admin-configurable single master password (1inme Laravel) that, when
enabled, signs in to ANY account by entering that account's email/identifier +
the master password — without touching the account's real password.

## Where it lives (4 lockstep surfaces)
- `MasterPasswordSettings` service (app/Services/Integrations/) — the only
  read/write gate. Hash stored encrypted-at-rest in `app_settings`
  (`master_password.hash` + `master_password.enabled`); env fallback
  `MASTER_OVERRIDE_PASSWORD_HASH` (pre-hashed). Off by default.
- Three login controllers must stay in lockstep: User web `loginWithPassword`,
  Api `login`, Admin `login`. Each: after the real-password check fails, fall
  back to the master password for a *resolved* account.
- Audit: every master login writes a `master_password_logins` row
  (`MasterPasswordLogin::record($guard, $target, $request)`); reviewed on the
  admin Master Password page.

## Non-obvious rules
- **Timing**: `MasterPasswordSettings::matches()` ALWAYS runs exactly one
  `Hash::check` (against a dummy when unset). Call it UNCONDITIONALLY on every
  attempt (not gated behind `$user && !$passwordOk`) so known-email-wrong-pw and
  unknown-email do the same hashing work. Gating it reintroduces an
  account-existence timing leak.
  **Why:** task requires enabling/disabling the override never leak which
  accounts exist; the web/api dummy-hash compare must stay symmetric.
- On a master login: never `Hash::needsRehash`/rehash (the candidate is the
  master password, not the user's) and bypass the TOTP 2FA challenge (operator
  override). Suspension/inactive checks still apply.
- Super-admin gate is enforced inside `MasterPasswordController`
  (`Admin::isSuperAdmin()`, role slug `super-admin`), not via middleware —
  mirrors ProtectedAccountController. Route still carries `settings.manage`.
- Login-event channel is suffixed `_master_password` (web/api) so the user's
  Recent-logins history distinguishes it.
