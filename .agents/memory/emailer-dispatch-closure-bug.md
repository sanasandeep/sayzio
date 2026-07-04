---
name: Emailer dispatch closure bug
description: A missing use($key) on Emailer::dispatch()'s retry closure silently broke every outbound email platform-wide.
---

`app/Modules/Common/Services/Emailer.php::dispatch()` builds a retry closure that referenced `$key` (the email registry key) without capturing it in the closure's `use(...)` list. PHP closures do not implicitly capture outer-scope variables, so every call threw "Undefined variable $key" inside the closure.

**Impact:** this was not a scoped bug — `Emailer::send()` is the single centralized pipeline for ALL outbound transactional/marketing mail (see centralized-email-pipeline.md). The bug silently failed every email type platform-wide, with the exception swallowed by Emailer's own error handling (see mailfake-raw-noop.md — Emailer::send never re-throws transport failures), so nothing surfaced in normal usage. It was only caught while verifying a new feature's email-delivery leg by inspecting `email_logs` and seeing zero successful sends.

**Fix:** add `$key` to the closure's `use()` clause.

**How to apply:** if you're debugging "email never arrives" with no thrown exception visible anywhere in the request/job cycle, check `email_logs` directly for a status/error message, and check the `use()` capture list of any closures inside `Emailer::dispatch()` — a swallowed closure error looks identical to a silent no-op from the caller's perspective.
