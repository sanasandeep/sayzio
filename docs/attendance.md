# Attendance

## Where to find it

- Workspace: **Money → Billing & Accounting → Attendance** in rail navigation, or **Billing & Accounting → Attendance** in the classic sidebar. URL: `/user/attendance`.
- Internal Sayzio staff: **Billing & Accounts → Attendance** in the admin sidebar. URL: `/admin/attendance`.

Workspace attendance uses actual owners and active workspace members. Internal attendance uses active admin accounts, under a separate scope. A user and an admin with the same numeric ID cannot read one another's attendance records.

## Behavior

Staff can clock in, start/end breaks and clock out repeatedly during a day. Each event is timestamped by the server. The participant row is locked while events are recorded, so concurrent submissions cannot start two sessions. A single-use, two-minute challenge is bound to the actor, scope and requested action. Retrying the same completed challenge returns the original receipt.

Photo-enabled clock-in/out asks for a random pose and explicit browser camera permission; location permission is also explicit. Authorized reviewers manually compare the photo and issued pose. The app does **not** claim automated face recognition, anti-spoofing or verified liveness. Browser capture timestamps and coordinates are client evidence, not independently trusted assertions. There is no gallery uploader, fabricated evidence or offline punch queue. Employee-configured automatic punch-out is a server-generated event without photo/location evidence.

Hours use UTC intervals and the configured attendance timezone, support overnight work, and exclude breaks. Open sessions remain open until manual clock-out or their configured automatic cutoff. The scheduler closes due sessions idempotently, including sessions currently on break. Recorded hours include pending/rejected evidence; the review status remains visible for follow-up. This is an attendance report, not an automatic payroll calculation.

Staff may request leave, overtime or corrections. Another authorized reviewer must approve/reject them with a note. Corrections preserve the original receipt timestamp and write before/after times into the audit trail; they cannot reorder events. Duplicate pending/approved requests are rejected. Approved overtime and leave appear separately in CSV exports. Date and staff filters apply to the history table.

## Permissions

Workspace roles have independent `attendance.view`, `attendance.create`, `attendance.review`, `attendance.manage` and `attendance.export` capabilities in **Team → Roles**. Owners retain full access. By default active members can view and record their own attendance; only owners/admins can review others, manage policy and export. A role override can revoke or delegate each capability. Reviewers cannot approve their own evidence or requests.

Employee attendance settings include a scoped display name/code, designation, enabled state, allowed IP addresses/CIDRs and local automatic punch-out time. Changing attendance settings does not rename or delete the person’s global account. Existing Team invitations/member removal and Admin Staff create/edit/delete screens remain the account-management source of truth; authorized links open those screens from Employees.

Every active internal admin can view/record their own attendance. Super admins can manage/review/export. Other internal roles need explicit **Staff attendance** permissions (`attendance.review`, `attendance.manage`, `attendance.export`) in **Admin → Roles & Permissions**. Customer identity privileges are not needed, because this screen contains only internal staff data.

## S3 evidence

The feature reuses the existing `AWS_*` connection and `AWS_BUCKET` used for user files. No new bucket is required. `ATTENDANCE_EVIDENCE_BUCKET` is an optional override.

**Photo bytes are authenticated-encrypted with Laravel's application encrypter before upload**, stored as random `.enc` objects under `attendance-encrypted/<scope>/`. Plaintext photos are never uploaded to the public media/CDN path, and the UI never emits an S3/CDN evidence URL. The authenticated attendance endpoint verifies the active scope and owner/reviewer permission before decrypting the object and returning an image with `Cache-Control: private, no-store`.

Keep the existing `APP_KEY` and its backups. For key rotation, retain previous encryption keys with Laravel's `APP_PREVIOUS_KEYS` configuration until retained evidence has expired. Decryption failures fail closed. Existing user-file upload behavior is unchanged.

When S3 is configured, photo capture is enabled and required by default. If S3 is absent, standard punches work; policy managers can require photos once storage is connected. Policy controls attendance timezone, required photos/location, optional work-area coordinates/radius and evidence retention (7–365 days; default 90). Geofencing rejects out-of-bound or insufficiently accurate readings.

The registered daily `attendance:prune-evidence` job deletes expired photos and coordinates; it keeps punch times, decisions and request history. Storage failures prevent a punch from being committed or a retention record from being cleared. Check the deployment's normal scheduler is running.

## Deployment and validation

Run the normal migrations, cache rebuild and deployment process. No new service is required. The change has isolated PostgreSQL integration coverage for membership/role isolation, replay/expiry, encrypted photo storage and gated retrieval, reviews, geofencing, overnight calculation, corrections, admin staff scope and retention. Desktop/narrow layouts and challenge-dialog controls are checked in a browser with generated fixtures. A physical camera/GPS and live S3 upload must still be exercised on a test account after deployment.

## CRM feature checklist

| Requested capability | Implementation |
|---|---|
| Dashboard, notifications, graph reports | Live working/on-break/pending counters, dated notices and 14-day work-hour graph. Zero-data states stay explicit. |
| Employee create/edit/delete | Existing Team/Staff account workflows linked from Employees; scoped attendance enrolment can be configured or disabled without losing history. |
| IP restriction | Exact IPv4/IPv6 and CIDR rules enforced server-side on challenge and punch submission. Applies to attendance, not unrelated Sayzio services. |
| Auto punch-out | Per-employee local cutoff, overnight rollover and registered every-minute `attendance:auto-punch-out` job. Events explicitly identify their system origin. |
| Login as user | Existing platform `users.impersonate` capability only, with an audit record. Workspace managers receive no account impersonation power. Impersonated sessions cannot submit attendance. |
| Designations | Scoped create/edit/delete, multiple staff selection and role-group bulk assignment. |
| Holidays | Create/update date ranges with weekday selection; edit/delete individual dates; working-day leave calculations exclude holidays/weekends. |
| Leave | Paid/unpaid types, annual allowances, approved/pending/remaining balances, reviewed requests and over-allocation checks. Cross-year requests must be split. |
| Notices | Attendance managers create, edit, schedule and delete plain-text notices; staff see only currently published notices. |
| Individual/summary reports | Staff/date filters, daily or employee-summary CSV, actual XLSX and rendered PDF exports. Text cells stay text; CSV formula prefixes are escaped. |
| Bulk accept/reject | Atomic selected evidence/request review, notes per decision and no self-approval. |
| Bulk modification | Employee field updates and selected punch-time shifts (up to 24h), with original receipt/evidence retained. Invalid timelines and self-modification are rejected atomically. |
| QR clock-in | Manager-issued 90-second rotating workplace QR. Self-hosted jsQR camera decoder plus paste/native-camera fallback. Scan only opens confirmation; normal identity/IP/selfie/location checks still apply. One redemption per staff/code. |

Report exports are limited to 93 days and 50,000 estimated daily rows; PDFs are limited to 3,000 rows. The existing S3 encryption/storage approach is preserved. Leave types with requests cannot be deleted, and disabling attendance records a system clock-out when needed. Retention keeps attendance/audit history while removing expired photos/coordinates. No wage, tax or statutory payroll calculations are introduced.

Biometric device ingestion is **not connected**. Implementing it requires the vendor/model, transport/SDK, credentials and explicit mapping to staff accounts. The UI displays this state rather than importing invented punches.


## Shifts and employee self-service

Attendance and Leave Management use the Work & Team navigation for workspaces and Billing & Accounts for internal staff. Managers can create and archive shift templates, assign work/off rosters in bulk, and configure roster-required check-in, early check-in windows and automatic shift-end punch-out. Overnight assignments are supported. Assignments snapshot times and grace periods; assignments referenced by attendance cannot be rewritten or cancelled. Planned breaks are informational; actual worked time subtracts recorded breaks.

Employee Self-Service shows personal attendance, dated rosters, timing, leave balances, and request history. Employees can submit leave, attendance correction, overtime, training and general requests, and withdraw pending requests. Training requests capture a title, provider and course URL. Requests use the existing scoped approval permissions and prohibit self-approval.

Leave types can allow half-day/whole-day encashment requests with an annual limit. Leave and encashment share the same balance; pending requests reserve days and withdrawal releases reservations. Approval consumes days. Managers record a separate externally completed settlement with currency, amount and payment reference; this workflow does not transfer funds or calculate payroll. Internal staff use the same workflows in their separate attendance scope.


## Management navigation

Attendance, Leave Management and Team Management are separate server-rendered areas, rather than anchors into one page. Internal admins see Staff Management. Each area renders only its selected URL-backed sub-tab. Attendance contains overview, records, employee requests, personal workspace, QR, reports, policy and audit history. Leave Management contains requests/approvals, personal balances, encashments and manager-only leave types. Team/Staff Management contains people, rosters, designations/holidays, notices and roles/permissions.

Workspace role links lead to the workspace team role editor; internal staff links lead to the admin role editor. Permissions and records remain isolated by their existing workspace/admin guards. Manager-only tabs are checked server-side, and existing mutation permissions remain enforced. Buttons use the shared btn-primary/btn-ghost theme classes.


## Workspace people configuration

Workspace employment settings now live at `/user/team/people` and are linked from the existing workspace Team page. `/user/attendance/team` redirects there, preserving the selected tab. Internal staff remain in their admin scope. Sidebar Attendance/Leave navigation is sufficient; duplicate primary area buttons were removed. These pages override scoped button tokens with a light semantic-primary surface, retaining shared button sizes.

Owners (and roles explicitly granted attendance.manage) configure fixed, flexible-daily or weekly working arrangements, daily/weekly hours, working weekdays, timezone and check-in windows. Fixed mode derives dated roster snapshots and enforces their window; explicit assignments/off days take precedence and holidays are excluded from automatically derived shifts. Flexible and weekly targets are informational, not payroll deductions or maximum-hour enforcement. Weekly targets use Monday–Sunday in the workspace timezone. Employment start dates can be assigned from employment settings.

Built-in templates include Annual, Earned, Sick, Casual and Unpaid leave. Templates are selectable starter policies, not automatic grants; owners can add and edit custom types. Upfront annual allowance remains compatible with existing types. Interval accrual supports a decimal credit every N calendar days, an opening balance, policy start and annual cap. Only completed intervals count; balances are computed as of today and reset January 1, starting from the later of the year start, employment/join date and policy start. Leave and encashment share earned balances and pending reservations. There is no carry-over. Editing policy recalculates entitlement; recorded requests remain unchanged.

## AI people assistance

The owner must opt in through working policy. AI Staff can select the People data connection; AI Minds can add the live People feature source. Coach and Zio tools can read the same authenticated, permission-scoped context. Mind snapshots use the requesting user, never a shared Mind owner's private people data; anonymous and background requests receive none. Snapshots include policies, personal balances and authorized aggregate counts, excluding evidence, location and request reasons.

AI assistance in Team can prepare personal leave or manager roster drafts using an owned active AI Staff member and its connected Minds. Zio exposes prepare/read tools but no approve tool. Preparation saves only a pending draft. Human approval locks the draft and rechecks actor, workspace, AI opt-in, AI Staff read/draft mode, resource IDs, balances and current permissions through the existing leave/roster controllers. A leave draft becomes a pending leave request, still requiring a separate reviewer. Repeat approvals are rejected; failed applies roll back and retain the pending draft. AI provider billing and Staff spending limits continue to apply.
