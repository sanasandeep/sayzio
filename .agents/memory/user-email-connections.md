---
name: User-owned reusable SMTP connections
description: How per-user email connections (email-kind IntegrationConfig) route sends and where each surface hooks in.
---

# User-owned reusable SMTP connections

All per-user "send via my SMTP" traffic goes through `EmailConnectionMailer` (User/Services): it resolves an email-kind IntegrationConfig **withoutGlobalScope('workspace')** (public-visitor sends have no workspace bound; connections are account-level), builds a runtime mailer, stamps `email_logs.meta.transport = "connection:{id}"`, and purges `mail.mailers` afterwards.

**Rule:** a missing/inactive/half-configured/unwired-provider connection **falls back to the platform mailer** (logged), never drops the send. **Why:** silent skips lost form/broadcast mail; only smtp+sendgrid are wired.

**How to apply:**
- New surfaces should call `EmailConnectionMailer::send/emailOpts` — don't hand-build EsmtpTransport (subscriber legacy inline SMTP path is honored only when no connection is picked; settings key `settings['subscription']['email_connection_id']`).
- Billing companies don't reference the connection at send time — picking `smtp_connection_id` COPIES its fields onto the company on save, so CompanyMailSettings' fully-configured-only rule keeps guarding invoices.
- The SMTP Connections page reuses the integrations CRUD via `return_to=connections` (redirect + hidden input in create/edit forms).
- Integrations CRUD form fields are namespaced `fields[...]` in POSTs; the whole `user.integrations.*` area is behind the coming-soon feature gate until a connected app is platform-configured (tests: `PlatformServiceSettings::setConnectedApp*('salesforce', ...)`).
- Test-send recipients are restricted to the owner's account email / connection from-address (anti-relay), same idea as billing `allowsTestRecipient`.
