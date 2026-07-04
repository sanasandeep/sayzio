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

Out of scope: subscription refunds go through a different `RefundService` (User-module
`BillingController::refundInvoice`), not this service.
