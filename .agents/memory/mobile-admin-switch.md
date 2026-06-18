---
name: Mobile admin dashboard switch & impersonation
description: How mobile achieves admin↔user dashboard switching, role assignment, and impersonation parity with the web back-office.
---

In a token world there is no separate admin "login": a signed-in mobile user
holds one Sanctum bearer token for a `web` User. The web back-office runs on
the `admin` guard. The two pools are bridged by **email** via
`User::adminAccount()` (returns the active back-office `Admin` row whose email
matches). That linked Admin record — and its admin-guard role permissions — is
the operator's authority for every `/api/v1/admin/*` action.

**Why:** mirrors the web flow exactly without a second auth handshake, so
"switch to admin dashboard" on mobile is pure navigation (to `app/admin/*`),
not a re-login. The same token already authorizes the admin endpoints.

**How to apply:**
- Server: `AdminAccessController` resolves `activeAdmin()` = `$request->user()->adminAccount()` with status 'active', then gates each endpoint on that admin's `hasPermission(...)` (users.view/edit/impersonate, staff.create/delete). 403 otherwise.
- Impersonation = mint a fresh `mobile`-kind token for the target (`SessionTokenIssuer::issue`, NO login alert); the app stashes the operator's session in secure storage (`getImpersonator`/`setImpersonator`) and swaps it back on "stop". Stop best-effort revokes the impersonation token first.
- Mobile gates UI on `GET /admin/context` (`has_admin_access` + per-action `can` map); the impersonate button keys off `can.impersonate`.
