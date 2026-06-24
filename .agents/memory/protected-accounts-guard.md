---
name: Protected accounts guard
description: How "never delete/suspend" account protection is enforced in the 1inme Laravel admin.
---

Protection is a single email-keyed list: table `protected_accounts` + model
`App\Modules\Admin\Models\ProtectedAccount`. The canonical guard is the static
`ProtectedAccount::isProtected($user|$admin|$email)` (case-insensitive on email).

**Why email-keyed:** the web `User` pool and back-office `Admin` pool share no FK
and are bridged only by email, so one protected entry covers both surfaces at once.

**How to apply:** every NEW delete/suspend path (user mgmt, staff mgmt, any future
bulk/destructive action) must call `ProtectedAccount::isProtected(...)` server-side
and bail with a clear message — UI hiding alone is not enough (defense in depth).
Blocked attempts are logged via `AdminActionLogger` (`DELETE_BLOCKED` /
`SUSPEND_BLOCKED`); list edits via `PROTECTED_ADDED` / `PROTECTED_REMOVED`. Add any
new action constant to BOTH `ActivityLogController::ACTIONS` and
`AdminActionAudit::actionLabel()` or it shows raw in the activity log.

Hard-locked seeds (`locked=true`: superadmin + demo) can never be removed — the
seed lives in the create-table migration via idempotent `updateOrInsert`. Only a
superadmin (`Admin::isSuperAdmin()`) may edit the list; staff with `users.view`
can read it (store/destroy do an inline `requireSuperAdmin()` check, mirroring
`DemoContentController`).

Note: "suspend" here means blocking the account/sign-in (UserManagementController
suspend + status→banned/suspended/inactive, StaffController status→inactive +
delete). The 18+ adult-flag "suspend" in `AdultModerationController` is a different
concept (toggles the public NSFW tag, not the account) and is intentionally NOT
guarded.
