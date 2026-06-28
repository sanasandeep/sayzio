---
name: Emailer swallows transport errors
description: Emailer::send never propagates a mail transport failure; how to test email-failure recovery paths
---

# Emailer swallows transport errors

`Emailer::dispatch()` wraps `Mail::html`/`Mail::raw` in a `catch (\Throwable)` that
logs a `failed` email_logs row and returns — it never re-throws. `writeLog()` and the
`view`-render branch in `resolveBody()` are likewise guarded. So
`Emailer::send(...)` (and `sendMailable`) **cannot propagate a mail transport
failure** to its caller.

**Why it matters:** code like `ClientInvoiceService::markSent()` is written to
"deliver first, stamp `sent_at` only on success" assuming the send throws on
failure. With the real Emailer a transport failure is swallowed, so `markSent`
proceeds to stamp anyway. The API `sendInvoice` 502 + `pay_url` recovery branch is
therefore only reachable when `markSent` throws for a NON-transport reason (e.g. the
final `save()`, or a programming/Error before/around the protected block).

**How to test the recovery / no-stamp contract:** do NOT
`Mail::shouldReceive('html')->andThrow()` — dispatch eats it and the test sees a
successful send. Instead bind a service double whose method throws:
`$this->app->instance(ClientInvoiceService::class, Mockery::mock(...)->shouldReceive('markSent')->andThrow(...))`,
then assert the controller's 502 envelope + that the DB row is untouched. For the
success path use `Mail::fake()` and the real service (MailFake's `raw` is a no-op and
`html` is swallowed if unsupported — either way `sent_at` still stamps).
See `tests/Feature/ClientInvoiceSendFailureApiTest.php`.
