---
name: MailFake::raw is a no-op
description: Mail::fake() does not record Mail::raw() sends, so email-count assertions on raw mail always fail.
---

In Laravel's `MailFake`, the `raw($text, $callback)` method is an empty stub —
it records nothing. Commands/services that send via `Mail::raw(...)` (the ops
alert pattern used by `db:check-pending-migrations`,
`templates:check-design-health`, mail-settings test email, etc.) cannot be
asserted with `Mail::assertSent(...)` / `Mail::assertSentCount(...)`.

**Why:** those assertions inspect the recorded-mailables list, which `raw()`
never appends to. `Mail::assertNothingSent()` still passes trivially (also
because nothing is recorded), so it gives no real signal either.

**How to apply:** for raw-mail code paths, assert on the *other* reliable
signal instead — the in-app `UserNotification` rows the same command writes,
or the `app_settings` episode/cooldown state. Keep `Mail::fake()` only to stop
real delivery during the test. If you must assert the email itself, refactor
the sender to a real Mailable class.
