---
name: Client-invoice refund idempotency
description: How duplicate client-invoice refunds are prevented (double-click / retry / webhook re-delivery)
---

# Client-invoice refund idempotency

`ClientInvoiceService::refund()` is the single funnel for both the web
(`ClientInvoiceController::refund`) and API (`Api\...\BillingController::refundInvoice`)
client-invoice refund surfaces. Duplicate-refund protection lives entirely in the
service so both callers inherit it.

Two guards, both inside a `DB::transaction` with `lockForUpdate()` on the invoice
(so concurrent attempts serialize and see each other's committed writes instead of
both passing the balance check):
1. **Explicit idempotency key** — callers pass `?string $idempotencyKey` (web
   hidden field / API body `idempotency_key` OR the `Idempotency-Key` header). A
   repeat with the same key returns the ORIGINAL Refund untouched. Backed by a
   UNIQUE `(invoice_id, idempotency_key)` index as a race backstop. Postgres treats
   NULL keys as distinct, so legacy/unkeyed refunds are unaffected.
2. **Short dedupe window** (no key supplied, e.g. plain web double-submit) — an
   identical succeeded refund (same amount + reason + `user_initiated`) created
   within `config('billing.refund.dedupe_seconds')` (default 10) collapses to a
   no-op returning that prior refund.

**Why:** a double-click, impatient retry, or webhook re-delivery previously created
a second Refund AND a second CreditNote (the reversing ledger entry via
`CreditNoteService::issue`), over-refunding the client.

**How to apply:** a refund creates TWO rows in lockstep — Refund + CreditNote — so
any new refund path must go through this service, not mint either row directly.
Note `refunds` also has a separate UNIQUE `(gateway, gateway_ref)`.

Subscription refunds go through a SEPARATE `RefundService::issue()` (web
`User\BillingController::refundInvoice` + admin `Admin\RefundController::store`),
not `ClientInvoiceService`. It now carries the SAME two-guard shape (explicit
`opts['idempotency_key']` returning the prior Refund via the shared UNIQUE
`(invoice_id, idempotency_key)` index, plus the `billing.refund.dedupe_seconds`
window) inside its existing invoice row lock. Difference vs client-invoice: the
dedupe window matches refunds in status IN ('pending','succeeded') because
offline/manual refunds sit in `pending` until an admin `confirmManual()`s them —
matching only 'succeeded' would miss a double-click on an offline refund. On a
duplicate hit `issue()` returns the original refund WITHOUT re-running the gateway
adapter or the post-success pipeline. Web form passes a stable per-render hidden
`idempotency_key`; controller also honours the `Idempotency-Key` header.
