---
name: Centralized email pipeline
description: How all 1inme outbound email is templated, logged, previewed and resent — the surfaces that must stay in lockstep.
---

# Centralized email pipeline (1inme Laravel)

All outbound transactional email flows through `Emailer` (Common\Services\Emailer):
`send($key,$to,$tokens,$opts)` / `sendMailable(...)` / `resend(EmailLog)` / `preview($key,$draft?)`.

- Each email type is a **key** enumerated in `EmailTemplateRegistry` (key, category, label,
  description, format html|text, body_type view|inline|mailable|dynamic, default view/body,
  subject token-template, variables token=>{label,sample}). `byCategory()`, `categoryLabel()`,
  `CATEGORY_LABELS`, `sampleTokens()`, `get()` are the helpers UIs use.
- **Overrides** live in `app_settings` under `email_tpl.{key}` via `EmailTemplateSettings`
  (get/put/forget/hasOverride) — mirrors the MailSettings pattern. No override => the registry
  default renders, so content is identical to pre-task (no regressions).
- **Every send is logged** to `email_logs` by the Emailer (tags `X-Sayzio-Logged` header) plus a
  catch-all `LogOutboundEmail` listener on MessageSent for any send not already logged.
- **Resend** re-sends the stored subject/body/format/recipient and writes a NEW log row with
  `meta.resent_from`; throttled via RateLimiter (admin 30/60s, user 5/60s).

**Why:** task required one editable, observable, resendable funnel across ~70 emails without
changing the actual content when unconfigured.

**How to apply — keep these surfaces in lockstep when adding/changing an email type:**
- Register the key in `EmailTemplateRegistry` (with variables+samples) or it won't appear in the
  admin editor / preview and won't get a friendly category.
- Send via `Emailer::send`/`sendMailable` (not raw Mail::) so it's logged + override-aware.
- Web UI: Admin\EmailTemplateController + EmailLogController, User\EmailHistoryController;
  routes in routes/modules/{admin,user}.php; sidebar links in admin & user layouts (user nav is
  DUAL — desktop aside + mobile drawer).
- API parity: Api\{EmailTemplateController,EmailLogController,EmailHistoryController} + routes in
  routes/api.php (admin under /admin/email-*, user under /me/emails). All admin endpoints gate on
  `settings.manage`; user resend is self-scoped (user_id + recipient email match) and limited to
  `EmailHistoryController::RESENDABLE_KEYS` (invoices/receipts/verification).
- `preview()` returns `{subject,body,format}`; view-body templates have empty `entry['body']`, so
  editors prefill from the rendered preview.

**Retention (so email_logs can't grow forever):** `EmailLogRetentionPolicy` + the daily
`email-logs:prune-history` command. Two tiers, both AppSetting-windowed + chunked/capped:
null heavy bodies past `email_logs.body_retention_days`, then delete whole rows past
`email_logs.retention_days`. Plus a write-time `EmailLog::capBody()` (`email_logs.max_body_bytes`)
called by BOTH writers (Emailer::writeLog + LogOutboundEmail listener).
**Deviates from the stats-retention pattern on purpose:** stats prune is no-op when unconfigured
(user-entitled paid history), but email logs are operational, so the windows DEFAULT to bounded
values (365d rows / 90d bodies / 256KiB body) — bounded out of the box. Safety kept: 30-day floor,
`-1` per window = keep-forever no-op for that tier.
