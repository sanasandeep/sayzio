---
name: e2e slow-redirect click auto-wait
description: 1inme browser e2e — form-submit clicks on SLOW server round-trips time out on click()'s 30s navigation auto-wait even inside Promise.all([waitForURL, click])
---

# Slow redirect/error-path clicks time out on click() navigation auto-wait

In 1inme Playwright specs (run over the distant RDS), a `type="submit"` button
click that triggers a navigation still auto-waits for that navigation to finish,
capped at the **click action timeout (default 30s)** — even when wrapped in
`Promise.all([page.waitForURL(..., {timeout: 120_000}), btn.click()])`. The
`waitForURL` 120s budget does NOT extend the click's own post-action navigation
wait.

**Why:** Happy-path clicks land on warmed pages (<30s) so they pass. But
error/recovery paths are slower — e.g. a controller catch that does
`report($e)` then 302-redirects back to a cold, un-warmed page — and the full
round-trip exceeds 30s, so `click()` fails with
`locator.click: Timeout 30000ms exceeded ... waiting for scheduled navigations
to finish` even though the click itself fired ("click action done").

**How to apply:** For any e2e click that submits a form and triggers a slow
redirect (error paths, recovery paths, cold-render targets):
- `btn.click({ noWaitAfter: true })` so click returns immediately, and
- `page.waitForURL(..., { timeout: 120_000, waitUntil: "commit" })` so the URL
  assertion resolves on commit (not full load) with the generous budget.
- Give the follow-up on-page assertion (e.g. the flash error text) a generous
  timeout (~60s) because the redirected-to page is a cold render.
- Flash/error copy can render in more than one node → scope text locators with
  `.first()` to avoid strict-mode "resolved to 2 elements".

Verify a single slow spec fast via `setValidationCommand` +
`startValidationRun(["<name>"])` running `run-validation.sh <one>.spec.ts`
(boots its own server) — never the 120s-capped bash path, and never the full
108-test `e2e` gate.
