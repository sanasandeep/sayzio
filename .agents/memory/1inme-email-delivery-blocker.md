---
name: 1inme email delivery requires a real SMTP credential
description: Why outbound email "fails" in the 1inme dev/shared environment and what actually fixes it (provider-agnostic).
---

# 1inme outbound email delivery blocker

The `Emailer` closure `use()` bug (missing `$key`) that made every send throw is already
fixed — that is NOT the cause of ongoing "failed" `email_logs` rows.

Root cause of failures in the dev/shared environment: **no SMTP password/credential is
stored anywhere.** `app_settings` has `mail.*` configured (was SendGrid: host
`smtp.sendgrid.net`, port 587, tls, username `apikey`) but `mail.password_enc` is empty
AND env `MAIL_PASSWORD` is unset, so SMTP AUTH fails for every message.

**Why:** delivery fundamentally needs a valid mailbox login; no first-class Replit
integration exists for SendGrid/Zoho, so the credential must come from the user.

**How to apply:**
- The reliable place to put the credential is the DB (`MailSettings::setPassword()`,
  encrypted in `app_settings`), NOT an env var — the app runs under `php artisan serve`
  whose php -S child strips env vars except `ServeCommand::$passthroughVariables`, so an
  env `MAIL_PASSWORD` may never reach the running server (see artisan-serve-env-passthrough).
- The admin Email/SMTP screen surfaces this gap: `MailSettings::isMissingSmtpCredential()`
  (mailer=smtp + no admin-or-env password) drives an amber warning banner on
  `admin/mail-settings/index.blade.php`, so the misconfiguration is visible, not silent.
- Zoho specifics (a common provider choice here): host `smtp.zoho.com` (or `.in`/`.eu`
  by region), port 587 TLS (or 465 SSL), username = full mailbox address, password = an
  app-specific password if 2FA is on. Zoho requires the `From` address to match the
  authenticated mailbox/alias, so set `mail.from_address` to the Zoho username.
- Verify with `MailSettings::verifyConnection()` (handshake+auth, no send), then send a
  real test via the admin sendTest path and confirm the `email_logs` row is `sent`.
- Changing `app_settings` mail.* touches the SHARED RDS, so it affects prod too — intended
  here (platform-wide transport) but be deliberate.
