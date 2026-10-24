# Attendance

## Where to find it

- Workspace: **Home → Attendance** in rail navigation, or **Workspace & Tools → Attendance** in the classic sidebar. URL: `/user/attendance`.
- Internal Sayzio staff: **Attendance** in the admin sidebar. URL: `/admin/attendance`.

Workspace attendance uses actual owners and active workspace members. Internal attendance uses active admin accounts, under a separate scope. A user and an admin with the same numeric ID cannot read one another's attendance records.

## Behavior

Staff can clock in, start/end breaks and clock out repeatedly during a day. Each event is timestamped by the server. The participant row is locked while events are recorded, so concurrent submissions cannot start two sessions. A single-use, two-minute challenge is bound to the actor, scope and requested action. Retrying the same completed challenge returns the original receipt.

Photo-enabled clock-in/out asks for a random pose and explicit browser camera permission; location permission is also explicit. Authorized reviewers manually compare the photo and issued pose. The app does **not** claim automated face recognition, anti-spoofing or verified liveness. Browser capture timestamps and coordinates are client evidence, not independently trusted assertions. No gallery uploader, fabricated evidence, offline punch queue or automatic check-out is included.

Hours use UTC intervals and the configured attendance timezone, support overnight work, and exclude breaks. Open sessions remain open until clock-out. Recorded hours include pending/rejected evidence; the review status remains visible for follow-up. This is an attendance report, not an automatic payroll calculation.

Staff may request leave, overtime or corrections. Another authorized reviewer must approve/reject them with a note. Corrections preserve the original receipt timestamp and write before/after times into the audit trail; they cannot reorder events. Duplicate pending/approved requests are rejected. Approved overtime and leave appear separately in CSV exports. Date and staff filters apply to the history table.

## Permissions

Workspace roles have independent `attendance.view`, `attendance.create`, `attendance.review`, `attendance.manage` and `attendance.export` capabilities in **Team → Roles**. Owners retain full access. By default active members can view and record their own attendance; only owners/admins can review others, manage policy and export. A role override can revoke or delegate each capability. Reviewers cannot approve their own evidence or requests.

Every active internal admin can view/record their own attendance. Super admins can manage/review/export. Other internal roles need explicit **Staff attendance** permissions (`attendance.review`, `attendance.manage`, `attendance.export`) in **Admin → Roles & Permissions**. Customer identity privileges are not needed, because this screen contains only internal staff data.

## S3 evidence

The feature reuses the existing `AWS_*` connection and `AWS_BUCKET` used for user files. No new bucket is required. `ATTENDANCE_EVIDENCE_BUCKET` is an optional override.

**Photo bytes are authenticated-encrypted with Laravel's application encrypter before upload**, stored as random `.enc` objects under `attendance-encrypted/<scope>/`. Plaintext photos are never uploaded to the public media/CDN path, and the UI never emits an S3/CDN evidence URL. The authenticated attendance endpoint verifies the active scope and owner/reviewer permission before decrypting the object and returning an image with `Cache-Control: private, no-store`.

Keep the existing `APP_KEY` and its backups. For key rotation, retain previous encryption keys with Laravel's `APP_PREVIOUS_KEYS` configuration until retained evidence has expired. Decryption failures fail closed. Existing user-file upload behavior is unchanged.

When S3 is configured, photo capture is enabled and required by default. If S3 is absent, standard punches work; policy managers can require photos once storage is connected. Policy controls attendance timezone, required photos/location, optional work-area coordinates/radius and evidence retention (7–365 days; default 90). Geofencing rejects out-of-bound or insufficiently accurate readings.

The registered daily `attendance:prune-evidence` job deletes expired photos and coordinates; it keeps punch times, decisions and request history. Storage failures prevent a punch from being committed or a retention record from being cleared. Check the deployment's normal scheduler is running.

## Deployment and validation

Run the normal migrations, cache rebuild and deployment process. No new service is required. The change has isolated PostgreSQL integration coverage for membership/role isolation, replay/expiry, encrypted photo storage and gated retrieval, reviews, geofencing, overnight calculation, corrections, admin staff scope and retention. Desktop/narrow layouts and challenge-dialog controls are checked in a browser with generated fixtures. A physical camera/GPS and live S3 upload must still be exercised on a test account after deployment.

Biometric device ingestion is **not connected**. Implementing it requires the vendor/model, transport/SDK, credentials and explicit mapping to staff accounts. The UI displays this state rather than importing invented punches.
