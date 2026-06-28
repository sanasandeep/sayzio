---
name: Credit-note two mint paths
description: Both refund paths must share the locked per-FY "CN" counter, never an id-keyed number.
---

Credit notes are minted from TWO places and both must use the SAME numbering:
- Subscription/gateway refunds: RefundService::finalizeSucceeded -> CreditNoteService::issue($refund).
- Client-invoice refunds: ClientInvoiceService::refund() -> CreditNoteService::issue($refund).

**Rule:** credit-note numbers come from the row-locked, gap-free per-(financial_year, prefix='CN') counter in `invoice_counters` (same pattern as invoices/receipts). `credit_notes.number` has a UNIQUE constraint; the create-migration docstring states "Numbering shares invoice_counters with prefix 'CN'".

**Why:** ClientInvoiceService::refund() previously built 'CN/<fy>/<refund_id>' off the GLOBAL auto-increment refund id. That left gaps (refund ids are not per-FY), emitted UNPADDED numbers diverging from invoices/receipts, and could collide against the UNIQUE number column once a refund id reached the counter pad width (default 5).

**How to apply:** never re-introduce an id-keyed CN number. Any new credit-note mint site must call CreditNoteService::issue(). issue() opens its own (nested) DB::transaction and snapshots invoice_number/reason/billing_address/merchant.
