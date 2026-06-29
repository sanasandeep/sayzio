---
name: Per-company SMTP + client email templates
description: How creator BillingCompany-scoped accounting email SMTP & template overrides layer onto the platform mail/template system without touching global email.
---

# Per-company (creator) client-facing accounting email customization

A creator's BillingCompany can send its **client-facing** accounting emails from
its own SMTP and customise their subject/body, scoped to that company. It mirrors
the admin `MailSettings` / `EmailTemplateSettings` pattern, one layer below it.

## Transport fallback rule
`CompanyMailSettings::emailOpts()` returns transport_label `'system'` (i.e. use
platform MailSettings) when SMTP is **off OR enabled-but-unconfigured** (e.g. no
host). Only a *fully configured* company yields `'company:{id}'` with per-send
`mailer`/`mailer_config`/`from`.
**Why:** an enabled-but-half-filled SMTP must never silently drop a client
invoice — it falls back to the platform sender, not an error.
**How to apply:** never gate the company-transport branch on the `smtp_enabled`
flag alone; require the connection fields too. Test the half-configured case.

## Template override precedence
Company override > admin/global override > registry default. Emailer takes a
`template_override` opt that wins over the admin `EmailTemplateSettings::get()`.
Reset removes only the company row.

## How send-time routing/precedence is verified (don't duplicate)
End-to-end proof lives in `CompanyInvoiceEmailTransportTest` (feature) +
`CompanyMailSettingsTransportTest` (unit, in-memory). Assert on the **email_logs
row** the Emailer always writes — `meta.transport` is `'company:{id}'` vs
`'system'`, and `body`/`meta.from` prove the template/sender — NOT on `Mail::fake`
(it doesn't record `raw()`/`html()`). To prove the *actual server* (not just the
label) is wired, read `config('mail.mailers.company_smtp_{id}')` after a real
`markSent()`: `dispatch()` registers it from `mailerConfig()` before sending, so
host/port/scheme/username/decrypted-password are asserted deterministically
regardless of the fake.
**How to apply:** a future "confirm client emails send from the right company
server" need is already covered; extend these files rather than adding a new one.

## Transport routing is BROADER than template editing
Two separate concerns:
- **SMTP transport** routes *every* client-facing accounting send through the
  company. Invoice/receipt/recurring resolve the company from the invoice's
  `billing_company_id` (recurring inherits it on generation → `markSent`).
  The **client-portal invite** has NO billing_company_id (portal/vault_client
  are workspace-scoped, not company-scoped), so it resolves
  `CompanyMailSettings::forWorkspaceDefault($workspaceId)` (default else first
  company) and uses `sendRaw()` — which falls back to the platform mailer when
  no company / SMTP unconfigured. Both web + Api `ClientPortalController::sendLink`.
- **Template editing** is gated to EXISTING client-facing *registry* keys only:
  `CompanyEmailTemplateSettings::KEYS` = `billing.client_invoice`,
  `billing.receipt`. Excluded: `billing.refund_issued` (recipient is the
  **owner**), payment-reminder (no registry template / no send path),
  client-portal-invite (sent via `Mail::raw`, not a registry template).
**Why:** routing transport reuses an existing send (not a new email type), so it
is in scope; but exposing an *editable template* for a non-registry send = adding
a new email type = out of scope.
**How to apply:** to route a new client send's transport, resolve its company
(invoice link, else workspace default) and use `emailOpts`/`sendRaw`. To make a
send's template editable, the key must first be registry-backed AND client-facing;
then add it to KEYS.
